# Current three-product roadmap

Status: **authoritative current execution roadmap**

Date: 2026-10-11

Canonical architecture: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

This roadmap exists to prevent implementation drift. Historical phase documentation remains useful evidence, but new work must follow this product boundary and order.

## North star

```text
SEO/GEO Migration Bridge
    = bring/rescue/transition the site

SEO/GEO Theme
    = build/render the public site

SEO/GEO Manager
    = give an authorized external operator safe control inside WordPress

External orchestration
    = strategy/research/creation/optimization/growth intelligence
```

The project targets **three independently sellable WordPress products**. External orchestration is the managed operating layer, not a fourth WordPress plugin.

---

# Global rules before any new implementation

Before opening a feature branch, classify the feature.

## Migration Bridge test

If the feature answers:

> How do we scan, clone, export/import, rescue, reset, migrate or cut over an old site?

it belongs to **Migration Bridge**.

## Theme test

If the feature answers:

> How should the public website look, render, respond, remain accessible and stay fast?

it belongs to **Theme**.

## Manager test

If the feature answers:

> How can an authorized external operator safely inspect/read/write/publish/verify WordPress state?

it belongs to **Manager**.

## External orchestration test

If the feature answers:

> What should we create/change/optimize, why, for which audience/keyword/market, and what should the content say?

it belongs to **external orchestration**.

No implementation starts until this classification is explicit.

---

# Track A — SEO/GEO Migration Bridge

## Objective

Productize the accepted migration system as a reliable standalone commercial tool.

## Scope to keep

- Scan/analyzer;
- source baseline;
- dependency graph;
- Portable Clone Engine;
- local clone;
- export;
- import;
- Rescue Manifest;
- reset-first workflow;
- sandbox isolation;
- supported migration transformations;
- SEO/GEO parity;
- cutover evidence;
- rollback/recovery evidence;
- final migration handoff/report;
- installable ZIP, versioning and operator UX.

## Scope not to add

- ongoing keyword research;
- recurring blog generation;
- continuous site optimization;
- Search Console opportunity strategy;
- post-launch content growth engine;
- Theme rendering responsibility;
- general remote WordPress control unrelated to migration.

## Commercial done condition

Migration Bridge is ready to sell when a client can install/use it for a supported migration/replatform engagement and complete the transition without requiring a third-party cloning plugin for the accepted path.

It may hand off to our Theme or another destination stack.

---

# Track B — SEO/GEO Theme

## Objective

Create one premium, fast, SEO/GEO-native frontend product with five reusable preset families.

## Current reference

Corporate/EMMAKE is the reference implementation for Theme-owned strategic rendering.

## Immediate work

1. close real Corporate field acceptance on `/nuevaweb/`;
2. freeze the semantic model -> renderer contract;
3. verify no Gutenberg constrained-layout dependency owns strategic composition;
4. verify desktop/tablet/mobile rendering;
5. verify semantic heading/link/accessibility behavior;
6. verify performance budgets;
7. verify native SEO/GEO output authority;
8. productize Theme installation/update/rollback.

## Then generalize presets

Apply the same renderer/model architecture to:

1. Corporate;
2. SaaS / Digital Product;
3. Local Pro;
4. Publisher / Editorial;
5. Ecommerce.

## Theme must own

- public strategic frontend;
- visual composition;
- design tokens/components;
- server rendering;
- responsive behavior;
- accessibility;
- performance;
- baseline SEO/GEO runtime;
- Schema/discovery output under authority contract;
- article shell.

## Theme must not own

- remote client strategy;
- keyword research;
- external growth prioritization;
- migration transport;
- Manager operation history/control plane.

## Commercial done condition

Theme is ready to sell when a clean WordPress installation can install the Theme and reach the documented baseline without mandatory SEO/GEO plugins, and the preset/rendering system is deterministic, fast and supportable.

---

# Track C — SEO/GEO Manager

## Objective

Create the generic secure bridge between external orchestration and a client's WordPress.

The Manager's commercial value is not that it contains our entire intelligence. Its value is that it gives us reliable **eyes + hands + safety** on many WordPress sites.

## C0 — Product boundary freeze

Status: **complete**.

