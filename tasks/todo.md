# Proposed delivery backlog: Ashbi Testimonials — fully free

Draft, 23 October 2026. No GitHub issues have been created from this list. Cameron has confirmed that all features are free and the product should build Ashbi credibility. Module boundaries in `plan.md` still need technical review before module specifications and implementation. An unchecked item has not completed its full acceptance and release process; implementation in review is tracked below.

## Current implementation

[PR #4](https://github.com/camster91/ashbi-testimonials/pull/4) and its prerequisite #3 are merged into main after passing all six CI jobs. They implement the first trust slice specified in `SPEC-trust-controls.md`:

- T03: native email-scoped personal data export, including historical revisions and all review states.
- T04: bounded permanent review erasure, retry/failure handling, cache invalidation and shared-media disclosure.
- T05: default retention and explicit per-site cleanup, including mixed multisite preferences.
- T07: fixes silent loss of valid immediate submissions. Remaining hostile-input, concurrency, retry and browser recovery work is still open.

The PRs carry verification results and review status. PR #5 continues T06 with management/nonce regression coverage and private-page picker permissions. This work is not part of the published v1.0.0 download until a subsequent release is approved and published. The broader roadmap remains unfinished; passing integration tests does not establish million-site readiness.

## Shared verification

Use the repository's current CI workflow for PHP 8.2/8.4 syntax, Plugin Check/package, WordPress 6.6/latest, WooCommerce and uninstall checks. Existing commands include:

```sh
node --check assets/js/admin.js
node --check assets/js/frontend.js
node --test tests/admin-regressions.test.cjs
php -d disable_functions=mb_strtoupper tests/avatar-without-mbstring.php
bash scripts/package.sh
wp-env run cli wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-smoke.php
wp-env run cli wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-review-regressions.php
wp-env run cli wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-deeper-review.php
wp-env run cli wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-woocommerce.php
```

The disposable WordPress/WooCommerce environments must be prepared as in `.github/workflows/validate.yml`; the WooCommerce command needs WooCommerce active. Run uninstall tests only on disposable sites with the existing explicit test guard. Browser/benchmark commands will be recorded when those runners are chosen, not invented here.

Paths below describe likely change locations; new modules/fixtures are proposals. Each item must begin with a failing reproduction or agreed acceptance fixture where behaviour changes.

## Foundation

### T01 — Confirm module boundaries and launch priorities
- [ ] Acceptance: record the approved all-free direction, review the capability map and first audience, and select the first delivery slice.
- Verify: review `tasks/plan.md`; no implementation or paid service activation.
- Dependencies: none. Scope: S; files: `tasks/plan.md`, `tasks/todo.md`.

### T02 — Prepare the corrected release candidate
- [ ] Acceptance: PR #3 receives final review and explicit merge approval; versions, readme and changelog agree in a new candidate package; installing over v1.0.0 preserves reviews, assignments and settings.
- Verify: existing CI, inspect the ZIP allowlist, rehearse upgrade/rollback on a disposable fixture. Publishing requires separate explicit approval.
- Dependencies: T01. Scope: M; likely files: `ashbi-testimonials.php`, `readme.txt`, `scripts/package.sh`, release test fixture.

### T03 — Export personal data through WordPress
- [ ] Acceptance: a verified privacy export request returns the requester's review-associated personal data in batches without including another person's data.
- Verify: WordPress privacy-tool integration tests with anonymous/email-only and registered-user fixtures.
- Dependencies: T02. Scope: M; likely files: new privacy integration class, bootstrap, privacy tests, privacy documentation.

### T04 — Erase or anonymize personal data safely
- [ ] Acceptance: the privacy eraser handles review text, reviewer identity/contact metadata and consent evidence according to an approved retention policy; unrelated reviews survive; repeated erasure is safe.
- Verify: privacy export/erase round trip and cache invalidation tests; document any deliberately retained fields and reasons.
- Dependencies: T03. Scope: M; likely files: privacy class, data layer, privacy tests, privacy documentation.

### T05 — Make deletion an explicit data choice
- [ ] Acceptance: deactivation keeps data; deletion keeps data by default under the proposed policy; explicit deletion removes only the plugin's data across the selected site/network scope.
- Verify: existing uninstall suites extended to both policies, including settings upgrades from v1.0.0.
- Dependencies: T02 and an accepted retention decision. Scope: M; likely files: settings/bootstrap, admin settings view, `uninstall.php`, uninstall tests.

### T06 — Enforce review-management permissions
- [ ] Acceptance: an approved role/object capability matrix is enforced for read/write/import/export and collection actions; privileged metadata never appears in anonymous output.
- Verify: integration requests as anonymous, subscriber, author, editor, administrator and multisite actors; nonce, object-ID and capability-filter adversarial cases.
- Dependencies: T02. Scope: M; likely files: admin class, data class, public handlers, authorization test fixture.

### T07 — Make submission outcomes truthful and bounded
- [ ] Acceptance: a valid fast submission never receives success without a saved pending review; cache/salt-rotation errors are recoverable; malformed and concurrent submissions respect documented bounds and rate policy.
- Verify: tests for the under-three-second branch, stale/tampered tokens, honeypots, duplicate retries, proxy assumptions, oversized payloads and races; manual form recovery.
- Dependencies: T06. Scope: M; likely files: form class, frontend script, form tests, frontend test fixture, form documentation.

### T08 — Separate testimonial display from schema eligibility
- [ ] Acceptance: imported external-source ratings and self-serving business ratings are excluded where Google policies require it; a filtered showcase cannot misrepresent a whole-product aggregate; schema matches visible eligible content and avoids duplicate entities.
- Verify: positive/negative JSON-LD fixtures, WooCommerce coexistence and manual Rich Results Test for approved examples. Do not claim guaranteed search stars.
- Dependencies: T06 and an accepted provenance model. Scope: M; likely files: render class, shortcode class, WooCommerce class, schema tests, schema policy document.

### Checkpoint A — Trustworthy baseline

- [ ] T02–T08 acceptance criteria pass; no unexplained privacy/data-loss issues remain.
- [ ] Upgrade, export/erase, deletion choices and representative forms have been manually verified.
- [ ] Cameron reviews the release candidate; no automatic client rollout.

## Measured performance and native UX

### T09 — Establish reproducible review-volume benchmarks
- [ ] Acceptance: deterministic 1k/10k/100k fixtures and representative product/collection queries report p50/p95, memory and query counts with cache state and environment declared.
- Verify: repeat benchmark runs on the documented reference host and a shared-hosting profile; store baseline results without presenting targets as achievements.
- Dependencies: T08. Scope: M; likely files: fixture generator, benchmark runner, benchmark report, CI benchmark configuration.

### T10 — Decide the storage strategy from evidence
- [ ] Acceptance: an ADR compares the current post/meta model with indexed dedicated tables using T09 results; it records compatibility, migration and rollback requirements without prematurely implementing a second engine.
- Verify: review the evidence against agreed budgets and realistic demand; approve or defer the migration decision.
- Dependencies: T09. Scope: S; files: proposed storage ADR and benchmark report.

### T11 — Remove measured pagination/cache bottlenecks
- [ ] Acceptance: stable ordering prevents duplicate/skipped pages; public query bounds limit expensive requests; affected-item cache invalidation and pagination meet the accepted benchmark budget.
- Verify: pagination/concurrency/cache regression tests and the same T09 workloads before/after. Split into separate PRs if more than five files are required.
- Dependencies: T10. Scope: M; likely files: data class, shortcode/public query handler, query tests, benchmark report.

### T12 — Add a basic server-rendered review block
- [ ] Acceptance: the free block renders an existing collection with layout options and a useful empty state; existing shortcodes remain compatible; assets load only on pages that need them.
- Verify: block/classic-theme frontend fixtures and no-JavaScript rendering; inspect unrelated pages for added assets.
- Dependencies: T08. Scope: M; likely files: block registration, block metadata, block rendering adapter, bootstrap, block tests.

### T13 — Give the block reusable design controls
- [ ] Acceptance: an approved Figma component set maps to understandable spacing/type/colour controls; mobile/tablet/desktop, RTL and keyboard states work without proprietary theme assumptions.
- Verify: 375/768/1440-px screenshots; keyboard/screen-reader and automated accessibility checks; translations and long-content fixtures.
- Dependencies: T12 and Bianca's design acceptance. Scope: M; likely files: block editor UI, block styles, frontend styles, browser fixtures, component documentation.

### T14 — Automate upgrades and critical browser flows
- [ ] Acceptance: a versioned fixture upgrades without data loss; create/edit/moderate/import/export/display flows pass across the selected compatibility matrix; failed upgrades have a tested recovery path.
- Verify: CI plus an independently reviewed manual QA checklist; record environment coverage and exclusions explicitly.
- Dependencies: T05, T07, T11, T13. Scope: M; likely files: browser runner configuration, workflow, browser flow tests, upgrade fixtures, QA checklist.

### T15 — Run an opt-in free pilot
- [ ] Acceptance: authorized participants install on 10–20 varied sites before expanding to 100; onboarding failures, retention and support effort are recorded; findings have owners.
- Verify: pilot scorecard, support log and release-error review over an agreed observation window. Obtain approval before contacting participants or installing on their sites.
- Dependencies: checkpoint A and T14. Scope: S; files: pilot protocol, scorecard, support runbook.

### Checkpoint B — Evidence to expand the free product

- [ ] Pilot demonstrates retained use, useful recommendations and specific demand for advanced free workflows.
- [ ] Performance claims have published test conditions; compatibility/support limitations are explicit.
- [ ] Advanced feature scope, maintenance budget and community support ownership are agreed.

## Advanced free workflows

### T16 — Add custom collection fields
- [ ] Acceptance: administrators define supported custom fields without code; server-side validation, output escaping, privacy export/erasure and portable CSV behaviour apply to every field; no licence or account is required.
- Verify: required/optional fields, malformed payloads, stored XSS attempts, privacy requests and field-definition import/export fixtures.
- Dependencies: T03, T04, T07 and the approved field specification. Scope: M per PR; proposed files: field definition model, form renderer, submission validation, field tests, privacy adapter. Split storage/import extensions into a follow-up slice if needed.

### T17 — Add reusable design presets
- [ ] Acceptance: users save/apply/export a preset that maps to the approved design tokens; importing one cannot execute code or overwrite unrelated settings; all presets are free.
- Verify: cross-site round trip, unknown version/field handling and mobile/tablet/desktop screenshots.
- Dependencies: T13. Scope: M; proposed files: preset schema, preset controls, import/export adapter, preset tests, template documentation.

### T18 — Add evidence-backed WooCommerce purchase verification
- [ ] Acceptance: an eligible product/order/customer relationship controls the verified-purchase badge; manual/imported flags cannot impersonate it; refund/deletion/revocation behaviour is documented.
- Verify: product variations, guest buyers, refund/order-state changes, duplicate submissions and forged order identifiers.
- Dependencies: T08 and the approved WooCommerce specification. Scope: M; likely files: WooCommerce adapter, review evidence data boundary, form handler, WooCommerce tests, badge policy document.

### T19 — Add reusable Elementor widgets
- [ ] Acceptance: free widgets expose the approved design controls, gracefully handle missing Elementor/plugin dependencies and render consistently with native layouts.
- Verify: the selected minimum/current Elementor versions, theme fixtures, RTL and responsive/accessibility checks; no fatal errors when Elementor is disabled.
- Dependencies: T13 and the approved Elementor specification. Scope: M; proposed files: Elementor registration, review widget, controls mapping, widget styles, integration tests.

### T20 — Prove one order-based review-request workflow
- [ ] Acceptance: an approved order event queues one eligible invitation; retries are bounded/idempotent; suppression, refund and cancellation rules work; sending requires authorized configuration.
- Verify: scheduler tests with failed/retried jobs and order-state changes; inspect messages without sending to real customers during development.
- Dependencies: T07, T18. Scope: M per PR; proposed files: request policy, scheduler adapter, mail template, queue tests, workflow settings. Split custom form fields and delivery-provider integration into separate specifications.

### T21 — Complete the support and distribution package
- [ ] Acceptance: install/upgrade/recovery guides, private diagnostics, security reporting and support ownership exist; the single free package and notices follow WordPress.org policy; privacy/support scope is clear and no payment, licence or account is required.
- Verify: an unfamiliar tester completes onboarding and restores a failed import; rehearse incident triage and withdrawal of a bad release. Submission, publication, outreach and spending each require approval.
- Dependencies: T15–T20 as applicable. Scope: M per documentation/release PR; likely files: readme, support guide, SECURITY.md, release checklist, packaging verification.

### T22 — Validate one external connector before shipping it
- [ ] Acceptance: choose one source, establish approved API access and permitted attribution/retention, and demonstrate resumable sync with provenance and revocation; document quotas and operating cost.
- Verify: sandbox/authorized account proof, rate-limit/revocation/source-deletion cases. This is a discovery slice, not a promise of Google/Facebook/Trustpilot support.
- Dependencies: checkpoint B and the source connector specification. Scope: M; proposed files: source adapter prototype, connector tests, API permission record, connector ADR.

### T23 — Prepare an honest Ashbi credibility showcase
- [ ] Acceptance: a polished demo, screenshots and a concise product page present shipped features, tested compatibility and real benchmark evidence; fictional examples are labelled; Ashbi credits are restrained and public-facing attribution is opt-in.
- Verify: first-time-user walkthrough, accessibility/responsive QA, link checks and a claim-by-claim evidence review. Obtain permission for client case studies; no automatic outreach, publishing or tracking.
- Dependencies: T13, T15, T21. Scope: M per content/design PR; proposed files: demo brief, screenshot checklist, product copy, evidence/claims checklist, About panel design.

### Checkpoint C — Wider launch decision

- [ ] Pilot users confirm value; onboarding, recovery and community support processes work; no open critical/high release blockers.
- [ ] Retained use, recommendations, measured quality and maintenance/support capacity justify expansion.
- [ ] Cameron explicitly approves the release and distribution actions. Later integration catalogues, advanced forms, service infrastructure and storage migrations receive their own small tasks after their module specifications are accepted.
