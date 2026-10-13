# Phase 10E real-site pilot — emmake.com

This is the operational source for the next real-site acceptance pass of the self-contained SEO/GEO Theme and SEO/GEO Manager on EMMAKE. Historical clone, migration and Corporate milestones remain valid evidence, but they do not override the current candidate.

## Current authority

Canonical machine-readable candidate:

`release/emmake-phase10e-candidate.json`

Frozen Corporate v5 A3.2 field identity:

- site ID: `emmake-com`;
- production origin: `https://emmake.com/`;
- sandbox origin: `https://emmake.com/nuevaweb/`;
- target Theme release: `0.1.2`;
- release channel: `prestable`;
- frozen product/field-pack source commit: `5907c17db1b54709b54ceddf0b3c04504d475fd5`;
- Theme ZIP SHA-256: `111cfbe772387471b4388e432d3258ae5b5dd26e7a0b5b9d411dc36750d5ab3e`;
- Migration Bridge version: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic field-pilot pack SHA-256: `7dd3f974bb554d27a0a907c54c7313172aad529ef4c291598d82e1efc4c0520d`;
- SEO/GEO Manager target: `0.3.73`;
- stable decision: `no-go`;
- real-site acceptance: `pending`.

Repository reproducibility is necessary evidence, but it is **not** visual acceptance. The exact frozen artifact still has to prove the product result on `/nuevaweb/`.

## Current pilot position

The technical Reset & Rebuild path has already reached the browser-QA boundary on the real `/nuevaweb/` clone:

- Rescue Manifest completed;
- Clone Reset completed;
- Corporate bootstrap completed;
- clean Home created;
- rescued/client content hydrated into semantic slots;
- SEO/GEO ownership/handoff established;
- machine readiness reached the browser-QA boundary.

Do **not** rerun Reset, recreate the clone, regenerate the Home or rehydrate content merely because Theme or Manager presentation/control-plane binaries changed. Migration Bridge `0.8.60` remains frozen unless a concrete migration defect is demonstrated.

## Current Corporate implementation

Corporate v5 keeps the Theme as sole strategic rendering authority. Gutenberg remains the editorial/CMS surface rather than the master layout engine.

The A3.2 Home supports Theme-declared strategic image bindings for hero, three capabilities, process/method, three Insights fallbacks and the final CTA.

Featured Images remain authoritative for Insights. A strategic image is used only when the corresponding article has no Featured Image. Safe local Theme fallbacks remain available when no valid strategic image is bound.

The A3.2 presentation layer adds media-aware capability cards, a stronger process composition, richer Insights treatment, a media-aware final CTA, responsive recomposition and reduced-motion behavior. The public strategic Home remains server rendered and does not require project frontend JavaScript or third-party visual dependencies.

## Manager operating surface

Manager `0.3.73` completes the repository-side operating surface required by EMMAKE:

- semantic Theme Build / Finish;
- normal pages and posts;
- SEO authority;
- contextual links;
- navigation;
- Featured Media;
- image ALT;
- Operation Evidence and Safe Recovery;
- Optimization Queue;
- draft-first Grow Workspace;
- direct guarded image upload;
- strategic Theme media assignment;
- explicit publish/schedule workflow.

Theme Media discovers its own bounded catalog from the active Theme strategic-media contract instead of depending on `page-models.json`. That makes `corporate-home-v1` and its A3.2 image slots operable while keeping raw Theme metadata private and preserving Theme layout authority.

The mutation path remains Preview → Apply → readback/Verify → Evidence → stale-safe Rollback where rollback is valid. Publish/schedule remains an explicit lifecycle transition rather than pretending to offer a generic rollback.

## Ownership boundary

```text
rescued + hydrated WordPress content/resources
        ↓
semantic model + declared strategic media
        ↓
SEO/GEO Manager controlled operations
        ↓
SEO/GEO Theme server renderer
        ↓
semantic public HTML + responsive visual system
```

WordPress remains CMS/resource authority. Theme owns public strategic layout/chrome. Manager owns bounded control-plane operations. External orchestration owns strategy/research/content intelligence.

Manager never becomes the public renderer, and Theme never becomes the strategic research/content engine.

## Sandbox safety boundary

All current acceptance work remains isolated to `/nuevaweb/`. Production remains untouched until explicit sandbox acceptance and a later production-entry decision.

The sandbox safety constants remain required:

```php
define( 'SEO_GEO_MIGRATION_SANDBOX', true );
define( 'SEO_GEO_MIGRATION_SANDBOX_MODE', 'subdirectory' );
define( 'SEO_GEO_MIGRATION_STORAGE_ISOLATED', true );
define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );
define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );
```

Search visibility, canonical/hreflang/sitemap authority, production `home`/`siteurl` and rollback boundaries must remain isolated from production. No credentials, dumps, private submissions or customer data belong in repository evidence.

