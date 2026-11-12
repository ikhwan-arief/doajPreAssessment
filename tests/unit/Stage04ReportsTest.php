<?php
/**
 * Stage 04 unit tests: Before You Apply action plan (AC-14), report model and the HTML, PDF and DOCX renderers (AC-18):
 * same content in every format (parity), A4, disclaimer, escaping, no prohibited claims, encoding, determinism.
 * Test IDs (S4-...) are referenced from docs/stage04/04-report.md.
 */
require_once __DIR__ . '/../support/DoajPaReportFixture.inc.php';

function s4_bad() {
	static $r = null;
	if ($r === null) $r = DoajPaReportFixture::run('bad');
	return $r;
}
function s4_model($run, $actions = array()) {
	return DoajPaReportModel::build(DoajPaReportFixture::input($run, $actions));
}

doajpa_test('S4-A01 Before You Apply: fails, warnings and contradictions grouped by the page to correct; recommendation and DOAJ clause verbatim; unverifiable listed separately', function () {
	$run = s4_bad();
	$plan = DoajPaActionPlan::build($run, array());
	$tasks = 0; $keys = array();
	foreach ($plan['groups'] as $g) foreach ($g['tasks'] as $t) {
		$tasks++; $keys[] = $t['criterionKey'];
		doajpa_assert(in_array($t['status'], array('fail', 'warning', 'contradiction'), true), 'only deficiencies: ' . $t['criterionKey']);
		$r = null;
		foreach ($run['results'] as $x) if ($x['criterion_key'] === $t['criterionKey']) $r = $x;
		if ($t['recommendation'] !== null) {
			doajpa_assert(strpos($r['rationale'], 'Recommended correction: ' . $t['recommendation'] . ' (DOAJ: "' . $t['clause'] . '")') !== false, 'verbatim split ' . $t['criterionKey']);
		}
	}
	$deficient = array_filter($run['results'], function ($r) { return in_array($r['machine_status'], array('fail', 'warning', 'contradiction'), true); });
	doajpa_assert($tasks >= count($deficient), 'every deficiency is a task (findings may add page tasks)');
	foreach ($deficient as $r) doajpa_assert(in_array($r['criterion_key'], $keys, true), 'task for ' . $r['criterion_key']);
	$verify = 0;
	foreach ($plan['verify'] as $g) foreach ($g['tasks'] as $t) { $verify++; doajpa_same('unverifiable', $t['status'], 'verify list'); }
	doajpa_same(count(array_filter($run['results'], function ($r) { return $r['machine_status'] === 'unverifiable'; })), $verify, 'every unverifiable result is listed for verification');
	doajpa_same($verify, $plan['counts']['verify'], 'count');
	// The unapproved metric is a task on the page that shows it (finding target), not on the journal as a whole.
	$metric = null;
	foreach ($plan['groups'] as $g) foreach ($g['tasks'] as $t) if ($t['criterionKey'] === 'guide.no-unapproved-metrics') $metric = $g['page'];
	doajpa_same('http://fixtures:8080/s3/index.php/bad/index', $metric, 'metric task on the homepage');
});

doajpa_test('S4-A02 Before You Apply: order and accountable rows (actions of the finding, owner, due date, suggested priority); a deficiency never disappears because an action is done', function () {
	$run = s4_bad();
	$fid = null;
	foreach ($run['observations'] as $o) if ($o['criterion_key'] === 'guide.oa-statement') $fid = $o['finding_id'];
	$actions = array(array('action_id' => 4, 'finding_id' => $fid, 'status' => 'done_pending_verification', 'priority' => 'high', 'owner_text' => 'Managing editor',
		'due_date' => '2026-11-30', 'recommendation' => 'x', 'target_url' => null, 'completion_evidence' => 'Policy page updated'));
	$plan = DoajPaActionPlan::build($run, $actions);
	$first = $plan['groups'][0]['tasks'][0];
	doajpa_assert(in_array($first['status'], array('contradiction', 'fail'), true), 'most severe first');
	$found = null;
	foreach ($plan['groups'] as $g) foreach ($g['tasks'] as $t) if ($t['criterionKey'] === 'guide.oa-statement') $found = $t;
	doajpa_same(1, count($found['actions']), 'action row linked through the finding');
	doajpa_same('Managing editor', $found['actions'][0]['owner_text'], 'owner');
	doajpa_same('fail', $found['status'], 'still a deficiency although the action is done (F6)');
	doajpa_same('high', $found['suggestedPriority'], 'Must not met: high');
	doajpa_same(1, $plan['counts']['withAction'], 'with action');
	doajpa_same(array('A text', 'Do it.', 'Clause.'), DoajPaActionPlan::split('A text Recommended correction: Do it. (DOAJ: "Clause.")'), 'split');
	doajpa_same(array('Only text.', null, null), DoajPaActionPlan::split('Only text.'), 'no recommendation');
});

