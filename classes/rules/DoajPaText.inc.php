<?php

/**
 * @file classes/rules/DoajPaText.inc.php
 *
 * @class DoajPaText
 * @brief Deterministic English text utilities for Stage 03 rules. Pure; no OJS dependency; no network; no inference.
 *  Every sentence or snippet returned is a verbatim substring of the evidence text, so DoajPaOutcomeGuard can verify
 *  quotes. Patterns are bounded (no nested unbounded quantifiers) and inputs are capped at the extractor limits.
 */

class DoajPaText {

	const MAX_QUOTE = 300;
	const NEGATION = '/\b(no|not|never|without|none|neither|nor|cannot|can\'t|don\'t|doesn\'t|isn\'t|aren\'t|won\'t)\b[^.!?\n]{0,40}$/i';

	/**
	 * Sentences and lines of a text, each a trimmed verbatim substring.
	 * @return string[]
	 */
	public static function sentences($text) {
		$out = array();
		foreach (preg_split('/\n+|(?<=[.!?])\s+(?=[A-Z0-9\x{201C}"(])/u', (string) $text) as $piece) {
			$piece = trim($piece);
			if ($piece !== '') $out[] = $piece;
		}
		return $out;
	}

	/**
	 * All sentences of the given evidence items that match $regex.
	 * @param $items array Evidence items (DoajPaEvaluationContext)
	 * @param $regex string PCRE
	 * @param $skipNegated boolean Skip a match preceded (within the sentence) by a negation word
	 * @return array List of ['evidence' => item, 'sentence' => string, 'quote' => verbatim snippet, 'match' => string]
	 */
	public static function find($items, $regex, $skipNegated = false) {
		$hits = array();
		foreach ($items as $e) {
			foreach (self::sentences($e['quote']) as $s) {
				if (!preg_match($regex, $s, $m, PREG_OFFSET_CAPTURE)) continue;
				if ($skipNegated && self::negatedAt($s, $m[0][1])) continue;
				$hits[] = array('evidence' => $e, 'sentence' => $s, 'quote' => self::snippet($s, $m[0][1]), 'match' => $m[0][0]);
			}
		}
		return $hits;
	}

	/** @return boolean Whether a negation word appears shortly before byte offset $pos in the sentence */
	public static function negatedAt($sentence, $pos) {
		$before = substr($sentence, max(0, $pos - 80), min(80, $pos));
		return preg_match(self::NEGATION, $before) === 1;
	}

	/** @return string A verbatim snippet of at most MAX_QUOTE characters around a byte offset */
	public static function snippet($sentence, $bytePos = 0) {
		if (mb_strlen($sentence, 'UTF-8') <= self::MAX_QUOTE) return $sentence;
		$charPos = mb_strlen(substr($sentence, 0, $bytePos), 'UTF-8');
		$start = max(0, $charPos - 100);
		return mb_substr($sentence, $start, self::MAX_QUOTE, 'UTF-8');
	}

	/** @return int Number of words (letter runs) */
	public static function words($text) {
		return preg_match_all('/\p{L}+/u', (string) $text);
	}

	/** Latin letters with diacritics -> ASCII (a fixed table: iconv transliteration depends on the C library and locale). */
	const FOLD = array(
		'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a', 'æ' => 'ae', 'ç' => 'c',
		'ć' => 'c', 'ĉ' => 'c', 'ċ' => 'c', 'č' => 'c', 'ď' => 'd', 'đ' => 'd', 'ð' => 'd', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
		'ē' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e', 'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g', 'ĥ' => 'h', 'ħ' => 'h',
		'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ĩ' => 'i', 'ī' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i', 'ĳ' => 'ij', 'ĵ' => 'j',
		'ķ' => 'k', 'ĺ' => 'l', 'ļ' => 'l', 'ľ' => 'l', 'ŀ' => 'l', 'ł' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ņ' => 'n', 'ň' => 'n', 'ŉ' => 'n',
		'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o', 'ŏ' => 'o', 'ő' => 'o', 'œ' => 'oe', 'ŕ' => 'r',
		'ŗ' => 'r', 'ř' => 'r', 'ś' => 's', 'ŝ' => 's', 'ş' => 's', 'š' => 's', 'ș' => 's', 'ß' => 'ss', 'ţ' => 't', 'ť' => 't', 'ŧ' => 't',
		'ț' => 't', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ũ' => 'u', 'ū' => 'u', 'ŭ' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
		'ŵ' => 'w', 'ý' => 'y', 'ÿ' => 'y', 'ŷ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z', 'þ' => 'th');

	/** @return string Lower-case form with Latin diacritics removed, for comparisons only (never shown to users) */
	public static function fold($s) {
		return strtr(mb_strtolower((string) $s, 'UTF-8'), self::FOLD);
	}

