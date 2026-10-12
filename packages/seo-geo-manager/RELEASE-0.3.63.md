# SEO/GEO Manager 0.3.63 — Featured Media Workspace

Validation slice for the Manager-first EMMAKE build path.

## Adds

- Featured Media Workspace tied to the selected Content Workspace page/post;
- current featured-image identity, URL, ALT, media fingerprint and relationship fingerprint;
- bounded picker over existing WordPress image attachments;
- client-side title/ALT/caption/ID filtering over the bounded media inventory;
- no arbitrary external URL writer;
- explicit `replace` and relationship-only `remove` actions;
- remove never deletes the attachment or mutates image bytes;
- Preview before Apply;
- expected relationship fingerprint;
- explicit published-resource acknowledgement;
- environment-bound Apply;
- idempotency key;
- explicit post-Apply Verify endpoint;
- operation ID and stale-safe rollback;
- exact rollback readback against the stored original featured-media ID and fingerprint;
- applied operations remain authoritative in the UI until rollback or page reload;
- WordPress runtime acceptance covers replace/verify/rollback and remove/verify/rollback;
- dedicated Featured Media Workspace CI contract guards zero content/binary mutation and zero client-domain hardcoding.

Do not promote unless all relevant checks are green and the deterministic 0.3.63 package identity is reproduced by Release CI.
