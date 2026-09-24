# Sandbox-to-production acceptance

Phase 10D defines the handoff between a non-production candidate and the real client production origin. The non-production environment may be client-provided staging or a portable sandbox created through the provider-neutral strategy in `docs/PORTABLE_SANDBOX.md`.

## Sandbox source

Clients are not required to have a pre-existing staging product. The accepted non-production origin may come from:

- client/hosting staging;
- agency-managed VPS/container or local isolated environment;
- another supported temporary provisioner.

Regardless of origin, the same indexing isolation, artifact identity, parity, quality and backup gates apply. Production is never the first migration test environment.

## Sandbox exit criteria

The sandbox candidate may request production entry only when all applicable evidence is accepted.

Require:

- non-production origin;
- `SEO_GEO_MIGRATION_SANDBOX=true` for existing-site migration;
- WordPress search-engine visibility disabled in sandbox;
- intended release version and ZIP SHA-256;
- representative migrated/clean-install content;
- accepted dependency/migration plan when applicable;
- fresh SEO parity for existing-site adoption;
- fresh Accessibility/Responsive evidence;
- fresh Performance/Lighthouse evidence;
- no project PHP runtime diagnostics;
- zero required SEO/GEO plugins in the final candidate;
- explicit intentional-difference list.

Sandbox success does not mutate production automatically.

## Production entry criteria

Before the production window starts, record:

- production origin;
- target release version/checksum;
- previous accepted release/checksum;
- database backup reference;
- uploads backup reference where applicable;
- rollback artifact location/reference;
- operator and approver;
- planned theme/plugin changes;
- cache/CDN ownership;
- maintenance/traffic/data-sync plan for dynamic sites, explicitly preventing stale sandbox databases from overwriting newer production orders/submissions/users;
- client-critical functional checks.

For an existing-site cutover, use the Phase 8 accepted cutover/handoff evidence as an input rather than recreating legacy state from memory.

## Environment separation

The sandbox and production contracts must not be confused.

- Sandbox retains its non-production marker and remains non-indexable.
- Production must not run with `SEO_GEO_MIGRATION_SANDBOX=true`.
- Production uses the canonical public origin.
- Sandbox URLs must not become production canonicals, hreflang targets or sitemap entries.
- Credentials/configuration are environment-owned and are not copied through repository documentation.

## Change window

Immediately before deployment:

1. reconfirm target and previous release checksums;
2. reconfirm backups/recovery references;
3. freeze unrelated theme/config changes for the deployment window;
4. record any dynamic-site transaction risk;
5. confirm the rollback operator has access to the previous artifact and hosting controls;
6. confirm there is no unresolved parity/quality blocker.

If evidence changed or expired, return to sandbox verification.

## Cutover execution

Use the controlled deployment path for the client environment.

- deploy/install the accepted release artifact;
- make only the planned theme/plugin mutations;
- preserve client content/options unless the accepted plan explicitly says otherwise;
- refresh rewrite/cache state only as required;
- immediately run `docs/PRODUCTION_VERIFICATION.md`.

Do not make opportunistic production customizations during cutover.

## Go/no-go decision

**Go** requires all mandatory production verification checks to pass.

Choose **no-go/rollback-required** when a blocker from the production verification checklist appears.

Choose **recovery-required** when runtime rollback cannot restore valid data/state.

Choose **review-required** when evidence is ambiguous, stale or unrelated drift makes automatic rollback unsafe.

The operator must not convert a failed mandatory check into a warning merely to finish the deployment.

## Acceptance record fields

Use `docs/templates/PRODUCTION_ACCEPTANCE_RECORD.example.json` as a bounded evidence shape.

The record links:

- site/release/commit identity;
- sandbox evidence;
- backup/recovery references;
- quality evidence;
- production checks;
- rollback artifact;
- final decision.

References may point to private client systems, but secrets and raw private payloads must not be copied into the repository.

## Handoff to Phase 10E

Phase 10E may consider a stable release only after the production-readiness contract is proven on the selected real-site sandbox/production acceptance. Phase 10D documentation alone is not a declaration that a real client site has passed production acceptance.
