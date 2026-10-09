<?php

/**
 * @file classes/framework/DoajPaRuleRegistry.inc.php
 *
 * @class DoajPaRuleRegistry
 * @brief The rules a LIVE assessment evaluates. Stage 02 ships none: no approved DOAJ criteria exist
 *  (Stage 01 F2/F3; DoajPaSchema::APPROVED_DATASETS is empty), so every live run records "NOT IMPLEMENTED".
 *  Stage 03 adds reviewed rule classes here. There is no setting, request parameter or file lookup that can
 *  add rules; test rules are passed only by CLI test code (never reachable from a request).
 */

class DoajPaRuleRegistry {

	/** @return DoajPaCriterionRule[] */
	public static function liveRules() {
		return array();
	}
}