	/**
	 * Person name tokens for matching: folded, without academic titles, punctuation and degrees.
	 * @return string[]
	 */
	public static function nameTokens($name) {
		$s = preg_replace('/\([^)]{0,80}\)/', ' ', self::fold($name));
		$s = preg_replace('/[^a-z\s\'-]/', ' ', $s);
		$stop = array('dr', 'drs', 'prof', 'professor', 'assoc', 'assist', 'asst', 'mr', 'mrs', 'ms', 'phd', 'msc', 'ma', 'md', 'mba', 'bsc', 'ba',
			'ir', 'hon', 'sir', 'jr', 'sr', 'ii', 'iii', 'spd', 'mpd', 'se', 'mm', 'st', 'mt', 'mkom', 'skom', 'mag', 'dipl', 'ing', 'eng');
		$out = array();
		foreach (preg_split('/[\s\'-]+/', trim($s)) as $t) {
			if ($t !== '' && !in_array($t, $stop, true)) $out[] = $t;
		}
		return $out;
	}

	/**
	 * Compare two person names. "confirmed" = same full name (at least two tokens, all equal, same order or same
	 * set); "ambiguous" = same surname and compatible initials, or one name a subset of the other; "none" otherwise.
	 * Uncertain identities are never treated as confirmed (Stage 01 F4).
	 * @return string confirmed|ambiguous|none
	 */
	public static function nameMatch($a, $b) {
		$x = self::nameTokens($a);
		$y = self::nameTokens($b);
		if (count($x) < 2 || count($y) < 2) return 'none';
		$sx = $x; $sy = $y; sort($sx); sort($sy);
		if ($x === $y || $sx === $sy) return 'confirmed';
		if (end($x) !== end($y)) return 'none';
		$fx = array_slice($x, 0, -1); $fy = array_slice($y, 0, -1);
		$compatible = true;
		$n = min(count($fx), count($fy));
		for ($i = 0; $i < $n; $i++) {
			if ($fx[$i][0] !== $fy[$i][0]) { $compatible = false; break; }
			if (strlen($fx[$i]) > 1 && strlen($fy[$i]) > 1 && $fx[$i] !== $fy[$i]) { $compatible = false; break; }
		}
		if (!$compatible) return 'none';
		return 'ambiguous';
	}

	const MONTHS = array('jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'sept' => 9,
		'oct' => 10, 'nov' => 11, 'dec' => 12);

	/**
	 * Parse one date. Numeric day/month orders that cannot be told apart (e.g. 03/04/2024) are "ambiguous".
	 * @return array ['date' => 'Y-m-d'|null, 'status' => ok|ambiguous|invalid]
	 */
	public static function parseDate($s) {
		$s = trim(self::fold($s));
		$mon = '(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sept?(?:ember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)';
		if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/', $s, $m)) return self::ymd((int) $m[1], (int) $m[2], (int) $m[3]);
		if (preg_match('/^(\d{1,2})(?:st|nd|rd|th)?[\s-]+' . $mon . '\.?,?[\s-]+(\d{4})\b/', $s, $m)) return self::ymd((int) $m[3], self::month($m[2]), (int) $m[1]);
		if (preg_match('/^' . $mon . '\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/', $s, $m)) return self::ymd((int) $m[3], self::month($m[1]), (int) $m[2]);
		if (preg_match('/^(\d{1,2})[\/.](\d{1,2})[\/.](\d{4})\b/', $s, $m)) {
			$a = (int) $m[1]; $b = (int) $m[2];
			if ($a > 12 && $b <= 12) return self::ymd((int) $m[3], $b, $a);
			if ($b > 12 && $a <= 12) return self::ymd((int) $m[3], $a, $b);
			if ($a === $b) return self::ymd((int) $m[3], $a, $b);
			return array('date' => null, 'status' => 'ambiguous');
		}
		return array('date' => null, 'status' => 'invalid');
	}

	private static function month($name) {
		$k = substr($name, 0, 3);
		return isset(self::MONTHS[$k]) ? self::MONTHS[$k] : 0;
	}

	private static function ymd($y, $m, $d) {
		if ($y < 1900 || $y > 2100 || !checkdate($m, $d, $y)) return array('date' => null, 'status' => 'invalid');
		return array('date' => sprintf('%04d-%02d-%02d', $y, $m, $d), 'status' => 'ok');
	}

	/** @return boolean Whether an ISSN (NNNN-NNNC) has a valid check digit */
	public static function issnValid($issn) {
		if (!preg_match('/^(\d{4})-(\d{3})([\dXx])$/', $issn, $m)) return false;
		$digits = str_split($m[1] . $m[2]);
		$sum = 0;
		foreach ($digits as $i => $d) $sum += (int) $d * (8 - $i);
		$check = (11 - $sum % 11) % 11;
		$c = strtoupper($m[3]);
		return ($check === 10 && $c === 'X') || ($check < 10 && $c === (string) $check);
	}
}
