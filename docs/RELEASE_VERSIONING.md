# Release versioning and upgrade contract

Phase 10B adds version governance and upgrade/rollback acceptance on top of the deterministic Phase 10A artifact.

## Version source of truth

`release/version.json` is authoritative for the target theme version.

Required fields:

- `schema_version`;
- `theme_slug`;
- `version` as SemVer;
- `release_channel` as `prestable` or `stable`.

The WordPress theme header in `packages/seo-geo-theme/style.css` must expose the exact same `Version`.

The release builder refuses to create a ZIP when those values diverge. The generated `release-integrity.json` records the theme slug, target version and release channel.

## Changelog contract

`CHANGELOG.md` must contain one active target heading:

`## [Unreleased] — target <version>`

The entry remains Unreleased until the final stable-release decision. A version bump must update `release/version.json`, the WordPress theme header and the active changelog target in the same change.

## Upgrade acceptance

`scripts/ci/release-upgrade-acceptance.sh` validates the minimum supported production baseline:

- WordPress 7.1;
- PHP 8.2;
- zero active plugins.

The acceptance creates a synthetic previous-version fixture from the current self-contained runtime. This fixture is used only to test WordPress overwrite/rollback mechanics; it is not published as a historical release.

The scenario:

1. installs and activates the previous fixture;
2. applies a real Corporate EN/ES setup;
3. creates published sentinel content;
4. upgrades the active theme to the current deterministic release ZIP;
5. verifies the current WordPress theme header and release-integrity version;
6. proves setup/report/content are unchanged;
7. proves the native runtime still loads from the theme;
8. proves no plugin was activated;
9. re-applies the same setup and requires an idempotent no-op;
10. rolls back to the previous fixture and repeats the preservation/idempotency checks.

## Release boundary

Phase 10B does not publish a GitHub Release and does not declare the project stable. Its purpose is to ensure that a versioned candidate can be installed, upgraded and rolled back safely before client deployment documentation and production acceptance.

## 10B acceptance evidence

Final candidate `cf63aaeae0e2bdb6ad02e3db78fdce8e2cb3dafb` passed Foundation CI `35944383852` and Release Artifact CI `35944383820`.

PR #105 was squash-merged as `994138dfc7a7ff3a07aea07f6c951a159a639540`.

Post-merge `main` repeated Foundation CI `35944485076` and Release Artifact CI `35944485045`. The deterministic ZIP reproduced SHA-256 `dae8da490526fd3584387324bc1bc596d17ad5e681513927c0468b48e786ebba`.

The upgrade/rollback acceptance proved WordPress 7.1 / PHP 8.2 compatibility, zero active plugins, preserved setup/report/content and idempotent setup across the synthetic `0.0.9 → 0.1.0 → 0.0.9` lifecycle.
