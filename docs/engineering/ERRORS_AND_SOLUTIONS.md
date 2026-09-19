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

## ERR-2026-006 — Self-contained smoke hid invalid `wp eval` namespace escaping

**Status:** resolved
**First seen:** 2026-09-17
**Last seen:** 2026-09-19
**Area:** ci / integration
**Signatures:** original `423ffeca3a8d`; recurrence `6b757d95f440`; LocalBusiness recurrence `9505c31710a7`; crawler-admin recurrence `f9a2c20341e9`; discovery-cache recurrence `2eb2e705da21`
**Reference:** PR #13 original; PR #28 recurrence; PR #34 LocalBusiness recurrence; PR #47 crawler-admin recurrence; PR #52 discovery-cache recurrence; recurrence failing Self-contained Theme CI run `35310848537` / job `105492394718`; LocalBusiness failing run `35333080832` / job `105561616365`; crawler-admin failing run `35435237575` / job `105876662693`; discovery-cache failing run `35437520567` / job `105882631181`; passing PR #34 run `35333402980`; post-merge PR #34 passing run `35333717649`; corrected PR #47 run `35435342878` / job `105876960276`

### Symptom / context

The first zero-plugin acceptance successfully built the theme and confirmed the embedded `Runtime.php` file existed, but the runtime-origin assertion reported an empty value when it attempted to reflect `SeoGeo\Core\Runtime` through `wp eval`.

This initially looked like the theme bootstrap had failed to load the embedded runtime.

### Root cause

Confirmed. The shell command passed a PHP expression to `wp eval` with doubled namespace separators as if the PHP source itself needed shell-style escaping. Inside the already single-quoted shell argument, that produced an invalid PHP namespace expression. The helper also redirected stderr away, so the command failure was converted into an apparently valid empty observed value.

The product runtime and theme build were not the cause.

### Solution

The self-contained smoke now:

- passes a valid PHP expression using `\SeoGeo\Core\Runtime` as PHP source inside the single-quoted shell argument;
- checks the `wp eval` command exit status before interpreting stdout;
- captures stderr to dedicated files;
- emits structured `runtime-eval` or `authority-eval` diagnostics when evaluation itself fails.

The runtime-origin assertion remains strict: a successful evaluation must resolve to the embedded theme path.

### Validation

- PR Self-contained Theme CI run `35257079331` passed the complete zero-plugin acceptance after the test fix;
- PR #13 then passed all nine required gates;
- PR #13 was squash-merged as `a320cd3033e2a5ea0fbcc83dffac500a7eaf8c88`;
- post-merge Self-contained Theme CI run `35257573577` passed again on `main`.

The passing acceptance proves zero active plugins, no installed `seo-geo-core` plugin directory, runtime origin inside the theme bundle, native canonical/meta/robots behavior and clean PHP diagnostics.

### Prevention / guardrail

Never treat empty stdout from a critical `wp eval` command as application state until the command exit status has been checked. Critical WP-CLI eval probes must preserve stderr and route execution failures through the structured diagnostic contract.

When PHP source is already contained in a single-quoted shell argument, escape for valid PHP syntax, not for a second shell/parser layer that does not exist.

### Regression coverage

`scripts/ci/self-contained-theme-smoke.sh` contains explicit exit-status/stderr handling for both the ReflectionClass runtime-origin probe and the native-authority probe. `Self-contained Theme CI` runs that smoke against the built theme on every relevant PR and `main` push.

### Notes/history

The same escaping class recurred in Phase 5B when a new Organization-identity fixture called `wp eval` with doubled namespace separators. The smoke failed immediately with a PHP parse error and structured signature `6b757d95f440`; the production graph code was not changed. The fixture was corrected to pass `\SeoGeo\Core\Schema\SchemaIdentityResolver` as valid PHP source.

PR #28 then passed Self-contained Theme CI run `35310937921`, all eight PR gates, and post-merge Self-contained Theme CI run `35311162371`.

The same class recurred again in Phase 5E when the LocalBusiness fixture introduced three new `wp eval` expressions with doubled namespace separators. Self-contained Theme CI run `35333080832` failed with PHP parse error signature `9505c31710a7`. Only the test harness was changed; the LocalBusiness runtime remained untouched. The corrected fixture passed PR run `35333402980` and post-merge `main` run `35333717649`.

New `wp eval` probes must reuse the established escaping rule rather than re-derive shell/PHP escaping ad hoc.

Phase 6E reproduced the same root cause in two new single-quoted crawler-admin `wp eval` probes. Doubled namespace separators caused a PHP parse error and structured signature `f9a2c20341e9`; the product resolver/admin code was not changed. The probes were corrected to pass valid single-backslash PHP namespaces, and Self-contained Theme CI run `35435342878` / job `105876960276` passed the complete zero-plugin smoke.

Phase 6G reproduced the same root cause in the new discovery-cache revision helper. The helper used doubled namespace separators and also suppressed stderr, so the smoke observed an empty revision and emitted structured signature `2eb2e705da21` even though the runtime service was initialized. The helper was corrected to pass `\SeoGeo\Core\Runtime` as valid PHP source and preserve stderr in the fixture temp directory. No cache runtime behavior changed.

## ERR-2026-007 — `wp eval` breadcrumb fixture used a local query instead of WordPress's global query

**Status:** resolved
**First seen:** 2026-09-17
**Last seen:** 2026-09-17
**Area:** ci / integration
**Signature:** `2f37ceea3167`
**Reference:** PR #15; failing Self-contained Theme CI run `35261299294` / job `105337536013`; passing PR run `35261611652`; post-merge passing run `35262006328`

### Symptom / context

The first Phase 3C zero-plugin acceptance passed Open Graph assertions but failed the breadcrumb data assertion. The fixture created a `WP_Query` for the representative post inside `wp eval`, yet `BreadcrumbResolver` did not observe the request as singular and the Python contract check raised an `AssertionError`.

The resolver itself worked through normal HTTP requests; the mismatch existed only in the WP-CLI evaluation fixture.

