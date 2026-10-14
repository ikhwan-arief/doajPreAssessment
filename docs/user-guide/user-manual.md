# User Manual — DOAJ Pre-Assessment 0.10.0.0

This manual describes every user-facing function of the released build. For a first walkthrough see
[Quick start](quick-start.md). For status meanings see [Understanding results](understanding-results.md).

## 1. Who can use the plugin

| User | Access |
|---|---|
| Root site administrator (OJS user ID **1**) | Yes, in every journal where the plugin is enabled |
| Members of the journal's default **Journal manager** user group | Yes, in their own journal only |
| Other site administrators (user ID ≠ 1) | No (by design) |
| Journal editors, production editors, section editors, custom groups with the Manager role, reviewers, authors, readers, anonymous visitors | No |
| Any session opened with OJS **Login as** (impersonation) | No, whoever the users are. Sign out of "Login as" first |

The server checks access on every request. A denied request shows *"You are not authorized to use DOAJ
Pre-Assessment for this journal."* (HTTP 403), and authenticated denials are written to the plugin's audit log.

The "Responsible person or team" field of a corrective action is free text. Naming someone there gives them no access.

## 2. Scope of version 0.10.0.0

### The 24 active criteria

All 24 are **Must** criteria approved for this plugin by its product owner (rule set `doajpa-criteria-1.0.0`, dataset
1.0.0). The quoted requirement texts are excerpts from DOAJ's *Guide to applying*, *Licensing & copyright* and the
*Principles of Transparency and Best Practice*, under CC BY-SA 4.0 and CC BY 4.0, wording unchanged. They are not
reviewed or endorsed by DOAJ; see `NOTICE.md`.

| Criterion | Area | Requirement (quoted) |
|---|---|---|
| `guide.actively-publishing` | Activity and history | The journal must be actively publishing scholarly research |
| `guide.oa-statement` | Open access | The journal must display an open access statement indicating that it fulfils the DOAJ definition of open access. |
| `guide.disclosure-oa-policy` | Mandatory disclosures | Open access policy |
| `guide.disclosure-aims-scope` | Aims and scope | Aims and scope |
| `guide.disclosure-editorial-board` | Editorial board | Editorial board (including institutional affiliations of all members) |
| `guide.disclosure-author-instructions` | Author guidance | Instructions for authors |
| `guide.disclosure-peer-review` | Peer review | Editorial process (peer review) |
| `guide.disclosure-licensing` | Licence and copyright | Licensing terms |
| `guide.disclosure-copyright` | Licence and copyright | Copyright terms |
| `guide.disclosure-author-charges` | Charges | Author charges |
| `guide.disclosure-publisher` | Journal identity | Publisher information |
| `guide.publisher-name-address-link` | Journal identity | The journal must provide the name and address of the publisher, with a link to the publisher website, if available. |
| `guide.issn-displayed` | ISSN | The ISSN(s) must be displayed on the website. |
| `guide.editor-and-board` | Editorial board | A journal must have an editor and an editorial board. |
| `guide.board-listed` | Editorial board | The editorial board must be listed on the website. |
| `guide.board-names-affiliations` | Editorial board | The name and affiliation of all editors and board members must be included. |
| `guide.peer-review-type-stated` | Peer review | The type and details of the peer review process must be stated clearly on the website, including the process for special issues, if applicable. |
| `guide.licensing-terms-stated` | Licence and copyright | The licensing terms for use and re-use of the published content must be clearly stated on the website. |
| `guide.copyright-stated-separately` | Licence and copyright | The copyright terms applied to the published content must be clearly stated and separate from those applied to the website. |
| `guide.copyright-no-contradiction` | Licence and copyright | Copyright terms must not contradict the licensing terms or the terms of the open access policy. |
| `transparency.oa-open-licence` | Licence and copyright | Content designated as Open Access must use an open licence. |
| `copyright.cc-terms-not-adapted` | Licence and copyright | You must not add to or adapt the terms of a Creative Commons license. |
| `copyright.no-contradiction` | Licence and copyright | Copyright terms must not contradict the licensing terms or the terms of the journal's or publisher's open access policy. |
| `copyright.no-fair-use-only` | Licence and copyright | We do not accept free-to-read journals that restrict reuse of their content to "fair use" only, also known as 'Bronze journals'. |

### Active DOAJ Form Guide questions

| Question | Answered from |
|---|---|
| FQ-07 Languages in which the journal accepts manuscripts | The manuscript-language statement in the author guidelines |
| FQ-08 Subject keywords (up to six, in English) | Never answered automatically (an editorial choice). The aims-and-scope text is offered as the source |
| FQ-15 Link to Aims & Scope | The result of `guide.disclosure-aims-scope` |
| FQ-17 Link to Instructions for Authors | The result of `guide.disclosure-author-instructions` |

### Not assessed in this version

- **113 criteria are not evaluated.** These include all criteria on article-level open access, embargoes, archiving,
  fees and waivers, endogeny, metrics and indexing, ISSN registration, plagiarism screening and human-judgement
  criteria.
