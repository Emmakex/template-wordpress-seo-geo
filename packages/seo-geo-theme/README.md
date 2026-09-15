# SEO GEO Theme

Installable lightweight WordPress block theme for the reusable SEO + GEO foundation.

## Owns

- `theme.json` design tokens and global styles;
- templates and template parts;
- reusable native block patterns;
- theme-specific CSS/JS only when required;
- semantic document structure;
- accessible presentation defaults.

## Does not own

- canonical/robots/meta policy;
- durable Schema/entity data;
- hreflang/language-provider logic;
- sitemap ownership;
- crawler policy;
- `llms.txt` or Markdown endpoint behavior.

Those belong to `packages/seo-geo-core`.

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
  - contact.

Patterns use theme presets instead of creating a second visual system. They do not emit Schema, own page-level H1s, embed remote assets or require third-party blocks.

See `docs/DESIGN_SYSTEM.md` and `docs/PATTERNS.md` for the contracts enforced by CI.
