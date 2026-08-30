# Mediline integrations regression tests

Dependency-free tests for the security and idempotency boundaries of the PAP and
Pipedrive integration. They use the production PHP/JavaScript files directly and
replace WordPress, WooCommerce, Pipedrive, PAP, and the durable outbox only at their
external boundaries.

Run from the repository root:

```powershell
powershell -ExecutionPolicy Bypass -File wp-content/plugins/mediline-integrations/tests/run-tests.ps1
```

Covered contracts:

- attribution normalization, double-sided Store/Catalog landing-URL allowlisting,
  and password exclusion;
- query and anchor-link attribution, marked-form frontend payload, and PAP hidden fields;
- direct-site versus generated-store affiliate isolation;
- rejection of client-controlled AffiliateID commission forcing;
- PAP API v3 Bearer-key origin pinning, exact affiliate lookup, and fail-closed Store Builder identity checks;
- official Pipedrive HTTPS-origin enforcement;
- exact Pipedrive Person and Deal lookup, idempotent Deal reuse, and payloads;
- Pipedrive v2 Won-transition parsing, Basic auth, and Deal-level queue dedupe;
- PAP sale payload, final 32-character visitor ID, and absence of Commission;
- AES-256-GCM round trip/tamper detection and admin-safe outbox summaries.

The suite deliberately does not call live services or boot a full WordPress site.
Run the local transactional WordPress/database smoke separately:

```powershell
php wp-content/plugins/mediline-integrations/tests/live-wordpress-smoke.php
```

It verifies the installed schema and cron schedules, encrypted/idempotent outbox,
locking and stale-worker wake races, PII redaction, and retention, then rolls every
test mutation back. Real Action Scheduler execution, remote webhook registration,
and live Pipedrive/PAP responses remain staging smoke tests after credentials are
configured.
