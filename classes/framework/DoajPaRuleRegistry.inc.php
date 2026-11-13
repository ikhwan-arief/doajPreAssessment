<?php

/**
 * @file classes/framework/DoajPaRuleRegistry.inc.php
 *
 * @class DoajPaRuleRegistry
 * @brief The rules an assessment evaluates.
 *  - liveRules(): rules from the APPROVED criteria dataset (DoajPaApprovedDataset), only when
 *    DoajPaSchema::APPROVED_DATASETS names it; otherwise none, and the default profile records "NOT IMPLEMENTED".
 *  - candidateRules(): the PROPOSED dataset (Stage 03, PENDING APPROVAL D-03-1). Used only when a Journal Manager
 *    explicitly selects the candidate profile for one run; results are labelled as candidate, not approved.
 *  There is no setting, request parameter or file lookup that can add rules; test rules are passed only by CLI test
 *  code (never reachable from a request).
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaRuleRegistry {

	/** @return DoajPaCriterionRule[] */
	public static function liveRules() {
		if (!DoajPaSchema::APPROVED_DATASETS) return array();
		require_once(dirname(__FILE__) . '/../rules/DoajPaApprovedRule.inc.php');
		return DoajPaApprovedRule::all();
	}

	/** @return DoajPaCriterionRule[] Candidate (proposed, not approved) criteria */
	public static function candidateRules() {
		require_once(dirname(__FILE__) . '/../rules/DoajPaCandidateRule.inc.php');
		return DoajPaCandidateRule::all();
	}
}
