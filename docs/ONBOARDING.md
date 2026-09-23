# Theme onboarding

Phase 9 makes onboarding a theme-owned capability for both clean installations and sites that completed Phase 8 migration.

## Phase 9A — Setup foundation and migration handoff

Status: **complete**

9A is deliberately read-only. It builds a setup plan but does not persist setup choices, create pages, install/activate/deactivate plugins, store external credentials or load Migration Bridge code.

The public setup-plan entrypoint is:

```php
$plan = seo_geo_theme_setup_plan();
```

The plan contains:

- schema version and `theme-setup-plan-read-only` mode;
- clean vs migrated site mode;
- the versioned future setup option contract `seo_geo_theme_setup_v1`;
- all five allowlisted presets and their suggested site-identity metadata;
- current native language configuration from `NativeLanguageConfiguration`;
- accepted Phase 8 handoff metadata from `seo_geo_migration_report_v1` when present;
- current native/external SEO and language provider detection;
- advisory WooCommerce/Elementor/Divi compatibility warnings when active;
- one next-step key for Phase 9B;
- explicit safety flags proving that planning performs no setup mutation.

### Migration handoff

The theme reads `seo_geo_migration_report_v1` directly. It does not load or call Migration Bridge classes.

A handoff is accepted only when:

- envelope schema is version 1;
- report schema is version 1;
- mode is `migration-report`;
- `ready_for_handoff=true`;
- the report explicitly declares `report_is_runtime_dependency=false`;
- envelope and report fingerprints are valid SHA-256 strings.

Only bounded handoff metadata is exposed to onboarding: identifiers/fingerprints, review counts and final bridge disposition.

### Presets and authorities

9A reuses the existing five-preset registry:

- `corporate`;
- `local-business`;
- `publisher`;
- `ecommerce`;
- `saas-digital-product`.

It also reuses the native language and runtime integration authorities. No parallel SEO, Schema, language or integration stack is created.

### Acceptance

Self-contained Theme acceptance runs with zero active plugins and proves both paths:

1. clean install → five-preset native setup plan;
2. synthetic accepted Phase 8 handoff → migrated-site setup plan while Migration Bridge classes are absent.

Protected setup state is fingerprinted before/after planning and must remain unchanged.

### 9A acceptance evidence

Final candidate `f89514cc7051da8917fece779d26a8331cd41a72` passed:

- Foundation CI `35883921399`;
- Phase 1 Package CI `35883921389`;
- PHP Quality CI `35883921378` — WPCS + PHPStan level 6;
- WordPress Smoke CI `35883921384`;
- Self-contained Theme CI `35883921418`;
- Native Multilingual CI `35883921401`;
- Accessibility & Responsive CI `35883921391`;
- Performance Baseline CI `35883921405`.

PR #86 was squash-merged as `17d4cc185addce336a3f2f86079b03dbf00a3468`.

Post-merge `main` repeated all eight gates successfully:

- Foundation CI `35884423588`;
- Phase 1 Package CI `35884423936`;
- PHP Quality CI `35884423746`;
- WordPress Smoke CI `35884423606`;
- Self-contained Theme CI `35884423758`;
- Native Multilingual CI `35884423933`;
- Accessibility & Responsive CI `35884423721`;
- Performance Baseline CI `35884423669`.

The self-contained acceptance proved both clean-install and migrated-site setup planning with zero active plugins, five presets, no Migration Bridge runtime and unchanged protected setup state.

## Phase 9B — Preset and language configuration

Status: **complete**

9B adds validated, explicit preset and native-language configuration on top of the read-only 9A plan. It still performs no persistence; atomic setup writes remain reserved for Phase 9E.

The public validation entrypoint is:

```php
$result = seo_geo_theme_validate_preset_language_setup(
    array(
        'preset'           => 'corporate',
        'default_language' => 'en',
        'languages'        => array(
            'en' => 'en_US',
            'es' => 'es_ES',
        ),
        'routing'          => 'prefix',
        'x_default'        => 'en',
    )
);
```

Validation:

