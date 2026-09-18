# Native Schema Graph

## Scope

Phase 5A establishes the native JSON-LD graph foundation for the self-contained theme. The baseline remains plugin-free: a clean WordPress installation plus the built theme is sufficient to emit the graph when native SEO authority is active.

Phase 5A established the baseline:

- `WebSite`;
- `WebPage`;
- stable deterministic `@id` values;
- canonical URL reuse;
- locale-aware `inLanguage`;
- one JSON-LD graph owner.

Phase 5B extends that same graph with conservative identity nodes:

- opt-in site `Organization` identity on the front page;
- WordPress author `Person` identity on native author archives;
- `ProfilePage` for author archives whose primary subject is that Person;
- explicit graph references rather than duplicated identity objects.

Article, BreadcrumbList and LocalBusiness remain later Phase 5 microphases and must reuse this graph rather than create parallel JSON-LD scripts.

## Ownership

Schema is an explicit signal in `SeoOutputAuthority`.

Native output is registered only when:

```text
provider = native
signal   = schema
```

The baseline therefore emits at most one project-owned Schema graph. Optional third-party SEO providers are outside the plugin-free baseline and must not cause duplicate project output.

## Output contract

For an indexable public HTML request, the native presenter emits exactly one:

```html
<script id="seo-geo-schema-graph" type="application/ld+json">...</script>
```

The payload uses:

```json
{
  "@context": "https://schema.org",
  "@graph": []
}
```

For a non-indexable request, the native graph is omitted.

## Stable IDs

IDs are derived only from authoritative public URLs:

- `WebSite`: `{home_url}/#website`
- `WebPage`: `{canonical_url}#webpage`

An existing URL fragment is replaced before the stable graph fragment is appended. IDs are not generated from database IDs, random values, request timestamps or translated labels.

The same logical node must therefore keep the same ID across renders while its authoritative URL remains unchanged.

## WebSite node

Phase 5A emits:

- `@type = WebSite`;
- stable `@id`;
- absolute site `url`;
- site `name` only when WordPress provides a non-empty value.

The graph does not fabricate Organization, publisher, logo, search action or social identity data.

## WebPage node

Phase 5A emits:

- `@type = WebPage`;
- stable `@id`;
- authoritative canonical `url`;
- `isPartOf` referencing the WebSite node;
- `inLanguage` from the current WordPress locale normalized to BCP 47 form;
- document `name` when WordPress provides a non-empty visible title.

The canonical resolver remains the source of truth. Localized pages therefore inherit the already-validated localized canonical contract rather than rebuilding language URLs inside Schema.

## Identity nodes — Phase 5B

### Organization

A site title is not enough to infer that a website represents an organization.

The native Organization node is therefore opt-in through the server-side option:

```php
update_option(
    'seo_geo_schema_identity',
    array( 'site_entity_type' => 'organization' )
);
```

When that explicit choice is present and the front page is indexable, the graph adds one stable Organization node:

- `@type = Organization`;
- `@id = {home_url}/#organization`;
- `name` from the visible WordPress site name;
- `url` from the authoritative site home URL.

The front-page WebSite node references that Organization through `publisher`.

Phase 5B intentionally does not infer or fabricate legal names, addresses, telephone numbers, social profiles, tax identifiers or logos. Richer Organization data belongs to later onboarding/preset work when an authoritative value and visible-content policy exist.

### Person + ProfilePage

A public WordPress author archive represents an existing editorial identity rather than a guessed site owner.

For an indexable native author archive, the existing WebPage node is specialized to `ProfilePage` and the graph adds one Person node:

- Person `@id = {author_url}#person`;
- Person `name` from the WordPress user's visible display name;
- Person `url` from the public author archive;
- Person `description` only when the user has a non-empty WordPress biography;
- ProfilePage `mainEntity` references the Person;
- Person `mainEntityOfPage` references the existing page node.

No Person node is emitted on ordinary posts merely because they have an author. Article-to-author linkage is a separate Phase 5 microphase.

Google's current ProfilePage guidance requires the page's primary focus to be one affiliated Person or Organization. Native author archives meet that product intent; arbitrary pages are not promoted to ProfilePage automatically.

References:

- https://developers.google.com/search/docs/appearance/structured-data/profile-page
- https://developers.google.com/search/docs/appearance/structured-data/organization
- https://schema.org/Person
- https://schema.org/Organization
- https://schema.org/ProfilePage

## Indexability

The graph builder consumes the existing `IndexabilityResolver`.

No native Schema graph is emitted for:

- search results;
- previews;
- password-protected singular content;
- explicit noindex states;
- 404/gone/redirect states;
- staged multilingual routes that have not been promoted to authoritative localized SEO.

This keeps Schema aligned with the same indexability decision used by canonical, robots and Open Graph.

## Language

`inLanguage` is derived from the active WordPress locale exposed through `LanguageManager`.

Examples:

- `en_US` -> `en-US`
- `es_ES` -> `es-ES`

The Schema layer does not infer a language from URL text by itself.

## Safety and quality rules

- JSON-LD must be parseable JSON.
- Output must describe the current public page and visible site identity.
- No unsupported rich-result or ranking claims are made.
- Optional properties are omitted when the project does not have truthful authoritative data.
- No frontend JavaScript generates or mutates the graph.
- Future entity nodes must reference existing stable IDs instead of duplicating nodes.

Google documents JSON-LD as a supported/recommended structured-data format and states that valid structured data does not guarantee a rich result. Schema.org defines `WebSite`, `WebPage` and `inLanguage`; `inLanguage` uses IETF BCP 47 language codes.

References:

- https://developers.google.com/search/docs/appearance/structured-data/sd-policies
- https://schema.org/WebSite
- https://schema.org/WebPage
- https://schema.org/inLanguage

## Acceptance

The zero-plugin WordPress smoke must prove:

- the built theme contains the Schema graph sources;
- exactly one project-owned JSON-LD script exists on the representative indexable post;
- the JSON parses;
- `@context` is `https://schema.org`;
- exactly one WebSite and one WebPage baseline node exist on an ordinary post;
- WebSite and WebPage IDs are deterministic and URL-derived;
- an explicit organization opt-in adds one Organization on the home page and links WebSite.publisher to its stable ID;
- the same organization data is not sprayed onto ordinary posts;
- a public author archive emits WebSite + ProfilePage + Person;
- ProfilePage.mainEntity and Person.mainEntityOfPage reference each other through stable IDs;
- Person name/description come from the real WordPress author identity;
- WebPage URL equals the canonical URL;
- WebPage references the WebSite node through `isPartOf`;
- `inLanguage` matches the active locale;
- a noindex search request emits no native Schema graph;
- zero plugins are active;
- runtime diagnostics stay clean.
