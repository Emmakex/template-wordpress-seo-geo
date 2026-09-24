# Content publishing contract

## Goal

SEO/GEO Manager must publish landing pages and blog posts to client WordPress sites **without requiring GitHub** and without making content generation, approval and publication one irreversible operation.

Generation may happen in ChatGPT, an agency workflow, a future hosted controller or another provider. The WordPress plugin is the controlled publication endpoint.

## Core workflow

    content brief / generated content
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
    publish/update
            ↓
    verify public output
            ↓
    bounded report + rollback reference

Draft-first is the default. Direct publish is an explicit privileged action, never an inferred consequence of receiving a payload.

## Publication manifest

The first implementation must define a versioned manifest. At minimum it needs bounded fields for:

- schema/version;
- operation ID and idempotency key;
- content type: landing or blog;
- target site;
- target resource ID when updating;
- requested slug/path;
- language/locale;
- title;
- body/content blocks;
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

## Landing publication rules

Landing pages must have a declared search/user intent.

Required guards:

- no mass city/service token swapping;
- no doorway-page batches without unique local/service value;
- no duplicate or near-duplicate target intent without review;
- no invented addresses, reviews, ratings, availability, prices, customers, certifications or performance claims;
- visible content and Schema facts must agree;
- canonical/indexability changes must be explicit;
- internal links must use valid existing/planned targets;
- external sources must be genuine and reviewable.

Renderer contract:

1. native WordPress blocks first;
2. Elementor/Divi only through accepted write adapters;
3. unsupported builder modules block publication rather than being silently dropped.

## Blog publication rules

Blog/article content requires:

- explicit author identity;
- real publication/update dates from WordPress;
- sources/references where the content claims them;
- no fabricated quotes/citations;
- no inferred reviewer or expertise claims;
- Article/BlogPosting behavior only on genuine article content;
- category/tag creation controlled by policy to prevent taxonomy sprawl;
- related/internal links chosen from valid resources, not invented URLs.

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
- explicit about dry-run/draft/publish mode.

The API must not expose arbitrary WordPress option writes, plugin installation, shell execution or unrestricted file writes.

## Acceptance baseline

Before the publishing feature is considered sellable, CI/real-site acceptance must prove:

- create one landing draft;
- retry same operation without duplication;
- publish after explicit authorization;
- update with expected fingerprint;
- reject stale overwrite;
- roll back Manager-owned changes;
- create/update one blog post;
- preserve author/provenance;
- multilingual relationship handling;
- Theme authority integration;
- generic WordPress authority path;
- no duplicate SEO/GEO output;
- no private data in reports;
- clean uninstall/deactivation behavior that does not delete client content.
