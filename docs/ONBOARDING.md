# Theme onboarding

Phase 9 makes onboarding a theme-owned capability for both clean installations and sites that completed Phase 8 migration.

## Phase 9A — Setup foundation and migration handoff

Status: **complete**

9A is deliberately read-only. It builds a setup plan but does not persist setup choices, create pages, install/activate/deactivate plugins, store external credentials or load Migration Bridge code.

The public setup-plan entrypoint is:

```php
$plan = seo_geo_theme_setup_plan();
```

The plan contains:

- schema version and `theme-setup-plan-read-only` mode;
- clean vs migrated site mode;
- the versioned future setup option contract `seo_geo_theme_setup_v1`;
- all five allowlisted presets and their suggested site-identity metadata;
- current native language configuration from `NativeLanguageConfiguration`;
- accepted Phase 8 handoff metadata from `seo_geo_migration_report_v1` when present;
- current native/external SEO and language provider detection;
- advisory WooCommerce/Elementor/Divi compatibility warnings when active;
- one next-step key for Phase 9B;
- explicit safety flags proving that planning performs no setup mutation.

### Migration handoff

The theme reads `seo_geo_migration_report_v1` directly. It does not load or call Migration Bridge classes.

A handoff is accepted only when:

- envelope schema is version 1;
- report schema is version 1;
- mode is `migration-report`;
- `ready_for_handoff=true`;
- the report explicitly declares `report_is_runtime_dependency=false`;
- envelope and report fingerprints are valid SHA-256 strings.

Only bounded handoff metadata is exposed to onboarding: identifiers/fingerprints, review counts and final bridge disposition.

### Presets and authorities

9A reuses the existing five-preset registry:

- `corporate`;
- `local-business`;
- `publisher`;
- `ecommerce`;
- `saas-digital-product`.

It also reuses the native language and runtime integration authorities. No parallel SEO, Schema, language or integration stack is created.

### Acceptance

Self-contained Theme acceptance runs with zero active plugins and proves both paths:

1. clean install → five-preset native setup plan;
2. synthetic accepted Phase 8 handoff → migrated-site setup plan while Migration Bridge classes are absent.

Protected setup state is fingerprinted before/after planning and must remain unchanged.


### 9A acceptance evidence

Final candidate `f89514cc7051da8917fece779d26a8331cd41a72` passed:

- Foundation CI `35883921399`;
- Phase 1 Package CI `35883921389`;
- PHP Quality CI `35883921378` — WPCS + PHPStan level 6;
- WordPress Smoke CI `35883921384`;
- Accessibility & Responsive CI `35883921391`;
- Performance Baseline CI `35883921405`;
- Self-contained Theme CI `35883921418`;
- Native Multilingual CI `35883921401`.

PR #86 was squash-merged as `17d4cc185addce336a3f2f86079b03dbf00a3468`.

Post-merge `main` repeated all eight gates successfully: Foundation `35884423588`, Package `35884423936`, PHP Quality `35884423746`, WordPress Smoke `35884423606`, Accessibility/Responsive `35884423721`, Performance `35884423669`, Self-contained Theme `35884423758` and Native Multilingual `35884423933`.

The zero-plugin acceptance proved clean and migrated site modes, five-preset availability, direct migration handoff consumption without Migration Bridge runtime, and unchanged protected setup state.

## Phase 9B — Preset and language configuration

Status: **active**

9B adds explicit, validated operator choices on top of the read-only 9A plan. It will reuse the existing preset registry and native language authority rather than introducing parallel configuration stores.
