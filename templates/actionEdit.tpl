{**
 * templates/actionEdit.tpl
 *
 * Edit one corrective action. The lock version detects concurrent edits (HTTP 409, nothing saved).
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	{assign var=a value=$doajPaAction}
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.actions.action"} A-{$a.action_id|escape}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{if $doajPaError == 'conflict'}
			<p class="doajPa-notice doajPa-notice--error" role="alert"><strong>{translate key="plugins.generic.doajPreAssessment.actions.conflictLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.actions.conflict"}</p>
		{elseif $doajPaError}
			<p class="doajPa-notice doajPa-notice--error" role="alert"><strong>{translate key="plugins.generic.doajPreAssessment.gate.errorLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.actions.invalid"}</p>
		{/if}
		<p class="doajPa-notice doajPa-notice--info">{translate key="plugins.generic.doajPreAssessment.actions.notEvidence"}</p>
		<p>{translate key="plugins.generic.doajPreAssessment.common.finding"}: {if $a.finding_id}<a href="{url page=$doajPaPage op="finding" path=$a.finding_id}">F-{$a.finding_id|escape}</a>{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}.
		{translate key="plugins.generic.doajPreAssessment.actions.lastUpdated"} {$a.updated_at_utc|escape} UTC.</p>
		{if $doajPaError == 'conflict' || !$doajPaForm}
			{assign var=v value=['recommendation' => $a.recommendation, 'targetUrl' => $a.target_url, 'ownerText' => $a.owner_text, 'dueDate' => $a.due_date, 'priority' => $a.priority, 'status' => $a.status, 'completionEvidence' => $a.completion_evidence]}
		{else}
			{assign var=v value=$doajPaForm}
		{/if}
		<form class="doajPa-form" method="post" action="{url page=$doajPaPage op="actionUpdate" path=$a.action_id}">
			{csrf}
			<input type="hidden" name="lockVersion" value="{$a.lock_version|escape}" />
			{include file=$doajPaActionFields v=$v editing=true}
			<p><button type="submit" class="pkpButton">{translate key="plugins.generic.doajPreAssessment.common.save"}</button>
			<a href="{url page=$doajPaPage op="actions"}">{translate key="plugins.generic.doajPreAssessment.common.cancel"}</a></p>
		</form>
	</div>
</div>
{/block}
