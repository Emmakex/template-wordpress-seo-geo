# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.1`.

Release channel: `prestable`.

The migration/reset/hydration/SEO-GEO engine, deterministic packaging, onboarding, upgrade/rollback and repository-side technical contracts are working. Corporate v5 A2 now has a frozen deterministic Theme-owned frontend candidate whose browser, accessibility, responsive, performance, smoke and quality gates pass. The remaining blocker is real-site visual/product acceptance of that exact candidate on the isolated EMMAKE `/nuevaweb/` sandbox.

Corporate v5 A1 proved the strategic renderer/model boundary, but the first real `/nuevaweb/` screenshot still failed the human premium/WOW gate. It also exposed that database-overridable block template parts could leak legacy/custom header/footer presentation into the strategic document, and that carrying the v4 Corporate stylesheet into v5 preserved unnecessary presentation debt.

A2 therefore makes the Theme own the entire Corporate strategic chrome, removes the legacy Corporate v4 visual stylesheet from v5 requests/releases, and rebuilds the Corporate presentation as a clean v5 visual system. This is a field-candidate improvement, not stable acceptance.

SaaS, Local Pro, Publisher and Ecommerce visual iteration remains frozen until the Corporate v5 renderer is accepted on the real sandbox. Repository CI alone cannot declare the visual product accepted.

## Canonical Phase 10E candidate

The machine-readable source of truth remains:

`release/emmake-phase10e-candidate.json`

Frozen Corporate v5 A2 technical identity:

- source commit: `df63d7c4d05cd5eee5440e85a4232b8404e489d7`;
- Theme: `0.1.1` / `prestable`;
- Theme ZIP SHA-256: `fca2ae8cc64304051713e4a37601b3af49bfcc153d0bc3c69e24acdeed8ab340`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `f490d3e36cdd484c521bf9721e080a157822f91b16d54bffce9418af6ed6de5d`.

This identity is reproducible technical evidence and is the only Corporate v5 candidate authorized for the next `/nuevaweb/` field pass. It is **not** an accepted stable or production candidate. Any Theme-content change requires a new deterministic Theme SHA and a newly frozen candidate before field installation.

## Architecture and field evidence

Earlier candidates proved Rescue Manifest, Clone Reset, Corporate bootstrap, clean Home creation, content hydration, native SEO/GEO handoff and Step 7 machine readiness on `/nuevaweb/`.

Corporate v4 established the premium/WOW art direction, but v4.3 real-site evidence showed that a premium strategic Home should not depend on Gutenberg as its master layout engine. Corporate v5 A1 then moved the Home to a Theme-owned semantic server renderer.

The A1 real-site screenshot confirmed the new master-section alignment direction but rejected the visual result. The main field defects were:

- a broken strategic header caused by database-overridable `block_template_part()` state;
- excessive empty space and weak visual balance in the hero;
- capability/service hierarchy that still looked template-like;
- residual list markers in Insights;
- a final CTA inheriting a default WordPress blue button;
- a weak residual footer;
- continued visual debt from the previous Corporate stylesheet.

Corporate v5 A2 corrects these at the architectural presentation boundary rather than adding another selector patch layer.

The permanent ownership rule remains:

> **Gutenberg provides editorial autonomy. SEO/GEO Manager provides automation and growth. SEO/GEO Theme provides frontend rendering, strategic chrome, design, semantic HTML and performance.**

Canonical architecture: `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`.

## Corporate v5 A2 repository evidence

A2 renders the strategic Home from the existing semantic content model/state and owns the strategic header, Home and footer server-side while keeping WordPress as CMS/resource authority.

The strategic master surface no longer depends on:

- Gutenberg `contentSize` / `wideSize` as page-width authority;
- `is-layout-constrained` for master sections;
- `wp-block-post-content` as the strategic layout surface;
- nested layout blocks as the renderer contract;
- database-overridable block template parts for Corporate strategic chrome;
- the legacy Corporate v4 visual stylesheet in v5 client delivery.

Repository acceptance for the frozen A2 source confirms:

- semantic header/main/footer landmarks and one strategic H1;
- keyboard/focus, reduced-motion and WCAG automated checks;
- responsive/reflow acceptance at `320 / 390 / 768 / 1024 / 1440`;
- WordPress 7.1 / PHP 8.2 smoke and upgrade/rollback acceptance;
- PHP Quality and PHPStan level 6;
- self-contained deterministic Theme packaging;
- zero project frontend JavaScript and zero third-party requests;
- Corporate Lighthouse performance score `100`;
- Corporate FCP/LCP `752.5 ms`, CLS `0`, TBT `0`;
- Corporate transferred CSS `4794` bytes;
- static Corporate CSS gzip proxy `4554 / 7100` bytes.

These are repository/fixture results. They do not substitute for the human visual field gate on `/nuevaweb/`.

## SEO/GEO Manager relationship

SEO/GEO Manager remains a separate future plugin product and is not required for Theme stable runtime.

The v5 renderer boundary supports the future Manager direction:

- Manager Landing Engine creates/updates structured strategic-page models rather than visual Gutenberg block trees;
- Theme renders those models;
- Manager Blog Engine can create complete blog posts automatically;
- automated blog posts remain normal WordPress posts editable by authorized users in Gutenberg;
- Manager handles draft/preview/schedule/publish/refresh/rollback;
- Theme continues to own the public article shell and strategic frontend presentation;
- exactly one SEO/GEO output owner remains active per signal.

## Selected real-site pilot

Production origin: `https://emmake.com/`.

Sandbox: `https://emmake.com/nuevaweb/`.

The sandbox already has a hydrated clean Home state and Step 7 readiness. **Do not** rerun Reset, regenerate the Home, rehydrate content or change Migration Bridge merely because the Theme presentation changed.

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

Item 1 is satisfied by the frozen Corporate v5 A2 identity above. Items 2–10 remain field/acceptance work; repository CI cannot satisfy them by inference.

Until production acceptance exists, `release/stable-release-decision.json` remains `decision=no-go`, real-site acceptance remains `pending`, its reference remains `null`, and blocker `real-site-production-acceptance-pending` stays present.

## Product boundary

SEO/GEO Manager is a separate plugin product and is not a mandatory dependency for Theme `0.1.1` stable runtime.

Migration Bridge `0.8.60` remains the accepted migration/reset implementation for this pilot.

The standalone Core wrapper remains `deprecated-retained-nondistributed` and is not included in the self-contained Theme release ZIP.

## Automated decision contract

`scripts/ci/validate-stable-release-decision.py` enforces decision/version consistency, canonical candidate identity, pending evidence for NO-GO, changelog state and the Core wrapper distribution boundary.

`EMMAKE Field Pilot Pack CI` rebuilds Theme, Migration Bridge and the field pack byte-for-byte and verifies their SHA-256 identities against `release/emmake-phase10e-candidate.json`.

Corporate v5 A1 remains historical field evidence. Corporate v5 A2 is the current frozen technical field candidate and still requires real `/nuevaweb/` visual/browser acceptance before any stable or production decision.

## Promotion from no-go to go

Only after real sandbox and production acceptance: record bounded evidence, clear blockers, change real-site acceptance to `accepted`, move the release channel to `stable`, release the changelog entry, rerun all required gates and publish only if they remain green.

No production evidence is fabricated or inferred from repository CI.
