<?php
require_once __DIR__ . '/../../classes/history/DoajPaFindingKey.inc.php';
require_once __DIR__ . '/../../classes/history/DoajPaLifecycle.inc.php';
require_once __DIR__ . '/../../classes/history/DoajPaRunStateMachine.inc.php';

function doajpa_throws($fn) { try { $fn(); } catch (InvalidArgumentException $e) { return true; } return false; }

// --- Finding key ---------------------------------------------------------------------------------
doajpa_test('FindingKey: equivalent URLs normalize identically', function () {
	$a = DoajPaFindingKey::normalizeUrl('HTTPS://Journal.Example.org:443/index.php/jpa/about/../about/editorialTeam/?b=2&a=1#team');
	$b = DoajPaFindingKey::normalizeUrl('https://journal.example.org/index.php/jpa/about/editorialTeam?a=1&b=2');
	doajpa_same($b, $a, 'normalized URL');
	doajpa_same('https://journal.example.org/index.php/jpa/about/editorialTeam?a=1&b=2', $a, 'canonical form');
	doajpa_same('http://x.org/', DoajPaFindingKey::normalizeUrl('http://X.org:80'), 'root path and default port');
	doajpa_same('http://x.org:8080/a', DoajPaFindingKey::normalizeUrl('http://x.org:8080/a/'), 'non-default port kept');
});
doajpa_test('FindingKey: same inputs give the same key; any component change gives a new key', function () {
	$base = DoajPaFindingKey::build(1, 'oa-statement', 1, 'missing', 'page', 'https://j.org/p');
	doajpa_same($base, DoajPaFindingKey::build(1, 'oa-statement', 1, 'missing', 'page', 'https://J.org/p/'), 'stable');
	doajpa_same(64, strlen($base), 'sha256 length');
	$variants = array(
		DoajPaFindingKey::build(2, 'oa-statement', 1, 'missing', 'page', 'https://j.org/p'),
		DoajPaFindingKey::build(1, 'oa-statement-x', 1, 'missing', 'page', 'https://j.org/p'),
		DoajPaFindingKey::build(1, 'oa-statement', 2, 'missing', 'page', 'https://j.org/p'),
		DoajPaFindingKey::build(1, 'oa-statement', 1, 'contradiction', 'page', 'https://j.org/p'),
		DoajPaFindingKey::build(1, 'oa-statement', 1, 'missing', 'article', 'https://j.org/p'),
		DoajPaFindingKey::build(1, 'oa-statement', 1, 'missing', 'page', 'https://j.org/q'),
	);
	foreach ($variants as $i => $v) doajpa_assert($v !== $base, "variant $i differs");
	doajpa_same(count($variants), count(array_unique($variants)), 'all variants distinct');
});
doajpa_test('FindingKey: journal-level targets ignore the target string', function () {
	doajpa_same(DoajPaFindingKey::build(1, 'issn', 1, 'missing', 'journal', null), DoajPaFindingKey::build(1, 'issn', 1, 'missing', 'journal', 'anything'), 'journal target');
});
doajpa_test('FindingKey: invalid inputs are rejected', function () {
	foreach (array(
		function () { DoajPaFindingKey::build(0, 'k', 1, 's', 'page', 'https://j.org/'); },
		function () { DoajPaFindingKey::build('1', 'k', 1, 's', 'page', 'https://j.org/'); },
		function () { DoajPaFindingKey::build(1, 'Bad Key', 1, 's', 'page', 'https://j.org/'); },
		function () { DoajPaFindingKey::build(1, 'k', 0, 's', 'page', 'https://j.org/'); },
		function () { DoajPaFindingKey::build(1, 'k', 1, 's', 'page', 'ftp://j.org/'); },
		function () { DoajPaFindingKey::build(1, 'k', 1, 's', 'page', 'file:///etc/passwd'); },
		function () { DoajPaFindingKey::build(1, 'k', 1, 's', 'page', 'not a url'); },
		function () { DoajPaFindingKey::build(1, 'k', 1, 's', 'unknown', 'https://j.org/'); },
	) as $i => $fn) doajpa_assert(doajpa_throws($fn), "invalid input $i rejected");
});

