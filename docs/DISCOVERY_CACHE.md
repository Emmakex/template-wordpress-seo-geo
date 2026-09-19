# Discovery cache and invalidation

## Status

Phase 6G defines the cache/revalidation contract for the optional SEO/GEO discovery documents served by the self-contained theme.

The baseline remains deliberately conservative:

- the theme does **not** keep a persistent rendered copy of HTML, Schema, llms.txt or Markdown payloads;
- WordPress remains the source of truth for content, options, users and metadata;
- llms.txt and Markdown may be stored by normal HTTP caches, but must revalidate before reuse;
- cache correctness is driven by one lightweight mutation revision rather than a payload cache;
- third-party page caches, CDNs and hosting layers remain externally owned.

This avoids introducing stale private/public representations merely to optimize payloads that are currently cheap to generate.

## HTTP policy

The two native virtual discovery-document surfaces use:

```http
Cache-Control: public, no-cache, must-revalidate, max-age=0
ETag: "seo-geo-..."
```

In this contract, `no-cache` does **not** mean “do not store”. It means a stored representation must be revalidated with the origin before reuse.

Supported conditional methods:

- GET;
- HEAD.

When `If-None-Match` matches the current representation validator, the origin returns HTTP 304 without emitting the body.

The validator accepts the normal comma-separated `If-None-Match` form, wildcard `*`, and weak validators for GET/HEAD comparison while the server emits a strong ETag.

## Revision authority

`DiscoveryCacheRevision` owns one persisted revision token:

```text
seo_geo_discovery_cache_revision
```

Read-only public requests never create or mutate the option. Before the first relevant mutation, the logical revision is `initial`.

A relevant mutation replaces the revision with a new UUID. Only one bump occurs per WordPress request because intermediate mutations inside the same request cannot be observed by an external client.

ETags are derived from:

- stable surface key;
- optional resource ID;
- current discovery revision.

Therefore:

- unchanged state produces a stable ETag;
- a relevant mutation produces a different ETag;
- llms.txt and one Markdown resource do not accidentally share the same validator.

## Relevant invalidations

The native invalidator advances the revision after inputs that can change public SEO/GEO discovery state.

### Posts

WordPress `clean_post_cache` invalidates after a real post mutation.

Post revisions are ignored as direct resources.

A post update can affect:

- Markdown body/excerpt/title;
- canonical/discovery relationships;
- llms.txt curated-resource output;
- article provenance;
- Schema;
- author/archive/search/feed/sitemap state.

### Translation metadata

The following native relationship metadata invalidates discovery:

- `NativeTranslationRegistry::META_GROUP`;
- `NativeTranslationRegistry::META_LANGUAGE`;
- `NativeTranslationRegistry::META_TRANSLATIONS`.

This covers direct metadata mutations that may occur outside the normal editor save flow.

### Author identity

User creation/profile update/deletion invalidates because public author identity can affect:

- visible author/profile output;
- BlogPosting Person identity;
- HTML/Open Graph provenance;
- Markdown provenance.

### Authoritative options

The revision changes for options that may alter public discovery output, including:

- crawler policy;
- llms.txt configuration;
- Markdown-alternate configuration;
- native language configuration;
- Schema identity;
- LocalBusiness configuration;
- WordPress public/private visibility;
- site name/description;
- home/site URL;
- permalink structure;
- timezone/locale;
- front-page configuration.

An unrelated option must not change the discovery revision.

## External cache/CDN integration

After a relevant mutation, Core emits:

```php
do_action(
    'seo_geo_discovery_cache_invalidated',
    $revision,
    $reason,
    $resource_id
);
```

This is the integration boundary for a future hosting adapter, page-cache plugin or CDN purge implementation.

Core deliberately does **not**:

- call a provider-specific purge API;
- require a cache plugin;
- assume a CDN vendor;
- purge arbitrary filesystem caches;
- flush the full WordPress object cache;
- start cron/background invalidation jobs.

An integration may listen to the action and map the global revision event onto its own purge model.

## HTML and Schema

Phase 6G does not add a theme-owned full-page HTML cache.

HTML SEO metadata and Schema remain request-derived. This keeps canonical, indexability, language, visible-content Schema and privacy decisions attached to the current WordPress request.

External full-page caches remain responsible for purging/revalidation of HTML according to their own integration with WordPress. The native invalidation action gives those integrations one explicit SEO/GEO change signal when needed.

## Privacy

Cache behavior must never weaken the Phase 6F privacy invariant.

The revision contains no content, user data or secret material; it is an opaque mutation token.

A stale validator must stop matching after a relevant mutation. A 304 is only valid when the current ETag still matches the request validator.

Disabled/private GEO endpoints keep their existing visibility guards; cache headers do not make an unavailable resource public.

## Acceptance contract

The zero-plugin self-contained acceptance proves:

- runtime exposes a non-empty logical revision;
- unrelated option changes leave the revision unchanged;
- enabling/updating llms.txt advances the revision;
- llms.txt returns the conservative Cache-Control policy and a strong ETag;
- a matching llms.txt validator returns 304;
- changing llms.txt configuration makes the old validator stale and returns updated content;
- Markdown returns the same revalidation policy with a resource-scoped ETag;
- a matching Markdown validator returns 304;
- changing source post content invalidates the old Markdown validator and returns updated content;
- changing public author identity invalidates Markdown provenance;
- changing native translation metadata advances the revision;
- all checks reuse the existing disposable WordPress fixture.

## Performance rationale

The baseline chooses revalidation rather than rendered-payload persistence because:

- current discovery payload generation is small;
- WordPress already has its own object-cache semantics for source data;
- a second content cache would introduce additional invalidation and privacy failure modes;
- ETag/304 reduces transferred response bodies while keeping the origin authoritative.

A future persistent payload cache requires measured evidence that generation cost materially warrants the additional correctness complexity.

## Scope boundary

This contract governs project-owned discovery documents and exposes an invalidation signal for integrations.

It does not claim to control:

- browser cache implementation details;
- CDN TTL configuration;
- reverse-proxy behavior;
- third-party plugin page caches;
- search-engine copies;
- AI-provider caches;
- hosting snapshots.

Those systems must respect their own cache semantics and should integrate with the invalidation action where applicable.
