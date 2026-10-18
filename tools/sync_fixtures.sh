#!/usr/bin/env bash
# Copy test-data/ into tests/fixtures/ so the plugin's PHPUnit tests never read outside the plugin.
#   tools/sync_fixtures.sh          copy
#   tools/sync_fixtures.sh --check  exit 1 if tests/fixtures differs from test-data (run before every release)
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
src="$root/test-data"
dst="$root/tests/fixtures"

build() {
  local out="$1"
  mkdir -p "$out/plans" "$out/cases"
  cp "$src"/plans/*.md "$out/plans/"
  cp "$src"/cases/*.md "$out/cases/"
  cp "$src/cases/expected.json" "$out/cases-expected.json"
  cp "$src/expected.json" "$out/expected.json"
  cp "$src/holidays-centre-2026-27.txt" "$src/holidays-expanded.json" "$out/"
}

if [[ "${1:-}" == "--check" ]]; then
  tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
  build "$tmp"
  diff -r "$tmp" "$dst" && echo "tests/fixtures matches test-data"
else
  rm -rf "$dst"; build "$dst"; echo "copied test-data -> tests/fixtures"
fi