doajpa_test('S4-M01 Report model: run record, normative baseline, required and recommended counted separately, candidate and non-official disclaimers, no prohibited claims', function () {
	$run = s4_bad();
	$m = s4_model($run);
	$all = implode("\n", DoajPaReportModel::texts($m));
	foreach (array(DoajPaReportModel::NOT_OFFICIAL, DoajPaAssessmentOrchestrator::CANDIDATE_DISCLAIMER, 'Candidate criteria - PENDING APPROVAL', 'Proposed criteria (not yet approved)',
		DoajPaReportModel::ENGLISH_ONLY, DoajPaReportModel::ATTRIBUTION, 'https://doaj.org/apply/guide/', 'their text was not used as evidence') as $t) {
		doajpa_assert(strpos($all, $t) !== false || stripos($all, $t) !== false, 'contains: ' . substr($t, 0, 60));
	}
	$headings = array_map(function ($s) { return $s['heading']; }, $m['sections']);
	doajpa_same(array('About this report', 'Assessment run', 'Method and coverage', 'Results summary', 'Criterion results', 'Findings and history',
		'Before You Apply: corrections by page', 'Corrective action register', 'DOAJ application form guide', 'Limitations', 'References', 'Disclaimer'), $headings, 'sections');
	// 1.2.0.0: no technical codes in the report (criterion keys, question keys, decision references, finding/action ids)
	foreach (array('/\b(?:guide|transparency|copyright)\.[a-z-]+\b/', '/\bFQ-\d\d\b/', '/\bADR-\d{3}\b/', '/\bD-03-\d\b/', '/\b[FA]-\d+\b/', '/language_undetermined/') as $rx) {
		doajpa_assert(!preg_match($rx, $all, $mm), 'no technical code ' . $rx . ': ' . ($mm ? $mm[0] : ''));
	}
	$summary = null;
	foreach ($m['sections'] as $s) if ($s['heading'] === 'Results summary') foreach ($s['blocks'] as $b) if ($b['type'] === 'table') $summary = $b;
	doajpa_same(array('Status', 'Must', 'Recommended', 'Conditional', 'Human judgement', 'Total'), $summary['head'], 'levels counted separately');
	$total = 0;
	foreach ($summary['rows'] as $r) $total += (int) end($r);
	doajpa_same(137, $total, 'all criteria counted');
	foreach ($run['results'] as $r) doajpa_assert(strpos($all, DoajPaHumanText::text($r['rationale'])) !== false, 'stored rationale shown (in plain language): ' . $r['criterion_key']);
	// The plugin's own wording (everything that is not a stored rationale, a part of one, or a stored form answer) makes no
	// accept/reject decision, probability, readiness score or guarantee (PROD-09).
	$stored = array_merge(array_column($run['results'], 'rationale'), array_column($run['results'], 'title'), array_column($run['formAnswers'], 'answer_text'), array_column($run['formAnswers'], 'uncertainty'));
	$stored = array_map(function ($x) { return $x === null ? null : DoajPaHumanText::text((string) $x); }, $stored);
	$own = array_filter(DoajPaReportModel::texts($m), function ($t) use ($stored) {
		foreach ($stored as $s) if ($s !== null && $t !== '' && (strpos((string) $s, $t) !== false || strpos($t, (string) $s) !== false && $s !== '')) return false;
		return true;
	});
	foreach (array('/\b(accepted|rejected) (?:by|into) DOAJ\b/i', '/\bprobability\b/i', '/\breadiness\b/i', '/\bscore\b/i', '/\bguaranteed? (?:acceptance|inclusion)\b/i') as $rx) {
		doajpa_same(0, preg_match($rx, implode("\n", $own)), 'no prohibited claim in the plugin\'s own wording: ' . $rx);
	}
});

