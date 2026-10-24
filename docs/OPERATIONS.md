# Install, upgrade, recover

## First installation

Use a disposable staging site first. Requirements: WordPress 6.6+, PHP 8.2+. WooCommerce and Elementor are optional. Upload the maintainer-approved ZIP through Plugins → Add New → Upload Plugin and activate it. Open Testimonials, create a collection and add a fictional review. Use the native Testimonials/Rating Summary/Review Form blocks, free Elementor widget or the shortcode builder. Publish the review and check the page anonymously on phone and desktop. Remove fictional examples before launch, or label a demo clearly.

For forms, verify an immediate submission becomes Pending, one exact retry does not duplicate it, the email stays private, and approval changes what visitors see. Test cache/CDN behaviour and both a success and a simulated network failure. Purge public form pages after WordPress salt rotation. Shared connections share the five-new-reviews-per-hour limit. Large curated/random showcases are not substitutes for an honest aggregate rating.

Collection questions explicitly label public/private answers. Do not collect sensitive details you do not need. CSV exports include private answers and contact details: store and transfer them securely. JSON design presets contain design configuration only, never reviews or notification settings.

## Updating from v1.0.0

Take a complete database and plugin-file backup and rehearse restoration on staging. Export CSV as an additional portable copy; CSV does not replace a database backup with revision/status/history. Record collection slugs, settings, counts by status and example pages. Install the approved candidate **over the existing plugin**, without deleting it. Deactivation retains records. Confirm the version, unchanged counts/settings/assignments, unpublished/trash records, reviews with paragraph formatting, public/private custom fields, pagination and privacy tools. Purge asset/page caches.

The automated upgrade fixture installs the exact v1.0.0 tagged source, seeds all review states, revisions, metadata, two collection assignments and custom settings, installs the candidate ZIP, compares a snapshot, rolls the binary back without uninstalling, then reinstalls the candidate. This does not replace a site-specific backup/theme/integration rehearsal. The fixture uses the tagged source, not an assertion about identical bytes in the original published ZIP.

## Import recovery

Import a small fictional CSV on staging and inspect row-level errors before a large transfer. Missing collections are restored by slug; existing collections retain their definitions/settings. Imported reviews cannot grant direct provenance or purchase proof automatically. Formula-like CSV cells are escaped for spreadsheets. Preserve the original export. A partial import is possible if later rows fail; do not blindly reimport successful rows. Filter by the staging/test collection and compare IDs/counts, then remove only confirmed duplicate records after authorisation. No automatic production deletion is part of recovery.

## Review reminder operations

Default off. An administrator must enable reminders, the WooCommerce product tab and its form collection. Only a signed-in owner who explicitly asks from their completed order is enrolled, once for that order. There is no guest/bulk backfill. Verify using a test mail sink and fictional orders before enabling it on a real store. A request is due seven days after consent; traffic or a system cron drives WP-Cron, so exact delivery time is not guaranteed.

Replayed jobs use an atomic, permanent mail-attempt claim. A mail transport acceptance is not proof of inbox delivery. Failed/ambiguous attempts and worker crashes after a claim are not automatically resent; verify transport logs before considering any manual claim reset. Cancelling a request removes its scheduled event; refunds, disabled settings, changed identity, deleted products/orders and existing reviews suppress delivery. A reminder's unsubscribe GET only shows confirmation; POST records suppression. Mail scanners do not change preferences by following links. Never copy signed preference URLs into public issues.

## Incident or bad release

Stop review reminders or deactivate the plugin to stop its callbacks. Keep data. Restore the previous approved plugin files over the current files from a trusted ZIP; **do not use Delete as a rollback step**, especially with the original v1.0.0 code. Compare the backed-up state on staging. Restore a database only after considering newer orders/content: a whole-database restore can discard unrelated changes. Ask the authorised site owner to approve any destructive recovery. Re-enable only after checking forms, anonymous output, privacy and integration flows. Document the affected version, containment, fix and validation without exposing personal data.

## Privacy and deletion

Use WordPress Tools → Export/Erase Personal Data with its verification workflow. Reviews matching the email include unpublished/trash states and revisions. Erasure permanently removes matching reviews; media files, backups and external copies require separate review. Reminder state is scoped to the matching account email; a minimal suppression preference remains to avoid future mail, and WooCommerce handles order privacy separately.

Deleting the candidate retains plugin data by default. Explicit per-site uninstall cleanup removes plugin-owned reviews/metadata, collections, settings, presets, request options and per-site suppression preferences; Media Library files remain. Uninstall clears queued reminder events even when retaining records. Reinstallation does not automatically reschedule those cleared events. Multisite settings apply per site. Back up before enabling cleanup.
