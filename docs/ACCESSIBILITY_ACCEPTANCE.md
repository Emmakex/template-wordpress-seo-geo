# Accessibility & Responsive Browser Acceptance

## Purpose

Phase 2C turns the accessibility contract into an executable browser gate against a disposable real WordPress installation. It does not claim that every site built from the starter automatically conforms to WCAG; project content, third-party plugins and later customizations can still introduce failures.

## Runtime fixture

The CI fixture installs and activates the repository theme and Core plugin on WordPress 7.1 / PHP 8.2 with MariaDB. It then composes representative pages from the theme's registered native patterns.

Two browser fixtures are published:

- `/acceptance-en/?fixture_lang=en`
- `/acceptance-es/?fixture_lang=es`

The Spanish fixture uses the same block structure with deliberately longer Spanish copy so responsive behavior is exercised under a second language. The acceptance-only locale MU-plugin exists only inside the disposable CI installation; it is not a production multilingual implementation and is never packaged with the theme/plugin.

## Browser matrix

Chromium is exercised at:

- 320 × 800 — narrow mobile baseline;
- 768 × 1024 — tablet baseline;
- 1440 × 900 — desktop baseline.

## Automated acceptance

For both EN and ES fixtures at every viewport the suite asserts:

- HTTP success;
- correct document language;
- one banner, one main landmark and one contentinfo landmark;
- exactly one H1 owned by the page template;
- non-empty headings with no upward hierarchy jump greater than one level;
- no automated axe violations for WCAG 2.0/2.1/2.2 A and AA tags;
- no document/body horizontal overflow;
- a usable skip link whose target receives focus;
- keyboard reachability of multiple controls;
- a visible `:focus-visible` state on a control inside main content;
- `prefers-reduced-motion: reduce` is honored by project content;
- no WordPress PHP fatal errors, warnings, notices or uncaught runtime errors during the fixture.

## Theme decisions proven by the gate

### Skip-link focus

WordPress supplies the block-template skip link and generated fragment ID. The theme makes the rendered `main` Group a focus target by adding `tabindex="-1"` server-side with `WP_HTML_Tag_Processor`. This preserves normal tab order while allowing keyboard skip-link activation to transfer focus into main content without custom frontend JavaScript.

### Primary navigation semantics

The foundation header uses a semantic native `nav` Group containing `core/page-list` rather than relying on the empty `core/navigation` Page List fallback. The WordPress 7.1 fallback produced an invalid nested-list relationship in the original browser fixture; the explicit native composition keeps automatic page discovery and passes the unsuppressed axe `list` rule.

### Visual focus

`theme.json` owns project link/button `:focus-visible` treatment using semantic palette tokens. The browser suite verifies that focused main-content controls have an observable focus-state style rather than merely asserting that a selector exists in source.

## Toolchain

Browser acceptance is reproducible from the committed Node dependency graph:

- Node 24.x;
- `@playwright/test` 1.63.0;
- `@axe-core/playwright` 4.13.0;
- committed `package-lock.json`;
- `npm ci` in CI.

No axe rule is suppressed for the baseline acceptance suite.

## Validation evidence

Phase 2C implementation candidate `97a8be8bf2dc092a3afb144863d6deee40ef6aa8` passed:

- Accessibility & Responsive CI run `34918470902`, job `104221118872` — **24/24 browser tests passed**;
- PHP Quality CI run `34918471012` — WPCS + PHPStan level 6 passed;
- Design System CI run `34918470964` — semantic token/contrast contract passed;
- Foundation CI run `34918471009` — passed;
- Phase 1 Package CI run `34918470910` — passed;
- Pattern Contract CI run `34918471015` — passed;
- WordPress Smoke CI run `34918470983`, job `104221130905` — passed.

The 24 browser cases cover 2 languages × 3 viewports × 4 acceptance groups: semantic/axe, responsive reflow, keyboard/focus and reduced motion.

The adoption failures that produced reusable engineering lessons are recorded as `ERR-2026-003`, `ERR-2026-004` and `ERR-2026-005` in `docs/engineering/ERRORS_AND_SOLUTIONS.md`.

## Ownership boundaries

- `theme.json` owns semantic visual focus treatment for links/buttons.
- WordPress/theme templates own landmarks and page-level heading structure.
- patterns own their internal visible structure but never the page H1.
- the browser fixture owns only test data and locale simulation.
- third-party widgets are outside this fixture until a preset or product journey requires them.

## Manual acceptance still required

Automated accessibility tooling cannot prove full WCAG conformance. Customer-facing releases must still review behavior automation cannot establish reliably, including meaning of alternative text, appropriateness of link language, content clarity, task flow, cognitive load and assistive-technology behavior for any custom interactive component.

## Local execution

With Node 24, Docker and the repository dependencies installed:

```bash
npm ci
npx playwright install --with-deps chromium
bash scripts/ci/browser-acceptance.sh
```

The script creates and destroys its own isolated WordPress/MariaDB containers and passes the temporary WordPress URL to Playwright through `SEO_GEO_BASE_URL`.
