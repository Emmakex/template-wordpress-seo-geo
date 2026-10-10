# SEO/GEO Manager

Status: **authoritative Manager product contract**

Canonical portfolio boundary: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Purpose

SEO/GEO Manager is the permanent, independently installable **WordPress bridge/control agent** that gives an authorized external operator safe eyes and hands inside a client's WordPress.

Manager is **not the strategic brain** of the system.

The strategic brain lives outside WordPress: our human + ChatGPT-assisted + future automation workflows perform research, strategy, content creation, optimization, prioritization and growth reasoning. Manager receives bounded instructions, applies them locally through WordPress/product APIs, verifies the result and preserves an audit/rollback path where supported.

The target operating loop is:

```text
external orchestration
      -> inspect real WordPress state through Manager
      -> reason/research/create outside WordPress
      -> prepare bounded operation
      -> Manager preview
      -> approve/policy gate
      -> Manager execute
      -> Manager verify
      -> operation evidence / rollback reference
      -> external orchestration decides next action
```

Manager must not require GitHub, a specific hosting company, Elementor, Divi, Migration Bridge or SEO/GEO Theme.

It should work on any **supported** WordPress environment for which the required capabilities/adapters are accepted.

---

## Product boundary

Target package: `packages/seo-geo-manager/`.

Manager owns **local WordPress control**, not migration transport, frontend rendering or strategic intelligence.

### Manager owns

- Site Intelligence and capability discovery;
- authenticated/scoped remote API operations;
- WordPress resource reads;
- exact Preview/diff;
- bounded Apply/Execute;
- draft/schedule/publish/update controls;
- Theme semantic-model reads/writes when Theme is active;
- provider-adapter writes where explicitly accepted;
- navigation/internal-link/media/taxonomy operations where authorized;
- output-authority resolution needed to prevent duplicate/conflicting output;
- stored/rendered verification;
- operation history/evidence;
- rollback where the operation contract supports safe reversal;
- production-environment, fingerprint, revision and idempotency safeguards.

### Manager does not own

- keyword/market research strategy;
- competitor analysis;
- deciding what article to write next;
- deciding what landing page should exist for commercial reasons;
- business positioning;
- creative direction;
- full copywriting strategy;
- deciding which Search Console/Bing opportunity matters most;
- cluster/growth prioritization;
- public frontend layout/rendering;
- clone/export/import migration transport;
- rescue/reset-first migration lifecycle.

Manager may expose deterministic diagnostics, evidence and capability state that the external operator uses for reasoning. That is different from embedding the strategic reasoning engine inside WordPress.

---

## Architectural principle

Manager is a **control plane / local execution bridge**, not a public renderer and not a self-contained autonomous SEO agent.

When SEO/GEO Theme is active:

- external orchestration decides/creates the semantic change intent;
- Manager safely persists WordPress resources and accepted semantic models;
- Theme renders strategic surfaces from those models;
- Gutenberg remains available for normal editorial content;
- public rendering remains local to WordPress;
- no remote Manager/controller request is required for normal frontend HTML.

Permanent responsibility split:

> **Migration Bridge brings/rescues the site. Theme renders the site. Manager safely controls WordPress. External orchestration provides the intelligence.**

---

# Manager capability families

## 1. Site Intelligence / Inspect

Read-only by default.

Manager exposes a truthful bounded view of the current site so external orchestration can reason from real evidence.

Responsibilities may include:

- WordPress/PHP/runtime inventory;
- active theme/child-theme and Theme/preset/model capabilities;
- plugin/must-use plugin inventory where permitted;
- builder/provider detection;
- page/post/CPT/taxonomy inventory;
- slugs, URLs, statuses, authors and revisions/fingerprints;
- menu/navigation inventory;
- internal-link graph and unresolved targets;
- public URL inventory;
- media/alt/context signals;
- current permalink and redirect-runtime state;
- SEO/GEO output-authority state;
- canonical/indexability/Schema/discovery evidence;
- current environment identity and clone/source-domain leakage;
- supported operation capabilities;
- recent operation summaries.

Site Intelligence should return machine-readable data suitable for remote orchestration, not merely wp-admin presentation.

## 2. Output Authority Resolver

Before Manager writes any SEO/GEO signal it resolves the accepted owner for that surface.

Surfaces include:

- title/meta description;
- canonical;
- robots/indexability;
- Open Graph/social metadata;
- hreflang;
- Schema graph;
- sitemap extensions;
- redirects;
- optional discovery surfaces.

Possible ownership states include:

- theme-native;
- manager-native where explicitly supported;
- external-provider adapter;
- wordpress-core;
- manual-review;
- blocked-conflict.

No overlapping signal is written while ownership is ambiguous.

The resolver does not decide the strategic content. It decides whether/how a proposed external instruction may safely be represented in the current WordPress stack.

## 3. Preview / Change-set Core

Before every meaningful mutation, Manager should be able to preview the exact bounded effect.

A preview may contain:

- resource identities;
- current revision/fingerprint;
- fields/model slots that would change;
- before/after summaries or policy-safe values;
- URL/slug implications;
- output-authority implications;
- collisions/conflicts;
- stale-state risk;
- required capability;
- required confirmation;
- expected resulting fingerprint;
- whether public verification is available.

Preview is not approval and not execution.

## 4. Execute / Apply

Manager performs approved local mutations using WordPress/product APIs.

Supported operation families may include:

- create/update WordPress pages;
- create/update normal WordPress posts;
- create/update Theme semantic model data;
- edit contract-defined Theme structured slots;
- write supported SEO-provider fields through accepted adapters;
- update navigation/internal links;
- bind media/featured media/alt metadata under policy;
- update approved taxonomy relationships;
- perform controlled slug/permalink/redirect operations when required;
- apply other explicitly modeled WordPress configuration changes.

No generic unrestricted shell/file-system mutation API is part of the product contract.

Every mutation should use the safest available combination of:

- capability checks;
- nonce/authentication;
- idempotency;
- expected revision/fingerprint;
- environment binding;
- explicit confirmation for high-impact writes;
- bounded operation data;
- exact post-write verification.

## 5. Publish / Schedule

Manager exposes WordPress publication controls needed by external orchestration:

- draft creation;
- previewable state;
- explicit approval path;
- schedule;
- publish;
- update/refresh;
- unpublish/status change where authorized;
- multilingual relationship data where supported.

Manager-created blog content must materialize as normal WordPress posts and remain editable by authorized client users in Gutenberg.

## 6. Verify

Manager separates "WordPress accepted the write" from "the resulting public surface works".

Verification may include:

- exact stored value/model check;
- exact revision/fingerprint check;
- semantic-model validation;
- exact same-site permalink;
- bounded rendered HTTP verification;
- canonical/indexability/output-authority consistency;
- redirect/URL result where relevant;
- structured operation status.

Rendered verification must remain bounded, same-site and privacy-safe. Response bodies are not retained merely for verification.

## 7. Operation history and rollback

Manager keeps private operation state and bounded operator summaries.

Operation records may include:

- operation ID/type;
- resource identities;
- environment binding;
- changed fields/model slots;
- timestamps;
- verification state;
- rollback eligibility;
- private previous values where required for rollback;
- stale-state conditions.

Operator summaries must not expose secrets or unnecessary raw private content.

Rollback must never silently overwrite a newer human/client change. Stale-safe rollback is mandatory for reversible operations.

## 8. Capability/adapters layer

Manager should advertise what the current client WordPress can safely do.

Potential adapters include:

- SEO providers: Yoast, Rank Math, AIOSEO;
- editors/builders where explicitly supported;
- multilingual providers;
- WooCommerce/business systems;
- forms/analytics/consent/cache detection and selected operations;
- SEO/GEO Theme semantic models.

Detection is not compatibility. A provider becomes writable only after its adapter contract is accepted.

---

# Theme-owned strategic content models

When Theme + Manager are both active, strategic resources use versioned semantic models.

Examples:

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

Manager must be able to read/write accepted model data without knowing the Theme renderer's CSS grid or Gutenberg nesting.

External orchestration prepares the content/model intent.

Manager validates/persists it.

Theme renders it.

---

# Blog/editorial interoperability

Automated blog work remains a first-class **orchestration workflow**, but content generation/research logic is external.

Preferred flow:

```text
external topic/research/brief
        -> full article + sources + metadata intent + link plan
        -> Manager validates target/current state
        -> Manager creates normal WordPress draft
        -> optional human edit in Gutenberg
        -> Manager schedules/publishes
        -> Theme renders article shell when active
        -> Manager verifies
        -> external orchestration later decides refresh
```

Manager needs strong primitives for this flow; it does not need to contain the strategic/research brain that invented the article.

The editable body should use a minimal stable WordPress/Gutenberg-compatible representation rather than preset-specific visual layout markup.

---

# External orchestration contract

External orchestration may use Manager to implement higher-level workflows such as:

## Build / Finish

External orchestration:

- requests real site state;
- reasons about missing/incomplete content;
- prepares fixes;
- drives preview/apply/verify loops until launch readiness.

