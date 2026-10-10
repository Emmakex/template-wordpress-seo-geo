# SEO/GEO Manager

SEO/GEO Manager is the independent WordPress control plane for **finishing, optimizing and growing** a site throughout its lifecycle.

It remains separate from the other products:

- **SEO/GEO Theme** owns frontend rendering, strategic layout, semantic HTML, accessibility and performance.
- **SEO/GEO Migration Bridge** owns scan/clone/rescue/reset-first migration workflows.
- **SEO/GEO Manager** owns site intelligence, Build / Finish operations, controlled mutations, SEO/GEO optimization, publishing, verification, operation evidence and growth.

Canonical architecture is documented in `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`, `docs/CONTENT_PUBLISHING.md`, `docs/SEO_GEO_MANAGER_MVP.md`, `docs/SEO_GEO_MANAGER_PRODUCT_MODES.md` and `docs/SEO_GEO_MANAGER_CHANGESETS.md`.

## Product modes

### Build / Finish

For a new or recently migrated site. Manager inventories real WordPress state, detects incomplete strategic content, environment-link leakage, navigation/permalink/SEO/media gaps and launch-readiness blockers, then prepares bounded changes that can be previewed, applied, verified and rolled back.

```text
new site:      SEO/GEO Theme -> Manager Build / Finish -> launch -> Optimize / Grow
migrated site: Migration Bridge -> SEO/GEO Theme -> Manager Build / Finish -> launch -> Optimize / Grow
```

EMMAKE `/nuevaweb/` is the first reference field target.

### Optimize

For an existing site. Manager analyzes intent, entities, headings, content completeness, internal linking, metadata, Schema consistency, GEO signals, stale content and duplicate/cannibalization candidates, then prepares controlled improvements.

### Grow

For continuous operation after launch. Manager will create/refresh landings and blog content, maintain clusters/internal links, schedule/publish under policy and later consume Search Console, Bing, analytics and other accepted signals.

## WordPress admin dashboard

The authenticated **SEO/GEO Manager** screen uses the logged-in WordPress session and `wp-api-fetch`.

Current operator capabilities include:

- one-click Site Intelligence with optional rendered-frontend verification;
- Build / Finish readiness with blockers and warnings;
- Theme/preset/model inspection and resolved SEO output authority;
- semantic recognition of existing strategic pages whose safe URL/title differs from the preset canonical label;
- navigation/environment leakage correction workflows;
- safe permalink inspection and historical-authority recovery;
- guarded historical identity recovery by WordPress post ID only when a local slug is unequivocally damaged by the migration marker pattern;
- exact-path permalink Apply with verification and rollback;
- atomic permalink + one-hop 301 Apply when historical SEO paths genuinely change;
- optional same-site rendered post-write verification for published generic and Theme-structured content;
- privacy-bounded operation history with rollback and rendered-verification state;
- **Field Gate** preflight that combines Build / Finish, frontend, historical authority and permalink evidence without writing anything;
- raw JSON evidence for technical review and handoff, with copy/download disabled until a valid preflight result exists.

## API v1

Base namespace: `/wp-json/seo-geo-manager/v1`.

### Read / intelligence

- `GET /health`
- `GET /content`
- `GET /content/{id}`
- `GET /site/snapshot`
- `GET /site/intelligence`
- `GET /operations`
- `GET /field-gate/preflight`

`/site/snapshot` exposes the current environment contract. Production writes require the exact current `environment_fingerprint`; it is an acknowledgement token, not a secret.

`/site/intelligence` includes bounded page/post inventory, permalink/logical-path mapping, internal-link evidence, clone/current-environment leakage, unresolved paths, orphan candidates, Theme contract/model completeness, media signals, SEO output authority, actionable diagnostics and Build / Finish readiness.

`/operations` exposes a bounded privacy-safe summary of recent Manager operations. It intentionally excludes content bodies, previous values, mutation payloads, payload hashes, environment fingerprints and rendered response bodies/digests. Site-wide summaries require `manage_options`; editors only see content operations for resources they can edit.

