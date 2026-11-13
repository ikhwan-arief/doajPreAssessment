<?php
/**
 * Release 1.3.1.0 (AC-08): negated or merely mentioned phrases are not statements. Generic wording only; no text of
 * any particular journal.
 */
require_once __DIR__ . '/../support/DoajPaRuleFixture.inc.php';

function r131_facts($sentences) {
	$filler = 'This page describes the policies of the journal for authors, readers and reviewers. The journal publishes peer reviewed research articles in its field and makes them available to everyone.';
	$html = '<!DOCTYPE html><html lang="en-US"><head><title>Policies</title></head><body><main role="main"><h1>Policies</h1><p>' . $filler . '</p>';
	foreach ($sentences as $s) $html .= '<p>' . $s . '</p>';
	$html .= '</main></body></html>';
	return DoajPaFacts::of(DoajPaRuleFixture::context(array('/about' => array('html', $html)), array('site' => 'r131-' . md5(implode('|', $sentences)))));
}

function r131_kinds($f, $kind) {
	return count(array_filter($f->copyright(), function ($x) use ($kind) { return $x['kind'] === $kind && !$x['chrome']; }));
}

doajpa_test('R131 "All rights reserved": applied notices count; negated or quoted mentions do not', function () {
	doajpa_same(1, r131_kinds(r131_facts(array('Copyright 2025 Example University Press. All rights reserved.')), 'arr'), 'applied notice');
	foreach (array(
		'The notice "All rights reserved" is not used for published articles.',
		'Articles never carry the words All rights reserved.',
		'We do not use \'All rights reserved\' on published papers, because they are openly licensed.',
		'All rights reserved is no longer shown on article pages.',
		'Instead of All rights reserved, every article states its Creative Commons licence.',
		'The phrase \x{201C}All rights reserved\x{201D} would contradict the open licence of the articles.',
	) as $s) {
		$s = json_decode('"' . str_replace('"', '\\"', $s) . '"');
		doajpa_same(0, r131_kinds(r131_facts(array($s)), 'arr'), 'not applied: ' . $s);
	}
});

doajpa_test('R131 Fees: negated or incidental fee words are not charges; stated fees are', function () {
	$fees = function ($s) { $f = r131_facts(array($s))->fees(); return array_map(function ($x) { return $x['type'] . ($x['amount'] !== null ? ':' . $x['amount'] : ''); }, $f['fees']); };
	doajpa_same(array('apc:USD 300'), $fees('The article processing charge is USD 300 per accepted article.'), 'APC with amount');
	doajpa_same(array('withdrawal:EUR 50'), $fees('A withdrawal fee of EUR 50 applies to manuscripts withdrawn after acceptance.'), 'withdrawal fee');
	doajpa_same(array(), $fees('Payment of a publication charge is not a condition for peer review or acceptance.'), 'negated after the phrase');
	doajpa_same(array(), $fees('Study participants may withdraw at any time without penalty or charges of any kind.'), 'withdraw without penalty');
	doajpa_same(array(), $fees('The journal never asks for page charges.'), 'negated before the phrase');
});
