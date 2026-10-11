# SEO/GEO Manager — Remote access runbook

Status: **0.3.50 development operating contract**

Canonical architecture: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.
Reference implementation pattern: `Emmakex/eduardoyauriluna-research` Research Manager.

## Product rule

SEO/GEO Manager is the permanent WordPress control plane. External orchestration talks to Manager semantic capabilities; it does not receive a generic WordPress proxy, shell or human administrator password.

Preferred operating path from 0.3.50:

```text
local WordPress administrator
 -> authorises one SEO/GEO Manager connection
 -> Manager issues one scoped revocable credential
 -> external orchestration calls Manager semantic endpoints
 -> Manager inspects / previews / applies / verifies through accepted services
 -> local administrator can disable, rotate or revoke the connection at any time
```

The architecture intentionally mirrors the Research Manager connection model so both products behave consistently.

## Local connection console

Administrators manage the connection from:

```text
SEO/GEO Manager -> Conexión remota
```

The console owns:

- stable connection ID;
- Manager credential generation;
- one-time credential display;
- hash-only persistence;
- credential rotation;
- enable / disable;
- revocation;
- explicit scopes;
- bound local WordPress administrator;
- bounded non-secret audit.

The Manager never needs to expose the human WordPress password.

## Scope model

Supported scope vocabulary begins with:

```text
site.read
site.diagnostics
content.read
content.write
seo.read
seo.write
media.read
media.write
navigation.read
navigation.write
operations.read
operations.apply
operations.rollback
credentials.manage
```

New connections start read-only with:

```text
site.read
site.diagnostics
operations.read
```

A scope grants access only to an accepted Manager surface. It never bypasses the underlying WordPress capability or operation safety checks.

## Request authentication

Outside local/development, remote Manager traffic requires HTTPS.

Authenticated requests use:

```text
Authorization: Bearer <Manager credential>
X-SEO-GEO-Manager-Request-Id: <stable request id>
X-SEO-GEO-Manager-Nonce: <unique nonce>
X-SEO-GEO-Manager-Timestamp: <unix timestamp>
```

The bridge validates:

- active Manager credential;
- credential hash match;
- granted scope;
- bound local WordPress administrator remains authorised;
- timestamp validity window;
- nonce replay protection;
- per-connection rate limit.

Mutation requests additionally use request fingerprints and idempotency in the R3 lifecycle.

## R2 read surface

0.3.50 R2 exposes only bounded authenticated reads:

```text
GET /wp-json/seo-geo-manager/v1/remote/status
GET /wp-json/seo-geo-manager/v1/remote/capabilities
GET /wp-json/seo-geo-manager/v1/remote/site-intelligence
GET /wp-json/seo-geo-manager/v1/remote/audit
```

Required scopes:

```text
/remote/status            -> site.read
/remote/capabilities      -> site.read
/remote/site-intelligence -> site.diagnostics
/remote/audit             -> site.diagnostics
```

R2 deliberately exposes no remote mutation route.

## R3 mutation boundary

Remote writes must not create a second mutation engine.

R3 will bind the authenticated bridge to the Manager services already accepted in C3–C8:

```text
Inspect
 -> Preview / Plan
 -> Apply exact accepted operation
 -> exact stored readback
 -> rendered verification where applicable
 -> operation evidence
 -> stale-safe rollback where supported
```

Examples:

- normal content through content/change-set services;
- Theme slots through Theme semantic contracts;
- SEO through the resolved Theme-native or supported provider adapter;
- navigation through the accepted navigation adapter;
- media through accepted media operations.

No raw arbitrary meta editor, arbitrary REST relay or generic code execution surface will be introduced.

## Security invariants

1. HTTPS outside local/development.
2. Credential value shown only at generation/rotation time.
3. Only credential hash is persisted.
4. Scopes are explicit and locally editable.
5. Every request has request ID, nonce and timestamp.
6. Nonces cannot be replayed within their validity window.
7. Remote requests are rate-limited per connection.
8. Audit contains metadata only; no authorization header, request body or credential.
9. Revocation immediately invalidates the Manager credential.
10. The bound WordPress administrator must remain authorised.
11. No generic shell or generic REST proxy.
12. Write scopes never bypass Preview / verification / rollback policy.

## Credential lifecycle

```text
administrator opens Conexión remota
 -> select minimum scopes
 -> generate credential
 -> copy one-time credential into external secret runtime
 -> use authenticated Manager API
 -> rotate when desired
 -> disable temporarily when desired
 -> revoke to terminate the connection
```

After revocation a new credential must be generated before remote access can resume.

## Secret handling

Never store these values in Git, Manager evidence, JSON exports, screenshots or support messages:

- Manager bearer credential;
- WordPress human password;
- Application Password values;
- Authorization headers;
- WordPress cookies/nonces;
- hosting/SSH/database credentials;
- third-party provider secrets.

Connection ID, credential prefix, scopes, generation number, request IDs and operation IDs are non-secret operational identifiers.

## Bounded audit

The remote audit stores at most a bounded recent history and records fields such as:

```text
timestamp
request_id
connection_id
wordpress_user_id
method
route
scope
event
outcome
error_code
operation_id
reason
```

It never stores the credential or request body.

## Application Password compatibility

The 0.3.34–0.3.49 Manager line added WordPress Application Password transport and acceptance. That path remains supported for compatibility, diagnostics and the frozen 0.3.49 C2/C9 evidence model.

It is **not the preferred persistent product UX from 0.3.50 onward**.

Do not create temporary WordPress users/Application Passwords merely because the old runbook required them when the Manager-owned connection is available and sufficient.

## C9 relationship

C9 remains a client-agnostic acceptance track, not the product control plane.

The frozen 0.3.49 candidate can still be evaluated under its original Application Password field contract without redefining the 0.3.50 operating model.

For 0.3.50+ field promotion, add Manager-owned bridge evidence separately:

- exact package identity;
- local connection created;
- bounded scopes;
- successful authenticated read;
- scope denial proof;
- nonce replay rejection;
- credential rotation/revocation proof;
- no secrets in audit/evidence.

## Dashboard relationship

The top-level Manager screen is a permanent control plane:

```text
Build / Finish
Optimize
Grow
```

Migration, Field Gate, permalink cutover and 301 tooling are retained inside Build / Finish as bounded compatibility/finalization tools. They no longer define the Manager product identity.
