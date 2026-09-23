# SEO/GEO Migration Bridge

Temporary WordPress migration tooling for adopting existing client sites without treating production as disposable.

## Phase 8A — Site Analyzer

The read-only analyzer inventories themes, plugins, builders, provider families, content-model registrations, menus/templates and non-content customization signals without exporting credentials, arbitrary option values, builder payloads or private content.

```php
$report = \SeoGeo\MigrationBridge\Plugin::analyzer()?->analyze();
```

Phase 8A remains strictly non-persistent and is guarded against WordPress mutation APIs.

## Phase 8B — SEO/GEO baseline snapshot

The baseline service captures anonymous, same-origin public output before migration:

```php
$snapshotter = \SeoGeo\MigrationBridge\Plugin::baseline_snapshotter();
$snapshot = $snapshotter?->capture();
$storage = null !== $snapshot ? $snapshotter?->persist( $snapshot ) : null;
```

The snapshot inventories WordPress public resources together with same-origin sitemap discoveries and records, where available:

- HTTP status, content type, redirect location and X-Robots-Tag;
- indexability state;
- title, meta description, canonical and robots;
- HTML language and hreflang;
- Open Graph properties;
- JSON-LD Schema types plus deterministic fingerprints;
- H1-H6 outline and breadcrumb signals;
- same-origin internal links;
- primary-content byte/word counts and SHA-256 fingerprint;
- robots.txt and sitemap fingerprints/locations;
- redirects observed while capturing inventoried URLs.

The bridge does **not** persist page bodies. Primary content and Schema payloads are represented by comparison-safe hashes/metadata where the later migration parity engine only needs to detect change.

### Persistence boundary

One baseline envelope is stored in the non-autoloaded WordPress option `seo_geo_migration_baseline_v1` only after an explicit `persist()` call.

An existing baseline is not overwritten unless the caller passes `true` as the replacement flag. This persistence belongs to the Migration Bridge itself; it does not alter client posts, terms, themes, plugins, permalinks or public SEO output.

The stored legacy baseline is an **acceptance reference only**. It is never treated as authority to reproduce invalid, duplicate or unsafe legacy output.

## Phase 8C — dependency graph

The dependency graph combines the Phase 8A environment inventory with the persisted Phase 8B public baseline:

```php
$analysis = \SeoGeo\MigrationBridge\Plugin::analyzer()?->analyze();
$graph = null !== $analysis
    ? \SeoGeo\MigrationBridge\Plugin::dependency_graph()?->build( $analysis )
    : null;
```

Phase 8C may inspect post bodies and known builder meta **inside WordPress** to detect coupling, but the report exports only resource IDs/status/public URLs, builder identifiers, shortcode identifiers and bounded evidence strings. It does not export raw post bodies, Elementor payloads, Divi bodies or shortcode attributes.

The graph classifies components conservatively as `KEEP`, `REPLACE`, `MIGRATE`, `OPTIONAL`, `REMOVE-CANDIDATE` or `UNKNOWN`. Every component has `auto_remove=false`; removal remains a later explicit migration decision.

It also records explicit resource-to-builder/shortcode edges and provider authority **candidates** for SEO, Schema and multilingual signals. Provider detection is never treated as proof of callback-level ownership.

## Safety boundary

The bridge follows these rules:

- analysis happens before mutation;
- public capture is anonymous and same-origin only;
- redirects are observed but not followed by the default HTTP transport;
- private/draft content is not inventoried;
- page bodies are not stored in the baseline;
- Phase 8B persistence is limited to the dedicated Migration Bridge option;
- no theme/plugin activation, deactivation, switching or content rewrite occurs;
- Phase 8C content scanning exports dependency metadata only, never raw content/builder payloads;
- Phase 8C never removes plugins, switches themes or rewrites content;
- production transformation/cutover remains a later explicitly authorized phase.

The plugin is temporary adoption tooling and is never required by the final self-contained theme.
