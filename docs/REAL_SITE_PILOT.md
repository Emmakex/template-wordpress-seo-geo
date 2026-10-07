# Phase 10E real-site pilot — emmake.com

This document is the operational source for the first real-site acceptance of the self-contained SEO/GEO Theme. Historical clone, migration and earlier Corporate milestones remain evidence, but they do not override the current product decision.

## Current authority

Canonical machine-readable candidate:

`release/emmake-phase10e-candidate.json`

Last frozen Corporate v4.3 field identity:

- site ID: `emmake-com`;
- production origin: `https://emmake.com/`;
- sandbox origin: `https://emmake.com/nuevaweb/`;
- target Theme release: `0.1.1`;
- release channel: `prestable`;
- frozen Theme-content source commit: `095e2f6471264ef4ba21c3242467a93d8e2b88ac`;
- Theme ZIP SHA-256: `11540b3bea463a5f5cabe4d527f628de2e1d3fe6ad4b0d7cdb11a60ec85f99d2`;
- Migration Bridge version: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic field-pilot pack SHA-256: `d82fa628b96e29bf1f522dd76822a9efd4ce4766b7771069457cb5463478e671`;
- stable decision: `no-go`;
- real-site acceptance: `pending`.

This v4.3 identity remains valid **technical evidence**, but it is no longer the visual architecture target after real-site review. Do not treat repository green status as visual acceptance.

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

Do **not** rerun Reset, regenerate the Home, rehydrate content or replace the Migration Bridge merely because the renderer architecture changes. The semantic draft and SEO/GEO state are already proven.

## v4.x field finding

Corporate v4 established the intended premium/WOW art direction. v4.1 corrected the first composition defects. v4.2 improved reading measure and introduced content-hash CSS versioning. v4.3 then attempted to escape inherited Gutenberg constrained-content widths while preserving the same semantic content.

The real-site v4.3 screenshot demonstrated that this remained insufficient as a reusable product architecture. Different master sections still resolved against different layout contexts and appeared visually misaligned. The page could be patched further with CSS, but doing so would turn the Theme into a growing layer of exceptions around Gutenberg layout behavior.

The field conclusion is therefore architectural:

> **Gutenberg remains an editor, but it is no longer the master layout authority for strategic SEO/GEO Theme surfaces.**

v4.3 is recorded as technically valid but **visual/architectural NO-GO**.

## Corporate v5 — Theme-owned frontend

The next active Corporate checkpoint is **Corporate v5 — Theme-owned frontend**.

Canonical architecture: `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`.

Corporate v5 must preserve the existing content/SEO state while changing who owns rendering:

```text
existing hydrated semantic content
        ↓
corporate-home-v1
        ↓
Corporate Theme renderer
        ↓
server-rendered semantic HTML
        ↓
Theme-owned layout / CSS / responsive behavior
```

The master Home must no longer depend on Gutenberg constructs such as:

- `wp-block-post-content` as the master layout surface;
- `is-layout-constrained` for strategic composition;
- `contentSize` / `wideSize` as the Corporate page-width authority;
- nested Group/Columns blocks as the renderer contract;
- generated Gutenberg layout CSS to determine master section alignment.

WordPress remains the CMS and resource authority. Gutenberg remains available for blog/editorial content and simple client pages.

## Corporate v5 acceptance rule

Corporate v5 must prove all of the following before the other preset master surfaces continue:

- reuse the existing rescued/hydrated content without casually rerunning Reset or migration;
- render the Corporate Home from the existing semantic model/content slots;
- make the Theme the sole strategic layout authority;
- preserve one correct semantic heading hierarchy;
- preserve internal links and SEO/GEO output;
- preserve accessibility and server-side rendering;
- remain within performance budgets;
- remain stable at `1440 / 1024 / 768 / 390`;
- meet the premium/WOW visual gate on the real `/nuevaweb/` sandbox;
- establish a reusable renderer boundary that SEO/GEO Manager can later use for generated landings.

The existing `320` compact-mobile automated check remains an additional stress case.

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

The product-owned clone from `https://emmake.com/` to `https://emmake.com/nuevaweb/`, Rescue Manifest, Reset, content rescue, clean semantic Home creation, hydration and SEO/GEO handoff remain accepted evidence. They do not need to be repeated merely because presentation architecture changes.

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

## Field execution for Corporate v5

The destructive/reset portion must **not** be repeated for this renderer migration unless a real data/state failure is proven.

1. Keep the existing `/nuevaweb/` clone and Step 1–7 evidence.
2. Keep Migration Bridge `0.8.60` unless a separate migration defect requires changing it.
3. Preserve the existing clean hydrated Home data/content.
4. Install only a frozen Corporate v5 Theme candidate when repository-side gates are green.
5. Preview the same logical Home through the new Theme-owned renderer.
6. Verify that all master sections share the intended renderer shell/alignment.
7. Run browser QA at `1440 / 1024 / 768 / 390`.
8. Do not advance Step 8 until the Home receives explicit visual approval.

If the semantic data or Step 7 state has drifted, stop and investigate instead of recreating evidence casually.

## Browser QA acceptance — premium/WOW gate

Record the five field checks:

1. `visual-layout` — immediate visual impact, premium hierarchy, intentional rhythm, section variety, coherent language and decisive CTAs; merely “better” is not sufficient;
2. `responsive-behavior` — no overflow, squeezed desktop layouts, pathological wrapping, narrow vertical text strips or weak mobile recomposition;
3. `accessibility` — correct headings/landmarks, keyboard/focus behavior, meaningful links and sufficient contrast;
4. `seo-geo-rendered-output` — title, description, canonical, robots, Schema/discovery output and important internal links remain correct;
5. `performance` — no material runtime/asset regression and acceptable measured sandbox performance.

A technically valid result that still fails the premium/WOW standard remains a blocker.

## Representative SEO/GEO regression set

At minimum validate Home, Sobre Nosotros, Trabaja con Nosotros, blog index, one representative recent article and Contacto, plus every additional URL class identified by the Rescue Manifest. Review status, canonical, robots/indexability, title/meta, Open Graph, Schema, hreflang when configured, redirects, sitemap membership, organization/contact facts and important links.

## Sandbox acceptance exit

The sandbox becomes eligible for production-entry review only when the exact frozen Corporate v5 artifact is installed, the Theme-owned Home passes the product-quality visual gate, no URL/SEO/GEO blocker remains, no required legacy builder runtime remains, responsive/accessibility/performance checks pass, critical navigation/forms work, browser evidence is complete and rollback remains available.

This is **not** automatic production cutover.

## Stable promotion boundary

Theme stable promotion remains **NO-GO**.

SEO/GEO Manager is a separate product and is not itself a dependency for Theme stable acceptance. However, the Theme renderer architecture must be suitable for the future Manager Landing Engine before the Corporate master is considered product-complete.

Until Corporate v5 and later production acceptance exist:

- `release/stable-release-decision.json` remains `no-go`;
- real-site acceptance remains `pending`;
- the v4.3 candidate record remains technical historical evidence until superseded by a newly frozen v5 candidate;
- no stable release is published.
