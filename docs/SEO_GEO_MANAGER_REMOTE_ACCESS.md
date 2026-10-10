# SEO/GEO Manager — Remote access runbook

Status: **active C2 operating contract**

Canonical architecture: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

This runbook defines the supported operating direction for connecting external orchestration to a client WordPress through SEO/GEO Manager.

The goal is not to expose WordPress broadly. The goal is to create one revocable, least-privilege, auditable bridge identity that can use the exact Manager capabilities required by the engagement.

## Core rule

> External orchestration authenticates as a dedicated WordPress identity. Manager exposes semantic WordPress operations. No generic shell and no shared human administrator password are required for routine managed operation.

## Transport baseline

Production remote operation requires HTTPS.

The preferred baseline is:

```text
HTTPS WordPress origin
+ active SEO/GEO Manager
+ dedicated WordPress operator user
+ WordPress Application Password
+ only the WordPress capabilities required by the operating profile
```

Do not commit the username, Application Password or Authorization header to the repository, client content, JSON evidence or support screenshots.

## Connection handshake

A controller should connect in this order:

```text
1. GET /wp-json/seo-geo-manager/v1/health
2. authenticate using the dedicated WordPress identity
3. GET /wp-json/seo-geo-manager/v1/capabilities
4. inspect environment fingerprint and available operation families
5. GET /site/snapshot and/or /site/intelligence
6. prepare work externally
7. call the exact preview endpoint
8. apply only after preview/guards pass
9. verify stored/rendered state
10. retain operation ID/evidence
```

The public health endpoint proves only that Manager is reachable. It does not prove that the caller is authorized to operate the site.

## Identity profiles

The preferred long-term model is capability-based, not role-name-based. Standard WordPress roles are useful acceptance fixtures but are not the product contract.

### Profile A — Content operator

Intended for routine blog/content operations where site-wide configuration is not required.

Typical required capabilities may include:

- `edit_posts`;
- `edit_pages` where page updates are allowed;
- `publish_posts` only if direct/scheduled publication is part of policy;
- `publish_pages` only if page publication is part of policy;
- `upload_files` if media operations are allowed;
- taxonomy capabilities only when category/tag changes are allowed.

Must not receive `manage_options` merely for convenience.

### Profile B — Site operator

Used only when workflows need site-wide Manager operations such as guarded permalink/Field Gate administration.

This profile may require broader WordPress authority and therefore has a higher-risk approval policy.

Use it for bounded Build / Finish work, not as the default permanent credential when a narrower content operator is sufficient.

### Profile C — Local human administrator

Normal WordPress administrator used by an authorized human for installation, recovery and local high-risk confirmation.

This is not the credential external orchestration should routinely store/use if a narrower dedicated operator can perform the job.

## Application Password lifecycle

Application Passwords are WordPress credentials for API authentication. Manager does not create, store or reveal their value.

The operating lifecycle is:

```text
create dedicated WordPress operator
 -> create one named Application Password for the managed connection
 -> store it only in the external secret store/runtime
 -> verify /capabilities
 -> operate
 -> revoke the Application Password when access is no longer required
```

If the credential is suspected to be exposed, revoke it and create a replacement. Do not reuse a human account password as the remote API credential.

## Capability discovery

Manager 0.3.34+ exposes:

```text
GET /wp-json/seo-geo-manager/v1/capabilities
```

The manifest tells the external controller which semantic operation families are available to the current identity.

The controller must adapt to the manifest rather than assume administrator access.

Examples:

- an editorial identity may be able to preview/apply normal content but not run Field Gate/permalink administration;
- an identity without `edit_theme_options` must not be offered navigation mutation;
- an identity without publish capabilities may still create/update drafts when the target endpoint contract permits it;
- a site-wide operation must remain unavailable to a normal editor even if the external strategy wants to perform it.

## Environment binding

Manager exposes a bounded environment fingerprint derived from the current WordPress environment/home/site identity.

