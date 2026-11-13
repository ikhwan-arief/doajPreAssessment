# DOAJ Pre-Assessment for OJS 3.3

> **ENGLISH-ONLY JOURNAL CONTENT.** This plugin evaluates only publicly accessible English-language journal websites. It uses deterministic rules, not Artificial Intelligence or automatic translation. Do not rely on its findings for policies written in other languages. If essential non-English content is encountered, the affected check will be marked Unsupported Language / Needs Verification. This limitation applies to the plugin, not to DOAJ eligibility.

> **Version 1.3.2.0 — production release.** Reads the published content of the OJS database as well as the website;
> all 20 DOAJ Form Guide questions are answered. Approved criteria only; the candidate profile is disabled. Plain-language
> interface and reports, a built-in PDF reader, deletion of assessment history and automatic corrective actions.
> Accepted by the product owner on 2026-11-13. The plugin is **independent of DOAJ**: no result is a DOAJ decision, a
> compliance claim or a prediction of an application outcome. See `RELEASE-NOTES.md`.

## Overview

DOAJ Pre-Assessment is a generic plugin for Open Journal Systems (OJS) 3.3. It reads a journal's public website,
evaluates it with deterministic rules against criteria approved for this plugin, and helps the journal correct
gaps before applying to the Directory of Open Access Journals (DOAJ).

## Key Features

1. **Deterministic Assessment Engine (No AI / No Hallucinations)**
   - Operates strictly on deterministic heuristics, exact pattern matching, and rulebook criteria approved by the product owner.
   - Extracts verbatim textual quotes from published journal pages and policies as auditable evidence.

2. **Dual-Source Evidence Extraction**
   - **Public Website Crawl**: Crawls public journal policy pages, editorial boards, author guidelines, indexing notices, and announcement sections.
   - **Internal OJS Database Inspection**: Directly inspects journal settings, custom/static pages, sections, and published issues/articles.

3. **Built-in Document & PDF Processing**
   - Built-in PDF reader for parsing and analyzing full-text article galleys and guidelines.
   - Optional external integration with Poppler `pdftotext` for environments requiring high-volume legacy PDF extraction.

4. **DOAJ Form Guide Mapping & Questions**
   - Answers DOAJ application questions (including Form Guide questions FQ-07, FQ-08, FQ-15, and FQ-17) backed by verified quotes and page references.

5. **Actionable "Before You Apply" Matrix**
   - Groups identified gaps, warnings, and contradictions by the exact page where corrections need to be made.
   - Provides verbatim corrective recommendations citing official DOAJ requirements.

6. **Accountable Corrective Action Register**
   - Built-in task tracking for editorial teams: assign findings to team members, set due dates, track priority levels, and record completion evidence.

7. **Tamper-Evident Assessment History & Diff Comparisons**
   - Sealed, immutable assessment run history with SHA-256 snapshot hashes.
   - Interactive side-by-side run comparisons to track improvements, resolved issues, and newly introduced gaps across editorial cycles.

8. **Multi-Format Export Reports**
   - Generate self-contained, audit-ready reports in **HTML**, **PDF** (with embedded DejaVu Sans fonts for full multilingual Unicode support), and **DOCX** (standard WordprocessingML).

9. **Seamless OJS 3.3 Backend Theme Integration**
   - Native integration with OJS backend tabs (`.pkpTabs`), data tables, notices, and modal forms.
   - Fully accessible design conforming to WCAG 2.1 AA contrast requirements and complete keyboard navigability.

10. **Pre-Production Staging Mode & Strict Access Control**
    - Secure staging mode allowing journals on private/staging domains (or local Docker containers) to run pre-assessments before public launch.
    - Strict role authorization restricted strictly to the primary Site Administrator (User ID 1) and Journal Managers in their own journal.

**Scope of 1.3.2.0:** **24 criteria** and the DOAJ Form Guide questions **FQ-07, FQ-08, FQ-15 and FQ-17**. The other
113 criteria and 16 questions are not assessed, so a result is never a complete DOAJ eligibility check.

## Documentation

The manuals are in the source repository; they are not included in the installation archive.

**User guide** (`docs/user-guide/`):
- [README](docs/user-guide/README.md)
- [Installation](docs/user-guide/installation.md)
- [Quick start](docs/user-guide/quick-start.md)
- [User manual](docs/user-guide/user-manual.md)
- [Understanding results](docs/user-guide/understanding-results.md)
- [Troubleshooting](docs/user-guide/troubleshooting.md)
- [FAQ](docs/user-guide/faq.md)

**Administrator guide** (`docs/administrator-guide/`):
- [Configuration](docs/administrator-guide/configuration.md)
- [Security and privacy](docs/administrator-guide/security-and-privacy.md)
- [Maintenance](docs/administrator-guide/maintenance.md)
- [Backup and restore](docs/administrator-guide/backup-restore.md)
- [Upgrade and rollback](docs/administrator-guide/upgrade-rollback.md)

## Requirements

| Requirement | |
|---|---|
| OJS | **3.3.0-23**. OJS 3.4 and 3.5 are not supported |
| PHP | **7.3 or 8.2** (tested on 7.3.33 and 8.2.34), with the extensions `curl`, `dom` and `mbstring` |
| Configuration | `disable_path_info = Off`; no OJS HTTP proxy; `files_dir` writable by the web server; the journal reachable at a public address |

