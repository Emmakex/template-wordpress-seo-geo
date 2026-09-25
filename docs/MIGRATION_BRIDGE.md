# SEO/GEO Migration Bridge

## Purpose

The current Migration Bridge package is the accepted Phase 8 WordPress migration implementation for adopting existing client sites before the self-contained SEO/GEO Theme becomes the active production presentation layer.

The **package is transitional; the capability is not**. The long-term product home for analyzer, parity, migration, sandbox coordination and cutover/rollback is the permanent **SEO/GEO Manager** plugin. Manager also owns ongoing landing/blog publishing, so it may remain installed after migration while its migration mode is disabled.

It exists because a real client site may already depend on:

- a legacy or custom theme;
- child-theme code;
- Elementor, Divi or native blocks;
- SEO/Schema/multilingual plugins;
- WooCommerce or other business systems;
- forms, analytics, redirects, caching and security plugins;
- custom post types, taxonomies, shortcodes, widgets and menus;
- custom CSS and project-owned PHP customizations.

The bridge is **not** part of the final zero-plugin Theme baseline. Future Manager is also optional from the Theme perspective: Theme must keep working with zero required plugins.

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

Status: **complete**

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

Status: **complete**

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

### Real sandbox preflight hardening

The real-site pilot extends the accepted Phase 8D contract with explicit runtime evidence before any migration action is considered ready:

- the current sandbox origin must differ from the production origin stored in the persisted baseline;
- `SEO_GEO_MIGRATION_SANDBOX=true`;
- `SEO_GEO_MIGRATION_OUTBOUND_SAFE=true`;
- `SEO_GEO_MIGRATION_BACKUPS_READY=true`;
- WordPress search visibility disabled;
- destination Theme active;
- baseline and dependency graph available;
- all raw `UNKNOWN` dependencies explicitly reviewed.

The outbound/backups markers are operator/environment confirmations, not claims that the plugin created backups or reconfigured mail/payment providers. Reviewed `UNKNOWN` decisions remain planning metadata; the raw graph classification is preserved while the sandbox state maps reviewed `KEEP` to `unchanged`, reviewed `MIGRATE`/`REPLACE` to `migrate`, and reviewed `OPTIONAL`/`REMOVE-CANDIDATE` to `manual-review`.

## Portable Clone Engine extension for Phase 10E

The real-site pilot identified a product dependency that the accepted Phase 8 sandbox contract intentionally left outside the Bridge: a hosting feature or third-party tool still had to create the actual clone.

That dependency is now removed from the target product path.

Migration Bridge gains a Portable Clone Engine governed by `docs/PORTABLE_CLONE_ENGINE.md`. It provides three transport/provisioning operations:

- local clone on the same server into an isolated destination;
- portable export;
- portable import.

The Engine is built from resumable shared primitives so local clone is orchestration of export/import behavior rather than a second copy implementation.

The clone subsystem remains below the sandbox/migration authorization boundary:

- source inventory/export is read-only on production;
- import requires a validated isolated destination;
- environment rewrite is serialization-safe and separately reported;
- sandbox hardening happens after restore;
- Phase 8D readiness remains the gate before Phase 8E can mutate client content;
- a clone package is private migration material and never valid repository evidence.

The Engine has no generic stale sandbox-database restore-to-production operation. Production remains the dynamic-data authority at cutover.

## Phase 8E — Migration Engine

Status: **implementation candidate**

Phase 8E is the first phase allowed to transform client content. The authorization boundary is intentionally narrow: project CI permits WordPress post/meta mutation APIs only inside `src/Migration/MigrationEngine.php`.

### Plan before mutation

```php
$engine = \SeoGeo\MigrationBridge\Plugin::migration_engine();
$plan = $engine?->plan( $post_id, 'elementor', 'corporate' );
```

The plan contains no raw post body or builder payload. It reports adapter support, blockers, warnings, preserved resource identity/media, selected preset, authoritative business systems and the authorization requirements.

