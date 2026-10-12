# SEO/GEO Manager 0.3.50 — permanent control plane + Manager-owned remote bridge

Status: development contract

## Why this slice exists

SEO/GEO Manager is a permanent WordPress control plane. It must not present itself primarily as a migration-finalization utility.

The operating reference for this slice is the proven pattern already used by `Emmakex/eduardoyauriluna-research`:

```text
operator / external orchestration
 -> authenticated Manager bridge
 -> semantic Manager capabilities
 -> WordPress / accepted Theme or SEO provider authority
```

Migration Bridge remains the product that brings/rescues/transitions a site. Theme remains the strategic renderer. Manager remains installed afterwards for Build / Finish, Optimize and Grow.

## Dashboard contract

The top-level SEO/GEO Manager screen is a permanent workspace with three lifecycle surfaces:

1. **Build / Finish** — complete structure, content, SEO, links, media and launch readiness.
2. **Optimize** — inspect a live site and apply bounded measurable improvements.
3. **Grow** — create new resources, pages, content, languages and assets through semantic Manager operations.

Field Gate, permalink migration and 301 tooling remain supported but are nested inside Build / Finish as bounded compatibility/finalization tools. They do not define the product identity.

The permanent core loop remains:

```text
Inspect -> Preview -> Apply -> Verify -> History / Rollback
```

## Remote bridge direction

0.3.50 starts a Manager-owned remote connection model aligned with Research Manager.

Local WordPress administrators receive a **Conexión remota** security console that owns:

- one stable site connection ID;
- one revocable Manager credential at a time;
- credential rotation;
- enable / disable;
- explicit scopes;
- bound local WordPress administrator identity;
- non-secret recent audit.

The generated credential is shown once. Manager persists only a password hash plus a short non-secret prefix.

Initial scope vocabulary:

- `site.read`
- `site.diagnostics`
- `content.read`
- `content.write`
- `seo.read`
- `seo.write`
- `media.read`
- `media.write`
- `navigation.read`
- `navigation.write`
- `operations.read`
- `operations.apply`
- `operations.rollback`
- `credentials.manage`

Default scopes are read-only:

```text
site.read
site.diagnostics
operations.read
```

## Request safety primitives

The remote line uses the same security shape as Research Manager, adapted to SEO/GEO Manager:

- HTTPS outside local/development;
- Manager bearer credential;
- unique request ID;
- nonce;
- bounded timestamp window;
- replay protection;
- per-connection rate limiting;
- idempotency memory for retry-safe mutations;
- bounded audit without request bodies or secrets.

Header names are Manager-specific:

```text
Authorization: Bearer <credential>
X-SEO-GEO-Manager-Request-Id: <stable request id>
X-SEO-GEO-Manager-Nonce: <unique nonce>
X-SEO-GEO-Manager-Timestamp: <unix timestamp>
```

## Important boundary

This is **not** a generic WordPress proxy and **not** a remote shell.

Remote routes may expose only accepted semantic Manager capabilities. Write routes must reuse existing Manager safety contracts instead of creating a second mutation engine.

In particular:

- content writes reuse content/change-set services;
- Theme writes reuse Theme semantic contracts;
- SEO writes reuse the resolved Theme-native or supported provider adapter;
- media/navigation operations reuse their existing guarded adapters;
- every meaningful write keeps Preview, expected state, environment binding, exact readback and rollback where supported.

## Application Password relationship

WordPress Application Password support introduced in the 0.3.34–0.3.49 line remains useful for compatibility, diagnostics and historical C2/C9 acceptance.

It is no longer the intended primary user experience for persistent managed operation.

The preferred product path from 0.3.50 onward is:

```text
local administrator authorizes Manager connection once
 -> Manager issues scoped revocable credential
 -> external orchestration uses semantic Manager API
 -> administrator can disable / rotate / revoke locally at any time
```

No human WordPress password is shared.

## 0.3.50 implementation slices

### R1 — local control plane foundation

Implemented in this development line:

- permanent Build / Finish + Optimize + Grow dashboard;
- migration/finalization nested as bounded Build / Finish tooling;
- stable remote connection identity;
- scope model;
- one-time Manager credential generation with hash-only persistence;
- rotation, disable, enable and revocation controls;
- request metadata/replay/rate/idempotency guard primitives;
- bounded non-secret audit;
- local `Conexión remota` admin console.

### R2 — authenticated semantic remote reads

Next acceptance slice:

- authenticated remote status;
- remote capabilities;
- bounded site intelligence;
- bounded operation/audit reads;
- no mutation transport yet.

### R3 — semantic remote mutation lifecycle

Only after R2 acceptance:

- plan/preview;
- exact apply through existing Manager services;
- operation status;
- verify;
- rollback;
- scope and local WordPress capability re-checks.

No raw metadata editor or generic REST passthrough will be introduced.

## Relationship to 0.3.49 field candidate

The installed 0.3.49 package remains the frozen C9 reference candidate with its existing deterministic identity.

0.3.50 is a new development package and must receive a new deterministic ZIP/tree identity before installation or field promotion. Do not describe 0.3.50 as equivalent to the frozen 0.3.49 artifact.

C9 remains an acceptance track, not the Manager product UX.
