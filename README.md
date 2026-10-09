# DOAJ Pre-Assessment for OJS 3.3

## Project Overview

DOAJ Pre-Assessment is a native Open Journal Systems (OJS) 3.3 plugin designed to help journal managers evaluate and improve their journal's readiness before applying for indexing in the Directory of Open Access Journals (DOAJ).

The plugin performs evidence-based, deterministic assessments of publicly accessible journal website content and generates findings, corrective recommendations, improvement action plans, and assessment history.

**This is an independent pre-assessment tool. It is not an official DOAJ evaluation system and does not predict or guarantee acceptance into DOAJ.**

## Target Environment

- Platform: Open Journal Systems 3.3.x
- Primary target: OJS 3.3.0-23
- Plugin type: Generic Plugin
- Plugin directory: `doajPreAssessment`
- Interface language: English only
- Assessment technology: Deterministic, rule-based analysis
- AI integration: None
- Database: OJS database, using plugin-specific tables

## Core Requirements

### 1. Access Control

Only the following authenticated users may access the plugin:

- Root Administrator with User ID 1, subject to administrator-role verification.
- Journal Managers assigned to the current journal context.

All other editorial roles, reviewers, authors, and public users are denied access.

Authorization must be enforced server-side for every protected operation.

### 2. English-Only Restriction

The plugin supports only journals whose relevant public website content and publication policies are written in English.

A prominent compatibility warning and explicit acknowledgment must appear during installation or activation and before starting each assessment.

English-only is a limitation of this plugin, not a DOAJ application requirement.

### 3. Public Website Assessment

The plugin examines publicly accessible journal website information, including:

- Homepage and navigation
- Journal identity and publisher information
- Open Access policies
- Aims and Scope
- Editorial Board
- Peer Review Policy
- Publication Ethics
- Copyright and Licensing
- Author Guidelines and publication fees
- Custom and Static Pages
- Archives and published issues
- Article landing pages and publicly available PDFs
- Indexing and journal metric claims

The plugin must not inspect private submissions, reviewer assignments, editorial decisions, or internal peer-review workflows.

### 4. Deterministic Assessment

The assessment engine uses HTML parsing, PDF text extraction, structured metadata extraction, regular expressions, validated terminology patterns, calculations, and cross-page consistency checks.

It does not use AI, large language models, machine learning, or automatic translation.

Missing, ambiguous, contradictory, or inaccessible evidence must be recorded explicitly. The engine must not invent evidence or claim compliance without sufficient verification.

### 5. Assessment History

Every assessment run is stored persistently.

Each finding has a stable identifier so that its status can be tracked across consecutive assessments.

Supported finding lifecycle states include Open, Resolved, Reopened, and Needs Verification.

Completed assessment snapshots must not be overwritten by subsequent assessments.

### 6. Corrective Actions

Every identified gap should produce an evidence-based corrective recommendation.

Journal Managers coordinate corrective actions, assign responsibilities, track implementation, and re-assess the public website to confirm improvements.

### 7. Reporting

The planned outputs include:

- Pre-Assessment Report
- DOAJ Application Form Guide
- Before You Apply Checklist
- Evidence Register
- Corrective Action Plan
- Assessment History and Comparison
- HTML, PDF, and DOCX reports

## Official DOAJ References

Assessment findings must provide relevant cross-check links to:

- [DOAJ Guide to Applying](https://doaj.org/apply/guide/)
- [Principles of Transparency and Best Practice](https://doaj.org/apply/transparency/)
- [DOAJ Licensing and Copyright](https://doaj.org/apply/copyright-and-licensing/)

Criteria must be version-controlled and must not change automatically when an external website changes.

## Project Documentation

The following documents define the project requirements and implementation process:

1. `docs/stage01/DOAJ_PreAssessment_OJS33_Stage01_FINAL_v2.0.0.md`
2. `docs/stage02/DOAJ_PreAssessment_OJS33_Stage02_ClaudeCode_Codex_Prompts_v1.0.0.md`

Stage 01 is the authoritative requirements baseline.

Stage 02 defines the development workflow and technical implementation tasks.

## Development Workflow

The implementation is divided into nine substages:

- 02.01 Compatibility and Source Reconnaissance
- 02.02 Plugin Bootstrap and Lifecycle
- 02.03 Authentication and Authorization
- 02.04 Database and Immutable History
- 02.05 Backend Dashboard and UX
- 02.06 Public Website Crawler and Extractor
- 02.07 Assessment Framework
- 02.08 Installation Package
- 02.09 Testing and Validation

Each substage must be reviewed and tested before proceeding to the next.

## Development Tools

- Claude Code: Primary implementation
- Codex: Independent code review and validation
- Git: Version control
- VS Code: Development environment

The OJS 3.3.0-23 source code is the technical reference for compatibility.

## Testing Policy

A test may be reported as PASS only when it has actually been executed successfully.

Use the following status labels:

- PASS
- FAIL
- NOT RUN
- BLOCKED

Record test commands, evidence, limitations, and unresolved findings.

Do not claim installation compatibility until it has been verified on a running OJS 3.3.0-23 environment.

## Project Status

- Stage 01: Final design baseline v2.0.0
- Stage 02: Development planned
- Native plugin implementation: Not yet verified
- Production readiness: Not established

## License and Copyright

Licensing and redistribution terms have not yet been finalized. Do not assume an open-source license or permission for redistribution.

## Disclaimer

DOAJ Pre-Assessment is an independent journal self-evaluation tool. Its findings are advisory and based on publicly available evidence. Only DOAJ is authorized to make official decisions on journal applications.
