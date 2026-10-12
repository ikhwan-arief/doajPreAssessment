# Release Notes — DOAJ Pre-Assessment 0.8.0.0

**Release type:** internal-testing build (not compliance-ready; never 1.0 before Stage 03–05 acceptance)
**Date:** 2026-10-12
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2. OJS 3.4 and 3.5 are not supported.
**Licence:** GPL-3.0-or-later (plugin code). The DOAJ-derived reference material is licensed separately under CC BY-SA 4.0, with attribution in `NOTICE.md`.
**Distribution:** internal-testing build, not a production release. The candidate criteria are PENDING APPROVAL, and the independent security audit is BLOCKED.

**Stage 04 internal-testing build; candidate DOAJ criteria are PENDING APPROVAL.** No DOAJ criterion is approved.
Every run and every report is labelled "Not a DOAJ assessment result". Nothing in it states, predicts or guarantees DOAJ
eligibility or acceptance.

> **ENGLISH-ONLY JOURNAL CONTENT.** The plugin evaluates only publicly accessible English-language journal websites,
> with deterministic rules and without Artificial Intelligence or automatic translation. A confirmation is required
> before first use in each journal and again before every assessment.

## What this release contains

- Everything in 0.7.0.0. That includes the candidate criteria (137, dataset 0.1.0-draft, PENDING APPROVAL), which
  run only in the explicit candidate profile.
- **Before You Apply:** the corrections of a completed run, grouped by the website page to correct. Each one carries:
  - the run's recommended correction and the DOAJ clause;
  - the finding;
  - the corrective actions recorded for it (owner, due date, priority, status);
  - a link that creates a prefilled corrective action.

  Results that could not be verified are listed separately; they are not deficiencies.
- **Reports in HTML, PDF and DOCX (A4)**, generated from the stored record of one completed run:
  - The three formats carry the same content: run, normative baseline, method, sources inspected and not inspected,
    results with their stored rationales, findings and history, corrections by page, corrective action register,
    DOAJ Form Guide, limitations, references and attribution, and the disclaimer.
  - Files are stored privately under the OJS files directory. Downloads are re-authorized and checked against the
    recorded SHA-256 on every request; a changed file is never served.
  - No third-party library is used.
- Stage 03 conditions:
  - all 597 per-criterion acceptance cases are executed;
  - the engine corrections they found are applied;
  - language-neutral facts (names, dates, ISSNs, counts) can be read from short pages of an English site, under
    explicit safeguards; this is PENDING APPROVAL (D-03-9) and every such result is labelled;
  - licence files and attribution are included.

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
| Production release | Not yet: the criteria are PENDING APPROVAL (D-03-1), and the independent security audit is BLOCKED (B-1) |

## Known limitations

- Pages that list mostly names (editorial board, contact) often have too little prose for the language check; they
  stay "undetermined" (ADR-011). Only language-neutral facts may be read from them (ADR-013, labelled, PENDING
  APPROVAL). Statements on such pages are never read, so results that depend on them are "unverifiable".
- The PDF report uses the standard PDF fonts (Windows-1252). Characters outside that set are written as [U+XXXX]
  code points, and the report says so; the HTML and DOCX reports show them unchanged.
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
