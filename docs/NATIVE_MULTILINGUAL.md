# Native Multilingual Contract

## Purpose

The built SEO GEO theme must be able to declare its language set without requiring WPML, Polylang or another multilingual plugin. Phase 4A establishes the server-side configuration contract only. It deliberately does **not** add localized routes, switch request locales, emit `hreflang`, or change canonical/Open Graph URLs yet.

This separation prevents a partially configured multilingual site from publishing contradictory SEO signals.

## WordPress option

The native provider reads one server-side WordPress option:

```text
seo_geo_native_languages
```

Expected shape:

```php
array(
    'default'   => 'es',
    'languages' => array(
        'es' => 'es_ES',
        'en' => 'en_US',
    ),
)
```

Rules:

- `default` must be a configured language code;
- language codes are normalized to lowercase BCP47-like identifiers such as `es`, `en` or `pt-br`;
- each language maps to one safe WordPress locale;
- duplicate locales are rejected;
- malformed configuration is rejected atomically rather than partially applied;
- no browser, URL parameter or client-side value can grant or mutate the configured language set.

## Safe fallback

When the option is absent or invalid, native mode remains a normal single-language WordPress site:

- provider: `native`;
- available languages: one entry derived from the active WordPress locale;
- default language: that same entry;
- `is_multilingual()`: `false`.

Existing installations therefore do not become multilingual merely by updating the theme.

## Normalized runtime API

`LanguageManager` exposes:

- current locale;
- current language code;
- default locale;
- default language code;
- available language-to-locale map;
- locale lookup by language code;
- multilingual state;
- provider identifier.

Other Core modules consume this facade rather than reading the WordPress option directly.

## Phase 4A boundary

Configuration is not routing. In Phase 4A, `NativeWordPressAdapter::current_locale()` continues to report the locale WordPress already resolved for the current execution context. Declaring Spanish as the configured default while WordPress is currently running in `en_US` does not silently switch that request.

The following remain outside 4A and must be implemented with their own acceptance gates:

- `/es/` and `/en/` route ownership;
- locale switching for localized requests;
- translated-resource relationships;
- reciprocal `hreflang` and optional `x-default`;
- localized canonical URLs;
- locale-aware breadcrumb/internal URLs;
- language-aware Schema output;
- WPML/Polylang adapters.

## Acceptance

The self-contained WordPress fixture must prove three states with zero active plugins:

1. no native language option -> single-language fallback;
2. valid ES/EN option -> multilingual language map with Spanish default and English locale lookup, while the current WordPress request locale remains unchanged until routing is implemented;
3. malformed duplicate-locale option -> the entire configuration is rejected and the runtime returns to the single-language fallback instead of partially applying it.

The acceptance fixture must continue to pass native SEO, accessibility and performance regression gates.
