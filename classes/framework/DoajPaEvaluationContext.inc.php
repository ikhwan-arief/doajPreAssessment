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
 */

class DoajPaEvaluationContext {

	/** @var array */
	private $_evidence;
	/** @var array */
	private $_resources;
	/** @var array */
	private $_coverage;

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

	/** @return array evidenceId => ['quote' => string, 'verifiedEnglish' => bool] for DoajPaOutcomeGuard */
	public function guardView() {
		$view = array();
		foreach ($this->_evidence as $id => $e) $view[$id] = array('quote' => $e['quote'], 'verifiedEnglish' => $e['verifiedEnglish']);
		return $view;
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
