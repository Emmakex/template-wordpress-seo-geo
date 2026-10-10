# SEO/GEO Manager 0.3.30 — protected local slug repair

Status: **field-derived implementation slice**.

## Why this exists

Manager 0.3.29 proved the historical URL map and selected the clean target architecture independently from legacy category debt. On the first EMMAKE field target, the map is complete but two local `post_name` values still contain the migration marker corruption.

Those two damaged values must be repaired before the global permalink plan can become eligible for review. Repairing them is deliberately a separate operation from changing `permalink_structure` or activating 301s.

## Field evidence that opened this slice

EMMAKE `/nuevaweb/`, Manager 0.3.29:

- 1,726 current published posts scanned;
- 1,726 historical posts scanned;
- 1,726 identities matched;
- zero missing historical identities;
- 2 identities recovered through the narrow migration-corrupted-slug same-ID fallback;
- clean target `/blog/%postname%/` supported by 1,702 / 1,726 historical routes (`0.986095`);
- 24 historical outliers identified before local slug repair;
- final permalink Apply blocked while 2 damaged local slugs remain.

## New protected operation

REST endpoints:

- `POST /seo-geo-manager/v1/permalinks/slug-repair/preview`
- `POST /seo-geo-manager/v1/permalinks/slug-repair/apply`
- existing `POST /seo-geo-manager/v1/permalinks/operations/{operation_id}/rollback` dispatches `post-slug-repair` operations to the protected rollback engine.

The WordPress dashboard exposes the same workflow after a Field Gate report contains `local_slug_repairs`:

```text
Field Gate -> Preview slug repair -> explicit confirmation -> Apply -> verify -> rerun Field Gate
```

## Preview guards

A candidate is accepted only when all of these remain true:

1. Historical authority is revalidated from the supplied legacy origin.
2. The expected authority fingerprint matches.
3. The expected authoritative plan fingerprint matches.
4. The post is still a published WordPress post.
5. Its current local `post_name` exactly matches the damaged value captured by the plan.
6. The target is derived from the verified historical slug only.
7. `wp_unique_post_slug()` confirms the target remains unique for that post.
8. Every planner collision is one of the exact migration-corrupted slugs being repaired; unrelated collisions block the operation.
9. Every expected damaged slug has a valid repair candidate.

Preview is read-only.

## Apply contract

Apply requires:

- the current repair fingerprint;
- the current authority fingerprint;
- the current plan fingerprint;
- a bounded idempotency key;
- `confirm_slug_repair=true`;
- the current production environment fingerprint when production write approval is required.

Forward writes use WordPress `wp_update_post()` and verify the exact persisted target slug.

The operation snapshots:

- exact damaged `before_slug`;
- repaired `after_slug`;
- before/after slug fingerprints;
- existing `_wp_old_slug` metadata;
- the historical URL evidence;
- environment binding;
- next authority/plan fingerprints after verification.

## What Apply is forbidden to change

The protected slug operation does **not**:

- change `permalink_structure`;
- activate or replace the Manager redirect runtime;
- register the final 301 map;
- flush rewrite rules;
- authorize the next permalink operation automatically.

## Post-write verification

After all candidate slugs are stored, Manager rebuilds the authoritative plan and requires:

- historical mapping is still authoritative;
- zero local slug repairs remain;
- the selected target structure is unchanged.

Any verification failure restores the exact previous local state.

## Rollback contract

Rollback is stale-safe.

Before restoring anything, every repaired post must still have the exact slug produced by this operation. If any later edit intervened, rollback is blocked instead of overwriting newer state.

When rollback is allowed, Manager restores:

- the exact original migration-corrupted `post_name` value;
- the exact pre-operation `_wp_old_slug` metadata set.

The exact corrupt value is restored intentionally because rollback means restoring the pre-operation database state, not sanitizing it into a third state.

## Acceptance fixture

The WordPress 7.1 / PHP 8.2 fixture verifies:

- read-only repair preview;
- protected Apply;
- exact stored-value verification;
- idempotent replay;
- fresh plan contains zero pending slug repairs;
- target remains `/blog/%postname%/`;
- stale rollback rejection after an intervening slug change;
- exact rollback after state is restored to the operation's expected after-state;
- `permalink_structure` remains untouched;
- redirect runtime remains untouched.

## Mandatory field sequence

After installing 0.3.30 on the clone:

1. rerun Field Gate with the historical origin;
2. preview the protected slug repair;
3. confirm the exact repair set;
4. explicitly apply the protected repair;
5. rerun Field Gate;
6. retain/download the new JSON evidence;
7. review the fresh redirect count and atomic runtime preview;
8. only then consider the separately confirmed permalink + 301 operation.

No Migration Bridge Reset, clone rebuild or Theme reinstall is required for this slice.
