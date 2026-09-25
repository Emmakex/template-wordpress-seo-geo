# Portable Clone Engine

## Decision

The Migration Bridge must not require a third-party migration/staging plugin to complete a supported client migration.

The product therefore gains a **Portable Clone Engine** that can:

1. create a safe local clone in an isolated subdirectory when hosting access permits;
2. export a portable clone package for another server/environment;
3. import and restore that package into a controlled sandbox;
4. rewrite environment-specific URLs safely;
5. harden the restored environment before any migration action;
6. verify integrity and resumability before handing control to the existing sandbox, migration, parity and cutover contracts.

This capability is implemented first in the transitional Migration Bridge for the Phase 10E real-site pilot and later becomes part of the permanent SEO/GEO Manager migration module.

The Theme remains independent and does not create clones.

## Product boundary

The Portable Clone Engine is a **migration transport/provisioning subsystem**, not a backup product and not a production deployment engine.

It may copy the minimum site state required to reproduce the WordPress site in a sandbox, but it must never:

- overwrite production from a stale sandbox;
- treat a URL folder as proof of storage isolation;
- copy or publish secrets into repository evidence;
- send live email, payment, webhook or CRM mutations from the sandbox;
- switch the production Theme automatically;
- bypass existing Migration Bridge baseline/dependency/parity/cutover gates;
- claim an import is accepted merely because file/database extraction succeeded.

The accepted path remains:

```text
production analysis/baseline
        ↓
dependency review
        ↓
Portable Clone Engine
        ↓
sandbox hardening + preflight
        ↓
content/theme migration
        ↓
SEO/GEO parity + functionality + accessibility + performance
        ↓
explicit production cutover
```

## Supported operating modes

### Mode A — Local clone

Use when the hosting account allows a separate WordPress installation under the same server/account, including an isolated subdirectory such as:

```text
https://example.com/nuevaweb/
```

Required properties:

- target path is not the production WordPress path;
- target database or table set is independent from production;
- target uploads/plugins/themes directories are independent mutable copies;
- target WordPress `home` / `siteurl` point to the sandbox path;
- sandbox mode is explicitly declared as `subdirectory` when the HTTP origin is shared;
- search indexing and outbound mutations are blocked before the sandbox is considered ready.

The Engine may automate the clone only when it can prove all destination paths/tables are outside the production mutable namespace.

### Mode B — Portable export

Use when the sandbox will be created on another server, container, local machine or hosting account.

The Engine produces a versioned package containing the site-copy payload and a bounded manifest. The package is not committed to Git and may contain private client data; it must be treated as protected migration material.

### Mode C — Portable import

Use at the destination WordPress/runtime.

Import validates the manifest, package integrity, destination safety and target emptiness/authorization before restoring any data.

Import is resumable and must stop before mutation when:

- checksum verification fails;
- source/destination package versions are unsupported;
- destination overlaps production;
- target database/table namespace is unsafe;
- required disk space cannot be established;
- the package is incomplete;
- an incompatible in-progress job exists.

## Clone package

### Package identity

Each export has a unique immutable ID:

```text
seo-geo-clone-<site-id>-<timestamp>-<random-id>
```

The package is versioned independently from the plugin runtime.

Initial manifest contract:

```json
{
  "schema_version": 1,
  "package_id": "...",
  "source": {
    "home_url": "https://example.com/",
    "site_url": "https://example.com/",
    "wordpress_version": "...",
    "php_version": "..."
  },
  "payload": {
    "database": {},
    "uploads": {},
    "plugins": {},
    "themes": {}
  },
  "integrity": {},
  "sandbox": {},
  "privacy": {}
}
```

The manifest records metadata/checksums, not credentials.

### Payload classes

Initial supported payload classes:

- WordPress database tables in the selected site/table prefix;
- `wp-content/uploads`;
- installed plugin files required to reproduce the site;
- installed theme files required to reproduce the site;
- bounded non-secret runtime metadata necessary to restore WordPress behavior.

Excluded by default:

- cache directories;
- generated backup archives;
- temporary files;
- logs;
- session stores;
- package-manager caches;
- repository `.git` directories;
- unrelated hosting-account files;
- server credentials/private keys;
- `wp-config.php` secret values;
- external service credentials.

A later adapter may explicitly add a provider-specific required file, but it must be allowlisted and covered by the package manifest.

## Database export contract

The database exporter operates by explicit WordPress table prefix/table inventory rather than dumping an entire hosting database blindly.

Required behavior:

