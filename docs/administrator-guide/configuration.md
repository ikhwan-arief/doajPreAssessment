# Configuration

For **OJS site administrators**. Version 1.0.0.0 has **no plugin settings**. Its behaviour depends on the OJS and PHP
configuration below. The approved criteria, the crawl limits and the access rules are fixed in the release and cannot
be changed at run time.

## Supported deployment environments

| Component | Supported | Tested |
|---|---|---|
| OJS | 3.3.0-23 | Fresh installations and upgrade from 0.9.0.0 |
| PHP | 7.3 and 8.2 | 7.3.33 and 8.2.34 (Apache, MariaDB) |
| PHP extensions | curl, dom, mbstring | |
| Optional program | Poppler `pdftotext` at `/usr/bin/pdftotext` or `/usr/local/bin/pdftotext` | 20.09.0 and 25.03.0 |

**Not supported:**
- OJS 3.4 or 3.5;
- `disable_path_info = On`;
- an OJS HTTP proxy;
- a journal served only on a private or intranet address.

## OJS configuration (`config.inc.php`)

| Setting | Requirement | Why |
|---|---|---|
| `[general] base_url` (and any per-journal `base_url[...]`) | The journal's **public** URL | The plugin assesses the homepage URL that OJS generates for the journal. It must be reachable from the server at a public address |
| `[general] disable_path_info` | `Off` | The website reader needs path-info URLs. With `On`, New Assessment shows "The website reader cannot run here." |
| `[proxy] http_proxy`, `https_proxy` | Not set | With a proxy, the reader refuses to run, because it cannot verify the real destination address through a proxy |
| `[files] files_dir` | Set, outside the web root, writable by the web server | The plugin stores private working files and reports under `{files_dir}/doajPreAssessment/` |

If your installation requires a proxy, or serves the journal only on a private address, the plugin cannot assess it.
Do not modify the plugin to work around this.

## PHP configuration

- **Time limit:** each assessment step requests up to 120 seconds with `set_time_limit`. A host that forbids raising
  the limit, or sets a stricter limit, may interrupt slow steps. Interrupted runs can be resumed.
- **Outbound HTTP/HTTPS** from the web server to the journal's public host must be allowed.
- **TLS:** HTTPS certificates are always verified, so the server's CA store must be current.

## Authorization model

Access is checked on the server for every request:
1. the operation must be on the plugin's fixed list;
2. the access policy is applied;
3. the HTTP method must be allowed;
4. POST requests need a valid CSRF token;
5. the database schema must be complete.

| Identity | Decision |
|---|---|
| Root site administrator, user ID 1, holding the site-administrator role | Allowed |
| Member of the current journal's default **Journal manager** group | Allowed (own journal only) |
| Any other user, including other site administrators, journal editors and custom manager groups | Denied (HTTP 403) |
| Any session using OJS **Login as** | Denied |

There is no plugin setting to change this. Grant access by assigning a user to the journal's Journal manager group.
The backend menu entry and the plugin-list action appear only for allowed users, but the server check applies to every
request.

## Resource and crawler limits (fixed per run)

| Limit | Value |
|---|---|
| Pages and files | 150 |
| Issues | 4 |
| Articles | 40 |
| PDF files | 20 |
| Link depth | 6 |
| HTML response size | 3 MB |
| PDF size | 20 MB |
| Total reading time | 900 s |
| Pause between requests | 0.25 s |
| Connect / request timeout | 5 s / 20 s |
| `pdftotext` timeout per PDF | 30 s |
| Reports per run | 30 |
| Active runs per journal | 1 |

**What the reader skips:**
- other hosts and ports, and paths outside the journal;
- OJS backend and dynamic paths (for example `login`, `submission`, `management`, `api`, `search`, `oai`, and the
  plugin's own pages);
- pages that `robots.txt` disallows.

**What it sends:** no cookies and no credentials.

## Filesystem and database

**Filesystem:**
- **Plugin code:** `plugins/generic/doajPreAssessment/`. Readable by the web server, and writable only during
  installation or upgrade.
- **Private files:** `{files_dir}/doajPreAssessment/{journalId}/crawl/{runId}/` for crawl state and
  `{files_dir}/doajPreAssessment/{journalId}/reports/{runId}/` for reports. Directories are created with mode 0700 and
  files with 0600, for the web-server user. They must not be served by the web server.

**Database:** 13 InnoDB tables named `doaj_*` in the OJS database. They are created by the plugin's idempotent
migration and use foreign keys that include the journal ID. The OJS database user needs its normal rights; no extra
grants are required.

## Logs and diagnostics

- **Plugin Settings page:**
  - the database schema level (**Complete**, or the problems found);
  - the approved rule set;
  - the English-only confirmations;
  - the **Audit log** (latest 100 events; it cannot be edited).
- **Audit event types:**
  - access: `access_denied`, `acknowledge`;
  - runs: `run_created`, `run_status_changed`, `run_interrupted`, `run_completed`;
  - crawl: `crawl_started`, `crawl_finished`;
  - findings and reports: `finding_transition`, `report_export_recorded`, `report_export_downloaded`.
- **PHP error log:** entries start with `doajPreAssessment:`, for example `doajPreAssessment: run N step failed: …`, or
  messages saying the authorization check or the denial audit failed closed.
- **Overview → Diagnostics:** plugin, OJS and PHP versions.
