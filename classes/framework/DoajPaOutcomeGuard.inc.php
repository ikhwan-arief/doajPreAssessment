<?php

/**
 * @file classes/framework/DoajPaOutcomeGuard.inc.php
 *
 * @class DoajPaOutcomeGuard
 * @brief Verifies a rule outcome before it is stored (Stage 02.07; Stage 01 F2, F5, F6). Pure; no OJS dependency.
 *  It only ever downgrades a status to "unverifiable", never upgrades one. A downgrade happens when:
 *   - the status is unknown, or the outcome is not an array (the rule failed);
 *   - cited evidence does not belong to the run, or fails provenance (see DoajPaEvaluationContext);
 *   - a quote is shorter than MIN_QUOTE characters or does not appear verbatim in the cited evidence;
 *   - "pass" has no verified quote, unless the outcome declares the basis "absence" (nothing non-conforming was found
 *     on the pages searched) or "observation" (a fact of the retrieval itself, e.g. the URL scheme) AND cites at least
 *     one searched evidence item, every cited item being retrieved, provenance-checked, verified-English evidence
 *     (Stage 03, ADR-010 R1.8);
 *   - cited evidence is not retrieved, verified-English evidence. The language check is an advisory
 *     heuristic, so "undetermined", "mixed" and "non_english" are never treated as verified English;
 *   - a detection is malformed, a duplicate, or also listed as verified clear.
 *  Basis "neutral" (ADR-013, PENDING APPROVAL D-03-9): an outcome built only from language-neutral facts (names,
 *  affiliations, dates, ISSNs, counts) may also cite evidence that the context marks as neutral-eligible (a page with
 *  too little text for a language verdict, on a site whose other pages are verified English; see
 *  DoajPaEvaluationContext::neutralEligible). Such evidence counts as acceptable for that outcome only: a pass still
 *  needs a verbatim quote, and it never yields a verified-clear item (a neutral fact never resolves a finding).
 *  Detections on unverified evidence are kept (a deficiency is never hidden) with criterion status
 *  "unverifiable". Verified-clear items need verified-English evidence and an outcome without downgrades;
 *  otherwise they are dropped, so the finding becomes "needs verification", never "resolved".
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaSchema.inc.php');
require_once(dirname(__FILE__) . '/../history/DoajPaFindingKey.inc.php');

class DoajPaOutcomeGuard {

	const VERSION = 'outcome-guard-1';
	const MIN_QUOTE = 10;

	/**
	 * @param $outcome mixed Rule return value
	 * @param $evidence array evidenceId => ['quote' => string, 'verifiedEnglish' => bool]
	 * @param $criterionKey string The rule's key (used to check finding identities)
	 * @return array status, rationale, interpretationLimit, evidenceIds, quotes, detections, verifiedClear, downgrades
	 */
	public static function apply($outcome, $evidence, $criterionKey) {
		$down = array();
		if (!is_array($outcome)) { $outcome = array(); $down[] = 'rule_returned_no_outcome'; }
		$status = isset($outcome['status']) ? $outcome['status'] : null;
		if (!is_string($status) || !in_array($status, DoajPaSchema::CRITERION_STATUSES, true)) $down[] = 'invalid_status';
		$out = array(
			'status' => $status,
			'rationale' => self::str($outcome, 'rationale'),
			'interpretationLimit' => self::str($outcome, 'interpretationLimit'),
			'evidenceIds' => array(), 'quotes' => array(), 'detections' => array(), 'verifiedClear' => array(), 'downgrades' => array(),
			'basis' => isset($outcome['basis']) && is_string($outcome['basis']) ? $outcome['basis'] : 'quote',
		);

		$cited = array();
		foreach (self::lst($outcome, 'evidenceIds') as $id) $cited[] = $id;
		$neutralBasis = isset($outcome['basis']) && $outcome['basis'] === 'neutral';
		// Evidence acceptable for this outcome: verified English, or (basis "neutral" only) neutral-eligible evidence.
		$ok = function ($id) use ($evidence, $neutralBasis) {
			return $evidence[$id]['verifiedEnglish'] || ($neutralBasis && !empty($evidence[$id]['neutral']));
		};
		$verifiedQuotes = 0;
		foreach (self::lst($outcome, 'quotes') as $q) {
			$id = is_array($q) && isset($q['evidenceId']) ? $q['evidenceId'] : null;
			$text = is_array($q) && isset($q['text']) && is_string($q['text']) ? $q['text'] : '';
			$cited[] = $id;
			if (!is_int($id) || !isset($evidence[$id])) { $down[] = 'quote_evidence_not_in_run'; continue; }
			if (mb_strlen($text, 'UTF-8') < self::MIN_QUOTE || strpos($evidence[$id]['quote'], $text) === false) { $down[] = 'quote_not_verbatim'; continue; }
			$out['quotes'][] = array('evidenceId' => $id, 'text' => $text);
			if ($ok($id)) $verifiedQuotes++;
		}

		$detections = array();
		$seen = array();
		foreach (self::lst($outcome, 'detections') as $d) {
			$item = self::item($d, $criterionKey, true);
			if ($item === null) { $down[] = 'malformed_detection'; continue; }
			if (isset($seen[$item['key']])) { $down[] = 'duplicate_detection'; continue; }
			$seen[$item['key']] = true;
			$cited[] = $item['evidenceId'];
			$detections[] = $item;
		}
		$clears = array();
		foreach (self::lst($outcome, 'verifiedClear') as $c) {
			$item = self::item($c, $criterionKey, false);
			if ($item === null) { $down[] = 'malformed_verified_clear'; continue; }
			if (isset($seen[$item['key']])) { $down[] = 'detected_and_verified_clear'; continue; }
			$seen[$item['key']] = true;
			$cited[] = $item['evidenceId'];
			$clears[] = $item;
		}

		$allVerified = true;
		$valid = array();
		foreach ($cited as $id) {
			if (!is_int($id) || !isset($evidence[$id])) { $down[] = 'evidence_not_in_run'; $allVerified = false; continue; }
			$valid[$id] = true;
			if (!$ok($id)) $allVerified = false;
		}
		if (!$allVerified && in_array($status, array('pass', 'fail', 'warning', 'contradiction'), true)) $down[] = 'evidence_not_verified_english';
		$basis = isset($outcome['basis']) && in_array($outcome['basis'], array('absence', 'observation'), true) ? $outcome['basis'] : 'quote';
		$basisOk = $basis !== 'quote' && $allVerified && count(array_filter($cited, function ($id) use ($evidence) { return is_int($id) && isset($evidence[$id]); })) > 0;
		if ($status === 'pass' && $verifiedQuotes === 0 && !$basisOk) $down[] = 'pass_without_verified_quote';

		$down = array_values(array_unique($down));
		if ($down) {
			$out['status'] = 'unverifiable';
			$out['rationale'] = trim('[Downgraded to unverifiable: ' . implode(', ', $down) . '] ' . $out['rationale']);
		}
		$out['evidenceIds'] = array_keys($valid);
		foreach ($detections as $d) {
			if (!isset($evidence[$d['evidenceId']])) continue;
			if ($down || !$ok($d['evidenceId'])) $d['criterionStatus'] = 'unverifiable';
			unset($d['key']);
			$out['detections'][] = $d;
		}
		foreach ($clears as $c) {
			if ($down || !isset($evidence[$c['evidenceId']]) || !$evidence[$c['evidenceId']]['verifiedEnglish']) continue;
			unset($c['key']);
			$out['verifiedClear'][] = $c;
		}
		$out['downgrades'] = $down;
		return $out;
	}

	/** @return array|null Normalized detection / verified-clear item with its identity key, or null if malformed */
	private static function item($raw, $criterionKey, $isDetection) {
		if (!is_array($raw) || !isset($raw['evidenceId'], $raw['subtype'], $raw['targetType']) || !is_int($raw['evidenceId'])) return null;
		$target = isset($raw['target']) ? $raw['target'] : null;
		try {
			$key = DoajPaFindingKey::build(1, $criterionKey, 1, $raw['subtype'], $raw['targetType'], $target);
		} catch (InvalidArgumentException $e) {
			return null;
		}
		$item = array('subtype' => $raw['subtype'], 'targetType' => $raw['targetType'], 'target' => $target, 'evidenceId' => $raw['evidenceId'], 'key' => $key);
		if ($isDetection) {
			$cs = isset($raw['criterionStatus']) ? $raw['criterionStatus'] : null;
			if (!is_string($cs) || !in_array($cs, DoajPaSchema::FINDING_CRITERION_STATUSES, true)) return null;
			$item['criterionStatus'] = $cs;
		}
		return $item;
	}

	private static function str($a, $k) {
		return isset($a[$k]) && is_string($a[$k]) ? $a[$k] : '';
	}

	private static function lst($a, $k) {
		return isset($a[$k]) && is_array($a[$k]) ? $a[$k] : array();
	}
}
