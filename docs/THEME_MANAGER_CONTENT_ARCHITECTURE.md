# Theme-owned frontend + Manager bridge + external orchestration architecture

Status: **authoritative content/rendering/control architecture**

Canonical portfolio boundary: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Decision

The project adopts a **WordPress-as-CMS, Theme-owned-frontend, Manager-as-bridge, external-orchestration-as-brain** architecture.

WordPress remains the content, identity, revision, permissions, taxonomy and URL platform.

Gutenberg remains available where editorial autonomy is useful.

SEO/GEO Theme owns strategic frontend rendering.

SEO/GEO Manager exposes safe local WordPress control to an authorized external operator.

External orchestration performs strategy, research, creation, optimization and growth reasoning.

The permanent separation is:

> **Gutenberg provides editorial autonomy. Theme provides frontend rendering. Manager provides safe WordPress control. External orchestration provides intelligence.**

This boundary is required so we can operate a client WordPress from outside WordPress in the same inspect/change/verify style used in our software projects, without making public rendering remote-dependent or embedding every strategic workflow inside a plugin.

---

# Product architecture

```text
External orchestration / operator
|
+-- strategy
+-- research
+-- SEO/GEO reasoning
+-- content creation
+-- landing/article briefs
+-- internal-link / cluster strategy
+-- Search Console / Bing / analytics interpretation
+-- optimization and growth decisions
|
|   authenticated structured operations
v
SEO/GEO Manager
|
+-- Site Intelligence / capability discovery
+-- WordPress resource APIs
+-- Theme model APIs
+-- output-authority/provider adapters
+-- preview/diff
+-- apply/execute
+-- draft/schedule/publish/update
+-- stored/rendered verification
+-- operation evidence/history
+-- rollback and stale-state guards
|
v
WordPress
|
+-- content/data layer
|   +-- posts, pages and custom entities
|   +-- slugs/status/authors/dates/revisions
|   +-- users/capabilities
|   +-- taxonomies
|   +-- media
|   +-- local persistent state
|
+-- Gutenberg editorial layer
|   +-- manual blog editing
|   +-- client-authored posts
|   +-- legal/privacy/cookie pages
|   +-- simple informational pages
|   +-- optional editing of Manager-created posts
|
+-- SEO/GEO Theme
    +-- Theme-owned strategic renderers
    +-- five preset families
    +-- article/editorial shell
    +-- semantic HTML
    +-- responsive layout
    +-- accessibility
    +-- baseline SEO/GEO runtime
    +-- Schema/discovery output
    +-- performance budgets
```

Migration Bridge sits before this normal operating loop when a legacy site must be rescued/replatformed:

```text
legacy WordPress
    -> Migration Bridge
    -> clean destination WordPress
    -> Theme + Manager + external orchestration as needed
```

---

# Core rule: content is not layout, and execution is not strategy

Two separations are mandatory.

## Content vs layout

External orchestration and Manager work with **semantic content/models and WordPress resources**.

Theme converts those accepted models/resources into public presentation.

Manager does not need to know CSS grids, `alignwide`, `contentSize`, Gutenberg Group nesting, preset-specific classes or visual breakpoints.

## Strategy vs execution

External orchestration decides **what should change and why**.

Manager decides only **whether/how that requested change can be executed safely in the current WordPress stack**.

For example:

- external orchestration decides that a new Barcelona automation landing is strategically useful;
- external orchestration creates the content, link plan, metadata intent and media brief;
- Manager validates current state, collisions, permissions and output authority;
- Manager previews/persists the accepted model/resource;
- Theme renders the page;
- Manager verifies the result;
- external orchestration evaluates performance and decides the next action.

---

# Strategic-page rendering

When SEO/GEO Theme is active, these surfaces should use Theme-owned rendering by default:

- Home;
- service pages;
- product/solution pages;
- SEO landing pages;
- GEO/location pages;
- campaign pages;
- commercial hubs/clusters;
- conversion-focused contact/demo pages where the preset provides the surface;
- preset-owned archive/hub surfaces where custom presentation is part of the product.

These remain normal WordPress resources with:

- stable ID;
- slug/path;
- status;
- author/owner where relevant;
- created/modified dates;
- revisions/change-set references;
- permissions/capabilities;
- taxonomy relationships;
- REST/admin visibility according to policy.

The presentation contract is separate from arbitrary Gutenberg layout markup.

Conceptual record:

```text
_seo_geo_model = corporate-landing-v1
_seo_geo_renderer = corporate
_seo_geo_content = versioned semantic content
_seo_geo_schema = validated entity/schema intent
_seo_geo_internal_links = validated link plan
_seo_geo_publication = provenance/publication metadata
```

---

# Versioned semantic models

Theme-owned pages use versioned models so external orchestration, Manager and Theme can evolve independently.

Examples:

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

A model defines semantic slots, not layout instructions.

Example:

```text
corporate-home-v1
+-- hero
+-- capabilities
+-- process
+-- insights
+-- final_cta
```

A model may carry:

- headings;
- body copy;
- CTAs;
- references;
- media IDs;
- verified facts;
- entities;
- FAQs;
- internal-link targets;
- SEO/GEO intent fields accepted by the authority contract.

It must not require the external publisher to understand the renderer's CSS implementation.

---

# Gutenberg boundary

Gutenberg remains supported and useful.

## Gutenberg is appropriate for

- client-authored blog posts;
- editorial articles;
- manual edits to Manager-created blog posts;
- legal notice;
- privacy policy;
- cookie policy;
- terms/compliance pages;
- simple informational pages where premium preset composition is not required.

## Gutenberg is not the layout authority for

- preset Home pages;
- externally orchestrated commercial landings rendered by Theme;
- service/location/campaign pages assigned to a Theme renderer;
- other strategic surfaces explicitly owned by Theme.

Theme may still provide `theme.json`, editor tokens and block styles so normal editorial content remains coherent.

---

# Strategic landing workflow

The intelligence/creation step is external.

```text
keyword / market / intent / GEO research
        |
        v
external orchestration
        +-- content brief
        +-- factual/source validation
        +-- duplicate-intent check
        +-- internal-link plan
        +-- SEO metadata intent
        +-- Schema/entity intent
        +-- canonical/indexability intent
        +-- media plan
        |
        v
semantic landing model
        |
        v
SEO/GEO Manager
        +-- inspect current target/state
        +-- validate capabilities/collisions
        +-- preview exact change
        +-- persist approved model/resource
        +-- record operation
        |
        v
SEO/GEO Theme renderer
        |
        v
semantic server-rendered frontend
        |
        v
SEO/GEO Manager verification
        |
        v
external orchestration measurement/iteration
```

Manager does not invent the market strategy or visual Gutenberg layout tree.

---

# Blog/editorial workflow

Automated blog creation remains a first-class capability of the **overall operating system**, but the research/generation intelligence is external to Manager.

```text
topic / keyword / cluster / search signals
        |
        v
external orchestration
        +-- research
        +-- title/excerpt
        +-- outline/headings/body
        +-- sources/references
        +-- author binding
        +-- media plan
        +-- categories/tags under policy
        +-- internal links
        +-- related landing/service links
        +-- SEO metadata intent
        +-- Article/BlogPosting intent
        |
        v
SEO/GEO Manager
        +-- validate current WordPress state
        +-- preview
        +-- create normal WordPress draft
        +-- schedule/publish/update
        +-- verify
        +-- record/rollback reference
        |
        +--> optional human/client edit in Gutenberg
        |
        v
SEO/GEO Theme article shell when active
```

Manager-created articles remain normal WordPress posts.

Gutenberg may own article-body editing.

Theme owns the public article shell, including:

- reading width;
- header/hero treatment;
- typography;
- table-of-contents presentation where enabled;
- author/date/provenance presentation;
- related content;
- CTAs;
- Schema/public metadata integration;
- responsive behavior;
- accessibility;
- performance.

---

# Optimization workflow

Optimization is external reasoning plus Manager execution.

```text
Manager inspection + Search Console/Bing/analytics signals
        |
        v
external orchestration
        +-- intent/entity/content analysis
        +-- cannibalization/thin/stale analysis
        +-- internal-link strategy
        +-- metadata/content/Schema recommendations
        +-- priority decision
        |
        v
bounded change set
        |
        v
Manager preview -> apply -> verify -> record
```

The plugin may calculate deterministic diagnostics such as broken targets, missing values, conflicts or capability state, but it does not become the full strategic optimizer.

---

# Growth loop

The intended long-term loop is:

```text
Search Console / Bing / analytics / market signals
        |
        v
external opportunity detection and prioritization
        |
        +-- new landing
        +-- new article
        +-- content refresh
        +-- internal-link improvement
        +-- cluster expansion
        +-- GEO/local opportunity
        |
        v
content/change intent
        |
        v
Manager preview/apply/publish/verify
        |
        v
Theme/local WordPress rendering
        |
        v
measurement
        |
        +----> next external iteration
```

