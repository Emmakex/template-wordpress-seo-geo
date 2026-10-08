# SEO/GEO Manager

SEO/GEO Manager is the WordPress control-plane endpoint for ongoing content creation, modification and optimization after a site has been rebuilt with the SEO/GEO Theme.

It is deliberately separate from both the Theme and Migration Bridge:

- **SEO/GEO Theme** owns rendering, design, semantic HTML, accessibility and frontend performance.
- **SEO/GEO Migration Bridge** owns scan/clone/rescue/reset-first migration workflows.
- **SEO/GEO Manager** owns ongoing site intelligence, content operations, SEO/GEO optimization, publishing, revisions and rollback.

The architecture follows `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md` and `docs/CONTENT_PUBLISHING.md`.

## MVP direction

The first MVP is designed so an agency workflow, ChatGPT session, CI job or future hosted controller can safely work with a WordPress site without hardcoding its production domain or directly editing the database.

Initial foundations:

1. bounded public health endpoint;
2. authenticated content inventory and resource read endpoint;
3. stable resource fingerprint for optimistic concurrency;
4. WordPress-generated permalinks so clones/staging installations never inherit hardcoded production navigation;
5. WordPress Application Password compatible authentication through normal REST authentication;
6. capability checks on every non-public operation.

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

Returns normalized WordPress resources, current environment permalinks and a deterministic fingerprint.

### `GET /content/{id}`

Authenticated. Requires permission to edit the requested resource.

Returns the normalized resource plus raw editable body/excerpt and identity fields required by the future preview/diff/update pipeline.

## Next endpoints

The next implementation slice adds:

- `/site/snapshot`
- `/changes/preview`
- `/changes/apply`
- `/changes/{operation_id}`
- `/changes/{operation_id}/rollback`
- `/optimize/analyze`
- `/publish/draft`

Writes remain draft-first and idempotent. No endpoint may silently publish, overwrite a newer human revision or create duplicate URL intent.

## Authentication

For MVP automation use WordPress REST authentication with an authorized WordPress user, preferably Application Passwords over HTTPS. Authorization is capability-based inside WordPress. Secrets are never stored in content manifests or committed to GitHub.

## Internal URL rule

Manager never stores production hostnames merely to link internal WordPress resources. Internal resource URLs are resolved from WordPress IDs/paths and the current site environment (`home_url()`, `get_permalink()`). This is mandatory for production/staging/cloned-site portability.
