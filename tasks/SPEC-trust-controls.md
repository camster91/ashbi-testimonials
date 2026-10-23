# Trust controls: first implementation slice

Scope selected after Cameron confirmed the fully free direction and instructed continued work. This slice implements privacy export/erasure, explicit uninstall retention and truthful fast-submission outcomes. It does not implement the entire product roadmap.

## Behaviour

- Register bounded, email-scoped callbacks with the native WordPress privacy tools. Export all plugin review fields and review states; never include another reviewer's data or another site's records.
- A confirmed erasure permanently removes matching reviews and their plugin metadata/revisions, and invalidates review statistics. Deletion uses WordPress APIs. Shared media attachments and third-party copies are outside plugin-owned review storage and must be disclosed.
- Paginate export stably. Erasure must not skip records while deletion changes the result set; retries are safe. Failed deletion produces an explicit retained-data result without an endless loop.
- Retain plugin data on deletion by default, including upgrades whose settings have no retention key. An administrator may explicitly choose destructive cleanup. Multisite applies each site's own setting.
- A valid, signed form submitted immediately is saved pending moderation before success. Keep signature/target checks, honeypot and existing rate limits. No new mail is sent during tests.

## Architecture and commands

Add a focused `ABT_Privacy` integration class using core privacy filters, `WP_Query` and `wp_delete_post`; bootstrap it alongside existing classes. Keep retention preference in existing settings. No new tables, libraries, external services or accounts.

Use existing `.github/workflows/validate.yml`; add `wp-env run cli wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-privacy.php` before disposable uninstall checks. Extend existing public-form and single/multisite uninstall fixtures. Run existing JavaScript and package checks through CI. Do not run destructive tests on real sites.

## Acceptance and review

Test at least 100+ matching records across states; two export pages; repeated erasure requests; a nonmatching reviewer and unrelated post; invalid email; deletion failure; stats invalidation; default retention, explicit cleanup and mixed multisite preferences; fresh-token submission and moderation.

No new public privacy endpoint. WordPress's existing verified/admin privacy request workflow owns authorization; callbacks are internal integration points. Admin-only, nonce-protected existing settings actions own the retention choice.

Publish only accurate support/privacy text. Do not claim legal certification, completed full browser QA or clearance of third-party backups/media. Changes ship as a reviewable PR stacked on PR #3 until that PR is merged. No merge, production data action or release publication is included.
