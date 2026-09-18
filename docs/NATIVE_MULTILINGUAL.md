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

Prefix routing is opt-in. An explicit `x_default` language can also be declared when one translated member is intentionally the catch-all/default hreflang target:

```php
array(
    'default'   => 'es',
    'routing'   => 'prefix',
    'x_default' => 'es',
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
- `x_default` is optional and, when present, must name one configured language; it never defaults implicitly;
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

The native registry uses three server-side post-meta fields:

```text
_seo_geo_translation_group
_seo_geo_language
_seo_geo_translations
```

The third field is the explicit language-to-resource-ID map for the relationship. Every valid member must publish the same normalized map. This avoids global meta searches and makes reciprocity directly verifiable by reading the resources named in the relationship.

A valid relationship requires:

- the current resource is published and belongs to a public, viewable post type;
- at least two distinct published resources are present in the explicit translation map;
- every mapped resource declares the same translation-group identifier;
- every mapped resource declares the language under which its ID appears;
- every language exists in the validated native language configuration;
- every mapped member stores the same normalized translation map;
- one WordPress resource cannot represent two languages in the same relationship;
- attachments, drafts, private resources, malformed group identifiers, unconfigured languages and non-reciprocal maps invalidate the relationship.

`Runtime::translations()` exposes the registry. The returned `NativeTranslationRelationship` contains the group ID, the current resource language and the published translation IDs keyed by language.

Phase 4C1 is deliberately data-only. A valid relationship **does not yet**:

- remove the staged `noindex`;
- emit a localized canonical;
- emit `hreflang` or `x-default`;
- rewrite breadcrumb/internal URLs;
- change Open Graph output.

Those SEO effects are promoted only in the next 4C microphase after the relationship contract has passed real WordPress acceptance.

## Phase 4C2 localized SEO promotion

Phase 4C2 promotes only authoritative singular translation routes from the staged state into indexable localized URLs. Promotion requires all of the following at request time:

1. native prefix routing is enabled;
2. WordPress matched a configured language prefix;
3. the queried singular resource belongs to a valid reciprocal Phase 4C1 relationship;
4. the active route prefix exactly matches the language explicitly assigned to that resource.

When those conditions hold, one shared localized SEO authority provides:

- the localized self-referencing canonical derived from that resource's real WordPress permalink and the validated language prefix;
- reciprocal `hreflang` links for every valid published relationship member, including self;
- `x-default` only when the native language configuration explicitly names its target language;
- Open Graph URL through the same canonical resolver and Open Graph locale through the same validated request locale;
- a safe translated-URL helper that refuses unrelated resources rather than inventing prefixed URLs;
- localized breadcrumb root/current/ancestor links when those resources have valid relationships in the active language;
- normalized current language/locale access through `Runtime::localized_seo()` for later Schema/GEO consumers.

The URL helper uses the **target translated object's own permalink**, so different translated slugs and page hierarchies are preserved. It never assumes that `/es/foo/` and `/en/foo/` are translations because their paths look similar.

Safety rules remain conservative:

- an unprefixed member of a valid translation relationship is `noindex,follow` while prefix routing is authoritative;
- a wrong-language prefix resolving the resource remains `noindex,follow`;
- a prefixed resource with no valid relationship remains `noindex,follow`;
- a relationship containing draft/private/non-reciprocal members cannot promote any member;
- non-authoritative routes emit no native canonical, Open Graph or hreflang output;
- breadcrumb links do not silently fall back to an unprefixed/cross-language post URL when an active localized request lacks a valid translated target.

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
3. a relationship pointing to a draft alternate resolves no relationship;
4. members that do not publish the same reciprocal map resolve no relationship;
5. one WordPress resource cannot be reused for multiple languages;
6. a mapped member assigned to an unconfigured language invalidates the relationship;
7. the built theme still runs the contract with zero active plugins and clean PHP diagnostics.

Phase 4C2 additionally proves:

1. ES and EN members with distinct real slugs/hierarchies resolve under their own language prefixes;
2. each authoritative localized route is indexable and emits exactly one localized self-canonical;
3. reciprocal self + alternate hreflang links use the explicit relationship members;
4. `x-default` is emitted only from the explicitly configured target;
5. Open Graph URL and locale agree with canonical and active request locale;
6. unprefixed, wrong-prefix, unrelated and invalid-relationship routes remain `noindex` and emit no native canonical/hreflang/Open Graph;
7. safe translated-URL lookup returns null for unrelated content;
8. breadcrumb home, ancestor and current links stay inside the active language when valid translations exist;
9. the complete contract runs on WordPress 7.1 / PHP 8.2 with zero active plugins and clean PHP diagnostics.

Accessibility, native SEO and performance regression gates remain required alongside the dedicated multilingual acceptance.
