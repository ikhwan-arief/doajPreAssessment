{**
 * templates/actions.tpl
 *
 * Corrective Actions: register and new-action form.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.actions"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{if $doajPaSaved}<p class="doajPa-notice doajPa-notice--success" role="status">{translate key="plugins.generic.doajPreAssessment.actions.saved"} A-{$doajPaSaved|escape}</p>{/if}
		<p class="doajPa-notice doajPa-notice--info">{translate key="plugins.generic.doajPreAssessment.actions.notEvidence"}</p>

		<div class="doajPa-tableWrap">
		<table class="doajPa-table">
			<caption>{translate key="plugins.generic.doajPreAssessment.actions.register"}</caption>
			<thead><tr>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.actions.action"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.finding"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.actions.recommendation"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.actions.targetUrl"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.actions.owner"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.actions.dueDate"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.actions.priority"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.status"}</th>
			</tr></thead>
			<tbody>
			{foreach from=$doajPaActions item=a}
				<tr>
					<th scope="row"><a href="{url page=$doajPaPage op="actionEdit" path=$a.action_id}">A-{$a.action_id|escape}</a></th>
					<td>{if $a.finding_id}<a href="{url page=$doajPaPage op="finding" path=$a.finding_id}">F-{$a.finding_id|escape}</a>{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td>
					<td>{$a.recommendation|truncate:160|escape}</td>
					<td>{if $a.target_url}{include file=$doajPaLink url=$a.target_url isLink=$a.targetUrlIsLink}{/if}</td>
					<td>{$a.owner_text|escape}</td>
					<td>{$a.due_date|escape}</td>
					<td>{include file=$doajPaBadge group="priority" code=$a.priority}</td>
					<td>{include file=$doajPaBadge group="action" code=$a.status}</td>
				</tr>
			{foreachelse}
				<tr><td colspan="8">{translate key="plugins.generic.doajPreAssessment.actions.none"}</td></tr>
			{/foreach}
			</tbody>
		</table>
		</div>

		<h2 id="doajPaNewAction">{translate key="plugins.generic.doajPreAssessment.actions.new"}</h2>
		{if $doajPaError}<p class="doajPa-notice doajPa-notice--error" role="alert"><strong>{translate key="plugins.generic.doajPreAssessment.gate.errorLabel"}</strong> {if $doajPaError == 'invalidFinding'}{translate key="plugins.generic.doajPreAssessment.actions.invalidFinding"}{else}{translate key="plugins.generic.doajPreAssessment.actions.invalid"}{/if}</p>{/if}
		<form class="doajPa-form" method="post" action="{url page=$doajPaPage op="actionCreate"}" aria-labelledby="doajPaNewAction">
			{csrf}
			<div class="doajPa-field">
				<label for="doajPaFinding">{translate key="plugins.generic.doajPreAssessment.common.finding"}</label>
				<select id="doajPaFinding" name="findingId">
					<option value="">{translate key="plugins.generic.doajPreAssessment.actions.noFinding"}</option>
					{foreach from=$doajPaFindingChoices item=fc}<option value="{$fc.finding_id|escape}"{if $doajPaForm.findingId == $fc.finding_id} selected{/if}>F-{$fc.finding_id|escape} {$fc.criterion_key|escape} ({$fc.subtype|escape}) {$fc.target|truncate:80|escape}</option>{/foreach}
				</select>
			</div>
			{include file=$doajPaActionFields v=$doajPaForm editing=false}
			<p><button type="submit" class="pkpButton">{translate key="plugins.generic.doajPreAssessment.actions.create"}</button></p>
		</form>
	</div>
</div>
{/block}
