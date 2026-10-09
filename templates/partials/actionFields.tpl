{**
 * templates/partials/actionFields.tpl
 *
 * Corrective action fields. Parameters: v (array of current values), editing (bool).
 *}
<div class="doajPa-field">
	<label for="doajPaRecommendation">{translate key="plugins.generic.doajPreAssessment.actions.recommendation"} <span aria-hidden="true">*</span></label>
	<textarea id="doajPaRecommendation" name="recommendation" rows="4" required aria-required="true">{$v.recommendation|escape}</textarea>
</div>
<div class="doajPa-field">
	<label for="doajPaTargetUrl">{translate key="plugins.generic.doajPreAssessment.actions.targetUrl"}</label>
	<input type="url" id="doajPaTargetUrl" name="targetUrl" value="{$v.targetUrl|escape}" maxlength="2048" />
</div>
<div class="doajPa-field">
	<label for="doajPaOwner">{translate key="plugins.generic.doajPreAssessment.actions.owner"}</label>
	<input type="text" id="doajPaOwner" name="ownerText" value="{$v.ownerText|escape}" maxlength="255" aria-describedby="doajPaOwnerHelp" />
	<p id="doajPaOwnerHelp" class="doajPa-help">{translate key="plugins.generic.doajPreAssessment.actions.ownerHelp"}</p>
</div>
<div class="doajPa-field">
	<label for="doajPaDue">{translate key="plugins.generic.doajPreAssessment.actions.dueDate"}</label>
	<input type="date" id="doajPaDue" name="dueDate" value="{$v.dueDate|escape}" />
</div>
<div class="doajPa-field">
	<label for="doajPaPriority">{translate key="plugins.generic.doajPreAssessment.actions.priority"}</label>
	<select id="doajPaPriority" name="priority">
		{foreach from=$doajPaLabels.priority key=code item=label}<option value="{$code|escape}"{if $v.priority == $code} selected{/if}>{$label|escape}</option>{/foreach}
	</select>
</div>
{if $editing}
	<div class="doajPa-field">
		<label for="doajPaStatus">{translate key="plugins.generic.doajPreAssessment.common.status"}</label>
		<select id="doajPaStatus" name="status" aria-describedby="doajPaStatusHelp">
			{foreach from=$doajPaLabels.action key=code item=label}<option value="{$code|escape}"{if $v.status == $code} selected{/if}>{$label|escape}</option>{/foreach}
		</select>
		<p id="doajPaStatusHelp" class="doajPa-help">{translate key="plugins.generic.doajPreAssessment.actions.statusHelp"}</p>
	</div>
	<div class="doajPa-field">
		<label for="doajPaCompletion">{translate key="plugins.generic.doajPreAssessment.actions.completionEvidence"}</label>
		<textarea id="doajPaCompletion" name="completionEvidence" rows="3">{$v.completionEvidence|escape}</textarea>
	</div>
{/if}
