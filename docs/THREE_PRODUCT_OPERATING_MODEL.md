# Three-product operating model

Status: **authoritative**

Date: 2026-10-10

This document is the canonical product-boundary contract for the WordPress SEO/GEO portfolio. If another document, roadmap note, implementation slice or field experiment appears to contradict this model, this document wins until an explicit architectural decision replaces it.

## North-star model

The project consists of **three independently sellable WordPress products** plus an external orchestration layer operated by us:

> **SEO/GEO Migration Bridge = brings/rescues the website.**
>
> **SEO/GEO Theme = builds and renders the website.**
>
> **SEO/GEO Manager = gives us eyes, hands and safe control inside the client's WordPress.**
>
> **External orchestration = the brain: strategy, research, creation, optimization and growth.**

The external orchestration layer is not a fourth installable WordPress product. It is the operating model through which our team, ChatGPT-assisted workflows and future automation services inspect client state, reason about it, prepare bounded instructions and drive the three products.

The portfolio must remain usable with any supported client WordPress installation. No product may contain hardcoded assumptions about EMMAKE, Kairoseth, IA Empleado or any other specific client.

## Why this boundary exists

The goal is not to push all intelligence into WordPress. WordPress should remain the local content/runtime platform. The product should let us operate a client's WordPress in the same inspect/change/verify style used in software projects such as Kairoseth and IA Empleado, while preserving WordPress identity, revisions, permissions and client autonomy.

The architecture therefore separates four responsibilities:

1. **transition** — move/rescue an existing website safely;
2. **presentation/runtime** — render a modern, fast, semantic frontend;
3. **local execution/control** — expose safe WordPress capabilities to the operator;
4. **intelligence/orchestration** — decide what should be researched, created, changed, optimized or published.

Keeping these responsibilities separate makes all three products sellable independently and reusable across clients.

---

# Product 1 — SEO/GEO Migration Bridge

## Commercial purpose

Migration Bridge is the **transition product** for existing sites that need to be cloned, rescued, reset, rebuilt or moved without throwing away useful search equity and business assets.

It is intentionally temporary in the normal client lifecycle. It may remain installed while migration work is active, but it is not the permanent SEO/content operating layer.

## Owns

Migration Bridge owns the migration/replatform workflow:

```text
Scan
  -> Clone / Export / Import
  -> Rescue Manifest
  -> Reset legacy runtime
  -> Theme + preset bootstrap
  -> Rebuild handoff
  -> Cutover / migration verification
```

It may provide:

- source-site inventory;
- clone/export/import transport;
- bounded rescue manifest;
- URL/SEO/content/media/business-fact preservation evidence;
- dependency classification;
- reset-first cleanup;
- migration safety and cutover evidence;
- rollback/recovery support for migration operations.

## Must preserve

Only assets that materially matter to the new site:

- useful pages/posts and factual content;
- important URLs/slugs and redirect intent;
- valuable SEO metadata/indexability/canonical intent;
- internal/external links worth preserving;
- selected media;
- verified organization/contact/business facts;
- legal content that must survive;
- multilingual relationships where relevant;
- required forms/integrations/business flows.

## Must discard by default

Migration Bridge must not preserve legacy presentation debt merely because it exists:

- obsolete themes/child themes;
- builder layout trees;
- page-builder CSS/JS;
- presentation-only shortcodes;
- old widgets and Customizer presentation state;
- redundant SEO/rendering plugins once replacement ownership is accepted;
- stale generated assets/caches;
- obsolete plugins that are not required by the rebuilt business flow.

## Does not own

Migration Bridge does **not** own:

- ongoing SEO strategy;
- continuous content generation;
- routine blog publication after handoff;
- long-term optimization/growth loops;
- public frontend rendering;
- client-specific strategic reasoning.

## Product success condition

A Migration Bridge engagement is successful when the useful digital asset has been rescued, the rebuild target is clean, the new Theme/preset can take ownership, and the site can continue through Manager without depending on the legacy runtime.

North-star sentence:

> **Rescue the asset, reset the clone, rebuild the product.**

