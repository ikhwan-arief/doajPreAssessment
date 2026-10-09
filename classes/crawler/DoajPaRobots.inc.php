<?php

/**
 * @file classes/crawler/DoajPaRobots.inc.php
 *
 * @class DoajPaRobots
 * @brief Minimal robots.txt rules (groups for our user agent or "*"; Allow/Disallow path prefixes, longest
 *  match wins, Allow wins ties). Pure; no OJS dependency. Wildcards are treated literally (conservative).
 */

class DoajPaRobots {

	const AGENT = 'doajpreassessment';

	/** @var array [['allow' => bool, 'path' => string]] */
	private $_rules = array();

	/**
	 * @param $text string|null robots.txt body (null = no robots.txt: everything allowed)
	 */
	public function __construct($text) {
		if (!is_string($text)) return;
		$groups = array(); $agents = array(); $rules = array(); $inRules = false;
		foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
			$line = trim(preg_replace('/#.*$/', '', $line));
			if ($line === '' || strpos($line, ':') === false) continue;
			list($field, $value) = array_map('trim', explode(':', $line, 2));
			$field = strtolower($field);
			if ($field === 'user-agent') {
				if ($inRules) { $groups[] = array($agents, $rules); $agents = array(); $rules = array(); $inRules = false; }
				$agents[] = strtolower($value);
			} elseif ($field === 'allow' || $field === 'disallow') {
				$inRules = true;
				if ($value !== '') $rules[] = array('allow' => $field === 'allow', 'path' => $value);
			}
		}
		if ($agents) $groups[] = array($agents, $rules);
		$specific = null; $star = null;
		foreach ($groups as $g) {
			foreach ($g[0] as $agent) {
				if ($agent !== '*' && strpos(self::AGENT, $agent) === 0) $specific = array_merge((array) $specific, $g[1]);
				if ($agent === '*') $star = array_merge((array) $star, $g[1]);
			}
		}
		$this->_rules = $specific !== null ? $specific : (array) $star;
	}

	/**
	 * @param $pathAndQuery string
	 * @return boolean
	 */
	public function allows($pathAndQuery) {
		$best = null;
		foreach ($this->_rules as $rule) {
			if (strpos($pathAndQuery, $rule['path']) === 0) {
				if ($best === null || strlen($rule['path']) > strlen($best['path']) || (strlen($rule['path']) === strlen($best['path']) && $rule['allow'])) $best = $rule;
			}
		}
		return $best === null || $best['allow'];
	}

	/**
	 * @param $rules array As returned by rules()
	 * @return DoajPaRobots
	 */
	public static function fromRules($rules) {
		$robots = new self(null);
		foreach ((array) $rules as $rule) {
			if (is_array($rule) && isset($rule['path'], $rule['allow']) && is_string($rule['path'])) {
				$robots->_rules[] = array('allow' => (bool) $rule['allow'], 'path' => $rule['path']);
			}
		}
		return $robots;
	}

	/** @return array */
	public function rules() {
		return $this->_rules;
	}
}
