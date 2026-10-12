{**
 * templates/partials/notResult.tpl
 *
 * Shown with every run that does not use an approved DOAJ rule set (all runs up to Stage 03).
 * Parameter: rulebook (string). Candidate runs (Stage 03) also say that the criteria are PENDING APPROVAL.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
<div class="doajPa-notice doajPa-notice--warning" role="note">
	<p><strong>{translate key="plugins.generic.doajPreAssessment.notResult.label"}</strong> {translate key="plugins.generic.doajPreAssessment.notResult.text"}
	{translate key="plugins.generic.doajPreAssessment.notResult.ruleSet"} <code>{$rulebook|escape}</code></p>
	{if $rulebook|substr:0:10 == 'candidate-'}<p><strong>{translate key="plugins.generic.doajPreAssessment.candidate.label"}</strong> {translate key="plugins.generic.doajPreAssessment.candidate.text"}</p>
	<p class="doajPa-attribution">{translate key="plugins.generic.doajPreAssessment.attribution.doaj"}</p>{/if}
</div>