---

# Product 2 — SEO/GEO Theme

## Commercial purpose

SEO/GEO Theme is the **presentation/runtime product** for new builds and full redesigns. It provides the modern frontend, design system and baseline SEO/GEO runtime without requiring a third-party page builder or SEO plugin for the documented baseline.

## Owns

Theme owns:

- server-rendered public frontend;
- strategic page composition;
- design system and presets;
- semantic HTML;
- responsive behavior;
- accessibility;
- performance budgets;
- baseline native SEO/GEO output;
- Schema/discovery output under the authority contract;
- article/editorial shell;
- visual consistency across strategic surfaces.

## Five reusable preset families

The product target is five reusable preset families:

1. Corporate;
2. Local Business / Local Pro;
3. SaaS / Digital Product;
4. Publisher / Editorial;
5. Ecommerce.

Presets are not separate client themes. They are reusable rendering families built on shared contracts.

## Strategic rendering rule

When SEO/GEO Theme is active, strategic pages are rendered from **versioned semantic content models**, not arbitrary Gutenberg layout trees.

Examples:

```text
corporate-home-v1
corporate-landing-v1
service-page-v1
location-page-v1
campaign-page-v1
saas-home-v1
local-home-v1
publisher-home-v1
ecommerce-home-v1
```

The model contains semantic content such as headings, body copy, CTAs, references, media IDs, facts, FAQs and internal-link targets. It must not encode CSS grid details, Gutenberg `Group` nesting, `alignwide`, `contentSize` or other visual implementation details.

## Gutenberg boundary

Gutenberg remains useful for editorial autonomy:

- normal blog posts;
- manual edits to Manager-created posts;
- legal/privacy/cookie pages;
- simple informational pages;
- other explicitly editorial content.

Gutenberg is **not** the master layout authority for Theme-owned strategic surfaces such as Home, commercial landings, services, locations, campaigns and product/preset hubs.

## Independence

Theme must work when Manager is not installed.

A client may buy only Theme for a clean new site or redesign. Manager can be added later for remote operations and continuous growth.

---

# Product 3 — SEO/GEO Manager

## Commercial purpose

SEO/GEO Manager is the **local WordPress bridge/control agent** that gives the external operator reliable eyes, hands and safe control inside the client's WordPress.

Manager is not the strategic brain. It is not a self-contained AI SEO consultant. It is not the place where client strategy, keyword research, competitive reasoning, editorial planning or growth decisions should accumulate.

Its job is to expose safe, generic WordPress capabilities so an external orchestrator can inspect real state, prepare a bounded operation, execute it, verify it and roll it back when necessary.

## Core contract

Manager should make this loop possible:

```text
external request / strategy
        -> inspect current WordPress state
        -> prepare bounded change set
        -> preview exact diff
        -> execute approved operation
        -> verify stored + rendered result
        -> record operation/evidence
        -> rollback when required
        -> continue iterating
```

## Manager capability families

### 1. Inspect / Site Intelligence

Manager exposes a truthful, bounded representation of the current WordPress site:

- WordPress/PHP/runtime information;
- current Theme/preset/model capabilities;
- pages/posts/CPTs/taxonomies;
- URLs/slugs/statuses/authors/revisions;
- menus and internal-link graph;
- media and relevant alt/context information;
- SEO output authority/provider state;
- canonical/indexability/Schema/discovery state;
- clone/current-environment leakage;
- current permalink/redirect state;
- supported integrations and capabilities;
- operation history and current fingerprints.

Inspection should be read-only by default.

### 2. Preview

Manager accepts a structured proposed operation and returns the exact bounded effect before writing:

- resource(s) affected;
- field/model changes;
- current fingerprint/revision;
- expected target fingerprint;
- URL/slug implications;
- SEO/output-authority implications;
- collisions/conflicts;
- permissions/capability result;
- whether explicit confirmation is required.

### 3. Execute

Manager performs approved WordPress mutations using local WordPress APIs and product contracts:

- create/update pages and posts;
- create/update Theme semantic model data;
- update supported SEO-provider fields through accepted adapters;
- manage internal links/navigation where authorized;
- manage media references/metadata where authorized;
- manage approved taxonomy relationships;
- perform controlled slug/URL/redirect changes when required;
- apply supported configuration changes.

Every mutation remains capability-checked, idempotent where applicable, stale-state protected and bounded.

### 4. Publish / Schedule

Manager can materialize and operate content inside WordPress:

- draft;
- preview;
- approval state;
- schedule;
- publish;
- update/refresh;
- unpublish where policy permits.

Manager-created blog posts remain normal WordPress posts and may be edited by authorized users in Gutenberg.

### 5. Verify

After a write, Manager verifies both local persistence and, when requested, the real public route:

- stored-value verification;
- semantic-model verification;
- exact current permalink;
- bounded same-site rendered verification;
- output-authority consistency;
- operation result/evidence.

### 6. History / Rollback

Manager keeps auditable operation references and supports safe reversal where the operation contract allows it:

- operation ID;
- affected resource identities;
- changed field names/model slots;
- timestamps;
- verification state;
- rollback eligibility;
- stale-state checks before reversal.

Sensitive values, credentials and private content must not leak through public summaries.

## What Manager does not own

Manager does not independently decide:

- which market/keyword to target;
- what competitive strategy to follow;
- which page should exist for business reasons;
- what article should be written next;
- what tone/positioning to use;
- what content cluster should be expanded;
- what GEO opportunity is commercially useful;
- how to prioritize Search Console/Bing opportunities;
- how a strategic page should look visually;
- what business claims are true.

Those decisions belong to external orchestration and/or the human operator.

Manager may calculate deterministic diagnostics, scores, conflicts and capabilities required to execute safely. Deterministic local analysis is allowed; strategic reasoning remains external.

## Admin UI role

The WordPress admin screen is an operator/safety surface, not the final control center of the product.

It should support:

- health and connection status;
- capability discovery;
- diagnostics;
- local preview/approval where useful;
- emergency rollback/history;
- evidence download;
- permissions/authentication setup.

The normal advanced operating model is remote orchestration through authenticated Manager APIs.

## Independence

Manager must work on supported WordPress sites without SEO/GEO Theme.

When Theme is active, Manager writes content/models/configuration and Theme renders strategic surfaces.

When Theme is not active, Manager operates only through accepted generic/native/provider adapters and must not assume Theme-specific rendering.

---

# External orchestration — the brain

## Purpose

The external orchestration layer is where strategy and intelligence live. This is how we operate client WordPress sites from outside WordPress, in the same development-style loop used on other software projects.

It may include human direction, ChatGPT-assisted work, research workflows, scheduled automation and future hosted orchestration services.

## Owns

External orchestration owns:

- business/SEO/GEO strategy;
- keyword and intent research;
- competitor/market research;
- content planning;
- article generation;
- landing-page briefs;
- copywriting and optimization;
- entity/fact/source planning;
- internal-link strategy;
- content-cluster strategy;
- Search Console/Bing/analytics interpretation;
- opportunity prioritization;
- refresh decisions;
- image/media planning;
- deciding which structured operation to ask Manager to perform;
- evaluating verification results and choosing the next action.

## Example — create a landing

```text
1. External orchestrator researches opportunity.
2. External orchestrator inspects current site through Manager.
3. External orchestrator checks duplicate intent/cannibalization.
4. External orchestrator prepares content + SEO/GEO intent + link plan + media plan.
5. Manager previews the exact WordPress/model changes.
6. Human/policy approves.
7. Manager executes locally.
8. Theme renders the strategic page.
9. Manager verifies stored and public result.
10. External orchestrator evaluates result and continues optimization.
```

## Example — publish a blog article

```text
1. External orchestrator chooses topic from research/signals.
2. External orchestrator creates the full article, sources, author binding, metadata intent and link plan.
3. Manager creates a normal WordPress draft.
4. Human may edit in Gutenberg.
5. Manager schedules/publishes under policy.
6. Theme renders the public article shell when active.
7. Manager verifies the public result.
8. External orchestrator later decides when/how to refresh it.
```

