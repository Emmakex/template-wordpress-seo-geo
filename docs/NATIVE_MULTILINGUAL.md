# Native Multilingual Contract

## Purpose

The built SEO GEO theme must be able to declare and route its language set without requiring WPML, Polylang or another multilingual plugin.

Phase 4A established the server-side language configuration contract. Phase 4B adds explicit native URL-prefix routing and request-locale switching while deliberately keeping newly localized routes out of the search index until translation relationships, localized canonicals and reciprocal `hreflang` are authoritative.

This staged separation prevents a partially configured multilingual site from publishing contradictory SEO signals or presenting duplicated content as if it were a verified translation. Phase 4C begins by adding an explicit translation-relationship registry; SEO promotion remains a separate step.

## WordPress option

The native provider reads one server-side WordPress option:

```text
seo_geo_native_languages
```

Language configuration without routing:

```php
array(
    'default'   => 'es',
    'languages' => array(
        'es' => 'es_ES',
        'en' => 'en_US',
    ),
)
```

Prefix routing is opt-in:

```php
array(
    'default'   => 'es',
    'routing'   => 'prefix',
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
- supported routing modes are `disabled` and `prefix`;
- omitting `routing` is equivalent to `disabled`, preserving Phase 4A behavior and existing installs;
- prefix routing only activates when more than one valid language is configured;
- malformed configuration is rejected atomically rather than partially applied;
- no browser, URL parameter or client-side value can grant or mutate the configured language set.

## Safe fallback

When the option is absent or invalid, native mode remains a normal single-language WordPress site:

- provider: `native`;
- available languages: one entry derived from the active WordPress locale;
- default language: that same entry;
- `is_multilingual()`: `false`;
- native language routing: disabled.

Existing installations therefore do not become multilingual or acquire new public routes merely by updating the theme.

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

`Runtime::language_router()` exposes the native routing state without making the router itself the source of language configuration.

Other Core modules consume these server-side services rather than reading the WordPress option or trusting request parameters directly.

## Phase 4B prefix routing

When `routing` is `prefix`, each configured language code becomes a reserved leading path segment for WordPress public-content routes. With the example configuration:

```text
/es/
/en/
/es/routing-fixture/
/en/routing-fixture/
```

The router duplicates WordPress's existing public rewrite rules under literal language prefixes. The prefix is added outside the original regular expression so existing `$matches[n]` capture numbering remains unchanged.

Global infrastructure endpoints such as REST, robots, favicons, sitemaps, top-level feeds and trackbacks are not intentionally localized by this layer.

### Request authority

The internal query variable is:

```text
seo_geo_lang
```

It is routing metadata, not user authority. The router activates a locale only when:

1. the requested language is present in the validated server-side configuration; and
2. WordPress reports a matched rewrite rule that starts with that literal configured language prefix.

A request such as:

```text
/routing-fixture/?seo_geo_lang=es
```

therefore cannot switch locale by query string alone.

After a valid prefixed rewrite match, the runtime uses WordPress locale switching and keeps `locale` / `determine_locale` helpers aligned with the validated route locale. The matching WordPress language pack should be installed so project and WordPress UI strings can actually load their translations.

### Rewrite lifecycle

Changing routing mode or configured language prefixes changes the rewrite contract. Rewrite rules must be regenerated after such configuration changes. The Phase 4B acceptance uses:

```text
wp rewrite flush --hard
```

A later onboarding/admin layer may own that lifecycle automatically; Phase 4B does not add an admin settings UI.

## Staged SEO safety in Phase 4B

Native prefixed routes are intentionally **not indexable yet**.

For a valid prefixed request whose normal state would otherwise be indexable, the runtime temporarily resolves:

```text
noindex,follow
```

Consequences in the native SEO layer:

- localized prefixed route resolves and renders with the requested locale;
- localized prefixed route emits `noindex`;
- native canonical output is suppressed because the request is not indexable;
- native Open Graph output is suppressed by the same indexability contract;
- unprefixed URLs keep their existing SEO authority and indexability;
- no `hreflang` is emitted in Phase 4B;
- no claim is made that two routes are translations of one another.

WordPress canonical redirection is suppressed for a validated active prefixed route so WordPress does not immediately redirect `/es/...` back to its unprefixed permalink. In prefix-routing mode, a language-shaped first path segment that is not configured (for example `/fr/...` when only `es` and `en` exist) is also treated as part of the reserved language namespace: canonical guessing is suppressed so the unmatched request remains a 404. Non-language-shaped requests retain normal WordPress canonical-redirect behavior.

This is an intentional migration state, not the final multilingual SEO architecture.

## Phase 4C1 explicit translation relationships

Native translations are **distinct WordPress resources**. Merely reaching the same post under multiple language prefixes never creates a translation relationship.

The native registry uses two server-side post-meta fields:

```text
_seo_geo_translation_group
_seo_geo_language
```

A valid relationship requires:

- the current resource is published and belongs to a public, viewable post type;
- at least two published resources share the same explicit translation-group identifier;
- every published group member declares exactly one language that exists in the validated native language configuration;
- no language appears more than once inside the same group;
- attachments, drafts, private resources, malformed group identifiers and unconfigured languages do not become valid members;
- resolving the relationship from any valid member yields the same language-to-resource map, making reciprocity a property of the group rather than a separately inferred link.

`Runtime::translations()` exposes the registry. The returned `NativeTranslationRelationship` contains the group ID, the current resource language and the published translation IDs keyed by language.

Phase 4C1 is deliberately data-only. A valid relationship **does not yet**:

- remove the staged `noindex`;
- emit a localized canonical;
- emit `hreflang` or `x-default`;
- rewrite breadcrumb/internal URLs;
- change Open Graph output.

Those SEO effects are promoted only in the next 4C microphase after the relationship contract has passed real WordPress acceptance.

## What remains for the next multilingual microphase

The following require an explicit translated-resource relationship before localized URLs can become indexable:

- mapping a source object to its real ES/EN translated objects;
- localized canonical URLs;
- reciprocal `hreflang` and optional `x-default`;
- locale-aware breadcrumb/internal URLs;
- locale-aware Open Graph alternates where appropriate;
- language-aware Schema output;
- translated slug relationships;
- optional WPML/Polylang adapters.

Phase 4C must promote only validated translation-linked routes from staged `noindex` to indexable localized URLs. Phase 4C1 first proves the relationship registry; subsequent 4C work owns promotion, localized canonicals and alternates. It must not infer translations merely because the same WordPress object is reachable under two prefixes.

## Acceptance

The self-contained WordPress fixtures run with zero active plugins.

Phase 4A continues to prove:

1. no native language option -> single-language fallback;
2. valid ES/EN option without `routing` -> multilingual language map with routing disabled;
3. malformed duplicate-locale option -> entire configuration rejected and safe single-language fallback.

Phase 4B additionally proves:

1. `routing=prefix` enables the native router only for valid multilingual configuration;
2. the Spanish WordPress language pack can be loaded for the fixture;
3. unprefixed representative content remains authoritative and indexable;
4. `/es/` resolves and switches the HTML language to Spanish;
5. `/es/routing-fixture/` and `/en/routing-fixture/` resolve without being redirected to the unprefixed permalink;
6. prefixed routes emit `noindex` and no native canonical/Open Graph output during the staged state;
7. a query-string language selector cannot activate locale without a matching prefixed rewrite rule;
8. an unconfigured language-shaped prefix such as `/fr/` remains HTTP 404 and is not canonical-redirected to unrelated unprefixed content;
9. runtime/debug logs contain no PHP fatal, warning, notice or uncaught error.

Phase 4C1 additionally proves:

1. a published ES/EN pair with the same explicit group resolves reciprocally from either member;
2. a reachable post without explicit translation metadata resolves no relationship;
3. a group with only one published member because its alternate is draft resolves no relationship;
4. duplicate language membership invalidates the group;
5. a published member assigned to an unconfigured language invalidates the group;
6. the built theme still runs the contract with zero active plugins and clean PHP diagnostics.

Accessibility, native SEO and performance regression gates remain required alongside the dedicated multilingual acceptance.