- accepts only the five allowlisted presets;
- delegates language-map validation to `NativeLanguageConfiguration::from_array()`;
- normalizes through the same Core authority later used by runtime;
- rejects duplicate locales, invalid codes/locales, unknown routing modes, invalid default/x-default values and single-language prefix routing;
- reports preset baseline locales that are not configured as advisory warnings rather than fabricating languages;
- warns when a third-party multilingual provider currently owns language behavior;
- never creates translations or routes;
- never persists preset/language options or mutates provider/plugin state.

Self-contained acceptance validates a correct Corporate EN/ES prefix configuration plus unsupported-preset, duplicate-locale and single-language-prefix failures while proving setup options remain unchanged.

### 9B acceptance evidence

Final candidate `bf0f0530204a3ed8e84f575f6fb2b76a380ff6c5` passed:

- Foundation CI `35889610406`;
- Phase 1 Package CI `35889610241`;
- PHP Quality CI `35889610245` — WPCS + PHPStan level 6;
- WordPress Smoke CI `35889610203`;
- Self-contained Theme CI `35889610206`;
- Native Multilingual CI `35889610240`;
- Accessibility & Responsive CI `35889610358`;
- Performance Baseline CI `35889610374`.

PR #88 was squash-merged as `0bc511ccc175b63df842db2b7b9bf181f7c1fda3`.

Post-merge `main` repeated all eight gates successfully:

- Foundation CI `35890113366`;
- Phase 1 Package CI `35890113361`;
- PHP Quality CI `35890113304`;
- WordPress Smoke CI `35890113371`;
- Self-contained Theme CI `35890113329`;
- Native Multilingual CI `35890113300`;
- Accessibility & Responsive CI `35890113277`;
- Performance Baseline CI `35890113320`.

The runtime acceptance proved explicit preset/native-language normalization, invalid preset/locale/routing rejection and unchanged setup state.

## Phase 9C — Entity and GEO configuration

Status: **implementation candidate**

9C validates explicit site-entity and GEO/discovery choices by composing the existing Core authorities. It does not persist options; atomic application remains reserved for Phase 9E.

The public entrypoint is:

```php
$result = seo_geo_theme_validate_entity_geo_setup(
    array(
        'preset'                       => 'local-business',
        'site_entity_type'             => 'local_business',
        'confirm_identity'             => true,
        'local_business'               => array(
            'type'             => 'ProfessionalService',
            'street_address'   => '123 Main Street',
            'address_locality' => 'Barcelona',
            'postal_code'      => '08001',
            'address_country'  => 'ES',
            'latitude'         => '41.38740',
            'longitude'        => '2.16860',
        ),
        'crawler_policy'               => array(
            'oai_searchbot' => 'allow',
            'gptbot'        => 'disallow',
        ),
        'llms_txt_enabled'             => true,
        'markdown_alternates_enabled'  => true,
    )
);
```

### Identity authority

The candidate site entity must be one of the Core-supported explicit identities: `organization` or `local_business`. Identity confirmation is mandatory.

Organization name and URL are not duplicated in onboarding; runtime continues to derive them from the WordPress site title and home URL.

LocalBusiness validation reuses `SchemaLocalBusinessResolver` for address and coordinate normalization. The onboarding result deliberately reports `schema_output_ready=false`: final LocalBusiness Schema remains blocked until the existing runtime visible-fact gate proves the configured address and optional public facts are visible on the public document.

9C only accepts basic LocalBusiness fields. It does not accept review/rating payloads, infer coordinates, infer opening hours or create location content.

### GEO/discovery authority

Crawler choices are validated against `CrawlerPolicyResolver::supported_crawlers()` and its allow/inherit/disallow states, then normalized through `sanitize_configuration()`.

Discovery opt-ins are bound to the existing Core authorities:

- `seo_geo_llms_txt` through `LlmsTxtResolver`;
- `seo_geo_markdown_alternates` through `MarkdownAlternateResolver`;
- provenance remains the existing native eligible-content behavior from `ContentProvenanceResolver`.

These controls do not claim ranking, inclusion, citation, training or crawler behavior guarantees. If WordPress site visibility is non-public, explicit discovery/allow choices are reported as advisory-inactive.

### Safety

Validation performs no option writes, page creation, plugin mutation, content selection, credential access or outbound requests. The self-contained acceptance fingerprints setup/entity/GEO/plugin options before and after valid and invalid validation cases.
