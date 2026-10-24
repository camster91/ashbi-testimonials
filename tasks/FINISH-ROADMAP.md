# Delivery status — 23 October 2026 (Toronto)

Cameron instructed “Finish all”. The all-free implementation is in a reviewable stacked PR chain; no new release or live client rollout has been performed.

| Area | Delivery and evidence | Remaining gate |
| --- | --- | --- |
| Privacy/retention/permissions | #4/#5 merged; native export/erase, per-site cleanup, role/nonce/private title tests | Site-specific policy and backup handling |
| Forms/trust/schema | #6/#7; bounded inputs, retries, conservative provenance/schema. #13 fixes a discovered core add_option race with true atomic inserts and repeated parallel tests | Merge the full chain, including #13 fixes |
| Blocks/Elementor | #8; three native blocks, free widget, real Elementor/browser matrix | Bianca design acceptance and representative theme/assistive-technology pilot |
| Design portability | #9; bounded named presets, validated JSON import/export | Full-chain release checks |
| Custom questions | #10; public/private/required fields, manager controls, snapshots, CSV and privacy round trips. #13 handles multi-collection limits/retired answers | Full-chain release checks |
| Purchase evidence | #11; separate authenticated order-backed badge, refund/revocation/privacy, legacy and HPOS checks | Guest purchases deliberately remain ordinary reviews without authenticated proof |
| Requested reminders | #12; default off, administrator configuration plus customer opt-in, seven-day native cron, intercepted mail tests. #13 fixes consent generations, variable products and cancellation/erasure/retry races | No real customer sending is authorised during development |
| Performance/browsing | #13; real 1k/10k/100k benchmark, one-join summary pivot, stable ties and bounded public requests; docs/BENCHMARKS.md and STORAGE-ADR.md | 100k cold paths remain slow; real hosting/concurrency budget and any indexed projection are future measured work |
| Upgrade/recovery/support | #13; actual v1.0.0 source → candidate → binary rollback → reinstall rehearsal, private diagnostics and operations/security/support guides | Confirm private security-reporting channel and support capacity |
| Showcase | Fictional CI screenshots plus docs/SHOWCASE.md copy and claims boundaries | Final candidate screenshots, human design acceptance and publication approval |
| Pilot | docs/PILOT.md protocol and scorecard prepared | Real opt-in 10–20 sites, observations over time, then explicit expansion decision |
| External connector | docs/CONNECTOR-DECISION.md contract/evidence requirements prepared | Source selection, authorised API/account access, terms/quota proof and sandbox validation; no connector shipped |
| Distribution | 1.1.0 candidate metadata/package checks, full CI and independent reviews | Exact merge/release approval; published latest remains v1.0.0 |

PRs #6–#13 are sequential dependencies. Do not publish an intermediate branch: final #13 contains review fixes for earlier slices. Final check/run references belong in the release record once they complete. Passing automation and synthetic benchmarks do not establish million-site readiness.

All functionality is free under GPL-2.0-or-later: no paid tier, licence key, Ashbi account, central review hosting, telemetry or forced public credit.
