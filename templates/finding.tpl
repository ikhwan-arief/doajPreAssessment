{**
 * templates/finding.tpl
 *
 * One finding: identity, per-run observation timeline with evidence, transitions, actions, DOAJ cross-check.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	{assign var=f value=$doajPaFinding}
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.common.finding"} F-{$f.finding_id|escape}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		<div class="doajPa-tableWrap">
		<table class="doajPa-table">
			<caption>{translate key="plugins.generic.doajPreAssessment.finding.identity"}</caption>
			<tbody>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.criterion"}</th><td><code>{$f.criterion_key|escape}</code> ({translate key="plugins.generic.doajPreAssessment.finding.lineage"} {$f.lineage_version|escape})</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.finding.subtype"}</th><td>{$f.subtype|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.target"}</th><td>{include file=$doajPaLink url=$f.target isLink=$f.targetIsLink} ({$f.target_type|escape})</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.findings.original"}</th><td>{include file=$doajPaBadge group="lifecycle" code=$f.original_status}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.findings.current"}</th><td>{include file=$doajPaBadge group="lifecycle" code=$f.current_status}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.finding.key"}</th><td><code class="doajPa-digest">{$f.finding_key|escape}</code></td></tr>
			</tbody>
		</table>
		</div>

		<h2 id="doajPaTimeline">{translate key="plugins.generic.doajPreAssessment.finding.timeline"}</h2>
		<p>{translate key="plugins.generic.doajPreAssessment.finding.timelineNote"}</p>
		<ol class="doajPa-timeline" aria-labelledby="doajPaTimeline">
		{foreach from=$f.timeline item=t}
			<li>
				<p><a href="{url page=$doajPaPage op="run" path=$t.run_id}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$t.run_number|escape}</a>, {$t.observed_at_utc|escape} UTC, {translate key="plugins.generic.doajPreAssessment.finding.criterionVersion"} {$t.criterion_version|escape}:
				{include file=$doajPaBadge group="observation" code=$t.observation} &rarr; {include file=$doajPaBadge group="lifecycle" code=$t.lifecycle_status}
				{if !$t.rulebookApproved}<span class="doajPa-badge doajPa-badge--failed">{translate key="plugins.generic.doajPreAssessment.notResult.short"}</span>{/if}</p>
				{if $t.source_url}
					<p>{translate key="plugins.generic.doajPreAssessment.common.evidence"}: {include file=$doajPaLink url=$t.source_url isLink=$t.sourceUrlIsLink}, {translate key="plugins.generic.doajPreAssessment.common.retrieved"} {$t.retrieved_at_utc|escape} UTC, {translate key="plugins.generic.doajPreAssessment.common.sourceState"}: {include file=$doajPaBadge group="acquisition" code=$t.acquisition_status}, {translate key="plugins.generic.doajPreAssessment.evidence.location"}: {$t.location|escape}</p>
					<blockquote class="doajPa-quote">{$t.quote|escape}</blockquote>
				{else}
					<p>{translate key="plugins.generic.doajPreAssessment.finding.noEvidence"}</p>
				{/if}
			</li>
		{/foreach}
		</ol>

		<h2 id="doajPaTransitions">{translate key="plugins.generic.doajPreAssessment.finding.transitions"}</h2>
		<div class="doajPa-tableWrap">
		<table class="doajPa-table" aria-labelledby="doajPaTransitions">
			<thead><tr>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.time"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.finding.from"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.finding.to"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.finding.reason"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.finding.actor"}</th>
			</tr></thead>
			<tbody>
			{foreach from=$f.transitions item=tr}
				<tr><td>{$tr.created_at_utc|escape} UTC</td><td>{include file=$doajPaBadge group="lifecycle" code=$tr.from_status}</td><td>{include file=$doajPaBadge group="lifecycle" code=$tr.to_status}</td><td><code>{$tr.reason_code|escape}</code></td><td>{if $tr.actor_username}{$tr.actor_username|escape}{else}{$tr.actor_type|escape}{/if}</td></tr>
			{/foreach}
			</tbody>
		</table>
		</div>

		<h2>{translate key="plugins.generic.doajPreAssessment.nav.actions"}</h2>
		<ul>
		{foreach from=$f.actions item=a}
			<li><a href="{url page=$doajPaPage op="actionEdit" path=$a.action_id}">A-{$a.action_id|escape}</a> {include file=$doajPaBadge group="action" code=$a.status} {$a.recommendation|truncate:120|escape}</li>
		{foreachelse}
			<li>{translate key="plugins.generic.doajPreAssessment.common.none"}</li>
		{/foreach}
		</ul>
		<p><a href="{url page=$doajPaPage op="actions" findingId=$f.finding_id}#doajPaNewAction">{translate key="plugins.generic.doajPreAssessment.actions.addForFinding"}</a></p>
		{include file=$doajPaRefs primary=$f.definitionReferenceUrl}
	</div>
</div>
{/block}
