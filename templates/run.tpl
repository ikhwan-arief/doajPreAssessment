{**
 * templates/run.tpl
 *
 * One run: sealed snapshot, coverage, criterion results and this run's own finding observations.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	{assign var=r value=$doajPaRun}
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$r.run_number|escape}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{include file=$doajPaNotResult rulebook=$r.rulebook_version}
		{if $r.dev_exception_used}<p class="doajPa-notice doajPa-notice--warning" role="note"><strong>{translate key="plugins.generic.doajPreAssessment.run.devException"}</strong></p>{/if}
		{if $r.status != 'completed'}<p class="doajPa-notice doajPa-notice--info">{translate key="plugins.generic.doajPreAssessment.run.notCompleted"}</p>{/if}

		<div class="doajPa-tableWrap">
		<table class="doajPa-table">
			<caption>{translate key="plugins.generic.doajPreAssessment.run.summary"}</caption>
			<tbody>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.status"}</th><td>{include file=$doajPaBadge group="run" code=$r.status}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.ruleSet"}</th><td><code>{$r.rulebook_version|escape}</code></td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.extraction"}</th><td><code>{$r.extraction_version|escape}</code></td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.executor"}</th><td>{$r.executor_username|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.created"}</th><td>{$r.created_at_utc|escape} UTC</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.completed"}</th><td>{if $r.completed_at_utc}{$r.completed_at_utc|escape} UTC{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.acknowledgement"}</th><td>{if $r.acknowledgement}{$r.acknowledgement.username|escape}, {$r.acknowledgement.acknowledged_at_utc|escape} UTC{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.previous"}</th><td>{if $r.previousRunNumber}<a href="{url page=$doajPaPage op="compare" from=$r.previous_run_id to=$r.run_id}">{translate key="plugins.generic.doajPreAssessment.run.compareWith"} {$r.previousRunNumber|escape}</a>{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.snapshot"}</th><td>{if $r.status == 'completed'}{if $r.snapshotVerified}<span class="doajPa-badge doajPa-badge--verified">{translate key="plugins.generic.doajPreAssessment.common.snapshotVerified"}</span>{else}<span class="doajPa-badge doajPa-badge--failed">{translate key="plugins.generic.doajPreAssessment.common.snapshotFailed"}</span>{/if} <code class="doajPa-digest">{$r.snapshot_digest|escape}</code>{else}{translate key="plugins.generic.doajPreAssessment.run.notSealed"}{/if}</td></tr>
			</tbody>
		</table>
		</div>

		<h2 id="doajPaObservations">{translate key="plugins.generic.doajPreAssessment.run.observations"}</h2>
		<p>{translate key="plugins.generic.doajPreAssessment.run.observationsNote"}</p>
		<div class="doajPa-tableWrap">
		<table class="doajPa-table" aria-labelledby="doajPaObservations">
			<thead><tr>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.finding"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.criterion"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.target"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.observation"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.run.statusInRun"}</th>
			</tr></thead>
			<tbody>
			{foreach from=$r.observations item=o}
				<tr>
					<th scope="row"><a href="{url page=$doajPaPage op="finding" path=$o.finding_id}">F-{$o.finding_id|escape}</a></th>
					<td><code>{$o.criterion_key|escape}</code> v{$o.criterion_version|escape} ({$o.subtype|escape})</td>
					<td>{include file=$doajPaLink url=$o.target isLink=$o.targetIsLink}</td>
					<td>{include file=$doajPaBadge group="observation" code=$o.observation}</td>
					<td>{include file=$doajPaBadge group="lifecycle" code=$o.lifecycle_status}</td>
				</tr>
			{foreachelse}
				<tr><td colspan="5">{translate key="plugins.generic.doajPreAssessment.overview.noFindingsInRun"}</td></tr>
			{/foreach}
			</tbody>
		</table>
		</div>

		<h2 id="doajPaResults">{translate key="plugins.generic.doajPreAssessment.run.results"}</h2>
		{foreach from=$r.results item=res}
			<section class="doajPa-criterion" aria-label="{$res.criterion_key|escape}">
				<h3><code>{$res.criterion_key|escape}</code> v{$res.criterion_version|escape}: {$res.title|escape}</h3>
				{if !$res.datasetApproved}<p class="doajPa-notice doajPa-notice--warning">{translate key="plugins.generic.doajPreAssessment.criteria.notApproved"} <code>{$res.dataset_version|escape}</code></p>{/if}
				<p>{translate key="plugins.generic.doajPreAssessment.common.level"}: {if isset($doajPaLabels.level[$res.level])}{$doajPaLabels.level[$res.level]|escape}{else}{$res.level|escape}{/if}.
				{translate key="plugins.generic.doajPreAssessment.run.machineStatus"}: {include file=$doajPaBadge group="criterion" code=$res.machine_status}
				{translate key="plugins.generic.doajPreAssessment.run.humanStatus"}: {include file=$doajPaBadge group="criterion" code=$res.human_status}</p>
				<p>{translate key="plugins.generic.doajPreAssessment.run.rationale"}: {$res.rationale|escape}</p>
				<p>{translate key="plugins.generic.doajPreAssessment.run.limit"}: {$res.interpretation_limit|escape}</p>
				<p>{translate key="plugins.generic.doajPreAssessment.common.evidence"}: {if $res.source_url}{include file=$doajPaLink url=$res.source_url isLink=$res.sourceUrlIsLink}, {translate key="plugins.generic.doajPreAssessment.common.retrieved"} {$res.retrieved_at_utc|escape} UTC{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</p>
				{include file=$doajPaRefs primary=$res.doaj_reference_url}
			</section>
		{foreachelse}
			<p>{translate key="plugins.generic.doajPreAssessment.run.noResults"}</p>
		{/foreach}

		<p><a href="{url page=$doajPaPage op="evidence" run=$r.run_id}">{translate key="plugins.generic.doajPreAssessment.run.evidenceLink"}</a></p>
	</div>
</div>
{/block}
