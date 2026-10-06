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

**Status: active — final system surfaces**

#### DS-3A — Final Home

**Status: complete in code/CI**

Delivered and merged on PR #240:

1. plain-language product Hero;
2. abstract non-evidentiary product preview;
3. functional Bento for problem/workflow/outcome;
4. three-step product flow;
5. integrations/security/trust area with verified-data gate;
6. use cases;
7. pricing-plan structure without invented prices;
8. FAQ;
9. one clear final CTA;
10. EN/ES provisional copy outside the renderer;
11. dedicated SaaS contract CI;
12. zero required project JavaScript and no Corporate performance regression.

The front-page template keeps ownership of the H1. The final Home pattern does not add a competing H1.

#### DS-3B — SaaS inner pages

**Status: complete in code/CI**

Delivered and merged on PR #241:

- Product;
- Features;
- Solutions;
- Integrations;
- Pricing;
- Resources;
- Contact/Demo.

All seven reuse the accepted SaaS visual primitives instead of adding another stylesheet. Copy is stored in an explicit ES/EN placeholder contract, remains non-publishable until hydrated and cannot introduce numeric pricing, fabricated integrations, contact details, response times or remote evidence.

The page template keeps document-H1 ownership. The preset-owned renderer starts below the title and exposes stable hydration slots for page lead, sections, cards, provisional product visual and CTA.

PR #241 passed SaaS contract, WPCS/PHPStan, WordPress 7.1/PHP 8.2 activation, zero-plugin self-contained Theme, accessibility/responsive, native multilingual and Lighthouse budgets before merge.

#### DS-3C — SaaS system surfaces

**Status: implementation candidate**

Current branch: `feat/saas-final-system-surfaces`.

Implemented candidate surfaces:

- Single with category, document H1, author/date, featured media, authored content, author context and previous/next navigation;
- Archive with dynamic archive title/description, responsive post grid, search fallback and pagination;
- localized ES/EN 404 with home recovery and native search.

The preset-template runtime is generalized through an explicit allowlist so Corporate behavior remains unchanged while SaaS can own the same three system surfaces. Only untouched `source=theme` templates are replaced. Site Editor `source=custom` templates always win; unsupported presets/slugs and missing files fail safe to the neutral Theme template.

No new CSS, frontend JavaScript, remote dependency or fabricated evidence is introduced by the system surfaces.

#### DS-3D — SaaS visual/performance acceptance

**Status: active with DS-3C**

Require PHP/WPCS/PHPStan, WordPress activation, accessibility/responsive, multilingual behavior, self-contained Theme, dedicated SaaS contract, executable runtime isolation/fallback tests and strict Lighthouse budgets before merge. Performance budgets must not be raised to make the new visual work pass.

When DS-3C/DS-3D close green, Tech / SaaS is complete in code/CI and the Design System roadmap moves to Local Business. This does not make the Theme stable; Phase 10E real-site acceptance remains a separate release gate.

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
