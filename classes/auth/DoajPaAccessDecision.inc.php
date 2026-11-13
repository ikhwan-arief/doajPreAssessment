<?php

/**
 * @file classes/auth/DoajPaAccessDecision.inc.php
 *
 * @class DoajPaAccessDecision
 * @brief Pure access decision for every plugin route (Stage 01 F9). No OJS dependency, so it can be
 *  unit-tested on every supported PHP version.
 *
 * PERMIT only when all of these hold:
 *  - an authenticated, non-disabled user;
 *  - a session that is not an OJS "Login as" (impersonated) session (ADR-002 R1.3);
 *  - a journal context;
 *  - (user_id === 1 AND verified site administrator) OR member of that journal's default
 *    "Journal manager" group (ADR-002 R1.2).
 * Every missing, unknown or non-boolean fact denies (fail closed).
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaAccessDecision {

	const ROOT_USER_ID = 1;

	const PERMIT_ROOT_SITE_ADMIN = 'permit_root_site_admin';
	const PERMIT_JOURNAL_MANAGER = 'permit_journal_manager';
	const DENY_ANONYMOUS = 'deny_anonymous';
	const DENY_USER_STATE = 'deny_user_disabled_or_unknown';
	const DENY_IMPERSONATED = 'deny_impersonated_session';
	const DENY_NO_CONTEXT = 'deny_no_context';
	const DENY_NOT_AUTHORIZED = 'deny_not_authorized';

	/**
	 * @param $facts array userId (int|null), userDisabled (bool|null), impersonated (bool|null), contextId (int|null),
	 *  isSiteAdmin (bool), isJournalManager (bool)
	 * @return array ['permit' => bool, 'reason' => string]
	 */
	public static function decide(array $facts) {
		$userId = array_key_exists('userId', $facts) ? $facts['userId'] : null;
		if (!is_int($userId) || $userId <= 0) return self::result(false, self::DENY_ANONYMOUS);

		if (!array_key_exists('userDisabled', $facts) || $facts['userDisabled'] !== false) {
			return self::result(false, self::DENY_USER_STATE);
		}

		if (!array_key_exists('impersonated', $facts) || $facts['impersonated'] !== false) {
			return self::result(false, self::DENY_IMPERSONATED);
		}

		$contextId = array_key_exists('contextId', $facts) ? $facts['contextId'] : null;
		if (!is_int($contextId) || $contextId <= 0) return self::result(false, self::DENY_NO_CONTEXT);

		$isSiteAdmin = array_key_exists('isSiteAdmin', $facts) ? $facts['isSiteAdmin'] : null;
		if ($userId === self::ROOT_USER_ID && $isSiteAdmin === true) {
			return self::result(true, self::PERMIT_ROOT_SITE_ADMIN);
		}

		$isJournalManager = array_key_exists('isJournalManager', $facts) ? $facts['isJournalManager'] : null;
		if ($isJournalManager === true) return self::result(true, self::PERMIT_JOURNAL_MANAGER);

		return self::result(false, self::DENY_NOT_AUTHORIZED);
	}

	/**
	 * @param $permit boolean
	 * @param $reason string
	 * @return array
	 */
	private static function result($permit, $reason) {
		return array('permit' => $permit, 'reason' => $reason);
	}
}
