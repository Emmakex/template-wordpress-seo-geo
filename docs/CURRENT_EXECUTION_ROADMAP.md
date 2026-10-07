# Current execution roadmap

## Authority

This document is the **current execution pointer** after the EMMAKE Corporate real-site findings.

`docs/ROADMAP.md` remains the historical/global phase record. Where an older roadmap item assumes Gutenberg/native blocks are the canonical strategic-page renderer, this current roadmap plus `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md` supersede that rendering assumption.

The project rule remains **finish before advancing**.

## Current product state

- Migration/Reset/Rescue/Hydration/SEO-GEO technical path: accepted through the current EMMAKE sandbox evidence.
- Migration Bridge: frozen at `0.8.60`; do not change it for Theme presentation work.
- Corporate v4.x: retained as historical technical evidence; visual/architectural direction superseded by v5.
- Corporate v5 A1 — renderer/model boundary: **complete**.
- Corporate v5 A2 — clean Theme-owned strategic Home/chrome: **complete**.
- Corporate v5 A3 — lighter field palette/presentation refinement: **complete as the base visual refinement**.
- Corporate v5 A3.1 — bounded visual/media closure: **repository implementation complete and technically green; exact field candidate frozen; real `/nuevaweb/` acceptance pending**.
- Stable Theme promotion: **NO-GO** pending exact `/nuevaweb/` browser/product acceptance.
- Renderer generalization: frozen until A3.1 passes the real-site premium/WOW gate.
- SaaS / Local Pro / Publisher / Ecommerce master-surface rollout: frozen until the Corporate renderer contract is accepted and generalized.
- SEO/GEO Manager implementation: planned after the Theme semantic renderer/model boundary is proven reusable; architecture and publishing contracts are already documented.

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

Status: **complete**.

Delivered:

- premium Corporate renderer using the accepted semantic content;
- Theme-owned strategic header, Home and footer;
- hero, capabilities, process, Insights and final CTA as Theme-owned components;
- article/query references resolved through WordPress data rather than visual block-position contracts;
- removal of legacy Corporate v4 presentation debt from v5 client delivery;
- responsive behavior at `1440 / 1024 / 768 / 390`, with `320` as automated stress case;
- accessibility and performance budgets as repository gates.

Real `/nuevaweb/` QA validated the architecture and exposed presentation issues rather than renderer-boundary failures. That evidence produced the bounded A3/A3.1 refinement path instead of reopening A1/A2.

### A3 — Lighter field refinement

Status: **complete as visual baseline**.

Delivered without changing content/URLs/SEO-GEO/Reset/hydration/Migration Bridge/production:

- lighter teal hero and primary feature surfaces;
- stronger secondary-text/card separation;
- lighter Method/final CTA treatment;
- branded teal footer and matching mobile palette;
- improved contrast and hierarchy while retaining the Theme-owned renderer.

### A3.1 — Visual/media closure

Status: **technically complete; frozen field candidate; field QA pending**.

Implementation source commit:

`c2396b0aa0a17f2ba8b6cfc4d8c7435ff884fa00`

Frozen technical identity:

- Theme `0.1.1` / `prestable`;
- Theme ZIP SHA-256 `f4c7074dd3105a04375a212845b54c5bec0e8782870d62df041a6086a36a584f`;
- Migration Bridge `0.8.60` / SHA-256 `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic field pack SHA-256 `1736d8b6dfb9aeec8cc8132a4ee1c42d5d9e4a3afb4510934edfd0cecd5a9fe2`.

Delivered:

- compact hero geometry so the primary actions fit the practical first desktop viewport;
- local Theme-owned hero visual slot;
- capability media/decorative treatment without remote dependencies;
- stronger Method/process hierarchy;
- featured Insights media that prefers WordPress featured media and falls back to a local Theme visual;
- reusable local editorial visual library for marketing, AI, automation/data, strategy/content and analytics/research;
- visual treatment optimized to preserve the existing performance budget rather than widening it.

Repository acceptance on the final implementation includes:

- Lighthouse performance `100`;
- Corporate FCP `751.72 ms`;
- Corporate LCP `901.72 ms`;
- CLS `0`;
- TBT `0`;
- `8` total Corporate requests;
- `0` third-party requests;
- `0` project JavaScript bytes;
- `118` DOM nodes;
- Foundation, Design System, Corporate Pipeline, Package, Release Artifact, PHP Quality/PHPStan, Multilingual, Self-contained, WordPress Smoke and Accessibility/Responsive gates green.

A temporary 13-request regression was traced to five decorative SVG data URIs. Those were replaced with local CSS/pseudo-element treatment. The existing request budget was preserved; it was not relaxed.

Field acceptance still requires:

- install only the exact frozen Theme on `/nuevaweb/`;
- keep the current clean/hydrated state;
- do not rerun Reset or hydration;
- real review at `1440 / 1024 / 768 / 390`;
- explicit premium/WOW visual approval;
- no SEO/GEO, accessibility, responsive or performance regression in the real sandbox.

Repository CI closes the technical candidate; it does **not** infer human field acceptance.

### A4 — Generalize renderer contract

Status: **blocked by A3.1 real-site acceptance**.

Only after Corporate v5 A3.1 passes real-site QA:

- extract reusable semantic renderer interfaces/helpers;
- define model-version compatibility behavior;
- define missing/optional/evidence-sensitive slot behavior;
- define renderer fallback/error behavior;
- keep public HTML server-rendered and local to WordPress;
- make the contract suitable for future SEO/GEO Manager structured landing models without coupling Manager to Theme layout internals.

This is the formal renderer-generalization phase. It must not be confused with the A3/A3.1 visual field iterations.

### A5 — Remaining preset master surfaces

After A4 closes, move strategic master surfaces to the same architecture in this order:

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

Support topic/search intent, cluster relationships, title/excerpt, structured body, provenance, author binding, taxonomies under policy, internal links, related commercial resources, media references, SEO metadata intent, Article/BlogPosting intent and locale/translation relationships.

### D2 — Normal WordPress post materialization

Manager-created articles become normal WordPress posts and remain editable by authorized users in Gutenberg.

The editable body uses a minimal stable editorial representation; it does not encode the Theme's premium page layout.

Theme owns the public article shell: reading width, typography, header/hero, author/date/provenance, related content, CTAs, responsive behavior, accessibility, Schema/public metadata integration and performance.

### D3 — Automated publication modes

Support progressively:

1. draft-only;
2. draft + explicit approval;
3. approved scheduling;
4. future policy-authorized direct publish with audit/rollback.

Draft-first must always remain available.

### D4 — Blog refresh/update

Support stale-content refresh, search-opportunity refresh, new-source/fact updates, internal-link improvement, cluster expansion, expected-revision protection, rollback and public verification.

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

Do **not** rerun Reset or hydration for A3.1 presentation.

Do **not** change Migration Bridge for A3.1.

Do **not** touch production.

Do **not** roll the other four preset master surfaces forward yet.

Do **not** begin A4 renderer generalization until the exact A3.1 field candidate passes `/nuevaweb/` visual/browser acceptance.

Do **not** require SEO/GEO Manager to render the frontend.

Current execution is:

**freeze/verify exact A3.1 candidate → one Theme-only `/nuevaweb/` install → real QA at 1440/1024/768/390 → explicit premium/WOW acceptance → A4 renderer generalization → SaaS / Digital Product**.
