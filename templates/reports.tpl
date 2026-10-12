{**
 * templates/reports.tpl
 *
 * Reports: export unavailable in this build; recorded export metadata only, no downloads.
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
		<p class="doajPa-notice doajPa-notice--info" role="status"><strong>{translate key="plugins.generic.doajPreAssessment.reports.unavailableLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.reports.unavailable"}</p>
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
				<tr><th scope="row">{$x.file_name|escape}</th><td>{$x.format|escape}</td><td><a href="{url page=$doajPaPage op="run" path=$x.run_id}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$x.run_number|escape}</a></td><td><code>{$x.rulebook_version|escape}</code></td><td>{$x.created_at_utc|escape} UTC</td></tr>
			{foreachelse}
				<tr><td colspan="5">{translate key="plugins.generic.doajPreAssessment.reports.none"}</td></tr>
			{/foreach}
			</tbody>
		</table>
		</div>
	</div>
</div>
{/block}
