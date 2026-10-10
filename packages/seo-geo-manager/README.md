# SEO/GEO Manager

SEO/GEO Manager is the permanent, independently installable **WordPress bridge/control agent** for the three-product SEO/GEO portfolio.

Canonical architecture:

> **Migration Bridge brings/rescues the website.**  
> **SEO/GEO Theme builds and renders the website.**  
> **SEO/GEO Manager gives an authorized external operator eyes, hands and safe control inside WordPress.**  
> **External orchestration provides strategy, research, creation, optimization and growth intelligence.**

Manager is not the strategic brain and is not the public frontend renderer. Its value is that it exposes reliable, client-agnostic WordPress capabilities that external orchestration can inspect and use safely.

Authoritative product contract: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Core operating loop

```text
Inspect
  -> Preview
  -> Execute
  -> Publish / Schedule
  -> Verify
  -> History / Rollback
```

External orchestration decides **what** should be created, changed or optimized and **why**. Manager validates whether the authenticated principal and current WordPress installation can perform the requested operation, applies accepted changes through WordPress-native APIs and returns bounded evidence.

## Manager owns

- authenticated Site Intelligence;
- capability discovery;
- current environment identity/fingerprint;
- WordPress resource identity;
- content/model read operations;
- exact preview/diff;
- guarded apply;
- idempotency;
- expected-revision/fingerprint protection;
- production-environment acknowledgement where required;
- normal page/post operations;
- Theme semantic-model operations;
- supported navigation/internal-link/media operations;
- SEO output-authority/provider adapters;
- stored-state verification;
- optional bounded rendered verification;
- operation IDs/history/evidence;
- stale-safe rollback where the operation contract supports it.

## Manager does not own

- business strategy;
- keyword/market/competitor research;
- deciding which landing should exist;
- deciding which article should be written;
- autonomous content strategy;
- autonomous Search Console/Bing prioritization;
- frontend visual composition when SEO/GEO Theme is active;
- cloning/reset/replatform transport owned by Migration Bridge;
- a generic command shell;
- client-specific hardcoded workflows.

## API

Base namespace:

```text
/wp-json/seo-geo-manager/v1
```

### Public health

```text
GET /health
```

Returns only bounded service/version health information.

### Authenticated capability discovery — 0.3.34+

```text
GET /capabilities
```

Returns what the **current authenticated WordPress identity** can do through this Manager installation.

The manifest includes:

- Manager/API version;
- current principal user ID;
- Application Password availability/support state;
- environment safety contract;
- WordPress runtime identity;
- available Manager capability families;
- required WordPress capability per family;
- semantic operation paths and risk class;
- supported safety features.

It never returns passwords, Application Password values, cookies, REST nonces, secrets or private post bodies.

Detailed contract: `docs/SEO_GEO_MANAGER_0.3.34_CAPABILITY_DISCOVERY.md`.

### Site/read operations

```text
GET /content
GET /content/{id}
GET /site/snapshot
GET /site/intelligence
GET /operations
GET /field-gate/preflight
```

`/site/snapshot` exposes the current bounded environment contract. Risky production writes require the exact current `environment_fingerprint` where the mutation endpoint applies that policy.

`/site/intelligence` exposes bounded current WordPress state for external reasoning: resources, paths, internal-link evidence, Theme/model signals, media signals, SEO output authority and launch-readiness diagnostics.

### Generic change sets

```text
POST /changes/preview
POST /changes/apply
GET  /changes/{operation_id}
POST /changes/{operation_id}/rollback
```

The generic change-set engine provides field-level preview, expected fingerprint protection, idempotency, environment binding, exact persistence verification and stale-safe rollback.

### Theme structured content

```text
POST /theme/structured/preview
POST /theme/structured/apply
```

When SEO/GEO Theme is active, Manager changes contract-defined semantic content/model slots. Manager does not generate or control the Theme's CSS grid, visual breakpoints or Gutenberg layout tree.

### Navigation

```text
POST /navigation/changes/preview
POST /navigation/changes/apply
GET  /navigation/changes/{operation_id}
POST /navigation/changes/{operation_id}/rollback
```

Navigation operations require the WordPress capability used by the navigation controller and retain the same environment/operation safety model.

### Migration-derived URL safety endpoints

