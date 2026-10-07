# Phase 10E real-site pilot — emmake.com

This document is the operational source for the first real-site acceptance of the self-contained SEO/GEO Theme. Historical clone, migration and earlier Corporate milestones remain evidence, but they do not override the current product decision.

## Current authority

Canonical machine-readable candidate:

`release/emmake-phase10e-candidate.json`

Frozen Corporate v5 A3.1 field identity:

- site ID: `emmake-com`;
- production origin: `https://emmake.com/`;
- sandbox origin: `https://emmake.com/nuevaweb/`;
- target Theme release: `0.1.1`;
- release channel: `prestable`;
- frozen Theme implementation source commit: `c2396b0aa0a17f2ba8b6cfc4d8c7435ff884fa00`;
- Theme ZIP SHA-256: `f4c7074dd3105a04375a212845b54c5bec0e8782870d62df041a6086a36a584f`;
- Migration Bridge version: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic field-pilot pack SHA-256: `1736d8b6dfb9aeec8cc8132a4ee1c42d5d9e4a3afb4510934edfd0cecd5a9fe2`;
- stable decision: `no-go`;
- real-site acceptance: `pending`.

This Corporate v5 A3.1 identity is the only current technical field candidate. Deterministic repository evidence is necessary, but it is **not visual acceptance**. The exact frozen artifact must still prove the premium/WOW result on `/nuevaweb/`.

## Current pilot position

The technical Reset & Rebuild sequence has already reached Step 7 on the real `/nuevaweb/` clone:

- Rescue Manifest completed;
- Clone Reset completed;
- Corporate bootstrap completed;
- clean Home draft created;
- rescued/client content hydrated into native semantic slots;
- Yoast detected as an external provider without becoming Theme authority;
- native SEO/GEO handoff reported SEO-ready;
- machine preflight reported `ready_for_browser_qa=true`.

Do **not** rerun Reset, regenerate the Home, rehydrate content or replace the Migration Bridge merely because the renderer or presentation changes. The semantic draft and SEO/GEO state are already proven.

Corporate v4.3 remains historical evidence: technically valid but **visual/architectural NO-GO**.

## Corporate v5 field evolution

### A1 — renderer/model boundary

Corporate v5 A1 proved the Theme-owned strategic Home renderer but failed the human visual gate on the real sandbox. The field result exposed legacy/database-customized strategic chrome, hero imbalance, generic capability hierarchy, residual list markers, default WordPress CTA styling, a weak footer and presentation debt from the previous Corporate stylesheet.

The permanent conclusion is that the strategic Theme must own not only `<main>` but the **entire strategic document chrome**.

### A2 — clean Theme-owned strategic system

Corporate v5 A2:

- renders a Theme-owned strategic header and footer directly server-side;
- keeps preset navigation as navigation authority without database-overridable `block_template_part()` presentation;
- removes legacy Corporate v4 presentation from strategic requests;
- builds the client release from the neutral Theme foundation plus the clean v5 strategic stylesheet;
- rebalances hero, capabilities, method, Insights, final CTA and footer as one coherent v5 visual system;
- explicitly resets list markers and owns CTA button styling;
- preserves the existing hydrated content and SEO/GEO state unchanged.

Real `/nuevaweb/` A2 QA confirmed the architecture was moving in the right direction, but the page still used too many near-black surfaces and lost visual separation in secondary text/cards.

### A3 — lighter field refinement

Corporate v5 A3 kept A2 architecture intact and changed presentation only:

- lighter teal hero and primary feature surfaces;
- stronger secondary text/card separation;
- Method moved from near-black to a light mint/white premium surface;
- final CTA moved to a light mint surface with dark ink and a strong green CTA;
- featured dark cards retained high contrast while moving several tones lighter;
- footer moved from near-black to branded teal with stronger hierarchy and pill navigation;
- mobile menu followed the same lighter strategic palette;
- no content, URL, SEO/GEO, Reset, hydration, Migration Bridge or production mutation.

### A3.1 — bounded visual/media closure

Corporate v5 A3.1 is the exact frozen technical candidate for the next single sandbox pass. It keeps the accepted A2/A3 semantic architecture and content state while:

- compacting the hero so the primary actions fit the practical first desktop viewport;
- adding a real local Theme-owned hero visual;
- adding stable capability visual treatment without remote dependencies;
- strengthening Method hierarchy and process continuity;
- allowing the first Insights card to prefer WordPress featured media and use a local Theme fallback when absent;
- adding a reusable local editorial visual library for marketing, AI, automation/data, strategy/content and analytics/research;
- keeping the public strategic Home server-rendered with zero required project frontend JavaScript;
- retaining zero third-party visual requests.

