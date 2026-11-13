{**
 * templates/partials/link.tpl
 *
 * Untrusted URL from a journal website: a link only for http(s), otherwise plain text. Parameters: url, isLink.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{if $url === 'journal'}{'journal'|doajpa_code|escape}{else}{if $isLink}<a href="{$url|escape}" target="_blank" rel="noopener noreferrer nofollow" class="doajPa-url">{$url|doajpa_url|escape}</a>{else}<span class="doajPa-url">{$url|doajpa_url|escape}</span>{/if}{/if}
