# Phase 10E real-site pilot — emmake.com

This document is the operational source for the first real-site acceptance of the self-contained SEO/GEO Theme. Historical clone, migration and earlier Corporate milestones remain evidence, but they do not override the frozen current candidate.

## Current authority

Canonical machine-readable candidate:

`release/emmake-phase10e-candidate.json`

Current Corporate v4.3 field identity:

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

Do not substitute an older Theme, Migration Bridge or pilot ZIP for this candidate. A Theme-content change requires a new deterministic Theme SHA and a newly frozen candidate.

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

Do **not** rerun Reset, regenerate the Home, rehydrate content or replace the Migration Bridge just to test v4.3. The semantic draft and SEO/GEO state are already proven; this iteration is Theme presentation only.

## Why Corporate v4.3 is required

Corporate v4 established the intended premium/WOW art direction. v4.1 fixed its first field composition defects. v4.2 improved horizontal reading measure and changed Theme CSS versioning from file timestamps to SHA-256 content hashes, preventing deterministic ZIP timestamps from keeping stale CSS in browser/CDN caches.

The real screenshot after installing that cache-safe v4.2 candidate proved the new CSS was active, but exposed a deeper reusable WordPress layout issue: the master surfaces still inherited Gutenberg constrained-content widths. As a result:

- the hero still wrapped across too many lines;
- Services occupied only a narrow central band instead of the intended master shell;
- Process text remained in narrow vertical strips;
- Insights remained visually compressed despite the wider v4.2 typography rules.

Corporate v4.3 keeps the v4 art direction but makes the Theme own its horizontal composition instead of inheriting Gutenberg's generic constrained width.

## Corporate v4.3 master-preset rule

Corporate v4.3 is the only active visual preset until this pilot approves it. SaaS, Local Pro, Publisher and Ecommerce visual iteration remains frozen.

The v4.3 field pass explicitly:

- expands the hero grid to the Corporate master shell;
- widens headline measure while moderating maximum display size;
- forces native Corporate sections to escape inherited `content-size` constraints;
- widens Services and its text-heavy cards;
- gives Process three readable desktop columns with broader text measure;
- lets Insights and the underlying Query/Post Template use the available shell;
- preserves the existing stacked mobile composition at `820px` and below;
- keeps content-hash asset versioning so the new CSS gets a distinct URL automatically.

The Home must be accepted at `1440`, `1024`, `768` and `390` widths. The existing `320` compact-mobile automated check remains an additional stress case.

Do not advance Step 8, inner-page rollout, production cutover or stable promotion before the Corporate Home itself is approved.

## Repository-side technical gate

Before field use, the frozen v4.3 Theme must retain:

- WordPress 7.1 / PHP 8.2 activation and upgrade/rollback acceptance;
- self-contained Theme and deterministic release integrity;
- Foundation, Design System and Corporate page-pipeline contracts;
- multilingual and preset regression checks;
- accessibility/responsive browser automation;
- performance budgets without relaxing them;
- content-hash asset versioning;
- the v4.3 Gutenberg width-escape contract;
- Corporate CSS gzip proxy below the existing `7100` byte internal safety ceiling.

Repository success proves technical readiness only. It does not satisfy real visual acceptance.

## Existing milestones remain valid

The product-owned clone from `https://emmake.com/` to `https://emmake.com/nuevaweb/`, Divi-to-native conversion, Corporate preset application and Reset & Rebuild technical sequence have already been proven and recorded. They do not need to be repeated for each Theme visual candidate.

The replatforming rule remains: preserve authored content, URLs/redirects, valid SEO signals, important links, useful media, verified organization/contact facts and required business behavior; replace legacy theme/builder presentation debt rather than copying it into the new site.

## Sandbox safety boundary

Corporate v4.3 is installed only on `/nuevaweb/` until acceptance. Production remains untouched.

The sandbox safety constants already used by this pilot remain required:

```php
define( 'SEO_GEO_MIGRATION_SANDBOX', true );
define( 'SEO_GEO_MIGRATION_SANDBOX_MODE', 'subdirectory' );
define( 'SEO_GEO_MIGRATION_STORAGE_ISOLATED', true );
define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );
define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );
```

Search visibility, canonical/hreflang/sitemap authority, production `home`/`siteurl` and rollback boundaries must remain isolated from production. No credentials, dumps, private submissions or customer data enter the repository.

## Field execution for Corporate v4.3

The destructive/reset portion must **not** be repeated for this visual iteration.

1. Verify Theme ZIP SHA-256 `11540b3bea463a5f5cabe4d527f628de2e1d3fe6ad4b0d7cdb11a60ec85f99d2`.
2. Replace only the Theme on `/nuevaweb/`.
3. Keep Migration Bridge `0.8.60` active.
4. Keep the existing clean hydrated Home draft and Step 1–7 state untouched.
5. Preview the same Home draft; content-hash CSS versioning should invalidate the previous stylesheet URL automatically.
6. Check that the hero, Services, Process and Insights now consume the intended horizontal master shell rather than narrow Gutenberg content columns.
7. Run browser QA at `1440 / 1024 / 768 / 390`.
8. Do not advance Step 8 until the Home receives explicit visual approval.

If the semantic draft or Step 7 state has drifted, stop and investigate instead of recreating evidence casually.

## Browser QA acceptance — premium/WOW gate

Record the five field checks:

1. `visual-layout` — immediate visual impact, premium hierarchy, intentional rhythm, section variety, coherent language and decisive CTAs; merely “better” is not sufficient;
2. `responsive-behavior` — no overflow, squeezed desktop layouts, pathological wrapping, narrow vertical text strips or weak mobile recomposition;
3. `accessibility` — correct headings/landmarks, keyboard/focus behavior, meaningful links and sufficient contrast;
4. `seo-geo-rendered-output` — title, description, canonical, robots, Schema/discovery output and important internal links remain correct;
5. `performance` — no material runtime/asset regression and acceptable measured sandbox performance.

A technically valid result that still fails the intended premium/WOW standard remains a blocker. Corporate v4.3 is the product master reference, not merely a patch for EMMAKE.

## Representative SEO/GEO regression set

At minimum validate Home, Sobre Nosotros, Trabaja con Nosotros, blog index, one representative recent article and Contacto, plus every additional URL class identified by the Rescue Manifest. Review status, canonical, robots/indexability, title/meta, Open Graph, Schema, hreflang when configured, redirects, sitemap membership, organization/contact facts and important links.

## Sandbox acceptance exit

The sandbox becomes eligible for production-entry review only when the exact frozen Corporate v4.3 artifact is installed, the Home passes the product-quality visual gate, no URL/SEO/GEO blocker remains, no required legacy builder runtime remains, responsive/accessibility/performance checks pass, critical navigation/forms work, browser evidence is complete and rollback remains available.

This is **not** automatic production cutover.

## Stable promotion boundary

The stable gate does not wait for SEO/GEO Manager.

Manager is a separate later product roadmap.

Theme `0.1.1` may move from `prestable` to `stable` only after real production acceptance exists.

Corporate v4.3 real-site browser acceptance is a required prerequisite before that later production acceptance can be considered.

Until then:

- `release/stable-release-decision.json` remains `no-go`;
- real-site acceptance remains `pending`;
- `release/emmake-phase10e-candidate.json` remains the frozen identity;
- no stable release is published.
