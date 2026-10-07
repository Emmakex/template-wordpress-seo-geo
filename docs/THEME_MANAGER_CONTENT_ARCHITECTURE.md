# Theme-owned frontend + SEO/GEO Manager content architecture

## Decision

The project adopts a **WordPress-as-CMS, Theme-owned-frontend** architecture.

WordPress remains the content, identity, revision, permissions, taxonomy and URL platform. Gutenberg remains available where editorial autonomy is useful. However, Gutenberg is **not** the layout authority for strategic SEO/GEO surfaces such as the Home, commercial landing pages, service pages, location pages, campaign pages and other preset-owned master pages.

The permanent separation of responsibilities is:

> **Gutenberg provides editorial autonomy. SEO/GEO Manager provides automation and growth. SEO/GEO Theme provides frontend rendering, design, semantic HTML and performance.**

This decision follows the real EMMAKE Corporate field pilot. Corporate v4.x proved that a premium preset can still be visually distorted when Theme composition depends on Gutenberg constrained-layout rules such as `contentSize`, `wideSize`, `is-layout-constrained`, block alignment and generated block CSS. The reusable product must not require an accumulating set of CSS exceptions to override editor layout decisions.

## Product architecture

```text
WordPress
|
+-- WordPress content/data layer
|   +-- posts, pages and custom content entities
|   +-- slugs, status, author, dates and revisions
|   +-- users, capabilities and permissions
|   +-- categories, tags and approved taxonomies
|   +-- media library
|   +-- REST/admin APIs
|
+-- Gutenberg editorial layer
|   +-- manual blog editing
|   +-- client-authored posts
|   +-- legal/privacy/cookie pages
|   +-- simple informational pages
|   +-- optional manual editing of Manager-created blog posts
|
+-- SEO/GEO Manager
|   +-- Site Intelligence
|   +-- Output Authority Resolver
|   +-- Landing Engine
|   +-- Blog Engine
|   +-- internal-link / cluster planning
|   +-- SEO/GEO metadata orchestration
|   +-- draft / preview / approval / schedule / publish
|   +-- revisions, idempotency and rollback
|   +-- optional migration module
|
+-- SEO/GEO Theme
    +-- Theme-owned page renderers
    +-- five preset renderers
    +-- article/editorial shell
    +-- semantic HTML
    +-- responsive layout
    +-- accessibility
    +-- baseline SEO/GEO runtime
    +-- Schema/discovery output
    +-- performance budgets
```

## Core rule: content is not layout

Content and presentation are separate contracts.

SEO/GEO Manager creates or modifies **content models and WordPress resources**. It does not need to understand CSS grids, `alignwide`, `contentSize`, Gutenberg Group nesting, preset-specific class names or visual breakpoints.

SEO/GEO Theme converts accepted content models into the public frontend.

For strategic pages the pipeline is:

```text
brief / research / existing content
        |
        v
SEO/GEO Manager structured model
        |
        v
WordPress resource + versioned SEO/GEO data
        |
        v
preset renderer owned by SEO/GEO Theme
        |
        v
semantic server-rendered HTML + controlled CSS
```

The renderer, not Gutenberg, owns:

- master width;
- grids and section composition;
- typography scale and reading measure;
- spacing rhythm;
- responsive breakpoints;
- component hierarchy;
- decorative presentation;
- interactive states;
- page-shell accessibility behavior;
- premium/WOW design consistency.

## Strategic-page rendering

The following surfaces should use Theme-owned rendering by default when the SEO/GEO Theme is active:

- Home;
- service pages;
- product/solution pages;
- SEO landing pages;
- GEO/location pages;
- campaign pages;
- commercial hub/cluster pages;
- conversion-focused contact/demo pages where the preset provides the surface;
- preset-owned archive/hub surfaces where a custom presentation is part of the product.

These resources remain normal WordPress entities. The architecture does **not** create a disconnected external CMS.

A strategic resource should retain normal WordPress identity such as:

- stable post/page/CPT ID;
- slug/path;
- status;
- author/owner where relevant;
- created/modified dates;
- revisions or Manager change-set references;
- permissions/capabilities;
- taxonomy relationships where applicable;
- REST/admin visibility according to policy.

The presentation contract is stored separately from arbitrary Gutenberg layout markup.

A conceptual strategic-page record may include:

```text
_seo_geo_model = corporate-landing-v1
_seo_geo_renderer = corporate
_seo_geo_content = versioned structured content
_seo_geo_schema = validated entity/schema intent
_seo_geo_internal_links = validated link plan
_seo_geo_publication = provenance/publication metadata
```

Exact persistence details are implementation work; this document defines the ownership boundary, not the final storage schema.

## Versioned content models

Theme-owned pages use versioned models so Manager and Theme can evolve independently without silently changing old content.

Initial model families may include:

```text
corporate-home-v1
corporate-landing-v1
service-page-v1
location-page-v1
campaign-page-v1
saas-home-v1
local-home-v1
publisher-home-v1
ecommerce-home-v1
```

A model defines semantic slots rather than layout instructions. Example:

