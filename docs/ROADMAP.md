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

Historical note: Phase 1 validated the original two-package bootstrap. The product direction was later tightened in Phase 3 so the distributable baseline becomes a single self-contained theme with zero required plugins.

## Phase 2 — Design system + performance baseline

Status: **complete**

Phase 2 was deliberately split so visual work could not outrun accessibility/performance acceptance.

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

Status: **complete**

Delivered on PR #8:

- dedicated `Performance Baseline CI`;
- Lighthouse 13.4.1 against disposable WordPress 7.1 / PHP 8.2 fixtures;
- three samples per language with median aggregation;
- representative EN/ES pages reused from the proven Phase 2C content fixture;
- performance score, FCP/LCP/CLS/TBT/Speed Index measurement;
- HTML/CSS/JS/image/total transfer measurement;
- request, third-party-request, project-JS and DOM-size measurement;
- raw Lighthouse JSON retained as workflow evidence;
- structured failure diagnostics;
- authoritative machine-readable enforced budgets in `tests/performance/budgets.json`;
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

PR #8 passed all eight PR gates with budgets in `enforce` mode and was squash-merged as `d34f04b763eef372b0ef73c93f30cbfb3d722713`. Post-merge `main` passed all eight gates:

- Foundation CI `34926357735`;
- Phase 1 Package CI `34926357777`;
- Design System CI `34926357715`;
- PHP Quality CI `34926357745`;
- Pattern Contract CI `34926357760`;
- WordPress Smoke CI `34926357714`;
- Accessibility & Responsive CI `34926357742`;
- Performance Baseline CI `34926357784`.

The enforced base budgets remain the authoritative regression contract; field Core Web Vitals still require production field evidence and are not inferred from Lighthouse lab timings.

### Phase 2 exit criteria

All exit criteria are satisfied:

- reusable design tokens and core patterns are complete;
- responsive/UX acceptance passes in ES+EN;
- keyboard/focus/reduced-motion checks pass;
- performance baseline is recorded and guarded;
- numeric budgets are derived from representative pages rather than guessed thresholds;
- Phase 2D PR and post-merge verification are green;
- Phase 2 documentation reflects the final verified state.

## Phase 3 — Native SEO foundation + self-contained packaging

Status: **in progress**

Phase 3 now has one overriding product invariant: **a clean WordPress installation plus the built theme must provide the baseline SEO/GEO behavior with zero required plugins**.

### Microphase 3A — native SEO authority and core signals

Status: **complete**

Delivered on PR #10:

- server-authoritative `SeoOutputAuthority` for canonical, meta description and robots;
- explicit `IndexabilityResolver` with `indexable`, `noindex-follow`, `noindex-nofollow`, `redirect`, `not-found` and `410-gone` states;
- canonical resolution for native indexable HTML;
- native meta-description resolution with conservative source rules and a documented product length ceiling;
- page-level robots policy through WordPress's `wp_robots` filter rather than a second independent robots renderer;
- removal/replacement of WordPress Core's `rel_canonical` callback only while native Core owns canonical output;
- real WordPress smoke assertions for exactly one canonical, exactly one expected description and `noindex,follow` search behavior;
- no frontend JavaScript;
- public contract in `docs/NATIVE_SEO.md`;
- Foundation CI now requires the native SEO contract.

PR #10 passed its six required gates and was squash-merged as `405e2bc13ccc33dea6bcef99fe1342faa18b2fcb`. Post-merge `main` passed all six gates triggered by the change:

- Foundation CI `34929248429`;
- Phase 1 Package CI `34929248493`;
- PHP Quality CI `34929248627`;
- WordPress Smoke CI `34929248475`;
- Accessibility & Responsive CI `34929248621`;
- Performance Baseline CI `34929248399`.

Adoption findings were fixed in code rather than suppressed:

- WPCS `MetaDescriptionResolver.php:70`, source `Universal.Operators.DisallowShortTernary.Found`, signature `738b94d70c77`;
- PHPStan `CanonicalResolver.php:44`, identifier `function.alreadyNarrowedType`, signature `50c0653ad762`.

### Microphase 3B — self-contained native SEO/GEO runtime

Status: **complete**

Delivered on PR #13:

