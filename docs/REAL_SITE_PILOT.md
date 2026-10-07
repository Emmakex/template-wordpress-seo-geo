# Phase 10E real-site pilot — emmake.com

This document is the current operational source for the first real-site acceptance of the self-contained SEO/GEO Theme.

Historical clone, migration and earlier Corporate milestones are preserved for context. They do not override the frozen current candidate.

## Current authority

The canonical machine-readable candidate is:

`release/emmake-phase10e-candidate.json`

Current Corporate v4.1 field identity:

- site ID: `emmake-com`;
- production origin: `https://emmake.com/`;
- sandbox origin: `https://emmake.com/nuevaweb/`;
- target Theme release: `0.1.1`;
- release channel: `prestable`;
- frozen source commit: `efdb0456ac684c95f1f320b4e76c533ba9f71efd`;
- Theme ZIP SHA-256: `01a558d9de8d8014ad3568d0661785741d784a3182db9280d5902889cff0c82f`;
- Migration Bridge version: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic field-pilot pack SHA-256: `c95c1fb142f858d949ccb72079bd7d423afca7a79eca6db2053bb4f74652066d`;
- stable decision: `no-go`;
- real-site acceptance: `pending`.

Do not substitute an older Theme, older Migration Bridge or earlier pilot ZIP for this candidate. Any packaged-content change requires a newly frozen and validated candidate before field use.

The source commit identifies the exact Theme-content state. Later candidate-record/documentation-only commits do not redefine the packaged Theme bytes.

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

Earlier visual candidates were not accepted. Corporate v4 finally established the intended premium/WOW art direction, but the first real v4 screenshot still exposed reusable composition defects: the hero headline became a word-by-word waterfall, secondary Services and Insights content was squeezed into columns that were too narrow, the method decoration competed with the steps, the closing CTA decoration outweighed its message, and the private-draft preview exposed the template-owned page title above the Home.

Those are Theme-level defects, not EMMAKE-only exceptions. Corporate v4.1 keeps the v4 art direction and fixes proportions, reading measures, section rhythm and preview behavior while preserving the proven semantic hydration and SEO/GEO state.

## Corporate v4.1 master-preset rule

Corporate v4.1 is the only active visual preset until this pilot approves it.

SaaS, Local Pro, Publisher and Ecommerce visual iteration is frozen. They may inherit approved technical primitives only after Corporate v4.1 passes its real browser gate.

The Home must be accepted at these product widths:

- `1440` desktop;
- `1024` compact desktop/tablet landscape;
- `768` tablet;
- `390` mobile.

The existing `320` compact-mobile automated check remains as an additional stress case.

Do not advance Step 8, inner-page rollout, production cutover or stable promotion before the Corporate Home itself is approved.

## Repository-side readiness of the frozen v4.1 candidate

The exact Theme-content source completed the technical checks needed to return to `/nuevaweb/`:

- WordPress 7.1 / PHP 8.2 activation smoke;
- self-contained Theme and upgrade/rollback contracts;
- Foundation, Design System and Corporate page-pipeline contracts;
- multilingual and other-preset regression checks;
- accessibility/responsive browser automation at the product widths;
- deterministic release packaging;
- Lighthouse performance budgets without raising those budgets;
- a dedicated v4.1 field-composition contract covering hero reading measure, balanced capability/Insights columns, content-first method section, preview-H1 handling and closing CTA proportions.

The Corporate v4.1 Lighthouse median records performance `100`, FCP/LCP `903.18 ms`, CLS `0`, TBT `0`, zero third-party requests, zero project JavaScript bytes and `7551` CSS bytes against an `8192` byte budget. The static gzip proxy is `6559` bytes against a `7100` byte internal ceiling.

The deterministic pack is rebuilt using fixed timestamps, ordering, file permissions and compression, and must reproduce the canonical SHA-256 listed above before field use.

These checks prove technical field readiness only. They do not satisfy the required human/browser visual acceptance.

## What is already proven

### Production baseline and dependency review

The production baseline and UNKNOWN dependency review established the useful content/URL/SEO/link/media inventory and reviewed the dependency boundary without authorizing production mutation.

### Product-owned clone milestone

Migration Bridge successfully transported and activated a real clone from `https://emmake.com/` to `https://emmake.com/nuevaweb/` using persistent multipart packaging and destination URL rewrite.

Bounded evidence remains in:

`release/emmake-clone-field-milestone-20261003.json`

This proves clone transport and activation only. It does not prove acceptance of the current Theme candidate.

### Divi-to-native milestone

Resources containing Divi content were inventoried and converted to native WordPress/Theme-owned content with rollback paths. Contact behavior was functionally verified during that milestone.

