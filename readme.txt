=== Ashbi Testimonials ===
Contributors: camster91
Tags: testimonials, reviews, product reviews, star rating, shortcode
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect, organise and display testimonials and reviews for your store, products, software or services with a clean editor and simple shortcodes.

== Description ==

Ashbi Testimonials collects, organises and displays directly collected reviews in your WordPress database. All features are free; no licence key, Ashbi account, telemetry or mandatory public credit.

* Collections group reviews: store reviews, product reviews, software reviews, service reviews or general testimonials. Create as many as you need.
* Attach any review to a WooCommerce product, page or post, or to a free-text label such as a plan name or SKU.
* A clean editor with search, rating and status filters, bulk approve, feature, trash and restore, and a slide-in form.
* Six layouts: grid, masonry, slider, list, spotlight and compact. Light, dark or automatic themes and an accent colour.
* A visual shortcode builder with a live preview.
* Rating summaries with average stars, review count and a five-to-one star breakdown.
* A public review form. Submissions wait for your approval and can email you.
* CSV import in batches (thousands of rows) and CSV export, including portable collection assignments and settings.
* Optional review structured data for products and software, and an optional WooCommerce product tab.

Testimonials are stored as a private post type, so large collections use the WordPress database indexes. Rating summaries are calculated with a single grouped query and cached until something changes.

== Shortcodes ==

`[ashbi_testimonials collection="store-reviews"]`
`[ashbi_testimonials product="current" layout="list" summary="1" load_more="1"]`
`[ashbi_testimonials collection="acme-crm" layout="slider" min_rating="4" show="avatar,rating,company"]`
`[ashbi_rating collection="acme-crm" style="inline"]`
`[ashbi_testimonial_form collection="store-reviews"]`

Attributes for ashbi_testimonials: collection, product (ID or current), subject, ids, layout, columns, limit, orderby (date, rating, featured, random), order, min_rating, featured, verified, show, words, summary, load_more, theme (light, dark, auto), accent, heading, schema, schema_name, class, label.

Theme developers can call `abt_render_testimonials( array( 'collection' => 'store-reviews' ) )`.

== Installation ==

1. Upload the plugin ZIP through Plugins, Add New, Upload Plugin and activate it.
2. Open Testimonials and pick what you want to collect.
3. Add testimonials, import a CSV, or place the review form on a page.
4. Click Get shortcode, choose a layout and paste the shortcode into any page, post or widget.

== Frequently Asked Questions ==

= Can I show reviews for one product among thousands? =
Yes. Attach each review to its product, then use product="current" on your product template or product="123" anywhere. Reviews without a page can use a label: subject="SKU-1042".

= Does the form work on cached pages? =
Yes. It uses a signed target-bound token without a time expiry instead of a nonce, a hidden honeypot field and a limit of five submissions per hour per visitor. Every submission is held as pending.

= Will it add star ratings in search results? =
For directly collected product/software reviews, it can add AggregateRating structured data when the aggregate is visible and the view is not curated. Existing/manual/CSV reviews stay visible but require a manager to confirm direct provenance. External source links/platform reviews are excluded; a mixed-provenance view adds no Ashbi aggregate. It never adds review markup for your own business, which search engines ignore. When the WooCommerce testimonials tab is enabled, eligible directly collected ratings are combined with native ratings in the existing Product schema. Whether stars appear is decided by the search engine.

= What happens when I delete the plugin? =
Deactivation and deletion keep reviews, collections and settings by default, including on existing installations. An administrator can enable permanent deletion in Testimonials > Settings before deleting the plugin. This preference applies separately to each multisite site. Media Library files are kept. Export a CSV backup before enabling deletion.

= How do privacy requests work? =
Use WordPress Tools > Export Personal Data or Erase Personal Data with the email stored on the review. Published, pending, draft and trashed matching reviews are included. Erasure permanently removes entire matching reviews and their metadata. Media Library files, backups and external copies need separate review. The plugin adds suggested text to the WordPress privacy policy guide for administrators to adapt.

== Changelog ==

= 1.1.0 =
* Free native Testimonials, Rating Summary and Review Form blocks and an optional free Elementor widget.
* Reusable design presets with validated JSON portability; custom collection questions with private/public answer controls and CSV round trips.
* Native WordPress privacy export/erasure, default data retention on deletion and explicit per-site cleanup.
* Reliable form outcomes, bounded inputs, concurrent rate protection, exact retry deduplication and accessible browser recovery.
* Conservative review provenance and schema eligibility; deterministic bounded public pagination.
* Separate order-backed Verified purchase badge and default-off customer-requested WooCommerce reminder workflow.
* Expanded real WordPress, WooCommerce HPOS/legacy, Elementor, multisite, browser/accessibility and upgrade/rollback checks.


= 1.0.0 =
* First release.