The current package contains guarded permalink/redirect/slug operations created from real EMMAKE field acceptance.

They remain valid **launch-readiness safety primitives**, but they do not redefine Manager as a migration product. New migration transport/rescue/reset functionality belongs to SEO/GEO Migration Bridge.

The migration-derived slice is frozen after the minimum accepted EMMAKE transition unless a generic Manager safety defect requires correction.

## Authentication

Manager uses WordPress authentication rather than inventing a parallel account system.

Preferred managed remote baseline:

```text
HTTPS
+ dedicated WordPress operator identity
+ WordPress Application Password
+ least-privilege WordPress capabilities
+ revocable credential
```

Browser-admin operations use the authenticated WordPress session and normal REST nonce middleware.

Manager never returns credentials through its capability manifest or operation evidence.

## Authorization

Discovering an operation does not authorize it.

Each REST endpoint retains its own capability checks. Resource-specific operations may additionally require `edit_post`/resource ownership checks. Site-wide operations remain more restrictive.

External orchestration must call `/capabilities` and inspect the current resource/environment before preparing an operation.

## Safety invariants

1. Inspect before mutate.
2. Preview before meaningful mutation.
3. No client-domain hardcoding.
4. No generic remote shell.
5. Use WordPress-native resource identity and permission checks.
6. Use expected fingerprints/revisions to reject stale automation.
7. Use idempotency for retry-safe writes.
8. Bind risky production mutations to the current environment.
9. Verify stored state after mutation.
10. Keep rendered verification same-site and bounded.
11. Keep operation summaries privacy-bounded.
12. Roll back only when the operation is still safe to reverse.
13. Never silently overwrite a newer human edit.
14. Never emit duplicate SEO/GEO authority.
15. Never fabricate business facts, authors, sources, reviews or proof.

## Build / Finish, Optimize and Grow

These remain useful lifecycle workflows, but they are **externally orchestrated workflows**, not autonomous AI modes embedded in the plugin.

### Build / Finish

```text
Manager inspection
 -> external reasoning / content / SEO / link / media plan
 -> Manager preview
 -> Manager apply
 -> Manager verify
 -> repeat until launch-ready
```

### Optimize

```text
Manager current state + search/analytics evidence
 -> external analysis
 -> bounded proposed changes
 -> Manager preview/apply/verify
 -> measurement
```

### Grow

```text
external research/opportunity/content generation
 -> Manager creates or updates WordPress resources
 -> optional editorial approval
 -> Manager schedules/publishes
 -> Theme/current stack renders
 -> Manager verifies
```

## Theme relationship

SEO/GEO Theme and Manager are independently installable.

When both are active:

- Manager creates/updates WordPress resources and semantic model data;
- Theme owns strategic frontend rendering/design/performance;
- WordPress remains CMS/resource authority;
- Gutenberg remains available for normal editorial content;
- output-authority resolution prevents duplicate canonical/robots/hreflang/Schema/social output.

## Migration Bridge relationship

Migration Bridge is a separate sellable product.

Typical full-redesign lifecycle:

```text
Migration Bridge
 -> rescued/reset rebuild workspace
 -> SEO/GEO Theme
 -> SEO/GEO Manager
 -> external Build / Finish
 -> launch
 -> external Optimize / Grow through Manager
```

Manager does not need to absorb or replace the complete Migration Bridge product.

## Current development line

- **0.3.33** closed the immediate Field Gate UI handoff defect found during EMMAKE finalization.
- **0.3.34** begins the post-boundary-freeze Manager roadmap with authenticated capability discovery for generic remote orchestration.

EMMAKE `/nuevaweb/` remains the first Theme-site field reference, not a hardcoded product assumption.

## Current roadmap

See:

- `docs/THREE_PRODUCT_OPERATING_MODEL.md`;
- `docs/CURRENT_THREE_PRODUCT_ROADMAP.md`;
- `docs/DOCUMENTATION_AUTHORITY.md`;
- `docs/SEO_GEO_MANAGER.md`;
- `docs/SEO_GEO_MANAGER_0.3.34_CAPABILITY_DISCOVERY.md`.

The next Manager focus after capability discovery is to close C2 remote-auth acceptance and then continue C3/C4 generic hands/content operations, rather than expanding migration-specific logic.
