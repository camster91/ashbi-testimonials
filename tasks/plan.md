# Ashbi Testimonials: a fully free, professional WordPress plugin

Direction confirmed by Cameron on 23 October 2026: all planned Ashbi Testimonials features are free. This replaces the earlier free/Pro split and proposed pricing. Detailed module specifications and release actions remain separate implementation work.

## Purpose

Earn credibility for Ashbi Design by shipping a useful, attractive and dependable open-source product that businesses and agencies are comfortable recommending. Success means retained use, honest recommendations, demonstrable quality and relevant awareness of Ashbi's design and development work.

Every feature in this roadmap will be available without an Ashbi payment, licence key, subscription, account, activation quota or installation cap. Reviews stay on the site's own WordPress installation. No upsell screens, withheld fixes, forced attribution, advertisements or mandatory telemetry. Integrations cannot make a third-party vendor's paid service free: clearly disclose any provider requirements, limits and costs before a user connects it, and offer native/local alternatives where practical.

The ambition is broad adoption. Millions of installations remains an adoption goal, not a benchmark result or a claim to place on the download page. Prove the product with 10–20 opt-in pilot sites, then 100, 1,000 and 10,000, and expand only when reliability and support capacity justify it.

## Confirmed starting point

- Repository: https://github.com/camster91/ashbi-testimonials
- Main at inspection: `af84ef7f8cde07d7d37baeb5a246687e9c64bf8b`; the four first-review fixes are merged.
- PR #3 contains six additional fixes and regression coverage. It remains open, with all six CI jobs passed on `b3a2157ce5b6dd50792cf243fa61da03180919ec`: https://github.com/camster91/ashbi-testimonials/pull/3
- Published release remains v1.0.0. Merged changes and the PR do not replace that download automatically.
- Existing functionality: collections; six layouts; shortcodes; public moderated forms; CSV import/export; rating summaries; optional WooCommerce tab; product/software structured data.
- Existing storage: a private custom post type, collection taxonomy and post metadata. PHP minimum is currently 8.2; WordPress minimum is 6.6.
- Existing CI includes PHP syntax, JavaScript syntax, Plugin Check, packaging, WordPress database tests, WooCommerce and single/multisite uninstall tests. Full browser, performance and hostile-request coverage is not established by those passes.
- No verified adoption, customer-demand study, high-volume benchmark or sustainable support capacity has been supplied.

## Product decisions

- One free plugin and one public roadmap; modular code does not imply paid editions.
- Audience: agencies, service businesses and WooCommerce stores. Native WordPress blocks and Elementor controls should make the product easy to hand over to clients.
- Cameron owns product/engineering acceptance; Bianca owns the reusable visual system and UX acceptance. No client is automatically enrolled in a pilot or contacted.
- Publish useful documentation, migration instructions, a public changelog, transparent compatibility information and reproducible performance evidence.
- Keep support sustainable through a knowledge base, community issue templates and clear scope. Do not promise dedicated support or guaranteed response times for every installation.
- No licensing, checkout, billing, premium downloads, customer entitlements or centralized review hosting work is required for this product direction.

## Proposed capability map

Module boundaries and technical contracts need review before implementation. The fully free direction is already decided.

| Module ID | Responsibility | Depends on |
|---|---|---|
| review-core | Review identity, collections, provenance, queries, import/export and migrations | — |
| trust-controls | Capabilities, consent, moderation, privacy, abuse controls and verified-state rules | review-core |
| display | Layouts, native blocks, Elementor controls, accessibility, design tokens and schema eligibility | review-core, trust-controls |
| woo-commerce | Product identity, purchase verification, native/custom review policy and product schema | review-core, trust-controls, display |
| workflows | Custom forms, review requests, templates and reusable agency presets | review-core, trust-controls, display; woo-commerce for order requests |
| source-connectors | Authorized source synchronization, attribution and provenance | review-core, trust-controls |
| quality-operations | Tests, benchmarks, releases, documentation, support and incident response | Tests each applicable module; not a frontend service dependency |

Build order: stable reviews and trust → polished display → real-site pilot → advanced free workflows → selected permitted connectors. Quality and support preparation apply at every stage.

## Included features and sequencing

| Area | Included free | Delivery order |
|---|---|---|
| Review management | Collections, moderation, search, filters, complete portable exports and recovery | Foundation |
| Display | Existing six layouts, responsive styles, native blocks, CSS tokens and accessible controls | Foundation and UX |
| Design workflows | Elementor widgets, reusable presets, templates and site-to-site settings transfer | After native UX |
| Collection | Custom forms, custom fields, optional evidence and spam protection | After trust controls |
| WooCommerce | Product reviews, evidence-backed purchase verification, eligible schema and order-based requests | Integration slices |
| Source integrations | Carefully chosen, permitted imports/sync with visible source attribution and error handling | After API feasibility |
| Product operations | Free updates, privacy tools, documentation, diagnostics and community support | Every release |

Deferred scope: video hosting/transcoding, a hosted cross-site review database, a large connector catalogue and generative AI features. These are not paywalled features; they require separate demand, cost and privacy evidence before entering the product.

## What scale means

