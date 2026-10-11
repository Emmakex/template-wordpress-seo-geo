# SEO/GEO Manager 0.3.45 — Author Binding

Status: **C4.2 repository acceptance complete**

Canonical architecture: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Purpose

Manager 0.3.45 adds a bounded WordPress author-relationship primitive so the remote operating identity and the public/editorial WordPress author may be different existing users.

The responsibility split remains explicit:

```text
External orchestration
    selects which accepted existing WordPress author should own the content and why

SEO/GEO Manager
    inspects the current relationship
    validates the operator, resource, post type and destination user
    previews the exact author change
    applies only the accepted relationship
    verifies persistence
    records the operation
    rolls back only while still safe

WordPress
    remains authority for users, roles/capabilities, resources and post_author
```

Manager does **not** create people, personas, biographies or editorial strategy in this slice.

## Product boundary

This capability belongs to Manager because it answers:

> How can an authorized external operator safely bind an existing WordPress user as the author of a supported post/page?

It does not answer:

> Who should write this article, which expert should represent the company, what author persona should exist, or what should that author say?

Those decisions remain external.

## Scope

0.3.45 supports:

- normal WordPress posts and pages;
- existing WordPress users only;
- bounded read of the current author relationship;
- public author evidence limited to ID/existence/display name;
- deterministic relationship fingerprint;
- Preview without mutation;
- post-type-aware author eligibility;
- explicit `edit_others_*` authority when assigning a different user;
- explicit approval before changing a published resource;
- production environment binding;
- idempotent Apply;
- exact readback verification;
- operation history/evidence;
- stale-safe rollback;
- permission and destination-author revalidation before rollback.

## Non-goals

0.3.45 intentionally does not:

- create WordPress users;
- invent author personas;
- generate biographies;
- expose email/login/password fields through the author-binding API;
- decide author/editorial strategy;
- bypass WordPress roles/capabilities;
- rewrite title/body/status/taxonomy relationships as a side effect;
- expose a generic user-management API.

User creation/profile-management, if later required, must be a separate explicitly scoped capability and not a hidden side effect of content publication.

## Remote routes

Base namespace:

```text
/wp-json/seo-geo-manager/v1
```

### Inspect current author

```text
GET /content/{resource_id}/author
```

Returns bounded evidence such as:

```json
{
  "schema_version": 1,
  "resource": {
    "id": 123,
    "post_type": "post",
    "status": "publish",
    "title": "Example"
  },
  "author": {
    "id": 17,
    "exists": true,
    "display_name": "Editorial Author"
  },
  "binding": {
    "author_id": 17,
    "fingerprint": "..."
  }
}
```

The endpoint deliberately omits email, login, password/application-password material and private profile fields.

If a legacy resource references an author ID whose user no longer exists, inspection remains observable (`exists=false`) rather than hiding the current WordPress state.

### Preview

```text
POST /content/{resource_id}/author/changes/preview
```

Example:

```json
{
  "author_id": 23,
  "expected_fingerprint": "..."
}
```

Preview validates:

- resource still exists and is a normal post/page;
- current principal may edit the resource;
- current relationship fingerprint is fresh;
- destination user already exists;
- destination user is eligible for that content type;
- if destination user differs from the operating identity, the operator has the post type's real `edit_others_posts` capability (for pages this resolves to the corresponding mapped page capability);
- requested author differs from the current author.

Preview does not mutate WordPress.

### Apply

```text
POST /content/{resource_id}/author/changes/apply
```

Example:

```json
{
  "author_id": 23,
  "expected_fingerprint": "...",
  "idempotency_key": "client-operation-456",
  "allow_published_target": true,
  "environment_fingerprint": "..."
}
```

Apply uses WordPress-native post APIs and then reloads the resource to require exact persistence of the requested `post_author`.

`allow_published_target=true` is required when the target is already public.

Production mutation follows the normal Manager `EnvironmentPolicy` contract.

### Rollback

```text
POST /content/authors/changes/{operation_id}/rollback
```

Rollback is allowed only when:

- operation belongs to the author-binding adapter;
- operation is still in applied state;
- current principal may still edit the resource;
- current environment matches the stored operation binding;
- current author is still exactly the author produced by Apply;
- previous destination author still exists/is eligible under the current WordPress capability model.

If a human or newer automation changed the author after Apply, Manager returns a stale conflict rather than overwriting that newer relationship.

## Capability model

The author relationship is not generic user administration.

The endpoint repeats real WordPress checks:

```text
edit_post(resource)
+ destination user exists
+ destination user can edit the target content type
+ assigning another user requires post_type.edit_others_posts
```