```text
corporate-home-v1
+-- hero
+-- capabilities
+-- process
+-- insights
+-- final_cta
```

The model may carry headings, body copy, CTAs, references, media IDs, verified facts, entities, FAQs and link targets. It must not require the publisher to know CSS or block-layout implementation details.

## Gutenberg boundary

Gutenberg remains a supported and useful editor, but its authority is bounded.

### Gutenberg is appropriate for

- client-authored blog posts;
- editorial articles;
- manual edits to Manager-created blog entries;
- legal notice;
- privacy policy;
- cookie policy;
- terms and similar compliance pages;
- simple informational pages where premium preset composition is not required.

### Gutenberg is not the layout authority for

- preset Home pages;
- Manager-generated commercial landings;
- service/location/campaign pages managed by the structured renderer;
- other strategic surfaces explicitly assigned to a Theme renderer.

The Theme may still provide `theme.json`, editor tokens and block styles so Gutenberg content looks coherent in both the editor and public frontend. `theme.json` remains useful for colors, typography tokens, spacing tokens and ordinary editorial blocks; its generic constrained-layout settings must not override Theme-owned strategic renderers.

## SEO/GEO Manager: automated landing creation

Landing Engine creates and maintains strategic pages through structured models rather than generated Gutenberg layout trees.

Typical automated flow:

```text
keyword / intent / market / GEO research
        |
        v
content brief
        |
        v
structured landing model
        |
        +-- facts/evidence validation
        +-- duplicate-intent check
        +-- internal-link plan
        +-- SEO metadata intent
        +-- Schema/entity intent
        +-- canonical/indexability policy
        |
        v
WordPress draft
        |
        v
SEO/GEO Theme preview renderer
        |
        v
approval / schedule / publish
        |
        v
public verification + rollback reference
```

Manager does not generate visual Gutenberg layout markup for Theme-owned landings. The same content model may render differently under Corporate, SaaS, Local Pro, Publisher or Ecommerce when the model/preset contract permits it.

## SEO/GEO Manager: automated blog creation

Automated blog creation is a **first-class Manager capability**, not a future incidental extension.

Blog Engine must be able to research/receive a brief, create a complete article draft, optimize it, schedule/publish it and later refresh it while preserving normal WordPress editorial ownership.

The preferred model is:

```text
topic / keyword / cluster / source brief
        |
        v
structured article model
        +-- title
        +-- excerpt
        +-- outline
        +-- headings
        +-- article body
        +-- sources/references
        +-- author binding
        +-- media references
        +-- categories/tags under policy
        +-- internal links
        +-- related landing/service links
        +-- SEO metadata intent
        +-- Article/BlogPosting entity intent
        |
        v
SEO/GEO Manager validation
        |
        v
normal WordPress post draft
        |
        v
optional human/client edit in Gutenberg
        |
        v
approval / schedule / publish
        |
        v
public verification + future refresh cycle
```

### Blog interoperability rule

Manager-created articles must remain normal WordPress posts and remain editable by authorized users in Gutenberg.

Manager may internally use a structured article model for generation, validation and future refresh. When materializing the editable article body, it should use a **minimal stable editorial representation** compatible with WordPress/Gutenberg rather than preset-specific layout blocks.

Gutenberg may own the article **body editing experience**, but the SEO/GEO Theme owns the public article shell:

- maximum reading width;
- header/hero treatment;
- article typography;
- table-of-contents presentation where enabled;
- author/date/provenance presentation;
- related-content surfaces;
- CTA surfaces;
- Schema/public metadata integration;
- responsive behavior;
- accessibility;
- performance.

This preserves client autonomy without making automated publishing dependent on Gutenberg as a page builder.

## Automated blog lifecycle

Blog Engine must support more than one-off post creation.

Required lifecycle capabilities include:

- topic/keyword/intent assignment;
- content-cluster relationship;
- source/reference provenance;
- author binding;
- draft-first generation;
- preview;
- explicit or policy-authorized scheduling/publication;
- category/tag policy to prevent taxonomy sprawl;
- internal-link insertion based on real targets;
- related landing/service link opportunities;
- featured-media references;
- multilingual sibling relationships when explicitly created;
- update/refresh operations with expected-revision protection;
- stale-content detection inputs when analytics/search data is connected;
- change-set and rollback;
- verification after publication.

Direct autonomous publication may be offered as a privileged policy mode later, but the baseline remains auditable and reversible. Draft-first must remain available at all times.

## Manager growth loop

The intended long-term loop is:

```text
Search Console / Bing / analytics / site signals
        |
        v
opportunity detection
        |
        +-- new landing opportunity
        +-- new blog topic
        +-- content refresh
        +-- internal-link improvement
        +-- GEO/local opportunity
        |
        v
SEO/GEO Manager
        |
        v
validated content/change set
        |
        v
Theme renderer or editorial-post materialization
        |
        v
preview / approval / publish
        |
        v
public verification
        |
        v
measurement -> next iteration
```

The Manager is therefore the ongoing growth/control plane, not merely a publishing form.

