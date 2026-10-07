# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.1`.

Release channel: `prestable`.

The migration/reset/hydration/SEO-GEO engine, packaging, onboarding, upgrade/rollback, client delivery and repository-side production-readiness contracts are working. Real EMMAKE browser QA rejected the original Corporate presentation and Corporate v3. Corporate v4 established the correct premium/WOW art direction and v4.1 corrected its first major composition defects. The latest real `/nuevaweb/` review then exposed a narrower issue: text-heavy titles and paragraphs still grew too vertically on horizontal screens because their usable boxes were too narrow. Corporate v4.2 is therefore the current master field candidate and remains the active release blocker until real browser approval.

SaaS, Local Pro, Publisher and Ecommerce visual iteration remains frozen until Corporate v4.2 is approved on the real sandbox. A green repository build alone is not sufficient to declare stable.

## Canonical Phase 10E candidate

The machine-readable source of truth is:

`release/emmake-phase10e-candidate.json`

The currently frozen Corporate v4.2 field candidate is:

- source commit: `dea41aaf3eddac37eb2c91484198af7762e1c565`;
- Theme: `0.1.1` / `prestable`;
- Theme ZIP SHA-256: `fc19a2e5c4137a524d8bf96682832de6b4b3eb560f7562e2c728e083fc927713`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `20e01026cf3ba49d2ee50ce31a8f0a53364008d2e272680a2dad99d097617aa5`.

The source commit above is the immutable Theme-content source for the frozen field artifact. Repository history may advance with candidate-record, documentation or validation-only commits without changing those packaged bytes. Any Theme, Migration Bridge, runbook, blueprint or pack-content change requires a new deterministic pack and an explicit update of `release/emmake-phase10e-candidate.json`.

`EMMAKE Field Pilot Pack CI` rebuilds the package byte-for-byte and rejects any version or SHA drift from this canonical record.

## Candidate technical evidence

The frozen Corporate v4.2 Theme passed the repository-side technical gates required before returning to the real browser:

- PHP quality and static/runtime contracts established for Theme `0.1.1`;
- WordPress 7.1 / PHP 8.2 activation smoke;
- self-contained Theme and deterministic release-artifact integrity;
- multilingual and preset regression contracts;
- Corporate page-pipeline and v4.2 horizontal reading-measure contract gates;
- accessibility/responsive browser automation, including `1440 / 1024 / 768 / 390` plus compact-mobile stress coverage;
- Lighthouse performance budget without relaxing the budget.

The Corporate v4.2 Lighthouse median is performance `100`, FCP/LCP `902.68 ms`, CLS `0`, TBT `0`, zero third-party requests, zero project JavaScript bytes and `7534` transferred CSS bytes against the `8192` byte Corporate CSS budget. The static Corporate CSS proxy is `6530` bytes against a `7100` byte internal ceiling.

This evidence proves technical readiness for field QA. It does **not** prove visual acceptance, production acceptance or stable-release readiness.

## Why the candidate changed

The earlier candidates successfully proved Rescue Manifest, Clone Reset, Corporate bootstrap, clean Home creation, content hydration, native SEO/GEO handoff and Step 7 machine readiness on `/nuevaweb/`.

The first visual candidate failed for generic card composition, weak responsive behavior and contrast. Corporate v3 fixed those engineering defects but remained visually below the premium product bar. Corporate v4 established the intended direction with a full-bleed hero, stronger editorial scale, asymmetric capability panels, a cinematic method section, magazine-style Insights and a dominant closing CTA. Corporate v4.1 then corrected the first real field defects: over-narrow hero wrapping, squeezed secondary panels, decorative dominance and the preview-only page-title strip.

The next real v4.1 screenshot confirmed the art direction and the v4.1 corrections, but showed that the typography system still forced too much vertical growth on horizontal screens. Long titles and paragraphs in Services, Process and Insights needed more usable width rather than larger heights or smaller visual ambition.

Corporate v4.2 keeps the same v4 art direction and rebalances horizontal measure: a `1400px` master shell where available, a wider hero copy ratio, wider text-heavy secondary panels, larger character measures, lower unnecessary card heights, a dedicated `1200px` laptop tuning layer and slightly more restrained maximum type scales. The semantic hydration model, rescued content, URLs and SEO/GEO state remain preserved.

