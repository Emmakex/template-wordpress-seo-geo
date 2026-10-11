# SEO/GEO Manager

SEO/GEO Manager is the permanent, independently installable **WordPress bridge/control agent** for the three-product SEO/GEO portfolio.

Canonical architecture:

> **Migration Bridge brings/rescues the website.**  
> **SEO/GEO Theme builds and renders the website.**  
> **SEO/GEO Manager gives authorized external orchestration eyes, hands and safe control inside WordPress.**  
> **External orchestration provides strategy, research, creation, optimization and growth intelligence.**

Manager is not the strategic brain, not the public frontend renderer and not a migration utility. It exposes reliable, client-agnostic semantic WordPress capabilities that external orchestration can inspect and use safely.

Authoritative product contract: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Permanent control plane

From 0.3.50 the top-level Manager screen follows the same operating pattern proven in `Emmakex/eduardoyauriluna-research` Research Manager.

The permanent workspace has three lifecycle surfaces:

- **Build / Finish** — complete structure, content, SEO, links, media and launch readiness;
- **Optimize** — inspect and improve a live site through bounded verifiable changes;
- **Grow** — create new pages, content, languages and assets through semantic Manager operations.

Migration-specific Field Gate, permalink cutover and 301 tooling remain available inside Build / Finish as bounded compatibility/finalization tools. They do **not** define the product identity.

## Core operating loop

```text
Inspect
 -> Preview
 -> Apply
 -> Publish / Schedule where relevant
 -> Verify
 -> History / Rollback
```

External orchestration decides **what** should be created, changed or optimized and **why**. Manager validates whether the current installation, authority and authenticated principal can perform the requested operation, executes through accepted WordPress/provider contracts and returns bounded evidence.

## Manager owns

- authenticated Site Intelligence;
- capability discovery;
- environment identity/fingerprint;
- WordPress resource identity;
- content/model reads;
- exact preview/diff;
- guarded apply;
- idempotency and expected-state protection;
- normal post/page lifecycle;
- taxonomy and existing-user author relationships;
- accepted multilingual relationship plumbing;
- Theme semantic-model operations;
- navigation/internal-link/media operations;
- SEO authority/provider adapters;
- stored-state verification;
- bounded rendered verification where applicable;
- operation IDs/history/evidence;
- stale-safe rollback where supported;
- Manager-owned remote connection identity, scopes, rotation/revocation and bounded audit.

## Manager does not own

- business/content/keyword strategy;
- market/competitor research;
- deciding which landing/article should exist;
- autonomous editorial/translation/author strategy;
- fabricated business facts, authors, sources, reviews or proof;
- frontend visual composition when SEO/GEO Theme is active;
- cloning/reset/replatform transport owned by Migration Bridge;
- a generic command shell;
- a generic WordPress REST proxy;
- client-specific hardcoded workflows.

## API namespace

```text
/wp-json/seo-geo-manager/v1
```

### Public health

```text
GET /health
```

Returns only bounded service/version health information.

### WordPress-authenticated capability discovery

```text
GET /capabilities
```

The pre-0.3.50 authenticated surface remains available for browser administration, compatibility and accepted Application Password integrations.

Detailed contract: `docs/SEO_GEO_MANAGER_0.3.34_CAPABILITY_DISCOVERY.md`.

### Manager-owned remote bridge — 0.3.50

The preferred persistent operating path now mirrors Research Manager:

```text
local administrator authorizes one Manager connection
 -> Manager generates one scoped revocable credential
 -> external orchestration calls bounded Manager endpoints
 -> local administrator can disable / rotate / revoke at any time
```

Local security console:

```text
SEO/GEO Manager -> Conexión remota
```

The credential is shown once. Manager stores only a password hash plus a short non-secret prefix.

Request metadata:

```text
Authorization: Bearer <Manager credential>
X-SEO-GEO-Manager-Request-Id: <stable request id>
X-SEO-GEO-Manager-Nonce: <unique nonce>
X-SEO-GEO-Manager-Timestamp: <unix timestamp>
```

