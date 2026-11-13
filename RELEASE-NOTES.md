# Release Notes — DOAJ Pre-Assessment 1.3.3.0 (production release)

**Release type:** production release (maintenance). **Product-owner acceptance:** 2026-11-13, by Ikhwan Arief (Product Owner).
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2.

**Production release 1.3.3.0: approved criteria only; the candidate profile is disabled.** No change to the
assessment, the criteria or the data.

- The developer's e-mail in copyright headers is written as `ikhwan[at]unand.ac.id`.
- The public repository contains the plugin, the user and administrator guides, the unit tests and the build tools.
  Internal project records (stage reports, approval package, criteria sources, integration tests and test-stack
  scripts) are kept privately.

---

# Release Notes — DOAJ Pre-Assessment 1.3.2.0 (production release)

**Release type:** production release (correction). **Product-owner acceptance:** 2026-11-13, by Ikhwan Arief (Product Owner).
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2.

**Release 1.3.2.0: approved criteria only; the candidate profile is disabled.** Engine `doajpa-checks/5`:

- **Zero APC.** "No APC", "No publication fee", "No fee" and fees stated as zero, free or none ("APC: 0",
  "APC: USD 0.00", "Publication fee: Rp 0,-", "APC: Free", "APCs: none") are read as no charge, also when they stand
  alone on a short line. A zero fee of one kind ("Submission fee: 0") covers only that kind.
- **Short fee lines with an amount** ("APC: USD 500") are read as charges; before, they were skipped as headings.

---

# Release Notes — DOAJ Pre-Assessment 1.3.1.0 (production release)

**Release type:** production release (correction). **Product-owner acceptance:** 2026-11-13, by Ikhwan Arief (Product Owner).
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2.

**Release 1.3.1.0: approved criteria only; the candidate profile is disabled.** Corrections of Stage 01
AC-08 ("negations handled or abstained"); engine `doajpa-checks/4`:

- **"All rights reserved"** counts only where it is applied. A policy sentence that negates or merely mentions it
  ('The notice "All rights reserved" is not used', 'Instead of All rights reserved, ...') no longer gives a
  contradiction or a failure.
- **Fees:** fee words that are negated or incidental ("payment of a publication charge is not a condition",
  "withdraw without penalty") are no longer read as charges. In the Form Guide, an explicit no-charge statement now
  answers the publication-fee question, with a note when other fee wording appears.
- Unit tests use generic wording only (`tests/unit/Release131Test.php`).

---

# Release Notes — DOAJ Pre-Assessment 1.3.0.0 (production release)

**Release type:** production release. **Product-owner acceptance:** 2026-11-13, by Ikhwan Arief (Product Owner), of
reading published OJS content and of answering all 20 Form Guide questions.
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2.

**Release 1.3.0.0: approved criteria only; the candidate profile is disabled.** The 24 approved criteria
are unchanged.

## New in 1.3.0.0

- **Published content from the OJS database.** After the website crawl, the plugin also reads, directly from the OJS
  database: the journal settings shown on About, Editorial Team and Submissions (about, description, open access
  policy, publisher, ISSN, licence, mailing address, editorial team, checklist, author guidelines, copyright notice,
  privacy statement); custom navigation-menu pages; Static Pages; and the latest published issues (4) and articles
  (40) with dates, authors, affiliations, licence and copyright. Nothing unpublished is read (no submissions in
  progress, reviews, files, user accounts or e-mail addresses). This revises Stage 01 AC-05 for published content.
- These pages go through the same extraction, language check and rules as website pages and can support a pass.
  Every result based on them is labelled "(from the OJS database)", with the public address where OJS shows the
  content; "not found on the public website during the crawl" is added when the crawl did not reach that page.
- **All 20 DOAJ Form Guide questions answered** with the reviewed mapping (product-owner decision 2026-11-13).
  Questions whose criteria are not active are answered from website and database facts where possible, otherwise
  "Needs verification"; subject keywords are never chosen automatically.
- User-Agent `DOAJPreAssessment/1.3`. No schema change.

---

# Release Notes — DOAJ Pre-Assessment 1.2.1.0 (production release)

**Release type:** production release. **Product-owner acceptance:** 2026-11-13, by Ikhwan Arief (Product Owner).
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2.

**Release 1.2.1.0: approved criteria only; the candidate profile is disabled.** Changes from 1.2.0.0:

- **Delete any runs, in any order.** Assessment History has a checkbox per run and "Delete selected runs". When a
  later run refers to a deleted run as its previous run, an empty hidden placeholder keeps the later run's sealed
  record intact; the later run shows "Run N (deleted)". "Delete all assessment history" is unchanged.
