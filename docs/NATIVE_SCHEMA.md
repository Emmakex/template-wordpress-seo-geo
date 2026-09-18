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

Phase 5C extends the same graph with native `BlogPosting` data for built-in WordPress posts. Phase 5D adds `BreadcrumbList` from the existing breadcrumb data authority. Phase 5E adds one explicitly configured physical `LocalBusiness` identity to the same graph; it never creates a parallel JSON-LD script.

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
- `BlogPosting`: `{canonical_url}#article`
- `BreadcrumbList`: `{canonical_url}#breadcrumb`
- `LocalBusiness`: `{home_url}/#localbusiness`

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

Phase 5B itself did not emit Person nodes on ordinary posts. Phase 5C now adds a Person node on a built-in WordPress post only when that same real WordPress user is explicitly linked as the post author. The Person keeps the same stable `{author_url}#person` identity used on the author's ProfilePage.

Google's current ProfilePage guidance requires the page's primary focus to be one affiliated Person or Organization. Native author archives meet that product intent; arbitrary pages are not promoted to ProfilePage automatically.

References:

- https://developers.google.com/search/docs/appearance/structured-data/profile-page
- https://developers.google.com/search/docs/appearance/structured-data/organization
- https://schema.org/Person
- https://schema.org/Organization
- https://schema.org/ProfilePage

## BlogPosting — Phase 5C

Phase 5C marks only singular, published built-in WordPress `post` resources as `BlogPosting`. Native pages, archives and arbitrary custom post types are not inferred to be articles.

For an eligible post the existing WebPage remains the page node and references one BlogPosting through `mainEntity`. The BlogPosting emits:

- `@type = BlogPosting`;
- stable `@id = {canonical_url}#article`;
- authoritative canonical `url`;
- `mainEntityOfPage` referencing the existing WebPage ID;
- `headline` from the visible WordPress post title;
- `datePublished` and `dateModified` from WordPress date-time APIs in ISO 8601 form with timezone;
- `inLanguage` from the same active language authority used by WebPage;
- `author` only when the real WordPress post author resolves to a public Person identity;
- `publisher` only when the site explicitly opted into the Organization identity defined in Phase 5B.

The author Person node reuses the same stable ID and public author URL used by the native ProfilePage graph. The article therefore references an existing identity rather than inventing a second author entity.

An Organization is not inferred from the site title. If the site has not explicitly opted into Organization identity, the BlogPosting simply omits `publisher`.

Phase 5C intentionally does not fabricate an `image`, logo, keywords, section, NewsArticle type or other optional properties. Image support belongs to later visible-content consistency work where the selected image can be proven representative and public.

Google currently supports `Article`, `NewsArticle` and `BlogPosting` for article structured data and lists author, publication/modification dates, headline and representative images among recommended properties; it does not require properties that the site cannot truthfully supply. Valid markup also does not guarantee a rich result.

References:

- https://developers.google.com/search/docs/appearance/structured-data/article
- https://schema.org/BlogPosting
- https://schema.org/mainEntityOfPage
- https://developer.wordpress.org/reference/functions/get_post_datetime/

## BreadcrumbList — Phase 5D

Phase 5D converts the existing reusable `BreadcrumbResolver` output into one `BreadcrumbList` node inside the same native `@graph`.

The Schema layer does not rediscover hierarchy, infer translated slugs or build a second breadcrumb model. It consumes the same breadcrumb labels and URLs used by the runtime data contract.

For an eligible page:

- `WebPage.breadcrumb` references `{canonical_url}#breadcrumb`;
- the BreadcrumbList uses the stable `{canonical_url}#breadcrumb` ID;
- `itemListElement` contains ordered `ListItem` nodes;
- positions start at 1 and increase sequentially;
- labels come from the existing breadcrumb authority;
- non-final breadcrumb items require absolute HTTP(S) URLs;
- the final item may omit `item` when the breadcrumb authority has no current-page URL;
- fewer than two breadcrumb items produce no BreadcrumbList;
- an invalid or missing intermediate URL suppresses the whole BreadcrumbList instead of emitting a misleading partial hierarchy.

The front page normally has only one breadcrumb item in the native data contract, so it does not emit BreadcrumbList markup.

