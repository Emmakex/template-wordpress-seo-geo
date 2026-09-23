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

## Phase 8D — Sandbox Migration Lab

The sandbox layer is provider-neutral. A hosting control panel may create the clone, but the Migration Bridge only considers it a valid migration lab when all required conditions are independently verifiable.

Required sandbox marker in `wp-config.php`:

```php
define( 'SEO_GEO_MIGRATION_SANDBOX', true );
```

The lab also requires WordPress search-engine visibility to be disabled, the destination `seo-geo-theme` to be active, the persisted Phase 8B baseline to exist and the Phase 8C dependency graph to be available.

When the marker is enabled the bridge adds defense-in-depth sandbox indexing guards:

- WordPress robots directives force `noindex`, `nofollow` and `noarchive`;
- HTTP responses include `X-Robots-Tag: noindex, nofollow, noarchive`.

The lab report exposes `migrate`, `manual-review`, `unchanged` and `blocked` states and always declares production cutover/mutation/indexing/canonical competition as disallowed.

A vendor-specific staging feature may create the clone, but it does not replace these provider-neutral acceptance checks.

## Phase 8E — Migration Engine

Phase 8E is the first bridge phase allowed to mutate WordPress content, and only inside an accepted Phase 8D sandbox.

The engine exposes a non-mutating plan first:

```php
$engine = \SeoGeo\MigrationBridge\Plugin::migration_engine();
$plan = $engine?->plan( $post_id, 'elementor', 'corporate' );
```

Execution requires all of the following:

- explicit sandbox readiness from Phase 8D;
- an administrator with `manage_options` **and** edit permission for the exact resource;
- a nonce scoped to the exact resource + adapter;
- an explicit confirmation flag;
- a supported builder adapter plan with zero blockers;
- a migration backup created before the content write.

The administrator entrypoint is registered as authenticated `admin_post_seo_geo_migration_execute`; it repeats capability, nonce and explicit-confirmation checks before calling the engine.

### Supported adapter boundary

Phase 8E ships conservative adapters for:

- Elementor: heading, text editor, image, button, divider and spacer;
- Divi: text, button, image, divider and spacer inside section/row/column structure.

Unknown Elementor widgets and Divi modules become blockers. They are never silently removed.

The adapters target native WordPress core blocks. Builder-specific visual styling/layout settings are not claimed to reproduce pixel-for-pixel; those differences remain visible to sandbox acceptance and later parity/manual review.

### Preservation

The engine changes the existing WordPress resource in place and verifies after mutation that:

- object ID did not change;
- slug did not change;
- permalink path did not change;
- referenced media IDs remain the same where the adapter can preserve them;
- plugins and active theme are not modified;
- business systems classified as `KEEP` remain outside the mutation scope.

Before the content write, the engine stores a private rollback source in `_seo_geo_migration_backup_v1`. The public migration result exposes only hashes/status/preservation metadata, not the backup body.

### Preset boundary

An explicitly requested preset must be one of the five bundled presets and must not conflict with the active destination-theme preset. The Migration Engine does not silently switch presets or force content into a different information architecture.

## Safety boundary

The bridge follows these rules:

- analysis happens before mutation;
- public capture is anonymous and same-origin only;
- redirects are observed but not followed by the default HTTP transport;
- private/draft content is not inventoried;
- page bodies are not stored in the baseline;
- Phase 8B persistence is limited to the dedicated Migration Bridge option;
- no theme/plugin activation, deactivation or switching occurs;
- content rewrite is permitted only in Phase 8E's authorized `MigrationEngine.php` sandbox boundary;
- Phase 8C content scanning exports dependency metadata only, never raw content/builder payloads;
- Phase 8C never removes plugins, switches themes or rewrites content;
- Phase 8E mutations require capability + edit permission + nonce + explicit confirmation + backup;
- unsupported builder structures remain blockers;
- production transformation/cutover remains a later explicitly authorized phase.

The plugin is temporary adoption tooling and is never required by the final self-contained theme.
