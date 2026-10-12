<?php

/**
 * @file classes/crawler/DoajPaIpPolicy.inc.php
 *
 * @class DoajPaIpPolicy
 * @brief Classifies IP addresses for SSRF protection (ADR-005). Pure; no OJS dependency.
 *  Only globally routable unicast addresses are public. IPv6 forms that embed an IPv4 address
 *  (IPv4-mapped, IPv4-compatible, NAT64 64:ff9b::/96, 6to4 2002::/16) are judged by that IPv4 address.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaIpPolicy {

	/** IPv4 ranges that are never fetched: [CIDR, reason]. */
	const V4_BLOCKED = array(
		array('0.0.0.0/8', 'unspecified'), array('10.0.0.0/8', 'private'), array('100.64.0.0/10', 'cgnat'),
		array('127.0.0.0/8', 'loopback'), array('169.254.0.0/16', 'link_local'), array('172.16.0.0/12', 'private'),
		array('192.0.0.0/24', 'reserved'), array('192.0.2.0/24', 'documentation'), array('192.88.99.0/24', 'reserved'),
		array('192.168.0.0/16', 'private'), array('198.18.0.0/15', 'benchmarking'), array('198.51.100.0/24', 'documentation'),
		array('203.0.113.0/24', 'documentation'), array('224.0.0.0/4', 'multicast'), array('240.0.0.0/4', 'reserved'),
	);

	/** IPv6 ranges that are never fetched (embedded-IPv4 forms are handled separately). */
	const V6_BLOCKED = array(
		array('::/128', 'unspecified'), array('::1/128', 'loopback'), array('64:ff9b:1::/48', 'reserved'),
		array('100::/64', 'reserved'), array('2001::/23', 'reserved'), array('2001:db8::/32', 'documentation'),
		array('fc00::/7', 'unique_local'), array('fe80::/10', 'link_local'), array('fec0::/10', 'site_local'),
		array('ff00::/8', 'multicast'),
	);

	/**
	 * @param $ip string Canonical IPv4 or IPv6 address (no brackets, no zone)
	 * @return string|null null if public; otherwise the reason it is blocked
	 */
	public static function blockedReason($ip) {
		if (!is_string($ip) || strpos($ip, '%') !== false) return 'invalid';
		$bin = @inet_pton($ip);
		if ($bin === false) return 'invalid';
		if (strlen($bin) === 4) return self::matchList($bin, self::V4_BLOCKED);
		$embedded = self::embeddedV4($bin);
		if ($embedded !== null) {
			$reason = self::matchList($embedded, self::V4_BLOCKED);
			return $reason === null ? null : 'embedded_ipv4_' . $reason;
		}
		return self::matchList($bin, self::V6_BLOCKED);
	}

	/**
	 * @param $bin string 16-byte IPv6 address
	 * @return string|null 4-byte embedded IPv4 address, if the IPv6 address is a form that carries one
	 */
	private static function embeddedV4($bin) {
		if (substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") return substr($bin, 12, 4);           // ::ffff:0:0/96
		if (substr($bin, 0, 12) === str_repeat("\0", 12) && substr($bin, 12) !== "\0\0\0\0" && substr($bin, 12) !== "\0\0\0\1") {
			return substr($bin, 12, 4);                                                                         // ::/96 (compatible)
		}
		if (substr($bin, 0, 12) === "\x00\x64\xff\x9b" . str_repeat("\0", 8)) return substr($bin, 12, 4);       // 64:ff9b::/96
		if (substr($bin, 0, 2) === "\x20\x02") return substr($bin, 2, 4);                                       // 2002::/16
		return null;
	}

	private static function matchList($bin, $list) {
		foreach ($list as $entry) {
			list($cidr, $reason) = $entry;
			list($net, $bits) = explode('/', $cidr);
			$netBin = inet_pton($net);
			if (strlen($netBin) !== strlen($bin)) continue;
			if (self::prefixEquals($bin, $netBin, (int) $bits)) return $reason;
		}
		return null;
	}

	private static function prefixEquals($a, $b, $bits) {
		$bytes = intdiv($bits, 8);
		if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) return false;
		$rest = $bits % 8;
		if ($rest === 0) return true;
		$mask = (0xff << (8 - $rest)) & 0xff;
		return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
	}
}
