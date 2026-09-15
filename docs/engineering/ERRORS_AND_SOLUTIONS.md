# Errors and Solutions Register

This file is the durable memory for relevant engineering failures. It prevents the team from repeatedly rediscovering the same root cause.

## When to add/update an entry

Record:

- production failures;
- regressions;
- security/privacy incidents;
- repeated CI/build/deploy failures;
- integration failures with a reusable lesson;
- performance regressions caused by architecture/code;
- SEO/multilingual defects that could silently affect many pages;
- bugs whose root cause should change a guardrail or test.

Do not log trivial typos unless they reveal a repeatable class of failure. Never include secrets, tokens, personal data or customer-confidential values.

## Structured diagnostic contract

Before the narrative entry, failures should be reducible to this shape when fields are applicable:

```yaml
pipeline: null
run_id: null
run_attempt: null
job: null
step: null
command: null
exit_code: null
primary_error: null
file_line: null
expected: null
received: null
error_signature: null
root_cause_status: unknown # confirmed | hypothesis | unknown
root_cause: null
fix_applied: null
validation: null
regression_coverage: null
reference: null
```

### Error signature

Use a stable signature that lets repeated instances be grouped. Prefer, in order:

1. normalized application/test error code if one exists;
2. stable assertion/test name + normalized error;
3. normalized exception class + key message + file/module;
4. a short deterministic hash generated from normalized failure fields.

Remove volatile values such as timestamps, random IDs, absolute temporary paths and secrets before deriving a signature.

## Incident entry template

Copy this section for a new root cause. If the same root cause already exists, update its validation/history rather than duplicating it.

```markdown
## ERR-YYYY-NNN — Short title

**Status:** open | mitigated | resolved
**First seen:** YYYY-MM-DD
**Last seen:** YYYY-MM-DD
**Area:** theme | core | seo | schema | language | geo | performance | ci | integration | security
**Signature:** `stable-signature`
**Reference:** issue/PR/commit/run

### Symptom / context
What the user/CI/runtime observed. Include the smallest useful reproduction context.

### Root cause
Confirmed technical cause. If not confirmed, explicitly label the current hypothesis and evidence.

### Solution
What changed and why it addresses the cause rather than only the symptom.

### Validation
Exact focused gates/reproduction that passed after the fix.

### Prevention / guardrail
Rule, assertion, lint, architecture boundary, monitor or process change that reduces recurrence.

### Regression coverage
Test name/path or reason automated coverage is not practical.

### Notes/history
Only durable follow-up information.
```

## Current entries

## ERR-2026-001 — WP-CLI Docker command lost the `wp` executable

**Status:** resolved
**First seen:** 2026-09-15
**Last seen:** 2026-09-15
**Area:** ci / integration
**Signature:** `01a4c5b523c6`
**Reference:** PR #3; failing run `34911349884` / job `104199306024`; passing run `34911497605` / job `104199821991`

### Symptom / context

The first real WordPress activation smoke reached the WP-CLI installation step and failed before WordPress core installation. The runtime error was:

```text
/usr/local/bin/docker-entrypoint.sh: exec: line 11: core: not found
```

The structured diagnostic reduced the failure to `WordPress Smoke CI -> wordpress-runtime-smoke -> wp core install` rather than requiring manual review of the full Docker image-pull log.

### Root cause

Confirmed. The smoke helper invoked the `wordpress:cli-2.12.0-php8.2` image with arguments beginning at `core install`. Supplying explicit arguments to `docker run` replaces the image CMD, so the container entrypoint attempted to execute `core` as the binary. The WP-CLI executable itself (`wp`) was omitted.

### Solution

The `wp_cli()` helper now executes:

```text
wordpress:cli-2.12.0-php8.2 wp <subcommand> ...
```

while preserving the shared WordPress volume, Docker network, database environment and Debian WordPress UID mapping.

### Validation

Resolved by WordPress Smoke CI run `34911497605`, job `104199821991`. The corrected fixture completed WordPress installation, activated `seo-geo-core` and `seo-geo-theme`, resolved `language=native` and `seo-provider=native`, served frontend/admin requests and completed runtime diagnostics without PHP fatal errors, warnings, notices or uncaught errors.

### Prevention / guardrail

Keep all WP-CLI calls behind the single `wp_cli()` helper so image invocation semantics are defined once. The runtime smoke itself is the guardrail: a missing executable prefix fails before theme/plugin activation and emits a stable structured signature.

### Regression coverage

