# SEO/GEO Migration Bridge

## Purpose

The Migration Bridge is a temporary WordPress plugin/tool for adopting existing client sites before the self-contained SEO/GEO theme becomes the active production presentation layer.

It exists because a real client site may already depend on:

- a legacy or custom theme;
- child-theme code;
- Elementor, Divi or native blocks;
- SEO/Schema/multilingual plugins;
- WooCommerce or other business systems;
- forms, analytics, redirects, caching and security plugins;
- custom post types, taxonomies, shortcodes, widgets and menus;
- custom CSS and project-owned PHP customizations.

The bridge is **not** part of the final zero-plugin theme baseline.

## Phase 8A — Site Analyzer

Phase 8A introduces a read-only analyzer available through:

```php
$report = \SeoGeo\MigrationBridge\Plugin::analyzer()?->analyze();
```

The returned value is a machine-readable PHP array and may be serialized with `wp_json_encode()`.

### Report sections

The Phase 8A report contains:

- site runtime metadata: WordPress/PHP version, locale and multisite flag;
- installed theme inventory with active/inactive state and child-theme relationship;
- installed plugin inventory with active/inactive/network/must-use state;
- page-builder detection for native blocks, Elementor and Divi;
- known provider families for business systems, SEO, Schema, multilingual, redirects, analytics, forms, cache and security;
- registered post types and taxonomies;
- registered shortcode and widget identifiers;
- menu inventory;
- active-theme block/classic template signals;
- custom CSS presence/size/hash;
- active-theme `functions.php` presence/size/hash.

### Deliberate privacy/safety limits

The analyzer does not export:

- credentials;
- arbitrary WordPress option values;
- post bodies;
- builder JSON/content payloads;
- private content;
- custom CSS source;
- PHP source.

Hashes and byte counts are used where the migration process only needs to know whether a customization exists and whether it changes later.

## Builder detector contract

Builder detection is extensible through:

```php
SeoGeo\MigrationBridge\Builders\BuilderDetectorInterface
```

Phase 8A ships:

- `NativeBlocksDetector`;
- `ElementorDetector`;
- `DiviDetector`.

These detectors identify installed/active environment signals only. They do **not** scan or convert page content in 8A.

Content-level builder dependency mapping belongs to Phase 8C.

## Non-destructive invariant

Phase 8A must not perform migration mutations.

Project CI statically blocks common mutation APIs inside the Migration Bridge package, including option writes, post/term writes, plugin activation/deactivation, theme switching and scheduling.

The WordPress runtime acceptance also fingerprints protected site state before and after analysis and fails if the analyzer changes that state.

Fixture setup itself may create synthetic plugins, themes, content-model registrations, menus or CSS so the analyzer has something realistic to inspect. Those fixture mutations occur **before** the analyzer call and are not analyzer behavior.

## Provider detection is informational

Detection of WooCommerce, Yoast, Rank Math, AIOSEO, WPML, Polylang, caching/security plugins, Elementor, Divi or other providers does not imply a supported integration.

A provider becomes supported only after its specific ownership/adapter contract is implemented and accepted.

## Phase boundaries

- **8A**: environment/site analyzer, read-only and non-persistent.
- **8B**: SEO/GEO public-output baseline snapshot and persistence.
- **8C**: dependency graph and content-level builder/plugin coupling.
- **8D+**: sandbox migration, transformation, parity and cutover.

The analyzer therefore does not crawl public URLs, store snapshots or propose destructive actions in 8A.
