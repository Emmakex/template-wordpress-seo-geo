# Roadmap — Design System SEO/GEO 1.0

## Product rule

The preset is the finished product, not a blank shell. A clean WordPress install must already look like a credible modern website before client content is hydrated.

The normal order is:

`final preset -> safe provisional content/media -> rescued/client content -> hydration -> site ready -> SEO/GEO Manager refinement`

Legacy presentation is never a design source. The old site may contribute useful authored content, URLs, metadata, links, factual entities and media only. Legacy themes, builders, styling and plugin debt are not carried forward by default.

## Current baseline — 2026-10-06

- Theme: `seo-geo-theme` `0.1.1`, channel `prestable`.
- Five-preset implementation closure: `14d309023f97bb4f54e24e7258b55cec2e5e703d`.
- EMMAKE field-pilot version-contract closure: `d3f4022ff2f46c2ee965bae561217b990bf2edf0`.
- Installable Theme SHA-256: `313796fde03e0204a334d204098222e5d0521b9efeb2ec622fbd92c0522b8c15`.
- Migration Bridge for the EMMAKE field pilot: `0.8.60`.
- Theme status: complete at implementation/CI level; **not stable yet**.
- Remaining release blocker: Phase 10E real-site acceptance on EMMAKE `/nuevaweb/`.

The stable-release decision remains `NO-GO` until the real-site acceptance evidence is complete. Code/CI closure alone does not authorize production cutover.

## DS-0 — Product and migration boundary

**Status: complete**

- Theme, Migration Bridge and SEO/GEO Manager are separate product responsibilities.
- Migration Bridge rescues what is valuable and supports reset/rebuild; it does not preserve legacy presentation debt.
- Theme owns reusable visual/layout/runtime behavior.
- SEO/GEO Manager will own ongoing optimization after the rebuilt site is ready.
- Placeholders cannot fabricate evidence, pricing, certifications, customers, reviews or performance claims.

## DS-1 — Corporate reference preset

**Status: complete in code/CI**

Corporate is the reference implementation for the reusable preset contract:

- final Home;
- final Services, Work, About, Contact and Insights compositions;
- preset-owned Single, Archive and 404 surfaces;
- responsive/accessibility contracts;
- strict performance budgets;
- safe provisional copy/media that blocks readiness until hydrated;
- Site Editor custom templates remain authoritative.

Corporate is the preset selected for the first real EMMAKE `/nuevaweb/` hydration/acceptance pilot.

## DS-2 — Shared Design System primitives

**Status: complete in code/CI**

The shared opt-in layer provides reusable shell widths, spacing rhythm, cards, Bento/editorial grids, process sequences, motion/reduced-motion and print behavior.

It is not globally forced. Presets opt in only to the primitives they need so accepted designs do not gain unnecessary CSS, JavaScript or requests.

## DS-3 — Tech / SaaS / Digital Product

**Status: complete in code/CI**

Delivered through PRs `#240`–`#242`.

Accepted scope:

- final SaaS Home;
- Product, Features, Solutions, Integrations, Pricing, Resources and Contact/Demo;
- Single, Archive and localized 404;
- EN/ES provisional copy contract;
- no fabricated integrations, prices, customers, security claims or response times;
- zero required project JavaScript for static presentation;
- runtime isolation and neutral fallback behavior;
- strict Lighthouse/performance, accessibility, multilingual and WordPress activation gates.

## DS-4 — Local Pro / Local Business

**Status: complete in code/CI**

Delivered through PRs `#243`–`#245`.

Accepted scope:

- final local-service Home;
- Services, Locations, About, FAQ and Contact;
- Single, Archive and localized 404;
- verified-fact gates for address, phone, hours and service area;
- explicit anti-doorway / no-city-clone rules;
- LocalBusiness Schema only when visible verified facts support it;
- no fabricated reviews, ratings, maps, credentials or coverage claims;
- full performance/accessibility/multilingual/runtime regression gates.

## DS-5 — Publisher / Editorial / Creative

**Status: complete in code/CI**

Delivered through PRs `#246`–`#248`.

Accepted scope:

- final editorial Home;
- Articles, Topics, Authors, About, Editorial Policy and Contact;
- Single, Archive and localized 404;
- real WordPress authors/dates/content remain authoritative;
- no fabricated authors, citations, readership, popularity, reviewer or fact-checker identities;
- maintained taxonomy instead of thin keyword archives;
- no required frontend JavaScript or remote visual dependency;
- full runtime isolation and regression gates.

## DS-6 — Ecommerce

**Status: complete in code/CI**

The fifth and final preset is closed through:

- PR `#249` — final Ecommerce Home;
- PR `#250` — final Ecommerce inner pages;
- PR `#251` — final Ecommerce Single, Archive and 404 system surfaces.

The final closure landed on `main` at `14d309023f97bb4f54e24e7258b55cec2e5e703d` with the complete post-merge workflow battery green.

