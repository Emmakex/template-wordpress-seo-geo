# SEO/GEO Manager 0.3.48 — Yoast SEO public Abilities adapter

Status: **accepted repository contract**  
Date: **2026-10-11**

## Purpose

0.3.48 adds the first verified non-Theme SEO provider adapter for SEO/GEO Manager.

The adapter supports Yoast SEO through Yoast's public WordPress Abilities contract. Manager does not write private Yoast post-meta keys and does not become a second public SEO renderer.

The responsibility boundary remains:

- **External orchestration** decides the SEO strategy and intended change.
- **SEO/GEO Manager** resolves authority, inspects, previews, applies, verifies, records and rolls back the supported provider mutation.
- **Yoast SEO** remains the provider storage and public output authority for the accepted non-Theme stack.
- **WordPress** remains the resource/permission authority.

## Verified provider contract

The accepted provider fixture pins:

```text
WordPress 7.1
PHP 8.2
Yoast SEO 28.6
SEO/GEO Manager 0.3.48
```

Manager requires the two public Yoast WordPress Abilities:

```text
yoast-seo/get-post-seo-data
yoast-seo/update-post-seo-data
```

The provider is not considered writable merely because the Yoast plugin is active. `SeoAuthorityScanner` marks Yoast writable only when:

1. Yoast is the single resolved SEO provider family;
2. both accepted WordPress Abilities are registered;
3. no second SEO provider conflict exists;
4. the current operator has the provider capability and resource permission required for the operation.

If Yoast is installed but the public write contract is unavailable, Manager keeps the provider resolved but read-only.

## Least-privilege provider identity

The pinned acceptance uses Yoast's real `wpseo_editor` role rather than artificially granting the provider capability to a WordPress administrator.

The write path requires:

```text
wpseo_edit_advanced_metadata
+
edit_post(resource)
```

This preserves the provider's own permission boundary.

## Writable surface

0.3.48 exposes only signals backed by the accepted public Yoast update Ability:

```text
canonical
robots
schema
```

The bounded provider state managed by the adapter is:

```text
canonical
noindex
nofollow
schema_page_type
schema_article_type
```

Manager validates the intended update against the live Yoast Ability input schema before Apply.

## Read-only surface

The following remain read-only in this slice:

```text
title-meta
open-graph
hreflang
sitemap
```

The Yoast read Ability may expose title/meta-description and Open Graph evidence. Manager returns those values as read-only context and verifies that its bounded write does not alter them.

Manager does not infer write support from readable provider data.

## No private provider metadata

The Manager package must not contain direct `_yoast_wpseo_*` dependencies.

The pinned provider acceptance explicitly fails if Manager source contains that private-meta prefix.

All accepted provider mutation goes through the public Yoast WordPress Ability lifecycle.

## REST contract

Base namespace:

```text
/wp-json/seo-geo-manager/v1
```

Routes:

```text
GET  /content/{resource_id}/seo/providers/yoast
POST /content/{resource_id}/seo/providers/yoast/changes/preview
POST /content/{resource_id}/seo/providers/yoast/changes/apply
POST /content/seo/providers/yoast/changes/{operation_id}/rollback
```

## Capability discovery

`GET /capabilities` exposes `yoast_seo_metadata` only when the current authority scanner resolves Yoast as a unique writable provider backed by the accepted Abilities.

The capability reports:

- provider: `yoast`;
- adapter: `yoast-abilities`;
- writable signals: `canonical`, `robots`, `schema`;
- read-only signals: `title-meta`, `open-graph`, `hreflang`, `sitemap`;
- `public_abilities_only=true`;
- no raw provider metadata exposure;
- no raw provider metadata write support;
- single-authority requirement.

Semantic operations:

```text
content.yoast_seo_metadata.read
content.yoast_seo_metadata.preview
content.yoast_seo_metadata.apply
content.yoast_seo_metadata.rollback
```

When Yoast owns the public SEO surface, the Theme-native SEO write capability is not advertised as available.

## Inspect

Inspection:

1. re-checks unique provider authority;
2. checks provider Abilities are registered;
3. checks `wpseo_edit_advanced_metadata` and `edit_post(resource)`;
4. executes Yoast's public read Ability;
5. extracts the bounded writable state;
6. returns a deterministic Manager fingerprint;
7. returns title/meta and Open Graph as read-only context.

No missing provider values are fabricated.

## Preview

Preview requires:

- `resource_id`;
- complete desired bounded provider state;
- `expected_fingerprint`.

It performs no mutation.

Before accepting the proposed state, Manager passes the intended provider payload through Yoast's live Ability input validation.

Preview exposes exact before/after state and explicitly declares which public SEO signals are writable versus read-only.

