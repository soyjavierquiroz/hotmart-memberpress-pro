# Changelog

## 0.4.0

- Separates delayed payments from overdue grace periods and makes grace expiration revalidate later payments.
- Adds subscription cancellation aliases and review handling when no paid expiration is known.
- Adds POST-only manual fallback actions with mandatory reasons and append-only operational audit events.
- Adds hourly cron locking, 50-row batches, operational counters and cron reporting.

## 0.3.1

- Automatically applies schema 0.3.0 from the WordPress web runtime without reactivation or WP-CLI.
- Adds locked, idempotent migration verification and safe administrative recovery.
- Adds PHP web environment and internal REST endpoint diagnostics.

## 0.3.0

- Reliable multi-period renewals and subscription cancellation.
- Grace periods, hourly expiration, limited retries and refund-request tracking.
- Diagnostics and authenticated manual fallback tools.

## 0.2.0

- Added complete Hotmart to MemberPress mapping administration.
- Added webhook history, filters, payload inspection and failed-event reprocessing.
- Added activation history with manual revoke and reactivate actions.
- Added centralized refund, chargeback, cancellation and expiration lifecycle handling.
- Added audit fields for manual activation actions.

## 0.1.0

- Added the secure webhook bootstrap and approved purchase flow.
