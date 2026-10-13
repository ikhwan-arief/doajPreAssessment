# Maintenance

## Routine checks

| When | Check | Where |
|---|---|---|
| After installation, upgrade or restore | Schema level **Complete**; approved rule set `doajpa-criteria-1.0.0` | Plugin **Settings** |
| Weekly, or after server changes | Latest completed run shows **Snapshot verified** | **Overview**, run pages |
| Weekly | No unexpected `access_denied` bursts; no runs left **Interrupted** | **Settings → Audit log**; **Assessment History** |
| Weekly | No `doajPreAssessment:` errors | PHP error log |
| Monthly | Free space in `files_dir`; `pdftotext` still detected | Server; **New Assessment** |
| Before OJS or PHP upgrades | The target versions are supported (OJS 3.3.0-23; PHP 7.3 or 8.2) | [Configuration](configuration.md) |

## Operational monitoring

**Health signals:**
- runs reach **Completed** with **Snapshot verified**;
- no PHP fatal errors mention `DoajPa`;
- `{files_dir}/doajPreAssessment/` is writable.

**Each completed run shows:**
- the pages read;
- the coverage gaps;
- the limits reached.

A sudden rise in **Inaccessible** pages usually means a network, DNS or TLS problem on the server or the journal site
(see [Troubleshooting](../user-guide/troubleshooting.md#dns-or-tls-errors)).

## Interrupted and abandoned runs

- An **Interrupted** run blocks new runs of that journal until it is resumed or cancelled. **Resume assessment** after
  removing the cause, or **Cancel assessment**.
- A **Running** run whose browser was closed stays Running. Open it from Assessment History and continue, or cancel it.
- Cancelled runs keep their records. They are never sealed and never reported.

## Disk usage

**What uses space:**
- **Crawl state:** each run keeps its private crawl state under `{files_dir}/doajPreAssessment/{journal}/crawl/{run}/`.
- **Reports:** at most 30 report files per run, under `.../reports/{run}/`. A PDF report is about 1.5 MB, because the
  fonts are embedded.

**Rules for cleanup:**
- The plugin does not delete files.
- Do not delete files of runs that are still active.
- Deleting a report file makes that report non-downloadable; it is never served altered. Generate a new report
  instead.
- After a database restore, the plugin moves the private files of discarded runs aside rather than reusing them. You
  may archive these after confirming they belong to discarded runs.

## Data that must not be edited

**Never edit, by hand:**
- the plugin's `doaj_*` tables;
- the sealed runs;
- the approved dataset (`classes/rules/data/DoajPaApprovedDataset.inc.php`).

**Why:**
- Direct edits to a run's records make its snapshot fail verification.
- The approved criteria change only through a new signed approval and a new release.

## Disabling and uninstalling

- **Disable:** untick the plugin in the journal's plugin list. Pages and the menu entry disappear. All data is kept
  and returns when it is enabled again.
- **Uninstall:** **Delete** in the plugin list removes the plugin files only. The 13 `doaj_*` tables, the plugin
  settings and the files in `{files_dir}/doajPreAssessment/` are kept, and reinstalling the same or a newer version
  reattaches them.

  Permanent removal of the data is a database administrator's decision outside the plugin, taken after a backup. The
  plugin offers no purge function.

## Licensing obligations when redistributing

- **Licence files:** keep `LICENSE` (GPL-3.0-or-later) and `NOTICE.md` with the plugin.
- **Notices:** keep the copyright notice "Copyright © 2026 Ikhwan Arief".
- **DOAJ-derived data:** the data files carry a separate CC BY-SA 4.0 licence; keep their attribution.
