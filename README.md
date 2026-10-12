# DOAJ Pre-Assessment for OJS 3.3

> **ENGLISH-ONLY JOURNAL CONTENT.** This plugin evaluates only publicly accessible English-language journal websites. It uses deterministic rules, not Artificial Intelligence or automatic translation. Do not rely on its findings for policies written in other languages. If essential non-English content is encountered, the affected check will be marked Unsupported Language / Needs Verification. This limitation applies to the plugin, not to DOAJ eligibility.

> **Internal-testing build (0.7.0.0): Stage 03 internal-testing build; candidate DOAJ criteria are PENDING APPROVAL.** No DOAJ criterion is approved. By default no criterion is evaluated; a Journal Manager may choose the candidate criteria for one run, and those results are labelled as candidate results. No result is a DOAJ assessment result. Internal testing only: no external redistribution (licence not decided). See `RELEASE-NOTES.md`.

## Project Overview

DOAJ Pre-Assessment is a native Open Journal Systems (OJS) 3.3 plugin designed to help journal managers evaluate and improve their journal's readiness before applying for indexing in the Directory of Open Access Journals (DOAJ).

The plugin performs evidence-based, deterministic assessments of publicly accessible journal website content and generates findings, corrective recommendations, improvement action plans, and assessment history.

**This is an independent pre-assessment tool. It is not an official DOAJ evaluation system and does not predict or guarantee acceptance into DOAJ.**

## Target Environment

- Platform: Open Journal Systems 3.3.x
- Primary target: OJS 3.3.0-23
- Plugin type: Generic Plugin
- Plugin directory: `doajPreAssessment`
- Interface language: English only
- Assessment technology: Deterministic, rule-based analysis
- AI integration: None
- Database: OJS database, using plugin-specific tables

## Core Requirements

### 1. Access Control

Only the following authenticated users may access the plugin:

- Root Administrator with User ID 1, subject to administrator-role verification.
- Journal Managers assigned to the current journal context.

"Journal Manager" means a member of the journal's default **Journal manager** user group. In OJS 3.3 the
default "Journal editor" and "Production editor" groups also carry the Manager role; they are **not**
granted access by this plugin, nor is any custom group.

Sessions opened with OJS "Login as" are always denied, whoever the original and impersonated users are.
To use the plugin, sign out of "Login as" first.

All other editorial roles, reviewers, authors, and public users are denied access.

Authorization must be enforced server-side for every protected operation.

### 2. English-Only Restriction

The plugin supports only journals whose relevant public website content and publication policies are written in English.

A prominent compatibility warning and explicit acknowledgment must appear during installation or activation and before starting each assessment.

> **ENGLISH-ONLY JOURNAL CONTENT.** This plugin evaluates only publicly accessible English-language journal websites. It uses deterministic rules, not Artificial Intelligence or automatic translation. Do not rely on its findings for policies written in other languages. If essential non-English content is encountered, the affected check will be marked Unsupported Language / Needs Verification. This limitation applies to the plugin, not to DOAJ eligibility.

OJS 3.3 has no installer or pre-activation hook for custom warnings. The warning therefore appears in the
plugin description (visible before enabling), as a notification right after enabling, and on a mandatory
first-use acknowledgement page that blocks the plugin for each journal until it is confirmed.

English-only is a limitation of this plugin, not a DOAJ application requirement.

### 3. Public Website Assessment

The plugin examines publicly accessible journal website information, including:

- Homepage and navigation
- Journal identity and publisher information
- Open Access policies
- Aims and Scope
- Editorial Board
- Peer Review Policy
- Publication Ethics
- Copyright and Licensing
- Author Guidelines and publication fees
- Custom and Static Pages
- Archives and published issues
- Article landing pages and publicly available PDFs
- Indexing and journal metric claims

The plugin must not inspect private submissions, reviewer assignments, editorial decisions, or internal peer-review workflows.

### 4. Deterministic Assessment

The assessment engine uses HTML parsing, PDF text extraction, structured metadata extraction, regular expressions, validated terminology patterns, calculations, and cross-page consistency checks.

It does not use AI, large language models, machine learning, or automatic translation.

Missing, ambiguous, contradictory, or inaccessible evidence must be recorded explicitly. The engine must not invent evidence or claim compliance without sufficient verification.

### 5. Assessment History

Every assessment run is stored persistently.

Each finding has a stable identifier so that its status can be tracked across consecutive assessments.

