# SEO/GEO Manager

Status: **authoritative Manager product contract**

Canonical product boundary: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Purpose

SEO/GEO Manager is the permanent, independently installable **WordPress bridge/control agent** that gives an authorized external operator safe eyes and hands inside a client's WordPress.

Manager is **not the strategic brain**. Strategy, research, content creation, optimization reasoning, opportunity prioritization and growth decisions live in the external orchestration layer operated by us.

Manager's job is to expose reliable WordPress capabilities so external orchestration can:

```text
Inspect
 -> Preview
 -> Execute
 -> Publish / Schedule
 -> Verify
 -> History / Rollback
```

The plugin must remain generic, client-agnostic and useful on supported WordPress sites whether or not SEO/GEO Theme is installed.

## Product position

The three sellable products remain separate:

- **SEO/GEO Migration Bridge** — scan/clone/export/import/rescue/reset/replatform/cutover transition product;
- **SEO/GEO Theme** — frontend rendering/design/semantic HTML/accessibility/performance/native SEO/GEO product;
- **SEO/GEO Manager** — permanent safe WordPress control bridge.

The external orchestration layer is not a fourth WordPress plugin. It is where our team and AI-assisted workflows reason about client state and decide what should change.

## Core principle

> **Manager exposes capabilities; external orchestration supplies intelligence.**

A feature belongs inside Manager when it answers:

> How can an authorized external operator safely read, write, publish or verify this WordPress state?

A feature normally belongs outside Manager when it answers:

> What keyword should we target, what content should we create, what should it say, which opportunity should we prioritize, or why should we make this change?

## Manager capability layers

### 1. Site Intelligence / Inspect

Read-only current-state APIs provide bounded evidence about:

- WordPress/PHP/runtime;
- current environment identity;
- Theme/preset/model capability;
- pages/posts/CPTs/taxonomies;
- resource IDs/slugs/URLs/statuses;
- internal-link/navigation evidence;
- media signals;
- permalink/redirect state;
- SEO output authority/providers;
- launch-readiness diagnostics;
- recent privacy-bounded operation summaries.

Deep migration analysis belongs to Migration Bridge. Manager inspection should remain lightweight and useful after launch.

### 2. Authentication and capability discovery

Manager uses WordPress authentication rather than inventing a parallel account system.

Preferred managed remote baseline:

```text
HTTPS
+ dedicated WordPress operator identity
+ WordPress Application Password
+ least-privilege capabilities
+ revocable credential
```

Browser-admin calls use normal WordPress session + REST nonce protections.

Manager 0.3.34 introduces:

```text
GET /wp-json/seo-geo-manager/v1/capabilities
```

The authenticated endpoint returns a bounded manifest describing what the current principal may do through this Manager installation, including:

- Manager/API version;
- current principal ID;
- actual WordPress Application Password support/availability;
- environment write-approval contract;
- per-capability availability;
- required WordPress capability per family;
- semantic operation paths/risk classes;
- supported safety primitives.

It never returns credentials, nonces, cookies, secrets or private content.

Detailed 0.3.34 contract: `docs/SEO_GEO_MANAGER_0.3.34_CAPABILITY_DISCOVERY.md`.

### 3. Output Authority Resolver

Before any public SEO/GEO output is modified, Manager determines the accepted owner for that surface.

Potential owners include:

- SEO/GEO Theme;
- WordPress Core;
- Manager-native adapter where explicitly enabled;
- accepted external provider adapter;
- blocked/manual-review state.

Surfaces include:

- title/meta description;
- canonical;
- robots/indexability;
- Open Graph/social metadata;
- hreflang;
- Schema;
- sitemap/discovery surfaces;
- redirects where the relevant operation owns them.

Manager must not create duplicate public authority.

### 4. Preview / Change-set Core

External orchestration submits a bounded proposed change. Manager validates current WordPress state and produces an exact preview/diff without silently mutating the site.

Required protections include:

- deterministic target identity;
- expected fingerprint/revision;
- collision checks;
- current permissions;
- environment policy;
- explicit changed fields/model slots;
- no hidden layout mutation.

