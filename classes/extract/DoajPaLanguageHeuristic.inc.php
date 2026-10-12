<?php

/**
 * @file classes/extract/DoajPaLanguageHeuristic.inc.php
 *
 * @class DoajPaLanguageHeuristic
 * @brief Advisory, deterministic language check for the English-only restriction (PROD-04, F2). Counts
 *  stopwords of English and seven other common journal languages, combined with the HTML lang attribute.
 *  It is a heuristic: it never proves a language. Short or weak-signal texts (for example navigation-heavy
 *  pages) are "undetermined"; "mixed" needs a real signal for English AND another language (needs human
 *  verification). No language model, no translation.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaLanguageHeuristic {

	const MIN_WORDS = 40;
	const STOPWORDS = array(
		'en' => array('the','of','and','to','in','is','that','for','it','as','with','be','by','on','not','this','are','or','from','at','which','an','have','has','was','were','will','can','may','all','their','its','these','they','we','our','been','should','must','any','such','other','also','into','than'),
		'id' => array('yang','dan','di','ke','dari','untuk','dengan','ini','itu','dalam','pada','adalah','tidak','akan','oleh','atau','juga','sebagai','kami','dapat','telah','bahwa','harus','setiap','tersebut','para','secara','bagi','agar','karena'),
		'es' => array('el','los','del','las','que','y','por','una','para','con','es','se','su','al','lo','como','más','pero','sus','le','ya','este','esta','son','entre','sobre','también'),
		'fr' => array('le','les','des','et','un','une','du','est','que','pour','dans','qui','pas','par','sur','au','avec','ce','sont','aux','ou','cette','ces','été','leur','nous'),
		'de' => array('der','die','das','und','ist','nicht','mit','von','zu','den','des','ein','eine','für','auf','im','dem','sich','auch','wird','werden','sind','bei','oder','nach'),
		'pt' => array('os','do','da','dos','das','em','um','uma','para','com','não','que','por','no','na','são','mais','como','seu','sua','ao','pelo','pela','ou'),
		'it' => array('il','lo','gli','di','che','e','per','una','con','non','sono','della','delle','degli','nel','nella','alla','dei','è','più','anche'),
		'nl' => array('de','het','een','en','van','dat','niet','op','te','zijn','voor','met','als','ook','aan','wordt','bij','naar','uit','deze'),
	);

	/**
	 * @param $text string
	 * @param $langAttribute string|null e.g. "en-US", "id"
	 * @return array verdict (english|non_english|mixed|undetermined), detected (language code|null), words, shares
	 */
	public static function assess($text, $langAttribute = null) {
		preg_match_all('/\p{L}+/u', mb_strtolower((string) $text, 'UTF-8'), $m);
		$words = $m[0];
		$n = count($words);
		$shares = array();
		foreach (self::STOPWORDS as $lang => $list) {
			$set = array_flip($list);
			$hits = 0;
			foreach ($words as $w) if (isset($set[$w])) $hits++;
			$shares[$lang] = $n ? round($hits / $n, 3) : 0.0;
		}
		$attr = $langAttribute ? strtolower(substr($langAttribute, 0, 2)) : null;
		$out = array('verdict' => 'undetermined', 'detected' => null, 'words' => $n, 'shares' => $shares, 'langAttribute' => $langAttribute);
		$others = $shares; unset($others['en']); arsort($others);
		$bestOther = key($others); $otherShare = current($others);
		if ($n < self::MIN_WORDS) {
			if ($attr !== null && $attr !== 'en') { $out['verdict'] = 'non_english'; $out['detected'] = $attr; }
			return $out;
		}
		if ($shares['en'] >= 0.20 && $shares['en'] >= 2 * $otherShare) {
			$out['verdict'] = 'english'; $out['detected'] = 'en';
		} elseif ($otherShare >= 0.15 && $shares['en'] < 0.08) {
			$out['verdict'] = 'non_english'; $out['detected'] = $bestOther;
		} elseif ($shares['en'] >= 0.08 && $otherShare >= 0.08) {
			$out['verdict'] = 'mixed';
		} else {
			$out['verdict'] = 'undetermined';
		}
		if ($out['verdict'] !== 'english' && $attr !== null && $attr !== 'en' && $out['verdict'] !== 'non_english') {
			$out['verdict'] = 'non_english'; $out['detected'] = $attr;
		}
		return $out;
	}
}
