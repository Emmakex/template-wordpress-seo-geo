# Documentation authority

Status: **authoritative precedence contract**

Date: 2026-10-10

This repository contains valuable historical phase documentation alongside the current product architecture. To prevent an older accepted phase or roadmap sentence from silently redefining the current product direction, documentation has an explicit precedence order.

## Level 1 — current product authority

When interpreting product ownership, commercial boundaries or new roadmap work, these documents win:

1. `docs/THREE_PRODUCT_OPERATING_MODEL.md`
2. `docs/CURRENT_THREE_PRODUCT_ROADMAP.md`
3. `docs/PRODUCT_VISION.md`
4. `docs/PRODUCT_PORTFOLIO.md`
5. `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`
6. `docs/SEO_GEO_MANAGER.md`
7. `docs/SEO_GEO_MANAGER_PRODUCT_MODES.md`
8. `docs/RESET_REBUILD_CONTRACT.md`

The canonical north star is:

> **Migration Bridge brings the asset. Theme builds the experience. Manager gives us safe control. We provide the intelligence.**

## Level 2 — active technical contracts

Technical documents for specific accepted subsystems remain authoritative for their local contract when they do not conflict with Level 1 product ownership.

Examples include:

- Manager versioned implementation contracts such as `docs/SEO_GEO_MANAGER_0.3.34_CAPABILITY_DISCOVERY.md`;
- SEO/GEO specifications;
- native SEO/Schema/discovery contracts;
- performance/accessibility contracts;
- content-publishing/change-set contracts;
- permalink/redirect safety contracts;
- clone/export/import transport contracts;
- provider adapter contracts;
- release/CI/compatibility contracts.

A local safety invariant remains valid even if the product roadmap changes. For example, idempotency, stale-state protection or safe rollback rules are not discarded merely because Manager's commercial role has been clarified.

## Level 3 — historical roadmap / phase evidence

These documents may contain old product-home assumptions while still containing useful implementation and acceptance evidence:

- `docs/ROADMAP.md` historical phases;
- `docs/MIGRATION_BRIDGE.md` Phase 8 implementation/acceptance history;
- old release-candidate notes;
- old PR descriptions;
- old real-site pilot snapshots;
- historical implementation plans.

They remain valuable evidence of what was built and tested, but they do **not** override Level 1 architecture.

## Explicitly superseded directions

The following earlier directions are no longer current product policy:

### "Two-product portfolio"

Superseded.

Current policy: **three independently sellable products** — Migration Bridge, Theme and Manager.

### "Migration Bridge package will be retired after Manager absorbs migration"

Superseded as a product/commercial requirement.

Current policy: Migration Bridge remains an independently sellable migration/replatform product. Low-level libraries may be shared, but Manager does not need to absorb/replace the complete Migration Bridge product.

### "Manager owns the strategic Landing/Blog/Optimizer/Growth intelligence"

Superseded as an architectural interpretation.

Current policy: Manager provides safe WordPress primitives/capabilities. Landing creation, blog creation, optimization and growth are end-to-end **external orchestration workflows** using Manager. Convenience endpoints/manifests may exist, but strategy/research/creation intelligence remains external.

### "wp-admin Manager dashboard is the primary control center"

Superseded as the advanced operating model.

Current policy: wp-admin is a diagnostics/safety/local-operator surface. The advanced managed workflow is external orchestration through authenticated Manager APIs.

### "Historical migration/permalink analysis should keep expanding until every legacy edge case is modeled inside Manager"

Superseded.

Current policy: close the minimum safe real transition, preserve generic safety primitives, then return Manager development to client-agnostic remote WordPress control capabilities.

## How to resolve a conflict

When two documents appear to disagree:

1. identify whether the disagreement is product ownership/roadmap or a local technical safety contract;
2. use Level 1 for ownership/roadmap;
3. preserve Level 2 safety invariants unless explicitly replaced;
4. treat Level 3 text as historical evidence;
5. update the misleading active document before implementing new code if ambiguity remains.

## Change-control rule

A future change to the three-product boundary must be explicit.

It requires coordinated updates to at least:

- `THREE_PRODUCT_OPERATING_MODEL.md`;
- `CURRENT_THREE_PRODUCT_ROADMAP.md`;
- `PRODUCT_VISION.md`;
- `PRODUCT_PORTFOLIO.md`;
- the affected product contract(s).

A single feature branch, PR note or implementation shortcut cannot silently redefine product ownership.
