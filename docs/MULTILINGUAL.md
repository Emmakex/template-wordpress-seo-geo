# Multilingual Architecture

## Contract

Multilingual support is part of the foundation, not a later compatibility patch. ES and EN are first-class project languages. Additional locales such as CA, FR and DE must use the same contract without core rewrites.

Customer-facing functionality ships EN/ES together. Strings must be internationalization-ready from the first implementation.

The product baseline is the **self-contained theme with its embedded runtime**. No multilingual plugin is required to declare the native language set. The Phase 4A configuration contract is defined in `docs/NATIVE_MULTILINGUAL.md`.

## Adapter model

The Core runtime exposes one language service and hides provider-specific APIs behind adapters:

```text
LanguageManager
├── NativeWordPressAdapter
│   └── NativeLanguageConfiguration  (baseline)
├── WPMLAdapter                       (optional future adapter)
└── PolylangAdapter                   (optional future adapter)
```

Other modules never read multilingual plugin APIs or the native WordPress option directly. They request normalized data such as:

- current language/locale;
- default language/locale;
- configured language map;
- translated resource URL once routing/translation relationships exist;
- alternate URLs once hreflang support exists;
- locale-aware home URL;
- whether a translation exists;
- language direction where needed.

Phase 4A intentionally implements only the configuration-facing subset. URL relationships and locale switching are separate microphases so incomplete configuration cannot publish contradictory SEO signals.

## URL strategy

The native runtime must support clean localized URLs, for example:

```text
/es/servicios/seo/
/en/services/seo/
/ca/serveis/seo/
```

Declaring a language in `seo_geo_native_languages` does not create those routes by itself. Native route ownership, request locale switching and translated slug relationships require their own implementation and acceptance gate.

When an optional external multilingual provider is used later, slug translation belongs to that provider/preset strategy. The Core does not invent aliases independently of the authoritative routing system.

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

## Integration priority

1. Native WordPress fallback plus native language configuration, with no multilingual plugin required.
2. Native localized routing, locale switching and reciprocal hreflang acceptance.
3. Optional WPML adapter after the native baseline is complete.
4. Optional Polylang adapter after the native baseline is complete.
5. WooCommerce multilingual integration in the ecommerce phase.

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

Phase 4A has a narrower acceptance gate by design: it proves native monolingual fallback, valid ES/EN configuration and atomic invalid-configuration fallback without changing routes or metadata ownership.
