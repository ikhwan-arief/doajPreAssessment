{**
 * templates/findings.tpl
 *
 * Criteria & Findings. Criteria from non-approved datasets are labelled; no criterion is shown as assessed.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.findings"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		<h2 id="doajPaCriteria">{translate key="plugins.generic.doajPreAssessment.criteria.title"}</h2>
		<p class="doajPa-notice doajPa-notice--info">{translate key="plugins.generic.doajPreAssessment.criteria.notAssessed"}</p>
		{if $doajPaDefinitions}
			<div class="doajPa-tableWrap">
			<table class="doajPa-table" aria-labelledby="doajPaCriteria">
				<thead><tr>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.criterion"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.level"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.criteria.dataset"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.crossCheck"}</th>
				</tr></thead>
				<tbody>
				{foreach from=$doajPaDefinitions item=def}
					<tr>
						<th scope="row"><code>{$def.criterion_key|escape}</code> v{$def.criterion_version|escape}: {$def.title|escape}</th>
						<td>{if isset($doajPaLabels.level[$def.level])}{$doajPaLabels.level[$def.level]|escape}{else}{$def.level|escape}{/if}</td>
						<td><code>{$def.dataset_version|escape}</code> {if $def.datasetApproved}<span class="doajPa-badge doajPa-badge--verified">{translate key="plugins.generic.doajPreAssessment.common.approved"}</span>{else}<span class="doajPa-badge doajPa-badge--failed">{translate key="plugins.generic.doajPreAssessment.common.notApproved"}</span>{/if}</td>
						<td><a href="{$def.doaj_reference_url|escape}" target="_blank" rel="noopener noreferrer">{$def.doaj_reference_url|escape}</a></td>
					</tr>
				{/foreach}
				</tbody>
			</table>
			</div>
		{/if}

		<h2 id="doajPaFindingsList">{translate key="plugins.generic.doajPreAssessment.findings.title"}</h2>
		{if $doajPaFindings}
			<p>{translate key="plugins.generic.doajPreAssessment.findings.note"}</p>
			<div class="doajPa-tableWrap">
			<table class="doajPa-table" aria-labelledby="doajPaFindingsList">
				<thead><tr>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.finding"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.criterion"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.target"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.findings.original"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.findings.lastObserved"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.findings.current"}</th>
				</tr></thead>
				<tbody>
				{foreach from=$doajPaFindings item=f}
					<tr>
						<th scope="row"><a href="{url page=$doajPaPage op="finding" path=$f.finding_id}">F-{$f.finding_id|escape}</a></th>
						<td><code>{$f.criterion_key|escape}</code> ({$f.subtype|escape})</td>
						<td>{include file=$doajPaLink url=$f.target isLink=$f.targetIsLink}</td>
						<td>{include file=$doajPaBadge group="lifecycle" code=$f.original_status}</td>
						<td>{include file=$doajPaBadge group="lifecycle" code=$f.lastObservedStatus}{if $f.lastObservedRunNumber} <a href="{url page=$doajPaPage op="run" path=$f.lastObservedRunId}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$f.lastObservedRunNumber|escape}</a>{/if}</td>
						<td>{include file=$doajPaBadge group="lifecycle" code=$f.current_status}</td>
					</tr>
				{/foreach}
				</tbody>
			</table>
			</div>
		{else}
			<p class="doajPa-empty">{translate key="plugins.generic.doajPreAssessment.findings.none"}</p>
		{/if}
		{include file=$doajPaRefs}
	</div>
</div>
{/block}