R1/R2 safety includes:

- HTTPS outside local/development;
- explicit scopes;
- administrator-bound execution identity;
- timestamp validity window;
- nonce replay protection;
- per-connection rate limit;
- idempotency primitives for future mutation retries;
- bounded non-secret audit;
- local disable/rotation/revocation.

Current authenticated remote read surface:

```text
GET /remote/status             # site.read
GET /remote/capabilities       # site.read
GET /remote/site-intelligence  # site.diagnostics
GET /remote/audit              # site.diagnostics
```

R2 is deliberately read-only. Remote mutation transport belongs to R3 and must reuse the Manager's existing semantic mutation services; it will not introduce a raw metadata editor or generic proxy.

Detailed runbook: `docs/SEO_GEO_MANAGER_REMOTE_ACCESS.md`.

### Application Password compatibility

The 0.3.34–0.3.49 line proved WordPress Application Password transport, least privilege and revocation. That path remains valid for compatibility, diagnostics and the frozen 0.3.49 C2/C9 acceptance model.

It is **not the preferred persistent product UX from 0.3.50 onward**.

Do not create temporary WordPress users/Application Passwords merely because older field instructions required them when the Manager-owned connection is available and sufficient.

## Core semantic surfaces

### Site/read operations

```text
GET /content
GET /content/{id}
GET /site/snapshot
GET /site/intelligence
GET /operations
GET /field-gate/preflight
```

### Generic guarded change sets

```text
POST /changes/preview
POST /changes/apply
GET  /changes/{operation_id}
POST /changes/{operation_id}/rollback
```

### Content lifecycle

```text
POST /content/resources/preview
POST /content/resources/apply
POST /content/{id}/publication/preview
POST /content/{id}/publication/apply
```

### Taxonomy assignment

```text
GET  /content/{resource_id}/taxonomies/{taxonomy}
POST /content/{resource_id}/taxonomies/{taxonomy}/changes/preview
POST /content/{resource_id}/taxonomies/{taxonomy}/changes/apply
POST /content/taxonomies/changes/{operation_id}/rollback
```

Manager assigns **existing terms only**; strategy remains external.

Detailed contract: `docs/SEO_GEO_MANAGER_0.3.44_TAXONOMY_ASSIGNMENT.md`.

### Author binding

```text
GET  /content/{resource_id}/author
POST /content/{resource_id}/author/changes/preview
POST /content/{resource_id}/author/changes/apply
POST /content/authors/changes/{operation_id}/rollback
```

Manager binds **existing eligible WordPress users only** and never invents personas/users as a publishing side effect.

Detailed contract: `docs/SEO_GEO_MANAGER_0.3.45_AUTHOR_BINDING.md`.

### Native translation groups

```text
GET  /content/translation-groups/{group_id}
POST /content/translation-groups/{group_id}/changes/preview
POST /content/translation-groups/{group_id}/changes/apply
POST /content/translation-groups/changes/{operation_id}/rollback
```

External orchestration supplies language/market decisions and translated content; Manager only operates the accepted relationship contract.

Detailed contract: `docs/SEO_GEO_MANAGER_0.3.46_NATIVE_TRANSLATION_GROUPS.md`.

### Theme structured content

```text
POST /theme/structured/preview
POST /theme/structured/apply
```

When SEO/GEO Theme is active, Manager changes contract-defined semantic content/model slots. Theme owns rendering/design/performance.

### Navigation, links and media

Manager exposes guarded semantic navigation, contextual-link, featured-media, strategic-media, media-context, alt-text and upload operations. Each operation retains resource/capability, expected-state, idempotency and verification rules appropriate to its risk.

### SEO authority adapters

Manager resolves one accepted SEO output authority before writing.

Accepted write authorities currently include:

- SEO/GEO Theme native metadata contract;
- Yoast SEO via its public WordPress Abilities adapter.

Manager does not write provider private meta directly.

