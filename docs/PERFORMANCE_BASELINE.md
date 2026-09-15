# Phase 2D Performance Baseline

## Purpose

This document records the first representative performance baseline for the SEO + GEO starter and the regression budgets derived from measured WordPress fixtures. These are laboratory CI controls, not guarantees of production Core Web Vitals.

Field-oriented product targets remain:

- LCP < 2.5 s;
- INP < 200 ms;
- CLS < 0.1.

Production field data must be evaluated separately through Search Console and/or RUM when a real deployment is available.

## Fixture and toolchain

The baseline uses the same representative content proven by Phase 2C:

- WordPress 7.1 / PHP 8.2;
- MariaDB 11.8;
- `seo-geo-theme` + `seo-geo-core` active;
- representative English and Spanish pages built from the registered theme patterns;
- Lighthouse 13.4.1;
- Chromium supplied by the locked Playwright toolchain;
- three Lighthouse performance runs per language;
- median values used for decisions.

The CI fixture is disposable and provider-neutral. It intentionally contains no optimization/cache plugin, CDN or third-party frontend service that could hide foundation-level bloat.

## Observed baseline

Two independent observation runs produced materially stable results. The second run includes the corrected Lighthouse 13 DOM insight extraction and is the reference measurement for budget calibration.

| Metric | EN median | ES median |
| --- | ---: | ---: |
| Lighthouse performance | 100 | 100 |
| FCP | 653.46 ms | 664.39 ms |
| LCP | 653.46 ms | 664.39 ms |
| CLS | 0 | 0 |
| TBT | 0 ms | 0 ms |
| Speed Index | 653.46 ms | 664.39 ms |
| Total transfer | 20,063 B | 20,274 B |
| HTML transfer | 10,405 B | 10,616 B |
| External CSS transfer | 0 B | 0 B |
| JavaScript transfer | 5,729 B | 5,729 B |
| Image transfer | 0 B | 0 B |
| Requests | 5 | 5 |
| Third-party requests | 0 | 0 |
| Project-owned JavaScript | 0 B | 0 B |
| DOM nodes | 88 | 88 |

The approximately 5.7 KB JavaScript transfer is WordPress-owned runtime JavaScript. The theme and Core plugin contribute **0 project-owned frontend JavaScript bytes** in this fixture.

The `0 B` external CSS value means this representative page did not transfer a separate stylesheet resource in the measured network graph; required block/theme styles are emitted by WordPress/theme mechanisms within the rendered response. It is not a rule that future features can never add a stylesheet, which is why the enforced CSS budget includes a small bounded allowance.

## Enforced budgets

`tests/performance/budgets.json` is authoritative for CI. Both EN and ES fixtures currently enforce:

| Budget | Limit | Rationale |
| --- | ---: | --- |
| Minimum Lighthouse performance | 95 | Detect a meaningful category-score regression without treating a current 100 as permanently noise-free. |
| Maximum LCP | 1,200 ms | Substantial margin above the ~0.66 s baseline for shared-runner variance while remaining well inside the field-oriented 2.5 s target. |
| Maximum CLS | 0.05 | Stricter than the field-oriented 0.1 target. |
| Maximum TBT | 100 ms | Current baseline is 0; allows modest platform noise but catches meaningful main-thread work. |
| Maximum total transfer | 26,624 B | Roughly 31% headroom over the larger measured fixture. |
| Maximum HTML transfer | 14,336 B | Bounded headroom over the larger 10,616 B measured document. |
| Maximum external CSS transfer | 4,096 B | Allows a small justified stylesheet without permitting silent global CSS growth. |
| Maximum total JS transfer | 8,192 B | Bounds WordPress + project script transfer; project-owned JS has a separate stricter rule. |
| Maximum requests | 7 | Baseline is 5; allows two justified additions before the contract must be deliberately revisited. |
| Maximum DOM nodes | 112 | Approximately 27% headroom over the measured 88-node fixture. |
| Maximum third-party requests | 0 | Strict foundation rule: no third-party frontend dependency by default. |
| Maximum project-owned JS | 0 B | Strict current foundation rule: no frontend JS until a feature proves it is necessary. |

Budget changes require measured before/after evidence and a documented reason. Raising a budget merely to make CI pass is not acceptable.

## Why median-of-three

Single Lighthouse lab runs contain scheduling and environment noise. Phase 2D runs each language three times and uses the median so one unusually slow or fast sample does not define the regression contract.

Timing budgets intentionally have more headroom than byte/request budgets. Transfer size and request counts are comparatively deterministic, while timing metrics on shared CI runners vary more.

## Measurement outputs

`Performance Baseline CI` preserves the raw Lighthouse JSON reports plus WordPress runtime diagnostics as a short-lived workflow artifact. The structured reporter also prints a single `PERFORMANCE_BASELINE_JSON=...` line so future regressions can be compared without manually reading the entire Lighthouse log.

Reference observation runs:

- run `34925563889` — first successful baseline observation;
- run `34925805108` — successful corrected DOM measurement, reference baseline;
- reference DOM measurement: 88 nodes in EN and ES.

## Known boundaries

This gate deliberately does not claim to measure production hosting, CDN behavior, real-user interaction latency or real geographic network conditions. In particular:

- Lighthouse timing values are lab proxies;
- INP is a field/interaction metric and is not inferred from this static page-load run;
- the fixtures currently contain no editorial images, so image-transfer budgets will be introduced when a representative image-bearing contract exists;
- third-party integrations/presets must add their own scoped performance acceptance rather than weakening the base fixture silently.

## Regression rule

A future change that exceeds a budget must do one of two things:

1. reduce the regression and keep the existing budget; or
2. demonstrate that the additional cost is required, record before/after evidence, document the user/product benefit and deliberately revise the authoritative budget.

Performance budgets are product contracts, not score-chasing targets.
