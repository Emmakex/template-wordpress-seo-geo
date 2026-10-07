# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.1`.

Release channel: `prestable`.

The migration/reset/hydration/SEO-GEO engine, deterministic packaging, onboarding, upgrade/rollback and the Corporate v5 Theme-owned renderer boundary are working. Corporate v5 A1 proved the renderer/model boundary. Corporate v5 A2 proved clean Theme-owned strategic chrome and removed inherited v4 presentation debt. Corporate v5 A3 lightened the real-site visual system. A3.1 closed the first bounded visual/media pass. The current exact field candidate is **Corporate v5 A3.2**, which replaces the remaining synthetic hero/capability treatment with approved optimized local media before the next `/nuevaweb/` pass.

A3.2 keeps the accepted A2/A3/A3.1 architecture and hydrated content state intact while:

- replacing the synthetic hero visual with an optimized local AVIF generated for the EMMAKE visual language;
- combining the three approved capability visuals into one optimized AVIF atlas so Marketing, Market Research and AI/Automation share one network request;
- keeping the hero eager/high-priority while the shared capability atlas is reused by CSS;
- preserving intrinsic dimensions to protect CLS;
- removing redundant pseudo-art where real media now owns the visual role;
- retaining server-rendered semantic HTML and zero required project frontend JavaScript;
- preserving the existing Reset, hydration, URL, content, SEO/GEO and Migration Bridge state;
- preserving the existing strict request and transfer budgets rather than relaxing them for imagery.

Stable promotion remains blocked by real-site product acceptance. Repository CI, deterministic hashes and fixture performance are necessary evidence but cannot declare the visual product accepted.

SaaS, Local Pro, Publisher and Ecommerce master-surface rollout remains frozen until Corporate v5 passes the real sandbox gate.

## Canonical Phase 10E candidate

The machine-readable source of truth remains:

`release/emmake-phase10e-candidate.json`

Frozen Corporate v5 A3.2 technical identity:

- Theme implementation source commit: `59049c5aebc7e1a01a8c4ec433ccd3d6bfedc00f`;
- Theme: `0.1.1` / `prestable`;
- Theme ZIP SHA-256: `02eacf06c0e044e6520b6e633800a61f0f8628c5bea1f336b9e6a5a7f70a4df4`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `971c825b2010907b7285a949abad37c0484a4f76db94bdeef55b8dd45a8441cc`.

This identity is reproducible technical evidence for the next `/nuevaweb/` field pass. It is **not** an accepted stable or production candidate. Any Theme implementation change requires a new deterministic Theme SHA and a newly frozen candidate before field installation.

The candidate metadata may be committed after the implementation source commit without changing the Theme package. `source_commit` intentionally identifies the implementation bytes being frozen rather than self-referencing the metadata commit.

## A3.1 historical repository acceptance

The previous A3.1 implementation passed the repository gates for Foundation, Design System, Corporate Page Pipeline, Phase 1 Package, Release Artifact, PHP Quality / WPCS / PHPStan level 6, Native Multilingual, Self-contained Theme, WordPress Smoke, Accessibility & Responsive and Performance Baseline.

Its Corporate fixture recorded Lighthouse `100`, FCP `751.72 ms`, LCP `901.72 ms`, CLS `0`, TBT `0 ms`, `8` requests, `0` third-party requests, `0` project JavaScript bytes and `118` DOM nodes. A temporary decorative-request regression was corrected rather than weakening the budget.

A3.1 remains historical technical evidence. It was installed on `/nuevaweb/` and the field screenshot confirmed that the new architecture and lighter presentation were materially better, while also showing that real imagery would materially improve the premium/WOW result. That finding produced A3.2 rather than reopening the renderer/model boundary.

## A3.2 repository acceptance

A3.2 is deliberately bounded to real local media and the CSS geometry needed to present it. The first straightforward WebP implementation retained Lighthouse `100`, CLS `0` and TBT `0`, but exceeded the existing Corporate transfer/request budgets. The budget was **not** relaxed. The approved visuals were recomposed into one compact `640×360` AVIF hero plus one shared `320×720` AVIF capability atlas so all three service cards retain distinct imagery while paying for one shared request.

The deterministic Theme package for implementation commit `59049c5aebc7e1a01a8c4ec433ccd3d6bfedc00f` has SHA-256 `02eacf06c0e044e6520b6e633800a61f0f8628c5bea1f336b9e6a5a7f70a4df4`.

