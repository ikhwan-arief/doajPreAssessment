{**
 * templates/beforeYouApply.tpl
 *
 * Before You Apply (Stage 01 F7 item 4, AC-14): corrections of one completed run, grouped by the website page to
 * correct, with the accountable corrective-action rows of each finding. Unverifiable items are listed separately.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.beforeYouApply"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{if !$doajPaRun}
			<p class="doajPa-notice doajPa-notice--info" role="status">{translate key="plugins.generic.doajPreAssessment.bya.noRun"}</p>
		{else}
			{include file=$doajPaNotResult rulebook=$doajPaRun.rulebook_version}
			<p>{translate key="plugins.generic.doajPreAssessment.bya.intro"}</p>
			<form class="doajPa-form doajPa-form--inline" method="get" action="{url page=$doajPaPage op="beforeYouApply"}">
				<label for="doajPaByaRun">{translate key="plugins.generic.doajPreAssessment.common.run"}</label>
				<select id="doajPaByaRun" name="run">
					{foreach from=$doajPaCompletedRuns item=r}<option value="{$r.run_id|escape}"{if $r.run_id == $doajPaRun.run_id} selected{/if}>{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$r.run_number|escape} ({$r.completed_at_utc|escape} UTC)</option>{/foreach}
				</select>
				<button type="submit" class="pkpButton">{translate key="plugins.generic.doajPreAssessment.bya.show"}</button>
			</form>
			<p class="doajPa-meta">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$doajPaRun.run_number|escape} &middot; <a href="{url page=$doajPaPage op="run" path=$doajPaRun.run_id}">{translate key="plugins.generic.doajPreAssessment.bya.openRun"}</a> &middot;
				{translate key="plugins.generic.doajPreAssessment.bya.counts" tasks=$doajPaPlan.counts.tasks pages=$doajPaPlan.counts.pages withAction=$doajPaPlan.counts.withAction verify=$doajPaPlan.counts.verify}</p>
			<p class="doajPa-notice doajPa-notice--info">{translate key="plugins.generic.doajPreAssessment.actions.notEvidence"}</p>

			{if !$doajPaPlan.groups}
				<p>{translate key="plugins.generic.doajPreAssessment.bya.none"}</p>
			{/if}
			{foreach from=$doajPaPlan.groups item=g name=groups}
				<section class="doajPa-group" aria-labelledby="doajPaByaGroup{$smarty.foreach.groups.iteration|escape}">
					<h2 id="doajPaByaGroup{$smarty.foreach.groups.iteration|escape}">{if $g.page}{translate key="plugins.generic.doajPreAssessment.bya.page"} <a href="{$g.page|escape}" target="_blank" rel="noopener noreferrer nofollow" class="doajPa-url">{$g.page|escape}</a>{else}{translate key="plugins.generic.doajPreAssessment.bya.noPage"}{/if}</h2>
					<div class="doajPa-tableWrap">
					<table class="doajPa-table">
						<caption>{translate key="plugins.generic.doajPreAssessment.bya.tasks"}</caption>
						<thead><tr>
							<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.criterion"}</th>
							<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.status"}</th>
							<th scope="col">{translate key="plugins.generic.doajPreAssessment.bya.correction"}</th>
							<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.finding"}</th>
							<th scope="col">{translate key="plugins.generic.doajPreAssessment.bya.actions"}</th>
						</tr></thead>
						<tbody>
						{foreach from=$g.tasks item=t}
							<tr>
								<th scope="row">{$t.title|escape}<br>{include file=$doajPaBadge group="level" code=$t.level}</th>
								<td>{include file=$doajPaBadge group="criterion" code=$t.status}</td>
								<td>{if $t.recommendation}{$t.recommendation|escape}{else}{$t.rationale|escape}{/if}
									{if $t.clause}<blockquote class="doajPa-quote">DOAJ: &ldquo;{$t.clause|escape}&rdquo; (<a href="{$t.doajReferenceUrl|escape}" target="_blank" rel="noopener noreferrer">{$t.doajReferenceUrl|escape}</a>)</blockquote>{/if}</td>
								<td>{if $t.findingId}<a href="{url page=$doajPaPage op="finding" path=$t.findingId}">{translate key="plugins.generic.doajPreAssessment.common.viewFinding"}</a><br>{include file=$doajPaBadge group="lifecycle" code=$t.lifecycle}{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td>
								<td>
									{foreach from=$t.actions item=a}
										<p class="doajPa-actionRow"><a href="{url page=$doajPaPage op="actionEdit" path=$a.action_id}">{translate key="plugins.generic.doajPreAssessment.actions.edit"}</a> {include file=$doajPaBadge group="action" code=$a.status} {include file=$doajPaBadge group="priority" code=$a.priority}<br>
										{translate key="plugins.generic.doajPreAssessment.actions.owner"}: {if $a.owner_text}{$a.owner_text|escape}{else}&ndash;{/if}; {translate key="plugins.generic.doajPreAssessment.actions.dueDate"}: {if $a.due_date}{$a.due_date|escape}{else}&ndash;{/if}</p>
									{/foreach}
									{if $t.findingId}<a class="pkpButton" href="{url page=$doajPaPage op="actions" findingId=$t.findingId run=$doajPaRun.run_id}#doajPaNewAction">{translate key="plugins.generic.doajPreAssessment.bya.createAction"}</a>{/if}
								</td>
							</tr>
						{/foreach}
						</tbody>
					</table>
					</div>
				</section>
			{/foreach}

			{if $doajPaPlan.verify}
				<h2 id="doajPaByaVerify">{translate key="plugins.generic.doajPreAssessment.bya.verify" count=$doajPaPlan.counts.verify}</h2>
				<p>{translate key="plugins.generic.doajPreAssessment.bya.verifyIntro"}</p>
				{foreach from=$doajPaPlan.verify item=g}
					<h3>{if $g.page}<span class="doajPa-url">{$g.page|escape}</span>{else}{translate key="plugins.generic.doajPreAssessment.bya.noPage"}{/if}</h3>
					<ul class="doajPa-list">
						{foreach from=$g.tasks item=t}<li>{$t.title|escape} ({include file=$doajPaBadge group="level" code=$t.level}): {$t.rationale|truncate:400|escape}</li>{/foreach}
					</ul>
				{/foreach}
			{/if}
			{include file=$doajPaRefs}
		{/if}
	</div>
</div>
{/block}
