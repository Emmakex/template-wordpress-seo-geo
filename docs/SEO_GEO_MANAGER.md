# SEO/GEO Manager

## Purpose

SEO/GEO Manager is the permanent WordPress plugin product for **continuous SEO/GEO optimization, automated content generation/publication and editorial operations**.

Its primary product case is the site **after launch or rebuild**: keep improving content, internal linking, landing pages, blogs and SEO/GEO signals without returning to a developer-led rebuild cycle.

It may also support existing WordPress sites that keep their current theme, but that compatibility path must not distort the clean Theme + preset workflow.

The Manager must not require GitHub, a staging environment, a specific hosting company, Elementor, Divi or the SEO/GEO Theme.

The canonical Theme/Manager/Gutenberg ownership contract is `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`.

## Planned package boundary

Target package: `packages/seo-geo-manager/`.

The current `packages/seo-geo-migration-bridge` remains the accepted migration implementation until its capabilities are deliberately absorbed into the Manager. Do not rename or remove the accepted bridge package before migration parity is proven.

## Architectural principle

Manager is the **control plane**, not the public renderer.

When the SEO/GEO Theme is active:

- Manager creates and updates structured content and WordPress resources;
- Theme renders strategic surfaces from versioned semantic models;
- Gutenberg remains available for article editing and simple client-authored pages;
- normal public rendering remains local to WordPress and does not require a remote Manager/controller request.

The permanent split is:

> **Gutenberg provides editorial autonomy. SEO/GEO Manager provides automation and growth. SEO/GEO Theme provides frontend rendering, design, semantic HTML and performance.**

## Modules

### 1. Site Intelligence

Read-only by default and intentionally lightweight for already-rebuilt Theme sites. Deep legacy-stack analysis is only required when operating on a non-Theme existing site.

Responsibilities:

- WordPress/PHP/runtime inventory;
- theme/child-theme inventory;
- plugin and must-use plugin inventory;
- builder detection;
- CPT/taxonomy/shortcode/widget/menu inventory;
- public URL inventory;
- SEO/GEO baseline capture;
- forms, analytics, consent, redirects, cache/CDN and business-system detection;
- content-model and customization signals;
- bounded, privacy-safe diagnostics.

The accepted Phase 8 analyzer/baseline contracts are the starting point.

### 2. Output Authority Resolver

Before Manager writes or emits SEO/GEO signals it resolves the current owner for each surface.

Surfaces include:

- title/meta description;
- canonical;
- robots/indexability;
- Open Graph/social metadata;
- hreflang;
- Schema graph;
- sitemap extensions;
- redirects;
- discovery surfaces such as llms.txt/Markdown alternates where enabled.

The resolver produces one of:

- theme-native;
- manager-native;
- external-provider adapter;
- wordpress-core;
- manual-review;
- blocked-conflict.

No signal is emitted by Manager while ownership is ambiguous.

### 3. Content Publishing Core

Provider-neutral publishing infrastructure for strategic pages and blog posts.

Responsibilities:

- versioned content manifest;
- dry-run validation;
- draft-first creation;
- preview;
- explicit or policy-authorized schedule/publish;
- idempotency key;
- stable resource identity;
- revision/change-set recording;
- rollback;
- per-language relationship data;
- bounded publication report;
- expected-revision protection against overwriting newer human changes.

Detailed contract: `docs/CONTENT_PUBLISHING.md`.

### 4. Landing Engine

Landing Engine owns automated strategic-page creation and refresh.

Responsibilities:

- create/update Home, service, product, solution, location, campaign and commercial hub resources through accepted models/adapters;
- preserve or explicitly plan slugs/canonicals;
- create versioned semantic content models rather than preset-specific Gutenberg layout trees when the SEO/GEO Theme is active;
- inject unique local/service/product value rather than token-swapped doorway content;
- support internal-link plans and content clusters;
- coordinate page SEO/GEO through the resolved authority;
- prevent duplicate intent/URL collisions;
- support preview, schedule/publish, verification and rollback.

#### Theme path

With SEO/GEO Theme active, Manager writes structured content and the Theme renderer owns visual composition. Manager does not need to build `Group`, `Columns`, `alignwide`, `contentSize` or other Gutenberg layout structures.

