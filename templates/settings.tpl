{**
 * templates/settings.tpl
 *
 * Settings: read-only plugin status, approved rule sets, acknowledgements and the audit log.
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
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.settings.schema"}</th><td>{$doajPaSchemaVersion|escape} {if !$doajPaSchemaProblems}<span class="doajPa-badge doajPa-badge--verified">{translate key="plugins.generic.doajPreAssessment.settings.schemaOk"}</span>{else}<span class="doajPa-badge doajPa-badge--failed">{translate key="plugins.generic.doajPreAssessment.settings.schemaProblems"}</span> {foreach from=$doajPaSchemaProblems item=p}{$p|escape}; {/foreach}{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.settings.ruleSets"}</th><td>{translate key="plugins.generic.doajPreAssessment.settings.noRuleSets"}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.settings.editable"}</th><td>{translate key="plugins.generic.doajPreAssessment.settings.noEditable"}</td></tr>
			</tbody>
		</table>
		</div>

		<div class="doajPa-tableWrap">
		<table class="doajPa-table">
			<caption>{translate key="plugins.generic.doajPreAssessment.settings.acknowledgements"}</caption>
			<thead><tr><th scope="col">{translate key="plugins.generic.doajPreAssessment.finding.actor"}</th><th scope="col">{translate key="plugins.generic.doajPreAssessment.common.time"}</th><th scope="col">{translate key="plugins.generic.doajPreAssessment.settings.textHash"}</th></tr></thead>
			<tbody>
			{foreach from=$doajPaAcknowledgements item=k}
				<tr><td>{$k.username|escape}</td><td>{$k.acknowledged_at_utc|escape} UTC</td><td><code class="doajPa-digest">{$k.warning_sha256|escape}</code></td></tr>
			{foreachelse}
				<tr><td colspan="3">{translate key="plugins.generic.doajPreAssessment.common.none"}</td></tr>
			{/foreach}
			</tbody>
		</table>
		</div>

		<h2 id="doajPaAudit">{translate key="plugins.generic.doajPreAssessment.settings.audit"}</h2>
		<p>{translate key="plugins.generic.doajPreAssessment.settings.auditNote"}</p>
		<div class="doajPa-tableWrap">
		<table class="doajPa-table" aria-labelledby="doajPaAudit">
			<thead><tr>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.time"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.settings.event"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.finding.actor"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.run"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.settings.details"}</th>
			</tr></thead>
			<tbody>
			{foreach from=$doajPaAudit item=e}
				<tr><td>{$e.created_at_utc|escape} UTC</td><td><code>{$e.event_type|escape}</code></td><td>{if $e.actor_username}{$e.actor_username|escape}{else}{translate key="plugins.generic.doajPreAssessment.settings.system"}{/if}</td><td>{$e.run_id|escape}</td><td><code class="doajPa-details">{$e.details|truncate:300|escape}</code></td></tr>
			{foreachelse}
				<tr><td colspan="5">{translate key="plugins.generic.doajPreAssessment.common.none"}</td></tr>
			{/foreach}
			</tbody>
		</table>
		</div>
	</div>
</div>
{/block}