## Client autonomy model

The product must not lock normal WordPress users out of their site.

A client can continue to:

- write a new post manually in Gutenberg;
- edit a Manager-created blog post in Gutenberg subject to revision/fingerprint rules;
- create a simple ordinary page when no strategic renderer is required;
- maintain legal/compliance pages;
- upload media;
- manage approved taxonomies and editorial metadata according to role/capability.

For a strategic new landing, service page, location page or campaign, the preferred product path is SEO/GEO Manager because that path provides model validation, SEO/GEO authority resolution, internal linking, rollback and Theme-owned presentation.

## Five-preset contract

The Theme-owned rendering rule applies consistently across the five product presets:

- Corporate;
- SaaS / Digital Product;
- Local Pro;
- Publisher / Editorial;
- Ecommerce.

The amount of Gutenberg-authored content may vary by preset. Publisher naturally gives Gutenberg a larger role in article bodies, while Ecommerce may delegate product/checkout business data to WooCommerce or another accepted commerce authority. These integrations do not transfer master frontend ownership away from the Theme unless an explicit adapter contract says so.

## SEO/GEO ownership

The architectural split does not create a second SEO authority.

When Theme + Manager are active:

- Manager creates/updates content and SEO/GEO intent;
- Output Authority Resolver determines the accepted owner for each public signal;
- Theme emits Theme-owned baseline output;
- supported external providers receive writes only through accepted adapters;
- duplicate canonical, robots, hreflang, Schema, Open Graph or sitemap output is forbidden.

Visible content and structured data must remain consistent.

## Performance and runtime rule

Normal frontend rendering must be local to WordPress and must not require a live remote Manager/controller request.

Strategic pages should render server-side from local accepted models. Manager automation is a control-plane operation. Remote generation/research services, when used, produce a bounded change set that is persisted locally before public rendering.

Theme-owned renderers should reduce rather than increase runtime complexity:

- no page-builder JavaScript dependency;
- no requirement for Elementor/Divi/Gutenberg layout CSS on strategic pages beyond unavoidable WordPress baseline assets;
- deterministic component CSS;
- explicit Core Web Vitals budgets;
- bounded DOM;
- no remote visual dependency required to render the page.

## Migration/Rebuild interaction

Migration Bridge remains the accepted migration/reset implementation until those capabilities move into Manager.

During a redesign:

1. rescue content, URLs, SEO signals, useful media, verified facts and required behavior;
2. discard legacy presentation/runtime debt;
3. map rescued content into versioned SEO/GEO content models;
4. let the Theme render the new site from those models;
5. use Manager after launch for ongoing landing/blog creation, optimization and refresh.

A legacy builder tree must not become the canonical content model of the new site.

## Corporate v5 architectural checkpoint

Corporate v4.x is retained as valuable real-site evidence: it established the premium/WOW art direction, cache-safe asset versioning and the failure mode caused by allowing Gutenberg constrained-layout behavior into master composition.

The next Corporate architecture is **Corporate v5 — Theme-owned frontend**.

Before additional pixel-level Corporate work or rollout to the other four presets, v5 should prove:

- the Home can render from `corporate-home-v1` without Gutenberg being its layout authority;
- no `wp-block-post-content`/`is-layout-constrained`/`contentSize` dependency controls master composition;
- the existing hydrated content can be reused without re-running migration/reset merely to change renderer architecture;
- semantic headings, links and SEO/GEO output remain correct;
- the renderer is server-side and accessible;
- the premium/WOW design is stable at `1440 / 1024 / 768 / 390`;
- performance stays inside enforced budgets;
- a future Manager can create a new Corporate landing using the same model/renderer boundary.

## Roadmap consequence

Do not continue visual rollout of SaaS, Local Pro, Publisher or Ecommerce until the Theme-owned rendering architecture is proven with Corporate v5.

Recommended order:

1. freeze/document the Theme/Manager/Gutenberg ownership boundary;
2. implement a model repository/reader for Theme-owned surfaces;
3. implement Corporate v5 server renderer using the existing `corporate-home-v1` semantic content;
4. prove field rendering on `/nuevaweb/` without regeneration/reset;
5. generalize the renderer contract;
6. migrate the remaining four preset master surfaces to the same architecture;
7. implement Manager Landing Engine against the structured model contract;
8. implement Manager Blog Engine with automated draft/schedule/publish plus Gutenberg editorial interoperability;
9. add search/analytics-driven refresh and growth loops;
10. absorb Migration Bridge capabilities into Manager only after the publishing/optimization core is stable.

## Non-goals

This architecture does **not** mean:

- removing Gutenberg from WordPress;
- preventing clients from creating posts/pages;
- replacing WordPress with an external CMS;
- requiring SEO/GEO Manager to run the public frontend;
- requiring the Theme for every Manager-supported WordPress site;
- generating unreviewed doorway pages at scale;
- giving automation permission to fabricate business facts, authors, reviews, sources or local claims.

It defines a clean ownership boundary so automation, editorial autonomy and premium frontend design can coexist without fighting each other.
