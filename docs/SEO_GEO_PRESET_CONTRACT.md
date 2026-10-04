# SEO/GEO-First Preset Contract

## Product rule

SEO/GEO is not an optional layer added after visual design. It is the runtime contract of the Theme and **every preset must preserve it**.

Corporate, Local Business, Publisher, Ecommerce and SaaS / Digital Product may differ in information architecture, visual composition and Schema expectations, but none may fork, weaken or bypass the shared SEO/GEO Core.

## HTML and crawlability

Every preset must preserve:

- server-rendered semantic HTML as the default delivery model;
- one page-owned `<h1>` on normal indexable content surfaces;
- structural landmarks from shared templates (`header`, `main`, `footer`, navigation landmarks);
- primary/internal navigation through normal crawlable `<a href>` links;
- meaningful content available without client-side JavaScript;
- no JavaScript-only primary navigation, content discovery or canonical content;
- native WordPress media markup so responsive `srcset`/`sizes`, intrinsic dimensions and platform image optimizations remain available.

Visual preset CSS may change presentation only. It must not change the canonical URL, page identity, indexability, language relationship or factual meaning of the content.

## SEO authority

Every preset composes the same shared authority model:

- canonical → shared SEO/GEO Core or the explicitly detected external provider;
- indexability/robots → shared Core authority;
- localized canonical/hreflang → shared multilingual authority;
- Open Graph → shared Core/provider authority;
- Schema → one coherent shared graph owner or a declared integration owner;
- sitemap/discovery eligibility → the same resolved indexability state;
- breadcrumbs → shared breadcrumb authority.

No preset may print a second canonical, robots policy, hreflang set, Open Graph stack or JSON-LD graph.

## GEO/discovery authority

GEO builds on ordinary crawlability and truthful visible content. Every preset must preserve:

- optional `llms.txt` limited to public/indexable policy-selected content;
- optional Markdown alternates that remain non-canonical;
- crawler policy separation for OAI-SearchBot and GPTBot;
- visible-fact gates for Schema/entity data;
- provenance/author/date/source data when genuinely applicable;
- no fabricated entities, reviews, ratings, customers, credentials, metrics, sources or locations;
- no claim that crawler access, `llms.txt`, Schema or a preset guarantees ranking or AI citation.

## Content semantics

Preset patterns should make entities and answers easy to understand for people, search engines and retrieval systems without creating templated spam.

Use when relevant:

- descriptive headings;
- concise summaries/answers;
- clear service/product/topic definitions;
- steps and processes;
- key facts and evidence;
- comparison tables where editorially justified;
- real sources/references;
- author/provenance information;
- genuinely useful FAQs;
- contextual internal links.

Do not force every page into the same formula. Content quality and intent remain primary.

## Performance contract

SEO/GEO output must stay fast. The shared baseline remains:

- Lighthouse performance score >= 95;
- LCP <= 1200 ms;
- CLS <= 0.05;
- TBT <= 100 ms;
- zero third-party requests by default;
- zero project JavaScript bytes by default.

Preset visual systems must remain CSS-first. JavaScript is an exception requiring a separately accepted functional need and budget update.

Media policy:

- use WordPress responsive images;
- preserve intrinsic dimensions where WordPress can provide them;
- lazy-load below-the-fold media;
- do not lazy-load the LCP image;
- avoid decorative media that materially harms LCP/CLS;
- prefer local/project-owned assets over remote dependencies.

## Preset acceptance rule

A preset is not accepted because it looks correct.

Before acceptance it must prove:

1. preset-specific declarative/content contract;
2. semantic/crawlable HTML;
3. single SEO/Schema authority;
4. canonical/indexability/hreflang correctness;
5. GEO privacy and visible-fact constraints;
6. multilingual correctness where enabled;
7. accessibility/responsive behavior;
8. strict performance baseline;
9. self-contained zero-plugin baseline;
10. cross-preset isolation.

This contract is global. Real-site fixtures such as Emmake may reveal gaps, but fixes must remain reusable at preset/Core level rather than client-specific patches.
