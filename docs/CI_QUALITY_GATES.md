# CI and Quality Gates

## Purpose

CI exists to prove the contract changed by a PR and to turn failures into small actionable diagnostics. CI should not become an enormous opaque log that developers must manually scan.

## Gate layers

### Foundation / repository

Always cheap and fast:

- required files/directories present;
- Markdown/document structure checks where configured;
- shell syntax for CI scripts;
- no obvious committed secrets/private keys;
- package metadata sanity.

### PHP / WordPress

When PHP/plugin/theme code changes:

- PHP syntax;
- WordPress Coding Standards;
- static analysis at the supported project level;
- unit/integration tests relevant to changed modules;
- plugin/theme activation smoke tests where transitional packaging is affected;
- zero-plugin theme smoke whenever the self-contained runtime or distribution boundary changes.

#### Phase 1C toolchain

The initial PHP quality contract is reproducible and deliberately strict:

- PHP 8.2 CI runtime;
- WordPress Coding Standards `3.4.1`;
- PHP_CodeSniffer `3.13.6`;
- PHPCSUtils `1.2.3`;
- PHPCSExtra `1.5.1`;
- PHPStan `2.2.14`;
- `phpstan-wordpress` `2.0.4`;
- WordPress stubs `7.1.0`;
- PHPCS Composer installer `1.2.1`.

All development packages are pinned to exact versions in `composer.json`. The dependency-resolution gate also emits the resolved `composer.lock` as a short-lived CI artifact so dependency changes are inspectable during quality-tool upgrades.

WPCS runs the official `WordPress` standard against project-owned PHP in `packages/seo-geo-theme` and `packages/seo-geo-core`. Two and only two filename-convention sniffs are excluded:

- `WordPress.Files.FileName.InvalidClassFileName`;
- `WordPress.Files.FileName.NotHyphenatedLowercase`.

The exception exists because Core deliberately uses namespaced PSR-style class paths for its small autoloader. It does **not** suppress API naming, documentation, security, escaping, database, internationalization or interoperability rules. Project-owned methods follow WordPress snake_case conventions even inside namespaced classes.

PHPStan starts at **level 6** with `phpstan-wordpress`. There is no generated baseline and no ignored-error list. A new exclusion must be narrow, documented and justified by the affected contract rather than added to make CI green.

`PHP Quality CI` executes in this order:

```text
Composer validation / dependency installation
-> WPCS
-> PHPStan level 6
```

The first failing gate stops the chain, so we diagnose and close one contract before opening the next.

### Frontend

When CSS/JS/theme patterns change:

- lint/format as configured;
- asset build;
- project-owned console error check;
- responsive fixture rendering;
- relevant accessibility checks;
- relevant performance budget checks.

### SEO/GEO

When public metadata/discovery behavior changes:

- exactly one canonical;
- indexability resolver agrees with sitemap/discovery output;
- robots output expected;
- no duplicate provider ownership;
- JSON-LD parses once Schema exists;
- Schema graph invariants once Schema exists;
- public links are crawlable;
- private/draft resources excluded;
- Markdown/llms outputs safe when enabled.

Phase 6F adds a cross-surface privacy matrix, sourced inside the existing Self-contained Theme fixture, that checks synthetic draft/private markers against HTML, native sitemaps, feeds, search, author archives, unauthenticated REST, Schema/provenance, llms.txt and Markdown. The matrix includes a positive published control so privacy cannot pass because discovery is globally broken.

Phase 6G adds discovery-document cache/revalidation acceptance in that same disposable fixture. It requires stable ETags for unchanged llms.txt/Markdown responses, HTTP 304 for matching conditional requests, changed ETags and refreshed bodies after relevant mutations, no invalidation for unrelated options, and revision changes for post, author/profile and translation metadata mutations.

The baseline contract is **native and self-contained**. An external SEO/GEO plugin is never required for these acceptance checks.

### Presets

Preset changes reuse existing gates rather than creating a runner per archetype.

