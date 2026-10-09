{**
 * templates/overview.tpl
 *
 * Stage 02 foundation overview with controlled diagnostics. No assessment data exists yet.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.displayName"}</h1>

	<div class="app__contentPanel doajPa">
		<p><strong>{translate key="plugins.generic.doajPreAssessment.overview.statusLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.overview.status"}</p>
		<p>{translate key="plugins.generic.doajPreAssessment.overview.disclaimer"}</p>

		<section class="doajPa__warning" aria-labelledby="doajPaWarningHeading" style="border:2px solid #b45309;padding:1rem;margin:1rem 0;">
			<h2 id="doajPaWarningHeading">{translate key="plugins.generic.doajPreAssessment.gate.warningLabel"}</h2>
			<p>{$doajPaWarningText|escape}</p>
		</section>

		<h2>{translate key="plugins.generic.doajPreAssessment.overview.diagnostics"}</h2>
		<table class="pkpTable" id="doajPaDiagnostics">
			<tbody>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.journal"}</th><td>{$doajPaJournalName|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.pluginVersion"}</th><td>{$doajPaPluginVersion|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.ojsVersion"}</th><td>{$doajPaOjsVersion|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.phpVersion"}</th><td>{$doajPaPhpVersion|escape}</td></tr>
				<tr><th scope="row">{translate key="plugins.generic.doajPreAssessment.overview.acknowledgedBy"}</th><td>{$doajPaAcknowledgement.username|escape} (ID {$doajPaAcknowledgement.userId|escape}), {$doajPaAcknowledgement.acknowledgedAtUtc|escape} UTC</td></tr>
			</tbody>
		</table>

		<h2>{translate key="plugins.generic.doajPreAssessment.overview.references"}</h2>
		<ul>
			<li><a href="https://doaj.org/apply/guide/" rel="noopener noreferrer" target="_blank"><strong>{translate key="plugins.generic.doajPreAssessment.reference.guide"}</strong></a> {translate key="plugins.generic.doajPreAssessment.reference.primary"}</li>
			<li><a href="https://doaj.org/apply/transparency/" rel="noopener noreferrer" target="_blank">{translate key="plugins.generic.doajPreAssessment.reference.transparency"}</a></li>
			<li><a href="https://doaj.org/apply/copyright-and-licensing/" rel="noopener noreferrer" target="_blank">{translate key="plugins.generic.doajPreAssessment.reference.copyright"}</a></li>
		</ul>
	</div>
</div>
{/block}
