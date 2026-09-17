# Native SEO Contract

## Scope

Phase 3A established the first public SEO-output layer in `seo-geo-core`. Its purpose is correctness and single ownership, not dependency on an external SEO plugin.

The native layer owns overlapping SEO output while the runtime SEO provider resolves to `native`. The installable baseline is a self-contained theme: external SEO plugins are optional compatibility targets, never prerequisites for canonical, robots, descriptions or discovery metadata.

Phase 3A covers:

- output authority for canonical, meta description and robots;
- explicit request indexability states;
- canonical resolution;
- native meta-description resolution;
- page-level robots policy through WordPress APIs;
- runtime acceptance proving non-duplicated output in a clean WordPress fixture.

Phase 3C extends the same authority model with native Open Graph metadata and a reusable breadcrumb data contract. See `docs/DISCOVERY_METADATA.md` for that contract.

Schema, hreflang, multilingual canonical behavior and crawler-specific GEO controls remain later phases.

## Authority model

`SeoOutputAuthority` is the server-side authority decision for overlapping SEO signals.

Current signals:

- `canonical`;
- `meta_description`;
- `robots`;
- `open_graph`.

Rules:

1. The runtime integration detector selects the active provider.
2. A clean install resolves to `native`.
3. Native Core emits a supported overlapping signal only when the provider is `native`.
4. Browser state, request parameters or content output cannot grant SEO ownership.
5. Optional provider adapters must preserve this single-owner invariant if they are introduced later.

The authority contract intentionally precedes optional provider adapters so duplication prevention is architectural rather than a collection of provider-specific patches.

## Indexability model

`IndexabilityResolver` produces one explicit state for every request:

| State | Meaning |
| --- | --- |
| `indexable` | Public HTML may be indexed. |
| `noindex-follow` | Do not index this URL; links may be followed. |
| `noindex-nofollow` | Do not index and request no link following. |
| `redirect` | Reserved for deliberate redirect policy. |
| `not-found` | WordPress resolved the request as 404. |
| `410-gone` | Reserved for deliberate gone-content policy. |

Native defaults:

- 404 → `not-found`;
- site visibility disabled → `noindex-nofollow`;
- preview → `noindex-nofollow`;
- search/embed → `noindex-follow`;
- password-protected singular content → `noindex-follow`;
- otherwise → `indexable`.

The `seo_geo_indexability_state` filter may override the native result only with one of the six supported states. Unsupported values do not become policy.

The same resolver is reused by discovery metadata and is intended to be reused later by sitemaps and agent-friendly alternate formats, preventing a URL from being `noindex` in metadata while accidentally remaining eligible elsewhere.

## Canonical contract

Native canonical output is emitted only for `indexable` requests.

Resolution rules:

- singular content uses WordPress `wp_get_canonical_url()`;
- the front page uses `home_url( '/' )`;
- other indexable public views resolve their current paginated URL with `get_pagenum_link()`;
- non-indexable states do not emit the native canonical.

The resolved value can be changed with `seo_geo_canonical_url`. An empty result suppresses native canonical output for that request.

When native Core owns canonical output, it removes WordPress Core's `rel_canonical` callback and emits the resolved canonical once. This prevents duplicate canonical output while retaining WordPress canonical semantics as the underlying singular resolver.

Multilingual current-language canonical behavior is not claimed complete yet; it closes with the native language-provider work in Phase 4.

## Meta description contract

Native description sources are deliberately conservative:

- singular content: explicit excerpt first, otherwise visible post content;
- front page/posts home: site tagline;
- category/tag/taxonomy archive: term description;
- unsupported request types: no native description rather than fabricated copy.

Normalization:

- shortcodes are removed;
- HTML is stripped;
- entities are decoded;
- whitespace is collapsed;
- output is escaped at render time;
- native output is capped at 160 characters and truncated with an ellipsis when required.

`seo_geo_meta_description` may replace or suppress the resolved value.

The 160-character ceiling is a product consistency rule, not a claim that search engines use a fixed snippet length or will display the supplied description verbatim.

## Robots contract

Core does not print an independent second robots tag. Native policy integrates through WordPress's official `wp_robots` filter so WordPress remains the single renderer of the page-level robots meta element.

Native behavior:

- `indexable`: preserve WordPress's existing directives;
- `noindex-nofollow`: ensure `noindex,nofollow`;
- other non-indexable states: ensure `noindex,follow`.

`robots.txt`, crawler-specific policy and page-level robots metadata remain separate concerns. OAI-SearchBot/GPTBot controls belong to the later GEO/crawler-policy phase.

## Discovery metadata contract

Phase 3C adds Open Graph as another explicitly owned signal rather than a second SEO stack.

The native presenter:

- reuses the canonical URL as `og:url`;
- reuses the native description as `og:description`;
- follows the WordPress document title for `og:title`;
- emits `article` for single posts and `website` for the remaining baseline types;
- emits site name and locale when available;
- uses a featured image or configured site icon when one exists;
- does not fabricate a social image when neither source exists;
- suppresses the native Open Graph baseline on non-indexable requests.

Breadcrumb hierarchy is resolved separately by `BreadcrumbResolver` and exposed through `Runtime::breadcrumbs()`. Phase 3C does not yet render BreadcrumbList JSON-LD; later Schema and any visible breadcrumb component must consume the same data contract.

See `docs/DISCOVERY_METADATA.md` for exact fields and acceptance rules.

## WordPress integration

`NativeSeoPresenter` registers only the signals that native Core owns.

It:

- removes WordPress Core's `rel_canonical` action only while native Core owns canonical output;
- renders Core-resolved canonical, meta description and Open Graph metadata in `wp_head`;
- modifies robots directives with `wp_robots` only while native Core owns robots;
- escapes URL and attribute output at the final render boundary.

No frontend JavaScript is required.

## Runtime acceptance

The disposable WordPress 7.1 / PHP 8.2 smoke fixture must preserve the Phase 3A contract:

- runtime SEO provider is `native`;
- output authority is `native`;
- a published singular fixture exposes exactly one canonical matching its public permalink;
- that fixture exposes exactly one expected meta description;
- a search fixture exposes no native canonical;
- that search fixture exposes exactly one WordPress robots meta element;
- search robots policy contains `noindex` and `follow` without `nofollow`;
- no PHP fatal/warning/notice or uncaught runtime error is emitted.

The self-contained theme gate additionally proves zero active plugins and, from Phase 3C onward, the discovery metadata and breadcrumb assertions documented in `docs/DISCOVERY_METADATA.md`.

WPCS and PHPStan level 6 remain mandatory with no new suppression or analysis baseline.

## Optional provider compatibility boundary

The product baseline does not require Yoast, Rank Math, AIOSEO or another SEO plugin. Detection of an external provider is an ownership safety boundary so Core can avoid competing output.

Full provider-specific interoperability may be implemented later as optional compatibility work, but it must not become a prerequisite for the theme to boot or for the native SEO/GEO baseline to function.

## Search and GEO claims

Correct canonical, description, robots and social metadata improve technical consistency but do not guarantee ranking, indexing, social-preview selection, AI citation or inclusion in any search feature. GEO functionality continues to build on ordinary crawlability, indexability, content quality and truthful visible information rather than special ranking markup.
