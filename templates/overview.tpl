{**
 * templates/overview.tpl
 *
 * Overview (dashboard). Only stored records are summarized; nothing is estimated or scored.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.overview"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		<p><strong>{translate key="plugins.generic.doajPreAssessment.overview.statusLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.overview.status"}</p>
		<p>{translate key="plugins.generic.doajPreAssessment.overview.disclaimer"}</p>
		{assign var=d value=$doajPaDashboard}

		{if $d.runCount == 0}
			<section class="doajPa-empty" aria-labelledby="doajPaEmpty">
				<h2 id="doajPaEmpty">{translate key="plugins.generic.doajPreAssessment.overview.noRuns.title"}</h2>
				<p>{translate key="plugins.generic.doajPreAssessment.overview.noRuns.text"}</p>
			</section>
		{else}
			{if $d.activeRun}
				<p class="doajPa-notice doajPa-notice--info"><strong>{translate key="plugins.generic.doajPreAssessment.overview.activeRun"}</strong>
				<a href="{url page=$doajPaPage op="run" path=$d.activeRun.run_id}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$d.activeRun.run_number|escape}</a>
				{include file=$doajPaBadge group="run" code=$d.activeRun.status}
				{if $d.activeRun.status == 'interrupted' || $d.activeRun.status == 'human_review'}<span>{translate key="plugins.generic.doajPreAssessment.overview.partial"}</span>{/if}</p>
			{/if}

			<section aria-labelledby="doajPaLatest">
				<h2 id="doajPaLatest">{translate key="plugins.generic.doajPreAssessment.overview.latest"}</h2>
				{if $d.latestRun}
					{include file=$doajPaNotResult rulebook=$d.latestRun.rulebook_version}
					<p><a href="{url page=$doajPaPage op="run" path=$d.latestRun.run_id}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$d.latestRun.run_number|escape}</a>,
					{translate key="plugins.generic.doajPreAssessment.common.completed"} {$d.latestRun.completed_at_utc|escape} UTC.
					{if $d.latestRun.snapshotVerified}<span class="doajPa-badge doajPa-badge--verified">{translate key="plugins.generic.doajPreAssessment.common.snapshotVerified"}</span>{else}<span class="doajPa-badge doajPa-badge--failed">{translate key="plugins.generic.doajPreAssessment.common.snapshotFailed"}</span>{/if}</p>
					<div class="doajPa-tableWrap">
					<table class="doajPa-table">
						<caption>{translate key="plugins.generic.doajPreAssessment.overview.latestFindings"}</caption>
						<thead><tr><th scope="col">{translate key="plugins.generic.doajPreAssessment.common.status"}</th><th scope="col">{translate key="plugins.generic.doajPreAssessment.common.count"}</th></tr></thead>
						<tbody>
						{foreach from=$d.latestObservationCounts key=code item=n}
							<tr><td>{include file=$doajPaBadge group="lifecycle" code=$code}</td><td>{$n|escape}</td></tr>
						{foreachelse}
							<tr><td colspan="2">{translate key="plugins.generic.doajPreAssessment.overview.noFindingsInRun"}</td></tr>
						{/foreach}
						</tbody>
					</table>
					</div>
					<div class="doajPa-tableWrap">
					<table class="doajPa-table">
						<caption>{translate key="plugins.generic.doajPreAssessment.overview.coverage"}</caption>
						<thead><tr><th scope="col">{translate key="plugins.generic.doajPreAssessment.common.sourceState"}</th><th scope="col">{translate key="plugins.generic.doajPreAssessment.common.count"}</th></tr></thead>
						<tbody>
						{foreach from=$d.latestCoverage key=code item=n}
							<tr><td>{include file=$doajPaBadge group="acquisition" code=$code}</td><td>{$n|escape}</td></tr>
						{foreachelse}
							<tr><td colspan="2">{translate key="plugins.generic.doajPreAssessment.overview.noPages"}</td></tr>
						{/foreach}
						</tbody>
					</table>
					</div>
				{else}
					<p>{translate key="plugins.generic.doajPreAssessment.overview.noCompleted"}</p>
				{/if}
			</section>

			<section aria-labelledby="doajPaCurrent">
				<h2 id="doajPaCurrent">{translate key="plugins.generic.doajPreAssessment.overview.current"}</h2>
				<p>{translate key="plugins.generic.doajPreAssessment.overview.currentNote"}</p>
				<ul class="doajPa-counts">
				{foreach from=$d.currentFindingCounts key=code item=n}
					<li>{include file=$doajPaBadge group="lifecycle" code=$code} {$n|escape}</li>
				{foreachelse}
					<li>{translate key="plugins.generic.doajPreAssessment.common.none"}</li>
				{/foreach}
				</ul>
				<h2>{translate key="plugins.generic.doajPreAssessment.nav.actions"}</h2>
				<ul class="doajPa-counts">
				{foreach from=$d.actionCounts key=code item=n}
					<li>{include file=$doajPaBadge group="action" code=$code} {$n|escape}</li>
				{foreachelse}
					<li>{translate key="plugins.generic.doajPreAssessment.common.none"}</li>
				{/foreach}
				</ul>
			</section>
		{/if}

		<section class="doajPa-notice doajPa-notice--warning" aria-labelledby="doajPaWarningHeading">
			<h2 id="doajPaWarningHeading">{translate key="plugins.generic.doajPreAssessment.gate.warningLabel"}</h2>
			<p>{$doajPaWarningText|escape}</p>
		</section>

		<h2>{translate key="plugins.generic.doajPreAssessment.overview.diagnostics"}</h2>
		<div class="doajPa-tableWrap">
		<table class="doajPa-table" id="doajPaDiagnostics">
			<caption class="doajPa-visuallyHidden">{translate key="plugins.generic.doajPreAssessment.overview.diagnostics"}</caption>
			<tbody>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.journal"}</th><td>{$doajPaJournalName|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.pluginVersion"}</th><td>{$doajPaPluginVersion|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.ojsVersion"}</th><td>{$doajPaOjsVersion|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.phpVersion"}</th><td>{$doajPaPhpVersion|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.acknowledgedBy"}</th><td>{$doajPaAcknowledgement.username|escape} (ID {$doajPaAcknowledgement.userId|escape}), {$doajPaAcknowledgement.acknowledgedAtUtc|escape} UTC</td></tr>
			</tbody>
		</table>
		</div>
		<h2>{translate key="plugins.generic.doajPreAssessment.overview.references"}</h2>
		{include file=$doajPaRefs}
	</div>
</div>
{/block}