A plan cannot become ready when:

- Phase 8D sandbox readiness is false;
- the resource does not exist;
- the adapter is unsupported;
- the destination preset is unsupported/conflicts with the active preset;
- the adapter reports an unsupported module/widget;
- a migration backup already exists for that resource.

### Administrator authorization

Execution requires:

- `manage_options`;
- `edit_post` for the exact resource;
- a nonce scoped to `resource ID + adapter ID`;
- explicit `confirmed=true`;
- a ready migration plan.

The authenticated `admin_post_seo_geo_migration_execute` endpoint repeats capability, nonce and explicit `confirm=migrate` checks. Calling the engine directly does not bypass those engine-level guards.

### Builder adapters

The adapter interface is:

```php
SeoGeo\MigrationBridge\Migration\BuilderMigrationAdapterInterface
```

Phase 8E initially supports conservative, content-preserving subsets:

**Elementor**
- heading;
- text editor;
- image;
- button;
- divider;
- spacer.

**Divi**
- text;
- button;
- image;
- divider;
- spacer;
- section/row/column wrappers are flattened into native content order.

Anything outside those allowlists becomes an explicit blocker such as `unsupported-elementor-widget:form` or `unsupported-divi-module:...`. Unsupported content is never silently dropped.

The target is native WordPress core blocks. Builder-specific styling/layout controls are not presented as exact visual parity and remain subject to sandbox/manual review.

### Backup and preservation

Immediately before a content write the engine creates `_seo_geo_migration_backup_v1`, containing the private pre-migration post content and only the metadata keys that the adapter may modify.

After transformation it verifies:

- same object ID;
- same slug;
- same permalink path.

On a failed post-mutation invariant, the engine restores the original content/meta and removes the incomplete migration markers.

Successful execution stores a hash/status marker in `_seo_geo_migration_state_v1`.

The engine does not activate/deactivate plugins, switch themes, modify terms or rewrite business-system configuration. Phase 8C `KEEP` business systems are surfaced in the plan/result and remain outside the mutation scope.

### Preset selection

The migration preset resolver accepts only:

- `corporate`;
- `local-business`;
- `publisher`;
- `ecommerce`;
- `saas-digital-product`.

The engine may reuse the active theme preset or accept an explicit compatible preset. It never silently changes `seo_geo_active_preset`.

### Phase boundary

8E transforms only explicitly supported resources in the non-indexable sandbox. It does not deactivate/remove legacy builders or SEO plugins and does not perform production cutover.

8F remains responsible for candidate-vs-baseline SEO/GEO parity.

## Phase 8F — SEO/GEO parity and regression engine

Status: **complete**

8F turns the persisted Phase 8B public-output baseline into a strict acceptance reference for the transformed sandbox.

### Origin-neutral comparison

Production and sandbox origins are not expected to match. The parity engine therefore keys resources by path/query and normalizes URL-valued SEO signals the same way. A hostname change alone is not a regression; a path, status, canonical target, hreflang relationship or redirect target change is.

### Compared signals

For every tracked public path the engine compares:

- URL presence and HTTP status;
- indexability state;
- canonical and robots directives;
- title and meta description;
- HTML language and hreflang;
- Open Graph;
- Schema type set and block count;
- H1 count;
- internal links;
- primary visible-content SHA-256.

It also compares observed redirects and sitemap status/location-count topology.

### Ownership/conflict evidence

The HTML extractor records candidate counts for title, meta description, canonical and robots output plus duplicated hreflang language keys.

Hard conflicts are non-allowable:

- duplicated canonical;
- duplicated robots meta;
- duplicated meta description;
- duplicated title;
- duplicated hreflang language key;
- exact duplicate JSON-LD block fingerprints;
- internal links that target a known tracked 4xx/error resource.

### Intentional-difference allowlist

An intentional difference must identify:

- exact path;
- exact signal;
- baseline-value SHA-256;
- candidate-value SHA-256;
- non-empty reason.

Rules are not wildcard approvals. If the candidate value changes again, its hash changes and the previous approval no longer matches.

### Privacy and phase boundary

The parity report exposes fingerprints and decision metadata rather than raw compared bodies. It is read-only and always declares `production_cutover_allowed=false`.

Accessibility/Responsive and Performance Baseline now include a representative post-migration native-block fixture and are triggered by Migration Bridge/parity changes. Passing the parity comparator alone is therefore not sufficient to close 8F.

## Phase 8G — Safe cutover and rollback

Status: **complete**

8G introduces the first production-runtime mutations, but only after the migration has already passed sandbox transformation and 8F parity.

### Mandatory external recovery evidence

Before any theme switch or plugin deactivation, the cutover engine requires recent evidence for a complete database backup and the uploads tree. Each record contains a recovery reference, SHA-256, creation time, scope and positive byte size; evidence older than 24 hours is rejected.

A production cutover also requires recent, passed evidence for both the representative migrated-page Accessibility/Responsive gate and the Performance/Lighthouse gate. Quality evidence carries a reference, SHA-256, creation time and pass state; stale or failed evidence blocks the plan.

The bridge deliberately does not claim to create provider-independent database/uploads disaster-recovery artifacts itself. Hosting or CLI backup systems create those artifacts; the bridge validates and records their evidence before mutation.

### Internal recovery snapshot

Immediately before cutover the bridge appends a non-autoloaded record under `seo_geo_cutover_history_v1` containing the original active theme/plugins, installed package versions, protected options, persisted baseline/redirect fingerprints, migrated-resource backup fingerprints, external recovery evidence and the exact planned actions.

Raw 8E rollback bodies are not copied into cutover history.

### Cutover blockers and mutation boundary

A plan is blocked while the sandbox marker is enabled, search visibility is disabled, multisite is detected, the target theme or baseline is missing, fresh 8F parity is not accepted, backups are missing/stale, another cutover is active, or an unsafe plugin deactivation is requested.

Only explicit `REPLACE`, `REMOVE-CANDIDATE` and `OPTIONAL` plugin dependencies may be deactivated. `KEEP`, `UNKNOWN` and still-`MIGRATE` dependencies remain blocking. The Migration Bridge itself cannot be deactivated before final acceptance.

8G never deletes plugins/themes, clears uploads or resets the database.

### Execution, post-check and rollback

The engine may switch to `seo-geo-theme`, deactivate only approved explicit plugins, and run rewrite/object-cache/sitemap refresh only when runtime changes require it.

Immediately after cutover it verifies the expected theme/plugin state, continued bridge activity, unchanged protected options and fresh 8F parity. Failure triggers automatic runtime rollback.

Manual rollback is available while status remains `cutover-active`. It first refuses unrelated runtime drift, then restores the recorded theme/plugins and verifies fresh parity. If runtime restoration succeeds but parity does not, the record becomes `rolled-back-review-required` so external database/uploads recovery can be used without hiding the unresolved state.

### Final acceptance

Explicit acceptance requires fresh runtime health and fresh 8F parity. It changes the record to `accepted`, closes runtime rollback and retains recovery evidence for the Phase 8H audit/report.

### 8G acceptance evidence

Final implementation candidate `bffb8b1f8a4fec960d681e4e6effdad283aa37e5` passed all six required gates:

- Foundation CI `35875100904`;
- Phase 1 Package CI `35875100776`;
- PHP Quality CI `35875100797` — WPCS + PHPStan level 6;
- WordPress Smoke CI `35875100885`;
- Accessibility & Responsive CI `35875100967`;
- Performance Baseline CI `35875101125`.

PR #79 was squash-merged as `4693f99b430b83f9039a0943575f13f45f7a82bf`.