- **Tidier tables.** Column headings never split a word: on the pages (CSS), in HTML reports (fixed layout) and in
  PDF/DOCX reports (column widths fitted to the longest heading and cell word). Long URLs in PDF cells break after
  "/", "-", "." and similar characters instead of in the middle of a word.

---

# Release Notes — DOAJ Pre-Assessment 1.2.0.0 (production release)

**Release type:** production release. **Product-owner acceptance:** 2026-11-13, by Ikhwan Arief (Product Owner), of
the user-friendliness changes listed below (decisions of 2026-11-13).
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2. OJS 3.4 and 3.5 are not supported.

**Release 1.2.0.0: approved criteria only; the candidate profile is disabled.** The approved criteria and
the question mapping are unchanged; stored (sealed) runs are not modified.

## New in 1.2.0.0

- **Plain language everywhere.** Pages and HTML/PDF/DOCX reports show criterion names instead of codes
  (`guide.disclosure-oa-policy` becomes "Navigation and mandatory disclosures: Open access policy"), DOAJ form
  questions instead of FQ-01..FQ-20, sentences instead of reason codes (`language_undetermined`,
  `pdf_extractor_unavailable`, ...), and no internal decision references (ADR-011, D-03-4). Finding and action
  numbers (F-1, A-1) are no longer shown. The stored records keep their codes.
- **Built-in PDF reader.** When `pdftotext` is not installed, PDF text is read by a reader written in plain PHP in the
  plugin (text-based PDFs; scanned PDFs still have no text). `pdftotext` is still used when present.
- **Delete assessment history.** Assessment History has a Delete button per run (newest first, because a later run
  builds on the earlier one) and "Delete all assessment history" (type DELETE to confirm). Results, evidence, findings
  seen only in the deleted runs, their corrective actions, report files and crawl files are removed. Nothing is ever
  deleted automatically; the audit log keeps a `run_deleted` record.
- **Automatic corrective actions.** A completed assessment creates one "Planned" corrective action for every open
  finding without an action, with the recommended correction, the page and the suggested priority. Actions you
  cancel are not recreated.
- **Audit log hidden** from the Settings page (still recorded).
- **Editorial Team page reading:** profile-link labels ([Scopus], [CV], ...), group headings, country lines and
  sidebar blocks no longer make a clean board list "unreadable".
- **Citation-format pages** (APA, IEEE, RIS, BibTeX exports of each article) are no longer crawled.
- Engine `doajpa-checks/3`, PDF extraction `doajpa-pdf/2`, User-Agent `DOAJPreAssessment/1.2`. No schema change.
- Independent security audit: the audit of 0.10.0.0 does not cover the changes of 1.1 and 1.2; they were reviewed and
  tested by the developer only.

---

# Release Notes — DOAJ Pre-Assessment 1.1.0.0 (production release)

**Release type:** production release. **Product-owner acceptance:** 2026-11-13, by Ikhwan Arief (Product Owner), of
the pre-production mode and the revision of ADR-005 R1.1 (private and loopback destinations).
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2. OJS 3.4 and 3.5 are not supported.

**Release 1.1.0.0: approved criteria only; the candidate profile is disabled.** The criteria, FQ mappings,
evaluation, authorization, persistence and export are unchanged from 1.0.0.0.

## New in 1.1.0.0: pre-production mode

- The server administrator can let the website reader read the journal's **own** website on a loopback or private
  address, to check a site before it is installed on its production server. It is enabled only in `config.inc.php`
  (`[doajPreAssessment] preproduction_private_targets = On`), never in the OJS interface.
- Optional `preproduction_connect_to = "host:port=ip:port"` connects the journal URL to another address and port
  (for example a Docker host port that does not exist inside the container); the URL and `Host` header are unchanged.
- Optional `preproduction_insecure_tls = On` accepts an unverifiable certificate for the journal origin only.
- Only the journal origin is covered; link-local/metadata, multicast, reserved and documentation addresses are never
  allowed; any malformed value disables the mode.
- Runs that use it are flagged, audited (`preproduction_private_destination_allowed`, once per destination and run)
  and show a PRE-PRODUCTION MODE notice on the run page and in every report.
- The crawler's User-Agent is `DOAJPreAssessment/1.1 (OJS plugin; journal self-assessment)`.
- No database schema change. Upgrade from 1.0.0.0 by uploading the new archive.
- Independent security audit: the audit of 0.10.0.0 (see below) does not cover the pre-production mode; it was
  reviewed and tested by the developer only.

---

