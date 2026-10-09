# Phase 10E real-site pilot — emmake.com

This document is the operational source for the first real-site acceptance of the self-contained SEO/GEO Theme. Historical clone, migration and earlier Corporate milestones remain evidence, but they do not override the current candidate record.

## Current authority

Canonical machine-readable candidate:

`release/emmake-phase10e-candidate.json`

Current MF-08 field identity:

- site ID: `emmake-com`;
- production origin: `https://emmake.com/`;
- sandbox origin: `https://emmake.com/nuevaweb/`;
- target Theme release: `0.1.1`;
- release channel: `prestable`;
- frozen Theme implementation source commit: `eb3c523e2302f464ed253b4693831967b7be4c8a`;
- Theme ZIP SHA-256: `c19935f45b7997090cf5fbcae85da584e159be66608820d0030eff3c9f7a92cc`;
- Migration Bridge version: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic field-pilot pack SHA-256: `d444e196dd2addca512ba9cc286bc8e094d2aca0cab52c0adccb48181a365561`;
- stable decision: `no-go`;
- real-site acceptance: `pending`.

The JSON record is the hash authority. The exact frozen artifact must still prove the premium/WOW result on `/nuevaweb/`; repository CI is not visual acceptance.

## Current pilot position

The technical Reset & Rebuild sequence already reached browser-readiness on the real `/nuevaweb/` clone:

- Rescue Manifest completed;
- Clone Reset completed;
- Corporate bootstrap completed;
- clean Home draft created;
- rescued/client content hydrated into native semantic slots;
- external SEO provider detected without becoming Theme authority;
- native SEO/GEO handoff reported SEO-ready;
- machine preflight reported `ready_for_browser_qa=true`.

Do **not** rerun Reset, regenerate the Home, rehydrate content or replace the Migration Bridge merely because the Theme renderer/presentation changes. The semantic draft and SEO/GEO state are already proven.

Corporate v4.3 remains historical evidence: technically valid but **visual/architectural NO-GO**.

## Corporate field evolution

Corporate v5 — Theme-owned frontend.

Corporate v5 moved strategic master rendering out of Gutenberg layout authority and into a Theme-owned semantic server renderer.

- **A1** proved the renderer/model boundary.
- **A2** established Theme-owned strategic header/footer/chrome.
- **A3** refined the real-site palette and separation.
- **A3.1** closed the bounded hero/media/method/Insights visual baseline while preserving the eight-request / zero-project-JS contract.
- **MF-08** is the current field candidate. It adds optional real-media support and the mobile fixes found in real iPhone QA without changing rescued content, URLs, SEO/GEO authority, Reset, hydration or Migration Bridge state.

A1/A2/A3/A3.1 and Corporate v4.3 remain historical evidence, not current install authority.

## MF-08 field scope

The current candidate adds an optional client media-atlas layer while the generic product remains asset-neutral. Authored WordPress featured images still take precedence where applicable.

Mobile field corrections cover:

- explicit and reliable menu toggle/X behavior;
- close on outside tap, menu link and Escape;
- focus restoration;
- reduced trigger/panel spacing while retaining the 44 px touch target;
- centered `Contacto` CTA;
- protected clearance so capability media cannot overlap headings at compact widths;
- no extra external project JavaScript request.

The Theme remains responsible for strategic frontend HTML, CSS, responsive behavior, accessibility and performance.

## Theme-owned frontend boundary

Canonical architecture: `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`.

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

The master Home does not depend on `wp-block-post-content`, `is-layout-constrained`, Gutenberg `contentSize` / `wideSize`, nested layout blocks or database-overridable template parts as strategic layout authority.

WordPress remains the CMS/resource authority. Gutenberg remains available for blog/editorial content and simple client-managed pages.

## Repository acceptance boundary

The candidate must remain green for Foundation, Design System, Corporate Page Pipeline, Phase 1 Package, Release Artifact, PHP Quality/PHPStan, Native Multilingual, Self-contained Theme, WordPress Smoke, Accessibility/Responsive, Performance Baseline and EMMAKE Field Pilot Pack CI.

The deterministic Theme package for this pilot is `c19935f45b7997090cf5fbcae85da584e159be66608820d0030eff3c9f7a92cc` and the deterministic outer pilot pack is `d444e196dd2addca512ba9cc286bc8e094d2aca0cab52c0adccb48181a365561`.

