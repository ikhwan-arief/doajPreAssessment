<?php

/**
 * @file classes/services/DoajPaHistoryDeletionService.inc.php
 *
 * @class DoajPaHistoryDeletionService
 * @brief Deleting assessment history on request (1.2.0.0, product-owner decision 2026-11-13; revises the retention
 *  rule R1.1 "no purge" to: no AUTOMATIC purge; a Journal Manager may delete runs explicitly).
 *
 *  - Any run that is not active can be deleted, in any order. When a later run refers to it as its previous run
 *    (that reference is part of the later run's sealed record), the deleted run keeps an empty, hidden placeholder
 *    row (status "deleted", no content) so the later record stays intact; the placeholder disappears as soon as no
 *    run refers to it any more. "Delete all" deletes every run of the journal.
 *  - Everything of the run goes: results, evidence, pages read, form answers, finding observations and transitions,
 *    report files and the private crawl files. Findings seen only in that run are deleted with their corrective
 *    actions; findings seen in other runs fall back to their latest remaining observation.
 *  - The audit log is kept and records each deletion (run_deleted).
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAuditService.inc.php');

class DoajPaHistoryDeletionService {

	const DELETED = 'deleted';

	/** @return array run_id => true for the runs of a journal that can be deleted now (every visible, inactive run) */
	public static function deletableRuns($contextId) {
		$runs = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))->get(array('run_id', 'status'))->all();
		$out = array();
		foreach ($runs as $r) if ($r->status !== self::DELETED && !in_array($r->status, DoajPaSchema::RUN_ACTIVE, true)) $out[(int) $r->run_id] = true;
		return $out;
	}

	/**
	 * Delete several runs (newest first, so placeholders are needed only where a kept run refers to a deleted one).
	 * @return array run numbers deleted
	 */
	public static function deleteRuns($contextId, $runIds, $actor, $filesDir) {
		$ids = array_values(array_unique(array_map(function ($id) { return DoajPaDb::id($id, 'run'); }, $runIds)));
		rsort($ids);
		$numbers = array();
		foreach ($ids as $id) $numbers[] = self::deleteRun($contextId, $id, $actor, $filesDir);
		return $numbers;
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
			if (!$run || $run->status === self::DELETED) throw new DoajPaNotFoundException('Assessment run not found.');
			if (in_array($run->status, DoajPaSchema::RUN_ACTIVE, true)) throw new DoajPaStateException('An active run cannot be deleted; cancel it first.');
			$referenced = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $contextId)->where('previous_run_id', '=', $runId)->exists();
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
			if ($referenced) {
				// placeholder: a later sealed run records this run as its previous run
				$t(DoajPaSchema::T_RUN)->where('run_id', '=', $runId)->update(array('status' => self::DELETED, 'coverage' => null, 'result_counts' => null,
					'source_scope' => '{}', 'snapshot_digest' => null, 'updated_at_utc' => DoajPaDb::now()));
			} else {
				$t(DoajPaSchema::T_RUN)->where('run_id', '=', $runId)->delete();
				self::dropUnreferencedPlaceholders($contextId);
			}
			DoajPaAuditService::record($contextId, 'run_deleted', $actor, null, 'run', $runId,
				array('runNumber' => (int) $run->run_number, 'status' => (string) $run->status, 'findingsAffected' => count($findingIds), 'placeholderKept' => $referenced));
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

	/** Delete every run of the journal. @return int number of runs deleted */
	public static function deleteAll($contextId, $actor, $filesDir) {
		$contextId = DoajPaDb::id($contextId, 'context');
		$runs = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $contextId)->get(array('run_id', 'status'))->all();
		foreach ($runs as $r) if (in_array($r->status, DoajPaSchema::RUN_ACTIVE, true)) throw new DoajPaStateException('An assessment is still active; cancel it first.');
		$n = count(self::deleteRuns($contextId, array_keys(self::deletableRuns($contextId)), $actor, $filesDir));
		self::dropUnreferencedPlaceholders($contextId);
		return $n;
	}

	/** Remove placeholder rows that no run refers to any more (repeated, because removing one can free another). */
	private static function dropUnreferencedPlaceholders($contextId) {
		for ($guard = 0; $guard < 10000; $guard++) {
			$referenced = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $contextId)->whereNotNull('previous_run_id')->pluck('previous_run_id')->all();
			$q = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $contextId)->where('status', '=', self::DELETED);
			if ($referenced) $q->whereNotIn('run_id', array_map('intval', $referenced));
			$ids = array_map('intval', $q->pluck('run_id')->all());
			if (!$ids) return;
			// a placeholder can still be a finding's first/last run only if the finding was refreshed elsewhere; keep those
			$used = array_map('intval', array_merge(
				DoajPaDb::table(DoajPaSchema::T_FINDING)->where('context_id', '=', $contextId)->whereIn('first_run_id', $ids)->pluck('first_run_id')->all(),
				DoajPaDb::table(DoajPaSchema::T_FINDING)->where('context_id', '=', $contextId)->whereIn('last_run_id', $ids)->pluck('last_run_id')->all()));
			$ids = array_values(array_diff($ids, $used));
			if (!$ids) return;
			DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $contextId)->whereIn('run_id', $ids)->delete();
		}
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