Each site's PHP and database serve its own reviews. Normal viewing, collection and moderation must not depend on an Ashbi server. Use WordPress.org's update route once listed, with versioned GitHub releases for development/review. No licence server or payment service is needed.

Per-site review volume is a separate engineering concern. Measure postmeta joins, deep pagination, cache invalidation, imports and queues. Retain the present storage until benchmark evidence justifies indexed dedicated tables. Any migration needs reconciliation, resumability, interruption recovery, backups and a tested rollback. Keep shortcodes and extension hooks compatible independently of storage.

## Release stages and evidence gates

| Stage | Work | Exit evidence |
|---|---|---|
| 0. Corrected baseline | Final review/merge approval for PR #3; candidate package, version consistency and upgrade/rollback rehearsal | Installable candidate preserves data; no unexplained regressions |
| 1. Trustworthy core | Privacy tools, explicit data retention, capability matrix, submission outcomes, provenance and schema rules | Unauthorized access is blocked; valid reviews are never falsely acknowledged; maintenance does not destroy reviews |
| 2. Professional UX | Approved Figma components, native blocks, clear onboarding, responsive layouts, translations and accessibility | A first-time tester publishes a good-looking review section without custom code; representative device/keyboard checks pass |
| 3. Measured pilot | Benchmarks and opt-in deployment to 10–20 varied sites, then 100 | Repeatable evidence, useful retained adoption and an affordable support workload |
| 4. Advanced free workflows | Elementor, custom forms, reusable presets, purchase verification and order requests | Each feature passes its own real-world acceptance tests and works without payment/account checks |
| 5. Credible public launch | WordPress.org submission, live demo, honest screenshots, documentation, public changelog and security contact | Publication approval; independently usable onboarding/recovery; no unsupported marketing claims |
| 6. Expansion | 1,000 → 10,000 → 100,000 → 1,000,000 active sites | Reliability, maintenance and support capacity increase with adoption; no guaranteed adoption dates |

## Technical quality bar

### Review integrity, privacy and security

- Distinguish manually asserted provenance, imported source identity and independently verified purchase evidence. A checkbox or CSV value must not confer “verified purchase.” Preserve evidence identifiers and invalidate badges when evidence no longer supports them.
- Define moderation roles and object-level permissions; cover anonymous, subscriber, author, editor, administrator and multisite actors. Admin capability filters cannot bypass object restrictions inadvertently.
- Bound public request sizes, query limits, pagination and import batches. Test malformed arrays/JSON, XSS, SQL injection, CSRF, replay, concurrent rate limits, proxies and cache behaviour.
- Test the existing under-three-second submission path: a valid review must not receive a success notice without being saved. Spam rejection must not falsely claim receipt to legitimate users.
- Register WordPress personal-data exporter and eraser callbacks. Document consent, email use, retention, log redaction and any third-party transfers. Separate marketing permissions from permission to publish a review.
- Recommend retaining review data on plugin deletion unless the administrator explicitly opts into deletion; test both behaviours on single and multisite. Deactivation never deletes content.
- Security contact, vulnerability reporting process, dependency inventory, code review and a release incident runbook are launch requirements.

### Rendering, schema and integrations

- Preserve existing shortcodes; server-render basic content. Conditionally load assets only where needed; avoid a frontend jQuery requirement, remote fonts and forced external image/avatar lookups.
- Native blocks first; free Elementor widget integration next, with optional compatibility testing for Elementor Pro. Use reusable Figma components, CSS variables and core WordPress editing conventions.
- Product-wide aggregate ratings must remain clearly distinguished from curated or filtered testimonials. Do not let a “five stars only” display manufacture a whole-product rating.
- Separate displayable imported reviews from reviews eligible for Google structured data. Google prohibits aggregating ratings from other websites; self-serving LocalBusiness/Organization review snippets are also restricted. Schema must identify a real eligible item and match visible information. Do not promise ranking gains or search stars.
- WooCommerce must define one consistent ownership policy for native reviews versus Ashbi reviews, deduplication during migrations, variations, deleted products, verified purchases and schema. Avoid duplicate Product entities.
- External connectors require current API permission, attribution, retention, quotas, OAuth recovery and source deletion policies. Google Business Profile access requires project approval; do not advertise an unlimited importer before proving access and permitted use.
- Review request queues need per-site rate caps, retries/backoff, idempotency, suppression/unsubscribe handling where applicable and safe order/refund behaviour. Use established available WordPress/WooCommerce scheduling capabilities before adding infrastructure.

### Proposed performance budgets — targets, not measured results

Start with deterministic fixtures at 1,000, 10,000 and 100,000 reviews, 20-item pages and representative multi-collection/product filters. Add a million-row experiment only if demand justifies it. Record PHP/WordPress/database versions, 2-vCPU/4-GB baseline host, database collation, cache state, concurrency and data distribution; validate on shared hosting as a separate profile.

Initial candidate budgets: warm published-review query/render p95 under 250 ms and cold under 800 ms on the declared 100,000-review reference profile; frontend JS at most 15 KB gzip and CSS at most 20 KB gzip excluding images; no plugin assets on unrelated pages; no new checkout/cart queries when the review feature is unused. Instrument p95/error rates and query counts, rather than relying on an average or an unqualified “fast.” Adjust targets only with recorded evidence and a reason.

