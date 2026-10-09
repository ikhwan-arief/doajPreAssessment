<?php

/**
 * @file tests/support/DoajPaDevPrivateAllowance.inc.php
 *
 * @class DoajPaDevPrivateAllowance
 * @brief DEVELOPMENT/TEST ONLY - NEVER PACKAGED (tools/build-package.sh excludes tests/). Allows the crawler
 *  to contact exact host:port=ip triples on private addresses (disposable test stacks, fixture servers).
 *  Loaded by DoajPaAddressGuard::loadDevAllowance() only when DOAJ_PA_DEV_ALLOW_PRIVATE has the exact
 *  switch value. Any malformed entry disables the whole allowance (fail closed). Wildcards, ranges and
 *  CIDR notation are not accepted.
 */

class DoajPaDevPrivateAllowance {

	const HOSTS_ENV = 'DOAJ_PA_DEV_ALLOW_HOSTS';

	/** @var array "host:port" => ip */
	private $_pairs;

	private function __construct($pairs) {
		$this->_pairs = $pairs;
	}

	/** @return DoajPaDevPrivateAllowance|null */
	public static function fromEnvironment() {
		$raw = getenv(self::HOSTS_ENV);
		if (!is_string($raw) || trim($raw) === '') return null;
		$pairs = array();
		foreach (explode(',', $raw) as $entry) {
			if (!preg_match('/^([a-z0-9][a-z0-9.-]{0,252}):([0-9]{1,5})=([0-9a-f:.]+)$/', trim($entry), $m)) return null;
			if (filter_var($m[3], FILTER_VALIDATE_IP) === false) return null;
			$pairs[$m[1] . ':' . (int) $m[2]] = inet_ntop(inet_pton($m[3]));
		}
		return new self($pairs);
	}

	/** @return boolean */
	public function allows($host, $port, $ip) {
		$key = strtolower($host) . ':' . (int) $port;
		return isset($this->_pairs[$key]) && @inet_pton($ip) !== false && $this->_pairs[$key] === inet_ntop(inet_pton($ip));
	}
}