### Root cause

Confirmed. The `wp eval` code assigned a local `$wp_query` variable. WordPress conditional functions such as `is_singular()` consult the global query object, so the resolver continued to see the WP-CLI command's original global query state rather than the fixture query.

### Solution

The acceptance probe now declares `global $wp_query` before assigning the fixture `WP_Query`. It then advances that query to the representative post before resolving breadcrumbs.

The failure path was also improved so a future breadcrumb-contract mismatch includes the raw JSON returned by the resolver instead of only a generic Python `AssertionError`.

### Validation

- Self-contained Theme CI run `35261611652` passed the full zero-plugin Phase 3C acceptance after the fixture correction;
- PR #15 passed all seven workflows triggered by the contract;
- PR #15 was squash-merged as `12a0b4a75b2980ad29211b474a6277ec30ea23ac`;
- post-merge Self-contained Theme CI run `35262006328` passed again on `main`.

The passing fixture proves the built theme resolves root + current-post breadcrumb data alongside native Open Graph, canonical, description and robots behavior with zero active plugins.

### Prevention / guardrail

When a `wp eval` acceptance probe depends on WordPress conditional tags or query globals, explicitly bind the real global query state or use a real HTTP request whose query lifecycle WordPress controls. Do not assume a local variable named `$wp_query` changes global request semantics.

Critical data-contract assertions should include the observed serialized payload in structured diagnostics so fixture-state problems can be distinguished from resolver bugs quickly.

### Regression coverage

`scripts/ci/self-contained-theme-smoke.sh` now sets `global $wp_query`, creates the representative singular query, advances it, resolves `Runtime::breadcrumbs()`, and validates the returned JSON shape. `Self-contained Theme CI` runs this path on every relevant PR and `main` push.

## ERR-2026-008 — Route locale switched but document `lang` stayed on the site language

**Status:** resolved
**First seen:** 2026-09-17
**Last seen:** 2026-09-18
**Area:** language / seo
**Signature:** `39e19e2128be`
**Reference:** PR #18; failing Native Multilingual CI run `35269785805` / job `105365895399`; passing PR run `35299698640`; post-merge passing run `35299884357`

### Symptom / context

The Phase 4B real-HTTP fixture proved that `/es/` matched the native prefixed rewrite and reached the page, but the rendered document did not expose `<html lang="es-ES">`. The structured acceptance failure was:

```text
primary_error: Spanish language root did not switch locale
expected: html lang=es-ES
received: expected attribute absent
```

### Root cause

Confirmed. The router switched the request locale during `parse_request`, which is sufficient for later locale-sensitive operations, but WordPress document language attributes have their own rendering path. Updating locale state alone did not guarantee that the final `language_attributes()` output reflected the validated prefixed route.

### Solution

`NativeLanguageRouter` now filters `language_attributes` from the same server-validated active route locale. It changes only `lang` / `xml:lang` and preserves WordPress-provided direction or other attributes. A query-string language value still cannot activate the router.

### Validation

- PHP Quality CI passed WPCS and PHPStan level 6 after the change;
- the PR routing smoke passed `/es/`, `/es/routing-fixture/` and `/en/routing-fixture/` language assertions in run `35299698640`;
- post-merge Native Multilingual CI run `35299884357` passed the same zero-plugin HTTP contract on `main`.

### Prevention / guardrail

Do not assume a WordPress locale switch automatically proves the final HTML language signal. Multilingual acceptance must verify the rendered `<html lang>` attribute over HTTP for every supported fixture language.

### Regression coverage

`scripts/ci/native-routing-smoke.sh` asserts `es-ES` and `en-US` document-language attributes on real prefixed routes and also asserts that query-string-only selection leaves the unprefixed language unchanged.

## ERR-2026-009 — Unknown language prefix was canonical-redirected instead of remaining 404

**Status:** resolved
**First seen:** 2026-09-17
**Last seen:** 2026-09-18
**Area:** language / seo
**Signature:** `735ae6352062`
**Reference:** PR #18; failing Native Multilingual CI run `35270128595` / job `105367216122`; passing PR run `35299698640`; post-merge passing run `35299884357`

### Symptom / context

The routing fixture expected an unconfigured language path such as `/fr/routing-fixture/` to remain unresolved. WordPress instead returned HTTP 301 because its canonical redirect machinery guessed an unprefixed resource.

The structured diagnostic was:

```text
primary_error: Unconfigured language prefix unexpectedly resolved
expected: 404
received: 301
```

### Root cause

Confirmed. The native router correctly generated rewrites only for configured languages, so the unknown prefix itself had no localized route. However, WordPress `redirect_canonical()` can attempt to repair unmatched URLs. Without a namespace guard, a language-shaped but unconfigured first path segment could therefore be redirected to unrelated unprefixed content.

### Solution

When native `routing=prefix` is active, language-shaped first path segments are treated as a reserved routing namespace. Canonical redirection is suppressed for:

- validated configured prefixed routes; and
- language-shaped prefixes that are not present in the validated configuration.

The latter remain on WordPress's normal unmatched-query path and therefore return 404. Non-language-shaped requests keep normal canonical redirect behavior.

### Validation

- final PR candidate `f6d4044018ced5e57b1d464815085dc6566ff6be` passed all eight gates;
- Native Multilingual CI run `35299698640` confirmed the unknown-prefix 404 contract;
- PR #18 was squash-merged as `ae799522e6ac27e2b77ae911d7160095e0ce2e7b`;
- post-merge Native Multilingual CI run `35299884357` passed the same contract again.

### Prevention / guardrail

A multilingual prefix system must test both configured and unconfigured language-shaped namespaces. Valid-route tests alone do not detect canonical guessing that can turn an intended 404 into a redirect.

### Regression coverage

`scripts/ci/native-routing-smoke.sh` requests `/fr/routing-fixture/` while only `es` and `en` are configured and requires HTTP 404.

