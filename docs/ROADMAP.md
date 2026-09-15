# Roadmap

The roadmap follows the project rule **finish before advancing**. A phase closes only when implementation, required quality gates, acceptance criteria, blockers and documentation are complete.

## Phase 0 — Foundation and contracts

Status: **complete**

Completed:

- repository bootstrap;
- product vision;
- architecture boundaries;
- SEO/GEO specification;
- multilingual contract;
- performance contract;
- accessibility contract;
- preset model;
- engineering rules;
- structured CI/diagnostic contract;
- initial repository skeleton;
- Foundation CI;
- PR #1 + post-merge `main` verification.

## Phase 1 — Minimal installable packages

Status: **complete**

### Microphase 1A — package skeleton and static contract

Status: **complete**

Delivered:

- installable block-theme skeleton;
- valid `theme.json` v3 and theme metadata;
- index/page/single/archive/404 block templates;
- semantic `<main>` landmarks;
- Core plugin bootstrap and namespace autoloader;
- safe activation/deactivation behavior;
- language provider interface/native adapter/LanguageManager;
- runtime integration detector;
- structured Phase 1 package contract.

PR #2 and post-merge package/foundation validation passed.

### Microphase 1B — real WordPress activation smoke

Status: **complete**

Tested baseline:

- WordPress 7.1.x;
- PHP 8.2+;
- WP-CLI 2.12.x;
- MariaDB 11.8.x.

Validated:

- clean WordPress install;
- plugin/theme activation and active state;
- native language + SEO provider service resolution;
- frontend and `/wp-admin/` HTTP paths;
- no project PHP fatal/warning/notice or uncaught runtime error;
- disposable fixture cleanup;
- compatibility matrix in `docs/COMPATIBILITY.md`.

`ERR-2026-001` captured the first WP-CLI Docker integration failure and its regression guard.

### Microphase 1C — WordPress coding standards and static analysis

Status: **complete**

Delivered:

- exact pinned PHP quality dependencies + committed `composer.lock`;
- WPCS 3.4.1 / PHPCS 3.13.6;
- PHPStan 2.2.14 + `phpstan-wordpress` 2.0.4 at level 6;
- WordPress 7.1 stubs;
- project-owned PHP scope;
- no PHPStan baseline or ignored-error list;
- structured first-error extraction for WPCS/PHPStan;
- WordPress snake_case Core API;
- only two documented WPCS filename-convention exceptions required by the namespaced PSR-style class-path contract.

Adoption findings closed:

- WPCS first diagnostic: `seo-geo-core.php:27:30`, source `Universal.NamingConventions.NoReservedKeywordParameterNames.classFound`, signature `e85b79b82c63`;
- PHPStan first diagnostic: `LanguageManager.php:77`, identifier `nullCoalesce.offset`, signature `def4f1ee7592`;
- both were fixed in code rather than suppressed.

PR #4 validation passed and was merged. Post-merge `main` SHA `fff40e24d93a3036f159ef1b7062338c1bc78805` passed:

- Foundation CI run `34913626730`;
- Phase 1 Package CI run `34913626714`;
- WordPress Smoke CI run `34913626741`;
- PHP Quality CI run `34913626770`.

### Phase 1 exit criteria

All exit criteria are satisfied:

- theme installs/activates;
- plugin installs/activates;
- supported runtime is clean;
- WPCS passes;
- PHPStan level 6 passes;
- compatibility documentation matches CI;
- required PR and post-merge validation is green.

## Phase 2 — Design system + performance baseline

Status: **in progress**

Phase 2 is deliberately split so visual work cannot outrun accessibility/performance acceptance.

### Microphase 2A — semantic design system

Status: **in progress on `feature/phase-2a-design-system`**

Scope:

- semantic color tokens;
- automated critical contrast pairs;
- system-only font families;
- stable + bounded fluid typography scale;
- spacing scale;
- border-radius scale;
- curated WordPress editor presets;
- global body/heading/link/button/caption styles via `theme.json`;
- zero remote font requests;
- design-system documentation;
- dedicated structured Design System CI.

Exit criteria:

