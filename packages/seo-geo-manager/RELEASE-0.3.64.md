# SEO/GEO Manager 0.3.64 — Media ALT Workspace

Validation slice for the Manager-first EMMAKE build path.

## Adds

- Media ALT Workspace over the bounded existing WordPress image inventory;
- search/filter by attachment ID, title, current ALT and caption;
- ALT-only editing for existing image attachments;
- no arbitrary URL writer, sideloading, binary replacement, EXIF mutation or content mutation;
- canonical media fingerprint before mutation;
- Preview before Apply;
- explicit public-media acknowledgement after Preview;
- environment-bound Apply;
- stable idempotency key retained across uncertain Apply retries;
- local post-Apply Verify endpoint using the canonical media fingerprint;
- operation ownership persisted before Verify so an applied mutation never loses its rollback path;
- stale-safe rollback with exact ALT/fingerprint readback;
- applied operations remain authoritative in the UI until rollback or page reload;
- WordPress runtime acceptance now covers local Apply -> Verify -> readback -> rollback for ALT;
- dedicated Media ALT Workspace CI contract guards ALT-only scope, zero binary/content mutation and zero client-domain hardcoding.

Do not promote unless all relevant checks are green and the deterministic 0.3.64 package identity is reproduced by Release CI.
