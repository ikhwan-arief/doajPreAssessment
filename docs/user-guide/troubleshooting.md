# Troubleshooting

Each problem lists its **symptom, likely cause, a safe diagnostic procedure, the corrective action, and when to
escalate**. "Escalate" means: contact the OJS site administrator. If they cannot resolve it, contact the plugin
maintainer with the run number, the time, the plugin version and the relevant lines of the PHP error log.

**Never** bypass the plugin's access control, its network protections (the refusal of private addresses, proxies and
redirects outside the journal), or its sealed history. Never edit plugin tables or files by hand.

**Useful places:**
- **Settings:** the schema status, the approved rule set and the **Audit log** of the latest 100 events.
- **Run page:** the run status, the interruption reason, and **Coverage and gaps**.
- **PHP error log:** plugin entries start with `doajPreAssessment:`.

---

### Plugin does not appear in OJS
1. **Symptom:** no "DOAJ Pre-Assessment" in **Settings → Website → Plugins → Installed Plugins**, or no menu entry.
2. **Cause:**
   - The archive was not installed, or the upload failed.
   - The plugin is not enabled in this journal (enabling is per journal).
   - Your account is not authorized, so the menu entry is hidden.
3. **Diagnose:**
   - Check that `plugins/generic/doajPreAssessment/` exists.
   - Look for the plugin under **Generic Plugins**.
   - Check your roles (see "Insufficient privileges").
4. **Fix:** install it ([Installation](installation.md)), then tick its checkbox in this journal.
5. **Escalate:** if it is installed and enabled but still absent for the root administrator.

### Installation or upgrade fails
1. **Symptom:** the upload reports an error; the plugin page answers "503 Service Unavailable. The DOAJ Pre-Assessment
   database tables are not installed…"; or Settings shows "Problems:" next to the schema level.
2. **Cause:**
   - The directory is not writable.
   - The archive is damaged.
   - The database step was interrupted, or the plugin was copied manually without running it.
3. **Diagnose:**
   - Verify the archive SHA-256.
   - Check the permissions of `plugins/generic/` and of the OJS temporary directory.
   - Read the PHP error log.
4. **Fix:**
   - Upload the archive again; the database step is safe to repeat.
   - Or, from the OJS root, run `php tools/upgrade.php upgrade`.
   - If an upgrade removed the old files and failed, upload the new archive with **Upload A New Plugin** (the kept
     tables are reused), or restore the backup ([Upgrade and rollback](../administrator-guide/upgrade-rollback.md)).
5. **Escalate:** if the schema stays incomplete after `tools/upgrade.php upgrade`.

### Insufficient privileges
1. **Symptom:** "You are not authorized to use DOAJ Pre-Assessment for this journal." (HTTP 403).
2. **Cause:** only the root site administrator (user ID 1) and members of the journal's default **Journal manager**
   group are admitted. Other site administrators, editors and custom manager groups are denied by design.
3. **Diagnose:**
   - In **Users & Roles**, check the user's groups in this journal.
   - The plugin's **Audit log** records authenticated denials as `access_denied`.
4. **Fix:** assign the user to the journal's **Journal manager** group, if that is appropriate for your organisation.
5. **Escalate:** only if an authorized user is still denied.

### Journal Manager cannot open the plugin
1. **Symptom:** a Journal Manager of journal A cannot see the plugin, or receives 403.
2. **Cause:**
   - The plugin is not enabled in journal A.
   - The user manages a different journal (access is per journal).
   - The user is in a group such as "Journal editor" rather than "Journal manager".
   - The session uses **Login as**.
3. **Diagnose:** check the plugin is enabled in A, the user's groups in A, and whether a "Login as" session is active.
4. **Fix:** enable the plugin or correct the group membership, then sign in directly.
5. **Escalate:** if all conditions are met and access is still denied.

### Impersonated sessions are denied
1. **Symptom:** an administrator using OJS **Login as** gets 403.
2. **Cause:** sessions opened with "Login as" are always denied, by design. The audit log names the effective user and
   the impersonator.