- inventory tables before export;
- export table schema + rows in deterministic chunks;
- persist per-table cursor/progress;
- avoid one-request full database dumps;
- record row counts and content hashes per completed chunk/table;
- support retry without duplicating rows in the package;
- keep source production read-only;
- never include database credentials in the package manifest.

Large tables must be chunked by a stable cursor appropriate to the table. If no safe cursor exists, the exporter must use a bounded deterministic fallback and record the chosen strategy.

## File export contract

Files are exported through a deterministic inventory.

For every accepted file record:

- normalized relative path;
- byte size;
- SHA-256;
- payload class;
- export status.

The Engine copies files in bounded batches by total bytes and/or file count, with progress persisted between requests.

Symlinks, path traversal, device files and paths outside accepted WordPress roots are rejected.

## Resumable job model

Clone/export/import operations use persistent jobs and never depend on a single HTTP request completing.

Initial states:

```text
created
  → inventory
  → database
  → files
  → manifest
  → integrity
  → completed
```

Import adds:

```text
validate
  → prepare-target
  → restore-database
  → restore-files
  → rewrite-environment
  → harden-sandbox
  → verify
  → completed
```

Any mutating state may also enter:

```text
paused
failed-retryable
failed-terminal
cancelled
```

Job state must include:

- job/package ID;
- operation type;
- current phase;
- cursor;
- completed counters;
- total counters when known;
- last bounded error code;
- timestamps;
- package schema/runtime version;
- integrity state.

A repeated request with the same job ID continues the existing job rather than restarting it.

## URL and environment rewrite

Import must not perform unsafe raw string replacement across serialized WordPress data.

The rewrite layer must preserve serialized values and operate through WordPress-aware or serialization-safe transforms.

At minimum rewrite:

- `home`;
- `siteurl`;
- same-origin absolute production URLs in supported WordPress options/content/meta;
- upload URLs where the clone path/origin changes.

It must not rewrite:

- arbitrary external URLs;
- email addresses merely containing the source hostname;
- hashes/signatures;
- credential/token values;
- opaque serialized payloads that cannot be transformed safely.

Every rewrite class must be separately counted and reported.

## Sandbox hardening after import

A restored clone is not considered usable until the existing Sandbox Migration Lab reports ready.

For same-origin subdirectory mode the Engine must coordinate/verify:

```php
define( 'SEO_GEO_MIGRATION_SANDBOX', true );
define( 'SEO_GEO_MIGRATION_SANDBOX_MODE', 'subdirectory' );
define( 'SEO_GEO_MIGRATION_STORAGE_ISOLATED', true );
define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );
define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );
```

The Engine may write only constants it explicitly owns and only to a destination sandbox configuration under an authorized operation. It must not modify production `wp-config.php` during clone creation.

Also required:

- `blog_public=0`;
- noindex/nofollow/noarchive response guards;
- no sandbox canonical/hreflang/sitemap leakage;
- outbound email/SMS/payment/webhook safety;
- production analytics contamination prevented or explicitly reviewed;
- destination Theme state reported, not silently switched unless an explicit later migration action authorizes it.

## Privacy and sensitive data

Clone payloads can contain private customer/site data and therefore are not repository-safe artifacts.

Rules:

- clone ZIP/database payloads are never committed to Git;
- manifests stored in repository evidence must remain bounded/redacted;
- package download endpoints require administrator capability + nonce;
- temporary package files must use unpredictable names and non-public storage when possible;
- package retention must be configurable and cleanup explicit;
- plaintext secrets must never be added to the bounded handoff manifest;
- sanitization/anonymization is a separate optional capability, not falsely claimed by the initial clone engine.

For dynamic sites containing orders, users, submissions, bookings or memberships, copied data is sandbox evidence only.

## Dynamic production data rule

The Portable Clone Engine is **one-way for acceptance**:

```text
production → sandbox
```

It must not create a generic:

```text
sandbox → overwrite production database
```

operation.

At production cutover the current production database remains authoritative. Accepted structural/content transformations are re-applied through the cutover contract. Any future data synchronization must be a separate explicit subsystem with per-domain ownership rules.

## Local clone safety

Before creating a local clone, the Engine must verify:

- destination absolute path is known;
- destination is not equal to or nested inside a mutable production subtree that would create recursion;
- destination is either empty or explicitly owned by the current clone job;
- database/table namespace differs from production;
- adequate storage can be estimated;
- source and destination URLs are known;
- current user has `manage_options`;
- operation uses a scoped nonce and explicit confirmation.

