# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.1`.

Release channel: `prestable`.

The migration/reset/hydration/SEO-GEO engine, packaging, onboarding, upgrade/rollback, client delivery and repository-side production-readiness contracts are working. The first real browser QA on EMMAKE rejected the previous Corporate visual candidate, so Corporate v3 is now the master visual preset and remains the active release blocker.

SaaS, Local Pro, Publisher and Ecommerce visual iteration remains frozen until Corporate v3 is approved on the real sandbox. A green repository build alone is not sufficient to declare stable.

## Canonical Phase 10E candidate

The machine-readable source of truth is:

`release/emmake-phase10e-candidate.json`

The currently frozen Corporate v3 field candidate is:

- source commit: `8d983183be02b38dece4b2b68c447c68c107d98d`;
- Theme: `0.1.1` / `prestable`;
- Theme ZIP SHA-256: `376a2775dc243e2fbfb38b88a2d0b0d19b63a392bef6f5ab30a4938b98b51ebe`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `790d560db976caa19365c6d6170c9ce235b68341fbb707d3994b40b16231ce24`.

The repository may advance with documentation or validation-only commits after the source commit above. That does not silently change the frozen field artifact. Any Theme, Migration Bridge, runbook, blueprint or pack-content change requires a new deterministic pack and an explicit update of `release/emmake-phase10e-candidate.json`.

`EMMAKE Field Pilot Pack CI` rebuilds the package and rejects any version or SHA drift from this canonical record.

## Why the candidate changed

The earlier `0.1.1` field candidate successfully completed Rescue Manifest, Clone Reset, Corporate bootstrap, clean Home creation, content hydration, native SEO/GEO handoff and Step 7 machine readiness on `/nuevaweb/`.

Its real browser presentation was **not accepted**. The field screenshot exposed generic card composition, weak tablet behavior, poor long-heading wrapping and insufficient contrast in the method section. Those are reusable Theme defects, not EMMAKE-specific content defects.

The old candidate therefore remains useful technical evidence but is superseded for visual acceptance by the Corporate v3 candidate identified above.

## Selected real-site pilot

Production origin: `https://emmake.com/`.

Sandbox: `https://emmake.com/nuevaweb/`.

The working `/nuevaweb/` clone already proved the product-owned clone path, reset-first replatforming, hydration and native SEO handoff. Those milestones do not substitute for browser acceptance of Corporate v3.

The current field sequence is defined in `docs/EMMAKE_HOME_FIELD_PILOT.md` and summarized in `docs/REAL_SITE_PILOT.md`.

## Remaining stable-release blocker

Phase 10E remains `no-go` until all of the following are real and recorded:

1. the frozen Corporate v3 candidate is installed only on the isolated `/nuevaweb/` sandbox;
2. the already proven Reset & Rebuild state remains intact without carrying legacy presentation debt;
3. the Corporate Home remains hydrated from rescued/client facts and content;
4. native SEO/GEO handoff has no unresolved review blocker;
5. field-pilot Step 7 reports `ready_for_browser_qa=true`;
6. Corporate v3 browser QA passes visual, responsive, accessibility, SEO/GEO rendered-output and performance checks at the master acceptance widths `1440 / 1024 / 768 / 390`;
7. the Corporate Home is explicitly accepted as a product-quality master preset before Step 8 or inner-page rollout advances;
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

Corporate v3 also has a dedicated visual-contract CI gate and browser acceptance now includes the product master widths in addition to the legacy compact-mobile check.

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
