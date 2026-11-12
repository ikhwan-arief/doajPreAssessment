<?php

/**
 * @file classes/rules/DoajPaFacts.inc.php
 *
 * @class DoajPaFacts
 * @brief Facts extracted deterministically from ONE run's stored evidence (Stage 03). Pure; computed once per
 *  evaluation context and shared by all candidate rules and the form guide. Every fact keeps the evidence item and the
 *  verbatim sentence it came from, so outcomes can quote it. Curated English patterns only (Stage 01 F2, PROD-06).
 *  Evidence methods: html_main_content (ADR-011 main region), html_text_extraction (whole page), html_structured
 *  (meta tags, table of contents, licence/locale links, image text), pdftotext_extraction (PDF full text).
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/DoajPaText.inc.php');

class DoajPaFacts {

	/** @var array cache: spl_object_hash(context) => DoajPaFacts */
	private static $cache = array();

	/** @var DoajPaEvaluationContext */
	public $context;
	public $evidence = array();
	public $resources = array();
	public $coverage = array();
	public $refDate;
	/** @var array evidenceId => bool: neutral-eligible evidence (ADR-013, DoajPaEvaluationContext::neutralEligible) */
	public $neutral = array();
	/** @var array resourceId => bool: resources whose evidence is neutral-eligible */
	public $neutralResource = array();
	/** @var array memoized facts (the context is immutable) */
	private $memo = array();

	const INSTITUTION = '/\b(univ\w*|institut\w*|college|school|faculty|department|dept\.?|hospital|cent(?:er|re)|academy|laborator\w*|ministry|foundation|agency|council|polytechnic|politeknik|universitas|universidad|universidade|universit\S*|research|inc\.?|ltd\.?|corporation|company|society|library|museum|clinic|sekolah|fakultas|kementerian|observator\w*|bureau|organi[sz]ation|association|commission|authority|directorate|station|survey|lab|labs|badan|lembaga|balai|pusat)\b/i';
	const ROLE = '/^(editors?[- ]in[- ]chief|chief editors?|editors?|managing editors?|associate editors?|section editors?|deputy editors?|guest editors?|executive editors?|editorial (?:boards?|team|advisory boards?|board members?)|advisory (?:boards?|committee)|international advisory boards?|scientific committee|reviewers?|peer reviewers?|board members?|members|copy ?editors?|layout editors?|proofreaders?|technical editors?)\b\s*:?/i';

	/** Profile-link labels shown next to names, e.g. "Dr. A. Name [Scopus] [CV]" (1.2.0.0) */
	const LINK_LABELS = '/\s*\[(?:scopus|scholar|google scholar|orcid|cv|sinta|wos|web of science|publons|researchgate|resume|profile|email|e-mail|homepage|website|garuda|researcher ?id|h-index[^\]]*)\]/i';
	/** Words that occur in website headings but not in personal names. */
	const NOT_A_NAME = '/\b(?:information|area|frequency|access|office|journal|language|subject|published|publisher|contact|polic(?:y|ies)|issn|editorial|team|guidelines?|submissions?|focus|scope|about|current|archives?|announcements?|home|indexing|abstracting|template|authors?|reviewers?|ethics|statistics|visitors|keywords|tools|menu|review|process|fees?|charges?|copyright|licen[cs]e|privacy|statement|history|news|papers?|issue|issues|articles?|volume|search|login|register|download|quick|links?)\b/i';
	/** Country names on their own line under an affiliation (lower-case). */
	const COUNTRIES = array('afghanistan','albania','algeria','andorra','angola','argentina','armenia','australia','austria','azerbaijan','bahamas','bahrain','bangladesh','barbados','belarus','belgium','belize','benin','bhutan','bolivia','bosnia and herzegovina','botswana','brazil','brunei','brunei darussalam','bulgaria','burkina faso','burundi','cambodia','cameroon','canada','cape verde','chad','chile','china','colombia','comoros','congo','costa rica','croatia','cuba','cyprus','czech republic','czechia','denmark','djibouti','dominican republic','ecuador','egypt','el salvador','eritrea','estonia','eswatini','ethiopia','fiji','finland','france','gabon','gambia','georgia','germany','ghana','greece','guatemala','guinea','guyana','haiti','honduras','hong kong','hungary','iceland','india','indonesia','iran','iraq','ireland','israel','italy','ivory coast','jamaica','japan','jordan','kazakhstan','kenya','korea','south korea','north korea','republic of korea','kosovo','kuwait','kyrgyzstan','laos','latvia','lebanon','lesotho','liberia','libya','lithuania','luxembourg','macau','madagascar','malawi','malaysia','maldives','mali','malta','mauritania','mauritius','mexico','moldova','monaco','mongolia','montenegro','morocco','mozambique','myanmar','namibia','nepal','netherlands','the netherlands','new zealand','nicaragua','niger','nigeria','north macedonia','norway','oman','pakistan','palestine','panama','papua new guinea','paraguay','peru','philippines','poland','portugal','qatar','romania','russia','russian federation','rwanda','saudi arabia','senegal','serbia','sierra leone','singapore','slovakia','slovenia','somalia','south africa','south sudan','spain','sri lanka','sudan','suriname','sweden','switzerland','syria','taiwan','tajikistan','tanzania','thailand','timor-leste','togo','trinidad and tobago','tunisia','turkey','turkiye','türkiye','turkmenistan','uganda','ukraine','united arab emirates','uae','united kingdom','uk','united states','united states of america','usa','uruguay','uzbekistan','venezuela','vietnam','viet nam','yemen','zambia','zimbabwe');

	/** @return DoajPaFacts */
	public static function of($context) {
		$k = spl_object_hash($context);
		if (!isset(self::$cache[$k])) {
			if (count(self::$cache) > 8) self::$cache = array();
			self::$cache[$k] = new DoajPaFacts($context);
		}
		return self::$cache[$k];
	}

	private function __construct($context) {
		$this->context = $context;
		foreach ($context->evidence() as $e) $this->evidence[$e['id']] = $e;
		foreach ($context->resources() as $r) $this->resources[$r['id']] = $r;
		$this->coverage = $context->coverage();
		$this->neutral = $context->neutralEligible();
		foreach ($this->evidence as $id => $e) if (!empty($this->neutral[$id])) $this->neutralResource[$e['resourceId']] = true;
		$finished = isset($this->coverage['finishedAtUtc']) ? $this->coverage['finishedAtUtc'] : null;
		$this->refDate = $finished ? substr($finished, 0, 10) : gmdate('Y-m-d');
	}

	//
	// Evidence selection
	//

	/**
	 * Evidence items of the given page types and detection methods.
	 * @param $types string[]|null page types (null = all)
	 * @param $methods string[] detection methods
	 * @return array
	 */
	public function items($types, $methods = array('html_main_content', 'pdftotext_extraction')) {
		$out = array();
		foreach ($this->evidence as $e) {
			if (($types === null || in_array($e['pageType'], $types, true)) && in_array($e['method'], $methods, true)) $out[] = $e;
		}
		return $out;
	}

	/** @return array Verified-English items among $items (evidence items, or facts/matches carrying an 'evidence' item) */
	public static function verified($items) {
		return array_values(array_filter($items, function ($e) { return isset($e['evidence']) ? $e['evidence']['verifiedEnglish'] : $e['verifiedEnglish']; }));
	}

	/** @return array Resources of the given page types */
	public function resourcesOf($types) {
		return array_values(array_filter($this->resources, function ($r) use ($types) { return $types === null || in_array($r['pageType'], $types, true); }));
	}

	/**
	 * Pages of the given types that were found but could not be read as verified English (language not verified,
	 * other language, inaccessible, parse failure). A fact missing from the readable pages may be on these (F5).
	 * A page that does not exist (404/410) counts as read. An OJS galley viewer page (/article/view/X/Y) whose language
	 * is only undetermined is not counted: it frames the full-text file, which is a resource of its own and is read
	 * (and counted) separately; likewise an undetermined archive page that is only a list (no sentence), whose issues are
	 * read as resources of their own (decision D-03-8). A galley or archive page in another language, or an archive page
	 * with prose, still counts. With $neutralOk (rules that read only language-neutral facts, ADR-013), a neutral-eligible
	 * page counts as read.
	 * @return array resources
	 */
	public function unreadOf($types, $neutralOk = false) {
		$f = $this;
		return array_values(array_filter($this->resourcesOf($types), function ($r) use ($f, $neutralOk) {
			if ($r['acquisition'] === 'not_found') return false;
			if ($neutralOk && !empty($f->neutralResource[$r['id']])) return false; // language-neutral facts were read (ADR-013)
			// A list page whose language is only undetermined frames resources that are read on their own: the galley viewer
			// frames the full-text file; the archive lists the issues (each issue page is a resource of its own).
			if (in_array($r['pageType'], array('galley', 'archive'), true) && $r['acquisition'] === 'retrieved' && is_string($r['reason']) && strpos($r['reason'], 'language_undetermined') === 0
				&& ($r['pageType'] === 'galley' || !$f->hasProse($r['id']))) return false;
			return $r['acquisition'] !== 'retrieved' || $r['reason'] !== null;
		}));
	}

	/** @return boolean Whether the main text of a resource contains a sentence (five or more words ending with . ! or ?), not only list entries */
	public function hasProse($resourceId) {
		$e = $this->mainOf($resourceId);
		if (!$e) return false;
		foreach (DoajPaText::sentences($e['quote']) as $s) if (DoajPaText::words($s) >= 5 && preg_match('/[.!?]$/', $s)) return true;
		return false;
	}

	/** @return boolean Whether the crawl stopped at a page or time limit (absence findings are then unverifiable) */
	public function crawlTruncated() {
		$hit = isset($this->coverage['limitsHit']) ? $this->coverage['limitsHit'] : array();
		return in_array('maxPages', $hit, true) || in_array('maxSeconds', $hit, true);
	}

	/** @return array|null Main-content (or PDF) item of a resource, or null when the resource has none */
	public function mainOf($resourceId) {
		foreach ($this->evidence as $e) {
			if ($e['resourceId'] === $resourceId && in_array($e['method'], array('html_main_content', 'pdftotext_extraction'), true)) return $e;
		}
		return null;
	}

	/** @return array Structured lines of a resource: list of [line, item] */
	public function structured($types = null) {
		$out = array();
		foreach ($this->items($types, array('html_structured')) as $e) {
			foreach (explode("\n", $e['quote']) as $line) {
				if ($line !== '') $out[] = array('line' => $line, 'evidence' => $e);
			}
		}
		return $out;
	}

	//
	// Topics (curated English patterns)
	//

	const TOPICS = array(
		'oa_statement' => '/\bopen[- ]access\b[^.!?\n]{0,200}\b(free|freely|without (?:charge|cost|restriction|delay)|immediate(?:ly)?|read, download|any lawful purpose|BOAI|Budapest)\b|\b(free|freely|immediate(?:ly)?)\b[^.!?\n]{0,120}\bopen[- ]access\b/i',
		'oa_policy' => '/\bopen[- ]access\b/i',
		'aims_scope' => '/\b(aims? (?:and|&) scope|focus (?:and|&) scope|scope of the journal|the journal (?:publishes|covers|focuses on|welcomes|accepts)|journal scope|subject areas?)\b/i',
		'author_instructions' => '/\b(author(?:\'s|s\')? guidelines|guidelines for authors|instructions (?:for|to) authors|submission guidelines|manuscript (?:preparation|format|template|guidelines)|authors (?:should|must|are (?:required|requested|asked) to)|submission preparation checklist)\b/i',
		'peer_review' => '/\b(peer[- ]review\w*|double[- ](?:blind|anonymous)|single[- ](?:blind|anonymous)|triple[- ](?:blind|anonymous)|open review|referee\w*|reviewers?)\b/i',
		'review_types' => '/\b(double[- ](?:blind|anonymi[sz]ed|anonymous)|single[- ](?:blind|anonymi[sz]ed|anonymous)|triple[- ](?:blind|anonymous)|open peer review|post[- ]publication peer review|editorial review)\b/i',
		'two_reviewers' => '/\b(at least|minimum of|a minimum of|two|2)\b[^.!?\n]{0,30}\b(two|2|independent)\b[^.!?\n]{0,30}\b(reviewers?|referees?|experts?)\b|\b(two|2)\s+(?:independent\s+)?(?:reviewers?|referees?)\b/i',
		'two_editors' => '/\b(at least|minimum of|two|2)\b[^.!?\n]{0,30}\beditors?\b/i',
		'plagiarism' => '/\b(plagiarism|similarity (?:check|report|index|score)|iThenticate|Turnitin|Crossref Similarity Check|Plagiarism ?Checker|Copyscape|Grammarly)\b/i',
		'target_readership' => '/\b(readership|readers|intended for|aimed at|audience|researchers,? (?:academics|practitioners)|practitioners)\b/i',
		'manuscript_types' => '/\b(original (?:research|articles?|papers?)|research articles?|review articles?|short communications?|case (?:reports?|studies)|types? of (?:articles|manuscripts|submissions|papers)|article types?)\b/i',
		'authorship' => '/\b(authorship|contributorship|contributors?|CRediT|ICMJE|corresponding author)\b/i',
		'schedule' => '/\b(publication frequency|published (?:annually|biannually|semi-?annually|quarterly|monthly|bi-?monthly|continuously|twice a year|three times a year|four times a year)|(?:annual|biannual|quarterly|monthly|semi-?annual) (?:issues?|publication)|(?:once|twice|three times|four times|two times|\d+ times) (?:a|per) year|issues? (?:per|a) year|continuous publication)\b/i',
		'archiving' => '/\b(LOCKSS|CLOCKSS|PKP ?PN|PKP Preservation Network|Portico|Internet Archive|PubMed Central|Europe ?PMC|Keepers Registry|national library|digital preservation|long[- ]term (?:preservation|archiving)|CINES)\b/i',
		'repository' => '/\b(self[- ]archiv\w*|institutional repositor\w*|subject repositor\w*|repositor(?:y|ies)|preprints?|accepted manuscripts?|author\'s accepted manuscript|version of record|post-?prints?)\b/i',
		'embargo' => '/\bembargo\w*\b/i',
		'ai_policy' => '/\b(artificial intelligence|generative AI|AI[- ](?:tools?|generated|assisted)|large language models?|LLMs?|ChatGPT|automated tools?|AI tools?)\b/i',
		'ownership' => '/\b(owned by|ownership|published by|publisher(?:\s+is)?|managed by|sponsored by|under the auspices of)\b/i',
		'peer_review_who' => '/\b(external (?:reviewers?|experts?|referees?)|independent (?:reviewers?|experts?)|reviewed by|editorial board members? (?:review|evaluate)|experts? in the (?:field|subject))\b/i',
		'fees_independent' => '/\b(fees?|payments?|charges?|waivers?)\b[^.!?\n]{0,120}\b(no|not|never)\b[^.!?\n]{0,40}\b(influence|affect|bearing|impact)\w*\b[^.!?\n]{0,60}\b(editorial|decision|peer review|acceptance)\b|\beditorial decisions?\b[^.!?\n]{0,80}\bindependent\b[^.!?\n]{0,60}\b(fees?|payments?|charges?)\b/i',
		'revenue' => '/\b(funded by|funding|sponsored by|sponsorship|subsid\w+|revenue|business model|supported by|financial support|financed by)\b/i',
		'advertising' => '/\badvertis\w+\b/i',
		'acceptance_guarantee' => '/\b(guarantee[sd]?\s+(?:of\s+)?(?:acceptance|publication)|(?:acceptance|publication)\s+(?:is\s+)?guaranteed|100\s?% acceptance|assured (?:acceptance|publication))\b/i',
		'review_time_claim' => '/\b(?:peer[- ]review|review process|first decision|decision)\b[^.!?\n]{0,60}\b(?:within|takes?|of|in)\b[^.!?\n]{0,15}\b(\d{1,3})\s*(days?|weeks?|months?)\b/i',
		'print_version' => '/\b(print (?:version|edition|copies|issues?)|printed (?:version|copies|issues?)|hard ?cop(?:y|ies))\b/i',
		'access_method' => '/\b(subscription|subscribe|pay[- ]per[- ]view|register to read|registration (?:is )?required|login to (?:read|access)|members only)\b/i',
		'affiliated_org' => '/\b(affiliated with|official journal of|published on behalf of|journal of the [A-Z]\w+ (?:Society|Association)|in (?:collaboration|cooperation|partnership) with)\b/i',
		'non_oa_policy' => '/\b(non[- ]open[- ]access|not open access|free[- ]to[- ]read content|subscription content|poetry)\b/i',
		'consent_policy' => '/\b(consent (?:to|for) (?:the )?publi\w+|(?:patient|participant|written|informed) consent\b[^.!?\n]{0,80}\b(?:publication of|to publish|patients?|case (?:details|reports?)|images?)|consent (?:form|was obtained))\b/i',
		'anonymisation' => '/\b(anonymi[sz]\w+|de-?identif\w+|identifiable (?:information|details|participant))\b/i',
		'case_report_review' => '/\breviewer guidelines?\b[^.!?\n]{0,80}\bcase reports?\b|\bcase reports?\b[^.!?\n]{0,80}\breviewer guidelines?\b/i',
		'care_guideline' => '/\bCARE (?:guidelines?|checklist|reporting)\b/i',
		'phd_members' => '/\b(Ph\.?\s?D\.?|doctorate|doctoral degree|D\.Phil)\b/i',
	);

	/** @return array Matches of a topic in the given items (see DoajPaText::find); headings and menu labels are not statements (F2) */
	public function topic($name, $items, $skipNegated = true) {
		return self::statements(DoajPaText::find($items, self::TOPICS[$name], $skipNegated));
	}

	/** @return array The matches whose sentence is a statement, not a heading or menu label (DoajPaText::headingLike) */
	public static function statements($hits) {
		return array_values(array_filter($hits, function ($h) { return !DoajPaText::headingLike($h['sentence']); }));
	}

	//
	// Conditions for conditional (C) criteria: a journal type is "confirmed" only by an explicit public statement
	//

	const CONDITIONS = array(
		'special_issues' => '/\b(special issues?|guest editors?|thematic issues?|guest[- ]edited)\b/i',
		'case_reports' => '/\bcase reports?\b/i',
		'conference' => '/\b(conference proceedings|proceedings of the|proceedings journal)\b/i',
		'student_run' => '/\b(student[- ]run|run by students|student[- ]led|student journal|undergraduate (?:research )?journal|graduate student journal)\b/i',
		'flipped' => '/\b(flipped to open access|previously (?:published )?(?:as a )?(?:subscription|hybrid)|converted to (?:full )?open access|transition(?:ed)? to (?:full )?open access|became (?:fully )?open access)\b/i',
		'mirror' => '/\bmirror journal\b/i',
		's2o' => '/\b(subscribe to open|S2O)\b/',
		'arts_humanities' => '/\b(arts|humanities|history|philosophy|literature|literary|linguistics|musicology|music|theatre|theater|art history|religious studies|theology|cultural studies|classics)\b/i',
		'editorial_review' => '/\beditorial review\b/i',
		'continuous' => '/\b(continuous(?:ly)? (?:publication|publishing|published)|published continuously|rolling (?:basis|publication)|article[- ]by[- ]article)\b/i',
		'waiver' => '/\b(waiv(?:er|ers|ed|e)|fee (?:discounts?|reductions?)|discount(?:s|ed)? on (?:the )?(?:APC|publication fee|charges))\b/i',
		'withdrawal_fee' => '/\bwithdraw(?:al|ing)?\b[^.!?\n]{0,60}\b(fee|charge|penalty|cost)\b/i',
		'republished' => '/\b(republish\w*|reprinted (?:from|with permission)|translated (?:from|article)|previously published (?:in|elsewhere))\b/i',
	);

	/** @return array Matches confirming a condition (main content and PDF text of all pages) */
	public function condition($name) {
		$memoKey = 'condition' . '|' . json_encode($name);
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		return $this->memo[$memoKey] = self::statements(DoajPaText::find($this->items(null), self::CONDITIONS[$name], true));
	}

	//
	// ISSN
	//

	/** @return array ISSN occurrences: issn, valid, label (print|online|unlabelled), evidence, sentence, quote */
	public function issns() {
		$memoKey = 'issns';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		$items = array_merge($this->items(null, array('html_main_content', 'html_text_extraction')), array());
		foreach ($items as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				if (!preg_match_all('/\b(\d{4}-\d{3}[\dXx])\b/', $s, $mm, PREG_OFFSET_CAPTURE)) continue;
				foreach ($mm[1] as $m) {
					$before = DoajPaText::fold(substr($s, max(0, $m[1] - 60), min(60, $m[1])));
					if (!preg_match('/\b(issn|e-?issn|p-?issn|online|print|electronic)\b/', $before . ' ' . DoajPaText::fold(substr($s, $m[1], 40)))) continue;
					$label = preg_match('/(e-?issn|online|electronic)[^0-9]{0,20}$/', $before) ? 'online'
						: (preg_match('/(p-?issn|print(?:ed)?)[^0-9]{0,20}$/', $before) ? 'print' : 'unlabelled');
					$out[] = array('issn' => strtoupper($m[0]), 'valid' => DoajPaText::issnValid(strtoupper($m[0])), 'label' => $label,
						'evidence' => $e, 'sentence' => $s, 'quote' => DoajPaText::snippet($s, $m[1]));
				}
			}
		}
		foreach ($this->structured(null) as $l) {
			if (preg_match('/^meta citation_issn: (\d{4}-\d{3}[\dXx])$/i', $l['line'], $m)) {
				$out[] = array('issn' => strtoupper($m[1]), 'valid' => DoajPaText::issnValid(strtoupper($m[1])), 'label' => 'unlabelled',
					'evidence' => $l['evidence'], 'sentence' => $l['line'], 'quote' => $l['line']);
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	//
	// Licences and copyright
	//

	/** @return string Display name of a licence code ("CC" = Creative Commons, variant not stated) */
	public static function licenceLabel($code, $version = null) {
		$names = array('CC' => 'Creative Commons (variant not stated)', 'other' => 'publisher\'s own licence', 'PDM' => 'Public Domain Mark');
		return (isset($names[$code]) ? $names[$code] : $code) . ($version ? ' ' . $version : '');
	}

	/**
	 * @return array Licence occurrences: code ("CC BY", "CC BY-NC-SA", ..., "CC0", "PDM"; "CC" when a Creative Commons
	 *  licence is named without its variant; "other" for a publisher's own licence), version, kind (url|name), evidence, quote
	 */
	public function licences() {
		$memoKey = 'licences';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		$items = array_merge($this->items(null, array('html_main_content', 'pdftotext_extraction')), $this->items(null, array('html_structured')));
		foreach ($items as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				if (preg_match_all('#creativecommons\.org/(?:licenses/(by(?:-nc)?(?:-sa|-nd)?)/(\d\.\d)|publicdomain/(zero|mark)/(\d\.\d))#i', $s, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
					foreach ($mm as $m) {
						$code = isset($m[3]) && $m[3][0] !== '' ? ($m[3][0] === 'zero' ? 'CC0' : 'PDM') : 'CC ' . strtoupper($m[1][0]);
						$out[] = array('code' => $code, 'version' => isset($m[4]) && $m[4][0] !== '' ? $m[4][0] : (isset($m[2]) ? $m[2][0] : null),
							'kind' => 'url', 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'link' => strpos($s, 'licence-link:') === 0);
					}
					continue;
				}
				// Groups: [1] "Attribution"/"BY", [2] NC, [3] SA or ND, [4] version.
				if (preg_match('/\bCreative Commons\s+(Attribution)?((?:[- ]?(?:Non-?Commercial|NonCommercial))?)((?:[- ]?(?:Share-?Alike|ShareAlike|No-?Deriv(?:atives|s)?))?)(?:\s+(\d\.\d))?(?:\s+International)?(?:\s+(?:License|Licence))?/i', $s, $m, PREG_OFFSET_CAPTURE)
					|| preg_match('/\bCC[- ]?(BY)((?:[- ]NC)?)((?:[- ](?:SA|ND))?)(?:\s+(\d\.\d))?\b/i', $s, $m, PREG_OFFSET_CAPTURE)) {
					$nc = isset($m[2]) && trim($m[2][0]) !== '' ? '-NC' : '';
					$x = isset($m[3]) ? strtoupper(trim($m[3][0], ' -')) : '';
					$sx = $x === '' ? '' : (strpos($x, 'S') === 0 ? '-SA' : '-ND');
					$version = isset($m[4]) && $m[4][0] !== '' ? $m[4][0] : null;
					// "a Creative Commons licence" without Attribution, a variant or a version does not say which licence it is.
					$code = ((!isset($m[1]) || $m[1][0] === '') && $nc === '' && $sx === '' && $version === null) ? 'CC' : 'CC BY' . $nc . $sx;
					$out[] = array('code' => $code, 'version' => $version, 'kind' => 'name',
						'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'link' => false);
				} elseif (preg_match('/\b(CC0|public domain dedication)\b/i', $s, $m, PREG_OFFSET_CAPTURE)) {
					$out[] = array('code' => 'CC0', 'version' => null, 'kind' => 'name', 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'link' => false);
				} elseif (preg_match('/\b(?:licensed under|is licensed|published under (?:the|a)|distributed under (?:the|a))\b[^.!?\n]{3,120}\b(?:licen[cs]e)\b/i', $s, $m, PREG_OFFSET_CAPTURE)
					&& !preg_match('/creative commons|\bCC\b/i', $s)) {
					$out[] = array('code' => 'other', 'version' => null, 'kind' => 'name', 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'link' => false);
				}
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	const COPYRIGHT = array(
		'retain' => '/\bauthors?\b[^.!?\n]{0,30}\b(?:retain|keep|hold|own|retains|holds|keeps|owns)\b[^.!?\n]{0,20}\bcopyright\b|\bcopyright\b[^.!?\n]{0,40}\b(?:remains?|stays?|is retained|retained)\b[^.!?\n]{0,30}\bauthors?\b/i',
		'transfer' => '/\b(?:transfer|assign)(?:s|red|ed|ing)?\b[^.!?\n]{0,30}\bcopyright\b|\bcopyright\b[^.!?\n]{0,30}\b(?:is|will be|shall be|are)\s+(?:transferred|assigned)\b|\bcopyright transfer\b/i',
		'exclusive' => '/\bexclusive\s+(?:publishing\s+|commercial\s+|right\s+of\s+first\s+publication\s+)?(?:rights?|licen[cs]e)\b/i',
		'arr' => '/\ball\s+rights\s+reserved\b/i',
		'fair_use' => '/\bfair[- ]use\b/i',
		'shared' => '/\bcopyright\b[^.!?\n]{0,30}\b(?:shared|jointly held|held jointly|co-owned)\b/i',
		'statement' => '/\bcopyright\b|\x{00A9}|\(c\)/iu',
	);

	/**
	 * Copyright statements: kind (see COPYRIGHT), evidence, quote, chrome (true when the sentence is only in the page
	 * header/footer, i.e. present in the whole-page text but not in the main content region).
	 * @return array
	 */
	public function copyright() {
		$memoKey = 'copyright';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		$main = array();
		foreach ($this->evidence as $e) {
			if (in_array($e['method'], array('html_main_content', 'pdftotext_extraction'), true)) $main[$e['resourceId']] = $e['quote'];
		}
		$items = array_merge($this->items(null, array('html_main_content', 'pdftotext_extraction', 'html_text_extraction')), $this->items(null, array('html_structured')));
		foreach ($items as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				$heading = DoajPaText::headingLike($s) && !preg_match('/(?:\x{00A9}|\(c\)|copyright)\s*\d{4}/iu', $s);
				foreach (self::COPYRIGHT as $kind => $rx) {
					if (!preg_match($rx, $s, $m, PREG_OFFSET_CAPTURE)) continue;
					if ($heading && $kind !== 'arr') continue; // "Copyright Notice" is a heading, not copyright terms
					if ($kind !== 'arr' && $kind !== 'statement' && (DoajPaText::negatedAt($s, $m[0][1]) || DoajPaText::negatedWithin($m[0][0]))) continue;
					$chrome = $e['method'] === 'html_text_extraction' && (!isset($main[$e['resourceId']]) || strpos($main[$e['resourceId']], $s) === false);
					if ($e['method'] === 'html_text_extraction' && !$chrome) continue; // already found in the main region
					$out[] = array('kind' => $kind, 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'sentence' => $s, 'chrome' => $chrome);
				}
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	//
	// Fees
	//

	const FEE_TYPES = array(
		'apc' => '/\b(article processing (?:charges?|fees?)|APCs?|publication (?:fees?|charges?)|publishing (?:fees?|charges?)|author processing (?:charges?|fees?))\b/i',
		'submission' => '/\bsubmission (?:fees?|charges?)\b/i',
		'editorial' => '/\beditorial processing (?:fees?|charges?)\b/i',
		'page' => '/\bpage (?:charges?|fees?)\b/i',
		'colour' => '/\bcolou?r (?:charges?|fees?)\b/i',
		'language' => '/\blanguage (?:editing|polishing) (?:fees?|charges?)\b/i',
		'membership' => '/\bmembership (?:fees?|charges?)\b/i',
		'withdrawal' => '/\bwithdraw(?:al|ing)?\b[^.!?\n]{0,40}\b(?:fees?|charges?|penalty)\b/i',
	);
	const NO_FEE = '/\b(?:no|without|free of)\s+(?:any\s+)?(?:article processing charges?|APCs?|publication (?:fees?|charges?)|submission (?:fees?|charges?)|charges?|fees?|author(?:\'s)? (?:fees?|charges?)|costs? (?:to|for) (?:the )?authors?)\b|\bdoes not charge\b|\b(?:do|will) not charge\b|\bnot charge (?:any|authors)\b|\bfree of charge\b|\bno (?:cost|charge) to (?:the )?authors?\b|\b(?:fees?|charges?|APCs?)\b[^.!?\n]{0,30}\b(?:is|are)\s+(?:free|waived for all|zero|0)\b/i';
	const AMOUNT = '/(?:USD|US\$|\$|EUR|\x{20AC}|GBP|\x{00A3}|IDR|Rp\.?|INR|\x{20B9}|MYR|RM|NGN|\x{20A6}|CAD|AUD|CHF|JPY|\x{00A5}|CNY|TRY|BRL|R\$)\s?\d[\d.,]*|\d[\d.,]*\s?(?:USD|EUR|GBP|IDR|INR|MYR|NGN|CAD|AUD|CHF|TRY|BRL|dollars|euros|rupiah)\b/iu';

	/**
	 * Fee statements in main content of policy pages: noFee[], fees[] (type, amount|null), waivers[].
	 * @return array
	 */
	public function fees() {
		$memoKey = 'fees';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array('noFee' => array(), 'fees' => array(), 'waivers' => array(), 'items' => array());
		$items = $this->items(array('home', 'about', 'custom', 'information', 'author_guidelines', 'about_other'), array('html_main_content'));
		$out['items'] = $items;
		foreach ($items as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				if (DoajPaText::headingLike($s)) continue; // "Article Processing Charges" as a heading states nothing
				if (preg_match(self::NO_FEE, $s, $m, PREG_OFFSET_CAPTURE)) {
					// Scope: the fee types the statement names ("no submission fee"), or all charges ("does not charge any fees").
					$scope = array();
					foreach (self::FEE_TYPES as $type => $rx) if (preg_match($rx, $m[0][0])) $scope[] = $type;
					$out['noFee'][] = array('evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'sentence' => $s, 'scope' => $scope ?: array('all'));
					continue;
				}
				foreach (self::FEE_TYPES as $type => $rx) {
					if (!preg_match($rx, $s, $m, PREG_OFFSET_CAPTURE)) continue;
					if (DoajPaText::negatedAt($s, $m[0][1])) continue;
					$amount = preg_match(self::AMOUNT, $s, $a) ? trim($a[0]) : null;
					$out['fees'][] = array('type' => $type, 'amount' => $amount, 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'sentence' => $s);
				}
				if (preg_match(self::CONDITIONS['waiver'], $s, $m, PREG_OFFSET_CAPTURE) && !DoajPaText::negatedAt($s, $m[0][1])) {
					$out['waivers'][] = array('evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'sentence' => $s);
				}
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	//
	// Editorial board
	//

	/**
	 * People listed on editorial team / reviewer pages (main content), parsed from lines. People without a recognisable
	 * affiliation are reported; uncertain parsing lowers confidence instead of guessing.
	 * @return array people[] (name, affiliation|null, role, evidence, quote), pages[], unparsed (int), lines (int)
	 */
	public function board($reviewers = false) {
		$memoKey = 'board' . '|' . json_encode($reviewers);
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$pages = array();
		foreach ($this->items(array('editorial_team', 'custom', 'about_other'), array('html_main_content')) as $e) {
			$isReviewerPage = preg_match('/reviewer/i', (string) $e['title'] . ' ' . $e['url']) === 1;
			if ($e['pageType'] === 'editorial_team' ? !$reviewers || $isReviewerPage : ($reviewers ? $isReviewerPage : preg_match('/editor|board/i', (string) $e['title'] . ' ' . $e['url']) === 1)) $pages[] = $e;
		}
		$people = array();
		$unparsed = 0; $lines = 0;
		foreach ($pages as $e) {
			$role = null;
			$raw = array_values(array_filter(array_map(function ($l) { return trim(preg_replace(self::LINK_LABELS, '', $l)); }, explode("\n", $e['quote'])), function ($l) { return $l !== ''; }));
			$n = count($raw);
			// The list runs from the first to the last "clear" entry of the page (a name with a title such as Dr or Prof,
			// or with an affiliation). Unrecognised lines count only inside that range, and name-like lines outside it
			// (page headings before the list, sidebar blocks after it) are ignored (1.2.0.0).
			$pageUnparsed = array(); $entries = array(); $firstStrong = null; $lastStrong = null; $lastEntry = null;
			for ($i = 0; $i < $n; $i++) {
				$l = $raw[$i];
				if ($i === 0 && preg_match('/^(editorial team|editorial boards?|reviewers?|peer reviewers?|editors?|boards?)$/i', $l)) continue;
				if (in_array(mb_strtolower(trim($l, " .,;"), 'UTF-8'), self::COUNTRIES, true)) {
					// a country line under an affiliation belongs to the previous person
					if ($entries && $lastEntry !== null && $lastEntry >= $i - 3) {
						$k = count($entries) - 1;
						$entries[$k]['person']['affiliation'] = $entries[$k]['person']['affiliation'] === null ? trim($l) : $entries[$k]['person']['affiliation'] . ', ' . trim($l);
					}
					continue;
				}
				if (preg_match(self::ROLE, $l, $rm)) {
					$rest = trim(substr($l, strlen($rm[0])), " :-\t");
					$role = trim($rm[1]);
					if ($rest === '') continue;
					$l = $rest;
				}
				if (DoajPaText::words($l) > 30) continue; // prose, not a list entry
				$person = self::parsePerson($l);
				if ($person === null) {
					if (!preg_match(self::INSTITUTION, $l)) $pageUnparsed[] = $i;
					continue;
				}
				$start = $i;
				if ($person['affiliation'] === null && $i + 1 < $n && preg_match(self::INSTITUTION, $raw[$i + 1]) && self::parsePerson($raw[$i + 1]) === null) {
					$person['affiliation'] = $raw[$i + 1];
					$i++;
				}
				$strong = $person['affiliation'] !== null || preg_match('/^(?:Dr|Prof|Professor|Assoc|Assist|Mr|Mrs|Ms|Ir|Drs|Hon)\.?\s/u', $person['name']) === 1;
				if ($strong) { if ($firstStrong === null) $firstStrong = $start; $lastStrong = $i; }
				$entries[] = array('person' => $person, 'role' => $role, 'quote' => DoajPaText::snippet($l, 0), 'start' => $start, 'end' => $i);
				$lastEntry = $i;
			}
			if ($firstStrong !== null) {
				// widen the range to weak entries that continue the list directly (at most one unrecognised line between)
				$gap = function ($a, $b) use ($pageUnparsed) { $c = 0; foreach ($pageUnparsed as $u) if ($u > $a && $u < $b) $c++; return $c; };
				foreach ($entries as $en) if ($en['start'] > $lastStrong && $gap($lastStrong, $en['start']) < 2) $lastStrong = $en['end'];
				foreach (array_reverse($entries) as $en) if ($en['end'] < $firstStrong && $gap($en['end'], $firstStrong) < 2) $firstStrong = $en['start'];
			}
			foreach ($entries as $en) {
				if ($firstStrong !== null && ($en['start'] < $firstStrong || $en['end'] > $lastStrong)) continue;
				$lines++;
				$people[] = array('name' => $en['person']['name'], 'affiliation' => $en['person']['affiliation'], 'role' => $en['role'], 'evidence' => $e, 'quote' => $en['quote']);
			}
			foreach ($pageUnparsed as $u) if ($firstStrong === null || ($u > $firstStrong && $u < $lastStrong)) { $unparsed++; $lines++; }
		}
		return $this->memo[$memoKey] = array('people' => $people, 'pages' => $pages, 'unparsed' => $unparsed, 'lines' => $lines);
	}

	/** @return array|null ['name', 'affiliation'|null] for "Name, Affiliation", "Name (Affiliation)", "Name - Affiliation" or "Name" */
	public static function parsePerson($line) {
		$line = trim(preg_replace('/\s+/u', ' ', $line), " \t;.");
		$name = $line; $aff = null;
		if (preg_match('/^(.{3,80}?)\s*(?:,|\(|\x{2013}|\x{2014}| - | \| )\s*(.{2,200}?)\)?$/u', $line, $m)) {
			$name = trim($m[1]); $aff = trim($m[2], " )");
		}
		$tokens = preg_split('/\s+/', $name);
		if (count($tokens) < 2 || count($tokens) > 7 || preg_match('/\d/', $name) || preg_match(self::INSTITUTION, $name)) return null;
		if (preg_match(self::NOT_A_NAME, $name)) return null; // page and sidebar headings such as "Journal Information" (1.2.0.0)
		// a personal name has no colon and no lower-case words other than name particles (van, de, bin, ...)
		if (strpos($name, ':') !== false) return null;
		foreach ($tokens as $tk) if (preg_match('/^\p{Ll}/u', $tk) && !preg_match('/^(?:van|von|de|der|den|del|della|di|da|dos|das|du|la|le|bin|binti|ibn|al|el|ten|ter|y|e|zu|af)$/u', $tk)) return null;
		if (!preg_match('/^(?:(?:Dr|Prof|Professor|Assoc|Assist|Mr|Mrs|Ms|Ir|Drs|Hon)\.?\s+)*[\p{Lu}]/u', $name)) return null;
		$caps = 0;
		foreach ($tokens as $t) if (preg_match('/^[\p{Lu}]/u', $t)) $caps++;
		if ($caps < 2) return null;
		if ($aff !== null && !preg_match(self::INSTITUTION, $aff) && !preg_match('/[\p{Lu}]/u', $aff)) $aff = null;
		return array('name' => $name, 'affiliation' => $aff);
	}

	//
	// Articles, issues and dates
	//

	/** @return array Articles: url, evidence (main), meta (name => values), authors[], type, published (Y-m-d|null) */
	public function articles() {
		$memoKey = 'articles';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach ($this->resourcesOf(array('article')) as $r) {
			if ($r['acquisition'] !== 'retrieved' && $r['acquisition'] !== 'ambiguous' && $r['acquisition'] !== 'unsupported_language') continue;
			$meta = array(); $metaItem = null;
			foreach ($this->evidence as $e) {
				if ($e['resourceId'] !== $r['id'] || $e['method'] !== 'html_structured') continue;
				$metaItem = $e;
				foreach (explode("\n", $e['quote']) as $line) {
					if (preg_match('/^meta ([^:]+): (.*)$/', $line, $m)) $meta[$m[1]][] = $m[2];
				}
			}
			$date = isset($meta['citation_date'][0]) ? DoajPaText::parseDate($meta['citation_date'][0]) : (isset($meta['DC.Date.issued'][0]) ? DoajPaText::parseDate($meta['DC.Date.issued'][0]) : array('date' => null));
			$out[] = array('url' => $r['url'], 'resource' => $r, 'main' => $this->mainOf($r['id']), 'metaItem' => $metaItem, 'meta' => $meta,
				'authors' => isset($meta['citation_author']) ? $meta['citation_author'] : array(),
				'type' => isset($meta['DC.Type.articleType'][0]) ? $meta['DC.Type.articleType'][0] : null, 'published' => $date['date']);
		}
		return $this->memo[$memoKey] = $out;
	}

	const RESEARCH_SECTION = '/^(articles?|research articles?|original (?:research )?articles?|original research|research papers?|full papers?|regular articles?|research)$/i';
	const NON_RESEARCH_SECTION = '/\b(editorials?|book reviews?|reviews? of books|letters?|errat\w+|corrigend\w+|retractions?|obituar\w+|news|announcements?|interviews?|commentar\w+|front matter|back matter|table of contents|index)\b/i';

	/** @return string research|non_research|uncertain for a section or article type label */
	public static function articleClass($label) {
		$label = trim((string) $label);
		if ($label === '') return 'uncertain';
		if (preg_match(self::RESEARCH_SECTION, $label)) return 'research';
		if (preg_match(self::NON_RESEARCH_SECTION, $label)) return 'non_research';
		return 'uncertain';
	}

	/** @return array Issues, newest first: url, title, published, toc[] (section, title, authors[], url), evidence (main), structured */
	public function issues() {
		$memoKey = 'issues';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach ($this->resourcesOf(array('issue')) as $r) {
			if ($r['acquisition'] !== 'retrieved' && $r['acquisition'] !== 'ambiguous') continue;
			$main = $this->mainOf($r['id']);
			$toc = array(); $sItem = null;
			foreach ($this->evidence as $e) {
				if ($e['resourceId'] !== $r['id'] || $e['method'] !== 'html_structured') continue;
				$sItem = $e;
				foreach (explode("\n", $e['quote']) as $line) {
					if (strpos($line, 'toc | ') !== 0) continue;
					$parts = array_map('trim', explode(' | ', substr($line, 6)));
					if (count($parts) < 4) continue;
					$authors = $parts[2] === '' ? array() : array_values(array_filter(array_map('trim', preg_split('/\s*(?:,|;|\band\b|&)\s*/u', $parts[2]))));
					$toc[] = array('section' => $parts[0], 'title' => $parts[1], 'authors' => $authors, 'url' => $parts[3], 'line' => $line);
				}
			}
			$published = null;
			if ($main && preg_match('/Published:?\s*\n?\s*(\d{4}-\d{2}-\d{2})/', $main['quote'], $m)) $published = $m[1];
			$out[] = array('url' => $r['url'], 'resource' => $r, 'title' => $main ? strtok($main['quote'], "\n") : '', 'published' => $published,
				'toc' => $toc, 'evidence' => $main, 'structured' => $sItem, 'id' => preg_match('#/issue/view/(\d+)#', $r['url'], $im) ? (int) $im[1] : 0);
		}
		usort($out, function ($a, $b) {
			if ($a['published'] !== $b['published']) return strcmp((string) $b['published'], (string) $a['published']);
			return $b['id'] - $a['id'];
		});
		return $this->memo[$memoKey] = $out;
	}

	const DATE_LABEL = '/\b(received|submitted|revised|accepted|published(?: online)?|available online|date of publication)\b[ \t]*(?:on|date)?[ \t]*:?\s{0,4}([0-9]{1,4}[^\n;|]{4,30}|(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]{0,6}\.?[ \t]+\d{1,2}[^\n;|]{3,20})/i';

	/**
	 * Publication history dates per sampled article (visible landing page text and PDF text), with the exact Stage 01 F4
	 * messages: complete | "No publication history date found" | "Incomplete publication history date" |
	 * "Publication history date requires verification".
	 * @return array per article: url, dates (label => Y-m-d), status, message, evidence, quotes[]
	 */
	public function articleDates() {
		$memoKey = 'articleDates';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach ($this->articles() as $a) {
			$sources = array();
			if ($a['main']) $sources[] = $a['main'];
			foreach ($this->evidence as $e) {
				if ($e['method'] === 'pdftotext_extraction' && $a['meta'] && isset($a['meta']['citation_pdf_url'])) {
					foreach ($a['meta']['citation_pdf_url'] as $pdfUrl) {
						$pv = preg_replace('#/article/download/#', '/article/view/', $pdfUrl);
						if ($e['url'] === $pdfUrl || $e['url'] === $pv || strpos($e['url'], $pdfUrl) === 0) $sources[] = $e;
					}
				}
			}
			$dates = array(); $quotes = array(); $bad = false;
			foreach ($sources as $e) {
				// Whole text: OJS shows "Published" and the date on separate lines. The quote is the verbatim match.
				if (preg_match_all(self::DATE_LABEL, $e['quote'], $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
					foreach ($mm as $m) {
						$label = strtolower($m[1][0]);
						$key = strpos($label, 'accept') === 0 ? 'accepted' : ((strpos($label, 'received') === 0 || strpos($label, 'submitted') === 0) ? 'submitted'
							: (strpos($label, 'revised') === 0 ? 'revised' : 'published'));
						$p = DoajPaText::parseDate($m[2][0]);
						if ($p['status'] !== 'ok') { $bad = true; continue; }
						if (isset($dates[$key]) && $dates[$key] !== $p['date']) $bad = true;
						$dates[$key] = $p['date'];
						$quotes[] = array('evidence' => $e, 'quote' => mb_substr($m[0][0], 0, DoajPaText::MAX_QUOTE, 'UTF-8'));
					}
				}
			}
			if (isset($dates['submitted'], $dates['accepted']) && $dates['accepted'] < $dates['submitted']) $bad = true;
			if (isset($dates['accepted'], $dates['published']) && $dates['published'] < $dates['accepted']) $bad = true;
			if ($bad) { $status = 'verify'; $message = 'Publication history date requires verification'; }
			elseif (!isset($dates['submitted']) && !isset($dates['accepted'])) { $status = 'none'; $message = 'No publication history date found'; }
			elseif (!isset($dates['submitted']) || !isset($dates['accepted'])) { $status = 'incomplete'; $message = 'Incomplete publication history date'; }
			else { $status = 'complete'; $message = 'Submitted, accepted and published dates found'; }
			$out[] = array('url' => $a['url'], 'dates' => $dates, 'status' => $status, 'message' => $message, 'quotes' => $quotes,
				'readable' => count($sources) > 0, 'main' => $a['main']);
		}
		return $this->memo[$memoKey] = $out;
	}

	//
	// Metrics and indexing claims (Stage 01 F4: kept as separate categories)
	//

	const ALLOWED_METRICS = '/\b(Clarivate|Web of Science|Journal Impact Factor \(Clarivate|Journal Citation Reports|JCR|Scopus|CiteScore|SCImago(?:JR)?|SJR|Qualis|Norwegian Register|Eigenfactor|Google Scholar|h5-?index|Altmetric|Crossref Event Data)\b/i';
	const METRIC = '/\b(impact factor|journal impact factor|\bJIF\b|\bSJIF\b|index copernicus value|\bICV\b|citescore|\bSJR\b|scimago|h5-?index|h-index|eigenfactor|altmetric|ranking|ranked|cosmos impact factor|global impact factor|universal impact factor|scientific journal impact factor|journal factor|impact index)\b/i';
	const INDEXING = '/\b(indexed (?:in|by)|abstracted (?:and indexed )?(?:in|by)|indexing\b|listed (?:in|on)|covered (?:in|by)|included in)\b/i';

	/** @return array metric claims: text, service (named), allowed (bool|null = service not named), evidence, quote */
	public function metricClaims() {
		$memoKey = 'metricClaims';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		$items = array_merge($this->items(null, array('html_main_content', 'html_text_extraction')), $this->items(null, array('html_structured')));
		$seen = array();
		foreach ($items as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				if (strpos($s, 'meta ') === 0 || strpos($s, 'toc | ') === 0) continue;
				if (!preg_match(self::METRIC, $s, $m, PREG_OFFSET_CAPTURE)) continue;
				$key = $e['resourceId'] . '|' . DoajPaText::fold($s);
				if (isset($seen[$key])) continue;
				$seen[$key] = true;
				$name = strtolower($m[1][0]);
				$disallowedNamed = preg_match('/\b(sjif|scientific journal impact factor|index copernicus|icv|cosmos impact factor|global impact factor|universal impact factor|journal factor|impact index)\b/i', $s) === 1;
				$allowed = preg_match(self::ALLOWED_METRICS, $s) === 1 && !$disallowedNamed;
				$generic = !$allowed && !$disallowedNamed;
				$out[] = array('text' => $s, 'metric' => $name, 'allowed' => $allowed, 'disallowed' => $disallowedNamed, 'unnamed' => $generic,
					'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]));
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	/** @return array indexing claims (inventory only; never classified as unreliable without an approved basis, D-03-5) */
	public function indexingClaims() {
		$memoKey = 'indexingClaims';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach (array_merge($this->items(null, array('html_main_content', 'html_text_extraction')), $this->items(null, array('html_structured'))) as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				if (preg_match(self::INDEXING, $s, $m, PREG_OFFSET_CAPTURE) && !DoajPaText::negatedAt($s, $m[0][1])) {
					$out[] = array('text' => $s, 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]));
				}
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	//
	// Publisher, contact, homepage, languages, identifiers
	//

	/** @return array publisher statements: name, evidence, quote */
	public function publisher() {
		$memoKey = 'publisher';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach ($this->items(array('home', 'about', 'custom', 'information', 'contact', 'about_other'), array('html_main_content', 'html_text_extraction')) as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				if (preg_match('/\b(?i:publisher|published by|publishing house|publishing institution)\s*:?\s*(?:is\s+)?(?:the\s+)?([\p{Lu}][^\n.;|]{2,120})/u', $s, $m, PREG_OFFSET_CAPTURE)) {
					$out[] = array('name' => trim($m[1][0]), 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'sentence' => $s);
				}
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	const ADDRESS = '/\b(street|st\.|road|rd\.|avenue|ave\.|boulevard|blvd|jalan|jl\.|building|floor|campus|kampus|p\.?\s?o\.?\s?box|postal code|zip|postcode|kode pos)\b|\b\d{5}\b/i';

	/** @return array addresses (sentences with address markers): evidence, quote */
	public function addresses($types = array('about', 'contact', 'custom', 'information', 'home', 'about_other')) {
		$memoKey = 'addresses' . '|' . json_encode($types);
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach ($this->items($types, array('html_main_content', 'html_text_extraction')) as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				if (preg_match(self::ADDRESS, $s, $m, PREG_OFFSET_CAPTURE) && DoajPaText::words($s) <= 40) {
					$out[] = array('evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'sentence' => $s);
				}
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	/** @return array emails: email, evidence, quote */
	public function emails($types = array('contact', 'about', 'custom', 'about_other')) {
		$memoKey = 'emails' . '|' . json_encode($types);
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach ($this->items($types, array('html_main_content')) as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				if (preg_match('/[A-Za-z0-9._%+-]{1,64}@[A-Za-z0-9.-]{1,190}\.[A-Za-z]{2,24}/', $s, $m, PREG_OFFSET_CAPTURE)) {
					$out[] = array('email' => $m[0][0], 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]), 'sentence' => $s);
				}
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	/** @return array the homepage resource and its main item (or nulls) */
	public function homepage() {
		foreach ($this->resourcesOf(array('home')) as $r) {
			if ($r['acquisition'] === 'retrieved' || $r['acquisition'] === 'ambiguous') return array('resource' => $r, 'main' => $this->mainOf($r['id']));
		}
		return array('resource' => null, 'main' => null);
	}

	/** @return array site titles found: title, evidence, quote */
	public function siteTitles() {
		$memoKey = 'siteTitles';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach ($this->structured(null) as $l) {
			if (preg_match('/^(site-name|meta citation_journal_title): (.+)$/', $l['line'], $m)) $out[] = array('title' => $m[2], 'evidence' => $l['evidence'], 'quote' => $l['line']);
		}
		return $this->memo[$memoKey] = $out;
	}

	/** @return array persistent identifiers found on article pages: type (DOI|ARK|Handle), value, evidence, quote */
	public function identifiers() {
		$memoKey = 'identifiers';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach ($this->items(array('article'), array('html_structured', 'html_main_content')) as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				if (preg_match('#(?:meta citation_doi: |doi\.org/|\bDOI:?\s*)(10\.\d{4,9}/[^\s"<>|]{1,200})#i', $s, $m, PREG_OFFSET_CAPTURE)) {
					$out[] = array('type' => 'DOI', 'value' => $m[1][0], 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]));
				} elseif (preg_match('#\bark:/\d{5}/\S{1,100}#i', $s, $m, PREG_OFFSET_CAPTURE)) {
					$out[] = array('type' => 'ARK', 'value' => $m[0][0], 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]));
				} elseif (preg_match('#hdl\.handle\.net/\S{3,100}#i', $s, $m, PREG_OFFSET_CAPTURE)) {
					$out[] = array('type' => 'Handle', 'value' => $m[0][0], 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]));
				}
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	const LANGUAGE_NAMES = '/\b(English|Indonesian|Bahasa Indonesia|Malay|Spanish|Portuguese|French|German|Italian|Arabic|Chinese|Japanese|Korean|Russian|Turkish|Dutch|Persian|Urdu|Hindi|Polish|Czech|Ukrainian|Romanian|Greek|Swedish|Norwegian|Danish|Finnish|Hungarian|Croatian|Serbian|Slovenian|Slovak|Bulgarian|Lithuanian|Latvian|Estonian|Thai|Vietnamese|Filipino|Swahili)\b/';

	/** @return array statements of accepted manuscript languages: languages[], evidence, quote */
	public function manuscriptLanguages() {
		$memoKey = 'manuscriptLanguages';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach ($this->items(array('author_guidelines', 'about', 'custom', 'information'), array('html_main_content')) as $e) {
			foreach (DoajPaText::sentences($e['quote']) as $s) {
				if (!preg_match('/\b(manuscripts?|articles?|papers?|submissions?)\b[^.!?\n]{0,60}\b(written|submitted|accepted|published|prepared)\b[^.!?\n]{0,20}\bin\b/i', $s, $m, PREG_OFFSET_CAPTURE)) continue;
				if (!preg_match_all(self::LANGUAGE_NAMES, $s, $ln)) continue;
				$out[] = array('languages' => array_values(array_unique($ln[1])), 'evidence' => $e, 'quote' => DoajPaText::snippet($s, $m[0][1]));
			}
		}
		return $this->memo[$memoKey] = $out;
	}

	/** @return array language-switch links (multilingual site indicator) */
	public function localeSwitches() {
		$memoKey = 'localeSwitches';
		if (array_key_exists($memoKey, $this->memo)) return $this->memo[$memoKey];
		$out = array();
		foreach ($this->structured(null) as $l) {
			if (strpos($l['line'], 'locale-switch: ') === 0) $out[] = array('line' => $l['line'], 'evidence' => $l['evidence']);
		}
		return $this->memo[$memoKey] = $out;
	}
}