### Build / Finish Field Gate — Manager 0.3.24+

`GET /field-gate/preflight` is the inspection-only gate used immediately after installing Manager on a real target such as a clone or staging site.

Parameters:

- `include_rendered=true|false` — optionally verify a bounded set of public rendered surfaces;
- `legacy_base_url=<url>` — optional historical WordPress origin used to recover same-host permalink authority. The endpoint contains no hardcoded client domain.

The Field Gate combines, in one response:

1. Site Intelligence, Theme contract, SEO authority, media intelligence, actionable diagnostics and Build / Finish readiness.
2. Current permalink syntax inspection.
3. Current Manager redirect-runtime snapshot.
4. Optional same-host historical permalink authority.
5. Optional authoritative permalink preservation plan.
6. Optional read-only atomic 301 runtime preview when historical paths genuinely change.

The summarized decision keeps three concerns separate:

- `build_finish.status` — current launch/readiness state;
- `historical_authority.status` — `not-requested`, `verified` or `blocked`;
- `permalink_plan.status` — `not-requested`, `blocked`, `direct-ready`, `atomic-ready` or `atomic-runtime-blocked`.

Overall Field Gate state can be `blocked`, `needs-input` or `ready-for-guarded-write`. A ready state is **not a write** and is not permission to mutate automatically. It only identifies which existing guarded path may be reviewed next: direct permalink Apply or atomic structure + 301 Apply.

The Field Gate contract is explicit:

- administrator-only (`manage_options`);
- GET/read-only;
- no content mutation;
- no `permalink_structure` mutation;
- no redirect-runtime activation;
- no rewrite-rule flush;
- no Manager operation record created;
- no hidden confirmation or Apply step;
- every real mutation still requires its separate endpoint, current fingerprints, explicit confirmations, environment policy and idempotency/stale-state guards.

### Semantic preset page resolution — Manager 0.3.26

A real migrated site does not always use the exact canonical labels defined by a preset. Manager 0.3.26 adds a generic, read-only semantic resolver before Theme Contract Intelligence declares a strategic page missing.

The resolver:

- uses the existing `seo_geo_manager_resolve_preset_page_id` contract rather than hardcoding a client site;
- recognizes a deliberately small role-based set of safe semantic equivalents for surfaces such as About and the editorial/posts index;
- treats a unique exact semantic slug as stronger evidence than a title alias;
- uses a bounded fallback that can inspect up to the Manager inventory ceiling on larger migrated sites;
- refuses to guess when evidence is ambiguous at the same confidence level;
- never creates, renames or mutates a page by itself.

This prevents Build / Finish from recommending duplicate strategic pages when an existing migrated URL is already a valid semantic equivalent. Runtime acceptance explicitly covers a site with more than 100 pages and verifies mappings equivalent to `Sobre Nosotros -> about` and `Blog -> insights`.

### Migration-corrupted slug recovery — Manager 0.3.27

Field evidence from a migrated WordPress can expose a narrow failure mode: the same temporary migration marker that damages `%category%` / `%postname%` may also replace the percent bytes inside an already percent-encoded `post_name`. In that case the historical post still exists, but exact slug matching alone can incorrectly report the URL as missing.

Manager 0.3.27 keeps slug matching as the primary authority and adds a deliberately narrow identity fallback:

- the local slug must contain one repeated braced hexadecimal migration marker of the accepted bounded length;
- replacing that marker with `%` must reconstruct valid percent-encoded byte sequences;
- the historical REST row must have the **same WordPress post ID** and that remote ID must be unique;
- same-host, complete-scan, unique-mapping and historical-link guards remain mandatory;
- a normal clean slug mismatch is never rescued by ID;
- the recovered row records `match_method=corrupted-slug-id-fallback`, the current local slug and the historical slug as evidence;
- authority can become complete/read-only again, but the permalink planner explicitly blocks Apply/301 and returns `repair-corrupted-local-slugs-first` until the damaged local `post_name` is repaired by a separate protected operation.

