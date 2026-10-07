# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.1`.

Release channel: `prestable`.

The migration/reset/hydration/SEO-GEO engine, deterministic packaging, onboarding, upgrade/rollback and repository-side technical contracts are working. The remaining blocker is real browser acceptance of the Corporate master preset on the isolated EMMAKE `/nuevaweb/` sandbox.

Corporate v4.3 passed the repository-side technical gates, including deterministic packaging, WordPress smoke, responsive/accessibility and Lighthouse, but **failed the human visual/architectural acceptance gate on the real sandbox**. The field screenshot showed that master sections could still resolve against different Gutenberg/WordPress constrained-layout contexts, producing a visually fragmented page despite the Theme's own width rules.

The product decision is therefore to stop patching Gutenberg layout behavior and move to **Corporate v5 — Theme-owned frontend**.

SaaS, Local Pro, Publisher and Ecommerce visual iteration remains frozen until the Corporate v5 renderer boundary is proven on the real sandbox. Repository CI alone cannot declare the visual product accepted.

## Canonical Phase 10E candidate

The machine-readable source of truth remains:

`release/emmake-phase10e-candidate.json`

Last frozen Corporate v4.3 technical identity:

- source commit: `095e2f6471264ef4ba21c3242467a93d8e2b88ac`;
- Theme: `0.1.1` / `prestable`;
- Theme ZIP SHA-256: `11540b3bea463a5f5cabe4d527f628de2e1d3fe6ad4b0d7cdb11a60ec85f99d2`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `d82fa628b96e29bf1f522dd76822a9efd4ce4766b7771069457cb5463478e671`.

This identity remains reproducible technical evidence. It is **not** the accepted visual candidate. A new Corporate v5 Theme-content change requires a new deterministic Theme SHA and a newly frozen candidate before field installation.

## v4.x evidence and architectural conclusion

Earlier candidates proved Rescue Manifest, Clone Reset, Corporate bootstrap, clean Home creation, content hydration, native SEO/GEO handoff and Step 7 machine readiness on `/nuevaweb/`.

Corporate v4 established the premium/WOW art direction. v4.1 corrected major composition defects. v4.2 widened reading measures and added content-hash asset versioning. v4.3 added a constrained-layout escape and remained within the existing performance budgets.

The real v4.3 screenshot nevertheless showed that a premium strategic Home should not depend on Gutenberg as its master layout engine. Continuing with increasingly specific width/selector overrides would create reusable product debt rather than solve the architecture.

The permanent ownership rule is now:

> **Gutenberg provides editorial autonomy. SEO/GEO Manager provides automation and growth. SEO/GEO Theme provides frontend rendering, design, semantic HTML and performance.**

Canonical architecture: `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`.

## Corporate v5 requirement

Corporate v5 must render the strategic Home from the existing semantic content model/state through a Theme-owned server renderer.

The master composition must no longer depend on:

- Gutenberg `contentSize` / `wideSize` as page-width authority;
- `is-layout-constrained` for master sections;
- `wp-block-post-content` as the strategic layout surface;
- nested layout blocks as the renderer contract;
- generated block-layout CSS to align master sections.

WordPress remains the CMS/resource authority. Gutenberg remains supported for blog/editorial bodies and simple pages.

Corporate v5 must preserve the existing rescued/hydrated content, links, SEO/GEO state and sandbox evidence without rerunning destructive migration/reset merely to change frontend rendering.

## SEO/GEO Manager relationship

SEO/GEO Manager remains a separate future plugin product and is not required for Theme stable runtime.

However, the Theme renderer boundary must support the future Manager product direction:

- Manager Landing Engine creates/updates structured strategic-page models rather than visual Gutenberg block trees;
- Theme renders those models;
- Manager Blog Engine can create complete blog posts automatically;
- automated blog posts remain normal WordPress posts editable by authorized users in Gutenberg;
- Manager handles draft/preview/schedule/publish/refresh/rollback;
- Theme continues to own the public article shell and strategic frontend presentation;
- exactly one SEO/GEO output owner remains active per signal.

The Manager roadmap does not delay Theme runtime unnecessarily, but Corporate v5 must prove the content-model/renderer boundary that later Manager automation will consume.

## Selected real-site pilot

Production origin: `https://emmake.com/`.

Sandbox: `https://emmake.com/nuevaweb/`.

The sandbox already has a hydrated clean Home state and Step 7 readiness. The destructive/reset sequence must not be repeated casually for Corporate v5. The next test changes rendering architecture while preserving content/SEO evidence.

## Remaining stable-release blocker

Phase 10E remains `no-go` until all of the following are real and recorded:

1. a deterministic Corporate v5 Theme candidate is frozen;
2. that exact candidate is installed only on `/nuevaweb/`;
3. the existing Reset & Rebuild and hydrated semantic content state remains intact;
4. native SEO/GEO handoff has no unresolved blocker;
5. Step 7 still reports `ready_for_browser_qa=true` or equivalent validated readiness after renderer migration;
6. browser QA passes at `1440 / 1024 / 768 / 390` for visual layout, responsive behavior, accessibility, SEO/GEO rendered output and performance;
7. the Corporate Home receives explicit product-quality approval at the premium/WOW standard;
8. the Theme-owned renderer contract is reusable enough to become the master pattern for the remaining presets;
9. the sandbox is explicitly accepted before any production-entry decision;
10. later production verification completes without a rollback trigger.

Until production acceptance exists, `release/stable-release-decision.json` remains `decision=no-go`, real-site acceptance remains `pending`, its reference remains `null`, and blocker `real-site-production-acceptance-pending` stays present.

## Product boundary

SEO/GEO Manager is a separate plugin product.

It is not a mandatory dependency or blocker for Theme `0.1.1` stable runtime.

Migration Bridge is the accepted migration/reset implementation for this Theme pilot.

The standalone Core wrapper remains `deprecated-retained-nondistributed` and is not included in the self-contained Theme release ZIP.

## Automated decision contract

`scripts/ci/validate-stable-release-decision.py` enforces decision/version consistency, canonical candidate identity, pending evidence for NO-GO, changelog state and the Core wrapper distribution boundary.

`EMMAKE Field Pilot Pack CI` rebuilds Theme, Migration Bridge and the field pack byte-for-byte and verifies their SHA-256 identities against `release/emmake-phase10e-candidate.json`.

The v4.3 candidate remains valid historical technical evidence. Corporate v5 will require its own renderer contracts, deterministic candidate identity and browser acceptance.

## Promotion from no-go to go

Only after real sandbox and production acceptance: record bounded evidence, clear blockers, change real-site acceptance to `accepted`, move the release channel to `stable`, release the changelog entry, rerun all required gates and publish only if they remain green.

No production evidence is fabricated or inferred from repository CI.
