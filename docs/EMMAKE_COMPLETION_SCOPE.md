# EMMAKE completion scope

Status: **authoritative execution lock**

Date: 2026-10-12

## Decision

EMMAKE is the only active client/reference implementation until it is complete end to end.

Do not spend implementation capacity on SaaS / Digital Product, Local Pro, Publisher / Editorial or Ecommerce preset rollout while this lock is active.

Do not expand SEO/GEO Manager with generic remote slices merely because they are technically possible. New Manager work is allowed only when it is required to close EMMAKE, repair a regression, preserve a safety invariant, or produce the exact field/release evidence needed for EMMAKE acceptance.

This scope lock exists to enforce the project rule:

> **Finish EMMAKE before generalizing what EMMAKE has not yet proven in the field.**

## What “EMMAKE complete” means

EMMAKE is not complete merely because repository CI is green. Completion requires all four gates below.

### Gate E1 — Theme field acceptance on `/nuevaweb/`

Required:

- install the exact frozen Corporate v5 A3.1 Theme candidate on `https://emmake.com/nuevaweb/`;
- preserve the existing clean/hydrated WordPress state;
- do not rerun Reset or hydration merely for presentation QA;
- review the real site at `1440 / 1024 / 768 / 390`, with `320` retained as automated stress coverage;
- verify header, navigation, mobile menu, hero, imagery, capabilities, process, Insights, CTA and footer;
- verify premium/WOW product quality rather than only technical correctness;
- verify no SEO/GEO, accessibility, responsive or performance regression;
- retain deterministic candidate identity and field evidence;
- make an explicit accept/reject decision.

Current frozen A3.1 identity:

- Theme: `0.1.1` / `prestable`;
- implementation source: `c2396b0aa0a17f2ba8b6cfc4d8c7435ff884fa00`;
- Theme ZIP SHA-256: `f4c7074dd3105a04375a212845b54c5bec0e8782870d62df041a6086a36a584f`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic field pack SHA-256: `1736d8b6dfb9aeec8cc8132a4ee1c42d5d9e4a3afb4510934edfd0cecd5a9fe2`.

### Gate E2 — Manager field acceptance on the two EMMAKE WordPress roots

The same Manager product must prove the accepted operating boundary on both:

- `https://emmake.com/` — existing WordPress + Yoast authority;
- `https://emmake.com/nuevaweb/` — SEO/GEO Theme-native authority.

C9 evidence remains based on the frozen `0.3.49` package identity defined by issue #272. Do not silently substitute the newer remote-development package into that frozen field protocol.

Required C9 proof:

- exact package/runtime fingerprint;
- least-privilege operator;
- dedicated draft resource only;
- HTTPS preflight;
- Preview;
- Apply;
- exact readback;
- Verify;
- Rollback;
- exact original state/fingerprint restoration;
- active evidence JSON without secrets;
- credential revocation;
- rejected old credential;
- revocation evidence JSON without secrets;
- same Manager package on both installations;
- no client-specific fork.

Separately, the current repository-certified remote-development baseline is Manager `0.3.57 R3.7` on commit `bc11ed8cd7fa9bf7a5f51e430cea57336ffe8174`. It is repository-certified, not a substitute for the frozen C9 field protocol.

### Gate E3 — EMMAKE operating proof

After E1 and E2, prove the real operating lifecycle using the accepted product boundaries:

```text
Migration Bridge
    -> rescued/transitioned WordPress asset
    -> SEO/GEO Theme public rendering
    -> SEO/GEO Manager safe WordPress control plane
    -> external orchestration decides/researches/creates/optimizes
    -> Manager applies/reads back/verifies/history/rolls back
```

The proof must demonstrate the practical EMMAKE workflows needed for ongoing operation, including where applicable:

- inspect current site state;
- edit normal page/post content safely;
- edit Theme structured strategic content through semantic contracts;
- change accepted SEO authority metadata without private provider writes;
- manage contextual internal links;
- manage bounded navigation changes;
- manage featured-media relationships;
- manage existing-image alt text;
- preserve normal WordPress resource identity;
- retain operation IDs/history/evidence;
- verify public result after writes;
- roll back safely when required.

No workflow may require Manager to become the public renderer or the strategic intelligence engine.

### Gate E4 — EMMAKE release decision

EMMAKE reaches completion only when:

- Theme real-site acceptance is explicit;
- Manager C9 field acceptance is complete;
- the EMMAKE operating proof is complete;
- repository CI remains green for the accepted identities;
- open EMMAKE-critical defects are zero;
- release/support documentation reflects the accepted workflow;
- install/update/rollback artifacts needed for the accepted EMMAKE path are reproducible;
- the stable/prestable decision for each product is explicit and evidence-backed.

## Frozen work while this lock is active

The following work is intentionally deferred:

- A4 renderer generalization for reuse outside the EMMAKE requirement;
- SaaS / Digital Product preset implementation;
- Local Pro preset implementation;
- Publisher / Editorial preset implementation;
- Ecommerce preset implementation;
- generic Theme feature expansion unrelated to an EMMAKE acceptance defect;
- generic Manager remote-operation expansion beyond the currently accepted baseline unless EMMAKE exposes a concrete missing primitive;
- Migration Bridge feature expansion unrelated to an EMMAKE migration/transition defect.

Historical/future preset documentation remains valid planning material, but it is not an active execution queue.

## Allowed work

While this lock is active, implementation may proceed on:

- EMMAKE visual/browser defects;
- EMMAKE responsive/accessibility/performance defects;
- EMMAKE SEO/GEO authority defects;
- EMMAKE Theme semantic-model defects;
- EMMAKE Manager field/remote defects;
- C9 harness-only corrections that do not alter the frozen Manager package identity;
- installation/update/rollback/release packaging needed by EMMAKE;
- evidence tooling needed to close E1–E4;
- documentation corrections that prevent operating drift.

## Stop/go rule

Before starting any new task ask:

1. Does this directly move E1, E2, E3 or E4 toward completion?
2. Is it required to fix an EMMAKE defect or preserve an accepted safety contract?

If both answers are **no**, defer the task.

## Unlock condition

This execution lock ends only after E1 + E2 + E3 + E4 are explicitly complete.

Only then may the project generalize the renderer and resume the other preset families, using EMMAKE field evidence as the reference rather than assumptions.
