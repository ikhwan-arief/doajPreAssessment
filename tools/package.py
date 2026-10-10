#!/usr/bin/env python3
"""Reproducible installation archive for the doajPreAssessment OJS 3.3 plugin (Stage 02.08).

Builds dist/doajPreAssessment-<release>.tar.gz with a single top-level directory doajPreAssessment/ (ADR-001).
Only the runtime plugin files in INCLUDE are packaged; docs/, tests/ (including tests/support/), tools/ and every
other path are never packaged. The archive is byte-for-byte reproducible for a given source:

  - files come from a git commit (default HEAD; --worktree packs the working tree for development builds);
  - entries are sorted; directories 0755, files 0644; owner root:root (0:0); every mtime is the commit time
    (or SOURCE_DATE_EPOCH for --worktree); ustar format; gzip header without file name and with mtime 0;
  - MANIFEST.sha256 inside the archive lists the SHA-256 of every other packaged file;
  - dist/doajPreAssessment-<release>.tar.gz.sha256 holds the archive hash (sha256sum format).

Usage: python3 tools/package.py [--ref <commit>|--worktree] [--out <dir>]
Requires only Python 3 and git.
"""
import argparse, gzip, hashlib, io, os, re, subprocess, sys, tarfile

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
TOP = "doajPreAssessment"
INCLUDE = ["index.php", "version.xml", "install.xml", "upgrade.xml", "DoajPreAssessmentPlugin.inc.php",
           "classes", "pages", "templates", "css", "locale", "README.md", "CHANGELOG.md", "RELEASE-NOTES.md"]
IGNORED_NAMES = {".DS_Store", "Thumbs.db"}


def git(*args):
    return subprocess.run(["git", "-C", ROOT] + list(args), check=True, capture_output=True).stdout


def from_ref(ref):
    names = git("ls-tree", "-r", "-z", "--name-only", ref, "--", *INCLUDE).decode().split("\0")
    files = {}
    for name in sorted(n for n in names if n):
        if os.path.basename(name) in IGNORED_NAMES:
            continue
        mode = git("ls-tree", ref, "--", name).decode().split()[0]
        if mode not in ("100644", "100755"):
            sys.exit(f"refusing to package {name}: git mode {mode} (only regular files are allowed)")
        files[name] = git("show", f"{ref}:{name}")
    return files, int(git("log", "-1", "--format=%ct", ref).decode().strip())


def from_worktree():
    files = {}
    for item in INCLUDE:
        path = os.path.join(ROOT, item)
        if os.path.isfile(path):
            files[item] = open(path, "rb").read()
            continue
        for dirpath, dirnames, filenames in os.walk(path):
            dirnames.sort()
            for fn in sorted(filenames):
                full = os.path.join(dirpath, fn)
                if fn in IGNORED_NAMES:
                    continue
                if os.path.islink(full) or not os.path.isfile(full):
                    sys.exit(f"refusing to package {full}: not a regular file")
                files[os.path.relpath(full, ROOT)] = open(full, "rb").read()
    epoch = os.environ.get("SOURCE_DATE_EPOCH")
    return files, int(epoch) if epoch else int(git("log", "-1", "--format=%ct", "HEAD").decode().strip())


def main():
    ap = argparse.ArgumentParser()
    g = ap.add_mutually_exclusive_group()
    g.add_argument("--ref", default="HEAD")
    g.add_argument("--worktree", action="store_true")
    ap.add_argument("--out", default=os.path.join(ROOT, "dist"))
    opts = ap.parse_args()

    files, mtime = from_worktree() if opts.worktree else from_ref(opts.ref)
    missing = [i for i in INCLUDE if not any(n == i or n.startswith(i + "/") for n in files)]
    if missing:
        sys.exit(f"missing from source: {missing}")
    m = re.search(rb"<release>([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)</release>", files["version.xml"])
    if not m:
        sys.exit("no <release> in version.xml")
    release = m.group(1).decode()
    manifest = "".join(f"{hashlib.sha256(files[n]).hexdigest()}  {TOP}/{n}\n" for n in sorted(files)).encode()
    files["MANIFEST.sha256"] = manifest

    dirs = set()
    for name in files:
        parts = name.split("/")[:-1]
        for i in range(1, len(parts) + 1):
            dirs.add("/".join(parts[:i]))
    entries = [(TOP, None)] + sorted([(f"{TOP}/{d}", None) for d in dirs] + [(f"{TOP}/{n}", files[n]) for n in files])

    raw = io.BytesIO()
    with tarfile.open(fileobj=raw, mode="w", format=tarfile.USTAR_FORMAT) as tar:
        for name, data in entries:
            info = tarfile.TarInfo(name)
            info.uid = info.gid = 0
            info.uname = info.gname = "root"
            info.mtime = mtime
            if data is None:
                info.type, info.mode = tarfile.DIRTYPE, 0o755
                tar.addfile(info)
            else:
                info.type, info.mode, info.size = tarfile.REGTYPE, 0o644, len(data)
                tar.addfile(info, io.BytesIO(data))
    os.makedirs(opts.out, exist_ok=True)
    name = f"{TOP}-{release}.tar.gz"
    path = os.path.join(opts.out, name)
    with open(path, "wb") as fh:
        with gzip.GzipFile(filename="", mode="wb", fileobj=fh, mtime=0, compresslevel=9) as gz:
            gz.write(raw.getvalue())
    digest = hashlib.sha256(open(path, "rb").read()).hexdigest()
    open(path + ".sha256", "w").write(f"{digest}  {name}\n")
    print(path)
    print(f"{digest}  {name}")
    print(f"source={'worktree' if opts.worktree else opts.ref} files={len(files)} mtime={mtime}")


if __name__ == "__main__":
    main()
