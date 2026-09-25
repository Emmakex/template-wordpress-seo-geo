# SEO/GEO Migration Bridge

Accepted Phase 8 WordPress migration tooling for adopting existing client sites without treating production as disposable.

This **package** is transitional, but its migration capability is not being discarded. The roadmap moves the accepted analyzer/baseline/dependency/parity/cutover behavior into the permanent **SEO/GEO Manager** plugin as an optional migration module. Manager is a separate sellable product that also publishes landings/blogs and may remain installed after migration.

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

Required sandbox markers in `wp-config.php`:

```php
define( 'SEO_GEO_MIGRATION_SANDBOX', true );
define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );
define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );
```

The default `origin` mode preserves the v0.8.7 rule: the sandbox HTTP(S) origin must differ from the production baseline origin.

A hosting account may instead use a same-origin WordPress clone in an isolated subdirectory such as `https://example.com/nuevaweb/`. This requires two additional explicit markers:

```php
define( 'SEO_GEO_MIGRATION_SANDBOX_MODE', 'subdirectory' );
define( 'SEO_GEO_MIGRATION_STORAGE_ISOLATED', true );
```

Subdirectory mode is accepted only when the production and sandbox origins are the same, the sandbox base path is non-root and differs from the baseline path, and storage/runtime isolation is explicitly confirmed. `SEO_GEO_MIGRATION_STORAGE_ISOLATED=true` means the operator has verified that the clone uses its own WordPress database/table set and does not share mutable runtime storage with production. It is a confirmation marker; the Bridge does not create or infer that isolation.

All modes also require WordPress search-engine visibility disabled, outbound transactions safe, fresh recoverable backup references, the destination `seo-geo-theme` active, the persisted Phase 8B baseline available, a valid Phase 8C dependency graph and complete operator review for every raw `UNKNOWN` component.

When the marker is enabled the bridge adds defense-in-depth sandbox indexing guards:

- WordPress robots directives force `noindex`, `nofollow` and `noarchive`;
- HTTP responses include `X-Robots-Tag: noindex, nofollow, noarchive`.

The lab report exposes `migrate`, `manual-review`, `unchanged` and `blocked` states and always declares production cutover/mutation/indexing/canonical competition as disallowed. Raw `UNKNOWN` classifications remain unchanged in the graph; their explicit operator reviews are consumed only to derive the sandbox planning state.

A vendor-specific staging feature may create the clone, but it does not replace these provider-neutral acceptance checks.

## Phase 10E Portable Clone Engine

The real-site release pilot extends the accepted Phase 8 migration flow with a product-owned clone/export/import path so a supported client does not need a separate migration plugin.

Migration Bridge 0.8.9 introduced resumable local-clone/export/import job contracts. Version 0.8.10 adds the read-only source inventory used before any payload is created:

- WordPress-prefix database table metadata/row/byte estimates;
- resumable uploads/plugins/themes file hashing;
- default exclusions for caches/backups/temp/log paths;
- symlink and unreadable-entry reporting;
- deterministic source fingerprint;
- read-only local destination planning for path/URL/table-prefix/free-space isolation.

The inventory writes only its own bounded non-autoloaded progress state. It does not copy database rows, create clone payload archives, restore files, switch themes or mutate client content. Migration Bridge 0.8.11 begins 10E.2A.3 with a private resumable database-export stage: inventoried tables are exported as deterministic schema + binary-safe JSON chunks, every chunk is SHA-256 hashed, progress is resumable, and production database access stays read-only. Migration Bridge 0.8.12 adds the file-export stage: accepted uploads/plugins/themes are streamed into the same private workspace in bounded file/byte batches, each file is SHA-256 verified, and completion must reconcile file count, bytes and the full source fingerprint with the prior inventory. Migration Bridge 0.8.13 adds package manifest + integrity: the complete private workspace is hashed in bounded resumable batches, exported file/database records are revalidated, then a second independent pass must reproduce the same package checksum before `verified=true` is written. Migration Bridge 0.8.14 adds authenticated package delivery + retention: a private ZIP is assembled in bounded resumable batches while reproducing the verified package checksum, downloads exist only behind administrator capability + nonce, the ZIP gets its own SHA-256, ready packages expire after 24 hours, and workspace/archive cleanup is bounded and job-scoped. Portable Import remains the next phase. Migration Bridge 0.8.15 begins Portable Import with private ZIP intake and a read-only destination preflight: archive topology, package/child manifests, hashes, sandbox isolation, noindex, outbound safety, recovery evidence, explicit target authorization and disk capacity are checked before any restore. This phase deliberately keeps full payload verification and restore disabled until 10E.2A.4.2. Migration Bridge 0.8.16 adds bounded private extraction plus exact full-payload checksum replay. The restore gate opens only after checksum, payload file count and payload byte count match the accepted package and a fresh destination preflight still passes; no database or WordPress-content restore occurs yet.

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

## Phase 8F — SEO/GEO parity and regression engine

Phase 8F compares the persisted Phase 8B baseline against a candidate public-output snapshot before any production cutover is considered.

```php
$engine = \SeoGeo\MigrationBridge\Plugin::parity_engine();
$report = $engine?->compare( $baseline, $candidate, $allowlist_rules );
```