### 5. Execute / Apply

Manager applies only supported semantic operations through WordPress APIs.

Execution must support as appropriate:

- idempotency;
- stale-state rejection;
- environment binding;
- exact stored-value verification;
- bounded previous-value/revision evidence;
- operation ID;
- rollback reference where safe.

Manager must never expose a generic remote shell as a shortcut.

### 6. Publish / Schedule

Manager provides publication primitives; it does not decide editorial strategy.

Required direction includes:

- create/update page;
- create/update normal WordPress post;
- draft;
- schedule;
- publish under capability/policy;
- author binding;
- excerpt/title/body;
- categories/tags under explicit policy;
- featured media references;
- multilingual relations through accepted adapters.

External orchestration may generate a complete article. Manager materializes it as a normal WordPress post and safely manages its lifecycle.

### 7. Theme semantic-model operations

When SEO/GEO Theme is active, Manager operates semantic content/model data rather than Gutenberg visual layout trees.

Manager must be able to:

- discover supported model versions;
- read the current model;
- preview changes;
- validate slots/types;
- apply accepted changes;
- verify persistence;
- request bounded rendered verification;
- roll back where safe.

SEO/GEO Theme remains the public strategic frontend authority.

### 8. Navigation / internal-link / media operations

Manager should expose semantic primitives for externally prepared plans:

- menu target reads/updates;
- bounded contextual-link updates;
- real target validation;
- clone/source-host leakage prevention;
- media ID/reference operations;
- alt/context updates under policy.

External orchestration decides the linking/media strategy. Manager executes and verifies it.

### 9. Verify

Stored-state verification and frontend verification are separate facts.

Manager verifies exact stored WordPress state after mutation.

Where supported, optional rendered verification checks the exact current same-site public route with bounded HTTP behavior and stores compact evidence, never the response body.

### 10. Operation history and rollback

Manager keeps private operation detail needed for verification/rollback and exposes bounded summaries for operator/remote visibility.

Requirements include:

- operation IDs;
- changed field/model-slot names;
- verification status;
- rollback eligibility;
- environment binding;
- stale-safe rollback;
- no secrets/private mutation values in summary APIs.

## External orchestration contract

External orchestration is the brain.

It owns activities such as:

- business/market understanding;
- keyword/search-intent research;
- competitor research;
- content strategy;
- landing selection and content creation;
- blog topic selection and complete article creation;
- entity/GEO reasoning;
- internal-link/cluster strategy;
- Search Console/Bing/analytics interpretation;
- opportunity prioritization;
- content-refresh decisions;
- growth planning.

Typical flow:

```text
external request/research
 -> GET Manager capabilities/current state
 -> external reasoning
 -> bounded Manager preview
 -> approval/policy
 -> Manager execute/publish
 -> Manager verify
 -> external measurement/next decision
```

Improving research/generation intelligence should normally **not require a WordPress plugin release**.

## Build / Finish, Optimize and Grow

These remain useful lifecycle workflows, but they are externally orchestrated workflows using Manager capabilities.

### Build / Finish

Take a fresh/rebuilt site to launch readiness.

Manager supplies inspection and safe execution. External orchestration determines fixes/content/SEO/link/media work.

### Optimize

Improve an existing site from real site/search evidence.

External reasoning prepares prioritized bounded changes; Manager previews/applies/verifies them.

### Grow

Run continuous publication/refresh/linking workflows.

External orchestration decides opportunities and creates content; Manager safely materializes and publishes the accepted work inside WordPress.

## wp-admin UI role

The Manager admin UI remains useful for:

- local diagnostics;
- connection/capability status;
- previews;
- safety confirmation for high-risk operations;
- operation history;
- downloadable technical evidence;
- recovery/emergency actions.

It is **not** intended to become the primary strategic control center for managed advanced operation. The advanced workflow is external orchestration through authenticated Manager APIs.

## WordPress/Gutenberg role

WordPress remains resource authority for:

- IDs;
- slugs/URLs;
- statuses;
- authors;
- revisions;
- permissions;
- media;
- taxonomies;
- local persistent content.

Gutenberg remains available for normal editorial autonomy, including manual editing of Manager-created blog posts where allowed.

Gutenberg is not the master layout authority for Theme-owned strategic pages.

## Theme relationship

Manager must work without SEO/GEO Theme on accepted WordPress stacks.

When Theme + Manager are both active:

- Manager reads/writes WordPress resources and semantic model data;
- Theme renders strategic frontend;
- output-authority resolution prevents duplicate SEO/GEO signals;
- frontend rendering remains local to WordPress and does not depend on a live remote orchestration request.

## Migration Bridge relationship

Migration Bridge remains a separate sellable product.

Manager does **not** need to absorb/replace the complete migration product.

Migration-derived safety primitives already built inside Manager may remain when useful for Build / Finish/launch readiness, but new scan/clone/export/import/rescue/reset/cutover product work belongs to Migration Bridge.

## Authentication and security

Every mutation surface requires the narrowest appropriate WordPress authority plus operation-specific guards.

Remote operation principles:

- HTTPS;
- dedicated/revocable WordPress identity;
- least privilege;
- no credentials in repository/content/operation reports;
- no unrestricted shell;
- bounded inputs/outputs;
- idempotency where retry is possible;
- current environment acknowledgement for guarded production changes;
- exact target/revision/fingerprint protection;
- auditable operations.

## Privacy

- remote inspection should prefer metadata/fingerprints where full content is unnecessary;
- private/customer/order/form data is outside the SEO/content operation contract unless an explicit future adapter requires narrowly scoped access;
- operation summary APIs must not expose rollback payload values or secrets;
- remote generation transports only what is needed for the requested operation.

## Performance/runtime

Manager is a control plane, not a frontend dependency.

Normal public requests must render from local WordPress state without contacting an external orchestration service.

Manager background/remote operations must remain bounded and invalidate only affected caches/surfaces.

## First stable Manager direction

A first stable Manager requires a proven generic bridge rather than embedded strategy engines.

Minimum product acceptance includes:

- independent install/activate/upgrade/rollback;
- supported WordPress/PHP matrix;
- authenticated capability discovery;
- real least-privilege remote connection acceptance;
- Site Intelligence;
- generic preview/apply/verify/rollback;
- normal post/page creation lifecycle;
- Theme semantic-model operations when Theme is active;
- navigation/link/media primitives;
- output-authority coordination;
- operation evidence/history;
- EN/ES operator UX where human UI is required;
- no duplicate SEO/GEO output;
- one Theme-site and one non-Theme-site field acceptance.

The first stable Manager does **not** require an embedded autonomous keyword strategist, competitor researcher, copywriter or growth brain.

## Current implementation state

Existing M1/M2 work already provides substantial foundations:

- health/content APIs;
- Site Intelligence;
- environment fingerprints;
- generic change-set preview/apply/rollback;
- Theme structured changes;
- navigation correction path;
- output-authority inspection;
- operation history;
- rendered verification;
- guarded URL/permalink launch-safety operations derived from EMMAKE field work.

Manager **0.3.34** adds authenticated capability discovery and begins the explicit post-boundary-freeze C2 remote-control roadmap.

The migration/permalink field machinery must not continue expanding as the center of the product. After the real EMMAKE transition is closed, development returns to generic remote-control/content capabilities.

## Roadmap

Canonical current execution order is maintained in `docs/CURRENT_THREE_PRODUCT_ROADMAP.md`.

Immediate Manager direction:

1. C2 capability discovery — implemented in 0.3.34;
2. real HTTPS Application Password + least-privilege field acceptance;
3. C3 generic hands/change-set generalization;
4. C4 complete page/post lifecycle;
5. C5 Theme semantic-model lifecycle;
6. C6 links/navigation/media;
7. C7 provider adapters;
8. C8 evidence/rollback completeness;
9. C9 Theme + non-Theme field acceptance.
