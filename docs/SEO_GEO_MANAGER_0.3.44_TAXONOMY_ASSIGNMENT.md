# SEO/GEO Manager 0.3.44 — Taxonomy Assignment

Status: **C4.1 repository acceptance complete**

Canonical architecture: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Purpose

Manager 0.3.44 adds a generic, bounded WordPress taxonomy-assignment primitive for external orchestration.

The responsibility split remains explicit:

```text
External orchestration
    decides which existing categories/tags/terms should be assigned and why

SEO/GEO Manager
    inspects the real WordPress state
    validates authority and target identity
    previews the exact replacement
    applies only the accepted assignment
    verifies persistence
    records the operation
    rolls back only when still safe

WordPress
    remains authority for resources, taxonomies, terms and relationships
```

Manager does **not** decide taxonomy strategy and does not invent categories or tags in this slice.

## Product boundary

This capability belongs to Manager because it answers:

> How can an authorized external operator safely read and change the existing taxonomy relationships of a WordPress content resource?

It does not answer:

> Which taxonomy strategy should the client use, which cluster should a post belong to, or which categories/tags should be created?

Those decisions remain in external orchestration.

## Scope

0.3.44 supports:

- normal WordPress posts and pages when the selected taxonomy is actually registered for that post type;
- any registered taxonomy whose real WordPress `assign_terms` capability is available to the current principal;
- exact read of the current assigned term IDs;
- bounded term summaries for current assignments;
- deterministic assignment fingerprint;
- Preview without mutation;
- exact replacement of the assignment with **existing term IDs only**;
- explicit approval before changing a published resource;
- production environment binding;
- idempotent Apply;
- exact readback verification;
- operation history/evidence;
- stale-safe rollback;
- permission re-check before rollback.

The first repository acceptance proves both:

- hierarchical taxonomy: `category`;
- flat taxonomy: `post_tag`.

The implementation is generic and is not hardcoded to those two names.

## Non-goals

0.3.44 intentionally does not:

- create taxonomy terms;
- rename/delete terms;
- create or register taxonomies;
- decide category/tag strategy;
- infer terms from article text;
- create client-specific taxonomy rules;
- modify `post_content` while assigning terms;
- bypass WordPress taxonomy capabilities;
- expose a generic database/meta mutation endpoint.

Term creation, if later required, must be a separate explicit primitive with its own permission, duplicate, slug and rollback contract. It must never happen as a hidden side effect of assignment.

## Remote routes

Base namespace:

```text
/wp-json/seo-geo-manager/v1
```

### Inspect current assignment

```text
GET /content/{resource_id}/taxonomies/{taxonomy}
```

Returns bounded evidence including:

```json
{
  "schema_version": 1,
  "resource": {
    "id": 123,
    "post_type": "post",
    "status": "publish",
    "title": "Example"
  },
  "taxonomy": {
    "name": "category",
    "label": "Categories",
    "hierarchical": true
  },
  "assignment": {
    "term_ids": [7, 12],
    "terms": [
      {"id": 7, "name": "SEO", "slug": "seo", "parent": 0},
      {"id": 12, "name": "WordPress", "slug": "wordpress", "parent": 0}
    ],
    "fingerprint": "..."
  }
}
```

The returned `assignment.fingerprint` is the optimistic-concurrency token for Preview/Apply.

### Preview exact replacement

```text
POST /content/{resource_id}/taxonomies/{taxonomy}/changes/preview
```

Example bounded body:

```json
{
  "term_ids": [12, 18],
  "expected_fingerprint": "..."
}
```

Preview validates:

- resource still exists;
- taxonomy is registered for that resource type;
- current principal can edit the resource;
- current principal has the taxonomy's real `assign_terms` capability;
- expected assignment fingerprint is current;
- every requested term ID already exists in the selected taxonomy;
- requested assignment differs from the current one.

Preview does not mutate WordPress.

### Apply exact replacement

```text
POST /content/{resource_id}/taxonomies/{taxonomy}/changes/apply
```

Example:

```json
{
  "term_ids": [12, 18],
  "expected_fingerprint": "...",
  "idempotency_key": "client-operation-123",
  "allow_published_target": true,
  "environment_fingerprint": "..."
}
```

`allow_published_target` is required when the target resource is already published.

`environment_fingerprint` follows the normal Manager environment policy. Production writes require the fresh current environment fingerprint obtained during inspection.

Apply uses WordPress-native taxonomy APIs and then reads the relationship back. Transport success alone is never treated as proof of persistence.

### Rollback

