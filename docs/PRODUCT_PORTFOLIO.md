# Product portfolio

Status: **authoritative commercial/product boundary**

Canonical operating model: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Decision

The WordPress SEO/GEO project is a **three-product portfolio**:

1. **SEO/GEO Migration Bridge** — transition/migration product;
2. **SEO/GEO Theme** — frontend/rendering product;
3. **SEO/GEO Manager** — local WordPress bridge/control product.

The strategic intelligence layer is external to WordPress and is operated by us through human + ChatGPT-assisted + future automation workflows.

The portfolio follows this permanent rule:

> **Migration Bridge brings the asset. Theme builds the experience. Manager gives us safe control. We provide the intelligence.**

The three products may share contracts and context-neutral libraries, but none may become a mandatory runtime dependency of the others unless a specific commercial bundle explicitly chooses to install more than one.

---

## Product A — SEO/GEO Migration Bridge

### Role

One-time transition/replatform product for existing WordPress sites.

### Core promises

- inspect the source safely;
- clone/export/import through supported transport;
- rescue only valuable content/SEO/link/media/business assets;
- create a bounded Rescue Manifest;
- reset legacy presentation/runtime debt where the rebuild path requires it;
- support cutover verification and migration recovery;
- hand a clean destination to Theme or another accepted destination stack.

### Commercial independence

Migration Bridge is a sellable product in its own right. It is not merely a temporary internal package waiting to be deleted.

A client may buy Migration Bridge without buying our Theme or Manager.

### Permanent boundary

Bridge does not own ongoing content strategy, continuous optimization, recurring blog publication or growth operations.

---

## Product B — SEO/GEO Theme

### Role

Self-contained WordPress frontend/presentation product for clean builds and redesigns.

### Core promises

- one deterministic installable Theme artifact;
- zero required SEO/GEO plugins for the documented baseline;
- native technical SEO/GEO, Schema, multilingual, accessibility and performance contracts;
- reusable preset families;
- Theme-owned server rendering for strategic pages;
- semantic model-driven strategic content;
- deterministic release, upgrade and verification lifecycle.

### Preset families

- Corporate;
- Local Business / Local Pro;
- SaaS / Digital Product;
- Publisher / Editorial;
- Ecommerce.

### Rendering authority

Strategic preset surfaces such as Home, commercial landings, service pages, location pages, campaign pages and master hubs are rendered by Theme-owned semantic renderers.

Gutenberg remains available for editorial/simple content but is not the master layout authority for those strategic surfaces.

### Commercial independence

Theme must remain fully usable when Manager is not installed.

A client may buy Theme only.

---

## Product C — SEO/GEO Manager

### Role

Permanent, independently installable **WordPress bridge/control agent** for existing or new WordPress sites.

### Core promises

Manager gives an authorized external operator:

- **eyes** — Site Intelligence, resources, URLs, SEO authority, models, media, links and capabilities;
- **preview** — exact bounded diff before mutation;
- **hands** — create/update supported WordPress resources and Theme models;
- **publication control** — draft, schedule, publish, update;
- **verification** — stored state + optional rendered/public verification;
- **safety** — capability checks, revision/fingerprint guards, idempotency and environment binding;
- **memory of operations** — history/evidence and stale-safe rollback where supported.

### Critical architectural correction

Manager is **not the strategic brain**.

It must not accumulate the full logic for:

- keyword research;
- market/competitor strategy;
- deciding article topics;
- deciding which landing should exist;
- business positioning;
- content-cluster prioritization;
- Search Console/Bing interpretation;
- copywriting strategy;
- growth prioritization.

Those activities belong to external orchestration.

Manager may provide deterministic diagnostics required for safe execution and expose real site evidence to the external operator.

### Commercial independence

Manager must work on supported WordPress sites without requiring our Theme or Migration Bridge.

A client may buy Manager for an existing WordPress site and keep the current design/theme when an accepted adapter path exists.

---

# External orchestration — not an installable WordPress product

External orchestration is the **brain/operator layer** used by us to govern client sites.

It may combine:

- human direction;
- ChatGPT-assisted research/reasoning;
- automated research workflows;
- Search Console/Bing/analytics interpretation;
- scheduled content workflows;
- future hosted agency automation.

It owns:

- strategy;
- research;
- content creation;
- landing/article briefs;
- optimization reasoning;
- internal-link/cluster strategy;
- opportunity prioritization;
- deciding which Manager operation to request next.

The intelligence layer may evolve rapidly without requiring a WordPress plugin release.

---

# WordPress/Gutenberg role

WordPress remains the CMS/resource authority for:

- page/post identity;
- slugs/URLs;
- statuses;
- authors;
- revisions;
- permissions/capabilities;
- media;
- taxonomies;
- local persistent content state.

Gutenberg remains supported for:

- client-authored blog posts;
- manual edits to Manager-created articles;
- legal/privacy/cookie pages;
- simple informational pages;
- explicitly editorial content.

Gutenberg is not the canonical layout engine for Theme-owned strategic pages.

---

# Content-model boundary

Strategic Theme pages use versioned semantic models rather than visual Gutenberg layout trees.

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

The responsibility split is:

```text
External orchestration -> decides and creates semantic content/change intent
SEO/GEO Manager       -> safely persists/updates WordPress resources and models
SEO/GEO Theme         -> renders the public strategic frontend
WordPress              -> retains identity, revisions, permissions and local state
```

For blog/editorial content:

