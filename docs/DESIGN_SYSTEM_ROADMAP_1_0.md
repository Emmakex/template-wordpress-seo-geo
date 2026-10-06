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

**Status: complete in code/CI**

`local-business` is now a 99%-finished Local Pro preset with location/service hierarchy, strong contact intent, verified-local-fact gates and LocalBusiness Schema only when visible facts support it.

#### DS-4A — Final Home

**Status: complete in code/CI**

Delivered and merged on PR #243 with 13/13 workflows green:

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

PR #243 passed the existing Local Business semantic contract, dedicated Local Pro Home contract, WPCS/PHPStan, WordPress 7.1/PHP 8.2 activation, zero-plugin self-contained Theme, accessibility/responsive, native multilingual and Lighthouse without relaxing budgets.

#### DS-4B — Local Pro inner pages

**Status: complete in code/CI**

Delivered and merged on PR #244 with 13/13 workflows green:

- Services — real offer, scope, exclusions and next-step structure without keyword-variant duplicate pages;
- Locations — verified locations/service areas only, with meaningful local operational differences and an explicit no-doorway/no-city-clone rule;
- About — sourced history, method, people and authority without invented age, awards, credentials or team size;
- FAQ — visible, maintainable answers aligned with authoritative service, coverage, hours and contact facts;
- Contact — public channels, customer-facing location and hours remain fact-gated until verified.

All five reuse the accepted Local Pro visual system instead of adding another stylesheet. ES/EN provisional copy lives outside the renderer. The page template retains document-H1 ownership. Locations and Contact expose fact placeholders independently from editorial copy so hydration can insert authoritative local facts without redesigning the page.

PR #244 passed the existing semantic contract, accepted Home contract, dedicated inner-page contract, WPCS/PHPStan, WordPress activation, self-contained Theme, accessibility/responsive, native multilingual and Lighthouse without relaxing budgets.

#### DS-4C — Local Pro system surfaces

**Status: complete in code/CI**

Delivered and merged on PR #245 with 13/13 workflows green:

- Single — category, document H1, date/author, featured media, authored content, author context and previous/next navigation;
- Archive — dynamic archive title/description, responsive post grid, no-results search and pagination;
- 404 — localized ES/EN recovery copy, home action and native site search;
- generalized preset-template runtime extended with `local-business` while preserving Corporate and SaaS behavior;
- Site Editor `source=custom` templates remain authoritative;
- unsupported presets/slugs and missing files fail safe to the neutral Theme template;
- system templates contain no hard-coded address, phone, hours, service area, review, rating, LocalBusiness Schema or remote-map evidence;
- no additional Local Pro CSS or required project JavaScript.

The merge landed on `main` as `0aef943d83cd2a404a92322e829a499fa0a6a6da`. The complete 13-workflow post-merge battery also passed with zero failures.

#### DS-4D — Local Pro visual/performance acceptance

**Status: complete in code/CI**

Local Pro is closed at implementation/CI level. This still does not make the Theme stable; Phase 10E real-site acceptance remains a separate release gate.

### DS-5 — Publisher / Creative transition

**Status: active**

Upgrade the existing `publisher` compatibility surface into the final editorial/creative product direction without silently breaking the existing preset ID, editorial model or migrations.

#### DS-5A — Publisher final Home

**Status: complete in code/CI**

Delivered and merged on PR #246 with 14/14 workflows green. The merge landed on `main` as `a24ee895ce746448e7c56e17d0f70062622b0388`, and the complete post-merge battery also finished green.

The accepted Home provides:

- type-led editorial identity and calm reading surface;
- finished lead-story composition without inventing a real article;
- mixed-scale priority-story grid rather than a flat feed;
- topic discovery prepared for the real taxonomy, not keyword-only archive pages;
- visible editorial-trust module for authorship, sourcing and native WordPress dates;
- evergreen-resource module for real guides, explainers or cornerstone content;
- author-discovery surface that only hydrates from approved public WordPress users;
- final follow/newsletter CTA that adds no external subscription dependency by itself;
- EN/ES provisional copy outside the renderer;
- zero required frontend JavaScript, remote fonts or third-party visual dependencies.

The front-page template retains H1 ownership. Provisional stories, topics and author surfaces remain non-publishable until hydration. The existing Publisher semantic contract remains authoritative for real authors, dates, references, BlogPosting eligibility and provenance.

#### DS-5B — Publisher inner pages

**Status: complete in code/CI**

Delivered and merged on PR #247 with 12/12 workflows green. The merge landed on `main` as `2ecbe3f00b517ae087d7e495380c87ec4bc9b553`, and the complete 12-workflow post-merge battery also finished green.

The accepted compositions are:

- Articles — real published work, editorial priorities and maintained reading paths without fabricated freshness or popularity;
- Topics — maintained taxonomy and distinct subject hubs, rejecting thin keyword-only archives;
- Authors — approved public WordPress users and real authored work only, with no inferred credentials or ghost identities;
- About — sourced publication purpose, scope, governance and accountability;
- Editorial Policy — real operating standards, sourcing, corrections and conflict rules without invented review or fact-checking claims;
- Contact — verified public editorial, contributor and business routes without invented departments, addresses or response times.

All six use one shared renderer and reuse the accepted Publisher visual system plus shared Design System primitives. DS-5B adds no new stylesheet and no required frontend JavaScript. EN/ES provisional copy lives outside the renderer and remains publication-blocking until hydrated.

The page template retains document-H1 ownership. The existing Publisher authorities remain unchanged: public authors from approved WordPress users/profiles, dates from WordPress timestamps, and references from real editor-added sources.

#### DS-5C — Publisher system surfaces

**Status: implementation candidate**

Current branch: `feat/publisher-final-system-surfaces`.

Candidate scope:

- Single — category, document H1, native WordPress date/author, featured media, authored content, author biography and previous/next navigation;
- Archive — real archive title/description, real public posts, native dates/authors, no-results search and pagination;
- 404 — localized ES/EN recovery copy, home route and native search;
- generalized preset-template runtime extended to Publisher while preserving Corporate, SaaS and Local Pro behavior;
- Site Editor `source=custom` templates remain authoritative;
- unsupported presets/slugs and missing files continue to fail safe to the neutral Theme template;
- `ecommerce` remains neutral until DS-6 and is used as the executable isolation control;
- system templates contain no hard-coded authors, citations, source URLs, dates, popularity counters, reviewer/fact-checker identities, Schema evidence or remote content;
- no additional Publisher CSS and no required project JavaScript.

DS-5C must pass the existing Publisher semantic, Home and inner-page contracts, the dedicated system-surface contract, executable 4×3 runtime isolation/fallback coverage, WPCS/PHPStan, WordPress activation, self-contained Theme, accessibility/responsive, native multilingual and strict Lighthouse budgets before merge.

#### DS-5D — Publisher visual/performance acceptance

**Status: active with DS-5C**

Publisher closes at implementation/CI level only after DS-5C is merged with the complete acceptance battery green. Phase 10E real-site acceptance remains a separate release gate.

### DS-6 — Ecommerce final preset

**Status: queued**

Create a complete Commerce/Product direction while preserving WooCommerce/provider boundaries and avoiding fabricated products, reviews, prices or stock claims.

### DS-7 — Five-preset acceptance

**Status: blocked by DS-5 through DS-6**

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
