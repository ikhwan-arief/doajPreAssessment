<?php

/**
 * @file classes/rules/DoajPaChecks.inc.php
 *
 * @class DoajPaChecks
 * @brief Deterministic check handlers for the Stage 03 candidate criteria. Each handler returns an outcome for
 *  DoajPaOutcomeGuard (ADR-010), which only ever downgrades it. Principles (Stage 01 F2, F5, F6; ADR-011):
 *   - a pass quotes verified-English public text (or, for absence/observation checks, cites the verified pages searched);
 *   - a missing fact is reported only when the relevant pages were read as verified English and the crawl was not cut
 *     short; otherwise the result is unverifiable (an unreadable page is never a missing fact);
 *   - a recommendation (R) never fails: missing R items give "warning"; conditional (C) items apply only when the
 *     condition is confirmed by public text, otherwise not_applicable;
 *   - human judgement (H) is for_doaj_review with the extracted text, never pass or fail;
 *   - external registries are not consulted (decision D-03-4 pending): unverifiable;
 *   - no index is called unreliable (decision D-03-5 pending).
 *  Failed and warned items carry a corrective recommendation that points to the DOAJ clause.
 */

require_once(dirname(__FILE__) . '/DoajPaFacts.inc.php');

class DoajPaChecks {

	const LIMIT = 'Machine check of the public website text read in this run (English-only, deterministic); not a DOAJ decision.';

	/**
	 * @param $spec array Dataset criterion
	 * @param $context DoajPaEvaluationContext
	 * @return array Outcome
	 */
	public static function evaluate($spec, $context) {
		$f = DoajPaFacts::of($context);
		$h = new DoajPaCheckRun($spec, $f);
		$method = 'c_' . str_replace(array('.', '-'), '_', $spec['key']);
		$o = method_exists(__CLASS__, $method) ? self::$method($h, $f) : $h->review('No deterministic check is defined for this clause; human review.');
		return self::bound($spec, $o);
	}

	/**
	 * Dataset limits that no handler may exceed: human-judgement (H) and human-only clauses never pass or fail; a
	 * recommendation (R) never fails; an external-registry clause never passes on website evidence (D-03-4).
	 */
	public static function bound($spec, $o) {
		$s = $o['status'];
		$decisive = in_array($s, array('pass', 'fail', 'warning', 'contradiction'), true);
		if (($spec['level'] === 'H' || $spec['assessability'] === 'human') && $decisive && $s !== 'contradiction') {
			$o['status'] = 'for_doaj_review';
			$o['detections'] = array(); $o['verifiedClear'] = array();
			$o['rationale'] = '[Human judgement reserved] ' . $o['rationale'];
		} elseif ($spec['level'] === 'R' && $s === 'fail') {
			$o['status'] = 'warning';
			foreach ($o['detections'] as $i => $d) if ($d['criterionStatus'] === 'fail') $o['detections'][$i]['criterionStatus'] = 'warning';
		} elseif ($spec['assessability'] === 'external' && $s === 'pass') {
			$o['status'] = 'unverifiable';
			$o['verifiedClear'] = array();
			$o['rationale'] = '[External registry verification not performed] ' . $o['rationale'];
		}
		return $o;
	}

	//
	// Open access and access to articles
	//

	private static function c_guide_oa_transparency_adherence($h, $f) {
		return $h->review('Adherence to the Principles of Transparency is assessed clause by clause (transparency.* criteria); overall adherence is a human judgement.');
	}

	private static function c_guide_oa_statement($h, $f) {
		return $h->presence('oa_statement', null, 8, 'an open access statement that meets the DOAJ definition (free, immediate access with re-use for any lawful purpose)',
			'Publish an open access statement that reflects the DOAJ definition, on a page linked from the homepage.');
	}

	private static function c_guide_disclosure_oa_policy($h, $f) {
		return $h->presence('oa_policy', null, 8, 'an open access policy', 'Publish the open access policy on a page linked from the journal homepage.', 2);
	}

	private static function c_guide_oa_only($h, $f) {
		$stmt = self::c_guide_oa_statement(new DoajPaCheckRun($h->spec, $f), $f);
		$access = $h->access('open');
		if ($stmt['status'] === 'pass' && $access['status'] === 'pass') return $h->merge($stmt, $access, 'pass', 'An open access statement is published and every sampled article full text was freely retrieved.');
		if ($stmt['status'] === 'fail' || $access['status'] === 'fail') return $h->merge($stmt, $access, 'fail', 'Open access is not established: ' . ($stmt['status'] === 'fail' ? 'no open access statement was found' : 'some sampled full texts were not freely accessible') . '.', 'Make all content open access and state it.');
		return $h->unverifiable('Open access could not be established from the evidence read (statement: ' . $stmt['status'] . '; access sample: ' . $access['status'] . ').');
	}

	private static function c_guide_oa_full_text_without_delay($h, $f) { return $h->access('open'); }
	private static function c_guide_no_registration_to_read($h, $f) { return $h->access('registration'); }
	private static function c_copyright_no_hybrid($h, $f) { return $h->access('hybrid'); }

	private static function c_guide_no_embargo($h, $f) {
		$items = $f->items(array('home', 'about', 'custom', 'information', 'author_guidelines', 'about_other'));
		$hits = array_values(array_filter($f->topic('embargo', $items), function ($x) {
			return !preg_match('/\b(self[- ]archiv|repositor|deposit|preprint|manuscript)\w*/i', $x['sentence']);
		}));
		$v = DoajPaFacts::verified($hits);
		if ($v) return $h->found($v, 'Embargo wording applies to the journal content: "' . $v[0]['quote'] . '".', 'Remove any embargo: all content must be open access without delay.');
		if ($hits) return $h->unverifiable('Embargo wording found on a page whose language is not verified English (ADR-011).', $hits);
		$access = $h->access('open');
		if ($access['status'] === 'pass') return $h->absent($items, 'No content embargo is stated, and sampled full texts were freely available.');
		return $h->unverifiable('No embargo is stated, but the access sample did not establish free access (' . $access['status'] . '); whether restricted content is under an embargo cannot be determined.');
	}

	private static function c_guide_dedicated_homepage($h, $f) {
		$home = $f->homepage();
		if (!$home['resource']) return $h->unverifiable('The journal homepage was not retrieved in this run.');
		if (!$home['main'] || !$home['main']['verifiedEnglish']) return $h->unverifiable('The homepage was retrieved, but its main content is not verified English (ADR-011), so it cannot be cited.');
		return $h->observed(array($home['main']), 'The journal homepage was retrieved at ' . $home['resource']['url'] . ' (one vantage point; "accessible from any location" is not proven).');
	}

	private static function c_guide_article_individual_full_text($h, $f) {
		$arts = $f->articles();
		if (!$arts) return $h->unverifiable('No article page was sampled.');
		$without = array();
		foreach ($arts as $a) {
			$hasFile = isset($a['meta']['citation_pdf_url']) || ($a['main'] && preg_match('/\b(PDF|HTML|Full Text|Download)\b/', $a['main']['quote']));
			if (!$hasFile) $without[] = $a['url'];
		}
		$cite = array_values(array_filter(array_map(function ($a) { return $a['main']; }, $arts)));
		if ($without) return $h->missingAt($cite, count($without) . ' of ' . count($arts) . ' sampled articles show no full-text galley: ' . implode(', ', array_slice($without, 0, 5)) . '.', 'Publish each article as an individual full text (HTML or PDF).');
		return $h->observed($cite, 'All ' . count($arts) . ' sampled articles have their own landing page and a full-text galley.');
	}

	private static function c_guide_article_unique_url($h, $f) {
		$arts = $f->articles();
		if (!$arts) return $h->unverifiable('No article page was sampled.');
		$urls = array_map(function ($a) { return $a['url']; }, $arts);
		$cite = array_values(array_filter(array_map(function ($a) { return $a['main']; }, $arts)));
		if (count(array_unique($urls)) !== count($urls)) return $h->missingAt($cite, 'Duplicate article URLs were found.', 'Give every article one unique URL.');
		return $h->observed($cite, 'Each of the ' . count($arts) . ' sampled articles has its own URL.');
	}

	private static function c_guide_article_html_or_pdf($h, $f) {
		$files = array();
		foreach ($f->resources as $r) {
			if (in_array($r['pageType'], array('file', 'galley', 'issue_file'), true) && $r['acquisition'] === 'retrieved') $files[] = $r;
		}
		$pdfs = $f->items(array('file', 'galley'), array('pdftotext_extraction', 'html_main_content'));
		if (!$files && !$pdfs) {
			return $f->articles() ? $h->unverifiable('No full-text file was retrieved and readable in the sample (for example, PDFs could not be read).') : $h->unverifiable('No article was sampled.');
		}
		$v = DoajPaFacts::verified($pdfs);
		if (!$v) return $h->unverifiable('Full-text files were retrieved, but none is verified English text (ADR-011) or readable.');
		return $h->observed($v, count($files) . ' sampled full-text files were retrieved (HTML or PDF).');
	}

	private static function c_transparency_website_https($h, $f) {
		$home = $f->homepage();
		if (!$home['resource'] || !$home['main'] || !$home['main']['verifiedEnglish']) return $h->unverifiable('The homepage was not retrieved as verified English text (ADR-011).');
		$url = isset($home['resource']['finalUrl']) && $home['resource']['finalUrl'] ? $home['resource']['finalUrl'] : $home['resource']['url'];
		if (stripos($url, 'https://') === 0) return $h->observed(array($home['main']), 'The journal homepage is served over https (' . $url . ').');
		return $h->missingAt(array($home['main']), 'The journal homepage is served over http, not https (' . $url . ').', 'Serve the journal website over https and redirect all http traffic.');
	}

	//
	// Disclosures (must be available online and easily accessible from the homepage)
	//

	private static function c_guide_disclosure_aims_scope($h, $f) {
		return $h->presence('aims_scope', null, 6, 'an aims and scope statement', 'Publish the aims and scope on a page linked from the homepage.', 2);
	}

	private static function c_guide_disclosure_author_instructions($h, $f) {
		return $h->presence('author_instructions', array('author_guidelines', 'about', 'custom', 'information'), 8, 'instructions for authors', 'Publish complete instructions for authors on a page linked from the homepage.', 2);
	}

	private static function c_guide_disclosure_peer_review($h, $f) {
		return $h->presence('peer_review', null, 8, 'a description of the editorial (peer review) process', 'Describe the peer review process on a page linked from the homepage.', 2);
	}

	private static function c_guide_disclosure_licensing($h, $f) { return self::licenceStated($h, $f, 'licensing terms'); }
	private static function c_guide_licensing_terms_stated($h, $f) { return self::licenceStated($h, $f, 'licensing terms for use and re-use of published content'); }

	private static function licenceStated($h, $f, $what) {
		$lic = $f->licences();
		$v = DoajPaFacts::verified(array_map(function ($l) { return $l['evidence']; }, $lic));
		$vh = array_values(array_filter($lic, function ($l) { return $l['evidence']['verifiedEnglish']; }));
		if ($vh) return $h->pass($vh, 'Licensing terms are stated: ' . implode(', ', array_unique(array_map(function ($l) { return DoajPaFacts::licenceLabel($l['code']); }, $vh))) . '.');
		if ($lic) return $h->unverifiable('Licence wording was found only on pages whose language is not verified English (ADR-011).', $lic);
		return $h->missing(null, 'No ' . $what . ' were found on the pages read.', 'State the licence (for example a Creative Commons licence) for published content on the website.');
	}

