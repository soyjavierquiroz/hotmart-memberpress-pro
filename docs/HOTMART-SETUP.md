# Hotmart setup

1. Activate Hotmart MemberPress Pro.
2. Open **Hotmart MemberPress > Settings** and save a strong HOTTOK.
3. Open **Hotmart MemberPress > Overview** and copy the complete webhook URL.
4. In Hotmart, create a webhook using that URL and the exact same HOTTOK.
5. Subscribe at least to `PURCHASE_APPROVED` and `PURCHASE_COMPLETE`.
6. Create an active row in the WordPress `{prefix}hmp_mappings` table.

Mappings are matched in this order:

1. `plan_id + offer_code + product_id`
2. `plan_id`
3. `offer_code`
4. `product_id`

Within the same match level, the lowest `priority` value wins.

The endpoint accepts JSON only. Authentication failures return HTTP 401 and malformed
JSON returns HTTP 400. Once an event has been stored, processing failures return HTTP
200 to avoid aggressive retries; the event remains visible with status `failed`.
