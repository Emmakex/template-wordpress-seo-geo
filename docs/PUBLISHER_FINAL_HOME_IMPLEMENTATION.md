# Publisher Home implementation map

- Preset contract: `presets/publisher/mockup.json`
- Provisional localized copy: `presets/publisher/mockup-copy.json`
- Final Home renderer: `packages/seo-geo-theme/preset-patterns/publisher-home-final.php`
- Publisher visual layer: `packages/seo-geo-theme/assets/css/presets/publisher.css`
- Active-preset pattern registration: `packages/seo-geo-theme/inc/Publisher/FinalHomePattern.php`
- Runtime loading / Design System opt-in: `packages/seo-geo-theme/functions.php`
- Semantic gate: `scripts/ci/validate-publisher-preset.php`
- DS-5A gate: `scripts/ci/validate-publisher-final-home.php`
- Dedicated workflow: `.github/workflows/publisher-final-home.yml`

The implementation intentionally does not add Publisher Single, Archive or 404 overrides in DS-5A. Those remain neutral until DS-5C after Home and inner-page acceptance.