## ERR-2026-010 — Translation discovery via global post-meta query violated the performance contract

**Status:** resolved  
**First seen:** 2026-09-18  
**Last seen:** 2026-09-18  
**Area:** language / performance / architecture  
**Signature:** `70735d839b0c`  
**Reference:** PR #21; failing PHP Quality CI run `35301569970` / job `105465100728`; passing PR PHP Quality run `35301709156`; passing PR Native Multilingual run `35301709161`; post-merge Native Multilingual run `35301930897`

### Symptom / context

The first Phase 4C1 registry implementation discovered translation-group members with a WordPress query filtered by `meta_key` and `meta_value`. WPCS blocked the implementation before PHPStan ran:

```text
Detected usage of meta_key, possible slow query.
WordPress.DB.SlowDBQuery.slow_db_query_meta_key
```

The same query also used `meta_value`, which generated the corresponding slow-query warning.

### Root cause

Confirmed. The proposed relationship model made group membership implicit in a global post-meta search. Even though the relationship itself was explicit, resolving one request could require searching the posts table/meta table for all objects sharing a group identifier.

That design was unnecessary for the product contract and created an avoidable scaling risk on large WordPress installations.

### Solution

The relationship contract was redesigned instead of suppressing WPCS.

Each translated resource now stores:

- its explicit translation group;
- its own configured language;
- the complete explicit language-to-resource-ID map for that relationship.

`NativeTranslationRegistry` validates only the IDs named in that map. Every mapped member must be published/public, declare the expected language and group, and expose the exact same normalized map. This makes reciprocity directly verifiable without a global meta discovery query.

The registry also rejects reused IDs, drafts/private resources, non-reciprocal maps and unconfigured languages.

### Validation

- final PR candidate `91fad9109456b72e622408ad6a3a44e8db436975` passed WPCS and PHPStan level 6 in run `35301709156`;
- Native Multilingual CI run `35301709161` passed 4A, 4B and the new zero-plugin 4C1 relationship acceptance;
- PR #21 passed all eight required gates;
- PR #21 was squash-merged as `bd5729ba6d785dff09d6176f0e92b2c45a5e0dba`;
- post-merge `main` passed all eight gates, including Native Multilingual CI `35301930897` and Performance Baseline CI `35301930882`.

### Prevention / guardrail

Do not discover native translation membership by scanning WordPress post meta at request time. The authoritative relationship must name its members directly and be validated by direct ID reads.

Performance-related WPCS warnings are treated as architecture feedback. They must not be silenced merely to pass CI when the data model can remove the slow-query class entirely.

### Regression coverage

`scripts/ci/native-translation-relations-smoke.sh` validates reciprocal ES/EN maps, draft rejection, non-reciprocal rejection, reused-resource rejection and unconfigured-language rejection on real WordPress with zero plugins.

PHP Quality CI retains `WordPress.DB.SlowDBQuery` checks, so reintroducing request-time translation discovery through `meta_key/meta_value` queries will fail the quality gate.

## ERR-2026-011 — WP-CLI fixture post had no real author identity

**Status:** resolved  
**First seen:** 2026-09-18  
**Last seen:** 2026-09-18  
**Area:** ci / schema / integration  
**Signature:** `0f1723aecd34`  
**Reference:** PR #30; failing Self-contained Theme CI run `35315248701` / job `105505385494`; passing PR run `35315353803`; post-merge passing run `35315585378`

### Symptom / context

The first Phase 5C self-contained Schema fixture expected a built-in WordPress post to emit WebSite + WebPage + BlogPosting + Person. The real graph correctly emitted WebSite + WebPage + BlogPosting, but omitted the article author and Person node.

The captured graph showed valid BlogPosting headline, dates, canonical linkage and language. Only the author identity was absent.

### Root cause

Confirmed. The fixture created its representative post with `wp post create` but did not set `--post_author`. In that WP-CLI fixture context the post was stored with `post_author=0`.

The production resolver intentionally requires a real WordPress user before emitting an author relationship. Resolving user ID 0 therefore returned no identity, and the graph correctly omitted both `BlogPosting.author` and the Person node instead of fabricating an author.

### Solution

The fixture now assigns the already existing WordPress administrator explicitly with `--post_author=1`.

No production Schema code was changed for this failure. The safe omission behavior remains part of the product contract.

### Validation

- PR #30 Self-contained Theme CI run `35315353803` passed the full zero-plugin BlogPosting graph after the fixture correction;
- PR #30 passed all eight required gates;
- PR #30 was squash-merged as `744be92a3d69bad1a4711291c6be162273cd416f`;
- post-merge Self-contained Theme CI run `35315585378` passed the same BlogPosting author/publisher contract on `main`;
- post-merge PHP Quality, Native Multilingual, Accessibility and Performance gates also passed.

### Prevention / guardrail

Acceptance fixtures that verify author identity must assign a real WordPress user explicitly. Do not assume WP-CLI content creation implicitly assigns the site administrator or another valid author.

Production identity resolvers must continue to omit optional author/person relationships when the source identity is absent or invalid rather than inventing an entity to satisfy a test.

### Regression coverage

`scripts/ci/self-contained-theme-smoke.sh` creates the representative BlogPosting with an explicit real author and asserts that the resulting Person uses the stable public author profile ID. A second authored fixture also verifies that BlogPosting author and explicit Organization publisher references coexist in the same native graph.

## ERR-2026-012 — Exact Schema fixture node counts drifted after adding BreadcrumbList

**Status:** resolved  
**First seen:** 2026-09-18  
**Last seen:** 2026-09-18  
**Area:** ci / schema / regression  
**Signatures:** generic graph-contract recurrence `0f1723aecd34`; authored-article contract `35d3d62a7606`  
**Reference:** PR #32; failing Self-contained Theme CI runs `35329315417` / job `105549712658` and `35329476405` / job `105550233437`; passing PR run `35329672629`; post-merge passing run `35329965975`

### Symptom / context

