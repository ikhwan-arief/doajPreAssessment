{**
 * templates/partials/badge.tpl
 *
 * Status badge with its text label (never colour alone). Parameters: group, code.
 *}
{if $code === null || $code === ''}<span class="doajPa-badge doajPa-badge--none">{translate key="plugins.generic.doajPreAssessment.common.none"}</span>{else}<span class="doajPa-badge doajPa-badge--{$code|escape}">{if isset($doajPaLabels[$group][$code])}{$doajPaLabels[$group][$code]|escape}{else}{$code|escape}{/if}</span>{/if}
