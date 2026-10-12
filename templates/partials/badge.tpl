{**
 * templates/partials/badge.tpl
 *
 * Status badge with its text label (never colour alone). Parameters: group, code.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}
{if $code === null || $code === ''}<span class="doajPa-badge doajPa-badge--none">{translate key="plugins.generic.doajPreAssessment.common.none"}</span>{else}<span class="doajPa-badge doajPa-badge--{$code|escape}">{if isset($doajPaLabels[$group][$code])}{$doajPaLabels[$group][$code]|escape}{else}{$code|escape}{/if}</span>{/if}
