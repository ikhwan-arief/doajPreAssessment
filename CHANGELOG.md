# Changelog

All notable changes to the DOAJ Pre-Assessment plugin. Versions below 1.0 are development builds.
They are not compliance-ready and do not contain DOAJ assessment rules.

## [0.4.0.0] - 2026-10-09 (Stage 02.06, development build)

### Added

- Public website crawler for one journal (no user interface yet): starts from the journal homepage as OJS
  routes it, stays on the same host, port and journal path, skips OJS backend and dynamic paths, honours
  robots.txt, and applies limits on pages, issues, articles, files, depth, size and time.
- SSRF protection: only http/https; strict host syntax; every resolved address must be public; the vetted
  address is pinned for the connection; every redirect hop is re-checked; no proxy, cookies or credentials;
  size caps after decompression. The crawler refuses to run when OJS is configured with an HTTP proxy.
- HTML text and link extraction (scripts never executed or followed) and PDF text extraction with page
  markers through the server's `pdftotext`, with explicit statuses for missing, inaccessible, unreadable,
  scanned, encrypted, oversized and non-English content.
- Results are stored through the existing services with URL, final URL, HTTP status, media type, SHA-256,
  UTC time and extraction version; a coverage summary is stored on the run and a full manifest privately.

### Security

- A development-only exception for private test addresses exists only in unpackaged test code and needs an
  exact switch value and exact host:port=ip entries; it is never part of this package.

## [0.3.0.0] - 2026-10-09 (Stage 02.05, development build)

### Added

- Backend views (Stage 01 F8 navigation): Overview, New Assessment, Criteria & Findings, finding detail,
  Evidence, DOAJ Form Guide, Before You Apply, Corrective Actions (create and edit), Assessment History,
  run detail, run comparison, Reports and Settings (including the audit log).
- Historical statuses are read from each run's own observations; comparisons flag different criterion versions.
- Every run is labelled "Not a DOAJ assessment result" while no approved DOAJ rule set is installed.
- Corrective-action edits use optimistic locking: a concurrent change gives HTTP 409 and saves nothing.
- Backend menu entry, shown only to users the plugin authorizes.
- Stylesheet: text-labelled badges, visible focus, layouts that fit narrow screens.

### Not included

- Running assessments, report generation and downloads, the Form Guide and Before You Apply content.
  These pages say so and show nothing generated.

## [0.2.0.0] - 2026-10-09 (Stage 02.04, development build)

### Added

- Plugin-owned schema (13 `doaj_` tables) with an idempotent migration run by `install.xml`, `upgrade.xml`
  and the OJS install/upgrade path. Journal isolation is enforced by composite foreign keys.
- Service layer for assessment runs, crawl resources, evidence, criterion results, form answers, findings
  with stable IDs, append-only observations and transitions, corrective actions, report-export metadata,
  acknowledgements and audit events.
- Completed runs are sealed with a SHA-256 snapshot digest and are never modified.
- A per-run English-only acknowledgement is required to create a run. The first-use acknowledgement and
  authenticated access denials are recorded in the audit log.
- Plugin requests answer HTTP 503 with remediation text when the schema is missing (manual-copy installs).

### Not included

- Crawling, DOAJ rules, findings from real assessments and any user interface for history. The history
  engine is exercised only by test fixtures.

## [0.1.1.0] - 2026-10-09 (Stage 02.03, development build)

### Security

- Deny every OJS "Login as" (impersonated) session. Before this fix, a Journal editor or a non-root site
  administrator could reach the plugin by using "Login as" on a default Journal manager (ADR-002 R1.3).

### Fixed

- `acknowledge` rejects non-string `warningSha256` input instead of casting it.
- Plugin pages mark their content `lang="en"`, so it stays correctly labelled under a non-English interface locale.

## [0.1.0.0] - 2026-10-09 (Stage 02.02, development build)

### Added

- Generic plugin bootstrap for OJS 3.3.0-23: `index.php`, `version.xml`, `DoajPreAssessmentPlugin`.
- Backend-only page `/{journal}/doajPreAssessment`, routed only while the plugin is enabled for that journal.
- Central authorization guard: user ID 1 with a verified site-administrator role, or a member of the
  journal's default "Journal manager" group. Everyone else receives HTTP 403 with no data.
- Op allow-list (404 for any other handler method), HTTP method checks (405) and CSRF on POST.
- Mandatory English-only first-use acknowledgement per journal (exact Stage 01 F1 text), bound to the
  SHA-256 of the warning text.
- English-only warning in the plugin description and in a notification after enabling.
- English locale file registered for every interface language.

### Not included

- Assessment, crawling, findings, history, reports and DOAJ rules. These belong to later stages.
