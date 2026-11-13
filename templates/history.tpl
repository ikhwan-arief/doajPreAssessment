{**
 * templates/history.tpl
 *
 * Assessment History: every run, newest first, and a form to compare two runs.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.history"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{if $doajPaDeleted}<p class="doajPa-notice doajPa-notice--info" role="status">{translate key="plugins.generic.doajPreAssessment.history.deleted" number=$doajPaDeleted|escape}</p>{/if}
		{if $doajPaDeletedAll !== null}<p class="doajPa-notice doajPa-notice--info" role="status">{translate key="plugins.generic.doajPreAssessment.history.deletedAll" count=$doajPaDeletedAll|escape}</p>{/if}
		{if $doajPaError}<p class="doajPa-notice doajPa-notice--error" role="alert">{translate key="plugins.generic.doajPreAssessment.history.error.`$doajPaError`"}</p>{/if}
		{if !$doajPaRuns}
			<p class="doajPa-empty">{translate key="plugins.generic.doajPreAssessment.overview.noRuns.text"}</p>
		{else}
			<p>{translate key="plugins.generic.doajPreAssessment.history.intro"}</p>
			<form class="doajPa-form" method="post" action="{url page=$doajPaPage op="runDelete"}" onsubmit="return confirm('{translate key="plugins.generic.doajPreAssessment.history.deleteSelectedConfirm"|escape:"javascript"|escape}');">
			{csrf}
			<div class="doajPa-tableWrap">
			<table class="doajPa-table">
				<caption>{translate key="plugins.generic.doajPreAssessment.history.caption"}</caption>
				<thead><tr>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.run"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.status"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.created"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.completed"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.executor"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.ruleSet"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.history.gaps"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.history.select"}</th>
				</tr></thead>
				<tbody>
				{foreach from=$doajPaRuns item=r}
					<tr>
						<th scope="row"><a href="{url page=$doajPaPage op="run" path=$r.run_id}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$r.run_number|escape}</a></th>
						<td>{include file=$doajPaBadge group="run" code=$r.status}</td>
						<td>{$r.created_at_utc|escape} UTC</td>
						<td>{if $r.completed_at_utc}{$r.completed_at_utc|escape} UTC{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td>
						<td>{$r.executor_username|escape}</td>
						<td>{if $r.rulebookApproved}{translate key="plugins.generic.doajPreAssessment.history.approvedCriteria"}{else}{$r.rulebook_version|escape} <span class="doajPa-badge doajPa-badge--failed">{translate key="plugins.generic.doajPreAssessment.common.notApproved"}</span>{/if}</td>
						<td>{$r.gapCount|escape}</td>
						<td>{if isset($doajPaDeletable[$r.run_id])}<input type="checkbox" name="runIds[]" value="{$r.run_id|escape}" id="doajPaDel{$r.run_id|escape}" /> <label for="doajPaDel{$r.run_id|escape}">{translate key="plugins.generic.doajPreAssessment.history.selectRun" number=$r.run_number|escape}</label>{else}<span class="doajPa-muted">{translate key="plugins.generic.doajPreAssessment.history.deleteActive"}</span>{/if}</td>
					</tr>
				{/foreach}
				</tbody>
			</table>
			</div>
			{if $doajPaDeletable}<p><button type="submit" class="pkpButton pkpButton--isWarnable">{translate key="plugins.generic.doajPreAssessment.history.deleteSelected"}</button></p>{/if}
			</form>

			<form class="doajPa-form" method="get" action="{url page=$doajPaPage op="compare"}">
				<fieldset>
					<legend>{translate key="plugins.generic.doajPreAssessment.compare.title"}</legend>
					<label for="doajPaFrom">{translate key="plugins.generic.doajPreAssessment.compare.from"}</label>
					<select id="doajPaFrom" name="from">
						{foreach from=$doajPaRuns item=r}{if $r.status == 'completed'}<option value="{$r.run_id|escape}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$r.run_number|escape}</option>{/if}{/foreach}
					</select>
					<label for="doajPaTo">{translate key="plugins.generic.doajPreAssessment.compare.to"}</label>
					<select id="doajPaTo" name="to">
						{foreach from=$doajPaRuns item=r}{if $r.status == 'completed'}<option value="{$r.run_id|escape}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$r.run_number|escape}</option>{/if}{/foreach}
					</select>
					<button type="submit" class="pkpButton">{translate key="plugins.generic.doajPreAssessment.compare.submit"}</button>
				</fieldset>
			</form>

			<form class="doajPa-form" method="post" action="{url page=$doajPaPage op="historyDelete"}" onsubmit="return confirm('{translate key="plugins.generic.doajPreAssessment.history.deleteAllConfirm"|escape:"javascript"|escape}');">
				{csrf}
				<fieldset>
					<legend>{translate key="plugins.generic.doajPreAssessment.history.deleteAll"}</legend>
					<p>{translate key="plugins.generic.doajPreAssessment.history.deleteAllText"}</p>
					<label for="doajPaConfirmDeleteAll">{translate key="plugins.generic.doajPreAssessment.history.deleteAllType"}</label>
					<input type="text" id="doajPaConfirmDeleteAll" name="confirmDeleteAll" autocomplete="off" required pattern="DELETE" />
					<button type="submit" class="pkpButton pkpButton--isWarnable">{translate key="plugins.generic.doajPreAssessment.history.deleteAll"}</button>
				</fieldset>
			</form>
		{/if}
	</div>
</div>
{/block}
