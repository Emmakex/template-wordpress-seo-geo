# SEO/GEO Manager 0.3.39 — C6.3 image alt control

## Purpose

C6.3 adds a deliberately narrow media mutation primitive to SEO/GEO Manager: controlled updates of the WordPress image alt field for an existing attachment.

This does **not** move SEO/GEO intelligence into WordPress. External orchestration decides whether an alt change is useful and supplies the exact text. Manager only inspects, previews, applies, verifies, records and rolls back the authorized WordPress mutation.

## Product boundary

The operating model remains:

- Migration Bridge rescues/migrates the asset.
- SEO/GEO Theme renders the site.
- SEO/GEO Manager provides safe eyes and hands inside WordPress.
- External orchestration performs research, strategy, creation, optimization and growth decisions.

Manager 0.3.39 therefore does not generate alt text, analyze an image semantically, invent metadata or choose which images should be optimized.

## C6.3 contract

The mutation target is an existing WordPress image attachment identified by numeric attachment ID. The only writable field in this slice is `_wp_attachment_image_alt`.

The flow is:

`Inspect media → Preview alt change → explicit approval → Apply → Verify → History → Rollback`

Apply requires:

- an authenticated operator with `upload_files` and permission to edit the target attachment;
- the attachment to exist and have an `image/*` MIME type;
- the expected media fingerprint from a fresh inspection;
- an explicit `allow_public_media=true` acknowledgement;
- the current environment fingerprint;
- an idempotency key.

## Fingerprint authority

C6.3 introduces a deterministic media fingerprint over bounded attachment identity and state:

- attachment ID;
- modified timestamp;
- title;
- MIME type;
- parent ID;
- attachment URL;
- whether alt metadata exists;
- current alt value;
- image width and height.

Including `alt_exists` is intentional. WordPress distinguishes an absent alt meta key from an existing empty key, so rollback can restore the exact pre-operation state rather than only the visible string.

## Safety invariants

C6.3 must preserve all of the following:

1. **Alt-only mutation.** No attachment title, caption, description, file, URL, EXIF or attachment metadata mutation.
2. **Attachment-ID-only targeting.** Remote callers cannot nominate arbitrary files or URLs for mutation.
3. **No fabricated metadata.** Manager never creates inferred image facts or generates alt text.
4. **No binary mutation.** Uploading, replacing, deleting or transforming image files is out of scope.
5. **Preview before mutation.** Preview is read-only and shows before/after alt state.
6. **Explicit public-media approval.** Apply is blocked without an explicit acknowledgement.
7. **Optimistic concurrency.** A stale expected fingerprint blocks the operation.
8. **Idempotency.** Repeating the same request/key replays the operation; reusing a key with different material is rejected.
9. **Environment binding.** Apply and rollback are bound to the inspected WordPress environment.
10. **Verified Apply.** Manager re-reads the alt and recalculates the fingerprint after mutation.
11. **Exact rollback.** Rollback restores both the previous alt value and whether the meta key existed.
12. **Stale-safe rollback.** A newer human or system edit blocks rollback rather than being overwritten.
13. **Least privilege.** Capability discovery may advertise media alt mutation only when the current identity can upload files; the adapter still enforces object-level edit permission.

## REST surface

- `POST /wp-json/seo-geo-manager/v1/media/{media_id}/alt/changes/preview`
- `POST /wp-json/seo-geo-manager/v1/media/{media_id}/alt/changes/apply`
- `POST /wp-json/seo-geo-manager/v1/media/alt/changes/{operation_id}/rollback`

Capability discovery advertises these as `media.alt.preview`, `media.alt.apply` and `media.alt.rollback` plus `media_alt_write`.

## Explicitly out of scope

C6.3 does not add:

- image generation;
- image understanding;
- automatic alt generation;
- media upload;
- binary replacement;
- media deletion;
- image resizing/compression;
- EXIF writes;
- arbitrary attachment-meta writes.

Those capabilities require separate, independently reviewable contracts if they are ever introduced.

## Acceptance gate

The WordPress runtime acceptance must prove:

- Preview is non-mutating;
- stale fingerprint rejection;
- explicit-approval rejection;
- successful Apply and readback;
- attachment metadata/title preservation;
- idempotent replay and idempotency-conflict rejection;
- exact rollback from a newly-created alt meta key back to a missing key;
- stale rollback protection after a simulated human edit;
- non-image rejection;
- capability discovery;
- subscriber denial.

Release CI must also require the media-alt controller, adapter and fingerprint implementation inside the deterministic installable ZIP.
