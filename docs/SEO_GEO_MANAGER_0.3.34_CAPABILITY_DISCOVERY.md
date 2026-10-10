# SEO/GEO Manager 0.3.34 — Remote capability discovery

Status: **implementation candidate**

Date: 2026-10-10

Canonical architecture: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Purpose

0.3.34 is the first implementation slice after the three-product boundary freeze that moves Manager explicitly toward its permanent role as the generic secure bridge between external orchestration and a client's WordPress.

The new capability contract answers one question before remote work begins:

> **What is this authenticated identity allowed to do through this Manager installation?**

External orchestration must not assume that every client has the same WordPress role, Theme, providers or mutation permissions.

## Endpoint

Authenticated endpoint:

```text
GET /wp-json/seo-geo-manager/v1/capabilities
```

The endpoint requires an authenticated WordPress identity capable of at least `edit_posts` or site administration.

It is intentionally read-only and bounded.

## Returned contract

The response contains:

- schema version;
- Manager service/version/API namespace;
- current authenticated WordPress user ID only;
- authentication/transport safety declarations;
- current Manager environment fingerprint policy;
- bounded WordPress runtime identity;
- per-capability availability;
- required WordPress capability for each exposed capability;
- supported Manager operation families and their method/path/risk class;
- safety features supported by this Manager package.

The endpoint does **not** return:

- passwords;
- Application Password values;
- cookies;
- REST nonces;
- API secrets;
- private post bodies;
- arbitrary WordPress options;
- rollback payload values;
- unrestricted command execution.

## Authentication model

Manager relies on WordPress authentication rather than inventing a second account system.

For managed remote operation, the preferred baseline is:

```text
HTTPS
  + dedicated WordPress operator identity
  + WordPress Application Password
  + least-privilege role/capabilities
  + revocable credential
```

Browser-admin calls continue using the logged-in WordPress session and REST nonce protections.

The capability endpoint declares the actual WordPress Application Password support/availability observed for the current principal but never returns or creates credentials.

## Capability families

Initial 0.3.34 discovery includes:

- Site Intelligence;
- content read;
- generic content change sets;
- Theme structured-content change sets;
- navigation change sets;
- media write capability;
- taxonomy management;
- post publication;
- page editing/publication;
- site-wide operations;
- permalink administration;
- operation-history summaries.

Availability is evaluated against the **current authenticated WordPress identity**, not against a hardcoded agency administrator assumption.

## Operation discovery

The manifest exposes bounded semantic operation families such as:

```text
inspect.site
inspect.snapshot
content.preview
content.apply
theme.preview
theme.apply
navigation.preview
navigation.apply
operations.read
field_gate.inspect
permalinks.admin
```

Each operation reports:

- whether the current principal may use it;
- HTTP method family;
- relative Manager API path;
- risk classification.

This is discovery metadata, not execution authority by itself. Every target endpoint still performs its own WordPress capability, stale-state, environment and mutation checks.

## Safety invariants

0.3.34 establishes these invariants for remote discovery:

1. **Authenticated discovery only.** Public `/health` remains separate and minimal.
2. **Least privilege is visible.** External orchestration can adapt to the actual principal instead of assuming administrator rights.
3. **No secret reflection.** Credentials/nonces are never returned.
4. **No generic shell.** Capabilities map to semantic WordPress operations only.
5. **Endpoint permission is not mutation permission.** Every mutation endpoint retains its own authorization and guards.
6. **Environment policy remains explicit.** The capability response includes the current bounded environment contract used by guarded writes.
7. **Client agnostic.** No EMMAKE-specific domain, page, preset or business rule is encoded in capability discovery.

## External orchestration flow

The intended connection handshake becomes:

```text
GET /health
  -> authenticate
  -> GET /capabilities
  -> GET /site/snapshot and/or /site/intelligence
  -> external reasoning
  -> preview supported operation
  -> apply with required guards
  -> verify
  -> record operation
```

This moves the Manager closer to being a reliable operational API for any supported client WordPress.

## Relationship to C2 roadmap

This slice implements the **capability discovery** part of C2 — Authentication / capability contract.

C2 is not fully closed by 0.3.34. Remaining acceptance work includes:

- explicit real HTTPS Application Password field connection on a client/sandbox installation;
- least-privilege operator-role acceptance rather than administrator-only acceptance;
- credential revocation verification;
- bounded remote-auth failure behavior;
- final remote-operation runbook.

No autonomous SEO/content intelligence is added to WordPress in this slice.

## EMMAKE installation boundary

Do **not** replace the currently installed 0.3.33 on `/nuevaweb/` merely because 0.3.34 exists in the development branch.

0.3.33 remains the field candidate for the pending final permalink/301 UI cycle. 0.3.34 should be installed on EMMAKE only after its repository CI is green and we intentionally move the field pilot to the remote-control/capability acceptance phase.
