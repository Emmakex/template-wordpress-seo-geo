# Phase 10E real-site pilot — emmake.com

This document is the current operational source for the first real-site acceptance of the self-contained SEO/GEO Theme.

Historical clone, migration and earlier Corporate milestones are preserved below for context. They do not override the frozen current candidate.

## Current authority

The canonical machine-readable candidate is:

`release/emmake-phase10e-candidate.json`

Current Corporate v3 field identity:

- site ID: `emmake-com`;
- production origin: `https://emmake.com/`;
- sandbox origin: `https://emmake.com/nuevaweb/`;
- target Theme release: `0.1.1`;
- release channel: `prestable`;
- frozen source commit: `8d983183be02b38dece4b2b68c447c68c107d98d`;
- Theme ZIP SHA-256: `376a2775dc243e2fbfb38b88a2d0b0d19b63a392bef6f5ab30a4938b98b51ebe`;
- Migration Bridge version: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic field-pilot pack SHA-256: `790d560db976caa19365c6d6170c9ce235b68341fbb707d3994b40b16231ce24`;
- stable decision: `no-go`;
- real-site acceptance: `pending`.

Do not substitute an older Theme, older Migration Bridge or an earlier pilot ZIP for this candidate. If any packaged component changes, freeze and validate a new candidate first.

## Current pilot position

The technical Reset & Rebuild sequence has already reached Step 7 on the real `/nuevaweb/` clone:

- Rescue Manifest completed;
- Clone Reset completed;
- Corporate bootstrap completed;
- clean Home draft created;
- rescued/client content hydrated into native semantic slots;
- Yoast detected as external provider without becoming Theme authority;
- native SEO/GEO handoff reported SEO-ready;
- machine preflight reported `ready_for_browser_qa=true`.

The first browser QA then rejected the old Corporate visual presentation. This is now an explicit field finding, not something CI is allowed to hide.

The rejected presentation showed:

- generic equal-card composition;
- long service headings breaking poorly at compact/tablet widths;
- weak contrast in the dark method section;
- cramped Insights cards;
- an overall visual result below the product target of a modern custom-designed site.

Those defects belong to the reusable Corporate preset. No EMMAKE-only CSS patch is permitted. Corporate v3 replaces the reusable presentation layer while preserving the already proven semantic hydration and SEO/GEO state.

## Corporate v3 master-preset rule

Corporate v3 is the only active visual preset until this pilot approves it.

SaaS, Local Pro, Publisher and Ecommerce visual iteration is frozen. They may inherit approved technical primitives only after Corporate v3 passes its real browser gate.

The Home must be accepted at these product widths:

- `1440` desktop;
- `1024` compact desktop/tablet landscape;
- `768` tablet;
- `390` mobile.

The existing `320` compact-mobile automated check remains as an extra stress case.

Do not advance Step 8, inner-page rollout, production cutover or stable promotion before the Corporate Home itself is approved.

## What is already proven

### Production baseline and dependency review

The production baseline and UNKNOWN dependency review were completed in September 2026. This established the useful content/URL/SEO/link/media inventory and reviewed the dependency boundary without authorizing production mutation.

### 2026-10-03 product-owned clone milestone

Migration Bridge successfully transported and activated a real clone from `https://emmake.com/` to `https://emmake.com/nuevaweb/` using persistent multipart packaging and destination URL rewrite.

Bounded evidence remains in:

`release/emmake-clone-field-milestone-20261003.json`

This proves clone transport and activation only. It does not prove acceptance of the current Theme candidate.

### 2026-10-03 Divi-to-native milestone

Eleven resources containing Divi content were inventoried and converted to native WordPress/Theme-owned content with rollback paths. The Contact page was also functionally verified, including successful mail delivery.

Bounded evidence remains in:

`release/emmake-divi-migration-field-milestone-20261003.json`

This proves migration capability, not final replatform acceptance.

### 2026-10-04 Corporate preset milestone

The reusable Corporate preset was applied successfully on `/nuevaweb/`, with WordPress locale authority preserved and Yoast treated as an advisory external SEO provider.

Bounded evidence remains in:

`release/emmake-corporate-preset-application-20261004.json`

This historical application and the later successful Reset & Rebuild technical pass do not close Phase 10E because browser visual acceptance is still pending.

## Replatforming rule

This is not a visual-parity migration.

Preserve only what is valuable and authoritative:

- authored content;
- URLs and redirects;
- SEO signals and metadata that remain valid;
- internal/external links;
- useful media;
- verified organization/contact facts;
- required business behavior.

Replace legacy presentation debt:

- Divi/legacy layout;
- old theme composition;
- obsolete CSS/widgets;
- decorative builder structure;
- plugins whose only purpose was the previous presentation layer.

The target is a fresh Corporate site built from reusable SEO/GEO Theme primitives and approved as a real product, not merely a technically valid WordPress render.

