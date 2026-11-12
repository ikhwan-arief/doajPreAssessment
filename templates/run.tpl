{**
 * templates/run.tpl
 *
 * One run: sealed snapshot, coverage, criterion results and this run's own finding observations.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	{assign var=r value=$doajPaRun}
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$r.run_number|escape}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{include file=$doajPaNotResult rulebook=$r.rulebook_version}
		{if $r.dev_exception_used}<p class="doajPa-notice doajPa-notice--warning" role="note"><strong>{if $r.coverageData && $r.coverageData.preProductionMode}{translate key="plugins.generic.doajPreAssessment.run.preProduction"}{else}{translate key="plugins.generic.doajPreAssessment.run.devException"}{/if}</strong></p>{/if}
		{if $r.status != 'completed'}<p class="doajPa-notice doajPa-notice--info">{translate key="plugins.generic.doajPreAssessment.run.notCompleted"}</p>{/if}
		{if $doajPaError}<p class="doajPa-notice doajPa-notice--error" role="alert"><strong>{translate key="plugins.generic.doajPreAssessment.run.errorLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.run.error.`$doajPaError`"}</p>{/if}
		{if $doajPaStepNotice}<p class="doajPa-notice doajPa-notice--info" role="status">{translate key="plugins.generic.doajPreAssessment.run.step.`$doajPaStepNotice`"}</p>{/if}

		{if $r.status == 'draft' || $r.status == 'queued' || $r.status == 'running' || $r.status == 'interrupted' || $r.status == 'human_review'}
		<section class="doajPa-progress" aria-labelledby="doajPaProgressHeading">
			<h2 id="doajPaProgressHeading">{translate key="plugins.generic.doajPreAssessment.run.progress"}</h2>
			{if $doajPaProgress}
				<p role="status">{translate key="plugins.generic.doajPreAssessment.run.pagesRead"}: <strong>{$doajPaProgress.fetched|escape}</strong>.
				{translate key="plugins.generic.doajPreAssessment.run.queued"}: <strong>{$doajPaProgress.queued|escape}</strong>.
				{if $doajPaProgress.done}{translate key="plugins.generic.doajPreAssessment.run.crawlDone"}{else}{translate key="plugins.generic.doajPreAssessment.run.crawlRunning"}{/if}</p>
			{else}
				<p role="status">{translate key="plugins.generic.doajPreAssessment.run.crawlNotStarted"}</p>
			{/if}
			{if $r.status == 'interrupted'}
				<div class="doajPa-notice doajPa-notice--warning" role="note">
					<p><strong>{translate key="plugins.generic.doajPreAssessment.run.interruptedLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.run.interrupted"}</p>
					{if $r.interruptions}<p>{translate key="plugins.generic.doajPreAssessment.run.interruptedReason"}: {$r.interruptions[0].reason|escape} ({$r.interruptions[0].created_at_utc|escape} UTC)</p>{/if}
				</div>
				<form class="doajPa-form" method="post" action="{url page=$doajPaPage op="runResume" path=$r.run_id}">
					{csrf}
					<input type="hidden" name="lockVersion" value="{$r.lock_version|escape}" />
					<button type="submit" class="pkpButton">{translate key="plugins.generic.doajPreAssessment.run.resume"}</button>
				</form>
			{/if}
			{if $doajPaCanAdvance}
				<form id="doajPaStepForm" class="doajPa-form" method="post" action="{url page=$doajPaPage op="runStep" path=$r.run_id}">
					{csrf}
					<p>{translate key="plugins.generic.doajPreAssessment.run.stepHelp"}</p>
					<button type="submit" class="pkpButton">{translate key="plugins.generic.doajPreAssessment.run.continue"}</button>
				</form>
				<p><input type="checkbox" id="doajPaAutoContinue" checked /> <label for="doajPaAutoContinue">{translate key="plugins.generic.doajPreAssessment.run.autoContinue"}</label></p>
				{literal}<script>
				(function () {
					// The OJS 3.3 backend re-renders the page after this script runs, so elements are looked up when used,
					// never kept: a kept reference is detached and its submit() is cancelled by the browser.
					function off() { try { return window.sessionStorage.getItem('doajPaAutoContinue') === 'off'; } catch (e) { return false; } }
					function sync() { var box = document.getElementById('doajPaAutoContinue'); if (box && off()) box.checked = false; }
					document.addEventListener('change', function (e) {
						if (e.target && e.target.id === 'doajPaAutoContinue') { try { window.sessionStorage.setItem('doajPaAutoContinue', e.target.checked ? 'on' : 'off'); } catch (x) {} }
					});
					window.setTimeout(sync, 0); window.setTimeout(sync, 500);
					window.setTimeout(function () {
						var form = document.getElementById('doajPaStepForm'), box = document.getElementById('doajPaAutoContinue');
						if (form && box && box.checked && !off()) form.submit();
					}, 1500);
				})();
				</script>{/literal}
			{/if}
			{if $doajPaCanCancel}
				<form class="doajPa-form" method="post" action="{url page=$doajPaPage op="runCancel" path=$r.run_id}">
					{csrf}
					<input type="hidden" name="lockVersion" value="{$r.lock_version|escape}" />
					<button type="submit" class="pkpButton">{translate key="plugins.generic.doajPreAssessment.run.cancel"}</button>
				</form>
			{/if}
		</section>
		{/if}

		<div class="doajPa-tableWrap">
		<table class="doajPa-table">
			<caption>{translate key="plugins.generic.doajPreAssessment.run.summary"}</caption>
			<tbody>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.status"}</th><td>{include file=$doajPaBadge group="run" code=$r.status}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.ruleSet"}</th><td>{if $r.rulebookApproved}{translate key="plugins.generic.doajPreAssessment.history.approvedCriteria"}{else}{$r.rulebook_version|escape}{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.executor"}</th><td>{$r.executor_username|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.created"}</th><td>{$r.created_at_utc|escape} UTC</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.completed"}</th><td>{if $r.completed_at_utc}{$r.completed_at_utc|escape} UTC{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.acknowledgement"}</th><td>{if $r.acknowledgement}{$r.acknowledgement.username|escape}, {$r.acknowledgement.acknowledged_at_utc|escape} UTC{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.previous"}</th><td>{if $r.previousRunNumber}<a href="{url page=$doajPaPage op="compare" from=$r.previous_run_id to=$r.run_id}">{translate key="plugins.generic.doajPreAssessment.run.compareWith"} {$r.previousRunNumber|escape}</a>{elseif $r.previousRunDeleted}{translate key="plugins.generic.doajPreAssessment.run.previousDeleted" number=$r.previousRunDeleted}{else}{translate key="plugins.generic.doajPreAssessment.common.none"}{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.snapshot"}</th><td>{if $r.status == 'completed'}{if $r.snapshotVerified}<span class="doajPa-badge doajPa-badge--verified">{translate key="plugins.generic.doajPreAssessment.common.snapshotVerified"}</span>{else}<span class="doajPa-badge doajPa-badge--failed">{translate key="plugins.generic.doajPreAssessment.common.snapshotFailed"}</span>{/if}{else}{translate key="plugins.generic.doajPreAssessment.run.notSealed"}{/if}</td></tr>
			</tbody>
		</table>
		</div>

		{if $r.coverageData}
		{assign var=c value=$r.coverageData}
		<h2 id="doajPaCoverage">{translate key="plugins.generic.doajPreAssessment.run.coverage"}</h2>
		<div class="doajPa-tableWrap">
		<table class="doajPa-table" aria-labelledby="doajPaCoverage">
			<tbody>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.pagesRead"}</th><td>{$c.counts.fetched|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.gaps"}</th><td>{if $r.gapCount > 0}<span class="doajPa-badge doajPa-badge--interrupted">{$r.gapCount|escape}</span> {translate key="plugins.generic.doajPreAssessment.evidence.gaps"}{else}0{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.languageNotVerified"}</th><td>{$r.languageNotVerifiedCount|escape}. {translate key="plugins.generic.doajPreAssessment.run.languageNote"}{if $c.languageCounts} ({foreach from=$c.languageCounts key=lang item=n name=lc}{$lang|escape}: {$n|escape}{if !$smarty.foreach.lc.last}, {/if}{/foreach}){/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.limitsHit"}</th><td>{foreach from=$c.limitsHit item=l name=lh}{$l|doajpa_code|escape}{if !$smarty.foreach.lh.last}, {/if}{foreachelse}{translate key="plugins.generic.doajPreAssessment.common.none"}{/foreach}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.notFollowed"}</th><td>{foreach from=$c.skippedCounts key=k item=n name=sk}{$k|doajpa_reason|escape} {$n|escape}{if !$smarty.foreach.sk.last}, {/if}{foreachelse}{translate key="plugins.generic.doajPreAssessment.common.none"}{/foreach}</td></tr>
				<tr><th scope="row">robots.txt</th><td>{$c.robots|doajpa_code|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.pdfTool"}</th><td>{if $c.pdfTool == 'unavailable'}{translate key="plugins.generic.doajPreAssessment.run.pdfUnavailable"}{elseif $c.pdfTool == 'php-reader/1'}{translate key="plugins.generic.doajPreAssessment.run.pdfBuiltIn"}{else}{$c.pdfTool|escape}{/if}</td></tr>
			</tbody>
		</table>
		</div>
		{/if}

		{if $r.assessment}
		{assign var=a value=$r.assessment}
		<h2 id="doajPaAssessment">{translate key="plugins.generic.doajPreAssessment.run.assessment"}</h2>
		{if $a.criteria == 'not_implemented'}
			<p class="doajPa-notice doajPa-notice--warning" role="note"><strong>{translate key="plugins.generic.doajPreAssessment.common.notImplemented"}</strong> {translate key="plugins.generic.doajPreAssessment.run.criteriaNotImplemented"}</p>
		{elseif $a.criteria == 'candidate_rules_pending_approval'}
			<p class="doajPa-notice doajPa-notice--warning" role="note"><strong>{translate key="plugins.generic.doajPreAssessment.candidate.label"}</strong> {translate key="plugins.generic.doajPreAssessment.run.candidateRules"}
			{if $a.candidateDataset}{$a.candidateDataset.version|escape} ({$a.candidateDataset.approval|escape}).{/if}</p>
		{elseif $a.criteria == 'approved_rules'}
			<p class="doajPa-notice doajPa-notice--info" role="note"><strong>{translate key="plugins.generic.doajPreAssessment.approved.label"}</strong> {translate key="plugins.generic.doajPreAssessment.run.approvedRules"}
			{if $a.approvedDataset}{$a.approvedDataset.version|escape} ({$a.approvedDataset.approval|escape}).{/if}</p>
		{elseif $a.criteria == 'test_fixture_rules'}
			<p class="doajPa-notice doajPa-notice--warning" role="note"><strong>{translate key="plugins.generic.doajPreAssessment.run.testRulesLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.run.testRules"}</p>
		{/if}
		<div class="doajPa-tableWrap">
		<table class="doajPa-table" aria-labelledby="doajPaAssessment">
			<tbody>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.rulesEvaluated"}</th><td>{$a.rulesEvaluated|escape} ({translate key="plugins.generic.doajPreAssessment.run.rulesReused"}: {$a.rulesReused|escape}; {translate key="plugins.generic.doajPreAssessment.run.rulesDowngraded"}: {$a.rulesDowngraded|escape})</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.nav.formGuide"}</th><td>{$a.formGuide.questions|escape} {translate key="plugins.generic.doajPreAssessment.run.questions"}, {include file=$doajPaBadge group="formAnswer" code=$a.formGuide.answerStatus}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.common.evidence"}</th><td>{$a.evidence.items|escape} {translate key="plugins.generic.doajPreAssessment.run.items"};
					{translate key="plugins.generic.doajPreAssessment.run.verifiedEnglish"}: {$a.evidence.verifiedEnglish|escape};
					{translate key="plugins.generic.doajPreAssessment.run.languageNotVerified"}: {$a.evidence.languageNotVerified|escape};
					{translate key="plugins.generic.doajPreAssessment.run.provenanceFailures"}: {$a.evidence.provenanceFailures|escape};
					{translate key="plugins.generic.doajPreAssessment.run.manifest"}: {if $a.evidence.manifestVerified}{translate key="plugins.generic.doajPreAssessment.common.yes"}{else}<strong>{translate key="plugins.generic.doajPreAssessment.common.no"}</strong>{/if}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.run.diff"}</th><td>{if $a.diff.previousRunId}{foreach from=$a.diff.counts key=k item=n name=dc}{if isset($doajPaLabels.diff[$k])}{$doajPaLabels.diff[$k]|escape}{else}{$k|escape}{/if}: {$n|escape}{if !$smarty.foreach.dc.last}; {/if}{/foreach}{else}{translate key="plugins.generic.doajPreAssessment.run.noPrevious"}{/if}</td></tr>
			</tbody>
		</table>
		</div>
		{if $a.statusCounts}<p>{translate key="plugins.generic.doajPreAssessment.run.statusCounts"}: {foreach from=$a.statusCounts key=k item=n name=sc}{include file=$doajPaBadge group="criterion" code=$k} {$n|escape}{if !$smarty.foreach.sc.last}; {/if}{/foreach}</p>{/if}
		{if $a.candidateModules}
		<h3 id="doajPaCandidateModules">{translate key="plugins.generic.doajPreAssessment.run.candidateModulesHeading"}</h3>
		<ul aria-labelledby="doajPaCandidateModules">{foreach from=$a.candidateModules item=k}<li><span class="doajPa-badge doajPa-badge--candidate">{translate key="plugins.generic.doajPreAssessment.candidate.badge"}</span> {if isset($doajPaFutureModules[$k])}{$doajPaFutureModules[$k]|escape}{else}{$k|escape}{/if}</li>{/foreach}</ul>
		{/if}
		{if $a.notImplemented}
		<h3 id="doajPaNotImplemented">{translate key="plugins.generic.doajPreAssessment.run.notImplementedHeading"}</h3>
		<ul aria-labelledby="doajPaNotImplemented">{foreach from=$a.notImplemented item=k}<li><span class="doajPa-badge doajPa-badge--not_implemented">{translate key="plugins.generic.doajPreAssessment.common.notImplemented"}</span> {if isset($doajPaFutureModules[$k])}{$doajPaFutureModules[$k]|escape}{else}{$k|escape}{/if}</li>{/foreach}</ul>
		{/if}
		{/if}

		<h2 id="doajPaObservations">{translate key="plugins.generic.doajPreAssessment.run.observations"}</h2>
		<p>{translate key="plugins.generic.doajPreAssessment.run.observationsNote"}</p>
		<div class="doajPa-tableWrap">
		<table class="doajPa-table" aria-labelledby="doajPaObservations">
			<thead><tr>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.finding"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.target"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.common.observation"}</th>
				<th scope="col">{translate key="plugins.generic.doajPreAssessment.run.statusInRun"}</th>
			</tr></thead>
			<tbody>
			{foreach from=$r.observations item=o}
				<tr>
					<th scope="row"><a href="{url page=$doajPaPage op="finding" path=$o.finding_id}">{$o.criterion_key|doajpa_criterion|escape}</a><br><span class="doajPa-meta">{$o.subtype|doajpa_code|escape}</span></th>
					<td>{include file=$doajPaLink url=$o.target isLink=$o.targetIsLink}</td>
					<td>{include file=$doajPaBadge group="observation" code=$o.observation}</td>
					<td>{include file=$doajPaBadge group="lifecycle" code=$o.lifecycle_status}</td>
				</tr>
			{foreachelse}
				<tr><td colspan="4">{translate key="plugins.generic.doajPreAssessment.overview.noFindingsInRun"}</td></tr>
			{/foreach}
			</tbody>
		</table>
		</div>

		<h2 id="doajPaResults">{translate key="plugins.generic.doajPreAssessment.run.results"}</h2>
		{foreach from=$r.results item=res}
			<section class="doajPa-criterion" aria-label="{$res.title|escape}">
				<h3>{$res.title|escape}</h3>
				{if !$res.datasetApproved}<p class="doajPa-notice doajPa-notice--warning">{translate key="plugins.generic.doajPreAssessment.criteria.notApproved"} {$res.dataset_version|escape}</p>{/if}
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
