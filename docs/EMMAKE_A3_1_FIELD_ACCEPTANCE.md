# EMMAKE Corporate v5 A3.1 field acceptance

Status: **active E1 field runbook**

Date: 2026-10-12

Parent execution lock: `docs/EMMAKE_COMPLETION_SCOPE.md`.

Tracking issue: #273.

## Purpose

This runbook closes E1 for the already prepared EMMAKE sandbox at:

`https://emmake.com/nuevaweb/`

It is intentionally narrower than the historical `EMMAKE_HOME_FIELD_PILOT.md` contained in the frozen field-pilot pack.

The historical pilot runbook proves how the sandbox was originally reset, rebuilt and hydrated. **Do not repeat those bootstrap steps now.** The current `/nuevaweb/` state is already the clean/hydrated state that must be preserved while the exact A3.1 Theme candidate is accepted or rejected.

## Precedence for this field pass

For the current E1 acceptance pass, this document supersedes any historical instruction that says to:

- run Reset & Rebuild;
- apply Clone Reset;
- regenerate the clean Home draft;
- rerun content blueprint import;
- rerun native hydration;
- rerun the SEO handoff merely to test presentation.

Those actions are out of scope unless a concrete field defect proves the sandbox state itself is invalid and the execution lock is explicitly updated.

## Frozen candidate identity

Source candidate manifest: `release/emmake-phase10e-candidate.json`.

Required identity:

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
- workflow: `EMMAKE Field Pilot Pack CI`;
- run: `37650586721` / run number `317`;
- result: `success`;
- artifact ID: `11495474298`;
- artifact wrapper SHA-256: `b3f1c0baaba5efc2d2ee24a1ac33258c864bdd2cad939aaab4f146357f6e3a63`;
- artifact expires: `2026-10-21T16:15:29Z`.

The downloaded artifact has been independently checked: its internal field pack SHA and both nested product ZIP SHA-256 values match the frozen candidate identity above.

## E1 execution — install only

### 1. Pre-install evidence

Before changing the Theme, retain:

- current `/nuevaweb/` URL;
- current active Theme/version;
- current Home resource identity;
- current front-page assignment;
- current permalink state;
- current visible Home content/model state;
- current SEO/GEO authority state;
- a reversible backup/snapshot appropriate for the sandbox.

Do not change production `https://emmake.com/`.

### 2. Candidate integrity

Use only the nested `seo-geo-theme.zip` whose SHA-256 is:

`f4c7074dd3105a04375a212845b54c5bec0e8782870d62df041a6086a36a584f`

Reject the field pass if the ZIP hash differs.

### 3. Theme-only update

On `/nuevaweb/`:

1. install/update the exact Theme candidate;
2. activate/retain SEO/GEO Theme as appropriate;
3. do **not** execute Reset & Rebuild;
4. do **not** run Clone Reset;
5. do **not** import the content blueprint again;
6. do **not** hydrate Home again;
7. do **not** change production;
8. do **not** change URLs/content merely to make the visual review pass.

If activation changes the semantic resource identity, front-page assignment, content state or SEO authority unexpectedly, stop and record a field defect rather than repairing it manually.

### 4. Browser/product QA matrix

Review the real sandbox at these viewport widths:

- desktop: `1440`;
- laptop/tablet landscape: `1024`;
- tablet/compact: `768`;
- mobile: `390`;
- retain `320` as automated stress coverage.

At every required viewport inspect:

- header height/alignment;
- logo/brand treatment;
- primary navigation;
- mobile-menu open/close behavior;
- close button, outside-click dismissal and focus behavior;
- centered mobile CTA/button text;
- hero geometry and first-viewport usefulness;
- hero image/visual quality and cropping;
- capability/service hierarchy and imagery;
- Method/process hierarchy;
- Insights cards and featured media;
- final CTA;
- footer hierarchy/navigation;
- typography, spacing and overflow;
- links/buttons/tap targets;
- absence of legacy layout leakage.

### 5. Technical acceptance

Confirm on the real sandbox:

- one coherent Theme-owned strategic shell;
- semantic heading order remains valid;
- canonical/internal links remain valid;
- accepted SEO/GEO output authority remains intact;
- no accidental `noindex`/canonical regression;
- no accessibility regression visible to automated/manual checks;
- no horizontal overflow at required breakpoints;
- no material performance regression versus the frozen repository baseline;
- no new third-party runtime dependency is introduced by the Theme candidate.

Repository reference baseline:

- Lighthouse performance: `100`;
- FCP: `751.72 ms`;
- LCP: `901.72 ms`;
- CLS: `0`;
- TBT: `0`;
- Corporate requests: `8`;
- third-party requests: `0`;
- project JavaScript bytes: `0`;
- DOM nodes: `118`.

Real hosting variance is allowed; unexplained architectural/runtime regression is not.

### 6. Evidence/result

Record the field result as one of:

- `accepted` — premium/WOW gate passed and no blocking regression;
- `rejected` — one or more blocking visual/product/technical defects found.

For a rejection, record each defect with:

- viewport;
- component;
- observed behavior;
- expected behavior;
- screenshot/evidence reference when available;
- whether the defect is Theme code, content/media, WordPress state or environment;
- severity/blocking status.

Do not broaden scope while fixing a rejected candidate. Apply the smallest EMMAKE-specific/generalizable correction, rerun the relevant repository gates, freeze a new exact candidate identity, then repeat this field pass.

## E1 close condition

E1 closes only when the exact installed candidate is explicitly accepted on the real `/nuevaweb/` sandbox and the result is recorded in #273.

Repository CI alone does not close E1.
