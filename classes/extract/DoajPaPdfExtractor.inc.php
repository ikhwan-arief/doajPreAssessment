<?php

/**
 * @file classes/extract/DoajPaPdfExtractor.inc.php
 *
 * @class DoajPaPdfExtractor
 * @brief PDF text with page markers through the external poppler "pdftotext" program (ADR-005 R1.5), run
 *  as a separate process with a timeout and an output cap. Nothing is bundled. Explicit outcomes (F4):
 *  ok, unavailable (no pdftotext), encrypted, corrupt, no_text (scanned / image-only), timeout.
 *  Text order follows pdftotext's reading-order heuristics; multi-column layouts may interleave.
 */

class DoajPaPdfExtractor {

	const VERSION = 'doajpa-pdf/1';
	const CANDIDATES = array('/usr/bin/pdftotext', '/usr/local/bin/pdftotext');
	const MAX_PAGES = 200;
	const MAX_OUTPUT = 5242880;
	const TIMEOUT = 30;

	/** @return string|null Path of pdftotext */
	public static function binary() {
		foreach (self::CANDIDATES as $path) if (is_file($path) && is_executable($path)) return $path;
		return null;
	}

	/** @return string e.g. "pdftotext 22.02.0", or "unavailable" */
	public static function toolVersion() {
		$bin = self::binary();
		if ($bin === null) return 'unavailable';
		$r = self::run(escapeshellarg($bin) . ' -v', 5, 4096);
		return preg_match('/pdftotext version ([0-9.]+)/', $r['stdout'] . $r['stderr'], $m) ? 'pdftotext ' . $m[1] : 'pdftotext unknown';
	}

	/**
	 * @param $bytes string PDF bytes
	 * @param $workDir string Private directory for the temporary file
	 * @return array status, reason, text (with [page N] markers), pages, version
	 */
	public static function extract($bytes, $workDir) {
		$out = array('status' => 'unavailable', 'reason' => null, 'text' => '', 'pages' => 0, 'version' => self::VERSION . ' (' . self::toolVersion() . ')');
		$bin = self::binary();
		if ($bin === null) { $out['reason'] = 'pdf_extractor_unavailable'; return $out; }
		if (substr($bytes, 0, 5) !== '%PDF-') { $out['status'] = 'corrupt'; $out['reason'] = 'not_a_pdf'; return $out; }
		if (!is_dir($workDir) && !@mkdir($workDir, 0700, true)) { $out['status'] = 'unavailable'; $out['reason'] = 'work_dir_unavailable'; return $out; }
		$file = tempnam($workDir, 'doajpa-pdf-');
		file_put_contents($file, $bytes);
		$cmd = escapeshellarg($bin) . ' -enc UTF-8 -l ' . (int) self::MAX_PAGES . ' ' . escapeshellarg($file) . ' -';
		$r = self::run($cmd, self::TIMEOUT, self::MAX_OUTPUT);
		@unlink($file);
		if ($r['timedOut']) { $out['status'] = 'timeout'; $out['reason'] = 'pdftotext_timeout'; return $out; }
		if ($r['exit'] !== 0) {
			$err = strtolower($r['stderr']);
			$encrypted = strpos($err, 'password') !== false || strpos($err, 'encrypt') !== false || $r['exit'] === 3;
			$out['status'] = $encrypted ? 'encrypted' : 'corrupt';
			$out['reason'] = ($encrypted ? 'encrypted_or_protected' : 'unreadable_pdf') . ' (exit ' . $r['exit'] . ')';
			return $out;
		}
		$pages = explode("\f", $r['stdout']);
		if (end($pages) === '') array_pop($pages);
		$out['pages'] = count($pages);
		$parts = array();
		$letters = 0;
		foreach ($pages as $i => $page) {
			$clean = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", $page)));
			$letters += preg_match_all('/\p{L}/u', $clean);
			$parts[] = '[page ' . ($i + 1) . "]\n" . $clean;
		}
		if ($letters < 20) { $out['status'] = 'no_text'; $out['reason'] = 'no_text_layer_scanned_or_image_only'; return $out; }
		$out['status'] = 'ok';
		$out['text'] = implode("\n\n", $parts);
		if (strlen($out['text']) > DoajPaHtmlExtractorLimits::MAX_TEXT_BYTES) {
			$out['text'] = mb_strcut($out['text'], 0, DoajPaHtmlExtractorLimits::MAX_TEXT_BYTES, 'UTF-8');
			$out['reason'] = 'text_truncated';
		}
		return $out;
	}

	/** @return array exit, stdout, stderr, timedOut */
	private static function run($cmd, $timeout, $maxOutput) {
		$proc = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if (!is_resource($proc)) return array('exit' => -1, 'stdout' => '', 'stderr' => 'proc_open failed', 'timedOut' => false);
		fclose($pipes[0]);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);
		$stdout = ''; $stderr = ''; $start = microtime(true); $timedOut = false;
		while (true) {
			$stdout .= (string) stream_get_contents($pipes[1]);
			$stderr .= (string) stream_get_contents($pipes[2]);
			$status = proc_get_status($proc);
			if (!$status['running']) break;
			if (microtime(true) - $start > $timeout || strlen($stdout) > $maxOutput) { $timedOut = microtime(true) - $start > $timeout; proc_terminate($proc, 9); break; }
			usleep(20000);
		}
		$stdout .= (string) stream_get_contents($pipes[1]);
		$stderr .= (string) stream_get_contents($pipes[2]);
		fclose($pipes[1]); fclose($pipes[2]);
		$exit = isset($status['exitcode']) && !$status['running'] ? $status['exitcode'] : proc_close($proc);
		if (is_resource($proc)) proc_close($proc);
		return array('exit' => (int) $exit, 'stdout' => substr($stdout, 0, $maxOutput), 'stderr' => substr($stderr, 0, 4096), 'timedOut' => $timedOut);
	}
}

/** Shared text cap. */
class DoajPaHtmlExtractorLimits {
	const MAX_TEXT_BYTES = 102400;
}
