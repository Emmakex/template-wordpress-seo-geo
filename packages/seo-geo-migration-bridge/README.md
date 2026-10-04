# SEO/GEO Migration Bridge

Accepted Phase 8 WordPress migration tooling for adopting existing client sites without treating production as disposable.

## Portable Home Content Blueprint + Emmake reference content — 0.8.54

Step 5 now accepts a portable reviewed **Content Blueprint** in addition to manual Content Kit editing.

A blueprint:

- uses schema `corporate-home-content-blueprint` + model `corporate-home-v1`;
- contains only locale, semantic values and evidence-verification flags;
- carries no WordPress post IDs, source IDs, plan hashes, kit hashes or timestamps;
- rejects unknown semantic slots and verification groups;
- must match the active Corporate preset locale;
- must populate every required field before import;
- is normalized and SHA-256 fingerprinted before it is bound to the current clean Home draft;
- is then validated through the same Content Kit contract used by manual editing;
- produces a deterministic Content Kit identity when the same blueprint is imported again.

The repository includes `examples/content-blueprints/emmake-home.es_ES.json` as the first real-site reference. It reorganizes existing public Emmake positioning around marketing digital, investigación de mercado digital, análisis de datos and inteligencia de negocio, with all proof/case-study groups deliberately disabled until evidence is explicitly reviewed.

This portable shape is also the handoff contract intended for the future SEO/GEO Manager: content can be prepared/reviewed outside the page layout and imported without carrying environment identity.

## Corporate Home Content Kit + Native Hydrator — 0.8.53

After the clean Corporate Home draft exists, **Tools → SEO/GEO Reset & Rebuild** exposes Step 5.

The Content Kit:

- uses the Theme-owned `corporate-home-v1` semantic model;
- stores typed `text`, `link` and `list` values;
- binds the kit to the clean draft and its plan SHA-256;
- requires all mandatory non-evidence fields;
- requires complete content before an evidence group can be marked verified;
- keeps `hero-proof`, `proof` and `case-study` verification explicit;
- persists a deterministic kit SHA-256.

The Native Hydrator:

- parses the clean scaffold as native WordPress blocks;
- finds fields through stable `seo-geo-content-slot--*` classes rather than placeholder text or block position;
- removes the hero proof column, proof band and case-study section when their evidence group is not verified;
- removes the optional secondary hero CTA when no reviewed value exists;
- always hydrates from the original scaffold backup;
- blocks re-apply after an untracked manual edit with `hydrated-draft-drift`;
- is idempotent for the same reviewed kit;
- offers exact rollback to the original preset scaffold;
- does not alter the rescued source page, front-page assignment or active plugins.

This is the first content-writing surface that shares the same semantic contract intended for the future SEO/GEO Manager.

## Clean Corporate Home rebuild — 0.8.52

After Corporate bootstrap, **Tools → SEO/GEO Reset & Rebuild** exposes Step 4.

The clean Home builder:

- reads the Rescue Manifest only to identify the existing front page, URL and content fingerprint;
- requires the active `corporate` preset and completed 0.8.51 bootstrap;
- composes the Home from Theme-registered Corporate patterns;
- creates a private draft with the same page title as the rescued front page;
- does not copy the legacy layout;
- does not run mandatory Content Remap;
- does not change `page_on_front`;
- does not mutate the rescued source page, plugins or SEO configuration;
- reuses an equivalent draft on identical replay.

The draft starts as a **preset scaffold**. Content refinement/selection is the next page-rebuild step before any replacement of the current front page.

## Theme + Corporate preset bootstrap — 0.8.51

After Clone Reset completes, **Tools → SEO/GEO Reset & Rebuild** exposes Step 3.

The bootstrap:

- requires the completed clone-reset report;
- requires `seo-geo-theme` to be active;
- delegates all SEO/GEO setup writes to the Theme-owned `SetupExecutor`;
- applies the `corporate` preset;
- seeds one native language from the active WordPress locale with prefix routing disabled;
- configures Organization identity only after an explicit administrator confirmation that the WordPress site title represents the organization;
- keeps LocalBusiness inference disabled;
- keeps crawler policy inherited;
- keeps `llms.txt` and Markdown alternates disabled by default;
- creates no pages and mutates no plugins.

The Theme's migration-handoff reader also recognizes a verified completed Clone Reset as `reset-rebuild-handoff-v1`. This means stale legacy migration-report state cannot re-block the clean rebuild after its runtime dependencies were intentionally removed.

