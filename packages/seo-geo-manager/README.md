# SEO/GEO Manager

SEO/GEO Manager is the independent WordPress control plane for **finishing, optimizing and growing** a site throughout its lifecycle.

It remains separate from the other two products:

- **SEO/GEO Theme** owns rendering, design, semantic HTML, accessibility and frontend performance.
- **SEO/GEO Migration Bridge** owns scan/clone/rescue/reset-first migration workflows.
- **SEO/GEO Manager** owns site intelligence, Build / Finish operations, SEO/GEO optimization, controlled content mutations, publishing, revisions, rollback and growth.

Architecture documents:

- `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`
- `docs/CONTENT_PUBLISHING.md`
- `docs/SEO_GEO_MANAGER_MVP.md`
- `docs/SEO_GEO_MANAGER_PRODUCT_MODES.md`
- `docs/SEO_GEO_MANAGER_CHANGESETS.md`

## Product modes

### Build / Finish

For a new or recently migrated site. Manager inventories real WordPress state, detects incomplete strategic content, clone-domain leakage, navigation/SEO/media gaps and launch-readiness blockers, then prepares bounded changes that can be previewed, applied, verified and rolled back.

```text
new site:      SEO/GEO Theme -> Manager Build / Finish -> launch -> Optimize / Grow
migrated site: Migration Bridge -> SEO/GEO Theme -> Manager Build / Finish -> launch -> Optimize / Grow
```

EMMAKE `/nuevaweb/` is the first reference target.

### Optimize

For an existing site. Manager analyzes intent, entities, headings, content completeness, internal linking, metadata, Schema consistency, GEO signals, stale content and duplicate/cannibalization candidates, then prepares controlled improvements.

### Grow

For continuous operation after launch. Manager creates/refreshes landings and blog content, maintains clusters/internal links, schedules/publishes under policy and later consumes Search Console, Bing, analytics and other accepted signals.

## WordPress admin dashboard

Manager 0.3.2 adds the first authenticated WordPress admin surface under **SEO/GEO Manager**.

The Build / Finish dashboard uses the logged-in WordPress session and `wp-api-fetch`, so operators no longer need to open protected REST endpoints manually or construct REST nonces just to inspect the site.

The first dashboard slice provides:

- one-click **Analizar sitio** execution;
- optional rendered-frontend verification;
- overall Build / Finish readiness;
- blocker and warning counts;
- resolved SEO authority summary;
- readiness checks with bounded evidence;
- Theme/preset structure and required-content summary;
- navigation/environment leakage summary;
- raw diagnostic JSON for technical review.

The dashboard is intentionally read-only in this first slice. M2 mutation endpoints remain available through their existing safety contract; later admin iterations will expose proposal, preview, apply, verify, history and rollback without weakening those guards.

## API v1

Base namespace:

`/wp-json/seo-geo-manager/v1`

### `GET /health`

Public bounded service/version check.

### `GET /content`

Authenticated content inventory. Requires `edit_posts`.

Supported query arguments: `type`, `status`, `search`, `per_page`, `page`.

Returns normalized WordPress resources, current-environment permalinks and deterministic fingerprints.

### `GET /content/{id}`

Authenticated resource read. Requires permission to edit the target.

Returns raw editable body/excerpt plus identity and fingerprint data used by controlled change sets.

### `GET /site/snapshot`

Authenticated bounded environment snapshot: current URLs, WordPress version/language/permalinks, Manager/Bridge/Core presence, Theme identity, content counts, front/blog page identity, menu/taxonomy counts, SEO-provider detection and discovery URLs.

Manager exposes an explicit environment write contract inside `environment`:

- `type`: the standard WordPress environment type (`production`, `staging`, `development` or `local`);
- `fingerprint`: deterministic SHA-256 identity derived from environment type + current `home_url()` + current `site_url()`;
- `write_approval_required`: `true` in production, `false` otherwise.

The environment fingerprint is an acknowledgement token, not a secret. Automation must inspect the current site and echo the exact fingerprint before production mutation. If a clone, URL or environment type changes, the old fingerprint becomes stale automatically.

### `GET /site/intelligence`

Authenticated Build / Finish intelligence. It currently includes:

- bounded page/post inventory;
- current permalink/path/logical-path mapping;
- stored-content and WordPress-menu internal-link evidence;
- clone/current-environment leakage candidates;
- unresolved internal paths and published orphan-page candidates;
- optional rendered scan of up to 20 pages (`include_rendered=1`);
- active SEO/GEO Theme preset contract when available;
- expected strategic-page mapping with resolution method/confidence;
- semantic model IDs and required structured-slot completeness;
- required-any and required verified-group presence signals;
- page-level media signals and bounded library alt hygiene;
- resolved read-only SEO output authority with adapter identity/capabilities;
- aggregated Build / Finish readiness by structure, content, navigation, SEO authority, media, frontend verification and operations.

A high-confidence environment leakage candidate maps to a real local WordPress resource but escapes the installation's current `home_url()` path. This is the EMMAKE `/nuevaweb/` production-link acceptance case.

Manager 0.3.1 resolves SEO public-output ownership without writing provider metadata. It collapses free/pro variants into one provider family and recognizes Yoast, Rank Math, All in One SEO and The SEO Framework. Exactly one external family wins over the Theme fallback; multiple families are a blocker; with no external provider, the active SEO/GEO Theme becomes the resolved native authority. Title/meta, canonical, robots, Open Graph, Schema, hreflang and sitemap signals expose the same resolved owner. `safe_to_write_seo_metadata` remains `false` until that authority's write adapter is implemented and runtime-verified.

