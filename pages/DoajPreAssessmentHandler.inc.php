<?php

/**
 * @file pages/DoajPreAssessmentHandler.inc.php
 *
 * @class DoajPreAssessmentHandler
 * @brief Backend page handler: /index.php/{journalPath}/doajPreAssessment/{op}
 *
 * Request order, for every op:
 *  1. op allow-list: the OJS 3.3 page router routes any public method of the handler class
 *     (PKPPageRouter: get_class_methods(HANDLER_CLASS)), so every other op is answered with 404;
 *  2. central guard (DoajPaAccessPolicy), denial = HTTP 403 with no data;
 *  3. HTTP method allow-list (405), then CSRF on POST (403);
 *  4. schema present, otherwise 503 with remediation (manual-copy installs, ADR-004);
 *  5. English-only first-use gate before any other plugin function.
 * Assessment runs are started and advanced only by POST ops (runStart, runStep, runResume, runCancel): each one
 * is CSRF-protected, journal-scoped and bounded (one crawl step of at most STEP_SECONDS, or one evaluation).
 * Denials of authenticated users are written to the audit log (best effort; never changes the decision).
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import('classes.handler.Handler');
require_once(dirname(__FILE__) . '/../classes/auth/DoajPaAccessPolicy.inc.php');
require_once(dirname(__FILE__) . '/../classes/gate/DoajPaEnglishGate.inc.php');
require_once(dirname(__FILE__) . '/../classes/http/DoajPaResponse.inc.php');
require_once(dirname(__FILE__) . '/../classes/migration/DoajPaSchemaInspector.inc.php');
require_once(dirname(__FILE__) . '/../classes/services/DoajPaAcknowledgementService.inc.php');
require_once(dirname(__FILE__) . '/../classes/services/DoajPaReadService.inc.php');
require_once(dirname(__FILE__) . '/../classes/services/DoajPaCorrectiveActionService.inc.php');
require_once(dirname(__FILE__) . '/../classes/ui/DoajPaLabels.inc.php');
require_once(dirname(__FILE__) . '/../classes/framework/DoajPaAssessmentOrchestrator.inc.php');
require_once(dirname(__FILE__) . '/../classes/services/DoajPaReportExportService.inc.php');
require_once(dirname(__FILE__) . '/../classes/reports/DoajPaActionPlan.inc.php');

class DoajPreAssessmentHandler extends Handler {

	/** @copydoc PKPHandler::_isBackendPage */
	var $_isBackendPage = true;

	/** Routable ops and the HTTP methods each accepts. */
	const OPS = array(
		'index' => array('GET', 'HEAD'),
		'acknowledge' => array('POST'),
		'newAssessment' => array('GET', 'HEAD'),
		'runStart' => array('POST'),
		'runStep' => array('POST'),
		'runResume' => array('POST'),
		'runCancel' => array('POST'),
		'findings' => array('GET', 'HEAD'),
		'finding' => array('GET', 'HEAD'),
		'evidence' => array('GET', 'HEAD'),
		'formGuide' => array('GET', 'HEAD'),
		'beforeYouApply' => array('GET', 'HEAD'),
		'actions' => array('GET', 'HEAD'),
		'actionCreate' => array('POST'),
		'actionEdit' => array('GET', 'HEAD'),
		'actionUpdate' => array('POST'),
		'history' => array('GET', 'HEAD'),
		'run' => array('GET', 'HEAD'),
		'compare' => array('GET', 'HEAD'),
		'reports' => array('GET', 'HEAD'),
		'reportCreate' => array('POST'),
		'reportDownload' => array('GET', 'HEAD'),
		'settings' => array('GET', 'HEAD'),
	);

	/** Navigation (Stage 01 F8 order): op => locale key suffix. */
	const NAV = array(
		'index' => 'overview', 'newAssessment' => 'newAssessment', 'findings' => 'findings', 'evidence' => 'evidence',
		'formGuide' => 'formGuide', 'beforeYouApply' => 'beforeYouApply', 'actions' => 'actions', 'history' => 'history',
		'reports' => 'reports', 'settings' => 'settings',
	);

	/** @var DoajPreAssessmentPlugin */
	private static $_plugin;

	/**
	 * @param $plugin DoajPreAssessmentPlugin
	 */
	public static function setPlugin($plugin) {
		self::$_plugin = $plugin;
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$op = (string) $request->getRouter()->getRequestedOp($request);
		if (!array_key_exists($op, self::OPS)) DoajPaResponse::notFound();

		$this->addPolicy(new DoajPaAccessPolicy($request));
		if (!parent::authorize($request, $args, $roleAssignments)) {
			self::_auditDenial($request, $op);
			DoajPaResponse::forbidden();
		}

		$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '');
		if (!in_array($method, self::OPS[$op], true)) DoajPaResponse::methodNotAllowed(self::OPS[$op]);
		if ($method === 'POST' && !$request->checkCSRF()) DoajPaResponse::forbidden();
		if (!DoajPaSchemaInspector::isInstalled()) DoajPaResponse::schemaMissing();

		return true;
	}

	/**
	 * Record a denied request of an authenticated user in a journal. Anonymous requests are not logged.
	 * Any failure here is swallowed: the request is denied either way.
	 * @param $request PKPRequest
	 * @param $op string
	 */
	private static function _auditDenial($request, $op) {
		try {
			$user = $request->getUser();
			$context = $request->getContext();
			if (!is_a($user, 'User') || !is_a($context, 'Context') || !DoajPaSchemaInspector::isInstalled()) return;
			$decision = DoajPaAccessPolicy::decideRequest($request);
			$session = $request->getSession();
			$signedInAs = is_object($session) ? $session->getSessionVar('signedInAs') : null;
			DoajPaAuditService::record((int) $context->getId(), 'access_denied',
				DoajPaDb::userActor((int) $user->getId(), (string) $user->getUsername()), null, null, null,
				array('reason' => $decision['reason'], 'op' => $op,
					'method' => isset($_SERVER['REQUEST_METHOD']) ? substr((string) $_SERVER['REQUEST_METHOD'], 0, 10) : '',
					'impersonatedBy' => empty($signedInAs) ? null : (int) $signedInAs));
		} catch (\Throwable $e) {
			error_log('doajPreAssessment: denial audit failed (' . get_class($e) . ')');
		}
	}

	//
	// Views (GET/HEAD). Every view requires the English-only gate; every record lookup is journal-scoped.
	//

	/**
	 * Overview (dashboard), or the English-only gate when it has not been acknowledged for this journal.
	 */
	public function index($args, $request) {
		self::_noArgs($args);
		$gate = new DoajPaEnglishGate(self::$_plugin);
		if (!$gate->isAcknowledged($request->getContext()->getId())) {
			return $this->_displayGate($request, false);
		}
		$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
		$ojsVersion = $versionDao->getCurrentVersion();
		return $this->_render($request, 'overview.tpl', 'index', array(
			'doajPaDashboard' => DoajPaReadService::dashboard(self::_contextId($request)),
			'doajPaOjsVersion' => $ojsVersion ? $ojsVersion->getVersionString(false) : '',
			'doajPaPhpVersion' => PHP_VERSION,
			'doajPaAcknowledgement' => $gate->getAcknowledgement($request->getContext()->getId()),
			'doajPaWarningText' => DoajPaEnglishGate::WARNING_TEXT,
		));
	}

	/** New Assessment: the per-run English-only acknowledgement and the start form. */
	public function newAssessment($args, $request) {
		$this->_requireGate($request, $args);
		return $this->_renderNewAssessment($request, null, 200);
	}

	/** Criteria & Findings. */
	public function findings($args, $request) {
		$this->_requireGate($request, $args);
		$contextId = self::_contextId($request);
		return $this->_render($request, 'findings.tpl', 'findings', array(
			'doajPaDefinitions' => DoajPaReadService::definitions(),
			'doajPaFindings' => DoajPaReadService::findings($contextId),
		));
	}

	/** One finding: per-run observation timeline, transitions, actions, DOAJ cross-check. */
	public function finding($args, $request) {
		$this->_requireGate($request, array());
		$findingId = self::_idArg($args);
		try {
			$finding = DoajPaReadService::finding(self::_contextId($request), $findingId);
		} catch (DoajPaNotFoundException $e) {
			DoajPaResponse::notFound();
		}
		return $this->_render($request, 'finding.tpl', 'findings', array('doajPaFinding' => $finding));
	}

	/** Evidence and pages read, for one run (default: latest completed, else latest). */
	public function evidence($args, $request) {
		$this->_requireGate($request, $args);
		$contextId = self::_contextId($request);
		$runs = DoajPaReadService::runs($contextId);
		$runId = self::_idParam($request, 'run');
		if ($runId === null) {
			foreach ($runs as $r) { if ($r['status'] === 'completed') { $runId = (int) $r['run_id']; break; } }
			if ($runId === null && $runs) $runId = (int) $runs[0]['run_id'];
		}
		$run = null;
		if ($runId !== null) {
			try {
				$run = DoajPaReadService::run($contextId, $runId);
			} catch (DoajPaNotFoundException $e) {
				DoajPaResponse::notFound();
			}
		}
		return $this->_render($request, 'evidence.tpl', 'evidence', array('doajPaRuns' => $runs, 'doajPaRun' => $run));
	}

	/** DOAJ Form Guide: FQ-01..FQ-20, each "Not yet assessed" until approved criteria exist (no invented answers). */
	public function formGuide($args, $request) {
		$this->_requireGate($request, $args);
		return $this->_render($request, 'formGuide.tpl', 'formGuide', array(
			'doajPaFormGuide' => DoajPaReadService::formGuide(self::_contextId($request)),
			'doajPaFormGuideNote' => DoajPaAssessmentOrchestrator::FQ_NOT_YET_ASSESSED,
			'doajPaCandidateRulebook' => DoajPaAssessmentOrchestrator::CANDIDATE_RULEBOOK,
		));
	}

	/**
	 * Before You Apply (Stage 01 F7 item 4, AC-14): the corrections of one completed run (default: the latest), grouped by
	 * the website page to correct, with the journal's corrective-action rows for each finding. ?run= selects a run.
	 */
	public function beforeYouApply($args, $request) {
		$this->_requireGate($request, $args);
		$contextId = self::_contextId($request);
		$completed = array_values(array_filter(DoajPaReadService::runs($contextId), function ($r) { return $r['status'] === 'completed'; }));
		$runId = self::_idParam($request, 'run');
		if ($runId === null && $completed) $runId = (int) $completed[0]['run_id'];
		$run = null; $plan = null;
		if ($runId !== null) {
			try {
				$run = DoajPaReadService::run($contextId, $runId);
			} catch (DoajPaNotFoundException $e) {
				DoajPaResponse::notFound();
			}
			if ($run['status'] !== 'completed') DoajPaResponse::notFound();
			$plan = DoajPaActionPlan::build($run, DoajPaReadService::actions($contextId));
		}
		return $this->_render($request, 'beforeYouApply.tpl', 'beforeYouApply', array('doajPaRun' => $run, 'doajPaPlan' => $plan, 'doajPaCompletedRuns' => $completed));
	}

	/** Corrective Actions: register and create form. */
	public function actions($args, $request) {
		$this->_requireGate($request, $args);
		$form = self::_emptyActionForm();
		$preselect = self::_idValue($request->getUserVar('findingId'));
		if ($preselect) {
			$form['findingId'] = (string) $preselect;
			// From Before You Apply (?run=): prefill the run's recommended correction, the page and the suggested priority.
			$fromRun = self::_idValue($request->getUserVar('run'));
			if ($fromRun) {
				try {
					$plan = DoajPaActionPlan::build(DoajPaReadService::run(self::_contextId($request), $fromRun), array());
					foreach ($plan['groups'] as $g) foreach ($g['tasks'] as $t) {
						if ($t['findingId'] !== $preselect) continue;
						$form['recommendation'] = (string) ($t['recommendation'] !== null ? $t['recommendation'] : $t['rationale']);
						$form['targetUrl'] = (string) $g['page'];
						$form['priority'] = $t['suggestedPriority'];
					}
				} catch (DoajPaNotFoundException $e) {
					// another journal's or unknown run: nothing is prefilled
				}
			}
		}
		return $this->_renderActions($request, $form, null, 200);
	}

	/** Edit one corrective action. */
	public function actionEdit($args, $request) {
		$this->_requireGate($request, array());
		$actionId = self::_idArg($args);
		try {
			$action = DoajPaReadService::action(self::_contextId($request), $actionId);
		} catch (DoajPaNotFoundException $e) {
			DoajPaResponse::notFound();
		}
		return $this->_render($request, 'actionEdit.tpl', 'actions', array('doajPaAction' => $action, 'doajPaForm' => null, 'doajPaError' => null));
	}

	/** Assessment History. */
	public function history($args, $request) {
		$this->_requireGate($request, $args);
		return $this->_render($request, 'history.tpl', 'history', array('doajPaRuns' => DoajPaReadService::runs(self::_contextId($request))));
	}

	/** One run: progress and controls while active; snapshot, coverage, results and observations. */
	public function run($args, $request) {
		$this->_requireGate($request, array());
		return $this->_renderRun($request, self::_idArg($args), null, 200);
	}

	/** Compare two runs through their observations (?from=&to=). */
	public function compare($args, $request) {
		$this->_requireGate($request, $args);
		$from = self::_idParam($request, 'from');
		$to = self::_idParam($request, 'to');
		if ($from === null || $to === null) {
			$request->redirect(null, DoajPreAssessmentPlugin::PAGE, 'history');
		}
		try {
			$comparison = DoajPaReadService::compare(self::_contextId($request), $from, $to);
		} catch (DoajPaNotFoundException $e) {
			DoajPaResponse::notFound();
		}
		return $this->_render($request, 'compare.tpl', 'history', array('doajPaCompare' => $comparison));
	}

	/** Reports (Stage 04, AC-18): generate HTML, PDF or DOCX reports of completed runs; private, guarded downloads. */
	public function reports($args, $request) {
		$this->_requireGate($request, $args);
		return $this->_renderReports($request, null, 200);
	}

	/** Generate one report of a completed run (POST + CSRF, checked in authorize()). */
	public function reportCreate($args, $request) {
		$this->_requireGate($request, $args);
		$contextId = self::_contextId($request);
		$runId = self::_idValue($request->getUserVar('runId'));
		$format = $request->getUserVar('format');
		if (!$runId || !is_string($format) || !in_array($format, DoajPaSchema::EXPORT_FORMATS, true)) return $this->_renderReports($request, 'invalid', 400);
		$context = $request->getContext();
		$pluginVersion = self::$_plugin->getCurrentVersion();
		try {
			$id = DoajPaReportExportService::generate($contextId, $runId, $format,
				array('name' => (string) $context->getLocalizedName(), 'url' => $request->url(null, 'index')), self::_actor($request),
				$pluginVersion ? $pluginVersion->getVersionString(false) : '', (string) Config::getVar('files', 'files_dir'));
		} catch (DoajPaNotFoundException $e) {
			return $this->_renderReports($request, 'invalid', 404);
		} catch (DoajPaQuotaException $e) {
			return $this->_renderReports($request, 'quota', 429);
		} catch (DoajPaStateException $e) {
			return $this->_renderReports($request, 'notReady', 409);
		}
		$request->redirect(null, DoajPreAssessmentPlugin::PAGE, 'reports', null, array('saved' => $id));
	}

	/**
	 * Download one report: authorization, journal and run are checked on every request (authorize(), F8); the file is
	 * read from the private reports directory and must still have its recorded SHA-256. Sent as an attachment, never
	 * cached, with a sandboxing Content-Security-Policy.
	 */
	public function reportDownload($args, $request) {
		$this->_requireGate($request, array());
		$exportId = self::_idArg($args);
		$contextId = self::_contextId($request);
		try {
			$file = DoajPaReportExportService::file($contextId, $exportId, (string) Config::getVar('files', 'files_dir'));
		} catch (DoajPaNotFoundException $e) {
			DoajPaResponse::notFound();
		} catch (DoajPaStateException $e) {
			DoajPaResponse::terminate(409, '409 Conflict. The report file no longer matches its recorded hash and is not served. Generate the report again.');
		}
		if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'GET') {
			DoajPaAuditService::record($contextId, 'report_export_downloaded', self::_actor($request), $file['runId'], 'report_export', $file['exportId'], array('format' => $file['format']));
		}
		header('Content-Type: ' . $file['mediaType']);
		header('Content-Length: ' . strlen($file['bytes']));
		header('Content-Disposition: attachment; filename="' . $file['fileName'] . '"');
		header('Cache-Control: no-store, private');
		header('X-Content-Type-Options: nosniff');
		header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
		header('X-Report-SHA256: ' . $file['sha256']);
		echo $file['bytes'];
		exit;
	}

	/** Settings: plugin and schema status, acknowledgements and the audit log (read-only). */
	public function settings($args, $request) {
		$this->_requireGate($request, $args);
		$contextId = self::_contextId($request);
		$pluginVersion = self::$_plugin->getCurrentVersion();
		return $this->_render($request, 'settings.tpl', 'settings', array(
			'doajPaSchemaProblems' => DoajPaSchemaInspector::problems(),
			'doajPaSchemaVersion' => DoajPaSchema::VERSION,
			'doajPaPluginVersionFull' => $pluginVersion ? $pluginVersion->getVersionString(false) : '',
			'doajPaAcknowledgements' => DoajPaReadService::firstUseAcknowledgements($contextId),
			'doajPaAudit' => DoajPaReadService::auditEvents($contextId),
		));
	}

	//
	// Corrective action changes (POST + CSRF, checked in authorize()). Optimistic locking on update.
	//

	/** Create a corrective action. */
	public function actionCreate($args, $request) {
		$this->_requireGate($request, $args);
		$form = self::_actionForm($request, true);
		$data = $form['data'];
		$findingId = self::_idValue($request->getUserVar('findingId'));
		if ($findingId === false) return $this->_renderActions($request, $form['raw'], 'invalidFinding', 400);
		if ($findingId !== null) $data['findingId'] = $findingId;
		try {
			$id = DoajPaCorrectiveActionService::create(self::_contextId($request), self::_actor($request), $data);
		} catch (DoajPaValidationException $e) {
			return $this->_renderActions($request, $form['raw'], 'invalid', 400);
		} catch (DoajPaNotFoundException $e) {
			return $this->_renderActions($request, $form['raw'], 'invalidFinding', 400);
		}
		$request->redirect(null, DoajPreAssessmentPlugin::PAGE, 'actions', null, array('saved' => $id));
	}

	/** Update a corrective action; a stale lockVersion gives HTTP 409 and saves nothing. */
	public function actionUpdate($args, $request) {
		$this->_requireGate($request, array());
		$actionId = self::_idArg($args);
		$contextId = self::_contextId($request);
		try {
			$current = DoajPaReadService::action($contextId, $actionId);
		} catch (DoajPaNotFoundException $e) {
			DoajPaResponse::notFound();
		}
		$form = self::_actionForm($request, false);
		$lock = $request->getUserVar('lockVersion');
		if (!is_string($lock) || !preg_match('/^(0|[1-9][0-9]{0,9})$/', $lock)) {
			return $this->_render($request, 'actionEdit.tpl', 'actions', array('doajPaAction' => $current, 'doajPaForm' => $form['raw'], 'doajPaError' => 'invalid'), 400);
		}
		try {
			DoajPaCorrectiveActionService::update($contextId, $actionId, (int) $lock, $form['data'], self::_actor($request));
		} catch (DoajPaConcurrencyException $e) {
			return $this->_render($request, 'actionEdit.tpl', 'actions', array('doajPaAction' => $current, 'doajPaForm' => $form['raw'], 'doajPaError' => 'conflict'), 409);
		} catch (DoajPaValidationException $e) {
			return $this->_render($request, 'actionEdit.tpl', 'actions', array('doajPaAction' => $current, 'doajPaForm' => $form['raw'], 'doajPaError' => 'invalid'), 400);
		}
		$request->redirect(null, DoajPreAssessmentPlugin::PAGE, 'actions', null, array('saved' => $actionId));
	}

	//
	// Assessment runs (POST + CSRF, checked in authorize()). Every run is created with its own acknowledgement.
	//

	/** Start a run: per-run English-only acknowledgement, target validation, then the first crawl action. */
	public function runStart($args, $request) {
		$this->_requireGate($request, $args);
		$confirmed = $request->getUserVar('confirmEnglishOnly') === '1';
		$shownHash = $request->getUserVar('warningSha256');
		if (!$confirmed || !is_string($shownHash) || !hash_equals(DoajPaEnglishGate::warningSha256(), $shownHash)) {
			return $this->_renderNewAssessment($request, 'acknowledgement', 400);
		}
		// Assessment profile: approved criteria only (default), or the explicitly confirmed candidate criteria (Stage 03).
		$profile = $request->getUserVar('profile');
		if ($profile === null || $profile === '') $profile = DoajPaAssessmentOrchestrator::PROFILE_APPROVED;
		if (!in_array($profile, array(DoajPaAssessmentOrchestrator::PROFILE_APPROVED, DoajPaAssessmentOrchestrator::PROFILE_CANDIDATE), true)) {
			return $this->_renderNewAssessment($request, 'profile', 400);
		}
		if ($profile === DoajPaAssessmentOrchestrator::PROFILE_CANDIDATE && $request->getUserVar('confirmCandidate') !== '1') {
			return $this->_renderNewAssessment($request, 'candidate', 400);
		}
		$contextId = self::_contextId($request);
		if (DoajPaReadService::activeRun($contextId)) return $this->_renderNewAssessment($request, 'activeRun', 409);
		self::_extendTimeLimit();
		try {
			$homepage = DoajPaAssessmentOrchestrator::target($request, $request->getContext());
			$runId = DoajPaAssessmentOrchestrator::start($contextId, $homepage, self::_actor($request),
				array('confirmed' => true, 'warningSha256' => $shownHash), $profile);
		} catch (DoajPaStateException $e) {
			return $this->_renderNewAssessment($request, DoajPaReadService::activeRun($contextId) ? 'activeRun' : 'cannotStart', 409);
		} catch (DoajPaConcurrencyException $e) {
			return $this->_renderNewAssessment($request, 'activeRun', 409);
		}
		$request->redirect(null, DoajPreAssessmentPlugin::PAGE, 'run', array((string) $runId), array('step' => 'started'));
	}

	/** One bounded step of a running run, then back to the run page (POST-redirect-GET). */
	public function runStep($args, $request) {
		$this->_requireGate($request, array());
		$runId = self::_idArg($args);
		self::_extendTimeLimit();
		try {
			$result = DoajPaAssessmentOrchestrator::advance(self::_contextId($request), $runId, self::_actor($request));
		} catch (DoajPaNotFoundException $e) {
			DoajPaResponse::notFound();
		} catch (DoajPaStateException $e) {
			$result = array('phase' => 'notLive');
		}
		$request->redirect(null, DoajPreAssessmentPlugin::PAGE, 'run', array((string) $runId), array('step' => $result['phase']));
	}

	/** Resume an interrupted run (optimistic lock). */
	public function runResume($args, $request) {
		return $this->_runChange($args, $request, 'resume');
	}

	/** Cancel an active run (optimistic lock). Records stay; the run is never sealed. */
	public function runCancel($args, $request) {
		return $this->_runChange($args, $request, 'cancel');
	}

	private function _runChange($args, $request, $change) {
		$this->_requireGate($request, array());
		$runId = self::_idArg($args);
		$contextId = self::_contextId($request);
		try {
			DoajPaAssessmentRunService::getRun($contextId, $runId);
		} catch (DoajPaNotFoundException $e) {
			DoajPaResponse::notFound();
		}
		$lock = $request->getUserVar('lockVersion');
		if (!is_string($lock) || !preg_match('/^(0|[1-9][0-9]{0,9})$/', $lock)) return $this->_renderRun($request, $runId, 'invalid', 400);
		try {
			if ($change === 'resume') DoajPaAssessmentOrchestrator::resume($contextId, $runId, (int) $lock, self::_actor($request));
			else DoajPaAssessmentOrchestrator::cancel($contextId, $runId, (int) $lock, self::_actor($request));
		} catch (DoajPaConcurrencyException $e) {
			return $this->_renderRun($request, $runId, 'conflict', 409);
		} catch (DoajPaStateException $e) {
			return $this->_renderRun($request, $runId, 'state', 409);
		}
		$request->redirect(null, DoajPreAssessmentPlugin::PAGE, 'run', array((string) $runId), array('step' => $change === 'resume' ? 'resumed' : 'cancelled'));
	}

	//
	// Helpers
	//

	/** Step outcomes shown on the run page after a redirect (anything else is ignored). */
	const STEP_NOTICES = array('started', 'crawling', 'busy', 'completed', 'interrupted', 'conflict', 'notLive', 'resumed', 'cancelled');

	private function _renderNewAssessment($request, $error, $status) {
		$contextId = self::_contextId($request);
		$problem = null;
		try {
			DoajPaAssessmentOrchestrator::target($request, $request->getContext());
		} catch (DoajPaStateException $e) {
			$problem = $e->getMessage();
		}
		$pdfTool = DoajPaPdfExtractor::toolVersion();
		return $this->_render($request, 'newAssessment.tpl', 'newAssessment', array(
			'doajPaWarningText' => DoajPaEnglishGate::WARNING_TEXT,
			'doajPaCheckboxText' => DoajPaEnglishGate::CHECKBOX_TEXT,
			'doajPaWarningSha256' => DoajPaEnglishGate::warningSha256(),
			'doajPaActiveRun' => DoajPaReadService::activeRun($contextId),
			'doajPaTargetProblem' => $problem,
			'doajPaError' => $error,
			'doajPaPdfTool' => $pdfTool,
			'doajPaPdfAvailable' => $pdfTool !== 'unavailable',
			'doajPaRulebook' => DoajPaAssessmentOrchestrator::LIVE_RULEBOOK,
			'doajPaCandidateRulebook' => DoajPaAssessmentOrchestrator::CANDIDATE_RULEBOOK,
			'doajPaFutureModules' => DoajPaAssessmentOrchestrator::FUTURE_MODULES,
		), $status);
	}

	private function _renderRun($request, $runId, $error, $status) {
		$contextId = self::_contextId($request);
		try {
			$run = DoajPaReadService::run($contextId, $runId);
		} catch (DoajPaNotFoundException $e) {
			DoajPaResponse::notFound();
		}
		$notice = $request->getUserVar('step');
		$active = in_array($run['status'], array('running', 'interrupted'), true);
		return $this->_render($request, 'run.tpl', 'history', array(
			'doajPaRun' => $run,
			'doajPaProgress' => $active ? DoajPaCrawlService::progress($contextId, $runId) : null,
			'doajPaStepNotice' => is_string($notice) && in_array($notice, self::STEP_NOTICES, true) ? $notice : null,
			'doajPaError' => $error,
			'doajPaCanAdvance' => $run['status'] === 'running' && in_array($run['rulebook_version'], array(DoajPaAssessmentOrchestrator::LIVE_RULEBOOK, DoajPaAssessmentOrchestrator::CANDIDATE_RULEBOOK), true),
			'doajPaCanCancel' => in_array($run['status'], DoajPaSchema::RUN_ACTIVE, true),
			'doajPaFutureModules' => DoajPaAssessmentOrchestrator::FUTURE_MODULES,
		), $status);
	}

	/** A step may wait on one slow fetch after its budget; do not let PHP stop it half-way. */
	private static function _extendTimeLimit() {
		if (function_exists('set_time_limit')) @set_time_limit(120);
	}

	/**
	 * Common page rendering: backend layout, plugin stylesheet, navigation and labels.
	 * @return string
	 */
	private function _render($request, $template, $navKey, $vars, $status = 200) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->addStyleSheet('doajPreAssessment', $request->getBaseUrl() . '/' . self::$_plugin->getPluginPath() . '/css/doajpa.css',
			array('contexts' => 'backend'));
		$nav = array();
		foreach (self::NAV as $op => $key) {
			$nav[] = array('url' => $request->url(null, DoajPreAssessmentPlugin::PAGE, $op), 'labelKey' => 'plugins.generic.doajPreAssessment.nav.' . $key,
				'current' => $op === $navKey);
		}
		$pluginVersion = self::$_plugin->getCurrentVersion();
		$templateMgr->assign(array_merge(array(
			'pageTitle' => __('plugins.generic.doajPreAssessment.displayName'),
			'doajPaNav' => self::$_plugin->getTemplateResource('partials/nav.tpl'),
			'doajPaNotResult' => self::$_plugin->getTemplateResource('partials/notResult.tpl'),
			'doajPaBadge' => self::$_plugin->getTemplateResource('partials/badge.tpl'),
			'doajPaRefs' => self::$_plugin->getTemplateResource('partials/refs.tpl'),
			'doajPaLink' => self::$_plugin->getTemplateResource('partials/link.tpl'),
			'doajPaActionFields' => self::$_plugin->getTemplateResource('partials/actionFields.tpl'),
			'doajPaNavItems' => $nav,
			'doajPaLabels' => DoajPaLabels::all(),
			'doajPaJournalName' => $request->getContext()->getLocalizedName(),
			'doajPaPluginVersion' => $pluginVersion ? $pluginVersion->getVersionString(false) : '',
			'doajPaPage' => DoajPreAssessmentPlugin::PAGE,
			'doajPaReferences' => array('https://doaj.org/apply/guide/', 'https://doaj.org/apply/transparency/', 'https://doaj.org/apply/copyright-and-licensing/'),
		), $vars));
		if ($status !== 200) http_response_code($status);
		return $templateMgr->display(self::$_plugin->getTemplateResource($template));
	}

	private function _renderActions($request, $formValues, $error, $status) {
		$contextId = self::_contextId($request);
		return $this->_render($request, 'actions.tpl', 'actions', array(
			'doajPaActions' => DoajPaReadService::actions($contextId),
			'doajPaFindingChoices' => DoajPaReadService::findingChoices($contextId),
			'doajPaForm' => $formValues,
			'doajPaError' => $error,
			'doajPaSaved' => self::_idValue($request->getUserVar('saved')),
		), $status);
	}

	private function _renderReports($request, $error, $status) {
		$contextId = self::_contextId($request);
		$completed = array_values(array_filter(DoajPaReadService::runs($contextId), function ($r) { return $r['status'] === 'completed'; }));
		return $this->_render($request, 'reports.tpl', 'reports', array(
			'doajPaExports' => DoajPaReadService::exports($contextId),
			'doajPaCompletedRuns' => $completed,
			'doajPaFormats' => DoajPaSchema::EXPORT_FORMATS,
			'doajPaError' => $error,
			'doajPaSaved' => self::_idValue($request->getUserVar('saved')),
		), $status);
	}

	/** Views other than the overview need the first-use acknowledgement; otherwise go to the gate. */
	private function _requireGate($request, $args) {
		self::_noArgs($args);
		$gate = new DoajPaEnglishGate(self::$_plugin);
		if (!$gate->isAcknowledged($request->getContext()->getId())) {
			$request->redirect(null, DoajPreAssessmentPlugin::PAGE, 'index');
		}
	}

	/** @return array Empty form values (every field present) */
	private static function _emptyActionForm() {
		return array('recommendation' => '', 'targetUrl' => '', 'ownerText' => '', 'dueDate' => '', 'priority' => 'medium',
			'status' => 'planned', 'completionEvidence' => '', 'findingId' => '', 'lockVersion' => '');
	}

	/** @return array ['data' => service input, 'raw' => submitted strings for redisplay] */
	private static function _actionForm($request, $creating) {
		$raw = array();
		foreach (array('recommendation', 'targetUrl', 'ownerText', 'dueDate', 'priority', 'status', 'completionEvidence', 'findingId', 'lockVersion') as $field) {
			$value = $request->getUserVar($field);
			$raw[$field] = is_string($value) ? $value : '';
		}
		$data = array();
		foreach (array('recommendation', 'priority') as $field) $data[$field] = $raw[$field];
		foreach (array('targetUrl', 'ownerText', 'dueDate') as $field) $data[$field] = trim($raw[$field]) === '' ? null : trim($raw[$field]);
		if (!$creating) {
			$data['status'] = $raw['status'];
			$data['completionEvidence'] = trim($raw['completionEvidence']) === '' ? null : $raw['completionEvidence'];
		}
		return array('data' => $data, 'raw' => $raw);
	}

	private static function _actor($request) {
		$user = $request->getUser();
		return DoajPaDb::userActor((int) $user->getId(), (string) $user->getUsername());
	}

	private static function _contextId($request) {
		return (int) $request->getContext()->getId();
	}

	/** Ops without path arguments refuse extra path segments. */
	private static function _noArgs($args) {
		if (!empty($args)) DoajPaResponse::notFound();
	}

	/** @return int The single numeric path argument, or plugin 404 */
	private static function _idArg($args) {
		if (!is_array($args) || count($args) !== 1) DoajPaResponse::notFound();
		$id = self::_idValue($args[0]);
		if (!$id) DoajPaResponse::notFound();
		return $id;
	}

	/** @return int|null Optional numeric query parameter; malformed values give plugin 404 */
	private static function _idParam($request, $name) {
		$id = self::_idValue($request->getUserVar($name));
		if ($id === false) DoajPaResponse::notFound();
		return $id;
	}

	/** @return int|null|false null when empty, false when malformed */
	private static function _idValue($value) {
		if ($value === null || $value === '') return null;
		if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,17}$/', $value)) return false;
		return (int) $value;
	}

	/**
	 * Record the English-only acknowledgement for this journal (POST + CSRF, checked in authorize()).
	 * @param $args array
	 * @param $request PKPRequest
	 */
	public function acknowledge($args, $request) {
		$confirmed = $request->getUserVar('confirmEnglishOnly') === '1';
		$shownHash = $request->getUserVar('warningSha256');
		if (!$confirmed || !is_string($shownHash) || !hash_equals(DoajPaEnglishGate::warningSha256(), $shownHash)) {
			http_response_code(400);
			return $this->_displayGate($request, true);
		}

		$context = $request->getContext();
		$user = $request->getUser();
		$actor = DoajPaDb::userActor((int) $user->getId(), (string) $user->getUsername());
		DoajPaDb::transaction(function () use ($context, $actor) {
			DoajPaAcknowledgementService::recordFirstUse((int) $context->getId(), $actor);
		});
		// Written after the commit: OJS caches plugin settings, so a rolled-back write must never reach the cache.
		$gate = new DoajPaEnglishGate(self::$_plugin);
		$gate->acknowledge($context->getId(), $user->getId(), $user->getUsername());
		$request->redirect(null, DoajPreAssessmentPlugin::PAGE, 'index');
	}

	/**
	 * @param $request PKPRequest
	 * @param $showError boolean
	 */
	private function _displayGate($request, $showError) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->addStyleSheet('doajPreAssessment', $request->getBaseUrl() . '/' . self::$_plugin->getPluginPath() . '/css/doajpa.css',
			array('contexts' => 'backend'));
		$templateMgr->assign(array(
			'pageTitle' => __('plugins.generic.doajPreAssessment.gate.title'),
			'doajPaWarningText' => DoajPaEnglishGate::WARNING_TEXT,
			'doajPaCheckboxText' => DoajPaEnglishGate::CHECKBOX_TEXT,
			'doajPaWarningSha256' => DoajPaEnglishGate::warningSha256(),
			'doajPaShowError' => $showError,
		));
		return $templateMgr->display(self::$_plugin->getTemplateResource('gate.tpl'));
	}
}
