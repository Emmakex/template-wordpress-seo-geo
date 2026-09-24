# Client cloning, customization and safe updates

Phase 10C defines how a client-specific implementation may diverge from the reusable repository without making future upstream updates unsafe.

## Repository clone contract

Create one controlled client codebase from an accepted `main` revision or release tag/candidate.

The client codebase must preserve traceability to:

- upstream repository commit;
- accepted release version;
- client-specific commit history;
- generated release ZIP checksum.

Use feature branches, PR review and the repository's minimum-sufficient CI rules for client changes. Do not make routine direct commits to the protected production branch.

Do not commit hosting passwords, API keys, certificates, private exports, database dumps or uploads archives.

## Customization boundaries

Treat these areas differently.

### Upstream-owned runtime and release machinery

Changes here are product-level changes and must remain reviewable against upstream:

- `packages/seo-geo-core/src/`;
- `packages/seo-geo-theme/inc/seo-geo-core/` bootstrap boundary;
- `scripts/build-theme-package.sh`;
- `scripts/build-theme-release.py`;
- `release/version.json`;
- release/quality workflows and CI contracts.

Do not copy-paste or fork Core classes inside client templates to avoid an upstream merge.

### Client presentation layer

A client clone may intentionally customize:

- `packages/seo-geo-theme/theme.json`;
- `packages/seo-geo-theme/templates/`;
- `packages/seo-geo-theme/parts/`;
- `packages/seo-geo-theme/patterns/`;
- `packages/seo-geo-theme/assets/`;
- project-owned translations and presentation copy.

Keep customizations small and attributable. Prefer WordPress/theme composition over duplicating runtime services.

### Preset selection

The supported catalog remains:

- `corporate`;
- `local-business`;
- `publisher`;
- `ecommerce`;
- `saas-digital-product`.

A client should normally select the closest preset and customize presentation after cloning. Do not silently add a sixth preset or change preset semantics without extending the preset contracts and acceptance tests as a product change.

## Client-specific configuration

Values that belong to one deployed site should remain runtime configuration rather than reusable source when possible.

Use onboarding/configuration for:

- active preset;
- language map and routing;
- site entity type;
- LocalBusiness address/public facts;
- crawler policy;
- llms.txt enablement;
- Markdown alternate enablement.

Use environment/hosting secret storage for credentials. Never hardcode credentials into theme PHP, JavaScript, templates, patterns, documentation or release metadata.

## Safe update path

Never blindly overwrite a customized client theme directory with a newer upstream ZIP.

Use this update flow:

1. Record the currently deployed client commit, release version and ZIP SHA-256.
2. Create fresh database/uploads backups for non-disposable environments.
3. Fetch the accepted upstream revision into the client repository.
4. Create an update branch from the client's current accepted branch.
5. Perform a three-way merge or equivalent reviewable integration of upstream changes.
6. Resolve conflicts according to the ownership rules in this document.
7. Update `release/version.json`, the theme `Version:` header and `CHANGELOG.md` together when the client build changes release version.
8. Run the same relevant Foundation, release, self-contained, multilingual, accessibility/responsive and performance gates required by the changed files.
9. Build a deterministic client release ZIP and record its checksum.
10. Install the candidate in sandbox/staging.
11. Compare representative public SEO/GEO signals and client functionality before production deployment.
12. Deploy only after acceptance and retain the immediately previous client ZIP plus recovery references.

An upstream update is not accepted merely because it merges without Git conflicts.

## Merge conflict policy

Resolve conflicts by ownership and behavior, not by choosing "ours" or "theirs" globally.

- **Core/runtime conflict:** prefer the accepted upstream implementation unless the client intentionally carries a documented product-level fork. Re-run all affected runtime contracts.
- **Template/part/pattern conflict:** manually reconcile upstream structural/accessibility fixes with client presentation.
- **theme.json conflict:** preserve client design tokens only where they do not discard upstream schema/version/accessibility requirements.
- **Release metadata conflict:** the target client build must have one coherent version across `release/version.json`, `style.css` and `CHANGELOG.md`.
- **Migration/SEO contract conflict:** unexplained canonical, robots, sitemap, hreflang, redirect or Schema loss is a blocker.

Never resolve a conflict by deleting an acceptance test solely to make CI green.

## Client update acceptance

Before production, require evidence that the customized candidate:

- builds the self-contained theme successfully;
- contains the embedded runtime integrity manifest;
- requires zero SEO/GEO plugins for baseline runtime;
- preserves the client's persisted setup/report across upgrade;
- preserves representative content and URLs;
- passes relevant multilingual checks;
- passes accessibility/responsive checks;
- meets the accepted performance budget;
- preserves or explicitly changes SEO/GEO signals with a documented decision.

For client-specific integrations such as commerce, forms, analytics or consent systems, add focused acceptance rather than weakening the baseline gates.

## Rollback-ready client artifact

Every production update must retain:

- immediately previous accepted client ZIP;
- previous release/version metadata;
- database/uploads recovery references when applicable;
- deployment timestamp;
- acceptance evidence;
- operator responsible for rollback.

Phase 10D owns the detailed production rollback procedure. Phase 10C requires the artifacts to exist before deployment.

## Upstream contribution path

If a client change is reusable and not client-sensitive:

1. isolate it from client branding/data;
2. add/update the appropriate contract and acceptance;
3. contribute it upstream through a feature branch and PR;
4. merge the accepted upstream change back into the client clone.

This avoids permanent client-only forks for fixes that belong to the reusable product.
