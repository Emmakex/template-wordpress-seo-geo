# EMMAKE Home Field Pilot — /nuevaweb/

This runbook is the controlled field handoff for the real reset-first EMMAKE Home rebuild and the Corporate v5 A3.2 acceptance pass.

## Scope

- Target: `https://emmake.com/nuevaweb/`.
- Environment: isolated same-origin sandbox clone.
- Current production Home must remain untouched during the pilot.
- The field pilot ends at browser QA evidence. Replacing the current front page is a separate explicit decision.
- Existing Rescue Manifest, Reset, semantic hydration and SEO/GEO handoff evidence must be reused unless a concrete state defect is proven.

## Package contents

- `seo-geo-theme.zip` — self-contained SEO/GEO Theme 0.1.2 prestable release candidate with Corporate v5 A3.2.
- `seo-geo-migration-bridge.zip` — Migration Bridge 0.8.60.
- `emmake-home.es_ES.json` — reviewed portable Home Content Blueprint retained as an advanced fallback, not the normal migration path.
- `pilot-manifest.json` — exact versions, SHA-256 identities and execution sequence.
- `pilot-evidence-template.json` — field evidence record to complete after machine readiness.
- this runbook.

Verify the outer package SHA-256 and the nested component SHA-256 values from `pilot-manifest.json` before installation.

## Sandbox runtime markers

The `/nuevaweb/` WordPress installation must have these constants in its isolated configuration before Reset & Rebuild is used:

```php
define( 'SEO_GEO_MIGRATION_SANDBOX', true );
define( 'SEO_GEO_MIGRATION_SANDBOX_MODE', 'subdirectory' );
define( 'SEO_GEO_MIGRATION_STORAGE_ISOLATED', true );
define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );
define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );
```

Do not add these markers to the production WordPress configuration.

## Installation for the current A3.2 pass

1. Confirm a recoverable database/files backup for `/nuevaweb/`.
2. Verify that the existing sandbox semantic content and SEO/GEO state are still intact.
3. Install the exact `seo-geo-theme.zip` from this pack; do not change the production site.
4. Keep Migration Bridge `0.8.60` unchanged unless the installed clone genuinely requires recovery of the already-proven migration state.
5. Clear only relevant WordPress/Hostinger/browser caches.
6. Do **not** rerun Reset, recreate the clean Home or rehydrate rescued content merely because Theme presentation changed.

The original reset-first execution sequence below remains the provenance of the pilot and the recovery runbook if a real state failure is discovered.

## Reset & Rebuild execution provenance

### Step 1 — Rescue Manifest

Create or refresh the Rescue Manifest only when the migration state genuinely needs rebuilding.

Expected result:

- public page/post identities captured;
- useful URL/content/SEO/link/media evidence captured;
- no theme/builder layout preservation requirement.

### Step 2 — Reset clone runtime

Apply Clone Reset only as part of an intentional rebuild, never as a normal A3.2 presentation update.

Expected result:

- SEO/GEO Theme active;
- legacy themes/builders/plugins removed unless explicitly retained;
- rescued content and manifest unchanged.

### Step 3 — Corporate preset

Bootstrap the Corporate preset and confirm the organization identity.

Expected result:

- Corporate preset active;
- WordPress locale remains authoritative;
- no automatic production front-page replacement.

### Step 4 — Clean Home draft

Create the clean Home draft when rebuilding from scratch.

Expected result:

- one private draft;
- composition uses Theme-native Corporate structures only;
- source Home remains public and unchanged until explicit cutover.

### Step 5 — Automatic content + native hydration

Use **Recommended: Automatic Home Content → Generate and hydrate Home automatically** only when the semantic Home must genuinely be recreated.

Migration Bridge reads preserved Home/related WordPress pages, removes legacy presentation syntax as text, maps useful authored content into the Corporate semantic model, saves the Content Kit and hydrates the clean Home in a controlled action.

Expected result:

- locale follows the active WordPress/Corporate preset locale;
- model: `corporate-home-v1`;
- useful rescued content mapped into semantic Home slots;
- source Home identity/URLs preserved according to the accepted migration state;
- no Divi/Elementor/legacy layout executed or copied;
- `hero-proof`, `proof` and `case-study` remain disabled until independently verified;
- no fabricated client, metric, result or case-study evidence.

### Step 6 — Native SEO/GEO handoff

Apply the safe native SEO handoff only when rebuilding the migration state.

Expected result:

- safe rescued title/description/indexability move to the accepted SEO authority;
- normal self-canonical remains runtime-owned;
- custom canonical or unresolved provider template creates a review item instead of being copied blindly;
- SEO-ready status is reached only when no blocking review item remains.

### Step 7 — Field-pilot readiness

Machine readiness must report the equivalent of:

`ready_for_browser_qa=true`

Do not continue to browser QA while a real blocker remains.

The machine preflight verifies the accepted sandbox/reset/content/SEO ownership boundaries. It does not replace browser acceptance.

## Corporate v5 A3.2 field checks

Before recording the five formal QA results, confirm:

- the Home is rendered by the Theme-owned Corporate strategic renderer;
- the strategic header/footer are singular and no legacy builder chrome reappears;
- `corporate-home-v1` remains the semantic Home model;
- hero, capability, process, Insights fallback and final CTA imagery can be rendered through the declared Theme strategic-media contract;
- WordPress Featured Images remain authoritative for Insights when present;
- missing optional strategic media falls back safely rather than breaking layout;
- no project page-builder JavaScript or third-party visual dependency becomes required.

## Browser QA evidence

Complete all five checks in `pilot-evidence-template.json`:

1. `visual-layout` — desktop/mobile composition, premium hierarchy, spacing, image balance, CTAs and content order.
2. `responsive-behavior` — no overflow, broken controls, squeezed desktop layout or unusable mobile recomposition.
3. `accessibility` — headings, landmarks, keyboard/focus, contrast, meaningful links and appropriate image alternatives.
4. `seo-geo-rendered-output` — title, description, canonical, robots, Schema/discovery output and important internal links.
5. `performance` — no material asset/runtime regression and acceptable measured performance for the sandbox.

Run the field pass at `1440 / 1024 / 768 / 390`; keep `320` as an additional compact stress case.

Record the machine-readiness report SHA-256 in the evidence file when the current readiness workflow provides one.

## Cutover boundary

Passing machine readiness plus the five browser QA checks makes the rebuilt Home **eligible for production-entry review**.

It does not automatically replace or modify the production Home.

Production cutover requires a separate explicit decision.

Production entry requires a separate explicit decision after the evidence is reviewed, the premium/WOW visual gate is accepted, SEO/GEO regression checks are clear and rollback boundaries remain available.
