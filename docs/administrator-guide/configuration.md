# Configuration

For **OJS site administrators**. Version 1.1.0.0 has **no plugin settings in the OJS interface**; the only plugin option is the administrator-only pre-production mode in `config.inc.php` (below). Its behaviour depends on the OJS and PHP
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
- a journal served only on a private or intranet address, except in the pre-production mode below.

## OJS configuration (`config.inc.php`)

| Setting | Requirement | Why |
|---|---|---|
| `[general] base_url` (and any per-journal `base_url[...]`) | The journal's **public** URL | The plugin assesses the homepage URL that OJS generates for the journal. It must be reachable from the server at a public address |
| `[general] disable_path_info` | `Off` | The website reader needs path-info URLs. With `On`, New Assessment shows "The website reader cannot run here." |
| `[proxy] http_proxy`, `https_proxy` | Not set | With a proxy, the reader refuses to run, because it cannot verify the real destination address through a proxy |
| `[files] files_dir` | Set, outside the web root, writable by the web server | The plugin stores private working files and reports under `{files_dir}/doajPreAssessment/` |

If your installation requires a proxy, the plugin cannot assess it. A journal served only on a private address can be
assessed only with the pre-production mode. Do not modify the plugin to work around these rules.

## Pre-production mode (private or loopback journal address)

Use this mode to check a journal website **before** it is installed on its public production server, for example a
staging copy reachable only at `https://localhost:8445` or on an intranet address. By default the website reader
rejects every private and loopback address (protection against server-side request forgery). Only the server
administrator can relax this, in `config.inc.php`; there is no setting in the OJS interface.

```ini
[doajPreAssessment]
; Allow the reader to read THIS journal's own website on a loopback/private address
preproduction_private_targets = On
; Optional: connect host:port of the journal URL to another ip:port. Use it when OJS generates its URLs with a port
; that does not exist inside the server or container (here: the Docker host port 8445 maps to port 443 inside).
preproduction_connect_to = "localhost:8445=127.0.0.1:443"
; Optional: accept a self-signed or otherwise unverifiable TLS certificate for this journal origin only
preproduction_insecure_tls = On
```

Rules:
- Only the scheme, host and port of the journal homepage (from `base_url`) are covered. Any other host, port or
  redirect target stays under the normal public-only rule.
- Allowed address classes: loopback, private (RFC 1918), IPv6 unique local and CGNAT. Link-local (cloud metadata),
  multicast, reserved and documentation addresses are never allowed.
- `preproduction_connect_to` accepts exactly one `host:port=ip:port` entry, for the journal's own host and port, and
  the target must be an allowed address. Any malformed value disables the whole mode.
- The URL and the `Host` header are not changed; only the network connection goes to the mapped address.
- Every run that used the mode is flagged (`dev_exception_used`), its coverage records `preProductionMode`, each
  destination is written once per run to the audit log (`preproduction_private_destination_allowed`), and the run
  page and every report show a **PRE-PRODUCTION MODE** notice. Results describe the non-public copy, not the public
  website: assess again after the website goes live.
- Switch the mode **off** on public production servers.


## PHP configuration

- **Time limit:** each assessment step requests up to 120 seconds with `set_time_limit`. A host that forbids raising
  the limit, or sets a stricter limit, may interrupt slow steps. Interrupted runs can be resumed.
- **Outbound HTTP/HTTPS** from the web server to the journal's public host must be allowed.
- **TLS:** HTTPS certificates are always verified (except for the journal origin when the pre-production mode sets
  `preproduction_insecure_tls`), so the server's CA store must be current.

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
