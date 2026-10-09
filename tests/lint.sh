#!/usr/bin/env bash
# php -l on every plugin PHP file, on PHP 7.3 and PHP 8.2 (the ends of the OJS 3.3.0-23 range).
# Usage: tests/lint.sh   (needs Docker; pulls php:7.3-cli and php:8.2-cli if missing)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
status=0
for v in 7.3 8.2; do
  echo "== php:$v-cli"
  docker run --rm -v "$ROOT:/src:ro" -w /src "php:$v-cli" sh -c '
    php -v | head -1
    n=0; bad=0
    for f in $(find . -name "*.php" -not -path "./tools/runtime/.work/*" -not -path "./.git/*" | sort); do
      n=$((n+1)); out=$(php -l "$f" 2>&1) || { bad=$((bad+1)); echo "FAIL $f"; echo "$out"; }
    done
    echo "files=$n errors=$bad"; [ "$bad" -eq 0 ]' || status=1
done
exit $status