For Phase 7A Corporate:

- Foundation executes `scripts/ci/validate-corporate-preset.php` to prove the manifest, required templates, Organization confirmation rule, EN/ES content-map parity, allowed pattern references and token-safe Corporate patterns;
- Self-contained Theme CI bundles the preset and sources `scripts/ci/corporate-preset-acceptance.sh` inside the existing WordPress/MariaDB fixture;
- the WordPress acceptance proves Corporate is off by default, registers all three Corporate patterns only after allowlisted activation, resolves EN/ES preset copy, and rejects unsupported preset IDs;
- no additional Docker environment or standalone Corporate workflow is created.

Phase 7A closure evidence:

- final PR candidate `68a6c2161baffa8784d20a5a1663520a12ee379a` passed all ten workflows;
- Self-contained Theme CI `35440066944` proved the built preset package, default-off activation contract, EN/ES pattern copy and unsupported-ID fallback;
- PHP Quality CI `35440066742` passed WPCS and PHPStan level 6;
- PR #54 was squash-merged as `7f3499073d82fd362f88c6f3abd6949c52223057`;
- all ten post-merge workflows passed on `main`, including Self-contained Theme CI `35446033925`, Native Multilingual CI `35446033952`, Accessibility & Responsive CI `35446033944` and Performance Baseline CI `35446033965`.

For Phase 7B Local Business:

- Foundation executes `scripts/ci/validate-local-business-preset.php` alongside the Corporate validator;
- the static contract proves explicit LocalBusiness identity confirmation, visible-fact gates, physical-address requirement, EN/ES page parity, real multi-location/service-area modeling, anti-doorway rules, allowed pattern references and token-safe Local Business patterns;
- Self-contained Theme CI sources `scripts/ci/local-business-preset-acceptance.sh` inside the existing WordPress/MariaDB fixture;
- the runtime acceptance proves default-off behavior, exactly three Local Business patterns, declarative category ownership, Corporate/Local Business isolation, EN/ES copy and that preset activation does **not** auto-enable LocalBusiness Schema identity;
- Corporate's unsupported-preset probe uses a permanent sentinel rather than a planned future preset ID;
- no Local Business-specific workflow, second Docker fixture or new dependency is added.

Phase 7B closure evidence:

- final PR candidate `74a55c0efc2fbcc4163575f30afa61c3f83ce983` passed all ten workflows on its first CI candidate;
- Foundation CI `35448138067` passed the declarative Local Business contract;
- Self-contained Theme CI `35448138086` passed the built-theme Local Business activation/isolation contract;
- PHP Quality CI `35448138092`, Native Multilingual CI `35448138085`, Accessibility & Responsive CI `35448138080` and Performance Baseline CI `35448138089` all passed;
- PR #56 was squash-merged as `ef359337172e6a7fe54ff6e12e4b17ec0e44d14e`;
- all ten post-merge workflows passed again on `main`, including Self-contained Theme CI `35448283956`, Native Multilingual CI `35448283976`, Accessibility & Responsive CI `35448283930` and Performance Baseline CI `35448283928`.

For Phase 7C Publisher:

- Foundation executes `scripts/ci/validate-publisher-preset.php` alongside the Corporate and Local Business validators;
- the static contract proves WordPress post/user/date authority, explicit Organization confirmation, EN/ES page parity, native article/topic/author surfaces, anti-inference rules, allowed pattern references and token-safe Publisher patterns;
- Self-contained Theme CI sources `scripts/ci/publisher-preset-acceptance.sh` inside the existing WordPress/MariaDB fixture;
- runtime acceptance proves default-off behavior, exactly four Publisher patterns, declarative category ownership, isolation from Corporate/Local Business, EN/ES copy and that preset activation leaves the Schema identity option unchanged;
- Publisher deliberately reuses the neutral author-profile pattern and native content provenance rather than duplicating author/date metadata;
- no Publisher-specific workflow, second Docker fixture or new dependency is added.

Phase 7C closure evidence:

- final PR candidate `6a9407a288f253544e9c43d47c727d3771b12116` passed all ten workflows on its first CI candidate;
- Foundation CI `35454815270` passed the Publisher declarative contract;
- Self-contained Theme CI `35454815324` passed the built-theme Publisher activation/isolation and Schema-identity non-mutation contract;
- Native Multilingual CI `35454815269`, Accessibility & Responsive CI `35454815298` and Performance Baseline CI `35454815290` all passed;
- PR #58 was squash-merged as `6ef503ff03db934ef33170ad48d07bcc94ec45ac`;
- all ten post-merge workflows passed again on `main`, including Self-contained Theme CI `35454988460`, Native Multilingual CI `35454988462`, Accessibility & Responsive CI `35454988437` and Performance Baseline CI `35454988446`.

For Phase 7D Ecommerce:

- Foundation executes `scripts/ci/validate-ecommerce-preset.php` alongside the first three preset validators;
- the static contract proves provider-owned Product/Offer/review Schema, explicit Organization confirmation, zero-plugin safety, EN/ES parity, provider-owned shop/category/product surfaces, anti-facet/indexing defaults and core-block-only Ecommerce patterns;
- Self-contained Theme CI sources `scripts/ci/ecommerce-preset-acceptance.sh` inside the existing zero-plugin WordPress/MariaDB fixture;
- runtime acceptance explicitly proves WooCommerce is absent, Ecommerce still activates all four patterns, earlier preset patterns stay isolated, ES/EN copy resolves, and Schema identity configuration is not mutated;
- declaring WooCommerce as the preferred provider does not mark it supported; no live-commerce compatibility claim is allowed before a dedicated adapter/acceptance phase;
- no Ecommerce-specific workflow, second Docker fixture or new dependency is added.

### Multilingual

When URL/content metadata changes:

- ES fixture;
- EN fixture;
- correct self canonical;
- valid reciprocal hreflang set once implemented;
- optional `x-default` policy;
- correct `html[lang]`;
- Open Graph locale consistency when emitted;
- Schema `inLanguage` consistency once emitted;
- locale preserved in navigation/breadcrumbs/alternate formats.

### Build/distribution

When packaging changes:

- deterministic build;
- self-contained theme contents valid;
- embedded SEO/GEO runtime present;
- no development-only files/secrets in packages;
- fresh install smoke test with zero required plugins;
- runtime origin proved to be the installed theme bundle;
- upgrade test once versioning exists.

The final product boundary is one installable theme. The transitional standalone Core plugin wrapper is not a required distribution artifact.

## Minimum sufficient validation

CI workflows should use path/module awareness so a docs-only change does not run full browser suites, while a language resolver change runs every test contract affected by language resolution.

The changed contract—not file extension alone—determines sufficient validation.

Examples:

- quality-config-only change -> Foundation + PHP Quality;
- project PHP API change -> Foundation + Package + PHP Quality + relevant WordPress runtime smoke;
- self-contained runtime/bootstrap/build change -> Foundation + PHP Quality + WordPress Smoke + Self-contained Theme + affected browser/performance gates;
- runtime-smoke script change -> Foundation + the matching runtime smoke;
- documentation that changes a declared quality/phase contract -> the workflows whose contract the document changes.

## Failure diagnostics

Every custom CI script should fail with a concise structured block before the raw details where practical:

```json
{
  "schema_version": 1,
  "pipeline": "GitHub Actions",
  "run_id": "...",
  "run_attempt": "...",
  "job": "...",
  "step": "...",
  "command": "...",
  "exit_code": 1,
  "primary_error": "...",
  "file_line": null,
  "expected": null,
  "received": null,
  "error_signature": "...",
  "root_cause_status": "unknown"
}
```

For WPCS the quality wrapper extracts the first actionable message, file/line/column and sniff source. For PHPStan it extracts the first actionable message, file/line and error identifier. Full tool output remains available underneath for evidence.

