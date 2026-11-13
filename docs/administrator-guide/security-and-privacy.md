# Security and Privacy

## What the plugin processes

- **Public content only.** It reads the journal's public website: HTML pages and public PDF files. It does not log
  in, and it never reads submissions, reviews, editorial decisions, user data or any other OJS workflow table.
- **No AI.** It uses no large language model, no machine learning and no automatic translation. It sends no
  manuscript or other content to any external AI or translation service. Its only network traffic is to the journal's
  own public website.
- **Stored data:**
  - the pages read and the quoted evidence, results and findings;
  - corrective actions, including the free-text responsible person;
  - English-only confirmations, with user name and time;
  - audit events, with user ID and user name;
  - generated reports.

  All of it is stored in the OJS database and in `{files_dir}/doajPreAssessment/`.

## Access control

- **Who:** only the root site administrator (user ID 1) and members of the journal's default Journal manager group
  have access. Sessions opened with **Login as** are always denied, and the audit log records the effective user and
  the impersonator.
- **Every request:** every request is authorized on the server. Operations are limited to a fixed list with fixed HTTP
  methods; other operations are refused.
- **CSRF:** every state-changing request is a POST that needs a valid OJS CSRF token.
- **Journal isolation:** every read and write is limited to the current journal. Database foreign keys include the
  journal ID. Tests confirm that a Journal Manager of one journal cannot read or change another journal's runs,
  findings, actions or reports, even with a valid token.

## Network protections of the website reader (SSRF and DNS)

The reader:
- accepts only `http` and `https` URLs on the journal's own scheme, host, port and path;
- rejects URLs with user information, unusual characters or numeric-IP tricks;
- resolves the host and refuses it if **any** address is private, loopback, link-local or reserved (IPv4, IPv6 and
  embedded IPv4). This also defeats DNS rebinding;
- connects only to the checked address;
- follows redirects one at a time, checking each against the same rules; redirects leaving the journal are refused;
- refuses to run when OJS is configured with an HTTP proxy;
- verifies TLS certificates;
- sends no cookies or authorization headers;
- caps response sizes, time and the number of pages.

These protections cannot be configured or disabled in a production installation.

## Reports and files

- **Storage:** reports are private files under `{files_dir}/doajPreAssessment/{journal}/reports/{run}/`, with modes
  0700 and 0600.
- **Downloads:**
  - Every download checks access again.
  - It requires a report record of the same journal.
  - It verifies the file's SHA-256 before sending. A changed file is refused (HTTP 409).
  - The file is sent as an attachment with `no-store`, `nosniff` and a sandboxing Content-Security-Policy.
- **HTML reports:** these contain no scripts or external resources.
- **Escaping:** all text taken from websites is escaped in pages and reports.

## Sealed assessment history

When a run completes, its results are recorded and the run is sealed in one database transaction, with a SHA-256
digest over the run and its records. Each run page and report shows whether the snapshot still verifies.

Completed runs cannot be changed through the plugin. A changed run shows **Snapshot verification FAILED**, and no
report is generated from it.

## Limitations of these controls

- **Database and filesystem operators:** they can read or alter data. The snapshot digest **detects** such changes but
  cannot prevent them. Restrict database and file access accordingly, and keep backups.
- **Report headers:** download protection relies on the web server passing the plugin's response headers unchanged.
  A proxy that rewrites headers could weaken it.
- **Network trust:** the reader depends on the server's DNS resolver, PHP curl and the CA certificate store.
- **Orphaned files:** if writing a report record fails after the file was written, an orphaned file can remain. The
  download route cannot serve it. Remove such files only during maintenance (see [Maintenance](maintenance.md)).
- **No automatic deletion:** the plugin keeps all its data. Retention and deletion are the responsibility of the OJS
  operator.

## Independent review

The code of version 0.10.0.0 was audited independently, read-only, by OpenAI Codex (model `gpt-5.5`) on 2026-10-14, and every later change up to version 1.0.0.0 was reviewed the same way (internal audit records, not published).
The auditor reported no Critical or High findings. One Low finding concerned the root-only site-administrator access;
it is intended behaviour and is documented above. The report is in the source repository:
the internal audit record (not published).

## Independence from DOAJ

The plugin is not affiliated with, reviewed or endorsed by DOAJ. Its criteria are approved by the plugin's product
owner, and its results are a pre-assessment, never a DOAJ decision. DOAJ text is quoted under CC BY-SA 4.0, with
attribution in `NOTICE.md`.
