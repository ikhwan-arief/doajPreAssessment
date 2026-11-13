<?php

/**
 * @file classes/ui/DoajPaHumanText.inc.php
 *
 * @class DoajPaHumanText
 * @brief Plain-language display text (1.2.0.0). Stored records keep their technical codes (criterion keys, reason
 *  codes, decision references) unchanged, because sealed runs must not change; the interface and the reports show
 *  them in words a journal manager understands:
 *  - criterion keys (guide.disclosure-oa-policy) become the criterion name;
 *  - form question keys (FQ-07) become the question;
 *  - crawl reason codes (language_undetermined, pdf_extractor_unavailable, ...) become sentences;
 *  - internal design-decision references (ADR-011, D-03-4) are removed.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaHumanText {

	/** DOAJ application form topics of the 20 Form Guide entries (docs/stage03/criteria/fq-mapping.md). */
	const QUESTIONS = array(
		'FQ-01' => 'Open access statement (does the journal follow the DOAJ definition of open access?)',
		'FQ-02' => 'Year the journal started publishing all content under an open licence',
		'FQ-03' => 'Journal title (and alternative title)',
		'FQ-04' => 'Link to the journal homepage',
		'FQ-05' => 'ISSN (print)',
		'FQ-06' => 'ISSN (online)',
		'FQ-07' => 'Languages in which the journal accepts manuscripts',
		'FQ-08' => 'Subject keywords (up to six, in English)',
		'FQ-09' => 'Publisher name and country',
		'FQ-10' => 'Licence(s) used and where they are stated',
		'FQ-11' => 'Licence information shown in the articles',
		'FQ-12' => 'Do authors keep their copyright, and where is it stated?',
		'FQ-13' => 'Type of peer review and where the policy is stated',
		'FQ-14' => 'Plagiarism screening',
		'FQ-15' => 'Link to the Aims & Scope',
		'FQ-16' => 'Link to the Editorial Board',
		'FQ-17' => 'Link to the Instructions for Authors',
		'FQ-18' => 'Publication fees (APCs) and waivers',
		'FQ-19' => 'Other fees charged to authors',
		'FQ-20' => 'Archiving, repository policy and persistent identifiers',
	);

	/** Whole reason codes => sentence. Prefix codes end with ":". */
	const REASONS = array(
		'language_undetermined' => 'English not confirmed: too little running text (mostly names, titles or lists)',
		'language_mixed' => 'The page mixes English with another language',
		'language_non_english' => 'The page is not in English',
		'pdf_extractor_unavailable' => 'The PDF text could not be read: no PDF reader is available on the server',
		'pdf_reader_limit' => 'The PDF is too large or complex to read within the safety limits',
		'no_text_layer_scanned_or_image_only' => 'The PDF has no text layer (scanned or image-only)',
		'encrypted_or_protected' => 'The PDF is password-protected',
		'unreadable_pdf' => 'The PDF file could not be read',
		'not_a_pdf' => 'The file is not a valid PDF',
		'pdftotext_timeout' => 'Reading the PDF took too long',
		'unsupported_media_type' => 'This file type is not read',
		'blocked_destination' => 'This address is not allowed (private or local network)',
		'redirect_blocked_destination' => 'The page redirects to an address that is not allowed',
		'redirect_out_of_scope' => 'The page redirects outside the journal website',
		'redirect_loop' => 'The page redirects in a loop',
		'too_many_redirects' => 'The page redirects too many times',
		'robots_disallow' => 'Not read: the website\'s robots.txt asks crawlers not to read it',
		'not_fetched_limit_reached' => 'Not read: the crawl limit was reached',
		'excluded_backend_or_dynamic_path' => 'Not read: not a public journal page',
		'other_host_or_port' => 'Not read: outside the journal website',
		'outside_journal_path' => 'Not read: outside this journal',
		'query_too_long' => 'Not read: the link is too long',
		'timeout' => 'The page took too long to respond',
		'network_error' => 'The page could not be reached (network error)',
		'tls_error' => 'The secure connection (HTTPS certificate) failed',
		'size_limit_exceeded' => 'The file is larger than the size limit',
		'pinned_address_mismatch' => 'The connection went to an unexpected address and was stopped',
		'empty_document' => 'The page is empty',
		'unparseable_html' => 'The page could not be read',
		'duplicate_canonical' => 'Duplicate of another page that was read',
		'interrupted_not_reprocessed' => 'Not read again after an interruption',
		'text_truncated' => 'Only the first part of the text was read (very long page)',
		'dns_no_address' => 'The website address could not be found',
		'not_http_link' => 'Not read: not a web page link (for example an e-mail address)',
		'meta_refresh_not_followed' => 'An automatic page redirect was not followed',
		'stale_private_state_retired' => 'An older unfinished crawl was set aside',
	);

	private static $titles = null;

	/** @return array criterion key => display name (approved and candidate datasets) */
	public static function titles() {
		if (self::$titles !== null) return self::$titles;
		self::$titles = array();
		foreach (array('DoajPaCandidateDataset', 'DoajPaApprovedDataset') as $class) {
			$file = dirname(__FILE__) . '/../rules/data/' . $class . '.inc.php';
			if (!class_exists($class) && is_file($file)) require_once($file);
			if (!class_exists($class)) continue;
			$data = $class::data();
			foreach ($data['criteria'] as $c) if (isset($c['key'], $c['title'])) self::$titles[$c['key']] = self::cleanTitle($c['title']);
		}
		return self::$titles;
	}

	/** @return string The criterion name for display (the key itself only when it is unknown) */
	public static function criterion($key) {
		$t = self::titles();
		return isset($t[$key]) ? $t[$key] : (string) $key;
	}

	/** @return string The Form Guide question for display */
	public static function question($key) {
		return isset(self::QUESTIONS[$key]) ? self::QUESTIONS[$key] : (string) $key;
	}

	/** Other stored codes (transition reasons, finding subtypes, crawl limits, robots status) in words. */
	const CODES = array(
		'first_detected' => 'First detected', 'still_detected' => 'Still detected', 'not_detected_verified' => 'Corrected (verified by new evidence)',
		'not_established' => 'Could not be checked again', 'reappeared' => 'Detected again', 'superseded' => 'Replaced by a newer criterion version',
		'not-met' => 'Requirement not met', 'contradiction' => 'Contradictory statements', 'unapproved-metric' => 'Unapproved metric',
		'maxPages' => 'page limit', 'maxIssues' => 'issue limit', 'maxArticles' => 'article limit', 'maxFiles' => 'file limit', 'maxDepth' => 'link-depth limit',
		'maxSeconds' => 'time limit', 'applied' => 'Found and respected', 'journal' => 'Whole journal', 'page' => 'Page', 'issue' => 'Issue', 'article' => 'Article', 'pdf' => 'PDF file',
		'html_page' => 'Web page', 'html_main_content' => 'Main text of the page', 'html_structured' => 'Page structure', 'html_text_extraction' => 'Page text',
		'pdftotext_extraction' => 'PDF text', 'pdf_text' => 'PDF text', 'pdf_page' => 'PDF page', 'main' => 'Main content', 'full_page' => 'Whole page',
	);

	/** @return string */
	public static function code($code) {
		$code = (string) $code;
		if (isset(self::CODES[$code])) return self::CODES[$code];
		if (preg_match('/^unavailable:(.+)$/', $code, $m)) return 'Not available (' . self::reason($m[1]) . ')';
		if (preg_match('/^none_http_(\d+)$/', $code, $m)) return 'None (HTTP ' . $m[1] . ')';
		if (isset(self::REASONS[$code])) return self::REASONS[$code];
		return ucfirst(str_replace(array('_', '-'), ' ', $code));
	}

	/** @return string A crawl reason (skip_reason / acquisition reason) as a sentence */
	public static function reason($reason) {
		if ($reason === null || $reason === '') return '';
		$r = trim((string) $reason);
		if (preg_match('/^language_(undetermined|mixed|non_english)(?::([a-z]{2,3}))?/', $r, $m)) {
			return self::REASONS['language_' . $m[1]] . (!empty($m[2]) && $m[1] === 'non_english' ? ' (detected: ' . $m[2] . ')' : '') . '.';
		}
		if (preg_match('/^unsupported_media_type:\s*(.+)$/', $r, $m)) return self::REASONS['unsupported_media_type'] . ' (' . self::mediaType($m[1]) . ').';
		if (preg_match('/^(?:http_|access_restricted_http_)(\d{3})/', $r, $m)) return 'The page answered with HTTP error ' . $m[1] . '.';
		if (preg_match('/^(?:unavailable|corrupt|encrypted|no_text|timeout|parse_failed)?:?\s*([a-z_]+)/', $r, $m) && isset(self::REASONS[$m[1]])) return self::REASONS[$m[1]] . '.';
		foreach (self::REASONS as $code => $text) if (strpos($r, $code) === 0) return $text . '.';
		return self::text($r);
	}

	/**
	 * Free text (rationales, uncertainty notes, interpretation limits): criterion keys become names, question keys
	 * become questions, reason codes become words, internal decision references are removed.
	 */
	/** Labels of the backend source marker (1.3.0.0). */
	const SOURCE_OJS = ' (from the OJS database)';
	const SOURCE_OJS_UNLINKED = ' (from the OJS database; not found on the public website during the crawl: make sure it is published and linked)';

	/** @return string A URL for display: the backend marker becomes words */
	public static function url($url) {
		$url = (string) $url;
		if (strpos($url, 'doajpa-source=ojs-unlinked') !== false) return preg_replace('/[?&]doajpa-source=ojs-unlinked/', '', $url) . self::SOURCE_OJS_UNLINKED;
		if (strpos($url, 'doajpa-source=ojs') !== false) return preg_replace('/[?&]doajpa-source=ojs/', '', $url) . self::SOURCE_OJS;
		return $url;
	}

	public static function text($s) {
		if (!is_string($s) || $s === '') return $s;
		$s = preg_replace_callback('#https?://[^\s"\'<>()]+doajpa-source=ojs(?:-unlinked)?#', function ($m) { return self::url($m[0]); }, $s);
		$s = str_replace('language_undetermined (advisory heuristic; not verified English)', 'English not confirmed: too little running text', $s);
		$s = preg_replace_callback('/\blanguage_(undetermined|mixed|non_english)(?::([a-z]{2,3}))?(?:\s*\(advisory heuristic[^)]*\))?/', function ($m) {
			$t = array('undetermined' => 'English not confirmed: too little running text', 'mixed' => 'mixes English with another language',
				'non_english' => 'not in English');
			return $t[$m[1]] . (!empty($m[2]) && $m[1] === 'non_english' ? ' (' . $m[2] . ')' : '');
		}, $s);
		$s = preg_replace('/\bunavailable:\s*pdf_extractor_unavailable\b/', 'PDF text not read (no PDF reader available)', $s);
		$s = preg_replace('/\bpdf_extractor_unavailable\b/', 'no PDF reader available', $s);
		$s = preg_replace('/\[Language-neutral facts \(ADR-013\):/', '[Based on names, dates, ISSNs or counts:', $s);
		$s = preg_replace('/\s*\((?:see )?(?:ADR|D)-\d{2,3}(?:-\d+)?[^()]*\)/', '', $s);
		$s = preg_replace('/\s*\bADR-0\d\d\b(?: R\d\.\d)?/', '', $s);
		$s = preg_replace('/\s*\((?:as )?(?:decision |see )?D-\d\d-\d+(?: pending)?\)/', '', $s);
		$s = preg_replace('/,\s*(?:decision\s+)?D-\d\d-\d+(?=\))/', '', $s);
		$s = preg_replace('/\s*\b(?:as )?(?:decision )?D-\d\d-\d+\b/', '', $s);
		$s = preg_replace('/\bnot verified English\b/', 'not confirmed as English', $s);
		$s = preg_replace('/\bverified-English\b/', 'confirmed-English', $s);
		$s = preg_replace('/\bverified English\b/', 'confirmed English', $s);
		$s = preg_replace_callback('/\bFQ-(0[1-9]|1[0-9]|20)\b/', function ($m) { return '"' . self::question('FQ-' . $m[1]) . '"'; }, $s);
		$titles = self::titles();
		$s = preg_replace_callback('/\b((?:guide|transparency|copyright|licensing|form)\.[a-z0-9][a-z0-9.-]*[a-z0-9])\b/', function ($m) use ($titles) {
			return isset($titles[$m[1]]) ? '"' . $titles[$m[1]] . '"' : $m[0];
		}, $s);
		return $s;
	}

	/**
	 * Apply text() to every string in a nested array, except identifiers, URLs, hashes and codes that templates use
	 * for links, badges and logic.
	 */
	public static function deep($value, $key = null) {
		if (is_array($value)) {
			$out = array();
			foreach ($value as $k => $v) $out[$k] = self::deep($v, is_string($k) ? $k : $key);
			return $out;
		}
		if (!is_string($value) || $value === '' || preg_match('/^https?:\/\//i', $value)) return $value;
		if ($key !== null && preg_match(self::KEEP_KEYS, $key)) return $value;
		if ($key !== null && preg_match('/(^|_)reason$|Reason$/', $key)) return self::reason($value);
		return self::text($value);
	}

	/** Keys whose values are codes or identifiers and stay unchanged. */
	const KEEP_KEYS = '/(_id|Id|_key|Key|url|Url|URL|sha256|Sha256|digest|Digest|_status|Status|status|^status$|^code$|^type$|_type|Type|version|Version|level|^format$|^subtype$|^lifecycle$|^observation$|^change$|^labelKey$|^op$|_utc|AtUtc|^page$|^target$|^homepage$|^finalUrl$|token|Token|^mediaType$|^media_type$|^quote$|excerpt|Excerpt)/';

	private static function cleanTitle($title) {
		return trim(preg_replace('/\s*\((?:ADR|D)-[^)]*\)/', '', (string) $title));
	}

	private static function mediaType($type) {
		$type = strtolower(trim($type));
		$known = array('application/x-research-info-systems' => 'RIS citation file', 'application/x-bibtex' => 'BibTeX citation file',
			'application/xml' => 'XML file', 'text/xml' => 'XML file', 'application/zip' => 'ZIP archive', 'image/jpeg' => 'image', 'image/png' => 'image',
			'application/msword' => 'Word document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word document',
			'application/epub+zip' => 'EPUB file', 'text/plain' => 'plain-text file');
		return isset($known[$type]) ? $known[$type] : $type;
	}
}
