<?php

/**
 * @file classes/history/DoajPaRunStateMachine.inc.php
 *
 * @class DoajPaRunStateMachine
 * @brief Valid assessment-run status transitions (Stage 01 F5). Pure; no OJS dependency.
 *  completed and cancelled are terminal. An interrupted run can resume (running) or be cancelled.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaRunStateMachine {

	const TRANSITIONS = array(
		'draft' => array('queued', 'cancelled'),
		'queued' => array('running', 'cancelled'),
		'running' => array('human_review', 'completed', 'interrupted', 'cancelled'),
		'human_review' => array('completed', 'cancelled'),
		'interrupted' => array('running', 'cancelled'),
		'completed' => array(),
		'cancelled' => array(),
	);

	/**
	 * @param $from string
	 * @param $to string
	 * @return boolean
	 */
	public static function canTransition($from, $to) {
		return is_string($from) && is_string($to) && isset(self::TRANSITIONS[$from])
			&& in_array($to, self::TRANSITIONS[$from], true);
	}

	/**
	 * @param $status string
	 * @return boolean
	 */
	public static function isTerminal($status) {
		return isset(self::TRANSITIONS[$status]) && self::TRANSITIONS[$status] === array();
	}
}
