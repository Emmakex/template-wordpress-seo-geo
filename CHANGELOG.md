# Changelog

All notable changes to the installable self-contained SEO/GEO theme are recorded here.

## [Unreleased] — target 0.1.0

- Migration Bridge 0.8.58 completes the **Corporate reset-first page pipeline and whole-site readiness**: reusable Services/Work/About/Contact Blueprint→hydration→SEO→readiness flow, evidence/provenance/contact safety gates, native dynamic Insights index, reversible assignment, and a final machine gate across Home + all inner surfaces before browser QA.

- Corporate Services now has its own **`corporate-services-v1` semantic content contract**: EN/ES references, required service/process/CTA slots, proof gated by explicit verification, and an optional native FAQ with stable question/answer slots and filler-content prohibition.

- Migration Bridge 0.8.57 adds **Clean Corporate inner-page rebuild v1**: explicit rescued-source mapping, immutable page/source binding, URL/content preservation and a reusable native scaffold for Services first, then Work/About/Contact; Home remains on its dedicated pipeline and Insights remains blocked until its dynamic composition is defined.

- Added **Deterministic EMMAKE Home Field Pilot Pack** tooling: one reproducible ZIP containing Theme, Migration Bridge 0.8.56, the accepted Emmake blueprint, a `/nuevaweb/` runbook, component SHA-256 manifest and browser-QA evidence template; automatic production cutover remains forbidden.

- Migration Bridge 0.8.56 adds **Home Field Pilot Readiness v1**: a read-only sandbox preflight that requires reset/content/source/front-page/SEO integrity, rejects legacy builder debris and preset placeholders, and distinguishes machine readiness from the remaining browser QA before cutover.

- Migration Bridge 0.8.55 adds **Native Home SEO/GEO handoff v1** and Core per-resource SEO metadata: safe rescued Yoast/Rank Math title/description/indexability carryover, native self-canonical preservation, explicit custom-canonical/template review findings, idempotent handoff reporting, and permanent provider-neutral SEO ownership after Bridge removal.

- Migration Bridge 0.8.54 adds **Portable Home Content Blueprint v1**: portable locale/model-bound semantic content without runtime IDs or plan hashes, strict unknown-slot/group rejection, deterministic blueprint fingerprinting, import through the existing Content Kit validation, and the first real Emmake `es_ES` Home blueprint with all unverified evidence groups disabled.

- Migration Bridge 0.8.53 adds **Corporate Home Content Kit + Native Hydrator v1**: typed semantic content bound to `corporate-home-v1`, explicit evidence verification groups, deterministic block-slot hydration, omission of unverified proof/case-study sections, draft-drift detection, idempotent replay and exact scaffold rollback.

- Corporate Home adds **native content contract v1**: stable semantic slot IDs across EN/ES patterns and the base CTA, typed required/optional fields, explicit verification groups for proof/case-study material, and `omit-unless-verified` policy so future hydration/Manager workflows never depend on placeholder strings or legacy block positions.

- Migration Bridge 0.8.52 adds **Clean Corporate Home rebuild v1**: a private, idempotent Home draft composed only from Theme-owned Corporate patterns. It uses Rescue Manifest only to bind the existing front-page identity/URL and verify source integrity; legacy layout, Divi/Elementor markup and mandatory Content Remap are excluded.

- Migration Bridge 0.8.51 adds **Theme + Corporate preset bootstrap** for reset-first rebuilds: a completed Clone Reset is recognized by the Theme as a clean handoff, stale migration reports no longer re-block setup, Corporate configuration is applied only through the Theme-owned SetupExecutor, Organization identity requires explicit administrator confirmation, GEO discovery opt-ins remain conservative by default, and Clone Reset now blocks unless the installed target Theme contains its embedded Core runtime.

- Migration Bridge 0.8.50 adds **Clone Reset Engine v1**: after a Rescue Manifest exists, the clone switches to the SEO/GEO Theme, removes every non-kept plugin and non-target theme, clears Divi/Elementor generated presentation caches, widget assignments, rewrite rules and object cache, while proving rescued post content and the manifest remain unchanged. Migration Bridge retains itself until handoff; business plugins survive only when explicitly kept.

- Migration Bridge 0.8.49 introduces **Reset & Rebuild / Rescue Manifest v1**: a dedicated full-redesign path that captures pages/posts, URL identity, content fingerprints, useful SEO metadata, links and media without requiring UNKNOWN dependency review or mutating content/theme/plugin state. The existing Migration Bridge screen links directly to this reset-first path.

- Product direction corrected to a **reset-first rebuild** for full redesigns: once a usable clone exists, Migration Bridge creates a minimal Rescue Manifest, legacy theme/builder/plugin baggage is removed, Theme + preset rebuild from clean native components, and SEO/GEO Manager becomes the permanent post-launch optimization/content layer.

- Migration Bridge 0.8.48 packages the accepted Native Replatform review flow for sandbox use: draft-only Composer, provenance-bound Content Remap, Reviewed Apply with source/slot drift rejection, reversible private-draft review, direct Preview and privacy-bounded acceptance evidence. Existing 0.8.47 clone/handoff state remains resumable and production source pages remain untouched.

- Native Replatform Reviewed Apply v1 adds explicit Corporate semantic slots, candidate-only draft mutation, mandatory verification for evidence-sensitive sections, source/slot drift rejection, reversible private-draft preview rollback, direct draft preview, privacy-bounded acceptance evidence, selected/unmapped asset evidence and idempotent replay while preserving the public source page.

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
