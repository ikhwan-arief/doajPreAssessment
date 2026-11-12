<?php

/**
 * @file classes/services/DoajPaReadService.inc.php
 *
 * @class DoajPaReadService
 * @brief Read-only, journal-scoped queries for the backend views (Stage 02.05). Every method takes the
 *  journal context ID and filters by it; records of other journals are "not found". Historical statuses
 *  come from per-run observations (Stage 01 F6), never from the current finding status alone.
 *  Values are returned unescaped; templates escape every output.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAssessmentRunService.inc.php');
require_once(dirname(__FILE__) . '/DoajPaCorrectiveActionService.inc.php');

class DoajPaReadService {

	/** Display caps (pages say when a list is truncated). */
	const LIST_LIMIT = 500;
	const AUDIT_LIMIT = 100;

	/**
	 * Whether a run's rule set is an approved rule set (criteria approved by the product owner of this plugin).
	 * Runs of any other rule set are development, test or candidate runs and are labelled "not a DOAJ assessment result".
	 * @param $rulebookVersion string
	 * @return boolean
	 */
	public static function isApprovedRulebook($rulebookVersion) {
		return in_array((string) $rulebookVersion, DoajPaSchema::APPROVED_RULEBOOKS, true);
	}

	/**
	 * Question titles come from the mapping a run used: the proposed mapping for candidate runs (D-03-2 pending), the
	 * approved mapping for approved runs; none otherwise.
	 * @return array FQ => title
	 */
	public static function fqTitles($rulebookVersion) {
		$data = null;
		if ($rulebookVersion === DoajPaSchema::CANDIDATE_RULEBOOK) {
			require_once(dirname(__FILE__) . '/../rules/data/DoajPaCandidateDataset.inc.php');
			$data = DoajPaCandidateDataset::data();
		} elseif (self::isApprovedRulebook($rulebookVersion)) {
			require_once(dirname(__FILE__) . '/../rules/data/DoajPaApprovedDataset.inc.php');
			$data = DoajPaApprovedDataset::data();
		}
		$titles = array();
		if ($data) foreach ($data['fqMapping'] as $m) $titles[$m['fq']] = $m['title'];
		return $titles;
	}

	/** @return array Dashboard figures, all from stored records */
	public static function dashboard($contextId) {
		$ctx = DoajPaDb::id($contextId, 'context');
		$runs = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $ctx);
		$out = array(
			'runCount' => (int) (clone $runs)->count(),
			'completedCount' => (int) (clone $runs)->where('status', '=', 'completed')->count(),
			'activeRun' => self::rowArray((clone $runs)->whereIn('status', DoajPaSchema::RUN_ACTIVE)->orderBy('run_id', 'desc')->first()),
			'latestRun' => null, 'latestObservationCounts' => array(), 'latestCoverage' => array(),
			'currentFindingCounts' => self::countBy(DoajPaSchema::T_FINDING, 'current_status', $ctx),
			'actionCounts' => self::countBy(DoajPaSchema::T_ACTION, 'status', $ctx),
		);
		$latest = (clone $runs)->where('status', '=', 'completed')->orderBy('run_id', 'desc')->first();
		if ($latest) {
			$runId = (int) $latest->run_id;
			$out['latestRun'] = self::runSummary($latest, true);
			$out['latestObservationCounts'] = self::countBy(DoajPaSchema::T_OBSERVATION, 'lifecycle_status', $ctx, $runId);
			$out['latestCoverage'] = self::countBy(DoajPaSchema::T_RESOURCE, 'acquisition_status', $ctx, $runId);
		}
		return $out;
	}

	/** @return array Runs of the journal, newest first */
	public static function runs($contextId) {
		$ctx = DoajPaDb::id($contextId, 'context');
		$rows = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $ctx)->orderBy('run_number', 'desc')->limit(self::LIST_LIMIT)->get()->all();
		$out = array();
		foreach ($rows as $row) {
			$summary = self::runSummary($row, false);
			$summary['gapCount'] = (int) DoajPaDb::table(DoajPaSchema::T_RESOURCE)->where('context_id', '=', $ctx)
				->where('run_id', '=', (int) $row->run_id)->where('acquisition_status', '!=', 'retrieved')->count();
			$out[] = $summary;
		}
		return $out;
	}

	/** @return array Run detail with coverage, results, observations and form answers */
	public static function run($contextId, $runId) {
		$ctx = DoajPaDb::id($contextId, 'context');
		$run = DoajPaAssessmentRunService::getRun($ctx, $runId);
		$runId = (int) $run->run_id;
		$detail = self::runSummary($run, true);
		$detail['acknowledgement'] = self::rowArray(DoajPaDb::table(DoajPaSchema::T_ACK)->where('context_id', '=', $ctx)->where('run_id', '=', $runId)->first());
		$detail['previousRunNumber'] = $run->previous_run_id === null ? null
			: DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $ctx)->where('run_id', '=', (int) $run->previous_run_id)->value('run_number');
		$detail['resources'] = self::resources($ctx, $runId);
		$detail['evidence'] = self::evidence($ctx, $runId);
		$detail['results'] = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_RESULT . ' as r')
			->join(DoajPaSchema::T_DEFINITION . ' as d', 'd.definition_id', '=', 'r.definition_id')
			->leftJoin(DoajPaSchema::T_EVIDENCE . ' as e', function ($join) {
				$join->on('e.evidence_id', '=', 'r.evidence_id')->on('e.context_id', '=', 'r.context_id');
			})
			->where('r.context_id', '=', $ctx)->where('r.run_id', '=', $runId)->orderBy('d.criterion_key')
			->get(array('r.result_id', 'r.machine_status', 'r.rationale', 'r.interpretation_limit', 'r.human_status', 'r.human_rationale',
				'r.evaluated_at_utc', 'd.criterion_key', 'd.criterion_version', 'd.level', 'd.title', 'd.dataset_version', 'd.doaj_reference_url',
				'e.evidence_id', 'e.source_url', 'e.retrieved_at_utc'))->all());
		foreach ($detail['results'] as &$result) {
			$result['datasetApproved'] = in_array($result['dataset_version'], DoajPaSchema::APPROVED_DATASETS, true);
			$result['sourceUrlIsLink'] = self::isHttpUrl($result['source_url']);
		}
		unset($result);
		$detail['observations'] = self::observationsForRun($ctx, $runId);
		$detail['formAnswers'] = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_FORM_ANSWER)->where('context_id', '=', $ctx)
			->where('run_id', '=', $runId)->orderBy('question_key')->get()->all());
		$coverage = $run->coverage === null ? null : json_decode((string) $run->coverage, true);
		$detail['coverageData'] = is_array($coverage) ? $coverage : null;
		$detail['assessment'] = is_array($coverage) && isset($coverage['assessment']) && is_array($coverage['assessment']) ? $coverage['assessment'] : null;
		$detail['gapCount'] = (int) DoajPaDb::table(DoajPaSchema::T_RESOURCE)->where('context_id', '=', $ctx)->where('run_id', '=', $runId)
			->where('acquisition_status', '!=', 'retrieved')->count();
		$detail['languageNotVerifiedCount'] = self::languageNotVerifiedCount($ctx, $runId);
		$detail['interruptions'] = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_AUDIT)->where('context_id', '=', $ctx)->where('run_id', '=', $runId)
			->where('event_type', '=', 'run_interrupted')->orderBy('event_id', 'desc')->limit(20)->get(array('created_at_utc', 'details', 'actor_username'))->all());
		foreach ($detail['interruptions'] as &$event) {
			$details = json_decode((string) $event['details'], true);
			$event['reason'] = is_array($details) && isset($details['reason']) ? (string) $details['reason'] : '';
		}
		unset($event);
		return $detail;
	}

	/**
	 * Resources whose text was read but whose language is not verified English: retrieved pages the heuristic
	 * could not classify, mixed-language and other-language pages (Stage 02.07; never treated as English).
	 * @return int
	 */
	public static function languageNotVerifiedCount($contextId, $runId) {
		return (int) DoajPaDb::table(DoajPaSchema::T_RESOURCE)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->where('run_id', '=', DoajPaDb::id($runId, 'run'))->where('skip_reason', 'like', 'language\\_%')->count();
	}

	/** @return array|null The journal's active run (at most one), or null */
	public static function activeRun($contextId) {
		return self::rowArray(DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->whereIn('status', DoajPaSchema::RUN_ACTIVE)->orderBy('run_id', 'desc')->first(array('run_id', 'run_number', 'status', 'lock_version')));
	}

	/**
	 * FQ-01..FQ-20 entries from the latest completed run (every stored answer is "not yet assessed" in this
	 * version); questions without a stored answer are shown as not yet assessed as well. No answer is invented.
	 * @return array run (or null) and entries
	 */
	public static function formGuide($contextId) {
		$ctx = DoajPaDb::id($contextId, 'context');
		$latest = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $ctx)->where('status', '=', 'completed')->orderBy('run_id', 'desc')->first();
		$stored = array();
		if ($latest) {
			foreach (DoajPaDb::table(DoajPaSchema::T_FORM_ANSWER)->where('context_id', '=', $ctx)->where('run_id', '=', (int) $latest->run_id)
				->orderBy('answer_id')->get()->all() as $a) $stored[$a->question_key] = (array) $a;
		}
		$titles = $latest ? self::fqTitles($latest->rulebook_version) : array();
		$sources = array();
		$ids = array_values(array_filter(array_map(function ($a) { return $a['evidence_id']; }, $stored)));
		if ($ids) {
			foreach (DoajPaDb::table(DoajPaSchema::T_EVIDENCE)->where('context_id', '=', $ctx)->whereIn('evidence_id', $ids)->get(array('evidence_id', 'source_url'))->all() as $e) {
				$sources[(int) $e->evidence_id] = $e->source_url;
			}
		}
		$entries = array();
		for ($i = 1; $i <= 20; $i++) {
			$key = sprintf('FQ-%02d', $i);
			$a = isset($stored[$key]) ? $stored[$key] : null;
			$url = $a && $a['evidence_id'] !== null && isset($sources[(int) $a['evidence_id']]) ? $sources[(int) $a['evidence_id']] : null;
			$entries[] = array('questionKey' => $key, 'title' => isset($titles[$key]) ? $titles[$key] : null, 'answerStatus' => $a ? $a['answer_status'] : 'not_yet_assessed',
				'answerText' => $a ? $a['answer_text'] : null, 'uncertainty' => $a ? $a['uncertainty'] : null, 'stored' => $a !== null,
				'source_url' => $url, 'sourceUrlIsLink' => self::isHttpUrl($url));
		}
		return array('run' => $latest ? self::runSummary($latest, false) : null, 'entries' => $entries);
	}

	/** @return array Resources (pages read) of one run */
	public static function resources($contextId, $runId) {
		$rows = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_RESOURCE)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->where('run_id', '=', DoajPaDb::id($runId, 'run'))->orderBy('resource_id')->limit(self::LIST_LIMIT)->get()->all());
		foreach ($rows as &$row) $row['urlIsLink'] = self::isHttpUrl($row['url']);
		unset($row);
		return $rows;
	}

	/** @return array Evidence excerpts of one run */
	public static function evidence($contextId, $runId) {
		$rows = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_EVIDENCE . ' as e')
			->leftJoin(DoajPaSchema::T_RESOURCE . ' as r', function ($join) {
				$join->on('r.resource_id', '=', 'e.resource_id')->on('r.context_id', '=', 'e.context_id');
			})
			->where('e.context_id', '=', DoajPaDb::id($contextId, 'context'))->where('e.run_id', '=', DoajPaDb::id($runId, 'run'))
			->orderBy('e.evidence_id')->limit(self::LIST_LIMIT)
			->get(array('e.evidence_id', 'e.source_url', 'e.source_title', 'e.source_type', 'e.quote', 'e.location', 'e.detection_method',
				'e.retrieved_at_utc', 'e.extraction_version', 'r.acquisition_status'))->all());
		foreach ($rows as &$row) $row['sourceUrlIsLink'] = self::isHttpUrl($row['source_url']);
		unset($row);
		return $rows;
	}

	/** @return array Observations of one run, with finding identity */
	public static function observationsForRun($contextId, $runId) {
		$rows = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_OBSERVATION . ' as o')
			->join(DoajPaSchema::T_FINDING . ' as f', function ($join) {
				$join->on('f.finding_id', '=', 'o.finding_id')->on('f.context_id', '=', 'o.context_id');
			})
			->where('o.context_id', '=', DoajPaDb::id($contextId, 'context'))->where('o.run_id', '=', DoajPaDb::id($runId, 'run'))
			->orderBy('o.finding_id')->limit(self::LIST_LIMIT)
			->get(array('o.finding_id', 'o.observation', 'o.lifecycle_status', 'o.criterion_status', 'o.criterion_version', 'o.evidence_id',
				'f.criterion_key', 'f.subtype', 'f.target_type', 'f.target'))->all());
		foreach ($rows as &$row) $row['targetIsLink'] = self::isHttpUrl($row['target']);
		unset($row);
		return $rows;
	}

	/**
	 * Compare two runs of the journal through their observations. Different criterion versions are
	 * annotated, never silently equated (Stage 01 F6).
	 * @return array
	 */
	public static function compare($contextId, $fromRunId, $toRunId) {
		$ctx = DoajPaDb::id($contextId, 'context');
		$from = DoajPaAssessmentRunService::getRun($ctx, $fromRunId);
		$to = DoajPaAssessmentRunService::getRun($ctx, $toRunId);
		$a = array(); foreach (self::observationsForRun($ctx, (int) $from->run_id) as $o) $a[(int) $o['finding_id']] = $o;
		$b = array(); foreach (self::observationsForRun($ctx, (int) $to->run_id) as $o) $b[(int) $o['finding_id']] = $o;
		$ids = array_unique(array_merge(array_keys($a), array_keys($b)));
		sort($ids);
		$rows = array();
		foreach ($ids as $id) {
			$left = isset($a[$id]) ? $a[$id] : null;
			$right = isset($b[$id]) ? $b[$id] : null;
			$identity = $left ?: $right;
			if ($left && $right) {
				$change = $left['lifecycle_status'] === $right['lifecycle_status'] ? 'unchanged' : 'changed';
				$crossVersion = (string) $left['criterion_version'] !== (string) $right['criterion_version'];
			} else {
				$change = $left ? 'not_observed_in_to' : 'not_observed_in_from';
				$crossVersion = false;
			}
			$rows[] = array('finding_id' => $id, 'criterion_key' => $identity['criterion_key'], 'subtype' => $identity['subtype'],
				'target' => $identity['target'], 'targetIsLink' => $identity['targetIsLink'],
				'fromStatus' => $left ? $left['lifecycle_status'] : null, 'toStatus' => $right ? $right['lifecycle_status'] : null,
				'fromVersion' => $left ? $left['criterion_version'] : null, 'toVersion' => $right ? $right['criterion_version'] : null,
				'change' => $change, 'crossVersion' => $crossVersion);
		}
		return array('from' => self::runSummary($from, false), 'to' => self::runSummary($to, false), 'rows' => $rows,
			'differentRulebooks' => $from->rulebook_version !== $to->rulebook_version);
	}

	/** @return array Findings with original, last observed (from observations) and current status */
	public static function findings($contextId) {
		$ctx = DoajPaDb::id($contextId, 'context');
		$rows = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_FINDING)->where('context_id', '=', $ctx)->orderBy('finding_id')->limit(self::LIST_LIMIT)->get()->all());
		foreach ($rows as &$row) {
			$last = DoajPaDb::table(DoajPaSchema::T_OBSERVATION . ' as o')
				->join(DoajPaSchema::T_RUN . ' as r', function ($join) { $join->on('r.run_id', '=', 'o.run_id')->on('r.context_id', '=', 'o.context_id'); })
				->where('o.context_id', '=', $ctx)->where('o.finding_id', '=', (int) $row['finding_id'])
				->orderBy('o.observation_id', 'desc')->first(array('o.lifecycle_status', 'o.run_id', 'r.run_number'));
			$row['lastObservedStatus'] = $last ? $last->lifecycle_status : null;
			$row['lastObservedRunId'] = $last ? $last->run_id : null;
			$row['lastObservedRunNumber'] = $last ? $last->run_number : null;
			$row['targetIsLink'] = self::isHttpUrl($row['target']);
		}
		unset($row);
		return $rows;
	}

	/** @return array Finding detail: per-run observation timeline, transitions and linked actions */
	public static function finding($contextId, $findingId) {
		$ctx = DoajPaDb::id($contextId, 'context');
		$finding = DoajPaDb::table(DoajPaSchema::T_FINDING)->where('context_id', '=', $ctx)
			->where('finding_id', '=', DoajPaDb::id($findingId, 'finding'))->first();
		if (!$finding) throw new DoajPaNotFoundException('Finding not found.');
		$detail = self::rowArray($finding);
		$detail['targetIsLink'] = self::isHttpUrl($detail['target']);
		$detail['timeline'] = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_OBSERVATION . ' as o')
			->join(DoajPaSchema::T_RUN . ' as r', function ($join) { $join->on('r.run_id', '=', 'o.run_id')->on('r.context_id', '=', 'o.context_id'); })
			->leftJoin(DoajPaSchema::T_EVIDENCE . ' as e', function ($join) { $join->on('e.evidence_id', '=', 'o.evidence_id')->on('e.context_id', '=', 'o.context_id'); })
			->leftJoin(DoajPaSchema::T_RESOURCE . ' as res', function ($join) { $join->on('res.resource_id', '=', 'e.resource_id')->on('res.context_id', '=', 'e.context_id'); })
			->where('o.context_id', '=', $ctx)->where('o.finding_id', '=', (int) $finding->finding_id)->orderBy('o.observation_id')
			->get(array('o.run_id', 'r.run_number', 'r.rulebook_version', 'o.observed_at_utc', 'o.criterion_version', 'o.observation',
				'o.lifecycle_status', 'o.criterion_status', 'e.quote', 'e.source_url', 'e.location', 'e.retrieved_at_utc', 'res.acquisition_status'))->all());
		foreach ($detail['timeline'] as &$entry) {
			$entry['sourceUrlIsLink'] = self::isHttpUrl($entry['source_url']);
			$entry['rulebookApproved'] = self::isApprovedRulebook($entry['rulebook_version']);
		}
		unset($entry);
		$detail['transitions'] = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_TRANSITION)->where('context_id', '=', $ctx)
			->where('finding_id', '=', (int) $finding->finding_id)->orderBy('transition_id')->get()->all());
		$detail['actions'] = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_ACTION)->where('context_id', '=', $ctx)
			->where('finding_id', '=', (int) $finding->finding_id)->orderBy('action_id')->get()->all());
		$detail['definitionReferenceUrl'] = DoajPaDb::table(DoajPaSchema::T_DEFINITION)->where('criterion_key', '=', $finding->criterion_key)
			->orderBy('criterion_version', 'desc')->value('doaj_reference_url');
		return $detail;
	}

	/** @return array Registered criterion definitions with approval state */
	public static function definitions() {
		$rows = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_DEFINITION)->orderBy('criterion_key')->orderBy('criterion_version')->limit(self::LIST_LIMIT)->get()->all());
		foreach ($rows as &$row) $row['datasetApproved'] = in_array($row['dataset_version'], DoajPaSchema::APPROVED_DATASETS, true);
		unset($row);
		return $rows;
	}

	/** @return array Corrective actions of the journal */
	public static function actions($contextId) {
		$rows = self::rowsArray(DoajPaDb::table(DoajPaSchema::T_ACTION)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->orderBy('action_id', 'desc')->limit(self::LIST_LIMIT)->get()->all());
		$keys = array();
		$ids = array_values(array_filter(array_map(function ($r) { return $r['finding_id']; }, $rows)));
		if ($ids) foreach (DoajPaDb::table(DoajPaSchema::T_FINDING)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))->whereIn('finding_id', $ids)->get(array('finding_id', 'criterion_key'))->all() as $f) $keys[(int) $f->finding_id] = (string) $f->criterion_key;
		foreach ($rows as &$row) {
			$row['targetUrlIsLink'] = self::isHttpUrl($row['target_url']);
			$row['criterion_key'] = $row['finding_id'] !== null && isset($keys[(int) $row['finding_id']]) ? $keys[(int) $row['finding_id']] : null;
		}
		unset($row);
		return $rows;
	}

	/** @return array One corrective action */
	public static function action($contextId, $actionId) {
		$row = self::rowArray(DoajPaCorrectiveActionService::get($contextId, $actionId));
		$row['targetUrlIsLink'] = self::isHttpUrl($row['target_url']);
		return $row;
	}

	/** @return array Live findings for the action form */
	public static function findingChoices($contextId) {
		return self::rowsArray(DoajPaDb::table(DoajPaSchema::T_FINDING)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->where('current_status', '!=', 'superseded')->orderBy('finding_id')->limit(self::LIST_LIMIT)
			->get(array('finding_id', 'criterion_key', 'subtype', 'target_type', 'target'))->all());
	}

	/** @return array Latest audit events of the journal */
	public static function auditEvents($contextId) {
		return self::rowsArray(DoajPaDb::table(DoajPaSchema::T_AUDIT)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->orderBy('event_id', 'desc')->limit(self::AUDIT_LIMIT)->get()->all());
	}

	/** @return array First-use acknowledgements of the journal */
	public static function firstUseAcknowledgements($contextId) {
		return self::rowsArray(DoajPaDb::table(DoajPaSchema::T_ACK)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->where('ack_type', '=', 'first_use')->orderBy('acknowledgement_id', 'desc')->limit(self::LIST_LIMIT)->get()->all());
	}

	/** @return array Generated reports of the journal (metadata; files are downloaded through the guarded reportDownload op) */
	public static function exports($contextId) {
		return self::rowsArray(DoajPaDb::table(DoajPaSchema::T_EXPORT . ' as x')
			->join(DoajPaSchema::T_RUN . ' as r', function ($join) { $join->on('r.run_id', '=', 'x.run_id')->on('r.context_id', '=', 'x.context_id'); })
			->where('x.context_id', '=', DoajPaDb::id($contextId, 'context'))->orderBy('x.export_id', 'desc')->limit(self::LIST_LIMIT)
			->get(array('x.export_id', 'x.format', 'x.file_name', 'x.created_at_utc', 'x.run_id', 'r.run_number', 'x.rulebook_version', 'x.file_sha256', 'x.byte_length'))->all());
	}

	//
	// Helpers
	//

	/** @return array Summary of one run row */
	private static function runSummary($run, $verify) {
		$summary = self::rowArray($run);
		$summary['rulebookApproved'] = self::isApprovedRulebook($run->rulebook_version);
		$summary['resultCounts'] = $run->result_counts ? (array) json_decode($run->result_counts, true) : array();
		$summary['snapshotVerified'] = ($verify && $run->status === 'completed')
			? DoajPaAssessmentRunService::verifySnapshot((int) $run->context_id, (int) $run->run_id) : null;
		return $summary;
	}

	private static function countBy($table, $column, $contextId, $runId = null) {
		$query = DoajPaDb::table($table)->where('context_id', '=', $contextId);
		if ($runId !== null) $query->where('run_id', '=', $runId);
		$counts = array();
		foreach ($query->groupBy($column)->get(array($column . ' as k', DoajPaDb::raw('COUNT(*) as n')))->all() as $row) $counts[(string) $row->k] = (int) $row->n;
		return $counts;
	}

	/** @return boolean Only http(s) URLs are rendered as links */
	public static function isHttpUrl($value) {
		return is_string($value) && preg_match('#^https?://[^\s<>"\']+$#i', $value) === 1;
	}

	private static function rowArray($row) {
		return $row ? (array) $row : null;
	}

	private static function rowsArray($rows) {
		return array_map(function ($row) { return (array) $row; }, $rows);
	}
}
