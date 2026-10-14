# Frequently Asked Questions

**Does this plugin determine DOAJ eligibility?**
No. It pre-assesses 24 criteria on the public pages it reads. Only DOAJ decides on applications, using all of its
criteria and human review.

**Is the plugin officially affiliated with DOAJ?**
No. It is independent of DOAJ and not reviewed or endorsed by it. It quotes DOAJ's published criteria under their
CC BY-SA 4.0 licence.

**Why are only 24 criteria active?**
Version 1.0.0.0 activates the first wave of criteria approved by the plugin's product owner. The other 113 criteria
and 16 Form Guide questions are deferred: some need amendments, an external registry, or an approved reference. They
are not assessed.

**Why are some outcomes unverifiable?**
Because the plugin abstains when it cannot establish a requirement from verified English pages. This happens when a
page was not readable, its language was not verified, the reading stopped at a limit, or the wording was not
recognised. Unverifiable is never a failure. See [Understanding results](understanding-results.md).

**Can the plugin assess non-English journals?**
No. It supports English-language journal websites only. Non-English pages are recorded but never used as evidence.
This is a limitation of the plugin, not a DOAJ requirement.

**Does it verify an ISSN against the ISSN Portal?**
No. It checks that an ISSN is displayed and that its check digit is valid. It makes no external lookups.

**Does it verify indexing claims?**
No. Indexing and metrics criteria are not active in this version, and no index is classified as unreliable.

**Does it automatically submit the DOAJ application?**
No. The Form Guide helps you prepare four answers; you complete and submit the application yourself on DOAJ's site.

**Does it access unpublished manuscripts?**
No. It reads only the public website, without logging in. It never reads submissions, reviews, editorial decisions or
workflow data, and it sends nothing to an AI service.

**Can a Journal Manager use it?**
Yes, if they are a member of the journal's default **Journal manager** group. The root site administrator (user ID 1)
can also use it. Other roles cannot.

**Can multiple journals use it on one OJS installation?**
Yes. Enable it per journal. Each journal's runs, findings, actions and reports are isolated from the others.

**Why are some PDF publication dates missing?**
Article-level publication-date criteria are not active in 1.0.0.0. PDF text is read only when `pdftotext` is
installed and the PDF contains extractable text. Activity is judged from the dated issues and articles that were read.

**Are assessments stored permanently?**
Yes. Completed runs are sealed with a SHA-256 snapshot and never change. Disabling, upgrading or deleting the plugin
keeps all data; the plugin has no data purge.

**How are findings resolved?**
Only by a new assessment that passes the criterion on new public evidence. The finding becomes "Resolved (verified)"
under the same ID. Marking a corrective action as done does not resolve anything.

**Can results be exported?**
Yes. Reports can be generated as HTML, PDF or DOCX for any completed, verified run, with at most 30 per run. They are
private downloads for authorized users of the journal.

**How should the plugin be upgraded safely?**
1. Back up the database and the plugin directory.
2. Upload the newer archive through **Upgrade** in the plugin list.
3. Check that Settings shows the schema as **Complete** and that earlier runs still verify.

See [Upgrade and rollback](../administrator-guide/upgrade-rollback.md).
