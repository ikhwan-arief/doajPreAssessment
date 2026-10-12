<?php

/**
 * @file classes/auth/DoajPaAccessPolicy.inc.php
 *
 * @class DoajPaAccessPolicy
 * @brief Central authorization policy for every plugin route. Collects facts from the request and
 *  the OJS role tables, then applies DoajPaAccessDecision. Any error denies.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');
require_once(dirname(__FILE__) . '/DoajPaAccessDecision.inc.php');
require_once(dirname(__FILE__) . '/DoajPaRoleQuery.inc.php');

class DoajPaAccessPolicy extends AuthorizationPolicy {

	/** @var PKPRequest */
	private $_request;

	/**
	 * @param $request PKPRequest
	 */
	public function __construct($request) {
		parent::__construct('user.authorization.accessDenied');
		$this->_request = $request;
	}

	/**
	 * @copydoc AuthorizationPolicy::effect()
	 */
	public function effect() {
		return self::isRequestAuthorized($this->_request) ? AUTHORIZATION_PERMIT : AUTHORIZATION_DENY;
	}

	/**
	 * @param $request PKPRequest
	 * @return boolean
	 */
	public static function isRequestAuthorized($request) {
		$decision = self::decideRequest($request);
		return $decision['permit'] === true;
	}

	/**
	 * @param $request PKPRequest
	 * @return array ['permit' => bool, 'reason' => string]
	 */
	public static function decideRequest($request) {
		try {
			return DoajPaAccessDecision::decide(self::collectFacts($request));
		} catch (\Throwable $e) {
			error_log('doajPreAssessment: authorization check failed closed (' . get_class($e) . ')');
			return array('permit' => false, 'reason' => 'deny_error');
		}
	}

	/**
	 * @param $request PKPRequest
	 * @return array
	 */
	public static function collectFacts($request) {
		$facts = array(
			'userId' => null,
			'userDisabled' => null,
			'impersonated' => null,
			'contextId' => null,
			'isSiteAdmin' => false,
			'isJournalManager' => false,
		);

		$user = $request->getUser();
		if (is_a($user, 'User')) {
			$facts['userId'] = (int) $user->getId();
			$facts['userDisabled'] = (bool) $user->getDisabled();
		}

		// OJS "Login as" stores the original user ID in the session variable 'signedInAs'
		// (LoginHandler::signInAsUser()). Impersonated sessions are always denied (ADR-002 R1.3).
		$session = $request->getSession();
		if (is_object($session)) {
			$signedInAs = $session->getSessionVar('signedInAs');
			$facts['impersonated'] = !empty($signedInAs);
		}

		$context = $request->getContext();
		if (is_a($context, 'Context')) $facts['contextId'] = (int) $context->getId();

		if ($facts['userId'] === null || $facts['userDisabled'] !== false || $facts['impersonated'] !== false || !$facts['contextId']) {
			return $facts;
		}

		if ($facts['userId'] === DoajPaAccessDecision::ROOT_USER_ID) {
			$facts['isSiteAdmin'] = DoajPaRoleQuery::isSiteAdmin($facts['userId']);
		}
		$facts['isJournalManager'] = DoajPaRoleQuery::isDefaultJournalManager($facts['contextId'], $facts['userId']);
		return $facts;
	}
}
