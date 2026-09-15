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
- CI/diagnostic contract;
- initial repository skeleton;
- Foundation CI validating the documented contract;
- PR #1 merged after CI success;
- post-merge `main` verification passed.

## Phase 1 — Minimal installable packages

Status: **in progress — final microphase 1C**

### Microphase 1A — package skeleton and static contract

Status: **complete**

Completed:

#### Theme
- valid `style.css` metadata;
- `theme.json` v3;
- required `templates/index.html`;
- header/footer parts;
- basic page/single/archive/404 templates;
- semantic `<main>` landmarks so WordPress core can provide its block-template skip link;
- no unnecessary project-owned frontend strings in static templates.

#### Core plugin
- valid plugin bootstrap;
- minimal namespace autoloader;
- safe activation/deactivation behavior;
- language provider interface + native adapter;
- normalized LanguageManager facade;
- runtime integration detector interface/implementation;
- no SEO/Schema output yet.

#### Validation
- required package files;
- PHP syntax;
- `theme.json` v3 parse/contract;
- theme/plugin headers and text domains;
- semantic main landmark on every shipped template;
- structured diagnostic on contract failure;
- PR #2 and post-merge `main` Foundation/Package CI passed.

### Microphase 1B — real WordPress activation smoke

Status: **complete**

Tested baseline:

- WordPress 7.1.x;
- PHP 8.2+;
- WP-CLI 2.12.x for CI installation/activation;
- MariaDB 11.8.x for the disposable CI fixture.

Completed validation:

- theme/plugin copied into a clean WordPress fixture;
- WordPress installed through WP-CLI;
- plugin activated and verified active;
- theme activated and verified active;
- Core language service resolved `native`;
- clean SEO integration detector resolved `native`;
- frontend request returned rendered HTML;
- `/wp-admin/` resolved through the expected login flow;
- runtime/debug logs contained no PHP fatal/warning/notice or uncaught error;
- exact compatibility matrix documented in `docs/COMPATIBILITY.md`;
- first CI integration incident captured and resolved as `ERR-2026-001`.

Passing PR validation: WordPress Smoke CI run `34911640680`.
Passing post-merge `main` validation: WordPress Smoke CI run `34911791718`.

### Microphase 1C — WordPress coding standards and static analysis

Status: **in progress on PR #4**

Implemented:

- exact pinned PHP development toolchain in `composer.json`;
- WPCS `3.4.1` / PHPCS `3.13.6` gate;
- PHPStan `2.2.14` + `phpstan-wordpress` `2.0.4` at level 6;
- WordPress `7.1.0` stubs;
- project-only PHP scope;
- no PHPStan baseline or ignored-error file;
- structured diagnostics extracting first WPCS file/line/sniff or PHPStan file/line/identifier;
- quality workflow isolated from unrelated gates;
- dependency-resolution artifact for the generated Composer lock;
- Core API adjusted to WordPress snake_case conventions before public SEO behavior exists;
- runtime smoke updated and revalidated after the method-name changes.

Narrow documented WPCS exception:

- `WordPress.Files.FileName.InvalidClassFileName`;
- `WordPress.Files.FileName.NotHyphenatedLowercase`.

These two sniffs only conflict with the deliberate namespaced PSR-style class-path/autoload contract. No security, escaping, documentation or API naming rules are suppressed.

Observed initial adoption failure:

- WPCS correctly exposed reserved parameter naming, camelCase methods, missing PHPDoc and filename convention conflicts;
- the first structured diagnostic identified `seo-geo-core.php:27:30`, source `Universal.NamingConventions.NoReservedKeywordParameterNames.classFound`, signature `e85b79b82c63`;
- real code findings were fixed; only the two architectural filename sniffs were excluded.

Current required closure:

- clean WPCS rerun;
- clean PHPStan level 6 run;
- final PR #4 required gates green;
- merge;
- post-merge `main` verification green.

### Phase 1 exit criteria

- theme installs/activates;
- plugin installs/activates;
- no project PHP warnings/notices in supported fixture;
- WordPress Coding Standards gate passes;
- documented PHP static-analysis gate passes;
- documentation matches the tested compatibility matrix;
- all Phase 1 PRs pass required CI and post-merge `main` verification.

Phase 2 may not begin until every item above is complete.

## Phase 2 — Design system + performance baseline

Deliverables:

- design tokens in `theme.json`;
- typography/spacing/color primitives;
- system/local font policy implementation;
- core patterns: hero, CTA, services, trust, FAQ, author, contact;
- per-block stylesheet strategy;
- representative fixture pages;
- Lighthouse/performance baseline;
- lock initial numeric asset/performance budgets based on real fixtures.

Exit criteria:
- responsive/UX acceptance ES+EN;
- keyboard/focus/reduced-motion checks;
- baseline performance recorded and guarded.

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
- Website/WebPage/Organization/Person/ProfilePage/Article/BreadcrumbList;
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