## Clone Reset Engine — 0.8.50

After the Rescue Manifest is saved, **Tools → SEO/GEO Reset & Rebuild** exposes Step 2: Clone Reset Engine.

Default behavior is intentionally minimal:

- activate `seo-geo-theme`;
- keep Migration Bridge during handoff;
- delete every other plugin unless explicitly checked as a business function to retain;
- delete every other installed theme;
- clear old widget assignments, rewrite rules and object cache;
- remove generated `wp-content/et-cache` and `uploads/elementor/css`;
- preserve pages/posts and verify their Rescue Manifest content hashes after reset;
- preserve the Rescue Manifest itself.

The reset is blocked unless the site is explicitly marked as a sandbox/clone, the Rescue Manifest exists and the SEO/GEO Theme is installed. It does not require baseline or UNKNOWN dependency completion.

## Reset & Rebuild mode — 0.8.49

For full redesigns, Migration Bridge now has a separate reset-first entrypoint under **Tools > SEO/GEO Reset & Rebuild**.

The first action is a **Rescue Manifest**, not another mandatory legacy dependency review.

It records only the assets a clean rebuild may need:

- pages/posts and URL identity;
- content/excerpt fingerprints;
- useful Yoast/Rank Math title/description/canonical/indexability metadata when present;
- crawlable links found in saved content;
- media references found in saved content;
- front/posts/privacy page identities.

The Rescue Manifest deliberately records:

- `legacy_theme_preserved=false`;
- `legacy_builder_preserved=false`;
- `legacy_plugins_preserved=false`;
- `dependency_review_required=false`;
- `unknown_components_blocking=false`.

Capture is read-only with respect to posts, themes and plugins; only the private non-autoloaded Rescue Manifest option is written.

The next reset-first microphase removes legacy presentation/runtime baggage while retaining the saved WordPress/search asset.

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

The inventory writes only its own bounded non-autoloaded progress state. It does not copy database rows, create clone payload archives, restore files, switch themes or mutate client content. Migration Bridge 0.8.11 begins 10E.2A.3 with a private resumable database-export stage: inventoried tables are exported as deterministic schema + binary-safe JSON chunks, every chunk is SHA-256 hashed, progress is resumable, and production database access stays read-only. Migration Bridge 0.8.12 adds the file-export stage: accepted uploads/plugins/themes are streamed into the same private workspace in bounded file/byte batches, each file is SHA-256 verified, and completion must reconcile file count, bytes and the full source fingerprint with the prior inventory. Migration Bridge 0.8.13 adds package manifest + integrity: the complete private workspace is hashed in bounded resumable batches, exported file/database records are revalidated, then a second independent pass must reproduce the same package checksum before `verified=true` is written. Migration Bridge 0.8.14 adds authenticated package delivery + retention: a private ZIP is assembled in bounded resumable batches while reproducing the verified package checksum, downloads exist only behind administrator capability + nonce, the ZIP gets its own SHA-256, ready packages expire after 24 hours, and workspace/archive cleanup is bounded and job-scoped. Portable Import remains the next phase. Migration Bridge 0.8.15 begins Portable Import with private ZIP intake and a read-only destination preflight: archive topology, package/child manifests, hashes, sandbox isolation, noindex, outbound safety, recovery evidence, explicit target authorization and disk capacity are checked before any restore. This phase deliberately keeps full payload verification and restore disabled until 10E.2A.4.2. Migration Bridge 0.8.16 implements that next boundary: the accepted ZIP is frozen, extracted only into a private job workspace in bounded resumable batches, and the complete extracted payload must reproduce the exact export checksum/file/byte contract. A fresh destination preflight is then required before restore becomes eligible, so late sandbox/noindex/outbound/recovery/authorization drift keeps restore locked without forcing a new extraction. The destination database and WordPress files still remain untouched until the later guarded restore phases. Migration Bridge 0.8.17 starts the database restore boundary conservatively: verified database schemas/chunks are restored only into deterministic job-owned InnoDB staging tables, every mutating batch repeats the fresh destination guard, and row writes plus the resumable cursor commit transactionally. Active destination WordPress tables are not changed in this phase; foreign-key/nontransactional schemas are blocked until a later compatible restore contract.

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


