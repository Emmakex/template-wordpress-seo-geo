# EMMAKE A3.2 — Corporate v5 Hero/CTA/mobile-navigation visual acceptance

Scope: only the Theme-owned Corporate v5 strategic homepage renderer at `emmake.com/nuevaweb/`, keeping WordPress front page ID 7 and the Site Manager-owned `corporate-home-v1` 28-slot semantic overlay intact.

## Evidenced failure

The real read-only Chromium screenshots produced on 11 Nov 2026 (GitHub Actions run `43050776883`, QA artifact `13869267108`, 1440×900 desktop and 390×844 mobile) show:

- Corporate Hero H1 with dark foreground on dark blue/teal background, despite source v5 CSS specifying white; this can be due to effective cascade on the installed client bundle.
- Secondary Hero outline CTA with dark-on-dark foreground.
- Mobile navigation `<details><summary>Menu</summary>` appears as a blank white pill with invisible text in the screenshot.
- Mobile H1 font/line breaking pushes primary CTA below the first 844px viewport.

The existing automatic browser acceptance **90/90** covers HTTP, horizontal overflow, image/JS errors and basic DOM only. It **does not** measure computed color contrast or mobile first-screen readability, so that success is not visual acceptance.

## Bounded solution

The source of authority remains `packages/seo-geo-theme/assets/css/presets/corporate-v5-a3-1.css`. Add final high-specificity rules that survive inherited WordPress global styles while remaining exclusive to `body.seo-geo-corporate-home-v5`:

- Explicit accessible light Hero H1 foreground (including WebKit text fill), white outline-CTA foreground and opaque-enough dark button background.
- Dark mobile menu summary with a visible white label, 44px minimum target and focus-visible outline.
- Compact mobile H1 scale, full-width copy, more reasonable eyebrow tracking and wide tap targets so buttons appear sooner.
- No arbitrary body/content edits, new JS or plugin, change to SEO, Gutenberg source or Site Manager model.
- The official target remains **0.1.1 prestable** (stable release remains `no-go`). Distribute this as a checksum-distinct **A3.2 hotfix** using the official deterministic builder and integrity manifest. Native content-hash CSS asset versioning ensures cache invalidation even when the WordPress theme header stays at 0.1.1.

## Acceptance after installation

1. Replace the existing Theme with the official **0.1.1 A3.2 hotfix** ZIP on the **staging site only**.
2. Run Site Manager `site.overview`, `site.theme_model` and `site.diagnostics` to confirm unchanged site/model/noindex.
3. Repeat public HTTP acceptance and Chromium screenshots for desktop/mobile. Check **computed** heading text/background contrast, secondary CTA legibility, visibly labelled mobile `Menu` trigger and CTA placement within first viewport; manual screenshot review still mandatory.
4. Confirm no navigation/permalink regressions. Keep staging noindex until explicit domain cutover acceptance.

Do not infer deployment or a full visual fix from a successful build alone.
