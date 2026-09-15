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

**Status:** mitigated; validation pending
**First seen:** 2026-09-15
**Last seen:** 2026-09-15
**Area:** ci / integration
**Signature:** `01a4c5b523c6`
**Reference:** PR #3; WordPress Smoke CI run `34911349884`; job `104199306024`

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

Pending focused rerun of WordPress Smoke CI on PR #3. This entry must be changed to `resolved` only after the same installation/activation path passes.

### Prevention / guardrail

Keep all WP-CLI calls behind the single `wp_cli()` helper so image invocation semantics are defined once. The runtime smoke itself is the guardrail: a missing executable prefix fails before theme/plugin activation and emits a stable structured signature.

### Regression coverage

`scripts/ci/wordpress-smoke.sh` exercises `wp core install`, plugin activation, theme activation, service initialization and frontend/admin HTTP requests using the same helper.

### Notes/history

Do not infer Docker image CMD behavior from the logical command name. When `docker run IMAGE args...` supplies arguments explicitly, verify whether the image entrypoint expects a binary name or a subcommand.
