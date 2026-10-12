# SEO/GEO Manager 0.3.61 — Contextual Links Workspace

Validation slice for the Manager-first EMMAKE build path.

## Adds

- contextual-link workspace tied to the selected page/post;
- bounded discovery with anchor text, href, classification and verified target;
- explicit environment-leakage highlighting without automatic rewriting;
- published internal target picker backed by WordPress resource IDs;
- no arbitrary target URL writer;
- href-only correction through the accepted contextual-link adapter;
- Preview before Apply;
- expected source fingerprint;
- published-source acknowledgement;
- environment-bound Apply;
- idempotency key;
- exact post-Apply Verify by source + ordinal + href + target resource + fingerprint;
- operation ID and stale-safe rollback;
- exact post-Rollback readback of the original href;
- raw post bodies and private rollback snapshots remain server-side only;
- no client-domain hardcoding.

This validation branch adds only this release note on top of exact implementation head `1e9570bc436d6f51f4da0ee977b941b00412bdaa` so the full Manager workflows execute before promotion.
