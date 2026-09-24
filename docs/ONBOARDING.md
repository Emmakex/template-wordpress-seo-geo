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

Status: **complete**

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


### 9C acceptance evidence

Final candidate `f6592459f3da94353b317d4c7529ee262ca43def` passed all eight gates:

- Foundation CI `35891851206`;
- Phase 1 Package CI `35891851157`;
- PHP Quality CI `35891851148` — WPCS + PHPStan level 6;
- WordPress Smoke CI `35891851159`;
- Accessibility & Responsive CI `35891851222`;
- Performance Baseline CI `35891851165`;
- Self-contained Theme CI `35891851408`;
- Native Multilingual CI `35891851154`.

PR #90 was squash-merged as `215f26e336b7772223a955f1284e500b935d19dd`.

Post-merge `main` repeated all eight gates successfully: Foundation `35892343305`, Package `35892343445`, PHP Quality `35892343430`, WordPress Smoke `35892343480`, Accessibility/Responsive `35892343290`, Performance `35892343401`, Self-contained Theme `35892343313` and Native Multilingual `35892343413`.

The runtime acceptance proved explicit entity/GEO normalization, visible-fact authority retention, rejection of invalid address/coordinates/crawler/claim input and unchanged setup state.

## Phase 9D — Theme-owned wizard UI

Status: **complete**

9D turns the validated 9A–9C setup model into one WordPress-native theme wizard. The UI remains a presentation layer over existing validators and does not introduce parallel preset, language, entity or GEO authorities.

The theme now registers **Appearance → SEO/GEO Setup**. Phase 9D is preview-only: submitting the form validates the candidate through `PresetLanguageValidator` and `EntityGeoValidator`; it does not persist setup options.

Delivered:

- built-in key-complete English/Spanish copy selected from the WordPress user locale;
- preset/language, entity and GEO/discovery sections on one native admin screen;
- current theme/Core state prefilled read-only from the same authorities used at runtime;
- nonce + `manage_options` capability enforcement for preview submissions;
- explicit acknowledgement that validation does not save configuration;
- server-side result summary with errors/warnings and normalized preview;
- `aria-live` result region plus automatic focus management after validation;
- responsive admin grid collapsing to one column at the WordPress mobile breakpoint;
- wizard CSS/JS enqueued only on its own admin page;
- no setup option writes, page creation, plugin mutation, cron, external credentials or outbound POSTs.

Self-contained acceptance renders EN/ES, submits a valid nonce-protected preview, verifies assets/capability enforcement and fingerprints protected setup state before/after.

Browser acceptance logs into WordPress admin and exercises the wizard at 320/768/1440 with axe WCAG A/AA, responsive reflow, keyboard navigation, valid preview focus and announced validation errors.

### 9D acceptance evidence

Final candidate `d5ecc25e5ac9506e5c965eef8fd892224218a8cf` passed all eight gates: Foundation `35896877290`, Package `35896877207`, PHP Quality `35896877228`, WordPress Smoke `35896877259`, Self-contained Theme `35896877277`, Native Multilingual `35896877196`, Accessibility/Responsive `35896877471` and Performance `35896877134`.

PR #94 was squash-merged as `a841b3ccee453554e50826176e310c9d7c95310b`.

Post-merge `main` repeated all eight gates successfully: Foundation `35897366497`, Package `35897366553`, PHP Quality `35897366546`, WordPress Smoke `35897366570`, Self-contained Theme `35897366543`, Native Multilingual `35897366527`, Accessibility/Responsive `35897366647` and Performance `35897366562`.

## Phase 9E — Setup execution and generated report

Status: **complete**

9E is the first onboarding microphase allowed to persist configuration. Persistence is validator-backed, atomic at the application layer, idempotent and limited to the existing theme/Core option authorities.

### Execution boundary

`seo_geo_theme_apply_setup( $candidate, $confirmed )` revalidates the complete candidate through the same preset/language and entity/GEO validators used by Preview. A missing explicit apply confirmation fails before any write.

The desired state writes only these authorities:

- `seo_geo_active_preset`;
- `seo_geo_native_languages`;
- `seo_geo_schema_identity`;
- `seo_geo_schema_local_business`;
- `seo_geo_crawler_policy`;
- `seo_geo_llms_txt`;
- `seo_geo_markdown_alternates`;
- `seo_geo_theme_setup_v1`.

