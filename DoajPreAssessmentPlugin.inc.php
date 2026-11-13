<?php

/**
 * @file DoajPreAssessmentPlugin.inc.php
 *
 * @class DoajPreAssessmentPlugin
 * @brief DOAJ Pre-Assessment generic plugin for OJS 3.3 (Stage 02 foundation build).
 *
 * The plugin is backend-only. When disabled for a journal it registers no feature hooks, so its
 * page route does not exist (404). It never hooks public article/issue templates or the editorial
 * workflow.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class DoajPreAssessmentPlugin extends GenericPlugin {

	/** Page name of the backend route: /index.php/{journalPath}/doajPreAssessment/{op} */
	const PAGE = 'doajPreAssessment';

	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!$success) return false;
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;

		// Lifecycle only: it acts solely when this plugin is enabled in the plugin grid
		// (ADR-007 item 2). It must be registered while the plugin is still disabled.
		HookRegistry::register('PluginGridHandler::plugin', array($this, 'callbackPluginGridAction'));

		if ($this->getEnabled($mainContextId)) {
			HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));
			HookRegistry::register('TemplateManager::setupBackendPage', array($this, 'callbackSetupBackendPage'));
		}
		return true;
	}

	/**
	 * Schema for the OJS install/upgrade path (Installer::postInstall). Upload installs and plugin upgrades
	 * use install.xml/upgrade.xml. The migration is idempotent (ADR-004).
	 * @copydoc Plugin::getInstallMigration()
	 */
	public function getInstallMigration() {
		$this->import('classes.migration.DoajPaSchemaMigration');
		return new DoajPaSchemaMigration();
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.generic.doajPreAssessment.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription() {
		return __('plugins.generic.doajPreAssessment.description');
	}

	/**
	 * Plugin text is English only (PROD-04): register the en_US file whatever the UI locale is,
	 * because OJS 3.3 shows ##key## instead of falling back to English (PKPLocale::translate()).
	 * @copydoc Plugin::getLocaleFilename()
	 */
	public function getLocaleFilename($locale) {
		$file = $this->getPluginPath() . '/locale/en_US/locale.po';
		return file_exists($file) ? array($file) : array();
	}

	/**
	 * Offer a link to the plugin's backend page, only to users the plugin authorizes.
	 * @copydoc Plugin::getActions()
	 */
	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) return $actions;

		$this->import('classes.auth.DoajPaAccessPolicy');
		if (!DoajPaAccessPolicy::isRequestAuthorized($request)) return $actions;

		import('lib.pkp.classes.linkAction.request.RedirectAction');
		$dispatcher = $request->getDispatcher();
		array_unshift($actions, new LinkAction(
			'doajPreAssessmentOpen',
			new RedirectAction($dispatcher->url($request, ROUTE_PAGE, null, self::PAGE)),
			__('plugins.generic.doajPreAssessment.action.open'),
			null
		));
		return $actions;
	}

	/**
	 * The plugin offers no management verbs through the plugin grid. The grid's `manage` op admits
	 * any site administrator and any manager, so every verb is refused here.
	 * @copydoc Plugin::manage()
	 */
	public function manage($args, $request) {
		import('lib.pkp.classes.core.JSONMessage');
		return new JSONMessage(false, __('plugins.generic.doajPreAssessment.error.forbidden'));
	}

	/**
	 * Add the plugin to the backend menu, only for users the plugin authorizes. The menu is cosmetic: every
	 * request is authorized by the handler (ADR-002).
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	public function callbackSetupBackendPage($hookName, $args) {
		$request = Application::get()->getRequest();
		if (!$request->getContext()) return false;
		$this->import('classes.auth.DoajPaAccessPolicy');
		if (!DoajPaAccessPolicy::isRequestAuthorized($request)) return false;
		$templateMgr = TemplateManager::getManager($request);
		$menu = (array) $templateMgr->getState('menu');
		$menu['doajPreAssessment'] = array(
			'name' => __('plugins.generic.doajPreAssessment.displayName'),
			'url' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::PAGE),
			'isCurrent' => $request->getRequestedPage() === self::PAGE,
		);
		$templateMgr->setState(array('menu' => $menu));
		return false;
	}

	/**
	 * Route the plugin's backend page to its handler.
	 * @param $hookName string
	 * @param $args array [&$page, &$op, &$sourceFile]
	 * @return boolean
	 */
	public function callbackLoadHandler($hookName, $args) {
		$page = $args[0];
		if ($page !== self::PAGE || defined('HANDLER_CLASS')) return false;

		$this->import('pages.DoajPreAssessmentHandler');
		define('HANDLER_CLASS', 'DoajPreAssessmentHandler');
		DoajPreAssessmentHandler::setPlugin($this);
		return true;
	}

	/**
	 * After this plugin is enabled in the plugin grid, show the English-only warning to the user who
	 * enabled it. OJS 3.3 offers no hook before enabling (PluginGridCellProvider: direct AjaxAction).
	 * @param $hookName string
	 * @param $args array [$verb, $args, $message, $messageParams, $plugin]
	 * @return boolean
	 */
	public function callbackPluginGridAction($hookName, $args) {
		if (!isset($args[0], $args[4]) || $args[0] !== 'enable') return false;
		$plugin = $args[4];
		if (!is_object($plugin) || !method_exists($plugin, 'getName') || $plugin->getName() !== $this->getName()) return false;

		$user = Application::get()->getRequest()->getUser();
		if (!$user) return false;

		$this->import('classes.gate.DoajPaEnglishGate');
		import('classes.notification.NotificationManager');
		$notificationManager = new NotificationManager();
		$notificationManager->createTrivialNotification(
			$user->getId(),
			NOTIFICATION_TYPE_WARNING,
			array('contents' => DoajPaEnglishGate::WARNING_TEXT)
		);
		return false;
	}
}
