# Roadmap — Design System SEO/GEO 1.0

## Product rule

The preset is the finished product, not a blank shell. A clean WordPress install must already look like a credible modern website before client content is hydrated.

Legacy presentation is never a design source. The old site may contribute useful authored content, URLs, metadata, links, factual entities and media only. Legacy themes, builders, styling and plugin debt are not carried forward by default.

The normal order is:

`finished preset -> rescued/client content -> semantic hydration -> real-site QA -> SEO/GEO Manager refinement`

## Current baseline — 2026-10-06

- Theme: `seo-geo-theme` `0.1.1`, channel `prestable`.
- Migration Bridge field-pilot version: `0.8.60`.
- Rescue -> reset -> Corporate bootstrap -> clean Home -> hydration -> native SEO handoff -> machine preflight has passed end to end on EMMAKE `/nuevaweb/`.
- The first real browser QA rejected the current Corporate visual result.
- Stable-release decision remains `NO-GO`.

The current blocker is therefore no longer migration architecture. It is product-level visual acceptance of the reference preset.

## Master preset strategy

**Corporate v3 is now the only active visual preset.**

SaaS, Local Pro, Publisher and Ecommerce remain frozen at their current implementation level until Corporate v3 is accepted on the real EMMAKE sandbox.

This avoids polishing five different visual systems before the product has proven one complete reference implementation.

Corporate v3 must establish the reusable quality baseline for:

- shell/grid geometry;
- typography and spacing rhythm;
- navigation behavior;
- responsive breakpoints;
- semantic hydration slots;
- media treatment;
- accessibility/focus/contrast;
- performance budgets;
- real browser acceptance procedure.

After Corporate v3 is approved, the other presets will derive from those proven primitives while keeping their own layout/commercial identity.

See `docs/CORPORATE_V3_MASTER_PRESET.md` for the binding visual/product contract.

## DS-0 — Product and migration boundary

**Status: complete**

- Theme, Migration Bridge and SEO/GEO Manager remain separate product responsibilities.
- Migration Bridge rescues what is valuable and supports reset/rebuild; it does not preserve legacy presentation debt.
- Theme owns reusable visual/layout/runtime behavior.
- SEO/GEO Manager will own ongoing optimization after the rebuilt site is healthy.
- Placeholders cannot fabricate evidence, pricing, certifications, customers, reviews or performance claims.

## DS-1 — Corporate v3 reference preset

**Status: active — highest priority**

The previous Corporate implementation passed code/CI but failed the first real visual acceptance on EMMAKE `/nuevaweb/`.

Corporate v3 must now be finished before any other preset receives further visual work.

Required scope:

- final Home with bespoke-quality visual direction;
- final Services, Work, About, Contact and Insights compositions;
- preset-owned Single, Archive and 404 surfaces;
- graceful states when proof/case studies/media are unavailable;
- semantic content slots that accept rescued content without reproducing legacy layout;
- responsive behavior designed for 1440 / 1024 / 768 / 390 px;
- no pathological wrapping, compressed cards or desktop navigation leaking into tablet/mobile;
- accessible contrast/focus/landmarks;
- strict performance budgets;
- Site Editor custom templates remain authoritative.

Corporate is approved only after the real hydrated `/nuevaweb/` Home passes browser QA at all four viewport gates.

## DS-2 — Shared Design System primitives

**Status: provisional until Corporate v3 acceptance**

The existing shared layer remains available, but Corporate v3 is allowed to refine or replace primitives where the real pilot shows that the current abstraction produces a generic or weak result.

Only primitives proven by Corporate v3 should become the base for the other presets.

## DS-3 — SaaS / Digital Product

**Status: frozen pending Corporate v3 approval**

Current code/CI work is retained. No further visual iteration until the master preset is accepted.

## DS-4 — Local Pro / Local Business

**Status: frozen pending Corporate v3 approval**

Current code/CI work is retained. No further visual iteration until the master preset is accepted.

## DS-5 — Publisher / Editorial

**Status: frozen pending Corporate v3 approval**

Current code/CI work is retained. No further visual iteration until the master preset is accepted.

## DS-6 — Ecommerce

**Status: frozen pending Corporate v3 approval**

Current code/CI work is retained. No further visual iteration until the master preset is accepted.

## Phase 10E — EMMAKE real-site acceptance

**Status: active — Corporate v3 field laboratory**

Target: `https://emmake.com/nuevaweb/`.

Already proven successfully:

1. sandbox safety guards;
2. Rescue Manifest;
3. clone runtime reset;
4. SEO/GEO Theme ownership;
5. Corporate bootstrap;
6. clean private Home creation;
7. automatic content hydration;
8. provider-neutral Yoast SEO handoff;
9. machine preflight -> `ready for browser QA`.

First real browser QA result:

- visual-layout: fail;
- responsive-behavior: fail;
- accessibility/contrast: fail;
- SEO/GEO rendered-output: pending browser validation;
- performance: pending browser validation.

Do not continue the Services rebuild and do not move toward production cutover until the Corporate Home is visually approved.

EMMAKE is a real acceptance environment, not a design source. Reusable defects found there must be fixed in Corporate v3, not patched only on the client site.

## Corporate v3 acceptance gate

The same hydrated Home must pass at:

- 1440 px desktop;
- 1024 px compact desktop/tablet landscape;
- 768 px tablet;
- 390 px mobile.

At every viewport verify:

- hierarchy/composition;
- no overflow or pathological word wrapping;
- correct navigation mode;
- readable typography and spacing;
- contrast and keyboard focus;
- logical content order and CTA clarity;
- no legacy-builder debris or exposed placeholder copy;
- acceptable performance without loosening budgets.

The existing SEO/GEO and machine-readiness gates remain mandatory.

## After Corporate approval

Only after Corporate v3 is approved:

1. extract the proven reusable primitives;
2. lock the reference responsive/accessibility/performance contract;
3. rebase SaaS on the approved foundation and accept it;
4. rebase Local Pro and accept it;
5. rebase Publisher and accept it;
6. rebase Ecommerce and accept it;
7. only then consider Theme 1.0 stable.

The presets may share engineering primitives, but must not become visual clones of Corporate.

## Next product phase — SEO/GEO Manager

**Status: queued after Theme acceptance**

The Manager will own ongoing content optimization, landing/blog publishing, internal-link opportunities, metadata/structured-data refinement, keyword/topic/entity opportunities, GEO/discovery improvements, Search Console/Bing iteration and optimization history.

It must refine a healthy rebuilt site, not compensate for an unfinished Theme.

## Definition of done

Design System SEO/GEO 1.0 is not complete merely because five preset implementations exist in code.

It becomes release-ready only when:

1. Corporate v3 proves the product standard on the real EMMAKE sandbox;
2. the other four presets are derived from the proven foundation and independently pass the same acceptance gates;
3. SEO/GEO, accessibility and performance gates remain green;
4. production cutover remains an explicit separate action.