Phase 5D correctly added one BreadcrumbList node to eligible Schema graphs, but two exact-count assertions in the self-contained acceptance remained on their pre-5D values.

The production graph captured by CI was structurally correct: WebPage referenced the new stable BreadcrumbList node and the ListItems matched the existing breadcrumb data authority. The failures came from stale fixture expectations.

The first failure also reused the generic signature `0f1723aecd34`, previously seen for a different Schema fixture issue, because the structured diagnostic used the same broad failure code and primary message.

### Root cause

Confirmed. While updating several graph fixtures, a broad text replacement changed one count incorrectly and missed another fixture-specific count. Exact node counts are intentional regression guards, but every graph shape must be updated independently when a new shared node is introduced.

Production BreadcrumbList logic was not the cause.

### Solution

The acceptance was corrected per fixture:

- ordinary authored post: five nodes, including BreadcrumbList;
- front page: no one-item BreadcrumbList is fabricated;
- author ProfilePage: four nodes, including BreadcrumbList;
- authored post with explicit Organization: six nodes, including BreadcrumbList.

The representative post also asserts the BreadcrumbList ID, WebPage reference, ordered positions, visible labels and public URLs. The generic representative failure code was narrowed to `schema-breadcrumb-graph-contract` so future failures are easier to distinguish.

### Validation

- final PR candidate `a6d410815b825386238b54573ac222c14fae953e` passed all eight required gates;
- PR #32 Self-contained Theme CI run `35329672629` passed the complete zero-plugin Schema graph acceptance;
- PR #32 was squash-merged as `51cfffb584d6fbf505ab08a7bf86eca95174e107`;
- post-merge Self-contained Theme CI run `35329965975` passed the same contract on `main`;
- post-merge PHP Quality, Native Multilingual, Accessibility and Performance gates also passed.

### Prevention / guardrail

When a shared Schema node is added, review every fixture that asserts an exact graph size. Do not use global search-and-replace for fixture-specific counts.

Keep exact counts because they detect duplicate nodes, but pair them with type/reference assertions so a failure immediately shows which graph relationship changed.

Structured error signatures should use sufficiently specific failure codes or primary messages; a generic graph-contract signature can collide across unrelated fixture failures.

### Regression coverage

`scripts/ci/self-contained-theme-smoke.sh` now covers the updated graph sizes and explicitly validates the representative BreadcrumbList contents plus the front-page negative case.

## ERR-2026-013 — WordPress robots filter parameter reused the reserved keyword `public`

**Status:** resolved  
**First seen:** 2026-09-18  
**Last seen:** 2026-09-18  
**Area:** ci / geo / code quality  
**Signature:** `27da5fd524a3`  
**Reference:** PR #39; failing PHP Quality CI run `35350266853` / job `105616456784`; passing PR PHP Quality run `35350353447`; post-merge passing PHP Quality run `35350709657`

### Symptom / context

The first Phase 6A crawler-policy candidate compiled and its zero-plugin robots acceptance was functionally correct, but WPCS stopped PHP Quality before PHPStan because the `robots_txt` filter callback named its second parameter `$public`.

The structured diagnostic was:

```text
It is recommended not to use reserved keyword "public" as function parameter name.
```

### Root cause

Confirmed. WordPress documentation commonly describes the second `robots_txt` filter argument as the site's public visibility value. The implementation mirrored that vocabulary directly as `$public`, but the project's coding-standard rules reject reserved PHP keywords as parameter names.

There was no crawler-policy logic failure.

### Solution

The callback parameter and its PHPDoc entry were renamed to `$site_public`.

No behavior, option schema, robots directive or privacy rule changed.

### Validation

- WPCS passed on final PR candidate `47216b37bfcbd692a3f2733e56df43eb2d12a272`;
- PHPStan level 6 passed in PR run `35350353447`;
- Self-contained Theme CI run `35350353324` passed the full zero-plugin crawler-policy acceptance;
- PR #39 passed all ten workflows and was squash-merged as `da1c98d89771c34d0c9ad86e869b86fa4dab7ebb`;
- post-merge PHP Quality CI `35350709657` and Self-contained Theme CI `35350709442` passed again on `main`.

### Prevention / guardrail

When implementing WordPress hooks whose documented argument labels overlap PHP reserved words, use descriptive local names such as `$site_public`, `$visibility` or another domain-specific variant instead of copying the documentation's generic label verbatim.

Do not suppress the naming sniff for this class of issue; a behavior-preserving rename is the correct fix.

### Regression coverage

PHP Quality CI keeps the reserved-keyword naming rule enabled. The zero-plugin self-contained smoke separately proves that the renamed callback still preserves WordPress privacy precedence and independent OAI-SearchBot/GPTBot behavior.

## ERR-2026-014 — New GEO classes failed WPCS because PHPDoc contracts were incomplete

**Status:** resolved  
**First seen:** 2026-09-18  
**Last seen:** 2026-09-18  
**Area:** ci / geo / code quality  
**Signatures:** `7427c299bab5`, `929d5def865f`  
**Reference:** PR #41; failing PHP Quality CI runs `35352905073` / job `105625085830` and `35353029100` / job `105625556642`; passing PHP Quality run `35353137444`; post-merge passing PHP Quality run `35353585197`

### Symptom / context

The first Phase 6B `llms.txt` implementation was functionally accepted by WordPress-oriented smoke work, but PHP Quality stopped at WPCS before PHPStan.

The first candidate lacked the full member/method PHPDoc required by the repository standard. After completing those comments, a second candidate exposed two narrower formatting rules: a short description beginning with lowercase `llms.txt` and one misaligned `@param` type column.

### Root cause

Confirmed. The new classes were created with concise implementation comments rather than the repository's stricter WordPress PHPDoc contract. The product logic was not the cause.

### Solution

Both LLMS classes were brought to the same documentation standard as the rest of Core:

- member properties document their authority role and type;
- constructors and public/private methods have short descriptions;
- parameters and return shapes are documented where required;
- the presenter short description begins with an uppercase token (`LLMS.txt`);
- parameter columns follow WPCS alignment.

