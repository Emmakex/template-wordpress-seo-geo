# Design System

## Purpose

The base theme ships a neutral design system that can be branded per project without adding a page builder, remote font dependency or an uncontrolled collection of one-off values.

`theme.json` is the authoritative source for global design tokens. Project presets and block patterns should consume these presets rather than hard-code duplicate colors, spacing or typography values.

Primary WordPress references:

- https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/
- https://developer.wordpress.org/themes/global-settings-and-styles/settings/typography/
- https://developer.wordpress.org/themes/features/block-stylesheets/

## Principles

1. **Semantic tokens before decorative values.** Patterns use `base`, `contrast`, `surface`, `muted`, `border`, `accent` and related semantic tokens rather than project-specific color names.
2. **System fonts by default.** The foundation performs no remote font request. A client project can deliberately add licensed local WOFF2 faces later.
3. **Fluid where it improves reading hierarchy.** Body and small utility text stay stable; display sizes scale within bounded minimum/maximum values.
4. **Consistent spacing.** Layouts use the project spacing presets instead of arbitrary margins/padding.
5. **Accessible critical color pairs.** Text/action pairs are guarded by automated WCAG contrast calculations.
6. **Curated editor choices.** WordPress default palettes, gradients, font sizes and spacing presets are disabled so project authors use the defined system.
7. **`theme.json` first.** CSS is only introduced when a requirement cannot be expressed cleanly through WordPress global styles. Block-specific CSS must use WordPress block stylesheets so it can load only where relevant.

## Color tokens

| Slug | Value | Intended use |
| --- | --- | --- |
| `base` | `#FFFFFF` | Primary page background |
| `contrast` | `#111827` | Main text/headings |
| `surface` | `#F8FAFC` | Secondary sections/cards |
| `muted` | `#475569` | Secondary text/captions |
| `border` | `#CBD5E1` | Dividers and neutral borders |
| `accent` | `#1D4ED8` | Links and primary actions |
| `accent-strong` | `#1E40AF` | Strong/interactive accent state |
| `accent-contrast` | `#FFFFFF` | Text on accent surfaces |

Automated minimum contrast contract:

- `contrast` on `base`: >= 7:1;
- `muted` on `base`: >= 4.5:1;
- `accent` on `base`: >= 4.5:1;
- `accent-contrast` on `accent`: >= 4.5:1;
- `contrast` on `surface`: >= 7:1;
- `accent-contrast` on `accent-strong`: >= 4.5:1.

The initial palette exceeds these targets. A branded project may replace values only if the same semantic pair contract remains valid.

## Typography

### Families

`system-sans` is the default stack:

```text
-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif
```

`system-serif` is available for editorial/preset use:

```text
ui-serif, Georgia, Cambria, "Times New Roman", Times, serif
```

Neither declares `fontFace`; therefore the base theme does not fetch font files.

### Size scale

| Slug | Base | Fluid bounds |
| --- | ---: | --- |
| `xs` | 0.8125rem | static |
| `sm` | 0.9375rem | static |
| `md` | 1rem | static |
| `lg` | 1.25rem | 1.125–1.25rem |
| `xl` | 1.75rem | 1.375–1.75rem |
| `2xl` | 2.5rem | 1.75–2.5rem |
| `3xl` | 3.5rem | 2.25–3.5rem |

Body text uses `md` with line-height `1.6`. Headings use a `1.15` line-height and progressively map H1–H4 to the display scale.

## Spacing

The scale is intentionally small and reusable:

```text
2xs  0.25rem
xs   0.5rem
sm   0.75rem
md   1rem
lg   1.5rem
xl   2rem
2xl  3rem
3xl  4.5rem
```

Global block gap starts at `lg`. Patterns may compose different preset steps, but should not introduce arbitrary spacing without a documented component requirement.

## Radius

Available radius presets:

```text
sm   0.25rem
md   0.5rem
lg   1rem
pill 999px
```

The default button uses `md`.

## Links and buttons

Base links use the accent color and remain underlined. This makes link recognition independent from color alone.

Primary buttons use `accent` + `accent-contrast`, font-weight 600, consistent preset padding and the medium radius. Interactive focus/hover/reduced-motion behavior is completed and acceptance-tested in Phase 2C rather than embedded as ad-hoc CSS in this token phase.

## Customization contract

A client implementation should normally customize the **values** attached to semantic token slugs, not rename the slugs. Preserving slugs keeps presets/patterns portable across projects.

If a project needs another semantic concept, add the smallest reusable token that represents it. Do not add colors named after a specific page or component such as `homepage-blue` or `card-2-gray`.

## Performance contract

The Phase 2A design system itself adds:

- zero JavaScript;
- zero remote requests;
- zero web-font files;
- zero standalone global CSS files;
- only WordPress-generated global style declarations derived from `theme.json`.

Later component styling should remain in `theme.json` where possible. When CSS is required, register it per block with `wp_enqueue_block_style()` rather than creating a monolithic stylesheet.
