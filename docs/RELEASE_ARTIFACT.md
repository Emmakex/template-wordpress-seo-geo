# Release artifact contract

Phase 10A defines the first distributable artifact for the self-contained SEO/GEO theme. It does not publish a GitHub Release and does not introduce versioning policy; those belong to later Phase 10 microphases.

## Artifact shape

The release builder is:

```bash
python3 scripts/build-theme-release.py --output dist/seo-geo-theme.zip
```

It always produces:

- `seo-geo-theme.zip`;
- `seo-geo-theme.zip.sha256`.

The ZIP has exactly one top-level root: `seo-geo-theme/`.

Repository-only paths such as `.git/`, `.github/`, `tests/`, `scripts/`, `packages/` and `node_modules/` are forbidden from the release root.

## Reproducibility

The release ZIP is deterministic for identical source content:

- entries are sorted;
- entry timestamps are fixed to the ZIP epoch;
- file modes are normalized to `0644`;
- directory entries are omitted;
- compression parameters are fixed;
- no build timestamp, absolute path or host-specific metadata is stored.

`scripts/ci/release-artifact-acceptance.sh` performs two independent builds and requires the resulting ZIP bytes and SHA-256 hashes to match.

## Embedded runtime integrity

Before the ZIP is written, the builder creates `release-integrity.json` inside the theme root.

The manifest contains:

- schema version and manifest mode;
- embedded runtime root;
- runtime file count;
- SHA-256 for every file under `inc/seo-geo-core/`;
- canonical SHA-256 of the complete runtime-file hash map.

Acceptance independently recalculates every runtime hash from the ZIP, validates the tree fingerprint and verifies that the embedded `inc/seo-geo-core/src/Runtime.php` matches `packages/seo-geo-core/src/Runtime.php`.

The manifest contains no secrets, credentials, environment paths or build timestamps.

## CI boundary

`Release Artifact CI`:

1. validates shell/Python syntax;
2. proves two same-source builds are byte-identical;
3. validates ZIP root/safety/integrity constraints;
4. builds one release candidate;
5. uploads only the release ZIP and checksum as a short-lived workflow artifact.

Publishing tags, GitHub Releases, semantic versioning and upgrade acceptance are intentionally deferred to Phase 10B.


## Phase 10A acceptance evidence

Final candidate `6fc2d5a490529f3c194800f28da0a927b0190e01` passed Foundation CI `35940973500` and Release Artifact CI `35940973445`.

Both independent candidate builds and the preserved release candidate produced:

`b560e8be3a1e7e1666842be28b19743e184a5119c58dfdb1f9f21bb3890b3838`

PR #103 was squash-merged as `342d0806e0778a0d83a91e526456396df2042d35`.

Post-merge `main` repeated Foundation CI `35941055453` and Release Artifact CI `35941055386`, reproducing the exact same ZIP SHA-256. This proves that commit metadata and workflow context do not alter the release artifact when theme/Core/preset source content is unchanged.