- context-neutral `SeoGeo\Core\Runtime` extracted from the transitional plugin wrapper;
- theme-owned runtime bootstrap under `inc/seo-geo-core/bootstrap.php`;
- single-theme build through `scripts/build-theme-package.sh`;
- reusable Core source embedded into the distributable theme under `inc/seo-geo-core/src`;
- standalone Core plugin wrapper demoted to optional compatibility/development packaging;
- dedicated `Self-contained Theme CI`;
- real WordPress 7.1 / PHP 8.2 acceptance with **zero active plugins**;
- explicit assertion that `wp-content/plugins/seo-geo-core` is absent;
- reflection assertion proving `SeoGeo\Core\Runtime` is loaded from the built theme bundle;
- native SEO authority preserved without an SEO plugin;
- exactly one canonical and one expected meta description on an indexable fixture;
- exactly one search robots meta containing `noindex`;
- clean PHP runtime/debug logs;
- Foundation contract hardened so the embedded bootstrap, Runtime, build script and zero-plugin workflow cannot silently disappear.

PR #12 explored Yoast/Rank Math/AIOSEO interoperability but was intentionally closed unmerged after the product direction was clarified. Third-party provider compatibility is optional and outside the baseline critical path.

PR #13 passed all nine PR gates and was squash-merged as `a320cd3033e2a5ea0fbcc83dffac500a7eaf8c88`. Post-merge `main` passed all nine gates:

- Accessibility & Responsive CI `35257573280`;
- Self-contained Theme CI `35257573577`;
- Design System CI `35257573373`;
- Foundation CI `35257573446`;
- WordPress Smoke CI `35257573636`;
- Pattern Contract CI `35257573645`;
- Performance Baseline CI `35257573410`;
- PHP Quality CI `35257573415`;
- Phase 1 Package CI `35257573523`.

The dedicated zero-plugin post-merge run `35257573577` proves the distribution invariant on real WordPress rather than inferring it from repository layout. The first adoption run exposed a CI-harness namespace-escaping defect, recorded as `ERR-2026-006`; the runtime itself did not require a product fix.

### Microphase 3C — native discovery metadata

Status: **pending**

Planned scope:

- native Open Graph baseline;
- breadcrumb data contract reusable by visible UI and later Schema;
- native title/social description policy;
- no Schema graph ownership yet;
- no third-party provider required for acceptance.

### Phase 3 exit criteria

- built distribution is one installable theme;
- zero active plugins are required in the baseline acceptance fixture;
- canonical/robots/meta-description output remains single-owner and deterministic;
- Open Graph baseline follows the native ownership model;
- breadcrumb data has one reusable contract;
- performance/accessibility budgets remain green after embedding the runtime;
- optional compatibility code is not required for the product to boot.

## Phase 4 — Native multilingual core

Deliverables:

- LanguageManager contract;
- native ES/EN language configuration;
- hreflang resolver;
- locale-aware metadata hooks;
- localized breadcrumbs/internal URL helpers;
- ES/EN native integration fixtures;
- optional WPML/Polylang adapters only after the native baseline is complete.

Exit criteria:

- reciprocal valid alternates in the native baseline;
- current-language canonical;
- correct HTML lang/OG locale/Schema language hooks;
- no cross-language navigation regressions;
- no multilingual plugin required for baseline acceptance.

## Phase 5 — Schema graph + entities

Deliverables:

- native graph builder;
- stable entity IDs;
- WebSite/WebPage/Organization/Person/ProfilePage/Article/BreadcrumbList;
- local business entity support;
- locale-aware graph fields;
- visible-content consistency invariants.

Exit criteria:

- valid parseable JSON-LD;
- deterministic node IDs;
- exactly one native graph owner in the baseline fixture;
- language tests pass;
- no Schema plugin required.

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
- crawler rules are explicit and documented without ranking guarantees;
- no GEO plugin required.

## Phase 7 — Presets

Implement in this order:

1. Corporate
2. Local Business
3. Publisher
4. Ecommerce

Each preset closes independently before the next begins.

## Phase 8 — Theme onboarding

Deliverables:

- theme-owned setup wizard/admin screen;
- preset choice;
- primary/additional language configuration;
- organization/entity basics;
- crawler/GEO opt-ins;
- generated setup report;
- optional detection of external systems only for compatibility warnings or enhancements.

The onboarding flow must not instruct users to install an SEO/GEO plugin to complete the baseline setup.

## Phase 9 — Distribution

Deliverables:

- reproducible **single-theme ZIP** build;
- embedded SEO/GEO runtime integrity check;
- versioning/changelog;
- clean-install and upgrade tests with zero required plugins;
- documentation for project cloning and per-client customization;
- production verification checklist;
- decision on deprecating/removing the transitional standalone Core plugin wrapper.

## Backlog rules

A feature only enters a phase when:

- it belongs to the product scope;
- its authoritative owner/module is known;
- multilingual impact is understood;
- SEO/performance/accessibility impact is known;
- acceptance can be tested;
- it does not turn an optional third-party plugin into a baseline dependency.
