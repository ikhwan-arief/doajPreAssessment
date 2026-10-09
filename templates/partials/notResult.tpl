{**
 * templates/partials/notResult.tpl
 *
 * Shown with every run that does not use an approved DOAJ rule set (all runs in Stage 02).
 * Parameter: rulebook (string).
 *}
<div class="doajPa-notice doajPa-notice--warning" role="note">
	<p><strong>{translate key="plugins.generic.doajPreAssessment.notResult.label"}</strong> {translate key="plugins.generic.doajPreAssessment.notResult.text"}
	{translate key="plugins.generic.doajPreAssessment.notResult.ruleSet"} <code>{$rulebook|escape}</code></p>
</div>
