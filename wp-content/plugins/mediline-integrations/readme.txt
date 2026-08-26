=== Mediline PAP & CRM Integrations ===
Contributors: mediline
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 1.0.0

Production integration between Post Affiliate Pro, Pipedrive, and WooCommerce.

== Data flow ==

1. PAP click tracking runs on public pages and writes visitor/affiliate IDs only to explicitly marked lead forms.
2. Current/first-touch UTM parameters, click IDs, interface language, and landing URL are stored in a bounded first-party cookie.
3. Partner registration and WooCommerce checkout enqueue an encrypted Pipedrive job before any external CRM request.
4. The worker finds or creates a Person and idempotently creates one Deal per submission/order.
5. A Basic-Auth protected Pipedrive v2 Won webhook accepts only Deals bound to a completed local job, then durably queues one PAP S2S Sale per Deal.

Browser-side Sale tracking is intentionally not used. Pipedrive Won is the single source of truth. Local idempotency and a stable OrderID handle webhook retries; PAP duplicate-OrderID recognition remains a required second line of defense.

For direct/public traffic, commission attribution uses only PAP's 32-character visitor ID. A browser-supplied AffiliateID is retained for CRM diagnostics but can never force a commission. AffiliateID sale fallback is permitted only for the server-pinned owner of a generated Mediline storefront.

== Setup ==

1. Activate the plugin and open Mediline CRM in wp-admin.
2. Enter the Pipedrive company HTTPS base and API token, then save and enable CRM delivery.
3. Run "Provision/verify Deal fields". Pipedrive field codes are company-specific and must not be copied from another tenant.
4. In PAP enable Sale Tracking Fraud Protection, choose data2-data5, set a secret, and configure a long duplicate-OrderID recognition interval. Enter the same field/secret and confirm duplicate protection in WordPress.
5. Set the intended pipeline/stage, expected company ID/host when available, then save.
6. On the public production HTTPS domain, run "Register Won webhook".
7. Submit a test lead and order, verify their queue rows and Pipedrive fields, then mark the test Deal Won and verify exactly one PAP sale even after replaying the webhook.

The API token, PAP checksum secret, and webhook password are encrypted at rest with AES-256-GCM using WordPress salts. They can alternatively be supplied through MEDILINE_PIPEDRIVE_API_TOKEN, MEDILINE_PAP_FRAUD_SECRET, MEDILINE_PIPEDRIVE_WEBHOOK_USER, and MEDILINE_PIPEDRIVE_WEBHOOK_PASSWORD constants.

== Privacy and safety ==

Only the marked partner registration and WooCommerce order pipeline are CRM sources. Login credentials, passwords, search forms, Store Builder admin credentials, and wp-admin forms are never collected. The public lead mirror uses a same-origin nonce, an atomic per-IP rate limit, and a honeypot; production deployments may attach CAPTCHA or another risk check with `mediline_integrations_validate_public_lead`. Behind a trusted reverse proxy or CDN, configure the web server to expose the verified client address as `REMOTE_ADDR`; otherwise every visitor may share the proxy's rate-limit bucket. Queue payloads are encrypted and are not shown in wp-admin; logs contain machine error codes only. Contact PII is removed from a queue record immediately after successful Pipedrive delivery, leaving only the minimal encrypted attribution provenance needed for a later Won event. A daily cleanup removes failed rows after 30 days and completed provenance after 730 days; deployments can change these periods with the `mediline_integrations_failed_retention_days` and `mediline_integrations_completed_retention_days` filters.

Deployments must document the CRM/PAP processing purpose in their privacy notice and select a legally appropriate consent/legal-basis policy for their markets.