Migration Bridge 0.8.18 adds phase 10E.2A.4.4: verified file restoration into a private job-owned staging tree. It requires completed database staging, never writes active uploads/plugins/themes, validates every copied file against `files-meta`, and completes only after a second bounded SHA-256 pass exactly reconciles file and byte totals.


Migration Bridge 0.8.19 adds serialization-safe environment rewriting over verified job-owned staging tables. Supported PHP serialized values and JSON containers are decoded structurally, same-origin URLs are mapped to the destination, credential/token values remain opaque, and a second read-only pass must prove idempotence. Active destination tables and staged/active file bytes remain untouched; opaque serialized bytes containing source URLs block completion rather than being raw-replaced.


Migration Bridge 0.8.20 starts phase 10E.2A.4.6.1 with a read-only sandbox finalization preflight. After database/file staging and serialization-safe environment rewrite are complete, the Bridge re-hashes deterministic staging-table rows and manifest-backed staged files in bounded resumable batches, rechecks sandbox hardening, and derives an immutable activation/rollback plan hash. This version deliberately does not rename/swap active tables or wp-content roots: `activation_allowed` may become true only after reconciliation, while `handoff_ready` remains false until the later controlled promotion/verification step.

Migration Bridge 0.8.21 implements phase 10E.2A.4.6.2: reversible sandbox database activation. The accepted 0.8.20 activation plan is rebound through a fresh safety gate, import control-plane options are preserved into staging, `blog_public=0` is forced, Migration Bridge remains active, and all database tables are promoted through one atomic `RENAME TABLE` map with deterministic rollback names. The activation journal is stored outside `wp_options` in the private job workspace so a table swap cannot erase recovery state. Post-swap verification checks exact table counts, protected control-plane hashes, noindex and Bridge activation; any verification failure triggers an immediate reverse atomic rename. Active `wp-content` remains untouched and `handoff_ready` remains false.


Migration Bridge 0.8.22 implements phase 10E.2A.4.6.3: reversible file promotion plus final sandbox target verification. It freezes a workspace-backed promotion journal after the accepted 0.8.21 database activation, copies verified staged uploads/plugins/themes into deterministic same-filesystem sibling candidates in resumable batches, replays the accepted SHA-256 file fingerprint, and blocks promotion unless the currently active plugin/theme runtime exists in those candidates. Each root is then swapped through a deterministic rollback sibling, with crash-aware recovery for interrupted renames. A second bounded pass verifies the promoted active roots against staging and the accepted fingerprint. Any integrity or sandbox-safety failure reverses promoted roots; only complete post-promotion verification may set `handoff_ready=true`.

Migration Bridge 0.8.23 begins phase 10E.2A.5.1 with a read-only local-clone destination/ownership contract. A `local-clone` job may proceed only from a fully verified package, then freezes the absolute target path, target URL, isolated table prefix, capacity estimate and package/source fingerprints into a non-autoloaded state record. Same-origin subdirectories such as `/nuevaweb/` are accepted only when the destination differs from production and source payload roots; production paths/URLs, the live table prefix, insufficient capacity and non-empty unowned directories are blocked. A ready plan is immutable, capability/nonce/explicit-confirmation gated, and performs zero target mutations; the next microphase provisions the target bootstrap from this frozen contract.

Migration Bridge 0.8.24 starts phase 10E.2A.5.2.1 with a target ownership/recovery bootstrap. It revalidates the accepted 0.8.23 destination plan and verified package immediately before the first filesystem mutation, then claims only that exact empty target by creating a deterministic job-bound marker. The state journal records whether the directory was created by the job, keeps production filesystem/database state untouched, and supports safe release only while the ownership marker is still the sole target entry. Marker drift, symlinks, existence drift, non-empty targets or changed package identity block the bootstrap; no WordPress runtime files or database tables are copied in this microphase.

Migration Bridge 0.8.25 starts phase 10E.2A.5.2.2.1 with a resumable WordPress core runtime copy. It requires the verified 0.8.24 ownership marker on every batch, copies only the canonical WordPress core allowlist (`wp-admin`, `wp-includes` and standard root core files) through the centralized filesystem authority, and deliberately excludes `wp-content`, `wp-config.php`, arbitrary root directories and database writes. An independent second traversal must match the target directory topology and reproduce exact file count, byte count and deterministic SHA-256 fingerprint before `runtime_core_ready=true`; source-version/package/ownership drift or target tamper blocks completion.

