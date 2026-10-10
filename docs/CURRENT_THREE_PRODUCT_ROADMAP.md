# Current three-product roadmap

Status: **authoritative current execution roadmap**

Date: 2026-10-10

Canonical architecture: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

This roadmap exists to prevent implementation drift. Historical phase documentation remains useful evidence, but new work must follow this product boundary and order.

## North star

```text
SEO/GEO Migration Bridge
    = bring/rescue/transition the site

SEO/GEO Theme
    = build/render the public site

SEO/GEO Manager
    = give an authorized external operator safe control inside WordPress

External orchestration
    = strategy/research/creation/optimization/growth intelligence
```

The project targets **three independently sellable WordPress products**. External orchestration is the managed operating layer, not a fourth WordPress plugin.

---

# Global rules before any new implementation

Before opening a feature branch, classify the feature.

## Migration Bridge test

If the feature answers:

> How do we scan, clone, export/import, rescue, reset, migrate or cut over an old site?

it belongs to **Migration Bridge**.

## Theme test

If the feature answers:

> How should the public website look, render, respond, remain accessible and stay fast?

it belongs to **Theme**.

## Manager test

If the feature answers:

> How can an authorized external operator safely inspect/read/write/publish/verify WordPress state?

it belongs to **Manager**.

## External orchestration test

If the feature answers:

> What should we create/change/optimize, why, for which audience/keyword/market, and what should the content say?

it belongs to **external orchestration**.

No implementation starts until this classification is explicit.

---

# Track A — SEO/GEO Migration Bridge

## Objective

Productize the accepted migration system as a reliable standalone commercial tool.

## Scope to keep

- Scan/analyzer;
- source baseline;
- dependency graph;
- Portable Clone Engine;
- local clone;
- export;
- import;
- Rescue Manifest;
- reset-first workflow;
- sandbox isolation;
- supported migration transformations;
- SEO/GEO parity;
- cutover evidence;
- rollback/recovery evidence;
- final migration handoff/report;
- installable ZIP, versioning and operator UX.

## Scope not to add

- ongoing keyword research;
- recurring blog generation;
- continuous site optimization;
- Search Console opportunity strategy;
- post-launch content growth engine;
- Theme rendering responsibility;
- general remote WordPress control unrelated to migration.

## Commercial done condition

Migration Bridge is ready to sell when a client can install/use it for a supported migration/replatform engagement and complete the transition without requiring a third-party cloning plugin for the accepted path.

It may hand off to our Theme or another destination stack.

---

# Track B — SEO/GEO Theme

## Objective

Create one premium, fast, SEO/GEO-native frontend product with five reusable preset families.

## Current reference

Corporate/EMMAKE is the reference implementation for Theme-owned strategic rendering.

## Immediate work

1. close real Corporate field acceptance on `/nuevaweb/`;
2. freeze the semantic model -> renderer contract;
3. verify no Gutenberg constrained-layout dependency owns strategic composition;
4. verify desktop/tablet/mobile rendering;
5. verify semantic heading/link/accessibility behavior;
6. verify performance budgets;
7. verify native SEO/GEO output authority;
8. productize Theme installation/update/rollback.

## Then generalize presets

Apply the same renderer/model architecture to:

1. Corporate;
2. SaaS / Digital Product;
3. Local Pro;
4. Publisher / Editorial;
5. Ecommerce.

## Theme must own

- public strategic frontend;
- visual composition;
- design tokens/components;
- server rendering;
- responsive behavior;
- accessibility;
- performance;
- baseline SEO/GEO runtime;
- Schema/discovery output under authority contract;
- article shell.

## Theme must not own

- remote client strategy;
- keyword research;
- external growth prioritization;
- migration transport;
- Manager operation history/control plane.

## Commercial done condition

Theme is ready to sell when a clean WordPress installation can install the Theme and reach the documented baseline without mandatory SEO/GEO plugins, and the preset/rendering system is deterministic, fast and supportable.

---

# Track C — SEO/GEO Manager

## Objective

Create the generic secure bridge between external orchestration and a client's WordPress.