#### Non-Theme path

On supported non-Theme WordPress sites, Manager may publish through explicitly accepted native-block/builder/provider adapters. Those compatibility adapters do not redefine the canonical Theme architecture.

### 5. Blog Engine

Automated blog publication is a **first-class Manager feature**.

Blog Engine must be able to generate, draft, optimize, schedule/publish, update and refresh normal WordPress posts.

Responsibilities:

- topic/keyword/search-intent assignment;
- content-cluster relationship;
- structured article model;
- outline/headings/body generation or ingestion;
- author and provenance binding;
- source/reference metadata;
- categories/tags only through explicit policy;
- internal-link plan;
- links to real service/landing/product targets;
- featured media references;
- localized article relationships;
- SEO title/meta/canonical/indexability intent;
- publication/update timestamps from WordPress authority;
- Article/BlogPosting behavior only when visible content and output authority support it;
- expected-revision protection;
- refresh/update workflow;
- rollback and public verification.

The engine never fabricates authors, sources, reviews, dates, claims or expertise.

#### Gutenberg interoperability

A Manager-created blog article becomes a **normal WordPress post** and must remain editable by authorized client users in Gutenberg.

Manager may use a structured article model internally for generation, validation and future refresh. The editable body should use a minimal stable WordPress/Gutenberg-compatible editorial representation, not preset-specific layout markup.

Gutenberg may control **article body editing**. SEO/GEO Theme controls the public article shell: reading width, header treatment, typography, author/date/provenance presentation, related content, CTAs, Schema integration, responsive behavior, accessibility and performance.

This gives clients autonomy without making automated publishing depend on Gutenberg as the frontend layout engine.

### 6. Growth / Opportunity Engine

Long-term Manager operation should use connected search/analytics signals to discover opportunities rather than wait only for manual briefs.

Potential inputs:

- Search Console;
- Bing Webmaster/search data;
- analytics;
- current rankings/impressions/clicks;
- content inventory;
- internal-link graph;
- stale-content signals;
- commercial/service priorities;
- local/GEO gaps.

Potential outputs:

- new landing opportunity;
- new automated blog topic;
- existing landing refresh;
- existing article refresh;
- internal-link recommendation/change set;
- cluster expansion;
- local/GEO content opportunity.

Every resulting mutation still passes the Publishing Core, authority resolution, validation and rollback contracts.

### 7. Migration module

This is an **optional secondary/backward-compatibility module**, not the primary reason the Manager exists. It absorbs accepted Migration Bridge capabilities only after the Manager's optimization/content core is already stable:

- analyzer;
- baseline;
- dependency graph;
- builder migration adapters;
- parity;
- cutover/rollback;
- bounded final report.

Migration mode is optional. After accepted handoff it can be disabled while the Manager remains active for publishing.

### 8. Portable Sandbox coordinator

Supports clients who have no staging environment.

Responsibilities:

- determine available sandbox mode;
- produce a bounded migration/export manifest;
- coordinate backup references;
- enforce sandbox indexing isolation;
- verify candidate artifact identity;
- run parity/quality evidence collection;
- never treat production as the first test environment.

Detailed contract: `docs/PORTABLE_SANDBOX.md`.

### 9. Integrations

Adapters are optional and individually accepted.

Potential families:

- SEO: Yoast SEO, Rank Math, All in One SEO;
- builders/editors: Gutenberg/native blocks, Elementor, Divi where specifically supported;
- multilingual: native SEO/GEO language layer, WPML, Polylang;
- commerce/business: WooCommerce and client-specific systems;
- forms/analytics/consent/cache: detection first, mutation only with explicit adapter authority.

Detection is not compatibility. A provider is supported only after its adapter contract passes real WordPress acceptance.

## Theme-owned strategic content models

When Theme + Manager are both active, strategic resources use versioned semantic models.

Initial families may include:

```text
corporate-home-v1
corporate-landing-v1
service-page-v1
location-page-v1
campaign-page-v1
saas-home-v1
local-home-v1
publisher-home-v1
ecommerce-home-v1
```

