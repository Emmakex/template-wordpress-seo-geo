# Current execution roadmap

## Authority

This document is the **current execution pointer** for work after the EMMAKE Corporate real-site findings.

`docs/ROADMAP.md` remains the historical/global phase record. Where an older roadmap item assumes Gutenberg/native blocks are the canonical strategic-page renderer, this current roadmap plus `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md` supersede that rendering assumption.

The project rule remains **finish before advancing**.

## Current product state

- Migration/Reset/Rescue/Hydration/SEO-GEO technical path: accepted through the current EMMAKE sandbox evidence.
- Corporate v4.x: retained as historical technical evidence; visual/architectural direction superseded by v5.
- Corporate v5 A1 — renderer/model boundary: **complete**.
- Corporate v5 A2 — clean Theme-owned strategic Home/chrome: **complete as architecture/implementation baseline**.
- Corporate v5 A3 — lighter real-site visual refinement: **current execution pointer**.
- Stable Theme promotion: **NO-GO** pending exact `/nuevaweb/` browser/product acceptance.
- SaaS / Local Pro / Publisher / Ecommerce master-surface rollout: frozen until Corporate v5 passes the real-site premium/WOW gate and its renderer contract is generalized.
- SEO/GEO Manager implementation: planned after the Theme renderer/model boundary is generalized enough to consume safely; architecture and publishing contracts are already documented.

## Architectural ownership

Permanent rule:

> **Gutenberg provides editorial autonomy. SEO/GEO Manager provides automation and growth. SEO/GEO Theme provides frontend rendering, design, semantic HTML and performance.**

WordPress remains the CMS/resource authority.

## Track A — Corporate v5 Theme-owned frontend

### A1 — Renderer/model boundary

Status: **complete**.

Delivered:

- Theme-side semantic model registry/reader;
- `corporate-home-v1` renderer contract;
- server-side strategic renderer independent from Gutenberg master layout;
- WordPress resource identity preserved;
- existing hydrated Home content reused;
- no Reset/regeneration/rehydration required merely to change renderer architecture.

Accepted boundary:

- no strategic dependency on Gutenberg `contentSize`, `wideSize`, `is-layout-constrained` or `wp-block-post-content`;
- one coherent master shell;
- semantic headings and links preserved;
- existing SEO/GEO output path remains valid;
- zero required Manager dependency.

### A2 — Corporate v5 Home implementation

Status: **complete as structural/architectural baseline; real-site visual result superseded by A3 refinement**.

Delivered:

- premium Corporate renderer using the accepted semantic content;
- Theme-owned strategic header, Home and footer;
- hero, capabilities, process, Insights and final CTA as Theme-owned components;
- article/query references resolved through WordPress data rather than visual block-position contracts;
- removal of legacy Corporate v4 presentation debt from v5 client delivery;
- responsive behavior at `1440 / 1024 / 768 / 390`, with `320` as automated stress case;
- accessibility and performance budgets preserved as repository gates.

Real `/nuevaweb/` QA validated the architecture but showed the palette remained too dark and some secondary surfaces lacked separation. That field finding created A3 rather than reopening the renderer boundary.

### A3 — Corporate v5 lighter field refinement

Status: **current**.

Scope is presentation only:

- lighter teal hero and primary feature surfaces;
- improved secondary text/card separation;
- light mint/white Method section;
- light final CTA with dark ink and strong green action;
- lighter high-contrast featured cards;
- branded teal footer with stronger hierarchy and pill navigation;
- matching mobile-menu palette;
- no content, URL, SEO/GEO, Reset, hydration, Migration Bridge or production change.

Acceptance:

- exact deterministic candidate identity frozen;
- repository gates green on that exact candidate;
- install only the frozen Theme on `/nuevaweb/`;
- preserve existing Step 1–7 state and hydrated Home;
- real QA at `1440 / 1024 / 768 / 390`;
- explicit premium/WOW visual approval;
- no SEO/GEO, accessibility or performance regression.

Do not begin renderer generalization until this field gate is closed.

### A4 — Generalize renderer contract

Only after Corporate v5 A3 passes real-site QA:

- extract reusable semantic renderer interfaces/helpers;
- define model-version compatibility behavior;
- define missing/optional/evidence-sensitive slot behavior;
- define renderer fallback/error behavior;
- keep public HTML server-rendered and local to WordPress;
- make the contract suitable for future SEO/GEO Manager structured landing models without coupling Manager to Theme layout internals.

### A5 — Remaining preset master surfaces

Move master strategic surfaces to the same architecture in this order:

1. SaaS / Digital Product;
2. Local Pro;
3. Publisher / Editorial;
4. Ecommerce.

Each preset closes independently before the next.

Gutenberg/editorial body usage may differ by preset, but it does not become master layout authority for strategic surfaces.

## Track B — SEO/GEO Manager publishing foundation

