# SEO/GEO Manager 0.3.53 — R3.3 remote SEO authority

## Goal

Expose one bounded remote SEO mutation without exposing provider selection, arbitrary metadata or a generic REST relay.

Remote operation:

`seo-authority-change`

The caller supplies the complete desired state required by the currently accepted SEO adapter. The caller does **not** select Theme-native, Yoast, an adapter ID or provider metadata. Manager resolves the single active SEO authority itself and rejects unresolved/conflicting/unsupported states.

## Accepted authorities

### SEO/GEO Theme native authority

Resolved authority:

- authority: `seo-geo-theme`
- adapter: `seo-geo-theme-native`
- provider: `theme-native`
- writable signals: title/meta intent, canonical, robots/indexability

Delegation: `NativeSeoMetadataChangeAdapter`.

### Yoast SEO 28.6

Resolved authority:

- authority: `yoast`
- adapter: `yoast-abilities`
- provider: `yoast`
- writable signals: canonical, robots/noindex/nofollow, Schema page/article type

Delegation: `YoastSeoMetadataChangeAdapter` through the accepted public WordPress Abilities bridge. Manager does not write `_yoast_wpseo_*` private metadata.

## Lifecycle

The existing Manager remote lifecycle is reused unchanged:

`Inspect -> Plan/Preview -> explicit Apply -> provider readback -> Verify -> bounded operation evidence -> stale-safe Rollback`

R3.3 adds operation-aware verification and rollback dispatch:

- content and Theme structured content continue to verify through stored content fingerprints;
- `seo-authority-change` verifies through the exact SEO adapter fingerprint that created the operation;
- rollback dispatches through the stored accepted SEO adapter and remains stale-safe;
- changing SEO authority after Apply blocks unsafe provider rollback rather than guessing.

## Security and privacy boundaries

- no provider selector in the remote operation contract;
- one resolved SEO authority required, with no competing provider conflict;
- no generic shell;
- no generic REST proxy;
- no raw metadata writer;
- no Yoast private metadata access;
- Plan/Preview may show the exact bounded before/after SEO diff needed for operator confirmation;
- operation/history responses expose bounded authority identity and fingerprints, not private before/after provider payloads;
- Apply and Rollback remain environment-bound and explicitly confirmed;
- request idempotency, connection ownership, replay protection and bounded secret-free audit remain unchanged.

## Repository acceptance required before promotion

R3.3 is not repository-certified merely because the code exists. Promotion requires all of the following on one exact HEAD:

1. Static remote bridge R1–R3.3 contract passes.
2. Existing R1–R3.2 regression runtime remains green.
3. A real WordPress 7.1 / PHP 8.2 / MariaDB runtime with the self-contained SEO/GEO Theme proves `seo-authority-change` resolves Theme-native automatically and completes Plan -> Apply -> Verify -> Rollback.
4. A separate WordPress 7.1 runtime with Yoast SEO **28.6** proves the same operation resolves Yoast automatically, uses public WordPress Abilities only, preserves unsupported/read-only SEO state, verifies the exact provider fingerprint and rolls back exactly.
5. PHP Quality, Manager WordPress acceptance and deterministic release gates pass.
6. The complete PR workflow set is green on the same exact HEAD.

Repository/runtime acceptance is not client field acceptance. C9 remains a separate field-evidence track.
