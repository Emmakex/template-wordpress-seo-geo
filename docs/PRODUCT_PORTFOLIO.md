# Product portfolio

## Decision

The WordPress SEO/GEO project is a **two-product portfolio**. The products share contracts and reusable libraries, but neither may become a mandatory runtime dependency of the other.

### Product A — SEO/GEO Theme

A self-contained WordPress block theme for new builds and full redesign/migration projects.

Core promises:

- one installable theme artifact;
- zero required SEO/GEO plugins for the documented baseline;
- native technical SEO/GEO, Schema, multilingual, accessibility and performance contracts;
- five reusable presets;
- clean-install and migrated-site onboarding;
- deterministic release, upgrade, rollback and production verification.

The Theme must remain fully usable when SEO/GEO Manager is not installed.

### Product B — SEO/GEO Manager

A permanent, independently installable WordPress plugin for existing or new WordPress sites.

Core promises:

- works without requiring the SEO/GEO Theme;
- analyzes the current site before making changes;
- publishes and manages optimized landing pages and blog content;
- coordinates SEO/GEO output through an explicit authority/provider resolver;
- contains migration capabilities as an optional module;
- supports a portable-sandbox workflow when the client has no staging environment;
- keeps content publication, migration and production cutover reversible and auditable;
- may remain installed after migration because publishing/operations are permanent capabilities.

The Manager is not the deprecated standalone Core wrapper. It is a separate product with its own package, version, release artifact and acceptance lifecycle.

## Commercial combinations

| Client scenario | Theme | Manager | Expected path |
| --- | --- | --- | --- |
| New site / redesign | Yes | Optional | Build with the self-contained Theme; add Manager only when ongoing publishing/operations are desired. |
| Existing WordPress, keep current design | No | Yes | Analyze current stack, resolve SEO authority, publish through supported content/provider adapters. |
| Existing WordPress, migrate to SEO/GEO Theme | Yes | Yes during adoption, optional afterward | Manager migration module performs analysis/sandbox/parity/cutover; Manager may stay for ongoing publishing. |
| Agency-managed content operations | Optional | Yes | Use Manager as the controlled publishing endpoint for landings/blogs across supported client stacks. |

## Hard independence rules

1. Installing the Theme must never require SEO/GEO Manager.
2. Installing SEO/GEO Manager must never require the Theme.
3. If both are active, the Theme remains the native SEO/GEO presentation/runtime authority unless an explicit integration contract says otherwise.
4. Manager must not emit duplicate canonical, robots, hreflang, Schema, sitemap or social metadata.
5. Existing SEO providers are detected before Manager claims an output surface.
6. A migration capability may be disabled after handoff while the Manager plugin remains active for publishing.
7. The deprecated Core wrapper is not repurposed as the Manager product.
8. Product versions and release channels are independent.

## Shared source boundaries

The repository may continue to host shared, context-neutral libraries under packages/seo-geo-core/src when doing so avoids duplicated SEO/GEO rules.

Shared source does **not** imply shared activation:

- Theme bundles the subset it needs into its deterministic theme ZIP.
- Manager will package the subset it needs inside its own plugin ZIP.
- Runtime initialization must be collision-safe when both products are active.
- Public output ownership is resolved once per signal, not once per package.

## Product lifecycle

### Theme lifecycle

The current 0.1.0 Theme release remains governed by Phase 10E and real-site acceptance. The Manager roadmap does not add a new blocker to Theme 0.1.0.

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

## Migration Bridge transition

packages/seo-geo-migration-bridge is the already-accepted Phase 8 implementation of migration behavior. Its current package remains valid evidence for the Theme 0.1.0 migration path.

The long-term product direction is:

    Phase 8 Migration Bridge capabilities
            ↓ preserve accepted behavior
    SEO/GEO Manager
      ├── Site Intelligence
      ├── Authority Resolver
      ├── Content Publishing
      ├── Landing Engine
      ├── Blog Engine
      ├── Migration module
      ├── Portable Clone Engine
      ├── Portable Sandbox coordinator
      └── Provider / builder integrations

The **bridge package** may eventually be retired after its accepted contracts are absorbed and regression-covered inside Manager. The **migration capability** is not retired; it becomes an optional Manager module.

## Product success criteria

The portfolio is successful only when all of the following remain true:

- Theme works with zero required plugins.
- Manager works on a supported WordPress site without the Theme.
- Theme + Manager do not duplicate SEO/GEO ownership.
- Existing-provider sites can be analyzed without destructive mutation.
- Landings/blogs can be previewed, published idempotently and rolled back.
- Clients without staging have a safe portable-sandbox path without requiring a third-party cloning plugin for supported environments.
- Dynamic sites never receive an unsafe stale-database overwrite during cutover.
- Both products can be sold, versioned, updated and supported independently.
