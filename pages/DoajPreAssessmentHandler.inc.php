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
 * Denials of authenticated users are written to the audit log (best effort; never changes the decision).
 */

import('classes.handler.Handler');
require_once(dirname(__FILE__) . '/../classes/auth/DoajPaAccessPolicy.inc.php');
require_once(dirname(__FILE__) . '/../classes/gate/DoajPaEnglishGate.inc.php');
require_once(dirname(__FILE__) . '/../classes/http/DoajPaResponse.inc.php');
require_once(dirname(__FILE__) . '/../classes/migration/DoajPaSchemaInspector.inc.php');
require_once(dirname(__FILE__) . '/../classes/services/DoajPaAcknowledgementService.inc.php');

class DoajPreAssessmentHandler extends Handler {

	/** @copydoc PKPHandler::_isBackendPage */
	var $_isBackendPage = true;

	/** Routable ops and the HTTP methods each accepts. */
	const OPS = array(
		'index' => array('GET', 'HEAD'),
		'acknowledge' => array('POST'),
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

	/**
	 * Overview, or the English-only gate when it has not been acknowledged for this journal.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	public function index($args, $request) {
		$context = $request->getContext();
		$gate = new DoajPaEnglishGate(self::$_plugin);
		if (!$gate->isAcknowledged($context->getId())) {
			return $this->_displayGate($request, false);
		}

		$this->setupTemplate($request);
		$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
		$ojsVersion = $versionDao->getCurrentVersion();
		$pluginVersion = self::$_plugin->getCurrentVersion();
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'pageTitle' => __('plugins.generic.doajPreAssessment.displayName'),
			'doajPaJournalName' => $context->getLocalizedName(),
			'doajPaPluginVersion' => $pluginVersion ? $pluginVersion->getVersionString(false) : '',
			'doajPaOjsVersion' => $ojsVersion ? $ojsVersion->getVersionString(false) : '',
			'doajPaPhpVersion' => PHP_VERSION,
			'doajPaAcknowledgement' => $gate->getAcknowledgement($context->getId()),
			'doajPaWarningText' => DoajPaEnglishGate::WARNING_TEXT,
		));
		return $templateMgr->display(self::$_plugin->getTemplateResource('overview.tpl'));
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
