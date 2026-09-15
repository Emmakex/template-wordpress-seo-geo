# Native SEO Contract

## Scope

Phase 3A establishes the first public SEO-output layer in `seo-geo-core`. Its purpose is correctness and single ownership, not feature breadth.

The native layer owns overlapping SEO output only when the runtime SEO provider resolves to `native`. When a supported external SEO provider is detected, Core keeps its internal policy/resolver services available but does not register the overlapping native frontend presenter.

Phase 3A covers:

- output authority for canonical, meta description and robots;
- explicit request indexability states;
- canonical resolution;
- native meta-description resolution;
- page-level robots policy through WordPress APIs;
- runtime acceptance proving non-duplicated output in a clean WordPress fixture.

Phase 3A does **not** yet implement Open Graph, breadcrumbs, Schema, hreflang, localized canonical adapters or provider-specific Yoast/Rank Math/AIOSEO integration hooks. Those belong to later Phase 3/4 work.

## Authority model

`SeoOutputAuthority` is the server-side authority decision for overlapping SEO signals.

Current signals:

- `canonical`;
- `meta_description`;
- `robots`.

Rules:

1. The runtime integration detector selects the active provider.
2. A clean install resolves to `native`.
3. Native Core emits a supported overlapping signal only when the provider is `native`.
4. Browser state, request parameters or content output cannot grant SEO ownership.
5. Provider adapters must preserve this single-owner invariant when introduced.

The authority contract intentionally precedes provider adapters so duplication prevention is architectural rather than a collection of provider-specific patches.

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

Native defaults in Phase 3A:

- 404 → `not-found`;
- site visibility disabled → `noindex-nofollow`;
- preview → `noindex-nofollow`;
- search/embed → `noindex-follow`;
- password-protected singular content → `noindex-follow`;
- otherwise → `indexable`.

The `seo_geo_indexability_state` filter may override the native result only with one of the six supported states. Unsupported values do not become policy.

The same resolver is intended to be reused later by sitemaps and agent-friendly alternate formats, preventing a URL from being `noindex` in metadata while accidentally remaining eligible elsewhere.

## Canonical contract

Native canonical output is emitted only for `indexable` requests.

Resolution rules in Phase 3A:

- singular content uses WordPress `wp_get_canonical_url()`;
- the front page uses `home_url( '/' )`;
- other indexable public views resolve their current paginated URL with `get_pagenum_link()`;
- non-indexable states do not emit the native canonical.

The resolved value can be changed with `seo_geo_canonical_url`. An empty result suppresses native canonical output for that request.

When native Core owns canonical output, it removes WordPress Core's `rel_canonical` callback and emits the resolved canonical once. This prevents Core + plugin duplication while retaining WordPress canonical semantics as the underlying singular resolver.

Multilingual current-language canonical behavior is not claimed complete in Phase 3A; it closes with the language-provider work in Phase 4.

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

## WordPress integration

`NativeSeoPresenter` registers only when Core is the active native SEO authority.

It:

- removes WordPress Core's `rel_canonical` action to avoid a duplicate canonical;
- renders the Core-resolved canonical and description in `wp_head`;
- modifies robots directives with `wp_robots`;
- escapes URL and attribute output at the final render boundary.

No frontend JavaScript is required.

## Phase 3A runtime acceptance

The disposable WordPress 7.1 / PHP 8.2 smoke fixture must prove all of the following:

- runtime SEO provider is `native`;
- output authority is `native`;
- canonical is owned by native Core;
- home exposes exactly one canonical;
- a published singular fixture exposes exactly one canonical matching its public permalink;
- that fixture exposes exactly one expected meta description;
- a search fixture exposes no native canonical;
- that search fixture exposes exactly one WordPress robots meta element;
- search robots policy contains `noindex` and `follow` without `nofollow`;
- no PHP fatal/warning/notice or uncaught runtime error is emitted.

WPCS and PHPStan level 6 remain mandatory with no new suppression or analysis baseline.

## Provider compatibility boundary

Phase 3A detects supported providers but does not yet claim full provider-specific compatibility. Phase 3B must add explicit adapters/acceptance for Yoast, Rank Math and AIOSEO and prove that overlapping canonical/robots/description output is not duplicated.

Until those provider fixtures pass, external-provider detection is an ownership safety boundary, not a completed integration claim.

## Search and GEO claims

Correct canonical, description and robots metadata improve technical consistency but do not guarantee ranking, indexing, AI citation or inclusion in any search feature. GEO functionality continues to build on ordinary crawlability, indexability, content quality and truthful visible information rather than special ranking markup.
