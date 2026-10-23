# quality-operations: reproducible volume and storage decision

T09/T10/T11: run deterministic 1k/10k/100k review fixtures on disposable MySQL WordPress, reporting PHP/WordPress/DB versions, p50/p95, query counts, peak memory and cold/warm cache conditions. Fixtures contain only labelled synthetic records. Bulk SQL seeding is test-only and guarded by ABT_DISPOSABLE_TEST=1; all measured queries use production APIs.

Record JSON/Markdown results as CI artifacts. Do not claim shared-hosting or million-install readiness from a GitHub runner. An ADR should retain the current post/meta model or propose a later migration based on these measurements, with compatibility/rollback and an explicit shared-hosting follow-up. No automatic new storage engine.

Pagination must deterministically order ties by ID for date/rating/featured; public load-more must reject malformed attributes, unbounded ID lists, excessive page offsets and random pagination. Test behaviour against real fixtures and rerun the same workloads only after relevant changes. Targeted cache invalidation is justified only if measurement shows it matters.
