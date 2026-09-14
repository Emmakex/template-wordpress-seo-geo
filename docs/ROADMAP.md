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

Status: **in progress**

### Microphase 1A — package skeleton and static contract

Deliverables:

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
- structured diagnostic on contract failure.

### Microphase 1B — real WordPress activation smoke

Pending after 1A is green:

- install theme/plugin in a clean WordPress fixture;
- activate plugin;
- activate theme;
- request representative frontend/admin paths;
- assert no PHP fatal/warning introduced by project code;
- record exact supported WordPress/PHP test matrix.

### Phase 1 exit criteria

- theme installs/activates;
- plugin installs/activates;
- no project PHP warnings/notices in supported fixture;
- coding/static checks required by the supported matrix pass;
- documentation matches the tested compatibility matrix.

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
