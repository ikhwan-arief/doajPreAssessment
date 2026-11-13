{**
 * templates/compare.tpl
 *
 * Compare two runs through their own observations. Different criterion versions are flagged, never equated.
 *
 * Copyright (c) 2026 Ikhwan Arief (ikhwan[at]unand.ac.id)
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	{assign var=c value=$doajPaCompare}
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.compare.title"}: {translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$c.from.run_number|escape} / {$c.to.run_number|escape}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{include file=$doajPaNotResult rulebook=$c.to.rulebook_version}
		{if $c.differentRulebooks}<p class="doajPa-notice doajPa-notice--warning" role="note"><strong>{translate key="plugins.generic.doajPreAssessment.compare.differentRulebooks"}</strong></p>{/if}
		<div class="doajPa-tableWrap">
		<table class="doajPa-table">
			<caption>{translate key="plugins.generic.doajPreAssessment.compare.caption"}</caption>
			<thead><tr>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.finding"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.target"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$c.from.run_number|escape}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$c.to.run_number|escape}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.compare.change"}</th>
			</tr></thead>
			<tbody>
			{foreach from=$c.rows item=row}
				<tr>
					<th scope="row"><a href="{url page=$doajPaPage op="finding" path=$row.finding_id}">{$row.criterion_key|doajpa_criterion|escape}</a></th>
					<td>{include file=$doajPaLink url=$row.target isLink=$row.targetIsLink}</td>
					<td>{include file=$doajPaBadge group="lifecycle" code=$row.fromStatus}{if $row.fromVersion} v{$row.fromVersion|escape}{/if}</td>
					<td>{include file=$doajPaBadge group="lifecycle" code=$row.toStatus}{if $row.toVersion} v{$row.toVersion|escape}{/if}</td>
					<td>{include file=$doajPaBadge group="change" code=$row.change}{if $row.crossVersion} <strong class="doajPa-crossVersion">{translate key="plugins.generic.doajPreAssessment.compare.crossVersion"}</strong>{/if}</td>
				</tr>
			{foreachelse}
				<tr><td colspan="5">{translate key="plugins.generic.doajPreAssessment.compare.empty"}</td></tr>
			{/foreach}
			</tbody>
		</table>
		</div>
	</div>
</div>
{/block}
