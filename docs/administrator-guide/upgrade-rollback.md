# Upgrade and Rollback

Tested on OJS 3.3.0-23 with PHP 7.3 and 8.2:
- upgrading 0.9.0.0 to 0.10.0.0 keeps the history row for row, and every sealed run still verifies;
- rollback by restoring the backups works.

## Before upgrading

1. **Verify the new archive's SHA-256** against the published value
   ([Installation](../user-guide/installation.md#2-verify-the-package)).
2. **Make sure no assessment is Running or Interrupted.** Finish, resume or cancel each one.
3. **Back up the database and the `plugins/generic/doajPreAssessment/` directory**, and preferably
   `{files_dir}/doajPreAssessment/` too ([Backup and restore](backup-restore.md)). A rollback requires these backups.

## Upgrade

1. Sign in as the site administrator.
2. Go to **Settings → Website → Plugins → Installed Plugins**. Find **DOAJ Pre-Assessment** and choose **Upgrade**.
3. Select the newer archive and confirm. OJS replaces the plugin files and runs the plugin's database migration. The
   migration only adds what is missing and never removes data.
4. **Reload PHP before using the plugin.** While OJS removes and copies the plugin files, plugin pages requested at
   that moment fail briefly: with HTTP 404, or with HTTP 500 and "Unable to read template" or "stat failed" for plugin
   files. With PHP OPcache, old and new code can also be mixed for up to `opcache.revalidate_freq` seconds (2 s by
   default). That gives HTTP 500 with "Undefined constant DoajPaSchema::…" or a similar missing class or method.
   - **Do this:** upgrade or roll back when nobody is using the plugin. Then reload PHP before anyone uses it:
     `apache2ctl -k graceful` for Apache with mod_php, or restart PHP-FPM.
   - **Measured on OJS 3.3.0-23** (`tests/integration/opcache_window_0601.py`, 20 file replacements per PHP version
     under continuous load; `package_0208` checks U00 and B00):
     - every error ended within 0.6 s of the start of the copy, during the copy or within the OPcache interval after it;
     - errors occurred in every trial on PHP 8.2 with OPcache, and in 6–9 of 20 trials on PHP 7.3 without OPcache;
     - the error kinds were missing templates or files, and the plugin's template resource not yet registered. The
       mixed-code kind was seen twice in real upgrades on PHP 8.2 and never lasted beyond the revalidation interval;
     - after a PHP reload, the first requests succeeded with no error;
     - the plugin tables were byte-identical before and after.
   - **If it persists:** an HTTP 500 that continues after a reload is a defect. Stop and escalate (see
     [Troubleshooting](../user-guide/troubleshooting.md#upgrade-leaves-an-inconsistent-state)).
   - **Timing:** avoid upgrading while an assessment is running.
5. **Check:**
   - **Installed Plugins** shows the new version (for example `0.10.0.0`);
   - plugin **Settings** shows the schema as **Complete** and the approved rule set;
   - earlier completed runs still show **Snapshot verified**.

**What changes in 0.10.0.0:**
- runs made before the upgrade keep their original rule set and labels;
- a candidate-profile run left active by 0.9.0.0 can only be cancelled.

**What OJS refuses:** an archive older than the installed version ("installed version is newer"). There is no
downgrade through the plugin list; use a rollback.

**If the upgrade fails:**
- If OJS removed the old files and the upgrade then failed, upload the new archive with **Upload A New Plugin**. The
  kept tables are reused.
- If the plugin answers 503, run `php tools/upgrade.php upgrade` from the OJS root.
- Otherwise, roll back.

## Rollback

A rollback restores the state from before the upgrade. **It requires both backups taken before the upgrade: the
database and the plugin directory.**

1. Put OJS in maintenance mode, or make sure no assessment is running.
2. Restore the **database backup**. This also restores OJS's record of the installed plugin version.
3. Restore the **plugin directory backup** to `plugins/generic/doajPreAssessment/`.
4. Restore `{files_dir}/doajPreAssessment/` from the same backup, if you backed it up.
5. Check that Installed Plugins shows the previous version, that Settings shows the schema as **Complete**, and that
   completed runs show **Snapshot verified**.

**Consequences:**
- **Lost data:** runs, findings, actions and reports created after the backup are lost. Export the reports you need
  before rolling back.
- **Files without database:** restoring only the older plugin files over a newer database is **not supported**.
- **Reused run IDs:** after the restore, OJS may reuse the IDs of discarded runs. The plugin moves those runs' leftover
  private files aside. If a run cannot continue, cancel it and start a new one.
