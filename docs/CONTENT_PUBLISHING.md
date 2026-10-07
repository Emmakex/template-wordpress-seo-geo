# Content publishing contract

## Goal

SEO/GEO Manager must publish and maintain **strategic landing pages and automated blog posts** on client WordPress sites without requiring GitHub and without making generation, approval and publication one irreversible operation.

Generation may happen in ChatGPT, an agency workflow, a future hosted controller or another provider. The WordPress plugin is the controlled publication endpoint.

The publishing architecture follows `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`:

- strategic pages use structured content models and Theme-owned renderers when the SEO/GEO Theme is active;
- blog posts remain normal WordPress posts and remain editable in Gutenberg;
- Gutenberg is an editorial interface, not the layout engine for strategic Theme surfaces;
- public rendering remains local to WordPress.

## Core workflow

```text
content opportunity / brief / generated content
        ↓
versioned publication manifest
        ↓
validate + resolve target + resolve SEO authority
        ↓
dry run / diff
        ↓
draft
        ↓
preview + human/authorized approval
        ↓
publish / schedule / update
        ↓
verify public output
        ↓
bounded report + rollback reference
```

Draft-first is the baseline. Direct or scheduled publication may be enabled as an explicit privileged policy, never inferred merely because a payload was received.

## Publication manifest

The first implementation must define a versioned manifest. At minimum it needs bounded fields for:

- schema/version;
- operation ID and idempotency key;
- content type: landing, strategic page or blog;
- structured model identifier when applicable;
- target site;
- target resource ID when updating;
- requested slug/path;
- language/locale;
- title;
- structured content/body;
- excerpt/summary when applicable;
- author reference when applicable;
- featured media references when applicable;
- categories/taxonomies when explicitly allowed;
- SEO title/meta description intent;
- canonical intent;
- indexability intent;
- Open Graph intent;
- Schema/entity hints rather than fabricated facts;
- internal links;
- external source/reference links;
- publication mode: dry-run, draft, schedule or explicit publish;
- expected previous revision/fingerprint for updates.

Secrets are never part of the manifest.

## Idempotency

Every create/update request has an idempotency key.

Required behavior:

- retrying an accepted request cannot create a second page/post;
- a reused key with a different payload is rejected;
- successful create returns/stores the stable WordPress object ID;
- update may require the expected previous fingerprint so stale automation cannot overwrite newer human edits;
- URL/slug collision is a blocker unless the operation explicitly targets the existing resource.

## Change set and rollback

Before a Manager update to an existing resource:

- capture the exact resource/revision reference required for rollback;
- capture only the metadata keys the Manager is authorized to modify;
- record previous/new fingerprints;
- preserve resource ID unless the operation explicitly creates a new resource.

Rollback restores only Manager-owned changes. It must not overwrite unrelated newer orders, form submissions, users or other site-wide data.

## Authority resolution

The publication engine never assumes it owns SEO output.

Before applying SEO/GEO data it asks the Manager Output Authority Resolver.

Examples:

- SEO/GEO Theme → use Theme-native configuration/public contracts;
- supported Yoast/Rank Math/AIOSEO adapter → write through that adapter;
- manager-native mode → use Manager accepted native authority;
- ambiguous provider ownership → keep publication draft-only or block the affected SEO mutation.

## Strategic landing publication rules

Theme-managed strategic pages use a **versioned semantic model**, not generated Gutenberg layout markup.

Examples include:

- Home;
- service/solution landing;
- product landing;
- local/GEO landing;
- campaign page;
- commercial hub/cluster page;
- conversion page supplied by a preset renderer.

Required guards:

- declared search/user intent;
- no mass city/service token swapping;
- no doorway-page batches without unique local/service value;
- no duplicate or near-duplicate target intent without review;
- no invented addresses, reviews, ratings, availability, prices, customers, certifications or performance claims;
- visible content and Schema facts must agree;
- canonical/indexability changes must be explicit;
- internal links must use valid existing/planned targets;
- external sources must be genuine and reviewable.

### Theme-owned landing renderer contract

When the SEO/GEO Theme is active:

1. Manager creates/updates a structured content model and normal WordPress resource identity;
2. Manager does not need to construct Group/Columns/alignwide/contentSize layout trees;
3. SEO/GEO Theme renders the strategic page server-side;
4. Theme owns master width, responsive composition, typography, semantic HTML, accessibility and presentation;
5. Manager remains responsible for content-operation provenance, idempotency and rollback;
6. SEO output is emitted only by the resolved authority.

On non-Theme sites, Manager may use accepted adapters. Elementor/Divi/native-block writing support is an integration path, not the canonical Theme path. Unsupported builder modules block publication rather than being silently dropped.

## Automated Blog Engine contract

Automated blog publication is a **first-class SEO/GEO Manager capability**.

Manager must be able to create a complete blog entry from an approved topic/brief, keep it auditable, optionally schedule/publish it and later refresh it.

A structured article model should support at least:

- target topic/intent/keyword cluster;
- working/final title;
- excerpt;
- article outline;
- headings;
- body sections;
- author binding;
- source/reference provenance;
- featured media references;
- categories/tags under explicit policy;
- internal-link plan;
- links to relevant landings/services/products;
- SEO title/meta description intent;
- canonical/indexability intent;
- Article/BlogPosting entity hints;
- locale and multilingual sibling relationships;
- planned publication date/time when scheduling is requested.