All option writes are isolated behind `SetupOptionWriterInterface`. The default `WordPressSetupOptionWriter` verifies read-back after each write. Before mutation, `SetupExecutor` snapshots every affected option plus the setup report. Any failed write restores changed options in reverse order.

No pages, posts, users, plugins, themes, cron jobs or external credentials are created or mutated.

### Idempotency

The executor fingerprints the normalized authority target plus bounded Phase 8 handoff metadata. If all target options already match and the current report has the same configuration fingerprint, execution returns `idempotent=true` without rewriting options or regenerating timestamps.

### Generated setup report

The stable report option is `seo_geo_theme_setup_report_v1`, stored non-autoloaded. It contains:

- configuration/report SHA-256 fingerprints;
- clean vs migrated site mode;
- selected preset;
- language codes/routing/x-default;
- entity type and whether LocalBusiness configuration exists;
- hashes/booleans for GEO/discovery choices;
- bounded migration handoff ID/report hash/disposition/review counts;
- native/external provider IDs and advisory warning codes;
- changed option names;
- explicit safety declarations.

The report never stores LocalBusiness street address, telephone, coordinates, private content, credentials or raw Migration Bridge artifacts.

### Wizard application

Appearance → SEO/GEO Setup now exposes **Preview** and **Apply setup** as distinct actions. Both are nonce/capability-gated. Apply always revalidates server-side and requires a dedicated confirmation checkbox; it does not trust a previous preview.

Self-contained acceptance proves a migrated LocalBusiness setup, stable idempotent rerun, non-autoloaded setup/report options, no page/plugin creation, Migration Bridge class absence and full rollback after an injected mid-write failure.


### 9E acceptance evidence

Final candidate `73a3a2d752241d50d0e3f968bbe0a69cb234bbea` passed all eight gates: Foundation `35937968265`, Package `35937968352`, PHP Quality `35937968217`, WordPress Smoke `35937968337`, Self-contained Theme `35937968239`, Native Multilingual `35937968360`, Accessibility/Responsive `35937968240` and Performance `35937968246`.

PR #98 was squash-merged as `a95d325027a8e787f030e48fd82f4f626a7c3c65`.

Post-merge `main` repeated all eight gates successfully: Foundation `35938267798`, Package `35938267866`, PHP Quality `35938267816`, WordPress Smoke `35938267890`, Self-contained Theme `35938267878`, Native Multilingual `35938267963`, Accessibility/Responsive `35938267901` and Performance `35938267767`.

The runtime acceptance proved atomic option-only setup application, exact rollback after injected mid-write failure, stable idempotent reruns, non-autoloaded privacy-bounded reporting, migrated-site handoff without Migration Bridge runtime dependency and zero required plugins.

## Phase 9F — Onboarding acceptance

Status: **implementation candidate**

9F is the final onboarding acceptance pass. It does not introduce a new product owner or persistence boundary; it consolidates clean-install and migrated-site proof across the existing Phase 9 setup flow.

Acceptance must prove:

- clean-install onboarding from the built self-contained theme;
- migrated-site onboarding using the bounded Phase 8 handoff;
- zero required plugins before, during and after onboarding;
- EN/ES keyboard, focus, axe and responsive behavior;
- Lighthouse performance budgets;
- WPCS, PHPStan level 6, security and static mutation-boundary contracts;
- no operator copy instructs installation of an SEO/GEO plugin;
- the final generated setup report remains non-sensitive and reproducible for an unchanged configuration.


### 9F implementation candidate

The acceptance surface now exercises the production onboarding shape rather than the transitional standalone Core plugin:

- Playwright runs against the built self-contained theme with zero active plugins;
- the browser harness verifies the embedded Core runtime originates from the active theme;
- one desktop acceptance applies a clean EN setup, proves an unchanged second Apply, then confirms the same persisted configuration through the ES UI;
- self-contained smoke runs a clean Corporate setup with no migration handoff and requires `site_mode=clean`;
- the migrated LocalBusiness scenario still consumes the bounded Phase 8 handoff and requires `site_mode=migrated` without loading Migration Bridge;
- both clean and migrated setup paths preserve zero active plugins and stable page counts;
- setup copy explicitly states in EN/ES that no SEO/GEO plugin is required for baseline setup;
- the static setup contract rejects common operator guidance that would instruct installation of an SEO/GEO plugin.