Migration Bridge 0.8.26 starts phase 10E.2A.5.2.2.2 with the isolated sandbox control runtime. After the 0.8.25 core is verified, it copies and independently verifies only the Migration Bridge plugin, then atomically generates a target `wp-config.php` using the accepted same-database/different-table-prefix contract and an always-on MU-plugin. The MU-plugin forces `blog_public=0`, noindex/noarchive headers, blocks `wp_mail`, blocks HTTP outside the sandbox subdirectory and loads Migration Bridge before normal plugins. Configuration state stores only hashes/readiness flags; DB credentials/salts are written directly to the protected target config and never persisted in Bridge state or logs. Client uploads/themes and destination tables remain absent until package handoff.

Migration Bridge 0.8.27 begins phase 10E.2A.5.3.1 with a private same-server package handoff. It reuses the existing `PackageDelivery` ZIP engine for `local-clone` jobs without rewriting the already frozen package manifest or changing its manifest hash, keeps the local-clone job active, and binds the ready archive SHA-256/bytes to current ownership, sandbox-control-file and package identities. No public URL or authenticated download workflow is introduced for this path, and no destination tables/uploads/themes are restored yet. A changed archive, ownership marker, sandbox control file or package hash invalidates the handoff; target intake/preflight remains the next microphase.

Migration Bridge 0.8.28 implements phase 10E.2A.5.3.2.1 with private target intake + sandbox preflight. The ready local handoff is copied only into private import workspace storage and validated by the existing `ImportPreflight` engine through a deterministic child `import` job. The child destination context is derived from the verified 0.8.26 runtime authority—target path/URL, isolated table prefix, noindex, outbound blocking and backup authorization—not from operator-supplied free-form parameters. Local-clone packages keep their frozen transport contract (`delivery_ready=false`, no public delivery metadata), while normal Portable Import behavior remains unchanged. Completion requires package/child-manifest integrity, archive identity, same-origin subdirectory isolation, zero destination tables/uploads/themes and `restore_allowed=false`; full payload checksum extraction is still the next guarded step.

Migration Bridge 0.8.29 implements phase 10E.2A.5.3.2.2 with bounded private payload extraction and exact checksum replay. It delegates every batch to the accepted `ImportPayloadVerifier` on the deterministic child `import` job, while the local-clone parent revalidates frozen target/package/archive authority before and after progress. Extraction remains inside private job storage. Only after the replayed checksum equals the frozen package checksum and a fresh destination preflight still passes may the child expose `restore_allowed=true`; `/nuevaweb/` tables, uploads and themes remain untouched. If parent authority drifts, the local wrapper marks the parent blocked and revokes the child restore gate. The next phase is transactional database staging restore, reusing the accepted Portable Import restore primitives.

Migration Bridge 0.8.30 implements phase 10E.2A.5.4.1 with transactional local database staging. It reuses the accepted `ImportDatabaseRestorer` against the verified child import job and adds a strict `private-same-server` destination-prefix adapter so the control-plane WordPress may restore toward the isolated `/nuevaweb/` prefix without changing production `$wpdb->prefix`. Local staging tables use a deterministic namespace that cannot begin with either the production prefix or the future target prefix, so active production tables and future `/nuevaweb/` tables remain untouched while rows are restored and verified. Every mutating batch revalidates the parent handoff/payload authority; drift blocks the parent and revokes child restore eligibility. The next phase is private file staging restore, again reusing the accepted Portable Import primitives.

Migration Bridge 0.8.31 implements phase 10E.2A.5.4.2 with private local file staging. It reuses the accepted `ImportFileRestorer` on the verified child import job, copies uploads/plugins/themes only into the child private workspace, and preserves the existing per-file `files-meta` integrity contract plus independent verification pass. The local parent requires completed database staging, revalidates frozen handoff/payload authority around each batch, and proves that `/nuevaweb/` still contains no promoted uploads/themes or client plugin payload; the previously installed Migration Bridge control plugin is the only allowed target plugin entry. Completion exposes only private staging readiness. Next: serialization-safe environment rewrite before any database activation or file promotion.

Migration Bridge 0.8.32 implements phase 10E.2A.5.5.1 with serialization-safe local environment rewrite. It reuses the accepted `ImportEnvironmentRewriter` on the verified child job only after local database and file staging remain valid, binds each batch to the frozen handoff/payload/database/files authority, and rewrites source-environment URLs exclusively inside job-owned staging tables. PHP serialized values and JSON containers are decoded without object instantiation, transformed recursively and re-encoded, while credential-like keys remain intentionally opaque. A second verification pass must find zero remaining rewritable source URLs. Production `home`/`siteurl`, active tables and target client roots remain untouched; no target table activation or file promotion occurs. Next: guarded finalization planning.