`scripts/ci/wordpress-smoke.sh` exercises `wp core install`, plugin activation, theme activation, service initialization and frontend/admin HTTP requests using the same helper.

### Notes/history

Do not infer Docker image CMD behavior from the logical command name. When `docker run IMAGE args...` supplies arguments explicitly, verify whether the image entrypoint expects a binary name or a subcommand.

## ERR-2026-002 — CI validator used invalid foreach destructuring

**Status:** resolved
**First seen:** 2026-09-15
**Last seen:** 2026-09-15
**Area:** ci / theme
**Signatures:** initial `fb38eec22c88`; recurrence `4d480edaa2d5`
**Reference:** PR #5 and PR #6; initial failing run `34914017559` / job `104207579105`; recurrence run `34915126783` / job `104210994598`

### Symptom / context

The first Phase 2A Design System CI run failed before the semantic-token/contrast contract executed. PHP lint reported:

```text
PHP Parse error: syntax error, unexpected token ")", expecting "->" or "?->" or "{" or "[" in scripts/ci/validate-design-system.php on line 233
```

The same root cause reappeared while introducing the Phase 2B pattern validator. This time the structured lint wrapper immediately reduced it to:

```text
file_line: scripts/ci/validate-patterns.php:125
error_signature: 4d480edaa2d5
```

The recurrence happened during development and was blocked by CI before merge.

### Root cause

Confirmed. Both validators attempted tuple destructuring with `array(...)`, for example:

```php
foreach ( $expected as $filename => array( $expected_slug, $expected_categories ) )
```

`array(...)` constructs an array; it is not valid foreach destructuring syntax. PHP foreach destructuring must use `list(...)` or square-bracket destructuring.

### Solution

Both loops use `list(...)`. `scripts/ci/php-lint-diagnostic.sh` wraps `php -l` so syntax failures report pipeline, run, job, step, command, file/line, expected/received and a deterministic signature.

After the PR #6 recurrence, the wrapper was strengthened with an explicit pre-lint guard for the repeated `foreach ... as array(` pattern. If it appears again in a linted CI PHP file, the diagnostic reports a **confirmed** root cause and instructs the developer to use `list(...)` or `[...]` before generic PHP parsing begins.

### Validation

Initial resolution:

- Design System CI run `34914490756`, job `104209041351`, passed wrapper syntax, PHP syntax, semantic-token validation and contrast calculations.

Recurrence resolution:

- Pattern Contract CI run `34915350804` passed all seven pattern PHP files and the reusable pattern contract after the `list(...)` fix;
- PHP Quality CI run `34915350787` passed WPCS and PHPStan level 6 after pattern header corrections;
- WordPress Smoke CI run `34915478691`, job `104212048598`, confirmed the real WordPress fixture with **7/7 theme patterns registered**.

### Prevention / guardrail

CI validators are production engineering code too. PHP validator syntax must go through `scripts/ci/php-lint-diagnostic.sh` rather than raw `php -l` workflow commands.

The wrapper now has two layers:

1. a targeted guard for the known invalid `foreach ... as array(` destructuring class;
2. generic PHP syntax linting for every other parse failure.

This does not claim developers can never type the same mistake; it ensures the known class cannot pass the validation boundary silently or reach merge.

### Regression coverage

- `.github/workflows/design-system.yml` lints `validate-design-system.php` through the wrapper before executing its contract;
- `.github/workflows/patterns.yml` lints `validate-patterns.php` and all seven theme pattern PHP files through the same wrapper;
- `scripts/ci/php-lint-diagnostic.sh` contains the targeted recurrence guard before invoking `php -l`.

### Notes/history

When destructuring tuples in PHP foreach loops, use `list(...)` or `[...]`; never use `array(...)` as a destructuring form. A recurrence should update this incident rather than create a duplicate root-cause entry.

## ERR-2026-003 — `wp eval-file` fixture failed with `strict_types`

**Status:** resolved
**First seen:** 2026-09-15
**Last seen:** 2026-09-15
**Area:** ci / integration
**Signature:** `730d68efe991`
**Reference:** PR #7; failing Accessibility & Responsive CI run `34917847965` / job `104219254704`; passing run `34918470902` / job `104221118872`

### Symptom / context

The first Phase 2C browser fixture installed WordPress and reached the acceptance-page seed step, then PHP aborted before any page could be created because `declare(strict_types=1)` was no longer the first statement in the evaluated code.

### Root cause

