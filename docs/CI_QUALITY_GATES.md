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
- plugin/theme activation smoke tests.

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
- JSON-LD parses;
- Schema graph invariants;
- public links are crawlable;
- private/draft resources excluded;
- Markdown/llms outputs safe when enabled.

### Multilingual

When URL/content metadata changes:

- ES fixture;
- EN fixture;
- correct self canonical;
- valid reciprocal hreflang set;
- optional `x-default` policy;
- correct `html[lang]`;
- Open Graph locale consistency when emitted;
- Schema `inLanguage` consistency;
- locale preserved in navigation/breadcrumbs/alternate formats.

### Build/distribution

When packaging changes:

- deterministic build;
- theme ZIP contents valid;
- plugin ZIP contents valid;
- no development-only files/secrets in packages;
- fresh install smoke test;
- upgrade test once versioning exists.

## Minimum sufficient validation

CI workflows should use path/module awareness so a docs-only change does not run full browser suites, while a language resolver change runs every test contract affected by language resolution.

The changed contract—not file extension alone—determines sufficient validation.

Examples from Phase 1:

- quality-config-only change -> Foundation + PHP Quality;
- project PHP API change -> Foundation + Package + PHP Quality + WordPress runtime smoke;
- runtime-smoke script change -> Foundation + WordPress runtime smoke;
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
unit                 # later phase
seo-contract          # later phase
multilingual-contract # later phase
theme-frontend        # later phase
a11y                   # later phase
performance            # later phase
package-smoke          # distribution phase
```

Do not collapse unrelated gates into one giant job solely for convenience.

## Phase 0 CI

The Foundation workflow validates the repository contract:

- required documentation and skeleton paths;
- shell syntax of project CI scripts;
- structured failure summary when a required path is missing;
- fast execution on every relevant PR/main change.

## Phase 1 package CI

The package contract validates the static installable skeleton:

- required theme/plugin files;
- PHP syntax;
- `theme.json` version 3;
- package headers/text domains;
- WordPress 7.1 / PHP 8.2 package baseline;
- semantic `<main>` landmark on shipped block templates.

## Phase 1 runtime smoke

The runtime smoke validates the package in a disposable real WordPress fixture:

- WordPress 7.1 / PHP 8.2;
- MariaDB 11.8.x;
- WP-CLI 2.12.x;
- plugin + theme activation/state;
- Core language/integration service initialization;
- frontend/admin HTTP routes;
- runtime/debug logs free of project PHP fatal/warning/notice/uncaught errors;
- resource cleanup after the run.
