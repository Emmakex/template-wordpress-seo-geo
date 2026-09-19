# Discovery cache and invalidation strategy

## Status

Phase 6G defines a conservative cache contract for native SEO/GEO discovery output.

The theme does **not** introduce a full-page cache, a generated-document transient cache, cron-based refreshes, background workers or a dependency on a CDN/page-cache plugin.

Instead, dynamic agent-facing documents remain request-derived and gain standards-based HTTP revalidation.

## Why revalidation instead of payload caching

The native runtime can change its public output when any of these authorities change:

- published post content or status;
- author/profile identity;
- explicit translation relationships;
- native language/routing configuration;
- llms.txt curation;
- Markdown-alternate enablement;
- Schema Organization/LocalBusiness configuration;
- WordPress public visibility;
- site identity, URL and permalink settings.

Persisting rendered llms.txt or Markdown bodies inside the theme would create a second cache lifecycle that must stay synchronized with all of those authorities.

Phase 6G therefore keeps generated payloads ephemeral and uses one lightweight mutation revision to make downstream storage safe to revalidate.

## Revision authority

`DiscoveryCacheRevision` owns the option:

```text
seo_geo_discovery_cache_revision
```

Read-only requests do not create or mutate that option. When no revision has been persisted yet, the resolver uses the stable fallback token `initial`.

A relevant mutation replaces the token with a new WordPress UUID.

Only one revision bump is necessary inside a single WordPress request because no external client can observe the intermediate mutations before that request completes.

## Relevant invalidation inputs

`DiscoveryCacheInvalidator` advances the revision for:

- post cache mutations for non-revision posts;
- native translation relationship metadata:
  - `_seo_geo_translation_group`;
  - `_seo_geo_language`;
  - `_seo_geo_translations`;
- user creation/profile update/deletion because public author provenance can change;
- project-owned crawler, llms.txt, Markdown, language, Schema identity and LocalBusiness options;
- WordPress options that alter public visibility, site identity, URLs, permalink behavior, timezone or front-page authority.

Unrelated options do not advance the discovery revision.

This is deliberately broad enough to prevent stale public discovery output while avoiding an all-options invalidation policy.

## HTTP policy

The optional virtual discovery documents use:

```http
Cache-Control: public, no-cache, must-revalidate, max-age=0
ETag: "seo-geo-..."
```

`public` permits a browser, proxy or CDN to store the public representation.

`no-cache` means a stored representation must be revalidated before reuse. It does not mean “never store”.

`must-revalidate` prevents serving a stale representation after validation is required.

The ETag includes:

- a stable surface key;
- the resource ID where relevant;
- the current discovery mutation revision.

The current native surfaces are:

- site-root `/llms.txt`;
- per-resource localized Markdown alternates.

## Conditional requests

GET and HEAD requests may send `If-None-Match`.

When the supplied validator matches the current ETag, the presenter responds with HTTP 304 and no generated body.

A relevant mutation changes the revision, which changes the ETag. A request carrying the old ETag therefore receives HTTP 200 and the freshly generated representation.

Weak-form `If-None-Match` values are accepted for safe read revalidation; the server itself emits a strong validator.

## HTML, Schema and metadata

The theme does not add an internal cache for:

- canonical URLs;
- robots/indexability;
- Open Graph;
- hreflang;
- Schema graphs;
- visible provenance metadata;
- WordPress HTML pages.

Those surfaces remain derived from the current WordPress request and authoritative server state.

If a hosting layer, page-cache plugin, reverse proxy or CDN caches HTML, that system owns its cache and purge mechanics.

## External purge integration

Every native revision bump fires:

```php
do_action(
    'seo_geo_discovery_cache_invalidated',
    $revision,
    $reason,
    $resource_id
);
```

Optional infrastructure integrations can listen to this action and purge CDN/page/object caches they own.

Core deliberately does not contain vendor-specific purge APIs or credentials.

## Privacy interaction

The cache contract does not weaken Phase 6F.

If a site becomes globally private or a resource becomes non-public, runtime visibility checks remain authoritative. Relevant WordPress mutations also advance the revision and emit the invalidation action.

External cache layers still need to honor revalidation/purge semantics; the theme cannot guarantee behavior of infrastructure that ignores HTTP cache directives or never executes its integration hook.

## Acceptance contract

The self-contained zero-plugin fixture must prove:

- the runtime exposes a non-empty discovery revision;
- unrelated option changes do not advance it;
- a relevant llms.txt option mutation advances it;
- llms.txt emits the required Cache-Control and strong ETag;
- a matching llms.txt ETag returns 304;
- changing llms.txt configuration changes the ETag and refreshed body;
- Markdown emits the same revalidation policy;
- a matching Markdown ETag returns 304;
- post-content mutation changes the Markdown ETag and body;
- public author/profile mutation changes the Markdown ETag and provenance;
- native translation metadata mutation advances the revision;
- all checks reuse the existing disposable WordPress fixture.

## Scope boundary

Phase 6G provides invalidation signals and HTTP validators, not a universal cache implementation.

It does not:

- control CDN configuration;
- clear arbitrary third-party plugin caches;
- guarantee that a misconfigured proxy honors HTTP semantics;
- cache private/admin responses;
- persist rendered llms.txt or Markdown payloads;
- run scheduled/background refresh jobs.

Production deployments that add a cache layer should integrate that layer with the public invalidation action or an equivalent platform purge mechanism.
