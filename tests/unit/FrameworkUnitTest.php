<?php
require_once __DIR__ . '/../../classes/framework/DoajPaCriterionRule.inc.php';
require_once __DIR__ . '/../../classes/framework/DoajPaOutcomeGuard.inc.php';
require_once __DIR__ . '/../../classes/framework/DoajPaEvaluationContext.inc.php';
require_once __DIR__ . '/../../classes/framework/DoajPaRuleRegistry.inc.php';

/** Evidence view as DoajPaOutcomeGuard receives it. */
function doajpa_fw_evidence() {
	return array(
		1 => array('quote' => 'The journal publishes peer reviewed research articles under an open access policy.', 'verifiedEnglish' => true),
		2 => array('quote' => 'About Contact Short. Privacy', 'verifiedEnglish' => false), // undetermined language
		3 => array('quote' => 'La revue publie des articles en libre accès.', 'verifiedEnglish' => false),
	);
}

doajpa_test('Framework: no live rules and no approved datasets in this version (every live run is NOT IMPLEMENTED)', function () {
	doajpa_same(array(), DoajPaRuleRegistry::liveRules(), 'live rules');
	doajpa_same(array(), DoajPaSchema::APPROVED_DATASETS, 'approved datasets');
	doajpa_same(array(), DoajPaSchema::APPROVED_RULEBOOKS, 'approved rulebooks');
});
doajpa_test('Guard: pass with a verbatim quote from verified-English evidence stays pass', function () {
	$o = DoajPaOutcomeGuard::apply(array('status' => 'pass', 'rationale' => 'ok', 'evidenceIds' => array(1),
		'quotes' => array(array('evidenceId' => 1, 'text' => 'peer reviewed research articles'))), doajpa_fw_evidence(), 'k');
	doajpa_same('pass', $o['status'], 'status');
	doajpa_same(array(), $o['downgrades'], 'no downgrades');
	doajpa_same(array(1), $o['evidenceIds'], 'evidence');
});
doajpa_test('Guard: undetermined, mixed or other-language evidence is never verified English (pass -> unverifiable)', function () {
	$o = DoajPaOutcomeGuard::apply(array('status' => 'pass', 'evidenceIds' => array(2),
		'quotes' => array(array('evidenceId' => 2, 'text' => 'About Contact Short.'))), doajpa_fw_evidence(), 'k');
	doajpa_same('unverifiable', $o['status'], 'status');
	doajpa_assert(in_array('evidence_not_verified_english', $o['downgrades'], true), 'language downgrade');
	doajpa_assert(in_array('pass_without_verified_quote', $o['downgrades'], true), 'no verified quote');
	foreach (array('fail', 'warning', 'contradiction') as $st) {
		$f = DoajPaOutcomeGuard::apply(array('status' => $st, 'evidenceIds' => array(3)), doajpa_fw_evidence(), 'k');
		doajpa_same('unverifiable', $f['status'], $st . ' on non-English evidence');
	}
});
doajpa_test('Guard: fabricated, short or foreign-run quotes downgrade to unverifiable', function () {
	$ev = doajpa_fw_evidence();
	$a = DoajPaOutcomeGuard::apply(array('status' => 'pass', 'evidenceIds' => array(1),
		'quotes' => array(array('evidenceId' => 1, 'text' => 'This journal is indexed in every database.'))), $ev, 'k');
	doajpa_same(array('unverifiable', array('quote_not_verbatim', 'pass_without_verified_quote')), array($a['status'], $a['downgrades']), 'fabricated');
	$b = DoajPaOutcomeGuard::apply(array('status' => 'pass', 'quotes' => array(array('evidenceId' => 1, 'text' => 'journal'))), $ev, 'k');
	doajpa_same('unverifiable', $b['status'], 'too short');
	$c = DoajPaOutcomeGuard::apply(array('status' => 'pass', 'evidenceIds' => array(99),
		'quotes' => array(array('evidenceId' => 99, 'text' => 'peer reviewed research articles'))), $ev, 'k');
	doajpa_same('unverifiable', $c['status'], 'evidence of another run');
	doajpa_assert(in_array('evidence_not_in_run', $c['downgrades'], true), 'reason');
	$d = DoajPaOutcomeGuard::apply(array('status' => 'pass', 'evidenceIds' => array('1'),
		'quotes' => array(array('evidenceId' => 1, 'text' => 'peer reviewed research articles'))), $ev, 'k');
	doajpa_same('unverifiable', $d['status'], 'string id is not an evidence id');
});
doajpa_test('Guard: invalid status, non-array outcome and pass without quote are unverifiable; never upgraded', function () {
	$ev = doajpa_fw_evidence();
	doajpa_same('unverifiable', DoajPaOutcomeGuard::apply(null, $ev, 'k')['status'], 'null outcome');
	doajpa_same('unverifiable', DoajPaOutcomeGuard::apply(array('status' => 'PASS'), $ev, 'k')['status'], 'unknown status');
	doajpa_same('unverifiable', DoajPaOutcomeGuard::apply(array('status' => 'pass', 'evidenceIds' => array(1)), $ev, 'k')['status'], 'no quote');
	doajpa_same('not_applicable', DoajPaOutcomeGuard::apply(array('status' => 'not_applicable'), $ev, 'k')['status'], 'n/a unchanged');
	doajpa_same('for_doaj_review', DoajPaOutcomeGuard::apply(array('status' => 'for_doaj_review', 'evidenceIds' => array(2)), $ev, 'k')['status'], 'human review kept');
	$u = DoajPaOutcomeGuard::apply(array('status' => 'unverifiable', 'rationale' => 'r'), $ev, 'k');
	doajpa_same(array('unverifiable', 'r'), array($u['status'], $u['rationale']), 'unverifiable unchanged');
});
doajpa_test('Guard: detections on unverified evidence are kept as unverifiable; malformed and duplicate ones downgrade', function () {
	$ev = doajpa_fw_evidence();
	$d = array('subtype' => 'missing', 'targetType' => 'page', 'target' => 'https://j.example/a', 'criterionStatus' => 'fail', 'evidenceId' => 2);
	$o = DoajPaOutcomeGuard::apply(array('status' => 'fail', 'evidenceIds' => array(1), 'detections' => array($d)), $ev, 'k');
	doajpa_same(1, count($o['detections']), 'deficiency kept');
	doajpa_same('unverifiable', $o['detections'][0]['criterionStatus'], 'detection status');
	doajpa_same('unverifiable', $o['status'], 'outcome status');
	$bad = DoajPaOutcomeGuard::apply(array('status' => 'fail', 'evidenceIds' => array(1),
		'detections' => array(array('subtype' => 'Bad Subtype', 'targetType' => 'page', 'target' => 'x', 'criterionStatus' => 'fail', 'evidenceId' => 1))), $ev, 'k');
	doajpa_assert(in_array('malformed_detection', $bad['downgrades'], true) && $bad['status'] === 'unverifiable' && !$bad['detections'], 'malformed');
	$d1 = array('subtype' => 'missing', 'targetType' => 'page', 'target' => 'https://j.example/a', 'criterionStatus' => 'fail', 'evidenceId' => 1);
	$dup = DoajPaOutcomeGuard::apply(array('status' => 'fail', 'detections' => array($d1, $d1)), $ev, 'k');
	doajpa_assert(in_array('duplicate_detection', $dup['downgrades'], true) && count($dup['detections']) === 1, 'duplicate');
	$ok = DoajPaOutcomeGuard::apply(array('status' => 'fail', 'evidenceIds' => array(1), 'detections' => array($d1)), $ev, 'k');
	doajpa_same(array('fail', 'fail'), array($ok['status'], $ok['detections'][0]['criterionStatus']), 'verified detection kept as is');
});
doajpa_test('Guard: verified-clear needs verified English and no downgrade; conflict with a detection is refused', function () {
	$ev = doajpa_fw_evidence();
	$c1 = array('subtype' => 'missing', 'targetType' => 'page', 'target' => 'https://j.example/a', 'evidenceId' => 1);
	$c2 = array('subtype' => 'missing', 'targetType' => 'page', 'target' => 'https://j.example/b', 'evidenceId' => 2);
	$o = DoajPaOutcomeGuard::apply(array('status' => 'pass', 'evidenceIds' => array(1), 'quotes' => array(array('evidenceId' => 1, 'text' => 'open access policy')),
		'verifiedClear' => array($c1, $c2)), $ev, 'k');
	doajpa_same(array(), $o['verifiedClear'], 'cited unverified evidence: outcome downgraded, nothing resolved');
	$p = DoajPaOutcomeGuard::apply(array('status' => 'pass', 'evidenceIds' => array(1), 'quotes' => array(array('evidenceId' => 1, 'text' => 'open access policy')),
		'verifiedClear' => array($c1)), $ev, 'k');
	doajpa_same(array('pass', 1), array($p['status'], count($p['verifiedClear'])), 'verified clear accepted');
	$d = $c1 + array('criterionStatus' => 'fail');
	$x = DoajPaOutcomeGuard::apply(array('status' => 'fail', 'detections' => array($d), 'verifiedClear' => array($c1)), $ev, 'k');
	doajpa_assert(in_array('detected_and_verified_clear', $x['downgrades'], true) && !$x['verifiedClear'] && count($x['detections']) === 1, 'conflict');
});
doajpa_test('EvaluationContext: fingerprint changes with content, gaps and language; null without inputs', function () {
	$res = array(10 => array('id' => 10, 'url' => 'https://j/a', 'urlSha256' => 'u1', 'pageType' => 'about', 'httpStatus' => 200,
		'acquisition' => 'retrieved', 'reason' => null, 'contentSha256' => 'c1'));
	$ev = array(1 => array('id' => 1, 'resourceId' => 10, 'url' => 'https://j/a', 'pageType' => 'about', 'quote' => 'q', 'quoteSha256' => 'qs',
		'language' => 'english', 'provenance' => true, 'verifiedEnglish' => true, 'acquisition' => 'retrieved', 'title' => null, 'sourceType' => 'html_page'));
	$base = new DoajPaEvaluationContext($ev, $res, array());
	$fp = $base->fingerprint(array('k', 1), array('about'));
	doajpa_same(64, strlen($fp), 'fingerprint');
	doajpa_same($fp, (new DoajPaEvaluationContext($ev, $res, array()))->fingerprint(array('k', 1), array('about')), 'deterministic');
	doajpa_assert($fp !== $base->fingerprint(array('k', 2), array('about')), 'rule version');
	$r2 = $res; $r2[10]['contentSha256'] = 'c2';
	doajpa_assert($fp !== (new DoajPaEvaluationContext($ev, $r2, array()))->fingerprint(array('k', 1), array('about')), 'content');
	$r3 = $res; $r3[10]['acquisition'] = 'inaccessible';
	doajpa_assert($fp !== (new DoajPaEvaluationContext(array(), $r3, array()))->fingerprint(array('k', 1), array('about')), 'gap');
	$e4 = $ev; $e4[1]['language'] = 'undetermined';
	doajpa_assert($fp !== (new DoajPaEvaluationContext($e4, $res, array()))->fingerprint(array('k', 1), array('about')), 'language');
	doajpa_same(null, $base->fingerprint(array('k', 1), array('editorial_team')), 'no inputs: never reused');
	doajpa_same(array(1 => array('quote' => 'q', 'verifiedEnglish' => true)), $base->guardView(), 'guard view');
});
