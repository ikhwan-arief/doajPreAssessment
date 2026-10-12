<?php

/**
 * @file classes/reports/DoajPaReportDocx.inc.php
 *
 * @class DoajPaReportDocx
 * @brief Renders a report model (DoajPaReportModel) as a DOCX (Office Open XML WordprocessingML) document, A4 portrait.
 *  No third-party library: the package is a ZIP archive with stored (uncompressed) entries, written here with
 *  CRC-32 checksums and a fixed timestamp, so the output is deterministic for a model. Pure.
 *  Text is written unchanged; characters that XML 1.0 cannot carry (control characters) are shown as [U+XXXX].
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaReportDocx {

	/** A4 in twentieths of a point; 2 cm margins */
	const PAGE_W = 11906;
	const PAGE_H = 16838;
	const MARGIN = 1134;
	const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

	/** @return string DOCX bytes */
	public static function render($model) {
		$body = self::p($model['title'], 'Title') . self::p($model['subtitle'], 'Subtitle');
		foreach ($model['sections'] as $s) {
			$body .= self::p($s['heading'], 'Heading1');
			foreach ($s['blocks'] as $b) $body .= self::block($b);
		}
		$body .= '<w:sectPr><w:pgSz w:w="' . self::PAGE_W . '" w:h="' . self::PAGE_H . '"/><w:pgMar w:top="' . self::MARGIN . '" w:right="' . self::MARGIN
			. '" w:bottom="' . self::MARGIN . '" w:left="' . self::MARGIN . '" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>';
		$files = array(
			'[Content_Types].xml' => self::xml('<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
				. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
				. '<Default Extension="xml" ContentType="application/xml"/>'
				. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
				. '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
				. '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>'),
			'_rels/.rels' => self::xml('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
				. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
				. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>'),
			'docProps/core.xml' => self::xml('<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
				. 'xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>' . self::t($model['title']) . '</dc:title><dc:creator>DOAJ Pre-Assessment plugin</dc:creator>'
				. '<dc:language>en</dc:language></cp:coreProperties>'),
			'word/_rels/document.xml.rels' => self::xml('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
				. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'),
			'word/styles.xml' => self::xml(self::styles()),
			'word/document.xml' => self::xml('<w:document xmlns:w="' . self::W . '"><w:body>' . $body . '</w:body></w:document>'),
		);
		return self::zip($files);
	}

	private static function block($b) {
		switch ($b['type']) {
			case 'notice': return '<w:p><w:pPr><w:pStyle w:val="Notice"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">Notice: </w:t></w:r>' . self::runs($b['text']) . '</w:p>';
			case 'p': return self::p($b['text'], null);
			case 'subheading': return self::p($b['text'], 'Heading2');
			case 'list':
				$o = '';
				foreach ($b['items'] as $i) $o .= self::p("\xE2\x80\xA2 " . $i, 'ListItem');
				return $o;
			case 'kv':
				$rows = array();
				foreach ($b['rows'] as $r) $rows[] = array($r[0], $r[1]);
				return self::table(array(), $rows, array(0.32, 0.68), true);
			case 'table': return self::table($b['head'], $b['rows'], $b['widths'], false);
		}
		return '';
	}

	private static function table($head, $rows, $widths, $boldFirst) {
		$total = self::PAGE_W - 2 * self::MARGIN;
		$tw = array();
		foreach ($widths as $w) $tw[] = (int) floor($total * $w);
		$o = '<w:tbl><w:tblPr><w:tblStyle w:val="ReportTable"/><w:tblW w:w="' . array_sum($tw) . '" w:type="dxa"/><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>';
		foreach ($tw as $w) $o .= '<w:gridCol w:w="' . $w . '"/>';
		$o .= '</w:tblGrid>';
		if ($head) {
			$o .= '<w:tr><w:trPr><w:tblHeader/><w:cantSplit/></w:trPr>';
			foreach ($head as $i => $c) $o .= self::cell($c, $tw[$i], true, 'E8E8E8');
			$o .= '</w:tr>';
		}
		foreach ($rows as $r) {
			$o .= '<w:tr>';
			foreach ($r as $i => $c) $o .= self::cell($c, $tw[$i], $boldFirst && $i === 0, null);
			$o .= '</w:tr>';
		}
		return $o . '</w:tbl>' . self::p('', null);
	}

	private static function cell($text, $width, $bold, $fill) {
		return '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/>' . ($fill ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '') . '</w:tcPr>'
			. '<w:p><w:pPr><w:pStyle w:val="TableText"/></w:pPr>' . self::runs($text, $bold) . '</w:p></w:tc>';
	}

	private static function p($text, $style) {
		return '<w:p>' . ($style ? '<w:pPr><w:pStyle w:val="' . $style . '"/></w:pPr>' : '') . self::runs($text) . '</w:p>';
	}

	/** Runs of one paragraph; line breaks become w:br */
	private static function runs($text, $bold = false) {
		$o = '';
		foreach (explode("\n", (string) $text) as $i => $line) {
			$o .= '<w:r>' . ($bold ? '<w:rPr><w:b/></w:rPr>' : '') . ($i > 0 ? '<w:br/>' : '') . '<w:t xml:space="preserve">' . self::t($line) . '</w:t></w:r>';
		}
		return $o;
	}

	/** XML text: escaped; characters XML 1.0 cannot carry are written as [U+XXXX] (never dropped) */
	private static function t($text) {
		$text = preg_replace_callback('/[\x{0}-\x{8}\x{B}\x{C}\x{E}-\x{1F}\x{FFFE}\x{FFFF}]/u', function ($m) { return sprintf('[U+%04X]', self::ord($m[0])); }, (string) $text);
		return htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private static function ord($c) {
		$u = unpack('N', mb_convert_encoding($c, 'UCS-4BE', 'UTF-8'));
		return $u[1];
	}

	private static function xml($s) {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . $s;
	}

	private static function styles() {
		$p = function ($id, $name, $ppr, $rpr, $basedOn = 'Normal') {
			return '<w:style w:type="paragraph" w:styleId="' . $id . '"><w:name w:val="' . $name . '"/>' . ($basedOn ? '<w:basedOn w:val="' . $basedOn . '"/>' : '')
				. '<w:qFormat/><w:pPr>' . $ppr . '</w:pPr><w:rPr>' . $rpr . '</w:rPr></w:style>';
		};
		return '<w:styles xmlns:w="' . self::W . '"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial" w:eastAsia="Arial"/>'
			. '<w:sz w:val="20"/><w:szCs w:val="20"/><w:lang w:val="en-GB"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="80" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
			. '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
			. $p('Title', 'Title', '<w:spacing w:after="60"/>', '<w:b/><w:sz w:val="32"/>')
			. $p('Subtitle', 'Subtitle', '<w:spacing w:after="200"/>', '<w:color w:val="333333"/>')
			. $p('Heading1', 'heading 1', '<w:keepNext/><w:pBdr><w:bottom w:val="single" w:sz="4" w:space="1" w:color="999999"/></w:pBdr><w:spacing w:before="280" w:after="100"/><w:outlineLvl w:val="0"/>', '<w:b/><w:sz w:val="26"/>')
			. $p('Heading2', 'heading 2', '<w:keepNext/><w:spacing w:before="160" w:after="60"/><w:outlineLvl w:val="1"/>', '<w:b/><w:sz w:val="22"/>')
			. $p('Notice', 'Notice', '<w:pBdr><w:top w:val="single" w:sz="6" w:space="2" w:color="8A6D00"/><w:left w:val="single" w:sz="6" w:space="4" w:color="8A6D00"/>'
				. '<w:bottom w:val="single" w:sz="6" w:space="2" w:color="8A6D00"/><w:right w:val="single" w:sz="6" w:space="4" w:color="8A6D00"/></w:pBdr>'
				. '<w:shd w:val="clear" w:color="auto" w:fill="FFF8E1"/><w:spacing w:before="80" w:after="120"/>', '')
			. $p('ListItem', 'List item', '<w:ind w:left="360" w:hanging="180"/>', '')
			. $p('TableText', 'Table text', '<w:spacing w:after="0" w:line="240" w:lineRule="auto"/>', '<w:sz w:val="17"/><w:szCs w:val="17"/>')
			. '<w:style w:type="table" w:styleId="ReportTable"><w:name w:val="Report table"/><w:tblPr><w:tblBorders>'
			. '<w:top w:val="single" w:sz="4" w:space="0" w:color="888888"/><w:left w:val="single" w:sz="4" w:space="0" w:color="888888"/>'
			. '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="888888"/><w:right w:val="single" w:sz="4" w:space="0" w:color="888888"/>'
			. '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="888888"/><w:insideV w:val="single" w:sz="4" w:space="0" w:color="888888"/></w:tblBorders>'
			. '<w:tblCellMar><w:top w:w="40" w:type="dxa"/><w:left w:w="80" w:type="dxa"/><w:bottom w:w="40" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style>'
			. '</w:styles>';
	}

	/** @return string ZIP archive with stored entries, a fixed time (1980-01-01 00:00) and UTF-8 names */
	public static function zip($files) {
		$data = ''; $central = ''; $n = 0;
		foreach ($files as $name => $content) {
			$crc = crc32($content);
			$len = strlen($content);
			$head = pack('vvvvvVVVvv', 20, 0x0800, 0, 0, 0x0021, $crc, $len, $len, strlen($name), 0);
			$central .= pack('V', 0x02014b50) . pack('v', 20) . $head . pack('vvvVV', 0, 0, 0, 0, strlen($data)) . $name;
			$data .= pack('V', 0x04034b50) . $head . $name . $content;
			$n++;
		}
		return $data . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($central), strlen($data), 0);
	}
}