Migration Bridge 0.8.33 implements phase 10E.2A.5.5.2 with guarded local finalization planning. It reuses the accepted `ImportFinalizationPlanner` on the bound private same-server child, extends its safety gate to revalidate `LocalCloneTargetPreflight`, and fingerprints the rewritten database staging plus private uploads/plugins/themes staging in bounded batches. The resulting activation/rollback plan points only at the isolated `/nuevaweb/` table prefix and future `wp-content` roots, freezes deterministic rollback names and same-filesystem candidate paths, and is accepted by the parent only while all local authority and staging hashes remain valid. This phase remains read-only for target tables and client file roots: activation candidates/rollback paths are planned but not created. Next: reversible local database activation.

Migration Bridge 0.8.34 implements phase 10E.2A.5.5.3 with reversible local database activation. It reuses the accepted atomic `ImportDatabaseActivator` while adding a strict `private-same-server` authority path: the frozen child activation plan is rebound to the isolated target prefix, a recovery journal is prepared without mutation, and activation uses one atomic multi-table `RENAME TABLE`. Before the swap, the staged target `options` table receives only a bounded safety overlay: destination `home`/`siteurl` must already match, `blog_public=0`, and the Migration Bridge control plugin remains active while client plugins stay disabled until file promotion. Production WordPress options and client files remain untouched. The activated target is verified by exact row counts/control hashes and may be atomically rolled back to staging until file promotion begins. Next: reversible local file promotion.

Migration Bridge 0.8.35 implements phase 10E.2A.5.5.4 with reversible local file promotion. It reuses the accepted `ImportFilePromotionPlanner` and `ImportFilePromoter` while binding their roots and runtime operations to the verified private same-server target. Candidate uploads/plugins/themes are copied and fingerprinted outside active target roots, then swapped only inside `/nuevaweb/wp-content`; source WordPress roots and options are never mutated. The isolated target runtime is read/written directly through its activated `wp_options`, keeping `blog_public=0`, activating the source plugin/theme runtime plus Migration Bridge only after candidate integrity passes, and retaining rollback siblings after final verification. Explicit file rollback restores the control-runtime layout and re-enables database rollback. Completion marks the local clone handoff ready for final target smoke/reporting.

Migration Bridge 0.8.36 implements phase 10E.2A.5.5.5 with final local target smoke and handoff reporting. `LocalCloneHandoffReporter` is read-only for the isolated target: it requires verified file promotion, rechecks every activated target table/row count, destination `home`/`siteurl`, `blog_public=0`, exact plugin/theme runtime, Migration Bridge control files, active file fingerprint, source isolation and rollback availability. The bounded report stores no content payload and produces a deterministic SHA-256 over target identity and acceptance evidence; `verified_snapshot()` recomputes the evidence so any later target drift or rollback invalidates the handoff. Sandbox hardening and rollback are deliberately preserved. Next: 10E.2A.6 real `emmake.com/nuevaweb/` clone acceptance.


Migration Bridge 0.8.37 improves the real-site file-export operator flow discovered during the Emmake pilot. The resumable exporter now exposes two explicit choices: **one-click automatic** mode, which keeps issuing safe bounded 100-file / 16 MB requests until completion, and **manual batch** mode, which preserves the existing operator-selected file/byte limits. Automatic mode remains resumable, keeps the same integrity contract, stops after a bounded safety-cycle limit, and never turns the 20k+ file export into one unbounded PHP request.


Migration Bridge 0.8.38 hardens real-site file export after the Emmake pilot showed both automatic and manual modes could appear stuck on the same durable count. The exporter now checkpoints traversal during the request, persists the first copied file and every small copy interval, uses an explicit active-directory cursor, applies a conservative per-request time budget, and records bounded operator diagnostics. The Tools screen adds file/byte progress bars plus a recent-event log with root-relative paths, last action, last file size, request duration and checkpoint count. Version 1 file-export state remains resumable through the version 2 state normalizer.


Migration Bridge 0.8.39 fixes the browser-side continuation of one-click automatic file export. WordPress `submit_button()` uses `name="submit"` by default, which shadows the native form `submit()` method in DOM form collections; the previous automatic chain therefore completed the first request but could fail before the second. The automatic control now uses a non-conflicting button name plus `requestSubmit()` with a native prototype fallback, while manual batch behavior remains unchanged.


