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

Status: **complete**

Delivered:

- semantic color tokens;
- automated critical WCAG contrast pairs;
- system-only Sans/Serif font families with no remote font requests;
- stable + bounded fluid typography scale;
- spacing scale;
- border-radius scale;
- curated WordPress editor presets;
- global body/heading/link/button/caption styles via `theme.json`;
- dedicated `Design System CI`;
- `docs/DESIGN_SYSTEM.md`;
- structured PHP lint diagnostics for validator code.

`ERR-2026-002` captured and closed the first Design System validator syntax failure. The invalid foreach destructuring was fixed in code, and `scripts/ci/php-lint-diagnostic.sh` now ensures validator parse failures are structured instead of raw log output.

PR #5 was merged. Post-merge `main` SHA `01928349f8e1ffd19f22d8f098a22c400b3bc2f1` passed:

- Foundation CI run `34914619987`;
- Phase 1 Package CI run `34914619939`;
- Design System CI run `34914619985`;
- PHP Quality CI run `34914620034`;
- WordPress Smoke CI run `34914620027`.

### Microphase 2B — reusable core patterns

Status: **complete**

Delivered:

- native theme `/patterns` registration model;
- hero pattern;
- CTA pattern;
- services/features pattern;
- trust/proof pattern with no fabricated claims;
- FAQ pattern using native Details blocks and no Schema ownership;
- author/profile pattern;
- dependency-free contact pattern;
- translation-ready escaped project copy;
- no reusable H1 ownership;
- semantic design-token consumption;
- no embedded scripts/styles, remote dependencies or third-party blocks;
- `docs/PATTERNS.md`;
- dedicated structured `Pattern Contract CI`;
- real WordPress registry acceptance for all seven patterns.

PR #6 was merged as `80d5f316daf642b754fa9385d161c7bd571ce73d`. Post-merge `main` passed Foundation, Package, Design System, PHP Quality, Pattern Contract and WordPress Smoke; the runtime smoke confirmed **7/7 theme patterns registered**.

### Microphase 2C — responsive + accessibility acceptance

Status: **complete**

Delivered on PR #7:

- representative EN and ES WordPress fixture pages composed from registered theme patterns;
- locked Node 24 browser-test toolchain with Playwright 1.63.0 and axe 4.13.0 integration;
- Chromium acceptance at 320 × 800, 768 × 1024 and 1440 × 900;
- WCAG A/AA automated axe scan without baseline rule suppressions;
- one-banner/one-main/one-contentinfo and single-H1 assertions;
- heading hierarchy assertions;
- horizontal overflow/reflow assertions;
- keyboard reachability and visible `:focus-visible` assertions;
- skip-link activation with required main-target focus;
- reduced-motion assertion;
- WordPress runtime diagnostics;
- semantic native primary navigation instead of the invalid empty Navigation fallback observed in the fixture;
- server-side focusability for the main skip-link target without frontend JavaScript;
- `docs/ACCESSIBILITY_ACCEPTANCE.md`;
- dedicated `Accessibility & Responsive CI`.

PR #7 was squash-merged as `6c64528d58be3192ede8616256cbf43650eb15cb`. Post-merge `main` passed all seven established gates:

- Foundation CI `34919171184`;
- Phase 1 Package CI `34919171310`;
- Design System CI `34919171290`;
- PHP Quality CI `34919171239`;
- Pattern Contract CI `34919171215`;
- WordPress Smoke CI `34919171238`;
- Accessibility & Responsive CI `34919171224`.

The browser gate retained **24/24 passing EN/ES acceptance cases**. Adoption findings with reusable lessons are recorded as `ERR-2026-003`, `ERR-2026-004` and `ERR-2026-005`.

### Microphase 2D — performance baseline and budgets

Status: **in progress on PR #8 (`feature/phase-2d-performance-baseline`)**

Implemented:

- dedicated `Performance Baseline CI`;
- Lighthouse 13.4.1 against disposable WordPress 7.1 / PHP 8.2 fixtures;
- three samples per language with median aggregation;
- representative EN/ES pages reused from the proven Phase 2C content fixture;
- performance score, FCP/LCP/CLS/TBT/Speed Index measurement;
- HTML/CSS/JS/image/total transfer measurement;
- request, third-party-request, project-JS and DOM-size measurement;
- raw Lighthouse JSON retained as workflow evidence;
- structured failure diagnostics;
- authoritative machine-readable budgets in `tests/performance/budgets.json`;
- measured baseline and rationale in `docs/PERFORMANCE_BASELINE.md`.

Reference observation run `34925805108` measured:

- Lighthouse performance: 100 EN / 100 ES;
- LCP median: 653.46 ms EN / 664.39 ms ES;
- CLS: 0 / 0;
- TBT: 0 ms / 0 ms;
- total transfer: 20,063 B / 20,274 B;
- requests: 5 / 5;
- third-party requests: 0 / 0;
- project-owned frontend JS: 0 B / 0 B;
- DOM nodes: 88 / 88.

Budgets are now set to `enforce`. Required closure:

- enforced budgets pass on a fresh PR run;
- Phase 2D documentation matches the accepted measurement contract;
- PR #8 squash merge;
- relevant post-merge `main` checks pass.

### Phase 2 exit criteria

- reusable design tokens and core patterns complete;
- responsive/UX acceptance passes in ES+EN;
- keyboard/focus/reduced-motion checks pass;
- performance baseline is recorded and guarded;
- numeric budgets reflect representative pages rather than guessed thresholds;
- Phase 2D merge and post-merge verification are green.

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