### Blog materialization rule

Manager-created articles become **normal WordPress posts**.

They must remain editable by authorized client users in Gutenberg. Manager may use a structured model internally for generation, validation and later refresh, but the persisted editable body should use a minimal stable editorial representation compatible with normal WordPress/Gutenberg editing rather than preset-specific visual layout blocks.

Gutenberg may therefore own **body editing**, while SEO/GEO Theme owns the public article shell:

- reading width;
- article header/hero;
- typography;
- author/date/provenance presentation;
- table of contents where enabled;
- related content;
- CTA surfaces;
- responsive behavior;
- accessibility;
- Schema/public metadata integration;
- performance.

This preserves client autonomy without making automatic publishing depend on Gutenberg as the frontend layout engine.

## Automated Blog Engine workflow

```text
Search Console / Bing / content plan / manual brief
        ↓
topic + intent + cluster
        ↓
research / source set / factual constraints
        ↓
structured article draft
        ↓
quality + SEO/GEO + duplication validation
        ↓
internal-link plan
        ↓
normal WordPress post draft
        ↓
optional Gutenberg human/client edit
        ↓
approval / schedule / explicit publish
        ↓
public verification
        ↓
future measurement / refresh / rollback
```

## Blog publication rules

Blog/article content requires:

- explicit author identity;
- real publication/update dates from WordPress;
- sources/references where the content claims them;
- no fabricated quotes/citations;
- no inferred reviewer or expertise claims;
- Article/BlogPosting behavior only on genuine article content;
- category/tag creation controlled by policy to prevent taxonomy sprawl;
- related/internal links chosen from valid resources, not invented URLs;
- explicit handling of external references;
- no silent replacement of newer human edits.

## Blog automation modes

The product may support multiple policy levels:

- **draft-only** — Manager creates a draft and a person approves;
- **draft + scheduled approval** — person approves schedule/publication;
- **policy-authorized schedule** — pre-approved rules allow scheduling after validation;
- **policy-authorized direct publish** — future privileged mode with strong guardrails, audit and rollback.

The baseline product must always retain draft-first operation. Autonomous publication must never remove the ability to review or roll back.

## Content refresh

Blog Engine and Landing Engine must support updates, not only creation.

Refresh operations may be triggered by:

- declining impressions/clicks;
- ranking opportunity;
- stale dates/facts;
- newly available source material;
- missing internal links;
- cluster gaps;
- changed products/services;
- GEO/local opportunity;
- explicit editorial request.

Every refresh must use expected-revision/fingerprint protection so Manager cannot silently overwrite a newer client edit.

## Internal linking and cluster behavior

Manager should treat content as a graph rather than independent documents.

Before publication it may propose or validate:

- links from article → service/landing;
- links from landing → supporting articles;
- links between related articles;
- hub/cluster relationships;
- anchor-text diversity;
- orphan-content remediation.

Only real accepted URLs may be emitted. Planned targets must not be published as broken links.

## Multilingual rules

A translated/localized publication is a distinct resource with an explicit relationship to its siblings.

The engine must:

- identify locale;
- preserve one canonical per localized resource;
- create hreflang relationships only when both targets genuinely exist/are accepted;
- never auto-create a translation merely because a locale is configured;
- keep x-default behavior consistent with the active language provider.

## Publication states

Minimum states:

- received;
- validated;
- blocked;
- drafted;
- scheduled;
- published;
- verified;
- rolled-back;
- failed.

A state transition is recorded with bounded metadata. Raw credentials/private client data are excluded.

## Public verification

After publication/update, verify at minimum:

- HTTP status;
- expected resource ID/path;
- title/H1;
- canonical/indexability;
- duplicate owner absence;
- expected language relationship;
- critical internal links;
- Schema type/identity coherence where applicable;
- sitemap inclusion when indexable;
- no PHP fatal/warning/notice caused by the operation.

A successful WordPress write without public verification is not a completed publication.

## API boundary

A future remote publication API must be:

- authenticated;
- scoped;
- rate-limited;
- replay-resistant;
- idempotent;
- versioned;
- capability-aware;
- explicit about dry-run/draft/schedule/publish mode.

The API must not expose arbitrary WordPress option writes, plugin installation, shell execution or unrestricted file writes.

## Acceptance baseline

Before the publishing feature is considered sellable, CI/real-site acceptance must prove:

- create one Theme-owned strategic landing draft from a structured model;
- render that landing without Gutenberg controlling master layout;
- retry same operation without duplication;
- publish after explicit authorization;
- update with expected fingerprint;
- reject stale overwrite;
- roll back Manager-owned changes;
- automatically create one complete blog draft;
- preserve author/provenance/sources;
- allow the created blog post to be edited in Gutenberg;
- schedule/publish through an authorized path;
- refresh an existing article without overwriting a newer human revision;
- multilingual relationship handling;
- Theme authority integration;
- generic WordPress authority path;
- no duplicate SEO/GEO output;
- no private data in reports;
- clean uninstall/deactivation behavior that does not delete client content.