doajpa_test('S4-M02 Report model without approved or candidate criteria: coverage only, never presented as an assessment result', function () {
	$run = s4_bad();
	$run['results'] = array(); $run['observations'] = array(); $run['formAnswers'] = array();
	$run['rulebook_version'] = DoajPaAssessmentOrchestrator::LIVE_RULEBOOK;
	$run['coverageData']['assessment'] = array('report' => array('candidate' => false, 'disclaimer' => DoajPaAssessmentOrchestrator::DISCLAIMER));
	$in = DoajPaReportFixture::input($run);
	$in['formGuide'] = array();
	for ($i = 1; $i <= 20; $i++) $in['formGuide'][] = array('question_key' => sprintf('FQ-%02d', $i), 'title' => null, 'answer_status' => 'not_yet_assessed', 'answer_text' => null, 'uncertainty' => null);
	$all = implode("\n", DoajPaReportModel::texts(DoajPaReportModel::build($in)));
	doajpa_assert(strpos($all, DoajPaAssessmentOrchestrator::DISCLAIMER) !== false, 'coverage-only disclaimer');
	doajpa_assert(strpos($all, 'No criterion was evaluated in this run') !== false, 'nothing evaluated');
	doajpa_assert(strpos($all, 'PENDING APPROVAL') === false, 'no candidate wording');
	doajpa_same(20, substr_count($all, 'Not yet assessed'), 'FQ not yet assessed');
});

doajpa_test('S4-M03 Report model of an approved run (activation): independence and not-a-DOAJ-decision disclaimers, approval baseline, DOAJ attribution, no candidate wording', function () {
	$run = s4_bad();
	$run['rulebook_version'] = 'doajpa-criteria-9.9.9';
	$run['rulebookApproved'] = true;
	$run['coverageData']['assessment']['candidateDataset'] = null;
	$run['coverageData']['assessment']['approvedDataset'] = array('version' => '9.9.9', 'status' => 'approved', 'engine' => 'doajpa-checks/2',
		'approval' => 'approved by A. Owner on 2026-11-01 (product owner of this plugin, not DOAJ)');
	$run['coverageData']['assessment']['report'] = array('candidate' => false, 'disclaimer' => DoajPaAssessmentOrchestrator::APPROVED_DISCLAIMER);
	foreach ($run['formAnswers'] as &$a) $a['uncertainty'] = str_replace('PENDING APPROVAL', 'approved', (string) $a['uncertainty']);
	unset($a);
	$all = implode("\n", DoajPaReportModel::texts(s4_model($run)));
	foreach (array(DoajPaReportModel::NOT_OFFICIAL, DoajPaAssessmentOrchestrator::APPROVED_DISCLAIMER, 'It is independent of DOAJ', 'approved by the product owner of this plugin, not by DOAJ',
		'Approved criteria, version 9.9.9', DoajPaReportModel::ATTRIBUTION, DoajPaReportModel::ENGLISH_ONLY) as $t) {
		doajpa_assert(strpos($all, $t) !== false, 'contains: ' . substr($t, 0, 60));
	}
	doajpa_assert(strpos($all, 'PENDING APPROVAL') === false && strpos($all, 'Candidate criteria') === false, 'no candidate wording');
});

doajpa_test('S4-R01 HTML report: self-contained (no script, no external resource, restrictive CSP), A4 print layout, hostile text escaped, every model text present', function () {
	$m = s4_model(s4_bad());
	$h = DoajPaReportHtml::render($m);
	doajpa_assert(stripos($h, '<script') === false, 'no script element');
	doajpa_assert(strpos($h, 'alert("s3-xss")') === false && strpos($h, '&lt;script&gt;alert(&quot;s3-xss&quot;)') !== false, 'quoted hostile text escaped');
	doajpa_assert(strpos($h, "default-src 'none'") !== false, 'CSP');
	doajpa_assert(strpos($h, '@page{size:A4') !== false, 'A4');
	doajpa_assert(!preg_match('#(src|href)=["\']?https?://#i', $h), 'no external resource');
	$text = DoajPaReportFixture::htmlText($h);
	foreach (DoajPaReportModel::texts($m) as $t) doajpa_assert(DoajPaReportFixture::containsInOrder($text, $t), 'HTML contains: ' . mb_substr($t, 0, 80));
});

