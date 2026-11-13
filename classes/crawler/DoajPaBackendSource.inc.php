<?php

/**
 * @file classes/crawler/DoajPaBackendSource.inc.php
 *
 * @class DoajPaBackendSource
 * @brief Published journal content read directly from the OJS 3.3 database (1.3.0.0, product-owner decision
 *  2026-11-13; revises AC-05 for published content only). Each item becomes a page that goes through the same
 *  extraction, language check and rules as a page read from the website.
 *
 *  Read: journal settings shown on About, Editorial Team and Submissions; custom navigation-menu pages; Static Pages;
 *  published issues and articles (titles, dates, authors, affiliations, licence, copyright). Never read: submissions
 *  in progress, reviews, files, user accounts, e-mail addresses or anything unpublished.
 *
 *  Every page carries the public address where OJS shows the content, with the marker "?doajpa-source=ojs" (or
 *  "ojs-unlinked" when the crawl did not find that public page), so results based on it are labelled as coming from
 *  the OJS database. The content is wrapped in a minimal HTML document whose wording is the stored text plus fixed
 *  English labels (for example "Publisher:"); nothing is invented.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');

class DoajPaBackendSource {

	const VERSION = 'doajpa-ojs-backend/1';
	const MARKER = 'doajpa-source';
	const MAX_ISSUES = 4;
	const MAX_ARTICLES = 40;
	const MAX_CUSTOM_PAGES = 100;
	const PUBLISHED = 3; // STATUS_PUBLISHED

	/**
	 * @param $contextId int
	 * @param $homepage string Journal homepage URL, e.g. https://host/index.php/jaes
	 * @return array list of ['path' => relative path, 'title' => string, 'html' => complete HTML document, 'lang' => 'en'|...]
	 */
	public static function pages($contextId, $homepage) {
		$ctx = DoajPaDb::id($contextId, 'context');
		$journal = DoajPaDb::table('journals')->where('journal_id', '=', $ctx)->first(array('primary_locale'));
		if (!$journal) return array();
		$locale = (string) $journal->primary_locale;
		$lang = strtolower(substr($locale, 0, 2));
		$s = self::settings('journal_settings', 'journal_id', array($ctx), $locale);
		$set = isset($s[$ctx]) ? $s[$ctx] : array();
		$g = function ($k) use ($set) { return isset($set[$k]) ? trim((string) $set[$k]) : ''; };
		$pages = array();

		// About the Journal (OJS shows about, the open access policy and the journal information here)
		$info = array();
		if ($g('publisherInstitution') !== '') $info[] = '<p>Publisher: ' . self::e($g('publisherInstitution')) . '</p>';
		$issn = array();
		if ($g('printIssn') !== '') $issn[] = 'p-ISSN ' . self::e($g('printIssn'));
		if ($g('onlineIssn') !== '') $issn[] = 'e-ISSN ' . self::e($g('onlineIssn'));
		if ($issn) $info[] = '<p>' . implode(' | ', $issn) . '</p>';
		if ($g('mailingAddress') !== '') $info[] = '<p>Mailing address: ' . nl2br(self::e($g('mailingAddress'))) . '</p>';
		if ($g('licenseUrl') !== '') $info[] = '<p>' . self::licence($g('licenseUrl')) . '</p>';
		$about = self::section('', $g('description')) . self::section('', $g('about')) . self::section('Open Access Policy', $g('openAccessPolicy'))
			. ($info ? '<h2>Journal Information</h2>' . implode('', $info) : '');
		if (trim(strip_tags($about)) !== '') $pages[] = self::page('about', 'About the Journal', $about, $lang);

		if ($g('editorialTeam') !== '') $pages[] = self::page('about/editorialTeam', 'Editorial Team', $g('editorialTeam'), $lang);

		$sub = self::section('Submission Preparation Checklist', $g('submissionChecklist')) . self::section('Author Guidelines', $g('authorGuidelines'))
			. self::section('Copyright Notice', $g('copyrightNotice')) . self::section('Privacy Statement', $g('privacyStatement'));
		if (trim(strip_tags($sub)) !== '') $pages[] = self::page('about/submissions', 'Submissions', $sub, $lang);

		// Custom pages of the navigation menus and Static Pages (published content only)
		$custom = DoajPaDb::table('navigation_menu_items')->where('context_id', '=', $ctx)->where('type', '=', 'NMI_TYPE_CUSTOM')
			->whereNotNull('path')->limit(self::MAX_CUSTOM_PAGES)->get(array('navigation_menu_item_id', 'path'))->all();
		$cs = self::settings('navigation_menu_item_settings', 'navigation_menu_item_id', array_map(function ($r) { return (int) $r->navigation_menu_item_id; }, $custom), $locale);
		foreach ($custom as $r) {
			$x = isset($cs[(int) $r->navigation_menu_item_id]) ? $cs[(int) $r->navigation_menu_item_id] : array();
			if (!self::safePath($r->path) || empty($x['content'])) continue;
			$pages[] = self::page($r->path, isset($x['title']) ? (string) $x['title'] : $r->path, (string) $x['content'], $lang);
		}
		if (self::tableExists('static_pages')) {
			$static = DoajPaDb::table('static_pages')->where('context_id', '=', $ctx)->limit(self::MAX_CUSTOM_PAGES)->get(array('static_page_id', 'path'))->all();
			$ss = self::settings('static_page_settings', 'static_page_id', array_map(function ($r) { return (int) $r->static_page_id; }, $static), $locale);
			foreach ($static as $r) {
				$x = isset($ss[(int) $r->static_page_id]) ? $ss[(int) $r->static_page_id] : array();
				if (!self::safePath($r->path) || empty($x['content'])) continue;
				$pages[] = self::page($r->path, isset($x['title']) ? (string) $x['title'] : $r->path, (string) $x['content'], $lang);
			}
		}

		// Published issues and articles
		$copyrightHolderType = $g('copyrightHolderType');
		$issues = DoajPaDb::table('issues')->where('journal_id', '=', $ctx)->where('published', '=', 1)->orderBy('date_published', 'desc')
			->limit(self::MAX_ISSUES)->get(array('issue_id', 'volume', 'number', 'year', 'date_published', 'url_path'))->all();
		$is = self::settings('issue_settings', 'issue_id', array_map(function ($r) { return (int) $r->issue_id; }, $issues), $locale);
		$subs = DoajPaDb::table('submissions as s')->join('publications as p', 'p.publication_id', '=', 's.current_publication_id')
			->where('s.context_id', '=', $ctx)->where('p.status', '=', self::PUBLISHED)->orderBy('p.date_published', 'desc')->limit(self::MAX_ARTICLES)
			->get(array('s.submission_id', 'p.publication_id', 'p.date_published', 'p.url_path'))->all();
		$pubIds = array_map(function ($r) { return (int) $r->publication_id; }, $subs);
		$ps = self::settings('publication_settings', 'publication_id', $pubIds, $locale);
		$authors = array();
		if ($pubIds) {
			$rows = DoajPaDb::table('authors')->whereIn('publication_id', $pubIds)->orderBy('seq')->get(array('author_id', 'publication_id'))->all();
			$as = self::settings('author_settings', 'author_id', array_map(function ($r) { return (int) $r->author_id; }, $rows), $locale);
			foreach ($rows as $a) {
				$x = isset($as[(int) $a->author_id]) ? $as[(int) $a->author_id] : array();
				$name = trim((isset($x['givenName']) ? $x['givenName'] : '') . ' ' . (isset($x['familyName']) ? $x['familyName'] : ''));
				if ($name !== '') $authors[(int) $a->publication_id][] = array('name' => $name, 'affiliation' => isset($x['affiliation']) ? trim((string) $x['affiliation']) : '');
			}
		}
		$byIssue = array();
		foreach ($subs as $r) {
			$x = isset($ps[(int) $r->publication_id]) ? $ps[(int) $r->publication_id] : array();
			$title = isset($x['title']) ? trim(strip_tags((string) $x['title'])) : '';
			if ($title === '') continue;
			$au = isset($authors[(int) $r->publication_id]) ? $authors[(int) $r->publication_id] : array();
			$date = $r->date_published ? substr((string) $r->date_published, 0, 10) : '';
			$path = 'article/view/' . ($r->url_path && self::safePath($r->url_path) ? $r->url_path : (int) $r->submission_id);
			$meta = '<meta name="citation_title" content="' . self::e($title) . '" />';
			foreach ($au as $a) $meta .= '<meta name="citation_author" content="' . self::e($a['name']) . '" />' . ($a['affiliation'] !== '' ? '<meta name="citation_author_institution" content="' . self::e($a['affiliation']) . '" />' : '');
			if ($date !== '') $meta .= '<meta name="citation_date" content="' . str_replace('-', '/', $date) . '" />';
			$body = '<ul class="authors">';
			foreach ($au as $a) $body .= '<li>' . self::e($a['name']) . ($a['affiliation'] !== '' ? ', ' . self::e($a['affiliation']) : '') . '</li>';
			$body .= '</ul>' . ($date !== '' ? '<p>Published: ' . self::e($date) . '</p>' : '') . self::section('Abstract', isset($x['abstract']) ? (string) $x['abstract'] : '');
			$holder = isset($x['copyrightHolder']) ? trim((string) $x['copyrightHolder']) : '';
			$year = isset($x['copyrightYear']) ? trim((string) $x['copyrightYear']) : '';
			if ($holder !== '' || $year !== '') $body .= '<p>Copyright (c) ' . self::e(trim($year . ' ' . $holder)) . '</p>';
			$lic = isset($x['licenseUrl']) && trim((string) $x['licenseUrl']) !== '' ? trim((string) $x['licenseUrl']) : $g('licenseUrl');
			if ($lic !== '') $body .= '<p>' . self::licence($lic) . '</p>';
			$pages[] = self::page($path, $title, $body, $lang, $meta);
			if (isset($x['issueId'])) $byIssue[(int) $x['issueId']][] = array('title' => $title, 'path' => $path, 'authors' => $au);
		}
		foreach ($issues as $r) {
			$x = isset($is[(int) $r->issue_id]) ? $is[(int) $r->issue_id] : array();
			$label = trim(($r->volume ? 'Vol. ' . $r->volume . ' ' : '') . ($r->number ? 'No. ' . $r->number . ' ' : '') . ($r->year ? '(' . $r->year . ')' : ''));
			$t = isset($x['title']) && trim((string) $x['title']) !== '' ? $label . ': ' . trim((string) $x['title']) : $label;
			$body = ($r->date_published ? '<p>Published: ' . self::e(substr((string) $r->date_published, 0, 10)) . '</p>' : '') . '<h2>Articles</h2>';
			foreach (isset($byIssue[(int) $r->issue_id]) ? $byIssue[(int) $r->issue_id] : array() as $a) {
				$body .= '<div class="obj_article_summary"><h3>' . self::e($a['title']) . '</h3><div class="authors">'
					. self::e(implode(', ', array_map(function ($p) { return $p['name']; }, $a['authors']))) . '</div></div>';
			}
			$pages[] = self::page('issue/view/' . ($r->url_path && self::safePath($r->url_path) ? $r->url_path : (int) $r->issue_id), $t !== '' ? $t : 'Issue', $body, $lang);
		}
		return $pages;
	}

	/** @return string The address with the backend marker ("ojs" or "ojs-unlinked") */
	public static function markedUrl($homepage, $path, $linked) {
		return rtrim($homepage, '/') . '/' . ltrim($path, '/') . '?' . self::MARKER . '=' . ($linked ? 'ojs' : 'ojs-unlinked');
	}

	/** @return string|null "ojs", "ojs-unlinked" or null for a URL read from the website */
	public static function sourceOf($url) {
		return is_string($url) && preg_match('/[?&]' . self::MARKER . '=(ojs(?:-unlinked)?)\b/', $url, $m) ? $m[1] : null;
	}

	//
	// Helpers
	//

	/** @return array id => setting name => value (primary locale, then the untranslated value, then any locale) */
	private static function settings($table, $idColumn, $ids, $locale) {
		if (!$ids) return array();
		$out = array(); $rank = array();
		foreach (DoajPaDb::table($table)->whereIn($idColumn, $ids)->get(array($idColumn, 'locale', 'setting_name', 'setting_value'))->all() as $r) {
			$id = (int) $r->$idColumn; $name = (string) $r->setting_name;
			$score = $r->locale === $locale ? 3 : ($r->locale === '' || $r->locale === null ? 2 : 1);
			if (isset($rank[$id][$name]) && $rank[$id][$name] >= $score) continue;
			if ($r->setting_value === null || $r->setting_value === '') continue;
			$rank[$id][$name] = $score;
			$out[$id][$name] = (string) $r->setting_value;
		}
		return $out;
	}

	private static function page($path, $title, $bodyHtml, $lang, $meta = '') {
		$doc = '<!DOCTYPE html><html lang="' . self::e($lang) . '"><head><meta charset="utf-8" /><title>' . self::e(strip_tags($title)) . '</title>' . $meta
			. '</head><body><main role="main" class="pkp_structure_main"><h1>' . self::e(strip_tags($title)) . '</h1>' . self::clean($bodyHtml) . '</main></body></html>';
		return array('path' => trim($path, '/'), 'title' => strip_tags($title), 'html' => $doc, 'lang' => $lang);
	}

	private static function section($heading, $html) {
		if (trim(strip_tags((string) $html)) === '') return '';
		return ($heading !== '' ? '<h2>' . self::e($heading) . '</h2>' : '') . '<div>' . self::clean($html) . '</div>';
	}

	/** Stored rich text without active content (the extractor removes scripts anyway). */
	private static function clean($html) {
		return preg_replace('#<(script|style|iframe|object|embed)\b.*?</\1>#is', '', (string) $html);
	}

	/** The licence line OJS shows under an article (Creative Commons name derived from the licence URL). */
	private static function licence($url) {
		$name = $url;
		if (preg_match('#creativecommons\.org/licenses/([a-z-]+)/([0-9.]+)#i', $url, $m)) {
			$parts = array('by' => 'Attribution', 'sa' => 'ShareAlike', 'nc' => 'NonCommercial', 'nd' => 'NoDerivatives');
			$name = 'Creative Commons ' . implode('-', array_map(function ($p) use ($parts) { return isset($parts[$p]) ? $parts[$p] : $p; }, explode('-', strtolower($m[1])))) . ' ' . $m[2] . ' International License';
		}
		return 'This work is licensed under a <a rel="license" href="' . self::e($url) . '">' . self::e($name) . '</a>.';
	}

	private static function safePath($path) {
		return is_string($path) && preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]{0,250}$#', $path) && strpos($path, '..') === false;
	}

	private static function tableExists($table) {
		try { DoajPaDb::table($table)->limit(1)->count(); return true; } catch (\Throwable $e) { return false; }
	}

	private static function e($s) {
		return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
