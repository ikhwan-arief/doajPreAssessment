<?php

/**
 * @file classes/crawler/DoajPaPageClassifier.inc.php
 *
 * @class DoajPaPageClassifier
 * @brief Deterministic page type from the OJS 3.3 public URL pattern (path relative to the journal).
 *  The type only guides crawling order and later rule lookup; it is never evidence of content.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaPageClassifier {

	/** @return string page type */
	public static function classify($relativePath) {
		$p = strtolower(trim($relativePath, '/'));
		$s = $p === '' ? array() : explode('/', $p);
		if (!$s || $p === 'index' || $p === 'index/index') return 'home';
		switch ($s[0]) {
			case 'about':
				$map = array('editorialteam' => 'editorial_team', 'submissions' => 'author_guidelines', 'privacy' => 'privacy',
					'contact' => 'contact', 'aboutthispublishingsystem' => 'publishing_system');
				return isset($s[1]) ? (isset($map[$s[1]]) ? $map[$s[1]] : 'about_other') : 'about';
			case 'issue':
				if (isset($s[1]) && $s[1] === 'archive') return 'archive';
				if (isset($s[1]) && ($s[1] === 'view' || $s[1] === 'current')) return isset($s[3]) ? 'issue_file' : 'issue';
				return 'other';
			case 'article':
				if (isset($s[1]) && $s[1] === 'view') return isset($s[3]) ? 'galley' : 'article';
				if (isset($s[1]) && $s[1] === 'download') return 'file';
				return 'other';
			case 'announcement': return 'announcement';
			case 'information': return 'information';
		}
		return count($s) === 1 ? 'custom' : 'other';
	}

	/** Crawl priority: lower first. */
	public static function priority($pageType, $linkLocation) {
		$byType = array('home' => 0, 'about' => 1, 'editorial_team' => 1, 'author_guidelines' => 1, 'privacy' => 1, 'contact' => 1,
			'custom' => 1, 'information' => 2, 'archive' => 2, 'issue' => 3, 'announcement' => 4, 'article' => 5, 'galley' => 6,
			'file' => 6, 'issue_file' => 7);
		$p = isset($byType[$pageType]) ? $byType[$pageType] : 8;
		if ($linkLocation === 'nav' || $linkLocation === 'footer') $p = min($p, 1);
		return $p;
	}
}
