<?php

/**
 * @file classes/gate/DoajPaEnglishGate.inc.php
 *
 * @class DoajPaEnglishGate
 * @brief Mandatory English-only first-use gate per journal (PROD-05, ADR-007 R1.1).
 *
 * The warning and checkbox texts are the exact Stage 01 F1 wording. An acknowledgement is bound to
 * the SHA-256 of the warning text, so the gate re-arms if the text ever changes.
 */

class DoajPaEnglishGate {

	/** Stage 01 FINAL v2.0.0, section F1, "Exact English warning for UI". */
	const WARNING_TEXT = 'ENGLISH-ONLY JOURNAL CONTENT. This plugin evaluates only publicly accessible English-language journal websites. It uses deterministic rules, not Artificial Intelligence or automatic translation. Do not rely on its findings for policies written in other languages. If essential non-English content is encountered, the affected check will be marked Unsupported Language / Needs Verification. This limitation applies to the plugin, not to DOAJ eligibility.';

	/** Stage 01 FINAL v2.0.0, section F1, checkbox text. */
	const CHECKBOX_TEXT = "I understand and confirm that this journal's public assessment content is in English.";

	const WARNING_SOURCE = 'Stage 01 FINAL v2.0.0 F1';

	const SETTING_NAME = 'englishGateAcknowledgement';

	/** @var object Plugin providing getSetting()/updateSetting() */
	private $_plugin;

	/**
	 * @param $plugin object
	 */
	public function __construct($plugin) {
		$this->_plugin = $plugin;
	}

	/**
	 * @return string
	 */
	public static function warningSha256() {
		return hash('sha256', self::WARNING_TEXT . "\n" . self::CHECKBOX_TEXT);
	}

	/**
	 * The stored acknowledgement for a journal, or null if none or unreadable.
	 * @param $contextId int
	 * @return array|null
	 */
	public function getAcknowledgement($contextId) {
		$raw = $this->_plugin->getSetting((int) $contextId, self::SETTING_NAME);
		if (!is_string($raw) || $raw === '') return null;
		$data = json_decode($raw, true);
		return is_array($data) ? $data : null;
	}

	/**
	 * @param $contextId int
	 * @return boolean
	 */
	public function isAcknowledged($contextId) {
		$ack = $this->getAcknowledgement($contextId);
		return $ack !== null
			&& isset($ack['warningSha256'], $ack['userId'], $ack['acknowledgedAtUtc'])
			&& hash_equals(self::warningSha256(), (string) $ack['warningSha256']);
	}

	/**
	 * Record the acknowledgement for a journal.
	 * @param $contextId int
	 * @param $userId int
	 * @param $username string Snapshot at acknowledgement time
	 * @return array The stored record
	 */
	public function acknowledge($contextId, $userId, $username) {
		$record = array(
			'contextId' => (int) $contextId,
			'userId' => (int) $userId,
			'username' => (string) $username,
			'acknowledgedAtUtc' => gmdate('Y-m-d\TH:i:s\Z'),
			'warningSha256' => self::warningSha256(),
			'warningSource' => self::WARNING_SOURCE,
		);
		$this->_plugin->updateSetting((int) $contextId, self::SETTING_NAME, json_encode($record), 'string');
		return $record;
	}
}