## Example — optimize an existing page

```text
1. External orchestrator requests current page/site evidence from Manager.
2. External orchestrator analyzes intent, content, entities, links and search data.
3. External orchestrator prepares a bounded change set.
4. Manager previews the exact diff against the current revision/fingerprint.
5. Manager applies the approved change.
6. Manager verifies stored + rendered result.
7. External orchestrator measures and iterates.
```

---

# Product interaction by client scenario

## A. New WordPress site

```text
SEO/GEO Theme
      -> external orchestration creates/optimizes content
      -> SEO/GEO Manager executes and verifies ongoing operations (optional at first, recommended for managed service)
```

Commercially sellable as:

- Theme only;
- Theme + Manager;
- Theme + Manager + managed orchestration/service.

## B. Existing site, full redesign/replatform

```text
Migration Bridge
      -> rescue/reset/handoff
      -> SEO/GEO Theme
      -> SEO/GEO Manager
      -> external orchestration for continuous operation
```

This is the EMMAKE reference path.

## C. Existing WordPress, keep current design

```text
existing WordPress/theme
      -> SEO/GEO Manager
      -> accepted provider/native adapters
      -> external orchestration for analysis/content/optimization
```

Theme is not required.

## D. One-time migration client

```text
Migration Bridge only
```

The client may use another Theme afterward. Migration Bridge remains commercially useful independently.

## E. Theme-only customer

```text
SEO/GEO Theme only
```

The site still receives the documented Theme baseline without requiring Manager.

## F. Managed growth client

```text
SEO/GEO Manager installed
      <-> external orchestration
```

Theme may be ours or a supported third-party theme. This is the recurring service/product path for continuous SEO/GEO/content operations.

---

# Hard independence rules

1. Migration Bridge must not be required for a clean new Theme installation.
2. Theme must not require Manager for baseline rendering/SEO/GEO behavior.
3. Manager must not require Theme to inspect and operate a supported WordPress site.
4. Manager must not require Migration Bridge for ordinary ongoing operation.
5. Migration Bridge must not become the permanent content strategy/SEO-growth product.
6. Theme owns frontend rendering, not Manager.
7. Manager owns local execution/control, not strategic reasoning.
8. External orchestration owns strategy/research/creation/optimization decisions, not public frontend rendering.
9. Exactly one accepted output authority may emit each canonical/robots/hreflang/Schema/social/sitemap signal.
10. No product may hardcode a client domain, content strategy or client-specific business fact.
11. All three products must have independent versions, installable artifacts, release lifecycles and acceptance gates.
12. A client must be able to buy/use each product independently when the scenario supports it.

---

# Security and remote-control contract

Because Manager is the bridge between external orchestration and client WordPress, remote control must be safer than ad-hoc admin automation.

Required principles:

- authenticated scoped WordPress identity or API client;
- least-privilege capabilities;
- HTTPS;
- revocable credentials;
- no secrets committed to repository/content;
- nonce protection for wp-admin actions;
- idempotency for create/update operations;
- expected-revision/fingerprint checks;
- environment binding for risky production writes;
- explicit confirmation for destructive/high-impact changes;
- bounded payloads and responses;
- operation history;
- rollback where the operation contract allows it;
- no arbitrary shell/file-system execution exposed as a generic remote API;
- no hidden autonomous mutation outside an accepted policy.

Manager should expose semantic WordPress/product operations, not raw unrestricted server control.

---

# Commercial packaging

The repository must support three separately marketable products:

## SEO/GEO Migration Bridge

Value proposition: **move/rebuild safely without losing the useful digital asset or carrying legacy technical debt.**

Typical buyer:

- agency;
- developer;
- business replatforming an old WordPress site.

## SEO/GEO Theme

Value proposition: **fast, modern, semantic WordPress frontend with native SEO/GEO foundations and reusable premium presets.**

Typical buyer:

- agency;
- developer;
- business launching a new site or redesign.

## SEO/GEO Manager

