# Native Replatform Review Evidence

Status: **Phase 10E.4 acceptance support**

## Purpose

Native Replatform review evidence is a privacy-bounded JSON snapshot used to prove that an applied native draft is still tied to the exact preserved source and the exact reviewed Content Remap decision.

It exists for real-site acceptance such as the first Corporate Native Home review on the Emmake `/nuevaweb/` sandbox.

The evidence is **not** a backup, page export, content archive or cutover authorization.

## Evidence contents

A ready snapshot may contain only bounded operational facts:

- native draft ID, status and SHA-256;
- preserved source ID, public path and SHA-256;
- source-unchanged boolean;
- preset and page key;
- native composition plan SHA-256;
- Content Remap plan SHA-256;
- reviewed selection SHA-256;
- explicitly verified semantic sections;
- selected asset counts by units/links/media;
- unmapped asset counts by units/links/media;
- rollback availability and pre-apply SHA-256;
- whether a prior rollback record exists;
- sandbox-only and sandbox-active safety state;
- deterministic evidence SHA-256;
- blocker codes when the snapshot is not ready.

## Explicitly excluded

The snapshot must never contain:

- source page body;
- native draft body;
- rollback backup body;
- extracted source text;
- link URLs from candidate inventories;
- media URLs or attachment payloads;
- credentials, cookies or tokens;
- database dumps;
- uploaded files;
- private package paths;
- customer or form submissions.

The public source path may be retained because it is required to identify the accepted surface.

## Ready gate

The evidence status is `ready` only when:

1. the request is running inside the accepted Migration Bridge sandbox;
2. the destination still exists as a private page draft;
3. the draft still has complete Native Replatform metadata;
4. an active Reviewed Apply ledger exists;
5. the current Native Replatform plan remains ready;
6. source identity is unchanged;
7. source content SHA-256 matches the draft-bound source fingerprint;
8. native composition SHA-256 is unchanged;
9. Content Remap plan SHA-256 is unchanged;
10. current draft SHA-256 matches the Reviewed Apply ledger;
11. rollback backup SHA-256 matches the ledger's pre-apply hash and the retained backup body.

Any failure returns `blocked` and explicit blocker codes. A production context therefore cannot manufacture a valid review-evidence snapshot merely by carrying equivalent metadata.

## Operator flow

For a reviewed native draft:

1. apply reviewed source candidates;
2. preview/edit the private native draft;
3. if the content selection is wrong, restore the pre-apply draft and review again;
4. when the reviewed draft is acceptable and unchanged, download the bounded JSON evidence;
5. retain that JSON with the real-site acceptance record;
6. run SEO/GEO, links/media, accessibility, responsive and performance checks;
7. do not infer production approval from the review-evidence snapshot alone.

## Determinism

The `evidence_sha256` is calculated from the bounded snapshot itself before the hash field is added.

Repeated snapshots over identical accepted state must produce the same evidence SHA-256.

## Emmake Home acceptance

For the first Corporate Native Home pilot, the repository may retain only the bounded review-evidence JSON or an equivalent sanitized record. The real page bodies, media payloads, database/backups and private migration artifacts must remain outside Git.

Passing this evidence gate proves the Reviewed Apply state is internally consistent inside the accepted sandbox. It does not authorize production cutover.