For critical `wp eval`/WP-CLI checks, the command exit status must be checked before interpreting stdout. Stderr must be preserved and surfaced through the structured diagnostic rather than redirected away; otherwise an execution failure can masquerade as an empty application value.

CI may upload a diagnostic artifact later, but the key summary must remain visible in the job output.

## Failure workflow

1. Identify the first failing contract/subcommand.
2. Reduce it to the structured diagnostic.
3. Reproduce locally/in focused CI if possible.
4. Confirm root cause or label the hypothesis explicitly.
5. Add focused regression coverage.
6. Apply the minimal root-cause fix.
7. Run the minimum sufficient gates.
8. Record the incident if it meets the error-register criteria.
9. Only then expand validation to required downstream gates.

## Workflow split

The repository currently separates these concerns and will continue expanding rather than collapsing them into one opaque job:

```text
foundation
phase-1-package
wordpress-smoke
php-quality
design-system
patterns
accessibility-responsive
performance
self-contained-theme
unit                 # later phase
seo-contract         # later phase
multilingual-contract # later phase
package-smoke        # distribution phase
```

Do not collapse unrelated gates into one giant job solely for convenience.

## Phase 0 CI

The Foundation workflow validates the repository contract:

- required documentation and skeleton paths;
- self-contained bootstrap/runtime/build/smoke paths;
- shell syntax of project CI scripts;
- structured failure summary when a required path is missing;
- fast execution on every relevant PR/main change.

## Phase 1 package CI

The package contract validates the original static package skeleton:

- required theme/transitional plugin files;
- PHP syntax;
- `theme.json` version 3;
- package headers/text domains;
- WordPress 7.1 / PHP 8.2 package baseline;
- semantic `<main>` landmark on shipped block templates.

This gate is retained for regression coverage even though Phase 3 changed the final distribution target from two required packages to one self-contained theme.

## Phase 1 runtime smoke

The historical runtime smoke validates both source packages in a disposable real WordPress fixture:

- WordPress 7.1 / PHP 8.2;
- MariaDB 11.8.x;
- WP-CLI 2.12.x;
- transitional plugin + theme activation/state;
- Core language/integration service initialization;
- frontend/admin HTTP routes;
- runtime/debug logs free of project PHP fatal/warning/notice/uncaught errors;
- resource cleanup after the run.

It remains useful as backwards/regression coverage but is no longer the authoritative distribution acceptance.

## Self-contained Theme CI

`Self-contained Theme CI` is the authoritative packaging/runtime gate for the baseline product introduced in Phase 3B.

It must prove on a disposable real WordPress fixture that:

- `scripts/build-theme-package.sh` assembles the installable theme and embeds `packages/seo-geo-core/src` under `inc/seo-geo-core/src`;
- the fixture runs WordPress 7.1 / PHP 8.2 with the built theme active;
- **zero plugins are active**;
- `wp-content/plugins/seo-geo-core` is absent;
- `SeoGeo\Core\Runtime` resolves from `wp-content/themes/seo-geo-theme/inc/seo-geo-core/src/Runtime.php`;
- native SEO authority is active;
- an indexable fixture exposes exactly one canonical and one expected meta description;
- the same fixture exposes exactly one parseable native Schema JSON-LD graph with deterministic WebSite/WebPage IDs and canonical/language alignment;
- a search fixture exposes exactly one robots meta containing `noindex` and no native Schema graph;
- runtime/debug logs contain no PHP fatal errors, warnings, notices or uncaught exceptions.

A green source-level test is not a substitute for this gate. The acceptance must exercise the **built distribution** so repository fallback paths cannot hide a missing embedded runtime.

Phase 3B PR #13 passed this gate before merge (`35257079331`) and again post-merge on `main` (`35257573577`).

Phase 5A extended this gate with native Schema graph acceptance. PR #25 passed the self-contained gate before merge and post-merge run `35309620077` passed again on `main`. Native Multilingual CI run `35309620064` additionally proves localized ES/EN Schema language alignment.