The final repository performance fixture for Corporate A3.1 records Lighthouse `100`, FCP `751.72 ms`, LCP `901.72 ms`, CLS `0`, TBT `0`, `8` total requests, `0` third-party requests, `0` project JavaScript bytes and `118` DOM nodes.

A temporary 13-request regression was traced to five decorative SVG data URIs. Those decorative icons were replaced with local CSS/pseudo-element treatment rather than weakening the eight-request product budget. Hero and Insights retain their meaningful media slots.

## Corporate v5 — Theme-owned frontend

Canonical architecture: `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`.

Corporate v5 preserves the existing content/SEO state while changing who owns rendering:

```text
existing hydrated semantic content
        ↓
corporate-home-v1
        ↓
Corporate Theme renderer
        ↓
server-rendered semantic strategic document
        ↓
Theme-owned header / Home / footer / CSS / responsive behavior
```

The master Home no longer depends on Gutenberg constructs such as:

- `wp-block-post-content` as the master layout surface;
- `is-layout-constrained` for strategic composition;
- `contentSize` / `wideSize` as the Corporate page-width authority;
- nested Group/Columns blocks as the renderer contract;
- generated Gutenberg layout CSS to determine master section alignment;
- database-overridable block template parts for the Corporate strategic header/footer.

WordPress remains the CMS and resource authority. Gutenberg remains available for blog/editorial content and simple client-managed pages.

## Repository acceptance boundary

The exact A3.1 implementation source `c2396b0aa0a17f2ba8b6cfc4d8c7435ff884fa00` has passed the repository gates for Foundation, Design System, Corporate Page Pipeline, Package, Release Artifact, PHP Quality/PHPStan, Native Multilingual, Self-contained Theme, WordPress Smoke, Accessibility/Responsive and Performance Baseline.

The accepted v5 baseline covers:

- Theme-owned semantic header/main/footer and one strategic H1;
- server-side rendering and dynamic WordPress Insights;
- no strategic `wp-block-post-content` authority;
- no legacy Corporate v4 visual CSS in the v5 client bundle;
- keyboard/focus and reduced-motion behavior;
- automated WCAG/accessibility checks;
- responsive/reflow coverage at `320 / 390 / 768 / 1024 / 1440`;
- WordPress smoke, upgrade and rollback coverage;
- PHP quality and static analysis;
- deterministic self-contained packaging;
- zero required project frontend JavaScript;
- zero required third-party visual requests.

Automated results do **not** replace the real `/nuevaweb/` visual/product gate.

## Corporate v5 acceptance rule

Corporate v5 must prove all of the following before the other preset master surfaces continue:

- reuse the existing rescued/hydrated content without rerunning Reset or migration;
- render the Corporate Home from the existing semantic model/content slots;
- make the Theme the sole strategic layout/chrome authority;
- preserve one correct semantic heading hierarchy;
- preserve internal links and SEO/GEO output;
- preserve accessibility and server-side rendering;
- remain within performance budgets;
- remain stable at `1440 / 1024 / 768 / 390`;
- meet the premium/WOW visual gate on the real `/nuevaweb/` sandbox;
- establish a reusable renderer boundary that SEO/GEO Manager can later use for generated landings.

The `320` compact-mobile automated check remains an additional stress case.

## Relationship with SEO/GEO Manager

The v5 renderer boundary is intentionally designed for the future Manager.

Manager will be able to:

- create/update structured strategic landing models without generating Gutenberg layout trees;
- create complete blog posts automatically;
- materialize automated articles as normal WordPress posts;
- let authorized clients edit those articles in Gutenberg;
- schedule/publish/refresh content under explicit policy;
- maintain internal-link/cluster plans;
- resolve SEO/GEO output ownership without duplicating Theme output.

This Manager roadmap does **not** require Manager to run the public frontend. Theme rendering remains local to WordPress.

## Existing milestones remain valid

The product-owned clone from `https://emmake.com/` to `https://emmake.com/nuevaweb/`, Rescue Manifest, Reset, content rescue, clean semantic Home creation, hydration and SEO/GEO handoff remain accepted evidence. They do not need to be repeated because presentation architecture changes.

The replatforming rule remains: preserve authored content, URLs/redirects, valid SEO signals, important links, useful media, verified organization/contact facts and required business behavior; replace legacy theme/builder presentation debt rather than copying it into the new site.

## Sandbox safety boundary

All Corporate v5 work remains isolated to `/nuevaweb/` until acceptance. Production remains untouched.

