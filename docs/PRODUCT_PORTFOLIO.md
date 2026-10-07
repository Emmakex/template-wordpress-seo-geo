# Product portfolio

## Decision

The WordPress SEO/GEO project is a **two-product portfolio**. The products share contracts and reusable libraries, but neither may become a mandatory runtime dependency of the other.

The portfolio now follows the canonical ownership rule documented in `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`:

> **Gutenberg provides editorial autonomy. SEO/GEO Manager provides automation and growth. SEO/GEO Theme provides frontend rendering, design, semantic HTML and performance.**

### Product A — SEO/GEO Theme

A self-contained WordPress Theme for new builds and full redesign/migration projects.

Core promises:

- one installable Theme artifact;
- zero required SEO/GEO plugins for the documented baseline;
- native technical SEO/GEO, Schema, multilingual, accessibility and performance contracts;
- five reusable presets;
- Theme-owned server rendering for strategic pages;
- clean-install and migrated-site onboarding;
- deterministic release, upgrade, rollback and production verification.

The Theme must remain fully usable when SEO/GEO Manager is not installed.

Strategic preset surfaces such as Home, commercial landings, service pages, location pages, campaign pages and master hubs are rendered by Theme-owned semantic renderers when the Theme provides that surface. Gutenberg may remain available for editorial content and ordinary simple pages, but it is not the master layout authority for these strategic surfaces.

### Product B — SEO/GEO Manager

A permanent, independently installable WordPress plugin for existing or new WordPress sites.

Core promises:

- works without requiring the SEO/GEO Theme;
- acts as the permanent optimization/content-operations layer after launch;
- creates and manages structured strategic landing pages;
- **creates complete blog posts automatically** as normal WordPress posts;
- supports draft, preview, approval, scheduling, publication, update, refresh and rollback;
- allows authorized users to edit Manager-created blog posts in Gutenberg;
- refreshes and improves existing content and internal linking;
- coordinates SEO/GEO output through an explicit authority/provider resolver;
- consumes Search Console/Bing/analytics feedback when connected;
- can use those signals to identify new landing/blog opportunities and refresh needs;
- contains migration capabilities only as an optional later module;
- keeps publication/update/rollback auditable;
- may remain installed permanently because ongoing publishing/optimization is its primary capability.

The Manager is not the deprecated standalone Core wrapper. It is a separate product with its own package, version, release artifact and acceptance lifecycle.

## WordPress/Gutenberg role

WordPress remains the CMS and resource authority for:

- page/post identity;
- slugs/URLs;
- publication status;
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
- simple informational pages where premium strategic composition is not required.

Gutenberg is **not** the canonical layout engine for Theme-owned strategic pages.

## Commercial combinations

| Client scenario | Theme | Manager | Expected path |
| --- | --- | --- | --- |
| New site / redesign | Yes | Optional | Build with the self-contained Theme and Theme-owned preset renderers; add Manager when ongoing automated publishing/operations are desired. |
| Existing WordPress, keep current design | No | Yes | Analyze current stack, resolve SEO authority, publish through supported content/provider adapters. |
| Existing WordPress, full redesign/replatform to SEO/GEO Theme | Yes | Recommended afterward | Migration Bridge creates the clone and Rescue Manifest, the clone is reset, rescued content is mapped into Theme semantic models, then Manager becomes the ongoing publishing/optimization layer. |
| Agency-managed content operations | Optional | Yes | Use Manager as the controlled publishing endpoint for automated landings, blog posts, refreshes and internal-link changes across supported client stacks. |
| Client-managed editorial site | Optional | Optional | Client may keep writing/editing ordinary posts in Gutenberg while Theme controls the public shell and Manager optionally automates growth operations. |

## Hard independence rules

1. Installing the Theme must never require SEO/GEO Manager.
2. Installing SEO/GEO Manager must never require the Theme.
3. If both are active, the Theme remains the native SEO/GEO presentation/runtime authority unless an explicit integration contract says otherwise.
4. Manager must not emit duplicate canonical, robots, hreflang, Schema, sitemap or social metadata.
5. Existing SEO providers are detected before Manager claims an output surface.
6. A migration capability may be disabled after handoff while the Manager plugin remains active for publishing.
7. The deprecated Core wrapper is not repurposed as the Manager product.
8. Product versions and release channels are independent.
9. Theme-owned strategic surfaces must not depend on Gutenberg layout rules as their presentation authority.
10. Manager-created blog posts remain standard WordPress posts and client-editable in Gutenberg.
11. Manager automation must remain auditable, idempotent and rollback-capable.

## Shared source boundaries

The repository may continue to host shared, context-neutral libraries under `packages/seo-geo-core/src` when doing so avoids duplicated SEO/GEO rules.

Shared source does **not** imply shared activation:

- Theme bundles the subset it needs into its deterministic Theme ZIP;
- Manager packages the subset it needs inside its own plugin ZIP;
- runtime initialization must be collision-safe when both products are active;
- public output ownership is resolved once per signal, not once per package.

## Content-model boundary

Strategic Theme pages use versioned semantic models rather than visual Gutenberg layout trees.

Examples may include:

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

Manager creates/updates model data. Theme renders it. WordPress retains resource identity.

