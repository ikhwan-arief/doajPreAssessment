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

doajpa_test('Framework: live rules come only from the approved dataset named in DoajPaSchema (none when nothing is approved)', function () {
	$rules = DoajPaRuleRegistry::liveRules();
	doajpa_same(count(DoajPaSchema::APPROVED_DATASETS), count(DoajPaSchema::APPROVED_RULEBOOKS), 'one rule set per dataset');
	if (!DoajPaSchema::APPROVED_DATASETS) {
		doajpa_same(array(), $rules, 'no live rules');
		return;
	}
	doajpa_assert(count($rules) > 0, 'approved rules');
	foreach ($rules as $r) {
		doajpa_assert($r instanceof DoajPaApprovedRule && in_array($r->datasetVersion(), DoajPaSchema::APPROVED_DATASETS, true), $r->key() . ' approved dataset');
		doajpa_same(array(2, 1), array($r->version(), $r->lineage()), $r->key() . ' version 2 of lineage 1');
	}
	doajpa_same(array(DoajPaApprovedDataset::RULEBOOK), DoajPaSchema::APPROVED_RULEBOOKS, 'approved rule set');
	doajpa_same(false, DoajPaSchema::CANDIDATE_PROFILE_ENABLED, 'candidate profile off');
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
	doajpa_same(array(1 => array('quote' => 'q', 'verifiedEnglish' => true, 'neutral' => false)), $base->guardView(), 'guard view (neutral: ADR-013 eligibility, false without an English site)');
});
doajpa_test('S5-L01 Operational wording: plugin description, Settings and the Form Guide notice describe the approved release; no development-build wording is reachable in the approved state', function () {
	$po = file_get_contents(__DIR__ . '/../../locale/en_US/locale.po');
	preg_match_all('/msgid "plugins\.generic\.doajPreAssessment\.([^"]+)"\nmsgstr "((?:[^"\\\\]|\\\\.)*)"/', $po, $m);
	$s = array_combine($m[1], $m[2]);
	doajpa_assert(strpos($s['description'], 'Evidence-based DOAJ pre-assessment') === 0 && strpos($s['description'], '24 approved criteria') !== false
		&& strpos($s['description'], 'not a complete DOAJ eligibility check') !== false && strpos($s['description'], 'Independent of DOAJ') !== false, 'description');
	doajpa_assert(strpos($s['description'], 'ENGLISH-ONLY JOURNAL CONTENT') !== false && strpos($s['description'], 'not to DOAJ eligibility') !== false, 'English-only warning kept');
	doajpa_same('No configurable settings are available in this release. Access and assessment behaviour are fixed by the plugin.', $s['settings.noEditable'], 'settings');
	doajpa_assert(strpos($s['formGuide.noApprovedRun'], '{$questions}') !== false && strpos($s['formGuide.noApprovedRun'], 'not installed') === false, 'Form Guide notice');
	doajpa_assert(strpos($s['run.criteriaNotImplemented'], 'in this run') !== false, 'old runs: no claim about this version');
	// keys rendered by templates when an approved rule set is installed, plus the description and the crawler User-Agent
	$approvedState = array('description', 'settings.noEditable', 'formGuide.noApprovedRun', 'formGuide.noApprovedRunLabel', 'approvedBuild.text', 'approved.text',
		'overview.statusApproved', 'newAssessment.approvedScope', 'formGuide.approved', 'settings.approvedRuleSet', 'criteria.approvedInstalled', 'run.approvedRules');
	foreach ($approvedState as $k) doajpa_assert(!preg_match('/development build|internal.testing|not yet implemented|PENDING APPROVAL/i', $s[$k]), $k . ': no obsolete wording');
	require_once __DIR__ . '/../../classes/crawler/DoajPaHttpFetcher.inc.php';
	doajpa_assert(strpos(DoajPaHttpFetcher::USER_AGENT, 'DOAJPreAssessment/') === 0 && stripos(DoajPaHttpFetcher::USER_AGENT, 'development') === false, 'User-Agent');
	$tpl = file_get_contents(__DIR__ . '/../../templates/formGuide.tpl');
	doajpa_assert(strpos($tpl, '{elseif $doajPaApprovedRulebook}') < strpos($tpl, 'unavailable.formGuide') && strpos($tpl, 'questions=$doajPaApprovedQuestions|escape') !== false, 'Form Guide: approved state before the not-installed notice, escaped');
});
