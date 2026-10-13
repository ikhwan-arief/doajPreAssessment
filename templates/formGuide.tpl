{**
 * templates/formGuide.tpl
 *
 * DOAJ Form Guide: FQ-01..FQ-20. "Not yet assessed" unless the latest completed run used the candidate profile
 * (Stage 03), whose answers come from verified-English evidence only and are labelled PENDING APPROVAL (D-03-2).
 * No question text or answer is invented here.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.formGuide"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{assign var=g value=$doajPaFormGuide}
		{if $g.run && $g.run.rulebook_version == $doajPaCandidateRulebook}
			{include file=$doajPaNotResult rulebook=$g.run.rulebook_version}
			<p class="doajPa-notice doajPa-notice--warning" role="note"><strong>{translate key="plugins.generic.doajPreAssessment.candidate.label"}</strong> {translate key="plugins.generic.doajPreAssessment.formGuide.candidate"}</p>
		{elseif $g.run && $g.run.rulebookApproved}
			{include file=$doajPaNotResult rulebook=$g.run.rulebook_version}
			<p class="doajPa-notice doajPa-notice--info" role="note">{translate key="plugins.generic.doajPreAssessment.formGuide.approved"}</p>
		{elseif $doajPaApprovedRulebook}
		<p class="doajPa-notice doajPa-notice--info" role="status"><strong>{translate key="plugins.generic.doajPreAssessment.formGuide.noApprovedRunLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.formGuide.noApprovedRun" questions=$doajPaApprovedQuestions|escape}</p>
		{else}
		<p class="doajPa-notice doajPa-notice--info" role="status"><strong>{translate key="plugins.generic.doajPreAssessment.unavailable.label"}</strong> {translate key="plugins.generic.doajPreAssessment.unavailable.formGuide"}</p>
		{/if}
		<p>{if $g.run}{translate key="plugins.generic.doajPreAssessment.formGuide.fromRun"} <a href="{url page=$doajPaPage op="run" path=$g.run.run_id}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$g.run.run_number|escape}</a>.{else}{translate key="plugins.generic.doajPreAssessment.formGuide.noRun"}{/if}</p>
		<div class="doajPa-tableWrap">
		<table class="doajPa-table">
			<caption>{translate key="plugins.generic.doajPreAssessment.formGuide.caption"}</caption>
			<thead><tr>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.formGuide.question"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.status"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.formGuide.answer"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.formGuide.uncertainty"}</th>
			</tr></thead>
			<tbody>
			{foreach from=$g.entries item=e}
				<tr>
					<th scope="row">{$e.questionKey|escape}{if $e.title}: {$e.title|escape}{/if}</th>
					<td>{include file=$doajPaBadge group="formAnswer" code=$e.answerStatus}</td>
					<td>{if $e.answerText}{$e.answerText|escape}{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}
					{if $e.source_url}<br />{translate key="plugins.generic.doajPreAssessment.common.evidence"}: {include file=$doajPaLink url=$e.source_url isLink=$e.sourceUrlIsLink}{/if}</td>
					<td>{if $e.uncertainty}{$e.uncertainty|escape}{else}{$doajPaFormGuideNote|escape}{/if}</td>
				</tr>
			{/foreach}
			</tbody>
		</table>
		</div>
		{include file=$doajPaRefs}
	</div>
</div>
{/block}
