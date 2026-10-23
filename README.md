# Ashbi Testimonials

A WordPress plugin for testimonials and reviews that scales from a handful of client quotes to thousands of products with their own reviews. Companion to [Ashbi Mega Menu](https://github.com/camster91/ashbi-mega-menu), with the same admin look and conventions. Licensed under GPL-2.0-or-later.

## Features

- **Collections** for store, product, software, service or general testimonials. Each has its own shortcode, default layout and accent colour.
- **Attach reviews** to a WooCommerce product, page or post, or a free-text label (plan, SKU, project).
- **Clean editor**: search, rating/status/product filters, bulk approve/feature/trash/restore, slide-in form with star picker, media-library avatar and product search. Press `N` to add a testimonial.
- **Six layouts**: grid, masonry, slider (scroll-snap, works without JS), list, spotlight, compact. Light, dark or auto theme.
- **Shortcode builder** with live preview.
- **Rating summary** with average, count and per-star breakdown, or an inline badge.
- **Review form** with moderation, honeypot, signed cache-safe token and rate limiting. Tokens are bound to the collection and product without a time expiry; submissions always require moderation. Purge page caches after rotating WordPress salts.
- **CSV import/export**: the browser sends large files in batches of 100 rows. Exports include collection names, slugs and display settings; imports restore missing collections by slug and keep existing settings. A selected import collection is added alongside the restored groups.
- **Structured data** for products and software only; optional **WooCommerce tab**. When the tab and schema are enabled, its ratings are combined with native WooCommerce ratings in the existing Product entity.

Schema is conservative: existing/manual/CSV reviews remain visible but need confirmed direct provenance before they can contribute to Ashbi aggregate markup. Public forms record direct collection; CSV files cannot grant that status automatically. Managers can confirm provenance in the review editor. External source links and sources other than Website/Email/Survey remain ineligible. A mixed-provenance view emits no Ashbi aggregate; WooCommerce retains its native aggregate. Shortcode markup requires a visible summary and an uncurated, item-specific view. IDs, minimum-rating, featured/verified filters, subject subsets and random showcases do not emit aggregates. Search engines decide eligibility and display.

## Free by design

All plugin features are free under GPL-2.0-or-later. No paid edition, license key, mandatory account or forced attribution. The [professional product roadmap](tasks/plan.md) tracks the work toward broad adoption.

Form fields are validated on the server as well as in the browser. Exact retries from the same connection within an hour reuse the saved review. A short processing lock prevents simultaneous requests from racing the five-review limit. Shared connections share this limit; forwarded IP headers are not trusted.

## Privacy and retention

WordPress **Tools → Export Personal Data / Erase Personal Data** includes reviews matching the request email, including unpublished and trashed reviews. Requests use the native WordPress verification workflow. Erasure permanently deletes whole matching reviews and their metadata in batches of 100. Records without a matching email need manual review. Shared Media Library files are retained and reported for separate review; backups and external copies are outside this tool. Adapt the suggested privacy policy text to the site’s actual practices.

Deleting or deactivating the plugin keeps data by default, including for existing installations. Administrators may explicitly enable **Permanently delete this site’s plugin data** in Settings before deleting the plugin. Cleanup applies independently to each site on multisite and preserves Media Library files. Export a backup before enabling deletion.

## Shortcodes

```text
[ashbi_testimonials collection="store-reviews"]
[ashbi_testimonials product="current" layout="list" summary="1" load_more="1"]
[ashbi_testimonials collection="acme-crm" layout="slider" min_rating="4"]
[ashbi_rating collection="acme-crm" style="inline"]
[ashbi_testimonial_form collection="store-reviews" product="current"]
```

Every attribute is listed in `readme.txt` and in the reference table under **Testimonials → Settings**.

Review dates must be today or earlier in the WordPress site time zone. Date-only entries use midnight so saving today never schedules them for noon.

## Storage

Testimonials are a private post type (`abt_testimonial`) grouped by a private taxonomy (`abt_collection`). Meta keys are prefixed `_abt_`. Rating stats use one grouped SQL query, cached in a transient that is invalidated on every change. Managing testimonials requires `edit_others_posts` by default (filter `abt_capability`); settings require `manage_options`.

## Hooks

- `abt_capability` — capability for managing testimonials.
- `abt_reviewable_post_types` — post types offered in the product picker.
- `abt_card_html` — filter a rendered card.
- `abt_schema` — filter structured data before output.
- `abt_submitted` — action after a public submission is saved.

## Requirements

WordPress 6.6+, PHP 8.2+. WooCommerce is optional.

