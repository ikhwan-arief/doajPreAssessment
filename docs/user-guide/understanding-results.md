# Understanding Results

Every result is a **pre-assessment of the public website pages that one run read**. It is not a DOAJ decision. A
pass shows that the plugin found the required evidence on the pages it read. It does not certify that DOAJ will accept
the journal: DOAJ applies all of its criteria, including the 113 not assessed in this version and every human
judgement, to the whole journal.

## 1. Criterion outcomes (machine status)

| Status | Meaning in this plugin |
|---|---|
| **Pass** | The requirement is supported by the evidence the run read: a verbatim quotation from a page verified as English, or the absence of any non-conforming statement on all relevant pages (see section 2). A pass whose quotation cannot be matched again in the stored evidence is automatically **downgraded to Unverifiable** |
| **Fail** | A required statement is missing from the relevant verified pages that were all read, or a non-conforming statement was found. The result names the pages searched or quotes the offending text, and gives a recommended correction with the DOAJ clause |
| **Warning** | Used for recommended (R) or conditional "should" requirements, which never fail. All 24 criteria active in 0.10.0.0 are Must criteria, so their deficiencies appear as Fail or Contradiction |
| **Contradiction** | Two statements on the site conflict, for example a Creative Commons licence and "All rights reserved". Both are quoted with their pages |
| **Unverifiable** | The plugin could not establish the requirement either way. Typical reasons: a relevant page could not be read, its language was not verified as English, the crawl stopped at a limit, or the requirement needs an external registry. **Unverifiable is never a deficiency.** Check the listed pages yourself or run again |
| **Not applicable** | The requirement depends on a condition that the website shows does not apply, for example the Creative Commons terms check when no Creative Commons licence is used. When a relevant page is unread, the result is Unverifiable instead |
| **For DOAJ review** | Requirements that need human judgement. The plugin extracts the text but never decides. None of the 24 active criteria is of this kind |

### Form Guide answers

| Status | Meaning |
|---|---|
| **Supported** | The answer is backed by verified English text from the site, quoted with its address |
| **Not found** | The relevant verified pages were read and the information is not there |
| **Needs verification** | The plugin found something but cannot confirm it; check it yourself |
| **Not yet assessed** | Not answered: the question's mapping is not approved in this version (16 questions), or it is never answered automatically (FQ-08 offers the source only) |

### Finding lifecycle (status of a finding across runs)

| Status | Meaning |
|---|---|
| **Open** | First recorded in this run |
| **Still open** | Recorded again in a later run |
| **Needs verification** | A later run could not check it again, for example because the page was not readable. It is **never** automatically resolved |
| **Resolved (verified)** | A later run's result for the criterion is Pass on new public evidence covering this finding. Only a new assessment can resolve a finding; marking a corrective action as done cannot |
| **Reopened** | The problem was resolved earlier and has appeared again |
| **Superseded** | No longer tracked: the criterion changed incompatibly (a new lineage), or the page or item it concerned no longer exists |

Each run's own record of a finding (Detected, Verified corrected, Not established) never changes afterwards. The
**Current status** is the latest of them.

## 2. Kinds of evidence

| Kind | What it is | How it appears |
|---|---|---|
| **Quoted published statement** | Text copied verbatim from a page that was verified as English | The rationale quotes it; Evidence lists the excerpt with its URL and location |
| **Direct observation** | A fact of the reading itself, such as which pages exist or were retrieved | The rationale states the observation and the pages concerned |
| **Absence on inspected pages** | No non-conforming statement on **all** relevant pages, all read as verified English, with the crawl complete. Used by `copyright.cc-terms-not-adapted` and `copyright.no-fair-use-only` | "No … was found on the pages read. Pages searched: *n*." It says nothing about pages that were not read |
| **Language-neutral factual observation** | Names, affiliations, dates, ISSNs or counts read from a page too short for a language verdict, only on a site whose policy pages are verified English (ADR-013, safeguards S1–S7) | Labelled "[Language-neutral facts (ADR-013): …]". Such a result **never resolves a finding** and should be checked by a person |
| **Sampled result** | A result based on the part of the site that was read, for example the latest dated issues for `guide.actively-publishing` (at most 4 issues and 40 articles per run) | The rationale names the date or items found and the reference date. When the archive was not read completely, a negative result becomes Unverifiable |
| **Finding requiring human verification** | Any Unverifiable result, any "Needs verification" finding or Form Guide answer, and any language-neutral result | Listed under "Needs verification" on Before You Apply; check it on the live site |

The **interpretation limit** shown with each result states what the result cannot establish and when the plugin
abstains.

## 3. Examples (illustrative)

These examples are illustrative. They follow the scenario used in the plugin's end-to-end test.

- **Fail:** the About page shows "ISSN: 2049-3631". The check digit is invalid, so `guide.issn-displayed` fails. The
  rationale quotes the text and its page and recommends: "Display the ISSN exactly as registered at issn.org."
- **Contradiction:** the same page says "Articles are licensed under CC BY 4.0. All rights reserved by the publisher."
  `guide.copyright-no-contradiction` reports a contradiction and quotes both statements.
- **Resolved (verified):** after the page is corrected to "Online ISSN: 2049-3630", the next run passes
  `guide.issn-displayed` on the new quotation. The comparison shows the same finding F-*n* as "Resolved (verified)".
- **Unverifiable:** the editorial board page lists only names, so it is too short for a language verdict. If the
  site's policy pages are not verified English, `guide.board-names-affiliations` is Unverifiable rather than Fail.

## 4. Why a pass is not a DOAJ approval

- **Partial scope:** only 24 criteria and 4 Form Guide questions are assessed.
- **Partial reading:** the reading is limited and may not cover every page, issue or article.
- **No registries:** no external register is consulted. The ISSN is not checked against the ISSN Portal, DOIs are not
  resolved, and indexing claims are not checked.
- **Wording:** deterministic English patterns can miss unusual wording. Such cases give Unverifiable, never a pass.
- **Judgement:** DOAJ also judges quality, ethics and editorial practice, which this plugin never decides.
- **Independence:** the plugin is not affiliated with, reviewed or endorsed by DOAJ.
