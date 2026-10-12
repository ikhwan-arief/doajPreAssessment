{**
 * templates/unavailable.tpl
 *
 * DOAJ Form Guide / Before You Apply: not available until approved DOAJ criteria exist. No answers are generated.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
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