Supported finding lifecycle states include Open, Resolved, Reopened, and Needs Verification.

Completed assessment snapshots must not be overwritten by subsequent assessments.

### 6. Corrective Actions

Every identified gap should produce an evidence-based corrective recommendation.

Journal Managers coordinate corrective actions, assign responsibilities, track implementation, and re-assess the public website to confirm improvements.

### 7. Reporting

The planned outputs include:

- Pre-Assessment Report
- DOAJ Application Form Guide
- Before You Apply Checklist
- Evidence Register
- Corrective Action Plan
- Assessment History and Comparison
- HTML, PDF, and DOCX reports

## Official DOAJ References

Assessment findings must provide relevant cross-check links to:

- [DOAJ Guide to Applying](https://doaj.org/apply/guide/)
- [Principles of Transparency and Best Practice](https://doaj.org/apply/transparency/)
- [DOAJ Licensing and Copyright](https://doaj.org/apply/copyright-and-licensing/)

Criteria must be version-controlled and must not change automatically when an external website changes.

## Project Documentation

The following documents define the project requirements and implementation process:

1. `docs/stage01/DOAJ_PreAssessment_OJS33_Stage01_FINAL_v2.0.0.md`
2. `docs/stage02/DOAJ_PreAssessment_OJS33_Stage02_ClaudeCode_Codex_Prompts_v1.0.0.md`

Stage 01 is the authoritative requirements baseline.

Stage 02 defines the development workflow and technical implementation tasks.

## Development Workflow

The implementation is divided into nine substages:

- 02.01 Compatibility and Source Reconnaissance
- 02.02 Plugin Bootstrap and Lifecycle
- 02.03 Authentication and Authorization
- 02.04 Database and Immutable History
- 02.05 Backend Dashboard and UX
- 02.06 Public Website Crawler and Extractor
- 02.07 Assessment Framework
- 02.08 Installation Package
- 02.09 Testing and Validation

Each substage must be reviewed and tested before proceeding to the next.

## Development Tools

- Claude Code: Primary implementation
- Codex: Independent code review and validation
- Git: Version control
- VS Code: Development environment

The OJS 3.3.0-23 source code is the technical reference for compatibility.

## Testing Policy

A test may be reported as PASS only when it has actually been executed successfully.

Use the following status labels:

- PASS
- FAIL
- NOT RUN
- BLOCKED

Record test commands, evidence, limitations, and unresolved findings.

Do not claim installation compatibility until it has been verified on a running OJS 3.3.0-23 environment.

## Installation (OJS 3.3.0-23)

Version `0.7.0.0` is an internal-testing build. It contains the access guard, the English-only gates, the database
for assessment history, the backend views, the public website crawler, the assessment framework and the Stage 03
deterministic evaluation engine with the **candidate** criteria dataset 0.1.0-draft (137 criteria, PENDING APPROVAL).
A Journal Manager can start an assessment (with a fresh English-only confirmation for every run); the plugin reads
the journal's public website in short steps and seals the run.

- **Default ("Approved DOAJ criteria only"):** no DOAJ criteria are approved, so no criterion is evaluated, the DOAJ
  Form Guide questions FQ-01 to FQ-20 are "Not yet assessed", and the run records evidence and coverage only.
- **Candidate criteria (internal testing):** chosen per run, with an extra confirmation. Every criterion of the
  proposed dataset is evaluated deterministically from the public pages read in that run, and FQ-01 to FQ-20 are
  answered only from verified English evidence. Results and answers are labelled "Candidate criteria - PENDING
  APPROVAL" and "Not a DOAJ assessment result". They are not approved DOAJ rules and not a compliance claim.

Supported: OJS 3.3.0-23 on PHP 7.3 to 8.2 (tested on PHP 7.3.33 and 8.2.34 in disposable stacks). OJS 3.4 and 3.5
are **not** supported and were not tested.

### Package

`doajPreAssessment-0.7.0.0.tar.gz` contains one directory, `doajPreAssessment/`, with the plugin code, templates,
stylesheet, English locale, this README, the change log, the release notes and `MANIFEST.sha256` (the SHA-256 of every
other file). It contains no test code, no development tools, no third-party libraries and no executables. Check the
archive against the published SHA-256 before installing (`shasum -a 256 doajPreAssessment-0.7.0.0.tar.gz`).
Maintainers build it with `tools/build-package.sh` (reproducible: the same commit always gives the same archive).

### Install and activate

1. Back up the OJS database and files (always, before installing or upgrading any plugin).
2. As the site administrator: Settings → Website → Plugins → **Upload A New Plugin**, and choose the archive.
   OJS copies the plugin to `plugins/generic/doajPreAssessment/` and creates its database tables.
3. Enable "DOAJ Pre-Assessment" in each journal that should use it (enabling is per journal). OJS shows the
   English-only warning right after enabling.
4. Open the plugin from the backend menu entry "DOAJ Pre-Assessment" (shown to Journal Managers of that journal and
   to user 1 as site administrator), from the plugin's "Open DOAJ Pre-Assessment" action, or at
   `/index.php/{journalPath}/doajPreAssessment`. The first visit in each journal shows the English-only statement,
   which must be confirmed before anything else is available. Every assessment asks for it again.

Manual installation (copying `doajPreAssessment/` into `plugins/generic/`) also works; then run
`php tools/upgrade.php upgrade` once on the server to create the tables. Until then the plugin answers HTTP 503 with
this instruction.

### Deactivate

Disable the plugin in the journal's plugin list. Its pages and menu entry disappear; all assessment history, findings,
corrective actions and the journal's English-only confirmation are kept and come back when it is enabled again.

### Upgrade

1. Back up the OJS database and the `plugins/generic/doajPreAssessment/` directory.
2. Plugin list → DOAJ Pre-Assessment → **Upgrade**, and choose the newer archive. OJS replaces the files and runs the
   plugin's database migration, which only adds what is missing and never removes data.
3. OJS refuses an archive older than the installed version ("installed version is newer"). There is no downgrade
   through the plugin list; use Rollback.

Completed assessment runs stay sealed across upgrades; each run page shows whether its snapshot still verifies.

### Rollback

Restore both backups taken before the upgrade: the database (which also restores OJS's record of the installed plugin
version) and the plugin directory. Anything recorded after the backup, such as runs started after the upgrade, is
lost by the restore. Restoring only the files of an older version over a newer database is not supported. After a database restore, OJS may give new runs the IDs of discarded ones; the plugin then moves the discarded runs'
private crawl files (under `files_dir/doajPreAssessment/`) aside instead of reusing them, and refuses to continue a run
whose private files are newer than its stored records (cancel such a run and start a new one).

### Uninstall and reinstall

Plugin list → DOAJ Pre-Assessment → **Delete** removes the plugin files only. **All plugin data is kept**: the 13
`doaj_` tables (runs, evidence, findings, history, corrective actions, report metadata, audit events) and the plugin
settings. The plugin has no data purge. Installing the same or a newer archive again reattaches the kept history, and
earlier runs still verify. Removing the data permanently is a database administrator's decision outside the plugin
(after a backup); the plugin offers no function for it.

### Recovery after a failure

- **Upload or install failed:** OJS removes the copied files when the installation script fails. Correct the cause
  (for example file permissions) and upload again; the migration is idempotent.
- **Upgrade failed after OJS removed the old files:** upload the archive again with "Upload A New Plugin" (the kept
  tables are reused) or restore the backup.
- **Plugin answers HTTP 503:** the tables are missing or incomplete; run `php tools/upgrade.php upgrade`.
- **An assessment step failed:** the run is "Interrupted" with the reason; resume or cancel it (see below).

### Running an assessment

New Assessment → choose the criteria to apply (approved only, or the candidate criteria with their extra
confirmation) → confirm the English-only statement → Start assessment. The run page then continues the run in
steps of about ten seconds of reading (each step is a separate, CSRF-protected request; the page submits the next
step automatically while "Continue automatically" is ticked, or use "Continue assessment"). When reading is
complete, the results, coverage and the 20 form-guide entries are stored and the run is sealed in one database
transaction. Only one run per journal can be active.

- If a step fails (for example the private files directory is not writable), the run becomes **Interrupted**
  with the reason shown; **Resume assessment** continues it. Pages already stored are not read again; a page
  that was stored but not fully processed is listed as a coverage gap (`interrupted_not_reprocessed`).
- If the browser is closed, the run stays **Running**; open it from Assessment History and continue.
- **Cancel assessment** ends an active run; its records stay, but it is never sealed.
- Pages that could not be read, limits that were reached, links that were not followed and pages whose
  language was not verified as English are listed under "Coverage and gaps" of the run. A page whose language
  the advisory check could not establish is never treated as verified English.
- The language of a page is identified from its main content region (ADR-011), not from menus, sidebars or the
  footer. Pages with too little text (for example a list of names) stay "undetermined"; a candidate result that
  depends on such a page is "unverifiable", never a failure. The English-only confirmation never makes a page
  verified English.

### Candidate results (internal testing)

Each candidate result shows the machine status (pass, fail, warning, contradiction, unverifiable, for DOAJ review,
not applicable), the rationale with the verbatim quotes and page addresses it relies on, the interpretation limit
and, for fail, warning and contradiction, a recommended correction citing the DOAJ clause. Deficiencies become
findings with stable IDs that are tracked across runs. Recommendations (R) never fail, human-judgement criteria
are always "for DOAJ review", criteria needing an external registry (ISSN Portal, DOI resolution) are always
"unverifiable" (no external lookup is made), and no index is ever called unreliable.

### Server requirements

- PHP `curl`, `dom` and `mbstring`. Each step may take up to about 40 seconds on a slow website; the plugin asks
  PHP for up to 120 seconds per step (`set_time_limit`), so a stricter limit set by the host may interrupt steps.
- The crawler contacts only public internet addresses, so a journal hosted on a private or intranet address
  cannot be assessed (its pages are recorded as inaccessible). It refuses to run when OJS is configured to use an
  HTTP proxy, and when `disable_path_info` is On.
- **Optional: Poppler `pdftotext`** (Debian/Ubuntu package `poppler-utils`), found at `/usr/bin/pdftotext` or
  `/usr/local/bin/pdftotext`. It is a separate program installed by the server administrator; it is **not**
  included in the plugin. The New Assessment page shows whether it was found. Behaviour:
  - not installed: PDF files are still downloaded and recorded, but their text is not read; each PDF is a coverage
    gap with status "Parse failed" and reason `unavailable: pdf_extractor_unavailable`;
  - encrypted, corrupt, scanned (no text), timed out (30 s) or not really a PDF: status "Parse failed" with the
    reason beginning with `encrypted`, `corrupt`, `no_text`, `timeout` or `not_a_pdf`; the PDF text is never guessed.
  - Tested with pdftotext 25.03.0 (PHP 8.2 stack) and 20.09.0 (PHP 7.3 stack).

Disabling, upgrading or deleting the plugin keeps all plugin data. The plugin has no automatic data purge.

## Project Status

- Stage 01: Final design baseline v2.0.0
- Stage 02.01: Compatibility reconnaissance complete (conditionally accepted)
- Stage 02.02: Plugin bootstrap and lifecycle implemented; see `docs/stage02/02.02-report.md`
- Stage 02.03: Authentication and authorization tested; see `docs/stage02/02.03-report.md`
- Stage 02.04: Database and immutable assessment history; see `docs/stage02/02.04-report.md`
- Stage 02.05: Backend dashboard and views; see `docs/stage02/02.05-report.md`
- Stage 02.06: Public website crawler and extraction; see `docs/stage02/02.06-report.md`
- Stage 02.07: Assessment framework and orchestration; see `docs/stage02/02.07-report.md`
- Stage 02.08: Installation package; see `docs/stage02/02.08-report.md`
- Stage 03: deterministic evaluation engine and candidate criteria dataset 0.1.0-draft (PENDING APPROVAL); see
  `docs/stage03/03-acceptance-report.md`
- DOAJ assessment rules: none approved (candidate criteria for internal testing only)
- Production readiness: Not established

## License and Copyright

Copyright © 2026 Ikhwan Arief <ikhwan@unand.ac.id>

The plugin code is free software, licensed under the GNU General Public License, version 3 or (at your option) any
later version (GPL-3.0-or-later). The full licence text is in `LICENSE`.

The DOAJ-derived reference material (`classes/rules/data/DoajPaCandidateDataset.inc.php`) is licensed separately under
CC BY-SA 4.0. It contains excerpts from DOAJ documents (CC BY-SA 4.0) and from the Principles of Transparency and Best
Practice in Scholarly Publishing (COPE, DOAJ, OASPA, WAME; CC BY 4.0). `NOTICE.md` gives the full attribution. The
plugin is not reviewed or endorsed by DOAJ.

The licence permits redistribution. This build is nevertheless an internal-testing build, not a production release:
the candidate criteria are PENDING APPROVAL and the independent security audit is BLOCKED.

## Disclaimer

DOAJ Pre-Assessment is an independent journal self-evaluation tool. Its findings are advisory and based on publicly available evidence. Only DOAJ is authorized to make official decisions on journal applications.
