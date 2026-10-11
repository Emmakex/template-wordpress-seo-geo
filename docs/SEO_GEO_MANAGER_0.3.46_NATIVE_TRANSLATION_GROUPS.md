# SEO/GEO Manager 0.3.46 — Native translation groups

Status: **accepted repository contract**  
Date: **2026-10-11**

## Purpose

0.3.46 closes the remaining generic C4 multilingual relationship gap without turning SEO/GEO Manager into a translation engine.

The responsibility boundary is:

- **External orchestration** chooses languages/markets, translation strategy and translated content.
- **SEO/GEO Manager** provides safe relationship plumbing.
- **SEO/GEO Core** remains the storage/public multilingual authority for the native provider.
- **Theme/current frontend** renders the WordPress resources and consumes the Core SEO/GEO runtime.

Manager does not generate translations, choose target markets or write the native provider's post-meta keys directly.

## Native authority

The native provider stores reciprocal translation relationships through `SeoGeo\Core\Language\NativeTranslationGroupWriter`.

Manager talks to that authority through its bridge/adapter contract. It does **not** write:

- `_seo_geo_translation_group`;
- `_seo_geo_language`;
- `_seo_geo_translations`;

or any other raw provider metadata directly.

The public `NativeTranslationRegistry` remains responsible for deciding whether a relationship is valid for public multilingual output.

## REST contract

Base namespace:

```text
/wp-json/seo-geo-manager/v1
```

Routes:

```text
GET  /content/translation-groups/{group_id}
POST /content/translation-groups/{group_id}/changes/preview
POST /content/translation-groups/{group_id}/changes/apply
POST /content/translation-groups/changes/{operation_id}/rollback
```

`group_id` is a bounded semantic relationship identifier. Manager operates a **complete reciprocal group**, not a collection of independent one-way links.

## Capability discovery

When the native Core mutation authority is available, `/capabilities` advertises:

```text
native_translation_groups
```

and the semantic operations:

```text
content.native_translation_group.read
content.native_translation_group.preview
content.native_translation_group.apply
content.native_translation_group.rollback
```

The capability manifest explicitly declares:

- provider: `native-core`;
- complete-group replacement;
- no raw metadata exposure;
- no raw metadata write support.

If the native authority is unavailable, Manager must not pretend that the operation is supported.

## Inspect

Inspection returns a bounded provider-neutral representation containing the relationship state needed by external orchestration, including:

- group ID;
- post type;
- normalized language-to-resource map;
- bounded member state;
- consistency state;
- deterministic fingerprint;
- public-readiness state.

Raw provider metadata keys are not returned.

An empty group is valid inspection state and has its own deterministic fingerprint.

## Preview

Preview accepts:

- the complete desired `translations` map;
- `expected_fingerprint`.

Preview validates the requested relationship but does not mutate provider state.

The native Core authority validates, among other invariants:

- configured language codes only;
- unique resource per language;
- supported group-size bound;
- existing eligible WordPress resources;
- one consistent post type across members;
- no conflict with a different existing translation group.

## Apply

Apply adds the normal Manager write guards:

- expected fingerprint / optimistic concurrency;
- permission checks over all affected resources;
- explicit approval when published resources are affected;
- production environment binding where required;
- idempotency key;
- exact provider readback verification;
- operation ID/evidence;
- bounded previous-state capture for rollback.

Manager requests one complete reciprocal replacement from Core. It does not build relationships by writing one member at a time.

## Public readiness

Persisting a relationship does not automatically make it public.

The native Core registry continues to suppress public translation/hreflang relationship output until the full reciprocal relationship is valid for public use.

Repository acceptance proves both states:

1. a group containing a draft translation is persisted but **not** public-ready and does not resolve through the public registry;
2. after the remaining translation is published, the same Manager-created relationship becomes public-ready and resolves through `NativeTranslationRegistry` for both members.

This prevents Manager from bypassing the native public SEO/GEO authority.

## Idempotency and verification

Replaying the same accepted operation with the same idempotency key returns the original operation rather than duplicating mutation.

After Apply, Manager verifies the exact reciprocal map through the provider authority. If persistence cannot be verified, the mutation contract fails rather than returning fabricated success.

## Rollback

Rollback is stale-safe.

Manager records enough bounded previous state to restore the prior complete relationship only while it is still safe to do so.

Repository acceptance proves:

- a newer valid provider edit blocks rollback;
- after the exact applied state is restored, rollback succeeds;
- rollback restores the original empty relationship in the acceptance scenario;
- rollback remains environment-bound;
- provider permissions are re-evaluated through the operation contract.

Manager does not blindly overwrite a newer human/provider relationship edit.

## Least privilege

The REST controller requires an authenticated editorial principal and the relationship adapter/reader checks authority over the affected WordPress resources.

Repository acceptance proves that a least-privilege Author that cannot edit all group resources is rejected.

Capability discovery is not authorization: every operation retains its own resource checks.

## Privacy and evidence

Manager responses/evidence must remain provider-neutral and bounded.

0.3.46 acceptance explicitly verifies that REST readback, Preview and operation evidence do not expose raw `_seo_geo_*` metadata keys.

The operation also preserves the translated resources' `post_content`; relationship plumbing does not rewrite translated body content.

## Repository acceptance

Accepted repository path validates:

- 0.3.46 capability discovery;
- empty-group inspection + fingerprint;
- Preview without mutation;
- published-target approval guard;
- environment binding;
- idempotent Apply;
- exact reciprocal readback;
- draft-containing relationship not publicly exposed;
- rejection of an unconfigured language;
- valid newer provider edit causing stale rollback rejection;
- exact rollback after safe state restoration;
- public readiness after all relationship members are published;
- resolution through the real native public registry;
- least-privilege rejection;
- `post_content` preservation;
- no raw provider metadata leakage.

Accepted technical baseline before documentation closure:

```text
Manager version: 0.3.46
PHP Quality CI: 38124711719 — success
SEO GEO Manager WordPress CI: 38124711680 — success
```

The WordPress acceptance runs the native relationship fixture in the same real WordPress stack used for featured/strategic media, with the native ES/EN language configuration persisted before Theme/Core runtime boot.

## C4 closure

With 0.3.46 accepted, the generic C4 content-operation path has repository-proven foundations for:

- create/update normal posts/pages;
- draft/publish/schedule lifecycle;
- title/excerpt/body changes through bounded change sets;
- featured/media relationships;
- taxonomy assignment;
- existing-user author binding;
- safe native multilingual relationship plumbing.

Therefore **C4 is complete at repository-contract level**.

C4 closure does not mean the whole Manager product is complete. C5, C6, C7, C8 and C9 retain their own exit criteria, and C2 still requires per-client HTTPS/operator/revocation field acceptance.

## Non-goals

0.3.46 does not:

- translate content;
- choose languages or markets;
- decide hreflang strategy;
- create localized content automatically;
- invent translator/author identities;
- expose or support arbitrary raw multilingual meta writes;
- implement third-party multilingual provider adapters automatically.

Additional provider support, if needed, belongs behind explicit accepted provider adapters and must preserve the same single-authority rule.
