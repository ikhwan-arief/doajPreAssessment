<?php

/**
 * @file classes/services/DoajPaAssessmentRunService.inc.php
 *
 * @class DoajPaAssessmentRunService
 * @brief Assessment runs and their evidence, results, form answers and finding history
 *  (Stage 01 F5, F6, F10; Stage 02.04).
 *
 * Rules enforced here:
 *  - every read and write is scoped to one journal context; other journals' records are "not found";
 *  - a run is created only with the per-run English-only acknowledgement (PROD-05), and at most one run
 *    per journal is active at a time, so each run has an unambiguous baseline;
 *  - child records can be added only while the run is running or in human review;
 *  - completion records one observation per non-superseded finding, appends transitions, and seals the
 *    run with a SHA-256 snapshot digest. A completed run is never modified again;
 *  - all multi-row writes, and their audit events, happen in one transaction.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use Illuminate\Database\Capsule\Manager as Capsule;

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/../history/DoajPaRunStateMachine.inc.php');
require_once(dirname(__FILE__) . '/../history/DoajPaLifecycle.inc.php');
require_once(dirname(__FILE__) . '/../history/DoajPaFindingKey.inc.php');
require_once(dirname(__FILE__) . '/../gate/DoajPaEnglishGate.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAuditService.inc.php');

class DoajPaAssessmentRunService {

	//
	// Runs
	//

	/**
	 * Create a draft run together with its English-only acknowledgement.
	 * @param $contextId int
	 * @param $actor array DoajPaDb::userActor()
	 * @param $acknowledgement array ['confirmed' => true, 'warningSha256' => string]
	 * @param $options array rulebookVersion, extractionVersion, sourceScope (array), selectedPeriod (optional)
	 * @return int run_id
	 */
	public static function createDraftRun($contextId, $actor, $acknowledgement, $options) {
		DoajPaDb::id($contextId, 'context');
		if ($actor['type'] !== 'user') throw new DoajPaValidationException('A run must be created by a user.');
		if (!is_array($acknowledgement) || !isset($acknowledgement['confirmed'], $acknowledgement['warningSha256'])
			|| $acknowledgement['confirmed'] !== true || !is_string($acknowledgement['warningSha256'])
			|| !hash_equals(DoajPaEnglishGate::warningSha256(), $acknowledgement['warningSha256'])) {
			throw new DoajPaValidationException('The English-only acknowledgement is required before each assessment.');
		}
		$rulebook = DoajPaDb::text(isset($options['rulebookVersion']) ? $options['rulebookVersion'] : null, 'rulebook version', 64);
		$extraction = DoajPaDb::text(isset($options['extractionVersion']) ? $options['extractionVersion'] : null, 'extraction version', 64);
		$period = DoajPaDb::text(isset($options['selectedPeriod']) ? $options['selectedPeriod'] : null, 'selected period', 64, true);
		$scope = DoajPaDb::json(isset($options['sourceScope']) && is_array($options['sourceScope']) ? $options['sourceScope'] : array());

		return DoajPaDb::transaction(function () use ($contextId, $actor, $rulebook, $extraction, $period, $scope) {
			// Lock this journal's runs: a concurrent creator waits here, then sees the new active run.
			$runs = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $contextId)
				->lockForUpdate()->get(array('run_id', 'status', 'run_number'))->all();
			$maxNumber = 0;
			foreach ($runs as $run) {
				if (in_array($run->status, DoajPaSchema::RUN_ACTIVE, true)) {
					throw new DoajPaStateException('An assessment is already active for this journal.');
				}
				$maxNumber = max($maxNumber, (int) $run->run_number);
			}
			$now = DoajPaDb::now();
			$runId = (int) DoajPaDb::table(DoajPaSchema::T_RUN)->insertGetId(array(
				'context_id' => $contextId, 'run_number' => $maxNumber + 1, 'status' => 'draft',
				'rulebook_version' => $rulebook, 'extraction_version' => $extraction, 'source_scope' => $scope,
				'selected_period' => $period, 'executor_user_id' => $actor['userId'], 'executor_username' => $actor['username'],
				'dev_exception_used' => false, 'lock_version' => 0, 'created_at_utc' => $now, 'updated_at_utc' => $now,
			));
			DoajPaDb::table(DoajPaSchema::T_ACK)->insert(array(
				'context_id' => $contextId, 'run_id' => $runId, 'ack_type' => 'run', 'user_id' => $actor['userId'],
				'username' => $actor['username'], 'warning_sha256' => DoajPaEnglishGate::warningSha256(),
				'warning_source' => DoajPaEnglishGate::WARNING_SOURCE, 'acknowledged_at_utc' => $now,
			));
			DoajPaAuditService::record($contextId, 'run_created', $actor, $runId, 'run', $runId, array('runNumber' => $maxNumber + 1));
			return $runId;
		});
	}

	/**
	 * @param $contextId int
	 * @param $runId int
	 * @return object Run row
	 * @throws DoajPaNotFoundException
	 */
	public static function getRun($contextId, $runId) {
		$run = DoajPaDb::table(DoajPaSchema::T_RUN)
			->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->where('run_id', '=', DoajPaDb::id($runId, 'run'))
			->first();
		if (!$run) throw new DoajPaNotFoundException('Assessment run not found.');
		return $run;
	}

	/**
	 * @param $contextId int
	 * @return array Runs of the journal, newest first
	 */
	public static function listRuns($contextId) {
		return DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->orderBy('run_number', 'desc')->get()->all();
	}

	/**
	 * Change run status (not to "completed": use complete()). Optimistic lock on lock_version.
	 * @return int New lock_version
	 */
	public static function transition($contextId, $runId, $toStatus, $expectedLockVersion, $actor) {
		DoajPaDb::oneOf($toStatus, DoajPaSchema::RUN_STATUSES, 'run status');
		if ($toStatus === 'completed') throw new DoajPaStateException('Use complete() to complete a run.');
		if (!is_int($expectedLockVersion) || $expectedLockVersion < 0) throw new DoajPaValidationException('Invalid lock version.');
		return DoajPaDb::transaction(function () use ($contextId, $runId, $toStatus, $expectedLockVersion, $actor) {
			$run = self::lockRun($contextId, $runId);
			if ((int) $run->lock_version !== $expectedLockVersion) {
				throw new DoajPaConcurrencyException('The run was changed by someone else; reload and try again.');
			}
			if (!DoajPaRunStateMachine::canTransition($run->status, $toStatus)) {
				throw new DoajPaStateException('A run cannot move from ' . $run->status . ' to ' . $toStatus . '.');
			}
			$now = DoajPaDb::now();
			$changes = array('status' => $toStatus, 'lock_version' => $expectedLockVersion + 1, 'updated_at_utc' => $now);
			if ($toStatus === 'running' && $run->started_at_utc === null) $changes['started_at_utc'] = $now;
			self::updateRunChecked($contextId, $runId, $expectedLockVersion, $changes);
			DoajPaAuditService::record($contextId, 'run_status_changed', $actor, $runId, 'run', $runId,
				array('from' => $run->status, 'to' => $toStatus));
			return $expectedLockVersion + 1;
		});
	}

	//
	// Child records (only while the run is running or in human review)
	//

	/** @return int resource_id */
	public static function addCrawlResource($contextId, $runId, $data) {
		$url = DoajPaDb::text(isset($data['url']) ? $data['url'] : null, 'URL', 2048);
		$row = array(
			'url' => $url,
			'url_sha256' => hash('sha256', DoajPaFindingKey::normalizeUrl($url)),
			'final_url' => DoajPaDb::text(isset($data['finalUrl']) ? $data['finalUrl'] : null, 'final URL', 2048, true),
			'http_status' => isset($data['httpStatus']) ? (int) $data['httpStatus'] : null,
			'media_type' => DoajPaDb::text(isset($data['mediaType']) ? $data['mediaType'] : null, 'media type', 127, true),
			'content_sha256' => self::optionalSha(isset($data['contentSha256']) ? $data['contentSha256'] : null),
			'byte_length' => isset($data['byteLength']) ? (int) $data['byteLength'] : null,
			'acquisition_status' => DoajPaDb::oneOf(isset($data['acquisitionStatus']) ? $data['acquisitionStatus'] : null, DoajPaSchema::ACQUISITION_STATUSES, 'acquisition status'),
			'skip_reason' => DoajPaDb::text(isset($data['skipReason']) ? $data['skipReason'] : null, 'skip reason', 255, true),
			'page_type' => DoajPaDb::text(isset($data['pageType']) ? $data['pageType'] : null, 'page type', 40, true),
			'extraction_version' => DoajPaDb::text(isset($data['extractionVersion']) ? $data['extractionVersion'] : null, 'extraction version', 64),
			'retrieved_at_utc' => DoajPaDb::now(),
		);
		return self::insertChild($contextId, $runId, DoajPaSchema::T_RESOURCE, $row);
	}

	/** @return int evidence_id */
	public static function addEvidence($contextId, $runId, $data) {
		$quote = DoajPaDb::text(isset($data['quote']) ? $data['quote'] : null, 'quote', 1048576);
		$resourceId = isset($data['resourceId']) ? DoajPaDb::id($data['resourceId'], 'resource') : null;
		$row = array(
			'resource_id' => $resourceId,
			'source_url' => DoajPaDb::text(isset($data['sourceUrl']) ? $data['sourceUrl'] : null, 'source URL', 2048),
			'source_title' => DoajPaDb::text(isset($data['sourceTitle']) ? $data['sourceTitle'] : null, 'source title', 255, true),
			'source_type' => DoajPaDb::text(isset($data['sourceType']) ? $data['sourceType'] : null, 'source type', 40),
			'quote' => $quote,
			'quote_sha256' => hash('sha256', $quote),
			'location' => DoajPaDb::text(isset($data['location']) ? $data['location'] : null, 'location', 255),
			'detection_method' => DoajPaDb::text(isset($data['detectionMethod']) ? $data['detectionMethod'] : null, 'detection method', 64),
			'extraction_version' => DoajPaDb::text(isset($data['extractionVersion']) ? $data['extractionVersion'] : null, 'extraction version', 64),
			'retrieved_at_utc' => DoajPaDb::now(),
			'created_at_utc' => DoajPaDb::now(),
		);
		return self::insertChild($contextId, $runId, DoajPaSchema::T_EVIDENCE, $row, function () use ($contextId, $runId, $resourceId) {
			if ($resourceId !== null) self::requireRunChild(DoajPaSchema::T_RESOURCE, 'resource_id', $resourceId, $contextId, $runId);
		});
	}

	/** @return int result_id */
	public static function addCriterionResult($contextId, $runId, $definitionId, $data) {
		DoajPaDb::id($definitionId, 'criterion definition');
		$evidenceId = isset($data['evidenceId']) ? DoajPaDb::id($data['evidenceId'], 'evidence') : null;
		$row = array(
			'definition_id' => $definitionId,
			'machine_status' => DoajPaDb::oneOf(isset($data['machineStatus']) ? $data['machineStatus'] : null, DoajPaSchema::CRITERION_STATUSES, 'criterion status'),
			'rationale' => DoajPaDb::text(isset($data['rationale']) ? $data['rationale'] : null, 'rationale', 65535),
			'interpretation_limit' => DoajPaDb::text(isset($data['interpretationLimit']) ? $data['interpretationLimit'] : null, 'interpretation limit', 65535),
			'evidence_id' => $evidenceId,
			'evaluated_at_utc' => DoajPaDb::now(),
		);
		return self::insertChild($contextId, $runId, DoajPaSchema::T_RESULT, $row, function () use ($contextId, $runId, $evidenceId, $definitionId) {
			if (!DoajPaDb::table(DoajPaSchema::T_DEFINITION)->where('definition_id', '=', $definitionId)->exists()) {
				throw new DoajPaNotFoundException('Criterion definition not found.');
			}
			if ($evidenceId !== null) self::requireRunChild(DoajPaSchema::T_EVIDENCE, 'evidence_id', $evidenceId, $contextId, $runId);
		});
	}

	/** @return int answer_id */
	public static function addFormAnswer($contextId, $runId, $data) {
		$question = isset($data['questionKey']) ? $data['questionKey'] : null;
		if (!is_string($question) || !preg_match(DoajPaSchema::FORM_QUESTION_PATTERN, $question)) throw new DoajPaValidationException('Invalid form question.');
		$evidenceId = isset($data['evidenceId']) ? DoajPaDb::id($data['evidenceId'], 'evidence') : null;
		$row = array(
			'question_key' => $question,
			'answer_status' => DoajPaDb::oneOf(isset($data['answerStatus']) ? $data['answerStatus'] : null, DoajPaSchema::FORM_ANSWER_STATUSES, 'answer status'),
			'answer_text' => DoajPaDb::text(isset($data['answerText']) ? $data['answerText'] : null, 'answer', 65535, true),
			'evidence_id' => $evidenceId,
			'uncertainty' => DoajPaDb::text(isset($data['uncertainty']) ? $data['uncertainty'] : null, 'uncertainty', 65535, true),
			'created_at_utc' => DoajPaDb::now(),
		);
		return self::insertChild($contextId, $runId, DoajPaSchema::T_FORM_ANSWER, $row, function () use ($contextId, $runId, $evidenceId) {
			if ($evidenceId !== null) self::requireRunChild(DoajPaSchema::T_EVIDENCE, 'evidence_id', $evidenceId, $contextId, $runId);
		});
	}

	/**
	 * Store the run's crawl coverage summary (JSON, at most 60,000 bytes) while the run is writable.
	 * Part of the sealed snapshot once the run completes. Does not change lock_version (data, not state).
	 */
	public static function recordCoverage($contextId, $runId, $coverage, $actor) {
		$json = DoajPaDb::json($coverage);
		if (strlen($json) > 60000) throw new DoajPaValidationException('Coverage summary too large.');
		return DoajPaDb::transaction(function () use ($contextId, $runId, $json) {
			$run = self::lockRun($contextId, $runId);
			if (!in_array($run->status, DoajPaSchema::RUN_WRITABLE, true)) throw new DoajPaStateException('Coverage can be recorded only while a run is running or in human review.');
			DoajPaDb::table(DoajPaSchema::T_RUN)->where('run_id', '=', $runId)->where('context_id', '=', $contextId)
				->update(array('coverage' => $json, 'updated_at_utc' => DoajPaDb::now()));
			return true;
		});
	}

	/**
	 * Flag a writable run as having used the development-only network exception (ADR-005 R1.1).
	 */
	public static function markDevExceptionUsed($contextId, $runId) {
		return DoajPaDb::transaction(function () use ($contextId, $runId) {
			$run = self::lockRun($contextId, $runId);
			if (!in_array($run->status, DoajPaSchema::RUN_WRITABLE, true)) throw new DoajPaStateException('Run is not writable.');
			DoajPaDb::table(DoajPaSchema::T_RUN)->where('run_id', '=', $runId)->where('context_id', '=', $contextId)
				->update(array('dev_exception_used' => true));
			return true;
		});
	}

	/** @return boolean Whether a resource with this URL is already stored for the run (crawl resume) */
	public static function hasCrawlResource($contextId, $runId, $url) {
		return DoajPaDb::table(DoajPaSchema::T_RESOURCE)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->where('run_id', '=', DoajPaDb::id($runId, 'run'))
			->where('url_sha256', '=', hash('sha256', DoajPaFindingKey::normalizeUrl($url)))->exists();
	}

	//
	// Completion and finding history
	//

	/**
	 * Complete a running (or human-review) run: track findings and seal the snapshot.
	 * @param $detected array Items: criterionKey, criterionVersion, subtype, targetType, target, criterionStatus, evidenceId
	 * @param $verifiedClear array Items: criterionKey, criterionVersion, subtype, targetType, target, evidenceId
	 * @return array Summary: runId, snapshotDigest, counts
	 */
	public static function complete($contextId, $runId, $expectedLockVersion, $actor, $detected, $verifiedClear) {
		if (!is_int($expectedLockVersion) || $expectedLockVersion < 0) throw new DoajPaValidationException('Invalid lock version.');
		if (!is_array($detected) || !is_array($verifiedClear)) throw new DoajPaValidationException('Invalid finding input.');
		return DoajPaDb::transaction(function () use ($contextId, $runId, $expectedLockVersion, $actor, $detected, $verifiedClear) {
			$run = self::lockRun($contextId, $runId);
			if ((int) $run->lock_version !== $expectedLockVersion) {
				throw new DoajPaConcurrencyException('The run was changed by someone else; reload and try again.');
			}
			if (!DoajPaRunStateMachine::canTransition($run->status, 'completed')) {
				throw new DoajPaStateException('A run cannot move from ' . $run->status . ' to completed.');
			}
			$now = DoajPaDb::now();
			$counts = self::trackFindings($contextId, $runId, $actor, $detected, $verifiedClear, $now);

			$previous = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $contextId)
				->where('status', '=', 'completed')->where('run_id', '<', $runId)->max('run_id');
			self::updateRunChecked($contextId, $runId, $expectedLockVersion, array(
				'status' => 'completed', 'previous_run_id' => $previous === null ? null : (int) $previous,
				'result_counts' => DoajPaDb::json($counts), 'completed_at_utc' => $now, 'updated_at_utc' => $now,
				'lock_version' => $expectedLockVersion + 1,
			));
			$digest = self::computeSnapshotDigest($contextId, $runId);
			DoajPaDb::table(DoajPaSchema::T_RUN)->where('run_id', '=', $runId)->where('context_id', '=', $contextId)
				->update(array('snapshot_digest' => $digest));
			DoajPaAuditService::record($contextId, 'run_completed', $actor, $runId, 'run', $runId,
				array('snapshotDigest' => $digest, 'counts' => $counts));
			return array('runId' => $runId, 'snapshotDigest' => $digest, 'counts' => $counts);
		});
	}

	/**
	 * Recompute a completed run's digest and compare it with the sealed value.
	 * @return boolean
	 */
	public static function verifySnapshot($contextId, $runId) {
		$run = self::getRun($contextId, $runId);
		if ($run->status !== 'completed' || $run->snapshot_digest === null) return false;
		return hash_equals((string) $run->snapshot_digest, self::computeSnapshotDigest($contextId, $runId));
	}

	/**
	 * SHA-256 over the run's immutable fields and all of its child rows, in id order. Every value is
	 * normalized to a string (or null) first, so the digest does not depend on how the database driver
	 * types values (native integers or strings depend on PDO settings such as emulated prepares and on
	 * the driver version). A sealed run must keep verifying after a PHP or driver change; in Stage 02.04
	 * runs sealed on PHP 7.3 verified on PHP 8.2 and vice versa.
	 * @return string
	 */
	public static function computeSnapshotDigest($contextId, $runId) {
		$run = (array) self::getRun($contextId, $runId);
		foreach (array('status', 'lock_version', 'updated_at_utc', 'snapshot_digest') as $mutable) unset($run[$mutable]);
		$snapshot = array('run' => self::normalizeRow($run));
		$children = array(
			DoajPaSchema::T_ACK => 'acknowledgement_id', DoajPaSchema::T_RESOURCE => 'resource_id',
			DoajPaSchema::T_EVIDENCE => 'evidence_id', DoajPaSchema::T_RESULT => 'result_id',
			DoajPaSchema::T_FORM_ANSWER => 'answer_id', DoajPaSchema::T_OBSERVATION => 'observation_id',
			DoajPaSchema::T_TRANSITION => 'transition_id',
		);
		foreach ($children as $table => $pk) {
			$rows = DoajPaDb::table($table)->where('context_id', '=', $contextId)->where('run_id', '=', $runId)->orderBy($pk)->get()->all();
			$snapshot[$table] = array_map(function ($row) { return DoajPaSchemaRowNormalizer::normalize((array) $row); }, $rows);
		}
		return hash('sha256', DoajPaDb::json($snapshot));
	}

	/**
	 * Findings of a journal, with current and original status.
	 * @return array
	 */
	public static function listFindings($contextId) {
		return DoajPaDb::table(DoajPaSchema::T_FINDING)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->orderBy('finding_id')->get()->all();
	}

	/**
	 * Observations of one run (the run's snapshot of finding statuses).
	 * @return array
	 */
	public static function listObservations($contextId, $runId) {
		self::getRun($contextId, $runId);
		return DoajPaDb::table(DoajPaSchema::T_OBSERVATION)->where('context_id', '=', $contextId)
			->where('run_id', '=', $runId)->orderBy('finding_id')->get()->all();
	}

	//
	// Internals
	//

	/** @return array Counts by lifecycle status for this run */
	private static function trackFindings($contextId, $runId, $actor, $detected, $verifiedClear, $now) {
		$results = DoajPaDb::table(DoajPaSchema::T_RESULT . ' as r')
			->join(DoajPaSchema::T_DEFINITION . ' as d', 'd.definition_id', '=', 'r.definition_id')
			->where('r.context_id', '=', $contextId)->where('r.run_id', '=', $runId)
			->get(array('d.criterion_key', 'd.criterion_version', 'd.lineage_version', 'r.machine_status'))->all();
		$evaluated = array();
		foreach ($results as $r) {
			if (isset($evaluated[$r->criterion_key])) throw new DoajPaValidationException('A run cannot evaluate two versions of one criterion.');
			$evaluated[$r->criterion_key] = array('version' => (int) $r->criterion_version, 'lineage' => (int) $r->lineage_version, 'status' => $r->machine_status);
		}

		$detectedByKey = self::indexItems($contextId, $runId, $detected, $evaluated, true);
		$clearByKey = self::indexItems($contextId, $runId, $verifiedClear, $evaluated, false);
		foreach ($clearByKey as $key => $item) {
			if (isset($detectedByKey[$key])) throw new DoajPaValidationException('A finding cannot be both detected and verified clear in one run.');
		}

		$counts = array_fill_keys(DoajPaSchema::LIFECYCLE, 0);
		$existing = DoajPaDb::table(DoajPaSchema::T_FINDING)->where('context_id', '=', $contextId)
			->where('current_status', '!=', 'superseded')->lockForUpdate()->get()->all();
		$existingByKey = array();
		foreach ($existing as $finding) $existingByKey[$finding->finding_key] = $finding;

		foreach ($detectedByKey as $key => $item) {
			if (isset($existingByKey[$key])) continue;
			$findingId = (int) DoajPaDb::table(DoajPaSchema::T_FINDING)->insertGetId(array(
				'context_id' => $contextId, 'finding_key' => $key, 'criterion_key' => $item['criterionKey'],
				'lineage_version' => $item['lineage'], 'subtype' => $item['subtype'], 'target_type' => $item['targetType'],
				'target' => DoajPaFindingKey::normalizeTarget($item['targetType'], $item['target']),
				'first_run_id' => $runId, 'original_status' => 'open', 'current_status' => 'open', 'was_resolved' => false,
				'last_run_id' => $runId, 'lock_version' => 0, 'created_at_utc' => $now, 'updated_at_utc' => $now,
			));
			self::observe($contextId, $runId, $findingId, $item['criterionVersion'], DoajPaLifecycle::DETECTED,
				$item['criterionStatus'], 'open', $item['evidenceId'], $now);
			self::appendTransition($contextId, $runId, $findingId, null, 'open', 'first_detected', $actor, $now);
			$counts['open']++;
		}

		foreach ($existingByKey as $key => $finding) {
			$findingId = (int) $finding->finding_id;
			$family = isset($evaluated[$finding->criterion_key]) ? $evaluated[$finding->criterion_key] : null;
			if (isset($detectedByKey[$key])) {
				$item = $detectedByKey[$key];
				$observation = DoajPaLifecycle::DETECTED; $version = $item['criterionVersion'];
				$criterionStatus = $item['criterionStatus']; $evidenceId = $item['evidenceId']; $reason = 'detected_again';
			} elseif ($family !== null && $family['lineage'] !== (int) $finding->lineage_version) {
				$observation = DoajPaLifecycle::SUPERSEDED; $version = $family['version'];
				$criterionStatus = null; $evidenceId = null; $reason = 'criterion_incompatible_change';
			} elseif ($family !== null && isset($clearByKey[$key])) {
				$item = $clearByKey[$key];
				$observation = DoajPaLifecycle::VERIFIED_CLEAR; $version = $item['criterionVersion'];
				$criterionStatus = $family['status']; $evidenceId = $item['evidenceId']; $reason = 'verified_corrected';
			} else {
				$observation = DoajPaLifecycle::NOT_ESTABLISHED;
				$version = $family !== null ? $family['version'] : self::lastObservedVersion($contextId, $findingId);
				$criterionStatus = $family !== null ? $family['status'] : null; $evidenceId = null;
				$reason = $family !== null ? 'no_equivalent_verification' : 'criterion_not_evaluated';
			}
			$newStatus = DoajPaLifecycle::next($finding->current_status, (bool) $finding->was_resolved, $observation);
			self::observe($contextId, $runId, $findingId, $version, $observation, $criterionStatus, $newStatus, $evidenceId, $now);
			if ($newStatus !== $finding->current_status) {
				self::appendTransition($contextId, $runId, $findingId, $finding->current_status, $newStatus, $reason, $actor, $now);
			}
			$updated = DoajPaDb::table(DoajPaSchema::T_FINDING)->where('finding_id', '=', $findingId)
				->where('context_id', '=', $contextId)->where('lock_version', '=', (int) $finding->lock_version)
				->update(array(
					'current_status' => $newStatus,
					'was_resolved' => DoajPaLifecycle::nextWasResolved((bool) $finding->was_resolved, $observation),
					'last_run_id' => $runId, 'lock_version' => (int) $finding->lock_version + 1, 'updated_at_utc' => $now,
				));
			if ($updated !== 1) throw new DoajPaConcurrencyException('A finding was changed concurrently.');
			$counts[$newStatus]++;
		}
		return $counts;
	}

	/** @return array Items keyed by finding key, with lineage resolved */
	private static function indexItems($contextId, $runId, $items, $evaluated, $isDetection) {
		$byKey = array();
		foreach ($items as $item) {
			if (!is_array($item)) throw new DoajPaValidationException('Invalid finding input.');
			$criterionKey = isset($item['criterionKey']) ? $item['criterionKey'] : null;
			$version = isset($item['criterionVersion']) ? $item['criterionVersion'] : null;
			if (!is_string($criterionKey) || !isset($evaluated[$criterionKey]) || $evaluated[$criterionKey]['version'] !== $version) {
				throw new DoajPaValidationException('Each finding must refer to a criterion result of this run.');
			}
			$evidenceId = isset($item['evidenceId']) ? DoajPaDb::id($item['evidenceId'], 'evidence') : null;
			if ($evidenceId === null) throw new DoajPaValidationException('Each detection and each verification needs evidence.');
			self::requireRunChild(DoajPaSchema::T_EVIDENCE, 'evidence_id', $evidenceId, $contextId, $runId);
			if ($isDetection) {
				DoajPaDb::oneOf(isset($item['criterionStatus']) ? $item['criterionStatus'] : null, DoajPaSchema::FINDING_CRITERION_STATUSES, 'criterion status');
			}
			$targetType = DoajPaDb::oneOf(isset($item['targetType']) ? $item['targetType'] : null, DoajPaSchema::TARGET_TYPES, 'target type');
			$subtype = isset($item['subtype']) ? $item['subtype'] : null;
			$target = isset($item['target']) ? $item['target'] : null;
			try {
				$key = DoajPaFindingKey::build($contextId, $criterionKey, $evaluated[$criterionKey]['lineage'], $subtype, $targetType, $target);
			} catch (InvalidArgumentException $e) {
				throw new DoajPaValidationException($e->getMessage());
			}
			if (isset($byKey[$key])) throw new DoajPaValidationException('Duplicate finding in one run.');
			$byKey[$key] = array(
				'criterionKey' => $criterionKey, 'criterionVersion' => $version, 'lineage' => $evaluated[$criterionKey]['lineage'],
				'subtype' => $subtype, 'targetType' => $targetType, 'target' => $target, 'evidenceId' => $evidenceId,
				'criterionStatus' => $isDetection ? $item['criterionStatus'] : null,
			);
		}
		return $byKey;
	}

	private static function observe($contextId, $runId, $findingId, $version, $observation, $criterionStatus, $lifecycle, $evidenceId, $now) {
		DoajPaDb::table(DoajPaSchema::T_OBSERVATION)->insert(array(
			'context_id' => $contextId, 'finding_id' => $findingId, 'run_id' => $runId, 'criterion_version' => (int) $version,
			'observation' => $observation, 'criterion_status' => $criterionStatus, 'lifecycle_status' => $lifecycle,
			'evidence_id' => $evidenceId, 'observed_at_utc' => $now,
		));
	}

	private static function appendTransition($contextId, $runId, $findingId, $from, $to, $reason, $actor, $now) {
		DoajPaDb::table(DoajPaSchema::T_TRANSITION)->insert(array(
			'context_id' => $contextId, 'finding_id' => $findingId, 'run_id' => $runId, 'from_status' => $from,
			'to_status' => $to, 'reason_code' => $reason, 'actor_type' => $actor['type'],
			'actor_user_id' => $actor['userId'], 'actor_username' => $actor['username'], 'created_at_utc' => $now,
		));
		DoajPaAuditService::record($contextId, 'finding_transition', $actor, $runId, 'finding', $findingId,
			array('from' => $from, 'to' => $to, 'reason' => $reason));
	}

	private static function lastObservedVersion($contextId, $findingId) {
		return (int) DoajPaDb::table(DoajPaSchema::T_OBSERVATION)->where('context_id', '=', $contextId)
			->where('finding_id', '=', $findingId)->orderBy('observation_id', 'desc')->value('criterion_version');
	}

	/** @return object Run row, locked FOR UPDATE */
	private static function lockRun($contextId, $runId) {
		$run = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->where('run_id', '=', DoajPaDb::id($runId, 'run'))->lockForUpdate()->first();
		if (!$run) throw new DoajPaNotFoundException('Assessment run not found.');
		return $run;
	}

	private static function updateRunChecked($contextId, $runId, $expectedLockVersion, $changes) {
		$updated = DoajPaDb::table(DoajPaSchema::T_RUN)->where('run_id', '=', $runId)->where('context_id', '=', $contextId)
			->where('lock_version', '=', $expectedLockVersion)->update($changes);
		if ($updated !== 1) throw new DoajPaConcurrencyException('The run was changed by someone else; reload and try again.');
	}

	/**
	 * Insert a run child row after checking, under a row lock, that the run accepts writes.
	 * @return int New id
	 */
	private static function insertChild($contextId, $runId, $table, $row, $extraChecks = null) {
		DoajPaDb::id($contextId, 'context');
		DoajPaDb::id($runId, 'run');
		return DoajPaDb::transaction(function () use ($contextId, $runId, $table, $row, $extraChecks) {
			$run = self::lockRun($contextId, $runId);
			if (!in_array($run->status, DoajPaSchema::RUN_WRITABLE, true)) {
				throw new DoajPaStateException('Records can be added only while a run is running or in human review.');
			}
			if ($extraChecks) $extraChecks();
			$row['context_id'] = $contextId;
			$row['run_id'] = $runId;
			return (int) DoajPaDb::table($table)->insertGetId($row);
		});
	}

	private static function requireRunChild($table, $pk, $id, $contextId, $runId) {
		$exists = DoajPaDb::table($table)->where($pk, '=', $id)->where('context_id', '=', $contextId)->where('run_id', '=', $runId)->exists();
		if (!$exists) throw new DoajPaNotFoundException('Referenced record not found in this run.');
	}

	private static function optionalSha($value) {
		if ($value === null) return null;
		if (!is_string($value) || !preg_match('/^[0-9a-f]{64}$/', $value)) throw new DoajPaValidationException('Invalid SHA-256.');
		return $value;
	}

	private static function normalizeRow($row) {
		return DoajPaSchemaRowNormalizer::normalize($row);
	}
}

/**
 * Canonical form of a database row for hashing: keys sorted, values as strings or null.
 */
class DoajPaSchemaRowNormalizer {
	/**
	 * @param $row array
	 * @return array
	 */
	public static function normalize($row) {
		ksort($row);
		foreach ($row as $key => $value) {
			if ($value === null) continue;
			if (is_bool($value)) $value = $value ? '1' : '0';
			$row[$key] = (string) $value;
		}
		return $row;
	}
}
