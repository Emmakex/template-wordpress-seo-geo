# EMMAKE Home Field Pilot — /nuevaweb/

This runbook is the controlled field handoff for the first real reset-first Home rebuild.

## Scope

- Target: `https://emmake.com/nuevaweb/`.
- Environment: isolated same-origin sandbox clone.
- Current production Home must remain untouched during the pilot.
- The field pilot ends at browser QA evidence. Replacing the current front page is a separate explicit decision.

## Package contents

- `seo-geo-theme.zip` — self-contained SEO/GEO Theme release candidate.
- `seo-geo-migration-bridge.zip` — Migration Bridge 0.8.59.
- `emmake-home.es_ES.json` — reviewed portable Home Content Blueprint retained as an advanced fallback, not the normal migration path.
- `pilot-manifest.json` — exact versions, SHA-256 identities and execution sequence.
- `pilot-evidence-template.json` — field evidence record to complete after Step 7.
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

## Installation

1. Confirm a recoverable database/files backup for `/nuevaweb/`.
2. Install `seo-geo-theme.zip`; do not change the production site.
3. Install and activate `seo-geo-migration-bridge.zip` on the sandbox clone.
4. Open **Tools → SEO/GEO Reset & Rebuild**.
5. Confirm the screen identifies the clone as a sandbox before any destructive reset action.

## Reset & Rebuild execution

### Step 1 — Rescue Manifest

Create or refresh the Rescue Manifest.

Expected result:

- public page/post identities captured;
- useful URL/content/SEO/link/media evidence captured;
- no theme/builder layout preservation requirement.

### Step 2 — Reset clone runtime

Apply Clone Reset.

Keep only a plugin whose business function genuinely must survive the new site. Migration Bridge keeps itself automatically until handoff is complete.

Expected result:

- SEO/GEO Theme active;
- legacy themes/builders/plugins removed unless explicitly retained;
- rescued content and manifest unchanged.

### Step 3 — Corporate preset

Bootstrap the Corporate preset and confirm the organization identity.

Expected result:

- Corporate preset active;
- WordPress locale remains authoritative;
- no front-page replacement.

### Step 4 — Clean Home draft

Create the clean Home draft.

Expected result:

- one private draft;
- composition uses Theme-native Corporate patterns only;
- source Home remains public and unchanged.

### Step 5 — Automatic content + native hydration

Use **Recommended: Automatic Home Content → Generate and hydrate Home automatically**.

Migration Bridge must read the preserved Home and related WordPress pages, remove legacy presentation syntax as text, map useful authored content into the Corporate semantic model, save the Content Kit and hydrate the private clean Home draft in one controlled action.

Expected result:

- locale follows the active WordPress/Corporate preset locale;
- model: `corporate-home-v1`;
- useful rescued content mapped automatically into semantic Home slots;
- source Home and current front-page assignment remain unchanged;
- no Divi/Elementor/legacy layout is executed or copied;
- `hero-proof`, `proof` and `case-study` remain disabled until independently verified;
- no fabricated client, metric, result or case-study evidence;
- manual Content Kit/portable Blueprint controls remain available only as advanced fallback/editing tools.

### Step 6 — Native SEO/GEO handoff

Apply the safe native SEO handoff.

Expected result:

- safe rescued title/description/indexability move to provider-neutral Core metadata;
- normal self-canonical remains runtime-owned;
- custom canonical or unresolved provider template creates a review item instead of being copied blindly;
- `cutover_seo_ready=true` only when no SEO review item remains.

### Step 7 — Field-pilot readiness

Step 7 must report:

`ready_for_browser_qa=true`

Do not continue to browser QA while any blocker remains.

The machine preflight verifies:

- sandbox marker;
- completed reset integrity;
- SEO/GEO Theme ownership;
- active plugin set unchanged since reset;
- hydrated clean Home draft without drift;
- rescued source Home unchanged;
- current front page still pointing to the rescued source;
- native SEO handoff applied and clear;
- no known legacy builder debris;
- no known preset placeholder copy.

## Browser QA evidence

Complete all five checks in `pilot-evidence-template.json`:

1. `visual-layout` — desktop/mobile composition, hierarchy, spacing, CTAs and content order.
2. `responsive-behavior` — no overflow, broken controls or unusable mobile layout.
3. `accessibility` — headings, landmarks, keyboard/focus, contrast and meaningful links.
4. `seo-geo-rendered-output` — title, description, canonical, robots, Schema/discovery output and internal links.
5. `performance` — no obvious asset/runtime regression and acceptable measured performance for the sandbox.

Record the Step 7 `report_sha256` in the evidence file.

## Cutover boundary

Passing Step 7 plus the five browser QA checks means the rebuilt Home is **eligible for cutover review**.

It does not automatically replace the current Home.

Production cutover requires a separate explicit decision after the evidence is reviewed.