Google's breadcrumb structured-data documentation requires at least two ListItems and requires `position` and `name`; `item` is optional only for the final item. Schema.org defines `WebPage.breadcrumb` as accepting a `BreadcrumbList`.

References:

- https://developers.google.com/search/docs/appearance/structured-data/breadcrumb
- https://schema.org/BreadcrumbList
- https://schema.org/breadcrumb
- https://schema.org/ListItem

## LocalBusiness — Phase 5E

Phase 5E supports one explicitly selected physical business identity without inferring local-business status from a site title, category, address-like text or theme preset.

The site identity option must explicitly select:

```php
update_option(
    'seo_geo_schema_identity',
    array( 'site_entity_type' => 'local_business' )
);
```

The business data then lives in the separate server-side option `seo_geo_schema_local_business`. The native baseline requires all of the following before it emits a LocalBusiness node:

- non-empty visible WordPress site name;
- `street_address`;
- `address_locality`;
- `postal_code`;
- `address_country`.

The public URL is always the authoritative WordPress home URL and the stable node ID is `{home_url}#localbusiness`.

The optional `type` field is accepted only when it matches the native supported LocalBusiness subtype allowlist. An unsupported value falls back to generic `LocalBusiness` rather than being emitted as arbitrary Schema type data.

Optional properties are conservative:

- `address_region` is added only when non-empty;
- `telephone` is added only when explicitly configured;
- `price_range` is added only when non-empty and shorter than 100 characters;
- `geo` is added only when latitude and longitude are both valid WGS84-range numbers supplied with at least five decimal places;
- `openingHoursSpecification` is built only from valid day lists and 24-hour opening/closing times;
- malformed optional values are omitted instead of invalidating otherwise truthful required business identity data;
- reviews, aggregate ratings, images, logos, social profiles, tax identifiers and service areas are not inferred.

`Organization` and `LocalBusiness` are mutually exclusive baseline site-identity selections. On the indexable front page, a valid LocalBusiness becomes `WebSite.publisher` and `WebPage.mainEntity`. On a BlogPosting it may be reused as `publisher`, because LocalBusiness is a subtype of Organization, without creating a second Organization identity.

Google's current LocalBusiness documentation requires `name` and a physical `address` for LocalBusiness rich-result eligibility and recommends the most specific applicable subtype plus useful real-world fields such as telephone, geo coordinates, opening hours, price range and URL. Valid structured data does not guarantee a rich result.

References:

- https://developers.google.com/search/docs/appearance/structured-data/local-business
- https://developers.google.com/search/docs/appearance/structured-data/organization
- https://schema.org/LocalBusiness
- https://schema.org/PostalAddress
- https://schema.org/GeoCoordinates
- https://schema.org/OpeningHoursSpecification

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
- an ordinary built-in post emits WebSite + WebPage + BreadcrumbList + BlogPosting + real author Person in one graph;
- WebSite, WebPage and BlogPosting IDs are deterministic and URL-derived;
- WebPage.mainEntity and BlogPosting.mainEntityOfPage reference each other through stable IDs;
- WebPage.breadcrumb references the stable BreadcrumbList node;
- BreadcrumbList contains at least two sequential ListItems derived from the reusable breadcrumb authority;
- breadcrumb labels and URLs match the runtime breadcrumb data contract and do not invent missing intermediate links;
- BlogPosting headline/dates/language come from real WordPress content state;
- BlogPosting author reuses the stable WordPress author Person identity;
- BlogPosting publisher appears only after explicit Organization opt-in;
- a post without Organization opt-in does not fabricate publisher or image;
- an explicit organization opt-in adds one Organization on the home page and links WebSite.publisher to its stable ID;
- an explicit LocalBusiness opt-in with a complete physical address adds exactly one typed LocalBusiness node on the home page;
- LocalBusiness uses the stable `{home_url}#localbusiness` ID and is linked from WebSite.publisher and WebPage.mainEntity;
- LocalBusiness address, coordinates and opening-hours structures are validated before output;
- incomplete required LocalBusiness address data suppresses the LocalBusiness node rather than fabricating missing fields;
- Organization and LocalBusiness are never emitted together as competing primary site identities;
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