Manager provides the evidence and execution primitives.

## Optimize

External orchestration:

- analyzes intent, entities, content quality, SEO/GEO, links, search data and opportunities;
- prepares bounded optimization changes;
- uses Manager to preview/apply/verify them.

Manager does not independently choose optimization strategy.

## Grow

External orchestration:

- chooses new landing/article opportunities;
- creates content;
- plans clusters/internal links;
- interprets Search Console/Bing/analytics;
- decides refresh priorities;
- uses Manager to create/update/publish/verify the resulting WordPress changes.

These are **operating modes of the external system using Manager**, not proof that the plugin must contain autonomous Landing/Blog/Growth intelligence engines.

---

# Authentication and remote control

Remote orchestration must use an accepted authentication path such as WordPress Application Passwords over HTTPS or another explicitly accepted scoped mechanism.

Every mutation surface requires:

- authenticated identity;
- least-privilege capability check;
- explicit resource/action scope;
- idempotency for create/update operations where relevant;
- validation before mutation;
- revision/fingerprint guard;
- operation record.

Browser-admin writes also require WordPress nonce protection.

Credentials:

- are never committed to GitHub/content;
- are never included in reports;
- must be revocable;
- should use the safest available deployment mechanism.

---

# Privacy model

- Inspection defaults to metadata/fingerprints rather than unnecessary raw private-content export.
- External orchestration receives only data needed for the requested workflow.
- Client/customer/order/form records are outside the generic content-operation contract unless a separate integration explicitly requires them.
- Telemetry is opt-in.
- Verification does not persist full response bodies.

---

# Performance model

Manager must not turn public WordPress into a remote-rendered application.

Principles:

- Manager is control plane;
- public HTML renders from WordPress-local state;
- Theme renderer executes locally/server-side;
- remote intelligence is not required for page delivery;
- background work is bounded and observable;
- caches are invalidated only for affected surfaces.

---

# wp-admin UI role

The authenticated SEO/GEO Manager screen is a **diagnostic, safety and local-operator surface**.

It may expose:

- connection/health state;
- Site Intelligence;
- capability discovery;
- local preview/confirmation for risky changes;
- operation history;
- rollback;
- technical evidence download;
- authentication/setup information.

It is not intended to become the only place where all research, content strategy and creation happen.

The primary advanced workflow is remote orchestration calling the Manager API.

---

# Migration boundary

SEO/GEO Migration Bridge remains a separate sellable product.

The previous idea that its complete migration capability must eventually be absorbed into Manager is superseded by `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

Manager may:

- inspect migration results;
- repair bounded post-migration issues;
- verify URLs/redirects;
- operate the rebuilt site after handoff.

Manager does not become the default home for clone/export/import/rescue/reset/cutover product workflows.

Shared low-level code is allowed where useful; product ownership remains separate.

---

# Definition of done for first stable Manager release

A first stable Manager release requires:

- independent install/activate/upgrade/rollback;
- WordPress/PHP support matrix;
- safe authentication/scoped remote access;
- Site Intelligence accepted on representative Theme and non-Theme sites;
- current-environment identity/fingerprint;
- output-authority resolver;
- generic Preview -> Apply -> Verify -> Rollback primitives;
- idempotent create/update behavior;
- expected-revision/fingerprint stale protection;
- normal WordPress page/post draft/update/publish operations;
- Theme semantic-model read/write support;
- at least one accepted generic/no-provider SEO authority path plus Theme coexistence;
- operation history/evidence;
- EN/ES operator/safety UX;
- no duplicate SEO/GEO output;
- real-site acceptance on at least one Theme site and one supported non-Theme WordPress site.

The first stable Manager does **not** require an embedded autonomous keyword strategist, copywriter, competitor-research engine or Search Console opportunity brain.

Those higher-level capabilities are external orchestration workflows that use the stable Manager primitives.

---

# Current development priority

After closing the minimum EMMAKE field URL/301 safety case, Manager development must stop expanding migration/permalink intelligence as the center of the product.

Priority becomes:

1. stabilize the remote Site Intelligence contract;
2. stabilize authenticated capability discovery;
3. stabilize generic Preview/Apply/Verify/Rollback APIs;
4. complete Theme semantic-model operations;
5. complete normal WordPress post/page publication operations;
6. complete media/navigation/internal-link primitives;
7. complete output-authority/provider adapters;
8. prove external-orchestration workflows for Build/Finish, Optimize and Grow on real client sites.

EMMAKE `/nuevaweb/` is the first field target, not a client-specific architecture.