## Apply

Apply preserves the standard Manager safety contract:

- current authority re-check;
- resource/provider capability re-check;
- expected fingerprint / optimistic concurrency;
- production environment binding at the REST controller;
- explicit approval for published targets;
- idempotency key;
- provider Ability validation;
- provider Ability execution;
- exact provider readback/fingerprint verification;
- operation ID and stored evidence;
- compensation attempt if exact verification or operation-history persistence fails.

A provider write is not considered successful merely because the Ability call returns without error. The bounded state must match the exact requested normalized state.

## Idempotency

Replaying an already accepted payload with the same idempotency key returns the original Manager operation rather than applying the provider mutation twice.

Manager operation history remains the evidence authority; Yoast remains the SEO provider/state/output authority.

## Published-resource guard

Applying provider SEO changes to a published resource requires explicit approval after Preview.

A published target without `allow_published_target=true` is rejected.

## Rollback

Rollback is stale-safe.

Manager stores exact bounded before/after states and fingerprints. Rollback proceeds only while the provider still matches the state produced by the original Apply.

If a newer human/provider/automation edit changed the bounded state, rollback is blocked instead of overwriting newer work.

Restoration also goes through the public Yoast update Ability and is verified by exact provider readback.

## Multiple-provider conflict

Repository acceptance also activates a second known provider identity in the fixture state.

The resolver must then report a multiple-provider conflict and `safe_to_write_seo_metadata=false`.

The Yoast adapter refuses inspection/mutation while that conflict exists.

Manager never chooses one provider silently when more than one accepted provider family is active.

## Real provider acceptance

The dedicated isolated fixture proves against the real pinned Yoast SEO 28.6 plugin:

- the public read/write Abilities are registered;
- the real `wpseo_editor` role exists and carries the required provider capability;
- Yoast resolves as the unique external writable authority;
- only canonical/robots/Schema become writable;
- title/meta/Open Graph/hreflang/sitemap remain non-writable;
- Preview validates without mutation;
- Apply persists the exact bounded state;
- exact readback/fingerprint verification passes;
- read-only title/meta and Open Graph evidence is unchanged by the bounded write;
- idempotent replay returns the same operation;
- rollback restores the exact initial provider state/fingerprint;
- published targets require explicit approval;
- capability discovery advertises the provider adapter and disables the native write path;
- a second provider creates a hard authority conflict;
- Manager source contains no direct `_yoast_wpseo_*` private-meta dependency.

## Repository acceptance

Accepted technical baseline:

```text
Manager version: 0.3.48
Implementation/version commit: b2b20f3a5685600f00bdf54766ad50583679cc44
PHP Quality CI: 38129352608 — success
SEO GEO Manager WordPress CI: 38129352549 — success
SEO GEO Manager Release CI: 38129352623 — success
Manager ZIP files: 101
Manager ZIP SHA-256: 58e6b0070ae4ff1e172ba9ab493e17844f0a5d180e4970e0cae329f57b1d5ed0
```

WPCS and PHPStan level 6 pass for existing products and Manager. The exact 0.3.48 WordPress workflow passes the real pinned Yoast SEO 28.6 provider acceptance plus the existing authentication, content, media, multilingual, permalink, operation-history, rendered-verification and Field Gate regression suites.

## C7 position after 0.3.48

C7 now has repository-accepted write paths for two distinct authority models:

1. **SEO/GEO Theme native authority** — bounded title/meta, canonical and robots/indexability through Core (`0.3.47`);
2. **Yoast non-Theme provider authority** — bounded canonical, robots and Schema through public Yoast WordPress Abilities (`0.3.48`).

This satisfies the intended first supported Theme and first supported non-Theme provider architecture without building a speculative provider matrix.

Do not add Rank Math/AIOSEO/SEO Framework write adapters merely for coverage. Reopen C7 only for a demonstrated field requirement or a missing public-signal contract that blocks an accepted C9 scenario.

## C9 remains pending

0.3.48 is repository acceptance, not client-field acceptance.

C9 still requires the same Manager package to be proven on:

1. one accepted SEO/GEO Theme field installation;
2. one accepted non-Theme WordPress + Yoast field installation;
3. real HTTPS/Application Password least-privilege/revocation evidence;
4. no client-specific fork.

No field evidence is fabricated from CI.

## Non-goals

0.3.48 does not:

- write Yoast private post meta directly;
- write Yoast SEO title/meta description or Open Graph values;
- make Manager a public SEO renderer;
- support multiple active SEO providers simultaneously;
- add speculative provider adapters;
- claim C9 field acceptance;
- change Theme stable-release NO-GO or `/nuevaweb/` product acceptance state.
