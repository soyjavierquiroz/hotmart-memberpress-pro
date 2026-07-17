# Architecture

Version 0.4.0 keeps schema 0.3.0. Operational states live on activations, while each manual action is appended to `hmp_events` as `HMP_MANUAL_ACTION` with source `manual_audit`; this preserves complete history without another table or schema migration. The hourly grace job uses a transient lock, processes at most 50 rows, and revalidates later approved payments before touching the related MemberPress transaction.

Plugin version 0.3.1 uses schema version 0.3.0. `Upgrader` is the single source for table definitions; it runs automatically during `plugins_loaded` in the WordPress web environment, uses a five-minute transient lock, verifies required columns before advancing the stored schema version, and exposes a nonce-protected manual recovery action.

Version 0.3.0 keeps one activation per Hotmart transaction and derives subscription state from ordered activations. Refunds target one transaction; cancellation marks only the latest period. Native WP-Cron handles hourly grace expiration and 15-minute transient retries.

The main plugin file defines constants, registers lifecycle hooks and starts `HMP\Plugin`
on `plugins_loaded`. The custom autoloader maps the `HMP` namespace to `includes/`.

Webhook flow:

1. `Webhook_Controller` checks enablement, authentication and JSON.
2. `Payload_Normalizer` extracts a stable internal payload.
3. `Event_Key` creates a deterministic SHA-256 idempotency key.
4. `Event_Repository` persists the original JSON outside public files.
5. `Event_Processor` routes supported event names.
6. `MemberPress_Service` resolves a mapping, user, transaction and activation.
7. `Revocation_Service` is the only service that expires, refunds, revokes, cancels or
   reactivates access.

The plugin owns three tables:

- `hmp_events`: immutable inbound payload and processing state
- `hmp_activations`: relationship between Hotmart purchases and MemberPress access
- `hmp_mappings`: Hotmart product/offer/plan to MemberPress membership rules

MemberPress classes are referenced only after `class_exists()` checks. This allows the
plugin to activate and store a clear failed state when MemberPress is unavailable.

## Administration

Administration is split into small page controllers:

- `Mappings` owns mapping CRUD and MemberPress membership selection.
- `Webhooks` owns filtering, payload inspection, ignored status and safe reprocessing.
- `Activations` owns filtering and manual revoke/reactivate actions.

All state-changing actions require `manage_options` and an action-specific nonce.
Manual activation actions are stored with action name, UTC date and result.

## Revocation lifecycle

The revocation service first resolves one exact activation using the Hotmart transaction
and, when available, subscription/product/offer/plan identifiers. Ambiguous matches fail
without changing access. It never deletes a user or modifies unrelated transactions.

Refunds and chargebacks mark the related MemberPress transaction refunded and expire it.
Purchase cancellation/expiration expires only the related transaction. Subscription
cancellation marks the activation canceled while preserving paid access through its
existing `expires_at`.

The daily cleanup removes old `processed` and `ignored` events according to retention.
Activations are never removed by cleanup. Uninstall is non-destructive unless
`HMP_REMOVE_DATA_ON_UNINSTALL` is explicitly defined as `true`.
