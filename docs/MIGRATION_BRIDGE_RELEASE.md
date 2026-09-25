# Migration Bridge release package

## Purpose

SEO/GEO Migration Bridge must be installable on a real WordPress site as a normal plugin ZIP before the Theme 0.1.0 real-site pilot begins.

The release package is built from:

- source: `packages/seo-geo-migration-bridge/`;
- main plugin: `seo-geo-migration-bridge.php`;
- current plugin version: `0.8.10`;
- ZIP root: `seo-geo-migration-bridge/`.

## Build

Run:

```bash
python3 scripts/build-migration-bridge-release.py \
  --output dist-migration-bridge/seo-geo-migration-bridge.zip
```

The builder also creates:

```text
seo-geo-migration-bridge.zip.sha256
```

The ZIP is deterministic: two builds from identical source must be byte-identical.

## Acceptance

`scripts/ci/migration-bridge-release-acceptance.py` proves:

- plugin header version matches the runtime version constant;
- exactly one WordPress plugin root exists;
- the main plugin file is present at the expected root;
- all source files are present and no repository-only files are added;
- paths are safe;
- timestamps and file modes are normalized;
- two independent builds are byte-identical.

The workflow additionally runs PHP syntax validation for every plugin PHP file.

## GitHub Actions artifact

`.github/workflows/migration-bridge-release.yml` publishes a short-lived CI artifact containing:

- `seo-geo-migration-bridge.zip`;
- `seo-geo-migration-bridge.zip.sha256`.

This artifact is an installation candidate for controlled client analysis. It is not a declaration that a real client migration is accepted.

## emmake.com use

For the first Phase 10E pilot:

1. install the accepted Migration Bridge ZIP from **Plugins → Add New Plugin → Upload Plugin**;
2. activate it;
3. use the read-only analysis path and explicitly capture the public SEO/GEO baseline before any migration mutation;
4. do not activate the destination Theme directly in production;
5. create/use an isolated sandbox before transformation;
6. retain current production backups/recovery references.

The operator screen only shows the baseline capture action when no baseline exists. The action is capability/nonce protected and runs as a **resumable incremental capture**. Robots/sitemap discovery remains conservative, while page analysis uses an operator-selectable batch size from **1 to 20 pages per request**. The default/recommended value is **10**. The selected value is persisted with capture progress and can be changed safely while the capture is running; existing 0.8.3 progress without a stored batch size resumes at the 0.8.4 default of 10. Progress is persisted in a dedicated non-autoloaded option after every step, so a timeout/closed connection can resume instead of restarting. The final baseline is stored only after all accepted steps complete and an existing final baseline is never replaced automatically.

After the baseline is complete, 0.8.5 exposes the bounded dependency rows behind the summary so UNKNOWN/MIGRATE/REPLACE items can be reviewed before sandbox work. It also provides an authenticated **sandbox handoff JSON** download containing runtime identity, baseline ID/hash/counts, bounded dependency decisions and sandbox safety requirements. The handoff never includes post bodies, builder payloads, credentials, arbitrary option values, database dumps, uploads or customer data.

Version 0.8.6 adds an explicit operator-review workflow for `UNKNOWN` components. Each review is capability/nonce-gated and stores only the component ID, one fixed planning classification (`KEEP`, `REPLACE`, `MIGRATE`, `OPTIONAL` or `REMOVE-CANDIDATE`), a fixed reason code and timestamp in a non-autoloaded option. Review evidence is exported separately in the handoff manifest and **does not replace the raw dependency-graph classification or authorize production plugin deactivation/theme switching**. The operator can revise or clear a decision while the component remains a live `UNKNOWN` item.

Version 0.8.7 hardens the real sandbox preflight. A clone is not ready until its origin differs from the production baseline origin, `SEO_GEO_MIGRATION_SANDBOX=true`, WordPress search visibility is disabled, `SEO_GEO_MIGRATION_OUTBOUND_SAFE=true`, `SEO_GEO_MIGRATION_BACKUPS_READY=true`, the destination Theme is active, the baseline/dependency graph are present and every raw `UNKNOWN` component has an explicit operator review. Reviewed `UNKNOWN` decisions affect sandbox planning only: `KEEP` becomes `unchanged`; `MIGRATE`/`REPLACE` become `migrate`; `OPTIONAL`/`REMOVE-CANDIDATE` remain `manual-review`. Raw graph classifications are preserved.

Version 0.8.8 adds a second accepted sandbox topology for shared-hosting clients: an explicitly isolated same-origin subdirectory. The default remains `origin`. Subdirectory mode requires `SEO_GEO_MIGRATION_SANDBOX_MODE='subdirectory'`, a non-root base path different from the production baseline path and `SEO_GEO_MIGRATION_STORAGE_ISOLATED=true`, in addition to all v0.8.7 guards. The operator UI exposes mode, production/sandbox paths and location/storage isolation. The Bridge never assumes that a folder is isolated merely because its URL path differs.

The plugin is transitional for the Theme 0.1.0 migration path. Its accepted capabilities later become the migration module inside SEO/GEO Manager.


### Version 0.8.9 — Portable Clone contract foundation

Phase 10E.2A.1 starts the product-owned clone/export/import path without copying site payload yet. It adds a versioned non-autoloaded resumable job store, a privacy-bounded planning manifest, capability/nonce-gated planning-job creation for local clone/export/import, EN/ES operator controls and runtime/static acceptance. Database/file inventory and payload creation remain intentionally deferred to 10E.2A.2/10E.2A.3.


### Version 0.8.10 — read-only source inventory

Phase 10E.2A.2 adds the first real source-discovery layer for Portable Clone. Local-clone/export jobs can inventory WordPress-prefix table metadata and uploads/plugins/themes in bounded resumable batches, hash accepted files, persist only bounded inventory state in a non-autoloaded option, report exclusions/symlinks/unreadable entries and produce a deterministic source fingerprint. A read-only destination planner rejects production-path, payload-root, same-origin-root and shared-table-prefix collisions and can compare estimated required bytes with free space. No database rows or files are copied/restored in this version; payload creation remains 10E.2A.3.