	private static function c_guide_disclosure_copyright($h, $f) {
		$c = array_values(array_filter($f->copyright(), function ($x) { return !$x['chrome'] && in_array($x['kind'], array('retain', 'transfer', 'exclusive', 'statement'), true); }));
		$v = array_values(array_filter($c, function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if ($v) return $h->pass($v, 'Copyright terms for published content are stated.');
		if ($c) return $h->unverifiable('Copyright wording was found only on pages whose language is not verified English (ADR-011).', $c);
		return $h->missing(null, 'No copyright terms for published content were found on the pages read.', 'State the copyright terms for published content (who holds copyright) on the website.');
	}

	private static function c_guide_disclosure_author_charges($h, $f) {
		$fees = $f->fees();
		$all = array_merge($fees['noFee'], $fees['fees']);
		$v = array_values(array_filter($all, function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if ($v) return $h->pass($v, 'Author charges are stated (' . ($fees['noFee'] ? 'explicit no-charge statement' : 'charges listed') . ').');
		if ($all) return $h->unverifiable('Charge wording was found only on pages whose language is not verified English (ADR-011).', $all);
		return $h->missing($fees['items'], 'No statement of author charges (or of no charges) was found on the pages read.', 'State all author charges, or that there are none, on a page linked from the homepage.');
	}

	private static function c_guide_disclosure_publisher($h, $f) {
		$p = $f->publisher();
		$v = array_values(array_filter($p, function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if ($v) return $h->pass($v, 'Publisher: ' . $v[0]['name'] . '.');
		if ($p) return $h->unverifiable('Publisher information was found only on pages whose language is not verified English (ADR-011).', $p);
		return $h->missing($f->items(array('about', 'contact', 'home', 'custom', 'information')), 'No publisher information was found on the pages read.', 'Name the publisher on the website.');
	}

	private static function c_guide_publisher_name_address_link($h, $f) {
		$p = array_values(array_filter($f->publisher(), function ($x) { return $x['evidence']['verifiedEnglish']; }));
		$a = array_values(array_filter($f->addresses(), function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if ($p && $a) return $h->pass(array($p[0], $a[0]), 'Publisher name and an address are stated (a link to the publisher website, if any, is not verified).');
		if ($p) return $h->missingAt(array($p[0]['evidence']), 'The publisher is named, but no postal address was recognised.', 'Give the publisher\'s full address.');
		return self::c_guide_disclosure_publisher($h, $f);
	}

	private static function c_guide_contact_person_email($h, $f) {
		$items = $f->items(array('contact', 'about'), array('html_main_content'));
		$contactRead = $f->resourcesOf(array('contact'));
		$e = array_values(array_filter($f->emails(), function ($x) { return $x['evidence']['verifiedEnglish']; }));
		$named = DoajPaText::find(DoajPaFacts::verified($items), '/\b(principal contact|contact person|managing editor|editor[- ]in[- ]chief|support contact)\b/i');
		if ($e && $named) return $h->pass(array($named[0], $e[0]), 'A contact (' . $named[0]['match'] . ') and an email address are published.');
		if (!$contactRead) return $h->unverifiable('The contact page was not read in this run.');
		if (!DoajPaFacts::verified($items)) return $h->unverifiable('The contact page is not verified English (ADR-011).');
		if (!$e) return $h->missing($items, 'No email address was found on the contact pages (it may be shown only as an image or through a form).', 'Publish the name of a contact person and the journal\'s dedicated email address.');
		return $h->missing($items, 'An email address is published, but no named contact person was recognised.', 'Name a contact person for the journal.');
	}

	private static function c_guide_publisher_country($h, $f) {
		return $h->review('The publisher\'s country of registration and business activity cannot be verified from the website.', $f->addresses());
	}

	//
	// ISSN
	//

	private static function c_guide_issn_displayed($h, $f) {
		$all = $f->issns();
		$valid = array_values(array_filter($all, function ($x) { return $x['valid'] && $x['evidence']['verifiedEnglish']; }));
		if ($valid) {
			$distinct = array_unique(array_map(function ($x) { return $x['issn']; }, $valid));
			return $h->pass($valid, 'ISSN displayed: ' . implode(', ', $distinct) . ' (check digit valid).');
		}
		$invalid = array_values(array_filter($all, function ($x) { return !$x['valid'] && $x['evidence']['verifiedEnglish']; }));
		if ($invalid) return $h->found($invalid, 'An ISSN is displayed but its check digit is invalid: ' . $invalid[0]['issn'] . '.', 'Display the ISSN exactly as registered at issn.org.');
		if ($all) return $h->unverifiable('ISSN wording was found only on pages whose language is not verified English (ADR-011).', $all);
		return $h->missing($f->items(array('home', 'about'), array('html_main_content', 'html_text_extraction')), 'No ISSN was found on the pages read.', 'Display the journal\'s ISSN(s) on the website.');
	}

	private static function c_transparency_website_issns($h, $f) {
		$valid = array_values(array_filter($f->issns(), function ($x) { return $x['valid'] && $x['evidence']['verifiedEnglish']; }));
		if (!$valid) return $h->unverifiable('No valid ISSN was found in verified-English text.');
		$labelled = array_values(array_filter($valid, function ($x) { return $x['label'] !== 'unlabelled'; }));
		if ($labelled) return $h->pass($labelled, 'ISSN(s) labelled by version: ' . implode(', ', array_unique(array_map(function ($x) { return $x['issn'] . ' (' . $x['label'] . ')'; }, $labelled))) . '.');
		return $h->found($valid, 'The ISSN is displayed without saying whether it is the print or the online ISSN.', 'Label each ISSN as print or online.');
	}

	private static function c_guide_issn_registered($h, $f) { return self::external($h, $f, 'Registration and confirmation at issn.org'); }
	private static function c_guide_title_matches_issn($h, $f) { return self::external($h, $f, 'Comparison of the journal title with the ISSN Portal record'); }

	private static function external($h, $f, $what) {
		$facts = array_values(array_filter($f->issns(), function ($x) { return $x['valid']; }));
		$titles = $f->siteTitles();
		$note = $facts ? ' Website ISSN(s): ' . implode(', ', array_unique(array_map(function ($x) { return $x['issn']; }, $facts))) . '.' : '';
		$note .= $titles ? ' Website title: ' . $titles[0]['title'] . '.' : '';
		return $h->unverifiable($what . ' needs an external ISSN Portal lookup, which is not approved (decision D-03-4 PENDING APPROVAL); website evidence alone cannot verify it.' . $note);
	}

	//
	// Editorial board and reviewers
	//

	private static function boardOrAbstain($h, $f, $reviewers = false) {
		$b = $f->board($reviewers);
		if (!$b['pages']) return array(null, $reviewers ? $h->notApplicable('No reviewer list page was found.') : (
			$f->resourcesOf(array('editorial_team')) ? $h->unverifiable('The editorial team page was retrieved but has no readable main content.') : $h->missing(null, 'No editorial team page was found on the pages read.', 'Publish the editorial board on a page linked from the homepage.')));
		if (!DoajPaFacts::verified($b['pages'])) return array(null, $h->unverifiable('The editorial team page is not verified English (ADR-011).'));
		if (!$b['people'] || $b['unparsed'] > max(2, 0.3 * $b['lines'])) {
			return array(null, $h->unverifiable('The editorial team page could not be read reliably as a list of people (' . count($b['people']) . ' recognised, ' . $b['unparsed'] . ' unrecognised lines); human review.', array_slice(array_map(function ($p) { return $p; }, $b['people']), 0, 3)));
		}
		return array($b, null);
	}

	private static function c_guide_disclosure_editorial_board($h, $f) {
		list($b, $stop) = self::boardOrAbstain($h, $f);
		if ($stop) return $stop;
		return $h->pass(array_slice($b['people'], 0, 3), count($b['people']) . ' editorial team members are listed.');
	}

	private static function c_guide_board_listed($h, $f) { return self::c_guide_disclosure_editorial_board($h, $f); }

	private static function c_guide_editor_and_board($h, $f) {
		list($b, $stop) = self::boardOrAbstain($h, $f);
		if ($stop) return $stop;
		$editors = array_values(array_filter($b['people'], function ($p) { return $p['role'] !== null && preg_match('/editor/i', $p['role']) && !preg_match('/board|member/i', $p['role']); }));
		if ($editors && count($b['people']) >= 2) return $h->pass(array($editors[0]), 'An editor (' . $editors[0]['role'] . ') and ' . count($b['people']) . ' team members are listed.');
		if (!$editors) return $h->unverifiable('People are listed, but no editor role was recognised; human review.', array_slice($b['people'], 0, 2));
		return $h->missingAt(array($editors[0]['evidence']), 'Only one person is listed; no editorial board was found.', 'List the editorial board in addition to the editor.');
	}

	private static function c_guide_board_names_affiliations($h, $f) {
		list($b, $stop) = self::boardOrAbstain($h, $f);
		if ($stop) return $stop;
		$missing = array_values(array_filter($b['people'], function ($p) { return $p['affiliation'] === null; }));
		if (!$missing) return $h->pass(array_slice($b['people'], 0, 3), 'All ' . count($b['people']) . ' listed members have a name and an affiliation.');
		return $h->missingAt(array($missing[0]['evidence']), count($missing) . ' of ' . count($b['people']) . ' listed members have no affiliation: ' . implode('; ', array_slice(array_map(function ($p) { return $p['name']; }, $missing), 0, 8)) . '.',
			'Give the name and institutional affiliation of every editor and board member.', $missing[0]);
	}

	private static function c_guide_board_five_editors($h, $f) {
		list($b, $stop) = self::boardOrAbstain($h, $f);
		if ($stop) return $stop;
		if (count($b['people']) >= 5) return $h->pass(array_slice($b['people'], 0, 2), count($b['people']) . ' editorial team members are listed (qualifications and expertise need human review).');
		return $h->missingAt(array($b['people'][0]['evidence']), 'Only ' . count($b['people']) . ' editorial team members are listed (the Guide recommends at least five editors).', 'Consider a board of at least five editors with appropriate qualifications.');
	}

	private static function c_guide_board_not_single_institution($h, $f) {
		list($b, $stop) = self::boardOrAbstain($h, $f);
		if ($stop) return $stop;
		$affs = array();
		foreach ($b['people'] as $p) if ($p['affiliation'] !== null) $affs[preg_replace('/[^a-z]/', '', DoajPaText::fold($p['affiliation']))] = $p;
		if (count($affs) >= 2) return $h->pass(array_slice(array_values($affs), 0, 2), count($affs) . ' different affiliations are listed (variants of one institution may need review).');
		if (count($affs) === 1) return $h->missingAt(array($b['people'][0]['evidence']), 'All listed members with an affiliation are from the same institution.', 'Include board members from other institutions.');
		return $h->unverifiable('No affiliations were recognised.');
	}

	private static function c_guide_reviewer_list_affiliations($h, $f) {
		list($b, $stop) = self::boardOrAbstain($h, $f, true);
		if ($stop) return $stop;
		$missing = array_values(array_filter($b['people'], function ($p) { return $p['affiliation'] === null; }));
		if (!$missing) return $h->pass(array_slice($b['people'], 0, 2), 'All ' . count($b['people']) . ' listed reviewers have a name and an affiliation.');
		return $h->missingAt(array($missing[0]['evidence']), count($missing) . ' listed reviewers have no affiliation.', 'Give the name and affiliation of every listed reviewer.');
	}

	private static function c_guide_student_run_advisory_board($h, $f) {
		if (!$h->confirmed('student_run')) return $h->notApplicable('The journal is not stated to be student-run.');
		$phd = DoajPaText::find(DoajPaFacts::verified($f->items(array('editorial_team', 'about', 'custom'), array('html_main_content'))), DoajPaFacts::TOPICS['phd_members']);
		if (count($phd) >= 2) return $h->pass(array_slice($phd, 0, 2), 'Advisory board members with a PhD or equivalent are listed.');
		return $h->review('Student-run journal: at least two advisory board members with a PhD (or equivalent) were not recognised; human review.', $phd);
	}

	private static function c_transparency_editorial_office_contact($h, $f) {
		$a = array_values(array_filter($f->addresses(array('contact', 'about', 'editorial_team')), function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if ($a) return $h->pass(array($a[0]), 'An editorial office address is published.');
		return $h->missing($f->items(array('contact', 'about'), array('html_main_content')), 'No editorial office mailing address was recognised.', 'Publish the editorial office\'s full mailing address.');
	}

	private static function c_transparency_board_current($h, $f) {
		return $h->review('Whether the board list is up to date and members agreed to serve cannot be verified from the website.');
	}

	//
	// Peer review, ethics, policies
	//

	private static function c_guide_peer_review_type_stated($h, $f) {
		$items = $f->items(array('about', 'custom', 'information', 'home', 'author_guidelines', 'about_other'));
		$hits = $f->topic('review_types', $items);
		$v = DoajPaFacts::verified($hits);
		if ($v) return $h->pass($v, 'Peer review type stated: ' . implode(', ', array_unique(array_map(function ($x) { return strtolower($x['match']); }, $v))) . '.');
		if ($hits) return $h->unverifiable('Review type wording found only on pages whose language is not verified English (ADR-011).', $hits);
		$general = DoajPaFacts::verified($f->topic('peer_review', $items));
		if ($general) return $h->found($general, 'Peer review is mentioned, but its type (for example double-anonymous) is not stated.', 'State the type and details of the peer review process.');
		return $h->missing($items, 'No description of the peer review type was found.', 'State the type and details of the peer review process.');
	}

	private static function c_guide_two_independent_reviewers($h, $f) {
		$items = $f->items(array('about', 'custom', 'information', 'home', 'author_guidelines', 'about_other'));
		$topic = $h->confirmed('editorial_review') && $h->confirmed('arts_humanities') ? 'two_editors' : 'two_reviewers';
		return $h->presence($topic, $items, 5, 'a statement that each article is reviewed by at least two independent reviewers', 'State that at least two independent reviewers review each article (two editors for arts and humanities editorial review).');
	}

	private static function c_guide_peer_review_before_publication($h, $f) {
		return $h->review('Peer review in practice cannot be proven from the website; the stated policy is quoted for review.', $f->topic('peer_review', $f->items(array('about', 'custom', 'information', 'author_guidelines'))));
	}

	private static function c_guide_plagiarism_screening($h, $f) {
		return $h->presence('plagiarism', null, 3, 'a plagiarism screening statement', 'State whether and how submissions are screened for plagiarism.');
	}

	private static function c_transparency_peer_review_who($h, $f) {
		return $h->presence('peer_review_who', null, 4, 'who conducts the peer review', 'State who conducts the peer review (for example external experts).');
	}

	private static function c_transparency_ethics_policies($h, $f) {
		return $h->elements(array('authorship' => '/\b(authorship|contributorship)\b/i', 'complaints and appeals' => '/\b(complaints?|appeals?)\b/i',
			'misconduct' => '/\b(misconduct|fabricat\w+|falsif\w+)\b/i', 'conflicts of interest' => '/\b(conflicts? of interest|competing interests?)\b/i',
			'data sharing' => '/\b(data (?:sharing|availability|access)|reproducib\w+)\b/i', 'ethical oversight' => '/\b(ethic(?:s|al) (?:committee|approval|oversight|review)|informed consent|institutional review board|IRB)\b/i',
			'intellectual property' => '/\b(intellectual property|copyright)\b/i', 'post-publication discussion' => '/\b(post-publication|comments?|discussion|letters? to the editor)\b/i',
			'corrections and retractions' => '/\b(correction|retraction|erratum|errata|corrigendum)\w*\b/i'), 'publication ethics policies');
	}

	private static function c_transparency_peer_review_policy_elements($h, $f) {
		return $h->elements(array('whether content is peer reviewed' => DoajPaFacts::TOPICS['peer_review'], 'type of review' => DoajPaFacts::TOPICS['review_types'],
			'author-recommended reviewers' => '/\b(suggest|recommend|propos)\w*\b[^.!?\n]{0,40}\breviewers?\b/i', 'masking of identities' => '/\b(anonym\w+|blind\w*|masked|identities)\b/i',
			'supplementary material' => '/\bsupplementary (?:material|files?|data)\b/i', 'reviews posted' => '/\b(reviews?|reports?)\b[^.!?\n]{0,30}\b(published|posted)\b/i',
			'signed or anonymous reviews' => '/\b(signed|anonymous) (?:reviews?|reports?)\b|\breviews? (?:are|is) (?:signed|anonymous)\b/i',
			'decision process' => '/\b(final decision|decision (?:is|will be) (?:made|taken)|editor (?:makes|decides|will decide))\b/i',
			'exceptions' => '/\b(not (?:subject to|undergo|sent (?:out )?for) (?:peer )?review|exempt from (?:peer )?review|editorials? (?:are|is) not)\b/i'), 'peer review policy elements');
	}

	private static function c_guide_ai_policy($h, $f) {
		return $h->presence('ai_policy', null, 6, 'a policy on artificial intelligence and automated tools', 'Publish a policy on the use of generative AI and automated tools by authors, reviewers and editors.', null, false);
	}

	private static function c_transparency_website_target_readership($h, $f) { return $h->presence('target_readership', null, 6, 'the target readership', 'State the journal\'s target readership.'); }
	private static function c_transparency_website_manuscript_types($h, $f) { return $h->presence('manuscript_types', array('author_guidelines', 'about', 'custom'), 4, 'the types of manuscripts considered', 'List the types of manuscripts the journal considers.'); }
	private static function c_transparency_website_authorship_criteria($h, $f) { return $h->presence('authorship', array('author_guidelines', 'about', 'custom'), 4, 'authorship criteria', 'State the authorship criteria.'); }
	private static function c_transparency_publishing_schedule($h, $f) { return $h->presence('schedule', null, 3, 'the publishing frequency', 'Describe the publishing frequency.'); }
	private static function c_transparency_ownership_management($h, $f) { return $h->presence('ownership', null, 4, 'ownership and management information', 'State who owns and manages the journal.'); }
	private static function c_transparency_fees_not_influence_decisions($h, $f) { return $h->presence('fees_independent', null, 6, 'a statement that fees do not influence editorial decisions', 'State that author fees or waivers do not influence editorial decisions.'); }
	private static function c_transparency_revenue_sources($h, $f) { return $h->presence('revenue', null, 4, 'business model or revenue sources', 'State the journal\'s business model and revenue sources.'); }
	private static function c_transparency_advertising_policy($h, $f) { return $h->presence('advertising', null, 4, 'a statement whether the journal accepts advertising', 'State whether the journal accepts advertising, and its advertising policy.', null, false); }
	private static function c_transparency_archiving($h, $f) { return $h->presence('archiving', null, 3, 'a long-term digital preservation plan', 'State the journal\'s digital preservation arrangements (for example PKP PN, LOCKSS, Portico).'); }
	private static function c_guide_archiving_policy($h, $f) { return $h->presence('archiving', null, 3, 'a long-term archiving service', 'Deposit content in a long-term digital archiving service and state it on the website.'); }
	private static function c_guide_repository_policy($h, $f) { return $h->presence('repository', null, 5, 'a self-archiving (repository) policy', 'State which versions authors may deposit in repositories.'); }
	private static function c_transparency_repository_licensing_policy($h, $f) { return $h->presence('repository', null, 5, 'a policy on posting manuscripts in third-party repositories', 'State the policy on posting manuscripts and published articles in repositories.'); }

	private static function c_guide_repository_no_embargo($h, $f) {
		$items = $f->items(array('home', 'about', 'custom', 'information', 'author_guidelines', 'about_other'));
		$repo = DoajPaFacts::verified($f->topic('repository', $items));
		if (!$repo) return $h->unverifiable('No self-archiving policy was found in verified-English text.');
		$emb = array_values(array_filter($repo, function ($x) { return preg_match('/\bembargo\w*/i', $x['sentence']) && !preg_match('/\b(no|without)\s+(?:any\s+)?embargo/i', $x['sentence']); }));
		if ($emb) return $h->found($emb, 'The self-archiving policy applies an embargo.', 'Allow self-archiving without an embargo.');
		return $h->pass($repo, 'The self-archiving policy states no embargo.');
	}

	private static function c_transparency_no_acceptance_guarantee($h, $f) {
		$items = $f->items(array('home', 'about', 'custom', 'information', 'author_guidelines', 'about_other'));
		$hits = $f->topic('acceptance_guarantee', $items);
		$v = DoajPaFacts::verified($hits);
		if ($v) return $h->found($v, 'The website appears to guarantee acceptance or publication.', 'Remove any guarantee of acceptance or publication.');
		if ($hits) return $h->unverifiable('Guarantee wording was found on a page whose language is not verified English.', $hits);
		return $h->absent($items, 'No guarantee of acceptance or publication was found on the pages read.');
	}

	//
	// Charges and waivers
	//

	private static function c_guide_no_charges_stated($h, $f) {
		$fees = $f->fees();
		$charged = array_values(array_filter($fees['fees'], function ($x) { return $x['amount'] !== null; }));
		if ($charged) return $h->notApplicable('The journal states charges (' . $charged[0]['amount'] . ').');
		$v = array_values(array_filter($fees['noFee'], function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if ($v) return $h->pass($v, 'The journal states that there are no charges.');
		return $h->review('No statement of charges (or of no charges) was found, so it is unknown whether the journal charges fees.', $fees['noFee']);
	}

	private static function c_guide_all_fees_listed($h, $f) {
		$fees = $f->fees();
		$mentions = array_values(array_filter($fees['fees'], function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if (!$mentions) return $h->notApplicable('No charges are stated in verified-English text.');
		$noFee = array_values(array_filter($fees['noFee'], function ($x) { return $x['evidence']['verifiedEnglish']; }));
		$withAmount = array_values(array_filter($mentions, function ($x) { return $x['amount'] !== null; }));
		if ($noFee && $withAmount) return $h->contradiction(array($noFee[0], $withAmount[0]), 'The website states both that there are no charges and that charges apply.', 'Make the fee statements consistent on all pages.');
		$noAmount = array_values(array_filter($mentions, function ($x) { return $x['amount'] === null; }));
		$types = array_values(array_unique(array_map(function ($x) { return $x['type']; }, $mentions)));
		if ($noAmount && !$withAmount) return $h->found($noAmount, 'Charges are mentioned (' . implode(', ', $types) . ') without amounts.', 'State every fee that may be charged to authors, with amounts and conditions.');
		return $h->pass($withAmount, 'Charges are listed with amounts: ' . implode(', ', $types) . '.');
	}

	private static function waivers($h, $f, $pattern, $what, $rec) {
		$fees = $f->fees();
		$w = array_values(array_filter($fees['waivers'], function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if (!$w) return $h->notApplicable('No fee waiver is stated in verified-English text.');
		$ok = DoajPaText::find(array_map(function ($x) { return $x['evidence']; }, $w), $pattern);
		if ($ok) return $h->pass($ok, 'The waiver statement gives ' . $what . '.');
		return $h->found($w, 'A waiver is offered, but ' . $what . ' is not stated.', $rec);
	}

	private static function c_guide_fee_waiver_conditions($h, $f) {
		return self::waivers($h, $f, '/\b(eligib\w+|low[- ]income|lower[- ]middle|countr(?:y|ies)|\d+\s?%|percent|full waiver|partial waiver|criteria|conditions?|upon request|on request)\b/i', 'the conditions of the waiver', 'Specify the waiver conditions (who is eligible, amount, period).');
	}
	private static function c_transparency_waiver_details($h, $f) { return self::waivers($h, $f, '/\b(eligib\w+|low[- ]income|countr(?:y|ies)|students?|authors? (?:from|in|with))\b/i', 'who is eligible', 'State who is eligible for a waiver.'); }
	private static function c_transparency_waiver_which_authors($h, $f) { return self::waivers($h, $f, '/\b(corresponding author|all (?:co-)?authors|first author|any (?:of the )?authors?)\b/i', 'which author(s) must be eligible', 'State which author(s) must be eligible for the waiver.'); }
	private static function c_transparency_waiver_how_to_apply($h, $f) { return self::waivers($h, $f, '/\b(apply|request|contact|email|form|upon submission|at submission)\b/i', 'when and how to apply', 'State when and how to apply for a waiver.'); }

	private static function c_guide_withdrawal_charge_limit($h, $f) {
		$fees = $f->fees();
		$wd = array_values(array_filter($fees['fees'], function ($x) { return $x['type'] === 'withdrawal' && $x['evidence']['verifiedEnglish']; }));
		if (!$wd) return $h->notApplicable('No withdrawal charge is stated.');
		$apc = array_values(array_filter($fees['fees'], function ($x) { return $x['type'] === 'apc' && $x['amount'] !== null; }));
		if (!$apc || $wd[0]['amount'] === null) return $h->unverifiable('A withdrawal charge is stated, but the amounts cannot be compared.', $wd);
		$n = function ($s) { return (float) str_replace(',', '', preg_replace('/[^0-9.,]/', '', $s)); };
		$cur = function ($s) { return preg_replace('/[0-9.,\s]/', '', $s); };
		if ($cur($wd[0]['amount']) !== $cur($apc[0]['amount'])) return $h->unverifiable('The withdrawal charge and the author charge are in different currencies.', array($wd[0], $apc[0]));
		if ($n($wd[0]['amount']) <= $n($apc[0]['amount'])) return $h->pass(array($wd[0], $apc[0]), 'The withdrawal charge does not exceed the author charge.');
		return $h->found(array($wd[0], $apc[0]), 'The withdrawal charge exceeds the author charge.', 'Do not charge more for withdrawal than the author charges.');
	}

	private static function c_transparency_fees_early_in_submission($h, $f) {
		$fees = $f->fees();
		$all = array_values(array_filter(array_merge($fees['noFee'], $fees['fees']), function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if (!$all) return $h->unverifiable('No verified-English fee statement was found.');
		foreach ($all as $x) {
			$depth = isset($f->resources[$x['evidence']['resourceId']]['depth']) ? $f->resources[$x['evidence']['resourceId']]['depth'] : null;
			if ($x['evidence']['pageType'] === 'author_guidelines' || ($depth !== null && $depth <= 1)) return $h->pass(array($x), 'Fee information is on the submission guidelines or a page linked from the homepage.');
		}
		return $h->found($all, 'Fee information was found only on pages more than one click from the homepage and not in the submission guidelines.', 'Present fee information early in the submission process.');
	}

	private static function c_transparency_future_charges($h, $f) {
		return $h->review('Whether the journal intends to introduce charges cannot be inferred; any statement found is quoted.', DoajPaText::find($f->items(null, array('html_main_content')), '/\b(future|plan(?:s|ned)? to|will (?:introduce|charge|start))\b[^.!?\n]{0,60}\b(fees?|charges?|APCs?)\b/i'));
	}

	//
	// Licensing and copyright consistency
	//

	private static function c_guide_licensing_cc_recommended($h, $f) {
		$lic = array_values(array_filter($f->licences(), function ($l) { return $l['evidence']['verifiedEnglish']; }));
		$cc = array_values(array_filter($lic, function ($l) { return $l['code'] !== 'other'; }));
		if ($cc) return $h->pass($cc, 'Creative Commons licensing is used: ' . implode(', ', array_unique(array_map(function ($l) { return DoajPaFacts::licenceLabel($l['code'], $l['version']); }, $cc))) . '.');
		if ($lic) return $h->found($lic, 'A licence is stated, but it is not a Creative Commons licence.', 'Consider using a Creative Commons licence.');
		return $h->unverifiable('No licence was identified in verified-English text.');
	}

	private static function c_guide_licensing_non_cc_similar($h, $f) { return self::nonCc($h, $f, 'Whether the publisher\'s own licence gives terms similar to a Creative Commons licence is a human judgement.'); }
	private static function c_guide_licensing_non_cc_clear($h, $f) { return self::nonCc($h, $f, 'Whether the publisher\'s own licence terms are stated clearly is a human judgement.'); }

	private static function nonCc($h, $f, $text) {
		$lic = $f->licences();
		$cc = array_filter($lic, function ($l) { return $l['code'] !== 'other'; });
		$other = array_values(array_filter($lic, function ($l) { return $l['code'] === 'other'; }));
		if ($cc || !$other) return $h->notApplicable($cc ? 'A Creative Commons licence is used.' : 'No non-Creative Commons licence was identified.');
		return $h->review($text, $other);
	}

	private static function pdfCheck($h, $f, $pattern, $what, $rec) {
		$pdfs = $f->items(array('file', 'galley', 'issue_file'), array('pdftotext_extraction'));
		$v = DoajPaFacts::verified($pdfs);
		if (!$v) return $h->unverifiable($pdfs ? 'No sampled full-text PDF is verified English text (ADR-011).' : 'No full-text PDF was read in this run (sampled PDFs may be scanned, protected or unavailable; pdftotext may not be installed).');
		$with = array(); $without = array();
		foreach ($v as $e) {
			$hit = DoajPaText::find(array($e), $pattern);
			if ($hit) $with[] = $hit[0]; else $without[] = $e;
		}
		if (!$without) return $h->pass($with, 'All ' . count($v) . ' readable sampled full texts show ' . $what . '.');
		return $h->missingAt($without, count($without) . ' of ' . count($v) . ' readable sampled full texts do not show ' . $what . ': ' . implode(', ', array_slice(array_map(function ($e) { return $e['url']; }, $without), 0, 5)) . '.', $rec);
	}

	private static function c_guide_licensing_in_full_text($h, $f) { return self::pdfCheck($h, $f, '/creative ?commons|\bCC[- ]?BY\b|\bCC0\b|licen[cs]ed under/i', 'licensing information', 'Display or embed the licence in every full-text article.'); }
	private static function c_transparency_licence_on_full_text($h, $f) { return self::c_guide_licensing_in_full_text($h, $f); }
	private static function c_transparency_copyright_on_full_text($h, $f) { return self::pdfCheck($h, $f, '/copyright|\x{00A9}|\(c\)\s?\d{4}/iu', 'a copyright statement', 'Name the copyright holder on every full-text article.'); }

	private static function c_guide_copyright_stated_separately($h, $f) {
		$all = $f->copyright();
		$content = array_values(array_filter($all, function ($x) { return !$x['chrome'] && in_array($x['kind'], array('retain', 'transfer', 'exclusive', 'statement'), true) && $x['evidence']['verifiedEnglish']; }));
		if ($content) return $h->pass($content, 'A copyright statement for the published content is given (separate from the website footer).');
		$chrome = array_values(array_filter($all, function ($x) { return $x['chrome'] && $x['evidence']['verifiedEnglish']; }));
		if ($chrome) return $h->found($chrome, 'Only a website copyright notice (page header or footer) was found; no copyright terms for the published content.', 'State the copyright terms for published content separately from the website copyright.');
		return self::c_guide_disclosure_copyright($h, $f);
	}

	/** @return array contradictions found: list of [a, b, why] */
	private static function contradictions($f) {
		$out = array();
		$lic = array_values(array_filter($f->licences(), function ($l) { return $l['evidence']['verifiedEnglish'] && $l['code'] !== 'other'; }));
		$codes = array();
		foreach ($lic as $l) if ($l['code'] !== 'CC') $codes[$l['code']][] = $l; // an unspecified "Creative Commons licence" never contradicts a variant
		$choice = DoajPaText::find($f->items(null, array('html_main_content')), '/\b(choose|choice|option|select|may (?:use|opt))\b[^.!?\n]{0,60}\blicen[cs]e/i');
		if (count($codes) > 1 && !$choice) {
			$k = array_keys($codes);
			$out[] = array($codes[$k[0]][0], $codes[$k[1]][0], 'different licences (' . $k[0] . ' and ' . $k[1] . ') are stated');
		}
		$arr = array_values(array_filter($f->copyright(), function ($x) { return $x['kind'] === 'arr' && !$x['chrome'] && $x['evidence']['verifiedEnglish']; }));
		if ($arr && $lic) $out[] = array($arr[0], $lic[0], '"All rights reserved" is stated for content that is also openly licensed');
		$fair = array_values(array_filter($f->copyright(), function ($x) { return $x['kind'] === 'fair_use' && !$x['chrome'] && $x['evidence']['verifiedEnglish'] && preg_match('/\bonly\b|limited to|restricted to/i', $x['sentence']); }));
		if ($fair && $lic) $out[] = array($fair[0], $lic[0], 're-use is restricted to fair use while an open licence is stated');
		return $out;
	}

	private static function c_guide_copyright_no_contradiction($h, $f) {
		$c = self::contradictions($f);
		if ($c) return $h->contradiction(array($c[0][0], $c[0][1]), 'Contradiction: ' . $c[0][2] . '.', 'Make the copyright, licence and open access terms consistent on every page and in the articles.');
		$lic = array_values(array_filter($f->licences(), function ($l) { return $l['evidence']['verifiedEnglish']; }));
		$cr = array_values(array_filter($f->copyright(), function ($x) { return !$x['chrome'] && $x['evidence']['verifiedEnglish'] && in_array($x['kind'], array('retain', 'transfer', 'exclusive', 'statement'), true); }));
		if ($lic && $cr) return $h->pass(array($lic[0], $cr[0]), 'The copyright and licence statements found do not contradict each other (deterministic comparison of exact claims).');
		return $h->unverifiable('Copyright and licence statements were not both found in verified-English text; consistency cannot be established.');
	}
	private static function c_copyright_no_contradiction($h, $f) { return self::c_guide_copyright_no_contradiction($h, $f); }

	private static function c_guide_no_all_rights_reserved($h, $f) {
		$arr = $f->copyright();
		$content = array_values(array_filter($arr, function ($x) { return $x['kind'] === 'arr' && !$x['chrome']; }));
		$v = array_values(array_filter($content, function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if ($v) return $h->found($v, '"All rights reserved" is applied to content.', 'Remove "All rights reserved" from open access content; apply an open licence.');
		if ($content) return $h->unverifiable('"All rights reserved" appears on a page whose language is not verified English.', $content);
		$items = $f->items(array('about', 'custom', 'information', 'author_guidelines', 'article', 'file', 'galley'));
		$chrome = array_filter($arr, function ($x) { return $x['kind'] === 'arr' && $x['chrome']; });
		return $h->absent($items, 'No "All rights reserved" notice applies to content on the pages read' . ($chrome ? ' (a website footer notice was found; it covers the website, not the articles)' : '') . '.');
	}
	private static function c_copyright_all_rights_reserved($h, $f) { return self::c_guide_no_all_rights_reserved($h, $f); }

	private static function c_transparency_oa_open_licence($h, $f) {
		$lic = array_values(array_filter($f->licences(), function ($l) { return $l['evidence']['verifiedEnglish']; }));
		$open = array_values(array_filter($lic, function ($l) { return $l['code'] !== 'other'; }));
		$arr = array_filter($f->copyright(), function ($x) { return $x['kind'] === 'arr' && !$x['chrome'] && $x['evidence']['verifiedEnglish']; });
		if ($open && !$arr) return $h->pass($open, 'Open access content uses an open licence: ' . DoajPaFacts::licenceLabel($open[0]['code'], $open[0]['version']) . '.');
		if ($arr) return $h->found(array_values($arr), '"All rights reserved" is applied to content designated as open access.', 'Apply an open licence to all open access content.');
		if ($lic) return $h->review('Only a publisher\'s own licence was found; whether it is an open licence is a human judgement.', $lic);
		return $h->missing(null, 'No licence was found for the open access content.', 'Apply and state an open licence for all content.');
	}

	private static function c_transparency_cc_licence_link($h, $f) {
		$lic = array_values(array_filter($f->licences(), function ($l) { return $l['code'] !== 'other' && $l['evidence']['verifiedEnglish']; }));
		if (!$lic) return $h->notApplicable('No Creative Commons licence was identified.');
		$links = array_values(array_filter($lic, function ($l) { return $l['kind'] === 'url'; }));
		if ($links) return $h->pass($links, 'The Creative Commons licence links to the licence on creativecommons.org.');
		return $h->found($lic, 'A Creative Commons licence is named without a link to the licence deed.', 'Link the licence statement to the correct licence on creativecommons.org.');
	}

	private static function c_copyright_cc_terms_not_adapted($h, $f) {
		$lic = array_values(array_filter($f->licences(), function ($l) { return $l['code'] !== 'other' && $l['evidence']['verifiedEnglish']; }));
		if (!$lic) return $h->notApplicable('No Creative Commons licence was identified.');
		if (!array_filter($lic, function ($l) { return $l['code'] !== 'CC'; })) return $h->unverifiable('A Creative Commons licence is named without its variant, so added restrictions cannot be compared with its terms.', $lic);
		$byOnly = array_filter($lic, function ($l) { return $l['code'] !== 'CC' && strpos($l['code'], '-NC') === false; });
		$items = $f->items(array('about', 'custom', 'information', 'author_guidelines', 'article', 'about_other'), array('html_main_content'));
		$restrict = array();
		if ($byOnly) {
			$restrict = DoajPaText::find(DoajPaFacts::verified($items), '/\b(commercial (?:use|purposes?|re-?use)|for (?:non-?commercial|commercial) purposes)\b[^.!?\n]{0,40}\b(not (?:permitted|allowed)|prohibited|forbidden)\b|\b(not (?:permitted|allowed)|prohibited|forbidden)\b[^.!?\n]{0,40}\bcommercial\b/i');
		}
		if ($restrict) return $h->found($restrict, 'A commercial-use restriction is stated next to a Creative Commons licence that allows commercial use.', 'Do not add restrictions to the terms of a Creative Commons licence.');
		return $h->absent($items, 'No restriction added to the Creative Commons licence terms was found.');
	}

	private static function c_copyright_authors_retain_recommended($h, $f) {
		$c = array_values(array_filter($f->copyright(), function ($x) { return !$x['chrome'] && $x['evidence']['verifiedEnglish']; }));
		$retain = array_values(array_filter($c, function ($x) { return $x['kind'] === 'retain'; }));
		$restrict = array_values(array_filter($c, function ($x) { return in_array($x['kind'], array('transfer', 'exclusive'), true); }));
		if ($retain && !$restrict) return $h->pass($retain, 'Authors retain copyright without stated restrictions.');
		if ($restrict) return $h->found($restrict, 'Copyright is transferred, or exclusive rights are required.', 'Consider allowing authors to retain copyright without restrictions.');
		return $h->unverifiable('No statement on who holds copyright was found in verified-English text.');
	}

	private static function c_copyright_no_fair_use_only($h, $f) {
		$c = array_values(array_filter($f->copyright(), function ($x) { return $x['kind'] === 'fair_use' && !$x['chrome'] && preg_match('/\bonly\b|limited to|restricted to/i', $x['sentence']); }));
		$v = array_values(array_filter($c, function ($x) { return $x['evidence']['verifiedEnglish']; }));
		if ($v) return $h->found($v, 'Re-use is restricted to fair use.', 'Apply an open licence; fair-use-only re-use is not open access.');
		return $h->absent($f->items(array('about', 'custom', 'information', 'author_guidelines')), 'No fair-use-only restriction was found on the pages read.');
	}

	private static function c_copyright_shared_copyright_not_recommended($h, $f) {
		$c = array_values(array_filter($f->copyright(), function ($x) { return $x['kind'] === 'shared' && !$x['chrome'] && $x['evidence']['verifiedEnglish']; }));
		if ($c) return $h->found($c, 'Copyright is shared between authors and the publisher.', 'DOAJ does not recommend shared copyright; consider author-retained copyright.');
		return $h->absent($f->items(array('about', 'custom', 'information', 'author_guidelines')), 'No shared-copyright arrangement was found on the pages read.');
	}

	private static function c_transparency_copyright_stated($h, $f) {
		$policy = self::c_guide_copyright_stated_separately(new DoajPaCheckRun($h->spec, $f), $f);
		$pdf = self::c_transparency_copyright_on_full_text(new DoajPaCheckRun($h->spec, $f), $f);
		if ($policy['status'] === 'pass' && $pdf['status'] === 'pass') return $h->merge($policy, $pdf, 'pass', 'Copyright terms are stated on the website and in the sampled full texts.');
		if ($policy['status'] === 'pass') return $h->merge($policy, $pdf, $pdf['status'] === 'unverifiable' ? 'unverifiable' : 'warning', 'Copyright terms are stated on the website; full texts: ' . $pdf['rationale'], 'Also state the copyright in every full-text article.');
		return $policy['status'] === 'fail' ? $h->merge($policy, $pdf, 'warning', $policy['rationale'], 'State the copyright terms on the website and in the content.') : $policy;
	}

	private static function nonOa($h, $f, $cond, $text) {
		$hits = $f->condition($cond);
		if (!$hits) return $h->notApplicable('No such content was identified on the pages read.');
		return $h->review($text, $hits);
	}

	private static function c_guide_non_oa_content_attributed($h, $f) { return $h->review('Whether non-open-access material inside articles is clearly indicated and attributed needs human review.'); }
	private static function c_copyright_third_party_material_attributed($h, $f) { return $h->review('Third-party material inside articles cannot be identified reliably; human review.'); }
	private static function c_guide_non_oa_free_content_limited($h, $f) { return self::nonOaTopic($h, $f, 'Non-open-access, free-to-read content is mentioned; whether it is limited, non-scholarly and clearly attributed is a human judgement.'); }
	private static function c_copyright_non_oa_content_policy($h, $f) {
		$hits = DoajPaFacts::verified($f->topic('non_oa_policy', $f->items(array('about', 'custom', 'information', 'author_guidelines'))));
		if (!$hits) return $h->notApplicable('No non-article, non-open-access material was identified.');
		return $h->pass($hits, 'A statement about non-open-access material is published (adequacy needs human review).');
	}
	private static function c_copyright_non_oa_material_marked($h, $f) { return self::nonOaTopic($h, $f, 'Whether the copyright and licensing of non-open-access material is marked on the content needs human review.'); }
	private static function nonOaTopic($h, $f, $text) {
		$hits = $f->topic('non_oa_policy', $f->items(null), false);
		if (!$hits) return $h->notApplicable('No non-open-access content was identified on the pages read.');
		return $h->review($text, $hits);
	}
	private static function c_copyright_republished_openly_licensed($h, $f) { return self::nonOa($h, $f, 'republished', 'Republished or translated articles are mentioned; whether they are openly licensed needs human review.'); }

	//
	// Activity, history, article dates
	//

	private static function dated($f) {
		$dates = array();
		foreach ($f->articles() as $a) {
			if ($a['published'] && $a['metaItem']) {
				foreach (explode("\n", $a['metaItem']['quote']) as $line) {
					if (preg_match('/^meta (citation_date|DC\.Date\.issued): /', $line)) { $dates[] = array('date' => $a['published'], 'evidence' => $a['metaItem'], 'quote' => $line, 'article' => $a); break; }
				}
			}
		}
		foreach ($f->issues() as $i) {
			if ($i['published'] && $i['evidence'] && preg_match('/Published:?\s*\n?\s*' . preg_quote($i['published'], '/') . '/', $i['evidence']['quote'], $m)) {
				$dates[] = array('date' => $i['published'], 'evidence' => $i['evidence'], 'quote' => $m[0], 'issue' => $i);
			}
		}
		usort($dates, function ($a, $b) { return strcmp($b['date'], $a['date']); });
		return $dates;
	}

	private static function c_guide_actively_publishing($h, $f) {
		$dates = array_values(array_filter(self::dated($f), function ($d) { return $d['evidence']['verifiedEnglish']; }));
		if (!$dates) return $h->unverifiable('No dated issue or article was read as verified-English evidence.');
		$cut = date('Y-m-d', strtotime($f->refDate . ' -365 days'));
		if ($dates[0]['date'] >= $cut) return $h->pass(array($dates[0]), 'Latest dated content: ' . $dates[0]['date'] . ' (within the 12 months before ' . $f->refDate . ').');
		if ($f->resourcesOf(array('archive')) && !$f->crawlTruncated()) return $h->missingAt(array($dates[0]['evidence']), 'The latest dated content read is from ' . $dates[0]['date'] . ', more than 12 months before ' . $f->refDate . '.', 'Publish research articles regularly (DOAJ: the journal must be actively publishing).');
		return $h->unverifiable('The latest dated content read is older than 12 months, but the archive was not read completely.');
	}

	private static function researchCount($f, $sinceDate) {
		$certain = 0; $uncertain = 0; $cite = array(); $complete = true;
		foreach ($f->issues() as $i) {
			if (!$i['published'] || $i['published'] < $sinceDate) continue;
			if (!$i['toc']) { $complete = false; continue; }
			foreach ($i['toc'] as $t) {
				$c = DoajPaFacts::articleClass($t['section']);
				if ($c === 'research') { $certain++; if (count($cite) < 3 && $i['structured']) $cite[] = array('evidence' => $i['structured'], 'quote' => $t['line']); }
				elseif ($c === 'uncertain') $uncertain++;
			}
		}
		return array($certain, $uncertain, $cite, $complete);
	}

	private static function c_guide_five_research_articles_per_year($h, $f) {
		$since = date('Y-m-d', strtotime($f->refDate . ' -365 days'));
		list($certain, $uncertain, $cite, $complete) = self::researchCount($f, $since);
		$cite = array_values(array_filter($cite, function ($c) { return $c['evidence']['verifiedEnglish']; }));
		if ($certain >= 5 && $cite) return $h->pass($cite, $certain . ' research articles in issues published in the 12 months before ' . $f->refDate . ' (sections read from the issue tables of contents).');
		$older = array_filter($f->issues(), function ($i) use ($since) { return $i['published'] && $i['published'] < $since; });
		if ($certain + $uncertain < 5 && $complete && $older && !$f->crawlTruncated()) {
			$ev = array_values(array_filter(array_map(function ($i) { return $i['evidence']; }, $f->issues())));
			return $h->missingAt($ev, 'Only ' . $certain . ' research articles (' . $uncertain . ' items of uncertain type) were published in issues of the 12 months before ' . $f->refDate . '.', 'Publish at least five research articles per year.');
		}
		return $h->unverifiable($certain . ' research articles and ' . $uncertain . ' items of uncertain type were counted in the last 12 months; the count cannot be completed from the issues read.');
	}

	private static function archiveYears($f) {
		$years = array(); $ev = null;
		foreach ($f->items(array('archive'), array('html_main_content')) as $e) {
			if (preg_match_all('/\((\d{4})\)/', $e['quote'], $m)) { $years = array_merge($years, array_map('intval', $m[1])); $ev = $e; }
		}
		return array($years, $ev);
	}

	private static function c_guide_new_journal_history($h, $f) {
		list($years, $ev) = self::archiveYears($f);
		$dates = self::dated($f);
		$earliest = $dates ? end($dates)['date'] : null;
		$refYear = (int) substr($f->refDate, 0, 4);
		if ($years && min($years) < $refYear - 1) return $h->notApplicable('The archive shows content from ' . min($years) . '; the journal is not newly launched.');
		if ($earliest && $earliest < date('Y-m-d', strtotime($f->refDate . ' -1 year'))) return $h->notApplicable('Content dated ' . $earliest . ' shows a publishing history of more than one year.');
		list($certain, $uncertain, $cite, $complete) = self::researchCount($f, '0000-00-00');
		if ($certain >= 10 && $cite) return $h->pass($cite, 'Newly launched journal with ' . $certain . ' research articles.');
		if (!$dates) return $h->unverifiable('The publishing history could not be dated from the evidence read.');
		return $h->review('The journal appears newly launched (earliest content read: ' . $earliest . ') with ' . $certain . ' research articles counted; the Guide requires more than one year of history or at least ten research articles.');
	}

	private static function c_guide_article_dates($h, $f) {
		$all = $f->articleDates();
		$read = array_values(array_filter($all, function ($a) { return $a['readable'] && $a['main'] && $a['main']['verifiedEnglish']; }));
		if (!$read) return $h->unverifiable('No sampled article was readable as verified-English text.');
		$complete = array_values(array_filter($read, function ($a) { return $a['status'] === 'complete'; }));
		$lines = array_map(function ($a) { return $a['url'] . ': ' . $a['message']; }, array_slice($read, 0, 10));
		$quotes = array();
		foreach ($complete as $a) foreach ($a['quotes'] as $q) if ($q['evidence']['verifiedEnglish'] && count($quotes) < 3) $quotes[] = $q;
		if (count($complete) === count($read) && $quotes) return $h->pass($quotes, 'All ' . count($read) . ' sampled articles display submitted, accepted and published dates.');
		$cite = array_values(array_filter(array_map(function ($a) { return $a['main']; }, $read)));
		return $h->missingAt($cite, (count($read) - count($complete)) . ' of ' . count($read) . ' sampled articles do not display a complete publication history. ' . implode(' | ', $lines) . '.',
			'Display submitted, accepted and published dates on articles (strongly recommended; missing dates are never a failure of a Must criterion).');
	}

	private static function c_transparency_publication_date($h, $f) {
		$all = $f->articleDates();
		$read = array_values(array_filter($all, function ($a) { return $a['main'] && $a['main']['verifiedEnglish']; }));
		if (!$read) return $h->unverifiable('No sampled article was readable as verified-English text.');
		$with = array_values(array_filter($read, function ($a) { return isset($a['dates']['published']); }));
		$quotes = array();
		foreach ($with as $a) foreach ($a['quotes'] as $q) if ($q['evidence']['verifiedEnglish'] && count($quotes) < 3) $quotes[] = $q;
		if (count($with) === count($read) && $quotes) return $h->pass($quotes, 'All ' . count($read) . ' sampled articles display a publication date.');
		return $h->missingAt(array_map(function ($a) { return $a['main']; }, $read), (count($read) - count($with)) . ' of ' . count($read) . ' sampled articles do not display a publication date.', 'Publish the date of publication with all research.');
	}

	private static function c_transparency_review_times_supported($h, $f) {
		$claims = DoajPaFacts::verified($f->topic('review_time_claim', $f->items(array('about', 'custom', 'information', 'author_guidelines', 'home'))));
		if (!$claims) return $h->notApplicable('No statement of peer review times was found.');
		if (!preg_match(DoajPaFacts::TOPICS['review_time_claim'], $claims[0]['sentence'], $m)) return $h->unverifiable('The review time claim could not be read.');
		$days = (int) $m[1] * (stripos($m[2], 'week') === 0 ? 7 : (stripos($m[2], 'month') === 0 ? 30 : 1));
		$intervals = array();
		foreach ($f->articleDates() as $a) {
			if (isset($a['dates']['submitted'], $a['dates']['accepted']) && $a['status'] === 'complete') {
				$intervals[] = (strtotime($a['dates']['accepted']) - strtotime($a['dates']['submitted'])) / 86400;
			}
		}
		if (count($intervals) < 3) return $h->unverifiable('The website states a review time of ' . $days . ' days, but fewer than three sampled articles show both submitted and accepted dates.', $claims);
		sort($intervals);
		$median = $intervals[(int) floor(count($intervals) / 2)];
		if ($median <= $days) return $h->pass($claims, 'Stated review time (' . $days . ' days) is consistent with the median submission-to-acceptance interval of ' . count($intervals) . ' sampled articles (' . (int) $median . ' days).');
		return $h->found($claims, 'Stated review time (' . $days . ' days) is shorter than the median submission-to-acceptance interval of ' . count($intervals) . ' sampled articles (' . (int) $median . ' days).', 'Support statements of review times with the published timeframes of accepted papers.');
	}

	//
	// Editorial endogeny (Stage 01 F4)
	//

	/** @return array per issue: issue, n (eligible research articles), confirmed, ambiguous, matches[], uncertainTypes, complete */
	public static function endogeny($f) {
		$board = $f->board(false);
		$reviewers = $f->board(true);
		$people = array_merge($board['people'], $reviewers['people']);
		$out = array();
		foreach (array_slice(array_values(array_filter($f->issues(), function ($i) { return (bool) $i['toc']; })), 0, 2) as $i) {
			$n = 0; $confirmed = 0; $ambiguous = 0; $matches = array(); $uncertainTypes = 0;
			foreach ($i['toc'] as $t) {
				$class = DoajPaFacts::articleClass($t['section']);
				if ($class === 'uncertain') { $uncertainTypes++; continue; }
				if ($class !== 'research') continue;
				$n++;
				$best = 'none'; $who = null;
				foreach ($t['authors'] as $author) {
					foreach ($people as $p) {
						$m = DoajPaText::nameMatch($author, $p['name']);
						if ($m === 'confirmed') { $best = 'confirmed'; $who = array($author, $p['name']); break 2; }
						if ($m === 'ambiguous' && $best === 'none') { $best = 'ambiguous'; $who = array($author, $p['name']); }
					}
				}
				if ($best === 'confirmed') $confirmed++;
				if ($best === 'ambiguous') $ambiguous++;
				if ($best !== 'none') $matches[] = array('article' => $t['url'], 'author' => $who[0], 'member' => $who[1], 'match' => $best, 'line' => $t['line']);
			}
			$out[] = array('issue' => $i, 'n' => $n, 'confirmed' => $confirmed, 'ambiguous' => $ambiguous, 'matches' => $matches, 'uncertainTypes' => $uncertainTypes,
				'boardPeople' => count($people));
		}
		return $out;
	}

	private static function c_guide_endogeny_25_issues($h, $f) {
		$units = self::endogeny($f);
		$board = $f->board(false);
		if (!$board['people']) return $h->unverifiable('No editorial board names could be read; endogeny cannot be computed.');
		if (count($units) < 2) return $h->unverifiable('Fewer than two issues with a readable table of contents were read (' . count($units) . '); the Guide requires each of the latest two issues.');
		$lines = array(); $over = array(); $unknown = false; $cite = array();
		foreach ($units as $u) {
			$label = ($u['issue']['title'] ?: $u['issue']['url']);
			if ($u['n'] === 0) { $unknown = true; $lines[] = $label . ': no research articles identified (' . $u['uncertainTypes'] . ' of uncertain type)'; continue; }
			$pct = round(100 * $u['confirmed'] / $u['n'], 1);
			$upper = round(100 * ($u['confirmed'] + $u['ambiguous']) / $u['n'], 1);
			$lines[] = $label . ': ' . $u['confirmed'] . '/' . $u['n'] . ' research articles with a confirmed editor/board/reviewer author (' . $pct . '%), ' . $u['ambiguous'] . ' uncertain name match(es) (upper bound ' . $upper . '%)' . ($u['uncertainTypes'] ? ', ' . $u['uncertainTypes'] . ' items of uncertain type excluded' : '');
			if ($u['issue']['evidence']) $cite[] = $u['issue']['evidence'];
			if ($u['issue']['structured']) {
				$cite[] = $u['issue']['structured'];
				foreach (array_slice($u['matches'], 0, 3) as $m) $cite[] = array('evidence' => $u['issue']['structured'], 'quote' => $m['line']);
			}
			if ($pct > 25) $over[] = $u;
			elseif ($upper > 25 || $u['uncertainTypes'] > 0) $unknown = true;
		}
		$text = implode('; ', $lines) . '. Matches: ' . implode('; ', array_map(function ($m) { return $m['author'] . ' ~ ' . $m['member'] . ' (' . $m['match'] . ', ' . $m['article'] . ')'; }, array_merge($units[0]['matches'], $units[1]['matches']))) . '.';
		if ($over) {
			$d = array();
			foreach ($over as $u) if ($u['issue']['structured']) $d[] = array('subtype' => 'endogeny-over-25', 'targetType' => 'issue', 'target' => $u['issue']['url'], 'criterionStatus' => 'fail', 'evidenceId' => $u['issue']['structured']['id']);
			return $h->outcome('fail', 'Endogeny exceeds 25% in at least one of the latest two issues. ' . $text, $cite, array(), $d, 'Reduce the share of research articles authored by editors, board members or reviewers to 25% or less per issue.');
		}
		if ($unknown) return $h->unverifiable('Endogeny cannot be established without human confirmation of uncertain names or article types. ' . $text, array());
		return $h->outcome('pass', 'Endogeny is at most 25% in each of the latest two issues. ' . $text, $cite, array(), array(), null, 'observation');
	}
	private static function c_guide_endogeny_minimised($h, $f) { return self::c_guide_endogeny_25_issues($h, $f); }

	private static function c_guide_endogeny_25_continuous($h, $f) {
		if ($f->issues() || !$h->confirmed('continuous')) return $h->notApplicable('The journal publishes in issues (the issue-based rule applies).');
		return $h->review('Continuous publication model: endogeny over the last calendar year needs the year\'s article list; human review.');
	}

	//
	// Metrics and indexing (Stage 01 F4; D-03-5)
	//

	private static function c_guide_no_unapproved_metrics($h, $f) {
		$claims = $f->metricClaims();
		$v = array_values(array_filter($claims, function ($c) { return $c['evidence']['verifiedEnglish']; }));
		$dis = array_values(array_filter($v, function ($c) { return $c['disallowed']; }));
		if ($dis) {
			$d = array();
			foreach ($dis as $c) $d[] = array('subtype' => 'unapproved-metric', 'targetType' => 'page', 'target' => $c['evidence']['url'], 'criterionStatus' => 'fail', 'evidenceId' => $c['evidence']['id']);
			$seen = array(); $d = array_values(array_filter($d, function ($x) use (&$seen) { if (isset($seen[$x['target']])) return false; $seen[$x['target']] = true; return true; }));
			return $h->outcome('fail', 'A metric from a service that the Guide does not list as allowed is displayed: "' . $dis[0]['quote'] . '".', $dis, array(), $d, 'Remove impact factors and ranking metrics from services other than those the Guide allows (Clarivate, Scopus, SCImago, Qualis, Norwegian Register, Eigenfactor, Google Scholar h5-index, Altmetric, Crossref Event Data).');
		}
		$unnamed = array_values(array_filter($v, function ($c) { return $c['unnamed']; }));
		if ($unnamed) return $h->review('An impact factor or ranking claim is displayed without naming the service; whether it is an allowed metric needs human review.', $unnamed);
		if ($claims && !$v) return $h->unverifiable('Metric claims appear only on pages whose language is not verified English.', $claims);
		return $h->absent($f->items(array('home', 'about', 'custom', 'information')), $v ? 'Only metrics the Guide lists as allowed are displayed.' : 'No impact factor or ranking claim was found on the pages read.');
	}

	private static function c_guide_avoid_unreliable_indexes($h, $f) {
		$claims = $f->indexingClaims();
		$text = $claims ? count($claims) . ' indexing claim(s) found, listed as unverified (e.g. "' . $claims[0]['quote'] . '")' : 'No indexing claim was found';
		return $h->unverifiable($text . '. No index is classified as unreliable: no approved, citable reference exists (decision D-03-5 PENDING APPROVAL).', array_slice($claims, 0, 3));
	}

	//
	// Persistent identifiers and links
	//

	private static function c_guide_persistent_identifiers($h, $f) {
		$arts = $f->articles();
		if (!$arts) return $h->unverifiable('No article page was sampled.');
		$ids = array_values(array_filter($f->identifiers(), function ($x) { return $x['evidence']['verifiedEnglish']; }));
		$with = array();
		foreach ($ids as $x) $with[$x['evidence']['resourceId']] = $x;
		if (count($with) >= count($arts)) return $h->pass(array_values($with), 'All ' . count($arts) . ' sampled articles carry a persistent identifier (' . implode(', ', array_unique(array_map(function ($x) { return $x['type']; }, $ids))) . ').');
		$cite = array_values(array_filter(array_map(function ($a) { return $a['main']; }, $arts)));
		return $h->missingAt($cite, (count($arts) - count($with)) . ' of ' . count($arts) . ' sampled articles show no persistent identifier (DOI, ARK or Handle).', 'Assign persistent identifiers (for example DOIs) to articles.');
	}

	private static function c_guide_persistent_links_resolve($h, $f) {
		return $h->unverifiable('Resolving persistent identifiers needs outbound requests to resolvers (doi.org), which are not approved (decision D-03-4 PENDING APPROVAL).');
	}

	//
	// Human judgement and conditional journal types
	//

	private static function c_guide_research_audience($h, $f) { return $h->review('The primary audience is a human judgement; the aims, scope and readership text is quoted.', $f->topic('target_readership', $f->items(array('about', 'custom', 'home')))); }
	private static function c_guide_no_intrusive_advertising($h, $f) { return $h->review('Whether advertising is intrusive is a human judgement.', $f->topic('advertising', $f->items(null, array('html_main_content', 'html_text_extraction')))); }
	private static function c_transparency_journal_name($h, $f) { return $h->review('Whether the journal name is unique and not misleading is a human judgement.', $f->siteTitles()); }
	private static function c_guide_book_review_only($h, $f) {
		$sections = array();
		foreach ($f->issues() as $i) foreach ($i['toc'] as $t) $sections[$t['section']] = isset($sections[$t['section']]) ? $sections[$t['section']] + 1 : 1;
		$text = $sections ? 'Sections in the issues read: ' . implode(', ', array_map(function ($k, $v) { return ($k ?: '(none)') . ' ' . $v; }, array_keys($sections), $sections)) . '.' : 'No table of contents was read.';
		return $h->review('Whether the journal publishes only book reviews is a human judgement. ' . $text);
	}

	private static function c_guide_multilingual_same_information($h, $f) {
		$l = $f->localeSwitches();
		if (!$l) return $h->notApplicable('No language switch was found; the site appears to be in one language.');
		return $h->review('The site offers other interface languages; this English-only plugin does not compare language versions (ADR-011). Human verification is needed.', $l);
	}

	private static function conditional($h, $f, $cond, $label, $text) {
		if (!$h->confirmed($cond)) return $h->notApplicable('The journal is not stated to be ' . $label . ' on the pages read.');
		return $h->review($text, $f->condition($cond));
	}

	private static function c_guide_special_issue_eic_responsible($h, $f) { return self::conditional($h, $f, 'special_issues', 'publishing special issues', 'Special issues are mentioned; the editor-in-chief\'s responsibility and scope fit need human review.'); }
	private static function c_guide_special_issue_guest_editor_credentials($h, $f) { return self::conditional($h, $f, 'special_issues', 'publishing special issues', 'Checking of guest editors\' credentials needs human review.'); }
	private static function c_guide_special_issue_guest_editor_oversight($h, $f) { return self::conditional($h, $f, 'special_issues', 'publishing special issues', 'Oversight of guest editors needs human review.'); }
	private static function c_guide_special_issue_guest_editor_papers($h, $f) { return self::conditional($h, $f, 'special_issues', 'publishing special issues', 'The share of guest-editor papers in a special issue needs the issue\'s author list and guest editors; human review.'); }
	private static function c_guide_special_issue_oversight_labelled($h, $f) {
		if (!$h->confirmed('special_issues')) return $h->notApplicable('The journal is not stated to publish special issues.');
		$labelled = DoajPaText::find(DoajPaFacts::verified($f->items(array('issue', 'article'), array('html_main_content'))), '/\bspecial issue\b/i');
		if ($labelled) return $h->pass($labelled, 'Special issue content is labelled as such (editorial oversight needs human review).');
		return $h->review('Special issues are mentioned, but no special issue label was found on the issues and articles read.');
	}

	private static function c_guide_arts_humanities_editorial_review($h, $f) {
		if (!$h->confirmed('editorial_review') || !$h->confirmed('arts_humanities')) return $h->notApplicable('Editorial review in an arts and humanities journal is not stated.');
		return $h->presence('two_editors', null, 4, 'that editorial review is handled by at least two editors', 'State that editorial review is handled by a minimum of two editors.');
	}

	private static function caseReport($h, $f, $topic, $what, $rec) {
		if (!$h->confirmed('case_reports')) return $h->notApplicable('The journal is not stated to publish case reports.');
		return $h->presence($topic, null, 4, $what, $rec);
	}
	private static function c_guide_case_report_consent_policy($h, $f) { return self::caseReport($h, $f, 'consent_policy', 'a policy on consent to publication', 'Publish a consent-to-publication policy for case details and images.'); }
	private static function c_guide_case_report_anonymisation($h, $f) { return self::caseReport($h, $f, 'anonymisation', 'a policy on identifiable information and anonymisation', 'Publish a policy on identifiable participant information and anonymisation.'); }
	private static function c_guide_case_report_reviewer_guidelines($h, $f) { return self::caseReport($h, $f, 'case_report_review', 'reviewer guidelines specific to case reports', 'Publish reviewer guidelines specific to case reports.'); }
	private static function c_guide_case_report_identified_care($h, $f) { return self::caseReport($h, $f, 'care_guideline', 'use of the CARE reporting guidelines', 'Encourage or require the CARE reporting guidelines.'); }

	private static function c_guide_conference_proceedings($h, $f) { return self::conditional($h, $f, 'conference', 'a conference proceedings journal', 'Conference proceedings journal: ISSN and permanent board oversight need human review.'); }
	private static function c_guide_conference_papers_peer_reviewed($h, $f) { return self::conditional($h, $f, 'conference', 'a conference proceedings journal', 'Peer review of conference papers needs human review.'); }
	private static function c_guide_conference_full_text($h, $f) { return $h->confirmed('conference') ? $h->access('open') : $h->notApplicable('The journal is not stated to be a conference proceedings journal.'); }

	private static function c_guide_flipped_disclosure($h, $f) {
		if (!$h->confirmed('flipped')) return $h->notApplicable('The journal is not stated to have flipped to open access.');
		return $h->elements(array('date of the change' => '/\b(19|20)\d{2}\b/', 'availability of archive material' => '/\b(archive|back ?issues?|previous issues?)\b[^.!?\n]{0,60}\b(open access|free|paywall\w*|subscription)\b/i',
			'reuse rights of archive material' => '/\b(all rights reserved|open licen[cs]e|creative commons)\b/i'), 'flipped-journal disclosures');
	}
	private static function c_guide_flipped_content_oa($h, $f) { return $h->confirmed('flipped') ? $h->access('open') : $h->notApplicable('The journal is not stated to have flipped to open access.'); }
	private static function c_guide_flipped_history($h, $f) { return self::conditional($h, $f, 'flipped', 'a flipped journal', 'The post-flip publishing history (more than a year, or ten open access research articles) needs the flip date; human review.'); }
	private static function c_guide_mirror_journal($h, $f) {
		if (!$h->confirmed('mirror')) return $h->notApplicable('The journal is not stated to be a mirror journal.');
		return $h->unverifiable('Mirror journal: comparing ISSNs with the subscription title needs an ISSN Portal lookup (decision D-03-4 PENDING APPROVAL).', $f->condition('mirror'));
	}
	private static function c_guide_s2o_disclosure($h, $f) {
		if (!$h->confirmed('s2o')) return $h->notApplicable('The journal is not stated to use Subscribe to Open.');
		return $h->elements(array('year of transition' => '/\b(19|20)\d{2}\b/', 'current-year content is open access' => '/\bcurrent\b[^.!?\n]{0,40}\bopen access\b/i',
			'availability of previous content' => '/\b(previous|earlier|back)\b[^.!?\n]{0,40}\b(open access|free|paywall\w*)\b/i', 'reuse rights of previous content' => '/\b(all rights reserved|open licen[cs]e|creative commons)\b/i',
			'copyright status of previous content' => '/\bcopyright\b/i'), 'Subscribe to Open disclosures');
	}
	private static function c_guide_s2o_basic_criteria_stated($h, $f) { return $h->confirmed('s2o') ? self::licenceStated($h, $f, 'licensing terms') : $h->notApplicable('The journal is not stated to use Subscribe to Open.'); }
	private static function c_guide_s2o_content_stays_open($h, $f) { return self::conditional($h, $f, 's2o', 'a Subscribe to Open journal', 'A commitment that content stays open in perpetuity cannot be verified; human review.'); }
	private static function c_guide_s2o_target_met($h, $f) { return self::conditional($h, $f, 's2o', 'a Subscribe to Open journal', 'Whether the S2O target has been met is not public website evidence; human review.'); }
	private static function c_guide_s2o_year_fully_open($h, $f) { return $h->confirmed('s2o') ? $h->access('open') : $h->notApplicable('The journal is not stated to use Subscribe to Open.'); }
	private static function c_guide_s2o_no_interruption($h, $f) { return self::conditional($h, $f, 's2o', 'a Subscribe to Open journal', 'The yearly S2O decision process is internal; human review.'); }

	private static function c_transparency_access_method($h, $f) {
		$restricted = $h->access('open');
		$topic = DoajPaFacts::verified($f->topic('access_method', $f->items(array('about', 'custom', 'information', 'home')), false));
		if ($restricted['status'] !== 'fail' && !$topic) return $h->notApplicable('No restricted online content was found.');
		if ($topic) return $h->pass($topic, 'The method of gaining access to restricted content is described.');
		return $h->missing(null, 'Some sampled content is restricted, but the access method is not described.', 'Describe how to gain access to any content that is not freely accessible.');
	}

	private static function c_transparency_offline_versions($h, $f) {
		$print = DoajPaFacts::verified($f->topic('print_version', $f->items(array('about', 'custom', 'information', 'home')), false));
		if (!$print) return $h->notApplicable('No print or offline version is mentioned.');
		$charges = array_values(array_filter($print, function ($x) { return preg_match('/\b(price|cost|charges?|fees?|free|subscription)\b/i', $x['sentence']) || preg_match(DoajPaFacts::AMOUNT, $x['sentence']); }));
		if ($charges) return $h->pass($charges, 'The print version and its charges are described.');
		return $h->found($print, 'A print version is mentioned without its charges.', 'Describe offline versions and any associated charges.');
	}

	private static function c_transparency_affiliated_organisation_links($h, $f) {
		$hits = DoajPaFacts::verified($f->topic('affiliated_org', $f->items(array('about', 'custom', 'information', 'home')), false));
		if (!$hits) return $h->notApplicable('No affiliation with a society, institution or sponsor is stated.');
		return $h->review('An affiliated organisation is named; whether its website is linked needs human review (links are not stored as evidence text).', $hits);
	}
}

/**
 * One criterion evaluation: outcome builders shared by the handlers.
 */
class DoajPaCheckRun {

	public $spec;
	/** @var DoajPaFacts */
	public $f;

	public function __construct($spec, $f) {
		$this->spec = $spec;
		$this->f = $f;
	}

	/** @return boolean Whether a conditional journal type is confirmed by public, verified-English text */
	public function confirmed($condition) {
		return (bool) DoajPaFacts::verified(array_map(function ($h) { return $h['evidence']; }, $this->f->condition($condition)));
	}

	private function limit($extra = '') {
		return trim(DoajPaChecks::LIMIT . ' ' . $this->spec['abstainWhen'] . '. ' . $extra);
	}

	/** Build an outcome. $hits: list of ['evidence', 'quote'] (quotes) or evidence items (cited without quote). */
	public function outcome($status, $rationale, $hits, $clear = array(), $detections = array(), $rec = null, $basis = 'quote') {
		$quotes = array(); $ids = array();
		foreach ($hits as $x) {
			if (isset($x['evidence'], $x['quote'])) {
				if (mb_strlen($x['quote'], 'UTF-8') >= DoajPaOutcomeGuard::MIN_QUOTE) $quotes[] = array('evidenceId' => $x['evidence']['id'], 'text' => $x['quote']);
				$ids[] = $x['evidence']['id'];
			} elseif (isset($x['evidence']['id'])) {
				$ids[] = $x['evidence']['id'];
			} elseif (isset($x['id'])) {
				$ids[] = $x['id'];
			}
		}
		$quotes = array_slice($quotes, 0, 5);
		$ids = array_values(array_unique(array_slice($ids, 0, 8)));
		return array('status' => $status, 'rationale' => $rationale . ($rec ? ' Recommended correction: ' . $rec . ' (DOAJ: "' . $this->spec['quote'] . '")' : ''),
			'interpretationLimit' => $this->limit(), 'evidenceIds' => $ids, 'quotes' => $quotes, 'detections' => $detections, 'verifiedClear' => $clear, 'basis' => $basis);
	}

	private function clearFor($hits) {
		foreach ($hits as $x) {
			$e = isset($x['evidence']) ? $x['evidence'] : $x;
			if (isset($e['id']) && $e['verifiedEnglish']) return array(array('subtype' => 'not-met', 'targetType' => 'journal', 'target' => null, 'evidenceId' => $e['id']));
		}
		return array();
	}

	private function detectionFor($items, $status) {
		foreach ($items as $x) {
			$e = isset($x['evidence']) ? $x['evidence'] : $x;
			if (isset($e['id'])) return array(array('subtype' => 'not-met', 'targetType' => 'journal', 'target' => null, 'criterionStatus' => $status, 'evidenceId' => $e['id']));
		}
		return array();
	}

	/** Pass with verbatim quotes from verified-English evidence (the guard checks them). */
	public function pass($hits, $rationale) {
		return $this->outcome('pass', $rationale, $hits, $this->clearFor($hits));
	}

	/** Pass on a fact of the retrieval itself (cites verified pages; ADR-010 R1.8). */
	public function observed($items, $rationale) {
		$v = DoajPaFacts::verified(array_values(array_filter($items)));
		if (!$v) return $this->unverifiable('The supporting pages are not verified English (ADR-011).');
		return $this->outcome('pass', $rationale, $v, $this->clearFor($v), array(), null, 'observation');
	}

	/**
	 * Pass because nothing non-conforming was found on the verified pages searched (ADR-010 R1.8). Not established
	 * when the crawl was cut short or a relevant page could not be read as verified English (F5, ADR-011).
	 */
	public function absent($items, $rationale) {
		$v = DoajPaFacts::verified($items);
		if (!$v) return $this->unverifiable('No relevant page was read as verified English (ADR-011); absence cannot be established.');
		if ($this->f->crawlTruncated()) return $this->unverifiable('The crawl stopped at a page or time limit; absence cannot be established.');
		$types = $this->spec['evidence'];
		foreach ($items as $e) if (!in_array($e['pageType'], $types, true)) $types[] = $e['pageType'];
		$unread = $this->f->unreadOf($types);
		if ($unread) {
			return $this->unverifiable('Nothing non-conforming was found on the verified-English pages, but ' . count($unread) . ' relevant page(s) could not be read as verified English (ADR-011), so absence is not established: '
				. implode(', ', array_slice(array_map(function ($r) { return $r['url'] . ' (' . ($r['reason'] !== null ? $r['reason'] : $r['acquisition']) . ')'; }, $unread), 0, 5)) . '.');
		}
		return $this->outcome('pass', $rationale . ' Pages searched: ' . count($v) . '.', array_slice($v, 0, 8), $this->clearFor($v), array(), null, 'absence');
	}

	/** A non-conforming statement was found (M/C: fail; R: warning). */
	public function found($hits, $rationale, $rec) {
		$status = $this->spec['level'] === 'R' ? 'warning' : 'fail';
		return $this->outcome($status, $rationale, $hits, array(), $this->detectionFor($hits, $status), $rec);
	}

	/**
	 * Required information missing on the verified pages that were read. Not established (unverifiable) when the
	 * crawl was cut short or when any relevant page could not be read as verified English (F5, ADR-011).
	 * @param $types string[]|null relevant page types (default: the criterion's evidence types)
	 */
	public function missing($items, $rationale, $rec, $types = null) {
		$types = $types ?: $this->spec['evidence'];
		$items = $items === null ? $this->f->items($types) : $items;
		foreach ($items as $e) if (!in_array($e['pageType'], $types, true)) $types[] = $e['pageType'];
		$v = DoajPaFacts::verified($items);
		if (!$v) return $this->unverifiable('No relevant page (' . implode(', ', $types) . ') was read as verified English (ADR-011); a missing fact is not established.');
		if ($this->f->crawlTruncated()) return $this->unverifiable('The crawl stopped at a page or time limit; the information may be on a page that was not read.');
		$unread = $this->f->unreadOf($types);
		if ($unread) {
			return $this->unverifiable('Not found on the verified-English pages, but ' . count($unread) . ' relevant page(s) could not be read as verified English (ADR-011), so a missing fact is not established: '
				. implode(', ', array_slice(array_map(function ($r) { return $r['url'] . ' (' . ($r['reason'] !== null ? $r['reason'] : $r['acquisition']) . ')'; }, $unread), 0, 5)) . '.');
		}
		$status = $this->spec['level'] === 'R' ? 'warning' : 'fail';
		return $this->outcome($status, $rationale . ' Pages searched: ' . implode(', ', array_slice(array_unique(array_map(function ($e) { return $e['url']; }, $v)), 0, 8)) . '.',
			array_slice($v, 0, 8), array(), $this->detectionFor($v, $status), $rec);
	}

	/** Missing/non-conforming at specific items (already selected). */
	public function missingAt($items, $rationale, $rec, $first = null) {
		$v = DoajPaFacts::verified(array_values(array_filter($items)));
		if (!$v) return $this->unverifiable('The relevant pages are not verified English (ADR-011).');
		$status = $this->spec['level'] === 'R' ? 'warning' : 'fail';
		return $this->outcome($status, $rationale, $first ? array_merge(array($first), $v) : $v, array(), $this->detectionFor($v, $status), $rec);
	}

	public function contradiction($hits, $rationale, $rec) {
		return $this->outcome('contradiction', $rationale, $hits, array(), $this->detectionFor($hits, 'contradiction'), $rec);
	}

	public function unverifiable($rationale, $hits = array()) {
		return $this->outcome('unverifiable', $rationale, array_slice($hits, 0, 3));
	}

	public function review($rationale, $hits = array()) {
		return $this->outcome('for_doaj_review', $rationale . ' (Human judgement reserved.)', array_slice($hits, 0, 3));
	}

	public function notApplicable($rationale) {
		// "Does not apply" needs readable evidence: with no verified-English page of the relevant types, it is unknown.
		if (!DoajPaFacts::verified($this->f->items($this->spec['evidence'], array('html_main_content', 'pdftotext_extraction', 'html_structured')))) {
			return $this->unverifiable('Whether this applies cannot be determined: no relevant page (' . implode(', ', $this->spec['evidence']) . ') was read as verified English (ADR-011). ' . $rationale);
		}
		return $this->outcome('not_applicable', $rationale . ' (Condition: ' . (isset($this->spec['condition']) ? $this->spec['condition'] : 'n/a') . '.)', array());
	}

	/**
	 * Presence of a topic passage: pass (verified quote, optionally within $maxDepth clicks of the homepage), otherwise
	 * unverifiable or missing (see missing()).
	 */
	public function presence($topic, $types, $minWords, $what, $rec, $maxDepth = null, $skipNegated = true) {
		// $types: null (the criterion's evidence page types), a list of page types (hits on earlier types first), or a list of evidence items.
		$order = $types === null ? $this->spec['evidence'] : $types;
		$items = is_array($types) && isset($types[0]) && is_array($types[0]) ? $types : $this->f->items($order);
		$hits = array_values(array_filter($this->f->topic($topic, $items, $skipNegated), function ($x) use ($minWords) { return DoajPaText::words($x['sentence']) >= $minWords; }));
		if (isset($order[0]) && is_string($order[0])) {
			$rank = array_flip($order);
			$pos = array();
			foreach ($hits as $i => $x) $pos[$i] = array(isset($rank[$x['evidence']['pageType']]) ? $rank[$x['evidence']['pageType']] : 99, $i);
			uksort($hits, function ($a, $b) use ($pos) { return $pos[$a] < $pos[$b] ? -1 : 1; });
			$hits = array_values($hits);
		}
		$v = DoajPaFacts::verified($hits);
		if ($v) {
			if ($maxDepth !== null) {
				$f = $this->f;
				$near = array_values(array_filter($v, function ($x) use ($f, $maxDepth) {
					$d = isset($f->resources[$x['evidence']['resourceId']]['depth']) ? $f->resources[$x['evidence']['resourceId']]['depth'] : null;
					return $d === null || $d <= $maxDepth;
				}));
				if (!$near) return $this->found($v, 'Found, but not within ' . $maxDepth . ' clicks of the homepage: ' . $what . '.', 'Link it from the homepage navigation.');
				$v = $near;
			}
			return $this->pass($v, 'Found: ' . $what . '.');
		}
		if ($hits) return $this->unverifiable('Found only on pages whose language is not verified English (ADR-011): ' . $what . '.', $hits);
		return $this->missing($items, 'Not found on the pages read: ' . $what . '.', $rec, isset($order[0]) && is_string($order[0]) ? $order : null);
	}

	/** Several elements (each a pattern); pass when all are present, otherwise warning/fail listing the missing ones. */
	public function elements($patterns, $what) {
		$items = DoajPaFacts::verified($this->f->items($this->spec['evidence']));
		if (!$items) return $this->unverifiable('No relevant page was read as verified English (ADR-011).');
		$found = array(); $missing = array();
		foreach ($patterns as $label => $rx) {
			$hit = DoajPaText::find($items, $rx);
			if ($hit) $found[$label] = $hit[0]; else $missing[] = $label;
		}
		if (!$missing) return $this->pass(array_values($found), 'All ' . count($patterns) . ' ' . $what . ' were found.');
		if (!$found) return $this->missing(null, 'None of the ' . $what . ' were found (' . implode(', ', $missing) . ').', 'Publish ' . $what . ': ' . implode(', ', $missing) . '.');
		$unread = $this->f->unreadOf($this->spec['evidence']);
		if ($unread || $this->f->crawlTruncated()) {
			return $this->unverifiable(count($found) . ' of ' . count($patterns) . ' ' . $what . ' were found; the others (' . implode(', ', $missing) . ') may be on '
				. ($unread ? count($unread) . ' relevant page(s) that could not be read as verified English (ADR-011)' : 'pages not read (crawl limit)') . '.', array_values($found));
		}
		return $this->outcome($this->spec['level'] === 'R' ? 'warning' : 'fail', count($found) . ' of ' . count($patterns) . ' ' . $what . ' were found; missing: ' . implode(', ', $missing) . '.',
			array_values($found), array(), $this->detectionFor(array_values($found), $this->spec['level'] === 'R' ? 'warning' : 'fail'), 'Publish the missing ' . $what . ': ' . implode(', ', $missing) . '.');
	}

	/**
	 * Access sample: every sampled article page, galley and file was freely retrieved (no login, payment or 401/403).
	 * $mode open | registration | hybrid (wording only).
	 */
	public function access($mode) {
		$res = $this->f->resourcesOf(array('article', 'galley', 'file'));
		if (!$res) return $this->unverifiable('No article page or full-text file was sampled.');
		$blocked = array_values(array_filter($res, function ($r) {
			return in_array($r['httpStatus'], array(401, 402, 403), true) || ($r['reason'] !== null && strpos($r['reason'], 'access_restricted') === 0)
				|| preg_match('#/(login|user/register)\b#', (string) $r['finalUrl']);
		}));
		$cite = DoajPaFacts::verified($this->f->items(array('article', 'galley', 'file')));
		if ($blocked) {
			$ev = $cite ?: $this->f->items(array('article'), array('html_main_content', 'html_text_extraction'));
			return $this->missingAt($ev, count($blocked) . ' of ' . count($res) . ' sampled article pages or files required login, registration or payment: ' . implode(', ', array_slice(array_map(function ($r) { return $r['url']; }, $blocked), 0, 5)) . '.',
				$mode === 'hybrid' ? 'Make every article open access; DOAJ does not index hybrid journals.' : 'Make the full text of all content freely available without registration or delay.');
		}
		if (!$cite) return $this->unverifiable('Sampled articles were retrieved, but none is verified English (ADR-011).');
		return $this->outcome('pass', 'All ' . count($res) . ' sampled article pages and full-text files were retrieved without login, registration or payment (a sample cannot prove "all content").',
			array_slice($cite, 0, 8), $this->clearFor($cite), array(), null, 'observation');
	}

	/** Merge two sub-outcomes into one (evidence and quotes of both). */
	public function merge($a, $b, $status, $rationale, $rec = null) {
		$ids = array_values(array_unique(array_merge($a['evidenceIds'], $b['evidenceIds'])));
		$quotes = array_slice(array_merge($a['quotes'], $b['quotes']), 0, 5);
		$clear = $status === 'pass' ? array_slice(array_merge($a['verifiedClear'], $b['verifiedClear']), 0, 1) : array();
		$det = in_array($status, array('fail', 'warning', 'contradiction'), true) && $ids ? array(array('subtype' => 'not-met', 'targetType' => 'journal', 'target' => null, 'criterionStatus' => $status, 'evidenceId' => $ids[0])) : array();
		return array('status' => $status, 'rationale' => $rationale . ($rec ? ' Recommended correction: ' . $rec . ' (DOAJ: "' . $this->spec['quote'] . '")' : ''),
			'interpretationLimit' => $this->limit(), 'evidenceIds' => $ids, 'quotes' => $quotes, 'detections' => $det, 'verifiedClear' => $clear,
			'basis' => (!$quotes && $status === 'pass') ? 'observation' : 'quote');
	}
}
