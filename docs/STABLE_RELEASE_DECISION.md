# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.1`.

Release channel: `prestable`.

The migration/reset/hydration/SEO-GEO engine, deterministic packaging, onboarding, upgrade/rollback and the Corporate v5 Theme-owned renderer boundary are working. Corporate v5 A1 proved the renderer/model boundary. Corporate v5 A2 proved clean Theme-owned strategic chrome and removed inherited v4 presentation debt. Corporate v5 A3 lightened the real-site visual system. The current exact field candidate is **Corporate v5 A3.1 with the C6.6B strategic-media binding extension, the bounded native translation-group writer contract and the bounded native SEO metadata writer contract**, preserving the accepted visual closure while adding safe reciprocal multilingual relationship persistence and controlled per-resource SEO metadata persistence inside the bundled native core.

A3.1 keeps the accepted A2/A3 architecture and hydrated content state intact while:

- compacting the hero so the primary actions fit the practical desktop first viewport;
- adding a real local hero visual slot;
- allowing the declared `hero-media` slot to bind an existing WordPress image through Theme-owned structured state, with the local SVG retained as the safe fallback;
- adding stable capability visual treatment without external dependencies;
- strengthening Method hierarchy and process continuity;
- allowing the featured Insights card to prefer a WordPress featured image with a local Theme fallback;
- adding a reusable local editorial visual library for future article/publication use;
- preserving zero required project frontend JavaScript and zero third-party requests;
- preserving the existing Reset, hydration, URL, content, SEO/GEO and Migration Bridge state.

The bundled native core additionally exposes a bounded translation-group writer that persists only validated reciprocal relationships and leaves public hreflang authority with the existing native registry. It also exposes the verified `NativeSeoMetadataWriter` authority used by SEO/GEO Manager `0.3.47` for complete replacement of bounded per-resource title/description, canonical and indexability override state. In this first C7 write slice the Manager exposes only `title-meta`, `canonical` and `robots` as writable. Open Graph, Schema, hreflang and sitemap remain read-only until separate verified mutation contracts exist, and detected external SEO providers remain read-only and block Theme-native mutation. This re-freeze changes deterministic package bytes but does not change the accepted A3.1 visual composition, content, URLs, Reset/hydration behavior, Migration Bridge or the rule that exactly one authority owns each public SEO output.

Stable promotion remains blocked by real-site product acceptance. Repository CI, deterministic hashes and fixture performance are necessary evidence but cannot declare the visual product accepted.

SaaS, Local Pro, Publisher and Ecommerce master-surface rollout remains frozen until Corporate v5 passes the real sandbox gate.

## Canonical Phase 10E candidate

The machine-readable source of truth remains:

`release/emmake-phase10e-candidate.json`

Frozen Corporate v5 A3.1 technical identity:

- Theme implementation source commit: `6a397d5766f90c854e1d73b5c57c023e33289b38`;
- Theme: `0.1.1` / `prestable`;
- Theme ZIP SHA-256: `0febf1f59e2ebbb6fd85d004ede4864c81030ae0e69e08b645322bba7583da51`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `60936489dd080c1902d9cc8d0f0ff02a3d13db7d9068ed9beabdcb2458032cd9`.

This identity is reproducible technical evidence for the next `/nuevaweb/` field pass. It is **not** an accepted stable or production candidate. Any Theme implementation change requires a new deterministic Theme SHA and a newly frozen candidate before field installation.

The candidate metadata may be committed after the implementation source commit without changing the Theme package. `source_commit` intentionally identifies the implementation bytes being frozen rather than self-referencing the metadata commit.

## A3.1 repository acceptance

The exact A3.1 implementation, including the optional Theme-owned strategic hero-media binding, bounded native translation-group writer and bounded native SEO metadata writer, passed the repository gates for:

- Foundation;
- Design System;
- Corporate Page Pipeline;
- Phase 1 Package;
- Release Artifact;
- PHP Quality / WPCS / PHPStan level 6;
- Native Multilingual;
- Self-contained zero-plugin Theme runtime;
- WordPress 7.1 / PHP 8.2 smoke;
- Accessibility & Responsive acceptance;
- Performance Baseline.

The Corporate performance fixture on the frozen implementation recorded:

- Lighthouse performance: `100`;
- FCP: `751.72 ms`;
- LCP: `901.72 ms`;
- CLS: `0`;
- TBT: `0 ms`;
- Speed Index: `751.72 ms`;
- transferred bytes: `28,466`;
- Corporate CSS bytes: `6,520`;
- image bytes: `2,794`;
- total requests: `8`;
- third-party requests: `0`;
- project JavaScript bytes: `0`;
- DOM nodes: `118`.

A3.1 originally exposed a useful product-budget regression: five decorative SVG data URIs were counted as extra image requests. The implementation was corrected rather than weakening the budget. Those decorative icons now use local CSS/pseudo-element treatment while the hero and Insights retain their meaningful media slots. The final implementation satisfies the existing eight-request Corporate budget.

