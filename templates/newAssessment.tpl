{**
 * templates/newAssessment.tpl
 *
 * New Assessment: shows the per-run acknowledgement that every run will require. Running is not available yet.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.newAssessment"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		<p class="doajPa-notice doajPa-notice--info" role="status"><strong>{translate key="plugins.generic.doajPreAssessment.unavailable.label"}</strong> {translate key="plugins.generic.doajPreAssessment.unavailable.newAssessment"}</p>
		<section class="doajPa-notice doajPa-notice--warning" aria-labelledby="doajPaWarningHeading">
			<h2 id="doajPaWarningHeading">{translate key="plugins.generic.doajPreAssessment.gate.warningLabel"}</h2>
			<p><strong>{$doajPaWarningText|escape}</strong></p>
		</section>
		<form class="doajPa-form" aria-describedby="doajPaRunHelp">
			<p id="doajPaRunHelp">{translate key="plugins.generic.doajPreAssessment.newAssessment.perRun"}</p>
			<p>
				<input type="checkbox" id="doajPaRunAck" name="confirmEnglishOnly" value="1" disabled />
				<label for="doajPaRunAck">{$doajPaCheckboxText|escape}</label>
			</p>
			<p><button type="button" class="pkpButton" disabled>{translate key="plugins.generic.doajPreAssessment.newAssessment.start"}</button></p>
		</form>
	</div>
</div>
{/block}
