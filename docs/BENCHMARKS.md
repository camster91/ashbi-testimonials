# Reproducible volume evidence — 23 October 2026 (Toronto)

These are observed disposable GitHub Actions results, not hosting/adoption guarantees. The baseline is [run 40045769436](https://github.com/camster91/ashbi-testimonials/actions/runs/40045769436); the one-join pivot is [run 40046735543](https://github.com/camster91/ashbi-testimonials/actions/runs/40046735543). Full recorded metrics/environment are in [baseline JSON](benchmark-baseline.json) and [pivot JSON](benchmark-pivot.json). The runner executed exact recorded revisions; the baseline PR event used a synthetic merge commit.

Both used PHP 8.4.27, WordPress 7.1.3, MariaDB 12.3.3 on Linux/ubuntu-latest, with no active external object-cache flag. CPU/RAM entitlement and storage hardware were not recorded. Each deterministic fixture contains 1k, 10k or 100k synthetic reviews, 10 collections and 100 synthetic product pages, plus one postmeta value per plugin field. There are 20 samples per workload/cache condition; p95 uses the nearest-rank sample. WP-cold clears WordPress object cache and invalidates plugin summaries; DB buffer/filesystem caches are not reset. Warm means the same PHP process, not a new HTTP request or a Redis deployment. Peak PHP memory is absolute process peak and includes WordPress/fixture overhead, not incremental request allocation.

The fixture bulk-inserts only in a guarded fresh disposable database. Measured calls use production query/summary APIs. No real client data, concurrent-traffic test, total-page/Core Web Vitals measurement or shared-hosting rehearsal is included. Runner-to-runner variation exists: observed differences are evidence for direction, not a guaranteed speedup.

## Uncached p95 (milliseconds)

| Reviews | Workload | Baseline | Pivot |
| ---: | --- | ---: | ---: |
| 1,000 | collection_date | 3.501 | 6.038 |
| 1,000 | product_date | 7.843 | 10.170 |
| 1,000 | rating_sort | 6.654 | 7.340 |
| 1,000 | featured_sort | 7.439 | 9.554 |
| 1,000 | search | 6.138 | 8.917 |
| 1,000 | page_100 | 0.706 | 0.815 |
| 1,000 | stats_global | 2063.799 | 15.515 |
| 1,000 | stats_collection | 205.703 | 34.464 |
| 10,000 | collection_date | 5.710 | 7.049 |
| 10,000 | product_date | 11.296 | 16.489 |
| 10,000 | rating_sort | 23.748 | 28.783 |
| 10,000 | featured_sort | 21.809 | 26.983 |
| 10,000 | search | 13.983 | 19.217 |
| 10,000 | page_100 | 8.146 | 11.799 |
| 10,000 | stats_global | 361.224 | 152.441 |
| 10,000 | stats_collection | 45.333 | 17.791 |
| 100,000 | collection_date | 25.362 | 30.305 |
| 100,000 | product_date | 957.749 | 813.597 |
| 100,000 | rating_sort | 943.880 | 965.388 |
| 100,000 | featured_sort | 808.258 | 1032.315 |
| 100,000 | search | 99.120 | 138.603 |
| 100,000 | page_100 | 41.468 | 48.860 |
| 100,000 | stats_global | 5133.865 | 2758.940 |
| 100,000 | stats_collection | 1353.060 | 967.073 |

The added product-summary workload has no baseline measurement: pivot p95 is 9.112/13.699/1385.632 ms at 1k/10k/100k. The global-summary p95 at 100k changed from 5133.865 to 2758.940 ms in these runs. At 10k its pivot p95 is 152.441 ms. At 100k, cold product and rating/featured sorts still approach or exceed one second. Cached summaries are much faster in the recorded same-process condition; do not quote those numbers as an end-to-end page speed.

The pivot removes independent provenance joins, aggregates each matching review once, preserves overlapping-collection counts and conservatively computes schema eligibility. Separate real WordPress fixtures check all filters, unrated reviews, IDs, product/subject, manual/imported provenance and unpublished records. No core database table or index is altered.

## Decision and remaining performance gate

Retain post/meta as the canonical storage for this candidate; see [storage ADR](STORAGE-ADR.md). The query change improves the measured summary workload but does not establish 100k cold-path readiness. Public browsing now has deterministic ties and hard query bounds. No accepted production latency budget or site-specific cache configuration was supplied, so broad scale claims remain blocked. Before advertising high-volume suitability, repeat on a named shared host with cross-request cache states, concurrent submissions/reads, realistic product/collection distributions and a maintainer-approved latency budget. A dedicated indexed read model and narrower invalidation can be evaluated using this fixture; do not claim they are already implemented.
