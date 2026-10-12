# SEO/GEO Manager change-set contract

## Purpose

M2 turns Manager from a read-only intelligence layer into a controlled WordPress mutation endpoint while preserving the product rules established for Build / Finish, Optimize and Grow.

The baseline mutation loop is:

```text
inspect current resource
        ↓
read fingerprint
        ↓
prepare versioned change set
        ↓
POST /changes/preview
        ↓
review field-level diff
        ↓
POST /changes/apply
        ↓
verify exact resulting values
        ↓
record operation + rollback state
```

No change set may silently overwrite a newer human edit.

## Schema v1

```json
{
  "schema_version": 1,
  "idempotency_key": "client-generated-stable-key",
  "target": {
    "id": 123,
    "expected_fingerprint": "sha256-from-content-read"
  },
  "changes": {
    "title": "New title",
    "slug": "new-slug",
    "excerpt": "Updated excerpt",
    "content": "Updated WordPress content",
    "status": "draft"
  },
  "allow_published_target": false,
  "allow_empty_content": false
}
```

Only fields actually being changed need to be present.

Initial supported fields:

- `title`
- `slug`
- `excerpt`
- `content`
- `status` (`draft` or `pending` only in the baseline)

SEO-provider metadata and Theme structured-model writes are separate adapters and are not silently treated as ordinary post fields.

## Preview

`POST /wp-json/seo-geo-manager/v1/changes/preview`

Preview is read-only. It validates:

- schema version;
- target existence and edit permission;
- expected fingerprint;
- supported fields;
- slug collisions;
- destructive empty-content intent;
- published-target policy state.

It returns a field-level `from` / `to` diff and does not reserve an idempotency key.

## Apply

`POST /wp-json/seo-geo-manager/v1/changes/apply`

Apply requires an idempotency key.

Before mutation Manager verifies the target fingerprint again. If the resource changed after inspection/preview, apply returns a stale-resource conflict and performs no write.

The idempotency key is reserved through a WordPress option whose name is derived from the key hash. Reusing the same key with the same normalized payload returns the existing operation. Reusing it with a different payload is a conflict.

## Published-target guard

Draft-first remains the baseline.

A currently published target is blocked unless `allow_published_target=true` is supplied explicitly and the authenticated user has the post type's publish capability.

This is intended for controlled staging/Build-Finish workflows such as EMMAKE `/nuevaweb/`. It is not an implicit permission for autonomous production publishing.

Future environment/policy configuration may distinguish staging from production more explicitly, but the baseline remains opt-in.

## Slug safety

Manager blocks a requested slug when another page/post of the same post type already uses it. It does not silently accept WordPress suffixing such as `-2` because that would change intended URL identity without approval.

## Operation record

A successful apply records:

- operation UUID;
- schema version;
- target ID/type;
- idempotency key + normalized payload hash;
- expected/before/after fingerprints;
- previous values for Manager-owned changed fields;
- requested changes;
- field-level diff;
- WordPress revision ID when one is created;
- post-apply verification result;
- timestamp.

Secrets are never stored in the operation record.

## Verification

After `wp_update_post`, Manager reloads the resource and verifies every requested field exactly.

If WordPress or another integration changes the requested value, Manager records `verification-failed` and returns a conflict with the operation ID so the change remains traceable and can be rolled back when safe.

## Operation read

`GET /wp-json/seo-geo-manager/v1/changes/{operation_id}`

The caller must still have permission to edit the target resource.

## Rollback

`POST /wp-json/seo-geo-manager/v1/changes/{operation_id}/rollback`

Rollback is bounded to the fields that Manager changed.

Before rollback, Manager requires the current resource fingerprint to equal the operation's recorded `after_fingerprint`. If a human, plugin or later operation has changed the resource in the meantime, rollback is blocked rather than overwriting newer work.

Rollback restores only the captured Manager-owned values. It does not restore unrelated site-wide state.

## Next M2 steps

The baseline change-set engine is followed by:

1. dedicated tests for preview/idempotency/stale-write/slug-collision/rollback behavior;
2. environment policy (`staging`, `production`, explicit approval mode);
3. Theme structured-model adapter;
4. SEO Output Authority Resolver adapters;
5. operation history/admin visibility;
6. creation manifests for new draft pages/posts;
7. rendered verification hooks after accepted changes.
