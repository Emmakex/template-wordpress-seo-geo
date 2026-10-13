# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.2`.

Release channel: `prestable`.

The repository-side EMMAKE product build is complete for the current field candidate, but repository CI is not a substitute for real `/nuevaweb/` browser and product acceptance. Stable promotion remains blocked by the explicit real-site acceptance gate.

SaaS, Local Business, Publisher and Ecommerce master-surface rollout remains frozen until the EMMAKE Corporate candidate passes that field gate.

## Canonical Phase 10E candidate

The machine-readable source of truth is:

`release/emmake-phase10e-candidate.json`

Frozen Corporate v5 A3.2 technical identity:

- Theme implementation/source freeze commit: `10c80cebddfe9b5956b9f51f38de53318d8b6053`;
- Theme: `0.1.2` / `prestable`;
- Theme ZIP SHA-256: `111cfbe772387471b4388e432d3258ae5b5dd26e7a0b5b9d411dc36750d5ab3e`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `3bd5b14f0c4c094abf60baba97f3aa6d515c5921f7b3cc58b54e2859b74999b2`.

The candidate metadata is intentionally committed after the source freeze. `source_commit` identifies the implementation and field-pack source bytes being frozen rather than self-referencing the metadata commit.

This identity is reproducible technical evidence for the next `/nuevaweb/` field pass. It is **not** an accepted stable or production candidate.

## What A3.2 closes

Corporate v5 A3.2 keeps the renderer/model boundary proven by A1–A3.1 and completes the EMMAKE visual/media system without returning layout authority to Gutenberg.

The strategic Corporate Home now supports Theme-declared image slots for hero, three capabilities, process/method, three Insights fallbacks and the final CTA. The public renderer consumes those slots as normal responsive WordPress image attachments. When no valid binding exists, existing safe Theme fallbacks remain available. For Insights, the WordPress Featured Image remains authoritative; the strategic slot is only the bounded fallback.

A3.2 also closes the final presentation layer for those images: premium card/media treatment, stronger method composition, richer Insights hierarchy, media-aware final CTA, responsive recomposition and reduced-motion behavior. It does not introduce a page builder, remote visual dependency or required project frontend JavaScript.

The installable Theme release bundles the neutral Theme foundation, Corporate v5 runtime, A3.1 and A3.2 into the existing single Corporate v5 CSS request.

## Manager completion relationship

SEO/GEO Manager remains a separate plugin product and is not required for Theme runtime. Its role is the permanent WordPress control plane.

The EMMAKE completion block reaches Manager `0.3.73` and provides the repository-side operating surfaces needed for the site: Build / Finish semantic Theme editing, normal page/post editing, SEO authority, contextual links, navigation, Featured Media, image ALT, Operation Evidence and Safe Recovery, Optimization Queue, draft-first Grow Workspace, guarded direct image upload, Theme Strategic Media assignment and explicit publish/schedule workflow.

Manager `0.3.73` adds the missing strategic-media catalog. The Theme Media workspace no longer depends on `page-models.json`; it discovers resolved strategic pages through the active Theme contract and image slots through `seo_geo_theme_strategic_media_contract`. Therefore `corporate-home-v1` and its A3.2 image slots are directly operable from wp-admin without exposing raw Theme metadata or transferring frontend ownership to Manager.

Strategic-media mutations continue through Preview → Apply → readback/Verify → Evidence → stale-safe Rollback with environment binding and idempotency. Safe Recovery remains allowlisted to type-specific adapters; there is no generic mutation or generic rollback endpoint.

## Ownership boundary

The permanent ownership rule is:

> **Gutenberg provides editorial autonomy. SEO/GEO Manager provides controlled automation and growth. SEO/GEO Theme provides frontend rendering, strategic chrome, design, semantic HTML and performance.**

WordPress remains the CMS/resource authority. External orchestration remains the strategic/research/content intelligence layer.

The Corporate master Home does not depend on `wp-block-post-content` as the strategic master-layout surface, `is-layout-constrained` as master composition authority, Gutenberg `contentSize` / `wideSize` as Corporate width authority, nested layout blocks as renderer contract, database-overridable block template parts for strategic chrome, legacy Corporate v4 presentation in v5 client delivery, remote frontend visual dependencies or project frontend JavaScript for the strategic Home.

Canonical architecture: `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`.

## Existing migration evidence remains valid

