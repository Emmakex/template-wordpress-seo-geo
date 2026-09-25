# Migration Bridge release package

## Purpose

SEO/GEO Migration Bridge must be installable on a real WordPress site as a normal plugin ZIP before the Theme 0.1.0 real-site pilot begins.

The release package is built from:

- source: `packages/seo-geo-migration-bridge/`;
- main plugin: `seo-geo-migration-bridge.php`;
- current plugin version: `0.8.16`;
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


### Version 0.8.11 — resumable private database export

Phase 10E.2A.3.1 begins real Portable Clone payload creation. After a completed 0.8.10 inventory, local-clone/export jobs can create a private temporary workspace and export only inventoried WordPress-prefix tables. Schema is stored separately; rows are written in bounded deterministic chunks using a single primary-key cursor when available or a recorded deterministic offset/order fallback otherwise. Values are binary-safe base64-or-null, each schema/chunk is SHA-256 hashed, progress is persisted in a non-autoloaded option and the final database manifest explicitly marks the payload private/non-repository-safe. Source database access in the exporter is SHOW/SELECT only. Final package assembly/download and retention policy remain later 10E.2A.3 substeps.

### Version 0.8.12 — resumable private file export

Phase 10E.2A.3.2 adds the file-payload stage after database export. Accepted uploads/plugins/themes are traversed deterministically with the same exclusion policy as the source inventory and streamed into the private job workspace in bounded file-count/byte batches. Each copied file is SHA-256 verified and receives a bounded metadata record. Completion is refused if exported file count, total bytes or the chained source fingerprint differ from the completed inventory, protecting package assembly from source drift. Symlinks and excluded cache/backup/temp/log paths remain outside the payload. Production source files are read only; package assembly, authenticated delivery and retention cleanup remain 10E.2A.3.3/10E.2A.3.4.


### Version 0.8.13 — package manifest + integrity

Phase 10E.2A.3.3 builds the first complete Portable Clone package manifest. After database and file exports complete, Migration Bridge scans the private workspace in bounded file/byte batches, revalidates database schema/chunk hashes and file-payload hashes against their exporter evidence, and computes a deterministic SHA-256 checksum over normalized path + byte count + file hash records. A second resumable verification pass must reproduce the same file count, byte count and checksum before the package manifest records `verified=true`. Deliberate payload drift/tampering blocks completion. The package remains private/non-repository-safe and `delivery_ready=false`; authenticated archive delivery and retention cleanup remain 10E.2A.3.4.

Acceptance evidence for 0.8.13: PR #131 squash-merged as `896c449116980238e4163da6b15ee4caec20b67c`; all seven post-merge gates passed (Foundation `36133942803`, Package `36133942785`, PHP Quality `36133942874`, WordPress Smoke `36133942852`, Accessibility/Responsive `36133942801`, Performance `36133942970`, Release `36133942826`). Deterministic installable ZIP SHA-256: `d7bb4225712274b8fb5538addf053f5a6c2b635174f4b9cb17bbd08059d10954`.


### Version 0.8.14 — authenticated package delivery + retention cleanup

Phase 10E.2A.3.4 turns a verified private package workspace into a downloadable portable ZIP without exposing a public archive URL. ZIP construction is resumable and bounded by file count/bytes, uses WordPress Core PclZip for broad hosting compatibility, and replays the package checksum contract while archiving so payload drift blocks delivery. The finalized ZIP receives its own SHA-256 and is streamed only through authenticated `admin_post` with `manage_options` + job-scoped nonce. Ready packages have a 24-hour private retention deadline; expired downloads are refused, explicit cleanup is capability/nonce gated, and opportunistic admin maintenance advances bounded job-scoped cleanup batches without touching unrelated temp files. Portable Import remains 10E.2A.4.

Acceptance evidence for 0.8.14: PR #133 merged as `2f7249e820a9e36c0d75fc95f43f695db017dbda`; all seven post-merge gates passed (Foundation `36137249879`, Package `36137249445`, PHP Quality `36137249424`, WordPress Smoke `36137249674`, Accessibility/Responsive `36137249673`, Performance `36137249588`, Release `36137249573`). Deterministic installable ZIP SHA-256: `e81a412bafff42f1c8c5a41370313373a5b83ec5ac73b8ad454973c7e2d9bab0`.


### Version 0.8.15 — Portable Import intake + destination preflight

Phase 10E.2A.4.1 starts the destination side without restoring payload. An administrator creates an `import` job, uploads a Portable Clone ZIP through a capability/job-nonce-gated endpoint, and the Bridge stages it only inside private job-owned temporary storage. Preflight rejects unsafe or duplicate archive paths, unknown archive roots, missing manifests, unsupported package contracts and child-manifest SHA-256 mismatches.

Destination validation requires an explicitly marked migration sandbox, search visibility disabled, outbound safety, fresh recovery references, explicit `SEO_GEO_MIGRATION_IMPORT_TARGET_AUTHORIZED=true`, a valid isolated origin/subdirectory topology and enough determinable free disk space. The source URL may not equal the destination URL. This version intentionally records `full_payload_verified=false` and `restore_allowed=false`; no database rows, WordPress options/content or destination files are restored in 0.8.15. Full payload checksum replay and resumable extraction belong to 10E.2A.4.2.


### Version 0.8.16 — Full payload verification + resumable private extraction

Phase 10E.2A.4.2 extracts only preflight-accepted ZIP file entries into the job-owned private `import/payload/` workspace, using bounded file/byte batches and resumable cursors. After extraction, a second bounded breadth-first pass reproduces the same `payload|path|bytes|sha256` checksum contract used by export while excluding `package/` metadata exactly as the exporter does.

The restore gate stays closed during extraction and verification. It opens only when the extracted payload reproduces the accepted package checksum, payload file count and payload byte count exactly, the extracted package manifest still matches the staged ZIP preflight hash, and a fresh destination preflight remains clean. This version does not execute restore SQL or write payload into WordPress content paths; 10E.2A.4.3 remains the first database-restore phase.
