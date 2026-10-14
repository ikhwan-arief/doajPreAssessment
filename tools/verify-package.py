#!/usr/bin/env python3
"""Verify a doajPreAssessment installation archive (Stage 02.08). Reads the archive only; never extracts it to disk.

Checks: gzip/tar integrity; single root doajPreAssessment/; no absolute, parent ('..'), backslash or control-character
paths; only regular files and directories (no symlinks, hard links, devices); modes 0644/0755, owner 0:0, one mtime;
only the runtime file set and allowed extensions; no development-only code (tests/, tools/, docs/, the dev exception,
fixture rules), no executables (ELF, Mach-O, PE, shebang scripts, a pdftotext binary), no archives, dumps, keys or test
credentials; version.xml and plugin registration metadata; install.xml/upgrade.xml well formed and without a version
attribute; MANIFEST.sha256 complete and correct; every template and locale key referenced exists; README states the
English-only warning and the optional, unbundled pdftotext; release notes state the release (approved criteria only,
candidate profile disabled); the approved rule set and dataset are the ones written by tools/apply-approval.py from a
signed, non-synthetic approval, and the candidate dataset is packaged as proposed and switched off; licence (decision D7,
2026-10-12): LICENSE is the verbatim GNU GPL v3 text, NOTICE.md names the copyright holder and GPL-3.0-or-later and
licenses the DOAJ-derived datasets separately (CC BY-SA 4.0) with their attribution, every source file carries its SPDX
line, and results show the attribution.

Usage: python3 tools/verify-package.py dist/doajPreAssessment-<release>.tar.gz [--json out.json]
Exit code 0 only when every check passes.
"""
import argparse, gzip, hashlib, io, json, os, re, sys, tarfile
import xml.etree.ElementTree as ET

TOP = "doajPreAssessment"
TOP_LEVEL = {"index.php", "version.xml", "install.xml", "upgrade.xml", "DoajPreAssessmentPlugin.inc.php", "classes", "pages",
             "templates", "css", "locale", "README.md", "CHANGELOG.md", "RELEASE-NOTES.md", "MANIFEST.sha256", "LICENSE", "NOTICE.md"}
REQUIRED = ["index.php", "version.xml", "install.xml", "upgrade.xml", "DoajPreAssessmentPlugin.inc.php", "README.md",
            "CHANGELOG.md", "RELEASE-NOTES.md", "MANIFEST.sha256", "LICENSE", "NOTICE.md", "locale/en_US/locale.po", "css/doajpa.css",
            "pages/DoajPreAssessmentHandler.inc.php", "classes/migration/DoajPaSchemaMigration.inc.php",
            "classes/framework/DoajPaAssessmentOrchestrator.inc.php", "classes/services/DoajPaCrawlService.inc.php"]
EXTENSIONS = (".php", ".tpl", ".xml", ".po", ".css", ".md", ".sha256")
FORBIDDEN_PATHS = re.compile(r"(^|/)(tests|tools|docs|dist|build|vendor|node_modules|\.git[^/]*|\.vscode|\.idea)(/|$)|"
                             r"DevPrivateAllowance|TestFixtureRules|\.DS_Store|~$|\.(orig|rej|bak|swp|sql|dump|env|zip|tar|tgz|gz|phar|sh|py|exe|so|dylib)$|"
                             r"(^|/)pdftotext", re.I)
FORBIDDEN_CONTENT = [rb"-----BEGIN [A-Z ]*PRIVATE KEY"]
SWITCH = b"I-UNDERSTAND-THIS-IS-A-TEST-STACK"  # the loader's comparison constant; allowed in the loader only
WARNING = ("ENGLISH-ONLY JOURNAL CONTENT. This plugin evaluates only publicly accessible English-language journal websites. "
           "It uses deterministic rules, not Artificial Intelligence or automatic translation.")
