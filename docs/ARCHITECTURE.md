# Architecture

## Product boundary

The repository defines a **two-product portfolio** with hard runtime independence.

1. **SEO/GEO Theme** is a single self-contained WordPress block theme. Its baseline SEO/GEO behavior must work on a clean WordPress installation with **zero required plugins**.
2. **SEO/GEO Manager** is a separate permanent WordPress plugin for analysis, controlled landing/blog publication, migration and ongoing operations. Manager must work on supported WordPress sites without requiring the Theme.

The existing **SEO/GEO Migration Bridge** is the accepted Phase 8 migration implementation and remains valid for the Theme 0.1.0 acceptance path. Its package is transitional; the accepted migration capabilities are planned to move into Manager as an optional module.

The repository may keep shared source packages separated for maintainability, but neither product distribution may expose that separation as a cross-product installation requirement.

## Repository shape

```text
template-wordpress-seo-geo/
├── packages/
│   ├── seo-geo-theme/
│   │   ├── inc/
│   │   │   └── seo-geo-core/
│   │   │       └── bootstrap.php
│   │   ├── parts/
│   │   ├── patterns/
│   │   ├── templates/
│   │   ├── functions.php
│   │   ├── style.css
│   │   └── theme.json
│   ├── seo-geo-core/
│   │   ├── src/
│   │   │   ├── Geo/
│   │   │   ├── Integrations/
│   │   │   ├── Language/
│   │   │   ├── Schema/
│   │   │   ├── Seo/
│   │   │   └── Runtime.php
│   │   └── seo-geo-core.php   # optional compatibility wrapper, not required
│   ├── seo-geo-migration-bridge/   # accepted Phase 8 migration implementation
│   └── seo-geo-manager/             # planned permanent plugin product
├── presets/
├── scripts/
│   └── build-theme-package.sh
├── tests/
├── docs/
└── .github/workflows/
```

## Distribution model

`packages/seo-geo-core/src` is the source of truth for reusable SEO/GEO services. The theme build copies that source tree into:

```text
seo-geo-theme/inc/seo-geo-core/src/
```

The committed theme bootstrap at `packages/seo-geo-theme/inc/seo-geo-core/bootstrap.php` loads the bundled classes and initializes the context-neutral `SeoGeo\Core\Runtime`.

In monorepo development, the same bootstrap may fall back to the sibling source package so the runtime is not duplicated in source control. The built distribution must always contain its own embedded source tree.

## `seo-geo-theme` ownership

The installable theme owns the complete baseline product:

- block templates and template parts;
- semantic design tokens and global styles through `theme.json`;
- reusable block patterns;
- accessible markup and responsive behavior;
- bootstrap of the embedded SEO/GEO runtime;
- native technical SEO output;
- future Schema/GEO/crawler/sitemap extensions that form part of the baseline product;
- preset presentation and configuration.

The theme must remain lightweight and WordPress-native. A feature is not allowed to introduce a third-party runtime dependency merely for convenience.

## `seo-geo-core/src` ownership

The reusable source library owns behavior that should remain modular even though it is bundled into the theme:

- canonical/meta/robots policy;
- indexability resolution;
- Open Graph/social metadata when implemented;
- Schema graph and stable entity IDs;
- language resolution abstractions;
- sitemap extensions where needed;
- AI crawler policy controls;
- optional machine-friendly alternate representations;
- author/entity metadata;
- local SEO entity configuration;
- compatibility detection for optional third-party systems.

This package is a **library source boundary**, not a mandatory WordPress plugin boundary.

## Migration Bridge ownership

The `seo-geo-migration-bridge` boundary owns existing-site adoption only.

Phase 8A implements its first read-only `SiteAnalyzer` plus an extensible builder detector interface. The analyzer reports environment/dependency signals without persisting a snapshot or scanning/converting page content. Builder-content coupling is intentionally deferred to Phase 8C.

The bridge owns:

- read-only site/theme/plugin/builder inventory by default;
- machine-readable provider/content-model/customization signals that avoid credentials and arbitrary option/content export;
- SEO/GEO baseline snapshots;
- dependency classification and migration-plan data;
- sandbox migration orchestration primitives;
- supported builder/content adapters;
- old-vs-new parity reports;
- explicit cutover/rollback assistance.

It must not become a second SEO/GEO output owner. It does not emit a competing canonical, robots policy, hreflang set or Schema graph merely because it is installed.

Production mutation is never inferred from analysis. Destructive actions require explicit administrator intent, WordPress capability checks, nonce/auth validation, a recoverable snapshot and a tested rollback path.

The current bridge **package** is transitional by product design. A completed migrated site must continue to satisfy the single-theme/zero-required-plugin Theme baseline even if the bridge/Manager is absent. The accepted migration capability itself is preserved and later absorbed into SEO/GEO Manager, where migration mode can be disabled while publishing/operations remain active.

## SEO/GEO Manager ownership

SEO/GEO Manager is a separate installable product, not a wrapper around the Theme and not a renamed Core compatibility plugin.

Manager owns control-plane behavior such as:

- read-only Site Intelligence and dependency analysis;
- per-signal SEO/GEO Output Authority resolution;
- versioned/idempotent content publication;
- Landing Engine and Blog Engine;
- optional migration/parity/cutover module;
- portable-sandbox coordination;
- provider/builder adapters;
- bounded audit/change-set/rollback records.

Manager must not become a second uncontrolled public-output owner. When the SEO/GEO Theme is active, Theme native runtime remains the default SEO/GEO output authority. When a supported external SEO provider owns a signal, Manager writes through that accepted adapter. Manager-native output is allowed only after an explicit conflict-free authority decision.

Manager frontend behavior must remain local to WordPress; normal page rendering cannot require a live remote service.

Detailed contracts live in `docs/PRODUCT_PORTFOLIO.md`, `docs/SEO_GEO_MANAGER.md`, `docs/CONTENT_PUBLISHING.md` and `docs/PORTABLE_SANDBOX.md`.

## Optional plugin wrapper

`packages/seo-geo-core/seo-geo-core.php` has the Phase 10E disposition **deprecated-retained-nondistributed**. It is deprecated for new installation paths, retained temporarily for compatibility/development workflows, excluded from the deterministic self-contained release ZIP and not required by distributable-theme acceptance. Removal is deferred until after the first real-site stable-release acceptance confirms no supported workflow still depends on it.

The context-neutral `SeoGeo\Core\Runtime` prevents duplicate initialization if both wrapper and theme are present during transitional development.

## WordPress baseline

The theme is a native block theme using `theme.json` version 3 and WordPress block templates. This keeps the product close to WordPress core and minimizes custom frontend code.

Use WordPress core before custom infrastructure when core already provides the required primitive, including:

- `wp_robots` for robots output;
- WordPress sitemaps as the default sitemap engine;
- responsive image handling;
- block templates/patterns;
- semantic HTML APIs;
- native rewrite and canonical primitives where appropriate.

Elementor, Divi and other builders may be detected and migrated through optional adoption adapters, but no builder is a baseline dependency. Unsupported builder modules are reported as blockers/manual-review items rather than silently discarded.

## Runtime service boundaries

### SEO
Resolves indexability, canonical, meta description, robots and later social metadata. Native output is the default because no SEO plugin is required.

### Schema
Builds a single coherent JSON-LD graph from page context and registered entities. Schema must correspond to visible/public content and use stable IDs.

### GEO
Owns retrieval-oriented enhancements such as provenance, machine-friendly alternates, semantic source/author structures and crawler discovery helpers. GEO cannot override factual SEO or indexability rules.

### Language
Provides a normalized language contract for the native ES/EN baseline. Optional adapters may extend it later, but WPML or Polylang are not prerequisites.

### Crawlers
Produces explicit policies for public search/discovery crawlers and keeps those controls separate from unrelated training-policy claims.

### Sitemaps
Uses WordPress core sitemaps by default and extends only where the product contract requires it. No parallel sitemap stack without a documented reason.

### Performance
Provides measurable optimizations that preserve the project's enforced budgets. Any behavior-changing optimization requires regression coverage.

### Integrations
Optional compatibility boundary only. Integrations may prevent conflicts or enhance projects that deliberately add third-party systems, but they cannot become part of the baseline boot path.

## Output authority rules

The baseline product is native-first:

- canonical: self-contained runtime;
- robots: self-contained runtime through WordPress `wp_robots`;
- meta description: self-contained runtime;
- Schema graph: self-contained runtime once implemented;
- sitemap: WordPress core plus native extensions where justified;
- hreflang: native language layer once implemented.

If a project deliberately installs a supported external provider later, exactly one owner may emit each overlapping signal. Compatibility is defensive behavior, not a product dependency.

## Stable entity IDs

The Schema graph will use deterministic URLs such as:

```text
https://example.com/#website
https://example.com/#organization
https://example.com/path/#webpage
https://example.com/author/name/#person
```

Translated content may have localized WebPage/Article nodes while Organization/Person identity is reused where semantically correct.

## Build and acceptance invariant

The most important packaging invariant is testable:

> A fresh WordPress installation with only the built `seo-geo-theme` active and zero active plugins must still provide the documented baseline SEO/GEO output.

`scripts/build-theme-package.sh` assembles that artifact. `Self-contained Theme CI` verifies the invariant on real WordPress rather than assuming that repository source layout equals installable product behavior.

For existing-site adoption, a second invariant applies: production remains on the accepted legacy stack until a sandbox candidate has passed dependency, SEO-parity, runtime and rollback acceptance. Migration tooling may assist the transition but may not weaken the final single-theme baseline.

For the Manager product, a third invariant applies: publishing must be draft-first by default, idempotent, authority-aware and rollback-capable. Theme + Manager and Manager + external-provider combinations must resolve exactly one owner for every overlapping SEO/GEO signal.

## Extensibility

Public modules should expose documented WordPress filters/actions rather than requiring edits to internal files. New presets compose existing runtime services and patterns instead of forking the core.
