# Quick Start: Your First Assessment

For a **Journal Manager** of a journal where the plugin is installed and enabled (see
[Installation](installation.md)). It takes about 15–30 minutes, most of it the website reading.

**Before you start:** the journal's public website, especially its policy pages, should be in English. The plugin
reads only what any visitor can see, without logging in.

## 1. Open the plugin

In the journal's backend, choose **DOAJ Pre-Assessment** in the left menu. The plugin's sections are listed at the top
of every page:
- Overview
- New Assessment
- Criteria & Findings
- Evidence
- DOAJ Form Guide
- Before You Apply
- Corrective Actions
- Assessment History
- Reports
- Settings

Under the sections you see **"Independent of DOAJ."** and the plugin version.

## 2. Accept the English-language limitation

On your first visit in this journal you see **"DOAJ Pre-Assessment: English-only acknowledgement"**. Tick *"I
understand and confirm that this journal's public assessment content is in English."* and choose **Confirm and
continue**.

This is needed once per journal. It does not make any page count as English: the plugin still checks the language of
every page it reads.

## 3. Start an assessment

1. Open **New Assessment**. The page shows:
   - "Pre-assessment, not a DOAJ decision." with the rule set `doajpa-criteria-1.0.0`;
   - what an assessment does;
   - whether the PDF text tool is available.
2. Tick the English-only statement again. Every assessment needs its own confirmation, recorded with your name and the
   time.
3. Choose **Start assessment**.

Only one assessment per journal can be active at a time.

## 4. Follow the reading and the evaluation

The run page opens with "The assessment started. The website is read in steps of about ten seconds."

- **Continuing the run:** while **Continue automatically** is ticked, the page continues by itself. Otherwise choose
  **Continue assessment** for each step.
- **Progress:**
  - "Pages and files read" and "Waiting to be read" grow and shrink as the site is read;
  - "Reading is complete; the next step records the results and seals the run." means the reading has finished.
- **The final step** evaluates the 24 approved criteria and the 4 Form Guide questions, then seals the run. You see
  "The website reading is complete and the run was sealed." and **Snapshot verified**.

If a step fails, the run shows **Interrupted.** with the reason. Remove the cause if you can, then choose **Resume
assessment** (see [Troubleshooting](troubleshooting.md#assessment-remains-interrupted)). If you close the browser,
the run stays **Running**: reopen it from **Assessment History** and continue.

## 5. Review the findings

On the run page:
- **"Pre-assessment, not a DOAJ decision."**
- **Machine results:** counts per status.
- **Criterion results:** one row per criterion, with the machine status, the rationale, the interpretation limit and
  the evidence.
- **Findings recorded by this run:** each deficiency becomes a finding with a stable ID, F-*n*.

**Criteria & Findings** lists all findings of the journal with their original, last recorded and current status. Open
a finding to see the status it had in each run. [Understanding results](understanding-results.md) explains every
status.

## 6. Open the evidence

**Evidence** (or "Show the pages read and the evidence of this run" on the run page) lists:
- **Pages read:** URL, state and language;
- **Evidence excerpts:** the exact quoted text with its page, location and extraction method;
- **Coverage gaps:** pages that could not be read, and why.

Follow the URL to check a quotation on the live site. A result is only as good as the pages that were read.

## 7. Read Before You Apply

**Before You Apply** lists the corrections from the latest completed run, grouped by the website page to correct.

**Each task shows:**
- the recommended correction;
- the DOAJ clause it relates to;
- the finding;
- the corrective actions already recorded.

**"Needs verification":** criteria the plugin could not establish from the pages read. These are not deficiencies;
check them yourself.

To act on a task, choose **Create a corrective action**. It opens a prefilled action. Set:
- **Responsible person or team** (free text);
- **Due date**;
- **Priority**.

Then save it. Actions are listed under **Corrective Actions**. Marking an action as done does **not** resolve the
finding: only a new assessment can.

## 8. Use the DOAJ Form Guide

**DOAJ Form Guide** lists the application form questions FQ-01 to FQ-20.

**Answered (approved mapping):** FQ-07, FQ-08, FQ-15 and FQ-17.
- Where possible, a supported answer quotes verified English text from your site, with its address.
- FQ-08 (subject keywords) is never answered automatically. It points to your aims-and-scope text.

**All other questions** show "Not yet assessed" because their mapping is not approved in this version.

Confirm every answer yourself before using it in a DOAJ application.

## 9. Generate reports

1. Open **Reports**.
2. Choose the completed run and a format: **HTML (web page)**, **PDF (A4)** or **DOCX (Word, A4)**.
3. Choose **Generate report**.

The report appears under "Recorded report exports" and downloads as a file.

**Contents:** all three formats carry the same content, including the disclaimers, the method, the sources inspected
and not inspected, the results with evidence, Before You Apply, the corrective actions, the Form Guide and the
limitations.

**Storage:** reports are private files. Only authorized users of this journal can download them. Each run keeps at most
30 reports.

## 10. Correct the journal website

Make the corrections on the public website, for example in **Settings → Journal** or in the journal's static pages.
Record what you changed in the corrective action ("Completion note"), and set its status to **Done, awaiting
verification**.

## 11. Run a reassessment

Start a new assessment (step 3). It needs its own English-only confirmation. The new run reads the website again and
evaluates the same 24 criteria.

## 12. Compare and review resolved findings

Open **Assessment History**, choose two completed runs (**Earlier run**, **Later run**) and choose **Compare**. You
can also use **Compare with run** on a run page.

- **Resolved (verified):** a finding whose criterion now passes on the new public evidence. It keeps its ID.
- **Still open:** findings that remain.
- **Needs verification:** findings that could not be checked again, for example because a page could not be read.
- **Open:** new findings.

Earlier runs never change, and each run page still shows **Snapshot verified**.