# Third-party assets: the DejaVu Sans fonts for readable Unicode PDF reports (Stage 05), unmodified (Debian fonts-dejavu-core 2.37-8)
FONTS = {"classes/reports/fonts/DejaVuSans.ttf": "57f73e11f51999432bf7ab22ce55b6f945d5eca1bf824404cfa9ec2e3718c84e",
         "classes/reports/fonts/DejaVuSans-Bold.ttf": "a4c5bc453ca281d90ea079e596da7ae0dfeb5777497c29ec254e76d97ff6f890"}
FONT_LICENCE = "classes/reports/fonts/LICENSE-DejaVu.txt"
GPL3_SHA256 = "3972dc9744f6499f0f9b2dbf76696f2ae7ad8af9b23dde66d6af86c9dfb36986"  # FSF text of GPL v3, 29 June 2007
COPYRIGHT = "Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>"
DATASET = "classes/rules/data/DoajPaCandidateDataset.inc.php"
APPROVED = "classes/rules/data/DoajPaApprovedDataset.inc.php"
SYNTHETIC = b"SYNTHETIC-TEST-NOT-AN-APPROVAL"
RELEASE_PHRASE = "Production release 1.0.0.0: approved criteria only; the candidate profile is disabled"
results = []


def check(cid, name, ok, evidence=""):
    results.append({"id": cid, "name": name, "result": "PASS" if ok else "FAIL", "evidence": str(evidence)[:600]})
    print(("PASS" if ok else "FAIL"), cid, name, "|", str(evidence)[:200])


ap = argparse.ArgumentParser()
ap.add_argument("archive")
ap.add_argument("--json")
opts = ap.parse_args()
path = os.path.abspath(opts.archive)
data = open(path, "rb").read()
sha = hashlib.sha256(data).hexdigest()
print(f"archive {os.path.basename(path)} sha256 {sha} bytes {len(data)}")

# ---------------------------------------------------------------- integrity and structure
try:
    raw = gzip.decompress(data)
    tar = tarfile.open(fileobj=io.BytesIO(raw), mode="r:")
    members = tar.getmembers()
    ok = True
except Exception as e:  # noqa: BLE001
    members, ok = [], False
    check("A01", "gzip and tar integrity", False, e)
    sys.exit(1)
side = path + ".sha256"
side_ok = os.path.exists(side) and open(side).read().split()[:2] == [sha, os.path.basename(path)]
check("A01", "gzip and tar integrity; archive hash matches the .sha256 file next to it", ok and side_ok,
      f"{len(members)} entries; .sha256 {'matches' if side_ok else 'missing or different'}")
m = re.fullmatch(r"doajPreAssessment-(\d+)\.(\d+)\.(\d+)\.(\d+)\.tar\.gz", os.path.basename(path))
check("A02", "Archive name doajPreAssessment-<release>.tar.gz (OJS takes the plugin directory from the name)", m is not None,
      os.path.basename(path))
names = [x.name for x in members]
bad_paths = [n for n in names if n.startswith("/") or "\\" in n or any(p in ("..", ".") for p in n.split("/"))
             or re.search(r"[\x00-\x1f\x7f]", n) or not (n == TOP or n.startswith(TOP + "/"))]
check("A03", "Single root doajPreAssessment/; no absolute, '..', '.', backslash or control-character paths", not bad_paths
      and names[0] == TOP and len(set(names)) == len(names), bad_paths[:5] or f"root={names[0]}")
types = [x.name for x in members if not (x.isfile() or x.isdir())]
check("A04", "Only regular files and directories (no symlinks, hard links, devices, FIFOs)", not types, types[:5])
modes = [(x.name, oct(x.mode)) for x in members if (x.isdir() and x.mode != 0o755) or (x.isfile() and x.mode != 0o644)]
owners = {(x.uid, x.gid, x.uname, x.gname) for x in members}
mtimes = {x.mtime for x in members}
check("A05", "Modes 0755 (directories) / 0644 (files), owner root 0:0, one fixed mtime (reproducible)",
      not modes and owners == {(0, 0, "root", "root")} and len(mtimes) == 1, modes[:5] or f"owners={owners} mtimes={sorted(mtimes)}")
