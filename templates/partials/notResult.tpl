{**
 * templates/partials/notResult.tpl
 *
 * Shown with every run that does not use an approved DOAJ rule set (all runs up to Stage 03).
 * Parameter: rulebook (string). Candidate runs (Stage 03) also say that the criteria are PENDING APPROVAL.
 *}
<div class="doajPa-notice doajPa-notice--warning" role="note">
	<p><strong>{translate key="plugins.generic.doajPreAssessment.notResult.label"}</strong> {translate key="plugins.generic.doajPreAssessment.notResult.text"}
	{translate key="plugins.generic.doajPreAssessment.notResult.ruleSet"} <code>{$rulebook|escape}</code></p>
	{if $rulebook|substr:0:10 == 'candidate-'}<p><strong>{translate key="plugins.generic.doajPreAssessment.candidate.label"}</strong> {translate key="plugins.generic.doajPreAssessment.candidate.text"}</p>{/if}
</div>