The deterministic EMMAKE field pack for the same Theme bytes, with Migration Bridge still frozen at `0.8.60`, has SHA-256 `971c825b2010907b7285a949abad37c0484a4f76db94bdeef55b8dd45a8441cc`.

`EMMAKE Field Pilot Pack CI` must pass only when those exact hashes agree with `release/emmake-phase10e-candidate.json`. Weakening or bypassing the deterministic hash gate is forbidden.

Repository CI remains the technical gate. Real `/nuevaweb/` browser/product acceptance remains a separate human gate.

## Architecture and field evidence

Earlier candidates proved Rescue Manifest, Clone Reset, Corporate bootstrap, clean Home creation, content hydration, native SEO/GEO handoff and Step 7 machine readiness on `/nuevaweb/`.

Corporate v4.3 passed repository-side technical gates but failed the human visual/architectural acceptance gate. Corporate v5 therefore moved premium strategic rendering out of Gutenberg master-layout authority and into a Theme-owned semantic server renderer.

Corporate v5 A1 established the renderer/model boundary. Corporate v5 A2 corrected strategic chrome and presentation ownership. Corporate v5 A3 refined the palette after real `/nuevaweb/` findings. Corporate v5 A3.1 closed the first bounded visual/asset treatment. Corporate v5 A3.2 now integrates the approved real media without changing semantic content or SEO/GEO ownership.

A3.2 does **not** change Reset, hydration, Migration Bridge, content, URLs or SEO/GEO ownership.

The permanent ownership rule remains:

> **Gutenberg provides editorial autonomy. SEO/GEO Manager provides automation and growth. SEO/GEO Theme provides frontend rendering, strategic chrome, design, semantic HTML and performance.**

WordPress remains the CMS/resource authority.

Canonical architecture: `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`.

## Corporate v5 repository baseline

The v5 strategic master surface does not depend on:

- Gutenberg `contentSize` / `wideSize` as page-width authority;
- `is-layout-constrained` for master sections;
- `wp-block-post-content` as the strategic layout surface;
- nested layout blocks as the renderer contract;
- database-overridable block template parts for Corporate strategic chrome;
- the legacy Corporate v4 visual stylesheet in v5 client delivery;
- remote frontend visual dependencies;
- project frontend JavaScript for the strategic Home.

The accepted repository baseline preserves semantic header/main/footer landmarks, one strategic H1, server-side dynamic Insights, keyboard/focus and reduced-motion behavior, responsive/reflow automation, WordPress smoke/upgrade/rollback, PHP quality, self-contained deterministic packaging and native SEO/GEO output ownership.

## SEO/GEO Manager relationship

SEO/GEO Manager is a separate future plugin product and is not required for Theme stable runtime.

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

The next field action is one bounded Theme-only install of the exact A3.2 candidate on `/nuevaweb/` after all repository gates are green.

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

Item 1 is satisfied by the frozen Corporate v5 A3.2 identity above once deterministic CI reconfirms it. Items 2–10 remain field/acceptance work. Repository CI cannot satisfy them by inference.

Until production acceptance exists, `release/stable-release-decision.json` remains `decision=no-go`, real-site acceptance remains `pending`, its reference remains `null`, and blocker `real-site-production-acceptance-pending` stays present.

## Product boundary

SEO/GEO Manager is a separate plugin product. It is not a mandatory dependency or blocker for Theme `0.1.1` stable runtime.

Migration Bridge is the accepted migration/reset implementation for this Theme pilot. Migration Bridge `0.8.60` remains frozen for the current field candidate.

The standalone Core wrapper remains `deprecated-retained-nondistributed` and is not included in the self-contained Theme release ZIP.

## Automated decision contract

`scripts/ci/validate-stable-release-decision.py` enforces decision/version consistency, canonical candidate identity, pending evidence for NO-GO, changelog state and the Core wrapper distribution boundary.

`EMMAKE Field Pilot Pack CI` rebuilds Theme, Migration Bridge and the field pack byte-for-byte and verifies their SHA-256 identities against `release/emmake-phase10e-candidate.json`.

Corporate v5 A1/A2/A3/A3.1 remain historical technical/field evidence. Corporate v5 A3.2 is the current frozen technical field candidate and still requires exact `/nuevaweb/` browser and visual acceptance before stable or production decisions.

## Promotion from no-go to go

Only after real sandbox and production acceptance: record bounded evidence, clear blockers, change real-site acceptance to `accepted`, move the release channel to `stable`, release the changelog entry, rerun all required gates and publish only if they remain green.

No production evidence is fabricated or inferred from repository CI.
