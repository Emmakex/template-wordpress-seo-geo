# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.1`.

Release channel: `prestable`.

The migration/reset/hydration/SEO-GEO engine, deterministic packaging, onboarding, upgrade/rollback and the Corporate v5 Theme-owned renderer boundary are working. Corporate v5 A1 proved the renderer/model boundary. Corporate v5 A2 proved the clean Theme-owned strategic chrome and removed the inherited v4 presentation debt. The current field candidate is **Corporate v5 A3**, a presentation-only refinement based on real `/nuevaweb/` QA.

A3 keeps the accepted A2 structure and content state intact while lightening the strategic palette, improving secondary-text/card separation, moving Method and the final CTA to lighter premium surfaces, refining the branded footer and aligning the mobile menu with the same visual system.

Stable promotion remains blocked by real-site product acceptance. Repository CI, deterministic hashes and fixture performance are necessary evidence but cannot declare the visual product accepted.

SaaS, Local Pro, Publisher and Ecommerce master-surface rollout remains frozen until Corporate v5 passes the real sandbox gate.

## Canonical Phase 10E candidate

The machine-readable source of truth remains:

`release/emmake-phase10e-candidate.json`

Frozen Corporate v5 A3 technical identity:

- Theme-content source commit: `82e3a6e0c2a4659d8d24cc473429d4fb1892eaaa`;
- Theme: `0.1.1` / `prestable`;
- Theme ZIP SHA-256: `7286b4e06af2ee5b0818bf748f8a7c5e01bb7fa7ff13cb0e46124b5d0a9e0708`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `6ddb9e8e4d5ab0f2443ee08e3b860e4c9abfc7b6ca46f7f3bc4a07be07422be1`.

This identity is reproducible technical evidence for the next `/nuevaweb/` field pass. It is **not** an accepted stable or production candidate. Any Theme-content change requires a new deterministic Theme SHA and a newly frozen candidate before field installation.

## Architecture and field evidence

Earlier candidates proved Rescue Manifest, Clone Reset, Corporate bootstrap, clean Home creation, content hydration, native SEO/GEO handoff and Step 7 machine readiness on `/nuevaweb/`.

Corporate v4.3 passed repository-side technical gates but failed the human visual/architectural acceptance gate. Corporate v5 therefore moved premium strategic rendering out of Gutenberg master-layout authority and into a Theme-owned semantic server renderer.

Corporate v5 A1 established the renderer/model boundary. Real-site A1 QA exposed broken database-overridable strategic chrome, hero imbalance, template-like capability hierarchy, residual list markers, default WordPress CTA styling, a weak footer and presentation debt from the previous Corporate stylesheet.

Corporate v5 A2 corrected those issues at the architectural presentation boundary:

- Theme-owned strategic header/main/footer;
- preset navigation rendered by the Theme rather than database-overridable block template parts;
- clean v5 strategic CSS without the old Corporate v4 presentation layer;
- coherent hero, capabilities, method, Insights, final CTA and footer;
- preserved rescued/hydrated content and SEO/GEO state.

Real `/nuevaweb/` QA of A2 confirmed that architecture was moving in the correct direction but the result still used too many near-black surfaces and lost separation in secondary content. Corporate v5 A3 therefore refines **presentation only**. It does not change Reset, hydration, Migration Bridge, content, URLs or SEO/GEO ownership.

The permanent ownership rule remains:

> **Gutenberg provides editorial autonomy. SEO/GEO Manager provides automation and growth. SEO/GEO Theme provides frontend rendering, strategic chrome, design, semantic HTML and performance.**

Canonical architecture: `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`.

## Corporate v5 repository baseline

The v5 strategic master surface no longer depends on:

- Gutenberg `contentSize` / `wideSize` as page-width authority;
- `is-layout-constrained` for master sections;
- `wp-block-post-content` as the strategic layout surface;
- nested layout blocks as the renderer contract;
- database-overridable block template parts for Corporate strategic chrome;
- the legacy Corporate v4 visual stylesheet in v5 client delivery.