No endpoint behavior, privacy rule, URL policy or generated Markdown changed.

### Validation

- final candidate `55cd18815e5514709c263dd73ae3bc554e5764d3` passed WPCS and PHPStan level 6 in PHP Quality CI `35353137444`;
- Self-contained Theme CI `35353137404` passed the full zero-plugin `llms.txt` privacy/leakage contract;
- Native Multilingual CI `35353137344` passed localized `llms.txt` URL acceptance;
- PR #41 passed all ten workflows and was squash-merged as `27e4b5a1fb657cbe6ea1dbfb71edd6aa448c56bf`;
- post-merge PHP Quality CI `35353585197`, Self-contained Theme CI `35353585261` and Native Multilingual CI `35353585306` passed again on `main`.

### Prevention / guardrail

New Core classes should start with the repository's full WordPress PHPDoc shape rather than treating comments as a cleanup step after implementation.

For acronyms or protocol/file names that normally begin lowercase, use a capitalized prose token in short descriptions when WPCS requires the first word to begin with a capital letter.

Do not suppress these documentation sniffs; they keep public/internal authority boundaries explicit and make later maintenance safer.

### Regression coverage

PHP Quality CI keeps WPCS and PHPStan level 6 mandatory. Foundation CI also requires the LLMS resolver/presenter and public contract, while functional behavior remains separately covered by the self-contained and multilingual acceptance suites.

## ERR-2026-015 — Markdown URL prefix caused a false HTML-link absence failure

**Status:** resolved  
**First seen:** 2026-09-18  
**Last seen:** 2026-09-18  
**Area:** ci / geo / multilingual / regression  
**Signature:** `cdbc6628425d`  
**Reference:** PR #43; failing Native Multilingual CI localized SEO acceptance; final passing Native Multilingual CI `35387083876`; post-merge passing Native Multilingual CI `35387364460`

### Symptom / context

Phase 6C changed curated `llms.txt` entries from authoritative localized HTML URLs to their Markdown alternates, for example:

```text
https://example.test/es/servicios/seo-tecnico/
https://example.test/es/servicios/seo-tecnico/index.md
```

The runtime produced the expected Markdown URL, but the localized acceptance failed with:

```text
llms.txt did not prefer authoritative localized Markdown URLs
```

### Root cause

Confirmed. The fixture used a broad negative assertion equivalent to:

```text
HTML_URL not in body
```

That assertion is invalid for directory-style Markdown alternates because the HTML URL is naturally a string prefix of the correct `index.md` URL.

The product output was correct. The test compared substrings when the contract concerned complete Markdown list-item URLs.

### Solution

The fixture now checks the exact HTML list-item form rather than banning the HTML URL as an arbitrary substring.

It asserts that:

- the exact ES Markdown list item exists;
- the exact EN Markdown list item exists;
- the exact ES HTML list item does not exist;
- the exact EN HTML list item does not exist;
- the staged unprefixed URL does not appear.

No runtime URL generation, multilingual authority or llms behavior changed.

### Validation

- final PR candidate `27a9248e41f1fc379e1913bd951cd895db03d5df` passed Native Multilingual CI `35387083876`;
- the same candidate passed PHP Quality, Self-contained Theme, Accessibility and Performance;
- PR #43 passed all ten workflows and was squash-merged as `173793586a3a75ee4f0819e17485460bf91e47d2`;
- post-merge Native Multilingual CI `35387364460` passed the exact same ES/EN Markdown authority contract;
- all ten post-merge workflows passed.

### Prevention / guardrail

When one valid URL is structurally derived by extending another URL, do not use raw substring absence as evidence that the shorter representation was not emitted.

For generated documents, compare complete semantic units such as:

- the exact Markdown list item;
- the exact HTML attribute;
- a parsed URL field;
- a structured JSON property.

Prefer parsing or exact-line assertions whenever one representation can legitimately contain another as a prefix.

### Regression coverage

`scripts/ci/native-localized-seo-smoke.sh` now validates exact localized Markdown and HTML list-item forms separately while also asserting that unprefixed staged URLs remain absent.

## ERR-2026-016 — Provenance additions disturbed WPCS alignment without changing behavior

**Status:** resolved  
**First seen:** 2026-09-19  
**Last seen:** 2026-09-19  
**Area:** ci / geo / code quality  
**Signature:** `3357a651e6fe`  
**Reference:** PR #45; failing PHP Quality CI `35422186475` / job `105841778598`; passing final PHP Quality CI `35422241379`; post-merge passing PHP Quality CI `35422365806`

### Symptom / context

The first Phase 6D candidate passed the zero-plugin provenance behavior but PHP Quality stopped at WPCS before PHPStan.

The reported findings were limited to:

- PHPDoc type/variable column alignment in `ContentProvenanceResolver`;
- PHPDoc alignment in `ContentProvenancePresenter`;
- one assignment alignment in Markdown provenance output;
- existing contiguous assignment alignment in `Runtime.php` after inserting the longer `content_provenance` property assignment.

The structured failure signature was `3357a651e6fe`.

### Root cause

Confirmed. The provenance feature introduced longer names into regions governed by strict WordPress Coding Standards alignment.

In particular, adding the long runtime assignment into an existing visually aligned assignment group forced WPCS to expect different spacing on unrelated neighboring assignments.

The product behavior was not the cause: the first candidate's Self-contained Theme smoke completed successfully.

### Solution

The fix was formatting-only:

- align PHPDoc parameter columns exactly;
- align the Markdown publisher assignment with its neighboring assignment;
- move the provenance runtime assignment into its own logical paragraph instead of widening an established assignment group.

No author identity, date, canonical source, Open Graph, Schema, Markdown or publisher behavior changed.

### Validation