The Engine must never recursively copy its own package/temporary directory.

## Import safety

Before restoring:

- package manifest parses and schema is supported;
- all expected payload files exist;
- package SHA-256/checksums match;
- destination has enough free space where measurable;
- destination database/table namespace is isolated;
- current installation is explicitly marked as a sandbox/import target;
- no production-origin exact match can be accepted accidentally;
- import job owns its temporary workspace.

Database restore and file restore must be individually restartable.

## Integrity verification

Export completion requires:

- expected file count/bytes reconciled;
- expected database tables/chunks reconciled;
- per-file/per-chunk hashes recorded;
- package manifest hash computed;
- package final checksum produced.

Import completion requires:

- package checksum match;
- restored file verification;
- database table/row-count reconciliation where supported;
- WordPress bootstrap success;
- target `home` / `siteurl` verification;
- Sandbox Migration Lab preflight result;
- no production mutation evidence.

## Initial operator UX

Migration Bridge adds a **Portable Clone** section with three actions:

- **Create sandbox copy on this server**
- **Export portable sandbox package**
- **Import portable sandbox package**

Actions are shown only when the environment supports them.

Local-clone setup asks for:

- destination path;
- destination URL;
- destination database/table configuration or an already-created isolated target;
- explicit acknowledgement that the target is non-production.

Export shows resumable progress by phase.

Import shows validation first and does not write until the operator confirms the validated plan.

All destructive/overwriting operations require a second explicit confirmation and must have a bounded recovery/cleanup path.

## Architecture boundaries

New source should live under dedicated namespaces, initially:

```text
packages/seo-geo-migration-bridge/src/Clone/
  CloneJobStore.php
  CloneManifest.php
  CloneInventory.php
  DatabaseExporter.php
  FileExporter.php
  PackageBuilder.php
  PackageValidator.php
  ImportPlanner.php
  DatabaseImporter.php
  FileImporter.php
  EnvironmentRewriter.php
  SandboxHardener.php
  AdminCloneController.php
```

The exact class split may evolve during implementation, but these boundaries remain:

- inventory does not mutate;
- export does not mutate production;
- import never runs on an unvalidated target;
- URL rewrite is isolated from raw restore;
- sandbox hardening is isolated from payload transport;
- UI/controllers never contain clone business logic;
- package integrity is validated before restore.

## Microphases

### 10E.2A.1 — Clone contract + persistent resumable jobs

Status: **complete in Migration Bridge 0.8.9**

Deliver:

- package/manifest schemas;
- job state machine;
- non-autoloaded job persistence;
- capability/nonce controller skeleton;
- static privacy/safety contract;
- deterministic test fixtures.

No database/files are copied yet.

### 10E.2A.2 — Read-only source inventory

Status: **complete in Migration Bridge 0.8.10; PR #128 merged and all seven post-merge gates passed**

Deliver:

- database table inventory;
- uploads/plugin/theme file inventory;
- exclusions;
- byte/file/table/row estimates;
- source fingerprint;
- local-clone destination safety planner.

No payload is created yet.

### 10E.2A.3 — Resumable export

Status: **complete in Migration Bridge 0.8.14 — database export, file export, package integrity, authenticated delivery and bounded retention cleanup are accepted**

Internal sequence:

- **10E.2A.3.1 — database export:** complete in 0.8.11; private workspace, schema + deterministic row chunks, resumable state and per-chunk/database-manifest SHA-256;
- **10E.2A.3.2 — file export:** complete in 0.8.12; accepted uploads/plugins/themes streamed into the private workspace in bounded file/byte batches with per-file SHA-256 and exact inventory fingerprint/count/byte reconciliation;
- **10E.2A.3.3 — package manifest + integrity:** complete in 0.8.13; revalidate exported records, compute a resumable full-workspace checksum, require a second matching verification pass before `verified=true`, and block deliberate payload drift/tampering;
- **10E.2A.3.4 — authenticated package delivery + retention cleanup:** complete in 0.8.14; resumable private ZIP build, package-checksum replay, archive SHA-256, administrator/nonce-only download, 24-hour expiry, explicit bounded cleanup and bounded expired-artifact maintenance.

Deliver:

- chunked database export;
- chunked file export;
- manifest/checksums;
- resumable package build;
- authenticated download;
- cleanup/retention.

### 10E.2A.4 — Portable import

Status: **active — 10E.2A.4.1 through 10E.2A.4.5 are accepted; 10E.2A.4.6.1 sandbox activation plan + recovery contract is the Migration Bridge 0.8.20 candidate**

