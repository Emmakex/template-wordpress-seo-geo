# Changelog

All notable changes to the installable self-contained SEO/GEO theme are recorded here.

## [Unreleased] — target 0.1.0

- Native Replatform Content Remap Intelligence v1 extracts provenance-bound source text/link/media assets, proposes preset semantic-slot candidates and forces evidence-sensitive mappings through manual review without generating factual copy.

- Field milestone (2026-10-03): Migration Bridge 1.0.8 completed a real `emmake.com` → `/nuevaweb/` clone after persistent 10-step packaging, 48 MiB multipart transport, verified reconstruction and safe destination activation. This proves the clone transport/activation path in the pilot environment; Theme sandbox migration/parity and production acceptance remain separate pending gates.


- Migration Bridge 0.8.47 makes the large private same-server handoff observable and practical: it exposes saved file/byte/cursor progress, adds a progress bar, and adds bounded automatic continuation (500 files / 32 MB per request) with stall and cycle-limit guards. Existing 0.8.46 handoff state resumes in place; no package rebuild or export restart is required.

- Migration Bridge 0.8.46 carries the already accepted live-source drift authority from package integrity into local-clone destination planning, target ownership and core-runtime bootstrap. Exact inventory identity remains accepted, while a differing exported fingerprint is allowed only when the completed file-export state proves the same recorded drift snapshot and manifest.

- Migration Bridge 0.8.45 fixes package-integrity validation for legitimate exported `index.php` and `.htaccess` files by checking per-file export evidence before generated workspace guards; a retained `package-exported-payload-mismatch` can be safely retried from its saved cursor and genuine tampering still blocks.

- Migration Bridge 0.8.44 finalizes fully traversed, exact-file-count snapshots even when a live source changed byte totals during export; byte drift is recorded and the private workspace is subsequently verified by package integrity.

- Migration Bridge 0.8.43 restores the finalization action for completed legacy drift-blocked exports even when normalized blocker metadata is missing, using the terminal last-action code plus exact file/byte totals.

- Migration Bridge 0.8.42 adds explicit saved-job selection so retained Portable Clone progress can be reopened instead of being hidden by a newer job.

### Added

- Self-contained WordPress block theme with native technical SEO/GEO runtime and zero required plugins.
- Five reusable presets: Corporate, Local Business, Publisher, Ecommerce and SaaS / Digital Product.
- Native EN/ES language baseline, routing, translation relationships and localized SEO.
- Theme-owned onboarding wizard with atomic setup application, rollback, idempotent re-Apply and privacy-bounded reporting.
- Existing-site Migration Bridge workflow with sandbox migration, parity gating, reversible cutover and bounded handoff.
- Deterministic single-theme release ZIP with embedded runtime integrity manifest and SHA-256 checksum.

### Fixed

- Clean-install onboarding now accepts the absence of migration handoff metadata.
- Browser acceptance now validates the final self-contained zero-plugin distribution shape instead of relying on the transitional standalone Core plugin.
- Migration Bridge 0.8.39 fixes one-click automatic file export continuation by avoiding the WordPress submit-button name collision and using browser-safe resubmission.
- Migration Bridge 0.8.40 finalizes a fully copied, per-file hash-verified snapshot when only the aggregate live-source fingerprint drifted, keeps count/byte drift terminal, and can recover an already-blocked 0.8.39 export without recopying the payload.
- Migration Bridge 0.8.41 carries that explicitly accepted drift snapshot into package integrity, binding the package to the exported snapshot fingerprint while retaining the original inventory fingerprint as provenance.

### Release policy

- `release/version.json` is the authoritative target version source.
- The WordPress `style.css` theme header must match that version exactly.
- This entry remains Unreleased until the stable-release decision in the final Phase 10 gate.
- Current Phase 10E decision is **NO-GO**: `0.1.0` remains `prestable` until a selected real site completes sandbox + production acceptance.
