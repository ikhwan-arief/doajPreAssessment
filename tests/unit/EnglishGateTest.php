<?php
require_once __DIR__ . '/../../classes/gate/DoajPaEnglishGate.inc.php';

/** In-memory stand-in for Plugin::getSetting()/updateSetting(). */
class DoajPaFakeSettingsPlugin {
	public $settings = array();
	public function getSetting($contextId, $name) { return isset($this->settings[$contextId][$name]) ? $this->settings[$contextId][$name] : null; }
	public function updateSetting($contextId, $name, $value, $type = null) { $this->settings[$contextId][$name] = $value; }
}

doajpa_test('EnglishGate: warning text is the exact Stage 01 F1 wording', function () {
	$expected = 'ENGLISH-ONLY JOURNAL CONTENT. This plugin evaluates only publicly accessible English-language journal websites. It uses deterministic rules, not Artificial Intelligence or automatic translation. Do not rely on its findings for policies written in other languages. If essential non-English content is encountered, the affected check will be marked Unsupported Language / Needs Verification. This limitation applies to the plugin, not to DOAJ eligibility.';
	doajpa_same($expected, DoajPaEnglishGate::WARNING_TEXT, 'warning text');
	doajpa_same("I understand and confirm that this journal's public assessment content is in English.", DoajPaEnglishGate::CHECKBOX_TEXT, 'checkbox text');
});
doajpa_test('EnglishGate: not acknowledged by default', function () {
	$gate = new DoajPaEnglishGate(new DoajPaFakeSettingsPlugin());
	doajpa_same(false, $gate->isAcknowledged(1), 'fresh gate');
});
doajpa_test('EnglishGate: acknowledgement is per journal and records user, UTC time and hash', function () {
	$plugin = new DoajPaFakeSettingsPlugin();
	$gate = new DoajPaEnglishGate($plugin);
	$record = $gate->acknowledge(1, 1, 'admin');
	doajpa_same(true, $gate->isAcknowledged(1), 'journal 1');
	doajpa_same(false, $gate->isAcknowledged(2), 'journal 2 unaffected');
	doajpa_same(1, $record['userId'], 'user id');
	doajpa_same(1, preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $record['acknowledgedAtUtc']), 'UTC timestamp format');
	doajpa_same(DoajPaEnglishGate::warningSha256(), $record['warningSha256'], 'hash stored');
});
doajpa_test('EnglishGate: re-arms when the stored hash does not match the current text', function () {
	$plugin = new DoajPaFakeSettingsPlugin();
	$gate = new DoajPaEnglishGate($plugin);
	$gate->acknowledge(1, 1, 'admin');
	$data = json_decode($plugin->settings[1][DoajPaEnglishGate::SETTING_NAME], true);
	$data['warningSha256'] = hash('sha256', 'an older warning text');
	$plugin->settings[1][DoajPaEnglishGate::SETTING_NAME] = json_encode($data);
	doajpa_same(false, $gate->isAcknowledged(1), 'stale hash');
});
doajpa_test('EnglishGate: corrupt or non-string stored values are treated as not acknowledged', function () {
	$plugin = new DoajPaFakeSettingsPlugin();
	$gate = new DoajPaEnglishGate($plugin);
	foreach (array('not json', '', '[]', '{"warningSha256":"x"}', 42, true) as $value) {
		$plugin->settings[1][DoajPaEnglishGate::SETTING_NAME] = $value;
		doajpa_same(false, $gate->isAcknowledged(1), 'value ' . var_export($value, true));
	}
});
