{**
 * templates/gate.tpl
 *
 * Mandatory English-only first-use gate (Stage 01 F1, PROD-05).
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div lang="en" dir="ltr" class="doajPa__page">
	<h1 class="app__pageHeading">{translate key="plugins.generic.doajPreAssessment.gate.title"}</h1>

	<div class="app__contentPanel doajPa">
		<section class="doajPa-notice doajPa-notice--warning" role="region" aria-labelledby="doajPaWarningHeading">
			<h2 id="doajPaWarningHeading">{translate key="plugins.generic.doajPreAssessment.gate.warningLabel"}</h2>
			<p><strong>{$doajPaWarningText|escape}</strong></p>
			<p>{translate key="plugins.generic.doajPreAssessment.gate.intro"}</p>
		</section>

		{if $doajPaShowError}
			<p class="doajPa-notice doajPa-notice--error" role="alert"><strong>{translate key="plugins.generic.doajPreAssessment.gate.errorLabel"}</strong> {translate key="plugins.generic.doajPreAssessment.gate.error"}</p>
		{/if}

		<form method="post" action="{url page="doajPreAssessment" op="acknowledge"}">
			{csrf}
			<input type="hidden" name="warningSha256" value="{$doajPaWarningSha256|escape}" />
			<p>
				<input type="checkbox" id="doajPaConfirmEnglishOnly" name="confirmEnglishOnly" value="1" required />
				<label for="doajPaConfirmEnglishOnly">{$doajPaCheckboxText|escape}</label>
			</p>
			<p><button type="submit" class="pkpButton">{translate key="plugins.generic.doajPreAssessment.gate.submit"}</button></p>
		</form>
	</div>
</div>
{/block}