Accepted scope:

- modern product-discovery Home;
- Shop discovery shell, Categories, Buying Guides, About, Support and Contact;
- native WordPress editorial Single/Archive plus localized 404;
- provider-safe category/catalog placeholders until real commerce data exists;
- verified policy/contact routes only;
- no fabricated product identity, price, discount, stock, availability, reviews, ratings, shipping, returns, warranty, certification or payment method;
- no Theme ownership of `single-product`, `archive-product`, cart, checkout or account;
- Product/Offer/Review/AggregateRating Schema remains commerce-provider owned;
- WooCommerce is the preferred provider, but live commerce capability requires an accepted adapter.

### DS-6 performance/quality closure

**Status: complete in code/CI**

The Ecommerce closure passed the same shared gates as the earlier presets without relaxing budgets: PHP quality, WordPress activation, self-contained Theme, accessibility/responsive, native multilingual, performance baseline and preset regressions.

## DS-7 — Five-preset acceptance

**Status: complete in code/CI; real-site acceptance pending**

All five product-facing presets now satisfy the implementation contract:

- credible clean-install visual composition;
- desktop/mobile/tablet hierarchy;
- keyboard/focus/contrast/reduced-motion support;
- no required remote fonts or heavy frontend framework;
- no required project JavaScript for static presentation;
- strict Core Web Vitals/performance budgets without raising limits to make designs pass;
- one-H1 ownership rules;
- crawlable HTML navigation and internal links;
- evidence/commercial-fact gates;
- EN/ES baseline where required;
- preset runtime isolation with safe neutral fallback;
- Site Editor `source=custom` templates remain authoritative.

This closes the **Design System and five-preset implementation phase**. It does not by itself declare Theme `stable`.

## Phase 10E — EMMAKE real-site acceptance

**Status: active — final external release gate**

Target: `https://emmake.com/nuevaweb/`.

The controlled pilot package contains:

- SEO/GEO Theme `0.1.1` prestable;
- Migration Bridge `0.8.60`;
- Corporate Home blueprint fallback;
- exact manifest/checksums;
- evidence template;
- field runbook.

Execution sequence:

1. verify recoverable sandbox database/files backup;
2. install the exact Theme and Migration Bridge artifacts;
3. create/refresh Rescue Manifest;
4. reset the clone runtime while retaining only genuinely required business functions;
5. bootstrap Corporate;
6. create the clean private Home draft;
7. automatically map useful rescued content into the native Corporate semantic model;
8. apply provider-neutral native SEO/GEO handoff;
9. obtain `ready_for_browser_qa=true` from field-pilot readiness;
10. complete browser evidence for visual layout, responsive behavior, accessibility, rendered SEO/GEO output and performance.

Production Home replacement is **not automatic**. Passing the sandbox pilot only makes the rebuilt Home eligible for explicit cutover review.

## EMMAKE role

EMMAKE `/nuevaweb/` is the first real hydration/acceptance client for Corporate. It is not the design source and must not receive one-off visual patches that belong in the reusable preset.

If the real client reveals a reusable Theme defect, fix the reusable Theme and re-run acceptance. If the difference is factual/client content, hydrate the client content rather than changing the preset architecture.

## Stable release gate

SEO/GEO Theme 1.0 may move from `prestable` toward `stable` only after Phase 10E demonstrates on the real sandbox that:

- reset/rebuild preserves the valuable SEO/content identity while discarding legacy presentation debt;
- the Corporate preset renders as intended with real rescued content;
- no legacy builder debris survives in rendered output;
- SEO title/description/indexability/canonical behavior is correct;
- Schema/discovery output is evidence-backed;
- internal links and rescued URLs are coherent;
- mobile/desktop accessibility and visual hierarchy pass;
- measured performance is acceptable without loosening product budgets;
- rollback/recovery remains available;
- production cutover remains an explicit separate action.

## Next product phase — SEO/GEO Manager

**Status: queued after Phase 10E acceptance**

Once the reusable Theme is proven on the real site, development focus moves to the SEO/GEO Manager for continuous optimization rather than further expanding Theme scope.

Manager responsibilities include:

- ongoing content optimization;
- landing/blog publishing workflows;
- internal-link opportunities;
- metadata and structured-data refinement;
- keyword/topic/entity opportunities;
- GEO/discovery improvements;
- Search Console/Bing-oriented iteration;
- optimization history and controlled recommendations.

The Manager must refine a healthy rebuilt site, not compensate for legacy layout debt or unfinished Theme presets.

## Definition of done

Design System SEO/GEO 1.0 is **complete in code/CI** because all five presets can create credible modern sites from a clean install while preserving the shared SEO/GEO, performance, accessibility, multilingual and evidence contracts.

The overall Theme product becomes **release-ready/stable only after Phase 10E real-site acceptance** is completed with recorded evidence.
