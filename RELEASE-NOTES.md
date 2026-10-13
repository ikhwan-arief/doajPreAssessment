# Release Notes — DOAJ Pre-Assessment 0.10.0.0 (operational release candidate 2)

**Release type:** operational release candidate. **Not a production release**: the independent security audit is
BLOCKED until it is executed. Never 1.0 before final acceptance.
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2. OJS 3.4 and 3.5 are not supported.
**Licence:** GPL-3.0-or-later (plugin code), Copyright © 2026 Ikhwan Arief <ikhwan@unand.ac.id>. The DOAJ-derived
reference material is licensed separately (CC BY-SA 4.0), and the PDF report fonts (DejaVu Sans) are under the
Bitstream Vera/Arev licence. Attribution is in `NOTICE.md`.

**Operational release candidate 2: approved criteria only; the candidate profile is disabled.** Every assessment
evaluates the criteria approved by the product owner of this plugin. The plugin is independent of DOAJ: DOAJ has not
reviewed or endorsed it, the criteria are not DOAJ rules, and no result states, predicts or guarantees DOAJ
eligibility or acceptance.

> **ENGLISH-ONLY JOURNAL CONTENT.** The plugin evaluates only publicly accessible English-language journal websites,
> with deterministic rules and without Artificial Intelligence or automatic translation. A confirmation is required
> before first use in each journal and again before every assessment.

## What this release candidate contains

- Everything in 0.9.0.0 (tag `v0.9.0.0-rc1`, archive SHA-256 `a69f1b91a1c25a1eb99328302e8c9c4a050ff399bc87b4ff2c1c9646e1920c7a`).
- **Approved criteria dataset 1.0.0**, written by `tools/apply-approval.py` from the signed approval record
  (`docs/stage05/normative-approval-package/signed/`): the criteria of the first activation wave and the approved
  FQ mapping rows, unchanged from the reviewed dataset 0.1.0-draft. They are registered as version 2 of the same
  criterion lineage, so stored candidate results are never mixed with approved ones.
- The default assessment profile evaluates the approved criteria; the Form Guide answers only the questions whose
  mapping was approved. Results and reports carry "Pre-assessment, not a DOAJ decision" and the independence notice.
- **Candidate profile disabled** (decision D-03-6). Candidate runs stored by earlier versions stay readable with their
  PENDING APPROVAL label; a candidate run left active by 0.9.0.0 can only be cancelled.
- Engine wording: decisions D-03-4, D-03-5 and D-03-9 are cited without "PENDING APPROVAL" (engine `doajpa-checks/2`);
  the checks themselves are unchanged.

## Not available in this release

| Feature | Status |
|---|---|
| Criteria not approved, approved with changes, or deferred | Not evaluated (a change needs a revised dataset, new acceptance cases and a new approval) |
| FQ questions whose mapping was not approved | "Not yet assessed" |
| ISSN Portal and DOI resolver lookups | Not performed (decision D-03-4); those criteria are always "unverifiable" |
| Unreliable-index classification | Not performed (decision D-03-5); indexing claims are listed as unverified |
| Candidate profile | Disabled (decision D-03-6) |
| Human confirmation of machine results | Not available (later stage) |
| Unattended (scheduled) assessments | Not available: steps run from the manager's browser |
| Independent security and code audit | **BLOCKED** (not executed); required before a production release |

## Known limitations

- Pages that list mostly names (editorial board, contact) often have too little prose for the language check; they
  stay "undetermined" (ADR-011). Only language-neutral facts may be read from them (ADR-013, labelled, under
  safeguards S1–S7). Statements on such pages are never read, so results that depend on them are "unverifiable".
- The PDF report embeds DejaVu Sans (about 1.5 MB per PDF). Characters that the font has no glyph for (for example
  CJK) are written as [U+XXXX] code points, and the report says so; the HTML and DOCX reports show them unchanged.
- Checks read only the pages crawled in the run, within the crawl limits. A sample cannot prove "all content".
- Matching is by curated English patterns; wording outside them is reported as unverifiable or for DOAJ review,
  never as a pass.

## Requirements and limits

- PHP `curl`, `dom`, `mbstring`. Optional: Poppler `pdftotext` (not bundled); without it, PDF text is not read and
  each PDF is listed as a coverage gap (`pdf_extractor_unavailable`).
- The reader contacts only public internet addresses. It refuses to run with an OJS HTTP proxy or with
  `disable_path_info = On`.

## Data

Disabling, upgrading or deleting the plugin keeps all its data. There is no automatic purge. Upgrading from 0.9.0.0
needs no schema change; earlier runs keep their rule set label and stay sealed. See README "Installation (OJS
3.3.0-23)" for upgrade, rollback, uninstall and recovery.