- **The other 16 Form Guide questions** are "Not yet assessed".
- **No external services:** no ISSN Portal or DOI lookup is made, and no index is classified as unreliable.

**A result from this plugin is never a complete DOAJ eligibility check.**

## 3. Pages

### Overview

Shows:
- the build status and the approved rule set;
- the disclaimer;
- the latest completed run, with its verified snapshot, its pages read and the status of its findings;
- any active or incomplete run;
- the current status of all findings;
- diagnostics: journal, plugin, OJS and PHP versions, and who confirmed the English-only statement.

### New Assessment

Starts a run.

**The page shows:**
- the scope of an assessment and the approved rule set;
- whether the PDF text tool `pdftotext` is available;
- the per-run English-only checkbox.

**Start assessment** is disabled while another run of the journal is active, or when the website reader cannot run
in this installation. In that case the page shows "The website reader cannot run here." with the reason.

### Run page (open from Assessment History or Overview)

Shows **Run details**:
- run number, status and who started it;
- the English-only confirmation;
- the rule set;
- the sealed snapshot (SHA-256 and whether it verifies);
- the previous completed run.

The other sections are described below.

#### Status and progress

| Run status | Meaning |
|---|---|
| Running | The website is being read, or the results are being recorded |
| Interrupted | A step failed. The reason is shown. Resume or cancel |
| Completed | Results recorded and the run sealed. It never changes afterwards |
| Cancelled | Ended by a user. Its records are kept, but it was never sealed |

**Progress** shows "Pages and files read" and "Waiting to be read".

Each step reads for about ten seconds and returns to the page. **Continue automatically** submits the next step by
itself; **Continue assessment** does one step. If several people or tabs continue the same run, the plugin runs one
step at a time ("Another step of this run is in progress. Try again in a moment.").

#### Interruptions and recovery

- **A failed step:** the run becomes **Interrupted** and nothing from the failed step is sealed. **Resume assessment**
  continues it:
  - pages already stored are not read again;
  - a page stored but not fully processed is listed as a coverage gap (`interrupted_not_reprocessed`).
- **A closed browser:** the run stays **Running**. Reopen it and continue.
- **Cancel assessment:** ends an active run. Its records stay, but it is not sealed.

#### Coverage and gaps

Lists:
- pages not retrieved, with their state: Not found, Inaccessible, Parse failed, Unsupported language or Ambiguous;
- limits reached;
- links not followed, by reason: `robots_disallow`, `excluded_backend_or_dynamic_path`, `other_host_or_port` or
  `not_fetched_limit_reached`;
- pages whose language was not verified as English;
- whether the PDF text tool was used.

#### Assessment framework and results

- **Machine results:** counts per status.
- **Criterion results:** for each criterion, the machine status, the rationale (with quotations and page addresses), the
  interpretation limit, the level and the DOAJ reference.
- **Findings recorded by this run:** the findings and their lifecycle status in this run.
- **Changes since the previous completed run:** pages new, unchanged, changed, not read again, or now not retrieved.

### Criteria & Findings

**Criteria:** each criterion definition used by the journal's runs, with its dataset and version. Approved criteria
are marked **Approved**. Criteria from older candidate or test runs are marked **Not approved**.

**Findings:** every finding of the journal, with:
- its **Original status**, from the run that first recorded it;
- its **Last recorded status**;
- its **Current status**.

**A finding page** shows:
- its identity: stable key, affected page or item, and issue type;
- **Status in each run**, with the evidence quote, page and DOAJ link (earlier entries never change);
- the status changes;
- the link **Add a corrective action for this finding**.

### Evidence

Choose a run to see:
- **Pages read**, with URL, media type, source state and language;
- **Evidence excerpts**: the exact quoted text, page, location, extraction method and retrieval time;
- **Coverage gaps**.

### DOAJ Form Guide

FQ-01 to FQ-20 from the latest completed run:
- the answer status: Supported, Not found, Needs verification or Not yet assessed;
- the website-supported answer, with its source page;
- the uncertainty.

Only FQ-07, FQ-08, FQ-15 and FQ-17 can be answered (see section 2). Before the first completed assessment, the page
shows "No completed assessment with the approved criteria yet. Complete an assessment to view evidence-supported Form Guide assistance. This
release covers FQ-07, FQ-08, FQ-15, FQ-17 only; the other questions are not assessed."

### Before You Apply

**Contents:** the corrections from one completed run (the latest, or another chosen with **Show**), grouped by the
website page to correct.

**Each correction shows:**
- the run's recommended correction;
- the DOAJ clause it relates to;
- the finding;
- its corrective actions.

**Other parts:**
- **Needs verification:** criteria the run could not establish.
- **Create a corrective action:** prefills an action for that finding.

### Corrective Actions

The journal's corrective action register.

**Each action has:**
- a recommendation;
- the page to change (an http or https URL);
- a responsible person or team (free text);
- a due date;
- a priority (High, Medium or Low);
- a status (Planned, In progress, Done, awaiting verification, or Cancelled);
- a completion note.