3. **Diagnose:** check whether OJS shows the "Login as" banner.
4. **Fix:** sign out of "Login as", then sign in as an authorized user.
5. **Escalate:** never; this is intended.

### Assessment cannot start
1. **Symptom:** New Assessment shows "The assessment was not started." or "The website reader cannot run here."
2. **Cause:**
   - The per-run English-only box was not ticked.
   - Another run is active ("Another assessment of this journal is still active. Finish, resume or cancel it first.").
   - An OJS HTTP proxy is configured.
   - `disable_path_info` is On.
   - The journal homepage URL cannot be read.
3. **Diagnose:** read the message on the page, and check **Assessment History** for a Running or Interrupted run.
4. **Fix:**
   - Tick the box.
   - Finish, resume or cancel the active run.
   - Have the administrator remove the OJS proxy setting or set `disable_path_info = Off`, if appropriate for the
     installation ([Configuration](../administrator-guide/configuration.md)).
5. **Escalate:** if the reason is the proxy or the URL and it cannot be changed. Do not try to work around it.

### Crawl fails or stops
1. **Symptom:** the run becomes **Interrupted**; or it completes with many "Pages not retrieved" or "Limits reached".
2. **Cause:**
   - **Interruption:** the private files directory is unwritable, or the server time limit is too short.
   - **Gaps:** the journal site was unreachable or slow, `robots.txt` disallows pages, or a crawl limit was reached
     (150 pages, 4 issues, 40 articles, 20 PDFs, 900 s).
3. **Diagnose:**
   - The run page shows the interruption **Reason**.
   - **Coverage and gaps** lists each page's state and reason.
   - The PHP error log may show `doajPreAssessment: run N step failed: …`.
4. **Fix:** remove the cause (permissions, time limit), then **Resume assessment**. For gaps, make the pages publicly
   reachable, then run again. The limits are fixed in this version.
5. **Escalate:** if the same step fails again after the cause was removed.

### Assessment remains interrupted
1. **Symptom:** **Resume assessment** fails, or the run stays Interrupted.
2. **Cause:**
   - The cause was not removed.
   - The private crawl state no longer matches the stored records, typically after a database restore: "The private
     crawl state does not belong to this run…" or "…is ahead of the stored records…".
   - The run was created by a 0.9 candidate profile: "The candidate profile is not available in this version; cancel
     this run."
3. **Diagnose:** read the interruption reason on the run page.
4. **Fix:** remove the cause and resume. Otherwise choose **Cancel assessment** and start a new run; cancelled runs
   keep their records.
5. **Escalate:** if new runs are also interrupted.

### DNS or TLS errors
1. **Symptom:** the homepage or pages are listed as **Inaccessible**, with a network reason.
2. **Cause:** the server cannot resolve the journal's host name, the connection times out, or the HTTPS certificate is
   invalid or not trusted by the server. Certificates are always verified.
3. **Diagnose:** from the server, test the journal URL with a normal client (for example `curl -I https://…`), and check
   the server's DNS and CA certificate store.
4. **Fix:** correct DNS, renew or complete the certificate chain, or update the server's CA store. Then run again.
5. **Escalate:** if the URL works from the server but the plugin still reports it as inaccessible.

### Network protection rejects a URL
1. **Symptom:** pages are **Inaccessible** with a reason beginning `blocked_address:`, `blocked_destination`, or links
   skipped as `other_host_or_port`.
2. **Cause:** the reader contacts only public internet addresses on the journal's own scheme, host, port and path. It
   refuses private, loopback or reserved addresses, DNS answers that mix public and private addresses, and redirects
   leaving the journal.
3. **Diagnose:** check the journal's public URL and DNS records, and whether the site redirects to another host.
4. **Fix:** a journal served only on a private or intranet address cannot be assessed. Publish it on a public address
   and make its URL consistent (scheme, host and path). **Do not disable or work around this protection.**
