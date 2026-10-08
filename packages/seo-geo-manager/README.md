# SEO/GEO Manager

SEO/GEO Manager is the independent WordPress control plane for **finishing, optimizing and growing** a site throughout its lifecycle.

It is deliberately separate from both the Theme and Migration Bridge:

- **SEO/GEO Theme** owns rendering, design, semantic HTML, accessibility and frontend performance.
- **SEO/GEO Migration Bridge** owns scan/clone/rescue/reset-first migration workflows.
- **SEO/GEO Manager** owns site intelligence, Build / Finish operations, SEO/GEO optimization, content operations, publishing, revisions, rollback and growth.

The architecture follows:

- `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`
- `docs/CONTENT_PUBLISHING.md`
- `docs/SEO_GEO_MANAGER_MVP.md`
- `docs/SEO_GEO_MANAGER_PRODUCT_MODES.md`

## Product modes

### Build / Finish

For a new site or recently migrated site. Manager inventories the real WordPress state, detects missing/incomplete strategic content, clone-domain leakage, broken internal navigation, metadata gaps, missing internal links and launch-readiness blockers. It then prepares bounded changes that can be previewed, applied, verified and rolled back.

Typical flow:

```text
new site:      SEO/GEO Theme -> Manager Build / Finish -> launch -> Optimize / Grow
migrated site: Migration Bridge -> SEO/GEO Theme -> Manager Build / Finish -> launch -> Optimize / Grow
```

EMMAKE `/nuevaweb/` is the first reference target for this mode.

### Optimize

For an existing site. Manager analyzes intent, entities, headings, content completeness, internal linking, metadata, Schema consistency, GEO signals, stale content and duplicate/cannibalization candidates, then prepares controlled improvements.

### Grow

For continuous operation after launch. Manager creates/refreshes landings and blog content, maintains clusters/internal links, schedules/publishes under policy and later consumes Search Console, Bing, analytics and other accepted signals to prioritize the next iteration.

## MVP direction

The first MVP is designed so an agency workflow, ChatGPT session, CI job or future hosted controller can safely work with a WordPress site without hardcoding its production domain or directly editing the database.

Initial foundations:

1. bounded public health endpoint;
2. authenticated content inventory and resource read endpoint;
3. stable resource fingerprint for optimistic concurrency;
4. WordPress-generated permalinks so clones/staging installations never inherit hardcoded production navigation;
5. WordPress Application Password compatible authentication through normal REST authentication;
6. capability checks on every non-public operation;
7. bounded site snapshot for Build / Finish and launch-readiness analysis;
8. resource inventory and internal-link graph;
9. clone/current-environment leakage detection;
10. orphan-page and unresolved internal-path review signals.

## API v1

Base namespace:

`/wp-json/seo-geo-manager/v1`

### `GET /health`

Public, bounded service check. It exposes only service name and plugin version.

### `GET /content`

Authenticated. Requires `edit_posts`.

Supported query arguments:

- `type` (default `page`)
- `status` (default `any`)
- `search`
- `per_page` (1-100)
- `page`

Returns normalized WordPress resources, current-environment permalinks and a deterministic fingerprint.

### `GET /content/{id}`

Authenticated. Requires permission to edit the requested resource.

Returns the normalized resource plus raw editable body/excerpt and identity fields required by the future preview/diff/update pipeline.

### `GET /site/snapshot`

Authenticated. Requires `edit_posts`.

Returns a bounded snapshot including:

- current `home_url` and `site_url`;
- WordPress/language/permalink environment;
- Manager/Bridge/Core presence when detectable;
- active Theme identity and SEO/GEO Theme detection;
- page/post/attachment counts;
- front-page/blog-page identity;
- menu and taxonomy counts;
- known SEO-provider detection state;
- robots and native WordPress sitemap discovery URLs.

### `GET /site/intelligence`

Authenticated. Requires `edit_posts`.

Build / Finish intelligence report including:

- bounded page/post resource inventory;
- current-environment permalink, path and logical-path mapping;
- per-resource fingerprint and body word-count signal;
- stored-content internal-link graph;
- WordPress menu link evidence;
- environment/clone leakage candidates;
- unresolved same-environment path candidates;
- published orphan-page candidates;
- bounded launch-readiness checks.

Optional query argument:

- `include_rendered=1` — fetch and scan up to 20 rendered published pages so Theme-generated links can be checked too. This is intended for final Build / Finish verification rather than every routine request.

A high-confidence leakage candidate is a link that maps to a real local WordPress resource but escapes the current WordPress `home_url()` path. This catches the EMMAKE clone case where a link on `/nuevaweb/` points to the equivalent production path instead of the clone path.

External-host path matches are reported at medium confidence and require review rather than being treated as proof of an error.

## Next endpoints

The next implementation slices add:

- `/changes/preview`;
- `/changes/apply`;
- `/changes/{operation_id}`;
- `/changes/{operation_id}/rollback`;
- `/optimize/analyze`;
- `/publish/draft`.

Writes remain draft-first and idempotent. No endpoint may silently publish, overwrite a newer human revision or create duplicate URL intent.

## Authentication

For MVP automation use WordPress REST authentication with an authorized WordPress user, preferably Application Passwords over HTTPS. Authorization is capability-based inside WordPress. Secrets are never stored in content manifests or committed to GitHub.

## Internal URL rule

Manager never stores production hostnames merely to link internal WordPress resources. Internal resource URLs are resolved from WordPress IDs/paths and the current site environment (`home_url()`, `get_permalink()`). This is mandatory for production/staging/cloned-site portability.