- WordPress accepts the updated theme in the real runtime smoke;
- Design System CI validates token sets and contrast;
- Package/Foundation/PHP Quality gates affected by the change remain green;
- PR merge + post-merge `main` verification.

### Microphase 2B — reusable core patterns

Pending after 2A closes.

Planned patterns:

- hero;
- CTA;
- services/features;
- trust/proof;
- FAQ;
- author/profile;
- contact.

Patterns must consume semantic tokens rather than duplicate raw design values.

### Microphase 2C — responsive + accessibility acceptance

Pending after 2B closes.

Scope:

- representative ES/EN fixture pages;
- keyboard interaction;
- visible focus;
- reduced-motion contract;
- heading/landmark checks;
- responsive overflow/reflow;
- automated accessibility scan plus manual-contract assertions where automation is insufficient.

### Microphase 2D — performance baseline and budgets

Pending after 2C closes.

Scope:

- Lighthouse representative fixtures;
- CSS/JS/HTML size measurements;
- no unnecessary third-party requests;
- lock initial numeric performance budgets from real fixture evidence;
- regression gate for future phases.

### Phase 2 exit criteria

- reusable design tokens and core patterns complete;
- responsive/UX acceptance passes in ES+EN;
- keyboard/focus/reduced-motion checks pass;
- performance baseline is recorded and guarded;
- numeric budgets reflect representative pages rather than guessed thresholds.

## Phase 3 — Native SEO foundation

Deliverables:

- indexability resolver;
- canonical resolver;
- meta description;
- robots meta;
- Open Graph baseline;
- breadcrumb data;
- single-provider ownership model;
- Yoast/Rank Math/AIOSEO detection/adapters as scoped;
- tests for duplicate-output prevention.

Exit criteria:

- one canonical/robots owner per fixture;
- no duplicate output with supported SEO integrations;
- localized canonical behavior tested.

## Phase 4 — Multilingual core

Deliverables:

- LanguageManager contract;
- native single-language adapter;
- WPML adapter;
- Polylang adapter;
- hreflang resolver;
- locale-aware metadata hooks;
- localized breadcrumbs/internal URL helpers;
- ES/EN integration fixtures.

Exit criteria:

- reciprocal valid alternates;
- current-language canonical;
- correct HTML lang/OG locale/Schema language hooks;
- no cross-language navigation regressions.

## Phase 5 — Schema graph + entities

Deliverables:

- graph builder;
- stable entity IDs;
- WebSite/WebPage/Organization/Person/ProfilePage/Article/BreadcrumbList;
- local business entity support;
- locale-aware graph fields;
- visible-content consistency invariants.

Exit criteria:

- valid parseable JSON-LD;
- deterministic node IDs;
- no duplicate overlapping graph with supported providers;
- language tests pass.

## Phase 6 — GEO / agent-friendly layer

Deliverables:

- crawler policy UI/config;
- OAI-SearchBot vs GPTBot controls kept distinct;
- optional `llms.txt` generator;
- optional localized Markdown alternates;
- provenance/author/source patterns;
- private/draft content leakage tests;
- cache/invalidation strategy.

Exit criteria:

- optional features can be disabled cleanly;
- no SEO canonical/indexability conflicts;
- multilingual alternates correct;
- crawler rules are explicit and documented without ranking guarantees.

## Phase 7 — Presets

Implement in this order:

1. Corporate
2. Local Business
3. Publisher
4. Ecommerce

Each preset closes independently before the next begins.

## Phase 8 — Installer/onboarding

Deliverables:

- setup wizard;
- preset choice;
- primary/additional language configuration;
- WPML/Polylang detection;
- organization/entity basics;
- SEO-provider choice/detection;
- crawler/GEO opt-ins;
- generated setup report.

## Phase 9 — Distribution

Deliverables:

- reproducible theme/plugin ZIP builds;
- versioning/changelog;
- install/upgrade tests;
- documentation for project cloning and per-client customization;
- production verification checklist.

## Backlog rules

A feature only enters a phase when:

- it belongs to the product scope;
- its authoritative owner/module is known;
- multilingual impact is understood;
- SEO/performance/accessibility impact is known;
- acceptance can be tested.
