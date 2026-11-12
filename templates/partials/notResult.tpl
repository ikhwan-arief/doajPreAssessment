{**
 * templates/partials/notResult.tpl
 *
 * Shown with every run. A run of the approved rule set says that it is a pre-assessment independent of DOAJ; any other
 * run is "not a DOAJ assessment result". Parameter: rulebook (string). Candidate runs (Stage 03) also say that the
 * criteria are PENDING APPROVAL.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{if $doajPaApprovedRulebook && $rulebook == $doajPaApprovedRulebook}
<div class="doajPa-notice doajPa-notice--info" role="note">
	<p><strong>{translate key="plugins.generic.doajPreAssessment.approved.label"}</strong> {translate key="plugins.generic.doajPreAssessment.approved.text"}</p>
	<p class="doajPa-attribution">{translate key="plugins.generic.doajPreAssessment.attribution.doaj"}</p>
</div>
{else}
<div class="doajPa-notice doajPa-notice--warning" role="note">
	<p><strong>{translate key="plugins.generic.doajPreAssessment.notResult.label"}</strong> {translate key="plugins.generic.doajPreAssessment.notResult.text"}
	{translate key="plugins.generic.doajPreAssessment.notResult.ruleSet"} {$rulebook|escape}</p>
	{if $rulebook|substr:0:10 == 'candidate-'}<p><strong>{translate key="plugins.generic.doajPreAssessment.candidate.label"}</strong> {translate key="plugins.generic.doajPreAssessment.candidate.text"}</p>
	<p class="doajPa-attribution">{translate key="plugins.generic.doajPreAssessment.attribution.doaj"}</p>{/if}
</div>
{/if}