- final PR candidate `660a7bb67019d6eb5ef05c4e28430e9d7fc46ba5` passed WPCS and PHPStan level 6 in PHP Quality CI `35422241379`;
- Self-contained Theme CI `35422241338` passed the cross-surface author/date/source consistency contract;
- all ten PR workflows passed and PR #45 was squash-merged as `4ee0c956ff1b0946ffad944c69779434e5cd7df1`;
- post-merge PHP Quality CI `35422365806`, Self-contained Theme CI `35422365830`, Native Multilingual CI `35422365725` and the remaining gates all passed on `main`.

### Prevention / guardrail

When adding a substantially longer variable/property name to a WPCS-aligned assignment block, prefer one of two approaches:

- deliberately realign the complete logical group in the same change; or
- isolate the new assignment with a blank line when it represents a separate authority/lifecycle step.

New PHPDoc blocks should be aligned before the first CI candidate rather than treated as cleanup.

Do not weaken or suppress the alignment checks; they continue to catch drift consistently across Core.

### Regression coverage

PHP Quality CI keeps WPCS and PHPStan level 6 mandatory. The provenance behavior itself is independently protected by the zero-plugin cross-surface acceptance so formatting-only fixes cannot hide a functional regression.



## ERR-2026-017 — Bash expanded embedded PHP variables under set -u

**Status:** resolved  
**First seen:** 2026-09-19  
**Last seen:** 2026-09-19  
**Area:** ci / shell / WordPress smoke  
**Signature:** `bd06327f2ae5`  
**Reference:** PR #49; failing Self-contained Theme CI `35436437082` / job `105879779536`; passing final Self-contained Theme CI `35436517496`; post-merge passing Self-contained Theme CI `35436653739`

### Symptom / context

The first Phase 6F candidate completed the HTTP privacy matrix successfully for direct HTML, native sitemap, feed, search, author archive, unauthenticated REST, llms.txt and Markdown. It failed only when entering the final direct resolver check.

The shell reported:

```text
scripts/ci/discovery-privacy-acceptance.sh: line 167: provenance: unbound variable
```

Because the smoke runs with `set -u`, the shell interpreted PHP variables such as `$provenance` and `$markdown` inside a double-quoted `wp eval` program as shell variables before WP-CLI received the PHP code.

### Root cause

Confirmed. This was a test-harness quoting defect, not a product privacy failure.

The embedded PHP program required shell interpolation only for the numeric fixture IDs. The PHP local variables were not escaped, so Bash attempted to expand them first.

### Solution

Escape PHP-local dollar signs inside the double-quoted `wp eval` argument while leaving the intended shell fixture-ID substitutions active.

No resolver, indexability, Schema, llms.txt, Markdown, provenance or WordPress query behavior changed.

### Validation

- final PR candidate `99c01be435b0098482ff50ea771957eb7a8bee41` passed Self-contained Theme CI `35436517496`;
- all ten PR #49 workflows passed;
- PR #49 was squash-merged as `feb3f50542e5e56da27d915b1a4e6efe3d73c115`;
- post-merge Self-contained Theme CI `35436653739` passed the same complete privacy matrix;
- all ten post-merge workflows passed.

### Prevention / guardrail

For PHP snippets embedded in shell:

- prefer single-quoted shell strings when no shell interpolation is required;
- when fixture values must be interpolated, escape every PHP-local `$` explicitly;
- keep `set -u` enabled so accidental shell expansion fails immediately;
- preserve the first failing structured diagnostic instead of weakening the assertion.

This is related to earlier WP-CLI harness quoting lessons but has a distinct failure mechanism: shell variable expansion rather than PHP namespace escaping.

### Regression coverage

`scripts/ci/discovery-privacy-acceptance.sh` retains the resolver-level provenance/Markdown guard under `set -u`, and Self-contained Theme CI executes it inside the real built-theme WordPress fixture.

## ERR-2026-018 — Conditional ETag header input was not sanitized for WPCS

**Status:** resolved  
**First seen:** 2026-09-19  
**Last seen:** 2026-09-19  
**Area:** ci / geo / cache / security  
**Signature:** `1834ed38c614`  
**Reference:** PR #52; failing PHP Quality CI `35437487947` / job `105882519913`; passing final PHP Quality CI `35437874378`; post-merge passing PHP Quality CI `35438013287`

### Symptom / context

The first Phase 6G cache-revalidation candidate reached PHP Quality but WPCS stopped before PHPStan on `DiscoveryCachePolicy.php`.

The only reported finding was direct use of:

```php
$_SERVER['HTTP_IF_NONE_MATCH']
```

with `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`.

### Root cause

Confirmed. The conditional-request parser validated type and bounded header length, but the raw server value was only passed through `wp_unslash()`. That was insufficient for the repository's WordPress security standard.

The cache design, ETag generation and invalidation model were not the cause.

### Solution

Normalize the request header through:

```php
sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) )
```

before length checks and token parsing.

No WPCS suppression was added, and no ETag matching semantics were weakened.

### Validation

- final PR candidate `42c85544ca535d617b052b9f1dc94bf9309a8c6e` passed PHP Quality CI `35437874378`, including WPCS and PHPStan level 6;
- Self-contained Theme CI `35437874331` passed the GET/HEAD ETag/304 and invalidation acceptance;
- PR #52 passed all ten workflows and was squash-merged as `d7d79dd3f6fdd235ee891167fcc2a2814dba3705`;
- post-merge PHP Quality CI `35438013287` passed again.

### Prevention / guardrail

Treat HTTP headers read from `$_SERVER` as request input even when the expected grammar is narrow.

The required sequence is:

- confirm the value exists and is a string;
- `wp_unslash()`;
- sanitize for the intended text grammar;
- enforce a reasonable length bound;
- parse tokens conservatively.

Do not suppress WordPress security sniffs for conditional-request headers.

### Regression coverage

PHP Quality CI keeps WPCS mandatory, while Self-contained Theme CI exercises `If-None-Match` against the built theme and requires correct 304 behavior.

## ERR-2026-019 — Cache-Control acceptance incorrectly required one textual directive order