The sandbox safety constants already used by this pilot remain required:

```php
define( 'SEO_GEO_MIGRATION_SANDBOX', true );
define( 'SEO_GEO_MIGRATION_SANDBOX_MODE', 'subdirectory' );
define( 'SEO_GEO_MIGRATION_STORAGE_ISOLATED', true );
define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );
define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );
```

Search visibility, canonical/hreflang/sitemap authority, production `home`/`siteurl` and rollback boundaries must remain isolated from production. No credentials, dumps, private submissions or customer data enter the repository.

## Field execution for Corporate v5 A3.1

The destructive/reset portion must **not** be repeated unless a real data/state failure is proven.

1. Keep the existing `/nuevaweb/` clone and Step 1–7 evidence.
2. Keep Migration Bridge `0.8.60` unchanged.
3. Preserve the existing clean hydrated Home data/content.
4. Verify the Theme ZIP SHA-256 is exactly `f4c7074dd3105a04375a212845b54c5bec0e8782870d62df041a6086a36a584f` before installation.
5. Replace the Theme with that frozen Corporate v5 A3.1 candidate **only** on `/nuevaweb/`.
6. Clear only relevant WordPress/Hostinger/browser caches; do not reset or rehydrate.
7. Open the same logical hydrated Home through the Theme-owned renderer.
8. Verify first that the header is a single coherent strategic header and that no legacy list/title/`Menu` stack remains.
9. Review the full desktop composition before advancing to narrower viewports.
10. After desktop visual direction is accepted, run field QA at `1024 / 768 / 390` one viewport at a time.
11. Do not advance the next roadmap stage until the Home receives explicit visual approval.

If the semantic data or Step 7 state has drifted, stop and investigate instead of recreating evidence casually.

## Browser QA acceptance — premium/WOW gate

Record the five field checks:

1. `visual-layout` — immediate visual impact, premium hierarchy, intentional rhythm, section variety, coherent language and decisive CTAs; merely “better” is not sufficient;
2. `responsive-behavior` — no overflow, squeezed desktop layouts, pathological wrapping, narrow vertical text strips or weak mobile recomposition;
3. `accessibility` — correct headings/landmarks, keyboard/focus behavior, meaningful links and sufficient contrast;
4. `seo-geo-rendered-output` — title, description, canonical, robots, Schema/discovery output and important internal links remain correct;
5. `performance` — no material runtime/asset regression and acceptable measured sandbox performance.

A technically valid result that still fails the premium/WOW standard remains a blocker.

For the next A3.1 desktop screenshot specifically verify:

- one clean premium header, with no stray bullets or duplicate `EMMAKE`/`Menu` surfaces;
- hero actions visible in the practical first desktop viewport;
- the hero visual provides purposeful right-side balance without dominating the copy;
- readable capabilities with clear separation between featured and secondary cards;
- Method feels premium and connected without becoming a dark visual wall;
- zero stray Insights bullets and correct featured media/fallback behavior;
- final CTA is deliberate, light and strongly actionable;
- branded teal footer has clear hierarchy and usable navigation;
- coherent vertical rhythm and no forced wrapping/overflow.

## Representative SEO/GEO regression set

At minimum validate Home, Sobre Nosotros, Trabaja con Nosotros, blog index, one representative recent article and Contacto, plus every additional URL class identified by the Rescue Manifest. Review status, canonical, robots/indexability, title/meta, Open Graph, Schema, hreflang when configured, redirects, sitemap membership, organization/contact facts and important links.

## Sandbox acceptance exit

The sandbox becomes eligible for production-entry review only when the exact frozen Corporate v5 A3.1 artifact is installed, the Theme-owned Home passes the product-quality visual gate, no URL/SEO/GEO blocker remains, no required legacy builder runtime remains, responsive/accessibility/performance checks pass, critical navigation/forms work, browser evidence is complete and rollback remains available.

This is **not** automatic production cutover.

## Stable promotion boundary

Theme stable promotion remains **NO-GO**.

SEO/GEO Manager is a separate product and is not itself a dependency for Theme stable acceptance. However, the Theme renderer architecture must be suitable for the future Manager Landing Engine before the Corporate master is considered product-complete.

Until Corporate v5 A3.1 and later production acceptance exist:

- `release/stable-release-decision.json` remains `no-go`;
- real-site acceptance remains `pending`;
- Corporate v5 A3.1 is the frozen technical field authority;
- Corporate v5 A1/A2/A3 and v4.3 remain historical field/technical evidence;
- no stable release is published.