doajpa_test('S4-R02 DOCX report: valid stored ZIP with CRC-32, well-formed WordprocessingML, A4 page, every model text present', function () {
	$m = s4_model(s4_bad());
	$d = DoajPaReportDocx::render($m);
	$files = DoajPaReportFixture::unzip($d);
	doajpa_same(array('[Content_Types].xml', '_rels/.rels', 'docProps/core.xml', 'word/_rels/document.xml.rels', 'word/styles.xml', 'word/document.xml'), array_keys($files), 'parts');
	foreach ($files as $name => $xml) { $x = new DOMDocument(); doajpa_assert($x->loadXML($xml) === true, 'well-formed ' . $name); }
	doajpa_assert(strpos($files['word/document.xml'], '<w:pgSz w:w="11906" w:h="16838"/>') !== false, 'A4');
	doajpa_assert(strpos($files['word/document.xml'], 'alert(&quot;s3-xss&quot;)') !== false || strpos($files['word/document.xml'], 'alert("s3-xss")') !== false, 'hostile text kept as text');
	$text = DoajPaReportFixture::docxText($d);
	foreach (DoajPaReportModel::texts($m) as $t) doajpa_assert(DoajPaReportFixture::containsInOrder($text, $t), 'DOCX contains: ' . mb_substr($t, 0, 80));
});

doajpa_test('S4-R03 PDF report: PDF 1.4 structure (objects, xref offsets, trailer), A4 pages, standard fonts, page numbers, every model text present', function () {
	$m = s4_model(s4_bad());
	$p = DoajPaReportPdf::render($m, '2026-10-02 09:00:00');
	doajpa_same('%PDF-1.4', substr($p, 0, 8), 'header');
	doajpa_assert(substr($p, -6) === "%%EOF\n", 'EOF');
	preg_match('/startxref\n(\d+)\n/', $p, $sx);
	doajpa_same('xref', substr($p, (int) $sx[1], 4), 'startxref points to the xref table');
	preg_match('/xref\n0 (\d+)\n(.*?)trailer/s', $p, $x);
	$entries = explode("\n", trim($x[2]));
	for ($n = 1; $n < (int) $x[1]; $n++) doajpa_same($n . ' 0 obj', substr($p, (int) substr($entries[$n], 0, 10), strlen($n . ' 0 obj')), 'xref offset of object ' . $n);
	$pages = preg_match_all('#/Type /Page /Parent#', $p);
	doajpa_assert($pages >= 3, 'several pages: ' . $pages);
	doajpa_same($pages, preg_match_all('#/MediaBox \[0 0 595\.28 841\.89\]#', $p), 'every page A4');
	doajpa_assert(DoajPaReportPdf::unicodeFonts(), 'DejaVu Sans fonts available');
	doajpa_assert(strpos($p, '/Subtype /CIDFontType2 /BaseFont /DejaVuSans ') !== false && strpos($p, '/BaseFont /DejaVuSans-Bold') !== false
		&& substr_count($p, '/FontFile2 ') === 2 && substr_count($p, '/ToUnicode ') === 2 && strpos($p, '/Encoding /Identity-H') !== false, 'embedded Unicode fonts with ToUnicode maps');
	$text = DoajPaReportFixture::pdfText($p);
	doajpa_assert(strpos($text, 'page 1 of ' . $pages) !== false && strpos($text, 'page ' . $pages . ' of ' . $pages) !== false, 'page numbers');
	foreach (DoajPaReportModel::texts($m) as $t) doajpa_assert(DoajPaReportFixture::containsInOrder($text, $t), 'PDF contains: ' . mb_substr($t, 0, 80));
});