Completed:

- Manager documented as bridge/control agent;
- no migration-product absorption requirement;
- no requirement to embed autonomous SEO/content strategy;
- Theme/Manager independence;
- external orchestration contract documented;
- documentation precedence and CI anti-drift contract established.

## C1 — Site Intelligence / eyes

Status: **implemented foundation; field acceptance continues**.

Manager exposes machine-readable current state for remote use:

- site/environment identity;
- WordPress/PHP/runtime;
- current Theme/preset/model capabilities;
- pages/posts/CPTs/taxonomies;
- IDs/slugs/URLs/statuses/authors/revisions;
- menus/navigation;
- internal links;
- media;
- SEO provider/output authority;
- canonical/indexability/Schema/discovery evidence;
- permalink/redirect state;
- operation capabilities;
- recent operation summaries.

### Exit criterion

External orchestration can understand a supported client's real current WordPress state without manual wp-admin inspection for the normal workflow.

## C2 — Authentication / capability contract

Status: **repository contract implemented; per-client HTTPS field acceptance continues — 0.3.34 capability-discovery slice implemented**.

Required:

- scoped authenticated remote identity;
- HTTPS;
- least privilege;
- revocable credentials;
- capability discovery;
- browser nonce protection;
- production-environment binding where needed;
- bounded requests/responses;
- no generic remote shell.

### Implemented from 0.3.34

- authenticated `GET /capabilities`;
- current principal ID without credential reflection;
- per-capability availability;
- required WordPress capability per family;
- semantic operation discovery;
- operation risk classification;
- actual WordPress Application Password support/availability reporting;
- explicit HTTPS/revocation/no-shell safety declarations;
- current environment safety contract;
- CI contract protecting capability-discovery semantics;
- isolated WordPress acceptance for least privilege, Application Password transport and revocation.

### Remaining before field closure

- real HTTPS Application Password connection against each accepted client/sandbox installation;
- dedicated least-privilege remote operator acceptance on that installation;
- credential revocation acceptance on that installation;
- retained non-secret connection evidence.

### Exit criterion

We can connect to a client WordPress safely enough to use it as an operational API rather than sharing full admin credentials for every routine task.

## C3 — Generic change-set engine / hands

Status: **substantial generic foundation implemented; incremental resource adapters continue**.

Required generic contract:

```text
Inspect current resource
 -> Preview proposed change
 -> Validate permissions/collisions/current fingerprint
 -> Explicit/policy approval
 -> Apply
 -> Verify stored state
 -> optional rendered verification
 -> Record operation
 -> rollback when supported
```

Required safety:

- idempotency;
- expected revision/fingerprint;
- stale-state rejection;
- deterministic resource identity;
- exact changed-field/model-slot reporting;
- bounded previous-value capture where rollback needs it.

### Exit criterion

External orchestration can safely change supported WordPress resources without a custom endpoint for every client-specific idea.

## C4 — WordPress content operations

Status: **complete at repository-contract level through 0.3.46**.

Required primitives:

- create/update page;
- create/update post;
- draft;
- schedule;
- publish;
- status changes under policy;
- excerpt/title/body;
- author binding;
- categories/tags under policy;
- featured media/reference operations;
- safe multilingual relationship operations where supported.

### Accepted slices

#### 0.3.35 — normal post/page lifecycle

Repository acceptance covers:

- normal post creation;
- normal page creation;
- draft-first operation;
- exact slug-collision rejection;
- idempotent create replay;
- publish now;
- future scheduling;
- expected-fingerprint publication protection;
- per-post-type WordPress capability checks.

Generic change sets continue to own bounded edits of existing title/slug/excerpt/content fields.

#### 0.3.44 — existing-term taxonomy assignment

Repository acceptance covers a generic `resource_id + taxonomy` contract:

```text
Inspect assignment
 -> Preview exact replacement
 -> validate existing term IDs + permissions + fingerprint
 -> explicit approval for published target
 -> Apply
 -> exact readback verification
 -> operation evidence
 -> stale-safe Rollback
```

Properties:

- registered taxonomies only;
- normal posts/pages only for this slice;
- current principal must have `edit_post(resource)` plus the taxonomy's real `assign_terms` capability;
- existing term IDs only;
- no implicit term creation;
- deterministic relationship fingerprint;
- production environment binding;
- idempotent Apply;
- rollback re-checks current permission;
- newer human/automation edits block rollback;
- taxonomy operations do not rewrite `post_content`.

Repository acceptance proves both `category` and `post_tag` while keeping the implementation generic.

Detailed contract: `docs/SEO_GEO_MANAGER_0.3.44_TAXONOMY_ASSIGNMENT.md`.

#### 0.3.45 — existing-user author binding

Repository acceptance covers a generic `resource_id + author_id` relationship contract:

```text
Inspect current author
 -> Preview existing eligible WordPress author
 -> validate resource + destination user + post-type permissions + fingerprint
 -> explicit approval for published target
 -> Apply
 -> exact readback verification
 -> operation evidence
 -> stale-safe Rollback
```

Properties:

- normal posts/pages only;
- existing WordPress users only;
- no implicit user/persona creation;
- author read API exposes only ID/existence/display name;
- destination author must be eligible for the target post type;
- assigning another user requires the target post type's real `edit_others_posts` capability;
- deterministic author-relationship fingerprint;
- production environment binding;
- idempotent Apply;
- rollback re-checks current permission/previous author eligibility;
- newer human/automation author edits block rollback;
- author operations do not rewrite body/title/status/category/tag state.

Repository acceptance proves operator != public author, page/post capability awareness, Subscriber rejection and least-privilege Author rejection when `edit_others_posts` is absent.

Detailed contract: `docs/SEO_GEO_MANAGER_0.3.45_AUTHOR_BINDING.md`.

#### 0.3.46 — native translation relationship groups

Repository acceptance closes the multilingual relationship gap through the existing native Core authority:

```text
Inspect complete reciprocal group
 -> Preview full language-to-resource relationship
 -> validate configured languages/resources/provider authority/fingerprint
 -> explicit approval for published targets
 -> Apply through NativeTranslationGroupWriter
 -> exact readback verification
 -> operation evidence
 -> stale-safe Rollback
```

Properties:

- Core remains storage/public multilingual authority;
- Manager never writes raw `_seo_geo_translation_*` metadata directly;
- configured language codes only;
- existing eligible resources only;
- all members use one compatible post type;
- complete reciprocal group replacement, not independent one-way links;
- deterministic relationship fingerprint;
- production environment binding;
- idempotent Apply;
- exact provider verification;
- newer valid provider edits block rollback;
- resource-level least privilege;
- raw provider metadata is not exposed by Manager responses/evidence;
- relationship plumbing never rewrites translated `post_content`.

Repository acceptance additionally proves the public-authority boundary: a relationship containing a draft member stays out of public registry output; once every member is published, the same Manager-created relationship becomes public-ready and resolves through the real `NativeTranslationRegistry`.

Detailed contract: `docs/SEO_GEO_MANAGER_0.3.46_NATIVE_TRANSLATION_GROUPS.md`.

### C4 closure

The generic content path now has repository-accepted foundations for:

- create/update normal content;
- draft/publish/schedule lifecycle;
- title/excerpt/body field changes;
- featured-media/reference operations;
- taxonomy assignment;
- existing-user author binding;
- safe native multilingual relationship plumbing.

External orchestration continues to own:

- language/market selection;
- translation strategy;
- translated content generation;
- localization decisions;
- hreflang/SEO strategy reasoning.

C4 is therefore **closed at repository-contract level**. Do not keep adding content endpoints merely because another operation can be imagined. Any additional provider-specific multilingual support must be justified as an explicit adapter and preserve the single-authority rule.

### Exit criterion

**Met at repository-contract level.** External orchestration can create and manage a normal WordPress article end to end through Manager while preserving WordPress identity, permissions and editorial autonomy.

Field/commercial readiness still depends on C2/C9 acceptance and on the remaining Manager tracks below.

## C5 — Theme semantic-model operations

Status: **complete at repository-contract level**.

Accepted evidence covers:

- discovery of supported model/version contracts from the active Theme preset;
- reading the current resolved model, resource, slots, values and fingerprint;
- Preview of bounded semantic changes;
- validation of declared models, writable slots, slot types and verification prerequisites;
- Apply through the existing Theme semantic contract rather than CSS/Gutenberg layout internals;
- exact stored-state readback and verification;
- idempotent operation handling;
- same-site bounded rendered verification;
- exact rollback while the applied fingerprint remains current;
- stale rollback rejection after a newer human edit.

The accepted implementation is represented by `ThemeModelReader`, `ThemeStructuredContentAdapter`, the Theme-model read acceptance and structured-content acceptance fixtures. External orchestration does not need to know the Theme's CSS grid, Gutenberg group nesting or breakpoint implementation.

### Exit criterion

**Met at repository-contract level.** External orchestration can create/update a Theme-owned strategic page through versioned semantic models without knowing Gutenberg layout trees or CSS implementation.

Do not expand C5 into a frontend builder. New model/slot types belong to the Theme contract first and Manager only gains matching safe control after that contract exists.

## C6 — Navigation, internal link and media primitives

Status: **complete at repository-contract level through the accepted 0.3.37–0.3.43 slices**.

Accepted evidence covers:

- Site Intelligence discovery of navigation/internal-link/media state;
- clone/source-host leakage detection;
- bounded navigation-menu correction with exact occurrence accounting;
- current-environment-only navigation destinations;
- explicit approval for public navigation writes;
- idempotent navigation Apply and exact rollback;
- bounded contextual-link reads/writes with real WordPress target identity;
- target/fingerprint verification and stale protection;
- privacy-safe rollback snapshots for link operations;
- media identity by attachment ID;
- media `alt` update under policy;
- title/caption/description context update under policy;
- direct validated image upload with MIME/hash/size/dimension constraints;
- no remote-URL media fetcher;
- featured-media inspect/bind/verify/rollback;
- Theme strategic-media inspect/bind/verify/rollback through the public Theme contract;
- no fabricated EXIF/media metadata and no raw metadata exposure.

Repository acceptance proves navigation Preview -> explicit approval -> Apply -> idempotent replay -> environment-bound operation -> Rollback. Dedicated link/media fixtures prove contextual-link writes, media reads, alt/context writes, image upload, featured media and strategic Theme media.

### Exit criterion

**Met at repository-contract level.** External orchestration can execute real internal-link/navigation/media plans safely on supported WordPress resources.

Do not add more media/link operations by default. Additional destructive binary replacement or provider-specific media behavior requires a separately justified contract.

## C7 — SEO output authority/provider adapters

Status: **active remaining functional track**.

Required:

- resolve owner for each public SEO/GEO surface;
- Theme-native coexistence;
- generic/no-provider accepted path;
- accepted provider adapters incrementally;
- no duplicate canonical/robots/hreflang/Schema/Open Graph/sitemap output.

### Exit criterion

External orchestration can request metadata/indexability/Schema intent changes without creating conflicting public output.

### Immediate rule

Do not build many provider adapters speculatively. Audit the already-accepted output-authority resolver and identify the **minimum first-field write path** required for supported Theme and non-Theme stacks while preserving one public owner per signal.

## C8 — Operation history/evidence/rollback

Status: **complete at repository-contract level**.

Accepted evidence covers:

- private per-operation detail through operation IDs;
- bounded `/operations` operator summaries;
- status and verification-state reporting;
- rollback eligibility/state refresh after reversal;
- adapter-specific stale-safe rollback;
- environment-bound rollback where required;
- scoped visibility so normal editors do not receive site-wide administrative operations;
- privacy-safe summaries that omit previous/private content, payload hashes and environment fingerprints;
- machine-readable REST/JSON evidence;
- admin diagnostic JSON copy/download controls for supported evidence surfaces.

`seo-geo-manager-operation-history-acceptance.php` proves the privacy boundary, editor/admin scope filtering and rollback-state refresh. The accepted resource adapters prove stale-safe rollback according to their individual contracts.

### Exit criterion

**Met at repository-contract level.** Meaningful supported remote mutations are auditable and recoverable according to their operation contracts.

Do not turn history into a second analytics platform. New operation types must plug into the existing evidence/privacy/rollback conventions.

