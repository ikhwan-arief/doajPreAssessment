<?php

/**
 * @file classes/services/DoajPaCriteriaRegistry.inc.php
 *
 * @class DoajPaCriteriaRegistry
 * @brief Versioned criterion definitions (Stage 01 F2, F10). A registered (key, version) is immutable:
 *  registering it again with different content is refused, so a pinned rule set never changes silently.
 *  Stage 02 ships no definitions; Stage 03 loads the approved dataset through this class.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAuditService.inc.php');

class DoajPaCriteriaRegistry {

	const FIELDS = array('criterionKey', 'criterionVersion', 'lineageVersion', 'datasetVersion', 'sourceVersion', 'level', 'title', 'doajReferenceUrl');

	/**
	 * @param $definition array Keys of self::FIELDS
	 * @param $actor array
	 * @return int definition_id (existing id if an identical definition is already registered)
	 */
	public static function register($definition, $actor) {
		$key = isset($definition['criterionKey']) ? $definition['criterionKey'] : null;
		if (!is_string($key) || !preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/', $key)) throw new DoajPaValidationException('Invalid criterion key.');
		$version = isset($definition['criterionVersion']) ? $definition['criterionVersion'] : null;
		$lineage = isset($definition['lineageVersion']) ? $definition['lineageVersion'] : null;
		if (!is_int($version) || $version <= 0 || !is_int($lineage) || $lineage <= 0 || $lineage > $version) {
			throw new DoajPaValidationException('Invalid criterion version or lineage.');
		}
		$url = DoajPaDb::text(isset($definition['doajReferenceUrl']) ? $definition['doajReferenceUrl'] : null, 'DOAJ reference URL', 255);
		if (!in_array($url, array('https://doaj.org/apply/guide/', 'https://doaj.org/apply/transparency/', 'https://doaj.org/apply/copyright-and-licensing/'), true)) {
			throw new DoajPaValidationException('The DOAJ reference must be one of the three official pages.');
		}
		$row = array(
			'criterion_key' => $key, 'criterion_version' => $version, 'lineage_version' => $lineage,
			'dataset_version' => DoajPaDb::text(isset($definition['datasetVersion']) ? $definition['datasetVersion'] : null, 'dataset version', 64),
			'source_version' => DoajPaDb::text(isset($definition['sourceVersion']) ? $definition['sourceVersion'] : null, 'source version', 64),
			'level' => DoajPaDb::oneOf(isset($definition['level']) ? $definition['level'] : null, DoajPaSchema::LEVELS, 'level'),
			'title' => DoajPaDb::text(isset($definition['title']) ? $definition['title'] : null, 'title', 255),
			'doaj_reference_url' => $url,
		);
		return DoajPaDb::transaction(function () use ($row, $actor) {
			$existing = DoajPaDb::table(DoajPaSchema::T_DEFINITION)->where('criterion_key', '=', $row['criterion_key'])
				->where('criterion_version', '=', $row['criterion_version'])->lockForUpdate()->first();
			if ($existing) {
				foreach ($row as $column => $value) {
					if ((string) $existing->$column !== (string) $value) {
						throw new DoajPaStateException('Criterion ' . $row['criterion_key'] . ' v' . $row['criterion_version'] . ' is already registered with different content.');
					}
				}
				return (int) $existing->definition_id;
			}
			$row['created_at_utc'] = DoajPaDb::now();
			$id = (int) DoajPaDb::table(DoajPaSchema::T_DEFINITION)->insertGetId($row);
			DoajPaAuditService::record(0, 'criterion_definition_registered', $actor, null, 'criterion_definition', $id,
				array('key' => $row['criterion_key'], 'version' => $row['criterion_version'], 'dataset' => $row['dataset_version']));
			return $id;
		});
	}
}
