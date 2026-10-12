# SEO/GEO Manager 0.3.51 — R3 remote content-change

Status: **development contract — requires repository certification before installation/promotion**.

0.3.51 is the first bounded remote mutation slice on top of the Manager-owned connection introduced and repository-certified in 0.3.50 R2.

The design follows the remote operating pattern already used by Research Manager:

```text
Plan -> Apply -> Verify -> Rollback
```

It does **not** introduce a second mutation engine. R3 delegates normal page/post field changes to the existing `ChangeSetEngine` accepted by C3.

## Supported operation

The only remote mutation operation in this slice is:

```text
content-change
```

It targets an existing WordPress `page` or `post` and may use only the fields already accepted by `ChangeSetEngine`:

- title;
- slug;
- excerpt;
- content;
- draft/pending status.

Initial R3 deliberately does not expose remote SEO-provider writes, Theme structured writes, media writes, navigation writes, taxonomy changes, author binding, translation changes, permalink administration or rendered verification.

## Routes

```text
POST /wp-json/seo-geo-manager/v1/remote/plans
GET  /wp-json/seo-geo-manager/v1/remote/plans/{plan_id}
POST /wp-json/seo-geo-manager/v1/remote/plans/{plan_id}/apply
GET  /wp-json/seo-geo-manager/v1/remote/operations/{operation_id}
POST /wp-json/seo-geo-manager/v1/remote/operations/{operation_id}/verify
POST /wp-json/seo-geo-manager/v1/remote/operations/{operation_id}/rollback
```

Scopes:

```text
plan             -> content.write
plan read        -> operations.read
apply            -> operations.apply
operation read   -> operations.read
verify           -> operations.read
rollback         -> operations.rollback
```

## Plan contract

A plan is private Manager state. It contains the exact semantic payload and exact preview used to prepare the mutation.

The plan is bound to:

- one Manager `connection_id`;
- one local WordPress execution user;
- the environment fingerprint at plan time;
- the target resource fingerprint inside the semantic payload.

Prepared plans have a 15-minute Apply TTL and are retained for at most one hour for bounded lifecycle inspection. An immutable checksum covers the plan identity, payload, preview, environment and expiry contract.

Apply cannot replace the payload that was previewed.

## Apply contract

Apply requires:

- the same authenticated Manager connection that created the plan;
- `operations.apply`;
- `confirm=true`;
- the current production environment fingerprint where production acknowledgement is required;
- the plan environment to still match the current environment;
- the target fingerprint to still match the resource inspected before Plan;
- a prepared, non-expired plan.

The remote bridge then delegates to `ChangeSetEngine::apply()` and uses `remote-plan:{plan_id}` as the semantic operation idempotency key.

The resulting private Manager operation is additionally bound to:

- `remote_connection_id`;
- `remote_plan_id`;
- `remote_actor_user_id`.

## Request idempotency

The remote request layer also retains request-ID idempotency. Replaying the same authenticated request ID with the same route/method/body returns the already-created plan/operation result instead of repeating the mutation.

A reused request ID with a different request body remains rejected.

## Verify contract

Remote Verify does not infer success from HTTP transport or operation history. It reloads the real WordPress target and compares its current `ContentFingerprint` with the operation's stored `after_fingerprint`.

A newer human or Manager edit therefore makes verification stale rather than falsely successful.

## Rollback contract

Rollback requires:

- ownership by the same remote Manager connection;
- `operations.rollback`;
- `confirm=true`;
- current environment acknowledgement where required;
- operation/environment match;
- the underlying `ChangeSetEngine` stale-safe rollback guard.

Rollback changes only the fields originally changed by the Manager operation and is blocked if the target has been edited since Apply.

## Privacy boundary

Remote operation responses expose bounded evidence such as:

- operation ID/status;
- target ID/type;
- changed field names;
- before/after fingerprints;
- verification state;
- bounded environment state;
- remote plan ID.

They do not expose the private stored `before` values or full `changes` payload through the remote operation-read route. Bearer credentials are never written to Manager audit/evidence.

## Runtime acceptance

The pinned WordPress 7.1 / PHP 8.2 acceptance must prove on a dedicated draft:

```text
create draft
 -> Plan
 -> prove Plan did not mutate
 -> Apply exact title/content change
 -> replay Apply request idempotently
 -> Verify exact stored fingerprint
 -> read privacy-bounded operation evidence
 -> Rollback
 -> prove exact original title/content/status restored
 -> prove remote audit contains plan/operation IDs but no bearer credential
```

The fixture deletes the dedicated draft after acceptance.

## Non-goals for 0.3.51

- no generic REST relay;
- no generic shell;
- no arbitrary/raw metadata writer;
- no speculative SEO/media/navigation remote writer;
- no automatic publication;
- no rendered verification in the initial R3 slice;
- no client-specific workflow hardcoding.

Future remote mutation families must reuse their already-accepted semantic Manager engines and receive their own explicit contract/runtime acceptance before being advertised in `/remote/capabilities`.
