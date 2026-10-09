<?php

/**
 * @file classes/migration/DoajPaSchemaInspector.inc.php
 *
 * @class DoajPaSchemaInspector
 * @brief Verifies the installed plugin schema: tables, storage engine and named foreign keys.
 *  The migration is idempotent (create-if-missing), so without this check a table left without its
 *  constraints by an earlier failed run would be accepted silently.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

require_once(dirname(__FILE__) . '/../persistence/DoajPaSchema.inc.php');

class DoajPaSchemaInspector {

	/** Foreign keys that must exist, by table. */
	const FOREIGN_KEYS = array(
		DoajPaSchema::T_RUN => array('doaj_run_prev_fk'),
		DoajPaSchema::T_ACK => array('doaj_ack_run_fk'),
		DoajPaSchema::T_RESOURCE => array('doaj_res_run_fk'),
		DoajPaSchema::T_EVIDENCE => array('doaj_ev_run_fk', 'doaj_ev_res_fk'),
		DoajPaSchema::T_RESULT => array('doaj_result_run_fk', 'doaj_result_def_fk', 'doaj_result_ev_fk'),
		DoajPaSchema::T_FINDING => array('doaj_find_first_run_fk', 'doaj_find_last_run_fk'),
		DoajPaSchema::T_OBSERVATION => array('doaj_obs_find_fk', 'doaj_obs_run_fk', 'doaj_obs_ev_fk'),
		DoajPaSchema::T_TRANSITION => array('doaj_tr_find_fk', 'doaj_tr_run_fk'),
		DoajPaSchema::T_ACTION => array('doaj_act_find_fk', 'doaj_act_run_fk'),
		DoajPaSchema::T_FORM_ANSWER => array('doaj_fq_run_fk', 'doaj_fq_ev_fk'),
		DoajPaSchema::T_EXPORT => array('doaj_exp_run_fk'),
	);

	/**
	 * @return array Human-readable problems; empty when the schema is complete
	 */
	public static function problems() {
		$problems = array();
		$database = Capsule::connection()->getDatabaseName();
		$tables = array();
		foreach (Capsule::select('SELECT TABLE_NAME AS t, ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', array($database)) as $row) {
			$tables[$row->t] = $row->e;
		}
		$fks = array();
		foreach (Capsule::select('SELECT TABLE_NAME AS t, CONSTRAINT_NAME AS c FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ?', array($database)) as $row) {
			$fks[$row->t][] = $row->c;
		}
		foreach (DoajPaSchema::TABLES as $table) {
			if (!isset($tables[$table])) { $problems[] = "missing table $table"; continue; }
			if (strtolower((string) $tables[$table]) !== 'innodb') $problems[] = "table $table is not InnoDB";
			$expected = isset(self::FOREIGN_KEYS[$table]) ? self::FOREIGN_KEYS[$table] : array();
			foreach ($expected as $fk) {
				if (!isset($fks[$table]) || !in_array($fk, $fks[$table], true)) $problems[] = "missing foreign key $fk on $table";
			}
		}
		return $problems;
	}

	/**
	 * Cheap check used on every plugin request: the recorded schema level matches this code.
	 * @return boolean
	 */
	public static function isInstalled() {
		$value = Capsule::table('plugin_settings')
			->where('plugin_name', '=', 'doajpreassessmentplugin')
			->where('context_id', '=', 0)
			->where('setting_name', '=', 'schemaVersion')
			->value('setting_value');
		return $value !== null && (int) $value >= DoajPaSchema::VERSION;
	}
}