5. **Escalate:** if a public URL is reported as blocked.

### PDF text cannot be extracted
1. **Symptom:** PDFs show "Parse failed" with a reason such as `pdf_extractor_unavailable`, `encrypted`, `corrupt`,
   `no_text`, `timeout` or `not_a_pdf`.
2. **Cause:**
   - `pdftotext` is not installed.
   - The PDF is encrypted, scanned (images only), damaged, larger than 20 MB, or took more than 30 s.
3. **Diagnose:** New Assessment shows whether `pdftotext` was found; the reason is shown per PDF.
4. **Fix:**
   - Install `poppler-utils` so that `/usr/bin/pdftotext` or `/usr/local/bin/pdftotext` exists.
   - Publish text-based, unencrypted PDFs.
   - Five active criteria can also use article or PDF text (`guide.actively-publishing`, `guide.licensing-terms-stated`,
     `guide.copyright-no-contradiction`, `transparency.oa-open-licence`, `copyright.no-contradiction`). Unreadable PDFs
     never cause a failure, but they can make those results Unverifiable.
5. **Escalate:** if `pdftotext` is installed but not detected.

### Journal language is undetermined
1. **Symptom:** many pages appear under "Language not verified as English"; results are Unverifiable.
2. **Cause:** a page counts as verified English only with at least 40 words of main content and a clear English share.
   Short pages (lists of names, menus) stay undetermined. Non-English or mixed pages are never used as English evidence.
3. **Diagnose:** open the page and check how much English prose its main content has.
4. **Fix:** publish policies as full English sentences on their pages. Ticking the English-only confirmation does not
   change the verdict.
5. **Escalate:** if a page with substantial English prose is classified otherwise; send its URL.

### Criteria return unverifiable
1. **Symptom:** many criteria are **Unverifiable**.
2. **Cause:**
   - Relevant pages were not read, or not verified as English.
   - The crawl hit a limit.
   - The wording is outside the plugin's English patterns.
   - Unverifiable is never a deficiency.
3. **Diagnose:** read each result's rationale; it names the missing or unreadable pages. Compare with **Coverage and
   gaps**.
4. **Fix:**
   - Make the policies available on readable English pages linked from the homepage, then run again.
   - Check anything that remains yourself (Before You Apply → Needs verification).
5. **Escalate:** if a clearly stated policy on a verified English page is consistently unverifiable.