**Status:** the status describes the work only. *"Marking an action as done does not resolve a finding."*

**Concurrent edits:** if two people edit the same action, the second save is refused with "This action was changed
by someone else after you opened it…" and the current values are shown.

### Assessment History

All runs of the journal, newest first, with status, rule set and pages not read.

**Compare** shows two completed runs side by side, using each run's own record. Runs from different rule sets, such
as an old candidate run and an approved run, are marked as not directly comparable.

### Reports

**Generate a report** from a completed run whose sealed snapshot verifies, as HTML, PDF (A4) or DOCX (A4). All three
formats contain the same sections:
1. About this report;
2. Assessment run;
3. Method and coverage;
4. Results summary;
5. Criterion results;
6. Findings and history;
7. Before You Apply: corrections by page;
8. Corrective action register;
9. DOAJ Form Guide (FQ-01 to FQ-20);
10. Limitations;
11. References;
12. Disclaimer.

**Downloads:**
- Reports are stored privately on the server.
- They are downloaded through **Recorded report exports**.
- Each download checks your access again and verifies the stored file's SHA-256 first. A file changed on disk is never
  served.
- Each run keeps at most **30** reports. After that: *"This run already has the maximum number of generated reports
  (30). Download an existing report of this run."*

**PDF:** embeds the DejaVu Sans fonts, so accented, Greek and Cyrillic text is readable. Characters without a glyph,
for example CJK, are written as `[U+XXXX]`.

### Settings

Read-only status:
- the plugin version;
- the database schema level (**Complete** or the problems found);
- the approved rule set;
- "Editable settings": "No configurable settings are available in this release. Access and assessment behaviour are
  fixed by the plugin.";
- the English-only confirmations of this journal, with their statement fingerprint;
- the **Audit log**: the latest 100 events, which cannot be edited.

The plugin has no editable settings.

## 4. How the website is read

The reader starts at the journal's homepage. It follows links only within the **same scheme, host, port and journal
path**.

**It skips:**
- OJS backend and dynamic paths, such as login, submissions, management, API, search and OAI;
- pages that `robots.txt` disallows.

**It reads:**
- HTML pages;
- public PDF files, if `pdftotext` is available.

### Limits per run

| Limit | Value |
|---|---|
| Pages and files | 150 |
| Issues | 4 |
| Articles | 40 |
| Files (PDF) | 20 |
| Link depth | 6 |
| HTML page size | 3 MB |
| PDF size | 20 MB |
| Total reading time | 900 s |
| Pause between requests | 0.25 s |
| Connection timeout | 5 s |
| Request timeout | 20 s |

When a limit is reached, the run says so under **Limits reached**. Checks that need complete coverage, such as "no
content newer than 12 months", then give **Unverifiable** instead of a failure.

### Language of each page

The language is judged from the page's main content, not from menus or footers. A page counts as **verified English**
only when it has at least 40 words and an English stopword share of at least 0.20 that clearly exceeds any other
language. Short pages, such as a list of names, stay "undetermined".

Non-English, mixed or undetermined pages are never used as English evidence, and never cause a failure.

## 5. Supported workflow and limitations

- **Supported:**
  - one active run per journal;
  - any number of completed runs;
  - assessment of the journal's own public website only, without login.
- **Not supported:**
  - journals on private or intranet addresses;
  - sites behind an OJS HTTP proxy;
  - `disable_path_info = On`;
  - OJS 3.4 or 3.5.
- **English-only patterns:** unusual wording gives Unverifiable or For DOAJ review, never a pass.
- **Coverage:** a crawl reads a sample. It cannot prove anything about content it did not read.
- **No AI:** the plugin uses no AI and no translation, and it never reads unpublished submissions or editorial
  workflow data.
- **Advisory only:** every result is advisory. A human must verify results before a DOAJ application.

### Interface wording (corrected build)

Earlier builds of 0.10.0.0 (tags `v0.10.0.0-rc2` and `v0.10.0.0-rc2.1`) showed three outdated texts. They are corrected
in the current build (tag `v0.10.0.0-rc3`):

| Where | Text now shown |
|---|---|
| Plugin list (description) | "Evidence-based DOAJ pre-assessment for OJS journals: evaluates the journal's public website against 24 approved criteria (not a complete DOAJ eligibility check). Independent of DOAJ: …", followed by the English-only warning |
| Settings → Editable settings | "No configurable settings are available in this release. Access and assessment behaviour are fixed by the plugin." |
| DOAJ Form Guide before the first completed assessment | "No completed assessment with the approved criteria yet. Complete an assessment to view evidence-supported Form Guide assistance. This release covers FQ-07, FQ-08, FQ-15, FQ-17 only; the other questions are not assessed." |

On a run made before the approved criteria were installed (version 0.9 or earlier), the run page says "No approved DOAJ
criteria were applied in this run." The crawler identifies itself to journal websites as
`DOAJPreAssessment/0.10 (OJS plugin; journal self-assessment)`.
