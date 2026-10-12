<?php

/**
 * @file classes/reports/DoajPaReportPdf.inc.php
 *
 * @class DoajPaReportPdf
 * @brief Renders a report model (DoajPaReportModel) as a PDF 1.4 document, A4 portrait (595.28 x 841.89 pt). Pure; no
 *  third-party library and no font files: the standard Type 1 fonts Helvetica and Helvetica-Bold with WinAnsiEncoding
 *  (Windows-1252), uncompressed content streams (the text can be extracted, for example by pdftotext), tables with
 *  repeated headers, rows split across pages when needed, and page numbers. Deterministic for a model.
 *  A character that Windows-1252 cannot encode is never dropped or replaced silently: it is written as [U+XXXX], and
 *  the report states how many there are (the HTML and DOCX formats show them unchanged).
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaReportPdf {

	const PAGE_W = 595.28;
	const PAGE_H = 841.89;
	const MARGIN = 48.0;
	const FOOTER = 30.0;
	const PAD = 3.0;

	/** Unicode code point => Windows-1252 byte for 0x80..0x9F */
	const CP1252 = array(0x20AC => 0x80, 0x201A => 0x82, 0x0192 => 0x83, 0x201E => 0x84, 0x2026 => 0x85, 0x2020 => 0x86, 0x2021 => 0x87, 0x02C6 => 0x88,
		0x2030 => 0x89, 0x0160 => 0x8A, 0x2039 => 0x8B, 0x0152 => 0x8C, 0x017D => 0x8E, 0x2018 => 0x91, 0x2019 => 0x92, 0x201C => 0x93, 0x201D => 0x94,
		0x2022 => 0x95, 0x2013 => 0x96, 0x2014 => 0x97, 0x02DC => 0x98, 0x2122 => 0x99, 0x0161 => 0x9A, 0x203A => 0x9B, 0x0153 => 0x9C, 0x017E => 0x9E, 0x0178 => 0x9F);

	/** Helvetica and Helvetica-Bold glyph widths (1/1000 em) for ASCII 32..126 (Adobe font metrics) */
	const W_REGULAR = array(278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556,
		278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944,
		667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556,
		500, 722, 500, 500, 500, 334, 260, 334, 584);
	const W_BOLD = array(278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556,
		333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944,
		667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611,
		556, 778, 556, 556, 500, 389, 280, 389, 584);
	/** Width assumed for Windows-1252 characters above 126 (wider than most glyphs, so lines never overflow) */
	const W_OTHER = 720;

	private $pages = array();
	private $out = '';
	private $y = 0.0;
	private $substituted = 0;

	/** @return string PDF bytes */
	public static function render($model, $createdUtc = null) {
		$r = new DoajPaReportPdf();
		return $r->document($model, $createdUtc);
	}

	/** @return int Characters of the model that Windows-1252 cannot encode (written as [U+XXXX]) */
	public static function unencodable($model) {
		$n = 0;
		foreach (DoajPaReportModel::texts($model) as $t) {
			foreach (preg_split('//u', (string) $t, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
				$cp = self::cp($ch);
				if ($cp > 0xFF ? !isset(self::CP1252[$cp]) : ($cp < 0x20 && $ch !== "\n") || ($cp >= 0x7F && $cp < 0xA0)) $n++;
			}
		}
		return $n;
	}

	private function document($model, $createdUtc) {
		$this->newPage();
		$this->wrapped($model['title'], true, 16, 0);
		$this->y -= 2;
		$this->wrapped($model['subtitle'], false, 10, 0);
		$this->y -= 6;
		$n = self::unencodable($model);
		if ($n > 0) $this->notice($n . ' character(s) that the PDF standard fonts cannot show are written as [U+XXXX] code points; the HTML and DOCX reports show them unchanged.');
		foreach ($model['sections'] as $s) {
			$this->heading($s['heading']);
			foreach ($s['blocks'] as $b) $this->block($b);
		}
		$this->endPage();
		return $this->assemble($model['title'], $createdUtc);
	}

	private function block($b) {
		switch ($b['type']) {
			case 'notice': $this->notice($b['text']); break;
			case 'p': $this->wrapped($b['text'], false, 9.5, 0); $this->y -= 4; break;
			case 'subheading': $this->ensure(40); $this->y -= 4; $this->wrapped($b['text'], true, 10.5, 0); $this->y -= 2; break;
			case 'list':
				foreach ($b['items'] as $i) {
					$this->ensure(14);
					$this->text(self::MARGIN + 4, $this->y - 9.5, false, 9.5, "\xE2\x80\xA2");
					$this->wrapped($i, false, 9.5, 14);
					$this->y -= 2;
				}
				$this->y -= 3;
				break;
			case 'kv':
				$rows = array();
				foreach ($b['rows'] as $r) $rows[] = array($r[0], $r[1]);
				$this->table(array(), $rows, array(0.3, 0.7), true);
				break;
			case 'table': $this->table($b['head'], $b['rows'], $b['widths'], false); break;
		}
	}

	//
	// Layout
	//

	private function width() {
		return self::PAGE_W - 2 * self::MARGIN;
	}

	private function newPage() {
		$this->out = '';
		$this->y = self::PAGE_H - self::MARGIN;
	}

	private function endPage() {
		$this->pages[] = $this->out;
	}

	private function ensure($h) {
		if ($this->y - $h < self::MARGIN + self::FOOTER) {
			$this->endPage();
			$this->newPage();
			return true;
		}
		return false;
	}

	private function heading($text) {
		$this->ensure(60);
		$this->y -= 8;
		$this->wrapped($text, true, 13, 0);
		$this->out .= sprintf("0.6 G 0.6 w %.2F %.2F m %.2F %.2F l S\n", self::MARGIN, $this->y - 1, self::PAGE_W - self::MARGIN, $this->y - 1);
		$this->y -= 6;
	}

	/** Wrapped text at the current position; moves the position down */
	private function wrapped($text, $bold, $size, $indent) {
		$lead = $size * 1.3;
		foreach (self::wrap($text, $bold, $size, $this->width() - $indent) as $line) {
			$this->ensure($lead);
			$this->text(self::MARGIN + $indent, $this->y - $size, $bold, $size, $line);
			$this->y -= $lead;
		}
	}

	private function notice($text) {
		$size = 9.5; $lead = $size * 1.3;
		$lines = self::wrap('Notice: ' . $text, false, $size, $this->width() - 2 * self::PAD - 4);
		while ($lines) {
			$this->ensure($lead + 2 * self::PAD);
			$fit = max(1, (int) floor(($this->y - self::MARGIN - self::FOOTER - 2 * self::PAD) / $lead));
			$chunk = array_splice($lines, 0, $fit);
			$h = count($chunk) * $lead + 2 * self::PAD;
			$this->out .= sprintf("0.996 0.973 0.882 rg %.2F %.2F %.2F %.2F re f 0.54 0.43 0 RG 0.8 w %.2F %.2F %.2F %.2F re S 0 g\n",
				self::MARGIN, $this->y - $h, $this->width(), $h, self::MARGIN, $this->y - $h, $this->width(), $h);
			$y = $this->y - self::PAD;
			foreach ($chunk as $line) { $this->text(self::MARGIN + self::PAD + 2, $y - $size, false, $size, $line); $y -= $lead; }
			$this->y -= $h;
		}
		$this->y -= 6;
	}

	/** Table with column widths (fractions of the text width); the header row repeats on each page */
	private function table($head, $rows, $widths, $boldFirst) {
		$size = 8.0; $lead = $size * 1.25;
		$cols = array();
		foreach ($widths as $w) $cols[] = $this->width() * $w;
		$headLines = array();
		foreach ($head as $i => $c) $headLines[] = self::wrap($c, true, $size, $cols[$i] - 2 * self::PAD);
		$headH = $head ? max(array_map('count', $headLines)) * $lead + 2 * self::PAD : 0;
		$this->ensure($headH + $lead + 2 * self::PAD);
		if ($head) $this->tableRow($headLines, $cols, $size, $lead, true, true);
		foreach ($rows as $r) {
			$cells = array();
			foreach ($r as $i => $c) $cells[] = self::wrap($c, $boldFirst && $i === 0, $size, $cols[$i] - 2 * self::PAD);
			while ($cells) {
				$need = max(array_map('count', $cells)) * $lead + 2 * self::PAD;
				$room = $this->y - self::MARGIN - self::FOOTER;
				if ($need <= $room) { $this->tableRow($cells, $cols, $size, $lead, false, $boldFirst); break; }
				// A row that fits on a fresh page moves there; only a row taller than a page is split.
				$full = self::PAGE_H - 2 * self::MARGIN - self::FOOTER - $headH;
				$fit = (int) floor(($room - 2 * self::PAD) / $lead);
				if ($need <= $full || $fit < 2) {
					$this->ensure(self::PAGE_H);
					if ($head) $this->tableRow($headLines, $cols, $size, $lead, true, true);
					continue;
				}
				$part = array(); $rest = array();
				foreach ($cells as $lines) { $part[] = array_slice($lines, 0, $fit); $rest[] = array_slice($lines, $fit); }
				$this->tableRow($part, $cols, $size, $lead, false, $boldFirst);
				$this->ensure(self::PAGE_H);
				if ($head) $this->tableRow($headLines, $cols, $size, $lead, true, true);
				$cells = max(array_map('count', $rest)) > 0 ? $rest : array();
			}
		}
		$this->y -= 6;
	}

	private function tableRow($cells, $cols, $size, $lead, $isHead, $boldFirst) {
		$h = max(1, max(array_map('count', $cells))) * $lead + 2 * self::PAD;
		$x = self::MARGIN;
		if ($isHead) $this->out .= sprintf("0.91 g %.2F %.2F %.2F %.2F re f 0 g\n", $x, $this->y - $h, array_sum($cols), $h);
		foreach ($cells as $i => $lines) {
			$y = $this->y - self::PAD;
			foreach ($lines as $line) { $this->text($x + self::PAD, $y - $size, $isHead || ($boldFirst && $i === 0), $size, $line); $y -= $lead; }
			$this->out .= sprintf("0.53 G 0.5 w %.2F %.2F %.2F %.2F re S\n", $x, $this->y - $h, $cols[$i], $h);
			$x += $cols[$i];
		}
		$this->y -= $h;
	}

	private function text($x, $y, $bold, $size, $line) {
		$this->out .= sprintf("BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $x, $y, self::escape(self::encode($line)));
	}

	//
	// Text: encoding, metrics, wrapping
	//

	private static function cp($ch) {
		$u = unpack('N', mb_convert_encoding($ch, 'UCS-4BE', 'UTF-8'));
		return $u[1];
	}

	/** @return string Windows-1252 bytes; unencodable characters as [U+XXXX] */
	public static function encode($text) {
		$out = '';
		foreach (preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
			$cp = self::cp($ch);
			if ($cp >= 0x20 && $cp < 0x7F) $out .= chr($cp);
			elseif ($cp >= 0xA0 && $cp <= 0xFF) $out .= chr($cp);
			elseif (isset(self::CP1252[$cp])) $out .= chr(self::CP1252[$cp]);
			elseif ($cp === 0x09) $out .= ' ';
			else $out .= sprintf('[U+%04X]', $cp);
		}
		return $out;
	}

	/** Width in points of Windows-1252 bytes */
	private static function measure($bytes, $bold, $size) {
		$w = 0;
		$table = $bold ? self::W_BOLD : self::W_REGULAR;
		$n = strlen($bytes);
		for ($i = 0; $i < $n; $i++) {
			$c = ord($bytes[$i]);
			$w += ($c >= 32 && $c <= 126) ? $table[$c - 32] : self::W_OTHER;
		}
		return $w * $size / 1000;
	}

	/** @return string[] UTF-8 lines that fit the width (words kept whole when possible; "\n" starts a new line) */
	public static function wrap($text, $bold, $size, $width) {
		$lines = array();
		foreach (explode("\n", (string) $text) as $para) {
			$line = '';
			foreach (preg_split('/ +/', $para) as $word) {
				$try = $line === '' ? $word : $line . ' ' . $word;
				if (self::measure(self::encode($try), $bold, $size) <= $width) { $line = $try; continue; }
				if ($line !== '') $lines[] = $line;
				$line = '';
				// A word longer than the line is broken by characters.
				foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
					if ($line !== '' && self::measure(self::encode($line . $ch), $bold, $size) > $width) { $lines[] = $line; $line = ''; }
					$line .= $ch;
				}
			}
			$lines[] = $line;
		}
		return $lines;
	}

	private static function escape($bytes) {
		$out = '';
		$n = strlen($bytes);
		for ($i = 0; $i < $n; $i++) {
			$c = $bytes[$i];
			$o = ord($c);
			if ($c === '(' || $c === ')' || $c === '\\') $out .= '\\' . $c;
			elseif ($o < 32 || $o > 126) $out .= sprintf('\\%03o', $o);
			else $out .= $c;
		}
		return $out;
	}

	//
	// File structure
	//

	private function assemble($title, $createdUtc) {
		$total = count($this->pages);
		$objects = array();
		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$kids = array();
		$fontF1 = 3; $fontF2 = 4; $info = 5;
		$objects[$fontF1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[$fontF2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$date = $createdUtc ? 'D:' . preg_replace('/[^0-9]/', '', $createdUtc) . "Z" : null;
		$objects[$info] = '<< /Title (' . self::escape(self::encode($title)) . ') /Producer (DOAJ Pre-Assessment plugin)' . ($date ? ' /CreationDate (' . $date . ')' : '') . ' >>';
		$id = 6;
		foreach ($this->pages as $i => $content) {
			$footer = sprintf("BT /F1 8.00 Tf %.2F %.2F Td (%s) Tj ET\n", self::MARGIN, self::MARGIN - 6,
				self::escape(self::encode('DOAJ Pre-Assessment report - not a DOAJ decision - page ' . ($i + 1) . ' of ' . $total)));
			$stream = $content . $footer;
			$pageId = $id++; $contentId = $id++;
			$objects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>',
				self::PAGE_W, self::PAGE_H, $fontF1, $fontF2, $contentId);
			$objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
			$kids[] = $pageId . ' 0 R';
		}
		$objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
		ksort($objects);
		$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ($objects as $n => $body) {
			$offsets[$n] = strlen($pdf);
			$pdf .= $n . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen($pdf);
		$max = max(array_keys($objects));
		$pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
		for ($n = 1; $n <= $max; $n++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
		return $pdf . "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Info " . $info . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
	}
}