```text
POST /content/taxonomies/changes/{operation_id}/rollback
```

Production rollback uses the current environment acknowledgement contract.

Rollback is allowed only when:

- operation belongs to the taxonomy-assignment adapter;
- operation is still in applied state;
- current principal still has authority over the resource/taxonomy;
- current environment is compatible with the stored operation binding;
- current assignment still equals the exact state produced by Apply.

If a human or newer automation changed the relationship after Apply, rollback returns a stale conflict instead of overwriting that newer edit.

## Capability discovery

`GET /capabilities` now advertises:

```text
capabilities.taxonomy_assignment
operations.content.taxonomy_assignment.read
operations.content.taxonomy_assignment.preview
operations.content.taxonomy_assignment.apply
operations.content.taxonomy_assignment.rollback
```

The capability contract declares:

```text
edit_post(resource) + taxonomy.assign_terms
existing_terms_only = true
```

The manifest is discovery, not authorization. Every target endpoint repeats its own resource/taxonomy checks.

The pre-existing broader `taxonomy_management` capability must not be confused with this operation. Managing/creating taxonomy terms and assigning existing terms are different authorities and remain separate contracts.

## Fingerprint contract

The assignment fingerprint is deterministic over:

```text
resource_id
+ taxonomy name
+ sorted current term IDs
```

This gives the remote controller a narrow concurrency token for the exact relationship it is about to change.

It deliberately does not use the entire post fingerprint. A taxonomy assignment can therefore be protected independently without pretending that changing terms is the same operation as rewriting article content.

## Idempotency

Apply requires an idempotency key.

A safe retry with the same key and same bounded payload resolves to the original operation rather than applying the assignment twice.

A conflicting reuse remains rejected by the shared Manager operation-store contract.

## Published-content guard

Preview may inspect a published resource normally.

Apply to a published resource requires:

```text
allow_published_target = true
```

This makes the transition from inspection to accepted public mutation explicit. External orchestration cannot silently convert Preview into a public taxonomy edit.

## Verification and compensation

After Apply, Manager reads the current relationship from WordPress and requires exact equality with the requested sorted term IDs.

If verification fails, Manager attempts to restore the previous assignment and returns a failure instead of reporting false success.

If the operation history cannot be persisted after the relationship changed, Manager likewise attempts compensation.

## Privacy

Operation summaries do not need article bodies or credentials to represent this mutation.

The operation record is relationship-focused:

- resource ID/type;
- taxonomy name;
- before/after term IDs;
- before/after assignment fingerprints;
- idempotency binding;
- environment binding;
- operation/verification state.

No Application Password, REST nonce, cookie or authorization header is stored in the operation evidence.

## Repository acceptance

The accepted 0.3.44 runtime cycle proves, in isolated real WordPress:

1. create/publish a normal Manager post;
2. create existing category/tag fixtures;
3. seed an initial category and tag assignment;
4. discover taxonomy assignment in `/capabilities`;
5. read the current category assignment;
6. Preview an exact category replacement and prove Preview does not mutate state;
7. reject Apply to the published post without explicit public-target approval;
8. Apply with approval, idempotency and environment fingerprint;
9. verify the exact assigned category ID;
10. replay the same Apply idempotently;
11. simulate a newer taxonomy edit;
12. reject stale rollback;
13. restore the applied state and perform an accepted rollback;
14. verify the original category assignment is restored;
15. read/Preview `post_tag` through the same generic contract;
16. prove taxonomy operations did not modify `post_content`;
17. keep WordPress debug output clean;
18. pass WPCS and PHPStan level 6.

Accepted implementation baseline before documentation closure:

- code SHA: `6a5651a5e080ecf0a88a1eecb2bec7b7915d44f8`;
- SEO GEO Manager WordPress CI run: `38120519902` — success;
- PHP Quality CI run: `38120520054` — success.

## C4 status after 0.3.44

0.3.44 closes the **taxonomy assignment** slice, not all of C4.

C4 already has substantial primitives for:

- normal post/page creation;
- draft-first operation;
- publish/schedule;
- generic content-field updates through change sets;
- featured media/reference operations;
- category/tag/existing-taxonomy assignment.

The next bounded C4 gap is **author binding**.

Current content creation still binds `post_author` to the authenticated operating identity. That is safe as a default but insufficient for managed editorial workflows where the API operator and the public WordPress author are intentionally different identities.

The next slice should therefore let external orchestration select an **existing WordPress author identity** while Manager validates permissions and preserves Preview/idempotency/verification/rollback safety. Manager must never invent an author persona or author strategy.
