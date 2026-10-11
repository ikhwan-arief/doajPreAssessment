# Release Notes — DOAJ Pre-Assessment 0.7.0.0

**Release type:** internal-testing build (not compliance-ready; never 1.0 before Stage 03–05 acceptance)
**Date:** 2026-10-12
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2. OJS 3.4 and 3.5 are not supported.
**Distribution:** internal testing only. The licence is not decided; no external redistribution.

**Stage 03 internal-testing build; candidate DOAJ criteria are PENDING APPROVAL.** No DOAJ criterion is approved.
Every run is labelled "Not a DOAJ assessment result." Nothing in it states, predicts or guarantees DOAJ eligibility
or acceptance.

> **ENGLISH-ONLY JOURNAL CONTENT.** The plugin evaluates only publicly accessible English-language journal websites,
> with deterministic rules and without Artificial Intelligence or automatic translation. A confirmation is required
> before first use in each journal and again before every assessment.

## What this release contains

- Everything in 0.6.0.0: access guard, English-only gates, sealed assessment history with stable finding IDs,
  backend views, SSRF-protected website reader, resumable assessment framework.
- Main-content language identification (ADR-011): navigation, breadcrumbs, sidebars and footers do not decide a
  page's language; too little text stays undetermined and is never verified English.
- Deterministic evaluation engine with the candidate criteria dataset 0.1.0-draft (137 criteria from the DOAJ
  Guide v2.9, the Principles of Transparency v4.0 and DOAJ Licensing & copyright v2.1, captured 2026-10-10).
- Two assessment profiles on New Assessment:
  - **Approved DOAJ criteria only** (default): none are approved, so no criterion is evaluated;
  - **Candidate criteria, pending approval**: an explicit, per-run choice with an extra confirmation; results are
    labelled "Candidate criteria - PENDING APPROVAL".
- Candidate results quote the verified English text they rely on, with page addresses; fail, warning and
  contradiction results include a recommended correction citing the DOAJ clause; deficiencies become findings
  tracked across runs.
- DOAJ Form Guide FQ-01 to FQ-20 answered from verified English evidence only in candidate runs (supported, not
  found, needs verification); FQ-08 (keywords) is never answered automatically.

## Not available in this release

| Feature | Status |
|---|---|
| Approved DOAJ criteria | **PENDING APPROVAL** (decision D-03-1); only the candidate profile evaluates criteria |
| FQ-01..FQ-20 mapping | **PENDING APPROVAL** (decision D-03-2); answers are candidate answers |
| ISSN Portal and DOI resolver lookups | Not performed (decision D-03-4); those criteria are always "unverifiable" |
| Unreliable-index classification | Not performed (decision D-03-5); indexing claims are listed as unverified |
| Before You Apply task list | Not available (needs approved criteria) |
| HTML, PDF and DOCX reports | Not available; export metadata only (Stage 04) |
| Human confirmation of machine results | Not available (later stage) |
| Unattended (scheduled) assessments | Not available: steps run from the manager's browser |
| Independent code review (Codex) of Stage 02 and Stage 03 | **BLOCKED** (not executed) |
| Licence | Not decided: no licence is granted by this release; no external redistribution |

## Known limitations

- Pages that list mostly names (editorial board, contact) often have too little prose for the language check; they
  stay "undetermined" (ADR-011) and results that depend on them are "unverifiable".
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
