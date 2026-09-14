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

No material incidents recorded yet.