### `POST /changes/preview`

M2 read-only change-set validation and field-level diff.

Requires:

- schema v1;
- target WordPress ID;
- the target fingerprint obtained during inspection;
- one or more supported changes.

Preview checks stale state, permissions, slug collisions, destructive empty-content intent and published-target policy without writing WordPress state. It also returns the current Manager environment contract so callers can carry the inspected fingerprint into an approved apply.

### `POST /changes/apply`

M2 controlled mutation endpoint.

Apply adds:

- mandatory idempotency key;
- optimistic-concurrency enforcement;
- draft-first published-target guard;
- pre-change revision attempt;
- bounded previous-value capture;
- exact post-apply verification;
- persisted operation/rollback record;
- explicit production environment approval;
- operation-to-environment binding.

Initial fields are `title`, `slug`, `excerpt`, `content` and `status`. Baseline status writes are limited to `draft` and `pending`.

When WordPress reports `production`, callers must include `environment_fingerprint` with the exact current fingerprint returned by `GET /site/snapshot`. Missing or stale approval is rejected before the mutation engine runs. Staging/development/local environments keep all M2 safety guards but do not require this extra acknowledgement.

A currently published target is blocked unless the caller explicitly sends `allow_published_target=true` and has the post type publish capability. This remains a second, separate approval: environment approval confirms **where** the write is happening, while published-target approval confirms **what lifecycle state** is being changed.

### `POST /theme/structured/preview`

Manager 0.3.0 added the first Theme-aware write adapter. It updates **content slots only** while leaving Theme-owned layout and section structure untouched.

The request must provide:

- schema v1;
- a target page ID and current content fingerprint;
- an exact Theme `model_id` already mapped to that page by `/site/intelligence`;
- a `slots` object containing only slot IDs defined by that model.

Writable slot types in this first slice are:

- `text`: a non-empty plain-text string;
- `link`: an object with non-empty `text` and safe `url` values.

Manager refuses unknown model slots, missing/duplicate slot markers, arbitrary HTML, unsupported slot types and model/target mismatches. Preview returns a slot-level diff instead of exposing a full-page replacement diff.

For model slots that declare `requires_verification`, the caller must explicitly include the matching group in `verified_groups`. This acknowledgement does not manufacture evidence: it only lets Manager write a slot after the operator/automation workflow has already verified the evidence source.

### `POST /theme/structured/apply`

Applies the exact slot transformation previewed above through the same M2 mutation engine used by generic changes. Therefore structured writes inherit:

- current target fingerprint validation;
- idempotency;
- draft-first published-target policy;
- explicit production environment approval;
- revisions/previous-value capture;
- exact post-write verification;
- stale-safe rollback.

The stored Manager operation includes `structured_model` metadata with preset, page key, model ID, changed slot diff and verified groups. The operation is also bound to the environment where it was applied.

This endpoint does **not** accept arbitrary page HTML or layout instructions. Its purpose is to let Build / Finish hydrate SEO/GEO Theme surfaces without turning Manager into a page builder.

### `GET /changes/{operation_id}`

Reads one Manager operation when the current user can still edit the target. New operations include the environment contract they were applied under. Structured Theme writes additionally include their `structured_model` metadata.

### `POST /changes/{operation_id}/rollback`

Restores only fields changed by that Manager operation. Rollback is blocked if the current fingerprint differs from the recorded post-apply fingerprint, protecting newer human/plugin changes.

Production rollback also requires the current `environment_fingerprint` and refuses an operation that is not bound to the same environment. This prevents operation metadata copied during a clone/migration from being replayed blindly against a different site.

Structured Theme writes currently roll back by restoring the exact previous `post_content`, which preserves the pre-operation Theme composition byte-for-byte when no newer edit has intervened.

## Safety invariants

1. Inspect before mutate.
2. No hardcoded production domain for internal resource identity.
3. Production mutations require current-environment acknowledgement.
4. Draft-first baseline.
5. Idempotent writes.
6. Optimistic concurrency.
7. Bounded rollback.
8. One accepted SEO authority per public output.
9. SEO authority resolution never implies write permission; write adapters must be verified separately.
10. No fabricated proof/facts.
11. Content is not layout on Theme-owned strategic surfaces.
12. Theme structured writes accept only contract-defined slots and supported value types.
13. Normal blog posts remain normal WordPress posts.

## Authentication

For MVP automation use normal WordPress REST authentication with an authorized WordPress user, preferably Application Passwords over HTTPS. Authorization remains capability-based. Secrets are never stored in content manifests or committed to GitHub.

Inside WordPress admin, the dashboard uses the authenticated session through WordPress' own REST nonce middleware. The protected `/site/intelligence` endpoint therefore remains private while still being usable from the Manager UI.

## Next implementation slices

- dashboard proposal/preview/apply/verify/rollback controls;
- provider-specific SEO metadata write adapters, starting with the accepted field authority;
- creation manifests for new draft pages/posts;
- operation history surface;
- rendered verification after accepted writes;
- M3 SEO/GEO Optimizer;
- M4 Landing Engine;
- M5 Blog Engine;
- M6 Search Console/Bing/analytics growth loop.
