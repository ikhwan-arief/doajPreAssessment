<?php

/**
 * @file classes/framework/DoajPaEvaluationContext.inc.php
 *
 * @class DoajPaEvaluationContext
 * @brief Read-only view of ONE run's stored public evidence, given to criterion rules (Stage 02.07). Pure.
 *  Rules see only what the crawler stored for this run: no network, no database, no other journal.
 *
 *  Each evidence item: id, resourceId, url, title, sourceType, pageType, quote, quoteSha256,
 *  acquisition (of its resource), language (english|undetermined|mixed|non_english|unknown),
 *  provenance (true when the evidence passed the provenance checks) and verifiedEnglish
 *  (provenance && acquisition "retrieved" && language "english").
 *  Each resource: id, url, urlSha256, pageType, httpStatus, acquisition, reason, contentSha256.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaEvaluationContext {

	/** @var array */
	private $_evidence;
	/** @var array */
	private $_resources;
	/** @var array */
	private $_coverage;
	/** @var array|null evidenceId => bool, see neutralEligible() */
	private $_neutral = null;

	/** Policy page types whose verified-English main content establishes that the journal site is in English (ADR-013) */
	const NEUTRAL_SITE_TYPES = array('home', 'about', 'author_guidelines', 'custom', 'information', 'about_other', 'contact');
	/** Minimum number of such verified-English pages (ADR-013 safeguard S3) */
	const NEUTRAL_MIN_ENGLISH_PAGES = 2;

	public function __construct($evidence, $resources, $coverage) {
		$this->_evidence = $evidence;
		$this->_resources = $resources;
		$this->_coverage = $coverage;
	}

	/**
	 * @param $pageTypes string[]|null Restrict to these page types (null = all)
	 * @return array Evidence items, in evidence id order
	 */
	public function evidence($pageTypes = null) {
		return array_values(array_filter($this->_evidence, function ($e) use ($pageTypes) {
			return $pageTypes === null || in_array($e['pageType'], $pageTypes, true);
		}));
	}

	/** @return array|null One evidence item of this run */
	public function evidenceById($id) {
		return isset($this->_evidence[$id]) ? $this->_evidence[$id] : null;
	}

	/**
	 * @param $pageTypes string[]|null
	 * @return array Resources including gaps (not found, inaccessible, parse failed, other language)
	 */
	public function resources($pageTypes = null) {
		return array_values(array_filter($this->_resources, function ($r) use ($pageTypes) {
			return $pageTypes === null || in_array($r['pageType'], $pageTypes, true);
		}));
	}

	/** @return array Crawl coverage summary (limits hit, skipped counts, language counts) */
	public function coverage() {
		return $this->_coverage;
	}

	/** @return array evidenceId => ['quote' => string, 'verifiedEnglish' => bool, 'neutral' => bool] for DoajPaOutcomeGuard */
	public function guardView() {
		$view = array();
		$neutral = $this->neutralEligible();
		foreach ($this->_evidence as $id => $e) $view[$id] = array('quote' => $e['quote'], 'verifiedEnglish' => $e['verifiedEnglish'], 'neutral' => !empty($neutral[$id]));
		return $view;
	}

	/**
	 * Evidence that may support LANGUAGE-NEUTRAL facts only (names, affiliations, dates, ISSNs, email addresses, counts),
	 * although its page has no language verdict (ADR-013, PENDING APPROVAL as decision D-03-9). Safeguards, all required:
	 *   S1 the page was retrieved and parsed, and its language is "undetermined" (too little text): never "non_english"
	 *      or "mixed", never an inaccessible or unparsed page; provenance checks passed; HTML only;
	 *   S2 the run contains at least NEUTRAL_MIN_ENGLISH_PAGES policy pages whose main content is verified English;
	 *   S3 no page of the run is in another language or mixed (any such page switches the exception off for the run).
	 * The outcome guard accepts such evidence only for an outcome that declares the basis "neutral"; it never makes a page
	 * verified English and never resolves a finding (DoajPaOutcomeGuard).
	 * @return array evidenceId => bool
	 */
	public function neutralEligible() {
		if ($this->_neutral !== null) return $this->_neutral;
		$english = array();
		$foreign = false;
		foreach ($this->_resources as $r) {
			if (in_array($r['acquisition'], array('unsupported_language', 'ambiguous'), true)) $foreign = true;
		}
		foreach ($this->_evidence as $e) {
			if (!empty($e['verifiedEnglish']) && isset($e['method'], $e['pageType']) && $e['method'] === 'html_main_content' && in_array($e['pageType'], self::NEUTRAL_SITE_TYPES, true)) $english[$e['resourceId']] = true;
		}
		$site = !$foreign && count($english) >= self::NEUTRAL_MIN_ENGLISH_PAGES;
		$out = array();
		foreach ($this->_evidence as $id => $e) {
			$r = isset($e['resourceId'], $this->_resources[$e['resourceId']]) ? $this->_resources[$e['resourceId']] : null;
			$out[$id] = $site && $r !== null && empty($e['verifiedEnglish']) && !empty($e['provenance']) && isset($e['language']) && $e['language'] === 'undetermined'
				&& $r['acquisition'] === 'retrieved' && isset($r['reason']) && is_string($r['reason']) && strpos($r['reason'], 'language_undetermined') === 0
				&& (!isset($r['mediaType']) || $r['mediaType'] === null || stripos((string) $r['mediaType'], 'html') !== false);
		}
		return $this->_neutral = $out;
	}

	/**
	 * Fingerprint of everything a rule depends on: the rule identity and, for the page types it needs, every
	 * resource (including gaps), its content hash, acquisition state, language and evidence hashes.
	 * Null when there is nothing to depend on (never reused).
	 * @return string|null
	 */
	public function fingerprint($ruleIdentity, $pageTypes) {
		$rows = array();
		foreach ($this->resources($pageTypes ?: null) as $r) {
			$quotes = array();
			foreach ($this->_evidence as $e) {
				if ($e['resourceId'] === $r['id']) $quotes[] = array($e['quoteSha256'], $e['language'], $e['provenance']);
			}
			$rows[] = array($r['urlSha256'], $r['pageType'], $r['acquisition'], (string) $r['reason'], (string) $r['contentSha256'], $quotes);
		}
		if (!$rows) return null;
		usort($rows, function ($a, $b) { return strcmp(json_encode($a), json_encode($b)); });
		return hash('sha256', json_encode(array($ruleIdentity, $rows)));
	}
}
