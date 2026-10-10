# Template WordPress SEO + GEO

This repository defines a **three-product WordPress SEO/GEO portfolio** designed to migrate, build and operate client sites with clean technical SEO, GEO-aware semantic structure, strong performance, accessibility and reusable automation contracts.

Canonical operating model: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## North-star architecture

> **SEO/GEO Migration Bridge = brings/rescues the website.**
>
> **SEO/GEO Theme = builds and renders the website.**
>
> **SEO/GEO Manager = gives us eyes, hands and safe control inside WordPress.**
>
> **External orchestration = the brain: strategy, research, creation, optimization and growth.**

The three WordPress products are independently installable, versioned and commercially sellable. The strategic intelligence layer lives outside WordPress so research/content/optimization workflows can evolve without turning the client plugin into a giant autonomous SEO application.

## Product 1 — SEO/GEO Migration Bridge

Transition/replatform product for existing WordPress sites.

Canonical flow:

```text
Scan -> Clone/Export/Import -> Rescue Manifest -> Reset -> Destination bootstrap -> Rebuild handoff -> Cutover verification
```

Bridge preserves valuable content, URLs, SEO signals, links, selected media, verified business facts and required workflows while discarding legacy presentation/runtime debt when a rebuild is requested.

It is a first-class sellable product and does not become the permanent content/growth operating layer.

## Product 2 — SEO/GEO Theme

Self-contained presentation/runtime product.

The Theme owns:

- frontend rendering;
- reusable premium preset families;
- semantic HTML;
- strategic layout/composition;
- responsive behavior;
- accessibility;
- performance budgets;
- baseline native SEO/GEO runtime;
- Theme-owned Schema/discovery output;
- article/editorial shell.

A clean WordPress installation plus the built Theme provides the documented baseline with **zero required SEO/GEO plugins**.

Strategic pages use versioned semantic models rather than arbitrary Gutenberg layout trees.

Preset families:

- Corporate;
- Local Business / Local Pro;
- SaaS / Digital Product;
- Publisher / Editorial;
- Ecommerce.

## Product 3 — SEO/GEO Manager

Permanent local WordPress **bridge/control agent**.

Manager is not the strategic brain and not the public renderer. It exposes safe WordPress capabilities to an authorized external operator:

```text
Inspect -> Preview -> Execute -> Publish/Schedule -> Verify -> History/Rollback
```

Manager provides Site Intelligence, resource/model operations, provider/output-authority adapters, publication controls, verification, operation evidence, idempotency, revision/fingerprint protection and rollback where supported.

Manager must work on supported WordPress sites without requiring SEO/GEO Theme or Migration Bridge.

## External orchestration

The external operating layer is where we work like we do on modern software projects:

- inspect the real client site through Manager;
- research and reason externally;
- create/optimize content;
- prepare a bounded operation;
- preview through Manager;
- apply/publish;
- verify;
- measure and iterate.

It owns:

- business/SEO/GEO strategy;
- keyword/intent/competitor research;
- landing/article creation;
- content optimization;
- internal-link/cluster strategy;
- Search Console/Bing/analytics interpretation;
- opportunity prioritization;
- growth decisions.

Improving these workflows should not normally require a WordPress plugin release.

## Product direction: replatform, do not visually clone

For redesign/migration projects, the old WordPress site is treated as a **source of digital assets**, not as the visual destination.

Preserve:

- valuable content;
- URLs/search equity;
- useful SEO/GEO signals;
- links;
- selected media;
- verified facts;
- required business workflows.

Replace/discard by default when rebuilding:

- legacy theme/builder layout;
- page-builder CSS/JS;
- obsolete columns/rows/spacers/widgets;
- visual composition debt;
- dependencies whose only purpose is reproducing the old site.

North-star migration sentence:

> **Rescue the asset, reset the clone, rebuild the product.**

## Core foundation pillars

1. **Performance first** — Core Web Vitals, minimal CSS/JS, optimized images and lean HTML.
2. **Native technical SEO** — indexability, canonical, robots, sitemaps, metadata, Open Graph, breadcrumbs, internal linking and coherent Schema without a required external SEO plugin for the Theme baseline.
3. **GEO** — semantic structure, entities, authors, sources, crawler access and optional machine-friendly formats without unsupported ranking claims.
4. **Multilingual by design** — ES/EN as first-class language capability with optional accepted integrations.
5. **Accessibility** — semantic HTML, keyboard support, focus, contrast, reduced motion and WCAG-aware patterns.

## WordPress / Gutenberg contract

WordPress remains the authority for resource identity, slugs, statuses, authors, revisions, permissions, media, taxonomies and local content state.

Gutenberg remains appropriate for normal/editorial content such as blog posts, legal pages and simple informational pages.

Gutenberg is **not** the master layout authority for Theme-owned strategic pages.