**Status:** resolved  
**First seen:** 2026-09-19  
**Last seen:** 2026-09-19  
**Area:** ci / geo / cache / HTTP semantics  
**Signature:** `2c351b882155`  
**Reference:** PR #52; failing Self-contained Theme CI `35437764396` / job `105883264970`; passing final Self-contained Theme CI `35437874331`; post-merge passing Self-contained Theme CI `35438013353`

### Symptom / context

After the runtime namespace and WPCS issues were corrected, Phase 6G reached the real llms.txt revalidation check but the smoke reported:

```text
Discovery response missed the conservative public revalidation policy
```

The first assertion required the complete `Cache-Control` line to equal one exact text sequence:

```text
public, no-cache, must-revalidate, max-age=0
```

### Root cause

Confirmed. The acceptance encoded header serialization order as part of the contract even though HTTP cache directives are semantic tokens and their order is not authoritative.

This made the test more brittle than the product contract and produced a failure without identifying the actual received header value.

### Solution

Parse all returned `Cache-Control` header values and validate the semantic policy instead of one exact serialization.

The acceptance now requires all of:

- `public`;
- `no-cache`;
- `must-revalidate`;
- `max-age=0`.

It also rejects `private` and `no-store`, because either would contradict the intended shared-cache revalidation model.

Failure diagnostics now include the actual received Cache-Control value.

### Validation

- final PR candidate `42c85544ca535d617b052b9f1dc94bf9309a8c6e` passed Self-contained Theme CI `35437874331`;
- the same run proved llms.txt and Markdown 200/304 revalidation, stale-validator rejection after mutations, author/provenance refresh and translation-meta invalidation;
- all ten PR workflows passed;
- PR #52 was squash-merged as `d7d79dd3f6fdd235ee891167fcc2a2814dba3705`;
- post-merge Self-contained Theme CI `35438013353` passed the same semantic cache contract.

### Prevention / guardrail

For structured HTTP headers, test protocol semantics rather than incidental serialization when the specification does not define ordering.

Acceptance should:

- require mandatory directives/tokens;
- reject explicitly incompatible directives;
- expose the received value in diagnostics;
- avoid weakening the contract merely to accept arbitrary server behavior.

### Regression coverage

`scripts/ci/discovery-cache-acceptance.sh` performs semantic Cache-Control checks for both llms.txt and Markdown, together with ETag and conditional-request acceptance inside the real built-theme WordPress fixture.

## ERR-2026-020 — Preset registry first candidate missed WPCS and PHPStan contract details

**Status:** resolved  
**First seen:** 2026-09-19  
**Last seen:** 2026-09-19  
**Area:** ci / presets / PHP quality  
**Signatures:** `76036c2fa168`, `2bebd3dc2f85`  
**Reference:** PR #54; failing PHP Quality CI `35439483616` / job `105887709773`; failing PHP Quality CI `35439526503` / job `105887820722`; passing final PHP Quality CI `35440066742`; post-merge passing PHP Quality CI `35446033930`

### Symptom / context

The first Corporate preset registry candidate was functionally complete enough for Foundation and packaging checks, but PHP Quality stopped on two successive code-quality defects in `packages/seo-geo-theme/inc/presets.php`.

WPCS first reported missing parameter documentation plus assignment-alignment warnings. After those were corrected, PHPStan level 6 reported that an `is_string()` check around the WordPress locale result was always true according to the WordPress stubs.

### Root cause

Confirmed. The implementation logic was not the cause.

The first issue came from adding new public helper functions without completing the repository's strict PHPDoc/alignment conventions before the first CI candidate. The second came from defensive runtime code that contradicted the authoritative WordPress stub type: `get_locale()` / the locale resolver already returns a string in the supported contract.

### Solution

- add complete `@param` documentation for the preset document/localized-value helpers;
- align assignment groups according to WPCS rather than suppressing the sniff;
- remove the redundant string-type guard and rely on the supported WordPress type contract;
- keep both WPCS and PHPStan unchanged at their existing strictness.

No preset activation, content-map, Schema, multilingual or pattern behavior was weakened.

### Validation

- final PR candidate `68a6c2161baffa8784d20a5a1663520a12ee379a` passed PHP Quality CI `35440066742`, including WPCS and PHPStan level 6;
- PR #54 passed all ten workflows and was squash-merged as `7f3499073d82fd362f88c6f3abd6949c52223057`;
- post-merge PHP Quality CI `35446033930` passed again on `main`;
- all ten post-merge workflows passed.

### Prevention / guardrail

For new theme-owned PHP registry/helpers:

- write complete WordPress-style PHPDoc before the first candidate;
- keep adjacent assignment alignment valid when introducing longer variable names;
- consult the pinned WordPress stubs before adding defensive type checks around core APIs;
- do not lower PHPStan certainty or add ignored errors to accommodate redundant checks.

### Regression coverage

`PHP Quality CI` remains mandatory for preset-runtime PHP changes and continues to run WPCS before PHPStan level 6.

## ERR-2026-021 — Corporate locale acceptance assumed unsupported WP-CLI locale behavior

**Status:** resolved  
**First seen:** 2026-09-19  
**Last seen:** 2026-09-19  
**Area:** ci / presets / multilingual / WP-CLI  
**Signatures:** `1790b2c0f575`, `0272e01cd26f`  
**Reference:** PR #54; failing Self-contained Theme CI `35439578232`, `35439683917`, `35439784948`, `35439874380`; passing final Self-contained Theme CI `35440066944`; post-merge passing Self-contained Theme CI `35446033925`

### Symptom / context

Corporate EN registration passed immediately, but the first Spanish acceptance continued to receive the English title:

`Corporate verified metrics`

instead of:

`Métricas corporativas verificadas`.

The initial probe changed `WPLANG` after WordPress had already bootstrapped. A later attempt used a `--locale=es_ES` WP-CLI argument, but the pinned `wordpress:cli-2.12.0-php8.2` runtime reported:

`unknown --locale parameter`.

### Root cause

Confirmed. This was an acceptance-harness assumption, not missing Spanish preset content.

