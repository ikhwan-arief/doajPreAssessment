<?php

/**
 * @file classes/extract/DoajPaPdfTextReader.inc.php
 *
 * @class DoajPaPdfTextReader
 * @brief Built-in PDF text reader in plain PHP (1.2.0.0, product-owner decision 2026-11-13), used when the external
 *  pdftotext program is not installed. No third-party code, no external process.
 *
 *  Reads text-based PDFs (as produced by word processors, LaTeX and OJS galley tools):
 *  - classic objects and compressed object streams (PDF 1.5+), FlateDecode streams;
 *  - the page tree in order, inherited resources, content streams (one or several);
 *  - text operators Tj, TJ, ' and " with line breaks from Td, TD, T-star and Tm; form XObjects one level deep;
 *  - fonts with a ToUnicode CMap (bfchar/bfrange, 1- or 2-byte codes); other simple fonts as WinAnsi/Latin-1.
 *  Not supported: encrypted PDFs (reported as encrypted), scanned/image-only pages (no text), fonts without a
 *  usable encoding (their text is skipped), filters other than FlateDecode in content streams.
 *  Hard limits on input size, decompressed bytes, objects, pages and time protect the server.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaPdfTextReader {

	const VERSION = 'php-reader/1';
	const MAX_INPUT = 20971520;
	const MAX_STREAM = 16777216;
	const MAX_TOTAL_INFLATED = 67108864;
	const MAX_OBJECTS = 50000;
	const MAX_PAGES = 200;
	const MAX_SECONDS = 20;

	/** @var string */
	private $pdf;
	/** @var array "num" => raw object body (dictionary + optional stream) */
	private $objects = array();
	private $inflated = 0;
	private $deadline;
	/** @var array font object key => decoder */
	private $fontCache = array();

	/**
	 * @param $bytes string
	 * @return array status (ok|encrypted|corrupt|no_text|timeout), reason, pages (list of page texts)
	 */
	public static function read($bytes) {
		$reader = new self();
		try {
			return $reader->run($bytes);
		} catch (DoajPaPdfReaderLimit $e) {
			return array('status' => 'timeout', 'reason' => 'pdf_reader_limit: ' . $e->getMessage(), 'pages' => array());
		} catch (\Throwable $e) {
			return array('status' => 'corrupt', 'reason' => 'unreadable_pdf (' . get_class($e) . ')', 'pages' => array());
		}
	}

	private function run($bytes) {
		$this->deadline = microtime(true) + self::MAX_SECONDS;
		if (strlen($bytes) > self::MAX_INPUT) throw new DoajPaPdfReaderLimit('file too large');
		if (substr($bytes, 0, 5) !== '%PDF-') return array('status' => 'corrupt', 'reason' => 'not_a_pdf', 'pages' => array());
		$this->pdf = $bytes;
		$this->indexObjects();
		if (preg_match('/\/Encrypt\s+\d+\s+\d+\s+R/', $bytes)) return array('status' => 'encrypted', 'reason' => 'encrypted_or_protected', 'pages' => array());
		$pages = $this->pageList();
		if (!$pages) return array('status' => 'corrupt', 'reason' => 'unreadable_pdf (no pages)', 'pages' => array());
		$texts = array();
		foreach (array_slice($pages, 0, self::MAX_PAGES) as $page) {
			$this->checkTime();
			$texts[] = $this->pageText($page);
		}
		return array('status' => 'ok', 'reason' => null, 'pages' => $texts);
	}

	private function checkTime() {
		if (microtime(true) > $this->deadline) throw new DoajPaPdfReaderLimit('time');
	}

	//
	// Objects
	//

	private function indexObjects() {
		if (!preg_match_all('/(?<![0-9])(\d{1,7})\s+(\d{1,5})\s+obj\b/', $this->pdf, $m, PREG_OFFSET_CAPTURE)) return;
		$count = count($m[0]);
		if ($count > self::MAX_OBJECTS) throw new DoajPaPdfReaderLimit('too many objects');
		for ($i = 0; $i < $count; $i++) {
			$start = $m[0][$i][1] + strlen($m[0][$i][0]);
			$end = $i + 1 < $count ? $m[0][$i + 1][1] : strlen($this->pdf);
			$body = substr($this->pdf, $start, $end - $start);
			$e = strrpos($body, 'endobj');
			if ($e !== false) $body = substr($body, 0, $e);
			$this->objects[(int) $m[1][$i][0]] = $body; // a later revision of the same object wins
		}
		// compressed object streams
		foreach ($this->objects as $num => $body) {
			if (!preg_match('/\/Type\s*\/ObjStm\b/', $this->dictOf($body))) continue;
			$this->checkTime();
			$data = $this->streamData($body);
			if ($data === null) continue;
			$dict = $this->dictOf($body);
			$n = preg_match('/\/N\s+(\d+)/', $dict, $x) ? (int) $x[1] : 0;
			$first = preg_match('/\/First\s+(\d+)/', $dict, $x) ? (int) $x[1] : 0;
			if ($n <= 0 || $n > self::MAX_OBJECTS || $first <= 0) continue;
			$nums = preg_split('/\s+/', trim(substr($data, 0, $first)));
			$pairs = array();
			for ($k = 0; $k + 1 < count($nums) && count($pairs) < $n; $k += 2) $pairs[] = array((int) $nums[$k], (int) $nums[$k + 1]);
			foreach ($pairs as $k => $p) {
				$s = $first + $p[1];
				$e = isset($pairs[$k + 1]) ? $first + $pairs[$k + 1][1] : strlen($data);
				if (!isset($this->objects[$p[0]])) $this->objects[$p[0]] = substr($data, $s, max(0, $e - $s));
				if (count($this->objects) > self::MAX_OBJECTS) throw new DoajPaPdfReaderLimit('too many objects');
			}
		}
	}

	/** @return string The dictionary part (before "stream") of an object body */
	private function dictOf($body) {
		$p = strpos($body, 'stream');
		return $p === false ? $body : substr($body, 0, $p);
	}

	/** @return string|null Resolved object body */
	private function obj($ref) {
		if (is_string($ref) && preg_match('/^\s*(\d+)\s+\d+\s+R\s*$/', $ref, $m)) $ref = (int) $m[1];
		return is_int($ref) && isset($this->objects[$ref]) ? $this->objects[$ref] : null;
	}

	/** @return string|null Decoded stream bytes (FlateDecode or no filter) */
	private function streamData($body) {
		if (!preg_match('/stream\r?\n/', $body, $m, PREG_OFFSET_CAPTURE)) return null;
		$dict = substr($body, 0, $m[0][1]);
		$raw = substr($body, $m[0][1] + strlen($m[0][0]));
		$len = null;
		if (preg_match('/\/Length\s+(\d+)\s+(\d+)\s+R/', $dict, $x)) {
			$lo = $this->obj((int) $x[1]);
			if ($lo !== null && preg_match('/^\s*(\d+)/', $lo, $y)) $len = (int) $y[1];
		} elseif (preg_match('/\/Length\s+(\d+)/', $dict, $x)) {
			$len = (int) $x[1];
		}
		if ($len !== null && $len <= strlen($raw)) $raw = substr($raw, 0, $len);
		else { $e = strrpos($raw, 'endstream'); if ($e !== false) $raw = rtrim(substr($raw, 0, $e), "\r\n"); }
		$filter = $this->value($dict, 'Filter');
		if ($filter !== null) {
			preg_match_all('/\/(\w+)/', $filter, $f);
			if ($f[1] !== array('FlateDecode')) return null;
			$out = @gzuncompress($raw, self::MAX_STREAM);
			if ($out === false) $out = @gzinflate(substr($raw, 2), self::MAX_STREAM);
			if ($out === false) return null;
			if (preg_match('/\/Predictor\s+(\d+)/', $dict, $pr) && (int) $pr[1] >= 10) {
				$cols = preg_match('/\/Columns\s+(\d+)/', $dict, $c) ? (int) $c[1] : 1;
				$out = self::unpredict($out, $cols);
			}
			$raw = $out;
		}
		$this->inflated += strlen($raw);
		if ($this->inflated > self::MAX_TOTAL_INFLATED) throw new DoajPaPdfReaderLimit('decompressed data too large');
		return $raw;
	}

	/** PNG predictors (used by some object/xref streams). */
	private static function unpredict($data, $cols) {
		$row = $cols + 1; $out = ''; $prev = str_repeat("\0", $cols);
		for ($i = 0; $i + $row <= strlen($data); $i += $row) {
			$type = ord($data[$i]); $line = substr($data, $i + 1, $cols); $cur = '';
			for ($j = 0; $j < $cols; $j++) {
				$a = $j > 0 ? ord($cur[$j - 1]) : 0; $b = ord($prev[$j]); $c = $j > 0 ? ord($prev[$j - 1]) : 0; $x = ord($line[$j]);
				if ($type === 1) $x += $a; elseif ($type === 2) $x += $b; elseif ($type === 3) $x += (int) (($a + $b) / 2);
				elseif ($type === 4) { $p = $a + $b - $c; $pa = abs($p - $a); $pb = abs($p - $b); $pc = abs($p - $c); $x += ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c); }
				$cur .= chr($x & 0xFF);
			}
			$out .= $cur; $prev = $cur;
		}
		return $out;
	}

	/** @return string|null Value of /Key in a dictionary text: a reference "n 0 R", a name, a <<dict>> or an [array] */
	private function value($dict, $key) {
		if (!preg_match('/\/' . preg_quote($key, '/') . '(?![A-Za-z0-9])\s*/', $dict, $m, PREG_OFFSET_CAPTURE)) return null;
		$p = $m[0][1] + strlen($m[0][0]);
		$rest = substr($dict, $p);
		if (preg_match('/^(\d+\s+\d+\s+R)/', $rest, $r)) return $r[1];
		if (substr($rest, 0, 2) === '<<') return self::balanced($rest, '<<', '>>');
		if (substr($rest, 0, 1) === '[') return self::balanced($rest, '[', ']');
		if (preg_match('/^(\/[^\s\/\[\]<>()]+|[-+0-9.]+|true|false|null)/', $rest, $r)) return $r[1];
		return null;
	}

	private static function balanced($s, $open, $close) {
		$depth = 0; $n = strlen($s);
		for ($i = 0; $i < $n; $i++) {
			if (substr($s, $i, strlen($open)) === $open) { $depth++; $i += strlen($open) - 1; }
			elseif (substr($s, $i, strlen($close)) === $close) { $depth--; $i += strlen($close) - 1; if ($depth === 0) return substr($s, 0, $i + 1); }
		}
		return $s;
	}

	/** Resolve a value that may be a reference to a dictionary. */
	private function deref($v) {
		if ($v !== null && preg_match('/^\d+\s+\d+\s+R$/', $v)) { $o = $this->obj($v); return $o === null ? null : $this->dictOf($o); }
		return $v;
	}

	//
	// Pages
	//

	/** @return array list of [pageDict, resourcesDict] in document order */
	private function pageList() {
		$root = null;
		if (preg_match_all('/\/Root\s+(\d+)\s+\d+\s+R/', $this->pdf, $m)) $root = $this->obj((int) end($m[1]));
		if ($root === null) foreach ($this->objects as $o) if (preg_match('/\/Type\s*\/Catalog\b/', $this->dictOf($o))) { $root = $o; break; }
		$pages = array();
		if ($root !== null) {
			$tree = $this->value($this->dictOf($root), 'Pages');
			if ($tree !== null) $this->walk($tree, null, $pages, 0);
		}
		if (!$pages) { // fallback: every page object in file order
			foreach ($this->objects as $o) {
				$d = $this->dictOf($o);
				if (preg_match('/\/Type\s*\/Page(?![s\w])/', $d)) $pages[] = array($d, $this->deref($this->value($d, 'Resources')));
				if (count($pages) >= self::MAX_PAGES) break;
			}
		}
		return $pages;
	}

	private function walk($ref, $inheritedRes, &$pages, $depth) {
		if ($depth > 30 || count($pages) >= self::MAX_PAGES) return;
		$o = $this->obj($ref);
		if ($o === null) return;
		$d = $this->dictOf($o);
		$res = $this->value($d, 'Resources');
		$res = $res !== null ? $this->deref($res) : $inheritedRes;
		if (preg_match('/\/Type\s*\/Pages\b/', $d)) {
			$kids = $this->value($d, 'Kids');
			if ($kids !== null && preg_match_all('/(\d+)\s+\d+\s+R/', $kids, $k)) foreach ($k[1] as $kid) $this->walk((int) $kid, $res, $pages, $depth + 1);
		} else {
			$pages[] = array($d, $res);
		}
	}

	private function pageText($page) {
		list($dict, $res) = $page;
		$contents = $this->value($dict, 'Contents');
		if ($contents === null) return '';
		$data = '';
		if (preg_match_all('/(\d+)\s+\d+\s+R/', $contents, $m)) {
			foreach ($m[1] as $ref) {
				$o = $this->obj((int) $ref);
				if ($o === null) continue;
				if (preg_match('/^\s*\[/', $this->dictOf($o))) { // a reference to an array of streams
					if (preg_match_all('/(\d+)\s+\d+\s+R/', $this->dictOf($o), $mm)) foreach ($mm[1] as $r2) { $o2 = $this->obj((int) $r2); if ($o2 !== null) $data .= "\n" . (string) $this->streamData($o2); }
					continue;
				}
				$data .= "\n" . (string) $this->streamData($o);
			}
		}
		return $this->contentText($data, $res, 0);
	}

	//
	// Content streams
	//

	private function contentText($data, $res, $depth) {
		$fonts = $res !== null ? $this->deref($this->value($res, 'Font')) : null;
		$xobjects = $res !== null ? $this->deref($this->value($res, 'XObject')) : null;
		$out = ''; $decoder = null; $operands = array(); $lastY = null;
		$n = strlen($data); $i = 0;
		while ($i < $n) {
			$c = $data[$i];
			if (ctype_space($c)) { $i++; continue; }
			if ($c === '%') { $e = strpos($data, "\n", $i); $i = $e === false ? $n : $e + 1; continue; }
			if ($c === '(') { list($s, $i) = self::literal($data, $i); $operands[] = array('s', $s); continue; }
			if ($c === '<' && ($data[$i + 1] ?? '') !== '<') {
				$e = strpos($data, '>', $i); if ($e === false) break;
				$hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($data, $i + 1, $e - $i - 1));
				if (strlen($hex) % 2) $hex .= '0';
				$operands[] = array('s', (string) @hex2bin($hex)); $i = $e + 1; continue;
			}
			if ($c === '[') { $operands[] = array('['); $i++; continue; }
			if ($c === ']') {
				$arr = array();
				while ($operands) { $x = array_pop($operands); if ($x[0] === '[') break; array_unshift($arr, $x); }
				$operands[] = array('a', $arr); $i++; continue;
			}
			if ($c === '<' || $c === '>') { $i += 2; continue; } // inline dictionaries (marked content)
			if ($c === '/') { preg_match('/\/[^\s\/\[\]<>()%{}]*/', $data, $m, 0, $i); $operands[] = array('n', substr($m[0], 1)); $i += max(1, strlen($m[0])); continue; }
			if (preg_match('/[-+0-9.]+/A', $data, $m, 0, $i)) { $operands[] = array('d', (float) $m[0]); $i += strlen($m[0]); continue; }
			if (!preg_match('/[A-Za-z\'"*]+/A', $data, $m, 0, $i)) { $i++; continue; }
			$op = $m[0]; $i += strlen($op);
			switch ($op) {
				case 'BI': // inline image: skip to EI
					$e = strpos($data, 'EI', $i); $i = $e === false ? $n : $e + 2; break;
				case 'Tf':
					foreach ($operands as $x) if ($x[0] === 'n') $decoder = $this->fontDecoder($fonts, $x[1]);
					break;
				case 'Tj': case "'": case '"':
					if ($op !== 'Tj') $out .= "\n";
					$s = end($operands); if ($s && $s[0] === 's') $out .= $this->decode($s[1], $decoder);
					break;
				case 'TJ':
					$a = end($operands);
					if ($a && $a[0] === 'a') foreach ($a[1] as $x) {
						if ($x[0] === 's') $out .= $this->decode($x[1], $decoder);
						elseif ($x[0] === 'd' && $x[1] < -180 && substr($out, -1) !== ' ') $out .= ' ';
					}
					break;
				case 'Td': case 'TD':
					if (count($operands) >= 2 && abs($operands[count($operands) - 1][1]) > 0.01) $out .= "\n";
					elseif (substr($out, -1) !== ' ' && substr($out, -1) !== "\n") $out .= ' ';
					break;
				case 'Tm':
					if (count($operands) >= 6) { $y = $operands[count($operands) - 1][1]; if ($lastY !== null && abs($y - $lastY) > 0.5) $out .= "\n"; elseif (substr($out, -1) !== ' ') $out .= ' '; $lastY = $y; }
					break;
				case 'T*': case 'ET':
					$out .= "\n"; break;
				case 'Do':
					$name = end($operands);
					if ($depth < 1 && $xobjects !== null && $name && $name[0] === 'n') {
						$xo = $this->obj($this->value($xobjects, $name[1]));
						if ($xo !== null && preg_match('/\/Subtype\s*\/Form\b/', $this->dictOf($xo))) {
							$xres = $this->value($this->dictOf($xo), 'Resources');
							$out .= "\n" . $this->contentText((string) $this->streamData($xo), $xres !== null ? $this->deref($xres) : $res, $depth + 1);
						}
					}
					break;
			}
			$operands = array();
		}
		$out = preg_replace('/[ \t]+/', ' ', $out);
		return trim(preg_replace("/\n\s*\n+/", "\n", $out));
	}

	/** @return array [string, next offset] for a literal string starting at "(" */
	private static function literal($data, $i) {
		$n = strlen($data); $depth = 0; $s = '';
		for ($i++; $i < $n; $i++) {
			$c = $data[$i];
			if ($c === '\\') {
				$d = $data[$i + 1] ?? '';
				$map = array('n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f", '(' => '(', ')' => ')', '\\' => '\\');
				if (isset($map[$d])) { $s .= $map[$d]; $i++; }
				elseif (preg_match('/[0-7]{1,3}/A', $data, $m, 0, $i + 1)) { $s .= chr(octdec($m[0]) & 0xFF); $i += strlen($m[0]); }
				elseif ($d === "\r" || $d === "\n") { $i++; if ($d === "\r" && ($data[$i + 1] ?? '') === "\n") $i++; }
				else { $s .= $d; $i++; }
				continue;
			}
			if ($c === '(') $depth++;
			if ($c === ')') { if ($depth === 0) return array($s, $i + 1); $depth--; }
			$s .= $c;
		}
		return array($s, $n);
	}

	//
	// Fonts
	//

	/** @return array|null ['map' => code => utf8, 'width' => 1|2] or null for WinAnsi decoding */
	private function fontDecoder($fonts, $name) {
		if ($fonts === null) return null;
		$ref = $this->value($fonts, $name);
		if ($ref === null) return null;
		if (isset($this->fontCache[$ref])) return $this->fontCache[$ref];
		$d = $this->deref($ref);
		$decoder = null;
		if ($d !== null) {
			$tu = $this->value($d, 'ToUnicode');
			if ($tu !== null && ($o = $this->obj($tu)) !== null && ($cmap = $this->streamData($o)) !== null) $decoder = self::parseCMap($cmap);
			if ($decoder === null && preg_match('/\/Subtype\s*\/Type0\b/', $d)) $decoder = array('map' => array(), 'width' => 2, 'unknown' => true);
			if ($decoder === null) {
				$enc = $this->deref($this->value($d, 'Encoding'));
				$diff = $enc !== null ? $this->value($enc, 'Differences') : null;
				if ($diff !== null) $decoder = self::differences($diff);
			}
		}
		return $this->fontCache[$ref] = $decoder;
	}

	private static function parseCMap($cmap) {
		$map = array(); $width = 1;
		if (preg_match('/begincodespacerange\s*<([0-9A-Fa-f]+)>/', $cmap, $m)) $width = strlen($m[1]) >= 4 ? 2 : 1;
		if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $blocks)) {
			foreach ($blocks[1] as $b) if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]*)>/', $b, $p, PREG_SET_ORDER)) {
				foreach ($p as $x) $map[hexdec($x[1])] = self::utf16hex($x[2]);
			}
		}
		if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $blocks)) {
			foreach ($blocks[1] as $b) {
				if (!preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<[0-9A-Fa-f]*>|\[[^\]]*\])/', $b, $p, PREG_SET_ORDER)) continue;
				foreach ($p as $x) {
					$lo = hexdec($x[1]); $hi = min(hexdec($x[2]), $lo + 65535);
					if ($x[3][0] === '[') {
						preg_match_all('/<([0-9A-Fa-f]*)>/', $x[3], $arr);
						foreach ($arr[1] as $k => $h) if ($lo + $k <= $hi) $map[$lo + $k] = self::utf16hex($h);
					} else {
						$start = substr($x[3], 1, -1);
						$base = hexdec(substr($start, -4)); $prefix = substr($start, 0, -4);
						for ($c = $lo; $c <= $hi; $c++) $map[$c] = self::utf16hex($prefix . str_pad(dechex($base + $c - $lo), 4, '0', STR_PAD_LEFT));
					}
				}
			}
		}
		return $map ? array('map' => $map, 'width' => $width) : null;
	}

	private static function utf16hex($hex) {
		if ($hex === '') return '';
		$bin = @hex2bin(strlen($hex) % 4 ? str_pad($hex, (int) ceil(strlen($hex) / 4) * 4, '0', STR_PAD_LEFT) : $hex);
		$s = $bin === false ? '' : @mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
		return is_string($s) ? $s : '';
	}

	/** Simple font with /Differences: map codes to glyph names we know (ligatures, quotes, standard letters). */
	private static function differences($diff) {
		$map = array(); $code = 0;
		if (!preg_match_all('/(\d+)|\/([^\s\/\]]+)/', $diff, $m, PREG_SET_ORDER)) return null;
		$glyphs = array('fi' => 'fi', 'fl' => 'fl', 'ff' => 'ff', 'ffi' => 'ffi', 'ffl' => 'ffl', 'quoteright' => "\u{2019}", 'quoteleft' => "\u{2018}",
			'quotedblleft' => "\u{201C}", 'quotedblright' => "\u{201D}", 'endash' => "\u{2013}", 'emdash' => "\u{2014}", 'bullet' => "\u{2022}", 'space' => ' ',
			'hyphen' => '-', 'period' => '.', 'comma' => ',', 'colon' => ':', 'semicolon' => ';', 'parenleft' => '(', 'parenright' => ')', 'slash' => '/');
		foreach ($m as $x) {
			if ($x[1] !== '') { $code = (int) $x[1]; continue; }
			$g = $x[2];
			if (isset($glyphs[$g])) $map[$code] = $glyphs[$g];
			elseif (preg_match('/^[A-Za-z]$/', $g)) $map[$code] = $g;
			elseif (preg_match('/^uni([0-9A-Fa-f]{4})$/', $g, $u)) $map[$code] = self::utf16hex($u[1]);
			$code++;
		}
		return array('map' => $map, 'width' => 1, 'fallback' => true);
	}

	private function decode($bytes, $decoder) {
		if ($decoder === null) return self::winAnsi($bytes);
		if (!empty($decoder['unknown'])) return '';
		$out = ''; $w = $decoder['width']; $n = strlen($bytes);
		for ($i = 0; $i + $w <= $n; $i += $w) {
			$code = $w === 2 ? (ord($bytes[$i]) << 8) | ord($bytes[$i + 1]) : ord($bytes[$i]);
			if (isset($decoder['map'][$code])) $out .= $decoder['map'][$code];
			elseif (!empty($decoder['fallback'])) $out .= self::winAnsi($bytes[$i]);
		}
		return $out;
	}

	private static function winAnsi($bytes) {
		$s = @mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
		return is_string($s) ? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) : '';
	}
}

/** A hard limit of the reader was reached. */
class DoajPaPdfReaderLimit extends \Exception {
}