**Optional: Poppler `pdftotext`** (Debian/Ubuntu package `poppler-utils`), found at `/usr/bin/pdftotext` or
`/usr/local/bin/pdftotext`. It is a separate program installed by the server administrator; it is not included in the
plugin. Without it, PDF files are listed but their text is not read; each PDF is recorded as a coverage gap with the
reason `pdf_extractor_unavailable`.

## Pre-production mode

The website reader normally refuses private and loopback addresses. To check a website before it is installed on its
public production server (for example `https://localhost:8445/index.php/jaes`), the server administrator can add to
`config.inc.php`:

```ini
[doajPreAssessment]
preproduction_private_targets = On
preproduction_connect_to = "localhost:8445=127.0.0.1:443"   ; optional host:port=ip:port
preproduction_insecure_tls = On                              ; optional, self-signed certificates
```

Only the journal's own host and port are covered; every run that uses the mode is flagged, audited and labelled
PRE-PRODUCTION MODE in its reports. Details: `docs/administrator-guide/configuration.md`. Switch it off in production.

## Access

Only two kinds of user can use the plugin:
- the **root site administrator (user ID 1)**;
- members of the journal's default **Journal manager** group, in their own journal.

All other users are denied, including other site administrators, editors and any session opened with OJS
"Login as".

## Installation (OJS 3.3.0-23)

1. **Verify the archive** against the published SHA-256:

   ```sh
   shasum -a 256 doajPreAssessment-1.3.2.0.tar.gz
   ```

   It contains one directory, `doajPreAssessment/`, with `MANIFEST.sha256` listing every file's SHA-256.
2. **Back up** the OJS database and files.
3. **Upload:** as site administrator, go to **Settings → Website → Plugins → Upload A New Plugin** and choose the
   archive. OJS installs the plugin in `plugins/generic/doajPreAssessment/` and creates its 13 database tables.
4. **Enable** "DOAJ Pre-Assessment" in **Installed Plugins → Generic Plugins** of each journal that should use it.
   OJS shows the English-only warning after enabling.
5. **Open** it from one of these:
   - the backend menu entry **DOAJ Pre-Assessment**;
   - **Open DOAJ Pre-Assessment** in the plugin list;
   - `/index.php/{journalPath}/doajPreAssessment`.
6. **Acknowledge:** the first visit in each journal requires the English-only acknowledgement. Every assessment asks
   for it again.

**Manual installation** (tested): copy `doajPreAssessment/` into `plugins/generic/`, then run
`php tools/upgrade.php upgrade` once from the OJS root. Until then the plugin answers HTTP 503 with this instruction.

**First-run check:** Settings shows the schema as **Complete**, and a first assessment ends with
"The website reading is complete and the run was sealed." See
[Installation](docs/user-guide/installation.md) and [Quick start](docs/user-guide/quick-start.md).

### Upgrade

1. Back up the OJS database and the `plugins/generic/doajPreAssessment/` directory.
2. Finish or cancel any active assessment.
3. In the plugin list, choose DOAJ Pre-Assessment → **Upgrade** and select the newer archive. The migration only adds
   what is missing and never removes data.
4. Reload PHP (`apache2ctl -k graceful`, or restart PHP-FPM) before using the plugin: pages requested while the files are
   replaced can fail briefly.

OJS refuses an older archive ("installed version is newer"). Completed runs stay sealed and verifiable.

### Rollback

Restore **both** backups taken before the upgrade: the database and the plugin directory. Runs and other records
created after the backup are lost. Restoring only older plugin files over a newer database is not supported. See
[Upgrade and rollback](docs/administrator-guide/upgrade-rollback.md).

### Uninstall and reinstall

**Delete** in the plugin list removes the plugin files only. All plugin data is kept, and reinstalling the same or a
newer version reattaches it:
- the 13 `doaj_` tables;
- the plugin settings;
- the files under `files_dir/doajPreAssessment/`.

The plugin has no data purge.

### Recovery after a failure

- **The upload failed:** correct the cause (for example permissions) and upload again; the migration can safely run
  again.
- **HTTP 503 (tables missing):** run `php tools/upgrade.php upgrade`.
- **An assessment step failed:** the run is "Interrupted" with its reason; resume or cancel it. See
  [Troubleshooting](docs/user-guide/troubleshooting.md).

## License and Copyright

Copyright © 2026 Ikhwan Arief <ikhwan@unand.ac.id>

**Plugin code:** free software under the GNU General Public License, version 3 or (at your option) any later version
(GPL-3.0-or-later). The full text is in `LICENSE`.

**DOAJ-derived reference material:** the files `classes/rules/data/DoajPaCandidateDataset.inc.php` and
`classes/rules/data/DoajPaApprovedDataset.inc.php` are licensed separately under CC BY-SA 4.0. They contain excerpts
from DOAJ documents (CC BY-SA 4.0) and from the Principles of Transparency and Best Practice in Scholarly Publishing
(COPE, DOAJ, OASPA, WAME; CC BY 4.0). `NOTICE.md` gives the full attribution. The plugin is not reviewed or endorsed by
DOAJ.

## Disclaimer

DOAJ Pre-Assessment is an independent journal self-evaluation tool. Its findings are advisory and based on publicly
available evidence. Only DOAJ is authorized to make official decisions on journal applications.