Value proposition: **secure operational bridge that lets an authorized external operator inspect, create, optimize, publish, verify and roll back changes in WordPress.**

Typical buyer:

- agency managing many client sites;
- business retaining an external SEO/content operation service;
- developer/automation team needing a stable WordPress control API.

The managed intelligence/orchestration service may be sold separately as a service layer, but it must not be required for the underlying products to remain technically valid.

---

# Anti-drift rules for future development

Before implementing a new feature, classify it:

### If the feature answers "How do we transport/rescue/reset the old site?"
It belongs to **Migration Bridge**.

### If the feature answers "How is the public website rendered/designed/performed?"
It belongs to **Theme**.

### If the feature answers "How can an authorized external operator safely read/write/verify WordPress state?"
It belongs to **Manager**.

### If the feature answers "What should we create/change/optimize and why?"
It belongs to **external orchestration**, not the WordPress plugin.

Deterministic local diagnostics needed for safe execution remain valid Manager features. Strategic/creative reasoning does not.

A migration/permalink edge case must not consume the Manager roadmap indefinitely. Once the bridge can safely expose/execute the required bounded operation, strategic reasoning and policy stay outside the plugin.

---

# Development priority from this decision

## Migration Bridge

- freeze accepted migration behavior;
- keep clone/export/import/rescue/reset/cutover reliable;
- productize installation, UX and release artifacts;
- avoid expanding into ongoing content operations.

## Theme

- finish Corporate field acceptance;
- generalize Theme-owned semantic renderer contract;
- complete the remaining preset families;
- preserve performance/accessibility/SEO/GEO budgets;
- productize independent installation/update lifecycle.

## Manager

Priority is not to build an internal autonomous SEO brain.

Priority is to complete the generic remote-control surface:

1. reliable Site Intelligence/capability discovery;
2. stable authenticated remote API contract;
3. generic Preview -> Apply -> Verify -> Rollback primitives;
4. Theme semantic-model read/write operations;
5. standard WordPress post/page create/update/publish operations;
6. media/navigation/internal-link operations;
7. SEO provider/output-authority adapters;
8. operation history/evidence;
9. production safety, idempotency and stale-state protection;
10. client-agnostic acceptance on both Theme and non-Theme WordPress sites.

After these primitives exist, landing creation, blog publication, optimization and growth are primarily orchestration workflows that call Manager capabilities rather than large autonomous engines embedded inside WordPress.

---

# EMMAKE reference interpretation

EMMAKE `/nuevaweb/` is the first real reference implementation, not a special architecture.

The intended path is:

```text
old EMMAKE
   -> Migration Bridge
   -> /nuevaweb/ clean rebuild workspace
   -> SEO/GEO Theme Corporate
   -> SEO/GEO Manager connection/control
   -> external orchestration from here
   -> finish
   -> optimize
   -> grow
```

EMMAKE-specific permalink/slug evidence is useful field validation, but it must not redefine Manager as a migration/permalink product.

Once the minimum safe URL/redirect transition is proven, the Manager roadmap returns to generic WordPress control capabilities and external orchestration workflows.

---

# Definition of portfolio success

The architecture is successful when:

- each of the three products can be installed, versioned, sold and supported independently;
- the same Manager can be installed on many supported client WordPress sites without client-specific code;
- the same Theme/preset system can serve many client sites without copying legacy layouts;
- Migration Bridge can complete one-time replatform work without becoming a permanent dependency;
- external orchestration can inspect and operate a client site through Manager without manual wp-admin repetition;
- strategic pages render from Theme-owned semantic models;
- blogs remain normal WordPress posts and client-editable;
- SEO/GEO output never has competing owners;
- every remote mutation is bounded, auditable, verifiable and reversible where supported;
- strategy/research/creation can evolve quickly outside WordPress without forcing a plugin release;
- WordPress remains fast and locally renderable even if the external orchestration service is unavailable.

## Final north-star sentence

> **Migration Bridge brings the asset. Theme builds the experience. Manager gives us safe control. We provide the intelligence.**
