{**
 * templates/partials/link.tpl
 *
 * Untrusted URL from a journal website: a link only for http(s), otherwise plain text. Parameters: url, isLink.
 *}
{if $isLink}<a href="{$url|escape}" target="_blank" rel="noopener noreferrer nofollow" class="doajPa-url">{$url|escape}</a>{else}<span class="doajPa-url">{$url|escape}</span>{/if}
