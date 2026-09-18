# Native Translation Relationships

## Purpose

Phase 4C1 adds an explicit server-side relationship contract for native multilingual content. It does **not** make localized routes indexable and does not emit localized canonicals, `hreflang` or `x-default`.

The contract exists so later multilingual SEO output can consume verified translation relationships instead of inferring them from URL prefixes or from the fact that the same WordPress object is reachable through more than one route.

## Storage contract

Each translated post stores two private post-meta values.

Language ownership:

```text
_seo_geo_language
```

Example:

```text
es
```

Reciprocal translation set:

```text
_seo_geo_translation_set
```

Example:

```php
array(
    'es' => 10,
    'en' => 20,
)
```

Every member of the same translation set stores the same normalized map. The individual `_seo_geo_language` value identifies which member language belongs to that post.

These metadata values are server-side authority. A URL prefix, query parameter or browser value cannot create or alter a translation relationship.

## Resolver

`NativeTranslationRelationshipResolver` exposes:

```php
Runtime::translations()?->resolve( $post_id )
```

A successful resolution returns a `TranslationRelationship` containing:

- current post ID;
- current language code;
- normalized `language_code => post_id` members;
- lookup of a post ID by language code.

Failure returns `null`. There is no partial relationship result.

## Fail-closed validation

A relationship is valid only when all of the following are true:

- the requested post exists;
- the requested post is publicly viewable;
- its declared language exists in the validated native language configuration;
- the translation set contains at least two unique posts;
- every language key belongs to the configured native language set;
- the current post appears under its own declared language;
- every member exists and is publicly viewable;
- every member has the same post type as the current post;
- every member declares the language represented by its map key;
- every member stores the same normalized reciprocal translation map.

Any missing post, draft/private member, unsupported language, duplicate post ID, post-type mismatch, language mismatch or non-reciprocal map invalidates the complete set.

The native baseline does not require a translation for every configured language. It requires every translation that is declared in a set to be valid and reciprocal.

## Public visibility

The resolver uses WordPress public-viewability rules rather than treating a `publish` string alone as sufficient authority. Draft, private and otherwise non-publicly-viewable members do not qualify as translations for public SEO use.

This distinction is important for later `hreflang` and canonical generation because alternates must never expose draft or private resources.

## Relationship identity

Phase 4C1 deliberately uses the reciprocal member map itself as the relationship contract. It does not introduce a public translation-set ID, taxonomy or custom post type.

This keeps the native baseline small while preserving a verifiable invariant: every valid member independently confirms the same language-to-resource map.

If a future persistence layer replaces post meta, it must preserve the same normalized resolver contract before SEO output changes.

## SEO staging

Phase 4B remains authoritative for frontend indexability during 4C1:

- prefixed localized routes remain `noindex,follow`;
- native canonical remains suppressed on those staged routes;
- native Open Graph remains suppressed on those staged routes;
- no `hreflang` or `x-default` is emitted;
- unprefixed routes keep their existing authority.

Phase 4C1 therefore cannot publish a new multilingual SEO surface merely because metadata is present.

## Next step

A later Phase 4C microphase may consume only a successfully resolved `TranslationRelationship` to introduce:

- localized self-referencing canonical URLs;
- reciprocal `hreflang`;
- optional explicit `x-default`;
- promotion of validated localized routes from staged `noindex` to indexable;
- locale-aware breadcrumb and internal URL helpers.

Those changes require their own real-HTTP acceptance and must continue to fail closed when a translation becomes draft, private, missing or non-reciprocal.

## Acceptance

The Phase 4C1 zero-plugin WordPress fixture proves:

1. an explicit reciprocal ES/EN set resolves from either member;
2. the resolver returns the correct current language and normalized member map;
3. a non-reciprocal member invalidates the relationship;
4. a draft translated member invalidates the relationship;
5. a member whose declared language disagrees with its map key invalidates the relationship;
6. restoring valid public reciprocal data restores resolution;
7. the fixture runs with the built self-contained theme and zero active plugins;
8. runtime/debug logs remain free of PHP fatal errors, warnings, notices and uncaught errors.

Passing this contract does not claim that localized URLs are indexable or that search engines will rank them differently.
