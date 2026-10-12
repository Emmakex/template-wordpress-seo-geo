# Product Vision

Status: **authoritative product vision**

Canonical operating boundary: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Purpose

Create a reusable WordPress SEO/GEO portfolio that lets us migrate, build and operate high-quality client websites without trading away performance, technical SEO, multilingual correctness, accessibility or future compatibility with AI-assisted discovery.

The commercial portfolio has **three independently sellable products**:

1. **SEO/GEO Migration Bridge** — the transition product that scans, clones/transports, rescues valuable digital assets, resets legacy runtime debt and hands a clean rebuild target to the destination stack.
2. **SEO/GEO Theme** — the self-contained presentation/runtime product that builds and renders modern WordPress sites through reusable presets, semantic server rendering and native SEO/GEO foundations.
3. **SEO/GEO Manager** — the permanent local WordPress bridge/control agent that gives an authorized external operator safe eyes and hands inside WordPress: inspect, preview, execute, publish, verify, record and roll back bounded operations.

The **strategic brain lives outside WordPress**. Our human + ChatGPT-assisted + future automation workflows perform strategy, research, content creation, optimization, prioritization and growth decisions. Manager executes those decisions safely against the client's real WordPress state.

North-star sentence:

> **Migration Bridge brings the asset. Theme builds the experience. Manager gives us safe control. We provide the intelligence.**

## Product principles

1. **Three products, three clear jobs.** Migration belongs to Bridge, rendering belongs to Theme, local WordPress execution belongs to Manager. Strategic reasoning belongs to external orchestration.
2. **Performance is architecture, not cleanup.** Avoid unnecessary runtime dependencies, third-party assets and global CSS/JS.
3. **SEO fundamentals come before GEO extras.** Crawlability, indexability, canonicalization, internal linking, meaningful content and structured data must be correct first.
4. **GEO means understandable and retrievable content, not gaming AI systems.** We optimize semantic structure, provenance, entities and machine-friendly access without unsupported ranking claims.
5. **Theme independence is mandatory.** A clean WordPress installation plus the built Theme must provide the documented baseline SEO/GEO experience with zero required SEO/GEO plugins.
6. **Manager independence is mandatory.** Manager must inspect and operate supported WordPress sites without requiring our Theme or Migration Bridge.
7. **Migration Bridge independence is mandatory.** A client may buy/use Bridge for a one-time migration even when the destination is not our Theme.
8. **External orchestration is the brain.** Keyword research, competitive reasoning, editorial planning, landing strategy, article creation and growth prioritization must not be hardcoded into the WordPress plugin.
9. **WordPress-native first.** Use WordPress core APIs for identity, permissions, revisions, posts, media, taxonomies, sitemaps and other solved problems before adding custom infrastructure.
10. **Content is not layout.** Theme-owned strategic pages use versioned semantic models; Gutenberg does not govern their master composition.
11. **Gutenberg preserves editorial autonomy.** Normal posts, legal pages and explicitly editorial/simple content remain editable through standard WordPress workflows.
12. **One output authority per signal.** Theme, Manager and external SEO providers must never emit competing canonical, robots, hreflang, Schema, sitemap or social metadata.
13. **Every remote mutation is controlled.** Preview, permissions, fingerprints/revisions, idempotency, verification, audit and rollback where supported are product requirements.
14. **Public rendering stays local.** A public page must not depend on a live remote orchestration request to render.
15. **Replatform, do not visually clone.** Existing sites contribute valuable content, URLs, SEO signals, links, media, verified facts and required business behavior; legacy visual/runtime debt is discarded.
16. **Client-agnostic by construction.** No product may contain hardcoded EMMAKE/client domain rules as product behavior.
17. **Strategy must evolve without plugin releases.** Improving research, copywriting, opportunity scoring or editorial reasoning should primarily improve the external operating workflow, not require shipping a new Manager version.

## Product 1 — SEO/GEO Migration Bridge

### Primary job

Safely move/rescue an existing WordPress site's valuable digital asset into a clean rebuild or destination environment.

### Canonical flow

```text
Scan
  -> Clone / Export / Import
  -> Rescue Manifest
  -> Reset legacy runtime
  -> Destination bootstrap
  -> Rebuild handoff
  -> Migration/cutover verification
```

### Preserve

- useful content and factual copy;
- valuable public URLs/slugs and redirect intent;
- relevant SEO/indexability/canonical intent;
- useful internal/external links;
- selected media;
- verified business/entity/contact facts;
- legal content;
- multilingual relationships where relevant;
- required business flows/integrations.

### Discard by default

- obsolete themes/child themes;
- Elementor/Divi/builder visual trees when rebuilding;
- presentation-only shortcodes;
- legacy CSS/JS and generated assets;
- obsolete widget/Customizer state;
- redundant rendering/SEO plugins after ownership transfer;
- dependencies whose only purpose is reproducing the old design.

### Non-goal

Bridge is not the permanent SEO/content/growth operating layer.

## Product 2 — SEO/GEO Theme

