{**
 * templates/evidence.tpl
 *
 * Evidence and pages read for one run, with source state and coverage gaps.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.evidence"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{if !$doajPaRun}
			<p class="doajPa-empty">{translate key="plugins.generic.doajPreAssessment.overview.noRuns.text"}</p>
		{else}
			{assign var=r value=$doajPaRun}
			<form class="doajPa-form" method="get" action="{url page=$doajPaPage op="evidence"}">
				<label for="doajPaRunSelect">{translate key="plugins.generic.doajPreAssessment.common.run"}</label>
				<select id="doajPaRunSelect" name="run">
					{foreach from=$doajPaRuns item=opt}<option value="{$opt.run_id|escape}"{if $opt.run_id == $r.run_id} selected{/if}>{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$opt.run_number|escape} ({if isset($doajPaLabels.run[$opt.status])}{$doajPaLabels.run[$opt.status]|escape}{else}{$opt.status|escape}{/if})</option>{/foreach}
				</select>
				<button type="submit" class="pkpButton">{translate key="plugins.generic.doajPreAssessment.common.show"}</button>
			</form>
			{include file=$doajPaNotResult rulebook=$r.rulebook_version}

			<h2 id="doajPaPages">{translate key="plugins.generic.doajPreAssessment.evidence.pages"}</h2>
			{assign var=gaps value=0}
			{foreach from=$r.resources item=res}{if $res.acquisition_status != 'retrieved'}{assign var=gaps value=$gaps+1}{/if}{/foreach}
			{if $gaps > 0}<p class="doajPa-notice doajPa-notice--warning" role="note"><strong>{translate key="plugins.generic.doajPreAssessment.evidence.gapsLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.evidence.gaps"} ({$gaps|escape})</p>{/if}
			{if $r.languageNotVerifiedCount > 0}<p class="doajPa-notice doajPa-notice--warning" role="note"><strong>{translate key="plugins.generic.doajPreAssessment.run.languageNotVerified"}:</strong> {$r.languageNotVerifiedCount|escape}. {translate key="plugins.generic.doajPreAssessment.run.languageNote"}</p>{/if}
			<div class="doajPa-tableWrap">
			<table class="doajPa-table" aria-labelledby="doajPaPages">
				<thead><tr>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.evidence.url"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.sourceState"}</th>
					<th scope="col">HTTP</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.evidence.mediaType"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.retrieved"}</th>
					<th scope="col">{translate key="plugins.generic.doajPreAssessment.evidence.skipReason"}</th>
				</tr></thead>
				<tbody>
				{foreach from=$r.resources item=res}
					<tr><th scope="row">{include file=$doajPaLink url=$res.url isLink=$res.urlIsLink}</th><td>{include file=$doajPaBadge group="acquisition" code=$res.acquisition_status}</td><td>{$res.http_status|escape}</td><td>{$res.media_type|escape}</td><td>{$res.retrieved_at_utc|escape} UTC</td><td>{$res.skip_reason|escape}</td></tr>
				{foreachelse}
					<tr><td colspan="6">{translate key="plugins.generic.doajPreAssessment.overview.noPages"}</td></tr>
				{/foreach}
				</tbody>
			</table>
			</div>

			<h2 id="doajPaExcerpts">{translate key="plugins.generic.doajPreAssessment.evidence.excerpts"}</h2>
			{foreach from=$r.evidence item=ev}
				<section class="doajPa-evidence" aria-label="{translate key="plugins.generic.doajPreAssessment.common.evidence"} {$ev.evidence_id|escape}">
					<p><strong>{translate key="plugins.generic.doajPreAssessment.common.evidence"} {$ev.evidence_id|escape}</strong> {include file=$doajPaLink url=$ev.source_url isLink=$ev.sourceUrlIsLink}{if $ev.source_title}, &ldquo;{$ev.source_title|escape}&rdquo;{/if}
					({$ev.source_type|doajpa_code|escape}, {translate key="plugins.generic.doajPreAssessment.evidence.location"}: {$ev.location|doajpa_code|escape}, {translate key="plugins.generic.doajPreAssessment.evidence.method"}: {$ev.detection_method|doajpa_code|escape}, {translate key="plugins.generic.doajPreAssessment.common.retrieved"} {$ev.retrieved_at_utc|escape} UTC)</p>
					<blockquote class="doajPa-quote">{$ev.quote|escape}</blockquote>
				</section>
			{foreachelse}
				<p>{translate key="plugins.generic.doajPreAssessment.evidence.none"}</p>
			{/foreach}
		{/if}
	</div>
</div>
{/block}