## Sandbox safety boundary

The candidate is installed and executed only on the isolated `/nuevaweb/` clone until acceptance is complete.

Before destructive Reset & Rebuild actions, the sandbox must have:

```php
define( 'SEO_GEO_MIGRATION_SANDBOX', true );
define( 'SEO_GEO_MIGRATION_SANDBOX_MODE', 'subdirectory' );
define( 'SEO_GEO_MIGRATION_STORAGE_ISOLATED', true );
define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );
define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );
```

Also require:

- independent mutable database/table state from production;
- independent uploads/plugins/themes copies;
- recoverable database/files backups;
- WordPress search visibility disabled on the sandbox;
- no sandbox canonical/hreflang/sitemap target exposed as production authority;
- production `home` and `siteurl` unchanged;
- a known rollback path.

No credentials, dumps, private form submissions, arbitrary option payloads or customer data enter the repository.

## Field execution for Corporate v3

The destructive/reset portion does **not** need to be repeated merely to test the new presentation layer because the existing Home draft is already hydrated into the stable semantic content slots.

For the Corporate v3 visual candidate:

1. verify the new Theme ZIP SHA-256;
2. update the Theme only on `/nuevaweb/`;
3. keep Migration Bridge `0.8.60` active and preserve the existing Step 1–7 state;
4. clear relevant WordPress/host/browser caches;
5. preview the already hydrated clean Home draft;
6. re-run browser QA at `1440 / 1024 / 768 / 390`;
7. do not advance Step 8 until the Home receives explicit visual approval.

If the semantic draft or Step 7 state has drifted, stop and investigate rather than recreating evidence casually.

## Browser QA acceptance

Record all five browser checks from the field evidence template:

1. `visual-layout` — the result must feel like a modern custom-designed Corporate site, with clear hierarchy, intentional rhythm, strong hero, purposeful section variety and coherent CTAs;
2. `responsive-behavior` — no overflow, broken controls, squeezed desktop layouts, pathological word wrapping or unusable mobile composition at the four master widths;
3. `accessibility` — headings, landmarks, keyboard/focus behavior, meaningful links and sufficient contrast, especially on dark/full-bleed sections;
4. `seo-geo-rendered-output` — title, description, canonical, robots, Schema/discovery output and internal links remain correct after the visual replacement;
5. `performance` — no material asset/runtime regression and acceptable measured sandbox performance.

Any blocker keeps the pilot in `pending` state.

## Representative SEO/GEO regression set

At minimum compare the preserved/current behavior for:

- Home;
- Sobre Nosotros;
- Trabaja con Nosotros;
- blog index;
- one representative recent article;
- Contacto;
- every additional URL class discovered by the Rescue Manifest.

For each representative URL validate:

- HTTP status;
- canonical;
- robots/indexability;
- title/meta description;
- Open Graph;
- Schema graph;
- hreflang/x-default when configured;
- redirects;
- sitemap membership;
- visible organization/contact facts;
- important internal links.

Intentional differences must be explicitly reviewed rather than silently accepted.

## Sandbox acceptance exit

The sandbox becomes eligible for production-entry review only when:

- the exact frozen Corporate v3 artifacts are installed;
- Corporate Home passes the explicit product-quality visual gate;
- no unexplained URL loss remains;
- no required legacy builder runtime remains;
- no unresolved SEO/GEO review item remains;
- onboarding/apply behavior is idempotent;
- accessibility/responsive checks pass;
- performance budgets remain acceptable;
- no project PHP fatal/warning/notice remains;
- critical navigation/form behavior works;
- browser QA evidence is complete;
- rollback remains available.

This is still not automatic production cutover.

## Production entry and verification

Production is changed only after explicit sandbox acceptance.

Immediately after controlled cutover execute `docs/PRODUCTION_VERIFICATION.md` and cover:

- runtime identity;
- SEO/GEO output;
- redirects and indexability;
- navigation/forms and critical functionality;
- accessibility;
- performance;
- relevant logs.

Material canonical/indexability/sitemap/hreflang/redirect regression, a broken critical form, private-content exposure, fatal/5xx response or artifact identity mismatch triggers rollback/recovery according to `docs/ROLLBACK_RECOVERY.md`.

## Stable promotion boundary

The stable gate does not wait for SEO/GEO Manager. Manager is a separate later product roadmap.

Theme `0.1.1` may move from `prestable` to `stable` only after Corporate v3 real-site browser acceptance and later controlled production acceptance exist with bounded evidence.

Until then:

- `release/stable-release-decision.json` remains `no-go`;
- real-site acceptance remains `pending`;
- `release/emmake-phase10e-candidate.json` remains the frozen candidate identity;
- no stable release is published.

After accepted production verification, update the bounded evidence reference, candidate acceptance state, stable decision, release channel and changelog, then rerun all required gates before publishing stable.
