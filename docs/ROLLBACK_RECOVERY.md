# Rollback and recovery

Phase 10D defines how to return a client site to an accepted state when production verification fails. Rollback restores the previous application artifact when data is healthy; recovery uses external backups when production data/state itself is damaged.

## Decision tree

Choose the smallest safe recovery action.

Use **runtime rollback** when:

- the new theme/runtime artifact is the cause;
- WordPress database/content remains valid;
- no destructive data migration occurred;
- the previous accepted theme ZIP is available.

Use **data recovery** when:

- database/options/content were corrupted or lost;
- uploads were deleted/corrupted;
- runtime rollback cannot restore an accepted state;
- migration/cutover evidence explicitly requires external recovery.

Use **review-required** when evidence is incomplete or unrelated production drift makes automatic restoration unsafe.

## Runtime rollback

Before changing production again:

1. capture the failed deployment version/checksum and error evidence;
2. confirm the immediately previous accepted client ZIP/checksum;
3. confirm database/uploads backups still exist even if they are not expected to be restored;
4. pause additional theme/configuration changes;
5. reinstall the previous accepted theme artifact using the controlled deployment mechanism;
6. confirm `seo-geo-theme` is active;
7. confirm the embedded runtime comes from the previous theme artifact;
8. confirm the active-plugin list matches the accepted baseline;
9. confirm persisted setup/report and content remain present;
10. run the post-rollback verification in this document.

Do not “fix forward” by editing production theme files directly while the accepted rollback artifact is available.

## Data recovery

Use database/uploads recovery only with an explicit operator decision and recovery reference.

Required evidence:

- backup creation time;
- backup scope;
- database recovery reference;
- uploads recovery reference when relevant;
- positive size/hash/provider evidence where available;
- known point-in-time data-loss implications;
- operator authorization.

Restore the minimum necessary scope. After restoration, redeploy an accepted theme artifact and run production verification again.

## Dynamic-site caution

Commerce, membership, booking, form and other write-heavy sites can receive legitimate production writes after the backup timestamp.

**Do not restore a stale database over new orders, form submissions or customer changes without an explicit data-recovery decision.**

For dynamic sites, choose a transaction-aware/provider-assisted restore or reconcile newer records before replacing live data. Database rollback convenience must never silently discard customer transactions.

## Migration Bridge rollback relationship

Phase 8 cutover records may include runtime rollback and external recovery references.

- Use the Migration Bridge rollback only while its accepted cutover state still owns that operation.
- Do not load Migration Bridge merely to satisfy normal theme runtime.
- Once the final handoff says the bridge is removable/audit-only, routine theme version rollback uses the accepted client artifacts from Phase 10.
- If Migration Bridge reports `rolled-back-review-required`, treat parity/data recovery as unresolved rather than declaring success.

## Cache and CDN recovery

After runtime/data restoration:

- purge only the required cache layers;
- invalidate stale CDN objects for affected public paths;
- verify production origin before broad cache purge;
- re-check redirects and canonical output after rewrite/cache refresh;
- keep sandbox/staging non-indexable.

Do not change DNS or canonical host solely to conceal a failed deployment.

## Post-rollback verification

A rollback is not complete until the previous accepted state is verified.

Check:

- representative HTTP status;
- active theme version/checksum;
- zero-required-plugin baseline;
- wp-admin reachability;
- setup/report availability;
- representative content/page count where relevant;
- canonical/robots/sitemap/hreflang/Schema;
- redirects;
- critical forms/commerce/account paths;
- project PHP/runtime logs;
- accessibility/performance spot checks.

Record whether the result is `accepted-rollback` or `review-required`.

## Incident evidence

Retain bounded evidence:

- failed and restored release version/SHA;
- timestamps;
- triggering check/error signature;
- operator;
- rollback/recovery method;
- backup references;
- verification result;
- follow-up issue/PR reference.

Do not store credentials, full database dumps, private form payloads, customer records or raw uploads in GitHub evidence.

## Retry policy

A failed release must return through a feature/fix branch, relevant CI and sandbox acceptance before another production attempt. Do not repeatedly redeploy the same failed bytes.
