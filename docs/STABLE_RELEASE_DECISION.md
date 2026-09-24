# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

The target remains `0.1.0` with `release_channel=prestable`.

Technical implementation, release packaging, onboarding, upgrade/rollback, client-delivery and production-readiness contracts are complete through Phase 10D. The remaining blocker is deliberately external to repository-only acceptance:

- a selected real WordPress site must complete sandbox validation;
- the accepted candidate must then complete the bounded production verification from Phase 10D;
- the resulting real-site acceptance must be recorded by reference without copying secrets/private payloads into the repository.

No real-site production acceptance is fabricated by this repository.

## Transitional Core wrapper decision

The standalone `packages/seo-geo-core/seo-geo-core.php` wrapper is:

**deprecated-retained-nondistributed**

Meaning:

- deprecated for new installation/deployment paths;
- retained temporarily in source for compatibility/development workflows;
- not a baseline dependency;
- not included in the deterministic single-theme release ZIP;
- not required by self-contained, onboarding, upgrade or production-readiness acceptance;
- removal is deferred until after the first stable-release acceptance confirms no remaining supported workflow depends on the wrapper.

New client deployments must use the self-contained theme artifact rather than installing the Core wrapper.

## Why retain it for now

Removing the wrapper before the first real-site acceptance would not simplify the distributed artifact because the wrapper is already absent from that artifact. It would only remove a compatibility/development entry point before the final field validation is complete.

Retention therefore has no production-runtime cost while preserving a controlled fallback for development during the first stable-release gate.

## Automated decision contract

`release/stable-release-decision.json` is the machine-readable Phase 10E decision.

`scripts/ci/validate-stable-release-decision.py` enforces:

- target version consistency with `release/version.json`;
- `no-go` requires `release_channel=prestable`;
- `go` requires `release_channel=stable`;
- a stable `go` requires real-site acceptance status `accepted` and a non-empty bounded reference;
- a `no-go` must keep at least one explicit blocker;
- wrapper disposition must match the actual repository/distribution boundary;
- changelog state must match the decision.

Release Artifact CI and Foundation CI both enforce this contract.

## Promotion from no-go to go

Only after real-site sandbox + production acceptance:

1. complete the client-owned production acceptance record using the Phase 10D schema;
2. store only a bounded evidence reference in `release/stable-release-decision.json`;
3. change `real_site_acceptance.status` to `accepted`;
4. clear all blockers;
5. change `decision` to `go`;
6. change `release/version.json` from `prestable` to `stable`;
7. convert the target changelog entry from Unreleased to a released `0.1.0` entry;
8. run Foundation + Release Artifact CI and all gates required by any code/content changes made during the real-site acceptance cycle;
9. publish a stable release only after those gates are green.

A successful repository CI run without the real-site acceptance reference is insufficient to promote the release.