# Release Notes — DOAJ Pre-Assessment 1.0.0.0 (production release)

**Release type:** production release. **Product-owner acceptance:** 2026-10-14, by Ikhwan Arief (Product Owner), of
the tested release candidate `v0.10.0.0-rc3`. Version 1.0.0.0 is that accepted build. It differs only in:
- release metadata and documentation: `version.xml`, `README.md`, `RELEASE-NOTES.md`, `CHANGELOG.md` and
  `MANIFEST.sha256`;
- the version in the crawler's User-Agent string.

The assessment, security and data logic are unchanged. Technical testing and an independent security audit are
complete.
**Target:** OJS 3.3.0-23, PHP 7.3 to 8.2. OJS 3.4 and 3.5 are not supported.
**Licence:** GPL-3.0-or-later (plugin code), Copyright © 2026 Ikhwan Arief (ikhwan[at]unand.ac.id). The DOAJ-derived
reference material is licensed separately (CC BY-SA 4.0), and the PDF report fonts (DejaVu Sans) are under the
Bitstream Vera/Arev licence. Attribution is in `NOTICE.md`.

**Production release 1.0.0.0: approved criteria only; the candidate profile is disabled.** Every assessment
evaluates the criteria approved by the product owner of this plugin. The plugin is independent of DOAJ: DOAJ has not
reviewed or endorsed it, the criteria are not DOAJ rules, and no result states, predicts or guarantees DOAJ
eligibility or acceptance.

> **ENGLISH-ONLY JOURNAL CONTENT.** The plugin evaluates only publicly accessible English-language journal websites,
> with deterministic rules and without Artificial Intelligence or automatic translation. A confirmation is required
> before first use in each journal and again before every assessment.

## What this release contains

- Everything in 0.9.0.0 (tag `v0.9.0.0-rc1`, archive SHA-256 `a69f1b91a1c25a1eb99328302e8c9c4a050ff399bc87b4ff2c1c9646e1920c7a`).
- **Approved criteria dataset 1.0.0**, written by `tools/apply-approval.py` from the signed approval record
  (internal record, not published): the criteria of the first activation wave and the approved
  FQ mapping rows, unchanged from the reviewed dataset 0.1.0-draft. They are registered as version 2 of the same
  criterion lineage, so stored candidate results are never mixed with approved ones.
- The default assessment profile evaluates the approved criteria; the Form Guide answers only the questions whose
  mapping was approved. Results and reports carry "Pre-assessment, not a DOAJ decision" and the independence notice.
- **Candidate profile disabled** (decision D-03-6). Candidate runs stored by earlier versions stay readable with their
  PENDING APPROVAL label; a candidate run left active by 0.9.0.0 can only be cancelled.
- Engine wording: decisions D-03-4, D-03-5 and D-03-9 are cited without "PENDING APPROVAL" (engine `doajpa-checks/2`);
  the checks themselves are unchanged.

## Since release candidate 0.10.0.0-rc2

- **Interface wording now matches this release:**
  - the plugin-list description: evidence-based pre-assessment, 24 approved criteria, not a complete DOAJ eligibility
    check, independent of DOAJ;
  - Settings: no configurable settings;
  - the DOAJ Form Guide notice before the first completed assessment;
  - the run page of runs made without approved criteria.
- **User-Agent:** the crawler's User-Agent is `DOAJPreAssessment/1.0 (OJS plugin; journal self-assessment)`.
- **Upgrade note:** plugin pages requested while OJS replaces the plugin files fail briefly (HTTP 404 or 500). With
  PHP OPcache, old code can be served for up to a few seconds longer. Upgrade or roll back when nobody is using the
  plugin, then reload PHP (`apache2ctl -k graceful`, or restart PHP-FPM). The database is not affected. See the
  administrator guide.

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

**Independent security audit:**
- **Audit:** executed on 2026-10-14 (read-only, Codex, model `gpt-5.5`) on the 0.10.0.0 code.
- **Coverage:** every later change up to 1.0.0.0 was reviewed the same way.
- **Results:** no Critical or High findings. One Low finding (only the root administrator, user ID 1, is admitted as
  site administrator) is the specified behaviour.
- **Reports:** kept as internal records (not published).

**Not included in this release:** deployment and publication. Both need the product owner's separate authorization.

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

Disabling, upgrading or deleting the plugin keeps all its data. There is no automatic purge. Upgrading from 0.9.0.0 or
0.10.0.0 needs no schema change; earlier runs keep their rule set label and stay sealed. See README "Installation (OJS
3.3.0-23)" for upgrade, rollback, uninstall and recovery.
