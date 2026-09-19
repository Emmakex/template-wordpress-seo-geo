# SEO GEO Theme

Installable **self-contained WordPress block theme** for the reusable SEO + GEO foundation.

A clean WordPress installation plus the built theme must provide the documented baseline with **zero required plugins**.

## Owns

- `theme.json` design tokens and global styles;
- templates and template parts;
- reusable native block patterns;
- theme-specific CSS/JS only when required;
- semantic document structure;
- accessible presentation defaults;
- bootstrap of the embedded SEO/GEO runtime;
- native canonical/meta/robots/indexability behavior;
- future native Schema, GEO, crawler and sitemap extensions that form part of the baseline product.

## Embedded runtime

The committed loader lives at:

```text
inc/seo-geo-core/bootstrap.php
```

During distribution, `scripts/build-theme-package.sh` embeds the reusable Core source under:

```text
inc/seo-geo-core/src/
```

The built theme therefore does not require `seo-geo-core` to exist in `wp-content/plugins`.

## Current foundation

The theme currently provides:

- `theme.json` v3 semantic design system;
- system-font typography with no remote font dependency;
- page/single/archive/404 block templates;
- header/footer template parts;
- seven reusable native patterns in `patterns/`:
  - hero;
  - CTA;
  - services/features;
  - trust/proof;
  - FAQ;
  - author/profile;
  - contact;
- embedded native SEO runtime for canonical, meta description and robots/indexability;
- bundled declarative preset packages plus an allowlisted preset registry; Corporate, Local Business and Publisher are complete Phase 7 presets, with all presets disabled by default;
- zero-plugin acceptance through `Self-contained Theme CI`.

Patterns use theme presets instead of creating a second visual system. They do not emit duplicate Schema, own page-level H1s, embed remote assets or require third-party blocks.

Optional integrations may be added later for compatibility with projects that deliberately install external systems, but they are not prerequisites for the baseline.

See `docs/ARCHITECTURE.md`, `docs/NATIVE_SEO.md`, `docs/DESIGN_SYSTEM.md`, `docs/PATTERNS.md` and `docs/PRESETS.md` for the contracts enforced by CI.