For guarded production mutations, the caller must use the fresh fingerprint obtained during inspection when the endpoint requires environment acknowledgement.

This prevents a stale plan prepared against one clone/environment from being blindly replayed against another.

The fingerprint is an acknowledgement token, not a secret.

## Request safety

External orchestration must follow these rules:

1. fetch fresh resource/environment state before a meaningful mutation;
2. use preview before apply;
3. send expected resource fingerprint/revision when required;
4. send a stable idempotency key for retryable writes;
5. never infer success from HTTP transport alone — inspect Manager result/verification state;
6. retain the returned operation ID;
7. do not retry a failed stale-state operation by removing its safety fields;
8. do not escalate to administrator credentials simply because a narrower identity lacks a capability; decide whether the operation should be separately authorized.

## Secret-handling rules

Never include these values in Manager evidence or repository artifacts:

- human passwords;
- Application Password values;
- HTTP Authorization headers;
- session cookies;
- WordPress REST nonces;
- hosting/SSH/database credentials;
- third-party provider secrets.

Capability discovery may report whether Application Passwords are supported/available, but never their values.

## Revocation acceptance

C2 field acceptance must prove revocation:

1. authenticate successfully with the dedicated Application Password;
2. fetch `/capabilities`;
3. revoke that exact Application Password in WordPress;
4. retry the authenticated request with the revoked credential;
5. confirm it can no longer access the authenticated Manager endpoint;
6. confirm normal public frontend and public `/health` behavior remain unaffected.

## Insufficient-capability acceptance

C2 must also prove least privilege:

- anonymous caller cannot read `/capabilities`;
- an authenticated identity without editorial/site capabilities cannot read it;
- a content/editor identity can discover/use content capabilities but cannot discover site-wide Manager operations as available;
- only a sufficiently authorized site operator may receive site-wide capability availability;
- target endpoints repeat their own authorization checks even when an operation appeared in the manifest.

Repository WordPress CI covers the role/capability separation. Real HTTPS/Application Password transport and revocation remain field acceptance.

## Field acceptance record

For each client installation, record only non-secret evidence:

```text
site identifier
Manager version
WordPress version
connection date
auth method = application-password
operator WordPress user ID or internal reference
capability profile
/capabilities schema version
environment fingerprint hash
successful read-only handshake
successful bounded preview
successful accepted write/verify when part of the test
revocation test status
```

Never include the Application Password itself.

## EMMAKE sequence

EMMAKE remains the first Theme-site field reference.

The current order is:

```text
1. finish the pending 0.3.33 permalink/301 UI field cycle
2. confirm EMMAKE is not left in a partially applied state
3. install a CI-green 0.3.34+ candidate intentionally for C2
4. create/use a dedicated remote operator identity
5. verify HTTPS Application Password connection
6. GET /capabilities
7. prove least-privilege behavior
8. run one read-only Site Intelligence cycle remotely
9. run one bounded Preview -> Apply -> Verify cycle
10. revoke credential and prove access stops
```

Do not rerun Migration Bridge, Reset or Theme hydration merely to test Manager remote access.

## Non-Theme client sequence

C9 requires a second accepted site using a supported non-SEO/GEO Theme stack.

The same connection handshake must work without a client-specific Manager fork:

```text
health
 -> authenticate
 -> capabilities
 -> site intelligence/provider discovery
 -> accepted generic/provider operation
 -> verify
 -> evidence/revocation
```

Unsupported provider/layout surfaces remain unavailable or blocked rather than guessed.

## Done condition for C2

C2 closes when all of these are proven:

- authenticated `/capabilities` contract is stable;
- anonymous/insufficient identities are rejected;
- least-privilege capability differences are accepted in real WordPress CI;
- HTTPS Application Password authentication works on a real field installation;
- the credential can be revoked and revocation immediately blocks authenticated Manager access;
- no secret material appears in Manager responses/evidence;
- a remote controller can complete the handshake without using a generic shell;
- the runbook is sufficient to repeat the connection on another client.