files = {x.name[len(TOP) + 1:]: tar.extractfile(x).read() for x in members if x.isfile()}
top = {n.split("/")[0] for n in files}
check("A06", "Only the runtime file set at the top level, and every required file present",
      top <= TOP_LEVEL and all(r in files for r in REQUIRED), {"unexpected": sorted(top - TOP_LEVEL), "missing": [r for r in REQUIRED if r not in files]})
ext = [n for n in files if not n.endswith(EXTENSIONS) and n != "LICENSE" and n not in FONTS and n != FONT_LICENCE]
check("A07", f"Allowed file types only ({', '.join(EXTENSIONS)}, plus LICENSE and the two pinned report fonts with their licence)", not ext, ext[:5])

# ---------------------------------------------------------------- development code, binaries, secrets
forbidden = [n for n in files if FORBIDDEN_PATHS.search(n)]
check("D01", "No development-only paths (tests/, tools/, docs/, dev exception, fixture rules, editor/OS files, dumps, archives)",
      not forbidden, forbidden[:5])
magic = [n for n, b in files.items() if b[:4] in (b"\x7fELF", b"\xcf\xfa\xed\xfe", b"\xce\xfa\xed\xfe", b"\xca\xfe\xba\xbe") or b[:2] == b"MZ"
         or b[:2] == b"#!" or b[:2] == b"\x1f\x8b" or b[:4] == b"PK\x03\x04"]
check("D02", "No executables or embedded archives (ELF, Mach-O, PE, shebang scripts, gzip, zip): pdftotext is NOT bundled",
      not magic and not any("pdftotext" in os.path.basename(n).lower() for n in files), magic[:5])
secrets = sorted({(n, p.decode()) for n, b in files.items() for p in FORBIDDEN_CONTENT if re.search(p, b)})
switch = sorted(n for n, b in files.items() if SWITCH in b)
check("D03", "No private keys or test credentials; the development switch value appears only as the loader's comparison constant",
      not secrets and switch == ["classes/crawler/DoajPaAddressGuard.inc.php"], secrets[:5] or switch)
docs_env = [n for n in ("README.md", "CHANGELOG.md", "RELEASE-NOTES.md") if b"DOAJ_PA_DEV" in files.get(n, b"")]
check("D04", "Packaged documentation does not advertise the development-exception variables", not docs_env, docs_env)
php_dev = sorted(n for n, b in files.items() if n.endswith(".php") and re.search(rb"tests/support|ForTests\s*\(", b))
check("D05", "Only the dev-allowance loader references tests/support, and only the orchestrator defines the CLI-only test entry points",
      php_dev == ["classes/crawler/DoajPaAddressGuard.inc.php", "classes/framework/DoajPaAssessmentOrchestrator.inc.php"], php_dev)
vendor = [n for n in files if re.search(r"(^|/)(composer\.(json|lock)|package(-lock)?\.json)$", n)]
check("D06", "No bundled third-party dependencies (no vendor directory, no composer or npm manifests)", not vendor, vendor)
licence = sorted(n for n in files if re.search(r"(^|/)(LICEN[CS]E|COPYING)(\.|$)", n, re.I))
notice = " ".join(files.get("NOTICE.md", b"").decode().split())
check("D07", "Licence (D7): LICENSE is the verbatim GNU GPL v3 text; NOTICE.md: copyright holder, GPL-3.0-or-later, separate CC BY-SA 4.0 licence and attribution of the DOAJ-derived dataset; no other licence file",
      licence == ["LICENSE"] and hashlib.sha256(files.get("LICENSE", b"")).hexdigest() == GPL3_SHA256
      and all(t in notice for t in ("Copyright © 2026 Ikhwan Arief <ikhwan@unand.ac.id>", "either version 3 of the License, or (at your option) any later version", "GPL-3.0-or-later",
                                    "classes/rules/data/DoajPaCandidateDataset.inc.php", APPROVED, "CC BY-SA 4.0", "https://creativecommons.org/licenses/by-sa/4.0/",
                                    "COPE, DOAJ, OASPA, WAME", "https://creativecommons.org/licenses/by/4.0/", "Changes made", "No endorsement")),
      {"licenceFiles": licence, "licenceSha256": hashlib.sha256(files.get("LICENSE", b"")).hexdigest()[:16]})