For blog automation, Manager may use a structured article model internally, then materialize a normal WordPress post body in a minimal stable Gutenberg-compatible editorial representation. Theme remains responsible for the public article shell and presentation.

## Automated content portfolio

Manager content automation covers two first-class families.

### Strategic pages

- Home variants where explicitly supported;
- landing pages;
- service/solution pages;
- product pages;
- location/GEO pages;
- campaign pages;
- commercial hubs/clusters.

These use Theme semantic renderers when the SEO/GEO Theme is active.

### Blog/editorial content

- new article creation;
- draft generation;
- author/source/provenance binding;
- category/tag policy;
- internal linking;
- scheduled publishing;
- post-publication verification;
- content refresh/update;
- rollback;
- Gutenberg editing by authorized users.

Automated blog creation is not a secondary convenience; it is part of the core ongoing-growth product.

## Product lifecycle

### Theme lifecycle

The current Theme release remains governed by real-site acceptance. The Manager roadmap does not add a new stable-release blocker to the Theme package itself.

However, the EMMAKE Corporate v4.x pilot exposed an architectural layout issue: Gutenberg constrained-layout behavior can fragment a premium strategic page even when the Theme attempts to own widths through CSS.

The next active Theme architecture checkpoint is **Corporate v5 — Theme-owned frontend**. It must prove the semantic renderer boundary before the other four preset master surfaces continue visual rollout.

### Manager lifecycle

Manager starts a separate version line after its package contract exists. It receives its own:

- version source of truth;
- changelog;
- installable ZIP;
- integrity/checksum contract;
- upgrade/rollback acceptance;
- WordPress/PHP compatibility matrix;
- security and capability contract;
- real-site acceptance matrix.

Its first stable path must prove both strategic landing creation and automated blog creation.

## Replatforming boundary

The commercial redesign path follows `docs/RESET_REBUILD_CONTRACT.md` and `docs/REPLATFORMING_CONTRACT.md`: the clone is a disposable rebuild workspace, the rescue set is deliberately minimal, and the old presentation/runtime is removed rather than normalized into the new product.

The new presentation model must preserve authored content, URLs/redirects, valid SEO signals, important links, useful media, verified organization/contact facts and required business behavior while discarding legacy builder/layout debt.

A legacy Gutenberg/Divi/Elementor layout tree must not become the canonical content model of the rebuilt strategic surface.

This is the intended WordPress equivalent of product-style web builds used elsewhere in the portfolio: component-based, modern, fast and centrally improvable, while preserving WordPress content ownership and search equity.

## Migration Bridge transition

`packages/seo-geo-migration-bridge` is the already-accepted Phase 8 implementation of migration behavior. Its current package remains valid evidence for the Theme migration path.

The long-term product direction is:

```text
Phase 8 Migration Bridge capabilities
        ↓ preserve accepted behavior
SEO/GEO Manager
  ├── Site Intelligence
  ├── Authority Resolver
  ├── Content Publishing Core
  ├── Landing Engine
  ├── Automated Blog Engine
  ├── Growth / Opportunity Engine
  ├── Internal-link / cluster engine
  ├── Migration module
  ├── Portable Clone Engine
  ├── Portable Sandbox coordinator
  └── Provider / builder integrations
```

The **bridge package** may eventually be retired after its accepted contracts are absorbed and regression-covered inside Manager. The **migration capability** is not retired; it becomes an optional Manager module.

## Roadmap order

The product roadmap is now:

1. document/freeze the WordPress + Gutenberg + Theme + Manager ownership model;
2. implement **Corporate v5 Theme-owned frontend** from the existing semantic Home model;
3. prove Corporate v5 on the real `/nuevaweb/` sandbox without rerunning migration/reset merely for rendering changes;
4. generalize the Theme semantic renderer/model registry;
5. move SaaS, Local Pro, Publisher and Ecommerce master surfaces to the same renderer architecture;
6. implement Manager Content Publishing Core;
7. implement Manager Landing Engine against the semantic model contract;
8. implement **Manager Automated Blog Engine** with normal WordPress/Gutenberg editorial interoperability;
9. implement internal-link, cluster and search/analytics-driven opportunity/refresh loops;
10. absorb Migration Bridge capabilities into Manager only after the optimization/publishing core is stable.

## Product success criteria

The portfolio is successful only when all of the following remain true:

- Theme works with zero required plugins;
- Manager works on a supported WordPress site without the Theme;
- Theme + Manager do not duplicate SEO/GEO ownership;
- strategic Theme pages render without Gutenberg governing their master layout;
- existing-provider sites can be analyzed without destructive mutation;
- Landings/blogs can be previewed, published idempotently and rolled back.
- complete blog posts can be created automatically by Manager;
- Manager-created blog posts remain editable in Gutenberg;
- existing blog posts can be refreshed without silently overwriting newer human revisions;
- internal-link/cluster changes use real targets;
- clients without staging have a safe portable-sandbox path;
- supported environments should not require a third-party cloning plugin because Portable Clone Engine owns clone/export/import transport;
- dynamic sites never receive an unsafe stale-database overwrite during cutover;
- both products can be sold, versioned, updated and supported independently.
