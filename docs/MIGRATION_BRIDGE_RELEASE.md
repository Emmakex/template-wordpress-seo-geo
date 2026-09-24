# Migration Bridge release package

## Purpose

SEO/GEO Migration Bridge must be installable on a real WordPress site as a normal plugin ZIP before the Theme 0.1.0 real-site pilot begins.

The release package is built from:

- source: `packages/seo-geo-migration-bridge/`;
- main plugin: `seo-geo-migration-bridge.php`;
- current plugin version: `0.8.6`;
- ZIP root: `seo-geo-migration-bridge/`.

## Build

Run:

```bash
python3 scripts/build-migration-bridge-release.py \
  --output dist-migration-bridge/seo-geo-migration-bridge.zip
```

The builder also creates:

```text
seo-geo-migration-bridge.zip.sha256
```

The ZIP is deterministic: two builds from identical source must be byte-identical.

## Acceptance

`scripts/ci/migration-bridge-release-acceptance.py` proves:

- plugin header version matches the runtime version constant;
- exactly one WordPress plugin root exists;
- the main plugin file is present at the expected root;
- all source files are present and no repository-only files are added;
- paths are safe;
- timestamps and file modes are normalized;
- two independent builds are byte-identical.

The workflow additionally runs PHP syntax validation for every plugin PHP file.

## GitHub Actions artifact

`.github/workflows/migration-bridge-release.yml` publishes a short-lived CI artifact containing:

- `seo-geo-migration-bridge.zip`;
- `seo-geo-migration-bridge.zip.sha256`.

This artifact is an installation candidate for controlled client analysis. It is not a declaration that a real client migration is accepted.

## emmake.com use

For the first Phase 10E pilot:

1. install the accepted Migration Bridge ZIP from **Plugins → Add New Plugin → Upload Plugin**;
2. activate it;
3. use the read-only analysis path and explicitly capture the public SEO/GEO baseline before any migration mutation;
4. do not activate the destination Theme directly in production;
5. create/use an isolated sandbox before transformation;
6. retain current production backups/recovery references.

The operator screen only shows the baseline capture action when no baseline exists. The action is capability/nonce protected and runs as a **resumable incremental capture**. Robots/sitemap discovery remains conservative, while page analysis uses an operator-selectable batch size from **1 to 20 pages per request**. The default/recommended value is **10**. The selected value is persisted with capture progress and can be changed safely while the capture is running; existing 0.8.3 progress without a stored batch size resumes at the 0.8.4 default of 10. Progress is persisted in a dedicated non-autoloaded option after every step, so a timeout/closed connection can resume instead of restarting. The final baseline is stored only after all accepted steps complete and an existing final baseline is never replaced automatically.

After the baseline is complete, 0.8.5 exposes the bounded dependency rows behind the summary so UNKNOWN/MIGRATE/REPLACE items can be reviewed before sandbox work. It also provides an authenticated **sandbox handoff JSON** download containing runtime identity, baseline ID/hash/counts, bounded dependency decisions and sandbox safety requirements. The handoff never includes post bodies, builder payloads, credentials, arbitrary option values, database dumps, uploads or customer data.

Version 0.8.6 adds an explicit operator-review workflow for `UNKNOWN` components. Each review is capability/nonce-gated and stores only the component ID, one fixed planning classification (`KEEP`, `REPLACE`, `MIGRATE`, `OPTIONAL` or `REMOVE-CANDIDATE`), a fixed reason code and timestamp in a non-autoloaded option. Review evidence is exported separately in the handoff manifest and **does not replace the raw dependency-graph classification or authorize production plugin deactivation/theme switching**. The operator can revise or clear a decision while the component remains a live `UNKNOWN` item.

The plugin is transitional for the Theme 0.1.0 migration path. Its accepted capabilities later become the migration module inside SEO/GEO Manager.
