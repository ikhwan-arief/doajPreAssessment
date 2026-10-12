<?php

/**
 * @file classes/reports/DoajPaActionPlan.inc.php
 *
 * @class DoajPaActionPlan
 * @brief "Before You Apply" correction list (Stage 01 F7 item 4, AC-14), built from ONE stored completed run. Pure.
 *  Every fail, warning and contradiction of the run becomes a task on the website page that needs the correction: the
 *  page a finding targets (an issue, a page with an unapproved metric), otherwise the page of the evidence cited. Each
 *  task carries the run's own recommendation and DOAJ clause (from the stored rationale, never rewritten), its finding
 *  and the journal's corrective-action rows for that finding (owner, due date, priority, status, completion evidence:
 *  accountable rows). Unverifiable results are listed separately as "needs verification": an unread page is never a
 *  deficiency. Nothing here changes a finding; completing an action is not evidence (F6).
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaActionPlan {

	const NO_PAGE = '';
	/** Severity order of task statuses */
	const ORDER = array('contradiction' => 0, 'fail' => 1, 'warning' => 2, 'unverifiable' => 3);
	const LEVEL_ORDER = array('M' => 0, 'C' => 1, 'R' => 2, 'H' => 3);

	/**
	 * @param $run array DoajPaReadService::run() detail (results, observations, evidence)
	 * @param $actions array DoajPaReadService::actions() rows of the journal
	 * @return array groups (to correct), verify (to verify), counts, run summary fields
	 */
	public static function build($run, $actions) {
		$evidenceUrl = array();
		foreach ($run['evidence'] as $e) $evidenceUrl[(int) $e['evidence_id']] = (string) $e['source_url'];
		$byFinding = array();
		foreach ($actions as $a) if ($a['finding_id'] !== null) $byFinding[(int) $a['finding_id']][] = $a;
		$obsByKey = array();
		foreach ($run['observations'] as $o) {
			if (in_array($o['criterion_status'], array('fail', 'warning', 'contradiction'), true)) $obsByKey[$o['criterion_key']][] = $o;
		}

		$groups = array(); $verify = array(); $counts = array('tasks' => 0, 'verify' => 0, 'pages' => 0, 'withAction' => 0);
		foreach ($run['results'] as $r) {
			$status = $r['machine_status'];
			if (!isset(self::ORDER[$status])) continue;
			list($text, $recommendation, $clause) = self::split((string) $r['rationale']);
			$base = array('criterionKey' => $r['criterion_key'], 'title' => $r['title'], 'level' => $r['level'], 'status' => $status,
				'rationale' => $text, 'recommendation' => $recommendation, 'clause' => $clause, 'doajReferenceUrl' => $r['doaj_reference_url']);
			if ($status === 'unverifiable') {
				$page = self::pageOf($r['source_url']);
				$verify[$page][] = $base + array('findingId' => null, 'lifecycle' => null, 'actions' => array());
				$counts['verify']++;
				continue;
			}
			$obs = isset($obsByKey[$r['criterion_key']]) ? $obsByKey[$r['criterion_key']] : array();
			if (!$obs) $obs = array(null);
			foreach ($obs as $o) {
				$page = self::NO_PAGE;
				if ($o !== null && in_array($o['target_type'], array('page', 'issue', 'article'), true) && self::isHttp($o['target'])) $page = $o['target'];
				elseif ($o !== null && $o['evidence_id'] !== null && isset($evidenceUrl[(int) $o['evidence_id']])) $page = self::pageOf($evidenceUrl[(int) $o['evidence_id']]);
				else $page = self::pageOf($r['source_url']);
				$linked = $o !== null && isset($byFinding[(int) $o['finding_id']]) ? $byFinding[(int) $o['finding_id']] : array();
				$groups[$page][] = $base + array('findingId' => $o !== null ? (int) $o['finding_id'] : null, 'lifecycle' => $o !== null ? $o['lifecycle_status'] : null,
					'target' => $o !== null ? $o['target'] : null, 'actions' => $linked, 'suggestedPriority' => self::priority($status, $r['level']));
				$counts['tasks']++;
				if ($linked) $counts['withAction']++;
			}
		}
		$out = array('groups' => self::sortGroups($groups), 'verify' => self::sortGroups($verify), 'counts' => $counts);
		$out['counts']['pages'] = count($out['groups']);
		return $out;
	}

	/** @return array [text before the recommendation, recommendation, DOAJ clause] from a stored rationale (verbatim parts) */
	public static function split($rationale) {
		$text = $rationale; $rec = null; $clause = null;
		$i = strpos($rationale, ' Recommended correction: ');
		if ($i !== false) {
			$text = substr($rationale, 0, $i);
			$rest = substr($rationale, $i + strlen(' Recommended correction: '));
			$j = strpos($rest, ' (DOAJ: "');
			if ($j !== false) {
				$rec = substr($rest, 0, $j);
				$end = strpos($rest, '")', $j);
				$clause = $end !== false ? substr($rest, $j + strlen(' (DOAJ: "'), $end - $j - strlen(' (DOAJ: "')) : null;
			} else {
				$rec = $rest;
			}
		}
		return array(trim($text), $rec === null ? null : trim($rec), $clause);
	}

	/** high: a Must (or conditional must) not met or contradicted; medium: a recommendation; low: needs verification only */
	public static function priority($status, $level) {
		if ($status === 'unverifiable') return 'low';
		return ($level === 'R' || $status === 'warning') ? 'medium' : 'high';
	}

	private static function pageOf($url) {
		return self::isHttp($url) ? (string) $url : self::NO_PAGE;
	}

	private static function isHttp($url) {
		return is_string($url) && preg_match('#^https?://[^\s<>"\']+$#i', $url) === 1;
	}

	/** Groups ordered by their most severe task (then by URL, the "whole website" group last); tasks by severity and level */
	private static function sortGroups($groups) {
		$out = array();
		foreach ($groups as $page => $tasks) {
			usort($tasks, function ($a, $b) {
				$x = array(self::ORDER[$a['status']], isset(self::LEVEL_ORDER[$a['level']]) ? self::LEVEL_ORDER[$a['level']] : 9, $a['criterionKey']);
				$y = array(self::ORDER[$b['status']], isset(self::LEVEL_ORDER[$b['level']]) ? self::LEVEL_ORDER[$b['level']] : 9, $b['criterionKey']);
				return $x < $y ? -1 : ($x > $y ? 1 : 0);
			});
			$out[] = array('page' => $page === self::NO_PAGE ? null : $page, 'tasks' => $tasks,
				'worst' => self::ORDER[$tasks[0]['status']], 'mustCount' => count(array_filter($tasks, function ($t) { return $t['level'] !== 'R'; })));
		}
		usort($out, function ($a, $b) {
			$x = array($a['page'] === null ? 1 : 0, $a['worst'], -$a['mustCount'], (string) $a['page']);
			$y = array($b['page'] === null ? 1 : 0, $b['worst'], -$b['mustCount'], (string) $b['page']);
			return $x < $y ? -1 : ($x > $y ? 1 : 0);
		});
		return $out;
	}
}
