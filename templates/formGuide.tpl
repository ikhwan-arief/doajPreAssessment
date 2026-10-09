{**
 * templates/formGuide.tpl
 *
 * DOAJ Form Guide: FQ-01..FQ-20. Every entry is "Not yet assessed" until approved DOAJ criteria and an approved
 * question mapping exist (Stage 03). No question text or answer is invented here.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.formGuide"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		<p class="doajPa-notice doajPa-notice--info" role="status"><strong>{translate key="plugins.generic.doajPreAssessment.unavailable.label"}</strong> {translate key="plugins.generic.doajPreAssessment.unavailable.formGuide"}</p>
		{assign var=g value=$doajPaFormGuide}
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
					<th scope="row">{$e.questionKey|escape}</th>
					<td>{include file=$doajPaBadge group="formAnswer" code=$e.answerStatus}</td>
					<td>{if $e.answerText}{$e.answerText|escape}{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td>
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
