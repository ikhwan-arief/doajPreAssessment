<?php

/**
 * @file classes/reports/DoajPaReportHtml.inc.php
 *
 * @class DoajPaReportHtml
 * @brief Renders a report model (DoajPaReportModel) as one self-contained HTML document: every text escaped, no script,
 *  no external resource (a restrictive Content-Security-Policy), A4 print layout. Pure; deterministic for a model.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaReportHtml {

	const CSS = '@page{size:A4;margin:18mm 16mm}body{font-family:Helvetica,Arial,sans-serif;font-size:10.5pt;line-height:1.4;color:#111;margin:0 auto;max-width:180mm;padding:8mm}h1{font-size:17pt;margin:0 0 2mm}h2{font-size:13pt;margin:7mm 0 2mm;border-bottom:1px solid #999;page-break-after:avoid}h3{font-size:11pt;margin:4mm 0 1mm;page-break-after:avoid}.sub{color:#333;margin:0 0 4mm}.notice{border:1px solid #8a6d00;background:#fff8e1;padding:2mm 3mm;margin:2mm 0}table{border-collapse:collapse;width:100%;table-layout:fixed;margin:2mm 0;font-size:9pt}th,td{border:1px solid #888;padding:1.2mm 1.6mm;text-align:left;vertical-align:top;white-space:pre-wrap;overflow-wrap:break-word}th{overflow-wrap:normal;word-break:normal;hyphens:none}thead th{background:#eee}tr{page-break-inside:avoid}dl{display:grid;grid-template-columns:52mm 1fr;gap:1mm 3mm;margin:2mm 0}dt{font-weight:bold}dd{margin:0;overflow-wrap:anywhere}li{margin:0 0 1mm;overflow-wrap:anywhere}';

	/** @return string HTML (UTF-8) */
	public static function render($model) {
		$h = '<!DOCTYPE html>' . "\n" . '<html lang="en"><head><meta charset="utf-8">'
			. '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . self::e($model['title']) . '</title><style>' . self::CSS . '</style></head><body>' . "\n"
			. '<h1>' . self::e($model['title']) . '</h1><p class="sub">' . self::e($model['subtitle']) . '</p>' . "\n";
		foreach ($model['sections'] as $s) {
			$h .= '<section><h2>' . self::e($s['heading']) . '</h2>' . "\n";
			foreach ($s['blocks'] as $b) $h .= self::block($b) . "\n";
			$h .= '</section>' . "\n";
		}
		return $h . '</body></html>' . "\n";
	}

	private static function block($b) {
		switch ($b['type']) {
			case 'notice': return '<p class="notice" role="note"><strong>Notice:</strong> ' . self::e($b['text']) . '</p>';
			case 'p': return '<p>' . self::e($b['text']) . '</p>';
			case 'subheading': return '<h3>' . self::e($b['text']) . '</h3>';
			case 'list':
				$o = '<ul>';
				foreach ($b['items'] as $i) $o .= '<li>' . self::e($i) . '</li>';
				return $o . '</ul>';
			case 'kv':
				$o = '<dl>';
				foreach ($b['rows'] as $r) $o .= '<dt>' . self::e($r[0]) . '</dt><dd>' . self::e($r[1]) . '</dd>';
				return $o . '</dl>';
			case 'table':
				$o = '<table><thead><tr>';
				foreach ($b['head'] as $i => $c) $o .= '<th scope="col" style="width:' . round(100 * $b['widths'][$i]) . '%">' . self::e($c) . '</th>';
				$o .= '</tr></thead><tbody>';
				foreach ($b['rows'] as $r) {
					$o .= '<tr>';
					foreach ($r as $c) $o .= '<td>' . self::e($c) . '</td>';
					$o .= '</tr>';
				}
				return $o . '</tbody></table>';
		}
		return '';
	}

	private static function e($text) {
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
