# Emmake real clone acceptance runbook

Phase **10E.2A.6** proves the accepted Portable Clone Engine against the real Emmake WordPress source without declaring a Theme production cutover.

This runbook is operational evidence guidance. It does not replace the guards implemented by Migration Bridge and it does not authorize writing to the production table/file namespace.

## Accepted identity

Use exactly:

- source origin: `https://emmake.com/`;
- isolated target origin: `https://emmake.com/nuevaweb/`;
- Migration Bridge: `0.8.45`;
- accepted Migration Bridge main commit: `e3c8e86a2cfb5e75ed2f1e204c47a4affd920716`;
- accepted Migration Bridge ZIP SHA-256: `224d489f7d2df25f9d613f21733368116e3ceb0c7964812fc319b27c192f2bf6`;
- bounded source handoff SHA-256: `48b87fed7c8b09be9778f0e62045c0eb8bcf23b35186883ee9f0629a19431d4b`;
- acceptance record: `release/emmake-real-clone-acceptance.json`.

Do not rebuild the plugin locally for this pilot. Use the accepted artifact identity above. Version 0.8.45 is the current Emmake pilot artifact. The user-supplied 0.8.39 ZIP (`70060e4e767b0a3699ed1878d2e9c2e57c7c6d35a8be0e7abb59073b4cf39146`) is preserved as the confirmed last-good real-export baseline that reached 20,256/20,256 files and 608/608 MB. 0.8.44 recovered that fully traversed snapshot despite live-source byte drift. The subsequent real package-integrity pass exposed a separate validator false positive: legitimate exported `index.php` and `.htaccess` payloads were being checked as generated workspace guards before their per-file evidence. 0.8.45 validates exported payload evidence first, keeps guard fallback for generated files, and permits the retained `package-exported-payload-mismatch` state to retry from its saved cursor. A genuine byte/hash mismatch still blocks immediately. Install 0.8.45 over the current plugin, reopen the same retained Emmake job, and use **Continue package integrity**; do not create a new clone or re-export the files.

## Operator-only inputs required before execution

These values are environment-owned and must not be committed to Git:

- current recoverable production database backup reference;
- current recoverable uploads/files backup reference;
- backup capture timestamp;
- absolute filesystem path that corresponds to `/nuevaweb/`;
- isolated target table prefix, distinct from the production prefix;
- operator identity;
- hosting/runtime access needed to complete the authorized WordPress actions.

The target absolute path and target table prefix must be discovered from the real hosting environment. Do not guess either value.

## Stop conditions before mutation

Do not continue if any of the following is true:

- the accepted plugin ZIP hash does not match;
- either backup reference is missing or not recoverable;
- the target absolute path is production or an unsafe mutable production subtree;
- the target table prefix equals or overlaps the production table namespace;
- the target contains unowned client data;
- storage isolation cannot be proven;
- free-space/capacity validation fails;
- source baseline or UNKNOWN review is no longer complete;
- an existing incompatible clone/import job is active;
- Migration Bridge reports any destination blocker.

Production remains the source of truth throughout this acceptance.

## Execution sequence

Run the sequence through **Tools → SEO/GEO Migration Bridge**. Complete each step before the next one appears. A repeated request for the same job must resume the existing job rather than create a second clone.

### 1. Reconfirm source evidence

Before creating a local clone:

- confirm the existing SEO/GEO baseline is Ready;
- confirm all 13 historical UNKNOWN items remain reviewed;
- confirm `unreviewed_unknown=0`;
- record fresh production backup references;
- record production `home` / `siteurl`, active theme/plugins and bounded source evidence used for the before/after comparison.

No repository evidence may contain credentials, arbitrary option values, post bodies, customer data, database dumps or uploads.

### 2. Build/verify the Portable Clone package

Use the existing Portable Clone export/package flow until package integrity is complete.

Require:

- database inventory/export complete;
- uploads/plugins/themes inventory/export complete;
- package manifest complete;
- all chunk/file hashes reconciled;
- final package checksum present;
- source production remains read-only.

Do not proceed with a partial or checksum-invalid package.

### 3. Freeze the local clone destination plan

Use **Local clone destination plan**.

Set:

- target URL: `https://emmake.com/nuevaweb/`;
- target absolute path: the operator-discovered isolated `/nuevaweb/` filesystem path;
- target table prefix: the operator-selected isolated prefix.

Confirm the target is non-production.

Expected result:

- destination plan status Ready;
- same-origin/subdirectory topology recognized;
- target path differs from production;
- target table prefix differs from production;
- capacity guard passes;
- frozen destination-plan SHA-256 exists;
- zero target mutation has occurred.

### 4. Establish target ownership

Use **Local clone target ownership**.

Expected result:

- the job owns the isolated target;
- ownership marker is bound to the frozen destination plan;
- an unrelated pre-existing target is rejected;
- production files/tables remain untouched.

### 5. Copy the WordPress core runtime

Use the bounded local runtime bootstrap.

Expected result:

- only accepted WordPress core runtime paths are copied;
- no arbitrary production-root files are copied;
- the second traversal/fingerprint pass matches;
- client `wp-content` payload is not promoted in this step.

### 6. Bootstrap the sandbox control runtime

Use the local sandbox runtime bootstrap.

Require the isolated target to preserve:

- `SEO_GEO_MIGRATION_SANDBOX=true`;
- `SEO_GEO_MIGRATION_SANDBOX_MODE='subdirectory'`;
- `SEO_GEO_MIGRATION_STORAGE_ISOLATED=true`;
- `SEO_GEO_MIGRATION_OUTBOUND_SAFE=true`;
- `SEO_GEO_MIGRATION_BACKUPS_READY=true`;
- Migration Bridge control runtime;
- sandbox MU-plugin;
- `blog_public=0`.

