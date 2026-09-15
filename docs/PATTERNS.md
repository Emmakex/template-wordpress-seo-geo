# Reusable Theme Patterns

## Purpose

The starter theme ships a small set of native WordPress block patterns that can be reused across corporate, local-business, publisher and ecommerce presets without coupling the foundation to a page builder.

Patterns are layout/editorial scaffolding. They are **not** a source of SEO metadata, Schema, business claims, customer testimonials or project-specific identity.

Primary WordPress references:

- https://developer.wordpress.org/themes/patterns/registering-patterns/
- https://developer.wordpress.org/themes/patterns/using-php-in-patterns/
- https://developer.wordpress.org/themes/patterns/usage-in-templates/

## Registration model

Theme-owned patterns live in:

```text
packages/seo-geo-theme/patterns/
```

WordPress discovers pattern files in this directory and registers files with valid pattern headers. Phase 2B deliberately uses this native registration path instead of adding manual `register_block_pattern()` calls to `functions.php`.

Required headers for every project pattern:

- `Title`;
- `Slug`;
- `Categories`;
- `Description`;
- `Viewport Width`.

Slugs are namespaced under `seo-geo-theme/`.

## Core pattern set

| File | Slug | Purpose |
| --- | --- | --- |
| `hero.php` | `seo-geo-theme/hero` | Value proposition + supporting copy + actions |
| `cta.php` | `seo-geo-theme/cta` | Focused next-step call to action |
| `services-features.php` | `seo-geo-theme/services-features` | Three comparable offerings/features |
| `trust-proof.php` | `seo-geo-theme/trust-proof` | Prompts for real evidence, experience and verification |
| `faq.php` | `seo-geo-theme/faq` | Native Details-based FAQ content |
| `author-profile.php` | `seo-geo-theme/author-profile` | Identity, role/expertise and concise bio |
| `contact.php` | `seo-geo-theme/contact` | Dependency-free contact methods/service area |

## Content rules

### No fabricated proof

Patterns must not ship invented clients, reviews, ratings, certifications, years of experience, revenue, percentages, awards or performance claims. `trust-proof.php` intentionally provides authoring prompts instead of fake evidence.

### Translation ready

Project-owned visible defaults use escaped WordPress translation functions with the `seo-geo-theme` text domain. The pattern source can use PHP for these translations because WordPress compiles the pattern content when it registers the theme pattern.

ES and EN translations will ship together when the project introduces customer-facing translation catalogs. Until then, the source strings remain translation-ready and neutral rather than duplicating hard-coded language variants inside every pattern.

### H1 ownership

Reusable patterns do not emit an H1. Page/template composition owns the page-level primary heading so inserting a reusable section cannot silently create multiple H1 elements.

A project may deliberately promote a hero heading to H1 when composing a page, but that is a page-level decision and will be validated by later representative fixtures.

### Schema ownership

Patterns never emit JSON-LD or Schema markup. FAQ, author/person and organization structured data belong to `seo-geo-core`, which can decide whether visible content and provider ownership justify the corresponding graph node.

### Native blocks only

The Phase 2B base set uses native WordPress blocks. It must not require Elementor, a forms plugin, a slider library, JavaScript widgets or a Custom HTML block.

## Design-token rules

Patterns consume the semantic design system from `theme.json` rather than defining another visual system.

Allowed styling sources include:

- semantic color presets;
- spacing presets;
- font-size presets;
- border-radius presets;
- WordPress core block style variations such as outline buttons.

Pattern source must not introduce:

- raw hex colors;
- raw `px`, `rem`, `em`, `vw` or `vh` measurements;
- embedded `<style>` elements;
- remote frontend URLs/dependencies.

WordPress may serialize preset-backed attributes into inline declarations such as `var(--wp--preset--spacing--lg)`. These remain token references, not duplicated raw design values.

## Performance contract

The base pattern set adds:

- zero JavaScript;
- zero custom CSS files;
- zero remote assets;
- zero web-font requests;
- zero third-party block dependencies.

Images are intentionally not hard-coded into the base patterns. Presets/projects can add real optimized media later with appropriate dimensions, alt behavior and LCP treatment.

## CI contract

`Pattern Contract CI` runs before a pattern change can close. It validates:

- exact expected core pattern set;
- PHP syntax through the structured PHP lint wrapper;
- required headers;
- expected unique namespaced slugs/categories;
- translation-ready escaped visible copy;
- native block-only source;
- no embedded scripts/styles;
- no remote URLs;
- no raw hex colors or raw CSS size literals;
- no reusable H1 ownership;
- no Schema output;
- every referenced `var:preset|type|slug` exists in `theme.json`.

The WordPress runtime smoke is also extended in Phase 2B to prove the expected theme patterns are registered after activating the theme in the real WordPress 7.1 fixture.
