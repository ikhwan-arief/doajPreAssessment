<?php

/**
 * @file classes/persistence/DoajPaSchema.inc.php
 *
 * @class DoajPaSchema
 * @brief Table names and closed value sets of the plugin schema (Stage 01 F5, F6, F10).
 *  Pure constants; no OJS dependency.
 */

class DoajPaSchema {

	/**
	 * Approved DOAJ rule sets and criterion datasets. Stage 02 has none: every stored run and definition is a
	 * development or test record and the UI labels it "not a DOAJ assessment result". Stage 03 adds the
	 * approved, pinned versions here after review (Stage 01 F2).
	 */
	const APPROVED_RULEBOOKS = array();
	const APPROVED_DATASETS = array();

	/** Schema level written by DoajPaSchemaMigration (plugin_settings, context 0, 'schemaVersion'). */
	const VERSION = 1;

	const T_DEFINITION = 'doaj_criterion_definition';
	const T_RUN = 'doaj_assessment_run';
	const T_ACK = 'doaj_acknowledgement';
	const T_RESOURCE = 'doaj_crawl_resource';
	const T_EVIDENCE = 'doaj_evidence_excerpt';
	const T_RESULT = 'doaj_criterion_result';
	const T_FINDING = 'doaj_finding';
	const T_OBSERVATION = 'doaj_finding_observation';
	const T_TRANSITION = 'doaj_finding_transition';
	const T_ACTION = 'doaj_corrective_action';
	const T_FORM_ANSWER = 'doaj_form_answer';
	const T_EXPORT = 'doaj_report_export';
	const T_AUDIT = 'doaj_audit_event';

	/** Creation order (parents first). */
	const TABLES = array(
		self::T_DEFINITION, self::T_RUN, self::T_ACK, self::T_RESOURCE, self::T_EVIDENCE, self::T_RESULT,
		self::T_FINDING, self::T_OBSERVATION, self::T_TRANSITION, self::T_ACTION, self::T_FORM_ANSWER,
		self::T_EXPORT, self::T_AUDIT,
	);

	/** Tables whose rows are never updated or deleted by any code path (F6). */
	const APPEND_ONLY = array(
		self::T_ACK, self::T_OBSERVATION, self::T_TRANSITION, self::T_EXPORT, self::T_AUDIT,
	);

	/** F5 run status. */
	const RUN_STATUSES = array('draft', 'queued', 'running', 'human_review', 'completed', 'interrupted', 'cancelled');
	const RUN_ACTIVE = array('draft', 'queued', 'running', 'human_review', 'interrupted');
	const RUN_WRITABLE = array('running', 'human_review');

	/** F5 criterion status. */
	const CRITERION_STATUSES = array('pass', 'fail', 'warning', 'contradiction', 'unverifiable', 'for_doaj_review', 'not_applicable');
	/** Criterion statuses that can be the subject of a finding. */
	const FINDING_CRITERION_STATUSES = array('fail', 'warning', 'contradiction', 'unverifiable', 'for_doaj_review');

	/** F5 evidence acquisition status. */
	const ACQUISITION_STATUSES = array('retrieved', 'not_found', 'inaccessible', 'parse_failed', 'unsupported_language', 'ambiguous');

	/** F6 finding lifecycle. */
	const LIFECYCLE = array('open', 'still_open', 'needs_verification', 'resolved_verified', 'reopened', 'superseded');

	const LEVELS = array('M', 'R', 'C', 'H');
	const TARGET_TYPES = array('journal', 'page', 'issue', 'article', 'pdf');
	const ACTION_STATUSES = array('planned', 'in_progress', 'done_pending_verification', 'cancelled');
	const ACTION_PRIORITIES = array('high', 'medium', 'low');
	const FORM_QUESTION_PATTERN = '/^FQ-(0[1-9]|1[0-9]|20)$/';
	const FORM_ANSWER_STATUSES = array('not_yet_assessed', 'supported', 'not_found', 'needs_verification');
	const EXPORT_FORMATS = array('html', 'pdf', 'docx');
	const ACK_TYPES = array('first_use', 'run');

	const AUDIT_EVENTS = array(
		'gate_acknowledged', 'access_denied', 'run_created', 'run_status_changed', 'run_completed',
		'finding_transition', 'corrective_action_created', 'corrective_action_updated', 'report_export_recorded',
		'criterion_definition_registered', 'crawl_started', 'crawl_finished', 'dev_private_destination_allowed',
	);
}
