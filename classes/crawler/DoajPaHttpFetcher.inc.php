<?php

/**
 * @file classes/crawler/DoajPaHttpFetcher.inc.php
 *
 * @class DoajPaHttpFetcher
 * @brief Unauthenticated, SSRF-hardened HTTP GET with ext-curl (ADR-005 R1.5).
 *
 *  - Redirects are never followed by curl. Each hop's Location is resolved, re-checked against the crawl
 *    scope and the address guard, then fetched; loops and more than MAX_REDIRECTS hops are refused.
 *  - The connection is pinned to the vetted IP (CURLOPT_RESOLVE) and the IP actually used is verified.
 *  - Only http/https protocols; no proxy (CURLOPT_PROXY '' and CURLOPT_NOPROXY '*'); no cookie engine;
 *    no Authorization or Cookie header is ever sent; TLS certificates are verified.
 *  - The body is capped after decompression (the write callback aborts beyond the cap), so compressed
 *    "bombs" cannot exhaust memory. Connect and total timeouts apply.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/DoajPaUrlTools.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAddressGuard.inc.php');

class DoajPaHttpFetcher {

	const MAX_REDIRECTS = 5;
	const USER_AGENT = 'DOAJPreAssessment/0.10 (OJS plugin; journal self-assessment)';

	/** @var DoajPaAddressGuard */
	private $_guard;
	/** @var callable scope check: fn(string $url): ?string reason */
	private $_scopeCheck;
	private $_connectTimeout;
	private $_totalTimeout;

	public function __construct($guard, $scopeCheck, $connectTimeout = 5, $totalTimeout = 20) {
		$this->_guard = $guard;
		$this->_scopeCheck = $scopeCheck;
		$this->_connectTimeout = (int) $connectTimeout;
		$this->_totalTimeout = (int) $totalTimeout;
	}

	/**
	 * @param $url string
	 * @param $maxBytes int
	 * @return array requestedUrl, finalUrl, status (int|null), mediaType, charset, body, bytes, sha256, error (null|code),
	 *  errorDetail, hops (list), devException (bool), devHosts (list), primaryIp, fetchedAtUtc
	 */
	public function fetch($url, $maxBytes) {
		$result = array('requestedUrl' => $url, 'finalUrl' => null, 'status' => null, 'mediaType' => null, 'charset' => null,
			'body' => '', 'bytes' => 0, 'sha256' => null, 'error' => null, 'errorDetail' => null, 'hops' => array(),
			'devException' => false, 'devHosts' => array(), 'primaryIp' => null, 'fetchedAtUtc' => gmdate('Y-m-d H:i:s'));
		$visited = array();
		$current = $url;
		for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
			$parsed = DoajPaUrlTools::parse($current);
			if (!$parsed['ok']) return $this->fail($result, 'invalid_url', $parsed['reason']);
			if ($hop > 0) {
				$scope = call_user_func($this->_scopeCheck, $parsed['url']);
				if ($scope !== null) return $this->fail($result, 'redirect_out_of_scope', $scope . ' -> ' . $parsed['url']);
			}
			$key = DoajPaUrlTools::key($parsed['url']);
			if (isset($visited[$key])) return $this->fail($result, 'redirect_loop', $parsed['url']);
			$visited[$key] = true;

			$address = $this->_guard->check($parsed['host'], $parsed['hostType'], $parsed['port']);
			if (!$address['ok']) return $this->fail($result, $hop === 0 ? 'blocked_destination' : 'redirect_blocked_destination', $address['reason']);
			if ($address['devException']) {
				$result['devException'] = true;
				$result['devHosts'][] = $parsed['host'] . ':' . $parsed['port'] . '=' . $address['ip'];
			}

			$response = $this->request($parsed, $address['ip'], $maxBytes);
			$result['hops'][] = array('url' => $parsed['url'], 'status' => $response['status'], 'ip' => $address['ip']);
			$result['fetchedAtUtc'] = gmdate('Y-m-d H:i:s');
			if ($response['error'] !== null) {
				$result['finalUrl'] = $parsed['url'];
				$result['status'] = $response['status'];
				return $this->fail($result, $response['error'], $response['errorDetail']);
			}
			$status = $response['status'];
			if ($status >= 300 && $status < 400 && $status !== 304 && $response['location'] !== null) {
				$next = DoajPaUrlTools::resolve($parsed['url'], $response['location']);
				if ($next === null) return $this->fail($result, 'redirect_invalid_location', $response['location']);
				$current = $next;
				continue;
			}
			$result['finalUrl'] = $parsed['url'];
			$result['status'] = $status;
			$result['mediaType'] = $response['mediaType'];
			$result['charset'] = $response['charset'];
			$result['body'] = $response['body'];
			$result['bytes'] = strlen($response['body']);
			$result['sha256'] = hash('sha256', $response['body']);
			$result['primaryIp'] = $response['primaryIp'];
			return $result;
		}
		return $this->fail($result, 'too_many_redirects', (string) self::MAX_REDIRECTS);
	}

	private function fail($result, $code, $detail) {
		$result['error'] = $code;
		$result['errorDetail'] = substr((string) $detail, 0, 250);
		$result['body'] = '';
		return $result;
	}

	/**
	 * One HTTP GET without redirects, pinned to $ip.
	 * @return array status, location, mediaType, charset, body, primaryIp, error, errorDetail
	 */
	private function request($parsed, $ip, $maxBytes) {
		$body = '';
		$truncated = false;
		$headers = array();
		$ch = curl_init();
		$pinHost = $parsed['hostType'] === 'ipv6' ? '[' . $parsed['host'] . ']' : $parsed['host'];
		$pinIp = strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip;
		curl_setopt_array($ch, array(
			CURLOPT_URL => $parsed['url'],
			CURLOPT_RESOLVE => array($pinHost . ':' . $parsed['port'] . ':' . $pinIp),
			CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_PROXY => '',
			CURLOPT_NOPROXY => '*',
			CURLOPT_CONNECTTIMEOUT => $this->_connectTimeout,
			CURLOPT_TIMEOUT => $this->_totalTimeout,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_ENCODING => '',
			CURLOPT_USERAGENT => self::USER_AGENT,
			CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml,application/pdf;q=0.9,*/*;q=0.1', 'Accept-Language: en'),
			CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
				if (count($headers) < 200) $headers[] = rtrim($line, "\r\n");
				return strlen($line);
			},
			CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$body, &$truncated, $maxBytes) {
				if (strlen($body) + strlen($data) > $maxBytes) {
					$truncated = true;
					return 0;
				}
				$body .= $data;
				return strlen($data);
			},
		));
		curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$primaryIp = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
		$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		curl_close($ch);

		$out = array('status' => $status ?: null, 'location' => null, 'mediaType' => null, 'charset' => null, 'body' => $body,
			'primaryIp' => $primaryIp, 'error' => null, 'errorDetail' => null);
		if ($truncated) return array_merge($out, array('error' => 'size_limit_exceeded', 'errorDetail' => 'more than ' . $maxBytes . ' bytes'));
		if ($errno !== 0) {
			$code = $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : ($errno === 60 || $errno === 35 || $errno === 51 ? 'tls_error' : 'network_error');
			return array_merge($out, array('error' => $code, 'errorDetail' => 'curl ' . $errno . ': ' . $error));
		}
		if ($primaryIp === '' || @inet_pton($primaryIp) !== @inet_pton($ip)) {
			return array_merge($out, array('error' => 'pinned_address_mismatch', 'errorDetail' => $primaryIp . ' != ' . $ip));
		}
		foreach ($headers as $line) {
			if (stripos($line, 'Location:') === 0) $out['location'] = trim(substr($line, 9));
		}
		if (is_string($contentType) && $contentType !== '') {
			$parts = array_map('trim', explode(';', $contentType));
			$out['mediaType'] = strtolower($parts[0]);
			foreach (array_slice($parts, 1) as $param) {
				if (stripos($param, 'charset=') === 0) $out['charset'] = strtolower(trim(substr($param, 8), " \"'"));
			}
		}
		return $out;
	}
}
