<?php

/**
 * @file classes/crawler/DoajPaUrlTools.inc.php
 *
 * @class DoajPaUrlTools
 * @brief Safe URL handling for the crawler. Pure; no OJS dependency.
 *  - parse(): only http/https, no userinfo, strict host syntax (numeric tricks such as 2130706433,
 *    0x7f000001 or 127.1 are rejected rather than resolved), port 1-65535, no control characters;
 *  - resolve(): RFC 3986 section 5.2 reference resolution;
 *  - key(): deduplication key (DoajPaFindingKey::normalizeUrl form) - never used as the URL to fetch.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../history/DoajPaFindingKey.inc.php');

class DoajPaUrlTools {

	const MAX_LENGTH = 2048;

	/**
	 * @param $url string
	 * @return array ['ok' => bool, 'reason' => string|null, 'scheme','host','hostType','port','path','query','url']
	 */
	public static function parse($url) {
		$fail = function ($reason) { return array('ok' => false, 'reason' => $reason); };
		if (!is_string($url) || $url === '' || strlen($url) > self::MAX_LENGTH) return $fail('invalid_length');
		if (preg_match('/[\x00-\x20\x7f]/', $url)) return $fail('control_or_space_character');
		if (strpos($url, '\\') !== false) return $fail('backslash_not_allowed');
		$p = parse_url($url);
		if ($p === false || !isset($p['scheme'], $p['host'])) return $fail('unparseable');
		$scheme = strtolower($p['scheme']);
		if ($scheme !== 'http' && $scheme !== 'https') return $fail('scheme_not_allowed');
		if (isset($p['user']) || isset($p['pass'])) return $fail('userinfo_not_allowed');
		$host = self::hostInfo($p['host']);
		if (!$host['ok']) return $fail($host['reason']);
		$port = isset($p['port']) ? (int) $p['port'] : ($scheme === 'https' ? 443 : 80);
		if ($port < 1 || $port > 65535) return $fail('invalid_port');
		$path = isset($p['path']) && $p['path'] !== '' ? $p['path'] : '/';
		$query = isset($p['query']) ? $p['query'] : null;
		$authority = ($host['type'] === 'ipv6' ? '[' . $host['host'] . ']' : $host['host'])
			. ((($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) ? '' : ':' . $port);
		return array('ok' => true, 'reason' => null, 'scheme' => $scheme, 'host' => $host['host'], 'hostType' => $host['type'],
			'port' => $port, 'path' => $path, 'query' => $query,
			'url' => $scheme . '://' . $authority . $path . ($query !== null ? '?' . $query : ''));
	}

	/**
	 * Strict host syntax. IPv4 literals must be canonical dotted-decimal; anything else made only of
	 * digits, dots or hex notation is rejected (resolver functions would turn such strings into IPs).
	 * @return array ['ok', 'reason', 'host', 'type' => hostname|ipv4|ipv6]
	 */
	public static function hostInfo($host) {
		$host = strtolower((string) $host);
		if (strlen($host) > 2 && $host[0] === '[' && substr($host, -1) === ']') {
			$inner = substr($host, 1, -1);
			if (strpos($inner, '%') !== false || !filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return array('ok' => false, 'reason' => 'invalid_ipv6');
			return array('ok' => true, 'reason' => null, 'host' => inet_ntop(inet_pton($inner)), 'type' => 'ipv6');
		}
		if (substr($host, -1) === '.') $host = substr($host, 0, -1);
		$octet = '(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])';
		if (preg_match('/^' . $octet . '(\.' . $octet . '){3}$/', $host)) return array('ok' => true, 'reason' => null, 'host' => $host, 'type' => 'ipv4');
		if ($host === '' || strlen($host) > 253) return array('ok' => false, 'reason' => 'invalid_host');
		$labels = explode('.', $host);
		foreach ($labels as $label) {
			if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label)) return array('ok' => false, 'reason' => 'invalid_host');
		}
		$last = end($labels);
		if (preg_match('/^[0-9]+$/', $last) || preg_match('/^0x[0-9a-f]*$/', $last)) return array('ok' => false, 'reason' => 'non_canonical_ip_literal');
		return array('ok' => true, 'reason' => null, 'host' => $host, 'type' => 'hostname');
	}

	/**
	 * RFC 3986 5.2.2 reference resolution. Fragments are dropped.
	 * @return string|null Absolute URL, or null when the reference cannot be resolved
	 */
	public static function resolve($base, $ref) {
		$ref = trim((string) $ref);
		if (($hash = strpos($ref, '#')) !== false) $ref = substr($ref, 0, $hash);
		$b = parse_url($base);
		if ($b === false || !isset($b['scheme'], $b['host'])) return null;
		if ($ref === '') return self::build($b['scheme'], self::authority($b), isset($b['path']) ? $b['path'] : '/', isset($b['query']) ? $b['query'] : null);
		if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.-]*):/', $ref, $m)) {
			$r = parse_url($ref);
			if ($r === false) return null;
			if (!isset($r['host'])) return strtolower($m[1]) === 'http' || strtolower($m[1]) === 'https' ? null : $ref;
			return self::build(strtolower($r['scheme']), self::authority($r), self::removeDotSegments(isset($r['path']) ? $r['path'] : ''), isset($r['query']) ? $r['query'] : null);
		}
		if (substr($ref, 0, 2) === '//') {
			$r = parse_url($b['scheme'] . ':' . $ref);
			if ($r === false || !isset($r['host'])) return null;
			return self::build($b['scheme'], self::authority($r), self::removeDotSegments(isset($r['path']) ? $r['path'] : ''), isset($r['query']) ? $r['query'] : null);
		}
		$qpos = strpos($ref, '?');
		$rPath = $qpos === false ? $ref : substr($ref, 0, $qpos);
		$rQuery = $qpos === false ? null : substr($ref, $qpos + 1);
		$bPath = isset($b['path']) ? $b['path'] : '';
		if ($rPath === '') {
			$path = $bPath;
			$query = $rQuery !== null ? $rQuery : (isset($b['query']) ? $b['query'] : null);
		} else {
			if ($rPath[0] === '/') {
				$path = self::removeDotSegments($rPath);
			} else {
				$merged = ($bPath === '' ? '/' : substr($bPath, 0, strrpos($bPath, '/') + 1)) . $rPath;
				$path = self::removeDotSegments($merged);
			}
			$query = $rQuery;
		}
		return self::build($b['scheme'], self::authority($b), $path, $query);
	}

	/** RFC 3986 5.2.4 */
	public static function removeDotSegments($path) {
		$output = array();
		$segments = explode('/', $path);
		$absolute = substr($path, 0, 1) === '/';
		foreach ($segments as $i => $segment) {
			if ($segment === '.' ) { if ($i === count($segments) - 1) $output[] = ''; continue; }
			if ($segment === '..') { if (count($output) > ($absolute ? 1 : 0)) array_pop($output); if ($i === count($segments) - 1) $output[] = ''; continue; }
			$output[] = $segment;
		}
		$result = implode('/', $output);
		if ($absolute && substr($result, 0, 1) !== '/') $result = '/' . $result;
		return $result === '' && $absolute ? '/' : $result;
	}

	/** @return string Deduplication key (never fetched) */
	public static function key($url) {
		return hash('sha256', DoajPaFindingKey::normalizeUrl($url));
	}

	private static function authority($p) {
		return (isset($p['user']) ? $p['user'] . (isset($p['pass']) ? ':' . $p['pass'] : '') . '@' : '')
			. $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
	}

	private static function build($scheme, $authority, $path, $query) {
		return strtolower($scheme) . '://' . $authority . ($path === '' ? '/' : $path) . ($query !== null ? '?' . $query : '');
	}
}
