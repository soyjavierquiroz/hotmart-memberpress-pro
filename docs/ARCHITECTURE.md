# Architecture

The main plugin file defines constants, registers lifecycle hooks and starts `HMP\Plugin`
on `plugins_loaded`. The custom autoloader maps the `HMP` namespace to `includes/`.

Webhook flow:

1. `Webhook_Controller` checks enablement, authentication and JSON.
2. `Payload_Normalizer` extracts a stable internal payload.
3. `Event_Key` creates a deterministic SHA-256 idempotency key.
4. `Event_Repository` persists the original JSON outside public files.
5. `Event_Processor` routes supported event names.
6. `MemberPress_Service` resolves a mapping, user, transaction and activation.

The plugin owns three tables:

- `hmp_events`: immutable inbound payload and processing state
- `hmp_activations`: relationship between Hotmart purchases and MemberPress access
- `hmp_mappings`: Hotmart product/offer/plan to MemberPress membership rules

MemberPress classes are referenced only after `class_exists()` checks. This allows the
plugin to activate and store a clear failed state when MemberPress is unavailable.

The daily cleanup removes old `processed` and `ignored` events according to retention.
Activations are never removed by cleanup. Uninstall is non-destructive unless
`HMP_REMOVE_DATA_ON_UNINSTALL` is explicitly defined as `true`.
