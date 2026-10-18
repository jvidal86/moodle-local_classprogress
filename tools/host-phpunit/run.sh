#!/usr/bin/env bash
# Run the plugin's database-free tests on the host with PHPUnit 9.6, without Moodle.
#   tools/host-phpunit/run.sh                         all tests whose class extends \basic_testcase
#   tools/host-phpunit/run.sh tests/local/x_test.php  one file
#   tools/host-phpunit/run.sh --group REQ-CAL-001     extra PHPUnit options are passed through
# First time in a checkout or worktree: (cd tools/host-phpunit && composer install)
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$here/../.." && pwd)"
cd "$root"
[[ -x "$here/vendor/bin/phpunit" ]] || { echo "Run: (cd tools/host-phpunit && composer install)"; exit 2; }
files=(); opts=()
for a in "$@"; do if [[ "$a" == *_test.php ]]; then files+=("$a"); else opts+=("$a"); fi; done
if [[ ${#files[@]} -eq 0 ]]; then
  mapfile -t files < <(grep -rl --include='*_test.php' 'extends \\basic_testcase' tests 2>/dev/null || true)
fi
[[ ${#files[@]} -gt 0 ]] || { echo "No database-free tests found."; exit 0; }
status=0
for f in "${files[@]}"; do
  "$here/vendor/bin/phpunit" --bootstrap "$here/bootstrap.php" --colors=never "${opts[@]}" "$f" || status=$?
done
exit $status
