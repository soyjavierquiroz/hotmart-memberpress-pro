# Hotmart MemberPress Pro

WordPress plugin that securely receives Hotmart webhooks and grants MemberPress
membership access.

Version 0.1.0 includes:

- `POST /wp-json/hmp/v1/webhook`
- HOTTOK authentication and deterministic idempotency
- Event, activation and mapping tables
- `PURCHASE_APPROVED` and `PURCHASE_COMPLETE` processing
- Basic administration, settings and retention cleanup

## Requirements

- WordPress 6.0+
- PHP 8.1+
- MemberPress is required to grant access, but the plugin can be activated without it

See [Hotmart setup](docs/HOTMART-SETUP.md) and [architecture](docs/ARCHITECTURE.md).

## License

GPL-2.0-or-later.
