# Discovery cache and invalidation

## Status

Phase 6G defines the cache/invalidation contract for generated GEO discovery documents.

The baseline is correctness-first:

- cache only derived text documents whose complete authority is known;
- do not cache request-context SEO decisions such as canonical, robots, indexability, hreflang, Schema or HTML presentation;
- use WordPress's Object Cache API instead of a custom database table or filesystem cache;
- invalidate by advancing one dedicated discovery generation;
- never flush the global WordPress object cache;
- do not require Redis, Memcached or any cache plugin for correctness;
- keep old-generation entries bounded by a one-hour TTL.

The first cached surfaces are:

- generated `llms.txt`;
- rendered Markdown alternates.

## Why versioned keys

The cache group is:

```text
seo_geo_discovery
```

Each key includes the group's WordPress `last_changed` generation.

Invalidation calls:

```php
wp_cache_set_last_changed( 'seo_geo_discovery' );
```

A resolver subsequently derives a different key from the new generation. Old entries therefore become unreachable immediately without calling a global cache flush.

WordPress exposes `wp_cache_get_last_changed()` and `wp_cache_set_last_changed()` for generation-style invalidation. The project deliberately does not rely on `wp_cache_flush_group()`: WordPress documents that group flushing should only be called after checking backend support, and unsupported persistent-cache implementations may otherwise fall back to broader behavior.

## Persistence model

The implementation uses `wp_cache_get()` and `wp_cache_set()`.

On a stock WordPress installation the object cache is request-local. A persistent object-cache implementation may retain the project cache between requests.

The product contract is identical in both cases:

- persistence is an optimization, never a correctness dependency;
- no persistent-cache plugin is required;
- generated output must remain correct when every request starts with an empty object cache.

Cached text entries use a one-hour TTL. Generation changes make stale entries unreachable immediately; the TTL limits storage of unreachable older generations in persistent backends.

## Cached and uncached surfaces

### Cached

#### llms.txt

The complete generated llms.txt document is cached under one versioned document key.

The cache is consulted only after the resolver confirms that llms.txt is enabled and the site is public.

#### Markdown alternate body

A rendered Markdown document is cached by:

- WordPress post ID;
- authoritative HTML source URL;
- resolved language context;
- current discovery generation.

The public-resource/request guards run before a Markdown request can reach the renderer.

### Deliberately uncached

The native baseline does not cache:

- indexability state;
- canonical URL;
- robots directives;
- Open Graph;
- hreflang;
- Schema graph;
- crawler-policy output;
- current request routing;
- HTTP response status;
- admin capability decisions.

Those values are request/context sensitive or already inexpensive enough that caching would add more invalidation risk than value.

## Invalidation authorities

One `DiscoveryCacheInvalidator` registers all invalidation hooks.

### Content lifecycle

`clean_post_cache` advances the generation after WordPress cleans a post.

This covers publication/status/title/content/permalink-affecting post changes in the normal WordPress lifecycle.

### Post metadata

The generation also advances on:

- `added_post_meta`;
- `updated_post_meta`;
- `deleted_post_meta`.

This is required because native translation relationships and future discovery facts may live in post metadata independently of a full post update.

### Author identity

`clean_user_cache` advances the generation.

Markdown provenance therefore cannot keep a stale author display name/profile after a user update.

### Relevant options

The following option families invalidate cached discovery output:

- public site identity: `blogname`, `blogdescription`;
- privacy: `blog_public`;
- URL authority: `home`, `siteurl`, `permalink_structure`;
- language/time authority: `WPLANG`, `timezone_string`, `gmt_offset`;
- front-page authority: `show_on_front`, `page_on_front`;
- native languages;
- native Schema identity/local-business configuration;
- llms.txt configuration;
- Markdown-alternate configuration.

Unrelated WordPress options do not advance the discovery generation.

## Privacy behavior

A cache hit never bypasses public-resource checks.

For llms.txt, `enabled()` and public-site privacy are evaluated before the cache lookup.

For Markdown, request/resource validation occurs before the cached rendered body is used.

When a published post becomes draft/private, WordPress post-cache cleaning advances the discovery generation. Any previously cached public representation is immediately unreachable through current project keys, while the Phase 6F guards continue to block the now non-public resource itself.

## Acceptance contract

The zero-plugin built-theme acceptance must prove:

- the cache service round-trips text values;
- unrelated option changes do not advance generation;
- relevant option changes do advance generation;
- advancing generation does not require a global/group flush;
- post updates advance generation;
- post-meta updates advance generation;
- user/author updates advance generation;
- llms.txt is physically present in the project cache after resolution;
- changing llms.txt configuration returns fresh content rather than the old cached document;
- rendered Markdown is physically present in the project cache;
- post edits invalidate Markdown;
- author identity edits invalidate Markdown provenance;
- the cache/runtime classes are included in the built self-contained theme;
- existing privacy, multilingual, accessibility and performance gates remain green.

## Primary references

Checked 2026-09-19:

- WordPress Object Cache class: https://developer.wordpress.org/reference/classes/wp_object_cache/
- `wp_cache_get()`: https://developer.wordpress.org/reference/functions/wp_cache_get/
- `wp_cache_set()`: https://developer.wordpress.org/reference/functions/wp_cache_set/
- `wp_cache_flush_group()`: https://developer.wordpress.org/reference/functions/wp_cache_flush_group/
- `clean_post_cache`: https://developer.wordpress.org/reference/hooks/clean_post_cache/
- `clean_user_cache()`: https://developer.wordpress.org/reference/functions/clean_user_cache/
- `updated_option`: https://developer.wordpress.org/reference/hooks/updated_option/
- metadata lifecycle hooks: https://developer.wordpress.org/reference/functions/update_metadata/

Cache policy is an engineering/performance mechanism. It does not imply or guarantee SEO rankings, AI citations, crawler inclusion or traffic.
