# EMMAKE A3.4 — Method card interior spacing

## Evidence

In the user's 12 November screenshot of the **Method** section at `https://emmake.com/nuevaweb/`, all three numbered cards visibly place the badge, headline and body text almost against the left edge. The Hero and the section-level outer gutters corrected in A3.3 are already accepted and must not change.

## CSS boundary

Only `packages/seo-geo-theme/assets/css/presets/corporate-v5-a3-1.css`:

- The exact three `.seo-geo-corporate-process-grid > .seo-geo-corporate-process-step` children receive symmetric `padding-inline` and `padding-block`, using a sufficiently specific, explicitly important rule to beat WordPress block/global padding resets.
- The badge remains inside the card padding with more vertical separation from the `h3`. The title and body retain their inherited typography with bounded inline width and regular rhythm.
- At <=820px and <=520px, the inset remains a minimum 24px on each side. Parent A3.3 responsive outer gutters and card gap remain intact.
- No content changes, no Gutenberg/Meta modification, no new plugin, no arbitrary URL/SEO changes. Official Theme remains 0.1.1 prestable; this ZIP is checksum-distinct A3.4.

## Acceptance

1. Exact-head CI must pass all Theme checks and the package must include A3.2, A3.3 and A3.4 CSS with an integrity manifest.
2. Replace **only** `seo-geo-theme` in staging `emmake.com/nuevaweb` and preserve Site Manager 0.11.11.
3. In real Chromium (1440 / 820 / 390 viewport), inspect `getBoundingClientRect()` on each card, badge, h3 and final paragraph. Verify content starts at least 24px to the right of the card inner border on desktop, tablet and mobile, and none crosses the card's right edge.
4. Verify Hero full-bleed, Method outer gutters, original 28 reviewed fields, no horizontal overflow, unchanged `noindex`, and public HTTP 200.
5. Human visual review of a fresh screenshot closes the styling issue; passing a static CSS test or CI alone does not.

No root-domain cutover is part of this change.
