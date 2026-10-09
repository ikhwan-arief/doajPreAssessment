{**
 * templates/partials/nav.tpl
 *
 * Plugin navigation (Stage 01 F8 order) and development-build notice.
 *}
<nav class="doajPa-nav" aria-label="{translate key="plugins.generic.doajPreAssessment.nav.label"}">
	<ul class="doajPa-nav__list">
		{foreach from=$doajPaNavItems item=item}
			<li><a class="doajPa-nav__link" href="{$item.url|escape}"{if $item.current} aria-current="page"{/if}>{translate key=$item.labelKey}</a></li>
		{/foreach}
	</ul>
</nav>
<p class="doajPa-notice doajPa-notice--info"><strong>{translate key="plugins.generic.doajPreAssessment.devBuild.label"}</strong> {translate key="plugins.generic.doajPreAssessment.devBuild.text"} ({$doajPaPluginVersion|escape})</p>
