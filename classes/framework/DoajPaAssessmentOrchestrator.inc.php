<?php

/**
 * @file classes/framework/DoajPaAssessmentOrchestrator.inc.php
 *
 * @class DoajPaAssessmentOrchestrator
 * @brief Deterministic assessment pipeline for one journal (Stage 02.07). It is the framework only: no
 *  approved DOAJ criteria exist in this version, so live runs evaluate no criterion, record every planned
 *  module as NOT IMPLEMENTED and every form question FQ-01..FQ-20 as "Not yet assessed". Nothing here
 *  produces a DOAJ eligibility statement.
 *
 * Pipeline (PIPELINE): validate the journal target -> crawl (bounded, resumable steps) -> snapshot of the
 * stored records and the crawl manifest -> diff with the previous completed run -> public-source provenance
 * -> map evidence for each rule -> invoke versioned rules -> verify quotes and language (DoajPaOutcomeGuard)
 * -> persist results, form answers and coverage and seal the snapshot -> report metadata (in the coverage).
 *
 * Every write goes through DoajPaAssessmentRunService. Evaluation and sealing happen in ONE transaction: if
 * anything fails, nothing of it is stored, the crawl stays complete and the next step evaluates again.
 * An unexpected failure moves the run to "interrupted" (audited); resume() continues it without duplicate
 * resource records (the crawler skips URLs already stored and reports them as coverage gaps).
 *
 * Live runs use DoajPaRuleRegistry::liveRules() only, which must come from an approved dataset (none exists, so
 * the default "approved" profile evaluates no criterion). Stage 03 adds one explicit, per-run "candidate" profile:
 * a Journal Manager may choose to evaluate the PROPOSED criteria dataset (PENDING APPROVAL, decision D-03-1) for
 * internal testing. Such runs carry CANDIDATE_RULEBOOK (never an approved rulebook), their results and FQ answers
 * are labelled as candidate, and the report states "Not a DOAJ assessment result". The test entry points (startForTests, advanceForTests) work only on the command line, only for runs created
 * with TEST_RULEBOOK, and only with rules whose dataset starts with TEST_DATASET_PREFIX.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/../services/DoajPaAssessmentRunService.inc.php');
require_once(dirname(__FILE__) . '/../services/DoajPaAuditService.inc.php');
require_once(dirname(__FILE__) . '/../services/DoajPaCriteriaRegistry.inc.php');
require_once(dirname(__FILE__) . '/../services/DoajPaCrawlService.inc.php');
require_once(dirname(__FILE__) . '/DoajPaCriterionRule.inc.php');
require_once(dirname(__FILE__) . '/DoajPaEvaluationContext.inc.php');
require_once(dirname(__FILE__) . '/DoajPaOutcomeGuard.inc.php');
require_once(dirname(__FILE__) . '/DoajPaRuleRegistry.inc.php');

class DoajPaAssessmentOrchestrator {

	/** Rule set label of live runs while no DOAJ rulebook is approved (never in DoajPaSchema::APPROVED_RULEBOOKS). */
	const LIVE_RULEBOOK = 'none-no-approved-doaj-rulebook';
	/** Rule set label of runs created by test tooling. */
	const TEST_RULEBOOK = 'TEST-FIXTURE-NOT-DOAJ-0207';
	const TEST_DATASET_PREFIX = 'TEST-FIXTURE-NOT-DOAJ';
	/** Rule set label of runs that evaluate the proposed (unapproved) criteria dataset. Never approved. */
	const CANDIDATE_RULEBOOK = DoajPaSchema::CANDIDATE_RULEBOOK;
	const PROFILE_APPROVED = 'approved';
	const PROFILE_CANDIDATE = 'candidate';
	const PIPELINE_VERSION = 'doajpa-orchestrator/1';
	/** Seconds of crawling per web step. */
	const STEP_SECONDS = 10;
	const PIPELINE = array('validate_target', 'crawl', 'snapshot', 'diff', 'provenance', 'map_evidence', 'invoke_rules',
		'verify_quotes', 'persist', 'report_metadata');

	/** Stage 03 modules: documented, NOT implemented, never reported as ready (Stage 01 F3, F4). */
	const FUTURE_MODULES = array(
		'endogeny' => 'Editorial endogeny: last two issues each separately, or the previous calendar year; 25% Guide threshold; uncertain name matches abstained (F4, AC-11)',
		'publication_history_dates' => 'Article PDF publication history dates, with the exact "No publication history date found" fallback (F4)',
		'licensing_consistency' => 'Licence and copyright statements, including cross-page contradictions (F3)',
		'indexer_claims' => 'Indexing claims: unreliable indexers, disallowed metrics and unverified claims kept apart (F4, AC-12)',
		'doaj_criteria' => 'All other DOAJ Guide, Transparency and Copyright-and-Licensing criteria (F2, F3)',
		'form_guide' => 'FQ-01..FQ-20 answers mapped to validated public sources (F3, AC-13)',
	);

	const FQ_NOT_YET_ASSESSED = 'Not yet assessed. No approved DOAJ rule maps this question in this plugin version, so no answer is given or inferred.';
	const DISCLAIMER = 'Not a DOAJ assessment result. No approved DOAJ rule set was applied; this run records website coverage and evidence only.';
	const CANDIDATE_DISCLAIMER = 'Not a DOAJ assessment result. Candidate criteria (dataset 0.1.0-draft, PENDING APPROVAL) were applied for internal testing only; they are not approved DOAJ rules, and no result is a compliance claim.';
	/** Stage 03 modules evaluated by the candidate profile (PENDING APPROVAL). */
	const CANDIDATE_MODULES = array('endogeny', 'publication_history_dates', 'licensing_consistency', 'indexer_claims', 'doaj_criteria', 'form_guide');

	//
	// Entry points used by the backend handler (live runs)
	//

	/**
	 * Validate the journal target before any run is created (pipeline stage "validate_target").
	 * @return string Journal homepage URL
	 * @throws DoajPaStateException when the crawler cannot run in this installation
	 */
	public static function target($request, $context) {
		if (Config::getVar('proxy', 'http_proxy') || Config::getVar('proxy', 'https_proxy')) {
			throw new DoajPaStateException('An HTTP proxy is configured for OJS. The crawler refuses to run, because the destination address cannot be verified through a proxy.');
		}
		$homepage = DoajPaCrawlService::homepageUrl($request, $context);
		try {
			new DoajPaCrawlScope($homepage);
		} catch (InvalidArgumentException $e) {
			throw new DoajPaStateException('The journal homepage URL cannot be crawled.');
		}
		return $homepage;
	}

	/**
	 * Create a live run with the per-run English-only acknowledgement, move it to running and start the crawl.
	 * A crawl start failure leaves the run "interrupted" (resumable or cancellable).
	 * @return int run_id
	 */
	public static function start($contextId, $homepage, $actor, $acknowledgement, $profile = self::PROFILE_APPROVED) {
		if ($profile !== self::PROFILE_APPROVED && $profile !== self::PROFILE_CANDIDATE) throw new DoajPaValidationException('Unknown assessment profile.');
		return self::startRun($contextId, $homepage, $actor, $acknowledgement, $profile === self::PROFILE_CANDIDATE ? self::CANDIDATE_RULEBOOK : self::LIVE_RULEBOOK, array());
	}

	/** @return boolean Whether a run evaluates the proposed (unapproved) candidate criteria */
	public static function isCandidate($rulebook) {
		return $rulebook === self::CANDIDATE_RULEBOOK;
	}

	/**
	 * One bounded step of a live run: crawl for up to STEP_SECONDS, or (when the crawl is complete) evaluate,
	 * persist and seal. Safe to call repeatedly and concurrently.
	 * @return array phase: crawling|busy|completed|interrupted|conflict|<run status>
	 */
	public static function advance($contextId, $runId, $actor) {
		$run = DoajPaAssessmentRunService::getRun($contextId, $runId);
		if (!in_array($run->rulebook_version, array_merge(array(self::LIVE_RULEBOOK, self::CANDIDATE_RULEBOOK), DoajPaSchema::APPROVED_RULEBOOKS), true)) {
			throw new DoajPaStateException('This run was not created by the live assessment and cannot be continued here.');
		}
		$rules = self::isCandidate($run->rulebook_version) ? self::candidateRules() : self::liveRules();
		return self::advanceRun($contextId, $run, $actor, $rules, array(), array());
	}

	/**
	 * The rules a live run evaluates. Each must come from an approved DOAJ criteria dataset.
	 * @return DoajPaCriterionRule[]
	 */
	public static function liveRules() {
		$rules = DoajPaRuleRegistry::liveRules();
		foreach ($rules as $rule) {
			if (!($rule instanceof DoajPaCriterionRule) || !in_array($rule->datasetVersion(), DoajPaSchema::APPROVED_DATASETS, true)) {
				throw new DoajPaStateException('A live rule does not come from an approved DOAJ criteria dataset.');
			}
		}
		return $rules;
	}

	/**
	 * The proposed criteria of the candidate profile. Each must come from the candidate dataset, which must NOT be an
	 * approved dataset (an approved version is registered as a new criterion version and runs as a live rule).
	 * @return DoajPaCriterionRule[]
	 */
	public static function candidateRules() {
		require_once(dirname(__FILE__) . '/../rules/DoajPaCandidateRule.inc.php');
		$rules = DoajPaRuleRegistry::candidateRules();
		foreach ($rules as $rule) {
			if (!($rule instanceof DoajPaCandidateRule) || $rule->datasetVersion() !== DoajPaCandidateDataset::VERSION
				|| DoajPaCandidateDataset::STATUS !== 'proposed' || in_array($rule->datasetVersion(), DoajPaSchema::APPROVED_DATASETS, true)) {
				throw new DoajPaStateException('A candidate rule does not come from the proposed criteria dataset.');
			}
		}
		return $rules;
	}

	/** Resume an interrupted run (optimistic lock). @return int New lock version */
	public static function resume($contextId, $runId, $expectedLockVersion, $actor) {
		$run = DoajPaAssessmentRunService::getRun($contextId, $runId);
		if ($run->status !== 'interrupted') throw new DoajPaStateException('Only an interrupted run can be resumed.');
		return DoajPaAssessmentRunService::transition($contextId, $runId, 'running', $expectedLockVersion, $actor);
	}

	/** Cancel an active run (optimistic lock). Stored records stay; the run is never sealed. @return int New lock version */
	public static function cancel($contextId, $runId, $expectedLockVersion, $actor) {
		return DoajPaAssessmentRunService::transition($contextId, $runId, 'cancelled', $expectedLockVersion, $actor);
	}

	//
	// Test entry points (command line only; never reachable from a request)
	//

	/** TEST ONLY. @return int run_id */
	public static function startForTests($contextId, $homepage, $actor, $acknowledgement, $crawlOptions) {
		self::requireCli();
		return self::startRun($contextId, $homepage, $actor, $acknowledgement, self::TEST_RULEBOOK, $crawlOptions);
	}

	/** TEST ONLY. $hooks: 'failBeforeSeal' => true injects a failure after all writes, before sealing. */
	public static function advanceForTests($contextId, $runId, $actor, $rules, $crawlOptions, $hooks = array()) {
		self::requireCli();
		$run = DoajPaAssessmentRunService::getRun($contextId, $runId);
		if ($run->rulebook_version !== self::TEST_RULEBOOK) throw new DoajPaStateException('Test rules can be used only for test runs.');
		foreach ($rules as $rule) {
			if (!($rule instanceof DoajPaCriterionRule) || strpos((string) $rule->datasetVersion(), self::TEST_DATASET_PREFIX) !== 0) {
				throw new DoajPaStateException('Test runs accept only test fixture rules.');
			}
		}
		return self::advanceRun($contextId, $run, $actor, $rules, $crawlOptions, $hooks);
	}

	//
	// Pipeline
	//

	private static function startRun($contextId, $homepage, $actor, $acknowledgement, $rulebook, $crawlOptions) {
		$runId = DoajPaAssessmentRunService::createDraftRun($contextId, $actor, $acknowledgement, array(
			'rulebookVersion' => $rulebook,
			'extractionVersion' => DoajPaHtmlExtractor::VERSION . '+' . DoajPaPdfExtractor::VERSION,
			'sourceScope' => array('homepage' => $homepage, 'pipeline' => self::PIPELINE_VERSION),
		));
		$lock = DoajPaAssessmentRunService::transition($contextId, $runId, 'queued', 0, $actor);
		DoajPaAssessmentRunService::transition($contextId, $runId, 'running', $lock, $actor);
		self::guarded($contextId, $runId, $actor, function () use ($contextId, $runId, $homepage, $actor, $crawlOptions) {
			DoajPaCrawlService::start($contextId, $runId, $homepage, $actor, $crawlOptions);
			return array('phase' => 'crawling');
		});
		return $runId;
	}

	private static function advanceRun($contextId, $run, $actor, $rules, $crawlOptions, $hooks) {
		$runId = (int) $run->run_id;
		if ($run->status !== 'running') return array('phase' => $run->status);
		$scope = json_decode((string) $run->source_scope, true);
		$homepage = is_array($scope) && isset($scope['homepage']) ? $scope['homepage'] : null;
		$budget = isset($crawlOptions['stepSeconds']) && is_int($crawlOptions['stepSeconds']) ? $crawlOptions['stepSeconds'] : self::STEP_SECONDS;
		return self::guarded($contextId, $runId, $actor, function () use ($contextId, $runId, $actor, $rules, $crawlOptions, $hooks, $homepage, $budget) {
			if (!DoajPaCrawlService::isStarted($contextId, $runId)) {
				if (!is_string($homepage)) throw new DoajPaStateException('The run has no journal homepage.');
				DoajPaCrawlService::start($contextId, $runId, $homepage, $actor, $crawlOptions);
			}
			$step = DoajPaCrawlService::step($contextId, $runId, $actor, $budget, $crawlOptions);
			if (!empty($step['busy'])) return array('phase' => 'busy');
			if (empty($step['done'])) return array('phase' => 'crawling', 'crawl' => $step);
			// One evaluation at a time: parallel steps that all see a finished crawl must not evaluate concurrently
			// (a failure of one would interrupt the run for all). The others report "busy"; the next step sees the result.
			$lock = @fopen(DoajPaCrawlService::workDir($contextId, $runId) . '/evaluate.lock', 'c');
			if (!$lock) throw new DoajPaStateException('Cannot open the evaluation lock.');
			if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return array('phase' => 'busy'); }
			try {
				$current = DoajPaAssessmentRunService::getRun($contextId, $runId);
				if ($current->status !== 'running') return array('phase' => $current->status);
				return array('phase' => 'completed', 'result' => self::evaluate($contextId, $runId, $actor, $rules, $hooks));
			} finally {
				flock($lock, LOCK_UN);
				fclose($lock);
			}
		});
	}

	/**
	 * Run one pipeline action. A concurrent change is reported as "conflict" (the next step retries); any other
	 * failure of a still-running run moves it to "interrupted" with an audited reason.
	 */
	private static function guarded($contextId, $runId, $actor, $callback) {
		try {
			return $callback();
		} catch (DoajPaConcurrencyException $e) {
			return array('phase' => 'conflict');
		} catch (\Throwable $e) {
			$reason = $e instanceof DoajPaException ? get_class($e) . ': ' . $e->getMessage() : get_class($e);
			if (!($e instanceof DoajPaException)) error_log('doajPreAssessment: run ' . $runId . ' step failed: ' . get_class($e) . ': ' . $e->getMessage());
			$run = DoajPaAssessmentRunService::getRun($contextId, $runId);
			if ($run->status !== 'running') return array('phase' => $run->status);
			self::interrupt($contextId, $runId, $actor, substr($reason, 0, 500));
			return array('phase' => 'interrupted', 'reason' => $reason);
		}
	}

	private static function interrupt($contextId, $runId, $actor, $reason) {
		try {
			DoajPaDb::transaction(function () use ($contextId, $runId, $actor, $reason) {
				$run = DoajPaAssessmentRunService::getRun($contextId, $runId);
				if ($run->status !== 'running') return;
				DoajPaAssessmentRunService::transition($contextId, $runId, 'interrupted', (int) $run->lock_version, $actor);
				DoajPaAuditService::record($contextId, 'run_interrupted', $actor, $runId, 'run', $runId, array('reason' => $reason));
			});
		} catch (DoajPaConcurrencyException $e) {
			// Another request changed the run first; its state stands.
		}
	}

	/**
	 * Evaluate the complete crawl and seal the run (pipeline stages snapshot .. report_metadata).
	 * @return array complete() summary plus evaluation counts
	 */
	private static function evaluate($contextId, $runId, $actor, $rules, $hooks) {
		$run = DoajPaAssessmentRunService::getRun($contextId, $runId);
		$coverage = json_decode((string) $run->coverage, true);
		if (!is_array($coverage) || !isset($coverage['manifestSha256'])) throw new DoajPaStateException('The crawl coverage summary is missing.');
		$keys = array();
		foreach ($rules as $rule) {
			if (isset($keys[$rule->key()])) throw new DoajPaStateException('Two rules share the criterion key ' . $rule->key() . '.');
			$keys[$rule->key()] = true;
		}

		// snapshot + provenance: stored records of this run, language from the sealed crawl manifest.
		$dir = DoajPaCrawlService::workDir($contextId, $runId);
		$manifestJson = is_file($dir . '/manifest.json') ? (string) file_get_contents($dir . '/manifest.json') : '';
		$manifestVerified = $manifestJson !== '' && hash_equals((string) $coverage['manifestSha256'], hash('sha256', $manifestJson));
		$languageByResource = array();
		$crawlInfo = array();
		if ($manifestVerified) {
			$manifest = json_decode($manifestJson, true);
			foreach (isset($manifest['resources']) ? $manifest['resources'] : array() as $r) {
				if (!isset($r['id'])) continue;
				$languageByResource[(int) $r['id']] = is_array($r['language']) ? (string) $r['language']['verdict'] : 'none';
				$crawlInfo[(int) $r['id']] = array('depth' => isset($r['depth']) ? (int) $r['depth'] : null,
					'mainRegion' => isset($r['mainRegion']) ? $r['mainRegion'] : null, 'linkText' => isset($r['linkText']) ? (string) $r['linkText'] : '');
			}
		}
		$resources = array();
		foreach (DoajPaDb::table(DoajPaSchema::T_RESOURCE)->where('context_id', '=', $contextId)->where('run_id', '=', $runId)->orderBy('resource_id')->get()->all() as $r) {
			$resources[(int) $r->resource_id] = array('id' => (int) $r->resource_id, 'url' => $r->url, 'urlSha256' => $r->url_sha256,
				'pageType' => $r->page_type, 'httpStatus' => $r->http_status === null ? null : (int) $r->http_status,
				'acquisition' => $r->acquisition_status, 'reason' => $r->skip_reason, 'contentSha256' => $r->content_sha256,
				'finalUrl' => $r->final_url, 'mediaType' => $r->media_type,
				'depth' => isset($crawlInfo[(int) $r->resource_id]) ? $crawlInfo[(int) $r->resource_id]['depth'] : null,
				'mainRegion' => isset($crawlInfo[(int) $r->resource_id]) ? $crawlInfo[(int) $r->resource_id]['mainRegion'] : null,
				'linkText' => isset($crawlInfo[(int) $r->resource_id]) ? $crawlInfo[(int) $r->resource_id]['linkText'] : '');
		}
		$evidence = array();
		$counts = array('items' => 0, 'verifiedEnglish' => 0, 'languageNotVerified' => 0, 'provenanceFailures' => 0);
		foreach (DoajPaDb::table(DoajPaSchema::T_EVIDENCE)->where('context_id', '=', $contextId)->where('run_id', '=', $runId)->orderBy('evidence_id')->get()->all() as $e) {
			$res = $e->resource_id !== null && isset($resources[(int) $e->resource_id]) ? $resources[(int) $e->resource_id] : null;
			$provenance = $res !== null && $res['httpStatus'] !== null && $res['httpStatus'] >= 200 && $res['httpStatus'] < 300
				&& $res['contentSha256'] !== null && hash_equals((string) $e->quote_sha256, hash('sha256', (string) $e->quote));
			$language = $provenance && isset($languageByResource[$res['id']]) ? $languageByResource[$res['id']] : 'unknown';
			// Verified English needs the stored state AND the sealed manifest to agree; anything else is not verified.
			$verified = $provenance && $res['acquisition'] === 'retrieved' && $res['reason'] === null && $language === 'english';
			$evidence[(int) $e->evidence_id] = array('id' => (int) $e->evidence_id, 'resourceId' => $res ? $res['id'] : null,
				'url' => $e->source_url, 'title' => $e->source_title, 'sourceType' => $e->source_type, 'pageType' => $res ? $res['pageType'] : null,
				'quote' => (string) $e->quote, 'quoteSha256' => $e->quote_sha256, 'acquisition' => $res ? $res['acquisition'] : null,
				'language' => $language, 'provenance' => $provenance, 'verifiedEnglish' => $verified,
				'method' => (string) $e->detection_method, 'location' => (string) $e->location);
			$counts['items']++;
			if (!$provenance) $counts['provenanceFailures']++;
			if ($verified) $counts['verifiedEnglish']++; else $counts['languageNotVerified']++;
		}
		$context = new DoajPaEvaluationContext($evidence, $resources, $coverage);

		// diff with the previous completed run
		$previousRunId = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', $contextId)->where('status', '=', 'completed')
			->where('run_id', '<', $runId)->max('run_id');
		$previousRunId = $previousRunId === null ? null : (int) $previousRunId;
		$diff = self::diff($contextId, $previousRunId, $resources);
		$previousEvaluation = self::previousEvaluation($contextId, $previousRunId);

		// map evidence, invoke rules (or reuse an unchanged result), verify quotes and language
		$records = array();
		$evaluation = array('version' => 1, 'runId' => $runId, 'rules' => array());
		$statusCounts = array();
		$reused = 0;
		$downgraded = 0;
		foreach ($rules as $rule) {
			$identity = array($rule->key(), $rule->version(), $rule->lineage(), $rule->datasetVersion(), $rule->sourceVersion(), DoajPaOutcomeGuard::VERSION);
			$scope = $rule->requiredEvidence();
			if (method_exists($rule, 'fingerprintScope')) {
				list($scope, $extra) = $rule->fingerprintScope($coverage);
				$identity[] = $extra;
			}
			$fingerprint = $context->fingerprint($identity, $scope);
			$raw = null;
			$wasReused = false;
			if ($fingerprint !== null && $previousEvaluation !== null && isset($previousEvaluation['rules'][$rule->key()])
				&& $previousEvaluation['rules'][$rule->key()]['fingerprint'] === $fingerprint) {
				$raw = self::remap($previousEvaluation['rules'][$rule->key()]['outcome'], $evidence);
				$wasReused = $raw !== null;
			}
			if ($raw === null) {
				try {
					$raw = $rule->evaluate($context);
				} catch (\Throwable $e) {
					$raw = array('status' => 'unverifiable', 'rationale' => '[Rule failed: ' . get_class($e) . '] No result was produced.');
				}
			}
			// Reused outcomes pass the guard again against this run's evidence.
			$outcome = DoajPaOutcomeGuard::apply($raw, $context->guardView(), $rule->key());
			if ($outcome['downgrades']) $downgraded++;
			if ($wasReused) $reused++;
			$statusCounts[$outcome['status']] = isset($statusCounts[$outcome['status']]) ? $statusCounts[$outcome['status']] + 1 : 1;
			$record = array('rule' => $rule, 'outcome' => $outcome);
			if (self::isCandidate($run->rulebook_version)) $record['cited'] = self::cited($outcome, $evidence);
			$records[] = $record;
			$evaluation['rules'][$rule->key()] = array('version' => $rule->version(), 'fingerprint' => $fingerprint, 'reused' => $wasReused,
				'outcome' => self::portable($outcome, $evidence));
		}
		// form guide: FQ-01..FQ-20 from the guarded outcomes and the run's facts (candidate profile only)
		$candidate = self::isCandidate($run->rulebook_version);
		$formAnswers = array();
		if ($candidate) {
			require_once(dirname(__FILE__) . '/../rules/DoajPaCandidateRule.inc.php');
			require_once(dirname(__FILE__) . '/../rules/DoajPaFormGuideBuilder.inc.php');
			$guarded = array();
			foreach ($records as $record) $guarded[$record['rule']->key()] = $record['outcome'];
			$data = DoajPaCandidateDataset::data();
			$builder = new DoajPaFormGuideBuilder(DoajPaFacts::of($context), $guarded, $data['fqMapping']);
			$formAnswers = $builder->build();
			$evaluation['formGuide'] = $formAnswers;
		} else {
			for ($i = 1; $i <= 20; $i++) {
				$formAnswers[] = array('questionKey' => sprintf('FQ-%02d', $i), 'answerStatus' => 'not_yet_assessed', 'uncertainty' => self::FQ_NOT_YET_ASSESSED);
			}
		}
		$answerCounts = array();
		foreach ($formAnswers as $a) $answerCounts[$a['answerStatus']] = isset($answerCounts[$a['answerStatus']]) ? $answerCounts[$a['answerStatus']] + 1 : 1;
		ksort($answerCounts);
		$evaluationJson = DoajPaDb::json($evaluation);
		self::atomicWrite($dir . '/evaluation.json', $evaluationJson);
		ksort($statusCounts);

		// report metadata (kept in the sealed coverage summary)
		$rulebookApproved = in_array($run->rulebook_version, DoajPaSchema::APPROVED_RULEBOOKS, true);
		$coverage['assessment'] = array(
			'pipelineVersion' => self::PIPELINE_VERSION, 'pipeline' => self::PIPELINE,
			'criteria' => !$rules ? 'not_implemented' : ($rulebookApproved ? 'approved_rules' : ($candidate ? 'candidate_rules_pending_approval' : 'test_fixture_rules')),
			'rulesEvaluated' => count($rules), 'rulesReused' => $reused, 'rulesDowngraded' => $downgraded, 'statusCounts' => $statusCounts,
			'notImplemented' => $candidate ? array() : array_keys(self::FUTURE_MODULES),
			'candidateModules' => $candidate ? self::CANDIDATE_MODULES : array(),
			'candidateDataset' => $candidate ? array('version' => DoajPaCandidateDataset::VERSION, 'status' => DoajPaCandidateDataset::STATUS,
				'sourceSha256' => DoajPaCandidateDataset::SOURCE_SHA256, 'engine' => DoajPaCandidateRule::ENGINE_VERSION, 'approval' => 'PENDING APPROVAL (D-03-1)') : null,
			'formGuide' => $candidate ? array('questions' => 20, 'answerStatus' => 'candidate_pending_approval', 'counts' => $answerCounts)
				: array('questions' => 20, 'answerStatus' => 'not_yet_assessed'),
			'evidence' => $counts + array('manifestVerified' => $manifestVerified),
			'diff' => $diff, 'evaluationSha256' => hash('sha256', $evaluationJson),
			'report' => array('rulebookVersion' => $run->rulebook_version, 'rulebookApproved' => $rulebookApproved, 'notADoajResult' => !$rulebookApproved,
				'candidate' => $candidate, 'disclaimer' => $candidate ? self::CANDIDATE_DISCLAIMER : self::DISCLAIMER, 'extractionVersion' => $run->extraction_version,
				'pdfTool' => isset($coverage['pdfTool']) ? $coverage['pdfTool'] : null, 'evaluatedAtUtc' => DoajPaDb::now()),
		);
		$lock = (int) $run->lock_version;

		// persist and seal: one transaction
		return DoajPaDb::transaction(function () use ($contextId, $runId, $actor, $records, $coverage, $lock, $hooks, $reused, $statusCounts, $formAnswers) {
			$detected = array();
			$verifiedClear = array();
			foreach ($records as $record) {
				$rule = $record['rule'];
				$o = $record['outcome'];
				$definitionId = DoajPaCriteriaRegistry::register(array(
					'criterionKey' => $rule->key(), 'criterionVersion' => $rule->version(), 'lineageVersion' => $rule->lineage(),
					'datasetVersion' => $rule->datasetVersion(), 'sourceVersion' => $rule->sourceVersion(), 'level' => $rule->level(),
					'title' => $rule->title(), 'doajReferenceUrl' => $rule->doajReferenceUrl(),
				), $actor);
				DoajPaAssessmentRunService::addCriterionResult($contextId, $runId, $definitionId, array(
					'machineStatus' => $o['status'],
					'rationale' => ($o['rationale'] !== '' ? $o['rationale'] : 'No rationale was given.') . (isset($record['cited']) ? $record['cited'] : ''),
					'interpretationLimit' => $o['interpretationLimit'] !== '' ? $o['interpretationLimit'] : $rule->humanReviewBoundary(),
					'evidenceId' => $o['evidenceIds'] ? $o['evidenceIds'][0] : null,
				));
				$identity = array('criterionKey' => $rule->key(), 'criterionVersion' => $rule->version());
				foreach ($o['detections'] as $d) $detected[] = $identity + $d;
				foreach ($o['verifiedClear'] as $c) $verifiedClear[] = $identity + $c;
			}
			foreach ($formAnswers as $a) DoajPaAssessmentRunService::addFormAnswer($contextId, $runId, $a);
			DoajPaAssessmentRunService::recordCoverage($contextId, $runId, $coverage, $actor);
			if (!empty($hooks['failBeforeSeal'])) throw new RuntimeException('Injected failure before sealing (test).');
			$summary = DoajPaAssessmentRunService::complete($contextId, $runId, $lock, $actor, $detected, $verifiedClear);
			DoajPaAuditService::record($contextId, 'assessment_evaluated', $actor, $runId, 'run', $runId, array(
				'criteria' => $coverage['assessment']['criteria'], 'rulesEvaluated' => count($records), 'rulesReused' => $reused,
				'statusCounts' => $statusCounts, 'snapshotDigest' => $summary['snapshotDigest']));
			return $summary + array('rulesEvaluated' => count($records), 'rulesReused' => $reused);
		});
	}

	/**
	 * Verified quotes and evidence URLs of a candidate outcome, appended to the stored rationale so the sealed record
	 * shows exactly what the result relies on (the quotes passed DoajPaOutcomeGuard).
	 * @return string
	 */
	private static function cited($outcome, $evidence) {
		$parts = array();
		foreach (array_slice($outcome['quotes'], 0, 3) as $q) $parts[] = '"' . $q['text'] . '" (' . $evidence[$q['evidenceId']]['url'] . ')';
		$out = $parts ? ' Verified quotes: ' . implode('; ', $parts) . '.' : '';
		$urls = array();
		foreach ($outcome['evidenceIds'] as $id) if (isset($evidence[$id])) $urls[$evidence[$id]['url']] = true;
		if ($urls) $out .= ' Evidence pages (' . count($urls) . '): ' . implode(', ', array_slice(array_keys($urls), 0, 8)) . (count($urls) > 8 ? ', ...' : '') . '.';
		return $out;
	}

	/** @return array Resource changes against the previous completed run (counts and up to 20 sample URLs each) */
	private static function diff($contextId, $previousRunId, $resources) {
		if ($previousRunId === null) return array('previousRunId' => null);
		$before = array();
		foreach (DoajPaDb::table(DoajPaSchema::T_RESOURCE)->where('context_id', '=', $contextId)->where('run_id', '=', $previousRunId)
			->get(array('url', 'url_sha256', 'acquisition_status', 'content_sha256'))->all() as $r) {
			$before[$r->url_sha256] = array('url' => $r->url, 'acquisition' => $r->acquisition_status, 'contentSha256' => $r->content_sha256);
		}
		$now = array();
		foreach ($resources as $r) $now[$r['urlSha256']] = $r;
		$out = array('previousRunId' => $previousRunId, 'counts' => array('new' => 0, 'unchanged' => 0, 'changed' => 0, 'notReadAgain' => 0, 'becameGap' => 0),
			'samples' => array('changed' => array(), 'notReadAgain' => array(), 'becameGap' => array()));
		foreach ($now as $k => $r) {
			if (!isset($before[$k])) { $out['counts']['new']++; continue; }
			$b = $before[$k];
			if ($b['acquisition'] === 'retrieved' && $r['acquisition'] !== 'retrieved') self::sample($out, 'becameGap', $r['url']);
			if ((string) $b['contentSha256'] === (string) $r['contentSha256'] && $b['acquisition'] === $r['acquisition']) $out['counts']['unchanged']++;
			else self::sample($out, 'changed', $r['url']);
		}
		foreach ($before as $k => $b) if (!isset($now[$k])) self::sample($out, 'notReadAgain', $b['url']);
		return $out;
	}

	private static function sample(&$out, $kind, $url) {
		$out['counts'][$kind]++;
		if (count($out['samples'][$kind]) < 20) $out['samples'][$kind][] = substr((string) $url, 0, 500);
	}

	/**
	 * The previous run's evaluation file, only if the previous run's snapshot verifies and the file matches the
	 * digest sealed in that snapshot. Otherwise null (every rule is evaluated again).
	 * @return array|null
	 */
	private static function previousEvaluation($contextId, $previousRunId) {
		if ($previousRunId === null) return null;
		$previous = DoajPaAssessmentRunService::getRun($contextId, $previousRunId);
		$coverage = json_decode((string) $previous->coverage, true);
		$sealed = is_array($coverage) && isset($coverage['assessment']['evaluationSha256']) ? (string) $coverage['assessment']['evaluationSha256'] : null;
		if ($sealed === null || !DoajPaAssessmentRunService::verifySnapshot($contextId, $previousRunId)) return null;
		$file = DoajPaCrawlService::workDir($contextId, $previousRunId) . '/evaluation.json';
		$json = is_file($file) ? (string) file_get_contents($file) : '';
		if ($json === '' || !hash_equals($sealed, hash('sha256', $json))) return null;
		$data = json_decode($json, true);
		return is_array($data) && isset($data['rules']) ? $data : null;
	}

	/** Outcome with evidence ids replaced by run-independent references (source URL + quote SHA-256). */
	private static function portable($outcome, $evidence) {
		$ref = function ($id) use ($evidence) {
			return isset($evidence[$id]) ? array('url' => $evidence[$id]['url'], 'sha' => $evidence[$id]['quoteSha256']) : null;
		};
		$p = array('status' => $outcome['status'], 'rationale' => $outcome['rationale'], 'interpretationLimit' => $outcome['interpretationLimit'],
			'basis' => isset($outcome['basis']) ? $outcome['basis'] : 'quote',
			'evidence' => array_map($ref, $outcome['evidenceIds']), 'quotes' => array(), 'detections' => array(), 'verifiedClear' => array());
		foreach ($outcome['quotes'] as $q) $p['quotes'][] = array('ref' => $ref($q['evidenceId']), 'text' => $q['text']);
		foreach (array('detections', 'verifiedClear') as $list) {
			foreach ($outcome[$list] as $item) { $item['ref'] = $ref($item['evidenceId']); unset($item['evidenceId']); $p[$list][] = $item; }
		}
		return $p;
	}

	/** Inverse of portable() for this run's evidence; null when any reference has no identical evidence now. */
	private static function remap($portable, $evidence) {
		$index = array();
		foreach ($evidence as $id => $e) $index[$e['url'] . '|' . $e['quoteSha256']] = $id;
		$find = function ($ref) use ($index) {
			return is_array($ref) && isset($ref['url'], $ref['sha'], $index[$ref['url'] . '|' . $ref['sha']]) ? $index[$ref['url'] . '|' . $ref['sha']] : null;
		};
		if (!is_array($portable)) return null;
		$o = array('status' => $portable['status'], 'rationale' => $portable['rationale'], 'interpretationLimit' => $portable['interpretationLimit'],
			'basis' => isset($portable['basis']) ? $portable['basis'] : 'quote',
			'evidenceIds' => array(), 'quotes' => array(), 'detections' => array(), 'verifiedClear' => array());
		foreach ($portable['evidence'] as $ref) { $id = $find($ref); if ($id === null) return null; $o['evidenceIds'][] = $id; }
		foreach ($portable['quotes'] as $q) { $id = $find($q['ref']); if ($id === null) return null; $o['quotes'][] = array('evidenceId' => $id, 'text' => $q['text']); }
		foreach (array('detections', 'verifiedClear') as $list) {
			foreach ($portable[$list] as $item) {
				$id = $find($item['ref']);
				if ($id === null) return null;
				unset($item['ref']);
				$item['evidenceId'] = $id;
				$o[$list][] = $item;
			}
		}
		return $o;
	}

	private static function atomicWrite($file, $content) {
		$tmp = $file . '.tmp-' . bin2hex(random_bytes(6));
		// Failures are reported by the exception below, not as PHP warnings.
		if (@file_put_contents($tmp, $content, LOCK_EX) === false || !@rename($tmp, $file)) {
			@unlink($tmp);
			throw new DoajPaStateException('Cannot write the evaluation file.');
		}
		@chmod($file, 0600);
	}

	private static function requireCli() {
		if (PHP_SAPI !== 'cli') throw new DoajPaStateException('Test-only entry point.');
	}
}
