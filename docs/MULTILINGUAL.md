# Multilingual Architecture

## Contract

Multilingual support is part of the foundation, not a later compatibility patch. ES and EN are first-class project languages. Additional locales such as CA, FR and DE must use the same contract without core rewrites.

Customer-facing functionality ships EN/ES together. Strings must be internationalization-ready from the first implementation.

## Adapter model

The Core plugin exposes one language service and hides vendor-specific APIs behind adapters:

```text
LanguageManager
├── NativeWordPressAdapter
├── WPMLAdapter
└── PolylangAdapter
```

Other modules never call WPML or Polylang directly. They request normalized data such as:

- current language/locale;
- default language;
- translated resource URL;
- alternate URLs;
- locale-aware home URL;
- whether a translation exists;
- language direction where needed.

## URL strategy

The adapter must support clean localized URLs, for example:

```text
/es/servicios/seo/
/en/services/seo/
/ca/serveis/seo/
```

Slug translation belongs to the selected multilingual provider/preset strategy. The Core does not invent aliases independently of WordPress routing.

## Hreflang

Google recommends explicitly connecting localized versions. Project rules:

- each localized page lists itself and all valid alternates;
- only published, resolvable URLs are emitted;
- language codes follow supported standards;
- region codes are optional and only added when the content truly targets that region;
- `x-default` is configurable and emitted only when its semantic target is clear;
- alternate URLs are absolute;
- one system owns hreflang output at runtime.

Reference: https://developers.google.com/search/docs/specialty/international/localized-versions

## Canonical + language

Every translated page normally has a self-referencing canonical for that localized URL. Translation relationships are represented by alternates, not by canonicalizing all translations to the default language.

CI invariants:

- canonical language matches the current page;
- hreflang alternate never points to a 404/draft/private resource;
- reciprocal alternate relationship exists where the provider exposes translations;
- no duplicate hreflang provider output.

## HTML language and locale metadata

The final document must expose the WordPress-resolved `lang`/`dir` attributes. Open Graph locale and Schema `inLanguage` must derive from the same language service to prevent inconsistent language signals.

## Schema

Language-aware graph rules:

- `WebPage`, `Article`, breadcrumbs and localized textual fields use the current language;
- shared identity such as an Organization may retain one stable entity ID while exposing localized names/descriptions only when real localized values exist;
- `Person` identity must not be duplicated merely because a profile is translated;
- URLs in graph nodes always resolve to the appropriate locale.

## Sitemaps

Use the authoritative WordPress/SEO-provider sitemap system. Language alternate handling must integrate with that provider instead of creating a second sitemap universe.

Where sitemap-based hreflang is intentionally selected, that choice replaces—not duplicates—an HTML implementation unless a documented exception exists.

## `llms.txt` and Markdown

Machine-friendly formats must preserve language boundaries. Example target behavior:

```text
/llms.txt               -> configured default/global discovery
/en/llms.txt            -> English scope where enabled
/ca/llms.txt            -> Catalan scope where enabled
/es/servicios/seo.md     -> Spanish Markdown representation
/en/services/seo.md      -> English Markdown representation
```

Actual routes are provider-aware and must not create collisions with translated slugs.

## Search, archives and navigation

Search forms, archive links, breadcrumbs, menus, pagination and related-content links must preserve the active locale. A user must not silently fall back to another language because a helper used a non-localized home URL.

## Translation readiness

All customer-facing strings must:

- use WordPress i18n functions;
- use one documented text domain per package;
- avoid concatenating sentences that become impossible to translate naturally;
- include translator comments where placeholders are ambiguous;
- escape output according to context after translation;
- ship EN/ES translations together for project-owned user-facing changes.

## Initial integration priority

1. Native/no multilingual plugin (single-language sites remain valid).
2. WPML adapter.
3. Polylang adapter.
4. WooCommerce multilingual integration in ecommerce phase.

## Multilingual acceptance gates

For any change that affects URLs, metadata, templates or content discovery, test at least default ES and secondary EN fixtures:

- localized route resolves;
- canonical stays in current locale;
- hreflang self + alternate set is correct;
- `x-default` follows configuration;
- `<html lang>` is correct;
- Open Graph locale is correct when emitted;
- Schema `inLanguage` is correct;
- breadcrumbs and internal links preserve language;
- sitemaps/provider integration does not duplicate entries;
- llms/Markdown alternates preserve locale if those features are enabled;
- customer-facing strings exist in EN and ES.
