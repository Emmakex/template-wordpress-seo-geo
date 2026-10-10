# SEO/GEO Manager

SEO/GEO Manager is the independent WordPress control plane for **finishing, optimizing and growing** a site throughout its lifecycle.

It remains separate from the other products:

- **SEO/GEO Theme** owns frontend rendering, strategic layout, semantic HTML, accessibility and performance.
- **SEO/GEO Migration Bridge** owns scan/clone/rescue/reset-first migration workflows.
- **SEO/GEO Manager** owns site intelligence, Build / Finish operations, controlled mutations, SEO/GEO optimization, publishing, verification, operation evidence and growth.

Canonical architecture is documented in `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`, `docs/CONTENT_PUBLISHING.md`, `docs/SEO_GEO_MANAGER_MVP.md`, `docs/SEO_GEO_MANAGER_PRODUCT_MODES.md`, `docs/SEO_GEO_MANAGER_CHANGESETS.md`, `docs/SEO_GEO_MIGRATION_POLICY.md` and `docs/SEO_GEO_MANAGER_0.3.30_SLUG_REPAIR.md`.

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
- read-only validation of historical permalink evidence;
- clean SEO/GEO target selection from dominant historical evidence without restoring legacy category debt;
- protected migration-corrupted local-slug Preview / Apply / rollback before any global permalink write;
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
5. Optional authoritative permalink preservation / clean-target plan.
6. Optional read-only atomic 301 runtime preview when historical paths genuinely change.

The summarized decision keeps three concerns separate:

- `build_finish.status` — current launch/readiness state;
- `historical_authority.status` — `not-requested`, `verified` or `blocked`;
- `permalink_plan.status` — `not-requested`, `blocked`, `direct-ready`, `atomic-ready` or `atomic-runtime-blocked`.

Overall Field Gate state can be `blocked`, `needs-input` or `ready-for-guarded-write`. A ready state is **not a write** and is not permission to mutate automatically. It only identifies which guarded path may be reviewed next.

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

A real migrated site does not always use the exact canonical labels defined by a preset. Manager adds a generic, read-only semantic resolver before Theme Contract Intelligence declares a strategic page missing.

The resolver:

- uses the existing `seo_geo_manager_resolve_preset_page_id` contract rather than hardcoding a client site;
- recognizes a deliberately small role-based set of safe semantic equivalents for surfaces such as About and the editorial/posts index;
- treats a unique exact semantic slug as stronger evidence than a title alias;
- uses a bounded fallback that can inspect up to the Manager inventory ceiling on larger migrated sites;
- refuses to guess when evidence is ambiguous at the same confidence level;
- never creates, renames or mutates a page by itself.

### Migration-corrupted slug identity recovery — Manager 0.3.27

Field evidence exposed a narrow migration failure mode where the same temporary marker that damages permalink tokens can also replace percent bytes inside a stored `post_name`.

Slug matching remains primary authority. Same-ID historical fallback is accepted only when the local slug is unequivocally migration-corrupted and the remote WordPress post ID is unique. A clean mismatched slug is never rescued by ID.

This proves historical identity without silently changing local storage.

### Historical permalink evidence — Manager 0.3.28

Manager can validate a syntactically safe historical candidate by rendering local posts through WordPress and comparing logical paths with verified historical URLs. This evidence is useful for understanding legacy behavior, but it does not force the new site to reproduce that legacy architecture.

### Clean SEO/GEO target architecture — Manager 0.3.29

Canonical policy: **preserve SEO authority, not legacy architecture debt**.

Historical URLs answer what must be preserved or redirected. They do not automatically define the permanent URL architecture of the rebuilt site.

When one stable category-independent literal prefix dominates a complete historical corpus, Manager may select it as the clean target under conservative guardrails:

- complete, unambiguous historical mapping;
- exactly one `%postname%` token;
- no `%category%` dependency;
- at least 90% corpus support;
- at least 10 supporting posts;
- low-quality candidates such as `/uncategorized/%postname%/` excluded;
- outliers retained as explicit 301 evidence;
- selection itself remains read-only.