The accepted A2 repository baseline already demonstrated semantic header/main/footer landmarks, one strategic H1, server-side dynamic Insights, keyboard/focus and reduced-motion behavior, WCAG/reflow automation, WordPress smoke/upgrade/rollback, PHP quality, self-contained deterministic packaging, zero project frontend JavaScript and zero third-party requests. A3 must preserve those gates while changing only the approved visual layer.

The frozen A3 identity above is the candidate that must be used for the next deterministic field pack and `/nuevaweb/` review. CI reruns for that exact candidate are authoritative; no result is inferred from A2.

## SEO/GEO Manager relationship

SEO/GEO Manager is a separate plugin product and is not required for Theme stable runtime.

The v5 renderer boundary supports the future Manager direction:

- Manager Landing Engine creates or updates structured strategic-page models rather than visual Gutenberg block trees;
- Theme renders those models;
- Manager Blog Engine can create complete normal WordPress posts;
- automated blog posts remain editable by authorized users in Gutenberg;
- Manager handles draft/preview/schedule/publish/refresh/rollback;
- Theme owns the public article shell and strategic frontend presentation;
- exactly one SEO/GEO output owner remains active per signal.

## Selected real-site pilot

Production origin: `https://emmake.com/`.

Sandbox: `https://emmake.com/nuevaweb/`.

The sandbox already has a hydrated clean Home state and Step 7 readiness. **Do not** rerun Reset, regenerate the Home, rehydrate content or change Migration Bridge merely because Theme presentation changes.

## Remaining stable-release blocker

Phase 10E remains `no-go` until all of the following are real and recorded:

1. a deterministic Corporate v5 Theme candidate is frozen;
2. that exact candidate is installed only on `/nuevaweb/`;
3. the existing Reset & Rebuild and hydrated semantic content state remains intact;
4. native SEO/GEO handoff has no unresolved blocker;
5. Step 7 remains `ready_for_browser_qa=true` or equivalent validated readiness;
6. real browser QA passes at `1440 / 1024 / 768 / 390` for visual layout, responsive behavior, accessibility, SEO/GEO rendered output and performance;
7. the Corporate Home receives explicit product-quality approval at the premium/WOW standard;
8. the Theme-owned renderer contract is reusable enough to become the master pattern for the remaining presets;
9. the sandbox is explicitly accepted before any production-entry decision;
10. later production verification completes without a rollback trigger.

Item 1 is satisfied by the frozen Corporate v5 A3 identity above. Items 2–10 remain field/acceptance work. Repository CI cannot satisfy them by inference.

Until production acceptance exists, `release/stable-release-decision.json` remains `decision=no-go`, real-site acceptance remains `pending`, its reference remains `null`, and blocker `real-site-production-acceptance-pending` stays present.

## Product boundary

SEO/GEO Manager is a separate plugin product.

It is not a mandatory dependency or blocker for Theme `0.1.1` stable runtime.

Migration Bridge is the accepted migration/reset implementation for this Theme pilot. Migration Bridge `0.8.60` remains frozen for the current field candidate.

The standalone Core wrapper remains `deprecated-retained-nondistributed` and is not included in the self-contained Theme release ZIP.

## Automated decision contract

`scripts/ci/validate-stable-release-decision.py` enforces decision/version consistency, canonical candidate identity, pending evidence for NO-GO, changelog state and the Core wrapper distribution boundary.

`EMMAKE Field Pilot Pack CI` rebuilds Theme, Migration Bridge and the field pack byte-for-byte and verifies their SHA-256 identities against `release/emmake-phase10e-candidate.json`.

Corporate v5 A1 and A2 remain historical technical/field evidence. Corporate v5 A3 is the current frozen technical field candidate and still requires exact `/nuevaweb/` browser and visual acceptance before stable or production decisions.

## Promotion from no-go to go

Only after real sandbox and production acceptance: record bounded evidence, clear blockers, change real-site acceptance to `accepted`, move the release channel to `stable`, release the changelog entry, rerun all required gates and publish only if they remain green.

No production evidence is fabricated or inferred from repository CI.