doajpa_test('S4-R04 PDF Unicode: Latin with diacritics, Greek, Cyrillic, typographic quotes and euro are written as readable text with the embedded DejaVu Sans fonts; a character without a glyph (CJK) is written as [U+XXXX] with a notice, never dropped; the standard-font fallback still works', function () {
	$run = s4_bad();
	$run['results'][0]['rationale'] = 'Name: Ştefan Ă (Bucureşti), Ελληνικά, Русский, José “q” € –, 中文.';
	$m = s4_model($run);
	$text = DoajPaReportFixture::pdfText(DoajPaReportPdf::render($m, null));
	doajpa_assert(DoajPaReportFixture::containsInOrder($text, 'Name: Ştefan Ă (Bucureşti), Ελληνικά, Русский, José “q” € –, [U+4E2D][U+6587].'), 'readable Unicode; CJK as code points');
	doajpa_assert(DoajPaReportFixture::containsInOrder($text, '2 character(s) that the embedded DejaVu Sans font cannot show are written as [U+XXXX] code points'), 'notice in the PDF');
	doajpa_assert(strpos(DoajPaReportFixture::htmlText(DoajPaReportHtml::render($m)), 'Ştefan Ă (Bucureşti), Ελληνικά, Русский, José “q” € –, 中文.') !== false, 'HTML unchanged');
	doajpa_assert(strpos(DoajPaReportFixture::docxText(DoajPaReportDocx::render($m)), 'Ştefan Ă (Bucureşti), Ελληνικά, Русский, José “q” € –, 中文.') !== false, 'DOCX unchanged');
	// Fallback when the font files are missing: Windows-1252 text unchanged, the rest as code points with a notice.
	doajpa_same("Jos\xE9 \x93q\x94 \x80 \x96", DoajPaReportPdf::encode("José “q” € –"), 'Windows-1252');
	doajpa_same('[U+0103][U+4E2D]', DoajPaReportPdf::encode("ă中"), 'code points');
	$std = DoajPaReportPdf::render($m, null, true);
	doajpa_assert(strpos($std, '/BaseFont /Helvetica /Encoding /WinAnsiEncoding') !== false, 'standard fonts');
	$st = DoajPaReportFixture::pdfText($std);
	doajpa_assert(DoajPaReportFixture::containsInOrder($st, 'Name: [U+015E]tefan [U+0102] (Bucure[U+015F]ti)'), 'fallback code points');
	doajpa_assert(DoajPaReportFixture::containsInOrder($st, 'character(s) that the PDF standard fonts cannot show are written as [U+XXXX] code points'), 'fallback notice');
});

doajpa_test('S4-R05 Parity and determinism: the three formats render the same model; the same model gives the same bytes', function () {
	$m = s4_model(s4_bad());
	$texts = DoajPaReportModel::texts($m);
	$h = DoajPaReportFixture::htmlText(DoajPaReportHtml::render($m));
	$d = DoajPaReportFixture::docxText(DoajPaReportDocx::render($m));
	$p = DoajPaReportFixture::pdfText(DoajPaReportPdf::render($m, '2026-10-02 09:00:00'));
	$missing = array();
	foreach ($texts as $t) foreach (array('html' => $h, 'docx' => $d, 'pdf' => $p) as $f => $x) if (!DoajPaReportFixture::containsInOrder($x, $t)) $missing[] = $f . ': ' . mb_substr($t, 0, 50);
	doajpa_same(array(), $missing, 'all ' . count($texts) . ' texts in all three formats');
	doajpa_same(DoajPaReportHtml::render($m), DoajPaReportHtml::render($m), 'HTML deterministic');
	doajpa_same(sha1(DoajPaReportDocx::render($m)), sha1(DoajPaReportDocx::render($m)), 'DOCX deterministic');
	doajpa_same(sha1(DoajPaReportPdf::render($m, '2026-10-02 09:00:00')), sha1(DoajPaReportPdf::render($m, '2026-10-02 09:00:00')), 'PDF deterministic');
	$g = s4_model(DoajPaReportFixture::run('good'));
	$gp = DoajPaReportFixture::pdfText(DoajPaReportPdf::render($g, null));
	foreach (DoajPaReportModel::texts($g) as $t) doajpa_assert(DoajPaReportFixture::containsInOrder($gp, $t), 'good journal PDF contains: ' . mb_substr($t, 0, 60));
});
