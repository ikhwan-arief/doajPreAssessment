<?php

/**
 * @file classes/history/DoajPaLifecycle.inc.php
 *
 * @class DoajPaLifecycle
 * @brief Finding lifecycle rules (Stage 01 F6). Pure; no OJS dependency.
 *
 * Each completed run gives every non-superseded finding of the journal exactly one observation:
 *  - detected: the deficiency was confirmed again (or for the first time);
 *  - verified_clear: new public evidence, with equivalent coverage, confirms it is corrected;
 *  - not_established: neither could be established (page missing, inaccessible, not evaluated);
 *  - superseded: the criterion changed incompatibly (new lineage) or the target disappeared.
 * "Resolved (Verified)" is reachable only through verified_clear. Manual action completion is never input.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DoajPaLifecycle {

	const DETECTED = 'detected';
	const VERIFIED_CLEAR = 'verified_clear';
	const NOT_ESTABLISHED = 'not_established';
	const SUPERSEDED = 'superseded';

	/**
	 * @param $previous string|null Current lifecycle status, null for a new finding
	 * @param $wasResolved boolean Whether the last definitive observation was verified_clear
	 * @param $observation string One of the class constants
	 * @return string New lifecycle status
	 * @throws InvalidArgumentException for impossible inputs
	 */
	public static function next($previous, $wasResolved, $observation) {
		if ($previous === 'superseded') {
			throw new InvalidArgumentException('A superseded finding cannot be observed again.');
		}
		if ($previous === null) {
			if ($observation !== self::DETECTED) {
				throw new InvalidArgumentException('A new finding can only start from a detection.');
			}
			return 'open';
		}
		if (!in_array($previous, array('open', 'still_open', 'needs_verification', 'resolved_verified', 'reopened'), true)) {
			throw new InvalidArgumentException('Unknown lifecycle status.');
		}
		switch ($observation) {
			case self::DETECTED:
				return ($wasResolved === true) ? 'reopened' : 'still_open';
			case self::VERIFIED_CLEAR:
				return 'resolved_verified';
			case self::NOT_ESTABLISHED:
				return 'needs_verification';
			case self::SUPERSEDED:
				return 'superseded';
		}
		throw new InvalidArgumentException('Unknown observation.');
	}

	/**
	 * Whether the "was resolved" memory changes after an observation.
	 * @param $wasResolved boolean
	 * @param $observation string
	 * @return boolean
	 */
	public static function nextWasResolved($wasResolved, $observation) {
		if ($observation === self::DETECTED) return false;
		if ($observation === self::VERIFIED_CLEAR) return true;
		return $wasResolved === true;
	}
}
