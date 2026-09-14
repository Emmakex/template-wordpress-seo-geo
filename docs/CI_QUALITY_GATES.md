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

## Planned workflow split

As implementation grows, prefer small jobs such as:

```text
foundation
php-quality
unit
seo-contract
multilingual-contract
theme-frontend
a11y
performance
package-smoke
```

Do not collapse unrelated gates into one giant job solely for convenience.

## Phase 0 CI

The initial workflow validates the repository foundation only. It should:

- verify required documentation and skeleton paths;
- validate shell syntax of project CI scripts;
- emit the structured failure summary when a required path is missing;
- remain fast enough to run on every PR.