The EMMAKE clone, Rescue Manifest, Clone Reset, Corporate bootstrap, clean Home creation, rescued/client-content hydration and SEO/GEO handoff are already established evidence.

The A3.2 presentation and Manager completion work must **not** cause Reset, clone creation, Home regeneration or content rehydration to be repeated. Migration Bridge `0.8.60` stays frozen unless a concrete migration defect is proven.

The replatforming rule remains: preserve authored content, URLs/redirects, valid SEO signals, important links, useful media, verified organization/contact facts and required business behavior; replace legacy theme/builder presentation debt rather than copying it into the rebuilt site.

## Repository acceptance boundary

The final repository gate covers the frozen A3.2 implementation and Manager completion block for Foundation, Design System, Corporate Page Pipeline, Phase 1 Package, Release Artifact, PHP Quality / WPCS / PHPStan level 6, Native Multilingual, self-contained zero-plugin Theme runtime, WordPress smoke, Accessibility & Responsive acceptance, Performance Baseline, deterministic EMMAKE Field Pilot Pack and the Manager WordPress / Workspace / Release / Remote Bridge / C9 / Navigation / Featured Media / Media ALT / Operation Evidence / Safe Recovery contracts.

A green repository gate proves reproducibility and technical integrity only. It does not satisfy visual/product acceptance on the real sandbox.

## Selected real-site pilot

Production origin: `https://emmake.com/`.

Sandbox: `https://emmake.com/nuevaweb/`.

The sandbox already contains the rescued/hydrated clean state. The next field action is a bounded install of the exact frozen Theme `0.1.2` candidate and the accepted Manager candidate on `/nuevaweb/`, followed by browser/product QA. Do not reset or rehydrate the site merely because the Theme/Manager binaries changed.

## Remaining stable-release blocker

Phase 10E remains `no-go` until all of the following are real and recorded:

1. the deterministic Corporate v5 A3.2 candidate is frozen;
2. that exact candidate is installed only on `/nuevaweb/`;
3. the existing rescued/hydrated semantic content state remains intact;
4. native SEO/GEO handoff has no unresolved blocker;
5. machine readiness remains valid;
6. browser QA passes at `1440 / 1024 / 768 / 390`, with `320` retained as a compact stress case;
7. visual layout, responsive behavior, accessibility, SEO/GEO rendered output and measured performance pass;
8. the Corporate Home receives explicit product-quality approval at the premium/WOW standard;
9. sandbox acceptance is explicit before any production-entry decision;
10. later production verification completes without a rollback trigger.

Item 1 is repository work. Items 2–10 require actual field evidence and cannot be inferred from CI.

Until that evidence exists, `release/stable-release-decision.json` remains `decision=no-go`, real-site acceptance remains `pending`, its reference remains `null`, blocker `real-site-production-acceptance-pending` remains present and `release/version.json` remains `release_channel=prestable`.

## Historical Corporate progression

A1 established the Theme renderer/model boundary. A2 moved strategic header/footer and presentation fully under Theme authority. A3 lightened and refined the real-site visual system. A3.1 closed the first bounded media treatment and repository performance budget. A3.2 expands that bounded media model across the real EMMAKE Home and completes the Manager-side strategic-media discovery/operation path.

The prior A3.1 repository baseline remains useful historical performance evidence: Lighthouse `100`, FCP `751.72 ms`, LCP `901.72 ms`, CLS `0`, TBT `0 ms`, eight Corporate requests, zero third-party requests, zero project JavaScript bytes and 118 DOM nodes. A3.2 must satisfy the active performance gates; those historical numbers are not substituted for the new run.

## Product boundary

SEO/GEO Manager is not a mandatory dependency for Theme stable runtime. Migration Bridge is not a permanent site dependency. The standalone Core wrapper remains `deprecated-retained-nondistributed`; the self-contained Theme bundles the runtime it needs.

The exact 0.1.2 release boundary is enforced by `release/version.json`, `release/stable-release-decision.json`, `release/emmake-phase10e-candidate.json`, the deterministic package builders and CI acceptance contracts.

## Promotion from no-go to go

Only after real sandbox and production acceptance: record bounded evidence, clear blockers, change real-site acceptance to `accepted`, move the release channel to `stable`, release the changelog entry, rerun the required gates and publish only if they remain green.

No production evidence is fabricated or inferred from repository CI.
