# Copyright and licence notices

## 1. Plugin code

DOAJ Pre-Assessment, a generic plugin for Open Journal Systems 3.3

Copyright © 2026 Ikhwan Arief <ikhwan@unand.ac.id>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program (file `LICENSE`). If not,
see <https://www.gnu.org/licenses/>.

SPDX-License-Identifier: `GPL-3.0-or-later`. This covers every file of the package except the reference material in
section 2 and the fonts in section 3.

## 2. DOAJ-derived reference material (licensed separately)

The files `classes/rules/data/DoajPaCandidateDataset.inc.php` and `classes/rules/data/DoajPaApprovedDataset.inc.php`
are **not** covered by section 1. They contain excerpts transcribed from the public DOAJ documents listed below (the
approved dataset is a subset of the candidate dataset, unchanged). The plugin shows these excerpts in results, in the
DOAJ Form Guide and in corrective recommendations. "Approved" means approved by the product owner of this plugin for
its use; DOAJ has not reviewed or endorsed either file.

Each file is licensed under the **Creative Commons Attribution-ShareAlike 4.0 International licence (CC BY-SA 4.0)**,
<https://creativecommons.org/licenses/by-sa/4.0/>. SPDX-License-Identifier: `CC-BY-SA-4.0`. Each is a separate work,
distributed with the plugin code as an aggregate.

| Source | Author / licensor | Version (captured) | Licence of the source |
|---|---|---|---|
| Guide to applying, <https://doaj.org/apply/guide/> | Directory of Open Access Journals (DOAJ) | 2.9 (2026-10-10) | CC BY-SA 4.0, <https://creativecommons.org/licenses/by-sa/4.0/> |
| Licensing & copyright, <https://doaj.org/apply/copyright-and-licensing/> | Directory of Open Access Journals (DOAJ) | 2.1, January 2026 (2026-10-10) | CC BY-SA 4.0, <https://creativecommons.org/licenses/by-sa/4.0/> |
| Application form, PDF version (for reference only), <https://doaj.org/static/doaj/docs/version-3.2-October-2026-DOAJ-Application-Form.pdf> | Directory of Open Access Journals (DOAJ) | 3.2, September 2026 (2026-10-10) | CC BY-SA 4.0 (DOAJ site licence), <https://creativecommons.org/licenses/by-sa/4.0/> |
| Principles of Transparency and Best Practice in Scholarly Publishing, <https://doaj.org/apply/transparency/>, <https://doi.org/10.24318/cope.2019.1.12> | COPE, DOAJ, OASPA, WAME | 4.0, September 2022 (2026-10-10) | CC BY 4.0, <https://creativecommons.org/licenses/by/4.0/> |

**Changes made:**
- Sentences and list items were extracted as excerpts.
- Their wording is unchanged: every excerpt is a verbatim substring of the pinned capture (checked by the project's
  dataset tests).
- The plugin author added these elements, which are not DOAJ text:
  - criterion identifiers;
  - requirement levels (M, R, C, H);
  - evidence types;
  - assessment methods;
  - abstention conditions;
  - the FQ-01..FQ-20 mapping.

**Status:** the criteria derived from these excerpts are a *proposed* dataset (0.1.0-draft). Neither DOAJ nor the
other licensors have reviewed or approved it.

**No endorsement:** this plugin is independent. The Directory of Open Access Journals (DOAJ), COPE, OASPA and WAME
have not endorsed, sponsored or approved it. "DOAJ" names the public documents the plugin refers to. Only DOAJ makes
decisions on journal applications.

## 3. Third-party fonts

The PDF reports embed the **DejaVu Sans** fonts, so that text in Latin, Greek, Cyrillic and many other scripts can be
read. The files are DejaVu 2.37, unmodified, taken from the Debian package `fonts-dejavu-core` 2.37-8:
- `classes/reports/fonts/DejaVuSans.ttf`;
- `classes/reports/fonts/DejaVuSans-Bold.ttf`.

They are not covered by section 1.

**Licence:** Bitstream Vera Fonts licence (© 2003 Bitstream, Inc.; Bitstream Vera is a trademark of Bitstream, Inc.),
with the Arev Fonts licence for glyphs imported from Arev fonts (© 2006 Tavmjong Bah). DejaVu changes are in the
public domain. The full licence text is in `classes/reports/fonts/LICENSE-DejaVu.txt`.

The fonts are embedded unmodified in each PDF report. They are distributed as part of this larger software package
and are never sold by themselves.

No other third-party software is bundled. OJS, PHP and the optional external `pdftotext` program (Poppler) are not
part of the package; they are used under their own licences.
