<?php

/**
 * @file classes/reports/DoajPaReportModel.inc.php
 *
 * @class DoajPaReportModel
 * @brief The content of an assessment report (Stage 01 F7 item 7, AC-18), built from ONE stored completed run. Pure.
 *  The HTML, PDF and DOCX renderers all render this one model, so the three formats carry the same content (parity).
 *  Nothing is invented or rewritten: statuses, rationales, quotes, URLs and answers are the stored values; the report
 *  adds only fixed English headings, labels, the method text and the disclaimers. No accept/reject decision, score,
 *  probability or readiness percentage appears (PROD-09).
 *
 *  Model: title, subtitle, fileStem, sections[] = {heading, blocks[]}; a block is one of
 *    {type: notice, text} | {type: p, text} | {type: kv, rows: [[label, value]]} | {type: list, items: [text]}
 *    | {type: table, head: [text], rows: [[text]], widths: [fraction]} | {type: subheading, text}
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/DoajPaActionPlan.inc.php');

class DoajPaReportModel {

	const VERSION = 'report-model-1';
	const NOT_OFFICIAL = 'This is an internal quality-improvement report produced by the DOAJ Pre-Assessment plugin. It is not a DOAJ decision, not an application to DOAJ and not a prediction of the outcome of an application. Only DOAJ is authorized to make official decisions on journal applications.';
	const PRE_PRODUCTION = 'PRE-PRODUCTION MODE. This run read the journal website on a private or loopback network address, as enabled by the server administrator, before the website is published on its production server. The results describe that non-public copy of the website, not the public journal website. Run the assessment again on the public website after installation.';
	const ENGLISH_ONLY = 'ENGLISH-ONLY JOURNAL CONTENT. This plugin evaluates only publicly accessible English-language journal websites. It uses deterministic rules, not Artificial Intelligence or automatic translation. This limitation applies to the plugin, not to DOAJ eligibility: DOAJ accepts qualifying journals published in any language.';
	const METHOD = 'The plugin read the public pages of the journal website (HTML pages and public PDF files) in one bounded crawl, stored them as evidence and evaluated them with deterministic rules: curated English patterns, verbatim quotes, date and ISSN checks and cross-page comparisons. It does not read OJS private data, editorial workflows or submissions, and it makes no external registry lookups. A result is "unverifiable" when the relevant pages could not be read as verified English text or the crawl was incomplete; an unread page is never treated as a missing fact.';
	const ATTRIBUTION = 'DOAJ text quoted in the results: excerpts, wording unchanged, from the DOAJ Guide to applying, Licensing & copyright and Application form (© DOAJ, CC BY-SA 4.0, https://creativecommons.org/licenses/by-sa/4.0/) and from the Principles of Transparency and Best Practice in Scholarly Publishing (COPE, DOAJ, OASPA, WAME, CC BY 4.0, https://creativecommons.org/licenses/by/4.0/). DOAJ has not reviewed or endorsed this plugin.';
	const REFERENCES = array(
		array('DOAJ Guide to applying (primary reference)', 'https://doaj.org/apply/guide/'),
		array('Principles of Transparency and Best Practice in Scholarly Publishing', 'https://doaj.org/apply/transparency/'),
		array('DOAJ Licensing & copyright', 'https://doaj.org/apply/copyright-and-licensing/'),
	);
	const STATUS = array('pass' => 'Pass', 'fail' => 'Fail', 'warning' => 'Warning', 'contradiction' => 'Contradiction', 'unverifiable' => 'Unverifiable',
		'for_doaj_review' => 'For DOAJ review', 'not_applicable' => 'Not applicable');
	const STATUS_ORDER = array('contradiction', 'fail', 'warning', 'unverifiable', 'for_doaj_review', 'pass', 'not_applicable');
	const LEVEL = array('M' => 'Must', 'R' => 'Recommended', 'C' => 'Conditional', 'H' => 'Human judgement');
	const LIFECYCLE = array('open' => 'Open', 'still_open' => 'Still open', 'needs_verification' => 'Needs verification', 'resolved_verified' => 'Resolved (verified)',
		'reopened' => 'Reopened', 'superseded' => 'Superseded');
	const ANSWER = array('supported' => 'Supported by the website', 'not_found' => 'Not found', 'needs_verification' => 'Needs verification', 'not_yet_assessed' => 'Not yet assessed');
	const ACTION = array('planned' => 'Planned', 'in_progress' => 'In progress', 'done_pending_verification' => 'Done, pending verification', 'cancelled' => 'Cancelled');
	const MAX_GAPS = 150;

	/**
	 * @param $in array journal {name, url}, run (DoajPaReadService::run), actions (rows), quotes (evidenceId => quote),
	 *  comparison (DoajPaReadService::compare or null), formGuide entries (rows), generatedAtUtc, generatedBy, pluginVersion
	 * @return array model
	 */
	public static function build($in) {
		$run = $in['run'];
		$coverage = is_array($run['coverageData']) ? $run['coverageData'] : array();
		$report = isset($coverage['assessment']['report']) && is_array($coverage['assessment']['report']) ? $coverage['assessment']['report'] : array();
		$candidate = !empty($report['candidate']);
		$approved = !empty($run['rulebookApproved']);
		$plan = DoajPaActionPlan::build($run, $in['actions']);
		$sections = array();

		// 1. About this report
		$about = array(array('type' => 'notice', 'text' => self::NOT_OFFICIAL));
		if (isset($report['disclaimer']) && is_string($report['disclaimer']) && $report['disclaimer'] !== '') $about[] = array('type' => 'notice', 'text' => $report['disclaimer']);
		elseif (!$approved) $about[] = array('type' => 'notice', 'text' => 'Not a DOAJ assessment result. No approved DOAJ rule set was applied.');
		if ($candidate) $about[] = array('type' => 'notice', 'text' => 'Candidate criteria - PENDING APPROVAL. The criteria applied in this run are a proposed dataset for internal testing. They are not approved DOAJ rules and are not presented as an approved DOAJ assessment.');
		if (!empty($coverage['preProductionMode'])) $about[] = array('type' => 'notice', 'text' => self::PRE_PRODUCTION);
		$about[] = array('type' => 'p', 'text' => self::ENGLISH_ONLY);
		$sections[] = array('heading' => 'About this report', 'blocks' => $about);

		// 2. Assessment run
		$dataset = isset($coverage['assessment']['candidateDataset']) && is_array($coverage['assessment']['candidateDataset']) ? $coverage['assessment']['candidateDataset']
			: (isset($coverage['assessment']['approvedDataset']) && is_array($coverage['assessment']['approvedDataset']) ? $coverage['assessment']['approvedDataset'] : null);
		$kv = array(
			array('Journal', (string) $in['journal']['name']),
			array('Journal website', (string) (isset($coverage['homepage']) ? $coverage['homepage'] : $in['journal']['url'])),
			array('Assessment run', 'Run ' . $run['run_number'] . ' (ID ' . $run['run_id'] . '), status ' . $run['status']),
			array('Started (UTC)', (string) $run['started_at_utc']),
			array('Completed (UTC)', (string) $run['completed_at_utc']),
			array('Executed by', (string) $run['executor_username']),
			array('Rule set', $run['rulebook_version'] . ($approved ? ' (approved by the product owner of this plugin; not a DOAJ rule set)' : ($candidate ? ' (candidate, PENDING APPROVAL)' : ' (no approved DOAJ rule set)'))),
			array('Normative baseline', $dataset ? 'Criteria dataset ' . $dataset['version'] . ' (' . strtoupper((string) $dataset['status']) . ', ' . $dataset['approval'] . '); engine ' . $dataset['engine'] . '; DOAJ sources captured 2026-10-10'
				: ($approved ? 'Approved criteria of rule set ' . $run['rulebook_version'] : 'None: no criteria were evaluated')),
			array('Extraction version', (string) $run['extraction_version']),
			array('Sealed snapshot (SHA-256)', (string) $run['snapshot_digest'] . ($run['snapshotVerified'] === true ? ' (verified when the report was generated)' : ($run['snapshotVerified'] === false ? ' (VERIFICATION FAILED)' : ''))),
			array('Previous run', $run['previousRunNumber'] !== null ? 'Run ' . $run['previousRunNumber'] : 'None'),
			array('Report generated (UTC)', (string) $in['generatedAtUtc'] . ' by ' . $in['generatedBy'] . ', plugin ' . $in['pluginVersion'] . ', ' . self::VERSION),
		);
		$sections[] = array('heading' => 'Assessment run', 'blocks' => array(array('type' => 'kv', 'rows' => $kv)));

		// 3. Method, sources inspected and not inspected
		$resources = $run['resources'];
		$read = 0; $notRead = array(); $byStatus = array();
		foreach ($resources as $r) {
			$byStatus[$r['acquisition_status']] = (isset($byStatus[$r['acquisition_status']]) ? $byStatus[$r['acquisition_status']] : 0) + 1;
			if ($r['acquisition_status'] === 'retrieved' && $r['skip_reason'] === null) $read++;
			else $notRead[] = $r;
		}
		ksort($byStatus);
		$m = array(array('type' => 'p', 'text' => self::METHOD));
		$m[] = array('type' => 'kv', 'rows' => array(
			array('Pages and files recorded', (string) count($resources)),
			array('Read as verified English', (string) $read),
			array('Not read as verified English', (string) count($notRead)),
			array('All pages by acquisition status', self::pairs($byStatus)),
			array('Crawl limits reached', isset($coverage['limitsHit']) && $coverage['limitsHit'] ? implode(', ', $coverage['limitsHit']) : 'None'),
			array('PDF text extraction', !isset($coverage['pdfTool']) || !is_string($coverage['pdfTool']) ? 'Not recorded'
				: ($coverage['pdfTool'] === 'unavailable' ? 'pdftotext not installed: PDF files were recorded but their text was not read' : $coverage['pdfTool'])),
		));
		if ($notRead) {
			$rows = array();
			foreach (array_slice($notRead, 0, self::MAX_GAPS) as $r) $rows[] = array((string) $r['url'], (string) $r['acquisition_status'], (string) $r['skip_reason']);
			$m[] = array('type' => 'subheading', 'text' => 'Pages not read as verified English (sources not inspected)');
			$m[] = array('type' => 'table', 'head' => array('Page', 'Status', 'Reason'), 'rows' => $rows, 'widths' => array(0.55, 0.15, 0.30));
			if (count($notRead) > self::MAX_GAPS) $m[] = array('type' => 'p', 'text' => (count($notRead) - self::MAX_GAPS) . ' more pages are listed on the run\'s Evidence page.');
		}
		$sections[] = array('heading' => 'Method and coverage', 'blocks' => $m);

		// 4. Results summary: required and recommended counted separately
		$results = $run['results'];
		$matrix = array();
		foreach ($results as $r) {
			$matrix[$r['machine_status']][$r['level']] = (isset($matrix[$r['machine_status']][$r['level']]) ? $matrix[$r['machine_status']][$r['level']] : 0) + 1;
		}
		$rows = array();
		foreach (self::STATUS_ORDER as $s) {
			if (!isset($matrix[$s])) continue;
			$row = array(self::STATUS[$s]);
			$total = 0;
			foreach (array('M', 'R', 'C', 'H') as $l) { $n = isset($matrix[$s][$l]) ? $matrix[$s][$l] : 0; $row[] = (string) $n; $total += $n; }
			$row[] = (string) $total;
			$rows[] = $row;
		}
		$summary = array();
		if (!$results) {
			$summary[] = array('type' => 'p', 'text' => 'No criterion was evaluated in this run (no approved DOAJ rule set; the run records coverage and evidence only).');
		} else {
			$summary[] = array('type' => 'p', 'text' => count($results) . ' criteria were evaluated. Must (required), recommended, conditional and human-judgement criteria are counted separately. A run without failures can still have unverifiable required criteria or an incomplete crawl; it is never an approval.');
			$summary[] = array('type' => 'table', 'head' => array('Status', 'Must', 'Recommended', 'Conditional', 'Human judgement', 'Total'), 'rows' => $rows, 'widths' => array(0.25, 0.13, 0.17, 0.15, 0.18, 0.12));
		}
		$sections[] = array('heading' => 'Results summary', 'blocks' => $summary);

		// 5. Criterion results (stored values, ordered by status and level)
		if ($results) {
			usort($results, function ($a, $b) {
				$x = array(array_search($a['machine_status'], self::STATUS_ORDER, true), $a['level'], $a['criterion_key']);
				$y = array(array_search($b['machine_status'], self::STATUS_ORDER, true), $b['level'], $b['criterion_key']);
				return $x < $y ? -1 : ($x > $y ? 1 : 0);
			});
			$rows = array();
			foreach ($results as $r) {
				$evidence = $r['source_url'] ? $r['source_url'] : '';
				if ($r['evidence_id'] !== null && isset($in['quotes'][(int) $r['evidence_id']]) && $in['quotes'][(int) $r['evidence_id']] !== '') {
					$evidence .= ($evidence !== '' ? ' ' : '') . '(evidence ' . $r['evidence_id'] . ')';
				}
				$rows[] = array($r['criterion_key'] . "\n" . $r['title'], self::LEVEL[$r['level']] . ' (' . $r['level'] . ')', self::STATUS[$r['machine_status']]
					. ($r['human_status'] ? "\nHuman: " . $r['human_status'] : ''), (string) $r['rationale'], $evidence);
			}
			$sections[] = array('heading' => 'Criterion results', 'blocks' => array(
				array('type' => 'p', 'text' => 'Each rationale is stored with the result and is shown here unchanged, including its verbatim quotes, the pages cited and the corrective recommendation with the DOAJ clause.'),
				array('type' => 'table', 'head' => array('Criterion', 'Level', 'Status', 'Rationale', 'Evidence page'), 'rows' => $rows, 'widths' => array(0.21, 0.10, 0.13, 0.38, 0.18))));
		}

		// 6. Findings and history
		$f = array();
		if ($run['observations']) {
			$rows = array();
			foreach ($run['observations'] as $o) {
				$rows[] = array('F-' . $o['finding_id'], (string) $o['criterion_key'], (string) $o['subtype'] . ($o['target'] ? "\n" . $o['target'] : ''),
					isset(self::LIFECYCLE[$o['lifecycle_status']]) ? self::LIFECYCLE[$o['lifecycle_status']] : (string) $o['lifecycle_status'],
					isset(self::STATUS[$o['criterion_status']]) ? self::STATUS[$o['criterion_status']] : (string) $o['criterion_status']);
			}
			$f[] = array('type' => 'p', 'text' => 'Finding IDs are stable across runs. "Resolved (verified)" needs new public evidence; a page that could not be read again gives "Needs verification", never "Resolved". Completing a corrective action is not evidence.');
			$f[] = array('type' => 'table', 'head' => array('Finding', 'Criterion', 'Subtype and target', 'Status in this run', 'Criterion status'), 'rows' => $rows, 'widths' => array(0.10, 0.27, 0.33, 0.15, 0.15));
		} else {
			$f[] = array('type' => 'p', 'text' => 'No finding was observed in this run.');
		}
		if (!empty($in['comparison'])) {
			$c = $in['comparison'];
			$changes = array();
			foreach ($c['rows'] as $row) $changes[$row['change']] = (isset($changes[$row['change']]) ? $changes[$row['change']] : 0) + 1;
			ksort($changes);
			$f[] = array('type' => 'p', 'text' => 'Comparison with run ' . $c['from']['run_number'] . ': ' . ($changes ? self::pairs($changes) : 'no findings in either run') . '.'
				. ($c['differentRulebooks'] ? ' The two runs used different rule sets (' . $c['from']['rulebook_version'] . ' and ' . $c['to']['rulebook_version'] . '); their results are not directly comparable.' : ''));
		}
		$sections[] = array('heading' => 'Findings and history', 'blocks' => $f);

		// 7. Before You Apply: corrections by website page, with accountable action rows
		$b = array();
		if (!$plan['groups']) {
			$b[] = array('type' => 'p', 'text' => 'No failed, warned or contradicted criterion in this run.');
		} else {
			$b[] = array('type' => 'p', 'text' => $plan['counts']['tasks'] . ' corrections on ' . $plan['counts']['pages'] . ' page group(s); ' . $plan['counts']['withAction'] . ' have a corrective action. Recommendations describe the gap and point to the DOAJ clause; they are not drafted policies.');
			foreach ($plan['groups'] as $g) {
				$b[] = array('type' => 'subheading', 'text' => 'Page: ' . ($g['page'] !== null ? $g['page'] : 'the journal website (no single page)'));
				$rows = array();
				foreach ($g['tasks'] as $t) {
					$acts = array();
					foreach ($t['actions'] as $a) $acts[] = 'A-' . $a['action_id'] . ' ' . (isset(self::ACTION[$a['status']]) ? self::ACTION[$a['status']] : $a['status']) . ', priority ' . $a['priority']
						. ($a['owner_text'] ? ', owner ' . $a['owner_text'] : '') . ($a['due_date'] ? ', due ' . $a['due_date'] : '');
					$rows[] = array($t['criterionKey'] . ($t['findingId'] ? "\nF-" . $t['findingId'] : ''), self::STATUS[$t['status']] . ' (' . $t['level'] . ')',
						(string) ($t['recommendation'] !== null ? $t['recommendation'] : $t['rationale']), (string) $t['clause'], $acts ? implode("\n", $acts) : 'No action yet (suggested priority: ' . $t['suggestedPriority'] . ')');
				}
				$b[] = array('type' => 'table', 'head' => array('Criterion and finding', 'Status', 'Recommended correction', 'DOAJ clause', 'Corrective action'), 'rows' => $rows, 'widths' => array(0.20, 0.13, 0.29, 0.20, 0.18));
			}
		}
		if ($plan['verify']) {
			$n = 0;
			foreach ($plan['verify'] as $g) $n += count($g['tasks']);
			$b[] = array('type' => 'subheading', 'text' => 'Needs verification (' . $n . ' criteria; not deficiencies)');
			$items = array();
			foreach ($plan['verify'] as $g) foreach ($g['tasks'] as $t) $items[] = $t['criterionKey'] . ' (' . $t['level'] . ') - ' . ($g['page'] !== null ? $g['page'] : 'no single page') . ': ' . $t['rationale'];
			$b[] = array('type' => 'list', 'items' => $items);
		}
		$sections[] = array('heading' => 'Before You Apply: corrections by page', 'blocks' => $b);

		// 8. Corrective action register
		$ar = array();
		if ($in['actions']) {
			$rows = array();
			foreach ($in['actions'] as $a) {
				$rows[] = array('A-' . $a['action_id'] . ($a['finding_id'] ? "\nF-" . $a['finding_id'] : ''), (string) $a['recommendation'], (string) $a['target_url'],
					trim(($a['owner_text'] ? $a['owner_text'] : '-') . "\n" . ($a['due_date'] ? 'Due ' . $a['due_date'] : '')), $a['priority'] . "\n" . (isset(self::ACTION[$a['status']]) ? self::ACTION[$a['status']] : $a['status']),
					(string) $a['completion_evidence']);
			}
			$ar[] = array('type' => 'p', 'text' => 'Owners are recorded as free text; naming an owner grants no access to the plugin or this report.');
			$ar[] = array('type' => 'table', 'head' => array('Action', 'Recommendation', 'Target page', 'Owner and due date', 'Priority and status', 'Completion evidence'), 'rows' => $rows,
				'widths' => array(0.10, 0.28, 0.20, 0.14, 0.12, 0.16));
		} else {
			$ar[] = array('type' => 'p', 'text' => 'No corrective action has been recorded for this journal.');
		}
		$sections[] = array('heading' => 'Corrective action register', 'blocks' => $ar);

		// 9. DOAJ Form Guide
		$rows = array();
		foreach ($in['formGuide'] as $q) {
			$rows[] = array($q['question_key'] . ($q['title'] ? "\n" . $q['title'] : ''), isset(self::ANSWER[$q['answer_status']]) ? self::ANSWER[$q['answer_status']] : (string) $q['answer_status'],
				(string) $q['answer_text'], (string) $q['uncertainty']);
		}
		$sections[] = array('heading' => 'DOAJ Form Guide (FQ-01 to FQ-20)', 'blocks' => array(
			array('type' => 'p', 'text' => 'Answers come only from evidence read in this run, with the page. "Not found" is shown only when the governing criterion established the absence; otherwise "Needs verification". FQ-08 is never answered automatically.'),
			array('type' => 'table', 'head' => array('Question', 'Answer status', 'Answer from the website', 'Uncertainty'), 'rows' => $rows, 'widths' => array(0.18, 0.15, 0.37, 0.30))));

		// 10. Limitations
		$unverifiable = isset($matrix['unverifiable']) ? array_sum($matrix['unverifiable']) : 0;
		$limits = array(
			'English-only, deterministic patterns: unusual wording gives "unverifiable" or "for DOAJ review", never a pass.',
			'The crawl reads a sample of issues, articles and files; it cannot prove "all content".',
			$unverifiable . ' criteria are unverifiable in this run; ' . (int) $run['languageNotVerifiedCount'] . ' pages were read but their language is not verified English.',
			'External registries (ISSN Portal, DOI resolution) were not consulted; registry criteria are unverifiable.',
			'Human-judgement criteria are listed for DOAJ review with the extracted text; the plugin never decides them.',
		);
		if ($candidate || $approved) $limits[] = ($candidate ? 'Candidate criteria are PENDING APPROVAL. ' : '') . 'Results marked "Language-neutral facts (ADR-013)" rest on names, dates, ISSNs or counts read from short pages without a language verdict.';
		$sections[] = array('heading' => 'Limitations', 'blocks' => array(array('type' => 'list', 'items' => $limits)));

		// 11. References and attribution
		$refs = array();
		foreach (self::REFERENCES as $r) $refs[] = $r[0] . ': ' . $r[1];
		$rb = array(array('type' => 'list', 'items' => $refs));
		if ($candidate || $approved) $rb[] = array('type' => 'p', 'text' => self::ATTRIBUTION);
		$sections[] = array('heading' => 'References', 'blocks' => $rb);

		// 12. Disclaimer (repeated)
		$sections[] = array('heading' => 'Disclaimer', 'blocks' => array(array('type' => 'notice', 'text' => self::NOT_OFFICIAL)));

		return array(
			'title' => 'DOAJ Pre-Assessment Report: ' . $in['journal']['name'],
			'subtitle' => 'Run ' . $run['run_number'] . ', completed ' . $run['completed_at_utc'] . ' UTC' . ($candidate ? ' - candidate criteria, PENDING APPROVAL' : ($approved ? '' : ' - no approved DOAJ rule set')),
			'fileStem' => 'doaj-preassessment-run-' . (int) $run['run_number'],
			'sections' => $sections,
		);
	}

	/** @return string[] Every text of the model, in order, for parity checks between formats */
	public static function texts($model) {
		$out = array($model['title'], $model['subtitle']);
		foreach ($model['sections'] as $s) {
			$out[] = $s['heading'];
			foreach ($s['blocks'] as $b) {
				switch ($b['type']) {
					case 'kv': foreach ($b['rows'] as $r) { $out[] = $r[0]; $out[] = $r[1]; } break;
					case 'list': foreach ($b['items'] as $i) $out[] = $i; break;
					case 'table': foreach ($b['head'] as $h) $out[] = $h; foreach ($b['rows'] as $r) foreach ($r as $c) $out[] = $c; break;
					default: $out[] = $b['text'];
				}
			}
		}
		return $out;
	}

	private static function pairs($counts) {
		$p = array();
		foreach ($counts as $k => $v) $p[] = $k . ' ' . $v;
		return implode(', ', $p);
	}
}
