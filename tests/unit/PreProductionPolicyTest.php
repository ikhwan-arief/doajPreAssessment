<?php
/**
 * Pre-production mode (ADR-005 R1.1 revision 2026-11-13): config-only, journal origin only, allowed address classes only.
 */
require_once __DIR__ . '/../../classes/crawler/DoajPaAddressGuard.inc.php';
require_once __DIR__ . '/../../classes/crawler/DoajPaPreProductionPolicy.inc.php';

doajpa_test('PreProduction: disabled unless switched on; malformed or foreign connect_to disables it', function () {
	$t = array('host' => 'localhost', 'port' => 8445);
	doajpa_same(null, DoajPaPreProductionPolicy::fromSettings($t, null, null, null), 'unset');
	doajpa_same(null, DoajPaPreProductionPolicy::fromSettings($t, 'Off', null, null), 'off');
	doajpa_same(null, DoajPaPreProductionPolicy::fromSettings($t, false, null, null), 'false');
	doajpa_same(true, DoajPaPreProductionPolicy::fromSettings($t, true, null, null) !== null, 'OJS parses On as true');
	doajpa_same(true, DoajPaPreProductionPolicy::fromSettings($t, 'On', '', null) !== null, 'string On, empty map');
	doajpa_same(null, DoajPaPreProductionPolicy::fromSettings($t, true, 'localhost:8445=127.0.0.1', null), 'map without port');
	doajpa_same(null, DoajPaPreProductionPolicy::fromSettings($t, true, 'other:8445=127.0.0.1:443', null), 'map for another host');
	doajpa_same(null, DoajPaPreProductionPolicy::fromSettings($t, true, 'localhost:80=127.0.0.1:443', null), 'map for another port');
	doajpa_same(null, DoajPaPreProductionPolicy::fromSettings($t, true, 'localhost:8445=169.254.169.254:80', null), 'metadata address');
	doajpa_same(null, DoajPaPreProductionPolicy::fromSettings($t, true, 'localhost:8445=8.8.8.8:443', null), 'public address');
	doajpa_same(null, DoajPaPreProductionPolicy::fromSettings($t, true, 'localhost:8445=127.0.0.1:443,x:1=10.0.0.1:1', null), 'list');
	doajpa_same(null, DoajPaPreProductionPolicy::fromSettings($t, true, 'localhost:8445=127.0.0.1:70000', null), 'port range');
	$p = DoajPaPreProductionPolicy::fromSettings($t, true, 'LocalHost:8445 = 127.0.0.1:443', 'On');
	doajpa_same(array('origin' => 'localhost:8445', 'connectTo' => '127.0.0.1:443', 'insecureTls' => true), $p->describe(), 'parsed');
});

doajpa_test('PreProduction: guard admits only the journal origin, only allowed classes; others stay blocked', function () {
	$resolver = new class { public $map = array(); public function resolve($h) { return isset($this->map[$h]) ? $this->map[$h] : array(); } };
	$resolver->map = array('localhost' => array('127.0.0.1'), 'meta.test' => array('169.254.169.254'), 'mixed.test' => array('8.8.8.8', '10.0.0.5'),
		'stage.test' => array('10.1.2.3'), 'pub.test' => array('8.8.8.8'));
	$plain = DoajPaPreProductionPolicy::fromSettings(array('host' => 'localhost', 'port' => 8445), true, null, null);
	$g = new DoajPaAddressGuard($resolver, null, $plain);
	$r = $g->check('localhost', 'hostname', 8445);
	doajpa_same(array(true, '127.0.0.1', true, true, null, false), array($r['ok'], $r['ip'], $r['devException'], $r['preProduction'], $r['connectPort'], $r['insecureTls']), 'origin allowed');
	doajpa_same(false, $g->check('localhost', 'hostname', 8080)['ok'], 'other port blocked');
	doajpa_same(false, $g->check('stage.test', 'hostname', 8445)['ok'], 'other host blocked');
	doajpa_same(false, $g->check('127.0.0.1', 'ipv4', 8445)['ok'], 'IP literal is another host');
	$m = new DoajPaAddressGuard($resolver, null, DoajPaPreProductionPolicy::fromSettings(array('host' => 'meta.test', 'port' => 80), true, null, null));
	doajpa_same(false, $m->check('meta.test', 'hostname', 80)['ok'], 'link-local never allowed');
	$x = new DoajPaAddressGuard($resolver, null, DoajPaPreProductionPolicy::fromSettings(array('host' => 'mixed.test', 'port' => 80), true, null, null));
	doajpa_same(false, $x->check('mixed.test', 'hostname', 80)['ok'], 'mixed answers never allowed');
	$pub = new DoajPaAddressGuard($resolver, null, DoajPaPreProductionPolicy::fromSettings(array('host' => 'pub.test', 'port' => 443), true, null, null));
	$pr = $pub->check('pub.test', 'hostname', 443);
	doajpa_same(array(true, false), array($pr['ok'], $pr['preProduction']), 'public origin uses the normal path, not flagged');
	$mapped = new DoajPaAddressGuard($resolver, null, DoajPaPreProductionPolicy::fromSettings(array('host' => 'localhost', 'port' => 8445), 'On', 'localhost:8445=127.0.0.1:443', 'On'));
	$mr = $mapped->check('localhost', 'hostname', 8445);
	doajpa_same(array(true, '127.0.0.1', 443, true, 'preproduction:loopback'), array($mr['ok'], $mr['ip'], $mr['connectPort'], $mr['insecureTls'], $mr['reason']), 'connect_to mapping');
	$none = new DoajPaAddressGuard($resolver, null, null);
	doajpa_same('blocked_address:loopback', $none->check('localhost', 'hostname', 8445)['reason'], 'without the mode: blocked as before');
});
