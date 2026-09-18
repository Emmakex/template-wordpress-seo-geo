# Native Schema Graph

## Purpose

The self-contained theme owns one native Schema.org JSON-LD graph when native SEO authority is active. Phase 5A establishes the graph foundation without requiring a Schema plugin and without inventing organization, person, article or local-business data.

Structured data is a machine-readable representation of page content. It does not guarantee rankings, rich-result display or inclusion in any search/AI experience.

## Output authority

Schema is an explicit signal in `SeoOutputAuthority`. The baseline provider is `native`, so the built theme owns one Schema graph alongside canonical, robots, description, Open Graph and hreflang.

The Phase 5A presenter emits at most one:

```html
<script type="application/ld+json">…</script>
```

Native Schema is omitted when the current request is not indexable. This keeps structured data aligned with the existing indexability contract instead of creating a second discovery policy.

## Graph contract

The output object uses:

```json
{
  "@context": "https://schema.org",
  "@graph": []
}
```

Phase 5A may contain only these node types:

- `WebSite`;
- `WebPage`;
- `BreadcrumbList` with ordered `ListItem` entries.

Later Phase 5 microphases may add entity-specific nodes only after their data contracts are explicit.

## Stable IDs

The native graph uses deterministic identifiers:

- site identity: `{home-url}#website`;
- page identity: `{canonical-url}#webpage`;
- breadcrumb identity: `{canonical-url}#breadcrumb`.

`WebPage.isPartOf` references the stable `WebSite` ID. When a breadcrumb graph exists, `WebPage.breadcrumb` references the stable `BreadcrumbList` ID.

The `WebSite` identity remains global rather than creating a different site entity merely because a page is localized.

## Canonical and indexability

`SchemaGraphBuilder` consumes the existing `IndexabilityResolver` and `CanonicalResolver`.

Rules:

- no native graph is emitted for `noindex`, redirect, not-found or gone states;
- the `WebPage.url` is the same authoritative canonical URL already used by native SEO;
- localized authoritative routes therefore use the localized self-canonical proven in Phase 4;
- non-authoritative localized routes remain Schema-free.

Schema does not reconstruct canonical or language routing independently.

## Language

`WebPage.inLanguage` comes from the authoritative localized SEO context when one exists. Otherwise it uses the current WordPress locale.

WordPress locale separators are normalized to BCP-47-style tags, for example:

- `es_ES` → `es-ES`;
- `en_US` → `en-US`.

Invalid language tags are omitted rather than guessed.

## BreadcrumbList

The Schema layer reuses the existing `BreadcrumbResolver`. It does not build a second hierarchy.

A `BreadcrumbList` is emitted only when:

- there are at least two breadcrumb items;
- every item has a non-empty label;
- every item has an absolute HTTP(S) URL.

If the visible/navigation breadcrumb contract intentionally withholds a URL—for example to avoid crossing a language boundary—the entire structured breadcrumb node is omitted rather than fabricating a destination.

Positions start at 1 and remain ordered.

## Visible-content consistency

Phase 5A uses conservative page/site names already available from WordPress. It does not add claims, ratings, authors, organizations, addresses or other entities merely to make the graph larger.

Entity-specific structured data must be supported by real configured or visible content before a later phase may emit it.

## Security and serialization

The graph is serialized with `wp_json_encode()` using Unicode/slash preservation plus JSON hex escaping for HTML-sensitive characters. WordPress prints the non-executable `application/ld+json` script tag.

No frontend JavaScript library or remote dependency is added.

## Phase 5A acceptance

`Schema Graph CI` runs WordPress 7.1 / PHP 8.2 with the built self-contained theme and zero active plugins. It verifies:

1. one JSON-LD graph on an indexable hierarchical page;
2. exactly one `WebSite`, one `WebPage` and one `BreadcrumbList`;
3. stable IDs and `WebPage.url` equal to the native canonical;
4. ordered Home → parent → child breadcrumb positions and URLs;
5. `inLanguage=en-US` for the default fixture;
6. zero native Schema on a noindex search request;
7. authoritative ES/EN translated routes use localized canonical URLs and `es-ES` / `en-US`;
8. localized breadcrumb URLs stay inside the active language;
9. zero active plugins and clean PHP runtime/debug logs.

## Explicit Phase 5A limitations

Phase 5A does **not** emit:

- `Organization`;
- `Person` or `ProfilePage`;
- `Article`;
- `LocalBusiness`;
- product/review/rating nodes;
- inferred entities from arbitrary text.

Those require separate data/visibility contracts and acceptance in later Phase 5 microphases.

## References

- Google Search Central — structured data general guidelines: https://developers.google.com/search/docs/appearance/structured-data/sd-policies
- Google Search Central — breadcrumb structured data: https://developers.google.com/search/docs/appearance/structured-data/breadcrumb
- Schema.org — WebPage: https://schema.org/WebPage
- Schema.org — BreadcrumbList: https://schema.org/BreadcrumbList
- Schema.org — inLanguage: https://schema.org/inLanguage
