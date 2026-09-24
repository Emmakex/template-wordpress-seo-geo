# Client installation and migration

Phase 10C defines the operator path for installing the self-contained SEO/GEO theme on a clean WordPress site and for adopting an existing production site without sacrificing search/discovery continuity.

## Distribution invariant

The installable baseline is the deterministic single-theme release ZIP produced by `scripts/build-theme-release.py`.

- Baseline runtime requires **zero active plugins**.
- `packages/seo-geo-core/src` is bundled inside the theme release and is not installed as a required plugin.
- The standalone Core plugin wrapper is transitional/development compatibility only.
- The current Migration Bridge package is existing-site adoption tooling; it is not a Theme runtime dependency.
- Future SEO/GEO Manager is a separate optional plugin product for publishing/operations and remains **optional** for Theme runtime.
- Use the release ZIP and its sibling `.sha256` file from the same accepted build.

Never replace an existing production theme with an unverified repository checkout or an unverified ZIP.

## Required inputs before installation

For every client, record:

- client/site identifier and production URL;
- accepted release version and ZIP SHA-256;
- WordPress/PHP baseline and hosting constraints;
- current theme, active/must-use plugins and builder dependencies;
- database and uploads backup references when the site is not disposable;
- staging/sandbox URL and access path for existing-site adoption, **or** the selected portable-sandbox mode when the client has no staging;
- DNS/CDN/cache ownership and rollback contacts;
- any intentional SEO changes that are allowed to differ from the current public site.

The repository baseline is WordPress 7.1 and PHP 8.2. A client environment outside the accepted compatibility evidence must be treated as a new compatibility task, not assumed safe.

## Clean WordPress installation

A clean site does not require the Migration Bridge.

1. Obtain the accepted `seo-geo-theme.zip` and checksum from the same release candidate.
2. Verify the ZIP SHA-256 before installation.
3. Confirm the WordPress/PHP baseline and capture a backup if the WordPress instance already contains non-disposable content.
4. Install the ZIP from WordPress **Appearance → Themes** or the equivalent controlled deployment mechanism.
5. Activate `seo-geo-theme`.
6. Confirm that the active-plugin list can remain empty.
7. Open **Appearance → SEO/GEO Setup**.
8. Select one of the five supported presets, configure languages, site identity and GEO/discovery choices, then run **Preview**.
9. Resolve every blocking validation issue before choosing **Apply setup**.
10. Apply once, reload the screen and confirm that an unchanged second Apply is idempotent.
11. Run the post-install verification in this document before considering the installation accepted.

No SEO/GEO plugin is required for baseline setup or runtime.

## Existing production site

Do not switch an existing production site directly to the destination theme.

Production remains on the accepted legacy stack until the sandbox candidate has passed dependency analysis, migration, SEO parity, quality gates and cutover prerequisites.

The existing-site path is:

1. Create current database and uploads backups with externally usable recovery references.
2. Capture the existing public SEO/GEO baseline with the Migration Bridge.
3. Build the dependency graph for themes, plugins, builders and content coupling.
4. Create a non-production sandbox on a distinct origin. If the client has no staging, use the portable-sandbox strategy in `docs/PORTABLE_SANDBOX.md` rather than testing first in production.
5. Set `SEO_GEO_MIGRATION_SANDBOX=true` and disable WordPress search-engine visibility in that sandbox.
6. Install/activate the destination self-contained theme in sandbox only.
7. Run supported migration transformations in sandbox.
8. Run strict parity against the captured public baseline.
9. Run Accessibility/Responsive and Performance acceptance on representative migrated pages.
10. Resolve blockers or explicitly approve intentional differences.
11. Only after accepted evidence exists, execute the controlled production cutover flow documented in `docs/MIGRATION_BRIDGE.md`.
12. Consume the final handoff from the theme onboarding flow without loading Migration Bridge code at runtime.

