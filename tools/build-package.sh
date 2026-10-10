#!/usr/bin/env bash
# Build the installation archive dist/doajPreAssessment-<release>.tar.gz (one top-level directory doajPreAssessment/,
# ADR-001) and verify it. Only runtime plugin files are packaged; docs/, tests/ (including tests/support/) and tools/
# are never packaged. Reproducible: the same commit always produces the same bytes (tools/package.py).
# Usage: tools/build-package.sh [--ref <commit>|--worktree] [--out <dir>]   (default: --ref HEAD, --out dist)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT=$(python3 "$ROOT/tools/package.py" "$@")
echo "$OUT"
ARCHIVE=$(echo "$OUT" | head -1)
python3 "$ROOT/tools/verify-package.py" "$ARCHIVE"
