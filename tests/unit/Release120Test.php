<?php
/**
 * Release 1.2.0.0: plain-language display text, built-in PDF reader, editorial-team list reading, citation-format
 * pages excluded from the crawl.
 */
require_once __DIR__ . '/../../classes/ui/DoajPaHumanText.inc.php';
require_once __DIR__ . '/../../classes/extract/DoajPaPdfTextReader.inc.php';
require_once __DIR__ . '/../../classes/crawler/DoajPaCrawlScope.inc.php';
require_once __DIR__ . '/../support/DoajPaRuleFixture.inc.php';

/** A minimal PDF: a page tree, one Helvetica font, one Flate content stream; optionally in an object stream. */
function r120_pdf($text, $objStm = false, $encrypt = false) {
	$content = gzcompress('BT /F1 12 Tf 72 720 Td (' . addcslashes($text, '()\\') . ") Tj 0 -14 Td [(Second) -250 (line)] TJ ET");
	$objs = array(
		1 => '<< /Type /Catalog /Pages 2 0 R >>',
		2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
		3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
		5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
	);
	$out = "%PDF-1.5\n";
	$stream = "4 0 obj\n<< /Length " . strlen($content) . " /Filter /FlateDecode >>\nstream\n" . $content . "\nendstream\nendobj\n";
	if ($objStm) {
		$head = ''; $body = '';
		foreach ($objs as $n => $o) { $head .= $n . ' ' . strlen($body) . ' '; $body .= $o . "\n"; }
		$data = gzcompress($head . $body);
		$out .= "6 0 obj\n<< /Type /ObjStm /N " . count($objs) . ' /First ' . strlen($head) . ' /Length ' . strlen($data) . " /Filter /FlateDecode >>\nstream\n" . $data . "\nendstream\nendobj\n" . $stream;
		$out .= "trailer\n<< /Root 1 0 R >>\n%%EOF";
	} else {
		foreach ($objs as $n => $o) $out .= "$n 0 obj\n$o\nendobj\n";
		$out .= $stream . "trailer\n<< /Root 1 0 R" . ($encrypt ? ' /Encrypt 9 0 R' : '') . " >>\n%%EOF";
	}
	return $out;
}

doajpa_test('R120 HumanText: criterion and question keys, reason codes and decision references become plain language', function () {
	doajpa_same('Navigation and mandatory disclosures: Open access policy', DoajPaHumanText::criterion('guide.disclosure-oa-policy'), 'criterion name');
	doajpa_same('Languages in which the journal accepts manuscripts', DoajPaHumanText::question('FQ-07'), 'question');
	doajpa_assert(strpos(DoajPaHumanText::reason('language_undetermined (advisory heuristic; not verified English)'), 'English not confirmed') === 0, 'language reason');
	doajpa_assert(strpos(DoajPaHumanText::reason('unavailable: pdf_extractor_unavailable'), 'no PDF reader') !== false, 'pdf reason');
	doajpa_same('This file type is not read (RIS citation file).', DoajPaHumanText::reason('unsupported_media_type: application/x-research-info-systems'), 'media type');
	doajpa_same('The page answered with HTTP error 404.', DoajPaHumanText::reason('http_404'), 'http');
	$t = DoajPaHumanText::text('No relevant page was read as verified English (ADR-011); see guide.oa-statement and FQ-01 (decision D-03-4).');
	doajpa_assert(!preg_match('/ADR-|D-03|guide\.|FQ-/', $t), 'codes removed: ' . $t);
	doajpa_assert(strpos($t, '"Open access and article-level access:') !== false && strpos($t, 'confirmed English') !== false, 'names inserted: ' . $t);
	$deep = DoajPaHumanText::deep(array('criterion_key' => 'guide.oa-statement', 'skip_reason' => 'robots_disallow', 'source_url' => 'https://x.org/guide.oa-statement',
		'quote' => 'see FQ-01', 'rationale' => 'per FQ-01'));
	doajpa_same('guide.oa-statement', $deep['criterion_key'], 'keys stay codes');
	doajpa_same('https://x.org/guide.oa-statement', $deep['source_url'], 'URLs unchanged');
	doajpa_same('see FQ-01', $deep['quote'], 'evidence quotes unchanged');
	doajpa_assert(strpos($deep['skip_reason'], 'robots.txt') !== false && strpos($deep['rationale'], 'FQ-') === false, 'reasons and prose translated');
});

doajpa_test('R120 PDF reader: classic objects and object streams, TJ spacing, encrypted and non-PDF input', function () {
	$r = DoajPaPdfTextReader::read(r120_pdf('Open access under CC BY 4.0 (author keeps copyright)'));
	doajpa_same('ok', $r['status'], 'classic status');
	doajpa_same("Open access under CC BY 4.0 (author keeps copyright)\nSecond line", $r['pages'][0], 'classic text');
	$r = DoajPaPdfTextReader::read(r120_pdf('Text in an object stream', true));
	doajpa_same(array('ok', "Text in an object stream\nSecond line"), array($r['status'], $r['pages'] ? $r['pages'][0] : null), 'object stream');
	doajpa_same('encrypted', DoajPaPdfTextReader::read(r120_pdf('x', false, true))['status'], 'encrypted');
	doajpa_same('corrupt', DoajPaPdfTextReader::read('<html>not a pdf</html>')['status'], 'not a PDF');
	doajpa_same('corrupt', DoajPaPdfTextReader::read("%PDF-1.4\ngarbage")['status'], 'no pages');
});