This separates **proving historical identity** from **mutating local storage**. The authority preview never repairs a slug, changes `permalink_structure`, activates redirects or creates an operation record.

The WordPress runtime regression fixture proves all four critical properties: corrupted-marker ID recovery works, a clean mismatch cannot use ID fallback, the planner requires local slug repair first, and the entire inspection remains read-only.

### Controlled generic changes

- `POST /changes/preview`
- `POST /changes/apply`
- `GET /changes/{operation_id}`
- `POST /changes/{operation_id}/rollback`

M2 uses current-resource fingerprints, idempotency, draft-first published-target guards, revisions/previous-value capture, exact stored-value verification, environment binding and stale-safe rollback.

For an explicitly approved published target, `verify_rendered=true` adds the M2.7 public-route gate. Manager verifies the exact current same-site permalink after the WordPress write. The check uses a bounded no-redirect GET, requires a 2xx HTML document, stores compact evidence only, and never persists the response body.

### Theme structured content

- `POST /theme/structured/preview`
- `POST /theme/structured/apply`

Theme writes mutate contract-defined structured text/link slots only. Manager never accepts arbitrary strategic-page layout HTML. Published structured targets can use `verify_rendered=true` under the same verification and rollback policy.

### Permalink authority and repair

- `GET /permalinks/preview` — syntax inspection only; malformed syntax is never historical SEO authority.
- `GET /permalinks/redirect-plan` — bounded mapping/collision analysis.
- `POST /permalinks/legacy-authority-preview` — same-host historical WordPress URL recovery.
- `POST /permalinks/authoritative-plan` — revalidates authority and compares logical paths while ignoring temporary clone prefixes.
- `GET /permalinks/redirect-runtime` — read-only runtime state.
- `POST /permalinks/redirect-runtime/preview` — server-derived one-hop 301 runtime preview.
- `POST /permalinks/apply` — exact-path-preservation Apply when zero redirects are required.
- `POST /permalinks/redirect-apply` — atomic structure + 301 Apply when verified historical paths change.
- `POST /permalinks/operations/{operation_id}/rollback` — reversible stale/environment-safe rollback.

The redirect map is derived from verified historical authority; arbitrary public redirect maps are never accepted. Atomic Apply arms the runtime first, mutates the structure second, revalidates every source/target after the change and restores/removes both pieces if verification fails.

When historical identity is recovered through the 0.3.27 corrupted-slug ID fallback, the authoritative plan intentionally remains blocked. A damaged local `post_name` must not be converted into a redirect target; it first requires an explicit, reversible slug-repair operation.

### Operation History — Manager 0.3.22

Manager keeps full private operation records for verification and rollback while maintaining a separate bounded summary index for operator visibility.

- maximum 100 summaries;
- latest 20 shown by default in admin;
- changed field names but never values;
- rollback state refreshed from the private operation;
- content visibility filtered through `edit_post`;
- site-wide operations remain administrator-only.

### Rendered post-write verification — Manager 0.3.23

M2.7 proves two different things separately: WordPress persisted the requested mutation, and the resulting public route can serve a bounded HTML document.

The rendered gate is opt-in, same-site only, exact `get_permalink()`, no redirects, five-second timeout and 2 MiB response cap. Private evidence stores route/status/content type/bytes/SHA-256/timestamp only. A public HTTP failure records `rendered-verification-failed` but does **not** automatically undo a valid persisted mutation; guarded manual rollback remains available.

## Safety invariants