// --- Lifecycle -------------------------------------------------------------------------------------
doajpa_test('Lifecycle: Stage 01 F6 transitions', function () {
	$L = 'DoajPaLifecycle';
	doajpa_same('open', DoajPaLifecycle::next(null, false, $L::DETECTED), 'new detection opens');
	doajpa_same('still_open', DoajPaLifecycle::next('open', false, $L::DETECTED), 'continuing');
	doajpa_same('resolved_verified', DoajPaLifecycle::next('still_open', false, $L::VERIFIED_CLEAR), 'verified correction');
	doajpa_same('needs_verification', DoajPaLifecycle::next('open', false, $L::NOT_ESTABLISHED), 'missing page is not resolved');
	doajpa_same('reopened', DoajPaLifecycle::next('resolved_verified', true, $L::DETECTED), 'reappearance reopens');
	doajpa_same('reopened', DoajPaLifecycle::next('needs_verification', true, $L::DETECTED), 'reappearance after resolved then unverifiable');
	doajpa_same('still_open', DoajPaLifecycle::next('needs_verification', false, $L::DETECTED), 'never resolved: still open');
	doajpa_same('needs_verification', DoajPaLifecycle::next('resolved_verified', true, $L::NOT_ESTABLISHED), 'resolved needs re-verification');
	doajpa_same('superseded', DoajPaLifecycle::next('open', false, $L::SUPERSEDED), 'incompatible criterion change');
});
doajpa_test('Lifecycle: impossible inputs are rejected', function () {
	$L = 'DoajPaLifecycle';
	doajpa_assert(doajpa_throws(function () use ($L) { DoajPaLifecycle::next(null, false, $L::VERIFIED_CLEAR); }), 'new finding cannot start resolved');
	doajpa_assert(doajpa_throws(function () use ($L) { DoajPaLifecycle::next(null, false, $L::NOT_ESTABLISHED); }), 'new finding cannot start unverifiable');
	doajpa_assert(doajpa_throws(function () use ($L) { DoajPaLifecycle::next('superseded', false, $L::DETECTED); }), 'superseded is terminal');
	doajpa_assert(doajpa_throws(function () { DoajPaLifecycle::next('open', false, 'manual_completion'); }), 'manual completion is not an observation');
});
doajpa_test('Lifecycle: resolved memory', function () {
	doajpa_same(true, DoajPaLifecycle::nextWasResolved(false, DoajPaLifecycle::VERIFIED_CLEAR), 'clear sets');
	doajpa_same(true, DoajPaLifecycle::nextWasResolved(true, DoajPaLifecycle::NOT_ESTABLISHED), 'unverifiable keeps');
	doajpa_same(false, DoajPaLifecycle::nextWasResolved(true, DoajPaLifecycle::DETECTED), 'detection clears');
});

// --- Run state machine ------------------------------------------------------------------------------
doajpa_test('RunStateMachine: valid and invalid transitions', function () {
	foreach (array(array('draft','queued'), array('queued','running'), array('running','human_review'), array('running','completed'),
		array('human_review','completed'), array('running','interrupted'), array('interrupted','running'), array('draft','cancelled')) as $t) {
		doajpa_same(true, DoajPaRunStateMachine::canTransition($t[0], $t[1]), $t[0] . '->' . $t[1]);
	}
	foreach (array(array('completed','running'), array('completed','cancelled'), array('cancelled','running'), array('draft','completed'),
		array('queued','completed'), array('interrupted','completed'), array('draft','running'), array('running','draft'), array('x','running')) as $t) {
		doajpa_same(false, DoajPaRunStateMachine::canTransition($t[0], $t[1]), $t[0] . '->' . $t[1] . ' refused');
	}
	doajpa_same(true, DoajPaRunStateMachine::isTerminal('completed'), 'completed terminal');
	doajpa_same(true, DoajPaRunStateMachine::isTerminal('cancelled'), 'cancelled terminal');
	doajpa_same(false, DoajPaRunStateMachine::isTerminal('interrupted'), 'interrupted resumable');
});
