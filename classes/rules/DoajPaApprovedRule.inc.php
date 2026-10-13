<?php

/**
 * @file classes/rules/DoajPaApprovedRule.inc.php
 *
 * @class DoajPaApprovedRule
 * @brief One criterion of the APPROVED dataset (DoajPaApprovedDataset, written by tools/apply-approval.py from the
 *  signed approval record). Its specification is the reviewed candidate criterion, unchanged; it is registered as
 *  version 2 of the same lineage (decision D-03-12), so stored candidate results are never mistaken for it.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/DoajPaCandidateRule.inc.php');
require_once(dirname(__FILE__) . '/data/DoajPaApprovedDataset.inc.php');

class DoajPaApprovedRule extends DoajPaCandidateRule {

	const VERSION = 2;

	/** @return DoajPaApprovedRule[] All approved criteria, in dataset order */
	public static function all() {
		$d = DoajPaApprovedDataset::data();
		$out = array();
		foreach ($d['criteria'] as $c) $out[] = new DoajPaApprovedRule($c);
		return $out;
	}

	public function version() { return self::VERSION; }
	public function datasetVersion() { return DoajPaApprovedDataset::VERSION; }

	public function fingerprintScope($coverage) {
		list($scope, $extra) = parent::fingerprintScope($coverage);
		$extra[1] = DoajPaApprovedDataset::SOURCE_SHA256 . '/' . DoajPaApprovedDataset::APPROVAL_SHA256;
		return array($scope, $extra);
	}
}
