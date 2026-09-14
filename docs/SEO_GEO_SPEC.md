# SEO + GEO Specification

## Scope

This document defines the functional contract for technical SEO and Generative Engine Optimization (GEO). GEO is an enhancement layer on top of sound SEO; it is not treated as a separate ranking system that can bypass crawlability, indexing, helpful content or page quality.

Google's current guidance for AI Overviews/AI Mode states that the existing SEO fundamentals remain relevant and that there are no additional technical requirements or special AI markup required to appear. The project therefore prioritizes standard Search correctness first.

Reference: https://developers.google.com/search/docs/appearance/ai-features

## Native SEO mode

The Core plugin may provide native output for sites that do not select an external SEO provider.

### Required outputs

- document title integration through WordPress APIs;
- meta description;
- canonical URL;
- robots directives;
- Open Graph baseline;
- social image resolution;
- published/modified metadata when relevant;
- breadcrumbs data source;
- sitemap integration/extension only where WordPress core is insufficient;
- stable Schema graph.

### Compatibility mode

When Yoast, Rank Math, AIOSEO or another explicitly supported SEO provider is active, overlapping output must be delegated instead of duplicated. Integration tests will assert single ownership of canonical, robots and Schema responsibilities.

## Indexability model

Every public URL should have an explicit resolved state:

```text
indexable
noindex-follow
noindex-nofollow
redirect
not-found
410-gone
```

The same resolver should be consumable by metadata, sitemap and alternate-format modules so a noindex page cannot accidentally remain in a generated sitemap or AI discovery index.

## Canonical rules

- One canonical per indexable HTML document.
- Self-referencing by default unless a deliberate canonical relationship exists.
- A translated page canonicals to its own localized URL, not another language.
- Markdown alternates never become canonical targets for the HTML page.
- Query-string policy is explicit and testable.
- Paginated archives follow a documented archive policy rather than blanket canonicalization to page 1.

## Robots

`robots.txt` and page-level meta/X-Robots directives solve different concerns and must not be conflated.

Crawler policy should support at minimum:

- general search indexing;
- OpenAI search discovery (`OAI-SearchBot`) as a distinct control;
- OpenAI training crawler (`GPTBot`) as a distinct control;
- future crawler entries through filterable configuration.

OpenAI reference: https://help.openai.com/en/articles/12627856

The default product must not claim that allowing a crawler guarantees inclusion or citation.

## Schema graph

The plugin will emit one coherent JSON-LD graph where native mode is authoritative.

Initial entities:

- `WebSite`
- `WebPage`
- `Organization`
- `Person`
- `ProfilePage`
- `Article` / `BlogPosting` where appropriate
- `BreadcrumbList`
- `LocalBusiness` and the most specific valid subtype when local preset is active
- WooCommerce/Product integration later in the ecommerce phase

### Graph principles

- Stable deterministic `@id` values.
- Data must match visible content.
- Do not emit properties merely because Schema.org allows them; emit only supported, truthful data.
- Page-specific nodes reference shared Organization/Person nodes where identity is truly the same.
- `inLanguage` reflects the resolved locale of the content.
- Author/profile information is reusable across articles and profile pages.
- Avoid deprecated or unsupported rich-result hacks.

Google structured data references:
- https://developers.google.com/search/docs/appearance/structured-data/organization
- https://developers.google.com/search/docs/appearance/structured-data/local-business
- https://developers.google.com/search/docs/appearance/structured-data/profile-page

## Content semantics for GEO

Reusable patterns should make important information easy to understand and retrieve without forcing a rigid editorial formula. Supported semantic building blocks include:

- concise answer/summary;
- key facts;
- definitions;
- steps/process;
- comparison table;
- evidence/data;
- source list;
- FAQ content where useful to users;
- author expertise/provenance;
- reviewed/updated date when editorially meaningful.

These patterns are editorial tools, not a promise of AI citations.

## Machine-friendly alternate formats

### `llms.txt`

Optional and enabled by configuration. It is treated as an interoperability experiment, not a Google ranking requirement.

The generator should support a root file and language-scoped views when appropriate. Its entries are derived from public/indexable content selected by policy, not from every WordPress URL.

Reference proposal: https://llmstxt.org/

### Markdown alternates

Optional selected content may expose a Markdown representation.

Requirements:

- same factual content/provenance as the source page;
- correct localized version;
- explicit relationship to the HTML resource;
- no accidental creation of a competing canonical/indexable surface;
- disableable globally and per content type;
- cacheable and deterministic output;
- no leakage of private fields, drafts, credentials or admin metadata.

Where used, discovery may expose `rel="alternate"` with `type="text/markdown"` and `rel="describedby"` for the relevant `llms.txt`, following the llms.txt v2 proposal.

## Internal linking

Presets and patterns should encourage:

- crawlable `<a href>` links;
- topic/service relationships;
- breadcrumbs;
- related content based on explicit relationships or taxonomy;
- no JavaScript-only navigation for primary content discovery.

## Authors and provenance

Author profiles may expose:

- display name;
- role/title;
- biography;
- expertise/topics;
- photo;
- public profile URLs / `sameAs`;
- organization relationship;
- editorial dates when meaningful.

The plugin must not fabricate credentials, experience or external identities.

## SEO/GEO acceptance

A release touching public output must validate, when applicable:

- exactly one canonical;
- correct indexability state;
- valid robots policy;
- no duplicate provider output;
- correct localized alternates;
- Schema JSON parses and passes project invariants;
- Schema matches page language and visible facts;
- sitemap does not include non-indexable resources;
- alternate Markdown/llms outputs do not expose private content;
- primary navigation and meaningful internal links are crawlable without client-side execution.
