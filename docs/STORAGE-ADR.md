# ADR: retain canonical WordPress storage; constrain scale claims

Status: candidate decision supported by the recorded benchmark; maintainer release approval pending.

Keep the private post type, post metadata and collection taxonomy as the canonical store. This preserves native revisions, trash/restore, existing integrations, current privacy behaviour and a data-preserving upgrade/binary rollback. Do not prematurely introduce a second write engine or modify indexes on WordPress core tables. The one-join metadata pivot replaces the measured multi-join summary bottleneck and passes filter/provenance regression fixtures.

Evidence: at 10k reviews the pivot global-summary cold p95 was 152.441 ms on the declared runner. At 100k it remained 2758.940 ms, with product/sort paths around one second. See BENCHMARKS.md and the exact JSON environments. Transient caches reduce repeated summary work, but broad invalidation and an uncached high-volume request remain material limitations. The current model is not a proven universal million-site/high-volume solution.

A plugin-owned indexed read projection could index product/collection/status/rating/date/ID and eligibility, leaving posts as source of truth. It could improve filtered reads, pagination and aggregation, but requires versioned schema, idempotent resumable backfill, dual-write consistency, deletion/privacy propagation, reconciliation, upgrade locking, multisite handling and a rebuild/rollback plan. A full dedicated canonical table would also need to replace native revision/trash/export compatibility and has a larger migration/data-loss surface. Neither is implemented or advertised in this candidate.

Before choosing either, establish a real hosting/traffic budget, benchmark the same fixtures plus concurrent workloads and measure cache invalidation behaviour. Prototype the projection in isolation with exact expected-data comparisons. Rollback must switch reads to posts while preserving reviews; do not drop old records/tables during a deployment. Schema changes and any production migration require separate explicit approval and backup/recovery rehearsal.

This decision lets the all-free plugin progress honestly for ordinary review collections while keeping measured high-volume constraints visible. No licence, paid service or central data hosting is needed to fund or activate an alternative engine.
