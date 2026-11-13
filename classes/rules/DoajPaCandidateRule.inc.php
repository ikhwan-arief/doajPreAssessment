<?php

/**
 * @file classes/rules/DoajPaCandidateRule.inc.php
 *
 * @class DoajPaCandidateRule
 * @brief One criterion of the PROPOSED dataset (DoajPaCandidateDataset), evaluated by DoajPaChecks (Stage 03).
 *  Candidate criteria are PENDING APPROVAL (decision D-03-1): they run only in the explicitly selected candidate
 *  profile, whose results are labelled "Not a DOAJ assessment result" and are never presented as approved.
 *  Version 1 is the candidate version; an approved criterion will be registered as a new version of the same lineage.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../framework/DoajPaCriterionRule.inc.php');
require_once(dirname(__FILE__) . '/data/DoajPaCandidateDataset.inc.php');
require_once(dirname(__FILE__) . '/DoajPaChecks.inc.php');

class DoajPaCandidateRule implements DoajPaCriterionRule {

	const VERSION = 1;
	/** Changes when a handler or fact extractor changes behaviour (part of the reuse fingerprint). */
	const ENGINE_VERSION = 'doajpa-checks/5';

	/** @var array */
	private $_spec;

	public function __construct($spec) {
		$this->_spec = $spec;
	}

	/** @return DoajPaCandidateRule[] All candidate criteria, in dataset order */
	public static function all() {
		$d = DoajPaCandidateDataset::data();
		$out = array();
		foreach ($d['criteria'] as $c) $out[] = new DoajPaCandidateRule($c);
		return $out;
	}

	public function spec() { return $this->_spec; }
	public function key() { return $this->_spec['key']; }
	public function version() { return self::VERSION; }
	public function lineage() { return 1; }
	public function level() { return $this->_spec['level']; }
	public function datasetVersion() { return DoajPaCandidateDataset::VERSION; }
	public function sourceVersion() { return $this->_spec['sourceVersion']; }
	public function title() { return $this->_spec['title']; }
	public function doajReferenceUrl() { return $this->_spec['doajReferenceUrl']; }
	public function requiredEvidence() { return $this->_spec['evidence']; }
	public function humanReviewBoundary() { return 'Abstain when: ' . $this->_spec['abstainWhen'] . '. Method: ' . $this->_spec['method']; }
	public function recommendationReference() { return $this->_spec['doajReferenceUrl'] . ' (' . $this->_spec['locator'] . ')'; }

	/**
	 * Facts are shared across pages (e.g. the board page is used by endogeny), and some checks use the crawl date and
	 * limits, so reuse depends on every resource of the run and on these values.
	 * @return array [page types or null for all, extra identity values]
	 */
	public function fingerprintScope($coverage) {
		$finished = isset($coverage['finishedAtUtc']) ? substr((string) $coverage['finishedAtUtc'], 0, 10) : '';
		$limits = isset($coverage['limitsHit']) && is_array($coverage['limitsHit']) ? $coverage['limitsHit'] : array();
		sort($limits);
		return array(null, array(self::ENGINE_VERSION, DoajPaCandidateDataset::SOURCE_SHA256, $finished, $limits));
	}

	public function evaluate($context) {
		return DoajPaChecks::evaluate($this->_spec, $context);
	}
}
