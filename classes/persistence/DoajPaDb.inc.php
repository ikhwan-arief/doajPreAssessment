<?php

/**
 * @file classes/persistence/DoajPaDb.inc.php
 *
 * @class DoajPaDb
 * @brief Database helpers for the plugin's service layer: UTC clock, transactions, JSON and actors.
 *  All queries elsewhere use the Capsule query builder with bound parameters.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

require_once(dirname(__FILE__) . '/DoajPaSchema.inc.php');

class DoajPaException extends Exception {}
/** The requested record does not exist in this journal (no information about other journals). */
class DoajPaNotFoundException extends DoajPaException {}
/** Input failed validation. */
class DoajPaValidationException extends DoajPaException {}
/** The operation is not allowed in the record's current state (e.g. a completed run). */
class DoajPaStateException extends DoajPaException {}
/** Optimistic-lock conflict, deadlock or duplicate key caused by a concurrent writer. */
class DoajPaConcurrencyException extends DoajPaException {}

class DoajPaDb {

	/** @return string UTC timestamp for *_utc columns */
	public static function now() {
		return gmdate('Y-m-d H:i:s');
	}

	/**
	 * Run $callback in one database transaction. Deadlocks and duplicate keys become
	 * DoajPaConcurrencyException; everything else is re-thrown after rollback.
	 * @param $callback callable
	 * @return mixed
	 */
	public static function transaction($callback) {
		try {
			return Capsule::connection()->transaction($callback);
		} catch (\Illuminate\Database\QueryException $e) {
			$code = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
			if (in_array($code, array(1062, 1205, 1213), true)) {
				throw new DoajPaConcurrencyException('A concurrent change was detected; nothing was saved.', 0, $e);
			}
			throw $e;
		}
	}

	/**
	 * @param $name string
	 * @return \Illuminate\Database\Query\Builder
	 */
	public static function table($name) {
		return Capsule::table($name);
	}

	/**
	 * @param $value mixed
	 * @return string
	 */
	public static function json($value) {
		$json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($json === false) throw new DoajPaValidationException('Value cannot be encoded as JSON.');
		return $json;
	}

	/**
	 * @param $userId int
	 * @param $username string
	 * @return array Actor descriptor
	 */
	public static function userActor($userId, $username) {
		if (!is_int($userId) || $userId <= 0) throw new DoajPaValidationException('Invalid actor.');
		return array('type' => 'user', 'userId' => $userId, 'username' => (string) $username);
	}

	/** @return array */
	public static function systemActor() {
		return array('type' => 'system', 'userId' => null, 'username' => null);
	}

	/**
	 * @param $value mixed
	 * @param $label string
	 * @return int Positive integer
	 */
	public static function id($value, $label) {
		if (!is_int($value) || $value <= 0) throw new DoajPaValidationException('Invalid ' . $label . '.');
		return $value;
	}

	/**
	 * @param $value mixed
	 * @param $allowed array
	 * @param $label string
	 * @return string
	 */
	public static function oneOf($value, $allowed, $label) {
		if (!is_string($value) || !in_array($value, $allowed, true)) throw new DoajPaValidationException('Invalid ' . $label . '.');
		return $value;
	}

	/**
	 * @param $value mixed
	 * @param $label string
	 * @param $max int
	 * @param $nullable boolean
	 * @return string|null
	 */
	public static function text($value, $label, $max, $nullable = false) {
		if ($value === null && $nullable) return null;
		if (!is_string($value) || ($value === '' && !$nullable) || strlen($value) > $max) {
			throw new DoajPaValidationException('Invalid ' . $label . '.');
		}
		return $value;
	}
}
