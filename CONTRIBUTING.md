# Contributing

## Workflow

All normal changes use:

```text
feature/fix/chore branch -> PR -> CI -> review -> merge -> verification
```

Direct `main` commits are not part of the normal workflow.

## Before coding

Identify:

- the product contract being changed;
- the authoritative module/provider;
- multilingual impact;
- SEO/GEO impact;
- performance impact;
- accessibility impact;
- security/privacy impact;
- minimum sufficient validation.

If those are unclear, reduce the task to a smaller microphase and close that decision before broad implementation.

## Branch naming

Examples:

```text
feature/native-canonical
feature/wpml-language-adapter
fix/duplicate-schema-provider
chore/foundation-ci
```

## Pull request expectations

A PR should state:

- problem/goal;
- scope;
- contracts affected;
- implementation summary;
- EN/ES impact;
- validation performed;
- risks/known limitations;
- documentation changed;
- linked incident/error entry when applicable.

## Definition of done

A change is done only when:

- implementation is complete;
- required tests/gates pass;
- responsive/UX/a11y acceptance is complete where relevant;
- EN/ES customer-facing output ships together;
- no known blocker remains for the stated scope;
- documentation matches behavior;
- material failures have structured diagnostics and error-register updates when applicable.

## Security

Never commit secrets, private keys, production credentials, customer data or private exports. Use test fixtures with synthetic values.