Comparison identity is origin-neutral: production and sandbox hosts are reduced to the same path/query identity. Canonicals, hreflang URLs, internal links, redirects and sitemap resources are normalized the same way so a staging hostname alone is not treated as a regression.

The engine checks URL presence/status/indexability, canonical/robots, title/meta, HTML language, hreflang, Open Graph, Schema type/count, H1 count, internal links, visible-content fingerprints, redirect maps and sitemap topology.

Intentional differences are approved only through exact rules bound to:

- resource path;
- signal name;
- baseline SHA-256;
- candidate SHA-256;
- a non-empty reason.

A stale rule cannot approve a later unrelated change.

Some regressions are deliberately non-allowable: duplicate canonical/robots/meta/title ownership, duplicate hreflang language keys, duplicate JSON-LD blocks and known broken internal links. These remain blocking even when a matching allowlist rule is supplied.

The parity report contains hashes and decision metadata, not raw before/after bodies. It always declares `production_cutover_allowed=false`; 8F is evidence, not deployment authority.

Accessibility and Performance gates include a representative post-migration page composed from the same conservative native-block families emitted by 8E.

## Phase 8G — Safe cutover and rollback

Phase 8G is the first bridge layer authorized to change the production presentation/runtime boundary.

The cutover engine is exposed through:

```php
$engine = \SeoGeo\MigrationBridge\Plugin::cutover_engine();
$plan = $engine?->plan(
    $backup_evidence,
    $plugins_to_deactivate,
    $parity_allowlist,
    $maintenance
);
```

A ready plan requires recent, hash-bound recovery evidence for both:

- a complete database backup;
- the uploads tree.

It also requires recent, passed evidence for the representative migration **Accessibility/Responsive** and **Performance** gates. Backup evidence alone never authorizes production cutover.

The bridge does not claim to create those environment-specific artifacts itself. It records their reference, SHA-256, timestamp, scope and size, together with the WordPress state required for deterministic rollback.

Before mutation, the bridge stores an append-only recovery record containing the active theme/plugins, installed package versions, protected options, baseline/redirect fingerprints and migrated-resource backup fingerprints.

Production cutover is blocked when the sandbox marker is still enabled, search-engine visibility is disabled, the persisted baseline is missing, the destination theme is unavailable, fresh 8F parity is not accepted, or another cutover is active.

Only explicit plugin basenames may be deactivated. A plugin is eligible only when its dependency classifications are entirely within `REPLACE`, `REMOVE-CANDIDATE` or `OPTIONAL`. Any `KEEP`, `UNKNOWN` or `MIGRATE` classification blocks deactivation. The Migration Bridge itself remains active until acceptance.

The engine never deletes plugins/themes, resets the database or clears uploads.

After cutover it verifies the exact expected theme/plugin state, protected options and fresh SEO/GEO parity. Failure triggers automatic runtime rollback. Manual rollback is also available while the cutover remains unaccepted and refuses to overwrite unrelated runtime drift.

Final acceptance performs fresh health + parity checks and changes the record to `accepted`. At that point runtime rollback is closed, while recovery evidence remains available for audit/disaster recovery.

## Phase 8H — Final migration report

Phase 8H provides a read-only final report plus an explicit non-autoloaded handoff store.

```php
$report = \SeoGeo\MigrationBridge\Plugin::migration_report()?->generate( $parity_allowlist );
$save = \SeoGeo\MigrationBridge\Plugin::migration_report_store()?->save( $report );
```

The report consolidates dependency counts/decisions, migration fingerprints, fresh URL/SEO/GEO parity, representative accessibility/performance comparisons, unresolved review items and cutover/rollback evidence.

Only reports with `ready_for_handoff=true` may be persisted to `seo_geo_migration_report_v1`. Existing reports are not overwritten unless explicitly requested.

The handoff report contains hashes/references and bounded metrics only. It does not contain post bodies, builder payloads, credentials or raw backup artifacts, and it is explicitly not a runtime dependency.

## Phase 8I — Operator UI

The temporary bridge now exposes a read-only operator screen under **Tools → SEO/GEO Migration**.

The screen:

- requires `manage_options`;
- ships English and Spanish copy together;
- summarizes baseline, dependency-plan, cutover and final-report status;
- shows blocking/advisory review counts plus bridge disposition;
- resolves the safest next step without executing migration/cutover actions;
- exposes explicit capability/nonce-gated planning actions for resumable baseline capture, UNKNOWN dependency review and sandbox-handoff download;
- stores UNKNOWN review decisions separately from the dependency graph, so an operator review cannot silently authorize production plugin deactivation or theme switching;
- renders/exports bounded metadata only and never exposes private bodies, builder payloads, credentials, arbitrary option values or raw backup artifacts.

`Plugin::operator_status()` exposes the same bounded read-only state for acceptance/diagnostics.

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

This bridge package is never required by the final self-contained Theme. It may be retired only after SEO/GEO Manager has absorbed its accepted contracts and regression acceptance proves parity. The future Manager plugin remains optional for the Theme and may stay active for ongoing publishing after migration mode is disabled.
