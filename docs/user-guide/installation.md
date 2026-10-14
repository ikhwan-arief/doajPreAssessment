# Installation Guide

For the **OJS site administrator**. Applies to version 1.0.0.0 on **OJS 3.3.0-23**.

## 1. Requirements

| | Required | Notes |
|---|---|---|
| OJS | **3.3.0-23** | OJS 3.4 and 3.5 are not supported and were not tested |
| PHP | **7.3 or 8.2** | Tested on PHP 7.3.33 and 8.2.34 |
| PHP extensions | **curl, dom, mbstring** | Used by the website reader and the text extraction |
| Database | MySQL or MariaDB with InnoDB | The plugin creates 13 tables named `doaj_*` |
| OJS `files_dir` | Set, and **writable by the web server** | The plugin keeps private working files and generated reports under `{files_dir}/doajPreAssessment/` |
| OJS `plugins/generic/` | Writable by the web server during installation | OJS copies the plugin there |
| Network | The server can reach the journal's **public** website over HTTP/HTTPS | The journal must be on a public internet address (see section 6) |
| OJS setting `disable_path_info` | **Off** | The website reader needs path-info URLs |
| OJS HTTP proxy | **Not configured** | The website reader refuses to run behind an OJS proxy |
| PHP time limit | Allow up to 120 seconds per request | Each assessment step asks PHP for up to 120 s (`set_time_limit`) and usually takes 10–40 s |

### Optional dependency: Poppler `pdftotext`

`pdftotext` is a separate program installed by the server administrator, for example the Debian/Ubuntu package
`poppler-utils`. It is **not** included in the plugin. The plugin looks for it at `/usr/bin/pdftotext` or
`/usr/local/bin/pdftotext`.

- **Without it:** PDF files are still downloaded and listed, but their text is not read. Each PDF is recorded as a
  coverage gap with the status "Parse failed" and the reason `pdf_extractor_unavailable`.
- **Check:** the New Assessment page shows whether it was found.
- **Tested versions:** pdftotext 25.03.0 and 20.09.0.

## 2. Verify the package

Download `doajPreAssessment-1.0.0.0.tar.gz` and its published SHA-256 checksum, then compare them:

```sh
shasum -a 256 doajPreAssessment-1.0.0.0.tar.gz        # macOS
sha256sum doajPreAssessment-1.0.0.0.tar.gz            # Linux
```

Install only if the value matches the published checksum exactly.

The archive contains one directory, `doajPreAssessment/`. Inside it, `MANIFEST.sha256` lists the SHA-256 of every
other file. After extracting, you can check every file from the extraction directory:

```sh
shasum -a 256 -c doajPreAssessment/MANIFEST.sha256
```

## 3. Back up first

Back up the OJS database and the OJS files before installing or upgrading any plugin. See
[Backup and restore](../administrator-guide/backup-restore.md).

## 4. Install through the OJS plugin interface (recommended)

1. Sign in as the **site administrator**.
2. Open a journal's settings: **Settings → Website → Plugins**.
3. Choose **Upload A New Plugin**, select `doajPreAssessment-1.0.0.0.tar.gz`, and save.
4. OJS copies the plugin to `plugins/generic/doajPreAssessment/` and creates the plugin's database tables.

The plugin's description in the plugin list shows the English-only warning before you enable it.

## 5. Enable it for each journal

Enabling is **per journal**. For each journal that should use the plugin:

1. Go to **Settings → Website → Plugins → Installed Plugins**. Under **Generic Plugins**, find **DOAJ Pre-Assessment**.
2. Tick its checkbox to enable it.
3. OJS shows the English-only warning as a notification right after enabling.

Journals where it is not enabled have no plugin pages and no menu entry.

## 6. First run and the English-only acknowledgement

Open the plugin in one of these ways:
- the backend menu entry **DOAJ Pre-Assessment**;
- the plugin's **Open DOAJ Pre-Assessment** action in the plugin list;
- `https://{your-site}/index.php/{journalPath}/doajPreAssessment`.

The menu entry is shown only to users the plugin authorizes. Those are the **root site administrator (user ID 1)**
and the members of the journal's default **Journal manager** group.

The first visit in each journal shows **"DOAJ Pre-Assessment: English-only acknowledgement"**. To continue:
1. Read the warning.
2. Tick *"I understand and confirm that this journal's public assessment content is in English."*
3. Choose **Confirm and continue**.

Until this is confirmed, every other plugin page redirects to it. The confirmation is recorded with the user's name
and the time. Every assessment later asks for its own confirmation.

## 7. Verify the installation

1. **Overview:** it shows
   - the status "Operational release…" with the approved rule set `doajpa-criteria-1.0.0`;
   - the plugin version `1.0.0.0`;
   - the OJS and PHP versions.
2. **Settings:**
   - "Database schema level" shows **Complete**;
   - "Approved DOAJ rule sets" shows `doajpa-criteria-1.0.0`.
3. **New Assessment:**
   - there is no message "The website reader cannot run here.";
   - the PDF text line shows whether `pdftotext` was found.
4. **First run:** run one assessment (see [Quick start](quick-start.md)). It is correctly installed when the run
   reaches "The website reading is complete and the run was sealed." and the run page shows **Snapshot verified**.

## 8. Manual installation (tested alternative)

1. Extract the archive.
2. Copy the `doajPreAssessment/` directory to `plugins/generic/` of the OJS installation, readable by the web server.
3. From the OJS root directory, run once:

   ```sh
   php tools/upgrade.php upgrade
   ```

   This creates the plugin's tables.
4. Enable the plugin per journal (section 5).

Until the tables exist, the plugin answers **HTTP 503** with an instruction to run that command.

## 9. Common installation problems

| Symptom | Cause | Remedy |
|---|---|---|
| The upload fails, or the plugin is not listed afterwards | `plugins/generic/` or the OJS temporary directory is not writable; the archive is damaged | Check the checksum (section 2) and directory permissions, then upload again. OJS removes copied files when the installation fails, and the database migration can safely run again |
| The plugin page answers "503 Service Unavailable. The DOAJ Pre-Assessment database tables are not installed…" | A manual copy without the database step, or an interrupted installation | Run `php tools/upgrade.php upgrade` from the OJS root, or upload the archive again |
| No **DOAJ Pre-Assessment** menu entry | The plugin is not enabled in this journal, or your account is not authorized | Enable it for the journal (section 5); see [Troubleshooting](troubleshooting.md#insufficient-privileges) |
| New Assessment shows "The website reader cannot run here." | An OJS HTTP proxy is configured, `disable_path_info` is On, or the journal URL cannot be read | See [Configuration](../administrator-guide/configuration.md) |
| "installed version is newer" | You uploaded an older archive over a newer installation | Use the newer archive; to go back, see [Upgrade and rollback](../administrator-guide/upgrade-rollback.md) |

To uninstall, use **Delete** in the plugin list. This removes the plugin files only. All plugin data is kept; see
[Maintenance](../administrator-guide/maintenance.md#disabling-and-uninstalling).
