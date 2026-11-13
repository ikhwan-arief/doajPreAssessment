<?php

/**
 * @file classes/rules/DoajPaFormGuideBuilder.inc.php
 *
 * @class DoajPaFormGuideBuilder
 * @brief FQ-01..FQ-20 answers (Stage 01 F3, AC-13) from validated information only: facts quoted from retrieved,
 *  verified-English evidence of the run, and criterion outcomes AFTER DoajPaOutcomeGuard. Never fabricated:
 *   - "supported" needs a verbatim quote from verified-English evidence (the quote and its URL are in the answer);
 *   - "not_found" only when the governing criterion established a missing fact (fail/warning on verified pages,
 *     crawl not truncated); an unread or unverified page is never "not found";
 *   - everything else is "needs_verification" (with what was found), and FQ-08 (keywords) is never answered.
 *  The mapping is PROPOSED (decision D-03-2, PENDING APPROVAL); answers are candidate answers.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/DoajPaFacts.inc.php');

class DoajPaFormGuideBuilder {

	const PENDING = 'Candidate answer: FQ mapping 0.1.0-draft is PENDING APPROVAL (D-03-2); confirm before use in a DOAJ application.';
	const ARCHIVES = '/\b(LOCKSS|CLOCKSS|PKP ?PN|PKP Preservation Network|Portico|Internet Archive|PubMed Central|Europe ?PMC|CINES|national library(?: of [A-Z]\w+)?)\b/i';

	/** @var DoajPaFacts */
	private $f;
	/** @var array criterion key => guarded outcome */
	private $o;
	/** @var array mapping row by FQ */
	private $map = array();
	/** @var string label appended to every answer */
	private $label;
	/** @var string|null when set, questions without a mapping row are not answered and carry this text */
	private $unmapped;

	/**
	 * @param $facts DoajPaFacts
	 * @param $outcomes array criterion key => outcome (after DoajPaOutcomeGuard)
	 * @param $mapping array fqMapping rows of the dataset
	 * @param $label string label appended to every answer (candidate: PENDING)
	 * @param $unmapped string|null for an approved mapping: text of the questions it does not map (never answered)
	 */
	public function __construct($facts, $outcomes, $mapping, $label = self::PENDING, $unmapped = null) {
		$this->f = $facts;
		$this->o = $outcomes;
		$this->label = $label;
		$this->unmapped = $unmapped;
		foreach ($mapping as $m) $this->map[$m['fq']] = $m;
	}

	/** @return array list of [questionKey, answerStatus, answerText, evidenceId, uncertainty] for FQ-01..FQ-20 */
	public function build() {
		$out = array();
		for ($i = 1; $i <= 20; $i++) {
			$fq = sprintf('FQ-%02d', $i);
			$m = isset($this->map[$fq]) ? $this->map[$fq] : null;
			if (!$m && $this->unmapped !== null) {
				$out[] = array('questionKey' => $fq, 'answerStatus' => 'not_yet_assessed', 'answerText' => null, 'evidenceId' => null, 'uncertainty' => $this->unmapped);
				continue;
			}
			$method = 'fq' . sprintf('%02d', $i);
			$a = $this->$method();
			$unc = trim(($a['uncertainty'] !== '' ? $a['uncertainty'] . ' ' : '') . ($m ? 'Mapping: ' . $m['title'] . '. ' : '') . $this->label);
			$out[] = array('questionKey' => $fq, 'answerStatus' => $a['status'], 'answerText' => $a['text'], 'evidenceId' => $a['evidenceId'], 'uncertainty' => $unc);
		}
		return $out;
	}

	//
	// Helpers
	//

	/** @return string The ADR-013 label when the governing outcome rests on language-neutral facts */
	private function neutralNote($key) {
		return isset($this->o[$key]['basis']) && $this->o[$key]['basis'] === 'neutral'
			? 'Based on language-neutral facts (names, dates, ISSNs or counts) read from a page with too little text for a language verdict (ADR-013); check the page.' : '';
	}

	private function status($key) {
		return isset($this->o[$key]) ? $this->o[$key]['status'] : null;
	}

	/** @return array Verified-English facts only (possibly empty) */
	private static function v($facts) {
		return array_values(array_filter($facts, function ($x) { return isset($x['evidence']) && $x['evidence']['verifiedEnglish']; }));
	}

	private static function q($x) {
		return '"' . $x['quote'] . '" (' . $x['evidence']['url'] . ')';
	}

	private function supported($text, $fact, $uncertainty = '') {
		return array('status' => 'supported', 'text' => $text, 'evidenceId' => $fact['evidence']['id'], 'uncertainty' => $uncertainty);
	}

	private function verify($text, $fact, $uncertainty) {
		return array('status' => 'needs_verification', 'text' => $text, 'evidenceId' => $fact ? $fact['evidence']['id'] : null, 'uncertainty' => $uncertainty);
	}

	/** Not found only when the governing criterion established it; otherwise needs verification. */
	private function notFound($key, $text) {
		$s = $this->status($key);
		if (in_array($s, array('fail', 'warning'), true)) {
			$ids = $this->o[$key]['evidenceIds'];
			return array('status' => 'not_found', 'text' => $text, 'evidenceId' => $ids ? $ids[0] : null,
				'uncertainty' => 'Searched the pages cited by ' . $key . ' (' . $s . '); the information may exist elsewhere on the site.');
		}
		return array('status' => 'needs_verification', 'text' => null, 'evidenceId' => null,
			'uncertainty' => 'Not established from the evidence read (' . $key . ': ' . ($s ?: 'not evaluated') . '); an unread or unverified page is never treated as missing.');
	}

	private function fromOutcome($key, $passText, $notFoundText) {
		$s = $this->status($key);
		if ($s === 'pass' && $this->o[$key]['quotes']) {
			$q = $this->o[$key]['quotes'][0];
			$e = $this->f->evidence[$q['evidenceId']];
			return array('status' => 'supported', 'text' => $passText . ' "' . $q['text'] . '" (' . $e['url'] . ')', 'evidenceId' => $q['evidenceId'], 'uncertainty' => $this->neutralNote($key));
		}
		if ($s === 'fail' || $s === 'warning') return $this->notFound($key, $notFoundText);
		$ids = $s !== null ? $this->o[$key]['evidenceIds'] : array();
		return array('status' => 'needs_verification', 'text' => null, 'evidenceId' => $ids ? $ids[0] : null,
			'uncertainty' => $key . ': ' . ($s ?: 'not evaluated') . ($s !== null ? ' - ' . mb_substr($this->o[$key]['rationale'], 0, 400, 'UTF-8') : ''));
	}

	//
	// FQ-01 .. FQ-20 (docs/stage03/criteria/fq-mapping.md)
	//

	private function fq01() {
		return $this->fromOutcome('guide.oa-statement', 'Yes (open access statement found):', 'No open access statement was found.');
	}

	private function fq02() {
		$years = array(); $ev = null;
		foreach (DoajPaFacts::verified($this->f->items(array('archive', 'issue'), array('html_main_content'))) as $e) {
			if (preg_match_all('/\b(?:Vol\.?|Volume)\s*\d+[^\n]{0,40}?\((\d{4})\)/i', $e['quote'], $m, PREG_OFFSET_CAPTURE)) {
				foreach ($m[1] as $i => $y) {
					if (!$ev || (int) $y[0] < min($years)) $ev = array('evidence' => $e, 'quote' => $m[0][$i][0]);
					$years[] = (int) $y[0];
				}
			}
		}
		$flip = self::v($this->f->condition('flipped'));
		if ($flip) return $this->verify('Flip statement: ' . self::q($flip[0]), $flip[0], 'Estimate only: the year of the change to open access must be confirmed by the journal.');
		if ($years) return $this->verify('Estimate: ' . min($years) . ' (earliest issue year in the archive read: ' . self::q($ev) . ').', $ev,
			'Estimate only: the archive may contain digitised back issues or content published before an open licence was used.');
		return $this->verify(null, null, 'No dated issue was read; the start year cannot be estimated.');
	}

	private function fq03() {
		$t = self::v($this->f->siteTitles());
		if (!$t) return $this->verify(null, null, 'No journal title was read from verified-English pages.');
		$titles = array_values(array_unique(array_map(function ($x) { return $x['title']; }, $t)));
		$text = 'Title as displayed: ' . $titles[0] . ' ' . self::q($t[0]) . (count($titles) > 1 ? '. Other forms found: ' . implode('; ', array_slice($titles, 1, 3)) . '.' : '.');
		return count($titles) > 1 ? $this->verify($text, $t[0], 'Different title forms appear on the website.')
			: $this->supported($text, $t[0], 'Match with the ISSN Portal record is not verified (external lookup not approved, D-03-4).');
	}

	private function fq04() {
		$h = $this->f->homepage();
		if ($h['resource'] && $h['main'] && $h['main']['verifiedEnglish']) {
			$url = $h['resource']['finalUrl'] ?: $h['resource']['url'];
			return array('status' => 'supported', 'text' => $url, 'evidenceId' => $h['main']['id'], 'uncertainty' => 'Retrieved in this run (one vantage point).');
		}
		return $this->verify($h['resource'] ? $h['resource']['url'] : null, null, 'The homepage was not read as verified-English text (ADR-011).');
	}

	private function issn($label) {
		$all = self::v($this->f->issns());
		$valid = array_values(array_filter($all, function ($x) { return $x['valid']; }));
		$hit = array_values(array_filter($valid, function ($x) use ($label) { return $x['label'] === $label; }));
		$ext = 'Registration at the ISSN Portal is not verified (D-03-4).';
		if ($hit) return $this->supported($hit[0]['issn'] . ' ' . self::q($hit[0]), $hit[0], $ext);
		$unl = array_values(array_filter($valid, function ($x) { return $x['label'] === 'unlabelled'; }));
		if ($unl) return $this->verify('ISSN shown without a print/online label: ' . $unl[0]['issn'] . ' ' . self::q($unl[0]), $unl[0], 'The website does not say whether this is the print or the online ISSN. ' . $ext);
		$bad = array_values(array_filter($all, function ($x) { return !$x['valid']; }));
		if ($bad) return $this->verify('ISSN with an invalid check digit: ' . $bad[0]['issn'] . ' ' . self::q($bad[0]), $bad[0], 'Check the ISSN against issn.org.');
		$other = array_values(array_filter($valid, function ($x) use ($label) { return $x['label'] !== $label; }));
		if ($other) return $this->verify(null, $other[0], 'Only a ' . $other[0]['label'] . ' ISSN (' . $other[0]['issn'] . ') is displayed; a ' . $label . ' ISSN may not exist.');
		return $this->notFound('guide.issn-displayed', 'No ' . $label . ' ISSN was found.');
	}

	private function fq05() { return $this->issn('print'); }
	private function fq06() { return $this->issn('online'); }

	private function fq07() {
		$l = self::v($this->f->manuscriptLanguages());
		if ($l) return $this->supported(implode(', ', $l[0]['languages']) . ' ' . self::q($l[0]), $l[0]);
		$read = DoajPaFacts::verified($this->f->items(array('author_guidelines'), array('html_main_content')));
		if ($read && !$this->f->crawlTruncated()) {
			return array('status' => 'not_found', 'text' => 'No statement of manuscript languages was found in the author guidelines.', 'evidenceId' => $read[0]['id'],
				'uncertainty' => 'Searched: ' . $read[0]['url'] . '.');
		}
		return $this->verify(null, null, 'The author guidelines were not read as verified-English text.');
	}

	private function fq08() {
		$src = DoajPaFacts::verified($this->f->topic('aims_scope', $this->f->items(null, array('html_main_content'))));
		return array('status' => 'not_yet_assessed', 'text' => null, 'evidenceId' => $src ? $src[0]['evidence']['id'] : null,
			'uncertainty' => 'Not answered automatically: subject keywords are an editorial choice.' . ($src ? ' Source to choose from: ' . self::q($src[0]) . '.' : ''));
	}

	private function fq09() {
		$p = self::v($this->f->publisher());
		if (!$p) return $this->notFound('guide.disclosure-publisher', 'No publisher name was found.');
		$a = self::v($this->f->addresses());
		$text = 'Publisher: ' . $p[0]['name'] . ' ' . self::q($p[0]) . ($a ? '. Address: ' . self::q($a[0]) : '. No address recognised') . '.';
		return $this->verify($text, $p[0], 'The publisher\'s country of registration and business activity cannot be verified from the website.');
	}

	private function fq10() {
		$l = self::v($this->f->licences());
		if (!$l) return $this->notFound('guide.licensing-terms-stated', 'No licence was found.');
		$codes = array();
		foreach ($l as $x) if (!isset($codes[$x['code']])) $codes[$x['code']] = $x;
		$text = implode('; ', array_map(function ($c, $x) { return DoajPaFacts::licenceLabel($c, $x['version']) . ' ' . DoajPaFormGuideBuilder::quoted($x); }, array_keys($codes), $codes));
		$c = $this->status('guide.copyright-no-contradiction');
		if ($c === 'contradiction') return $this->verify($text, reset($codes), 'Conflicting licence or copyright statements were found (guide.copyright-no-contradiction).');
		if (isset($codes['other'])) return $this->verify($text, $codes['other'], 'A publisher\'s own licence was found; whether it is an open licence needs review.');
		if (array_keys($codes) === array('CC')) return $this->verify($text, $codes['CC'], 'A Creative Commons licence is named without its variant (for example CC BY or CC BY-NC).');
		return $this->supported($text, reset($codes), count($codes) > 1 ? 'More than one licence is stated; check whether authors choose.' : '');
	}

	/** @internal */
	public static function quoted($x) { return self::q($x); }

	private function fq11() {
		$s = $this->status('guide.licensing-in-full-text');
		$o = $s !== null ? $this->o['guide.licensing-in-full-text'] : null;
		if ($s === 'pass' && $o['quotes']) {
			return array('status' => 'supported', 'text' => 'Yes: every readable sampled full text shows licensing information. ' . mb_substr($o['rationale'], 0, 300, 'UTF-8'),
				'evidenceId' => $o['quotes'][0]['evidenceId'], 'uncertainty' => 'A sample cannot prove all versions of every article.');
		}
		if ($s === 'fail' || $s === 'warning') return $this->notFound('guide.licensing-in-full-text', 'No: ' . mb_substr($o['rationale'], 0, 400, 'UTF-8'));
		return $this->verify(null, null, 'Full texts were not readable as verified-English text in this run (' . ($s ?: 'not evaluated') . ').');
	}

	private function fq12() {
		$c = array_values(array_filter(self::v($this->f->copyright()), function ($x) { return !$x['chrome']; }));
		$retain = array_values(array_filter($c, function ($x) { return $x['kind'] === 'retain'; }));
		$restrict = array_values(array_filter($c, function ($x) { return in_array($x['kind'], array('transfer', 'exclusive', 'shared'), true); }));
		if ($retain && $restrict) return $this->verify('Authors retain copyright ' . self::q($retain[0]) . ', but a restriction is also stated ' . self::q($restrict[0]) . '.', $retain[0], 'Mixed statements: check which applies.');
		if ($retain) return $this->supported('Yes: ' . self::q($retain[0]), $retain[0]);
		if ($restrict) return $this->supported('No: ' . self::q($restrict[0]), $restrict[0], 'Exclusive or shared rights may have conditions; check the full agreement.');
		$st = array_values(array_filter($c, function ($x) { return $x['kind'] === 'statement'; }));
		if ($st) return $this->verify('Copyright statement found, holder not recognised: ' . self::q($st[0]), $st[0], 'Who holds the copyright is not stated in recognisable terms.');
		return $this->notFound('guide.disclosure-copyright', 'No copyright terms for published content were found.');
	}

	private function fq13() {
		$items = $this->f->items(array('about', 'custom', 'information', 'home', 'author_guidelines', 'about_other'));
		$t = DoajPaFacts::verified($this->f->topic('review_types', $items));
		if ($t) {
			$types = array_values(array_unique(array_map(function ($x) { return strtolower($x['match']); }, $t)));
			$edOnly = $types === array('editorial review') && !DoajPaFacts::verified(array_map(function ($h) { return $h['evidence']; }, $this->f->condition('arts_humanities')));
			$text = implode(', ', $types) . ' ' . self::q($t[0]);
			return $edOnly ? $this->verify($text, $t[0], 'Editorial review is accepted only for arts and humanities journals.') : $this->supported($text, $t[0]);
		}
		$g = DoajPaFacts::verified($this->f->topic('peer_review', $items));
		usort($g, function ($a, $b) { return (DoajPaText::words($b['sentence']) >= 5) - (DoajPaText::words($a['sentence']) >= 5); });
		if ($g) return $this->verify('Peer review is mentioned without its type: ' . self::q($g[0]), $g[0], 'The type of peer review is not stated in recognisable terms.');
		return $this->notFound('guide.peer-review-type-stated', 'No peer review policy was found.');
	}

	private function fq14() {
		$p = DoajPaFacts::verified($this->f->topic('plagiarism', $this->f->items(null)));
		$named = array_values(array_filter($p, function ($x) { return preg_match('/iThenticate|Turnitin|Crossref Similarity Check|Similarity Check|Plagiarism ?Checker|Copyscape|Grammarly/i', $x['sentence']); }));
		if ($named) return $this->supported('Yes: ' . self::q($named[0]), $named[0]);
		if ($p) return $this->verify('Plagiarism is mentioned: ' . self::q($p[0]), $p[0], 'No screening service or routine screening is named.');
		return $this->notFound('guide.plagiarism-screening', 'No plagiarism screening statement was found.');
	}

	private function linkFrom($key, $what) {
		$s = $this->status($key);
		if ($s === 'pass' && $this->o[$key]['quotes']) {
			$e = $this->f->evidence[$this->o[$key]['quotes'][0]['evidenceId']];
			return array('status' => 'supported', 'text' => $e['url'] . ' ("' . $this->o[$key]['quotes'][0]['text'] . '")', 'evidenceId' => $e['id'], 'uncertainty' => $this->neutralNote($key));
		}
		return $this->fromOutcome($key, $what, 'No ' . $what . ' page was found.');
	}

	private function fq15() { return $this->linkFrom('guide.disclosure-aims-scope', 'aims and scope'); }
	private function fq17() { return $this->linkFrom('guide.disclosure-author-instructions', 'instructions for authors'); }

	private function fq16() {
		$a = $this->linkFrom('guide.disclosure-editorial-board', 'editorial board');
		if ($a['status'] === 'supported' && $this->status('guide.board-names-affiliations') === 'fail') {
			$a['uncertainty'] = trim($a['uncertainty'] . ' Some members have no affiliation: ' . mb_substr($this->o['guide.board-names-affiliations']['rationale'], 0, 300, 'UTF-8'));
		}
		return $a;
	}

	private function fq18() {
		$fees = $this->f->fees();
		$apc = array_values(array_filter(self::v($fees['fees']), function ($x) { return $x['type'] === 'apc'; }));
		// A no-fee statement conflicts with an APC only when it covers all charges or the APC ("no submission fee" does not).
		$no = array_values(array_filter(self::v($fees['noFee']), function ($x) { return !isset($x['scope']) || array_intersect($x['scope'], array('all', 'apc')); }));
		$w = self::v($fees['waivers']);
		$waiver = $w ? ' Waiver: ' . self::q($w[0]) . '.' : '';
		$amount = array_values(array_filter($apc, function ($x) { return $x['amount'] !== null; }));
		if ($no && $amount) return $this->verify('Conflicting: ' . self::q($no[0]) . ' and ' . self::q($amount[0]) . '.', $no[0], 'Fee statements conflict across pages.');
		if ($amount) return $this->supported('Yes: ' . $amount[0]['amount'] . ' ' . self::q($amount[0]) . '.' . $waiver, $amount[0], $w ? '' : 'No waiver statement was found.');
		// an explicit "no charge" statement outweighs fee words without an amount (often policy wording, not a charge)
		if ($no) return $this->supported('No: ' . self::q($no[0]) . '.' . $waiver, $no[0], $apc ? 'Fee wording without an amount also appears: ' . self::q($apc[0]) . '; check it is not a charge.' : '');
		if ($apc) return $this->verify('APC mentioned without an amount: ' . self::q($apc[0]) . '.' . $waiver, $apc[0], 'The amount is not stated.');
		return $this->notFound('guide.disclosure-author-charges', 'No statement of publication fees was found.');
	}

	private function fq19() {
		$fees = $this->f->fees();
		$other = array_values(array_filter(self::v($fees['fees']), function ($x) { return $x['type'] !== 'apc'; }));
		if ($other) {
			$text = implode('; ', array_map(function ($x) { return $x['type'] . ($x['amount'] !== null ? ' ' . $x['amount'] : ' (no amount)') . ' ' . DoajPaFormGuideBuilder::quoted($x); }, array_slice($other, 0, 6)));
			$noAmount = array_filter($other, function ($x) { return $x['amount'] === null; });
			return $noAmount ? $this->verify('Yes: ' . $text, $other[0], 'Some fees are mentioned without amount or conditions.') : $this->supported('Yes: ' . $text, $other[0]);
		}
		$general = array_values(array_filter(self::v($fees['noFee']), function ($x) { return preg_match('/\b(?:no|without|free of)\s+(?:any\s+)?(?:charges?|fees?)\b|does not charge|not charge (?:any|authors)/i', $x['sentence']); }));
		if ($general) return $this->supported('No: ' . self::q($general[0]) . '.', $general[0]);
		return $this->notFound('guide.disclosure-author-charges', 'No statement of other fees was found.');
	}

	private function fq20() {
		$items = $this->f->items(null);
		$arch = array_values(array_filter(DoajPaFacts::verified(DoajPaText::find($items, self::ARCHIVES, true)), function ($x) { return !preg_match(DoajPaFacts::INDEXING, $x['sentence']); }));
		$repo = DoajPaFacts::verified($this->f->topic('repository', $items));
		$ids = self::v($this->f->identifiers());
		$parts = array();
		if ($arch) $parts[] = 'Preservation: ' . implode(', ', array_values(array_unique(array_map(function ($x) { return $x['match']; }, $arch)))) . ' ' . self::q($arch[0]);
		if ($repo) $parts[] = 'Repository policy: ' . self::q($repo[0]);
		if ($ids) $parts[] = 'Identifiers in sampled articles: ' . implode(', ', array_values(array_unique(array_map(function ($x) { return $x['type']; }, $ids))));
		if (!$parts) return $this->notFound('guide.archiving-policy', 'No preservation service, repository policy or persistent identifier was found.');
		$first = $arch ? $arch[0] : ($repo ? $repo[0] : $ids[0]);
		$missing = array();
		if (!$arch) $missing[] = 'preservation service';
		if (!$repo) $missing[] = 'repository policy';
		if (!$ids) $missing[] = 'persistent identifiers';
		$text = implode('. ', $parts) . '.';
		return $missing ? $this->verify($text, $first, 'Not found: ' . implode(', ', $missing) . '. Whether archiving is active is not verified.')
			: $this->supported($text, $first, 'Whether archiving is active is not verified.');
	}
}
