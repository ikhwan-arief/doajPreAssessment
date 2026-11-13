# DOAJ Pre-Assessment for OJS 3.3 — User Guide

DOAJ Pre-Assessment (`doajPreAssessment`, version **1.0.0.0**) is a generic plugin for **OJS 3.3.0-23**. It reads
a journal's public website, checks it against a set of criteria approved for this plugin, and helps the journal fix
what it finds before applying to the Directory of Open Access Journals (DOAJ).

> **ENGLISH-ONLY JOURNAL CONTENT.** This plugin evaluates only publicly accessible English-language journal
> websites. It uses deterministic rules, not Artificial Intelligence or automatic translation. Do not rely on its
> findings for policies written in other languages. This limitation applies to the plugin, not to DOAJ eligibility.

> **Independent of DOAJ.** The plugin is not affiliated with, reviewed or endorsed by DOAJ. Its results are a
> pre-assessment, never a DOAJ decision, and they do not predict or guarantee the outcome of an application. Only
> DOAJ decides on applications.

## What version 1.0.0.0 assesses

- **24 criteria** approved by the plugin's product owner. They are listed in
  [user-manual.md](user-manual.md#the-24-active-criteria).
- **4 DOAJ Form Guide questions:** FQ-07, FQ-08, FQ-15 and FQ-17.

The other 113 criteria of the reviewed criteria set and the other 16 Form Guide questions are **not assessed** in this
version. A good result in this plugin is therefore **not** a complete check of DOAJ eligibility.

## Manuals

| Guide | For | Contents |
|---|---|---|
| [Installation](installation.md) | Site administrator | Requirements, package check, installation, first run, installation problems |
| [Quick start](quick-start.md) | Journal Manager | Your first assessment, step by step |
| [User manual](user-manual.md) | Journal Manager | Every page and function of the plugin |
| [Understanding results](understanding-results.md) | Journal Manager | What each status means and why a pass is not a DOAJ approval |
| [Troubleshooting](troubleshooting.md) | Both | Problems by symptom, with safe checks and fixes |
| [FAQ](faq.md) | Both | Short answers to common questions |

**Administrator guide:**
- [Configuration](../administrator-guide/configuration.md)
- [Security and privacy](../administrator-guide/security-and-privacy.md)
- [Maintenance](../administrator-guide/maintenance.md)
- [Backup and restore](../administrator-guide/backup-restore.md)
- [Upgrade and rollback](../administrator-guide/upgrade-rollback.md)

## Where these manuals are

They are in the source repository under `docs/user-guide/` and `docs/administrator-guide/`. The installation archive
`doajPreAssessment-1.0.0.0.tar.gz` contains only the plugin, with its `README.md`, `RELEASE-NOTES.md`,
`CHANGELOG.md`, `LICENSE` and `NOTICE.md`. It does not contain these manuals.

## Licence

**Plugin code:** GPL-3.0-or-later, Copyright © 2026 Ikhwan Arief (ikhwan[at]unand.ac.id).

**DOAJ-derived material:** the criteria text quoted from DOAJ and from the Principles of Transparency and Best
Practice is licensed separately under CC BY-SA 4.0, with attribution in `NOTICE.md`.