`EMMAKE Field Pilot Pack CI` is expected to pass only after the candidate record is aligned with the exact A3.1 Theme and field-pack hashes above. A red run produced solely by the previous frozen identity is not a Theme/runtime regression; weakening or bypassing the deterministic hash gate is forbidden.

## Architecture and field evidence

Earlier candidates proved Rescue Manifest, Clone Reset, Corporate bootstrap, clean Home creation, content hydration, native SEO/GEO handoff and Step 7 machine readiness on `/nuevaweb/`.

Corporate v4.3 passed repository-side technical gates but failed the human visual/architectural acceptance gate. Corporate v5 therefore moved premium strategic rendering out of Gutenberg master-layout authority and into a Theme-owned semantic server renderer.

Corporate v5 A1 established the renderer/model boundary. Corporate v5 A2 corrected strategic chrome and presentation ownership. Corporate v5 A3 refined the palette after real `/nuevaweb/` findings. Corporate v5 A3.1 closes the remaining bounded visual/asset treatment before the next single sandbox installation.

A3.1 does **not** change Reset, hydration, Migration Bridge, content, URLs or SEO/GEO ownership. C6.6B only adds a versioned Theme-owned binding for declared strategic media resources; the Theme remains the rendering authority and the local SVG remains the fallback when no valid binding exists. The native translation-group writer only provides validated relationship persistence for the already-existing native multilingual registry and does not become a second hreflang authority. The native SEO metadata writer persists only the bounded override state consumed by the existing Core SEO authority; it does not create a second SEO provider or bypass output-authority resolution.

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

SEO/GEO Manager is a separate plugin product and is not required for Theme stable runtime.

The v5 renderer boundary supports the Manager bridge/control-plane direction:

- external orchestration decides strategy, research, content, optimization and which bounded change should happen;
- Manager inspects WordPress/Theme capabilities and safely applies approved structured operations;
- strategic landing content is written as semantic models rather than visual Gutenberg layout trees;
- Theme renders those models and declared strategic media slots;
- complete automated articles remain normal WordPress posts editable by authorized users in Gutenberg;
- Manager handles inspect/preview/apply/publish/verify/history/rollback primitives;
- exactly one SEO/GEO output owner remains active per signal.

Manager `0.3.47` adds the first bounded C7 write path for the Theme-native authority: per-resource title/description, canonical and indexability changes use Inspect/Preview/Apply/exact verification/history/stale-safe rollback, optimistic fingerprints, idempotency and environment binding. This does not imply that every SEO output or external provider is writable; unsupported signals and external provider adapters remain read-only until independently verified.

## Selected real-site pilot

Production origin: `https://emmake.com/`.

Sandbox: `https://emmake.com/nuevaweb/`.

The sandbox already has a hydrated clean Home state and Step 7 readiness. **Do not** rerun Reset, regenerate the Home, rehydrate content or change Migration Bridge merely because Theme presentation changes.

The next field action is one bounded Theme-only install of the exact A3.1 candidate on `/nuevaweb/`.

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

Item 1 is satisfied by the frozen Corporate v5 A3.1 identity above once deterministic CI reconfirms it. Items 2–10 remain field/acceptance work. Repository CI cannot satisfy them by inference.

Until production acceptance exists, `release/stable-release-decision.json` remains `decision=no-go`, real-site acceptance remains `pending`, its reference remains `null`, and blocker `real-site-production-acceptance-pending` stays present.

## Product boundary

SEO/GEO Manager is a separate plugin product. It is not a mandatory dependency or blocker for Theme `0.1.1` stable runtime.

Migration Bridge is the accepted migration/reset implementation for this Theme pilot. Migration Bridge `0.8.60` remains frozen for the current field candidate.

The standalone Core wrapper remains `deprecated-retained-nondistributed` and is not included in the self-contained Theme release ZIP.

## Automated decision contract

`scripts/ci/validate-stable-release-decision.py` enforces decision/version consistency, canonical candidate identity, pending evidence for NO-GO, changelog state and the Core wrapper distribution boundary.

`EMMAKE Field Pilot Pack CI` rebuilds Theme, Migration Bridge and the field pack byte-for-byte and verifies their SHA-256 identities against `release/emmake-phase10e-candidate.json`.

Corporate v5 A1/A2/A3 remain historical technical/field evidence. Corporate v5 A3.1 is the current frozen technical field candidate and still requires exact `/nuevaweb/` browser and visual acceptance before stable or production decisions.

## Promotion from no-go to go

Only after real sandbox and production acceptance: record bounded evidence, clear blockers, change real-site acceptance to `accepted`, move the release channel to `stable`, release the changelog entry, rerun all required gates and publish only if they remain green.

No production evidence is fabricated or inferred from repository CI.
