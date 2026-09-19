# Global Engineering Rules

These rules are mandatory for this repository. They inherit the Kairoseth engineering discipline and are adapted to a reusable WordPress foundation.

## 1. Authority is resolved server-side

Capabilities, permissions, provider ownership and privileged actions are determined by PHP/WordPress server state. Browser state, request payloads, generated content or model output cannot grant authority.

For WordPress this means, where applicable:

- use capabilities/nonces for privileged actions;
- validate and authorize again server-side;
- never trust hidden inputs, JavaScript flags or AI-generated instructions as permission.

## 2. Platform/site administration stays separate from product/content roles

Administrative capability must not be inferred from editorial roles or frontend behavior. If this template is later embedded in a multi-product platform, Platform Admin remains separate from customer/product roles.

## 3. Client/browser/model output never grants roles, entitlements, credentials or tool permissions

Any future AI-assisted feature is advisory by default. A model cannot decide that a user may access an admin action, private post, integration credential or deployment tool.

## 4. Provider credentials never reach the browser or model context

Secrets, API keys, SMTP/third-party credentials and private integration tokens remain server-side. They are never serialized into HTML, JavaScript configuration, generated Markdown, `llms.txt`, logs intended for public artifacts or model prompts.

## 5. Provider/model/integration selection is server-authoritative

When multiple providers could own an output, the server selects one authoritative provider from validated configuration and active integrations. Examples in this project include SEO provider, Schema ownership, multilingual adapter and sitemap owner.

## 6. Organization/tenant data never crosses boundaries

The initial starter kit is not inherently multi-tenant, but any future SaaS/multi-tenant use must scope data server-side by organization/site. Cache keys, API responses, exports and diagnostics must preserve that boundary.

## 7. EN/ES ships together for customer-facing changes

Every project-owned user-facing change is internationalization-ready from its first PR. English and Spanish translations ship together. Additional languages use the same translation contract.

This includes:

- settings/admin labels;
- notices/errors;
- onboarding;
- frontend project-owned strings;
- preset-owned labels/content scaffolding where shipped as reusable UI.

## 8. Customer-facing changes pass responsive + UX acceptance

A feature is not complete because it works on one desktop viewport. Relevant changes must be checked for mobile, tablet/desktop behavior, keyboard interaction, focus, reduced motion and content overflow.

## 9. Minimum sufficient validation

Run the gates required by the changed contract, not unrelated work. Validation must be deep enough to prove the affected behavior and small enough to stay actionable.

Path scope is part of the engineering contract:

- repository-wide governance/status documents such as `docs/ROADMAP.md`, `docs/CI_QUALITY_GATES.md`, `docs/engineering/GLOBAL_ENGINEERING_RULES.md` and `docs/engineering/ERRORS_AND_SOLUTIONS.md` trigger **Foundation only**;
- specialized workflows may watch their own authoritative technical documentation when that document is part of the specific acceptance contract;
- a docs-only status/evidence/closure change must not trigger browser, Lighthouse, multilingual, packaging or runtime smoke suites;
- when documentation changes a real executable contract, the same change must include the code/test/workflow update that causes the relevant specialized gates to run. Documentation alone is not a substitute for implementation acceptance;
- Foundation enforces this scope through `scripts/ci/validate-ci-path-scope.sh`.

Examples:

- metadata change -> SEO output tests, not unrelated ecommerce E2E;
- language resolver change -> ES/EN canonical/hreflang/navigation tests;
- pattern styling change -> responsive/accessibility/performance checks for that pattern;
- build change -> package/install/build gates;
- roadmap/phase-status/evidence-only change -> Foundation only.

## 10. Finish before advancing

Phase N+1 cannot begin until Phase N implementation, required gates, acceptance, blockers and documentation are complete.

The same rule applies to microphases: close the current diagnostic or implementation unit with a concrete result before opening the next one. Do not leave a chain of half-finished investigative steps.

## 11. Feature branch -> PR -> CI -> merge -> verification

Normal flow:

```text
feature/fix/chore branch
-> pull request
-> required CI
-> review/acceptance
-> merge
-> install/deployment verification
```

Direct commits to `main` are not normal work. The repository's first empty-repo bootstrap commit is the documented one-time exception required to create the initial branch.

## 12. Every CI/build/test/deploy/runtime failure produces structured actionable diagnostics

Raw logs are evidence, not the final diagnostic. Every material failure should be reduced to:

```text
pipeline
run/job/step
command
exit_code
primary_error
file:line[:column] when available
expected
received
error_signature
root_cause_status: confirmed | hypothesis | unknown
root_cause or hypothesis
fix_applied
validation
regression_coverage
incident/reference
```

Diagnostics must include the minimum useful context and exclude secrets/personal data.

## 13. Record relevant errors and solutions

Material production failures, regressions, security issues, repeated CI failures and architectural traps are recorded in `docs/engineering/ERRORS_AND_SOLUTIONS.md` with:

- symptom/context;
- signature;
- root cause;
- solution;
- validation;
- prevention/guardrail;
- regression coverage;
- reference to PR/commit/issue when available.

Before solving a recurring class of error, consult the register. Do not create duplicate entries; update the existing incident when it is the same underlying problem. If an incident reveals an architectural lesson, update these rules/contracts as part of the fix.

## 14. One authoritative owner for each public SEO signal

The page must not emit competing duplicate canonicals, robots directives, hreflang sets, sitemaps or overlapping Schema graphs because two plugins are active. Integrations explicitly delegate or suppress output.

## 15. Private content never leaks into discovery surfaces

Drafts, private posts, protected fields, credentials, internal notes and admin-only metadata must never appear in public sitemaps, Schema, `llms.txt`, Markdown alternates, crawler-facing endpoints or diagnostics artifacts.

## 16. Dependency minimalism

Every production dependency has a cost in performance, security, maintenance and compatibility. Prefer WordPress/browser primitives. New runtime dependencies require a documented reason and must be scoped to where they are needed.

## 17. Documentation changes with the contract

When behavior changes an architecture boundary, public output, configuration, language handling, security assumption or acceptance criterion, the same PR updates the corresponding documentation.

## 18. No unsupported SEO/GEO claims

Product copy, comments and documentation must distinguish:

- requirements supported by primary documentation;
- project engineering choices;
- experimental interoperability mechanisms;
- hypotheses/measurements.

Do not describe experimental files or markup as guaranteed ranking/citation factors.

## 19. Security and escaping are contextual

Validate early, sanitize stored input according to its data type, and escape on output according to context. SQL uses WordPress database APIs/prepared statements. REST/AJAX/admin actions require capability and nonce/auth checks as appropriate.

## 20. Regression first for confirmed bugs

When practical, reproduce a confirmed bug with a focused failing test before or alongside the fix. The final validation must prove the original signature no longer occurs and guard the contract that failed.
