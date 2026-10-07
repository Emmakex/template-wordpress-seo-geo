# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.1`.

Release channel: `prestable`.

The migration/reset/hydration/SEO-GEO engine, deterministic packaging, onboarding, upgrade/rollback and repository-side technical contracts are working. The remaining blocker is real browser acceptance of the Corporate master preset on the isolated EMMAKE `/nuevaweb/` sandbox.

The latest real-site review proved the cache-safe v4.2 CSS was loading correctly, but also exposed a deeper reusable layout issue: WordPress/Gutenberg constrained-content widths were still compressing the Corporate master surfaces. Hero, Services, Process and Insights therefore remained too narrow and too vertical on horizontal screens. Corporate v4.3 is the current field candidate and explicitly escapes those inherited width constraints while preserving the existing semantic draft, rescued content and SEO/GEO state.

SaaS, Local Pro, Publisher and Ecommerce visual iteration remains frozen until Corporate v4.3 is approved on the real sandbox. Repository CI alone cannot declare the visual product accepted.

## Canonical Phase 10E candidate

The machine-readable source of truth is:

`release/emmake-phase10e-candidate.json`

Current frozen Corporate v4.3 identity:

- source commit: `f0ea48d311dadbec4ab7287c51f4bdd920c3e548`;
- Theme: `0.1.1` / `prestable`;
- Theme ZIP SHA-256: `b45b575f96a4163bc07d09de0cd65775e9558bba467e641e7d7a31c99a2ce234`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `8d397831a289ba856e401a7c8c6cdf3d51da944a6317fdd99158f30aeffe0520`.

The source commit identifies the immutable Theme-content state. Later record, documentation or validation-only commits do not redefine those Theme bytes. Any Theme, Migration Bridge, runbook, blueprint or pack-content change requires a newly frozen candidate.

## Why v4.3 exists

Earlier candidates proved Rescue Manifest, Clone Reset, Corporate bootstrap, clean Home creation, content hydration, native SEO/GEO handoff and Step 7 machine readiness on `/nuevaweb/`.

Corporate v4 established the intended premium/WOW art direction. v4.1 corrected major composition defects. v4.2 widened reading measures and added content-hash asset versioning so deterministic release timestamps cannot keep stale CSS alive in browser/CDN caches.

The next real screenshot showed that v4.2 was truly active but that several master blocks were still being visually constrained by Gutenberg layout rules. v4.3 therefore keeps the same art direction and introduces a Theme-owned wide-layout escape:

- the hero grid uses the master horizontal shell rather than a constrained content column;
- hero headline measure is wider and maximum scale is more controlled;
- native Corporate sections explicitly own their master width;
- Services uses a wider balanced two-column composition;
- Process uses three genuinely readable horizontal columns;
- Insights and its query/post template span the intended shell;
- the existing `<=820px` mobile recomposition is preserved;
- no Reset, content regeneration, rehydration or SEO rewrite is part of this visual change.

The Corporate CSS gzip safety proxy remains below the existing `7100` byte internal ceiling; the budget was not relaxed to accommodate v4.3.

## Selected real-site pilot

Production origin: `https://emmake.com/`.

Sandbox: `https://emmake.com/nuevaweb/`.

The current sandbox already has a hydrated clean Home draft and Step 7 readiness. For v4.3 the destructive/reset sequence must not be repeated casually. Only the Theme candidate is replaced, then the same draft is reviewed in the browser.

## Remaining stable-release blocker

Phase 10E remains `no-go` until all of the following are real and recorded:

1. the exact frozen Corporate v4.3 Theme is installed only on `/nuevaweb/`;
2. the existing Reset & Rebuild and hydrated Home state remains intact;
3. native SEO/GEO handoff has no unresolved blocker;
4. Step 7 still reports `ready_for_browser_qa=true`;
5. browser QA passes at `1440 / 1024 / 768 / 390` for visual layout, responsive behavior, accessibility, SEO/GEO rendered output and performance;
6. the Corporate Home receives explicit product-quality approval at the intended premium/WOW standard;
7. the sandbox is explicitly accepted before any production-entry decision;
8. later production verification completes without a rollback trigger.

Until production acceptance exists, `release/stable-release-decision.json` remains `decision=no-go`, real-site acceptance remains `pending`, its reference remains `null`, and blocker `real-site-production-acceptance-pending` stays present.

## Product boundary

SEO/GEO Manager is a separate future plugin product.

It is not a dependency or blocker for Theme `0.1.1` stable acceptance.

Migration Bridge is the accepted migration/reset implementation for this Theme pilot.

The standalone Core wrapper remains `deprecated-retained-nondistributed` and is not included in the self-contained Theme release ZIP.

## Automated decision contract

`scripts/ci/validate-stable-release-decision.py` enforces decision/version consistency, canonical candidate identity, pending evidence for NO-GO, changelog state and the Core wrapper distribution boundary.

`EMMAKE Field Pilot Pack CI` rebuilds Theme, Migration Bridge and the field pack byte-for-byte and verifies their SHA-256 identities against `release/emmake-phase10e-candidate.json`.

Corporate v4.3 has a dedicated layout-escape, cache-safe asset-version and lean-CSS contract. Browser acceptance remains the human product gate.

## Promotion from no-go to go

Only after real sandbox and production acceptance: record bounded evidence, clear blockers, change real-site acceptance to `accepted`, move the release channel to `stable`, release the changelog entry, rerun all required gates and publish only if they remain green.

No production evidence is fabricated or inferred from repository CI.
