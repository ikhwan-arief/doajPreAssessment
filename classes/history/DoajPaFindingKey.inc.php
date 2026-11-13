<?php

/**
 * @file classes/history/DoajPaFindingKey.inc.php
 *
 * @class DoajPaFindingKey
 * @brief Stable finding identity (Stage 01 F6, Stage 02.04): journal context + criterion family +
 *  criterion lineage + issue subtype + affected target. Never derived from prose. Pure; no OJS dependency.
 *
 * The lineage is the first criterion version whose findings remain comparable. An incompatible criterion
 * change starts a new lineage, so its findings get new IDs and the old ones become "superseded".
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaFindingKey {

	/**
	 * @return string 64 hex characters
	 */
	public static function build($contextId, $criterionKey, $lineageVersion, $subtype, $targetType, $target) {
		self::requireToken($criterionKey, 'criterion key');
		self::requireToken($subtype, 'subtype');
		if (!is_int($contextId) || $contextId <= 0) throw new InvalidArgumentException('Invalid context.');
		if (!is_int($lineageVersion) || $lineageVersion <= 0) throw new InvalidArgumentException('Invalid lineage version.');
		$normalized = self::normalizeTarget($targetType, $target);
		return hash('sha256', json_encode(array($contextId, $criterionKey, $lineageVersion, $subtype, $targetType, $normalized)));
	}

	/**
	 * @return string Normalized target used in the key ("journal" for journal-level findings)
	 */
	public static function normalizeTarget($targetType, $target) {
		if ($targetType === 'journal') return 'journal';
		if (!in_array($targetType, array('page', 'issue', 'article', 'pdf'), true)) {
			throw new InvalidArgumentException('Invalid target type.');
		}
		return self::normalizeUrl($target);
	}

	/**
	 * Deterministic URL normalization: lower-case scheme and host, default port removed, fragment removed,
	 * dot segments resolved, empty path => "/", trailing slash removed (except root), query parameters sorted.
	 * @param $url string
	 * @return string
	 */
	public static function normalizeUrl($url) {
		if (!is_string($url) || $url === '' || strlen($url) > 2048) throw new InvalidArgumentException('Invalid URL.');
		$parts = parse_url(trim($url));
		if ($parts === false || !isset($parts['scheme'], $parts['host'])) throw new InvalidArgumentException('Invalid URL.');
		$scheme = strtolower($parts['scheme']);
		if ($scheme !== 'http' && $scheme !== 'https') throw new InvalidArgumentException('Only http and https URLs are allowed.');
		$host = strtolower($parts['host']);
		$port = isset($parts['port']) ? (int) $parts['port'] : null;
		if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) $port = null;

		$segments = array();
		foreach (explode('/', isset($parts['path']) ? $parts['path'] : '/') as $segment) {
			if ($segment === '' || $segment === '.') continue;
			if ($segment === '..') { array_pop($segments); continue; }
			$segments[] = $segment;
		}
		$path = '/' . implode('/', $segments);

		$query = '';
		if (isset($parts['query']) && $parts['query'] !== '') {
			$pairs = explode('&', $parts['query']);
			sort($pairs, SORT_STRING);
			$query = '?' . implode('&', $pairs);
		}
		return $scheme . '://' . $host . ($port !== null ? ':' . $port : '') . $path . $query;
	}

	private static function requireToken($value, $label) {
		if (!is_string($value) || !preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/', $value)) {
			throw new InvalidArgumentException('Invalid ' . $label . '.');
		}
	}
}
