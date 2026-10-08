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

Manager 0.2.1 also exposes an explicit environment write contract inside `environment`:

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
- read-only SEO output-authority candidates;
- aggregated Build / Finish readiness by structure, content, navigation, SEO authority, media, frontend verification and operations.

A high-confidence environment leakage candidate maps to a real local WordPress resource but escapes the installation's current `home_url()` path. This is the EMMAKE `/nuevaweb/` production-link acceptance case.

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

### `GET /changes/{operation_id}`

Reads one Manager operation when the current user can still edit the target. New operations include the environment contract they were applied under.

### `POST /changes/{operation_id}/rollback`

Restores only fields changed by that Manager operation. Rollback is blocked if the current fingerprint differs from the recorded post-apply fingerprint, protecting newer human/plugin changes.

Production rollback also requires the current `environment_fingerprint` and refuses an operation that is not bound to the same environment. This prevents operation metadata copied during a clone/migration from being replayed blindly against a different site.

## Safety invariants

1. Inspect before mutate.
2. No hardcoded production domain for internal resource identity.
3. Production mutations require current-environment acknowledgement.
4. Draft-first baseline.
5. Idempotent writes.
6. Optimistic concurrency.
7. Bounded rollback.
8. One accepted SEO authority per public output.
9. No fabricated proof/facts.
10. Content is not layout on Theme-owned strategic surfaces.
11. Normal blog posts remain normal WordPress posts.

## Authentication

For MVP automation use normal WordPress REST authentication with an authorized WordPress user, preferably Application Passwords over HTTPS. Authorization remains capability-based. Secrets are never stored in content manifests or committed to GitHub.

## Next implementation slices

- Theme structured-model write adapter;
- SEO Output Authority Resolver adapters;
- creation manifests for new draft pages/posts;
- operation history/admin surface;
- rendered verification after accepted writes;
- M3 SEO/GEO Optimizer;
- M4 Landing Engine;
- M5 Blog Engine;
- M6 Search Console/Bing/analytics growth loop.
