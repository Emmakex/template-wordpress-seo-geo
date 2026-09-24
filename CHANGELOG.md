# Changelog

All notable changes to the installable self-contained SEO/GEO theme are recorded here.

## [Unreleased] — target 0.1.0

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

### Release policy

- `release/version.json` is the authoritative target version source.
- The WordPress `style.css` theme header must match that version exactly.
- This entry remains Unreleased until the stable-release decision in the final Phase 10 gate.
