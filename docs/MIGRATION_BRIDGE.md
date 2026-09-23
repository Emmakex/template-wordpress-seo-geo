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

Status: **complete**

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

## Phase 8C — Builder and plugin dependency graph

Status: **implementation candidate**

Phase 8C converts the Phase 8A inventory and Phase 8B baseline into a read-only migration plan.

### Content coupling

The bridge scans WordPress resources for dependency signals from:

- native block markup;
- Elementor edit-mode/data metadata;
- Divi builder metadata and `et_pb_` shortcode usage;
- registered shortcode identifiers appearing in content.

The scan may inspect private/draft content in memory because migration planning must know whether those resources depend on a legacy builder. The report never exports private bodies, raw builder payloads or shortcode attributes.

Each coupled resource is represented by object ID, post type, status, public URL only when actually public, builder IDs/evidence and shortcode identifiers.

### Classification contract

Detected components use exactly these classifications:

- `KEEP`: destination-native or authoritative business/operational dependency;
- `REPLACE`: active SEO/GEO authority candidate whose signals must be migrated before removal;
- `MIGRATE`: content is directly coupled to a legacy builder;
- `OPTIONAL`: installed dependency not required by the destination baseline;
- `REMOVE-CANDIDATE`: inactive/redundant-looking dependency requiring explicit review;
- `UNKNOWN`: insufficient evidence or conflicting authority.

No classification grants removal authority. Every component has `auto_remove=false`.

### Provider authority candidates

SEO, Schema and multilingual providers are correlated with public signals observed in the Phase 8B baseline. The result distinguishes no active provider, one exclusive active provider candidate, multiple active-provider conflict, and inactive providers only.

This is deliberately conservative: an installed/active provider can be an authority candidate, but the bridge does not claim callback-level ownership without a provider-specific adapter/acceptance contract.

### Graph edges and safety

The machine-readable report includes resource-to-builder and resource-to-shortcode edges so later migration stages can identify exactly which resources block removal.

Phase 8C declares and tests:

- no WordPress mutation during graph generation;
- no plugin removal;
- no theme switching;
- no automatic removal authority;
- no raw content export;
- no builder payload export;
- reuse of the persisted Phase 8B baseline when available.

## Phase 8D — Sandbox Migration Lab

Status: **implementation candidate**

Phase 8D defines a provider-neutral acceptance contract for an isolated clone/staging environment. The repository does not assume that Hostinger, WP Engine, a VPS script or any other vendor owns the cloning workflow.

### Clone/staging workflow

Before migration work starts:

1. create an isolated copy of database and required WordPress files/uploads using the hosting/provider's supported mechanism;
2. place the sandbox on a non-production origin;
3. define `SEO_GEO_MIGRATION_SANDBOX=true` in sandbox `wp-config.php`;
4. disable WordPress search-engine visibility (`blog_public=0`);
5. ensure the temporary Migration Bridge is active in the sandbox;
6. install/activate the destination `seo-geo-theme` in sandbox only;
7. retain/import the accepted Phase 8B baseline envelope;
8. regenerate/read the Phase 8C dependency graph;
9. require the Sandbox Migration Lab report to be `ready=true` before Phase 8E transformations may exist.

Production does not receive the destination-theme switch merely because the sandbox is ready.

### Indexing isolation

An explicitly marked sandbox forces:

- `noindex`;
- `nofollow`;
- `noarchive`;
- `X-Robots-Tag: noindex, nofollow, noarchive`.

The lab additionally requires WordPress's own search-engine visibility setting to be disabled. This layered contract prevents the sandbox from becoming a competing public index/canonical destination even when a hosting platform's own staging protection is incomplete.

The guard is marker-gated: it does not change normal production output unless `SEO_GEO_MIGRATION_SANDBOX` is explicitly true.

### Sandbox readiness

The report is blocked when any of these are missing:

- explicit sandbox marker;
- disabled search-engine visibility;
- active destination `seo-geo-theme`;
- persisted Phase 8B baseline;
- valid Phase 8C dependency graph.

### Migration-state report

Phase 8D maps 8C classifications to lab states:

- KEEP → `unchanged`;
- MIGRATE / REPLACE → `migrate`;
- OPTIONAL / REMOVE-CANDIDATE / UNKNOWN → `manual-review`;
- unsupported future classifications → `blocked`.

The report is descriptive only. It does not transform content.

### Safety contract

The report always declares:

- `production_cutover_allowed=false`;
- `production_mutation_allowed=false`;
- `indexing_allowed=false`;
- `canonical_competition_allowed=false`;
- `baseline_is_reference_only=true`.

Phase 8E may only introduce explicitly authorized transformations after this sandbox contract is accepted.

## Phase 8A acceptance evidence

Phase 8A closed through PR #67.

Final PR candidate `494139b10d3c2cc57eafb4cf499017d49c7e5fdb` passed Foundation, Package, PHP Quality, WordPress Smoke and Self-contained Theme. The implementation was squash-merged as `337cd161560b5fce9f0e250d59197546d0eee462`, and all five gates passed again on `main`.

The key runtime proof is WordPress Smoke: the analyzer inspected a synthetic legacy installation containing active/inactive plugins, Elementor, Divi, WooCommerce, Yoast, a child theme, CPT/taxonomy/shortcode, menu and custom CSS, while the protected-state fingerprint remained unchanged.

The Phase 8A analyzer remains the non-destructive environment inventory. Phase 8B adds the public-output baseline described above without turning legacy output into the new authority.
