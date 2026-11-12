# EMMAKE A3.3 — full-bleed Hero and Method gutters

**Scope:** Corporate v5 strategic Home layout only on staging `https://emmake.com/nuevaweb/`. Source: `packages/seo-geo-theme/assets/css/presets/corporate-v5-a3-1.css`. The Site Manager-owned semantic content and WordPress page 7 are out of scope.

## Evidence and issue

The user-provided full-page screenshot of 12 November 2026, 08:27 Europe/Madrid shows a visible white margin outside the Hero's teal background, while the three Method cards are too close to the section's left/right edges. The section itself must retain its edge-to-edge mint background.

## A3.3 fix

- Make only `main.seo-geo-corporate-v5-home > .seo-geo-corporate-native-hero` full viewport width, compensating for a constrained parent through `margin-inline:calc(50% - 50vw)`. The inner Hero grid keeps `--v5-shell` alignment; content does not touch the edges.
- Apply section-level `padding-inline` to `.seo-geo-corporate-native-process`. Keep the section background full-width, center heading and three cards within `max-inline-size:1400px`, and retain a readable card `gap`.
- Enforce smaller, nonzero gutters at <=820px and <=520px. Never place extra margins directly on the three card elements.
- Add no layout JS, no Gutenberg, theme version, content, image, SEO/GEO or permalink changes. Official WordPress theme version stays **0.1.1 prestable**, distributed as checksum-distinct **A3.3** hotfix.

## Acceptance gates

1. All exact-head GitHub Theme tests pass, including WordPress 7.1 smoke, accessibility/responsive, Lighthouse, frozen legacy pilot-pack integrity and deterministic A3.3 package.
2. Install new Theme ZIP over the active `seo-geo-theme` on **staging only**, without deactivating Site Manager, replacing content or adjusting indexability.
3. Read the live CSS asset hash and render the Home at 1440px and 390px. Confirm Hero's background spans screen width with the inner text aligned to the other sections.
4. Confirm Method cards have visible left/right padding at both sizes, same heading/card alignment, good gaps and no horizontal overflow.
5. Re-run `site.overview`, `site.theme_model`, `site.diagnostics`, and public fixed URL HTTP/browser checks; verify 28 approved fields, HTTPS, internal links and staging `noindex`.

**Before installation:** CI-ready source and a ZIP do not prove the visual result on the public site.
