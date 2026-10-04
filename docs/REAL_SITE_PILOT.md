# Phase 10E real-site pilot — emmake.com

This document fixes the first real-site acceptance target for the self-contained SEO/GEO theme and records the successful real-clone field milestone without confusing it with Theme sandbox acceptance or production acceptance.

## Pilot identity

- Site ID: `emmake-com`
- Production origin: `https://emmake.com`
- Target release: `0.1.0`
- Release channel: `prestable`
- Candidate main commit: `d3ff8353c08cfce6c796837a74e372ba7daf0073`
- Candidate ZIP SHA-256: `dae8da490526fd3584387324bc1bc596d17ad5e681513927c0468b48e786ebba`
- Stable decision: `no-go`
- Pilot status: **real product-owned clone completed; 11/11 Divi resources migrated to native blocks with 0 blockers and functional Contact form delivery verified; native Corporate rebuild, SEO/GEO regression acceptance and production cutover remain pending**
- Accepted Migration Bridge version: `0.8.36`
- Accepted Migration Bridge main commit: `b78f38016ac0848cd37b75fb608262b289d32ceb`
- Accepted Migration Bridge ZIP SHA-256: `1da3ce1fbadf28c379c8f4cb2272b17e223c36ea348d892aa5fbb110837fc792`

The production Migration Bridge baseline was completed on 2026-09-24 and the operator screen reported `SEO/GEO baseline = Ready`. This is operator-confirmed real-site evidence; no private baseline payload or production credentials are committed to the repository. The dependency summary at that point was `KEEP=4`, `REPLACE=2`, `MIGRATE=1`, `OPTIONAL=0`, `REMOVE-CANDIDATE=0`, `UNKNOWN=13`. The v0.8.5 handoff generated on 2026-09-24 reported 4,311 discovered public resources, 500 captured resources, zero request failures and a truncated baseline by the configured cap. A second v0.8.6 handoff generated at `2026-09-24T19:56:28Z` recorded all 13 UNKNOWN items as explicitly reviewed: 10 operator decisions `KEEP` and 3 `MIGRATE`, with zero unreviewed UNKNOWN items. The bounded handoff file SHA-256 is `48b87fed7c8b09be9778f0e62045c0eb8bcf23b35186883ee9f0629a19431d4b`. These review decisions are planning evidence only and do not authorize production mutation.

## 2026-10-03 real-clone field milestone

The product-owned clone path has now been executed successfully in the selected pilot environment:

- source: `https://emmake.com/`;
- destination: `https://emmake.com/nuevaweb/`;
- field-tested Migration Bridge artifact: `1.0.8`;
- installable ZIP SHA-256: `c8713e8cb2714364fc5f804dcb33213f220a6ab010497c22b5f52f6f1ed475e2`;
- persistent packaging completed through 10/10 saved chunks;
- the large final package was transported as physical multipart files with per-part SHA-256 verification;
- multipart upload/reconstruction completed on the destination;
- destination activation completed and the source URLs were rewritten to the `/nuevaweb/` target;
- operator verification confirmed that the public destination stopped showing the clean WordPress `Hello world!` installation and rendered the Emmake site instead.

Bounded field evidence for this milestone is stored at `release/emmake-clone-field-milestone-20261003.json`.

This milestone proves the practical clone **transport + destination activation** path. It does **not** by itself claim sandbox-storage isolation, Theme candidate acceptance, SEO/GEO parity, accessibility/performance acceptance or a production cutover. The next accepted operation is Theme migration and parity testing on the working `/nuevaweb/` sandbox clone.

## 2026-10-03 Divi-to-native migration field milestone

The working `/nuevaweb/` clone has now completed its builder-content migration:

- 11 resources containing Divi `et_pb_*` content were inventoried;
- all 11 resources were migrated to native WordPress/Theme-owned blocks;
- final Migration Bridge operator summary: `11 resources`, `0 ready`, `0 blocked`, `11 converted`;
- every converted resource retained a per-resource **Rollback Divi** path;
- two legacy modules (`et_pb_signup`, `et_pb_sidebar`) were intentionally omitted by operator decision rather than treated as unresolved blockers;
- the Contact page was migrated to the Theme-owned native contact-form runtime;
- the migrated Contact form rendered its Name, Email and Message fields, returned the configured success message after submission, and delivered the test email successfully to the configured recipient;
- the Contact resource kept its destination URL path under `/nuevaweb/contacto/`.

Bounded evidence for this milestone is stored at `release/emmake-divi-migration-field-milestone-20261003.json`.

This closes the **Divi dependency migration** hurdle for the sandbox. It does not claim final replatform acceptance, SEO/GEO regression acceptance, accessibility/performance acceptance or production cutover. The next accepted operation is to rebuild the public surfaces with the native Corporate preset, remapping preserved content into the new structure rather than reproducing the old Divi layout.

## Replatforming correction — 2026-10-04

The pilot is **not** a visual-parity migration. The old Emmake/Divi design is no longer the destination reference.

Preserve: valuable content, URLs, SEO signals, links, media, verified facts and required business behavior.

Replace: theme/builder layout, legacy composition, CSS, widgets, decorative structure and visual dependencies.

The target is a fresh Corporate site built with the SEO/GEO Theme and reusable native components. Emmake is evidence that the product can modernize a real legacy WordPress site without sacrificing its search asset.

## Non-negotiable boundary

The pilot does **not** switch production directly to the new theme.

Production remains on the current accepted site until a separate sandbox clone passes the Phase 8–10D contracts. No real-site acceptance reference may be written into `release/stable-release-decision.json` until the final production verification is actually accepted.

