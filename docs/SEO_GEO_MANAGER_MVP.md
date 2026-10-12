# SEO/GEO Manager MVP

## Why this starts now

The Corporate field pilot has reached the point where Theme iteration is no longer the fastest way to improve the real site. The next leverage layer is the tool that can inspect, create, modify, optimize, publish and refresh WordPress content repeatedly while the Theme and Migration Bridge continue toward their own MVP closures.

SEO/GEO Manager therefore becomes an independent product/workstream now rather than waiting for Theme/Migration polish to finish.

## Product boundary

The three products remain separate:

| Product | Primary responsibility |
| --- | --- |
| SEO/GEO Theme | frontend, preset renderers, semantic HTML, responsive design, accessibility, performance |
| SEO/GEO Migration Bridge | scan, clone, rescue, reset-first migration, cutover support |
| SEO/GEO Manager | site intelligence, Build / Finish, optimization, publishing, internal linking, revision control and growth loop |

Manager may consume output from Migration Bridge and render through SEO/GEO Theme, but it must also remain usable on a normal WordPress site through bounded adapters.

## Core operator experience

The target workflow is intentionally similar to the development loop used for Kairoseth and IA Empleado:

```text
request / opportunity / task
        ↓
inspect current real state
        ↓
prepare bounded change set
        ↓
preview diff
        ↓
apply to draft or approved target
        ↓
verify public/staging result
        ↓
iterate quickly
```

The operator should be able to say things such as:

- improve the Services page for a target intent;
- create a new landing for a real service;
- publish today's article as a draft;
- refresh an old article using verified current sources;
- repair internal links after a clone/domain change;
- optimize title/meta/schema intent without duplicating the active SEO provider;
- add media to a strategic slot;
- identify thin/orphaned pages and propose changes;
- create a content cluster and wire the internal links;
- schedule or publish only after the configured approval policy allows it.

## Non-negotiable principles

1. **Inspect before mutate.** Manager reads the current WordPress resource and fingerprint before proposing changes.
2. **No hardcoded production domain.** Internal URLs are resolved from WordPress resource identity and the current environment.
3. **Draft-first baseline.** Direct publication is explicit privileged policy, not the default.
4. **Idempotent writes.** Retrying an operation cannot create duplicate content.
5. **Optimistic concurrency.** Stale automation cannot overwrite a newer human edit.
6. **Rollback is bounded.** Restore Manager-owned changes only.
7. **One SEO authority per output.** Canonical, robots, Schema, hreflang, OG and sitemap ownership must be resolved before mutation.
8. **No fabricated proof.** Clients, reviews, metrics, addresses, certifications, prices and results require provenance.
9. **Content is not layout.** Theme-owned strategic pages receive structured semantic content, not generated layout trees.
10. **Normal posts remain normal WordPress posts.** Blog content stays editable in Gutenberg.

## MVP slices

### M0 — Control-plane foundation

Goal: connect safely to the real WordPress state.

Deliverables:

- standalone `seo-geo-manager` plugin package;
- `GET /health`;
- authenticated `GET /content` inventory;
- authenticated `GET /content/{id}`;
- stable resource fingerprint;
- current-environment permalink resolution;
- WordPress Application Password compatible authentication;
- capability-based authorization.

Acceptance:

- a clone/staging site reports its own URLs, never production URLs merely because source content was cloned;
- unauthorized content reads fail;
- content IDs/slugs/status/title/body can be inspected without database access;
- the same plugin can be installed independently of Migration Bridge.

### M1 — Site Intelligence + snapshot

Goal: understand the site before changing it.

Deliverables:

- `/site/snapshot`;
- `/site/intelligence`;
- pages/posts/taxonomies/media inventory;
- active Theme/preset and Manager/Bridge/Core version detection;
- SEO provider detection;
- canonical/indexability/schema authority map;
- internal-link graph summary;
- broken/unresolved internal URL candidate detection;
- clone/current-environment leakage detection;
- orphan/thin/duplicate-intent candidate signals;
- current sitemap/discovery surface inventory;
- bounded Build / Finish launch-readiness checks;
- optional rendered-page scan for Theme-generated navigation/link verification.

The scanner must remain bounded: it reports signals and evidence, not invented diagnoses.

#### M1 implementation status

Implemented on `feat/seo-geo-manager-mvp`:

- environment/product/Theme/content snapshot;
- SEO provider and sitemap discovery signals;
- bounded page/post resource inventory;
- current permalink + path + logical-path mapping;
- stored-content internal-link graph;
- WordPress menu link evidence;
- high-confidence same-host clone-path leakage detection;
- medium-confidence external-host/local-path leakage candidates;
- unresolved same-environment content-path candidates;
- published page orphan candidates;
- optional rendered scan of up to 20 public pages;
- first launch-readiness result with blocker/warning/pass evidence.

Still required before M1 closure:

- Theme preset/model awareness for required strategic surfaces and slots;
- output-authority map beyond provider presence;
- media inventory/detail signals;
- duplicate-intent/cannibalization candidate model;
- bounded content-completeness signals that understand Theme-owned structured content rather than only editor body content.

### M2 — Change Set / Preview / Apply

Goal: make controlled edits reproducible.

Deliverables:

- versioned change-set schema;
- `/changes/preview` dry run;
- field-level diff;
- idempotency key;
- expected fingerprint;
- `/changes/apply`;
- operation log;
- previous revision/change reference;
- rollback endpoint;
- post-apply verification.

Initial supported mutations:

- title;
- slug with collision guard;
- excerpt;
- body/editorial content;
- status draft/pending;
- selected Manager-owned metadata;
- structured Theme content slots where the active renderer supports them.

Publication remains draft-first.

### M3 — SEO/GEO Optimizer

Goal: convert inspection + change sets into measurable content optimization.

Deliverables:

- page intent/keyword/entity brief;
- title/meta recommendations and accepted writes;
- heading/answer-first/entity clarity analysis;
- internal-link recommendations;
- structured-data fact consistency checks;
- media/alt/context opportunities;
- duplicate/cannibalization signals;
- local/GEO checks without doorway generation;
- content completeness scoring based on declared intent rather than keyword density.

Manager must use the Output Authority Resolver before writing any public SEO signal.

### M4 — Landing Engine

Goal: create strategic pages at the speed of the Kairoseth/IA Empleado development loop.

Deliverables:

- service/solution landing model;
- local/location model;
- campaign model;
- hub/cluster model;
- Theme renderer binding;
- preview before creation;
- duplicate intent/path checks;
- media slot binding;
- internal-link plan;
- draft creation + verification.

When SEO/GEO Theme is active, Manager sends structured semantic content to a versioned renderer. It does not generate arbitrary Gutenberg visual layouts.

### M5 — Blog Engine

Goal: daily/recurring editorial production with normal WordPress ownership.

Deliverables:

- brief → article draft pipeline;
- sources/provenance storage;
- outline/headings/body/excerpt;
- featured media reference;
- approved category/tag policy;
- internal links to relevant strategic pages;
- author binding;
- SEO/GEO intent;
- normal Gutenberg-compatible post materialization;
- schedule/publish policy;
- later refresh with expected fingerprint.

### M6 — Growth loop integrations

Goal: prioritize work from real signals.

Later connectors:

- Google Search Console;
- Bing Webmaster Tools;
- analytics provider;
- optional keyword/competitive data providers;
- sitemap/indexing verification.

These integrations feed opportunity detection. They do not become frontend runtime dependencies.

## Suggested UI

WordPress admin remains useful for client visibility, but the canonical automation interface is the versioned REST contract.

A small WordPress admin surface should eventually expose:

- connection/status;
- site snapshot health;
- pending Manager drafts;
- recent operations;
- rollback actions;
- approval policy;
- SEO authority/provider status;
- API/Application Password setup guidance.

The heavy operator/controller UX can later live outside WordPress without making public rendering depend on it.

## Repository strategy

Keep Manager in the existing monorepo for the MVP because it shares contracts with Core, Theme and Migration Bridge:

`packages/seo-geo-manager/`

Once contracts stabilize, distribution can still be packaged independently as a WordPress plugin product.

Initial development branch:

`feat/seo-geo-manager-mvp`

## Immediate next microphase

Finish M1 preset/model awareness and then start **M2 Preview / Apply / Rollback** immediately. The useful loop we need is already clear: inspect the real site, identify a bounded issue, preview the exact change, apply it safely, verify the current environment and retain rollback evidence.

The Corporate pilot is the first real Manager target. One known acceptance case is clone-safe navigation: a Blog/Insights link on `/nuevaweb/` must resolve to the clone/current site rather than the production path unless an explicit external link was intentionally configured.
