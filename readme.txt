=== Hotmart MemberPress Pro ===
Contributors: soyjavierquiroz
Tags: hotmart, memberpress, webhook, membership
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Securely receives Hotmart webhooks and grants MemberPress access for approved purchases.

Version 0.3.0 adds reliable renewals, grace periods, retries, refund-request tracking and manual fallback tools.

== Description ==

Version 0.2.0 provides the secure webhook endpoint, complete mapping administration,
webhook and activation listings, manual reprocessing, and a precise membership
revocation lifecycle for refunds, chargebacks, cancellations and expirations.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Activate Hotmart MemberPress Pro.
3. Configure HOTTOK under Hotmart MemberPress > Settings.
4. Add mappings under Hotmart MemberPress > Mappings.
5. Configure Hotmart with the webhook URL shown on the overview page.

== Changelog ==

= 0.3.1 =
Automatic web-based database upgrades, schema verification, PHP web diagnostics and safe internal endpoint tests.

= 0.2.0 =
* Added complete Hotmart to MemberPress mapping administration.
* Added searchable webhook and activation listings.
* Added payload inspection and failed-event reprocessing.
* Added centralized refund, chargeback, cancellation and expiration handling.
* Added manual revoke/reactivate actions with audit details.

= 0.1.0 =
* Initial secure webhook and approved purchase flow.
