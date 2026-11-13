{**
 * templates/settings.tpl
 *
 * Settings: read-only plugin status, approved rule sets, acknowledgements and the audit log.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.settings"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		<div class="doajPa-tableWrap">
		<table class="doajPa-table">
			<caption>{translate key="plugins.generic.doajPreAssessment.settings.status"}</caption>
			<tbody>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.pluginVersion"}</th><td>{$doajPaPluginVersionFull|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.settings.schema"}</th><td>{if !$doajPaSchemaProblems}<span class="doajPa-badge doajPa-badge--verified">{translate key="plugins.generic.doajPreAssessment.settings.schemaOk"}</span>{else}<span class="doajPa-badge doajPa-badge--failed">{translate key="plugins.generic.doajPreAssessment.settings.schemaProblems"}</span> {foreach from=$doajPaSchemaProblems item=p}{$p|escape}; {/foreach}{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.settings.ruleSets"}</th><td>{if $doajPaApprovedRulebook}{translate key="plugins.generic.doajPreAssessment.settings.approvedRuleSet"}{else}{translate key="plugins.generic.doajPreAssessment.settings.noRuleSets"}{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.settings.editable"}</th><td>{translate key="plugins.generic.doajPreAssessment.settings.noEditable"}</td></tr>
			</tbody>
		</table>
		</div>

		<div class="doajPa-tableWrap">
		<table class="doajPa-table">
			<caption>{translate key="plugins.generic.doajPreAssessment.settings.acknowledgements"}</caption>
			<thead><tr><th scope="col">{translate key="plugins.generic.doajPreAssessment.finding.actor"}</th><th scope="col">{translate key="plugins.generic.doajPreAssessment.common.time"}</th></tr></thead>
			<tbody>
			{foreach from=$doajPaAcknowledgements item=k}
				<tr><td>{$k.username|escape}</td><td>{$k.acknowledged_at_utc|escape} UTC</td></tr>
			{foreachelse}
				<tr><td colspan="2">{translate key="plugins.generic.doajPreAssessment.common.none"}</td></tr>
			{/foreach}
			</tbody>
		</table>
		</div>

		{* The audit log is still recorded (DoajPaAuditService) but not shown in this version (1.2.0.0). *}
	</div>
</div>
{/block}
