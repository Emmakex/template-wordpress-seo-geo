# SEO/GEO Manager 0.3.40 — C6.4 guarded media editorial context

Status: development slice.

## Purpose

Extend the Manager media bridge from read-only references and guarded image alt changes to bounded editorial context on an existing image attachment.

The Manager remains an execution bridge. External orchestration decides what the title, caption or description should say and why.

## Supported fields

- attachment title (`post_title`);
- caption (`post_excerpt`);
- description (`post_content`).

No filename, binary, MIME, EXIF, generated sizes or raw attachment metadata mutation is part of this slice.

## API

- `POST /wp-json/seo-geo-manager/v1/media/{media_id}/context/changes/preview`
- `POST /wp-json/seo-geo-manager/v1/media/{media_id}/context/changes/apply`
- `POST /wp-json/seo-geo-manager/v1/media/context/changes/{operation_id}/rollback`

Payloads use `media_id` from the route, `expected_fingerprint`, a bounded `fields` object, and the normal environment/idempotency guards for Apply.

## Safety contract

- attachment ID is authoritative;
- image attachments only;
- least-privilege `upload_files` plus attachment edit permission;
- expected fingerprint before Preview/Apply;
- explicit `allow_public_media=true` before mutation;
- idempotency key on Apply;
- exact stored-value verification;
- operation history with private before/after rollback snapshots;
- public Apply/Rollback responses strip those snapshots;
- stale-safe rollback refuses to overwrite a newer human edit;
- no fabricated metadata;
- no binary mutation.

## Bounded reads

Media discovery now returns bounded caption/description values and explicit truncation flags. The fingerprint includes title, slug, status, caption, description, alt state, media URL, parent and dimensions so remote writes can detect concurrent edits.

## Boundary

Binary upload/replacement is intentionally deferred to a later media slice. C6.4 only controls existing WordPress attachment editorial context.