Migration Bridge 0.8.41 completes the live-source drift recovery path introduced in 0.8.40. Package integrity now accepts a file export whose aggregate inventory fingerprint differs only when the exporter has explicitly recorded a verified drift snapshot with identical file/byte totals. In that case the package binds to the exported snapshot fingerprint, retains the original inventory fingerprint as provenance in the package manifest, and continues through the same two-pass workspace integrity verification. Unrecorded fingerprint mismatch or structural count/byte drift remains blocked.


Migration Bridge 0.8.42 adds explicit operator access to retained Portable Clone jobs after real-site testing showed that a newer job can visually hide an older advanced export. The Tools screen lists saved jobs and allows the operator to open a specific existing job by ID without creating, resetting or mutating it. It is layered on top of the accepted 0.8.41 package-integrity drift-snapshot path, so both the completed Emmake file snapshot and later package-integrity recovery remain preserved.


Migration Bridge 0.8.43 fixes recovery visibility for legacy real-site jobs whose terminal `last_action` is `source-files-changed-since-inventory` but whose normalized `blockers` list is empty or contains additional historical entries. A blocked export is now recoverable when file and byte totals are both non-zero and exactly complete, and the drift reason is present either as the last action or among blocker codes. The engine applies the same predicate, so the displayed recovery button and server-side authorization cannot disagree.


Migration Bridge 0.8.44 fixes the remaining real-site finalization edge case: WordPress displays file bytes with rounded units, so a live site can show 608 MB / 608 MB while the exact exported and inventory byte totals differ. Recovery no longer requires byte-for-byte equality with the old inventory. It requires the terminal source-drift reason, exact completed file count, and completed traversal of all payload roots. The exported snapshot keeps its actual byte count, records inventory byte mismatch as source drift, and package integrity then re-hashes and validates the private workspace against each per-file export record before the clone can continue.


Migration Bridge 0.8.45 fixes a real package-integrity false positive discovered on the Emmake snapshot. Exported WordPress/plugin/theme payloads can legitimately be named `index.php` or `.htaccess`; package verification previously treated every file with either basename as a generated workspace guard before consulting its per-file export record, so the first real payload with one of those names was blocked as `package-exported-payload-mismatch`. Package integrity now validates `files/...` payload records first and only falls back to the deterministic guard contract when no exported payload record exists. The same retained package job can retry from its saved cursor after upgrading; an actual hash/byte mismatch immediately blocks again.


Migration Bridge 0.8.46 fixes the next Emmake real-clone boundary exposed after package integrity completed successfully. The local-clone destination plan, ownership bootstrap and WordPress core runtime still required the package source fingerprint to equal the original inventory fingerprint exactly, which rejected the same live-source drift snapshot that 0.8.44/0.8.45 had already verified and packaged. These stages now accept either exact inventory identity or the recorded completed drift snapshot only when its export fingerprint, files-manifest SHA-256, exact file count and completed root traversal still match the verified package/inventory evidence. No package rebuild or file re-export is required for the retained Emmake job.


Migration Bridge 0.8.47 improves the private same-server handoff for large real sites. Earlier versions advanced the underlying resumable ZIP builder but the local-clone UI exposed only the final archive size, so repeated 100-file batches appeared stuck at 0 B and could require hundreds of clicks. The handoff UI now reads the persisted delivery checkpoint, shows verified files/bytes and the current cursor, and offers bounded automatic continuation at 500 files / 32 MB per request. Automatic mode stops on zero progress or after a bounded cycle limit, while manual batches remain available. Existing building handoffs resume from the same saved cursor.


Migration Bridge 0.8.48 adds the reviewed Native Replatform workflow required after the real clone has already been created. Inside an accepted sandbox, the Bridge can compose preset-native replacement pages as private drafts, extract provenance-bound source text/link/media candidates, require explicit verification for evidence-sensitive sections, apply only reviewed candidate IDs to deterministic native slots, reject source/plan/slot drift, restore the hash-verified pre-apply draft for another review cycle and re-apply cleanly. The Tools surface also exposes a private draft Preview and an administrator-only review-evidence JSON containing only hashes, IDs, counts, verification decisions and drift state. Public source pages are never mutated or published by this workflow, and existing 0.8.47 clone/handoff jobs remain resumable without restart.
