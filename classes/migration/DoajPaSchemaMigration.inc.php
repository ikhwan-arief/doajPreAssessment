<?php

/**
 * @file classes/migration/DoajPaSchemaMigration.inc.php
 *
 * @class DoajPaSchemaMigration
 * @brief Plugin-owned schema, level 1 (Stage 02.04, ADR-003/ADR-004).
 *
 * Idempotent: every table is created only if it is missing, and nothing is ever dropped or altered
 * destructively. It runs from install.xml (upload install), upgrade.xml (plugin upgrade) and
 * getInstallMigration() (OJS install/upgrade, and whenever any plugin is installed while this one is
 * registered), so it must be safe to run any number of times.
 *
 * Context isolation is enforced by the database: each child row references its parent through the
 * composite key (id, context_id), so it cannot point at another journal's run, finding or evidence.
 * Core OJS tables are never altered and no foreign key points into them.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once(dirname(__FILE__) . '/../persistence/DoajPaSchema.inc.php');
require_once(dirname(__FILE__) . '/DoajPaSchemaInspector.inc.php');
import('lib.pkp.classes.install.DowngradeNotSupportedException');

class DoajPaSchemaMigration extends Migration {

	const PLUGIN_SETTINGS_NAME = 'doajpreassessmentplugin';

	/**
	 * Create every missing table, verify the result and record the schema level.
	 * @throws Exception if the schema is still incomplete afterwards
	 */
	public function up() {
		$schema = Capsule::schema();
		foreach (DoajPaSchema::TABLES as $table) {
			if (!$schema->hasTable($table)) {
				$schema->create($table, $this->definition($table));
			}
		}

		$problems = DoajPaSchemaInspector::problems();
		if ($problems) {
			throw new Exception('DOAJ Pre-Assessment schema is incomplete after migration: ' . implode('; ', $problems));
		}

		Capsule::table('plugin_settings')->updateOrInsert(
			array('plugin_name' => self::PLUGIN_SETTINGS_NAME, 'context_id' => 0, 'setting_name' => 'schemaVersion'),
			array('setting_value' => (string) DoajPaSchema::VERSION, 'setting_type' => 'int')
		);
	}

	/**
	 * Assessment history must survive; the plugin never drops its tables (ADR-004 R1.1).
	 */
	public function down() {
		throw new \PKP\install\DowngradeNotSupportedException();
	}

	/**
	 * @param $table string
	 * @return Closure
	 */
	private function definition($table) {
		switch ($table) {
			case DoajPaSchema::T_DEFINITION: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('definition_id');
				$t->string('criterion_key', 64);
				$t->unsignedInteger('criterion_version');
				$t->unsignedInteger('lineage_version')->comment('first version whose findings stay comparable');
				$t->string('dataset_version', 64);
				$t->string('source_version', 64)->comment('approved DOAJ source version / capture date');
				$t->string('level', 8);
				$t->string('title', 255);
				$t->string('doaj_reference_url', 255);
				$t->dateTime('created_at_utc');
				$t->unique(array('criterion_key', 'criterion_version'), 'doaj_cdef_key_version');
			};
			case DoajPaSchema::T_RUN: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('run_id');
				$t->bigInteger('context_id');
				$t->unsignedInteger('run_number');
				$t->string('status', 20);
				$t->string('rulebook_version', 64);
				$t->string('extraction_version', 64);
				$t->text('source_scope')->comment('JSON');
				$t->string('selected_period', 64)->nullable();
				$t->unsignedBigInteger('previous_run_id')->nullable();
				$t->bigInteger('executor_user_id');
				$t->string('executor_username', 255);
				$t->boolean('dev_exception_used')->default(false);
				$t->text('result_counts')->nullable()->comment('JSON, set on completion');
				$t->text('coverage')->nullable()->comment('JSON');
				$t->char('snapshot_digest', 64)->nullable()->comment('SHA-256 of the sealed snapshot');
				$t->unsignedInteger('lock_version')->default(0);
				$t->dateTime('created_at_utc');
				$t->dateTime('updated_at_utc');
				$t->dateTime('started_at_utc')->nullable();
				$t->dateTime('completed_at_utc')->nullable();
				$t->unique(array('run_id', 'context_id'), 'doaj_run_id_ctx');
				$t->unique(array('context_id', 'run_number'), 'doaj_run_ctx_number');
				$t->index(array('context_id', 'status'), 'doaj_run_ctx_status');
				$t->foreign(array('previous_run_id', 'context_id'), 'doaj_run_prev_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
			};
			case DoajPaSchema::T_ACK: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('acknowledgement_id');
				$t->bigInteger('context_id');
				$t->unsignedBigInteger('run_id')->nullable();
				$t->string('ack_type', 16);
				$t->bigInteger('user_id');
				$t->string('username', 255);
				$t->char('warning_sha256', 64);
				$t->string('warning_source', 64);
				$t->dateTime('acknowledged_at_utc');
				$t->unique(array('run_id'), 'doaj_ack_run');
				$t->index(array('context_id', 'ack_type'), 'doaj_ack_ctx_type');
				$t->foreign(array('run_id', 'context_id'), 'doaj_ack_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
			};
			case DoajPaSchema::T_RESOURCE: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('resource_id');
				$t->bigInteger('context_id');
				$t->unsignedBigInteger('run_id');
				$t->text('url');
				$t->char('url_sha256', 64)->comment('SHA-256 of the normalized URL');
				$t->text('final_url')->nullable();
				$t->smallInteger('http_status')->nullable();
				$t->string('media_type', 127)->nullable();
				$t->char('content_sha256', 64)->nullable();
				$t->unsignedBigInteger('byte_length')->nullable();
				$t->string('acquisition_status', 24);
				$t->string('skip_reason', 255)->nullable();
				$t->string('page_type', 40)->nullable();
				$t->string('extraction_version', 64);
				$t->dateTime('retrieved_at_utc');
				$t->unique(array('resource_id', 'context_id'), 'doaj_res_id_ctx');
				$t->unique(array('run_id', 'url_sha256'), 'doaj_res_run_url');
				$t->foreign(array('run_id', 'context_id'), 'doaj_res_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
			};
			case DoajPaSchema::T_EVIDENCE: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('evidence_id');
				$t->bigInteger('context_id');
				$t->unsignedBigInteger('run_id');
				$t->unsignedBigInteger('resource_id')->nullable();
				$t->text('source_url');
				$t->string('source_title', 255)->nullable();
				$t->string('source_type', 40);
				$t->mediumText('quote');
				$t->char('quote_sha256', 64);
				$t->string('location', 255);
				$t->string('detection_method', 64);
				$t->string('extraction_version', 64);
				$t->dateTime('retrieved_at_utc');
				$t->dateTime('created_at_utc');
				$t->unique(array('evidence_id', 'context_id'), 'doaj_ev_id_ctx');
				$t->index(array('run_id'), 'doaj_ev_run');
				$t->foreign(array('run_id', 'context_id'), 'doaj_ev_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
				$t->foreign(array('resource_id', 'context_id'), 'doaj_ev_res_fk')->references(array('resource_id', 'context_id'))->on(DoajPaSchema::T_RESOURCE);
			};
			case DoajPaSchema::T_RESULT: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('result_id');
				$t->bigInteger('context_id');
				$t->unsignedBigInteger('run_id');
				$t->unsignedBigInteger('definition_id');
				$t->string('machine_status', 24);
				$t->text('rationale');
				$t->text('interpretation_limit');
				$t->unsignedBigInteger('evidence_id')->nullable();
				$t->string('human_status', 24)->nullable();
				$t->text('human_rationale')->nullable();
				$t->bigInteger('human_reviewer_user_id')->nullable();
				$t->dateTime('human_reviewed_at_utc')->nullable();
				$t->dateTime('evaluated_at_utc');
				$t->unique(array('run_id', 'definition_id'), 'doaj_result_run_def');
				$t->foreign(array('run_id', 'context_id'), 'doaj_result_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
				$t->foreign('definition_id', 'doaj_result_def_fk')->references('definition_id')->on(DoajPaSchema::T_DEFINITION);
				$t->foreign(array('evidence_id', 'context_id'), 'doaj_result_ev_fk')->references(array('evidence_id', 'context_id'))->on(DoajPaSchema::T_EVIDENCE);
			};
			case DoajPaSchema::T_FINDING: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('finding_id');
				$t->bigInteger('context_id');
				$t->char('finding_key', 64)->comment('DoajPaFindingKey::build()');
				$t->string('criterion_key', 64);
				$t->unsignedInteger('lineage_version');
				$t->string('subtype', 64);
				$t->string('target_type', 16);
				$t->text('target');
				$t->unsignedBigInteger('first_run_id');
				$t->string('original_status', 24);
				$t->string('current_status', 24);
				$t->boolean('was_resolved')->default(false);
				$t->unsignedBigInteger('last_run_id');
				$t->unsignedInteger('lock_version')->default(0);
				$t->dateTime('created_at_utc');
				$t->dateTime('updated_at_utc');
				$t->unique(array('finding_id', 'context_id'), 'doaj_find_id_ctx');
				$t->unique(array('context_id', 'finding_key'), 'doaj_find_ctx_key');
				$t->index(array('context_id', 'current_status'), 'doaj_find_ctx_status');
				$t->foreign(array('first_run_id', 'context_id'), 'doaj_find_first_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
				$t->foreign(array('last_run_id', 'context_id'), 'doaj_find_last_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
			};
			case DoajPaSchema::T_OBSERVATION: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('observation_id');
				$t->bigInteger('context_id');
				$t->unsignedBigInteger('finding_id');
				$t->unsignedBigInteger('run_id');
				$t->unsignedInteger('criterion_version');
				$t->string('observation', 24);
				$t->string('criterion_status', 24)->nullable();
				$t->string('lifecycle_status', 24);
				$t->unsignedBigInteger('evidence_id')->nullable();
				$t->dateTime('observed_at_utc');
				$t->unique(array('finding_id', 'run_id'), 'doaj_obs_finding_run');
				$t->foreign(array('finding_id', 'context_id'), 'doaj_obs_find_fk')->references(array('finding_id', 'context_id'))->on(DoajPaSchema::T_FINDING);
				$t->foreign(array('run_id', 'context_id'), 'doaj_obs_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
				$t->foreign(array('evidence_id', 'context_id'), 'doaj_obs_ev_fk')->references(array('evidence_id', 'context_id'))->on(DoajPaSchema::T_EVIDENCE);
			};
			case DoajPaSchema::T_TRANSITION: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('transition_id');
				$t->bigInteger('context_id');
				$t->unsignedBigInteger('finding_id');
				$t->unsignedBigInteger('run_id')->nullable();
				$t->string('from_status', 24)->nullable();
				$t->string('to_status', 24);
				$t->string('reason_code', 64);
				$t->string('actor_type', 16);
				$t->bigInteger('actor_user_id')->nullable();
				$t->string('actor_username', 255)->nullable();
				$t->dateTime('created_at_utc');
				$t->index(array('finding_id'), 'doaj_tr_finding');
				$t->foreign(array('finding_id', 'context_id'), 'doaj_tr_find_fk')->references(array('finding_id', 'context_id'))->on(DoajPaSchema::T_FINDING);
				$t->foreign(array('run_id', 'context_id'), 'doaj_tr_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
			};
			case DoajPaSchema::T_ACTION: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('action_id');
				$t->bigInteger('context_id');
				$t->unsignedBigInteger('finding_id')->nullable();
				$t->unsignedBigInteger('run_id')->nullable();
				$t->text('recommendation');
				$t->text('target_url')->nullable();
				$t->string('owner_text', 255)->nullable()->comment('free text; grants no access');
				$t->date('due_date')->nullable();
				$t->string('priority', 16);
				$t->string('status', 32);
				$t->text('completion_evidence')->nullable();
				$t->unsignedInteger('lock_version')->default(0);
				$t->bigInteger('created_by_user_id');
				$t->bigInteger('updated_by_user_id');
				$t->dateTime('created_at_utc');
				$t->dateTime('updated_at_utc');
				$t->index(array('context_id', 'status'), 'doaj_act_ctx_status');
				$t->foreign(array('finding_id', 'context_id'), 'doaj_act_find_fk')->references(array('finding_id', 'context_id'))->on(DoajPaSchema::T_FINDING);
				$t->foreign(array('run_id', 'context_id'), 'doaj_act_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
			};
			case DoajPaSchema::T_FORM_ANSWER: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('answer_id');
				$t->bigInteger('context_id');
				$t->unsignedBigInteger('run_id');
				$t->string('question_key', 8);
				$t->string('answer_status', 24);
				$t->text('answer_text')->nullable();
				$t->unsignedBigInteger('evidence_id')->nullable();
				$t->text('uncertainty')->nullable();
				$t->dateTime('created_at_utc');
				$t->unique(array('run_id', 'question_key'), 'doaj_fq_run_q');
				$t->foreign(array('run_id', 'context_id'), 'doaj_fq_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
				$t->foreign(array('evidence_id', 'context_id'), 'doaj_fq_ev_fk')->references(array('evidence_id', 'context_id'))->on(DoajPaSchema::T_EVIDENCE);
			};
			case DoajPaSchema::T_EXPORT: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('export_id');
				$t->bigInteger('context_id');
				$t->unsignedBigInteger('run_id');
				$t->string('format', 8);
				$t->string('rulebook_version', 64);
				$t->char('run_snapshot_digest', 64);
				$t->string('file_name', 128)->comment('relative to the private run directory');
				$t->char('file_sha256', 64);
				$t->unsignedBigInteger('byte_length');
				$t->bigInteger('created_by_user_id');
				$t->dateTime('created_at_utc');
				$t->foreign(array('run_id', 'context_id'), 'doaj_exp_run_fk')->references(array('run_id', 'context_id'))->on(DoajPaSchema::T_RUN);
			};
			case DoajPaSchema::T_AUDIT: return function (Blueprint $t) {
				$t->engine = 'InnoDB';
				$t->bigIncrements('event_id');
				$t->bigInteger('context_id');
				$t->string('event_type', 64);
				$t->bigInteger('actor_user_id')->nullable();
				$t->string('actor_username', 255)->nullable();
				$t->unsignedBigInteger('run_id')->nullable();
				$t->string('subject_type', 32)->nullable();
				$t->unsignedBigInteger('subject_id')->nullable();
				$t->text('details')->comment('JSON');
				$t->dateTime('created_at_utc');
				$t->index(array('context_id', 'event_type'), 'doaj_aud_ctx_type');
				$t->index(array('run_id'), 'doaj_aud_run');
			};
		}
		throw new Exception('Unknown table ' . $table);
	}
}