Preset patterns are registered during WordPress bootstrap. Changing the site-language option after bootstrap does not retroactively re-register them. In addition, the pinned WP-CLI container used by the project does not expose the assumed global `--locale` flag.

A plain `switch_to_locale()` was also insufficient for this specific fixture because the site-locale resolution used by the preset registry needed to be forced consistently before re-registration.

### Solution

Keep the runtime authority on WordPress's site locale and test the alternate locale using WordPress APIs inside the same process:

- add a high-priority temporary `locale` filter returning `es_ES`;
- call `switch_to_locale( 'es_ES' )`;
- unregister the three Corporate patterns already registered during bootstrap;
- call the theme's preset registration function again;
- assert the Spanish title from the real WordPress block-pattern registry.

The test no longer depends on unsupported WP-CLI flags or post-bootstrap `WPLANG` mutation.

### Validation

- final PR candidate `68a6c2161baffa8784d20a5a1663520a12ee379a` passed Self-contained Theme CI `35440066944`;
- that run proved default-off behavior, Corporate activation, English copy, Spanish copy and unsupported preset fallback inside the built zero-plugin theme;
- PR #54 passed all ten workflows and was squash-merged as `7f3499073d82fd362f88c6f3abd6949c52223057`;
- post-merge Self-contained Theme CI `35446033925` passed the same contract on `main`;
- post-merge Native Multilingual CI `35446033952` also passed.

### Prevention / guardrail

For locale-sensitive WordPress acceptance:

- distinguish site locale, user/admin locale and process bootstrap timing;
- do not assume a WP-CLI global flag exists without verifying it against the pinned CLI runtime;
- when behavior is registered during bootstrap, change locale and re-register the specific behavior inside the same process rather than mutating an option after registration;
- prefer WordPress locale APIs over CLI-specific behavior for product-contract tests.

### Regression coverage

`scripts/ci/corporate-preset-acceptance.sh` retains the explicit EN/ES block-pattern registry checks inside Self-contained Theme CI. Future presets can reuse the same WordPress-native locale strategy instead of rediscovering WP-CLI-specific behavior.



## ERR-2026-022 — Global roadmap/governance docs over-triggered every specialized CI gate

**Status:** resolved; docs-only trigger proof in progress  
**First seen:** 2026-09-19  
**Last seen:** 2026-09-19  
**Area:** ci / workflow scope / runner efficiency  
**Signature:** `f18d0ada89a6`  
**Reference:** Phase 7 closure PR #61, docs-only head `c8a70af6af25222a8458eac8e5aae5bfe36ebb57`; unnecessary Accessibility run `35456600148`, Native Multilingual run `35456600052`, Self-contained Theme run `35456600053`, Performance Baseline run `35456600037`

### Symptom / context

PR #61 changed only documentation to close Phase 7, but GitHub Actions launched the complete ten-workflow matrix, including browser accessibility, Lighthouse performance, native multilingual runtime acceptance, package/smoke and the full self-contained WordPress fixture.

All jobs passed, but the execution contradicted the repository's **minimum sufficient validation** rule and consumed runner time for contracts that had not changed.

### Root cause

Confirmed. `docs/ROADMAP.md` was listed in the `paths:` trigger of almost every specialized workflow. `docs/CI_QUALITY_GATES.md` also directly triggered PHP Quality.

Because Phase closure/evidence commits necessarily update the roadmap and CI evidence documentation, routine docs-only closure work was treated as if theme/runtime/browser/performance code had changed.

The engineering rule described path-aware validation, but workflow configuration did not enforce the distinction between:

- global governance/status/evidence documents; and
- specialized technical contract documents owned by one gate.

### Solution

Make global governance/status documents **Foundation-only triggers**.

The fix:

- removes `docs/ROADMAP.md` from Accessibility, Design System, Native Multilingual, Pattern, Performance, Phase 1 Package, PHP Quality, Self-contained Theme and WordPress Smoke workflow path filters;
- removes `docs/CI_QUALITY_GATES.md` from PHP Quality path filters;
- retains specialized documentation triggers such as accessibility/performance/multilingual docs on the gates that actually own those contracts;
- keeps Foundation unconditional for pull requests and pushes to `main`;
- adds `scripts/ci/validate-ci-path-scope.sh` and executes it from Foundation;
- protects `ROADMAP.md`, `CI_QUALITY_GATES.md`, `GLOBAL_ENGINEERING_RULES.md` and `ERRORS_AND_SOLUTIONS.md` from being reintroduced into specialized workflow triggers.

A documentation change that genuinely changes executable behavior must ship with the matching code/test/workflow change; that executable change is what activates the relevant specialized gates.

### Validation

- fix PR #62 changed the specialized workflow definitions and therefore correctly ran the complete matrix; all ten PR workflows passed;
- PR #62 was squash-merged as `6e8d711ce32e2fba7d582c5b4196c48a24adbcc7`;
- all ten post-merge workflows passed again on that merge, including Foundation `35457685757`, Self-contained Theme `35457685761`, Native Multilingual `35457685751`, Accessibility & Responsive `35457685785` and Performance Baseline `35457685763`;
- this follow-up branch intentionally changes only this protected global documentation file. Its pull request is the regression proof: only Foundation should be created.

### Prevention / guardrail

- Global roadmap/governance/status/evidence documents are Foundation-only.
- Specialized workflows watch executable paths plus only their own authoritative technical documentation.
- Do not add a global phase/status document to a heavyweight workflow as a convenient way to make phase closures "re-run everything".
- `scripts/ci/validate-ci-path-scope.sh` fails Foundation if protected global docs appear in a specialized workflow trigger.
- Runner cost and queue pressure are part of CI correctness, not merely an operational concern.

### Regression coverage

Foundation executes `scripts/ci/validate-ci-path-scope.sh` on every pull request and push to `main`. The validator checks every specialized workflow and rejects protected global-doc triggers while also ensuring Foundation remains unconditional.