## C9 — Client-agnostic field acceptance

Status: **remaining field/commercial track**.

Must prove Manager on at least:

- one SEO/GEO Theme site;
- one supported non-Theme WordPress site.

EMMAKE is the first Theme-site reference, not a hardcoded special case.

### Exit criterion

The same Manager package operates both classes without client-specific code forks.

---

# Remaining Manager path to field/commercial readiness

With C4, C5, C6 and C8 closed at repository-contract level, do not reopen them without a demonstrated defect or an explicitly justified provider/field requirement.

The remaining path is deliberately narrow:

1. **C7** — close the minimum SEO output-authority/write-provider path required by the first supported field stacks without duplicate public output;
2. **C9** — prove the same Manager package on one SEO/GEO Theme site and one supported non-Theme WordPress site without forks;
3. **C2 field closure** — retain real HTTPS + dedicated least-privilege Application Password + revocation evidence on accepted field installations;
4. re-evaluate C1/C3 field evidence only where C9 exposes an actual gap.

This is the shortest path to a commercially usable Manager. It is not a reason to add autonomous SEO/content intelligence inside WordPress.

---

# External orchestration workflows after Manager primitives

These workflows are where our intelligence creates commercial value.

They are not a requirement to embed large AI engines inside Manager.

## Workflow O1 — Build / Finish

```text
request site state
 -> inspect through Manager
 -> external reasoning
 -> create/fix content/SEO/link/media plan
 -> Manager preview
 -> apply
 -> verify
 -> repeat until launch-ready
```

EMMAKE is the first reference.

## Workflow O2 — Optimize

```text
Manager state + search/analytics evidence
 -> external SEO/GEO/content analysis
 -> prioritized bounded changes
 -> Manager preview/apply/verify
 -> measurement
```

## Workflow O3 — Strategic landing creation

```text
external research/brief/content/model
 -> Manager duplicate/capability checks
 -> Manager preview
 -> Manager persists Theme semantic model/resource
 -> Theme renders
 -> Manager verifies
```

## Workflow O4 — Automated blog publication

```text
external topic/research/full article
 -> Manager creates normal WP draft
 -> optional human Gutenberg edit
 -> Manager schedule/publish
 -> Theme renders article shell when active
 -> Manager verifies
```

## Workflow O5 — Growth loop

```text
Search Console/Bing/analytics/market data
 -> external opportunity prioritization
 -> landing/article/refresh/link plan
 -> Manager execution
 -> measurement
 -> next iteration
```

---

# EMMAKE immediate decision

The existing URL/permalink/slug work remains valid because launch readiness requires preserving search equity safely.

However:

- it is a bounded field-safety slice, not the product center;
- do not keep expanding historical migration modeling inside Manager merely because another edge case can be imagined;
- close the real EMMAKE transition with the already-built guarded machinery;
- preserve reusable safety primitives;
- send new migration/rescue/clone requirements to Migration Bridge;
- continue Manager through generic remote-control capabilities.

0.3.33 remains the final migration-specific Field Gate UI correction. 0.3.34 starts the generic remote-control capability line.

---

# Commercial packaging target

The repository should ultimately produce three independently versioned installable artifacts:

```text
seo-geo-migration-bridge-x.y.z.zip
seo-geo-theme-x.y.z.zip
seo-geo-manager-x.y.z.zip
```

They may be sold:

- separately;
- as migration + redesign bundle;
- as Theme + managed operations bundle;
- as complete Bridge + Theme + Manager stack;
- Manager-only for supported existing WordPress clients.

External orchestration/services may be commercialized separately as managed SEO/GEO/content/growth operations.

---

# Anti-drift review

Before merging any substantial new feature, reviewers must answer:

1. Which of the three products owns it?
2. Is it actually intelligence that belongs outside WordPress?
3. Does it hardcode a field-client workaround instead of creating a reusable contract?
4. Does it duplicate a responsibility another product already owns?
5. Does it make WordPress frontend rendering depend on a remote control service?
6. Does it weaken preview/idempotency/verification/rollback safety?
7. Does it preserve independent installability?

If ownership cannot be answered cleanly, stop and re-evaluate before implementation.