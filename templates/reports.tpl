{**
 * templates/reports.tpl
 *
 * Reports (Stage 04, AC-18): generate an HTML, PDF or DOCX report of a completed run (all from the same stored run);
 * list of generated reports with private, guarded downloads.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.reports"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{if $doajPaSaved}<p class="doajPa-notice doajPa-notice--success" role="status">{translate key="plugins.generic.doajPreAssessment.reports.saved"} R-{$doajPaSaved|escape}</p>{/if}
		{if $doajPaError}<p class="doajPa-notice doajPa-notice--error" role="alert"><strong>{translate key="plugins.generic.doajPreAssessment.gate.errorLabel"}</strong> {if $doajPaError == 'notReady'}{translate key="plugins.generic.doajPreAssessment.reports.notReady"}{else}{translate key="plugins.generic.doajPreAssessment.reports.invalid"}{/if}</p>{/if}
		<p class="doajPa-notice doajPa-notice--info">{translate key="plugins.generic.doajPreAssessment.reports.about"}</p>

		<h2 id="doajPaReportNew">{translate key="plugins.generic.doajPreAssessment.reports.generate"}</h2>
		{if $doajPaCompletedRuns}
			<form class="doajPa-form" method="post" action="{url page=$doajPaPage op="reportCreate"}" aria-labelledby="doajPaReportNew">
				{csrf}
				<div class="doajPa-field">
					<label for="doajPaReportRun">{translate key="plugins.generic.doajPreAssessment.common.run"}</label>
					<select id="doajPaReportRun" name="runId">
						{foreach from=$doajPaCompletedRuns item=r}<option value="{$r.run_id|escape}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$r.run_number|escape} ({$r.completed_at_utc|escape} UTC, {$r.rulebook_version|escape})</option>{/foreach}
					</select>
				</div>
				<fieldset class="doajPa-field">
					<legend>{translate key="plugins.generic.doajPreAssessment.reports.format"}</legend>
					{foreach from=$doajPaFormats item=f name=formats}
						<label class="doajPa-choice"><input type="radio" name="format" value="{$f|escape}"{if $smarty.foreach.formats.first} checked{/if}> {if $f == 'html'}{translate key="plugins.generic.doajPreAssessment.reports.formatHtml"}{elseif $f == 'pdf'}{translate key="plugins.generic.doajPreAssessment.reports.formatPdf"}{else}{translate key="plugins.generic.doajPreAssessment.reports.formatDocx"}{/if}</label>
					{/foreach}
				</fieldset>
				<p><button type="submit" class="pkpButton">{translate key="plugins.generic.doajPreAssessment.reports.create"}</button></p>
			</form>
		{else}
			<p>{translate key="plugins.generic.doajPreAssessment.reports.noCompletedRun"}</p>
		{/if}

		<div class="doajPa-tableWrap">
		<table class="doajPa-table">
			<caption>{translate key="plugins.generic.doajPreAssessment.reports.recorded"}</caption>
			<thead><tr>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.reports.file"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.reports.format"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.run"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.ruleSet"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.created"}</th>
			</tr></thead>
			<tbody>
			{foreach from=$doajPaExports item=x}
				<tr><th scope="row"><a href="{url page=$doajPaPage op="reportDownload" path=$x.export_id}">{$x.file_name|escape}</a><br><span class="doajPa-meta">SHA-256 {$x.file_sha256|truncate:16:"":true|escape}&hellip;, {$x.byte_length|escape} bytes</span></th><td>{$x.format|upper|escape}</td><td><a href="{url page=$doajPaPage op="run" path=$x.run_id}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$x.run_number|escape}</a></td><td><code>{$x.rulebook_version|escape}</code></td><td>{$x.created_at_utc|escape} UTC</td></tr>
			{foreachelse}
				<tr><td colspan="5">{translate key="plugins.generic.doajPreAssessment.reports.none"}</td></tr>
			{/foreach}
			</tbody>
		</table>
		</div>
	</div>
</div>
{/block}