Post-merge `main` repeated the six-gate acceptance successfully:

- Foundation CI `35875813384`;
- Phase 1 Package CI `35875813344`;
- PHP Quality CI `35875813456`;
- WordPress Smoke CI `35875813345`;
- Accessibility & Responsive CI `35875813328`;
- Performance Baseline CI `35875813333`.

The runtime acceptance proved backup and quality evidence requirements, KEEP/MIGRATE protection, automatic rollback on failed parity, manual rollback, explicit acceptance and rollback closure after acceptance.

## Phase 8H — Migration report

Status: **complete**

8H converts the migration evidence into one stable handoff artifact that Phase 9 can consume after the temporary Migration Bridge is removed.

The report engine is exposed through:

```php
$report = \SeoGeo\MigrationBridge\Plugin::migration_report()?->generate( $parity_allowlist );
```

Generation is read-only. It consolidates:

- the persisted 8B public-output baseline;
- the current 8C dependency graph;
- 8E migrated-resource IDs and before/after/backup fingerprints;
- a fresh 8F parity comparison bound to the same exact allowlist hash used at cutover;
- representative Accessibility/Responsive and Performance before/after summaries retained by 8G;
- the accepted 8G cutover record and its recovery references.

The dependency section reports before/after active-plugin counts and before/after legacy-builder-coupled resource counts. Component decisions distinguish KEEP, replaced/deactivated, remove candidates and actual deletion. Actual deletion remains false because 8G forbids plugin/theme deletion.

Fresh parity exposes URL/status/redirect counts and intentional approved improvements as path/signal/fingerprint/reason metadata. Raw old/new values are not copied.

Manual-review rows separate blocking `MIGRATE`/`UNKNOWN` or parity regressions from advisory cleanup candidates and missing comparison summaries.

Bridge disposition is evidence-driven:

- `remove` when the accepted report has no remaining review items;
- `retain-audit-only` when migration is accepted but review items remain;
- `retain-operational` only while the migration itself is not final and rollback/cutover capability is still operationally required.

A ready report may be explicitly saved through:

```php
$storage = \SeoGeo\MigrationBridge\Plugin::migration_report_store()?->save( $report );
```

The stable handoff option is `seo_geo_migration_report_v1`, stored non-autoloaded. Phase 9 may read that option without loading the Migration Bridge plugin.

The report/store contract forbids private post bodies, raw Elementor/Divi payloads, credentials and raw database/uploads backup content.

### 8H acceptance evidence

Final candidate `62a3a9914469f74d5a88e586b3409b1b6388649f` passed all six required gates:

- Foundation CI `35878260227`;
- Phase 1 Package CI `35878260279`;
- PHP Quality CI `35878260020` — WPCS + PHPStan level 6;
- WordPress Smoke CI `35878260287`;
- Accessibility & Responsive CI `35878260087`;
- Performance Baseline CI `35878260054`.

PR #81 was squash-merged as `20df50545f84a14cd172147259e1233447660424`.

Post-merge `main` repeated the same six gates successfully: Foundation `35878908448`, Package `35878908405`, PHP Quality `35878908466`, WordPress Smoke `35878908657`, Accessibility/Responsive `35878908524` and Performance `35878908520`.

The remaining Phase 8 exit gap is not report functionality: it is the explicit EN/ES administrator/operator interface required by the Phase 8 exit criteria.

## Phase 8I — Operator UI and Phase 8 exit

Status: **complete**

8I adds the human-facing migration operations screen needed to close Phase 8 without expanding Phase 9 onboarding scope.

The Phase 8I baseline registered a read-only screen under **Tools → SEO/GEO Migration**. During the Phase 10E real-site pilot this screen was extended with explicit capability/nonce-gated planning actions for resumable baseline capture, UNKNOWN dependency review and privacy-bounded sandbox-handoff download. Opening/rendering the screen remains read-only; review actions persist only bounded planning metadata and do not execute migration, cutover, rollback, plugin deactivation or theme switching.