The model expresses semantic slots/content, not layout implementation. The Theme maps accepted model versions to preset renderers.

A future Manager release must be able to create a new strategic landing without knowing the renderer's CSS grid or Gutenberg block nesting.

## Automated publication modes

Manager may expose policy levels such as:

- draft-only;
- draft + explicit publish approval;
- approved schedule;
- future policy-authorized direct publish.

Draft-first remains the baseline and must always remain available. Autonomous modes require explicit administrator policy, audit, guardrails and rollback.

## Output-authority matrix

### SEO/GEO Theme active

The Theme remains native output authority. Manager publishes content/configuration through supported public contracts and does not duplicate Theme output.

### Supported external SEO provider active

Manager writes through that provider adapter where the adapter has explicit ownership support. Manager-native overlapping output remains disabled.

### No supported SEO provider and generic theme

Manager may offer a manager-native SEO/GEO authority mode only after:

- conflict scan is clean;
- administrator explicitly enables it;
- the relevant shared Core runtime is packaged in the Manager;
- zero-duplicate runtime acceptance passes.

### Multiple/conflicting providers

Publishing may remain draft-only, but SEO/GEO activation is blocked until ownership is resolved.

## Security model

Every mutation surface must require:

- authenticated WordPress user or authenticated scoped API client;
- least-privilege capability checks;
- nonce for browser-admin mutations;
- request authentication/signature for remote publishing;
- explicit resource/action scope;
- idempotency key for create/update operations;
- validation before mutation;
- bounded audit/change-set record.

Credentials and tokens:

- are never committed to repository or content;
- are never included in migration/publication reports;
- must be revocable;
- must be stored using the safest available deployment mechanism;
- should prefer environment/secret-manager injection for managed installations.

## Privacy model

- Analyzer defaults to metadata/fingerprints rather than raw private content export.
- Remote publishing transports only the content/configuration required for the requested operation.
- Client/customer/order/form data is outside the content-generation contract.
- Telemetry is opt-in and must be documented separately.
- GDPR/data-processing responsibilities must be explicit for any future hosted controller.

## Performance model

Manager must not turn every frontend request into a remote API call.

Default principles:

- publication is a control-plane operation;
- public pages render from WordPress-local state;
- remote services are not required to serve normal frontend HTML;
- Theme-owned renderers execute locally/server-side;
- background operations are bounded and observable;
- caches are invalidated only for affected surfaces.

## Commercial independence

Manager receives its own installable ZIP and release lifecycle. Licensing/update delivery, if added, is a distribution concern and cannot disable client content or break the public site when a license server is unavailable.

## Definition of done for first stable Manager release

A first stable Manager release requires:

- independent install/activate/upgrade/rollback;
- WordPress/PHP support matrix;
- Site Intelligence accepted on representative legacy fixtures;
- authority resolver accepted with Theme and at least one generic/no-provider path;
- secure draft-first publication core;
- idempotent create/update and rollback;
- create one Theme-owned strategic landing from a versioned semantic model;
- render that landing without Gutenberg controlling the master layout;
- automatically create one complete normal WordPress blog draft;
- preserve author/source/provenance metadata;
- allow the automated blog draft to be edited in Gutenberg;
- schedule/publish the blog through an authorized path;
- refresh an article with stale-revision protection;
- internal-link/cluster handling;
- Migration module regression parity with accepted Phase 8 contracts;
- portable-sandbox path validated;
- EN/ES operator UX;
- no duplicate SEO/GEO output;
- real-site acceptance on at least one Theme site and one non-Theme WordPress site.

## Roadmap dependency

Before implementing the Manager Landing Engine against the Theme, the project must prove **Corporate v5 — Theme-owned frontend** and generalize its semantic renderer contract.

The current recommended order is:

1. Corporate v5 renderer from existing `corporate-home-v1` content;
2. generalized Theme semantic renderer/model registry;
3. remaining preset master renderers;
4. Manager Content Publishing Core;
5. Manager Landing Engine;
6. Manager automated Blog Engine with Gutenberg interoperability;
7. growth/opportunity feedback loops;
8. Migration Bridge capability absorption after the optimization/publishing core is stable.