Examples:

- Administrator/Editor may bind an eligible existing Author to a post when WordPress capabilities permit it;
- a normal Author may inspect its own post;
- a normal Author cannot reassign that post to another user without `edit_others_posts`;
- a Subscriber is an existing user but is rejected as an eligible normal post author by this contract;
- page author eligibility uses page capabilities rather than assuming post capabilities.

## Capability discovery

`GET /capabilities` advertises:

```text
capabilities.author_binding
operations.content.author_binding.read
operations.content.author_binding.preview
operations.content.author_binding.apply
operations.content.author_binding.rollback
```

The manifest declares:

```text
existing_users_only = true
public_profile_fields_only = true
```

Discovery does not grant authority. Resource/post-type checks are repeated by the actual operation.

## Fingerprint contract

The author-binding fingerprint is deterministic over:

```text
resource_id
+ current author_id
```

This creates a narrow optimistic-concurrency token for the relationship being changed.

It deliberately does not pretend that changing the author is the same operation as rewriting article content. Content fingerprints and relationship fingerprints remain separate safety facts.

## Idempotency

Apply requires an idempotency key.

A retry with the same key and same bounded payload resolves to the original operation rather than reapplying the relationship.

Conflicting key reuse remains governed by the shared operation-store contract.

## Published-content guard

Inspection and Preview may run against published content.

Apply requires explicit:

```text
allow_published_target = true
```

for a public target.

This preserves a clear transition between read-only planning and accepted public mutation.

## Verification and compensation

After Apply, Manager reloads the WordPress resource and requires exact equality of `post_author`.

If verification fails, Manager attempts to restore the previous author and returns failure instead of claiming success.

If operation history cannot be stored after mutation, Manager likewise attempts compensation.

## Relationship isolation

Author Binding changes only the author relationship.

Repository acceptance proves it does not alter:

- `post_content`;
- `post_title`;
- `post_status`;
- category relationships;
- tag relationships.

This keeps content-field, taxonomy and author operations independently reviewable and independently concurrency-protected.

## Privacy

Author Binding exposes only what the remote content operation needs:

- resource identity/status/title;
- author ID;
- author existence flag;
- public display name;
- relationship fingerprint.

It does not return:

- email;
- user login;
- password/application-password material;
- cookies/nonces;
- private profile metadata.

Operation records remain relationship-focused: resource ID/type, before/after author IDs and fingerprints, idempotency/environment binding, status and timestamps.

## Repository acceptance

The accepted 0.3.45 runtime cycle runs in the same isolated WordPress used by the C4 content lifecycle/taxonomy gate and proves:

1. a published Manager-created post starts owned by the operating administrator;
2. `/capabilities` exposes Author Binding as existing-user/public-profile bounded;
3. GET returns current author + fingerprint without email/login leakage;
4. Preview to another existing Author succeeds without mutation;
5. published Apply without explicit approval is rejected;
6. approved Apply uses current environment fingerprint and idempotency;
7. exact readback shows the selected public author;
8. safe retry resolves to the same operation;
9. title/body/status/category/tag state remain unchanged;
10. a newer author edit blocks rollback as stale;
11. returning to the exact applied state allows rollback to the original author;
12. Subscriber is rejected as an ineligible author target;
13. Editor is accepted in page-author Preview through post-type-aware capabilities;
14. a least-privilege Author can inspect its own post but cannot assign another user without `edit_others_posts`;
15. final content integrity is preserved;
16. WordPress debug output remains clean;
17. WPCS and PHPStan level 6 remain green.

Accepted implementation baseline before CI-workflow hardening:

- code/acceptance SHA: `4a6fc21453acc502a75d255005052059be7eb34c`;
- SEO GEO Manager WordPress CI run: `38121238623` — success;
- PHP Quality CI run: `38121238620` — success.

CI fixture path/explicit syntax coverage was then hardened in commit `f9d21f1303934f442729ded0256844e924d17d67`.

## C4 status after 0.3.45

0.3.45 closes the **existing-user author binding** slice, not all of C4.

The generic content path now has accepted foundations for:

- normal post/page creation;
- draft-first operation;
- publication/scheduling;
- bounded existing-field changes;
- featured media/reference operations;
- existing-term taxonomy assignment;
- existing-user author binding.

The next step is not to add another feature automatically. Reassess the remaining end-to-end C4 gap first.

The principal known gap is **multilingual relationship handling through accepted adapters**. That must remain relationship/integration plumbing inside Manager; language strategy, translation strategy and translated content generation remain external orchestration responsibilities.
