# SEO/GEO Manager

## Purpose

SEO/GEO Manager is the permanent WordPress plugin product for **analysis, controlled content publication, migration and ongoing SEO/GEO operations**.

It is designed for two equally important cases:

1. a client already has a WordPress site and does not want to replace the current theme;
2. a client uses the SEO/GEO Theme and wants an ongoing publishing/operations layer.

The Manager must not require GitHub, a staging environment, a specific hosting company, Elementor, Divi or the SEO/GEO Theme.

## Planned package boundary

Target package: packages/seo-geo-manager/

The current packages/seo-geo-migration-bridge remains the accepted migration implementation until its capabilities are deliberately absorbed into the Manager. Do not rename or remove the accepted bridge package before migration parity is proven.

## Modules

### 1. Site Intelligence

Read-only by default.

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

Provider-neutral publishing infrastructure for landings and blog posts.

Responsibilities:

- versioned content manifest;
- dry-run validation;
- draft-first creation;
- preview;
- explicit publish authorization;
- idempotency key;
- stable resource identity;
- revision/change-set recording;
- rollback;
- scheduling hooks without making cron reliability assumptions;
- per-language relationship data;
- bounded publication report.

Detailed contract: docs/CONTENT_PUBLISHING.md.

### 4. Landing Engine

Responsibilities:

- create/update landing pages through supported content adapters;
- preserve or explicitly plan slugs/canonicals;
- compose approved layouts/patterns;
- inject unique local/service/product value rather than token-swapped doorway content;
- support internal-link plans;
- coordinate page SEO/GEO through the resolved authority;
- prevent duplicate intent/URL collisions.

The first renderer should be native WordPress blocks. Elementor/Divi writing support enters only through individually accepted adapters.

### 5. Blog Engine

Responsibilities:

- draft/update WordPress posts;
- author and provenance binding;
- categories/tags only through explicit policy;
- source/reference metadata;
- internal-link suggestions/manifest;
- featured media references;
- localized article relationships;
- publication/update timestamps from WordPress authority;
- Article/BlogPosting behavior only when the visible content and output provider support it.

The engine never fabricates authors, sources, reviews, dates, claims or expertise.

### 6. Migration module

Long-term home of the accepted Migration Bridge capabilities:

- analyzer;
- baseline;
- dependency graph;
- builder migration adapters;
- parity;
- cutover/rollback;
- bounded final report.

Migration mode is optional. After accepted handoff it can be disabled while the Manager remains active for publishing.

### 7. Portable Sandbox coordinator

Supports clients who have no staging environment.

Responsibilities:

- determine available sandbox mode;
- produce a bounded migration/export manifest;
- coordinate backup references;
- enforce sandbox indexing isolation;
- verify candidate artifact identity;
- run parity/quality evidence collection;
- never treat production as the first test environment.

Detailed contract: docs/PORTABLE_SANDBOX.md.

### 8. Integrations

Adapters are optional and individually accepted.

Potential families:

- SEO: Yoast SEO, Rank Math, All in One SEO;
- builders: native blocks, Elementor, Divi;
- multilingual: native SEO/GEO language layer, WPML, Polylang;
- commerce/business: WooCommerce and client-specific systems;
- forms/analytics/consent/cache: detection first, mutation only with explicit adapter authority.

Detection is not compatibility. A provider is supported only after its adapter contract passes real WordPress acceptance.

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
- secure draft-first content publication;
- idempotent create/update and rollback;
- native-block landing + blog publication;
- Migration module regression parity with accepted Phase 8 contracts;
- portable-sandbox path validated;
- EN/ES operator UX;
- no duplicate SEO/GEO output;
- real-site acceptance on at least one Theme site and one non-Theme WordPress site.