Detailed contracts:

- `docs/SEO_GEO_MANAGER_0.3.47_NATIVE_SEO_METADATA.md`;
- `docs/SEO_GEO_MANAGER_0.3.48_YOAST_ABILITIES.md`.

## Migration-derived URL safety

Guarded permalink/redirect/slug operations created from real EMMAKE field acceptance remain useful launch-readiness primitives.

They are nested inside Build / Finish. New migration transport/rescue/reset functionality belongs to SEO/GEO Migration Bridge.

## Authorization

Discovering an operation never authorizes it.

Every semantic endpoint retains its WordPress capability, resource, environment and authority checks. Remote scopes add another boundary; they do not replace those checks.

## Safety invariants

1. Inspect before mutate.
2. Preview before meaningful mutation.
3. No client-domain hardcoding.
4. No generic remote shell or REST proxy.
5. Use WordPress-native resource identity and permission checks.
6. Use expected fingerprints/revisions to reject stale automation.
7. Use idempotency for retry-safe writes.
8. Bind risky production mutations to the current environment.
9. Verify stored state after mutation.
10. Keep rendered verification same-site and bounded.
11. Keep operation/audit evidence privacy-bounded and secret-free.
12. Roll back only when the operation is still safe to reverse.
13. Never silently overwrite a newer human edit.
14. Never emit duplicate SEO/GEO authority.
15. Never fabricate facts/authors/sources/reviews/proof.
16. Never bypass an accepted provider authority with raw private metadata writes.
17. Remote credentials remain revocable and scope-bounded.
18. Remote writes must reuse the existing semantic operation engine.

## Product relationships

Typical full-redesign lifecycle:

```text
Migration Bridge
 -> rescued/reset rebuild workspace
 -> SEO/GEO Theme
 -> SEO/GEO Manager
 -> Build / Finish
 -> launch
 -> Optimize / Grow through Manager
```

SEO/GEO Theme and Manager remain independently installable.

## Current development line

- **0.3.34** established authenticated capability discovery and the Application Password compatibility baseline.
- **0.3.35–0.3.46** built generic content, media, taxonomy, author and multilingual relationship operations.
- **0.3.47** accepted Theme-native SEO metadata authority.
- **0.3.48** accepted Yoast public-Abilities SEO authority.
- **0.3.49** froze the deterministic C9 field candidate and exact runtime package identity.
- **0.3.50** aligns the product with the Research Manager operating pattern: permanent Build / Finish + Optimize + Grow workspace, Manager-owned scoped/revocable connection, bounded audit and authenticated remote read bridge.

0.3.49 remains a separate frozen field candidate. 0.3.50 must receive its own deterministic package identity and acceptance before installation/promotion.

## Current roadmap and contracts

See:

- `docs/THREE_PRODUCT_OPERATING_MODEL.md`;
- `docs/CURRENT_THREE_PRODUCT_ROADMAP.md`;
- `docs/DOCUMENTATION_AUTHORITY.md`;
- `docs/SEO_GEO_MANAGER.md`;
- `docs/SEO_GEO_MANAGER_REMOTE_ACCESS.md`;
- `docs/SEO_GEO_MANAGER_0.3.50_CONTROL_PLANE_REMOTE_BRIDGE.md`;
- `docs/SEO_GEO_MANAGER_0.3.44_TAXONOMY_ASSIGNMENT.md`;
- `docs/SEO_GEO_MANAGER_0.3.45_AUTHOR_BINDING.md`;
- `docs/SEO_GEO_MANAGER_0.3.46_NATIVE_TRANSLATION_GROUPS.md`;
- `docs/SEO_GEO_MANAGER_0.3.47_NATIVE_SEO_METADATA.md`;
- `docs/SEO_GEO_MANAGER_0.3.48_YOAST_ABILITIES.md`.

The Manager focus is generic semantic control primitives. Strategy stays in external orchestration; migration transport stays in Migration Bridge; rendering stays in Theme/current public stack.