The Manager's commercial value is not that it contains our entire intelligence. Its value is that it gives us reliable **eyes + hands + safety** on many WordPress sites.

## C0 — Product boundary freeze

Status: **current architectural decision**.

Required:

- Manager documented as bridge/control agent;
- no migration-product absorption requirement;
- no requirement to embed autonomous SEO/content strategy;
- Theme/Manager independence;
- external orchestration contract documented.

## C1 — Site Intelligence / eyes

Manager must expose machine-readable current state for remote use:

- site/environment identity;
- WordPress/PHP/runtime;
- current Theme/preset/model capabilities;
- pages/posts/CPTs/taxonomies;
- IDs/slugs/URLs/statuses/authors/revisions;
- menus/navigation;
- internal links;
- media;
- SEO provider/output authority;
- canonical/indexability/Schema/discovery evidence;
- permalink/redirect state;
- operation capabilities;
- recent operation summaries.

### Exit criterion

External orchestration can understand a supported client's real current WordPress state without manual wp-admin inspection for the normal workflow.

## C2 — Authentication / capability contract

Required:

- scoped authenticated remote identity;
- HTTPS;
- least privilege;
- revocable credentials;
- capability discovery;
- browser nonce protection;
- production-environment binding where needed;
- bounded requests/responses;
- no generic remote shell.

### Exit criterion

We can connect to a client WordPress safely enough to use it as an operational API rather than sharing full admin credentials for every routine task.

## C3 — Generic change-set engine / hands

Required generic contract:

```text
Inspect current resource
 -> Preview proposed change
 -> Validate permissions/collisions/current fingerprint
 -> Explicit/policy approval
 -> Apply
 -> Verify stored state
 -> optional rendered verification
 -> Record operation
 -> rollback when supported
```

Required safety:

- idempotency;
- expected revision/fingerprint;
- stale-state rejection;
- deterministic resource identity;
- exact changed-field/model-slot reporting;
- bounded previous-value capture where rollback needs it.

### Exit criterion

External orchestration can safely change supported WordPress resources without a custom endpoint for every client-specific idea.

## C4 — WordPress content operations

Required primitives:

- create/update page;
- create/update post;
- draft;
- schedule;
- publish;
- status changes under policy;
- excerpt/title/body;
- author binding;
- categories/tags under policy;
- featured media/reference operations;
- safe multilingual relationship operations where supported.

### Exit criterion

External orchestration can create and manage a normal WordPress article end to end through Manager.

## C5 — Theme semantic-model operations

Required:

- discover supported model versions;
- read current model;
- preview model changes;
- validate required slots/types;
- apply model changes;
- verify model persistence;
- rendered verification through Theme;
- rollback where safe.

### Exit criterion

External orchestration can create/update a Theme-owned strategic page without knowing Gutenberg layout trees or CSS implementation.

## C6 — Navigation, internal link and media primitives

Required:

- read/modify supported menu targets;
- read/modify bounded contextual links;
- verify targets are real/current;
- avoid clone/source-host leakage;
- media ID/reference support;
- alt/context updates under policy;
- no fabricated media metadata.

### Exit criterion

External orchestration can execute real internal-link/navigation/media plans safely.

## C7 — SEO output authority/provider adapters

Required:

- resolve owner for each public SEO/GEO surface;
- Theme-native coexistence;
- generic/no-provider accepted path;
- accepted provider adapters incrementally;
- no duplicate canonical/robots/hreflang/Schema/Open Graph/sitemap output.

### Exit criterion

External orchestration can request metadata/indexability/Schema intent changes without creating conflicting public output.

## C8 — Operation history/evidence/rollback

Required:

- private operation detail;
- bounded operator summary;
- operation IDs;
- verification state;
- rollback eligibility;
- stale-safe rollback;
- no secret/private-content leakage in summaries;
- downloadable machine-readable evidence where useful.

### Exit criterion

Every meaningful remote mutation is auditable and recoverable according to its contract.

## C9 — Client-agnostic field acceptance

Must prove Manager on at least:

- one SEO/GEO Theme site;
- one supported non-Theme WordPress site.

