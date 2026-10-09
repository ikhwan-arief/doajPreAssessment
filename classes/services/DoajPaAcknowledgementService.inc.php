<?php

/**
 * @file classes/services/DoajPaAcknowledgementService.inc.php
 *
 * @class DoajPaAcknowledgementService
 * @brief Append-only record of English-only acknowledgements (PROD-05). Per-run acknowledgements are
 *  written by DoajPaAssessmentRunService::createDraftRun(); this class records the first-use gate.
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/../gate/DoajPaEnglishGate.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAuditService.inc.php');

class DoajPaAcknowledgementService {

	/** @return int acknowledgement_id */
	public static function recordFirstUse($contextId, $actor) {
		DoajPaDb::id($contextId, 'context');
		$id = (int) DoajPaDb::table(DoajPaSchema::T_ACK)->insertGetId(array(
			'context_id' => $contextId, 'run_id' => null, 'ack_type' => 'first_use', 'user_id' => $actor['userId'],
			'username' => $actor['username'], 'warning_sha256' => DoajPaEnglishGate::warningSha256(),
			'warning_source' => DoajPaEnglishGate::WARNING_SOURCE, 'acknowledged_at_utc' => DoajPaDb::now(),
		));
		DoajPaAuditService::record($contextId, 'gate_acknowledged', $actor, null, 'acknowledgement', $id, array('type' => 'first_use'));
		return $id;
	}

	/** @return array */
	public static function listForContext($contextId) {
		return DoajPaDb::table(DoajPaSchema::T_ACK)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))
			->orderBy('acknowledgement_id')->get()->all();
	}
}
