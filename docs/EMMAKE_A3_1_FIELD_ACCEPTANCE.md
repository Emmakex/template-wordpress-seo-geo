# EMMAKE Corporate v5 A3.1 field acceptance

Status: **active E1 field runbook**

Date: 2026-10-12

Parent execution lock: `docs/EMMAKE_COMPLETION_SCOPE.md`.

Tracking issue: #273.

## Purpose

This runbook closes E1 for the already prepared EMMAKE sandbox at `https://emmake.com/nuevaweb/`.

It is intentionally narrower than the historical `EMMAKE_HOME_FIELD_PILOT.md` contained in the frozen field-pilot pack. The historical pilot runbook proves how the sandbox was originally reset, rebuilt and hydrated. **Do not repeat those bootstrap steps now.** The current `/nuevaweb/` state is already the clean/hydrated state that must be preserved while the exact A3.1 Theme candidate is accepted or rejected.

## Precedence for this field pass

For the current E1 acceptance pass, this document supersedes any historical instruction that says to run Reset & Rebuild, apply Clone Reset, regenerate the clean Home draft, rerun content blueprint import, rerun native hydration, or rerun the SEO handoff merely to test presentation.

## Frozen candidate identity

- target: `https://emmake.com/nuevaweb/`;
- Theme version: `0.1.1`;
- release channel: `prestable`;
- Theme implementation source: `c2396b0aa0a17f2ba8b6cfc4d8c7435ff884fa00`;
- Theme ZIP SHA-256: `f4c7074dd3105a04375a212845b54c5bec0e8782870d62df041a6086a36a584f`;
- Migration Bridge version: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- field-pilot pack SHA-256: `1736d8b6dfb9aeec8cc8132a4ee1c42d5d9e4a3afb4510934edfd0cecd5a9fe2`.

Frozen pack CI provenance:

- freeze commit: `08be115f6841ae2f59ac7dcd47f7977b8cb829b0`;
- `EMMAKE Field Pilot Pack CI` run `37650586721` / #317: `success`;
- artifact ID: `11495474298`;
- artifact wrapper SHA-256: `b3f1c0baaba5efc2d2ee24a1ac33258c864bdd2cad939aaab4f146357f6e3a63`;
- artifact expires: `2026-10-21T16:15:29Z`.

The downloaded artifact was independently checked on 2026-10-12: the internal field pack and both nested product ZIP hashes match the frozen identity.

## E1 execution — install only

### Pre-install evidence

Before changing the Theme retain current active Theme/version, Home resource identity, front-page assignment, permalink state, visible Home/model state, SEO/GEO authority state, and a reversible sandbox backup/snapshot. Do not change production `https://emmake.com/`.

### Candidate integrity

Use only the nested `seo-geo-theme.zip` with SHA-256 `f4c7074dd3105a04375a212845b54c5bec0e8782870d62df041a6086a36a584f`. Reject the field pass if the hash differs.

### Theme-only update

On `/nuevaweb/`:

1. install/update the exact Theme candidate;
2. activate/retain SEO/GEO Theme as appropriate;
3. do **not** execute Reset & Rebuild;
4. do **not** run Clone Reset;
5. do **not** import the content blueprint again;
6. do **not** hydrate Home again;
7. do **not** change production;
8. do **not** change URLs/content merely to make the visual review pass.

If activation unexpectedly changes resource identity, front-page assignment, content state or SEO authority, stop and record a field defect instead of repairing it manually.

## Browser/product QA

Review the real sandbox at `1440`, `1024`, `768` and `390`; retain `320` as automated stress coverage.

At every required viewport inspect header/alignment, logo, navigation, mobile-menu open/close behavior, close button/outside-click/focus behavior, centered mobile CTA text, hero geometry and visual crop, capabilities/services, Method/process, Insights media, final CTA, footer, typography/spacing/overflow, links/tap targets and absence of legacy layout leakage.

## Technical acceptance

Confirm one coherent Theme-owned shell; valid semantic heading order; valid canonical/internal links; accepted SEO/GEO output authority; no accidental noindex/canonical regression; no accessibility or horizontal-overflow regression; no material performance regression; and no new third-party runtime dependency.

Repository reference baseline: Lighthouse `100`, FCP `751.72 ms`, LCP `901.72 ms`, CLS `0`, TBT `0`, `8` Corporate requests, `0` third-party requests, `0` project JavaScript bytes and `118` DOM nodes. Real hosting variance is allowed; unexplained architectural/runtime regression is not.

## Evidence/result

Record `accepted` only when the premium/WOW gate passes with no blocking regression. Otherwise record `rejected` and capture each defect with viewport, component, observed behavior, expected behavior, evidence, ownership category and severity.

Do not broaden scope while fixing a rejected candidate. Apply the smallest correction, rerun relevant repository gates, freeze a new exact identity and repeat this field pass.

## E1 close condition

E1 closes only when the exact installed candidate is explicitly accepted on the real `/nuevaweb/` sandbox and the result is recorded in #273. Repository CI alone does not close E1.