Starts after the Theme semantic renderer contract is proven and generalized enough to consume safely.

### B1 — Manager package + independent release boundary

- own plugin package/version/changelog/ZIP;
- Theme-independent activation;
- collision-safe shared Core packaging;
- EN/ES operator foundation;
- no remote dependency for public rendering.

### B2 — Site Intelligence + Output Authority Resolver

- analyze supported WordPress stacks;
- detect Theme/builders/providers/business systems;
- resolve one owner per SEO/GEO signal;
- block ambiguous ownership;
- no destructive mutation while analyzing.

### B3 — Content Publishing Core

- versioned publication manifest;
- idempotency;
- dry-run/diff;
- draft-first;
- preview;
- explicit/policy-authorized schedule/publish;
- expected-revision protection;
- bounded change set;
- rollback;
- public verification.

## Track C — SEO/GEO Manager Landing Engine

### C1 — Structured strategic-page generation

Manager creates model data, not Theme-specific Gutenberg layout trees.

Initial capabilities:

- service/solution landing;
- product landing;
- location/GEO landing;
- campaign landing;
- commercial hub/cluster page;
- supported Home variants when explicitly allowed.

Required guards:

- declared intent;
- unique value;
- no doorway/token-swap batches;
- no fabricated business facts/evidence;
- URL/intent collision protection;
- valid internal links;
- authority-aware SEO/GEO intent.

### C2 — Theme renderer integration

With SEO/GEO Theme active:

```text
Manager structured model
        ↓
normal WordPress resource identity
        ↓
Theme semantic renderer
        ↓
public HTML
```

Manager never needs the renderer's CSS grid or Gutenberg block nesting.

For non-Theme sites, accepted adapters may provide alternate publication paths without redefining the canonical Theme path.

## Track D — SEO/GEO Manager Automated Blog Engine

Automated blog creation is a **core Manager capability**.

### D1 — Structured article generation

Support:

- topic/keyword/search intent;
- cluster relationship;
- title/excerpt;
- outline/headings/body;
- source/reference provenance;
- author binding;
- categories/tags under policy;
- internal links;
- related landing/service/product links;
- media references;
- SEO metadata intent;
- Article/BlogPosting intent;
- locale/translation relationships.

### D2 — Normal WordPress post materialization

Manager-created articles become normal WordPress posts.

They remain editable by authorized users in Gutenberg.

The editable body uses a minimal stable editorial representation; it does not encode the Theme's premium page layout.

Theme owns the public article shell:

- reading width;
- typography;
- header/hero;
- author/date/provenance;
- related content;
- CTAs;
- responsive behavior;
- accessibility;
- Schema/public metadata integration;
- performance.

### D3 — Automated publication modes

Support progressively:

1. draft-only;
2. draft + explicit approval;
3. approved scheduling;
4. future policy-authorized direct publish with audit/rollback.

Draft-first must always remain available.

### D4 — Blog refresh/update

Support:

- stale-content refresh;
- search-opportunity refresh;
- new source/fact updates;
- internal-link improvement;
- cluster expansion;
- expected-revision protection so automation cannot silently overwrite newer human edits;
- rollback and public verification.

## Track E — Growth and feedback loops

After Landing + Blog engines are stable:

- Search Console integration;
- Bing integration;
- analytics integration;
- ranking/impression/click opportunity detection;
- stale content detection;
- orphan/internal-link analysis;
- new landing suggestion;
- new blog topic suggestion;
- refresh proposals;
- GEO/local opportunity proposals.

Observed data guides proposals; it does not by itself authorize destructive/public mutations.

## Track F — Migration Bridge absorption

Only after Manager optimization/publishing core is stable:

- absorb accepted Site Intelligence/baseline/dependency behavior;
- absorb migration adapters;
- absorb parity/cutover/rollback/final-report behavior;
- preserve all accepted safety/privacy contracts;
- keep migration mode optional;
- retire the standalone Bridge package only after full regression parity is proven.

## Client autonomy contract

A client remains able to:

- create/edit blog posts manually in Gutenberg;
- edit Manager-created blog posts in Gutenberg;
- create legal/privacy/cookie/simple pages;
- manage media and authorized taxonomies.

Strategic SEO/GEO growth pages should preferentially use Manager because that path adds model validation, SEO/GEO authority resolution, internal linking, audit and rollback.

## Current stop/go rule

Do **not** rerun Reset or hydration merely to test A3 presentation.

Do **not** roll the other four preset master surfaces forward yet.

Do **not** begin A4 renderer generalization until the exact A3 field candidate passes `/nuevaweb/` visual/browser acceptance.

Do **not** require SEO/GEO Manager to render the frontend.

Current execution is: **close Corporate v5 A3 → freeze/verify deterministic candidate → real `/nuevaweb/` QA → explicit premium/WOW acceptance → A4 renderer generalization**.
