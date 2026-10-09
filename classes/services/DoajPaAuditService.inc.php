<?php

/**
 * @file classes/services/DoajPaAuditService.inc.php
 *
 * @class DoajPaAuditService
 * @brief Append-only audit log (Stage 01 F9). There is no update or delete path.
 *  Callers that change data record the event inside the same transaction.
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');

class DoajPaAuditService {

	const MAX_DETAILS_BYTES = 8192;

	/**
	 * @param $contextId int Journal context (0 only for site-level events)
	 * @param $eventType string One of DoajPaSchema::AUDIT_EVENTS
	 * @param $actor array DoajPaDb::userActor()/systemActor()
	 * @param $runId int|null
	 * @param $subjectType string|null
	 * @param $subjectId int|null
	 * @param $details array
	 * @return int event_id
	 */
	public static function record($contextId, $eventType, $actor, $runId = null, $subjectType = null, $subjectId = null, $details = array()) {
		if (!is_int($contextId) || $contextId < 0) throw new DoajPaValidationException('Invalid context.');
		DoajPaDb::oneOf($eventType, DoajPaSchema::AUDIT_EVENTS, 'audit event type');
		$json = DoajPaDb::json((array) $details);
		if (strlen($json) > self::MAX_DETAILS_BYTES) throw new DoajPaValidationException('Audit details too large.');
		return (int) DoajPaDb::table(DoajPaSchema::T_AUDIT)->insertGetId(array(
			'context_id' => $contextId,
			'event_type' => $eventType,
			'actor_user_id' => $actor['userId'],
			'actor_username' => $actor['username'],
			'run_id' => $runId,
			'subject_type' => $subjectType,
			'subject_id' => $subjectId,
			'details' => $json,
			'created_at_utc' => DoajPaDb::now(),
		));
	}

	/**
	 * Events of one journal, oldest first.
	 * @param $contextId int
	 * @param $eventType string|null
	 * @return array
	 */
	public static function listForContext($contextId, $eventType = null) {
		$query = DoajPaDb::table(DoajPaSchema::T_AUDIT)->where('context_id', '=', DoajPaDb::id($contextId, 'context'));
		if ($eventType !== null) $query->where('event_type', '=', $eventType);
		return $query->orderBy('event_id')->get()->all();
	}
}
