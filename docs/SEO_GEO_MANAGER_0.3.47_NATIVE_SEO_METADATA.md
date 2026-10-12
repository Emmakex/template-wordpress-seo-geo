# SEO/GEO Manager 0.3.47 — Theme-native SEO metadata authority

Status: **accepted repository contract**  
Date: **2026-10-11**

## Purpose

0.3.47 opens C7 with the smallest safe write path for the SEO/GEO Theme stack.

The goal is not to make Manager a second SEO renderer. The goal is to let external orchestration request bounded SEO metadata changes while preserving exactly one public owner for each SEO/GEO signal.

The responsibility boundary is:

- **External orchestration** decides SEO strategy and the desired change.
- **SEO/GEO Manager** inspects authority, previews, applies, verifies, records and rolls back the supported mutation.
- **SEO/GEO Core / Theme runtime** remains the public native SEO output authority.
- **External SEO providers** remain read-only until an explicit verified adapter exists.

## Single-authority rule

Manager calls `SeoAuthorityScanner` before advertising or executing the native SEO mutation path.

The Theme-native adapter is writable only when:

1. the SEO/GEO Theme is the active native baseline;
2. the native Core writer exists;
3. exactly one accepted SEO authority is resolved;
4. no supported external-provider conflict is active.

If Yoast, Rank Math, AIOSEO, The SEO Framework or another detected provider owns the public surface, the Theme-native writer is not used.

A multiple-provider conflict remains mutation-blocking.

## Writable surface

0.3.47 deliberately enables only the signals whose native storage/output contract is already bounded and verified:

```text
title-meta
canonical
robots
```

The following signals remain explicitly read-only in this slice:

```text
open-graph
schema
hreflang
sitemap
```

This is intentional. A signal is not made writable merely because Manager can detect it.

## Native storage authority

Manager writes through:

```text
SeoGeo\Core\Seo\NativeSeoMetadataWriter
```

Manager does not write arbitrary raw SEO metadata keys.

The writer owns normalization, persistence, inspection and deterministic fingerprinting for the bounded native values.

The accepted values are provider-neutral SEO intent:

- title/meta title override;
- meta description override;
- canonical URL override;
- native indexability state used by the Core robots authority.

## REST contract

Base namespace:

```text
/wp-json/seo-geo-manager/v1
```

Routes:

```text
GET  /content/{resource_id}/seo
POST /content/{resource_id}/seo/changes/preview
POST /content/{resource_id}/seo/changes/apply
POST /content/seo/changes/{operation_id}/rollback
```

The controller requires an authenticated editorial principal. Resource-level `edit_post(resource)` checks remain inside the adapter.

## Capability discovery

`GET /capabilities` advertises `native_seo_metadata` only when the current authority scanner resolves the Theme-native writer as safe to use.

The capability reports:

- adapter: `seo-geo-theme-native`;
- writable signals: `title-meta`, `canonical`, `robots`;
- read-only signals: `open-graph`, `schema`, `hreflang`, `sitemap`;
- single-authority requirement;
- no raw provider metadata exposure;
- no arbitrary raw provider metadata write support.

Semantic operations:

```text
content.native_seo_metadata.read
content.native_seo_metadata.preview
content.native_seo_metadata.apply
content.native_seo_metadata.rollback
```

Capability discovery is not authorization. Every concrete resource operation still re-checks the current user and current authority state.

## Inspect

Inspection returns the current bounded native values plus a deterministic fingerprint.

The response also includes the currently resolved SEO authority/output contract so external orchestration can see why mutation is or is not available.

Inspection does not create missing values and does not transfer output authority to Manager.

## Preview

Preview requires:

- `resource_id`;
- the complete desired bounded `values` state;
- `expected_fingerprint`.

It validates and normalizes the request without mutating WordPress.

Preview returns exact before/after values and fingerprints and declares the writable/read-only signal boundary.

## Apply

Apply adds the normal Manager safety contract:

- expected fingerprint / optimistic concurrency;
- current authority re-check;
- resource permission check;
- production environment binding;
- explicit approval for published targets;
- idempotency key;
- exact provider readback;
- operation ID and stored evidence;
- compensation attempt if exact verification or operation-history persistence fails.

The write is rejected if authority has changed since inspection.

## Idempotency

Replaying an already accepted payload with the same idempotency key returns the original operation rather than applying the change twice.

The operation store remains the Manager evidence authority; the Core writer remains the SEO-value storage authority.

## Verification

After Apply, Manager re-reads through `NativeSeoMetadataWriter` and compares both:

- exact normalized values;
- deterministic fingerprint.

A mismatch is an error, not a fabricated success.

## Rollback

Rollback is stale-safe.

Manager stores the bounded before/after states and fingerprints. Rollback is allowed only while the resource still matches the exact state produced by the original Apply.

If a newer human or automation edit changed the SEO values, rollback is blocked instead of overwriting newer work.

Environment binding and resource permissions are re-checked during rollback.

## Published-resource guard

Changing SEO metadata on a published resource requires explicit approval.

Draft content may be prepared without pretending that it has already become public output.

This keeps the mutation semantics aligned with the rest of Manager's publication-aware operations.

## Provider coexistence

0.3.47 does **not** implement write adapters for third-party SEO providers.

Repository acceptance proves that:

- a detected Yoast authority does not become writable through the native adapter;
- a multiple-provider conflict blocks mutation;
- Manager does not bypass provider ownership by writing native state underneath an external authority.

Third-party provider support belongs behind explicit adapters that use the provider's accepted public contract.

## Repository acceptance

The accepted WordPress path validates:

- Theme-native authority discovery;
- capability discovery for the native SEO adapter;
- exact writable/read-only signal declaration;
- inspection and deterministic fingerprint;
- Preview without mutation;
- published-target approval guard;
- environment binding;
- Apply through Core authority;
- exact readback verification;
- idempotent replay;
- stale-safe rollback;
- Yoast/external-provider blocking;
- multiple-provider conflict blocking;
- no regression across existing content/media/multilingual/permalink/history/field-gate contracts.

Accepted technical baseline:

```text
Manager version: 0.3.47
PHP Quality CI: 38128143363 — success
SEO GEO Manager WordPress CI: 38128143317 — success
SEO GEO Manager Release CI: 38128143327 — success
Manager ZIP SHA-256: 1314fada18ccbcef0ebbad63e08559195e545a1b73e8fb9b78f87ad33bce316e
```

WPCS and PHPStan level 6 pass for both existing products and Manager. The deterministic Manager release contains 98 files.

## C7 position after 0.3.47

The first C7 write slice is complete for the Theme-native stack.

C7 is **not yet closed** because the product still needs a verified non-Theme provider adapter for C9/client-agnostic field acceptance.

The next adapter must preserve the same rules:

- provider authority first;
- public provider API/contract only;
- no blind writes to private provider metadata;
- explicit capability discovery;
- Preview/Apply/verify/history/rollback;
- least privilege;
- one public owner per signal.

## Non-goals

0.3.47 does not:

- become an SEO strategy engine;
- generate SEO copy autonomously;
- write Open Graph, Schema, hreflang or sitemap state;
- take over public SEO rendering from Core/Theme;
- write Yoast/Rank Math/AIOSEO/SEO Framework private metadata;
- resolve a multiple-provider conflict automatically;
- claim C9 field acceptance or Theme stable acceptance.