Bounded evidence remains in:

`release/emmake-divi-migration-field-milestone-20261003.json`

This proves migration capability, not final replatform acceptance.

### Earlier Corporate preset milestone

The reusable Corporate preset was applied successfully on `/nuevaweb/`, with WordPress locale authority preserved and Yoast treated as an advisory external SEO provider.

Bounded evidence remains in:

`release/emmake-corporate-preset-application-20261004.json`

That historical application and the successful Reset & Rebuild technical pass do not close Phase 10E because Corporate v4.1 browser visual acceptance remains pending.

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

Also require independent mutable state from production, recoverable database/files backups, search visibility disabled on the sandbox, no sandbox canonical/hreflang/sitemap authority exposed as production, production `home` and `siteurl` unchanged, and a known rollback path.

No credentials, dumps, private form submissions, arbitrary option payloads or customer data enter the repository.

## Field execution for Corporate v4.1

The destructive/reset portion does **not** need to be repeated because the existing Home draft is already hydrated into stable semantic content slots and Step 7 is already technically ready for browser QA.

For the frozen Corporate v4.1 candidate:

1. verify the Theme ZIP SHA-256 is `01a558d9de8d8014ad3568d0661785741d784a3182db9280d5902889cff0c82f`;
2. update the Theme only on `/nuevaweb/`;
3. keep Migration Bridge `0.8.60` active and preserve the existing Step 1–7 state;
4. clear relevant WordPress/host/browser caches;
5. preview the already hydrated clean Home draft;
6. confirm the preview-only page-title strip no longer consumes visible layout space;
7. re-run browser QA at `1440 / 1024 / 768 / 390`;
8. do not advance Step 8 until the Home receives explicit visual approval.

If the semantic draft or Step 7 state has drifted, stop and investigate rather than recreating evidence casually.

## Browser QA acceptance — premium/WOW gate

Record all five browser checks from the field evidence template:

1. `visual-layout` — the result must feel like a modern custom-designed Corporate site from a top-tier agency: immediate visual impact, premium hierarchy, intentional rhythm, section variety, coherent visual language and decisive CTAs; “better” or merely technically correct is not sufficient;
2. `responsive-behavior` — no overflow, broken controls, squeezed desktop layouts, pathological word wrapping or weak mobile recomposition at the four master widths;
3. `accessibility` — headings, landmarks, keyboard/focus behavior, meaningful links and sufficient contrast, especially on dark/full-bleed sections;
4. `seo-geo-rendered-output` — title, description, canonical, robots, Schema/discovery output and internal links remain correct after the visual replacement;
5. `performance` — no material asset/runtime regression and acceptable measured sandbox performance.

Any blocker keeps the pilot in `pending` state. A technically valid result that does not create the intended premium/WOW impression is also a blocker because Corporate v4.1 is the master product reference for every later preset.

## Representative SEO/GEO regression set

At minimum compare preserved/current behavior for Home, Sobre Nosotros, Trabaja con Nosotros, blog index, one representative recent article, Contacto and every additional URL class discovered by the Rescue Manifest.

For each representative URL validate HTTP status, canonical, robots/indexability, title/meta description, Open Graph, Schema graph, hreflang/x-default when configured, redirects, sitemap membership, visible organization/contact facts and important internal links.

Intentional differences must be explicitly reviewed rather than silently accepted.

## Sandbox acceptance exit

The sandbox becomes eligible for production-entry review only when:

- the exact frozen Corporate v4.1 artifacts are installed;
- Corporate Home passes the explicit product-quality visual/WOW gate;
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

Immediately after any later controlled cutover execute `docs/PRODUCTION_VERIFICATION.md` and cover runtime identity, SEO/GEO output, redirects/indexability, navigation/forms, critical functionality, accessibility, performance and relevant logs.

Material canonical/indexability/sitemap/hreflang/redirect regression, a broken critical form, private-content exposure, fatal/5xx response or artifact identity mismatch triggers rollback/recovery according to `docs/ROLLBACK_RECOVERY.md`.

## Stable promotion boundary

The stable gate does not wait for SEO/GEO Manager. Manager is a separate later product roadmap.

Theme `0.1.1` may move from `prestable` to `stable` only after real production acceptance exists. Corporate v4.1 real-site browser acceptance is a required prerequisite before that production acceptance can even be considered.

Until then:

- `release/stable-release-decision.json` remains `no-go`;
- real-site acceptance remains `pending`;
- `release/emmake-phase10e-candidate.json` remains the frozen candidate identity;
- no stable release is published.

After accepted production verification, update the bounded evidence reference, candidate acceptance state, stable decision, release channel and changelog, then rerun all required gates before publishing stable.
