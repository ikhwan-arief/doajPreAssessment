<?php

/**
 * @file classes/crawler/DoajPaPreProductionPolicy.inc.php
 *
 * @class DoajPaPreProductionPolicy
 * @brief Pre-production mode (ADR-005 R1.1, revision 2026-11-13). Lets the crawler read the journal's OWN
 *  website when that website is reachable only on a loopback or private address, for example a staging copy
 *  checked before it is installed on the production server.
 *
 *  - Enabled only by the server administrator in config.inc.php (section [doajPreAssessment]); never from the UI.
 *  - Covers exactly one origin: the scheme, host and port of the journal homepage of the run. Any other host
 *    or port stays under the normal public-only rule.
 *  - Allowed address classes: loopback, private (RFC 1918), unique local (fc00::/7) and CGNAT. Link-local
 *    (cloud metadata), multicast, unspecified, reserved and documentation ranges are never allowed.
 *  - Optional connect_to maps host:port to ip:port (the URL and the Host header stay unchanged), for example
 *    when OJS sees itself as https://localhost:8445 but inside its container listens on 127.0.0.1:443.
 *  - Optional insecure_tls skips certificate verification for this origin only (self-signed test certificates).
 *  - Any malformed setting disables the whole mode (fail closed). Every run that uses it is flagged and audited.
 *
 * Configuration (config.inc.php):
 *   [doajPreAssessment]
 *   preproduction_private_targets = On
 *   preproduction_connect_to = "localhost:8445=127.0.0.1:443"
 *   preproduction_insecure_tls = On
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/DoajPaIpPolicy.inc.php');

class DoajPaPreProductionPolicy {

	const CONFIG_SECTION = 'doajPreAssessment';
	const ALLOWED_REASONS = array('loopback', 'private', 'unique_local', 'cgnat',
		'embedded_ipv4_loopback', 'embedded_ipv4_private', 'embedded_ipv4_cgnat');

	/** @var string lower-case host */
	private $_host;
	/** @var int */
	private $_port;
	/** @var array|null ['ip' => string, 'port' => int] */
	private $_connectTo;
	/** @var bool */
	private $_insecureTls;

	private function __construct($host, $port, $connectTo, $insecureTls) {
		$this->_host = $host;
		$this->_port = $port;
		$this->_connectTo = $connectTo;
		$this->_insecureTls = $insecureTls;
	}

	/**
	 * Build the policy from OJS configuration values.
	 * @param $homepageTarget array DoajPaCrawlScope::target() (host, port)
	 * @param $enabled mixed preproduction_private_targets
	 * @param $connectTo mixed preproduction_connect_to
	 * @param $insecureTls mixed preproduction_insecure_tls
	 * @return DoajPaPreProductionPolicy|null null when disabled or misconfigured
	 */
	public static function fromSettings($homepageTarget, $enabled, $connectTo, $insecureTls) {
		if (!self::isOn($enabled)) return null;
		if (!is_array($homepageTarget) || !isset($homepageTarget['host'], $homepageTarget['port'])) return null;
		$host = strtolower((string) $homepageTarget['host']);
		$port = (int) $homepageTarget['port'];
		$map = null;
		if (is_string($connectTo) && trim($connectTo) !== '') {
			if (!preg_match('/^\s*([a-z0-9][a-z0-9.-]{0,252}|\[[0-9a-f:.]+\]):([0-9]{1,5})\s*=\s*([0-9.]+|\[[0-9a-f:.]+\]):([0-9]{1,5})\s*$/i', $connectTo, $m)) return null;
			$fromHost = strtolower(trim($m[1], '[]'));
			$ip = trim($m[3], '[]');
			$toPort = (int) $m[4];
			if ($fromHost !== $host || (int) $m[2] !== $port) return null;
			if (filter_var($ip, FILTER_VALIDATE_IP) === false || $toPort < 1 || $toPort > 65535) return null;
			$ip = inet_ntop(inet_pton($ip));
			if (!self::addressAllowed($ip)) return null;
			$map = array('ip' => $ip, 'port' => $toPort);
		} elseif ($connectTo !== null && $connectTo !== '' && $connectTo !== false) {
			return null;
		}
		return new self($host, $port, $map, self::isOn($insecureTls));
	}

	/** Reads the [doajPreAssessment] section of config.inc.php. */
	public static function fromConfig($homepageTarget) {
		if (!class_exists('Config')) return null;
		return self::fromSettings($homepageTarget,
			Config::getVar(self::CONFIG_SECTION, 'preproduction_private_targets'),
			Config::getVar(self::CONFIG_SECTION, 'preproduction_connect_to'),
			Config::getVar(self::CONFIG_SECTION, 'preproduction_insecure_tls'));
	}

	/** @return bool Whether this host:port is the covered origin */
	public function covers($host, $port) {
		return strtolower((string) $host) === $this->_host && (int) $port === $this->_port;
	}

	/**
	 * Decide the connection for the covered origin.
	 * @param $resolvedIps array addresses from DNS (ignored when connect_to is set)
	 * @return array|null ['ip' => string, 'connectPort' => int|null, 'reason' => string] or null when not allowed
	 */
	public function decide($resolvedIps) {
		if ($this->_connectTo !== null) {
			return array('ip' => $this->_connectTo['ip'], 'connectPort' => $this->_connectTo['port'],
				'reason' => 'preproduction:' . DoajPaIpPolicy::blockedReason($this->_connectTo['ip']));
		}
		if (!$resolvedIps) return null;
		foreach ($resolvedIps as $ip) if (!self::addressAllowed($ip)) return null;
		sort($resolvedIps);
		return array('ip' => $resolvedIps[0], 'connectPort' => null, 'reason' => 'preproduction:' . DoajPaIpPolicy::blockedReason($resolvedIps[0]));
	}

	/** @return bool */
	public function insecureTls() {
		return $this->_insecureTls;
	}

	/** @return array Recorded in the crawl coverage and the audit log */
	public function describe() {
		return array('origin' => $this->_host . ':' . $this->_port,
			'connectTo' => $this->_connectTo === null ? null : $this->_connectTo['ip'] . ':' . $this->_connectTo['port'],
			'insecureTls' => $this->_insecureTls);
	}

	/** @return bool Only non-public addresses of the allowed classes */
	public static function addressAllowed($ip) {
		$reason = DoajPaIpPolicy::blockedReason($ip);
		return $reason !== null && in_array($reason, self::ALLOWED_REASONS, true);
	}

	private static function isOn($value) {
		if ($value === true || $value === 1) return true;
		return is_string($value) && in_array(strtolower(trim($value)), array('on', '1', 'true', 'yes'), true);
	}
}
