<?php

/**
 * @file classes/crawler/DoajPaCrawlScope.inc.php
 *
 * @class DoajPaCrawlScope
 * @brief Public journal boundary for one crawl (Stage 01 PROD-07, F9). Pure; no OJS dependency.
 *  In scope = same scheme, host and port as the journal homepage, and a path under the journal's own
 *  path prefix. OJS backend and dynamic paths are excluded even under that prefix; so are other journals.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/DoajPaUrlTools.inc.php');

class DoajPaCrawlScope {

	/** First path segment after the journal prefix that is never crawled (backend, login, APIs, search, plugin itself). */
	const EXCLUDED_SEGMENTS = array(
		'management', 'submissions', 'submission', 'workflow', 'authordashboard', 'reviewer', 'login', 'user', 'admin', 'api',
		'$$$call$$$', '%24%24%24call%24%24%24', 'dashboard', 'manageissues', 'stats', 'notification', 'payment', 'payments',
		'doajpreassessment', 'help', 'tools', 'editor', 'sectioneditor', 'author', 'copyeditor', 'layouteditor', 'proofreader',
		'subscriptionmanager', 'search', 'oai', 'gateway', 'sitemap', 'feed', 'catalog', 'management.php',
		'citationstylelanguage', // citation-format exports (APA, RIS, BibTeX...) of each article: no assessable content (1.2.0.0)
	);

	/** @var array */
	private $_target;

	/**
	 * @param $homepageUrl string Journal homepage, e.g. https://host/index.php/jpa
	 */
	public function __construct($homepageUrl) {
		$p = DoajPaUrlTools::parse($homepageUrl);
		if (!$p['ok']) throw new InvalidArgumentException('Invalid journal homepage URL: ' . $p['reason']);
		$this->_target = array('scheme' => $p['scheme'], 'host' => $p['host'], 'port' => $p['port'],
			'prefix' => rtrim($p['path'], '/'), 'homepage' => $p['url']);
	}

	/** @return array */
	public function target() {
		return $this->_target;
	}

	/**
	 * @param $url string Absolute URL
	 * @return string|null null when in scope; otherwise the reason
	 */
	public function reason($url) {
		$p = DoajPaUrlTools::parse($url);
		if (!$p['ok']) return 'invalid_url:' . $p['reason'];
		$t = $this->_target;
		if ($p['scheme'] !== $t['scheme'] || $p['host'] !== $t['host'] || $p['port'] !== $t['port']) return 'other_host_or_port';
		$path = $p['path'];
		if ($t['prefix'] !== '' && $path !== $t['prefix'] && strpos($path, $t['prefix'] . '/') !== 0) return 'outside_journal_path';
		$rest = trim(substr($path, strlen($t['prefix'])), '/');
		$first = strtolower(explode('/', $rest)[0]);
		if (in_array($first, self::EXCLUDED_SEGMENTS, true)) return 'excluded_backend_or_dynamic_path';
		if ($p['query'] !== null && strlen($p['query']) > 100) return 'query_too_long';
		return null;
	}

	/**
	 * Path relative to the journal prefix, for page classification.
	 * @return string
	 */
	public function relativePath($url) {
		$p = DoajPaUrlTools::parse($url);
		if (!$p['ok']) return '';
		return trim(substr($p['path'], strlen($this->_target['prefix'])), '/');
	}

	/** @return string robots.txt URL of the journal host */
	public function robotsUrl() {
		$t = $this->_target;
		$default = ($t['scheme'] === 'http' && $t['port'] === 80) || ($t['scheme'] === 'https' && $t['port'] === 443);
		$host = strpos($t['host'], ':') !== false ? '[' . $t['host'] . ']' : $t['host'];
		return $t['scheme'] . '://' . $host . ($default ? '' : ':' . $t['port']) . '/robots.txt';
	}
}