EMMAKE is the first Theme-site reference, not a hardcoded special case.

### Exit criterion

The same Manager package operates both classes without client-specific code forks.

---

# External orchestration workflows after Manager primitives

These workflows are where our intelligence creates commercial value.

They are not a requirement to embed large AI engines inside Manager.

## Workflow O1 — Build / Finish

```text
request site state
 -> inspect through Manager
 -> external reasoning
 -> create/fix content/SEO/link/media plan
 -> Manager preview
 -> apply
 -> verify
 -> repeat until launch-ready
```

EMMAKE is the first reference.

## Workflow O2 — Optimize

```text
Manager state + search/analytics evidence
 -> external SEO/GEO/content analysis
 -> prioritized bounded changes
 -> Manager preview/apply/verify
 -> measurement
```

## Workflow O3 — Strategic landing creation

```text
external research/brief/content/model
 -> Manager duplicate/capability checks
 -> Manager preview
 -> Manager persists Theme semantic model/resource
 -> Theme renders
 -> Manager verifies
```

## Workflow O4 — Automated blog publication

```text
external topic/research/full article
 -> Manager creates normal WP draft
 -> optional human Gutenberg edit
 -> Manager schedule/publish
 -> Theme renders article shell when active
 -> Manager verifies
```

## Workflow O5 — Growth loop

```text
Search Console/Bing/analytics/market data
 -> external opportunity prioritization
 -> landing/article/refresh/link plan
 -> Manager execution
 -> measurement
 -> next iteration
```

---

# EMMAKE immediate decision

The existing URL/permalink/slug work remains valid because launch readiness requires preserving search equity safely.

However:

- we finish the **minimum real EMMAKE URL/301 transition** with the existing guarded machinery;
- we do not keep expanding permalink/migration edge-case intelligence as the main Manager roadmap;
- after that field gate is accepted, Manager work returns to the generic control primitives in Track C;
- Theme and Manager field acceptance continue independently;
- external Build / Finish becomes the first full operating workflow.

## EMMAKE sequence from here

```text
1. Close current safe permalink/301 operation.
2. Re-run final site evidence.
3. Freeze that migration-specific Manager slice.
4. Complete remaining Corporate launch-readiness issues.
5. Stabilize remote Manager API/control primitives.
6. Operate EMMAKE from external orchestration rather than growing wp-admin into the brain.
7. Prove Optimize.
8. Prove landing creation.
9. Prove automated blog publication.
10. Connect measurement/growth signals.
```

---

# Three-product commercial readiness matrix

## Migration Bridge sellable when

- installable artifact exists;
- clone/export/import/rescue/reset/cutover path is accepted;
- privacy/safety evidence exists;
- operator documentation is clear;
- no Theme/Manager purchase is required for a valid supported migration engagement.

## Theme sellable when

- deterministic installable artifact exists;
- baseline requires zero SEO/GEO plugins;
- preset rendering contracts are accepted;
- SEO/GEO/accessibility/performance gates pass;
- client installation/update/rollback is documented.

## Manager sellable when

- deterministic installable artifact exists;
- secure remote authentication works;
- Site Intelligence is reliable;
- generic preview/apply/verify/rollback works;
- WordPress content operations work;
- Theme model operations work when Theme is present;
- supported non-Theme path exists;
- operation history/evidence is accepted;
- no client-specific hardcoding exists.

---

# Drift alarms

Stop and re-evaluate when any of these happens:

- one Manager edge case generates many versions without improving generic remote control;
- wp-admin starts becoming the primary research/content strategy application;
- Manager starts duplicating Theme rendering responsibilities;
- Manager starts absorbing complete Migration Bridge workflows without a compelling shared-primitive reason;
- Theme begins depending on Manager for normal public rendering;
- client-specific URLs/business rules enter generic product code;
- external intelligence improvements require unnecessary plugin releases;
- Gutenberg layout trees become canonical strategic models;
- a feature cannot be clearly assigned to Bridge, Theme, Manager or external orchestration.

When a drift alarm fires, return to `docs/THREE_PRODUCT_OPERATING_MODEL.md` before coding further.
