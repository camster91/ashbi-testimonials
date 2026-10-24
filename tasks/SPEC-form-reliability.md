# trust-controls: reliable public submissions

Implements the remaining public-form core of T07 under Cameron's instruction to finish the roadmap. Keep existing cache-safe signed targets, moderation, honeypot and five-per-hour connection policy. Native WordPress storage, no service/account/dependency.

Acceptance: reject non-string and oversized fields before sanitization/save, enforce form maxlengths on the server and require exact consent/rating values. Exact retries within an hour return success for the existing saved review without duplicate notifications. A database-backed per-connection lock protects the deduplication/rate/save sequence across PHP workers; lock contention gives an actionable retry response, stale locks recover, and failure paths release the lock. Only successful new saves consume rate budget. Use only REMOTE_ADDR (never trust forwarded headers by default). Shared connections share this limit; no raw IP is stored.

Store short-lived hashed lock/idempotency keys. Preserve existing reviews and settings. Default limits remain five reviews per rolling hour since the last accepted submission. No claim of blocking distributed abuse. Privacy policy text must describe temporary connection/retry hashes.

Tests: malformed arrays, length boundaries, invalid rating/consent, immediate/cached valid requests, idempotent retries, limit, contention, stale-lock recovery, database save failure. Add a disposable multi-process WordPress test for actual concurrent requests. Existing full CI remains required. Browser recovery/retained input will follow in the UI slice.

Commands: `wp-env run cli wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-form-safety.php`; full CI `.github/workflows/validate.yml`; package `bash scripts/package.sh`. Use existing PHP formatting and focused methods in the form class. No live client submissions or email tests.
