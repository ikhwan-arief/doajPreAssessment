<?php

/**
 * @file classes/auth/DoajPaRoleQuery.inc.php
 *
 * @class DoajPaRoleQuery
 * @brief Read-only role lookups in OJS core tables, used for authorization only (PROD-07).
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class DoajPaRoleQuery {

	/**
	 * Locale key that OJS stores on the default "Journal manager" group when a journal is created
	 * (registry/userGroups.xml:17; UserGroupDAO::installSettings()). It survives renaming the group.
	 */
	const MANAGER_GROUP_NAME_KEY = 'default.groups.name.manager';

	/**
	 * Whether the user is a site administrator (site-level group, context 0).
	 * @param $userId int
	 * @return boolean
	 */
	public static function isSiteAdmin($userId) {
		$roleDao = DAORegistry::getDAO('RoleDAO'); /* @var $roleDao RoleDAO */
		return (bool) $roleDao->userHasRole(CONTEXT_SITE, (int) $userId, ROLE_ID_SITE_ADMIN);
	}

	/**
	 * Whether the user belongs to the default "Journal manager" group of the given journal.
	 * Other groups holding ROLE_ID_MANAGER ("Journal editor", "Production editor", custom groups)
	 * do not qualify (ADR-002 R1.2).
	 * @param $contextId int
	 * @param $userId int
	 * @return boolean
	 */
	public static function isDefaultJournalManager($contextId, $userId) {
		return Capsule::table('user_user_groups as uug')
			->join('user_groups as ug', 'ug.user_group_id', '=', 'uug.user_group_id')
			->join('user_group_settings as ugs', 'ugs.user_group_id', '=', 'ug.user_group_id')
			->where('uug.user_id', '=', (int) $userId)
			->where('ug.context_id', '=', (int) $contextId)
			->where('ug.role_id', '=', ROLE_ID_MANAGER)
			->where('ugs.setting_name', '=', 'nameLocaleKey')
			->where('ugs.setting_value', '=', self::MANAGER_GROUP_NAME_KEY)
			->exists();
	}
}