Imports must resume safely after interruption, reject duplicate rows according to a documented identifier policy and report failures without silently losing data. Deep pagination requires a measured decision on stable ordering and cursor/keyset approaches. Cache invalidation should affect the changed product/collection rather than every review globally when practical.

### Compatibility and QA

Maintain a published minimum/supported matrix. Retain currently claimed minimums until a deliberate policy change is tested; re-evaluate the PHP floor against maintained versions and user demand rather than casually supporting obsolete runtimes.

Test minimum and current supported WordPress/PHP combinations, MySQL and MariaDB, persistent object cache on/off, full-page caching, single/multisite, WooCommerce and Elementor. Include core block/classic themes plus representative supported third-party themes; RTL, translations, long names, Unicode, screen readers, keyboard operation, reduced motion and high-contrast modes.

Representative viewport checks: 375-px mobile, 768-px tablet and 1440-px desktop. Test forms, validation/recovery, links, pagination, moderation, editing, CSV round trips, upgrades, uninstall choices and offline operation. Target WCAG 2.2 AA for plugin-controlled interfaces; automated checks alone do not establish conformance.

Release gates include PHP/JS tests, WordPress integration, security review, static analysis, reproducible package contents, version consistency, accessibility checks, browser coverage and upgrade/rollback tests. Add checks incrementally with useful fixtures, not test-count targets.

## Credibility and distribution

The product should demonstrate Ashbi's work directly: thoughtful typography, restrained layouts, clear forms, accessible interactions, careful engineering and useful documentation. A polished demo should show several realistic use cases: agency testimonials, local services and WooCommerce products. Sample testimonials must be explicitly labelled fictional; public case studies and customer quotes require permission.

Brand placement should be tasteful: plugin author metadata, readme, an About Ashbi panel within this plugin's own admin screen, documentation and the product/demo website. Keep it unobtrusive and dismissible where appropriate. Public-facing pages get no Ashbi link, logo or “powered by” credit by default; a user may explicitly opt into an optional credit. Do not add unrelated dashboard notices, hidden backlinks, lead capture or tracking.

Credibility assets to prepare: reusable Figma components; clear screenshots; no-login demo; a concise installation guide; a migration/recovery guide; accessibility and compatibility notes; measured benchmark reports; SECURITY.md; changelog and a public roadmap. The product page should accurately describe only shipped features and tested compatibility.

Seek distribution through WordPress.org discovery, helpful tutorials, migration guides, agency recommendations and opt-in case studies. Requests for feedback must be neutral; no gating by rating or invented endorsements. Ashbi services can have a separate, clearly identified link in the product's About/documentation surfaces, without interrupting plugin use or requiring contact details.

## Success measures and maintenance

Track retained active installations, first successful review section, onboarding completion, 30/90-day retention, upgrade failures, defects, support requests per 100 sites, resolution time, honest recommendations, voluntary demo/documentation referrals and explicitly attributed agency enquiries. Use available legitimate aggregates and opt-in research; do not silently instrument client websites or infer consent from installation.

There is no paid conversion, licence activation, renewal or average-revenue target for this plugin. Cameron can assess a maintenance budget against real, permission-based referral evidence and community value. Set a sustainable maintenance/support commitment before expanding distribution. Free software still uses development time and hosting/email resources; publish support boundaries without weakening data ownership or basic product quality.

Illustrative support sensitivity, not a forecast: at one million active sites, if 0.1% need help monthly, that is 1,000 requests; ten minutes each is about 167 support hours. Invest in self-service recovery, issue templates, a triage owner and reproducible diagnostics before mass adoption.

## Ownership and implementation boundaries

Cameron: product decisions, engineering acceptance, maintenance budget and release approval. Bianca: visual system and UX acceptance. AI-assisted implementation: small reviewed PRs, meaningful fixtures and documentation. Add independent security/accessibility review when complexity or launch exposure warrants it.

The all-free direction is approved by the user's instruction. Remaining module boundaries and detailed acceptance specifications are proposals. This document does not authorize production installs, PR merges, client outreach, WordPress.org submission, release publication, vendor spending or a storage migration. Work through one approved module specification at a time.

## First execution sequence

See `tasks/todo.md`: corrected baseline → trust/data protection → measured performance and native UX → opt-in pilot → advanced free workflows → credible launch → selected connectors. Licensing, billing and premium package work have been removed. Estimate delivery after module specifications and support capacity are agreed.

## Sources checked on 23 October 2026

- Repository and PR #3: https://github.com/camster91/ashbi-testimonials and https://github.com/camster91/ashbi-testimonials/pull/3
- WordPress.org detailed plugin guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- WordPress privacy integration: https://developer.wordpress.org/plugins/privacy/
- Google review snippet policies: https://developers.google.com/search/docs/appearance/structured-data/review-snippet
- Google Business Profile API setup: https://developers.google.com/my-business/content/basic-setup

External policies can change and must be rechecked when the related integration is implemented. Performance figures above are proposed budgets, not measured claims.
