<?php

/**
 * @file classes/services/DoajPaHistoryDeletionService.inc.php
 *
 * @class DoajPaHistoryDeletionService
 * @brief Deleting assessment history on request (1.2.0.0, product-owner decision 2026-11-13; revises the retention
 *  rule R1.1 "no purge" to: no AUTOMATIC purge; a Journal Manager may delete runs explicitly).
 *
 *  - One run can be deleted when it is not active and no later run refers to it as its previous run (a sealed later
 *    run records that reference in its snapshot, so the newest runs are deleted first). "Delete all" deletes the
 *    runs of the journal from the newest to the oldest.
 *  - Everything of the run goes: results, evidence, pages read, form answers, finding observations and transitions,
 *    report files and the private crawl files. Findings seen only in that run are deleted with their corrective
 *    actions; findings seen in other runs fall back to their latest remaining observation.
 *  - The audit log is kept and records each deletion (run_deleted).
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAuditService.inc.php');

class DoajPaHistoryDeletionService {

	/** @return array run_id => true for the runs of a journal that can be deleted now */
	public static function deletableRuns($contextId) {
		$runs = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))->get(array('run_id', 'status', 'previous_run_id'))->all();
		$referenced = array();
		foreach ($runs as $r) if ($r->previous_run_id !== null) $referenced[(int) $r->previous_run_id] = true;
		$out = array();
		foreach ($runs as $r) if (!in_array($r->status, DoajPaSchema::RUN_ACTIVE, true) && !isset($referenced[(int) $r->run_id])) $out[(int) $r->run_id] = true;
		return $out;
	}

	/**
	 * Delete one run.
	 * @throws DoajPaNotFoundException|DoajPaStateException
	 */
	public static function deleteRun($contextId, $runId, $actor, $filesDir) {
		$contextId = DoajPaDb::id($contextId, 'context');
		$runId = DoajPaDb::id($runId, 'run');
		$runNumber = DoajPaDb::transaction(function () use ($contextId, $runId, $actor) {
			$run = DoajPaDb::table(DoajPaSchema::T_RUN)->where('run_id', '=', $runId)->where('context_id', '=', $contextId)->lockForUpdate()->first();
			if (!$run) throw new DoajPaNotFoundException('Assessment run not found.');
			if (in_array($run->status, DoajPaSchema::RUN_ACTIVE, true)) throw new DoajPaStateException('An active run cannot be deleted; cancel it first.');
			if (DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $contextId)->where('previous_run_id', '=', $runId)->exists()) {
				throw new DoajPaStateException('A later run builds on this run; delete the later runs first.');
			}
			$t = function ($table) use ($contextId) { return DoajPaDb::table($table)->where('context_id', '=', $contextId); };
			$findingIds = array_map('intval', $t(DoajPaSchema::T_OBSERVATION)->where('run_id', '=', $runId)->pluck('finding_id')->all());
			$findingIds = array_values(array_unique(array_merge($findingIds,
				array_map('intval', $t(DoajPaSchema::T_TRANSITION)->where('run_id', '=', $runId)->pluck('finding_id')->all()),
				array_map('intval', $t(DoajPaSchema::T_FINDING)->where(function ($q) use ($runId) { $q->where('first_run_id', '=', $runId)->orWhere('last_run_id', '=', $runId); })->pluck('finding_id')->all()))));
			$t(DoajPaSchema::T_OBSERVATION)->where('run_id', '=', $runId)->delete();
			$t(DoajPaSchema::T_TRANSITION)->where('run_id', '=', $runId)->delete();
			foreach ($findingIds as $fid) self::refreshFinding($contextId, $fid, $runId);
			$t(DoajPaSchema::T_ACTION)->where('run_id', '=', $runId)->update(array('run_id' => null));
			$t(DoajPaSchema::T_FORM_ANSWER)->where('run_id', '=', $runId)->delete();
			$t(DoajPaSchema::T_RESULT)->where('run_id', '=', $runId)->delete();
			$t(DoajPaSchema::T_EXPORT)->where('run_id', '=', $runId)->delete();
			$t(DoajPaSchema::T_EVIDENCE)->where('run_id', '=', $runId)->delete();
			$t(DoajPaSchema::T_RESOURCE)->where('run_id', '=', $runId)->delete();
			$t(DoajPaSchema::T_ACK)->where('run_id', '=', $runId)->delete();
			$t(DoajPaSchema::T_RUN)->where('run_id', '=', $runId)->delete();
			DoajPaAuditService::record($contextId, 'run_deleted', $actor, null, 'run', $runId,
				array('runNumber' => (int) $run->run_number, 'status' => (string) $run->status, 'findingsAffected' => count($findingIds)));
			return (int) $run->run_number;
		});
		if (is_string($filesDir) && $filesDir !== '') {
			$base = rtrim($filesDir, '/') . '/doajPreAssessment/' . $contextId;
			self::removeTree($base . '/crawl/' . $runId);
			foreach ((array) glob($base . '/crawl/' . $runId . '.stale-*') as $stale) if (is_string($stale)) self::removeTree($stale);
			self::removeTree($base . '/reports/' . $runId);
		}
		return $runNumber;
	}

	/** Delete every run of the journal, newest first. @return int number of runs deleted */
	public static function deleteAll($contextId, $actor, $filesDir) {
		$contextId = DoajPaDb::id($contextId, 'context');
		$runs = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $contextId)->get(array('run_id', 'status'))->all();
		foreach ($runs as $r) if (in_array($r->status, DoajPaSchema::RUN_ACTIVE, true)) throw new DoajPaStateException('An assessment is still active; cancel it first.');
		$deleted = 0;
		for ($guard = 0; $guard < 10000; $guard++) {
			$ids = array_keys(self::deletableRuns($contextId));
			if (!$ids) break;
			rsort($ids);
			self::deleteRun($contextId, $ids[0], $actor, $filesDir);
			$deleted++;
		}
		return $deleted;
	}

	/** A finding loses the deleted run: delete it if nothing remains, otherwise fall back to its latest observation. */
	private static function refreshFinding($contextId, $findingId, $deletedRunId) {
		$obs = DoajPaDb::table(DoajPaSchema::T_OBSERVATION)->where('context_id', '=', $contextId)->where('finding_id', '=', $findingId)
			->orderBy('run_id', 'desc')->get()->all();
		if (!$obs) {
			DoajPaDb::table(DoajPaSchema::T_ACTION)->where('context_id', '=', $contextId)->where('finding_id', '=', $findingId)->delete();
			DoajPaDb::table(DoajPaSchema::T_TRANSITION)->where('context_id', '=', $contextId)->where('finding_id', '=', $findingId)->delete();
			DoajPaDb::table(DoajPaSchema::T_FINDING)->where('context_id', '=', $contextId)->where('finding_id', '=', $findingId)->delete();
			return;
		}
		$latest = $obs[0]; $earliest = end($obs);
		$resolved = DoajPaDb::table(DoajPaSchema::T_TRANSITION)->where('context_id', '=', $contextId)->where('finding_id', '=', $findingId)
			->where('to_status', '=', 'resolved_verified')->exists();
		DoajPaDb::table(DoajPaSchema::T_FINDING)->where('context_id', '=', $contextId)->where('finding_id', '=', $findingId)->update(array(
			'first_run_id' => (int) $earliest->run_id, 'last_run_id' => (int) $latest->run_id, 'current_status' => (string) $latest->lifecycle_status,
			'was_resolved' => $resolved, 'updated_at_utc' => DoajPaDb::now()));
	}

	/** Remove a private plugin directory (only below {files_dir}/doajPreAssessment). */
	private static function removeTree($dir) {
		if (!is_dir($dir) || strpos(realpath($dir), '/doajPreAssessment/') === false || is_link($dir)) return;
		foreach (scandir($dir) as $f) {
			if ($f === '.' || $f === '..') continue;
			$p = $dir . '/' . $f;
			if (is_dir($p) && !is_link($p)) self::removeTree($p); else @unlink($p);
		}
		@rmdir($dir);
	}
}
