# SEO/GEO Manager product modes

Status: **authoritative workflow interpretation**

Canonical portfolio boundary: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Decision

SEO/GEO Manager participates in **Build / Finish, Optimize and Grow** workflows, but those names describe the lifecycle modes of the **external operating system**, not three autonomous strategy engines that must live inside the WordPress plugin.

Manager is the local bridge/control agent.

External orchestration is the brain.

The product split is:

| Layer/product | Owns |
| --- | --- |
| SEO/GEO Migration Bridge | scan, clone/transport, rescue, reset-first migration and cutover support |
| SEO/GEO Theme | frontend rendering, presets, semantic HTML, responsive design, accessibility, performance |
| SEO/GEO Manager | Site Intelligence, safe WordPress control, preview/apply/publish/verify/history/rollback |
| External orchestration | strategy, research, creation, optimization reasoning, opportunity prioritization and growth decisions |

The products cooperate through explicit contracts and remain independently installable/distributable.

---

# Mode 1 — Build / Finish

## Purpose

Take a new, reset or recently migrated site from "technically assembled" to "ready to launch".

## External orchestration owns

- deciding which strategic pages/resources are required;
- evaluating content completeness and positioning;
- deciding what copy/content should change;
- deciding which links/CTAs/media are appropriate;
- deciding SEO/GEO improvements based on real evidence;
- deciding what should be fixed first;
- generating/revising the actual content/change intent.

## Manager owns

- inventorying the real current WordPress state;
- exposing pages/posts/menus/taxonomies/media/public URLs;
- exposing Theme/preset/model capabilities;
- detecting current-environment/source-host leakage;
- exposing output-authority/indexability/canonical/Schema evidence;
- exposing media/internal-link/navigation state;
- previewing exact proposed changes;
- creating/updating authorized WordPress resources/models;
- publishing/scheduling when requested;
- verifying stored/public results;
- recording operation/rollback references.

## Theme owns

- rendering strategic pages;
- responsive composition;
- semantic HTML;
- visual design;
- accessibility;
- performance;
- Theme-owned SEO/GEO public output.

## Typical flow

```text
external request
  -> Manager inspection
  -> external reasoning/content creation
  -> bounded operation
  -> Manager preview
  -> approval
  -> Manager apply/publish
  -> Theme/WordPress renders
  -> Manager verify
  -> external iteration
```

---

# Mode 2 — Optimize

## Purpose

Improve an already running site without requiring a rebuild.

## External orchestration owns

- page-by-page SEO/GEO analysis;
- search intent analysis;
- entity/content clarity reasoning;
- content completeness recommendations;
- cannibalization/thin/stale diagnosis using available evidence;
- internal-link strategy;
- metadata/content/Schema improvement decisions;
- local/GEO opportunity reasoning;
- refresh prioritization;
- deciding exactly what change to request.

## Manager owns

- retrieving current resource/content/SEO/link state;
- exposing Search Console/Bing/analytics connector data only when a supported connector exists, or exposing stored/local references needed by the external workflow;
- checking output authority/provider capabilities;
- previewing exact changes against current revisions/fingerprints;
- applying accepted changes;
- verifying and recording the result;
- stale-safe rollback where supported.

Manager may compute deterministic diagnostics such as missing fields, broken links, provider conflicts, stale fingerprints or model completeness. Strategic interpretation remains external.

---

# Mode 3 — Grow

## Purpose

Operate the continuous organic-growth/content loop after launch.

## External orchestration owns

- interpreting Search Console/Bing/analytics/keyword/market signals;
- deciding new landing opportunities;
- deciding new article topics;
- creating full landing/article content;
- planning content clusters;
- planning internal links;
- deciding stale-content refreshes;
- prioritizing commercial/GEO opportunities;
- measuring outcomes and deciding the next iteration.

## Manager owns

- exposing current site inventory and capabilities;
- preventing duplicate target/URL collisions;
- creating normal WordPress posts/pages/drafts;
- writing Theme semantic models;
- scheduling/publishing/updating;
- applying accepted internal-link/navigation/media changes;
- coordinating writes through accepted output-authority/provider adapters;
- verifying public results;
- recording operation evidence and rollback references.

## Theme owns