The EMMAKE 0.3.29 field report proved 1,726 / 1,726 historical identities, selected `/blog/%postname%/` from 1,702 supporting historical routes (98.6095%) and isolated 24 historical outliers before repairing two damaged local slugs.

### Protected local slug repair — Manager 0.3.30

Manager 0.3.30 implements the repair-first gate that 0.3.27–0.3.29 intentionally left blocked.

Endpoints:

- `POST /permalinks/slug-repair/preview`
- `POST /permalinks/slug-repair/apply`
- `POST /permalinks/operations/{operation_id}/rollback` for the resulting `post-slug-repair` operation.

The repair is derived only from the current authoritative plan. Preview requires the exact damaged local slug to still exist and validates the historical target with WordPress native slug uniqueness rules.

Apply requires:

- current authority fingerprint;
- current plan fingerprint;
- current repair fingerprint;
- explicit `confirm_slug_repair=true`;
- idempotency key;
- current production environment acknowledgement where required.

The operation snapshots the exact damaged slug and pre-operation `_wp_old_slug` metadata, writes the verified historical target slug through WordPress, verifies the stored value, rebuilds the authoritative plan and requires zero remaining local slug repairs with the same selected target architecture.

A failed verification restores the original local state. Rollback is stale-safe and refuses to overwrite a later slug edit.

The protected repair **never** changes `permalink_structure`, activates the redirect runtime or flushes rewrite rules. A successful repair still requires a fresh Field Gate before any permalink + 301 operation can be reviewed.

The dashboard exposes the same guarded sequence directly after a Field Gate report contains `local_slug_repairs`:

```text
Field Gate -> Preview slug repair -> explicit Apply -> verify -> rerun Field Gate
```

### Controlled generic changes

- `POST /changes/preview`
- `POST /changes/apply`
- `GET /changes/{operation_id}`
- `POST /changes/{operation_id}/rollback`

M2 uses current-resource fingerprints, idempotency, draft-first published-target guards, revisions/previous-value capture, exact stored-value verification, environment binding and stale-safe rollback.

For an explicitly approved published target, `verify_rendered=true` adds the public-route gate. Manager verifies the exact current same-site permalink after the WordPress write. The check uses a bounded no-redirect GET, requires a 2xx HTML document, stores compact evidence only, and never persists the response body.

### Theme structured content

- `POST /theme/structured/preview`
- `POST /theme/structured/apply`

Theme writes mutate contract-defined structured text/link slots only. Manager never accepts arbitrary strategic-page layout HTML. Published structured targets can use `verify_rendered=true` under the same verification and rollback policy.

### Permalink authority and repair

- `GET /permalinks/preview` — syntax inspection only; malformed syntax is never historical SEO authority.
- `GET /permalinks/redirect-plan` — bounded mapping/collision analysis.
- `POST /permalinks/legacy-authority-preview` — same-host historical WordPress URL recovery.
- `POST /permalinks/authoritative-plan` — revalidates authority and computes preservation / clean-target plan.
- `POST /permalinks/slug-repair/preview` — protected read-only repair preview for verified migration-corrupted local slugs.
- `POST /permalinks/slug-repair/apply` — separately confirmed local slug repair; no global permalink/runtime write.
- `GET /permalinks/redirect-runtime` — read-only runtime state.
- `POST /permalinks/redirect-runtime/preview` — server-derived one-hop 301 runtime preview.
- `POST /permalinks/apply` — exact-path-preservation Apply when zero redirects are required.
- `POST /permalinks/redirect-apply` — atomic structure + 301 Apply when verified historical paths change.
- `POST /permalinks/operations/{operation_id}/rollback` — reversible stale/environment-safe rollback.

Redirect maps are derived from verified historical authority; arbitrary public redirect maps are never accepted. Atomic Apply arms the runtime first, mutates the structure second, revalidates every source/target after the change and restores/removes both pieces if verification fails.

## Operation History

Manager keeps full private operation records for verification and rollback while maintaining a separate bounded summary index for operator visibility.

- maximum 100 summaries;
- latest 20 shown by default in admin;
- changed field names but never values;
- rollback state refreshed from the private operation;
- content visibility filtered through `edit_post`;
- site-wide operations remain administrator-only.

## Rendered post-write verification

