# Phase 10E real-site pilot — emmake.com

This document is the current operational source for the first real-site acceptance of the self-contained SEO/GEO Theme.

Historical clone and migration milestones are preserved below for context, but they are not allowed to override the frozen current candidate.

## Current authority

The canonical machine-readable candidate is:

`release/emmake-phase10e-candidate.json`

Current field identity:

- site ID: `emmake-com`;
- production origin: `https://emmake.com/`;
- sandbox origin: `https://emmake.com/nuevaweb/`;
- target Theme release: `0.1.1`;
- release channel: `prestable`;
- frozen source commit: `d3f4022ff2f46c2ee965bae561217b990bf2edf0`;
- Theme ZIP SHA-256: `313796fde03e0204a334d204098222e5d0521b9efeb2ec622fbd92c0522b8c15`;
- Migration Bridge version: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic field-pilot pack SHA-256: `6a84f39d2a5944eec57d60b74f09171a1e40303f4a53732bd922c0b5b4b01999`;
- stable decision: `no-go`;
- real-site acceptance: `pending`.

Do not substitute an older Theme, older Migration Bridge or an earlier pilot ZIP for this candidate. If any packaged component changes, freeze and validate a new candidate first.

## What is already proven

The pilot already has useful historical field evidence:

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

This proves a migration capability, not final replatform acceptance.

### 2026-10-04 Corporate preset milestone

The reusable Corporate preset was applied successfully on `/nuevaweb/`, with WordPress locale authority preserved and Yoast treated as an advisory external SEO provider.

Bounded evidence remains in:

`release/emmake-corporate-preset-application-20261004.json`

This historical application occurred before the current frozen `0.1.1` five-preset candidate and therefore does not close Phase 10E.

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

The target is a fresh Corporate site built from reusable SEO/GEO Theme primitives.

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

## Field execution

Use the deterministic pack identified above and follow `docs/EMMAKE_HOME_FIELD_PILOT.md`.

The accepted order is:

1. verify outer pack and nested component SHA-256 values;
2. confirm `/nuevaweb/` backup and sandbox guards;
3. install Theme `0.1.1` prestable candidate;
4. install and activate Migration Bridge `0.8.60`;
5. create or refresh the Rescue Manifest;
6. apply Clone Reset, retaining only genuine business dependencies;
7. bootstrap Corporate;
8. create the clean private Home draft;
9. generate and hydrate native Home content from rescued/client material;
10. apply native SEO/GEO handoff;
11. run field-pilot readiness;
12. continue only when Step 7 reports `ready_for_browser_qa=true`.

The source Home and production front-page assignment remain unchanged during this flow.

## Browser QA acceptance

After Step 7 is ready, record all five browser checks from the field evidence template:

1. `visual-layout` — hierarchy, spacing, CTAs and content order on representative desktop/mobile views;
2. `responsive-behavior` — no overflow, broken controls or unusable mobile composition;
3. `accessibility` — headings, landmarks, keyboard/focus behavior, contrast and meaningful links;
4. `seo-geo-rendered-output` — title, description, canonical, robots, Schema/discovery output and internal links;
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

- the exact frozen artifacts are installed;
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

Theme `0.1.1` may move from `prestable` to `stable` only after real production acceptance exists and a bounded reference is recorded.

Until then:

- `release/stable-release-decision.json` remains `no-go`;
- real-site acceptance remains `pending`;
- `release/emmake-phase10e-candidate.json` remains the frozen candidate identity;
- no stable release is published.

After accepted production verification, update the bounded evidence reference, candidate acceptance state, stable decision, release channel and changelog, then rerun all required gates before publishing stable.