SOURCE_EXT = (".php", ".tpl", ".xml", ".po", ".css")
unlabelled = sorted(n for n, b in files.items() if n.endswith(SOURCE_EXT) and n not in (DATASET, APPROVED)
                    and not (COPYRIGHT.encode() in b and b"SPDX-License-Identifier: GPL-3.0-or-later" in b))
ds_ok = all(b"SPDX-License-Identifier: CC-BY-SA-4.0" in ds and b"GPL" not in ds and b"Full attribution: NOTICE.md" in ds for ds in (files.get(DATASET, b""), files.get(APPROVED, b"")))
fonts_found = {n: hashlib.sha256(b).hexdigest() for n, b in files.items() if re.search(r"\.(ttf|otf|woff2?)$", n, re.I)}
font_lic = " ".join(files.get(FONT_LICENCE, b"").decode(errors="replace").split())
check("D09", "Third-party assets: exactly the DejaVu Sans regular and bold fonts (pinned SHA-256, unmodified) with their licence (Bitstream Vera and Arev, verbatim); attributed in NOTICE.md",
      fonts_found == FONTS and "Bitstream Vera Fonts Copyright" in font_lic and "Arev Fonts Copyright" in font_lic
      and "The Font Software may be sold as part of a larger software package" in font_lic and "DejaVu Sans" in notice and "LICENSE-DejaVu.txt" in notice,
      {"fonts": {k: v[:12] for k, v in fonts_found.items()}})
check("D08", "Every packaged source file carries the copyright line and SPDX GPL-3.0-or-later; the DOAJ-derived dataset carries CC-BY-SA-4.0 and its attribution instead",
      not unlabelled and ds_ok, {"unlabelled": unlabelled[:5], "dataset": ds_ok})

# ---------------------------------------------------------------- metadata
try:
    v = ET.fromstring(files["version.xml"])
    meta = {k: (v.findtext(k) or "").strip() for k in ("application", "type", "release", "date", "lazy-load", "class")}
except Exception as e:  # noqa: BLE001
    meta = {"error": str(e)}
rel = meta.get("release", "")
check("M01", "version.xml: application doajPreAssessment, type plugins.generic, class DoajPreAssessmentPlugin, lazy-load 1",
      meta.get("application") == TOP and meta.get("type") == "plugins.generic" and meta.get("class") == "DoajPreAssessmentPlugin"
      and meta.get("lazy-load") == "1" and re.fullmatch(r"\d{4}-\d\d-\d\d", meta.get("date", "")), meta)
notes_m02 = " ".join(files.get("RELEASE-NOTES.md", b"").decode(errors="replace").split())
check("M02", "Release matches the archive name; a release of major version 1 or later states the product-owner acceptance in its release notes "
      "(release policy: never 1.0 before final acceptance)",
      m is not None and rel == ".".join(m.groups()) and (rel.split(".")[0] == "0" or re.search(r"Product-owner acceptance:\*{0,2} \d{4}-\d\d-\d\d", notes_m02) is not None), rel)
idx = files.get("index.php", b"")
cls = files.get("DoajPreAssessmentPlugin.inc.php", b"")
check("M03", "Registration: index.php returns the plugin; the class extends GenericPlugin and loads the migration",
      b"return new DoajPreAssessmentPlugin();" in idx and re.search(rb"class DoajPreAssessmentPlugin extends GenericPlugin", cls) is not None
      and b"getInstallMigration" in cls)