Operator state comes from `OperatorStatus`, which exposes only bounded metadata:

- persisted baseline availability/fingerprint;
- dependency classification counts plus bounded UNKNOWN review progress;
- latest cutover status;
- final report availability, review counts and bridge disposition;
- one safe next-step key;
- explicit read-only/privacy safety flags.

When the final 8H handoff exists, dependency counts are read from that report. Before handoff, the screen may fall back to the existing 8A/8C read-only analyzer/graph in memory.

`OperatorCopy` ships one key-complete English/Spanish catalog. The selected language follows the WordPress user locale, with English fallback.

The screen never renders post bodies, Elementor/Divi payloads, credentials or raw database/uploads recovery artifacts. UNKNOWN review decisions are stored in the non-autoloaded `seo_geo_migration_dependency_reviews_v1` option as component ID + fixed classification/reason code + timestamp. They are exported separately from the raw dependency classification and cannot authorize production deactivation by themselves.

It provides:

- one capability-gated WordPress Tools screen for Migration Bridge status;
- built-in English and Spanish operator copy shipped together;
- locale-aware status for baseline, dependency plan, cutover and final handoff report;
- safe next-step guidance without executing migration/cutover mutations from page rendering;
- explicit planning-only UNKNOWN review decisions kept separate from dependency-graph authority;
- bridge disposition and unresolved review summary from the persisted 8H report;
- semantic headings/tables/status text suitable for keyboard and screen-reader use;
- no private bodies, builder payloads, credentials or raw recovery artifacts.

### 8I acceptance evidence

Final candidate `783c3473363bf5a6dd95b6668db408ebbe0bbd59` passed all six required gates:

- Foundation CI `35881439395`;
- Phase 1 Package CI `35881439605`;
- PHP Quality CI `35881439382` — WPCS + PHPStan level 6;
- WordPress Smoke CI `35881439637`;
- Accessibility & Responsive CI `35881439748`;
- Performance Baseline CI `35881439439`.

PR #84 was squash-merged as `e7f9c80ee0a8229917c0995f349ab49a4b0a00c3`.

Post-merge `main` repeated all six gates successfully:

- Foundation CI `35881964530`;
- Phase 1 Package CI `35881964347`;
- PHP Quality CI `35881964370`;
- WordPress Smoke CI `35881964477`;
- Accessibility & Responsive CI `35881964534`;
- Performance Baseline CI `35881964253`.

WordPress Smoke proved that the Tools screen is registered, EN/ES catalogs are complete, unauthorized users are rejected, rendering is semantic/privacy-bounded and the protected migration state fingerprint is unchanged before/after rendering.

Phase 8 is complete. For the existing Theme 0.1.0 path, the bridge may still be removed or retained audit-only according to the accepted 8H disposition; Phase 9 consumes the persisted handoff without loading Migration Bridge at runtime. For the two-product roadmap, these accepted migration contracts become the regression baseline that SEO/GEO Manager must absorb before the standalone bridge package can be retired.

## Phase 8A acceptance evidence

Phase 8A closed through PR #67.

Final PR candidate `494139b10d3c2cc57eafb4cf499017d49c7e5fdb` passed Foundation, Package, PHP Quality, WordPress Smoke and Self-contained Theme. The implementation was squash-merged as `337cd161560b5fce9f0e250d59197546d0eee462`, and all five gates passed again on `main`.

The key runtime proof is WordPress Smoke: the analyzer inspected a synthetic legacy installation containing active/inactive plugins, Elementor, Divi, WooCommerce, Yoast, a child theme, CPT/taxonomy/shortcode, menu and custom CSS, while the protected-state fingerprint remained unchanged.

The Phase 8A analyzer remains the non-destructive environment inventory. Phase 8B adds the public-output baseline described above without turning legacy output into the new authority.
