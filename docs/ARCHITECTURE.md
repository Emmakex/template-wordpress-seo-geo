# Architecture

## Repository shape

```text
template-wordpress-seo-geo/
├── packages/
│   ├── seo-geo-theme/
│   │   ├── assets/
│   │   ├── parts/
│   │   ├── patterns/
│   │   ├── styles/
│   │   ├── templates/
│   │   ├── functions.php
│   │   ├── style.css
│   │   └── theme.json
│   └── seo-geo-core/
│       ├── src/
│       │   ├── Crawlers/
│       │   ├── Geo/
│       │   ├── Integrations/
│       │   ├── Language/
│       │   ├── Markdown/
│       │   ├── Performance/
│       │   ├── Schema/
│       │   ├── Seo/
│       │   └── Sitemaps/
│       └── seo-geo-core.php
├── presets/
│   ├── corporate/
│   ├── ecommerce/
│   ├── local-business/
│   └── publisher/
├── scripts/
├── tests/
├── docs/
└── .github/workflows/
```

## Boundary: theme vs core plugin

### `seo-geo-theme`

Owns presentation only:

- block templates and template parts;
- design tokens and global styles through `theme.json`;
- block patterns;
- theme-specific CSS and minimal JS when unavoidable;
- visual variants and preset presentation hooks;
- semantic document structure and accessible markup.

It must not own durable SEO data or business logic that should survive a theme switch.

### `seo-geo-core`

Owns durable behavior:

- canonical/meta/robots policy;
- Open Graph/social metadata when native mode is active;
- Schema graph and stable entity IDs;
- multilingual resolution layer and plugin adapters;
- sitemap extensions where needed;
- AI crawler policy controls;
- optional `llms.txt` and Markdown alternate endpoints;
- author/entity metadata;
- local SEO entity configuration;
- compatibility detection for SEO plugins;
- performance-oriented hooks that are independent of theme styling.

## WordPress baseline

The initial theme will be a native block theme using `theme.json` version 3 and WordPress block templates. This keeps the foundation close to WordPress core and makes design tokens editable without requiring a third-party builder.

Elementor is explicitly **compatible but optional**. Projects that need it may add an Elementor preset/integration later; the core architecture must not depend on Elementor.

## Service boundaries inside the plugin

### SEO
Resolves title/meta/canonical/robots/social output in native mode and determines whether another SEO provider is authoritative.

### Schema
Builds a single coherent JSON-LD graph from page context and registered entities. Duplicate graphs from integrations must be suppressed or delegated rather than stacked blindly.

### GEO
Owns optional retrieval-oriented enhancements: content provenance metadata, machine-friendly alternates, source/author patterns and agent discovery helpers. It cannot override factual SEO rules.

### Language
Provides one normalized language contract independent of WPML/Polylang implementation details. Other modules ask Language for current locale, translated URL and alternates rather than calling vendor APIs directly.

### Crawlers
Produces policy helpers for public search/indexing crawlers, with explicit separation between discovery/search crawlers and training-oriented crawlers where providers expose that distinction.

### Markdown
Optional alternate representations for selected public content. Markdown output must preserve language and provenance, avoid creating an accidental duplicate-indexing surface and be independently disableable.

### Sitemaps
Uses WordPress core sitemaps by default and extends only where the product contract requires it. No parallel sitemap stack without a documented reason.

### Performance
Provides safe, measurable optimizations that cannot be expressed purely in the theme. Any optimization that changes WordPress behavior requires a regression test.

### Integrations
Adapter boundary for Yoast, Rank Math, AIOSEO, WPML, Polylang, WooCommerce and future supported systems.

## Provider authority rules

For any output with multiple possible owners, exactly one provider is authoritative at runtime. Examples:

- canonical: Core **or** external SEO plugin;
- Schema graph: Core **or** delegated provider for overlapping graph portions;
- hreflang: Language layer/integration, never duplicated by independent modules;
- sitemap: WordPress core/selected SEO provider, not multiple competing indexes.

Provider selection is resolved server-side from active plugins/configuration. Browser state never decides which SEO provider is authoritative.

## Stable entity IDs

The Schema graph will use deterministic URLs such as:

```text
https://example.com/#website
https://example.com/#organization
https://example.com/path/#webpage
https://example.com/author/name/#person
```

Translated content may have localized WebPage/Article nodes while Organization/Person identity is reused where semantically correct.

## Extensibility

Every public module should expose documented WordPress filters/actions rather than requiring theme edits. New presets should compose existing services and patterns, not fork the core.
