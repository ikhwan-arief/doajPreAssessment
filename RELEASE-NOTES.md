# Release Notes — DOAJ Pre-Assessment 0.6.0.0

**Release type:** development pre-release (not compliance-ready; never 1.0 before Stage 03–05 acceptance)
**Date:** 2026-10-10
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2. OJS 3.4 and 3.5 are not supported.

**Stage 02 framework; normative DOAJ assessment rules are not yet complete.** This build evaluates no DOAJ
criterion. Every run is labelled "Not a DOAJ assessment result." Nothing in it states, predicts or guarantees DOAJ
eligibility or acceptance.

> **ENGLISH-ONLY JOURNAL CONTENT.** The plugin evaluates only publicly accessible English-language journal websites,
> with deterministic rules and without Artificial Intelligence or automatic translation. A confirmation is required
> before first use in each journal and again before every assessment.

## What this release contains

- Access limited to the journal's default Journal Manager group and to user 1 as site administrator; "Login as"
  sessions are always denied.
- English-only first-use gate and per-run confirmation, both recorded with user and UTC time.
- Persistent, sealed assessment history: runs, evidence, findings with stable IDs and per-run observations,
  corrective actions, report metadata and an append-only audit log.
- Backend views: Overview, New Assessment, Criteria & Findings, Evidence, DOAJ Form Guide, Before You Apply,
  Corrective Actions, Assessment History (with comparison), Reports, Settings.
- Public website reader: SSRF-protected, bounded, robots.txt-aware; HTML and PDF text extraction with explicit
  statuses for every page that could not be read.
- Assessment framework: bounded, resumable steps; a rule interface; result checks (verbatim quotes, verified English
  evidence only); evaluation and sealing in one transaction.

## Not available in this release

| Feature | Status |
|---|---|
| DOAJ criteria (Guide, Transparency, Copyright and Licensing) | **NOT IMPLEMENTED** (Stage 03; no approved rule set) |
| Editorial endogeny (25% per issue / calendar year) | **NOT IMPLEMENTED** (Stage 03) |
| Article PDF publication-history dates | **NOT IMPLEMENTED** (Stage 03) |
| Licensing cross-page contradictions | **NOT IMPLEMENTED** (Stage 03) |
| Indexing claims and journal metrics | **NOT IMPLEMENTED** (Stage 03) |
| DOAJ Form Guide answers FQ-01 to FQ-20 | "Not yet assessed" for every question (Stage 03) |
| Before You Apply task list | Not available (needs approved criteria) |
| HTML, PDF and DOCX reports | Not available; export metadata only (Stage 04) |
| Human confirmation of machine results | Not available (later stage) |
| Unattended (scheduled) assessments | Not available: steps run from the manager's browser |
| Main-content language identification | Planned for Stage 03 (decision recorded); the current check is page-wide and advisory |
| Independent code review (Codex) of Stage 02 | **BLOCKED** (not executed) |
| Licence | Not decided: no licence is granted by this release |

## Requirements and limits

- PHP `curl`, `dom`, `mbstring`. Optional: Poppler `pdftotext` (not bundled); without it, PDF text is not read and
  each PDF is listed as a coverage gap (`pdf_extractor_unavailable`).
- The reader contacts only public internet addresses. It refuses to run with an OJS HTTP proxy or with
  `disable_path_info = On`.

## Data

Disabling, upgrading or deleting the plugin keeps all its data. There is no automatic purge. See README
"Installation (OJS 3.3.0-23)" for upgrade, rollback, uninstall and recovery.
