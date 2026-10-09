# Changelog

All notable changes to the DOAJ Pre-Assessment plugin. Versions below 1.0 are development builds.
They are not compliance-ready and do not contain DOAJ assessment rules.

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
