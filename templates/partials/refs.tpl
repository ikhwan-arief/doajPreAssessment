{**
 * templates/partials/refs.tpl
 *
 * "Cross-check with DOAJ" links. Parameter: primary (optional URL of the criterion's own DOAJ page).
 *}
<div class="doajPa-crosscheck">
	<span class="doajPa-crosscheck__label">{translate key="plugins.generic.doajPreAssessment.crossCheck"}</span>
	<ul>
		{foreach from=$doajPaReferences item=ref}
			<li><a href="{$ref|escape}" target="_blank" rel="noopener noreferrer">{if $ref == "https://doaj.org/apply/guide/"}{translate key="plugins.generic.doajPreAssessment.reference.guide"}{elseif $ref == "https://doaj.org/apply/transparency/"}{translate key="plugins.generic.doajPreAssessment.reference.transparency"}{else}{translate key="plugins.generic.doajPreAssessment.reference.copyright"}{/if}</a>{if isset($primary) && $primary == $ref} <strong>({translate key="plugins.generic.doajPreAssessment.reference.forThisCriterion"})</strong>{elseif $ref == "https://doaj.org/apply/guide/"} ({translate key="plugins.generic.doajPreAssessment.reference.primaryShort"}){/if}</li>
		{/foreach}
	</ul>
</div>
