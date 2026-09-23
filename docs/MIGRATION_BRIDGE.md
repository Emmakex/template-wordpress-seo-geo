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

Status: **complete**

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


## Phase 8B — SEO/GEO baseline snapshot

Status: **implementation candidate**

Phase 8B adds a comparison-oriented public-output snapshot before any migration transformation is allowed.

### Capture contract

The baseline service is exposed through:

```php
$snapshotter = \SeoGeo\MigrationBridge\Plugin::baseline_snapshotter();
$snapshot = $snapshotter?->capture();
```

Capture is anonymous and constrained to the configured WordPress home origin. The default HTTP transport does not follow redirects and does not send WordPress authentication cookies.

The URL inventory combines:

- the home page;
- published publicly queryable posts/pages/custom post types;
- public post-type archives;
- non-empty public taxonomy terms;
- author archives that currently have public posts;
- same-origin public URLs discovered from robots.txt and sitemap indexes;
- optional explicit same-origin seed URLs.

The default capture ceiling is 500 URLs and the report declares when discovery was truncated. A caller may raise the limit up to the hard ceiling of 5,000 URLs.

### Page signals

For each inventoried URL the snapshot records, where available:

- HTTP status, response content type, redirect location and X-Robots-Tag;
- derived indexability state;
- title, meta description, canonical and robots;
- HTML language and hreflang;
- Open Graph properties;
- JSON-LD Schema block count, types and deterministic SHA-256 fingerprints;
- H1-H6 outline and H1 count;
- visible breadcrumb-container signals;
- same-origin internal links;
- primary-content byte count, word count and SHA-256 fingerprint;
- source/content-type classification from WordPress or sitemap discovery;
- request error code when the anonymous request failed.

Public body content is **not** stored in the baseline.

### Sitemap and redirect inventory

The snapshot reads same-origin sitemap declarations from `robots.txt` and probes the conventional WordPress/generic sitemap index endpoints. Same-origin sitemap indexes are followed to a bounded depth, and each sitemap is represented by status, location count and body fingerprint.

Redirect coverage in 8B is intentionally labeled `observed-during-baseline`: redirects are recorded only when an inventoried URL returns a 3xx response. Provider-specific redirect databases/rules are not silently read or treated as authoritative in this microphase; those dependencies remain part of 8C classification/adapters.

### Persistence contract

Persistence is explicit:

```php
$storage = $snapshotter?->persist( $snapshot );
```

The bridge stores one envelope in the non-autoloaded option `seo_geo_migration_baseline_v1`. Existing baseline data is not overwritten unless the caller explicitly enables replacement.

Persistence is limited to Migration Bridge state. It must not alter posts, terms, active plugins, active theme, permalink structure or public SEO output.

The persisted legacy snapshot is an acceptance reference for later parity checks, not an instruction to reproduce duplicate, invalid or unsafe legacy markup.

### Privacy and authority

The Phase 8B snapshot declares:

- `same_origin_only=true`;
- `authenticated_requests=false`;
- `private_content_collected=false`;
- `body_content_persisted=false`;
- `legacy_output_is_authority=false`.

## Phase 8A acceptance evidence

Phase 8A closed through PR #67.

Final PR candidate `494139b10d3c2cc57eafb4cf499017d49c7e5fdb` passed Foundation, Package, PHP Quality, WordPress Smoke and Self-contained Theme. The implementation was squash-merged as `337cd161560b5fce9f0e250d59197546d0eee462`, and all five gates passed again on `main`.

The key runtime proof is WordPress Smoke: the analyzer inspected a synthetic legacy installation containing active/inactive plugins, Elementor, Divi, WooCommerce, Yoast, a child theme, CPT/taxonomy/shortcode, menu and custom CSS, while the protected-state fingerprint remained unchanged.

The Phase 8A analyzer remains the non-destructive environment inventory. Phase 8B adds the public-output baseline described above without turning legacy output into the new authority.
