# Native Discovery Metadata

## Purpose

Phase 3C extends the self-contained SEO foundation with native social/discovery metadata and a reusable breadcrumb data contract. The installable theme must provide this baseline without requiring an SEO, social-sharing or breadcrumb plugin.

This contract does not promise rankings, inclusion in a social preview, citation by an AI system or any other external outcome. It defines deterministic technical output owned by the theme runtime.

## Open Graph ownership

`SeoOutputAuthority::SIGNAL_OPEN_GRAPH` is an explicit output signal. Native Core emits Open Graph metadata only while the active SEO provider is `native`.

Open Graph output is generated from the same request state already used by native SEO. It is emitted for indexable public HTML requests and reuses existing canonical and description policy rather than resolving competing values independently.

Baseline properties:

- `og:title`: normalized WordPress document title from `wp_get_document_title()`;
- `og:type`: `article` for single posts and `website` for the remaining baseline request types;
- `og:url`: the native canonical URL;
- `og:site_name`: the WordPress site title when non-empty;
- `og:description`: the same resolved plain-text description used by the native meta description when available;
- `og:locale`: the current WordPress locale when available;
- `og:image`: the singular featured image, falling back to the configured WordPress site icon when one exists.

The runtime does **not** invent a social image when neither a featured image nor site icon is configured. A project should configure a real representative image rather than shipping a misleading placeholder.

The `seo_geo_open_graph_metadata` filter may adjust the final property map. After filtering, Core accepts only non-empty string values whose property names begin with `og:` or `article:`; arbitrary head markup cannot be injected through this filter.

Twitter/X card-specific metadata is outside Phase 3C. It can be added later only if it has a clear owner and acceptance contract.

## Title and description policy

There is one title source and one description source for the baseline:

- the social title follows WordPress's current document-title resolution;
- the social description reuses `MetaDescriptionResolver`;
- the social URL reuses `CanonicalResolver`;
- non-indexable requests do not receive the native Open Graph baseline.

This keeps the visible document title, canonical URL, meta description and social metadata aligned without copying policy into multiple modules.

## Breadcrumb data contract

`BreadcrumbResolver` produces data only. Phase 3C does not add a second visual breadcrumb component and does not emit BreadcrumbList JSON-LD yet.

The resolver is available through:

```php
SeoGeo\Core\Runtime::breadcrumbs()?->resolve();
```

Each item has this stable shape:

```php
array(
    'label'   => 'Current page',
    'url'     => 'https://example.com/current-page/', // or null when unavailable.
    'current' => true,
);
```

Rules:

- the first item represents the site root;
- the front page resolves to one current root item;
- hierarchical singular content includes its parent chain;
- regular posts include the configured posts page when WordPress has one;
- hierarchical taxonomy archives include their term ancestors;
- post-type and author archives resolve a current item;
- other query types fall back to the normalized WordPress document title;
- exactly one final item represents the current request for supported contexts.

Future visible breadcrumb UI and future `BreadcrumbList` Schema must consume this resolver rather than rebuild hierarchy independently.

## Packaging invariant

Both `OpenGraphResolver` and `BreadcrumbResolver` live under `packages/seo-geo-core/src` and are copied into the installable theme by `scripts/build-theme-package.sh`. No plugin activation is required.

The Self-contained Theme CI remains the authoritative runtime proof for the distribution model.

## Acceptance

Phase 3C is accepted only when the built theme on disposable WordPress 7.1 / PHP 8.2 demonstrates:

- zero active plugins;
- native Open Graph ownership;
- exactly one `og:title`, `og:type`, `og:url`, `og:site_name` and `og:description` on the representative indexable post;
- `og:url` equals the canonical URL;
- `og:description` equals the native description source;
- no fabricated `og:image` when no image source exists;
- breadcrumb data resolves root + current post in the representative singular fixture;
- canonical/meta-description/robots behavior from Phase 3A remains unchanged;
- no PHP runtime diagnostics;
- existing accessibility and performance budgets remain green.

## Primary references

- WordPress document title: https://developer.wordpress.org/reference/functions/wp_get_document_title/
- WordPress featured image URL: https://developer.wordpress.org/reference/functions/get_the_post_thumbnail_url/
- Open Graph protocol: https://ogp.me/