Confirmed. `wp eval-file` evaluates the target file inside code generated by WP-CLI. A `strict_types` declaration that is first in the source file is therefore not necessarily first in the resulting evaluated PHP compilation unit, which makes PHP reject it.

### Solution

`tests/fixtures/seed-acceptance.php` deliberately omits `declare(strict_types=1)` and documents why. The acceptance-only MU-plugin remains a normally loaded PHP file and keeps strict types.

### Validation

Accessibility & Responsive CI run `34918470902`, job `104221118872`, seeded both representative pages successfully and then completed all 24 Playwright/axe cases.

### Prevention / guardrail

Do not place `strict_types` declarations in repository scripts intended to be executed through `wp eval-file`. Runtime execution of the seed script is the authoritative guard because it exercises the same WP-CLI evaluation semantics used by CI.

### Regression coverage

`scripts/ci/browser-acceptance.sh` executes `wp eval-file /var/www/html/wp-content/seed-acceptance.php` on every accessibility/responsive gate before browser tests can begin.

## ERR-2026-004 — Empty Navigation fallback emitted invalid list structure

**Status:** resolved
**First seen:** 2026-09-15
**Last seen:** 2026-09-15
**Area:** theme / accessibility
**Signature:** `axe:list:.wp-block-navigation__container`
**Reference:** PR #7; failing Accessibility & Responsive CI run `34918100180` / job `104220004360`; passing run `34918470902` / job `104221118872`

### Symptom / context

The first complete browser run passed mobile semantic checks but axe reported a serious `list` violation at tablet/desktop widths. The rendered Navigation fallback contained a `ul.wp-block-navigation__container` whose direct child was another `ul.wp-block-page-list` rather than an `li`.

### Root cause

Confirmed for the WordPress 7.1 fixture. The block-theme header used an empty `core/navigation` block, so WordPress supplied its Page List fallback. In this fixture that fallback composition produced invalid list semantics for assistive technology.

### Solution

The base header no longer relies on the empty Navigation fallback. It uses a semantic native `nav` Group containing a direct `core/page-list` block. This keeps automatic page discovery, avoids custom JavaScript and produces a valid list hierarchy.

### Validation

Accessibility & Responsive CI run `34918470902` passed axe WCAG A/AA checks for EN and ES at 320, 768 and 1440 widths with zero accepted/suppressed accessibility violations.

### Prevention / guardrail

Do not assume a WordPress block fallback is semantically equivalent to explicitly composed markup. Representative templates must be rendered in real WordPress and scanned at responsive states where alternate/fallback markup can become visible.

### Regression coverage

`tests/browser/accessibility.spec.js` executes the axe scan against both language fixtures at all three viewports; the original `list` rule remains enabled.

## ERR-2026-005 — Skip-link fragment target was not focusable

**Status:** resolved
**First seen:** 2026-09-15
**Last seen:** 2026-09-15
**Area:** theme / accessibility
**Signature:** `browser:skip-link-main-focus`
**Reference:** PR #7; failing Accessibility & Responsive CI run `34918100180` / job `104220004360`; passing run `34918470902` / job `104221118872`

### Symptom / context

The WordPress block-template skip link became visible and navigated to the generated main-content fragment, but the main landmark did not receive DOM focus after keyboard activation. The same failure reproduced in EN and ES at 320, 768 and 1440 widths.

### Root cause

Confirmed. WordPress assigns the generated skip-link fragment ID to the first `main` landmark, but the starter's Group-rendered `<main>` was not itself a focusable target. Fragment navigation alone therefore did not satisfy the project's stronger keyboard-focus acceptance contract.

### Solution

The theme filters rendered `core/group` blocks whose `tagName` is `main` and uses `WP_HTML_Tag_Processor` to add `tabindex="-1"` when no tabindex already exists. This enables fragment/programmatic focus without adding the main landmark to normal sequential tab order and requires no frontend JavaScript.

### Validation

Accessibility & Responsive CI run `34918470902`, job `104221118872`, passed the skip-link, keyboard reachability and visible-focus test in all six language/viewport combinations. PHP Quality CI run `34918471012` also passed WPCS and PHPStan level 6 for the server-side implementation.

### Prevention / guardrail

A skip link is not considered accepted merely because the anchor exists or changes the URL fragment. The target must be verified with a real keyboard activation and focus assertion.

### Regression coverage

`tests/browser/accessibility.spec.js` tabs to `.skip-link`, activates it with Enter and requires the `main` landmark to be focused for EN and ES across the three browser viewports.