- strategic landing rendering;
- public article shell;
- responsive/accessibility/performance behavior;
- Theme-owned SEO/GEO output.

---

# Lifecycle flows

## New site

```text
SEO/GEO Theme
      -> external orchestration
      <-> SEO/GEO Manager when managed operation is enabled
      -> launch
      -> ongoing Optimize / Grow workflows
```

## Migrated site

```text
SEO/GEO Migration Bridge
      -> SEO/GEO Theme
      -> SEO/GEO Manager as ongoing control bridge
      <-> external orchestration
      -> Build / Finish
      -> launch
      -> Optimize / Grow
```

## Existing third-party WordPress site

```text
existing WordPress/theme/providers
      <-> SEO/GEO Manager
      <-> external orchestration
      -> Optimize / Grow through accepted adapters
```

---

# EMMAKE reference workflow

EMMAKE is the first real Build / Finish reference implementation, not a special-case architecture.

The external operating system should be able to:

1. connect to `/nuevaweb/` through Manager;
2. read the real current WordPress state;
3. inspect pages/posts/URLs/menus/media/models/SEO authority;
4. detect source/production-domain leakage;
5. reason externally about missing/incomplete content, SEO/GEO, links and media;
6. prepare bounded WordPress/Theme-model changes;
7. ask Manager for preview/diff;
8. apply accepted changes without overwriting newer human edits;
9. verify the resulting stored/public resource;
10. retain operation/rollback evidence;
11. repeat until launch readiness;
12. continue with Optimize/Grow without changing products.

EMMAKE-specific permalink/slug issues are field validation for safe URL operations. They must not become the dominant Manager roadmap indefinitely.

---

# Launch readiness

Build / Finish may expose/read evidence grouped into:

- **Structure:** required resources exist and resolve;
- **Content:** strategic slots are populated with non-placeholder content;
- **Navigation:** internal links resolve to real current-environment targets;
- **SEO:** title/meta/canonical/indexability authority is known and non-conflicting;
- **GEO:** geographic intent is supported by visible verified facts where relevant;
- **Schema/entities:** structured-data intent matches visible content;
- **Media:** required media/context/alt policy is satisfied;
- **Editorial:** blog/archive/article surfaces work;
- **Technical:** sitemap/discovery surfaces exist and clone/source leakage is absent;
- **Operations:** Manager can safely preview/apply/verify/rollback supported changes.

The evidence may be deterministic Manager output.

The strategic decision "is this good enough to launch?" remains an external/human policy decision unless an explicit bounded launch policy is defined.

---

# Operator contract

The canonical loop is:

```text
request
  -> Manager inspects real state
  -> external operator explains issue/opportunity
  -> external operator prepares content/change set
  -> Manager previews exact diff
  -> approval/policy
  -> Manager applies
  -> Manager verifies
  -> operation + rollback reference
  -> external operator iterates
```

This is the WordPress equivalent of the fast inspect/change/verify loop used on software projects while preserving WordPress revisions, permissions and client autonomy.

---

# Manager MVP roadmap after this correction

Immediate Manager priority is:

1. **M1 Site Intelligence / capability discovery**;
2. **M2 generic Preview / Apply / Verify / Rollback primitives**;
3. stable authenticated/scoped remote API;
4. Theme semantic-model read/write operations;
5. normal WordPress page/post draft/update/publish operations;
6. media/navigation/internal-link/taxonomy primitives;
7. output-authority/provider adapters;
8. prove external Build / Finish workflow on EMMAKE;
9. prove external Optimize workflow;
10. prove external landing/blog Grow workflows;
11. integrate Search Console/Bing/analytics into the external orchestration loop.

The previous labels `M3 Optimizer`, `M4 Landing Engine`, `M5 Blog Engine`, `M6 Growth Engine` must not be interpreted as a requirement to embed autonomous strategy/generation engines inside WordPress.

If retained as roadmap names, they mean **end-to-end workflows using Manager primitives plus external orchestration**.

---

# Anti-drift test

Before adding a Manager feature, ask:

> Does this feature help an authorized external operator safely inspect/read/write/publish/verify WordPress state?

If yes, it probably belongs to Manager.

If the feature instead answers:

> What should we create/optimize, why, for which keyword/market/client priority, and what should the content say?

then it belongs to external orchestration.