### Primary job

Render a modern, semantic, high-performance frontend from WordPress resources and versioned content models.

### Owns

- frontend rendering;
- strategic layout/composition;
- design system;
- reusable preset families;
- responsive behavior;
- accessibility;
- semantic HTML;
- native baseline SEO/GEO runtime;
- Schema/discovery output under authority rules;
- article/editorial shell;
- performance budgets.

### Preset families

- Corporate;
- Local Business / Local Pro;
- SaaS / Digital Product;
- Publisher / Editorial;
- Ecommerce.

### Strategic-page rule

Home, service, solution, location, campaign, commercial landing/hub and other preset-owned strategic surfaces are rendered from semantic models such as:

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

The model expresses content/meaning, not CSS/grid/block implementation.

## Product 3 — SEO/GEO Manager

### Primary job

Give an authorized external operator reliable **eyes and hands inside WordPress**.

Manager is a bridge/control agent, not the strategic intelligence layer.

### Core capability contract

```text
Inspect
  -> Preview
  -> Execute
  -> Publish/Schedule
  -> Verify
  -> History/Rollback
```

Manager may provide deterministic local diagnostics needed to execute safely, but it does not independently decide business strategy, keyword priorities, article topics, commercial positioning or growth plans.

### Manager must support

- Site Intelligence/capability discovery;
- WordPress resource reads;
- Theme semantic-model reads/writes;
- standard page/post create/update operations;
- draft/schedule/publish/update operations;
- internal-link/navigation/media/taxonomy operations where authorized;
- output-authority/provider adapters;
- exact previews/diffs;
- revision/fingerprint protection;
- idempotency;
- production-environment safety;
- stored and rendered verification;
- operation evidence/history;
- stale-safe rollback where supported.

### Manager must not become

- a giant autonomous SEO strategist inside WordPress;
- the public frontend renderer;
- a replacement for Migration Bridge;
- a client-specific rules engine;
- a remote shell/file-system backdoor.

## External orchestration — our operating layer

The external orchestration layer is where the fast product-style work happens.

It owns:

- SEO/GEO/business strategy;
- market/keyword/intent research;
- competitor analysis;
- content planning;
- landing briefs;
- full article creation;
- copy optimization;
- source/fact/entity planning;
- internal-link and cluster strategy;
- Search Console/Bing/analytics interpretation;
- opportunity prioritization;
- image/media planning;
- deciding the next bounded Manager operation;
- evaluating verification and iterating.

This is how we operate WordPress in the same inspect/change/verify style used on other software projects while preserving normal WordPress ownership and local rendering.

## Primary users

### Migration Bridge

- agencies migrating client WordPress sites;
- developers replatforming legacy sites;
- businesses moving away from heavy/obsolete stacks.

### Theme

- agencies that need a reusable modern frontend base;
- developers who want a clean starting point rather than a heavy multipurpose theme;
- businesses launching/rebuilding SEO/GEO-first sites.

### Manager

- agencies operating many client WordPress sites remotely;
- managed SEO/content teams;
- developers/automation teams needing a controlled WordPress operations API;
- businesses retaining an ongoing external optimization/content service.

## Commercial combinations

### New site

`Theme` or `Theme + Manager`.

### Existing site, full rebuild

`Migration Bridge -> Theme -> Manager`.

### Existing site, keep design

`Manager` with supported current-theme/provider adapters.

### One-time migration

`Migration Bridge` only.

### Managed ongoing growth

`Manager + external orchestration`, with our Theme optional.

## Non-goals

- Replacing WordPress core features that already solve the problem well.
- Shipping a mandatory visual page builder.
- Requiring an SEO/Schema/caching/multilingual plugin for the Theme baseline.
- Requiring Theme for every Manager-supported WordPress site.
- Requiring Manager for every Theme installation.
- Retiring Migration Bridge merely to reduce the number of products.
- Embedding all research/generation/strategy intelligence inside Manager.
- Claiming that `llms.txt`, special AI markup or proprietary files guarantee AI citations/rankings.
- Adding tracking/fonts/analytics/third-party scripts by default.
- Fabricating authors, sources, reviews, business facts, local presence or performance claims.

## Portfolio success criteria

The project succeeds when:

- **three independent installable products** can be sold, versioned and supported separately;
- Theme works with zero required SEO/GEO plugins for its baseline;
- Manager works on supported WordPress without Theme;
- Migration Bridge can complete one-time migration/replatform work without becoming a permanent dependency;
- Manager gives external orchestration enough safe primitives to create, optimize, publish and verify client content without manual wp-admin repetition;
- strategic Theme pages render without Gutenberg controlling master layout;
- Manager-created articles remain normal client-editable WordPress posts;
- Theme + Manager never duplicate SEO/GEO output authority;
- remote changes are bounded, auditable and reversible where supported;
- the same products work across clients without client-specific code;
- strategy/research/creation can evolve externally without destabilizing WordPress runtime;
- public WordPress pages continue rendering locally when external orchestration is unavailable.
