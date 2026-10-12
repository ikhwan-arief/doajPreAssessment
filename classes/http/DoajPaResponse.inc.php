<?php

/**
 * @file classes/http/DoajPaResponse.inc.php
 *
 * @class DoajPaResponse
 * @brief Terminal HTTP responses for plugin routes. OJS 3.3 answers authorization failures with a
 *  redirect or an HTTP 200 JSON message (PKPPageRouter/PKPComponentRouter::handleAuthorizationFailure);
 *  PROD-02 requires real status codes with no data in the body.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaResponse {

	/**
	 * @param $status int
	 * @param $message string Fixed English text; never request data
	 * @param $extraHeaders array
	 */
	public static function terminate($status, $message, $extraHeaders = array()) {
		if (!headers_sent()) {
			http_response_code((int) $status);
			header('Content-Type: text/plain; charset=utf-8');
			header('Cache-Control: no-store, private');
			header('X-Content-Type-Options: nosniff');
			foreach ($extraHeaders as $header) header($header);
		}
		echo $message . "\n";
		exit;
	}

	public static function forbidden() {
		self::terminate(403, '403 Forbidden. You are not authorized to use DOAJ Pre-Assessment for this journal.');
	}

	public static function schemaMissing() {
		self::terminate(503, '503 Service Unavailable. The DOAJ Pre-Assessment database tables are not installed. '
			. 'Install the plugin through Settings > Website > Plugins > Upload A New Plugin, or run "php tools/upgrade.php upgrade" '
			. 'on the server after copying the plugin into plugins/generic.');
	}

	public static function notFound() {
		self::terminate(404, '404 Not Found.');
	}

	/**
	 * @param $allowed array HTTP methods
	 */
	public static function methodNotAllowed($allowed) {
		self::terminate(405, '405 Method Not Allowed.', array('Allow: ' . implode(', ', $allowed)));
	}
}