The canonical split is:

```text
External orchestration -> decides/creates what should change
SEO/GEO Manager       -> safely reads/writes/verifies WordPress state
SEO/GEO Theme         -> renders strategic frontend
WordPress/Gutenberg   -> retains CMS/editorial ownership
```

## Client scenarios

### New site

`SEO/GEO Theme` with optional/recommended `SEO/GEO Manager` for managed ongoing operation.

### Existing WordPress, full redesign

`Migration Bridge -> Theme -> Manager -> external orchestration`.

### Existing WordPress, keep current design

`Manager -> accepted current-theme/provider adapters -> external orchestration`.

### One-time migration

`Migration Bridge` only is valid.

### Theme-only installation

`Theme` only is valid.

### Managed SEO/content/growth operation

`Manager + external orchestration`; Theme is optional if the current WordPress stack is supported.

## Engineering workflow

After repository bootstrap, implementation follows:

```text
feature branch -> PR -> CI -> merge -> deployment/install verification
```

No phase advances until the changed contract, required gates, blockers and documentation are complete.

Before adding a feature, apply the anti-drift test:

- transport/rescue/reset old site -> **Migration Bridge**;
- public design/rendering/performance -> **Theme**;
- safe external read/write/publish/verify WordPress control -> **Manager**;
- deciding what should be created/optimized and why -> **external orchestration**.

## Current real-site reference

EMMAKE `/nuevaweb/` is the first real reference implementation.

Its intended lifecycle is:

```text
old EMMAKE
 -> Migration Bridge
 -> clean /nuevaweb/ rebuild workspace
 -> Corporate Theme
 -> Manager control bridge
 -> external Build/Finish
 -> launch
 -> external Optimize/Grow workflows through Manager
```

EMMAKE-specific migration/permalink edge cases are field evidence, not the permanent center of the Manager product.

## Documentation

Start here:

- `docs/THREE_PRODUCT_OPERATING_MODEL.md` — **canonical product/operating boundary**;
- `docs/PRODUCT_VISION.md`;
- `docs/PRODUCT_PORTFOLIO.md`;
- `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`;
- `docs/SEO_GEO_MANAGER.md`;
- `docs/SEO_GEO_MANAGER_PRODUCT_MODES.md`;
- `docs/RESET_REBUILD_CONTRACT.md`;
- `docs/REPLATFORMING_CONTRACT.md`;
- `docs/MIGRATION_BRIDGE.md`;
- `docs/CONTENT_PUBLISHING.md`;
- `docs/PORTABLE_SANDBOX.md`;
- `docs/ARCHITECTURE.md`;
- `docs/SEO_GEO_SPEC.md`;
- `docs/NATIVE_SEO.md`;
- `docs/NATIVE_SCHEMA.md`;
- `docs/DISCOVERY_METADATA.md`;
- `docs/GEO_CRAWLERS.md`;
- `docs/LLMS_TXT.md`;
- `docs/MARKDOWN_ALTERNATES.md`;
- `docs/CONTENT_PROVENANCE.md`;
- `docs/DISCOVERY_PRIVACY.md`;
- `docs/CACHE_INVALIDATION.md`;
- `docs/MULTILINGUAL.md`;
- `docs/PERFORMANCE.md`;
- `docs/ACCESSIBILITY.md`;
- `docs/DESIGN_SYSTEM.md`;
- `docs/MODERN_PRESET_DESIGN_STRATEGY.md`;
- `docs/PATTERNS.md`;
- `docs/PRESETS.md`;
- `docs/COMPATIBILITY.md`;
- `docs/ONBOARDING.md`;
- `docs/RELEASE_ARTIFACT.md`;
- `docs/RELEASE_VERSIONING.md`;
- `docs/CLIENT_INSTALLATION.md`;
- `docs/CLIENT_CLONING.md`;
- `docs/SANDBOX_TO_PRODUCTION.md`;
- `docs/PRODUCTION_VERIFICATION.md`;
- `docs/ROLLBACK_RECOVERY.md`;
- `docs/STABLE_RELEASE_DECISION.md`;
- `docs/REAL_SITE_PILOT.md`;
- `docs/CI_QUALITY_GATES.md`;
- `docs/ROADMAP.md`;
- `docs/engineering/GLOBAL_ENGINEERING_RULES.md`;
- `docs/engineering/ERRORS_AND_SOLUTIONS.md`.

## Authoritative references

The project tracks primary documentation rather than SEO folklore:

- WordPress Theme Handbook and `theme.json` reference;
- Google Search Central documentation for AI features, localized pages, structured data and Core Web Vitals;
- OpenAI publisher/developer crawler guidance;
- Open Graph protocol;
- `llms.txt` as optional interoperability, never a claimed ranking requirement.
