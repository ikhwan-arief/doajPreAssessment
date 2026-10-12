# Release Notes — DOAJ Pre-Assessment 0.9.0.0 (operational release candidate 1)

**Release type:** operational release candidate. **Not accepted for operational use**: no DOAJ criterion is approved,
so the default assessment profile evaluates no criterion, and the independent security audit is BLOCKED. Never 1.0
before final acceptance.
**Date:** 2026-10-12
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2. OJS 3.4 and 3.5 are not supported.
**Licence:** GPL-3.0-or-later (plugin code). The DOAJ-derived reference material is licensed separately (CC BY-SA 4.0),
and the PDF report fonts (DejaVu Sans) are under the Bitstream Vera/Arev licence. Attribution is in `NOTICE.md`.

**Operational release candidate 1; candidate DOAJ criteria are PENDING APPROVAL.** Every run and every report is
labelled "Not a DOAJ assessment result". Nothing in it states, predicts or guarantees DOAJ eligibility or acceptance.

> **ENGLISH-ONLY JOURNAL CONTENT.** The plugin evaluates only publicly accessible English-language journal websites,
> with deterministic rules and without Artificial Intelligence or automatic translation. A confirmation is required
> before first use in each journal and again before every assessment.

## What this release candidate contains

- Everything in 0.8.0.0. That includes the candidate criteria (137, PENDING APPROVAL), Before You Apply, the
  corrective action register, and the HTML, PDF and DOCX reports.
- **Complete operational workflow, tested end to end** on OJS 3.3.0-23 with PHP 7.3 and 8.2: install, activate,
  acknowledge, assess, inspect evidence, create corrective actions, export reports, correct, reassess, and compare
  findings. Interruption recovery (Resume) is included. A clean upgrade from 0.8.0.0 keeps all history.
- **Readable Unicode in PDF reports:** the DejaVu Sans fonts are embedded, so Latin with diacritics, Greek, Cyrillic,
  typographic quotes and many other scripts are shown and extractable. A character without a glyph (for example CJK)
  is written as [U+XXXX], and the report says so.
- **Findings resolve correctly on new evidence:** when a corrected page no longer shows an unapproved metric, or an
  issue's endogeny falls to 25% or less, the page- or issue-level finding is resolved (verified).
- **Report quota:** at most 30 generated reports are kept per run (Stage 01 F9).
- **Normative approval package** for the 137 criteria and the FQ mapping: `docs/stage05/normative-approval-package/`.

## Not available in this release

| Feature | Status |
|---|---|
| Approved DOAJ criteria | **PENDING APPROVAL** (decision D-03-1); only the candidate profile evaluates criteria |
| FQ-01..FQ-20 mapping | **PENDING APPROVAL** (decision D-03-2); answers are candidate answers |
| ISSN Portal and DOI resolver lookups | Not performed (decision D-03-4); those criteria are always "unverifiable" |
| Unreliable-index classification | Not performed (decision D-03-5); indexing claims are listed as unverified |
| Human confirmation of machine results | Not available (later stage) |
| Unattended (scheduled) assessments | Not available: steps run from the manager's browser |
| Independent code review (Codex) of Stage 02 and Stage 03 | **BLOCKED** (not executed) |
| Operational acceptance of this release candidate | **Not accepted**: the criteria are PENDING APPROVAL (D-03-1), so the default profile evaluates nothing, and the independent security audit is BLOCKED (B-1) |

## Known limitations

- Pages that list mostly names (editorial board, contact) often have too little prose for the language check; they
  stay "undetermined" (ADR-011). Only language-neutral facts may be read from them (ADR-013, labelled, PENDING
  APPROVAL). Statements on such pages are never read, so results that depend on them are "unverifiable".
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

Disabling, upgrading or deleting the plugin keeps all its data. There is no automatic purge. Upgrading from 0.6.0.0
needs no schema change. See README "Installation (OJS 3.3.0-23)" for upgrade, rollback, uninstall and recovery.
