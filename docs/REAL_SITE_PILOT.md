# Phase 10E real-site pilot — emmake.com

This document fixes the first real-site acceptance target for the self-contained SEO/GEO theme without claiming acceptance before the sandbox and production gates are actually executed.

## Pilot identity

- Site ID: `emmake-com`
- Production origin: `https://emmake.com`
- Target release: `0.1.0`
- Release channel: `prestable`
- Candidate main commit: `d3ff8353c08cfce6c796837a74e372ba7daf0073`
- Candidate ZIP SHA-256: `dae8da490526fd3584387324bc1bc596d17ad5e681513927c0468b48e786ebba`
- Stable decision: `no-go`
- Pilot status: **sandbox acceptance pending**

The public production homepage is reachable. Public sitemap/robots/llms endpoint checks performed through the external web-reading surface were inconclusive because that surface could not retrieve those endpoints; they must be verified directly during the technical baseline with browser/curl and are not marked pass/fail here.

## Non-negotiable boundary

The pilot does **not** switch production directly to the new theme.

Production remains on the current accepted site until a separate sandbox clone passes the Phase 8–10D contracts. No real-site acceptance reference may be written into `release/stable-release-decision.json` until the final production verification is actually accepted.

## Sandbox preparation

Before installing the candidate:

1. create a current database backup and uploads backup with recoverable references;
2. create a distinct non-production clone of emmake.com;
3. set `SEO_GEO_MIGRATION_SANDBOX=true`;
4. disable WordPress search-engine visibility in the sandbox;
5. confirm sandbox URLs cannot become public canonical/hreflang/sitemap targets;
6. install the temporary Migration Bridge only in the adoption workflow where needed;
7. install the accepted candidate theme ZIP with the exact SHA-256 above;
8. record WordPress/PHP versions, active theme, active/must-use plugins, builder dependencies and client-critical integrations.

No credentials, database dumps, private form submissions or customer data are committed to this repository.

## Current-site baseline to capture

Before transformation, capture representative public output for:

- homepage;
- Sobre Nosotros;
- Trabaja con Nosotros;
- blog index;
- representative recent article;
- Contacto;
- any other URL class discovered by the site analyzer.

For each representative URL capture:

- HTTP status;
- canonical;
- robots/indexability;
- title/meta description;
- Open Graph;
- Schema graph;
- hreflang/x-default if present;
- redirects;
- sitemap membership;
- visible organization/contact facts relevant to Schema;
- key internal links.

Also inventory:

- current theme and builder;
- active/must-use plugins;
- forms;
- analytics/consent;
- redirects;
- cache/CDN;
- custom post types/taxonomies;
- media behavior;
- any external SEO/language provider ownership.

## Sandbox migration acceptance

The sandbox candidate must pass:

- Migration Bridge dependency classification;
- supported content/builder transformations only;
- no unexplained URL loss;
- strict SEO/GEO parity or explicit allowlisted intentional differences;
- zero-required-plugin final runtime;
- onboarding Apply + idempotent re-Apply;
- EN/ES behavior when configured;
- Accessibility/Responsive acceptance;
- Performance/Lighthouse budgets;
- no project PHP fatal/warning/notice;
- client-critical form/navigation behavior.

Unsupported builder modules or integrations are review/blocker items, never silently discarded.

## Production entry prerequisites

Before any production cutover:

- sandbox parity accepted;
- representative browser/accessibility evidence fresh;
- performance evidence fresh;
- database/uploads backups fresh;
- previous accepted site artifact/runtime recovery path known;
- target ZIP checksum reverified;
- planned theme/plugin mutations explicit;
- rollback operator and recovery references available;
- no unresolved blocker or unreviewed intentional SEO difference.

## Production verification

Immediately after controlled cutover, execute `docs/PRODUCTION_VERIFICATION.md`.

The final acceptance record must cover runtime, SEO/GEO, functionality, accessibility, performance and logs. Any material canonical/indexability/sitemap/hreflang/redirect regression, broken critical form, private-content exposure, fatal/5xx or artifact identity mismatch triggers rollback/recovery according to `docs/ROLLBACK_RECOVERY.md`.

## Two-product scope

The Theme 0.1.0 stable gate does **not** wait for the future SEO/GEO Manager product to be fully implemented. The current accepted Migration Bridge remains the migration implementation used for this Theme pilot.

The portfolio decision adds a second, later acceptance use for emmake.com:

- first: complete Theme sandbox + production acceptance for Phase 10E;
- later: after Manager reaches its publishing/integration roadmap phases, use emmake.com as the first **Theme + Manager** integration pilot;
- Manager publication acceptance starts draft-first and must prove idempotency, rollback and single SEO/GEO authority before public publishing is accepted.

This separation prevents a new product roadmap from silently blocking the already-defined Theme stable-release gate.

## Promotion to stable

Only after the real production acceptance is `accepted`:

1. store a bounded evidence reference in `release/stable-release-decision.json`;
2. change real-site acceptance to `accepted`;
3. remove the pending blocker;
4. change stable decision from `no-go` to `go`;
5. change `release/version.json` from `prestable` to `stable`;
6. convert the changelog target to released `0.1.0`;
7. run required CI again;
8. publish the first stable release only after all required gates are green.

Until those steps are complete, the correct decision remains **NO-GO**.
