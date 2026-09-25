# Migration Bridge release package

## Purpose

SEO/GEO Migration Bridge must be installable on a real WordPress site as a normal plugin ZIP before the Theme 0.1.0 real-site pilot begins.

The release package is built from:

- source: `packages/seo-geo-migration-bridge/`;
- main plugin: `seo-geo-migration-bridge.php`;
- current plugin version: `0.8.15`;
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

Acceptance evidence for 0.8.15: PR #135 squash-merged as `744384bfad7ec12f8c9445b53ac362746679ff88`; all seven post-merge gates passed (Foundation `36141521870`, Package `36141521962`, PHP Quality `36141521960`, WordPress Smoke `36141521891`, Accessibility/Responsive `36141521964`, Performance `36141521877`, Release `36141521885`). Deterministic installable ZIP SHA-256: `a678b8bee7644a0162860c82b751c600443db6a2ebff6bbf9f8c51276fece9d0`.


### Version 0.8.16 — full payload verification + resumable extraction

Phase 10E.2A.4.2 takes only a clean 0.8.15 preflight package. Once extraction starts the staged ZIP can no longer be silently replaced. The archive is extracted in bounded resumable batches into a job-owned private import workspace, never into the live WordPress destination. After extraction, the Bridge traverses that private payload with the same lexicographic breadth-first checksum contract used by export, excluding package metadata exactly as the source package did.

The phase requires exact archive identity, package-manifest identity, payload file count, payload byte count and SHA-256 chain equality. Checksum completion then triggers a fresh destination preflight against the current sandbox markers, indexing state, outbound safety, recovery evidence, authorization and isolation. Only if that fresh check still passes is the import state exposed as `full_payload_verified=true` and `restore_allowed=true`; destination drift keeps restore disabled while preserving the completed private checksum state so safety can be corrected and revalidated without unnecessary re-extraction. A checksum mismatch blocks the job. Even after a successful 0.8.16 verification no database rows or destination WordPress files are restored yet; `restore_allowed=true` is eligibility for the guarded database-restore phase 10E.2A.4.3, not permission for arbitrary mutation.


### Version 0.8.17 — transactional staging database restore

Phase 10E.2A.4.3 begins database restoration only after the complete payload has reproduced the accepted package checksum and a fresh destination preflight still reports `payload-verified` + `restore_allowed=true`. The Bridge maps source tables to deterministic job-owned staging tables and never mutates active destination WordPress tables in this version.

Schema/chunk files are reread from the verified private extraction and checked against database-manifest hashes before use. This first restore contract accepts only transactional InnoDB schemas without foreign keys/references. Rows are restored in bounded batches, and each batch commits both the staging-table writes and resumable cursor state in the same database transaction. Destination drift, manifest drift, archive drift, unexpected staging tables, nontransactional storage or schema incompatibility block further mutation. Final-table activation/swap remains a later phase.


### Version 0.8.18 — verified staging-file restore

Phase 10E.2A.4.4 starts only after 0.8.17 database staging restore is complete and the current destination still passes the verified-payload runtime guard. The Bridge copies manifest-backed `uploads`, `plugins` and `themes` files from the private extracted package into the job-owned `import/staged-files` tree. It never resolves or writes the active WordPress uploads/plugins/themes roots in this phase.

Every copied file is checked against its `files-meta` record and extracted SHA-256. After the copy traversal reconciles exact file/byte totals, a second resumable traversal re-hashes staged files and rejects missing, extra, symlinked or modified payload. Administrator controls use a 1–20 files/request selector plus a bounded byte budget. Environment rewrite, activation/promotion of staged files and final sandbox hardening remain later phases.


### Version 0.8.19 — serialization-safe environment rewrite

Phase 10E.2A.4.5 starts only after verified database and file staging have completed. It derives a fresh deterministic staging-table plan from the accepted database manifest and rewrites only supported WordPress environment surfaces (`options`, posts/content and core meta/comment/taxonomy text columns) inside job-owned staging tables. Active destination tables and active/staged file payloads are not activation targets in this phase.

`home` and `siteurl` are rewritten only when they still equal package source evidence. Other same-origin absolute URLs are mapped to the current destination while preserving path/query/fragment. Supported PHP serialized values are decoded with classes disabled and re-serialized after recursive changes so length metadata remains valid; JSON object/array containers are decoded/re-encoded structurally. Raw search/replace over serialized bytes is forbidden. Serialized-looking values that cannot be safely decoded are left untouched only when they contain no source-environment URL; otherwise the batch blocks. Credential/token-like option/meta keys are never rewritten and are reported as advisories.

Every mutating batch is transactional together with its resumable cursor state. After the rewrite pass, a second read-only pass runs the same transformation logic and must find zero remaining supported source-environment rewrites before completion. Sandbox promotion/activation remains outside 0.8.19 and belongs to 10E.2A.4.6.


### Version 0.8.20 — sandbox activation plan + recovery contract

Phase 10E.2A.4.6.1 is intentionally non-mutating. After the 0.8.19 environment rewrite completes, Migration Bridge re-runs the fresh import preflight, requires payload/database/file/rewrite states to remain complete and identity-consistent, rescans the private staged file tree, and derives the exact database staging → target → deterministic rollback-table map.

Activation planning requires the sandbox marker, noindex state, outbound safety, backup-ready marker, explicit import-target authorization and subdirectory storage isolation when applicable. In addition, the operator must supply fresh external recovery evidence (reference, SHA-256, timestamp, scope and size) for a full database backup and the full wp-content tree. Recovery evidence older than 24 hours blocks the plan.

The planner performs no SQL/file activation and persists `database_activation_allowed=false`, `file_activation_allowed=false` and `mutations_performed=false` even when the plan is ready. Database swaps, file-root promotion and final activated-target verification are separate 10E.2A.4.6 subphases so each destructive boundary can have its own nonce, runtime guard and rollback evidence.