Internal sequence:

- **10E.2A.4.1 — intake + destination preflight:** complete in 0.8.15; stage the ZIP only in job-owned private storage; reject traversal/unknown roots/duplicate paths; validate package + child-manifest contracts and hashes; require an explicitly authorized isolated sandbox destination, noindex, outbound safety, recovery references and sufficient disk space. This subphase performs no restore and keeps `restore_allowed=false`.
- **10E.2A.4.2 — full payload verification + resumable extraction:** complete in 0.8.16; extract only into private job storage, replay the exact package checksum, then rerun destination preflight before exposing restore eligibility. Destination drift keeps restore locked.\n- **10E.2A.4.3 — database restore:** complete in 0.8.17; create/populate deterministic job-owned InnoDB staging tables only. Active destination tables are not altered. Every mutating row batch reruns the fresh restore gate; row inserts and resumable state commit in the same transaction. Schemas with foreign keys/references or nontransactional engines are blocked in this first restore implementation. Final-table activation/swap is explicitly outside this subphase.
- **10E.2A.4.4 — file restore:** complete in 0.8.18; copy only manifest-backed uploads/plugins/themes from the checksum-verified private extraction into `import/staged-files`, never active `wp-content`; batches are resumable and a second independent SHA-256 traversal reconciles exact file/byte totals before completion.
- **10E.2A.4.5 — serialization-safe environment rewrite:** complete in 0.8.19; rewrite supported WordPress staging-table environment URLs only. PHP serialized arrays/scalars and JSON containers are decoded structurally and re-encoded only when changed; unsupported/opaque serialized bytes are never raw-replaced, and any such opaque value that still contains a source-environment URL blocks completion. Credential/token keys remain opaque and are surfaced as advisories. A second read-only pass is idempotent. Active destination tables and staged file bytes remain untouched.
- **10E.2A.4.6.1 — activation plan + recovery contract:** 0.8.20 candidate; revalidate payload/database/file/rewrite identity, require fresh external database + wp-content recovery evidence, verify active/staging root separation and reserve deterministic rollback table names. Planner mutations are forbidden.
- **10E.2A.4.6.2 — database activation:** planned; sandbox-only atomic swaps preserving rollback tables.
- **10E.2A.4.6.3 — file-root promotion:** planned; sandbox-only bounded promotion preserving rollback roots.
- **10E.2A.4.6.4 — final sandbox hardening + integrity:** planned; verify the activated clone and require the sandbox safety contract before handoff.

Deliver:

- package validation;
- destination isolation plan;
- chunked DB/files restore;
- serialization-safe environment rewrite;
- resumable import;
- integrity verification.

### 10E.2A.5 — Local clone orchestration

Deliver:

- direct production → isolated folder clone using the same export/import primitives;
- no duplicate clone implementation;
- `/nuevaweb/` same-origin support;
- automatic handoff to sandbox hardening/preflight.

### 10E.2A.6 — Emmake real clone acceptance

Deliver:

- clone `emmake.com` into `/nuevaweb/` using the Engine;
- confirm independent database/table/file state;
- confirm production remains unchanged;
- confirm full dependency/baseline/review evidence survives;
- Sandbox Migration Lab reports `ready=true`;
- record bounded evidence only.

Only after 10E.2A.6 may the pilot continue to Theme migration/parity acceptance.

## Acceptance criteria

The Portable Clone Engine is accepted only when:

- no third-party cloning plugin is required for a supported path;
- production remains read-only during export/local-copy source stages;
- jobs resume after request interruption;
- package integrity detects corruption;
- import cannot target production accidentally;
- same-origin folder mode requires explicit storage isolation;
- URL rewrite preserves supported serialized WordPress values;
- sandbox hardening is applied/verified before migration;
- no stale sandbox database restore-to-production path exists;
- no credentials/private clone payloads enter repository evidence;
- CI covers positive and negative safety cases;
- the emmake.com `/nuevaweb/` pilot proves the real workflow.

## Future Manager absorption

Phase 11 does not re-invent this subsystem.

SEO/GEO Manager absorbs the accepted Portable Clone Engine contracts into its migration/sandbox module. The standalone Migration Bridge may later be retired only after Manager regression tests prove equal or stronger behavior for:

- job resumability;
- package integrity;
- local clone;
- portable export/import;
- sandbox hardening;
- privacy;
- dynamic-data protection;
- rollback/cleanup boundaries.
