{**
 * templates/history.tpl
 *
 * Assessment History: every run, newest first, and a form to compare two runs.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.history"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{if !$doajPaRuns}
			<p class="doajPa-empty">{translate key="plugins.generic.doajPreAssessment.overview.noRuns.text"}</p>
		{else}
			<p>{translate key="plugins.generic.doajPreAssessment.history.intro"}</p>
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
				</tr></thead>
				<tbody>
				{foreach from=$doajPaRuns item=r}
					<tr>
						<th scope="row"><a href="{url page=$doajPaPage op="run" path=$r.run_id}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$r.run_number|escape}</a></th>
						<td>{include file=$doajPaBadge group="run" code=$r.status}</td>
						<td>{$r.created_at_utc|escape} UTC</td>
						<td>{if $r.completed_at_utc}{$r.completed_at_utc|escape} UTC{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td>
						<td>{$r.executor_username|escape}</td>
						<td><code>{$r.rulebook_version|escape}</code> {if $r.rulebookApproved}<span class="doajPa-badge doajPa-badge--verified">{translate key="plugins.generic.doajPreAssessment.common.approved"}</span>{else}<span class="doajPa-badge doajPa-badge--failed">{translate key="plugins.generic.doajPreAssessment.common.notApproved"}</span>{/if}</td>
						<td>{$r.gapCount|escape}</td>
					</tr>
				{/foreach}
				</tbody>
			</table>
			</div>

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
		{/if}
	</div>
</div>
{/block}