Automated results do **not** replace the real `/nuevaweb/` visual/product gate.

## Relationship with SEO/GEO Manager

SEO/GEO Manager is a separate product and is not a dependency for Theme stable runtime. The renderer boundary is intentionally compatible with Manager: Manager may create or update structured strategic models and normal WordPress posts, while Theme remains the public frontend renderer and SEO/GEO output ownership stays singular and explicit.

## Sandbox safety boundary

All field work remains isolated to `/nuevaweb/` until explicit acceptance. Production remains untouched.

The sandbox safety constants remain required:

```php
define( 'SEO_GEO_MIGRATION_SANDBOX', true );
define( 'SEO_GEO_MIGRATION_SANDBOX_MODE', 'subdirectory' );
define( 'SEO_GEO_MIGRATION_STORAGE_ISOLATED', true );
define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );
define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );
```

Search visibility, canonical/hreflang/sitemap authority, production `home`/`siteurl` and rollback boundaries must remain isolated from production. No credentials, dumps, private submissions or customer data enter the repository.

## Field execution for MF-08

The destructive/reset portion must **not** be repeated unless a real data/state failure is proven.

1. Keep the existing `/nuevaweb/` clone and prior Step 1–7 evidence.
2. Keep Migration Bridge `0.8.60` unchanged.
3. Preserve the existing clean hydrated Home data/content.
4. Verify `release/emmake-phase10e-candidate.json` is the active candidate record.
5. Verify the Theme ZIP SHA-256 is exactly `c19935f45b7997090cf5fbcae85da584e159be66608820d0030eff3c9f7a92cc` before installation.
6. If using the prepared outer pack, verify SHA-256 `d444e196dd2addca512ba9cc286bc8e094d2aca0cab52c0adccb48181a365561`.
7. Replace the Theme with that frozen MF-08 candidate **only** on `/nuevaweb/`.
8. Add the client media atlas only in the field package/site layer; do not commit it into generic Theme source.
9. Clear only relevant WordPress/Hostinger/browser caches; do not reset or rehydrate.
10. Open the same hydrated Home through the Theme-owned renderer.
11. Inspect desktop first, then `1024 / 768 / 390`; use `320` as an additional compact-mobile stress case.
12. Do not advance the roadmap stage until the Home receives explicit visual approval.

If semantic data or readiness state has drifted, stop and investigate instead of recreating evidence casually.

## Browser QA acceptance — premium/WOW gate

Record these field checks:

1. `visual-layout` — premium hierarchy, intentional rhythm, section variety and decisive CTAs;
2. `responsive-behavior` — no overflow, squeezed layouts, pathological wrapping or weak mobile recomposition;
3. `accessibility` — headings/landmarks, keyboard/focus behavior, meaningful links and sufficient contrast;
4. `seo-geo-rendered-output` — title, description, canonical, robots, Schema/discovery output and important internal links remain correct;
5. `performance` — no material runtime/asset regression and acceptable measured sandbox performance.

Additionally verify on the real compact mobile surface:

- menu opens and closes through X/toggle;
- tapping outside closes it;
- selecting a menu link closes it;
- Escape closes it and restores focus;
- trigger retains at least a 44 px touch target;
- `Contacto` is centered;
- capability 02/03 media never overlaps their headings;
- no external `preset-navigation.js` request appears.

A technically valid result that still fails the premium/WOW standard remains a blocker.

## Representative SEO/GEO regression set

At minimum validate Home, Sobre Nosotros, Trabaja con Nosotros, blog index, one representative recent article and Contacto, plus every additional URL class identified by the Rescue Manifest. Review status, canonical, robots/indexability, title/meta, Open Graph, Schema, hreflang when configured, redirects, sitemap membership, organization/contact facts and important links.

## Sandbox acceptance exit

The sandbox becomes eligible for production-entry review only when the exact MF-08 artifact is installed, the Theme-owned Home passes product-quality visual QA, no URL/SEO/GEO blocker remains, responsive/accessibility/performance checks pass, critical navigation/forms work, evidence is complete and rollback remains available.

This is **not** automatic production cutover.

## Stable promotion boundary

Theme stable promotion remains **NO-GO**.

Until real sandbox and later production acceptance exist:

- `release/stable-release-decision.json` remains `no-go`;
- real-site acceptance remains `pending`;
- MF-08 is the frozen technical field authority;
- earlier Corporate candidates remain historical evidence;
- no stable release is published.