1. Inspect before mutate.
2. No hardcoded production domain for internal resource identity.
3. Production mutations require current-environment acknowledgement.
4. Draft-first baseline for content.
5. Idempotent writes.
6. Optimistic concurrency and stale-plan protection.
7. Bounded stale-safe rollback.
8. One accepted SEO authority per public output.
9. SEO authority resolution never implies provider-metadata write permission.
10. No fabricated proof or facts.
11. Content is not layout on Theme-owned strategic surfaces.
12. Theme structured writes accept only contract-defined slots and supported value types.
13. Malformed permalink syntax is never treated as historical SEO authority.
14. Redirect maps come only from verified authority.
15. Redirect-required permalink mutation is valid only when structure + runtime verify as one reversible operation.
16. Operation-history surfaces expose summaries only; mutation values and rollback payloads remain private.
17. Rendered verification uses only the exact same-site public permalink, stores no HTML body and never auto-rolls back solely because HTTP verification failed.
18. Field Gate is inspection-only: it never creates an operation, writes content/permalinks, flushes rewrites or activates redirects.
19. Semantic page resolution is inspection-only and never creates duplicate pages automatically.
20. Historical post-ID fallback is allowed only for unequivocally migration-corrupted local slugs; it never authorizes permalink Apply while the damaged local `post_name` remains unrepaired.

## Authentication

Automation uses normal WordPress REST authentication with an authorized WordPress user, preferably Application Passwords over HTTPS. Inside WordPress admin, the dashboard uses the authenticated session through WordPress REST nonce middleware. Authorization remains capability-based; secrets are never stored in content manifests or committed to GitHub.

## Current candidate

- **SEO/GEO Manager `0.3.27`**.
- Build / Finish inspection and controlled structured writes are operational.
- Exact historical permalink preservation and redirect-required migrations have separate guarded reversible paths.
- Privacy-safe operation history and rendered post-write verification are operational.
- Field Gate provides one read-only preflight before any real field mutation.
- Semantic preset page resolution prevents false missing-page findings for safe existing equivalents on larger migrated sites.
- Migration-corrupted slugs can be identified against same-ID historical WordPress posts without silently accepting ordinary slug mismatches.
- The permalink planner blocks until every recovered corrupted local slug is repaired through a separate guarded operation.
- Field Gate JSON copy/download actions stay disabled until valid evidence exists.
- EMMAKE `/nuevaweb/` remains the first real field target before broader promotion.

### Real field sequence

For the next EMMAKE field cycle:

1. Keep the exact SEO/GEO Theme MF-08 candidate already selected for `/nuevaweb/`; replace/update only SEO/GEO Manager to **0.3.27**. Do **not** rerun Reset/hydration merely for this Manager correction.
2. Open SEO/GEO Manager → **Field Gate · Build / Finish**.
3. Set historical origin to `https://emmake.com/` and keep rendered verification enabled.
4. Run the Field Gate once and retain its JSON evidence.
5. Confirm whether the historical authority now maps the full published-post inventory with zero missing entries and reports the previously damaged slugs under `recovered_by_id` rather than treating them as absent.
6. Expect the authoritative permalink plan to remain blocked with `repair-corrupted-local-slugs-first` whenever `recovered_by_id_count > 0`; do **not** Apply permalinks or 301s at that point.
7. Implement/review the next protected reversible local-slug repair slice using the verified historical slug evidence.
8. Rerun Field Gate after that repair; only a fresh safe plan may unlock a separately confirmed reversible permalink operation.
9. Run the first real Theme-structured `Preview -> Apply -> stored verify -> rendered verify` cycle on an accepted published strategic page.
10. Only after this field acceptance continue into provider write adapters and M3 SEO/GEO Optimizer.

## Next implementation slices

- real `/nuevaweb/` Manager 0.3.27 Field Gate evidence with historical origin supplied;
- guarded preview/apply/rollback for the narrowly identified migration-corrupted local `post_name` values;
- fresh Field Gate after slug repair, then first accepted reversible real permalink operation only if the plan proves safe;
- first real structured Build / Finish rendered-verification cycle;
- provider-specific SEO metadata write adapters after field authority is confirmed;
- creation manifests for new draft pages/posts;
- M3 SEO/GEO Optimizer;
- M4 Landing Engine;
- M5 Blog Engine;
- M6 Search Console/Bing/analytics growth loop.