For the current Theme 0.1.0 path, the Migration Bridge may then be removed or retained only according to its accepted final disposition. In the future two-product path, SEO/GEO Manager may remain installed because publishing is permanent while its migration mode is disabled.

## Sandbox gate

A sandbox is a safety boundary, not a visual preview convenience. The client does **not** need to own a staging feature in advance: `docs/PORTABLE_SANDBOX.md` defines client staging, agency-managed portable sandbox and limited-access modes.

Before migration work starts, require all of the following:

- a non-production origin;
- `SEO_GEO_MIGRATION_SANDBOX=true`;
- WordPress search-engine visibility disabled;
- destination theme installed only in sandbox;
- no production cutover action available from sandbox state;
- representative content and URLs sufficient for parity testing.

If any sandbox guard is missing, stop the migration rather than compensating with manual production edits.

## Cutover prerequisites

Production cutover is blocked until there is current evidence for:

- database backup;
- uploads backup;
- accepted dependency plan;
- accepted migrated resources;
- fresh SEO parity;
- fresh Accessibility/Responsive acceptance;
- fresh Performance/Lighthouse acceptance;
- destination release version and checksum;
- explicit list of theme/plugin mutations;
- rollback/recovery references.

A visually correct page is not sufficient evidence.

## Post-install SEO/GEO verification

After clean activation or accepted cutover, verify the public production origin rather than relying only on wp-admin state.

Check at minimum:

- expected HTTP status for representative URLs;
- canonical URLs;
- robots/indexability directives;
- XML sitemap availability and representative entries;
- hreflang and x-default relationships when multilingual routing is enabled;
- Open Graph and social metadata;
- Schema graph identity and page-specific nodes;
- LocalBusiness facts against visible page content when applicable;
- redirect targets and absence of redirect loops;
- `robots.txt` crawler policy;
- `llms.txt` when enabled;
- Markdown alternates when enabled;
- page rendering with zero required plugins;
- keyboard/accessibility behavior on representative pages;
- performance budgets on representative pages.

For an existing site, compare these signals to the accepted migration baseline and document every intentional difference.

## Cache and indexing hygiene

After accepted deployment:

- flush only the caches required by the deployment path;
- do not change canonical/indexability merely to force recrawling;
- keep staging/sandbox non-indexable;
- confirm production uses the intended public origin;
- submit or refresh the canonical production sitemap in the client's search tooling only after verification succeeds.

Search-engine recrawl timing is external and must not be described as guaranteed.

## Client handoff record

Retain a bounded handoff containing:

- release version and ZIP SHA-256;
- installation/cutover date;
- backup/recovery references;
- selected preset and language routing;
- setup/report fingerprints;
- migration report/cutover identifiers when applicable;
- acceptance run references;
- known intentional differences;
- owner for the next update.

Do not copy credentials, private content or raw database/uploads artifacts into repository documentation.

## Boundary with Phase 10D

This document defines installation and migration prerequisites. The accepted production procedures are:

- `docs/SANDBOX_TO_PRODUCTION.md` for sandbox exit and production entry;
- `docs/PRODUCTION_VERIFICATION.md` for public production acceptance and abort criteria;
- `docs/ROLLBACK_RECOVERY.md` for runtime rollback, data recovery and post-recovery verification.

Installation is not complete merely because the theme activates; the applicable Phase 10D production checks must also pass.

## 10C acceptance evidence

Final candidate `74a3e957b2a6e8fae7259aa201853c26254be4a0` passed Foundation CI `35944919439`.

PR #107 was squash-merged as `d7d2a7e7273289c820abdddc09ddcd0ee1e0eda1`, and post-merge `main` repeated Foundation successfully as `35944954245`.

The accepted Foundation contract requires this installation guide, `docs/CLIENT_CLONING.md` and `scripts/ci/validate-client-delivery-docs.py`; sandbox/parity/backups, zero-required-plugin delivery, customization boundaries and safe upstream-update guidance are now repository invariants.