## Field execution — A3.2

The destructive/reset portion must **not** be repeated unless a real state failure is proven.

1. Keep the existing `/nuevaweb/` clone and existing rescue/hydration evidence.
2. Keep Migration Bridge `0.8.60` unchanged.
3. Preserve the current clean semantic Home/content state.
4. Verify the Theme ZIP SHA-256 is exactly `111cfbe772387471b4388e432d3258ae5b5dd26e7a0b5b9d411dc36750d5ab3e`.
5. Verify the complete Field Pilot Pack SHA-256 is exactly `7dd3f974bb554d27a0a907c54c7313172aad529ef4c291598d82e1efc4c0520d`.
6. Install that exact Theme `0.1.2` candidate only on `/nuevaweb/`.
7. Install the accepted Manager `0.3.73` candidate when its deterministic release identity is recorded.
8. Clear only relevant WordPress/Hostinger/browser caches; do not reset or rehydrate.
9. Open the existing logical Home through the Theme-owned renderer.
10. Use Manager Theme Media to confirm `corporate-home-v1` and its nine image slots are discoverable.
11. Upload/assign the prepared EMMAKE corporate imagery only through the guarded Manager media workflows.
12. Review the full desktop composition before narrower viewports.
13. Run field QA at `1440 / 1024 / 768 / 390`; retain `320` as an additional compact stress case.
14. Do not advance to production-entry review until the Home receives explicit product-quality approval.

If semantic content, URL identity, SEO/GEO ownership or machine readiness has drifted, stop and diagnose the drift instead of recreating evidence casually.

## Corporate v5 A3.2 field checks

Before recording the five formal QA results, confirm:

- the Home is rendered by the Theme-owned Corporate strategic renderer;
- the strategic header/footer are singular and no legacy builder chrome reappears;
- `corporate-home-v1` remains the semantic Home model;
- hero, capability, process, Insights fallback and final CTA imagery can be rendered through the declared Theme strategic-media contract;
- WordPress Featured Images remain authoritative for Insights when present;
- missing optional strategic media falls back safely rather than breaking layout;
- no project page-builder JavaScript or third-party visual dependency becomes required.

## Browser QA acceptance — premium/WOW gate

Complete all five checks in the field evidence:

1. `visual-layout` — desktop/mobile composition, premium hierarchy, spacing, image balance, CTAs and content order.
2. `responsive-behavior` — no overflow, broken controls, squeezed desktop layout or unusable mobile recomposition.
3. `accessibility` — headings, landmarks, keyboard/focus, contrast, meaningful links and appropriate image alternatives.
4. `seo-geo-rendered-output` — title, description, canonical, robots, Schema/discovery output and important internal links.
5. `performance` — no material asset/runtime regression and acceptable measured performance for the sandbox.

Run the field pass at `1440 / 1024 / 768 / 390`; keep `320` as an additional compact stress case.

A technically valid result that still fails the premium/WOW standard remains a blocker.

For the A3.2 Home specifically verify one coherent strategic header, hero actions in the practical desktop first viewport, balanced hero media, readable capability cards, a clear Method/process hierarchy, correct Insights Featured Image/fallback behavior, an actionable final CTA, correct mobile menu/close/outside-click behavior, usable footer navigation, no horizontal overflow and visible keyboard focus.

## Representative SEO/GEO regression set

At minimum validate Home, Sobre Nosotros, Trabaja con Nosotros, blog index, one representative recent article and Contacto, plus every additional URL class identified by the Rescue Manifest.

Review status, canonical, robots/indexability, title/meta, Open Graph, Schema, hreflang when configured, redirects, sitemap membership, organization/contact facts and important internal links.

## Repository vs field boundary

The repository gate covers deterministic packaging, PHP/WPCS/PHPStan, WordPress smoke, responsive/accessibility automation, performance budgets, multilingual contracts, Corporate pipeline and Manager-specific workspaces/recovery contracts.

A green repository gate can freeze the technical candidate. It cannot mark the field evidence as accepted.

The sandbox becomes eligible for production-entry review only when the **exact frozen A3.2 artifact** is installed, the Theme-owned Home passes the product-quality gate, no URL/SEO/GEO blocker remains, responsive/accessibility/performance checks pass, critical navigation/forms work, evidence is complete and rollback boundaries remain available.

This is **not** automatic production cutover.

## Stable promotion boundary

Theme stable promotion remains **NO-GO**.

Until real sandbox and later production acceptance exist:

- `release/stable-release-decision.json` remains `no-go`;
- real-site acceptance remains `pending`;
- `release/version.json` remains `prestable`;
- blocker `real-site-production-acceptance-pending` remains present.

Only after actual acceptance may the repository record `accepted`, clear the blocker, switch to `stable`, rerun the required gates and publish.

No production evidence is fabricated or inferred from CI.
