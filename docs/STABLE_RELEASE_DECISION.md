# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.1`.

Release channel: `prestable`.

The Theme, five-preset Design System, packaging, onboarding, upgrade/rollback, client delivery and repository-side production-readiness contracts are complete. The remaining blocker is real-site acceptance on EMMAKE followed by bounded production verification.

A green repository build alone is not sufficient to declare stable.

## Canonical Phase 10E candidate

The machine-readable source of truth is:

`release/emmake-phase10e-candidate.json`

The currently frozen field candidate is:

- source commit: `d3f4022ff2f46c2ee965bae561217b990bf2edf0`;
- Theme: `0.1.1` / `prestable`;
- Theme ZIP SHA-256: `313796fde03e0204a334d204098222e5d0521b9efeb2ec622fbd92c0522b8c15`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `6a84f39d2a5944eec57d60b74f09171a1e40303f4a53732bd922c0b5b4b01999`.

The repository may advance with documentation or validation-only commits after the source commit above. That does not silently change the frozen field artifact. Any Theme, Migration Bridge, runbook, blueprint or pack-content change requires a new deterministic pack and an explicit update of `release/emmake-phase10e-candidate.json`.

`EMMAKE Field Pilot Pack CI` rebuilds the package and rejects any version or SHA drift from this canonical record.

## Selected real-site pilot

Production origin: `https://emmake.com/`.

Sandbox: `https://emmake.com/nuevaweb/`.

The working `/nuevaweb/` clone already proved the product-owned clone path and historical Divi-to-native migration work. Those milestones are useful evidence, but they do not substitute for acceptance of the frozen Theme `0.1.1` candidate.

The current field sequence is defined in `docs/EMMAKE_HOME_FIELD_PILOT.md` and summarized in `docs/REAL_SITE_PILOT.md`.

## Remaining stable-release blocker

Phase 10E remains `no-go` until all of the following are real and recorded:

1. the frozen candidate is installed only on the isolated `/nuevaweb/` sandbox;
2. Reset & Rebuild completes without carrying legacy presentation debt;
3. the Corporate preset is hydrated from rescued/client facts and content;
4. native SEO/GEO handoff has no unresolved review blocker;
5. field-pilot Step 7 reports `ready_for_browser_qa=true`;
6. browser QA passes visual, responsive, accessibility, SEO/GEO rendered-output and performance checks;
7. the sandbox is explicitly accepted for controlled production entry;
8. production verification completes without a rollback trigger;
9. only a bounded acceptance reference, never credentials or private payloads, is stored in the repository.

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
9. run Foundation, Release Artifact, field-pilot and any affected quality gates again;
10. publish stable only when all required checks are green.

No production evidence is fabricated or inferred from repository CI.
