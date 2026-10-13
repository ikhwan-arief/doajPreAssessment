# Backup and Restore

The plugin's data lives in three places. **A consistent backup must include all three, taken at the same time,**
preferably while no assessment is running.

| What | Location |
|---|---|
| Plugin database tables | 13 tables named `doaj_*` in the OJS database, plus OJS's own `plugin_settings` and `versions` rows for the plugin |
| Private files | `{files_dir}/doajPreAssessment/`: crawl state and generated reports |
| Plugin code | `plugins/generic/doajPreAssessment/` |

## Backup

1. Make sure no run is **Running** (check **Assessment History** in each journal), or accept that an active run may
   need to be cancelled after a restore.
2. Back up the **whole OJS database**, so that the OJS version records stay consistent with the plugin tables.
   Example for MySQL or MariaDB:

   ```sh
   mysqldump --single-transaction -u <ojs_db_user> -p <ojs_db_name> > ojs-backup.sql
   ```

3. Back up the plugin directory and the plugin's private files:

   ```sh
   tar -czf doajpa-plugin-backup.tar.gz -C <ojs_root>/plugins/generic doajPreAssessment
   tar -czf doajpa-files-backup.tar.gz -C <files_dir> doajPreAssessment
   ```

4. Store the backups securely. They contain user names and the journal's assessment data.

Your normal full OJS backup of the database and `files_dir` already covers the plugin data, provided it also covers
`plugins/`.

## Restore

1. Put OJS in maintenance mode, or make sure no assessment is running.
2. Restore the database backup and the matching plugin directory. **Never combine plugin files of one version with a
   database from another version.**
3. Restore `{files_dir}/doajPreAssessment/` from the same backup.
4. Check the restore:
   - plugin **Settings** shows the schema as **Complete**;
   - completed runs show **Snapshot verified**.

**What is lost:** anything recorded after the backup, such as runs, actions and reports.

**Effect on later runs:** after a database restore, OJS may give new runs the IDs of discarded ones. The plugin detects
private files left from those discarded runs and moves them aside instead of reusing them. It refuses to continue a run
whose private files are newer than its stored records; cancel such a run and start a new one.

## What not to do

- **Do not restore single plugin tables or rows.** Partial restores break snapshot verification and journal
  consistency.
- **Do not edit sealed runs to "repair" them.** Restore a consistent backup instead.