doajpa_test('R120 PDF reader: ToUnicode CMap with 2-byte codes (Type0 fonts)', function () {
	$cmap = "/CIDInit /ProcSet findresource begin begincmap 1 begincodespacerange <0000> <FFFF> endcodespacerange\n"
		. "2 beginbfchar <0001> <0043> <0002> <00A9> endbfchar 1 beginbfrange <0010> <0012> <0041> endbfrange endcmap end";
	$cs = gzcompress('BT /F1 10 Tf <000100020010001100120003> Tj ET');
	$pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
		. "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
		. "4 0 obj\n<< /Length " . strlen($cs) . " /Filter /FlateDecode >>\nstream\n$cs\nendstream\nendobj\n"
		. "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /X /ToUnicode 6 0 R >>\nendobj\n"
		. "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n$cmap\nendstream\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
	$r = DoajPaPdfTextReader::read($pdf);
	doajpa_same("C\u{00A9}ABC", $r['pages'][0], 'mapped text (unmapped code skipped)');
});

doajpa_test('R120 CrawlScope: citation-format exports are not crawled', function () {
	$s = new DoajPaCrawlScope('https://j.example.org/index.php/jpa');
	doajpa_same('excluded_backend_or_dynamic_path', $s->reason('https://j.example.org/index.php/jpa/citationstylelanguage/get/apa?submissionId=5&publicationId=5'), 'CSL excluded');
	doajpa_same(null, $s->reason('https://j.example.org/index.php/jpa/article/view/5'), 'articles still crawled');
});

doajpa_test('R120 Editorial team: profile-link labels, group headings, country lines and sidebar text do not make a list unreadable', function () {
	$people = array(array('Dr. Maya Andalas', 'Universitas Andalas', 'Indonesia', '[Scholar] [Scopus]'), array('Prof. Arthur Pendelton', 'St. Jude Continental University', 'Australia', '[Scopus] [CV]'),
		array('Prof. Elena Rostova', 'Northern Laurentian Institute of Technology', 'Canada', '[Scopus]'), array('Dr. Liam Te Kawa', 'Southern Highlands Institute of Oceanography', 'New Zealand', '[Scholar]'),
		array('Prof. Benjamin W. Sterling', 'Pacific Coastal University', 'United States', '[Scopus] [CV]'), array('Dr. Fiona MacLeod', 'Caledonian Climate Observatory', 'United Kingdom', '[Scholar]'));
	$html = '<h2>Editor-in-Chief</h2>';
	foreach ($people as $i => $p) {
		if ($i === 1) $html .= '<h2>Editorial Boards</h2>';
		$html .= '<p>' . $p[0] . ' ' . preg_replace('/\[(\w+)\]/', '<a href="#">[$1]</a>', $p[3]) . '</p><p>' . $p[1] . '</p><p>' . $p[2] . '</p>';
	}
	$html .= '<h2>Journal Information</h2><p>ISSN</p><p>0000-0019</p><p>Subject Area</p><p>Science</p><p>Language</p><p>English</p>';
	$page = '<!DOCTYPE html><html lang="en-US"><head><title>Editorial Team</title></head><body><main role="main"><h1>Editorial Team</h1>' . $html . '</main></body></html>';
	$f = DoajPaFacts::of(DoajPaRuleFixture::context(array('/about/editorialTeam' => array('html', $page)), array('site' => 'r120')));
	$b = $f->board();
	doajpa_same(6, count($b['people']), 'six people: ' . implode(' | ', array_map(function ($p) { return $p['name']; }, $b['people'])));
	doajpa_same(0, $b['unparsed'], 'no unrecognised lines inside the list');
	doajpa_same('Dr. Maya Andalas', $b['people'][0]['name'], 'link labels removed from the name');
	doajpa_same('Universitas Andalas, Indonesia', $b['people'][0]['affiliation'], 'country joined to the affiliation');
	doajpa_same('Pacific Coastal University, United States', $b['people'][4]['affiliation'], 'two-word country is not a person');
});

doajpa_test('R120 Report tables: column widths keep heading words and ordinary cell words whole', function () {
	require_once __DIR__ . '/../../classes/reports/DoajPaReportModel.inc.php';
	$sec = array(array('heading' => 'x', 'blocks' => array(array('type' => 'table', 'head' => array('Finding', 'Recommendation', 'Status in this run', 'Result'),
		'rows' => array(array('A', 'Short', 'Contradiction', 'https://localhost:8445/index.php/jaes/copyright-policy')), 'widths' => array(0.70, 0.05, 0.15, 0.10)))));
	$w = DoajPaReportModel::fitTables($sec)[0]['blocks'][0]['widths'];
	doajpa_assert(abs(array_sum($w) - 1) < 0.001, 'widths sum to 1');
	doajpa_assert($w[1] * 499 >= 14 * 5.2 + 8 - 0.5, '"Recommendation" fits: ' . json_encode($w));
	doajpa_assert($w[2] * 499 >= 13 * 4.7 + 8 - 0.5, '"Contradiction" fits');
	doajpa_assert($w[0] > $w[1], 'widths taken from the widest column');
});
