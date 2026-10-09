<?php
require_once __DIR__ . '/../../classes/crawler/DoajPaIpPolicy.inc.php';
require_once __DIR__ . '/../../classes/crawler/DoajPaUrlTools.inc.php';
require_once __DIR__ . '/../../classes/crawler/DoajPaCrawlScope.inc.php';
require_once __DIR__ . '/../../classes/crawler/DoajPaRobots.inc.php';
require_once __DIR__ . '/../../classes/crawler/DoajPaPageClassifier.inc.php';
require_once __DIR__ . '/../../classes/crawler/DoajPaAddressGuard.inc.php';
require_once __DIR__ . '/../../classes/extract/DoajPaLanguageHeuristic.inc.php';
require_once __DIR__ . '/../../classes/extract/DoajPaHtmlExtractor.inc.php';

doajpa_test('IpPolicy: non-public IPv4 and IPv6 addresses are blocked, public ones allowed', function () {
	$blocked = array('0.0.0.0', '10.1.2.3', '100.64.0.1', '127.0.0.1', '127.255.255.254', '169.254.169.254', '172.16.0.1', '172.31.255.255',
		'192.0.0.8', '192.0.2.10', '192.168.1.1', '198.18.0.1', '198.51.100.7', '203.0.113.9', '224.0.0.1', '239.255.255.250', '240.0.0.1',
		'255.255.255.255', '::', '::1', 'fc00::1', 'fd12:3456::1', 'fe80::1', 'fec0::1', 'ff02::1', '2001:db8::1', '2001::1', '100::1',
		'::ffff:127.0.0.1', '::ffff:10.0.0.1', '::ffff:169.254.169.254', '64:ff9b::7f00:1', '64:ff9b::a00:1', '2002:7f00:1::1', '2002:a9fe:a9fe::1',
		'::127.0.0.1', '64:ff9b:1::1');
	foreach ($blocked as $ip) doajpa_assert(DoajPaIpPolicy::blockedReason($ip) !== null, "$ip blocked");
	foreach (array('8.8.8.8', '1.1.1.1', '93.184.216.34', '172.32.0.1', '100.128.0.1', '2606:4700:4700::1111', '2a00:1450:4001::1', '::ffff:8.8.8.8', '64:ff9b::808:808', '2002:808:808::1') as $ip) {
		doajpa_same(null, DoajPaIpPolicy::blockedReason($ip), "$ip public");
	}
	doajpa_same('invalid', DoajPaIpPolicy::blockedReason('fe80::1%eth0'), 'zone id');
	doajpa_same('invalid', DoajPaIpPolicy::blockedReason('999.1.1.1'), 'invalid');
});
doajpa_test('UrlTools: only http(s), no userinfo, strict hosts (numeric tricks rejected)', function () {
	$bad = array('ftp://x.org/', 'file:///etc/passwd', 'gopher://x.org/', 'javascript:alert(1)', 'http://user:pw@x.org/', 'http://user@x.org/',
		'http://2130706433/', 'http://0x7f000001/', 'http://0x7f.1/', 'http://127.1/', 'http://0177.0.0.1/', 'http://017700000001/',
		'http://127.0.0.01/', 'http://[fe80::1%25eth0]/', 'http://x.org:99999/', 'http://x.org/a b', "http://x.org/\x01", 'http://evil.com\\@good.com/',
		'http://-bad-.org/', 'http://xn--/', 'http://exa_mple.org/', 'http:///path', '//x.org/', 'http://1.2.3.4.5/');
	foreach ($bad as $u) doajpa_same(false, DoajPaUrlTools::parse($u)['ok'], "rejected: $u");
	$ok = DoajPaUrlTools::parse('HTTPS://Journal.Example.org:443/index.php/jpa?x=1');
	doajpa_same(true, $ok['ok'], 'valid');
	doajpa_same('https://journal.example.org/index.php/jpa?x=1', $ok['url'], 'canonical form');
	doajpa_same('ipv6', DoajPaUrlTools::parse('http://[::1]:8080/')['hostType'], 'ipv6 literal parsed (then blocked by the guard)');
	doajpa_same('ipv4', DoajPaUrlTools::parse('http://127.0.0.1/')['hostType'], 'canonical ipv4');
});
doajpa_test('UrlTools: RFC 3986 section 5.4 reference resolution examples', function () {
	$base = 'http://a/b/c/d;p?q';
	$cases = array('g' => 'http://a/b/c/g', './g' => 'http://a/b/c/g', 'g/' => 'http://a/b/c/g/', '/g' => 'http://a/g', '//g' => 'http://g/',
		'?y' => 'http://a/b/c/d;p?y', 'g?y' => 'http://a/b/c/g?y', '#s' => 'http://a/b/c/d;p?q', 'g#s' => 'http://a/b/c/g', ';x' => 'http://a/b/c/;x',
		'' => 'http://a/b/c/d;p?q', '.' => 'http://a/b/c/', './' => 'http://a/b/c/', '..' => 'http://a/b/', '../' => 'http://a/b/', '../g' => 'http://a/b/g',
		'../..' => 'http://a/', '../../' => 'http://a/', '../../g' => 'http://a/g', '../../../g' => 'http://a/g', '/./g' => 'http://a/g',
		'/../g' => 'http://a/g', 'g.' => 'http://a/b/c/g.', '.g' => 'http://a/b/c/.g', 'g..' => 'http://a/b/c/g..', './../g' => 'http://a/b/g',
		'./g/.' => 'http://a/b/c/g/', 'g/./h' => 'http://a/b/c/g/h', 'g/../h' => 'http://a/b/c/h');
	foreach ($cases as $ref => $expected) doajpa_same($expected, DoajPaUrlTools::resolve($base, $ref), "resolve '$ref'");
	doajpa_same('mailto:x@y.org', DoajPaUrlTools::resolve($base, 'mailto:x@y.org'), 'non-http scheme returned as-is (then refused)');
});
doajpa_test('CrawlScope: same host/port/journal path only; backend, other journals and other hosts excluded', function () {
	$s = new DoajPaCrawlScope('https://j.example.org/index.php/jpa');
	foreach (array('https://j.example.org/index.php/jpa', 'https://j.example.org/index.php/jpa/about', 'https://j.example.org/index.php/jpa/article/view/1/2') as $u) {
		doajpa_same(null, $s->reason($u), "in scope $u");
	}
	$out = array('https://j.example.org/index.php/jpb/about' => 'outside_journal_path', 'https://j.example.org/index.php/jpaxyz' => 'outside_journal_path',
		'http://j.example.org/index.php/jpa/about' => 'other_host_or_port', 'https://j.example.org:8443/index.php/jpa' => 'other_host_or_port',
		'https://other.example.org/index.php/jpa' => 'other_host_or_port', 'https://j.example.org/index.php/jpa/management/settings' => 'excluded_backend_or_dynamic_path',
		'https://j.example.org/index.php/jpa/login' => 'excluded_backend_or_dynamic_path', 'https://j.example.org/index.php/jpa/$$$call$$$/grid/x' => 'excluded_backend_or_dynamic_path',
		'https://j.example.org/index.php/jpa/doajPreAssessment' => 'excluded_backend_or_dynamic_path', 'https://j.example.org/index.php/jpa/api/v1/submissions' => 'excluded_backend_or_dynamic_path',
		'https://j.example.org/index.php/jpa/search/search?query=x' => 'excluded_backend_or_dynamic_path', 'https://j.example.org/index.php/jpa/user/profile' => 'excluded_backend_or_dynamic_path');
	foreach ($out as $u => $r) doajpa_same($r, $s->reason($u), "out of scope $u");
	doajpa_same('https://j.example.org/robots.txt', $s->robotsUrl(), 'robots URL');
});
doajpa_test('Robots: agent-specific group wins; longest match wins; Allow wins ties', function () {
	$r = new DoajPaRobots("User-agent: *\nDisallow: /private\n\nUser-agent: DOAJPreAssessment\nDisallow: /index.php/jpa/secret\nAllow: /index.php/jpa/secret/open\n");
	doajpa_same(false, $r->allows('/index.php/jpa/secret/x'), 'specific disallow');
	doajpa_same(true, $r->allows('/index.php/jpa/secret/open/a'), 'longer allow');
	doajpa_same(true, $r->allows('/private'), 'star group ignored when a specific group exists');
	$star = new DoajPaRobots("User-agent: *\nDisallow: /\n");
	doajpa_same(false, $star->allows('/index.php/jpa'), 'disallow all');
	doajpa_same(true, (new DoajPaRobots(null))->allows('/x'), 'no robots.txt');
	doajpa_same(false, DoajPaRobots::fromRules($r->rules())->allows('/index.php/jpa/secret/x'), 'rules survive serialization');
});
doajpa_test('PageClassifier: OJS 3.3 public URL patterns', function () {
	$cases = array('' => 'home', 'about' => 'about', 'about/editorialTeam' => 'editorial_team', 'about/submissions' => 'author_guidelines',
		'issue/archive' => 'archive', 'issue/view/3' => 'issue', 'issue/current' => 'issue', 'article/view/7' => 'article', 'article/view/7/9' => 'galley',
		'article/download/7/9' => 'file', 'announcement' => 'announcement', 'policies' => 'custom', 'a/b/c' => 'other');
	foreach ($cases as $path => $type) doajpa_same($type, DoajPaPageClassifier::classify($path), "classify '$path'");
});
doajpa_test('AddressGuard: mixed public/private answers are blocked; pinning IP is public; dev allowance needs exact triple', function () {
	$resolver = new class { public $map = array(); public function resolve($h) { return isset($this->map[$h]) ? $this->map[$h] : array(); } };
	$resolver->map = array('pub.test' => array('8.8.8.8'), 'mixed.test' => array('8.8.8.8', '10.0.0.5'), 'priv.test' => array('172.18.0.4'), 'none.test' => array());
	$g = new DoajPaAddressGuard($resolver, null);
	doajpa_same(array(true, '8.8.8.8'), array($g->check('pub.test', 'hostname', 80)['ok'], $g->check('pub.test', 'hostname', 80)['ip']), 'public');
	doajpa_same(false, $g->check('mixed.test', 'hostname', 80)['ok'], 'mixed answers blocked (rebinding pattern)');
	doajpa_same(false, $g->check('priv.test', 'hostname', 80)['ok'], 'private blocked');
	doajpa_same('dns_no_address', $g->check('none.test', 'hostname', 80)['reason'], 'no address');
	doajpa_same(false, $g->check('169.254.169.254', 'ipv4', 80)['ok'], 'metadata IP literal');
	$allow = new class { public function allows($h, $p, $ip) { return $h === 'priv.test' && $p === 8080 && $ip === '172.18.0.4'; } };
	$d = new DoajPaAddressGuard($resolver, $allow);
	doajpa_same(true, $d->check('priv.test', 'hostname', 8080)['devException'], 'exact triple allowed under dev allowance');
	doajpa_same(false, $d->check('priv.test', 'hostname', 80)['ok'], 'other port still blocked');
	doajpa_same(false, $d->check('mixed.test', 'hostname', 8080)['ok'], 'mixed answers never allowed');
	doajpa_same(false, $d->check('127.0.0.1', 'ipv4', 8080)['ok'], 'loopback not in allowance');
});
doajpa_test('AddressGuard: dev allowance is not loaded without the exact switch value and the unpackaged file', function () {
	putenv('DOAJ_PA_DEV_ALLOW_PRIVATE');
	doajpa_same(null, DoajPaAddressGuard::loadDevAllowance('/nonexistent'), 'switch unset');
	putenv('DOAJ_PA_DEV_ALLOW_PRIVATE=1');
	doajpa_same(null, DoajPaAddressGuard::loadDevAllowance(__DIR__ . '/../..'), 'truthy value ignored');
	putenv('DOAJ_PA_DEV_ALLOW_PRIVATE=I-UNDERSTAND-THIS-IS-A-TEST-STACK');
	doajpa_same(null, DoajPaAddressGuard::loadDevAllowance('/nonexistent'), 'file absent (packaged build)');
	putenv('DOAJ_PA_DEV_ALLOW_HOSTS=fixtures:8080=172.18.0.9,*:80=10.0.0.1');
	doajpa_same(null, DoajPaAddressGuard::loadDevAllowance(__DIR__ . '/../..'), 'one malformed entry disables everything');
	putenv('DOAJ_PA_DEV_ALLOW_HOSTS=fixtures:8080=172.18.0.9');
	$a = DoajPaAddressGuard::loadDevAllowance(__DIR__ . '/../..');
	doajpa_same(true, $a !== null && $a->allows('fixtures', 8080, '172.18.0.9') && !$a->allows('fixtures', 8080, '172.18.0.10') && !$a->allows('web', 8080, '172.18.0.9'), 'exact triple only');
	putenv('DOAJ_PA_DEV_ALLOW_PRIVATE'); putenv('DOAJ_PA_DEV_ALLOW_HOSTS');
});
doajpa_test('LanguageHeuristic: English, non-English, mixed and undetermined (advisory)', function () {
	$en = str_repeat('The journal publishes all articles under an open access policy and the authors retain the copyright of their work. ', 6);
	$id = str_repeat('Jurnal ini menerbitkan artikel yang telah ditelaah oleh mitra bestari dan dapat diakses secara terbuka untuk semua pembaca. ', 6);
	$fr = str_repeat('La revue publie des articles en libre accès et les auteurs conservent les droits sur leur travail dans cette revue. ', 6);
	doajpa_same('english', DoajPaLanguageHeuristic::assess($en)['verdict'], 'english');
	doajpa_same(array('non_english', 'id'), array(DoajPaLanguageHeuristic::assess($id)['verdict'], DoajPaLanguageHeuristic::assess($id)['detected']), 'indonesian');
	doajpa_same(array('non_english', 'fr'), array(DoajPaLanguageHeuristic::assess($fr)['verdict'], DoajPaLanguageHeuristic::assess($fr)['detected']), 'french');
	doajpa_same('mixed', DoajPaLanguageHeuristic::assess($en . $id . $id)['verdict'], 'mixed');
	doajpa_same('undetermined', DoajPaLanguageHeuristic::assess('Editorial Team')['verdict'], 'too short');
	$nav = str_repeat('Current Archives Announcements About Submissions Editorial Team Privacy Statement Contact Register Login Search ', 5);
	doajpa_same('undetermined', DoajPaLanguageHeuristic::assess($nav, 'en-US')['verdict'], 'navigation-heavy English page: weak signal, not mixed');
	doajpa_same('non_english', DoajPaLanguageHeuristic::assess('Tim Redaksi', 'id')['verdict'], 'short text with non-English lang attribute');
});
doajpa_test('HtmlExtractor: scripts never become text or links; nav/footer located; canonical, base and meta refresh recorded', function () {
	$html = '<!doctype html><html lang="en"><head><title> About  the Journal </title><base href="https://j.example.org/index.php/jpa/">'
		. '<link rel="canonical" href="about"><meta http-equiv="refresh" content="0;url=http://127.0.0.1/"><script>var u="https://evil.example/x";document.write("<a href=\'/x\'>x</a>")</script>'
		. '<style>.a{}</style></head><body><nav class="pkp_navigation_primary"><ul><li><a href="about">About</a><ul><li><a href="about/editorialTeam">Editorial Team</a></li></ul></li></ul></nav>'
		. '<main><h1>About</h1><p>First paragraph.</p><p>Second <b>bold</b> paragraph.</p><a href="javascript:alert(1)">bad</a><a href="mailto:e@x.org">mail</a>'
		. '<iframe src="https://frame.example/"></iframe><form action="/login"><input name="x"></form></main><footer class="pkp_structure_footer"><a href="../jpb/about">Other</a></footer></body></html>';
	$x = DoajPaHtmlExtractor::extract($html, 'utf-8', 'https://j.example.org/index.php/jpa/about');
	doajpa_same(true, $x['ok'], 'ok');
	doajpa_same('About the Journal', $x['title'], 'title');
	doajpa_same('en', $x['lang'], 'lang');
	doajpa_same('https://j.example.org/index.php/jpa/about', $x['canonical'], 'canonical resolved against base');
	doajpa_same('0;url=http://127.0.0.1/', $x['metaRefresh'], 'meta refresh recorded, not followed');
	doajpa_assert(strpos($x['text'], 'evil.example') === false && strpos($x['text'], 'document.write') === false, 'no script text');
	doajpa_assert(strpos($x['text'], "First paragraph.\nSecond bold paragraph.") !== false, 'paragraph boundaries kept: ' . json_encode($x['text']));
	$byText = array(); foreach ($x['links'] as $l) $byText[$l['text']] = $l;
	doajpa_same('nav', $byText['Editorial Team']['location'], 'dropdown link in nav');
	doajpa_same('https://j.example.org/index.php/jpa/about/editorialTeam', $byText['Editorial Team']['url'], 'relative link via base');
	doajpa_same('footer', $byText['Other']['location'], 'footer');
	doajpa_same('javascript:alert(1)', $byText['bad']['url'], 'non-http link kept as-is (refused later)');
	doajpa_same(false, isset($byText['x']), 'link written by script not extracted');
});
doajpa_test('HtmlExtractor: malformed, empty, legacy-charset and entity-laden documents', function () {
	doajpa_same('empty_document', DoajPaHtmlExtractor::extract('   ', null, 'https://j.example.org/')['reason'], 'empty');
	$m = DoajPaHtmlExtractor::extract('<html><body><p>Unclosed <b>tags <div>and <a href="x">links', null, 'https://j.example.org/a/');
	doajpa_same(true, $m['ok'] && strpos($m['text'], 'Unclosed tags') !== false && $m['links'][0]['url'] === 'https://j.example.org/a/x', 'malformed tolerated');
	$latin = DoajPaHtmlExtractor::extract("<html><body><p>Caf\xe9 policy</p></body></html>", 'iso-8859-1', 'https://j.example.org/');
	doajpa_same(true, strpos($latin['text'], 'Café policy') !== false, 'iso-8859-1 converted');
	$xxe = DoajPaHtmlExtractor::extract('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><html><body><p>&e;</p></body></html>', null, 'https://j.example.org/');
	doajpa_same(true, strpos($xxe['text'], 'root:') === false, 'external entity not loaded');
});
