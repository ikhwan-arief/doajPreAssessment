#!/usr/bin/env bash
# Build an installable development archive: dist/doajPreAssessment-<release>.tar.gz with a single
# top-level directory doajPreAssessment/ (ADR-001). Only runtime plugin files are included; docs/,
# tests/ (including tests/support/) and tools/ are never packaged. Full release packaging is 02.08.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
RELEASE=$(sed -n 's:.*<release>\(.*\)</release>.*:\1:p' version.xml)
[ -n "$RELEASE" ] || { echo "no <release> in version.xml"; exit 1; }
INCLUDE=(index.php version.xml install.xml upgrade.xml DoajPreAssessmentPlugin.inc.php classes pages templates locale README.md CHANGELOG.md)
STAGE="$(mktemp -d)"
trap 'rm -rf -- "$STAGE"' EXIT
mkdir "$STAGE/doajPreAssessment"
for item in "${INCLUDE[@]}"; do
  [ -e "$item" ] || { echo "missing $item"; exit 1; }
  cp -R "$item" "$STAGE/doajPreAssessment/"
done
find "$STAGE" -name '.DS_Store' -delete
find "$STAGE" -type d -exec chmod 755 {} +
find "$STAGE" -type f -exec chmod 644 {} +
mkdir -p dist
OUT="dist/doajPreAssessment-$RELEASE.tar.gz"
COPYFILE_DISABLE=1 tar --no-xattrs --uid 0 --gid 0 --uname root --gname root -czf "$OUT" -C "$STAGE" doajPreAssessment
echo "$OUT"
shasum -a 256 "$OUT"
tar -tzvf "$OUT"
