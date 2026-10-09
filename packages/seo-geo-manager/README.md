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

The authenticated **SEO/GEO Manager** screen exposes Build / Finish intelligence and bounded correction workflows through the logged-in WordPress session and `wp-api-fetch`.

Current operator capabilities include:

- one-click site analysis and optional rendered-frontend verification;
- Build / Finish readiness with blockers/warnings;
- resolved SEO authority and Theme/preset contract inspection;
- navigation/environment leakage correction workflows;
- safe permalink inspection and historical-authority recovery;
- exact-path permalink Apply with verification and rollback;
- atomic permalink + 301 Apply when historical SEO paths genuinely change;
- raw diagnostic JSON for technical review.

## API v1

Base namespace: `/wp-json/seo-geo-manager/v1`.

### Read / intelligence

- `GET /health`
- `GET /content`
- `GET /content/{id}`
- `GET /site/snapshot`
- `GET /site/intelligence`

`/site/snapshot` exposes the Manager environment contract. Production writes require the exact current `environment_fingerprint`, derived from environment type + current `home_url()` + current `site_url()`. The value is an acknowledgement token, not a secret.

`/site/intelligence` includes bounded page/post inventory, permalink/logical-path mapping, internal-link evidence, clone/current-environment leakage, unresolved paths, orphan candidates, Theme contract/model completeness, media signals, SEO output authority and aggregated Build / Finish readiness.

### Controlled generic changes

- `POST /changes/preview`
- `POST /changes/apply`
- `GET /changes/{operation_id}`
- `POST /changes/{operation_id}/rollback`

M2 changes use current-resource fingerprints, idempotency, draft-first published-target guards, revisions/previous-value capture, post-write verification, environment binding and stale-safe rollback.

### Theme structured content

- `POST /theme/structured/preview`
- `POST /theme/structured/apply`

Theme writes mutate contract-defined structured content slots only. Manager does not accept arbitrary strategic-page layout HTML. Writable text/link slots keep the same M2 fingerprint, idempotency, environment, verification and rollback contracts.

### Permalink authority and repair

Manager treats malformed permalink syntax and historical SEO authority as two different things.

- `GET /permalinks/preview` — deterministic syntax inspection/recovery candidate; never declares historical SEO authority.
- `GET /permalinks/redirect-plan` — bounded old/new mapping, ambiguity and collision analysis.
- `POST /permalinks/legacy-authority-preview` — same-host historical WordPress URL recovery by exact slug reconciliation.
- `POST /permalinks/authoritative-plan` — revalidates historical authority, compares logical paths while ignoring temporary clone prefixes and returns direct/atomic Apply eligibility. `apply_available=true` means zero-redirect direct Apply; `atomic_apply_candidate=true` means the plan must pass the separate 301-runtime preview before any write.
- `GET /permalinks/redirect-runtime` — read-only state of the bounded Manager 301 runtime.
- `POST /permalinks/redirect-runtime/preview` — derives a runtime candidate only from a freshly verified authoritative plan; arbitrary caller-supplied maps are not accepted.
- `POST /permalinks/apply` — exact-path-preservation Apply for authoritative plans requiring zero redirects.
- `POST /permalinks/redirect-apply` — atomic structure + 301 Apply for authoritative plans that genuinely change historical paths.
- `POST /permalinks/operations/{operation_id}/rollback` — dispatches to the correct reversible engine and blocks stale/cross-environment rollback.

#### Exact-path Apply

The zero-redirect path requires complete verified historical authority, a collision-free complete plan, matching authority/plan/current-state fingerprints, explicit confirmation, current environment approval when required and an idempotency key.

WordPress writes the target structure, flushes rewrite rules and re-runs the authoritative plan. Verification failure restores the exact Manager-captured previous structure, including malformed prior values.

#### Atomic 301 Apply — Manager 0.3.21

When verified historical SEO paths differ from the routes generated by the authoritative target structure, Manager uses the separate atomic 301 path.

The operation is deliberately staged:

1. Revalidate historical authority, plan fingerprint and current permalink fingerprint.
2. Derive the redirect map server-side from that plan.
3. Reject duplicate sources, invalid/root/identical paths, chains, loops and concurrent active runtimes.
4. Reserve the operation/idempotency key.
5. Persist the redirect runtime **armed but ineffective**, bound to the operation, plan and expected target structure.
6. Change `permalink_structure` and flush rewrite rules.
7. The runtime becomes effective only when WordPress reports the exact expected structure.
8. Revalidate the authoritative plan and verify each 301 source resolves to its planned target.
9. Persist the operation only after structure + runtime verification succeeds.
10. On failure, restore the previous structure and remove the runtime.

Atomic Apply requires both `confirm_permalink_change=true` and `confirm_redirect_runtime=true`, plus the normal environment/idempotency/stale-state guards.

Rollback validates both the currently active permalink structure and the exact runtime fingerprint. It restores the previous structure first, removes the runtime, verifies both pieces and records the rollback. If either side has changed since Apply, rollback is blocked instead of overwriting newer state.

The runtime handles only frontend GET/HEAD requests, excludes admin/AJAX, preserves query strings and supports bounded one-hop 301 targets only.

## Safety invariants

1. Inspect before mutate.
2. No hardcoded production domain for internal resource identity.
3. Production mutations require current-environment acknowledgement.
4. Draft-first baseline for content.
5. Idempotent writes.
6. Optimistic concurrency / stale-plan protection.
7. Bounded rollback.
8. One accepted SEO authority per public output.
9. SEO authority resolution never implies provider-metadata write permission.
10. No fabricated proof/facts.
11. Content is not layout on Theme-owned strategic surfaces.
12. Theme structured writes accept only contract-defined slots and supported value types.
13. Malformed permalink syntax is never treated as historical SEO authority.
14. Redirect maps are derived from verified authority; no arbitrary public redirect-map write endpoint exists.
15. A redirect-required permalink mutation is valid only when structure + runtime are verified as one reversible operation.

## Authentication

For automation use normal WordPress REST authentication with an authorized WordPress user, preferably Application Passwords over HTTPS. Inside WordPress admin, the dashboard uses the authenticated session through WordPress' REST nonce middleware. Authorization remains capability-based; secrets are never stored in content manifests or committed to GitHub.

## Current candidate

- SEO/GEO Manager `0.3.21`.
- Build / Finish inspection and controlled structured writes are operational.
- Exact historical permalink preservation is guarded and reversible.
- Redirect-required authoritative permalink plans now have a separately guarded atomic Apply/rollback path.
- EMMAKE `/nuevaweb/` remains the first field target before broader promotion.

### Field gate

0.3.21 is code/CI-ready when the repository matrix is green, but it is not broadly promoted until the real EMMAKE clone completes an inspection-first field cycle. The sequence is: install candidate -> Site Intelligence -> historical authority preview -> authoritative plan -> no write unless the plan is demonstrably safe -> one reversible Apply/verify operation -> retain rollback evidence.

## Next implementation slices

- field-install and inspect Manager 0.3.21 on EMMAKE `/nuevaweb/` before any real write;
- first real permalink authority/preview cycle and one accepted reversible field operation when the plan proves safe;
- first real structured Build / Finish content preview/apply/verify cycle on an EMMAKE strategic page;
- operation-history/admin visibility and rendered post-write verification;
- provider-specific SEO metadata write adapters, starting with the accepted field authority;
- creation manifests for new draft pages/posts;
- M3 SEO/GEO Optimizer;
- M4 Landing Engine;
- M5 Blog Engine;
- M6 Search Console/Bing/analytics growth loop.