Manager separates two facts: WordPress persisted the requested mutation, and the resulting public route can serve a bounded HTML document.

The rendered gate is opt-in, same-site only, exact `get_permalink()`, no redirects, five-second timeout and 2 MiB response cap. Private evidence stores route/status/content type/bytes/SHA-256/timestamp only. A public HTTP failure records a failed rendered verification but does **not** automatically undo a valid persisted mutation; guarded manual rollback remains available.

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
14. Historical authority and target architecture are separate decisions.
15. Redirect maps come only from verified authority.
16. Redirect-required permalink mutation is valid only when structure + runtime verify as one reversible operation.
17. Operation-history surfaces expose summaries only; mutation values and rollback payloads remain private.
18. Rendered verification uses only the exact same-site public permalink, stores no HTML body and never auto-rolls back solely because HTTP verification failed.
19. Field Gate is inspection-only: it never creates an operation, writes content/permalinks, flushes rewrites or activates redirects.
20. Semantic page resolution is inspection-only and never creates duplicate pages automatically.
21. Historical post-ID fallback is allowed only for unequivocally migration-corrupted local slugs.
22. Protected slug repair is a separate reversible operation and cannot change `permalink_structure` or redirect runtime.
23. A successful local slug repair never authorizes a global permalink mutation without a fresh Field Gate.

## Authentication

Automation uses normal WordPress REST authentication with an authorized WordPress user, preferably Application Passwords over HTTPS. Inside WordPress admin, the dashboard uses the authenticated session through WordPress REST nonce middleware. Authorization remains capability-based; secrets are never stored in content manifests or committed to GitHub.

## Current candidate

- **SEO/GEO Manager `0.3.30`**.
- Build / Finish inspection and controlled structured writes are operational.
- Historical authority recovery and clean target planning are separate from legacy architecture reproduction.
- EMMAKE field evidence verifies 1,726 / 1,726 historical identities and `/blog/%postname%/` as the dominant clean target.
- Two migration-corrupted local slugs are isolated as a protected repair prerequisite.
- 0.3.30 provides guarded Preview / Apply / rollback for those local repairs without touching global permalink structure or redirect runtime.
- Exact-path and redirect-required permalink migrations retain separate guarded reversible paths.
- Privacy-safe operation history and rendered post-write verification remain operational.
- Field Gate remains the required preflight before every real field transition.

### Real field sequence — EMMAKE `/nuevaweb/`

1. Keep the accepted MF-08 Theme candidate; update only SEO/GEO Manager to **0.3.30**. Do **not** rerun Migration Bridge Reset, clone or Theme hydration.
2. Open **Field Gate · Build / Finish**, historical origin `https://emmake.com/`, rendered verification enabled.
3. Run Field Gate once so the protected-repair panel receives fresh 0.3.30 evidence.
4. Confirm the report still shows complete historical authority and exactly the expected damaged local slugs.
5. Click **Previsualizar reparación de slugs**.
6. Review the exact `before_slug -> target_slug` set and confirm that `permalink_structure` and redirect runtime remain unchanged.
7. Explicitly Apply the protected repair.
8. Confirm the operation verifies and shows zero remaining local repairs in the regenerated plan.
9. Run Field Gate again and download the new JSON evidence.
10. Review that fresh evidence before using any permalink/301 Apply control.
11. Only after a fresh safe atomic plan is accepted, consider the separately confirmed `/blog/%postname%/` + one-hop 301 transition.
12. Continue Build / Finish content/media/SEO work and the first real Theme-structured `Preview -> Apply -> stored verify -> rendered verify` cycle.

## Next implementation slices

- real `/nuevaweb/` 0.3.30 protected slug-repair field cycle;
- fresh Field Gate after repair and exact review of the resulting redirect count;
- first accepted reversible real atomic permalink + 301 operation only if fresh evidence proves safe;
- first real structured Build / Finish rendered-verification cycle;
- provider-specific SEO metadata write adapters after field authority is confirmed;
- creation manifests for new draft pages/posts;
- M3 SEO/GEO Optimizer;
- M4 Landing Engine;
- M5 Blog Engine;
- M6 Search Console/Bing/analytics growth loop.