xml_ok = {}
for x in ("install.xml", "upgrade.xml"):
    try:
        root = ET.fromstring(files[x])
        xml_ok[x] = (root.tag, root.get("version"), [c.get("class") for c in root.iter("migration")])
    except Exception as e:  # noqa: BLE001
        xml_ok[x] = ("error", str(e), [])
check("M04", "install.xml and upgrade.xml well formed, no version attribute (C45/C46), run the plugin migration",
      all(t == "install" and ver is None and any("DoajPaSchemaMigration" in (c or "") for c in cl) for t, ver, cl in xml_ok.values()), xml_ok)
php_open = [n for n, b in files.items() if n.endswith(".php") and not b.startswith(b"<?php")]
check("M05", "Every PHP file starts with '<?php' (no output before headers)", not php_open, php_open[:5])

# ---------------------------------------------------------------- manifest and references
lines = files.get("MANIFEST.sha256", b"").decode().splitlines()
listed = {}
for line in lines:
    h, _, n = line.partition("  ")
    listed[n] = h
expected = {f"{TOP}/{n}": hashlib.sha256(b).hexdigest() for n, b in files.items() if n != "MANIFEST.sha256"}
check("I01", "MANIFEST.sha256 lists every packaged file exactly once with the correct SHA-256",
      listed == expected, {"missing": sorted(set(expected) - set(listed))[:3], "extra": sorted(set(listed) - set(expected))[:3],
                            "wrong": [k for k in expected if k in listed and listed[k] != expected[k]][:3], "files": len(expected)})
src = b"".join(b for n, b in files.items() if n.endswith(".php"))
tpls = set(re.findall(rb"'([A-Za-z][A-Za-z/]*\.tpl)'", src))  # every template name the PHP code passes as a literal
tpl_missing = sorted(t.decode() for t in tpls if "templates/" + t.decode() not in files)
unused = sorted(n for n in files if n.startswith("templates/") and n[len("templates/"):].encode() not in tpls
                and n[len("templates/"):].encode() not in b"".join(b for k, b in files.items() if k.endswith(".tpl")))
check("I02", "Every template the PHP code renders exists in the package (and every packaged template is used)",
      not tpl_missing and not unused and len(tpls) >= 15, tpl_missing or unused or f"{len(tpls)} templates")
po = files.get("locale/en_US/locale.po", b"").decode()
have = set(re.findall(r'^msgid "([^"]+)"', po, re.M))
used = set()
for n, b in files.items():
    if n.endswith(".tpl"):
        used |= set(re.findall(r'translate key="(plugins\.generic\.doajPreAssessment\.[A-Za-z0-9_.]+)"', b.decode()))
missing_keys = sorted(used - have)
check("I03", "Every static locale key used by a template exists in locale/en_US/locale.po (English only)",
      not missing_keys and [n for n in files if n.startswith("locale/")] == ["locale/en_US/locale.po"], missing_keys[:5])

# ---------------------------------------------------------------- documentation in the package
readme = " ".join(files.get("README.md", b"").decode().replace("**", "").split())  # line breaks and bold markers do not matter
notes = " ".join(files.get("RELEASE-NOTES.md", b"").decode().split())
check("R01", "README: the English-only warning (verbatim), install/upgrade/rollback/uninstall instructions for OJS 3.3.0-23",
      WARNING in readme and all(h in readme for h in ("## Installation", "### Upgrade", "### Rollback", "### Uninstall", "OJS 3.3.0-23")))
check("R02", "README: pdftotext is an optional external prerequisite, NOT bundled, with its unavailable behaviour",
      "pdftotext" in readme and "not included in the plugin" in readme and "pdf_extractor_unavailable" in readme)
check("R03", f"Release notes say '{RELEASE_PHRASE}', list what is not available in {rel} and state the independent security audit status",
      RELEASE_PHRASE in notes and rel in notes and "## Not available in this release" in notes and "Independent security audit" in notes)

