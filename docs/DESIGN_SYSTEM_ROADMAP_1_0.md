# Roadmap — Design System SEO/GEO 1.0

## Product rule

The preset is the finished product, not a blank shell. A clean WordPress install must already look like a credible bespoke website before client content is hydrated.

The normal order is:

`final preset -> safe provisional content/media -> rescued/client content -> hydration -> site ready -> SEO/GEO Manager refinement`

Legacy presentation is never a design source. The old site may contribute useful authored content, URLs, metadata, links, factual entities and media only.

## Current baseline

### DS-0 — Product and migration boundary

**Status: complete**

- Theme, Migration Bridge and future SEO/GEO Manager remain separate product responsibilities.
- Legacy layout/theme/plugin debt is not carried into the new site by default.
- Placeholders cannot fabricate evidence, pricing, certifications, customers or performance claims.

### DS-1 — Corporate reference implementation

**Status: complete in code/CI**

Corporate now provides the reference contract for a 99%-finished preset:

- final Home;
- final Services, Work, About, Contact and Insights compositions;
- preset-owned Single, Archive and 404 system surfaces;
- responsive and accessibility contracts;
- zero required project JavaScript;
- strict performance budgets;
- safe provisional copy/media that blocks readiness until hydrated;
- Site Editor custom templates remain authoritative.

Real-site sandbox and production acceptance remain separate Phase 10E release gates. Code/CI completion does not by itself make the Theme stable.

### DS-2 — Shared visual primitives

**Status: active transition**

A small opt-in CSS layer provides reusable shell widths, spacing rhythm, Bento/editorial grids, process sequences, card surfaces, motion/reduced-motion and print behavior.

The shared layer is not globally loaded. A preset adopts it only when its final visual implementation is ready. This prevents already accepted presets from gaining unnecessary bytes or requests.

### DS-3 — Tech / SaaS final preset

**Status: active**

#### DS-3A — Final Home

**Status: implementation candidate**

Target composition:

1. plain-language product Hero;
2. abstract non-evidentiary product preview;
3. functional Bento for problem/workflow/outcome;
4. three-step product flow;
5. integrations/security/trust area with verified-data gate;
6. use cases;
7. pricing-plan structure without invented prices;
8. FAQ;
9. one clear final CTA.

The front-page template keeps ownership of the H1. The final Home pattern must not add a competing H1.

#### DS-3B — SaaS inner pages

**Status: next**

Build final Product, Features, Solutions, Integrations, Pricing, Resources and Contact/Demo pages. Each must look finished before hydration and must keep unsupported product/commercial claims provisional.

#### DS-3C — SaaS system surfaces

**Status: next after inner pages**

Build SaaS-owned Single, Archive and 404 surfaces using the same preset runtime rule proven by Corporate, while preserving Site Editor custom templates.

#### DS-3D — SaaS visual/performance acceptance

**Status: next after full preset**

Require PHP/WPCS/PHPStan, WordPress activation, accessibility/responsive, multilingual behavior, self-contained Theme, dedicated SaaS contract and strict Lighthouse budgets before merge.

### DS-4 — Local Business final preset

**Status: queued**

Turn `local-business` into a 99%-finished Local Pro preset with location/service hierarchy, strong contact intent, verified local facts and LocalBusiness Schema only when visible facts support it.

### DS-5 — Publisher / Creative transition

**Status: queued**

Upgrade the existing `publisher` compatibility surface into the final editorial/creative product direction without silently breaking existing preset IDs or migrations.

### DS-6 — Ecommerce final preset

**Status: queued**

Create a complete Commerce/Product direction while preserving WooCommerce/provider boundaries and avoiding fabricated products, reviews, prices or stock claims.

### DS-7 — Five-preset acceptance

**Status: blocked by DS-3 through DS-6**

All five product-facing presets must pass:

- clean-install visual completeness;
- desktop/mobile/tablet hierarchy;
- accessible keyboard/focus/contrast/reduced motion;
- no required remote fonts or heavy frontend framework;
- no required project JS for static presentation;
- strict Core Web Vitals budgets without raising limits to make design pass;
- one H1 per page composition;
- crawlable HTML navigation and internal links;
- evidence and commercial-fact gates;
- EN/ES baseline where the preset contract requires it.

## EMMAKE role

EMMAKE `/nuevaweb/` is the first real hydration/acceptance client for Corporate. It is not the design source and it must not receive one-off visual patches that should live in the reusable preset.

## Definition of done

Design System SEO/GEO 1.0 is complete when the five presets can each create a credible modern website from a clean install, require only factual/client content hydration rather than manual layout design, remain self-contained and fast, and preserve the shared SEO/GEO semantic contracts.