```text
External orchestration -> chooses topic and creates/optimizes article
SEO/GEO Manager       -> materializes normal WordPress draft/post and controls publication
Gutenberg              -> optional human/client body editing
SEO/GEO Theme          -> public article shell when active
```

Manager-created blog posts remain standard WordPress posts.

---

# Commercial combinations

| Client scenario | Migration Bridge | Theme | Manager | Expected path |
| --- | --- | --- | --- | --- |
| New site | No | Yes | Optional/recommended for managed operation | Theme builds the site; Manager added when remote ongoing operation is desired. |
| Existing WordPress, keep current design | No | No | Yes | Manager exposes supported current WordPress/provider capabilities to external orchestration. |
| Existing WordPress, full redesign | Yes | Yes | Recommended | Bridge rescues/reset/handoff; Theme renders rebuild; Manager becomes ongoing control bridge. |
| One-time migration | Yes | Optional | No | Bridge may hand off to another destination stack. |
| Theme-only customer | No | Yes | No | Theme provides documented baseline independently. |
| Agency-managed SEO/content growth | Optional | Optional | Yes | Manager is the secure execution bridge; strategy/content live externally. |
| Full managed portfolio | Yes when needed | Yes | Yes | End-to-end migration -> rebuild -> continuous operation. |

---

# Hard independence rules

1. Installing Theme must never require Manager.
2. Installing Manager must never require Theme.
3. Installing Migration Bridge must never require Theme or Manager unless a specific workflow stage explicitly installs them.
4. Migration Bridge remains a first-class product; accepted migration behavior is not automatically absorbed into Manager.
5. If Theme + Manager are active, Theme owns frontend rendering while Manager owns controlled local execution.
6. Manager must not generate duplicate canonical, robots, hreflang, Schema, sitemap or social output.
7. Existing SEO providers are detected before Manager claims/writes an output surface.
8. Manager-created blog posts remain standard WordPress posts and client-editable in Gutenberg.
9. Theme-owned strategic surfaces must not depend on Gutenberg layout rules as presentation authority.
10. Manager remote operations must remain auditable, idempotent and stale-state protected.
11. Strategy/research/creation must not be hardcoded into client WordPress installations.
12. Product versions, release artifacts and release channels are independent.
13. No product may hardcode a client domain or client-specific business rule as generic behavior.

---

# Shared source boundaries

The repository may host context-neutral shared libraries under `packages/seo-geo-core/src` when this avoids duplicated rules.

Shared source does not imply shared activation:

- Theme bundles the subset needed by Theme;
- Manager packages the subset needed by Manager;
- Migration Bridge packages the subset needed by migration workflows;
- runtime initialization must be collision-safe;
- public output ownership is resolved once per signal.

---

# Product lifecycles

## Migration Bridge lifecycle

Must have its own:

- installable ZIP/version;
- migration acceptance matrix;
- clone/export/import acceptance;
- rescue/reset/cutover verification;
- safety/recovery documentation;
- upgrade/rollback policy where applicable.

## Theme lifecycle

Must have its own:

- version source of truth;
- deterministic Theme ZIP;
- five-preset acceptance;
- responsive/accessibility/performance gates;
- native SEO/GEO output gates;
- upgrade/rollback/production verification.

## Manager lifecycle

Must have its own:

- version source of truth;
- deterministic plugin ZIP;
- WordPress/PHP compatibility matrix;
- authentication/capability contract;
- remote API contract;
- operation safety/history/rollback contract;
- Theme and non-Theme real-site acceptance.

The first stable Manager does **not** require an autonomous strategy engine. It requires a sufficiently complete and safe remote-control surface for external orchestration to operate real client WordPress sites.

---

# Manager capability roadmap after the architectural correction

Manager development priority is:

1. Site Intelligence and capability discovery;
2. authenticated/scoped remote control contract;
3. generic Preview -> Apply -> Verify -> Rollback primitives;
4. Theme semantic-model read/write support;
5. normal WordPress page/post draft/update/publish support;
6. media/navigation/internal-link/taxonomy operations;
7. output-authority and provider adapters;
8. operation history/evidence and production safety;
9. client-agnostic acceptance on Theme and non-Theme sites.

Landing creation, blog creation, optimization and growth then become **external orchestration workflows using these Manager capabilities**.

They may have convenience API endpoints or manifests, but the strategic intelligence that decides/generates them remains outside WordPress.

---

# Migration boundary

The previous direction of eventually retiring Migration Bridge into Manager is superseded by `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

Migration Bridge remains a separately sellable product.

Code or low-level libraries may be shared when useful, but commercial/product ownership remains separate:

```text
Migration Bridge -> migration/rescue/reset/cutover
Manager          -> ongoing safe WordPress control
```

Manager may inspect migration results and execute bounded post-migration corrections, but it must not become the default home for the full migration product.

---

# Product success criteria

The portfolio is successful only when:

- all three products can be sold/versioned/updated independently;
- Bridge can complete migration without becoming a permanent runtime dependency;
- Theme works with zero required SEO/GEO plugins for baseline operation;
- Manager works on supported WordPress without Theme;
- Manager exposes enough safe primitives for external orchestration to build/optimize/publish without repetitive manual wp-admin work;
- strategic Theme pages render without Gutenberg controlling master layout;
- normal editorial posts remain WordPress-native and client-editable;
- output authority is never duplicated;
- remote mutations are bounded, verified and reversible where supported;
- external intelligence can improve independently of WordPress plugin release cycles;
- the same products can be reused across clients without client-specific forks.
