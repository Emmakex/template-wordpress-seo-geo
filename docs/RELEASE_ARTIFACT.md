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
