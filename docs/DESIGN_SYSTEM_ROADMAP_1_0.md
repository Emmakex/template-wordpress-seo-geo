# Roadmap — Design System SEO/GEO 1.0

## Status

Started after the first real EMMAKE `/nuevaweb/` field pilot proved that the Migration Bridge can rescue useful content and hydrate a clean native Home, while also proving that visual quality must be solved at product/design-system level rather than by patching one customer page.

The current EMMAKE generated Home is therefore a **functional migration baseline**, not the visual target.

## Goal

Ship one lightweight WordPress block Theme that can turn rescued content into a modern, fast, SEO/GEO-ready site through five product-facing presets without reusing the legacy site's presentation layer.

## Phase DS-0 — Decision and baseline

**Status: complete**

- accept legacy site as content/SEO source only;
- retain current EMMAKE pilot as migration baseline;
- define five product-facing presets;
- establish benchmark rule: study repeated successful patterns, do not clone third-party sites;
- define placeholder/evidence separation.

## Phase DS-1 — Shared design-system runtime

**Status: in progress**

Deliverables:

- shared CSS runtime aliases for shell width, reading width, spacing rhythm, radii, surfaces, motion and focus;
- zero required JavaScript;
- no remote font dependency;
- preset styles load after shared primitives;
- reduced-motion contract preserved;
- existing `theme.json` semantic/editor token contract preserved.

Acceptance:

- Design System CI green;
- Self-contained Theme CI green;
- Accessibility & Responsive CI green;
- no visual runtime dependency on removed builders/plugins.

## Phase DS-2 — Corporate Premium reference preset

**Status: in progress**

Home composition:

1. editorial Hero;
2. asymmetric/Bento capabilities;
3. optional proof/value band;
4. method as visual sequence/timeline;
5. optional featured work/case;
6. editorial Insights with featured-first hierarchy;
7. high-contrast final CTA;
8. minimal footer.

Implementation rule:

- keep runtime preset ID `corporate` for compatibility;
- product-facing name becomes **Corporate Premium**;
- use existing semantic content slots so Migration Bridge content mapping remains portable;
- solve layout at component/preset level rather than EMMAKE-specific selectors.

Acceptance:

- works with 1, 2, 3 and more capability candidates without broken hierarchy;
- missing proof/case groups collapse cleanly;
- mobile order remains semantic;
- print/PDF does not create obvious orphaned headings where CSS can prevent it;
- no low-contrast disabled-looking service card states;
- existing field-pilot content can be regenerated without manual page design.

## Phase DS-3 — Placeholder and content-state engine

**Status: implemented in PR #235; CI validation pending**

Delivered:

- explicit semantic slot state: `source`, `derived`, `placeholder`;
- automatic semantic placeholder scaffold when rescued copy cannot satisfy the required Home model;
- only editorial-content shortages are recoverable; structural/safety blockers remain hard blockers;
- placeholder slot list and `publishable` state are exposed by the automatic plan;
- placeholder state is persisted on the clean Home draft;
- WordPress public/scheduled status writes are forced back to `draft` while placeholder slots remain;
- evidence groups remain opt-in verified only;
- detailed contract in `docs/CONTENT_PLACEHOLDERS.md`.

The content optimizer can later replace placeholder slots first, improve weak derived/source copy and retain provenance without changing the visual component contract.

## Phase DS-4 — Remaining presets

**Status: planned**

Implement the same shared engine for:

- Tech / SaaS (`saas-digital-product`);
- Local Pro (`local-business`);
- Creative / Studio (`creative-studio`, with safe transition from the existing `publisher` compatibility surface);
- Commerce / Product (`ecommerce`).

Each preset gets its own composition/art direction but shares semantic tokens and primitive contracts.

## Phase DS-5 — Automated visual/performance acceptance

**Status: planned**

Extend CI to cover:

- design-system CSS presence/order;
- preset CSS isolation;
- representative responsive screenshots or DOM/layout assertions where practical;
- Lighthouse/performance baseline;
- contrast/focus/reduced motion;
- print/PDF layout sanity;
- no remote font/design dependency;
- no mandatory frontend JS for static presentation;
- placeholder publication guard.

## Phase DS-6 — EMMAKE regeneration

**Status: planned after Corporate Premium acceptance**

Procedure:

1. install the accepted Theme/Migration Bridge build on `/nuevaweb/`;
2. do not repeat rescue/reset unless required by data drift;
3. regenerate the clean Home using Automatic Home Content;
4. apply Corporate Premium composition;
5. review desktop/mobile/print output;
6. verify that no client-specific visual patch is needed.

Any reusable defect returns to DS-1/DS-2, not to an EMMAKE-only stylesheet.

## Phase DS-7 — Native SEO/GEO handoff and whole-site pipeline

**Status: blocked by DS-6 acceptance**

After Home visual acceptance:

- run Native SEO/GEO handoff;
- continue Corporate page pipeline (Services, Work, About, Contact);
- run Insights and Site Readiness;
- validate canonicals, metadata, structured data, internal links, crawler policy, accessibility and performance;
- prepare cutover plan.

## Definition of done for Design System 1.0

Design System SEO/GEO 1.0 is done when:

- the five product-facing presets use one shared Theme engine;
- each preset can render credible modern layouts using rescued or placeholder draft content;
- missing legacy copy does not force manual layout work;
- evidence cannot be fabricated by the placeholder/generation path;
- no preset requires a page builder;
- no preset requires remote fonts or heavy frontend frameworks;
- SEO/GEO semantic contracts survive visual variation;
- accessibility and performance gates remain green;
- EMMAKE can be regenerated from the same rescued source into Corporate Premium without one-off design patches.
