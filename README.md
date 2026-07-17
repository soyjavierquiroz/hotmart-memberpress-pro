# Hotmart MemberPress Pro 0.3.0

Version 0.3.0 adds safe subscription renewals, cancellation across paid periods, overdue-payment grace, limited transient retries, refund-request warnings and authenticated manual fallback tools. No OAuth or Hotmart API calls are used.

WordPress plugin that securely receives Hotmart webhooks and grants MemberPress
membership access.

Version 0.2.0 includes:

- `POST /wp-json/hmp/v1/webhook`
- HOTTOK authentication and deterministic idempotency
- Event, activation and mapping tables
- `PURCHASE_APPROVED` and `PURCHASE_COMPLETE` processing
- Basic administration, settings and retention cleanup
- Mapping creation, editing, activation and deletion
- Webhook history, filters, payload inspection and failed-event reprocessing
- Activation history with manual revoke/reactivate controls
- Refund, chargeback, cancellation and expiration lifecycle handling

## Requirements

- WordPress 6.0+
- PHP 8.1+
- MemberPress is required to grant access, but the plugin can be activated without it

See [Hotmart setup](docs/HOTMART-SETUP.md) and [architecture](docs/ARCHITECTURE.md).

## License

GPL-2.0-or-later.