## Sandbox handoff status

Production baseline capture and UNKNOWN dependency review are complete. The regenerated privacy-bounded Migration Bridge 0.8.6 handoff records `reviewed_unknown=13`, `unreviewed_unknown=0` and `complete=true`. The three UNKNOWN items marked `MIGRATE` are Classic Editor, Cookie Notice and Kairoseth AI Web Readiness; the remaining ten UNKNOWN items are recorded as operator `KEEP`. These decisions remain separate from raw dependency-graph classifications and do not authorize production mutation. The next accepted operation is to create a distinct non-production clone, carry the bounded review evidence into that clone, satisfy all sandbox isolation guards and run migration/parity/quality acceptance there.

Migration Bridge **0.8.36** is now the accepted Portable Clone Engine build for this pilot. It includes the same-origin subdirectory safety contract introduced in 0.8.8 plus resumable export/import, isolated database/file staging, serialization-safe rewrite, reversible activation/promotion and final read-only handoff reporting. The real `https://emmake.com/nuevaweb/` clone has now been executed and visually verified. That observation proves transport/activation, while the remaining sandbox-isolation and Theme parity evidence must still be collected before the sandbox can be accepted.

For this pilot, the selected topology is `subdirectory`. Before migration actions, the clone must preserve the production baseline/dependency/review evidence and satisfy `SEO_GEO_MIGRATION_SANDBOX=true`, `SEO_GEO_MIGRATION_SANDBOX_MODE='subdirectory'`, `SEO_GEO_MIGRATION_STORAGE_ISOLATED=true`, `SEO_GEO_MIGRATION_OUTBOUND_SAFE=true`, `SEO_GEO_MIGRATION_BACKUPS_READY=true`, WordPress search visibility disabled, a non-root `/nuevaweb/` home path distinct from the production `/` path, destination Theme active and complete UNKNOWN review. The storage marker is an explicit operator confirmation that the clone does not share mutable database/table state with production.

## Portable Clone Engine decision

The pilot exposed that requiring a separate cloning/migration plugin would weaken the product boundary. The accepted target is now for Migration Bridge itself to create or transport the sandbox.

The implementation contract is `docs/PORTABLE_CLONE_ENGINE.md`.

For emmake.com:

- the intended target remains `https://emmake.com/nuevaweb/`;
- the current clean/test installation in that path is not accepted as the migration clone;
- the Portable Clone Engine must create/restore a complete isolated copy of production there;
- database/table state and mutable file storage must be independent;
- the full production baseline/dependency/review evidence must survive the copy;
- only bounded evidence/checksums enter the repository;
- after clone integrity succeeds, the existing v0.8.8 subdirectory preflight must report `ready=true`.

Manual/hosting cloning may still be used as a temporary development fallback, but 10E.2A acceptance specifically proves the product-owned clone/export/import workflow.

## Sandbox preparation

Before installing the candidate:

1. create a current database backup and uploads backup with recoverable references;
2. replace the current clean `/nuevaweb/` installation with a complete isolated clone of emmake.com;
3. set `SEO_GEO_MIGRATION_SANDBOX=true`, `SEO_GEO_MIGRATION_SANDBOX_MODE='subdirectory'`, `SEO_GEO_MIGRATION_STORAGE_ISOLATED=true`, `SEO_GEO_MIGRATION_OUTBOUND_SAFE=true` and `SEO_GEO_MIGRATION_BACKUPS_READY=true`;
4. verify the clone uses isolated database/table state and disable WordPress search-engine visibility in the sandbox;
5. confirm sandbox URLs cannot become public canonical/hreflang/sitemap targets;
6. install/use Migration Bridge 0.8.36 from the accepted ZIP SHA-256 `1da3ce1fbadf28c379c8f4cb2272b17e223c36ea348d892aa5fbb110837fc792` and execute the product-owned local-clone workflow;
7. after the real clone handoff reports ready, install the accepted candidate theme ZIP with the exact Theme SHA-256 above;
8. record WordPress/PHP versions, active theme, active/must-use plugins, builder dependencies and client-critical integrations.

No credentials, database dumps, private form submissions or customer data are committed to this repository.

## 10E.2A.6 real clone acceptance record

Execute the live clone using `docs/EMMAKE_REAL_CLONE_RUNBOOK.md`; do not infer environment-owned filesystem/table-prefix values from repository documentation.

The real execution must update the bounded record at `release/emmake-real-clone-acceptance.json`, whose schema/example is `docs/templates/EMMAKE_REAL_CLONE_ACCEPTANCE_RECORD.example.json`. The repository record may contain identities, counts, hashes, booleans, blocker codes and private-system references, but never credentials, database dumps, uploads, post bodies, customer data or arbitrary option values.

Acceptance requires all of the following at the same time:

- the product-owned 0.8.36 Engine created/restored the clone into `/nuevaweb/`;
- target database/table namespace is independent from production;
- target uploads/plugins/themes are independent mutable copies;
- production `home`/`siteurl`, runtime and source fingerprints remain unchanged;
- the final local handoff report is verified and has a bounded SHA-256;
- `blog_public=0`, noindex/outbound/backups/storage isolation guards remain true;
- the pre-existing baseline/dependency/UNKNOWN-review evidence survives the clone;
- Sandbox Migration Lab reports `ready=true`;
- rollback remains available;
- no blocker remains.

The stable decision remains `no-go` until this record is accepted and referenced by `release/stable-release-decision.json`.

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
