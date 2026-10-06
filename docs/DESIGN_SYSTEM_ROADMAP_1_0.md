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

**Status: complete in code/CI**

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

**Status: complete in code/CI**

Delivered and merged on PR #242:

- Single with category, document H1, author/date, featured media, authored content, author context and previous/next navigation;
- Archive with dynamic archive title/description, responsive post grid, search fallback and pagination;
- localized ES/EN 404 with home recovery and native search;
- generalized preset-template runtime with explicit Corporate + SaaS allowlist;
- Site Editor `source=custom` templates always win;
- unsupported presets/slugs and missing files fail safe to the neutral Theme template.

PR #242 passed the full 12-workflow battery, including the dedicated SaaS runtime tests, Corporate regression contract, WPCS/PHPStan, self-contained Theme, native multilingual, accessibility/responsive, WordPress 7.1/PHP 8.2 and Lighthouse without relaxing budgets.

#### DS-3D — SaaS visual/performance acceptance

**Status: complete in code/CI**

Tech / SaaS is closed at implementation/CI level. This does not make the Theme stable; Phase 10E real-site acceptance remains a separate release gate.

### DS-4 — Local Business final preset

**Status: active**

Turn `local-business` into a 99%-finished Local Pro preset with location/service hierarchy, strong contact intent, verified local facts and LocalBusiness Schema only when visible facts support it.

#### DS-4A — Final Home

**Status: implementation candidate**

Current branch: `feat/local-business-final-home`.

Candidate composition:

- local-trust Hero with no fabricated address, phone, hours or coverage;
- clear enquiry / service-discovery actions;
- three-card service hierarchy prepared for the real offer;
- local service-area visual that does not require a remote map;
- explicit anti-doorway coverage contract;
- three-step contact/qualification flow without response-time promises;
- visible verified-fact surface for address, contact and hours;
- local FAQ;
- final contact CTA;
- ES/EN provisional copy outside the renderer;
- zero required frontend JavaScript and no third-party visual dependency.

The front-page template retains H1 ownership. Address, contact, hours, service-area and location facts remain marked as placeholders and block publication until authoritative hydration. LocalBusiness Schema stays governed by the existing visible-fact confirmation contract.

#### DS-4B — Local Pro inner pages

**Status: next after Home acceptance**

Build final Services, Locations, About, FAQ and Contact compositions with distinct local value, verified facts and no place-name doorway duplication.

#### DS-4C — Local Pro system surfaces

**Status: queued after inner pages**

Add preset-owned Single, Archive and 404 surfaces only after Home and inner pages are accepted.

#### DS-4D — Local Pro visual/performance acceptance

**Status: active with DS-4A**

Require existing Local Business semantic contract, dedicated final-Home contract, PHP/WPCS/PHPStan, WordPress activation, self-contained Theme, accessibility/responsive, native multilingual and strict Lighthouse budgets. Performance limits must not be raised to make the new visual layer pass.

### DS-5 — Publisher / Creative transition

**Status: queued**

Upgrade the existing `publisher` compatibility surface into the final editorial/creative product direction without silently breaking existing preset IDs or migrations.

### DS-6 — Ecommerce final preset

**Status: queued**

Create a complete Commerce/Product direction while preserving WooCommerce/provider boundaries and avoiding fabricated products, reviews, prices or stock claims.

### DS-7 — Five-preset acceptance

**Status: blocked by DS-4 through DS-6**

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