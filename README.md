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
- **CSV import/export**: the browser sends large files in batches of 100 rows.
- **Structured data** for products and software only; optional **WooCommerce tab**.

## Shortcodes

```text
[ashbi_testimonials collection="store-reviews"]
[ashbi_testimonials product="current" layout="list" summary="1" load_more="1"]
[ashbi_testimonials collection="acme-crm" layout="slider" min_rating="4"]
[ashbi_rating collection="acme-crm" style="inline"]
[ashbi_testimonial_form collection="store-reviews" product="current"]
```

Every attribute is listed in `readme.txt` and in the reference table under **Testimonials → Settings**.

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

