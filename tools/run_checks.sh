#!/usr/bin/env bash
# run_checks.sh - Quality gates for form_cardiology.
# Runs: php -l on every PHP file, node --check on every JS file (if node
# exists), the checker self-tests, then the schema checker.
# Prints a one-line summary per step. Exit code is non-zero on any failure.
set -u
cd "$(dirname "$0")/.."

fail=0

# 1. PHP lint
php_files=$(find . -name '*.php' -not -path './vendor/*' | sort)
php_fail=0
for f in $php_files; do
  if ! php -l "$f" >/dev/null 2>&1; then
    echo "PHP LINT FAIL: $f"
    php_fail=1
  fi
done
php_count=$(echo "$php_files" | wc -l)
if [ "$php_fail" = "0" ]; then
  echo "php -l: OK ($php_count files)"
else
  echo "php -l: FAILURES"
  fail=1
fi

# 2. JS lint (only when node exists)
if command -v node >/dev/null 2>&1; then
  js_files=$(find . -name '*.js' -not -path './vendor/*' | sort)
  js_fail=0
  for f in $js_files; do
    if ! node --check "$f" >/dev/null 2>&1; then
      echo "JS LINT FAIL: $f"
      js_fail=1
    fi
  done
  js_count=$(echo "$js_files" | wc -l)
  if [ "$js_fail" = "0" ]; then
    echo "node --check: OK ($js_count files)"
  else
    echo "node --check: FAILURES"
    fail=1
  fi
else
  echo "node --check: SKIPPED (node not installed)"
fi

# 3. Self-tests
if php tools/tests/run.php > /tmp/cardiology_selftests.out 2>&1; then
  echo "self-tests: OK ($(tail -1 /tmp/cardiology_selftests.out))"
else
  echo "self-tests: FAILURES (see /tmp/cardiology_selftests.out)"
  cat /tmp/cardiology_selftests.out
  fail=1
fi

# 4. Schema checker (writes docs/schema_audit.md)
if php tools/check_schema.php > /tmp/cardiology_checker.out 2>&1; then
  echo "check_schema: OK, 0 ERRORs (report: docs/schema_audit.md)"
else
  echo "check_schema: ERRORS found (report: docs/schema_audit.md)"
  fail=1
fi

exit "$fail"
