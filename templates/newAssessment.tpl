{**
 * templates/newAssessment.tpl
 *
 * New Assessment: per-run English-only acknowledgement (required for every run) and the start form.
 * No approved DOAJ rules exist in this version: a run reads the public website and records evidence and
 * coverage only; every planned module is shown as NOT IMPLEMENTED.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.newAssessment"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		{include file=$doajPaNotResult rulebook=$doajPaRulebook}
		{if $doajPaError}
			<p class="doajPa-notice doajPa-notice--error" role="alert"><strong>{translate key="plugins.generic.doajPreAssessment.newAssessment.errorLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.newAssessment.error.`$doajPaError`"}</p>
		{/if}
		{if $doajPaActiveRun}
			<p class="doajPa-notice doajPa-notice--info" role="status">{translate key="plugins.generic.doajPreAssessment.newAssessment.active"}
			<a href="{url page=$doajPaPage op="run" path=$doajPaActiveRun.run_id}">{translate key="plugins.generic.doajPreAssessment.common.runNumber"} {$doajPaActiveRun.run_number|escape}</a>
			({include file=$doajPaBadge group="run" code=$doajPaActiveRun.status})</p>
		{/if}
		{if $doajPaTargetProblem}
			<p class="doajPa-notice doajPa-notice--warning" role="note"><strong>{translate key="plugins.generic.doajPreAssessment.newAssessment.targetLabel"}</strong> {$doajPaTargetProblem|escape}</p>
		{/if}

		<section aria-labelledby="doajPaScopeHeading">
			<h2 id="doajPaScopeHeading">{translate key="plugins.generic.doajPreAssessment.newAssessment.scopeHeading"}</h2>
			<p>{translate key="plugins.generic.doajPreAssessment.newAssessment.scope"}</p>
			<p><strong>{translate key="plugins.generic.doajPreAssessment.common.notImplemented"}</strong> {translate key="plugins.generic.doajPreAssessment.newAssessment.notImplemented"}</p>
			<ul>{foreach from=$doajPaFutureModules key=k item=title}<li><span class="doajPa-badge doajPa-badge--not_implemented">{translate key="plugins.generic.doajPreAssessment.common.notImplemented"}</span> {$title|escape}</li>{/foreach}</ul>
			<p>{translate key="plugins.generic.doajPreAssessment.newAssessment.pdf"}
			{if $doajPaPdfAvailable}<code>{$doajPaPdfTool|escape}</code>{else}<strong>{translate key="plugins.generic.doajPreAssessment.newAssessment.pdfUnavailable"}</strong>{/if}</p>
		</section>

		<section class="doajPa-notice doajPa-notice--warning" aria-labelledby="doajPaWarningHeading">
			<h2 id="doajPaWarningHeading">{translate key="plugins.generic.doajPreAssessment.gate.warningLabel"}</h2>
			<p><strong>{$doajPaWarningText|escape}</strong></p>
		</section>
		{assign var=doajPaBlocked value=($doajPaActiveRun || $doajPaTargetProblem)}
		<form class="doajPa-form" method="post" action="{url page=$doajPaPage op="runStart"}" aria-describedby="doajPaRunHelp">
			{csrf}
			<input type="hidden" name="warningSha256" value="{$doajPaWarningSha256|escape}" />
			<p id="doajPaRunHelp">{translate key="plugins.generic.doajPreAssessment.newAssessment.perRun"}</p>
			<fieldset>
				<legend>{translate key="plugins.generic.doajPreAssessment.newAssessment.profile"}</legend>
				<p><input type="radio" id="doajPaProfileApproved" name="profile" value="approved" checked{if $doajPaBlocked} disabled{/if} />
				<label for="doajPaProfileApproved">{translate key="plugins.generic.doajPreAssessment.newAssessment.profileApproved"}</label></p>
				<p><input type="radio" id="doajPaProfileCandidate" name="profile" value="candidate"{if $doajPaBlocked} disabled{/if} aria-describedby="doajPaCandidateHelp" />
				<label for="doajPaProfileCandidate">{translate key="plugins.generic.doajPreAssessment.newAssessment.profileCandidate"} (<code>{$doajPaCandidateRulebook|escape}</code>)</label></p>
				<p id="doajPaCandidateHelp">{translate key="plugins.generic.doajPreAssessment.candidate.text"}</p>
				<p><input type="checkbox" id="doajPaConfirmCandidate" name="confirmCandidate" value="1"{if $doajPaBlocked} disabled{/if} />
				<label for="doajPaConfirmCandidate">{translate key="plugins.generic.doajPreAssessment.newAssessment.confirmCandidate"}</label></p>
			</fieldset>
			<p>
				<input type="checkbox" id="doajPaRunAck" name="confirmEnglishOnly" value="1" required{if $doajPaBlocked} disabled{/if} />
				<label for="doajPaRunAck">{$doajPaCheckboxText|escape}</label>
			</p>
			<p><button type="submit" class="pkpButton"{if $doajPaBlocked} disabled{/if}>{translate key="plugins.generic.doajPreAssessment.newAssessment.start"}</button></p>
		</form>
	</div>
</div>
{/block}