This keeps strategic intelligence easy to improve without releasing a new WordPress plugin every time our research/creation process improves.

---

# Client autonomy model

The architecture must not lock normal WordPress users out.

A client can continue to:

- write posts manually in Gutenberg;
- edit Manager-created blog posts subject to revision/fingerprint rules;
- create simple ordinary pages when no strategic renderer is required;
- maintain legal/compliance pages;
- upload media;
- manage approved taxonomies and metadata according to role/capability.

External orchestration must always read current revision/fingerprint before updating content that a human may have edited.

---

# Five-preset contract

Theme-owned strategic rendering applies across:

- Corporate;
- SaaS / Digital Product;
- Local Pro;
- Publisher / Editorial;
- Ecommerce.

The amount of Gutenberg-authored content varies by preset, but master strategic rendering remains Theme-owned unless an explicit adapter contract says otherwise.

---

# SEO/GEO ownership

The architecture must never create duplicate public-output authorities.

When Theme + Manager are active:

- external orchestration provides content/SEO/GEO intent;
- Manager resolves whether/how the intent may be persisted;
- Theme emits Theme-owned baseline output;
- supported external providers receive writes only through accepted adapters;
- duplicate canonical, robots, hreflang, Schema, Open Graph or sitemap output is forbidden.

Visible content and structured output must remain consistent.

---

# Performance/runtime rule

Normal frontend rendering must be local to WordPress.

Public HTML must not require a live request to ChatGPT, a remote Manager controller or another orchestration service.

External orchestration creates a bounded operation that is persisted locally before public rendering.

Theme-owned renderers should minimize runtime complexity:

- no page-builder JavaScript dependency for strategic pages;
- deterministic component CSS;
- explicit Core Web Vitals budgets;
- bounded DOM;
- no remote visual dependency required to render the page.

---

# Migration/rebuild interaction

SEO/GEO Migration Bridge remains the separate migration product.

During redesign:

1. Bridge rescues content, URLs, SEO signals, useful media, verified facts and required behavior;
2. Bridge/reset workflow discards legacy presentation/runtime debt;
3. rescued content is mapped into versioned semantic models/resources;
4. Theme renders the rebuilt site;
5. Manager becomes the ongoing safe control bridge;
6. external orchestration finishes, optimizes and grows the site.

A legacy Elementor/Divi/Gutenberg layout tree must not become the canonical strategic model of the rebuilt site.

---

# EMMAKE reference implementation

EMMAKE `/nuevaweb/` is the first real field implementation of this architecture.

Correct interpretation:

```text
old EMMAKE
 -> Migration Bridge
 -> clean /nuevaweb/ rebuild workspace
 -> Corporate Theme-owned frontend
 -> Manager installed as control bridge
 -> external orchestration from here
 -> finish
 -> optimize
 -> grow
```

EMMAKE-specific permalink/slug edge cases are valuable field evidence but must not redefine Manager as a migration/permalink product.

---

# Roadmap consequence

Development order from this architecture is:

1. freeze this four-responsibility boundary;
2. close Corporate Theme field acceptance and generalize semantic renderer contracts;
3. complete the three-product packaging/release boundaries;
4. finish Manager Site Intelligence and authenticated remote-control primitives;
5. finish generic Preview -> Apply -> Verify -> Rollback operations;
6. finish Theme semantic-model and normal WordPress post/page publication primitives;
7. prove remote Build/Finish workflow from external orchestration;
8. prove remote optimization workflow;
9. prove remote landing/blog publication workflows;
10. connect Search Console/Bing/analytics into external orchestration and prove the growth loop;
11. complete remaining Theme preset renderers and product acceptance without coupling them to Manager intelligence.

Do not expand Manager into a large autonomous strategy engine merely because a workflow is called "Optimizer", "Landing", "Blog" or "Growth".

Those names describe orchestration use cases; Manager supplies the safe local capabilities.

---

# Non-goals

This architecture does not mean:

- removing Gutenberg;
- preventing client editorial work;
- replacing WordPress with an external CMS;
- requiring Manager to render the frontend;
- requiring Theme for every Manager-supported site;
- requiring Migration Bridge for every client;
- moving all content/research intelligence into WordPress;
- exposing unrestricted server control through Manager;
- generating unreviewed doorway pages;
- fabricating business facts, authors, reviews, sources or local claims.

It defines a reusable boundary where migration, premium rendering, safe WordPress control and external intelligence can evolve independently.
