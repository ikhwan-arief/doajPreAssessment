<?php

/**
 * @file classes/services/DoajPaCorrectiveActionService.inc.php
 *
 * @class DoajPaCorrectiveActionService
 * @brief Corrective action register (Stage 01 F6, F7). Owners are free text and grant no access (F9).
 *  Completing an action never changes a finding: only new public evidence can resolve a finding (F6).
 *  Every change is audited; concurrent edits are detected with lock_version.
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAuditService.inc.php');

class DoajPaCorrectiveActionService {

	const EDITABLE = array('recommendation', 'targetUrl', 'ownerText', 'dueDate', 'priority', 'status', 'completionEvidence');

	/** @return int action_id */
	public static function create($contextId, $actor, $data) {
		DoajPaDb::id($contextId, 'context');
		if ($actor['type'] !== 'user') throw new DoajPaValidationException('Actions are created by users.');
		$findingId = isset($data['findingId']) ? DoajPaDb::id($data['findingId'], 'finding') : null;
		$runId = isset($data['runId']) ? DoajPaDb::id($data['runId'], 'run') : null;
		$row = self::validated(array_merge(array('priority' => 'medium', 'status' => 'planned'), $data), true);
		return DoajPaDb::transaction(function () use ($contextId, $actor, $findingId, $runId, $row) {
			if ($findingId !== null && !DoajPaDb::table(DoajPaSchema::T_FINDING)->where('finding_id', '=', $findingId)->where('context_id', '=', $contextId)->exists()) {
				throw new DoajPaNotFoundException('Finding not found.');
			}
			if ($runId !== null && !DoajPaDb::table(DoajPaSchema::T_RUN)->where('run_id', '=', $runId)->where('context_id', '=', $contextId)->exists()) {
				throw new DoajPaNotFoundException('Assessment run not found.');
			}
			$now = DoajPaDb::now();
			$row = array_merge($row, array(
				'context_id' => $contextId, 'finding_id' => $findingId, 'run_id' => $runId, 'lock_version' => 0,
				'created_by_user_id' => $actor['userId'], 'updated_by_user_id' => $actor['userId'],
				'created_at_utc' => $now, 'updated_at_utc' => $now,
			));
			$id = (int) DoajPaDb::table(DoajPaSchema::T_ACTION)->insertGetId($row);
			DoajPaAuditService::record($contextId, 'corrective_action_created', $actor, $runId, 'corrective_action', $id,
				array('findingId' => $findingId, 'status' => $row['status']));
			return $id;
		});
	}

	/**
	 * @param $changes array Subset of self::EDITABLE
	 * @return int New lock_version
	 */
	public static function update($contextId, $actionId, $expectedLockVersion, $changes, $actor) {
		DoajPaDb::id($contextId, 'context');
		DoajPaDb::id($actionId, 'corrective action');
		if (!is_int($expectedLockVersion) || $expectedLockVersion < 0) throw new DoajPaValidationException('Invalid lock version.');
		if (!is_array($changes) || !$changes || array_diff(array_keys($changes), self::EDITABLE)) throw new DoajPaValidationException('Invalid changes.');
		$row = self::validated($changes, false);
		return DoajPaDb::transaction(function () use ($contextId, $actionId, $expectedLockVersion, $row, $actor) {
			$current = DoajPaDb::table(DoajPaSchema::T_ACTION)->where('action_id', '=', $actionId)->where('context_id', '=', $contextId)->lockForUpdate()->first();
			if (!$current) throw new DoajPaNotFoundException('Corrective action not found.');
			if ((int) $current->lock_version !== $expectedLockVersion) throw new DoajPaConcurrencyException('The action was changed by someone else; reload and try again.');
			$before = array();
			foreach ($row as $column => $value) $before[$column] = $current->$column;
			$row['lock_version'] = $expectedLockVersion + 1;
			$row['updated_by_user_id'] = $actor['userId'];
			$row['updated_at_utc'] = DoajPaDb::now();
			DoajPaDb::table(DoajPaSchema::T_ACTION)->where('action_id', '=', $actionId)->where('context_id', '=', $contextId)
				->where('lock_version', '=', $expectedLockVersion)->update($row);
			unset($row['lock_version'], $row['updated_by_user_id'], $row['updated_at_utc']);
			DoajPaAuditService::record($contextId, 'corrective_action_updated', $actor, $current->run_id === null ? null : (int) $current->run_id,
				'corrective_action', $actionId, array('before' => $before, 'after' => $row));
			return $expectedLockVersion + 1;
		});
	}

	/** @return object */
	public static function get($contextId, $actionId) {
		$row = DoajPaDb::table(DoajPaSchema::T_ACTION)->where('action_id', '=', DoajPaDb::id($actionId, 'corrective action'))
			->where('context_id', '=', DoajPaDb::id($contextId, 'context'))->first();
		if (!$row) throw new DoajPaNotFoundException('Corrective action not found.');
		return $row;
	}

	/** @return array Columns => validated values */
	private static function validated($data, $creating) {
		$row = array();
		if ($creating || array_key_exists('recommendation', $data)) {
			$row['recommendation'] = DoajPaDb::text(isset($data['recommendation']) ? $data['recommendation'] : null, 'recommendation', 65535);
		}
		if (array_key_exists('targetUrl', $data)) {
			$url = DoajPaDb::text($data['targetUrl'], 'target URL', 2048, true);
			if ($url !== null && !preg_match('#^https?://[^\s]+$#i', $url)) throw new DoajPaValidationException('Invalid target URL.');
			$row['target_url'] = $url;
		}
		if (array_key_exists('ownerText', $data)) $row['owner_text'] = DoajPaDb::text($data['ownerText'], 'owner', 255, true);
		if (array_key_exists('dueDate', $data)) {
			$due = $data['dueDate'];
			if ($due !== null) {
				$parsed = is_string($due) ? DateTime::createFromFormat('!Y-m-d', $due) : false;
				if (!$parsed || $parsed->format('Y-m-d') !== $due) throw new DoajPaValidationException('Invalid due date.');
			}
			$row['due_date'] = $due;
		}
		if (array_key_exists('priority', $data)) $row['priority'] = DoajPaDb::oneOf($data['priority'], DoajPaSchema::ACTION_PRIORITIES, 'priority');
		if (array_key_exists('status', $data)) $row['status'] = DoajPaDb::oneOf($data['status'], DoajPaSchema::ACTION_STATUSES, 'action status');
		if (array_key_exists('completionEvidence', $data)) $row['completion_evidence'] = DoajPaDb::text($data['completionEvidence'], 'completion evidence', 65535, true);
		return $row;
	}
}
