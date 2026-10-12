<?php

/**
 * @file classes/reports/DoajPaTrueType.inc.php
 *
 * @class DoajPaTrueType
 * @brief Minimal reader of a TrueType font file, enough to embed it unmodified in a PDF as a CID font (Identity-H):
 *  Unicode-to-glyph lookup (cmap formats 4 and 12), advance widths (hmtx) and the font descriptor values (head, hhea,
 *  OS/2, post). Pure; reads only the given file; no third-party code. Used by DoajPaReportPdf for readable Unicode text.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaTrueType {

	/** @var string font file bytes (embedded unmodified) */
	public $data;
	public $postScriptName;
	public $unitsPerEm;
	public $bbox;
	public $ascent;
	public $descent;
	public $capHeight;
	public $italicAngle = 0;
	public $numGlyphs;
	private $tables = array();
	private $advances = array();
	/** cmap: format 12 groups [start, end, glyph], or format 4 segments */
	private $groups = array();
	private $segments = null;
	private $glyphCache = array();

	/** @return DoajPaTrueType|null The font, or null when the file is missing or not a usable TrueType font */
	public static function load($path, $postScriptName) {
		static $cache = array();
		if (array_key_exists($path, $cache)) return $cache[$path];
		$font = null;
		if (is_file($path) && is_readable($path)) {
			$data = file_get_contents($path);
			try {
				if ($data !== false) $font = new DoajPaTrueType($data, $postScriptName);
			} catch (Exception $e) {
				$font = null;
			}
		}
		return $cache[$path] = $font;
	}

	public function __construct($data, $postScriptName) {
		$this->data = $data;
		$this->postScriptName = $postScriptName;
		if (strlen($data) < 12 || !in_array(substr($data, 0, 4), array("\x00\x01\x00\x00", 'true'), true)) throw new Exception('Not a TrueType font.');
		$n = $this->u16(4);
		for ($i = 0; $i < $n; $i++) {
			$o = 12 + 16 * $i;
			$this->tables[substr($data, $o, 4)] = array($this->u32($o + 8), $this->u32($o + 12));
		}
		foreach (array('head', 'hhea', 'maxp', 'hmtx', 'cmap') as $t) if (!isset($this->tables[$t])) throw new Exception('Missing table ' . $t);
		$head = $this->tables['head'][0];
		$this->unitsPerEm = $this->u16($head + 18);
		$this->bbox = array($this->s16($head + 36), $this->s16($head + 38), $this->s16($head + 40), $this->s16($head + 42));
		$hhea = $this->tables['hhea'][0];
		$this->ascent = $this->s16($hhea + 4);
		$this->descent = $this->s16($hhea + 6);
		$numHMetrics = $this->u16($hhea + 34);
		$this->numGlyphs = $this->u16($this->tables['maxp'][0] + 4);
		$this->capHeight = $this->ascent;
		if (isset($this->tables['OS/2'])) {
			$os2 = $this->tables['OS/2'][0];
			if ($this->u16($os2) >= 2 && $this->tables['OS/2'][1] >= 90) $this->capHeight = $this->s16($os2 + 88);
		}
		if (isset($this->tables['post'])) $this->italicAngle = $this->s16($this->tables['post'][0] + 4);
		$hmtx = $this->tables['hmtx'][0];
		$last = 0;
		for ($g = 0; $g < $numHMetrics; $g++) $this->advances[$g] = $last = $this->u16($hmtx + 4 * $g);
		$this->advances['last'] = $last;
		$this->readCmap();
	}

	/** @return int Glyph ID of a Unicode code point (0 = no glyph) */
	public function glyph($cp) {
		if (isset($this->glyphCache[$cp])) return $this->glyphCache[$cp];
		$gid = 0;
		if ($this->groups) {
			foreach ($this->groups as $g) {
				if ($cp >= $g[0] && $cp <= $g[1]) { $gid = $g[2] + $cp - $g[0]; break; }
			}
		} elseif ($this->segments !== null && $cp <= 0xFFFF) {
			$s = $this->segments;
			for ($i = 0; $i < $s['count']; $i++) {
				$end = $this->u16($s['end'] + 2 * $i);
				if ($cp > $end) continue;
				$start = $this->u16($s['start'] + 2 * $i);
				if ($cp < $start) break;
				$delta = $this->u16($s['delta'] + 2 * $i);
				$rangeAt = $s['range'] + 2 * $i;
				$range = $this->u16($rangeAt);
				if ($range === 0) { $gid = ($cp + $delta) & 0xFFFF; }
				else {
					$g = $this->u16($rangeAt + $range + 2 * ($cp - $start));
					$gid = $g === 0 ? 0 : ($g + $delta) & 0xFFFF;
				}
				break;
			}
		}
		if ($gid >= $this->numGlyphs) $gid = 0;
		return $this->glyphCache[$cp] = $gid;
	}

	/** @return int Advance width of a glyph in 1/1000 em */
	public function advance($gid) {
		$w = isset($this->advances[$gid]) ? $this->advances[$gid] : $this->advances['last'];
		return (int) round($w * 1000 / $this->unitsPerEm);
	}

	/** @return int A font-unit value scaled to 1/1000 em */
	public function scale($v) {
		return (int) round($v * 1000 / $this->unitsPerEm);
	}

	private function readCmap() {
		$base = $this->tables['cmap'][0];
		$n = $this->u16($base + 2);
		$best4 = null; $best12 = null;
		for ($i = 0; $i < $n; $i++) {
			$platform = $this->u16($base + 4 + 8 * $i);
			$encoding = $this->u16($base + 6 + 8 * $i);
			$offset = $base + $this->u32($base + 8 + 8 * $i);
			$format = $this->u16($offset);
			if ($format === 12 && (($platform === 3 && $encoding === 10) || $platform === 0)) $best12 = $offset;
			if ($format === 4 && (($platform === 3 && $encoding === 1) || $platform === 0)) $best4 = $offset;
		}
		if ($best12 !== null) {
			$count = $this->u32($best12 + 12);
			for ($i = 0; $i < $count; $i++) {
				$o = $best12 + 16 + 12 * $i;
				$this->groups[] = array($this->u32($o), $this->u32($o + 4), $this->u32($o + 8));
			}
			return;
		}
		if ($best4 === null) throw new Exception('No Unicode cmap.');
		$segX2 = $this->u16($best4 + 6);
		$end = $best4 + 14;
		$start = $end + $segX2 + 2;
		$delta = $start + $segX2;
		$range = $delta + $segX2;
		$this->segments = array('count' => $segX2 / 2, 'end' => $end, 'start' => $start, 'delta' => $delta, 'range' => $range);
	}

	/** @return int unsigned 16-bit big-endian value at byte offset $o */
	private function u16($o) { $v = unpack('n', substr($this->data, $o, 2)); return (int) $v[1]; }
	/** @return int signed 16-bit big-endian value at byte offset $o */
	private function s16($o) { $v = $this->u16($o); return $v >= 0x8000 ? $v - 0x10000 : $v; }
	/** @return int unsigned 32-bit big-endian value at byte offset $o */
	private function u32($o) { $v = unpack('N', substr($this->data, $o, 4)); return (int) $v[1]; }
}
