# Support and maintenance

Ashbi Testimonials is one free GPL-2.0-or-later plugin. Cameron Ashley maintains this repository; no paid support tier, service-level guarantee, telemetry or mandatory account is included. Hosting, email delivery, backups and third-party services remain the site's responsibility.

Start with [installation and recovery](docs/OPERATIONS.md). For non-sensitive bugs, use [GitHub issues](https://github.com/camster91/ashbi-testimonials/issues). Describe the expected/actual behaviour, smallest reproduction, plugin/PHP/WordPress version, relevant WooCommerce/Elementor versions, theme, object-cache/page-cache configuration, and whether it reproduces on staging with other plugins disabled. Remove personal information from screenshots and logs. Never post passwords, salts, customer/order records, email addresses or a database backup.

Security reports follow [SECURITY.md](SECURITY.md). Support does not require the maintainer to access a production site. Any access must be explicitly authorised, scoped and temporary. No automatic client installation, outreach or data collection is part of this package.

## Triage

1. Reproduce using fictional records on a supported WordPress/PHP combination. Distinguish plugin, theme, cache, mail transport and upstream integration behaviour.
2. Prioritise data exposure/loss and forged trust badges; then blocked submissions, upgrades and accessibility; then rendering/convenience issues. Record affected versions and a reproducible test.
3. For suspected exposure, preserve private evidence and stop the affected workflow. For failed imports, stop subsequent batches, export current records and compare the per-row results before retrying; retries are not automatically idempotent CSV imports.
4. Add a regression, review the change and verify the supported matrix. Publish only after explicit maintainer approval. Keep the issue open until the corrected release and upgrade guidance are available.

Support capacity and incident ownership must be confirmed during the pilot before broad distribution. Automated checks do not establish sustained support capacity or million-site readiness.
