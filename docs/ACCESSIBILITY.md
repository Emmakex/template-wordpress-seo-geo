# Accessibility Contract

## Goal

The starter kit should make the accessible path the easiest path. Accessibility is a release criterion for customer-facing templates and patterns, not a post-launch audit item.

## Baseline

Target WCAG 2.2 AA-compatible implementation practices where applicable to the shipped UI. This document is an engineering contract, not a claim that every site built from the template automatically achieves conformance; editors and project-specific content can still introduce failures.

## Semantic structure

- One clear page-level main landmark.
- Meaningful header/nav/main/aside/footer landmarks.
- Heading levels represent document hierarchy rather than visual size.
- Links are links and buttons are buttons.
- Lists, tables and quotations use semantic HTML.
- Meaningful images have appropriate alternative text; decorative images are hidden from assistive technology.
- Form fields have programmatic labels and understandable errors.

## Keyboard and focus

- All interactive project-owned components are keyboard operable.
- Focus order follows reading/task order.
- Visible `:focus-visible` states must not be removed.
- Modal/off-canvas components manage focus and escape behavior correctly.
- Skip link to main content is provided by the theme.
- No keyboard traps.

## Motion

Project-owned motion and animated effects must respect `prefers-reduced-motion`. Essential information cannot depend solely on animation.

## Color and visual presentation

- Preset token combinations must be selected with AA contrast in mind.
- Information cannot rely only on color.
- Focus, validation and status states need non-color cues where required.
- Text remains usable under browser zoom and responsive reflow.

## Forms

- Labels remain available; placeholders are not substitutes for labels.
- Required state and errors are announced clearly.
- Error messages identify the field and remediation.
- Autocomplete tokens should be used for common personal/contact fields where appropriate.

## Patterns

Every reusable pattern must be tested as a standalone pattern and inside representative templates. A pattern that requires a client to manually repair landmark/heading/focus semantics before use is not considered a finished foundation pattern.

## Third-party integrations

The template cannot guarantee accessibility of third-party plugins/widgets. Integrations should:

- avoid worsening third-party output through wrappers;
- document known limitations;
- prefer accessible native alternatives where equivalent;
- treat critical third-party accessibility failures as project blockers when that component is required for the user journey.

## Acceptance gates

For customer-facing changes, minimum validation includes the relevant subset of:

- keyboard navigation;
- visible focus;
- heading/landmark structure;
- accessible name for controls;
- form labels/errors;
- contrast/token review;
- reduced-motion behavior;
- mobile/responsive reflow;
- automated accessibility scan for obvious failures;
- manual verification for behavior automation cannot prove.