The field delivery path also now versions Theme CSS from its SHA-256 content rather than file timestamps. Deterministic release ZIPs intentionally preserve fixed timestamps, so content-derived asset URLs prevent browser/CDN caches from serving an older Corporate stylesheet after a new candidate is installed.

## Selected real-site pilot

Production origin: `https://emmake.com/`.

Sandbox: `https://emmake.com/nuevaweb/`.

The working `/nuevaweb/` clone already proved the product-owned clone path, reset-first replatforming, hydration and native SEO handoff. Those milestones do not substitute for browser acceptance of Corporate v4.2.

The current field sequence is defined in `docs/EMMAKE_HOME_FIELD_PILOT.md` and summarized in `docs/REAL_SITE_PILOT.md`.

## Remaining stable-release blocker

Phase 10E remains `no-go` until all of the following are real and recorded:

1. the exact frozen Corporate v4.2 candidate is installed only on the isolated `/nuevaweb/` sandbox;
2. the already proven Reset & Rebuild state remains intact without carrying legacy presentation debt;
3. the Corporate Home remains hydrated from rescued/client facts and content;
4. native SEO/GEO handoff has no unresolved review blocker;
5. field-pilot Step 7 reports `ready_for_browser_qa=true`;
6. Corporate v4.2 browser QA passes visual, responsive, accessibility, SEO/GEO rendered-output and performance checks at `1440 / 1024 / 768 / 390`;
7. the Corporate Home is explicitly accepted as a product-quality master preset with the intended premium/WOW standard before Step 8 or inner-page rollout advances;
8. the sandbox is explicitly accepted for controlled production entry;
9. production verification completes without a rollback trigger;
10. only a bounded acceptance reference, never credentials or private payloads, is stored in the repository.

Until production acceptance exists, `release/stable-release-decision.json` must remain:

- `decision=no-go`;
- `real_site_acceptance.status=pending`;
- `real_site_acceptance.reference=null`;
- blocker `real-site-production-acceptance-pending` present.

## Product boundary

SEO/GEO Manager is a separate future plugin product. It is not a dependency or blocker for Theme `0.1.1` stable acceptance.

Migration Bridge is the accepted migration/reset implementation for this Theme pilot. Its later absorption or reuse inside Manager must preserve regression parity, but that later roadmap cannot postpone the Theme release gate.

## Transitional Core wrapper

The standalone `packages/seo-geo-core/seo-geo-core.php` wrapper remains:

**deprecated-retained-nondistributed**

It is retained only as a temporary source/development compatibility path, is not a baseline dependency and is not included in the self-contained Theme release ZIP.

New client deployments use the self-contained Theme artifact rather than installing the Core wrapper.

## Automated decision contract

`scripts/ci/validate-stable-release-decision.py` enforces:

- stable decision ↔ `release/version.json` consistency;
- stable decision ↔ canonical Phase 10E candidate consistency;
- candidate target, versions and SHA syntax;
- candidate identity markers in this document and `docs/REAL_SITE_PILOT.md`;
- `no-go` ↔ `prestable` and pending real-site evidence;
- `go` ↔ `stable` and accepted bounded evidence;
- changelog state;
- Core wrapper distribution boundary.

`EMMAKE Field Pilot Pack CI` separately rebuilds Theme, Migration Bridge and the field pack byte-for-byte and verifies their SHA-256 identities against `release/emmake-phase10e-candidate.json`.

Corporate v4.2 has a dedicated horizontal reading-measure and cache-safe asset-version CI gate, and browser acceptance includes the product master widths in addition to the compact-mobile stress check.

## Promotion from no-go to go

Only after real sandbox and production acceptance:

1. complete the bounded production acceptance record;
2. store its reference in `release/stable-release-decision.json`;
3. update the canonical Phase 10E candidate acceptance state;
4. change real-site acceptance to `accepted`;
5. clear blockers;
6. change the stable decision to `go`;
7. change `release/version.json` from `prestable` to `stable`;
8. convert the target changelog entry from Unreleased to released `0.1.1`;
9. run Foundation, Release Artifact, field-pilot and all affected quality gates again;
10. publish stable only when all required checks are green.

No production evidence is fabricated or inferred from repository CI.
