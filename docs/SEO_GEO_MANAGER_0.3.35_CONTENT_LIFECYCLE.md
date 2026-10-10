# SEO/GEO Manager 0.3.35 — Generic content lifecycle

Status: **implementation candidate**

Date: 2026-10-10

Canonical architecture: `docs/THREE_PRODUCT_OPERATING_MODEL.md`.

## Purpose

0.3.35 adds the first generic WordPress content lifecycle primitives required for external orchestration to create and publish normal posts/pages without embedding strategy or copy-generation intelligence inside WordPress.

The external controller decides **what** should exist and **what the content should say**. Manager only validates, previews, writes and verifies bounded WordPress state.

## Existing edit primitive

Existing resources continue to use the generic change-set contract:

```text
POST /wp-json/seo-geo-manager/v1/changes/preview
POST /wp-json/seo-geo-manager/v1/changes/apply
```

That path owns safe title/slug/excerpt/content updates with optimistic concurrency and idempotency.

0.3.35 does not duplicate that logic.

## Create resource

Preview:

```text
POST /wp-json/seo-geo-manager/v1/content/resources/preview
```

Apply:

```text
POST /wp-json/seo-geo-manager/v1/content/resources/apply
```

Supported resource types:

- `post`;
- `page`.

Supported initial states:

- `draft`;
- `pending`;
- `publish`;
- `future`.

A creation payload may contain:

- schema version;
- type;
- title;
- optional slug;
- optional excerpt;
- optional content;
- requested status;
- `scheduled_at_gmt` for `future`;
- idempotency key on apply;
- production environment fingerprint when required by the existing environment policy.

### Creation safety

- only normal WordPress posts/pages are accepted in this slice;
- payload sizes are bounded;
- title is required;
- explicit slug collisions are rejected instead of allowing silent WordPress suffixing;
- post/page creation authority is resolved from WordPress capabilities;
- direct publish/schedule requires the post type publish capability;
- apply is idempotent;
- post-write state is reloaded and verified;
- operation evidence stores target ID/type, permalink and post-write fingerprint.

## Publish or schedule an existing resource

Preview:

```text
POST /wp-json/seo-geo-manager/v1/content/{id}/publication/preview
```

Apply:

```text
POST /wp-json/seo-geo-manager/v1/content/{id}/publication/apply
```

Supported transitions in 0.3.35:

- publish now;
- schedule for a future GMT instant.

The request must include the exact fingerprint obtained during inspection. If a human or another process modifies the resource between inspection and publication, Manager blocks the transition with a stale-state conflict.

Publication/scheduling also requires the correct WordPress post-type publish capability.

## Scheduling contract

Scheduling uses an explicit ISO-8601 `scheduled_at_gmt` value.

Manager normalizes it to UTC and requires it to be more than 60 seconds in the future. The resulting WordPress `post_date_gmt` is verified after the write.

The external controller is responsible for deciding the editorial calendar/time. Manager is responsible only for applying the requested valid future time.

## Capability discovery additions

The authenticated capability manifest now includes:

- `post_create`;
- `page_create`;
- `content.create.preview`;
- `content.create.apply`;
- `content.publication.preview`;
- `content.publication.apply`.

Availability is derived from the current authenticated WordPress principal.

The Manager does not assume every client operator is an administrator.

## Generic external workflow

```text
inspect capabilities
  -> inspect current content
  -> external reasoning / content generation
  -> create preview
  -> apply draft creation
  -> optional existing change-set edits
  -> publication preview
  -> publish now OR schedule
  -> re-read/verify target
  -> retain operation evidence
```

This is the first concrete path that lets an external operator say:

- create this article;
- create this page;
- leave it as draft;
- publish it now;
- schedule it for a specific time;

without requiring a client-specific WordPress implementation.

## Non-goals for 0.3.35

This slice does not yet add:

- taxonomy/category/tag mutation;
- media upload/selection;
- featured-image assignment;
- Theme semantic-model creation beyond the existing Theme adapter;
- SEO-provider metadata writes;
- automatic internal-link strategy;
- autonomous content generation;
- autonomous keyword research;
- generic shell/command execution.

Those remain later capability slices or external-orchestration responsibilities.

## Acceptance

The WordPress CI fixture must prove:

1. preview + create a normal draft post;
2. deterministic idempotent replay;
3. exact slug-collision rejection;
4. publish-now transition from inspected draft;
5. scheduled future post creation with exact GMT date;
6. normal page creation;
7. stale fingerprint blocks publication after a newer edit;
8. per-type capability enforcement (for example an Author may create posts but not pages);
9. no WordPress debug warnings/fatals.

C4 is only considered closed after these runtime checks and the normal repository quality/release gates are green on the same candidate SHA.