### Article dates cannot be detected
1. **Symptom:** `guide.actively-publishing` is Unverifiable ("No dated issue or article was read as verified-English
   evidence", or the archive was not read completely).
2. **Cause:**
   - Issue and article dates come from the issue and article pages that were read.
   - The archive or issue pages were not reachable.
   - The crawl stopped at a limit.
   - Article-level publication-date criteria are not active in version 0.10.0.0.
3. **Diagnose:** check that the **Archives** page and the latest issues are public and listed as read.
4. **Fix:** make the archive and the issue pages public and readable, then run again.
5. **Escalate:** if dated issues are public and read but still not recognised.

### Findings appear inconsistent
1. **Symptom:** a finding changes status between runs without a website change, or two runs disagree.
2. **Cause:**
   - Each run reads the site again, so different pages may be read (availability, limits).
   - A page that cannot be read gives "Needs verification", never "Resolved".
   - Runs from different rule sets, such as an old candidate run, are not directly comparable.
3. **Diagnose:**
   - Compare **Coverage and gaps** of both runs.
   - In Compare, look for "These runs used different rule sets."
   - Read each run's own record; earlier runs never change.
4. **Fix:** ensure the relevant pages are reachable, then run again.
5. **Escalate:** if two runs with the same coverage and website give different results for the same criterion.

### Export or download fails
1. **Symptom:** "A report can be generated only for a completed run whose sealed snapshot verifies."; "This run already
   has the maximum number of generated reports (30)…"; a download is refused (HTTP 409); or generation fails.
2. **Cause:**
   - The run is not completed, or its snapshot does not verify.
   - The quota of 30 reports per run is reached.
   - The stored report file was changed or removed on disk; the plugin then refuses to serve it.
   - `{files_dir}/doajPreAssessment/` is not writable.
3. **Diagnose:** check the run's status and **Snapshot verified**, the number of recorded exports, and the directory
   permissions.
4. **Fix:**
   - Use a completed, verified run.
   - Download an existing report.
   - Correct the permissions.
   - Generate a new report instead of a changed one.
5. **Escalate:** if a verified completed run cannot be exported.

### HTML, PDF and DOCX output differs
1. **Symptom:** the formats look different.
2. **Cause:** the three formats are built from the same report model and carry the same text; only the layout differs.
   In the PDF, characters without a glyph in DejaVu Sans (for example CJK) appear as `[U+XXXX]`.
3. **Diagnose:** compare the section headings and the text, not the layout.
4. **Fix:** none needed for layout. For the full original characters, use the HTML or DOCX report.
5. **Escalate:** if text is missing from one format.

### Assessment history cannot be verified
1. **Symptom:** a completed run shows **Snapshot verification FAILED**.
2. **Cause:** the run's stored records were changed outside the plugin, for example by a direct database edit, a
   partial restore or corruption.
3. **Diagnose:**
   - Note the run number.
   - Check the database and backup history.
   - Do not modify anything.
4. **Fix:** restore a consistent database backup ([Backup and restore](../administrator-guide/backup-restore.md)). The
   plugin will not generate reports from an unverified run.
5. **Escalate:** always.

### Upgrade leaves an inconsistent state
1. **Symptom:** after an upgrade the plugin answers 503, Settings shows schema problems, or OJS lists an unexpected
   version. Or, right after the upgrade, a page fails once with HTTP 500 and the PHP error log shows "Undefined
   constant DoajPaSchema::…" or a similar missing class or constant.
2. **Cause:**
   - The upgrade was interrupted, or older files were restored over a newer database.
   - For an HTTP 404 or 500 during or right after an upgrade or rollback, the likely cause is that the page was
     requested while OJS was replacing the plugin files. With PHP OPcache, old code can also be served for up to a few
     seconds.

     An HTTP 500 is **not** harmless by default. It is only this transient case when **all** of the following hold:
     - the errors are "Unable to read template", "Unable to load template", "Unknown resource type", "stat failed",
       "Undefined constant", "Call to undefined method" or "Class … not found";
     - they concern files under `plugins/generic/doajPreAssessment/`;
     - they occurred only while the files were being replaced, or within a few seconds after;
     - they stop after PHP is reloaded.
3. **Diagnose:**
   - Check the installed files against `MANIFEST.sha256`, and the version under **Installed Plugins**.
   - For an HTTP 500, read the PHP error log entries of that time.
4. **Fix:**
   - For the transient OPcache case:
     1. Reload PHP (`apache2ctl -k graceful` for Apache with mod_php, or restart PHP-FPM).
     2. Open **Settings** and confirm the schema is **Complete**.
     3. Confirm that completed runs show **Snapshot verified**.
   - If any HTTP 500 continues after the reload, or the error is of another kind, treat it as a defect: stop using the
     plugin, keep the log, and escalate.
   - Run `php tools/upgrade.php upgrade`.
   - Or upload the intended archive again.
   - Or perform a complete rollback.
   - Do not mix files of one version with the database of another.
5. **Escalate:** if the HTTP 500 continues after a PHP reload, or if neither of the other fixes resolves it.

### Rollback is required
1. **Symptom:** the new version must be withdrawn.
2. **Cause:** an operational decision.
3. **Diagnose:** confirm that you have **both** backups taken before the upgrade: the database and the plugin directory.
4. **Fix:** follow [Upgrade and rollback](../administrator-guide/upgrade-rollback.md#rollback). Runs created after the
   backup are lost.
5. **Escalate:** if either backup is missing.
