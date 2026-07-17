#!/usr/bin/env bash
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_ROOT="$(cd "$ROOT/../../.." && pwd)"
fail=0
command -v wp >/dev/null || { echo "ERROR: WP-CLI is not available"; exit 1; }
cd "$WP_ROOT" || exit 1
prefix="$(wp db prefix 2>/dev/null)"
for table in hmp_events hmp_activations hmp_mappings; do
  wp db query "SHOW TABLES LIKE '${prefix}${table}'" --skip-column-names 2>/dev/null | grep -q "${prefix}${table}" || { echo "ERROR: missing table ${prefix}${table}"; fail=1; }
done
[[ "$(wp option get hmp_db_version 2>/dev/null || true)" == "0.3.0" ]] || { echo "ERROR: database version is not 0.3.0"; fail=1; }
wp cron event list --fields=hook --format=csv 2>/dev/null | grep -q hmp_process_grace_expirations || { echo "ERROR: grace cron missing"; fail=1; }
wp cron event list --fields=hook --format=csv 2>/dev/null | grep -q hmp_retry_failed_events || { echo "ERROR: retry cron missing"; fail=1; }
for fixture in "$ROOT"/tests/fixtures/*.json; do php -r '$d=json_decode(file_get_contents($argv[1]),true);exit(is_array($d)&&!empty($d["event"])?0:1);' "$fixture" || { echo "ERROR: invalid fixture $fixture"; fail=1; }; done
[[ "${HMP_ALLOW_TEST_WRITES:-0}" == "1" ]] || echo "Read-only mode: no test data created."
exit "$fail"
