#!/usr/bin/env bash
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
fail=0
check() { rg -q "$1" "$ROOT/$2" || { echo "ERROR: $3"; fail=1; }; }
check "payment_delayed" includes/memberpress/class-revocation-service.php "delayed state missing"
check "unchanged.*true" includes/memberpress/class-revocation-service.php "duplicate grace guard missing"
check "clear_pending_for_subscription" includes/memberpress/class-memberpress-service.php "approved payment recovery missing"
check "has_later_paid_activation" includes/memberpress/class-revocation-service.php "grace revalidation missing"
check "payment_overdue" includes/memberpress/class-revocation-service.php "overdue revocation reason missing"
check "refund_requested" includes/memberpress/class-revocation-service.php "refund request state missing"
check "SUBSCRIPTION_CANCEL(LATION|ED|LED)" includes/events/class-event-processor.php "cancellation aliases missing"
check "method=\\\"post\\\"" includes/admin/class-activations.php "manual actions are not POST forms"
check "reason.*required|required.*reason" includes/admin/class-tools.php "mandatory manual reason missing"
check "find_grace_expired\( 50 \)" includes/class-lifecycle.php "cron batch limit is not 50"
check "GRACE_LOCK" includes/class-lifecycle.php "cron lock missing"
if [[ "$fail" -eq 0 ]]; then echo "Operational invariants: OK"; fi
exit "$fail"
