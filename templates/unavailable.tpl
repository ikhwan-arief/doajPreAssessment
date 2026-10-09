{**
 * templates/unavailable.tpl
 *
 * DOAJ Form Guide / Before You Apply: not available until approved DOAJ criteria exist. No answers are generated.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.nav.`$doajPaUnavailable`"}</h1>
	{include file=$doajPaNav}
	<div class="app__contentPanel doajPa">
		<p class="doajPa-notice doajPa-notice--info" role="status"><strong>{translate key="plugins.generic.doajPreAssessment.unavailable.label"}</strong> {translate key="plugins.generic.doajPreAssessment.unavailable.`$doajPaUnavailable`"}</p>
		{include file=$doajPaRefs}
	</div>
</div>
{/block}
