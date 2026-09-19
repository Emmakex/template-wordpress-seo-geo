# Architecture

## Product boundary

The installable product is a **single self-contained WordPress block theme**. Baseline SEO/GEO behavior must work on a clean WordPress installation with **zero required plugins**.

The repository may keep source packages separated for maintainability, but distribution must not expose that separation as an installation requirement.

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
│   └── seo-geo-core/
│       ├── src/
│       │   ├── Geo/
│       │   ├── Integrations/
│       │   ├── Language/
│       │   ├── Schema/
│       │   ├── Seo/
│       │   └── Runtime.php
│       └── seo-geo-core.php   # optional compatibility wrapper, not required
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
- preset presentation and configuration;
- theme-owned onboarding/admin orchestration over existing authoritative Core/theme options.

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

## Optional plugin wrapper

`packages/seo-geo-core/seo-geo-core.php` may remain temporarily as a compatibility/development wrapper while the architecture migrates. It is not part of the required installation path and must not be needed by acceptance tests for the distributable theme.

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

Elementor may be compatible later but is never a dependency.

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

## Onboarding authority

Theme onboarding is an orchestration layer, not a second configuration store.

It may present and mutate existing server-authoritative options only after capability/nonce validation. Preset, language, Schema identity and crawler/GEO settings keep their existing owners. Wizard progress or UI state must not become a shadow copy of those settings.

The onboarding screen remains theme-owned because it configures the installable product as a whole; reusable resolution and output behavior remains in `seo-geo-core/src`.

## Extensibility

Public modules should expose documented WordPress filters/actions rather than requiring edits to internal files. New presets compose existing runtime services and patterns instead of forking the core.
