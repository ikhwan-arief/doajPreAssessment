<?php

/**
 * @file classes/crawler/DoajPaAddressGuard.inc.php
 *
 * @class DoajPaAddressGuard
 * @brief Decides whether a host may be contacted and which IP address to pin the connection to
 *  (ADR-005 R1.1). Every resolved address must be public; one non-public answer blocks the host
 *  (mixed answers are a DNS-rebinding pattern). The chosen IP is pinned with CURLOPT_RESOLVE, so the
 *  connection cannot be re-resolved to another address.
 *
 * Development-only exception: only if the environment variable DOAJ_PA_DEV_ALLOW_PRIVATE equals
 * "I-UNDERSTAND-THIS-IS-A-TEST-STACK" AND the file tests/support/DoajPaDevPrivateAllowance.inc.php exists
 * (it is never packaged) AND DOAJ_PA_DEV_ALLOW_HOSTS lists the exact host:port=ip triple.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/DoajPaIpPolicy.inc.php');
require_once(dirname(__FILE__) . '/DoajPaUrlTools.inc.php');

/** Resolves a hostname to all of its A and AAAA addresses. */
class DoajPaDnsResolver {
	/** @return array IP strings (empty when the name does not resolve) */
	public function resolve($host) {
		$ips = array();
		$v4 = @gethostbynamel($host);
		if (is_array($v4)) $ips = array_merge($ips, $v4);
		$v6 = @dns_get_record($host, DNS_AAAA);
		if (is_array($v6)) foreach ($v6 as $record) if (isset($record['ipv6'])) $ips[] = $record['ipv6'];
		return array_values(array_unique($ips));
	}
}

class DoajPaAddressGuard {

	const DEV_SWITCH_ENV = 'DOAJ_PA_DEV_ALLOW_PRIVATE';
	const DEV_SWITCH_VALUE = 'I-UNDERSTAND-THIS-IS-A-TEST-STACK';

	/** @var object resolve($host) => array */
	private $_resolver;
	/** @var object|null allows($host, $port, $ip) */
	private $_devAllowance;

	public function __construct($resolver, $devAllowance = null) {
		$this->_resolver = $resolver;
		$this->_devAllowance = $devAllowance;
	}

	/**
	 * @param $host string As returned by DoajPaUrlTools::parse()
	 * @param $hostType string hostname|ipv4|ipv6
	 * @param $port int
	 * @return array ['ok' => bool, 'ip' => string|null, 'reason' => string|null, 'devException' => bool]
	 */
	public function check($host, $hostType, $port) {
		$ips = $hostType === 'hostname' ? $this->_resolver->resolve($host) : array($host);
		if (!$ips) return array('ok' => false, 'ip' => null, 'reason' => 'dns_no_address', 'devException' => false);
		$blocked = array();
		foreach ($ips as $ip) {
			$reason = DoajPaIpPolicy::blockedReason($ip);
			if ($reason !== null) $blocked[$ip] = $reason;
		}
		if (!$blocked) {
			sort($ips);
			return array('ok' => true, 'ip' => $ips[0], 'reason' => null, 'devException' => false);
		}
		if ($this->_devAllowance !== null && count($ips) === 1 && $this->_devAllowance->allows($host, (int) $port, $ips[0])) {
			return array('ok' => true, 'ip' => $ips[0], 'reason' => 'dev_exception:' . reset($blocked), 'devException' => true);
		}
		return array('ok' => false, 'ip' => null, 'reason' => 'blocked_address:' . reset($blocked), 'devException' => false);
	}

	/**
	 * The development-only allowance, or null (always null in a packaged build: the file is not shipped).
	 * @param $pluginPath string Absolute plugin directory
	 * @return object|null
	 */
	public static function loadDevAllowance($pluginPath) {
		if (getenv(self::DEV_SWITCH_ENV) !== self::DEV_SWITCH_VALUE) return null;
		$file = rtrim($pluginPath, '/') . '/tests/support/DoajPaDevPrivateAllowance.inc.php';
		if (!is_file($file)) return null;
		require_once($file);
		return class_exists('DoajPaDevPrivateAllowance') ? DoajPaDevPrivateAllowance::fromEnvironment() : null;
	}
}
