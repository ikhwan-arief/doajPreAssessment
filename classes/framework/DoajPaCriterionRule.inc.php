<?php

/**
 * @file classes/framework/DoajPaCriterionRule.inc.php
 *
 * @brief Contract for one versioned, deterministic DOAJ criterion rule (Stage 02.07; rules arrive in Stage 03).
 *  A rule only reads the run's stored public evidence through DoajPaEvaluationContext and returns an outcome.
 *  DoajPaOutcomeGuard then verifies the outcome (quotes, language, evidence ownership) before anything is stored.
 *
 * Outcome array:
 *   status           one of DoajPaSchema::CRITERION_STATUSES
 *   rationale        string; interpretationLimit string
 *   evidenceIds      int[] evidence records the verdict relies on (same run)
 *   quotes           [['evidenceId' => int, 'text' => string]] exact excerpts that must appear verbatim
 *   detections       [['subtype','targetType','target','criterionStatus','evidenceId']] deficiencies found
 *   verifiedClear    [['subtype','targetType','target','evidenceId']] earlier deficiencies now verified corrected
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

interface DoajPaCriterionRule {
	/** @return string Stable criterion key (family) */
	public function key();
	/** @return int Criterion version */
	public function version();
	/** @return int First version whose findings stay comparable (lineage) */
	public function lineage();
	/** @return string M|R|C|H */
	public function level();
	/** @return string Criteria dataset version (approved datasets only in production: DoajPaSchema::APPROVED_DATASETS) */
	public function datasetVersion();
	/** @return string Approved DOAJ source version / capture date */
	public function sourceVersion();
	/** @return string English title */
	public function title();
	/** @return string One of the three official DOAJ pages */
	public function doajReferenceUrl();
	/** @return string[] Page types whose evidence the rule needs (see DoajPaPageClassifier) */
	public function requiredEvidence();
	/** @return string What a human must still judge (never automated) */
	public function humanReviewBoundary();
	/** @return string Reference for the corrective recommendation text */
	public function recommendationReference();
	/**
	 * @param $context DoajPaEvaluationContext
	 * @return array Outcome (see file comment)
	 */
	public function evaluate($context);
}
