<?php

/**
 * @file classes/ui/DoajPaLabels.inc.php
 *
 * @class DoajPaLabels
 * @brief English text labels for stored status codes. Every badge shows its text label, never colour
 *  alone (Stage 01 F5). The plugin interface is English-only (PROD-04).
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaLabels {

	/** @return array code group => (code => English label) */
	public static function all() {
		return array(
			'run' => array('draft' => 'Draft', 'queued' => 'Queued', 'running' => 'Running', 'human_review' => 'Human review',
				'completed' => 'Completed', 'interrupted' => 'Interrupted', 'cancelled' => 'Cancelled'),
			'lifecycle' => array('open' => 'Open', 'still_open' => 'Still open', 'needs_verification' => 'Needs verification',
				'resolved_verified' => 'Resolved (verified)', 'reopened' => 'Reopened', 'superseded' => 'Superseded'),
			'observation' => array('detected' => 'Detected', 'verified_clear' => 'Verified corrected', 'not_established' => 'Not established',
				'superseded' => 'Superseded'),
			'criterion' => array('pass' => 'Pass', 'fail' => 'Fail', 'warning' => 'Warning', 'contradiction' => 'Contradiction',
				'unverifiable' => 'Unverifiable', 'for_doaj_review' => 'For DOAJ review', 'not_applicable' => 'Not applicable'),
			'acquisition' => array('retrieved' => 'Retrieved', 'not_found' => 'Not found', 'inaccessible' => 'Inaccessible',
				'parse_failed' => 'Parse failed', 'unsupported_language' => 'Unsupported language', 'ambiguous' => 'Ambiguous'),
			'action' => array('planned' => 'Planned', 'in_progress' => 'In progress', 'done_pending_verification' => 'Done, awaiting verification',
				'cancelled' => 'Cancelled'),
			'priority' => array('high' => 'High', 'medium' => 'Medium', 'low' => 'Low'),
			'level' => array('M' => 'Must', 'R' => 'Recommended', 'C' => 'Conditional', 'H' => 'Human judgement'),
			'change' => array('unchanged' => 'Unchanged', 'changed' => 'Changed', 'not_observed_in_to' => 'Not observed in the later run',
				'not_observed_in_from' => 'Not observed in the earlier run'),
			'diff' => array('new' => 'New pages', 'unchanged' => 'Unchanged', 'changed' => 'Changed', 'notReadAgain' => 'Not read again',
				'becameGap' => 'Now not retrieved'),
			'formAnswer' => array('not_yet_assessed' => 'Not yet assessed', 'supported' => 'Supported', 'not_found' => 'Not found',
				'needs_verification' => 'Needs verification', 'candidate_pending_approval' => 'Candidate answers (pending approval)', 'approved_mapping' => 'Answers from the approved mapping'),
		);
	}
}