# ---------------------------------------------------------------- candidate criteria (Stage 03)
schema = files.get("classes/persistence/DoajPaSchema.inc.php", b"")
registry = files.get("classes/framework/DoajPaRuleRegistry.inc.php", b"")
dataset = files.get("classes/rules/data/DoajPaCandidateDataset.inc.php", b"")
approved = files.get(APPROVED, b"")
av = re.search(rb"const VERSION = '([0-9]+\.[0-9]+\.[0-9]+)';", approved)
by = re.search(rb"const APPROVED_BY = '([^']+)';", approved)
check("C01", "Approved rule set (activation): APPROVED_DATASETS and APPROVED_RULEBOOKS name exactly the packaged approved dataset, generated by "
      "tools/apply-approval.py, status approved, with approver, date and approval hash; the live registry loads it only when it is named",
      av is not None and by is not None and b"const STATUS = 'approved';" in approved and b"GENERATED by tools/apply-approval.py" in approved
      and re.search(rb"const APPROVED_AT_UTC = '\d{4}-\d\d-\d\d';", approved) is not None and re.search(rb"const APPROVAL_SHA256 = '[0-9a-f]{64}';", approved) is not None
      and b"const APPROVED_DATASETS = array('" + av.group(1) + b"');" in schema
      and b"const APPROVED_RULEBOOKS = array('doajpa-criteria-" + av.group(1) + b"');" in schema
      and re.search(rb"function liveRules\(\)\s*\{\s*if \(!DoajPaSchema::APPROVED_DATASETS\) return array\(\);", registry) is not None,
      {"approvedVersion": av.group(1).decode() if av else None, "approvedBy": by.group(1).decode() if by else None})
check("C02", "The candidate dataset is packaged as PROPOSED (no approver) and never approved; the candidate profile is switched off in this build (D-03-6)",
      b"const STATUS = 'proposed';" in dataset and b"'approvedBy' => null" in dataset and b"Status PROPOSED (PENDING APPROVAL" in dataset
      and b"const CANDIDATE_RULEBOOK = 'candidate-doajpa-criteria-0.1.0-draft';" in schema and b"const CANDIDATE_PROFILE_ENABLED = false;" in schema
      and b"'0.1.0-draft'" not in re.search(rb"const APPROVED_DATASETS = array\([^)]*\);", schema).group(0))
check("C03", "Results are labelled: approved runs as a pre-assessment independent of DOAJ (not a DOAJ decision); stored candidate runs as PENDING APPROVAL and not a DOAJ result",
      all(t in files.get("locale/en_US/locale.po", b"") for t in (b"Candidate criteria - PENDING APPROVAL.", b"Pre-assessment, not a DOAJ decision.", b"not affiliated with, reviewed or endorsed by DOAJ"))
      and all(t in files.get("templates/partials/notResult.tpl", b"") for t in (b"candidate.label", b"approved.label"))
      and b"approvedBuild.text" in files.get("templates/partials/nav.tpl", b""))
check("C04", "DOAJ text shown in results carries its attribution (licences, authors, wording unchanged, no endorsement)",
      b"attribution.doaj" in files.get("templates/partials/notResult.tpl", b"")
      and all(t in files.get("locale/en_US/locale.po", b"") for t in (b"CC BY-SA 4.0", b"COPE, DOAJ, OASPA, WAME, CC BY 4.0", b"wording unchanged", b"not reviewed or endorsed")))

synthetic = sorted(n for n, b in files.items() if SYNTHETIC in b)
check("C05", "No synthetic test approval is packaged: the approval was made by the product owner, not by the activation test", not synthetic, synthetic)

failed = [r["id"] for r in results if r["result"] != "PASS"]
summary = {"archive": os.path.basename(path), "sha256": sha, "bytes": len(data), "entries": len(members), "files": len(files),
           "release": rel, "results": results}
if opts.json:
    json.dump(summary, open(opts.json, "w"), indent=2)
print(f"checks={len(results)} pass={len(results) - len(failed)} fail={len(failed)} {failed}")
sys.exit(0 if not failed else 1)