Expected result: sandbox runtime Ready before client database/files are activated.

### 7. Build the private same-server package handoff

Use **Private same-server package handoff**.

Expected result:

- private ZIP complete;
- archive hash matches the frozen package authority;
- no public download URL is introduced;
- no target DB/client files are restored by this transport step.

### 8. Run target intake and sandbox preflight

Use **Target intake + sandbox preflight**.

Require:

- private archive identity valid;
- destination authority matches the frozen local plan;
- storage/noindex/outbound/backups guards pass;
- target table namespace is safe;
- no unexpected target client tables/uploads/themes exist;
- child import job is bound to the parent local-clone job.

A validated preflight is eligibility only; it is not restore permission until full payload verification completes.

### 9. Verify the full payload

Run local payload verification to completion.

Require:

- resumable extraction completes;
- package checksum is replayed exactly;
- fresh destination preflight remains valid;
- child import exposes `restore_allowed=true`;
- no target activation has occurred yet.

### 10. Restore database into isolated staging

Use **Transactional database staging restore**.

Require:

- only job-owned staging tables are created;
- staging namespace matches neither production nor the final target prefix;
- table/row counts reconcile;
- production tables remain unchanged;
- final target tables remain unactivated.

### 11. Restore files into private staging

Use **Private file staging restore**.

Require:

- uploads/plugins/themes are copied only to the private child workspace;
- per-file `files-meta` hashes/bytes reconcile;
- independent verification completes;
- client files are not yet promoted into `/nuevaweb/wp-content`.

### 12. Rewrite the staging environment

Use **Serialization-safe environment rewrite**.

Require:

- source URLs are rewritten only inside staging tables;
- PHP serialized and JSON values remain structurally valid;
- credential-like values remain opaque;
- the second verification pass reports zero rewritable source URLs;
- production `home` / `siteurl` remain unchanged.

### 13. Freeze the guarded finalization plan

Use **Guarded finalization plan**.

Require:

- database staging fingerprint present;
- file staging fingerprint present;
- deterministic activation-plan SHA-256 present;
- rollback table/file maps ready;
- target still unactivated;
- no candidate or rollback root has been mutated yet.

### 14. Activate the isolated target database

This is the first database activation step. Confirm only after all previous evidence is Ready.

Require:

- recovery journal frozen before mutation;
- only the isolated target table prefix is activated;
- target `home` / `siteurl` equal `https://emmake.com/nuevaweb/`;
- target `blog_public=0`;
- exact row/control verification passes;
- rollback remains available;
- production table namespace remains unchanged.

If any verification fails, stop and use the Engine rollback path. Never continue by converting a blocker into an advisory.

### 15. Promote isolated target files

Use the reversible local file promotion flow.

Require:

- uploads/plugins/themes candidates are built from verified private staging;
- candidate fingerprint equals the frozen file fingerprint;
- promotion occurs only inside the isolated target;
- target plugin/theme runtime is applied through target `wp_options`;
- active fingerprint equals the expected fingerprint;
- Migration Bridge control runtime remains present;
- file rollback remains available;
- production runtime/files remain unchanged.

### 16. Prepare the final local target handoff

Use **Final local target smoke / handoff reporting**.

This step is read-only.

Require the verified report to prove:

- activated target table count/row count are correct;
- target `home` / `siteurl` are correct;
- `blog_public=0`;
- target runtime matches expected plugin/theme state;
- Migration Bridge control files are present;
- active file fingerprint matches;
- source remains untouched;
- rollback remains available;
- blocker list is empty;
- deterministic final handoff `report_sha256` exists.

Do **not** execute rollback merely to finish 10E.2A.6. Rollback availability must remain preserved after the accepted handoff.

### 17. Run Sandbox Migration Lab

On the completed isolated clone, run the existing Sandbox Migration Lab/preflight.

Acceptance requires `ready=true` with:

- subdirectory sandbox mode;
- storage isolation;
- search visibility disabled;
- outbound safety;
- backups ready;
- destination Theme/dependency-review requirements satisfied for the next migration phase.

Record only a bounded reference to this evidence.

### 18. Check public discovery leakage

Verify all three fields in the acceptance record:

- `sandbox_canonical_leak_absent=true`;
- `sandbox_hreflang_leak_absent=true`;
- `sandbox_sitemap_leak_absent=true`.

The sandbox itself must remain noindex. Production canonical/hreflang/sitemap output must not advertise `/nuevaweb/` as a public target.

## Acceptance record

Update `release/emmake-real-clone-acceptance.json` only from observed evidence.

For `decision=accepted`, the CI gate requires:

- backup references + timestamp;
- clone job ID;
- final handoff report SHA-256;
- database/file independence;
- source untouched;
- `blog_public=0`;
- noindex/storage/outbound/backups/rollback guards;
- hashed target table-prefix identity;
- target runtime SHA-256;
- active file fingerprint;
- positive table/row/file/byte counts;
- Sandbox Migration Lab `ready=true` + bounded reference;
- all three public leak checks;
- zero blockers;
- evidence capture timestamp;
- operator identity.

Do not place the raw target table prefix itself in the acceptance record; store only its SHA-256.

## Decision boundary

10E.2A.6 accepts the **sandbox clone**, not the Theme production cutover.

After the record reaches `accepted` and passes CI:

1. keep production unchanged;
2. move to Theme migration/parity acceptance in the isolated sandbox;
3. keep `release/stable-release-decision.json` at `no-go` until the later real production acceptance is complete;
4. preserve the exact accepted clone evidence and rollback path.

The production Theme switch remains a separate explicit Phase 10D/10E cutover decision.
