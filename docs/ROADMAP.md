# Roadmap

The roadmap follows the project rule **finish before advancing**. A phase closes only when implementation, required quality gates, acceptance criteria, blockers and documentation are complete.

## Phase 0 — Foundation and contracts

Status: **complete**

Completed:

- repository bootstrap;
- product vision;
- architecture boundaries;
- SEO/GEO specification;
- multilingual contract;
- performance contract;
- accessibility contract;
- preset model;
- engineering rules;
- structured CI/diagnostic contract;
- initial repository skeleton;
- Foundation CI;
- PR #1 + post-merge `main` verification.

## Phase 1 — Minimal installable packages

Status: **complete**

### Microphase 1A — package skeleton and static contract

Status: **complete**

Delivered:

- installable block-theme skeleton;
- valid `theme.json` v3 and theme metadata;
- index/page/single/archive/404 block templates;
- semantic `<main>` landmarks;
- Core plugin bootstrap and namespace autoloader;
- safe activation/deactivation behavior;
- language provider interface/native adapter/LanguageManager;
- runtime integration detector;
- structured Phase 1 package contract.

PR #2 and post-merge package/foundation validation passed.

### Microphase 1B — real WordPress activation smoke

Status: **complete**

Tested baseline:

- WordPress 7.1.x;
- PHP 8.2+;
- WP-CLI 2.12.x;
- MariaDB 11.8.x.

Validated:

- clean WordPress install;
- plugin/theme activation and active state;
- native language + SEO provider service resolution;
- frontend and `/wp-admin/` HTTP paths;
- no project PHP fatal/warning/notice or uncaught runtime error;
- disposable fixture cleanup;
- compatibility matrix in `docs/COMPATIBILITY.md`.

`ERR-2026-001` captured the first WP-CLI Docker integration failure and its regression guard.

### Microphase 1C — WordPress coding standards and static analysis

Status: **complete**

Delivered:

- exact pinned PHP quality dependencies + committed `composer.lock`;
- WPCS 3.4.1 / PHPCS 3.13.6;
- PHPStan 2.2.14 + `phpstan-wordpress` 2.0.4 at level 6;
- WordPress 7.1 stubs;
- project-owned PHP scope;
- no PHPStan baseline or ignored-error list;
- structured first-error extraction for WPCS/PHPStan;
- WordPress snake_case Core API;
- only two documented WPCS filename-convention exceptions required by the namespaced PSR-style class-path contract.

Adoption findings closed:

- WPCS first diagnostic: `seo-geo-core.php:27:30`, source `Universal.NamingConventions.NoReservedKeywordParameterNames.classFound`, signature `e85b79b82c63`;
- PHPStan first diagnostic: `LanguageManager.php:77`, identifier `nullCoalesce.offset`, signature `def4f1ee7592`;
- both were fixed in code rather than suppressed.

PR #4 validation passed and was merged. Post-merge `main` SHA `fff40e24d93a3036f159ef1b7062338c1bc78805` passed:

- Foundation CI run `34913626730`;
- Phase 1 Package CI run `34913626714`;
- WordPress Smoke CI run `34913626741`;
- PHP Quality CI run `34913626770`.

### Phase 1 exit criteria

All exit criteria are satisfied:

- theme installs/activates;
- plugin installs/activates;
- supported runtime is clean;
- WPCS passes;
- PHPStan level 6 passes;
- compatibility documentation matches CI;
- required PR and post-merge validation is green.

Historical note: Phase 1 validated the original two-package bootstrap. The product direction was later tightened in Phase 3 so the distributable baseline becomes a single self-contained theme with zero required plugins.

## Phase 2 — Design system + performance baseline

Status: **complete**

Phase 2 was deliberately split so visual work could not outrun accessibility/performance acceptance.

### Microphase 2A — semantic design system

Status: **complete**

Delivered:

- semantic color tokens;
- automated critical WCAG contrast pairs;
- system-only Sans/Serif font families with no remote font requests;
- stable + bounded fluid typography scale;
- spacing scale;
- border-radius scale;
- curated WordPress editor presets;
- global body/heading/link/button/caption styles via `theme.json`;
- dedicated `Design System CI`;
- `docs/DESIGN_SYSTEM.md`;
- structured PHP lint diagnostics for validator code.

`ERR-2026-002` captured and closed the first Design System validator syntax failure. The invalid foreach destructuring was fixed in code, and `scripts/ci/php-lint-diagnostic.sh` now ensures validator parse failures are structured instead of raw log output.

PR #5 was merged. Post-merge `main` SHA `01928349f8e1ffd19f22d8f098a22c400b3bc2f1` passed:

- Foundation CI run `34914619987`;
- Phase 1 Package CI run `34914619939`;
- Design System CI run `34914619985`;
- PHP Quality CI run `34914620034`;
- WordPress Smoke CI run `34914620027`.

### Microphase 2B — reusable core patterns

Status: **complete**

Delivered:

- native theme `/patterns` registration model;
- hero pattern;
- CTA pattern;
- services/features pattern;
- trust/proof pattern with no fabricated claims;
- FAQ pattern using native Details blocks and no Schema ownership;
- author/profile pattern;
- dependency-free contact pattern;
- translation-ready escaped project copy;
- no reusable H1 ownership;
- semantic design-token consumption;
- no embedded scripts/styles, remote dependencies or third-party blocks;
- `docs/PATTERNS.md`;
- dedicated structured `Pattern Contract CI`;
- real WordPress registry acceptance for all seven patterns.

PR #6 was merged as `80d5f316daf642b754fa9385d161c7bd571ce73d`. Post-merge `main` passed Foundation, Package, Design System, PHP Quality, Pattern Contract and WordPress Smoke; the runtime smoke confirmed **7/7 theme patterns registered**.

### Microphase 2C — responsive + accessibility acceptance

Status: **complete**

Delivered on PR #7:

- representative EN and ES WordPress fixture pages composed from registered theme patterns;
- locked Node 24 browser-test toolchain with Playwright 1.63.0 and axe 4.13.0 integration;
- Chromium acceptance at 320 × 800, 768 × 1024 and 1440 × 900;
- WCAG A/AA automated axe scan without baseline rule suppressions;
- one-banner/one-main/one-contentinfo and single-H1 assertions;
- heading hierarchy assertions;
- horizontal overflow/reflow assertions;
- keyboard reachability and visible `:focus-visible` assertions;
- skip-link activation with required main-target focus;
- reduced-motion assertion;
- WordPress runtime diagnostics;
- semantic native primary navigation instead of the invalid empty Navigation fallback observed in the fixture;
- server-side focusability for the main skip-link target without frontend JavaScript;
- `docs/ACCESSIBILITY_ACCEPTANCE.md`;
- dedicated `Accessibility & Responsive CI`.

PR #7 was squash-merged as `6c64528d58be3192ede8616256cbf43650eb15cb`. Post-merge `main` passed all seven established gates:

- Foundation CI `34919171184`;
- Phase 1 Package CI `34919171310`;
- Design System CI `34919171290`;
- PHP Quality CI `34919171239`;
- Pattern Contract CI `34919171215`;
- WordPress Smoke CI `34919171238`;
- Accessibility & Responsive CI `34919171224`.

The browser gate retained **24/24 passing EN/ES acceptance cases**. Adoption findings with reusable lessons are recorded as `ERR-2026-003`, `ERR-2026-004` and `ERR-2026-005`.

### Microphase 2D — performance baseline and budgets

Status: **complete**

Delivered on PR #8:

- dedicated `Performance Baseline CI`;
- Lighthouse 13.4.1 against disposable WordPress 7.1 / PHP 8.2 fixtures;
- three samples per language with median aggregation;
- representative EN/ES pages reused from the proven Phase 2C content fixture;
- performance score, FCP/LCP/CLS/TBT/Speed Index measurement;
- HTML/CSS/JS/image/total transfer measurement;
- request, third-party-request, project-JS and DOM-size measurement;
- raw Lighthouse JSON retained as workflow evidence;
- structured failure diagnostics;
- authoritative machine-readable enforced budgets in `tests/performance/budgets.json`;
- measured baseline and rationale in `docs/PERFORMANCE_BASELINE.md`.

Reference observation run `34925805108` measured:

- Lighthouse performance: 100 EN / 100 ES;
- LCP median: 653.46 ms EN / 664.39 ms ES;
- CLS: 0 / 0;
- TBT: 0 ms / 0 ms;
- total transfer: 20,063 B / 20,274 B;
- requests: 5 / 5;
- third-party requests: 0 / 0;
- project-owned frontend JS: 0 B / 0 B;
- DOM nodes: 88 / 88.

PR #8 passed all eight PR gates with budgets in `enforce` mode and was squash-merged as `d34f04b763eef372b0ef73c93f30cbfb3d722713`. Post-merge `main` passed all eight gates:

- Foundation CI `34926357735`;
- Phase 1 Package CI `34926357777`;
- Design System CI `34926357715`;
- PHP Quality CI `34926357745`;
- Pattern Contract CI `34926357760`;
- WordPress Smoke CI `34926357714`;
- Accessibility & Responsive CI `34926357742`;
- Performance Baseline CI `34926357784`.

The enforced base budgets remain the authoritative regression contract; field Core Web Vitals still require production field evidence and are not inferred from Lighthouse lab timings.

### Phase 2 exit criteria

All exit criteria are satisfied:

- reusable design tokens and core patterns are complete;
- responsive/UX acceptance passes in ES+EN;
- keyboard/focus/reduced-motion checks pass;
- performance baseline is recorded and guarded;
- numeric budgets are derived from representative pages rather than guessed thresholds;
- Phase 2D PR and post-merge verification are green;
- Phase 2 documentation reflects the final verified state.

## Phase 3 — Native SEO foundation + self-contained packaging

Status: **complete**

Phase 3 established one overriding product invariant: **a clean WordPress installation plus the built theme provides the baseline SEO/GEO behavior with zero required plugins**.

### Microphase 3A — native SEO authority and core signals

Status: **complete**

Delivered on PR #10:

- server-authoritative `SeoOutputAuthority` for canonical, meta description and robots;
- explicit `IndexabilityResolver` with `indexable`, `noindex-follow`, `noindex-nofollow`, `redirect`, `not-found` and `410-gone` states;
- canonical resolution for native indexable HTML;
- native meta-description resolution with conservative source rules and a documented product length ceiling;
- page-level robots policy through WordPress's `wp_robots` filter rather than a second independent robots renderer;
- removal/replacement of WordPress Core's `rel_canonical` callback only while native Core owns canonical output;
- real WordPress smoke assertions for exactly one canonical, exactly one expected description and `noindex,follow` search behavior;
- no frontend JavaScript;
- public contract in `docs/NATIVE_SEO.md`;
- Foundation CI now requires the native SEO contract.

PR #10 passed its six required gates and was squash-merged as `405e2bc13ccc33dea6bcef99fe1342faa18b2fcb`. Post-merge `main` passed all six gates triggered by the change:

- Foundation CI `34929248429`;
- Phase 1 Package CI `34929248493`;
- PHP Quality CI `34929248627`;
- WordPress Smoke CI `34929248475`;
- Accessibility & Responsive CI `34929248621`;
- Performance Baseline CI `34929248399`.

Adoption findings were fixed in code rather than suppressed:

- WPCS `MetaDescriptionResolver.php:70`, source `Universal.Operators.DisallowShortTernary.Found`, signature `738b94d70c77`;
- PHPStan `CanonicalResolver.php:44`, identifier `function.alreadyNarrowedType`, signature `50c0653ad762`.

### Microphase 3B — self-contained native SEO/GEO runtime

Status: **complete**

Delivered on PR #13:

- context-neutral `SeoGeo\Core\Runtime` extracted from the transitional plugin wrapper;
- theme-owned runtime bootstrap under `inc/seo-geo-core/bootstrap.php`;
- single-theme build through `scripts/build-theme-package.sh`;
- reusable Core source embedded into the distributable theme under `inc/seo-geo-core/src`;
- standalone Core plugin wrapper demoted to optional compatibility/development packaging;
- dedicated `Self-contained Theme CI`;
- real WordPress 7.1 / PHP 8.2 acceptance with **zero active plugins**;
- explicit assertion that `wp-content/plugins/seo-geo-core` is absent;
- reflection assertion proving `SeoGeo\Core\Runtime` is loaded from the built theme bundle;
- native SEO authority preserved without an SEO plugin;
- exactly one canonical and one expected meta description on an indexable fixture;
- exactly one search robots meta containing `noindex`;
- clean PHP runtime/debug logs;
- Foundation contract hardened so the embedded bootstrap, Runtime, build script and zero-plugin workflow cannot silently disappear.

PR #12 explored Yoast/Rank Math/AIOSEO interoperability but was intentionally closed unmerged after the product direction was clarified. Third-party provider compatibility is optional and outside the baseline critical path.

PR #13 passed all nine PR gates and was squash-merged as `a320cd3033e2a5ea0fbcc83dffac500a7eaf8c88`. Post-merge `main` passed all nine gates:

- Accessibility & Responsive CI `35257573280`;
- Self-contained Theme CI `35257573577`;
- Design System CI `35257573373`;
- Foundation CI `35257573446`;
- WordPress Smoke CI `35257573636`;
- Pattern Contract CI `35257573645`;
- Performance Baseline CI `35257573410`;
- PHP Quality CI `35257573415`;
- Phase 1 Package CI `35257573523`.

The dedicated zero-plugin post-merge run `35257573577` proves the distribution invariant on real WordPress rather than inferring it from repository layout. The first adoption run exposed a CI-harness namespace-escaping defect, recorded as `ERR-2026-006`; the runtime itself did not require a product fix.

### Microphase 3C — native discovery metadata

Status: **complete**

Delivered on PR #15:

- native `OpenGraphResolver` under the same single-owner SEO authority model;
- `og:title`, `og:type`, `og:url`, `og:site_name`, `og:description` and `og:locale` for indexable public HTML;
- `og:url` reuses the canonical resolver and `og:description` reuses the native description resolver;
- `og:image` only when a real featured image or WordPress site icon exists, with no fabricated placeholder;
- no native Open Graph output for the noindex search fixture;
- reusable data-only `BreadcrumbResolver` exposed through `Runtime::breadcrumbs()` for later visible UI and `BreadcrumbList` Schema reuse;
- no Schema graph ownership and no Twitter/X-card ownership added in this phase;
- Foundation contract expanded for the discovery metadata layer;
- zero-plugin WordPress smoke expanded to validate Open Graph and breadcrumb behavior in the built theme.

PR #15 passed all seven workflows triggered by the final candidate and was squash-merged as `12a0b4a75b2980ad29211b474a6277ec30ea23ac`. Post-merge `main` passed all seven triggered gates:

- Self-contained Theme CI `35262006328`;
- WordPress Smoke CI `35262006347`;
- Accessibility & Responsive CI `35262006358`;
- PHP Quality CI `35262006369`;
- Phase 1 Package CI `35262006374`;
- Foundation CI `35262006388`;
- Performance Baseline CI `35262006444`.

The zero-plugin acceptance proves the built theme preserves canonical/meta-description/robots ownership while adding native Open Graph and root + current-item breadcrumb data. The WP-CLI query-scope fixture defect found during adoption is recorded as `ERR-2026-007`; it was fixed in the test harness rather than hidden with a suppression.

### Phase 3 exit criteria

All exit criteria are satisfied:

- built distribution is one installable theme;
- zero active plugins are required in the baseline acceptance fixture;
- canonical/robots/meta-description output remains single-owner and deterministic;
- Open Graph baseline follows the native ownership model;
- breadcrumb data has one reusable contract;
- performance/accessibility budgets remain green after embedding and extending the runtime;
- optional compatibility code is not required for the product to boot;
- Phase 3 implementation and post-merge verification are green on `main`.

## Phase 4 — Native multilingual core

Status: **complete**

Phase 4 establishes a native multilingual baseline with zero required multilingual plugins. Language configuration, prefixed routing, explicit reciprocal translation relationships and localized SEO promotion now share one server-authoritative contract.

### Microphase 4A — native language configuration contract

Status: **complete**

Delivered on PR #17:

- server-authoritative `NativeLanguageConfiguration` backed by `seo_geo_native_languages`;
- safe monolingual fallback to the active WordPress locale;
- atomic rejection of malformed or duplicate-locale configurations;
- normalized current/default language codes, language map and locale lookup through `LanguageManager`;
- zero-plugin ES/EN fixture on WordPress 7.1 / PHP 8.2;
- dedicated `Native Multilingual CI`;
- no route, canonical or `hreflang` changes in this configuration-only microphase.

PR #17 was squash-merged as `b313b6d2b6f7f111c45725c71919c1f48fea3e90`. Post-merge `main` passed all eight triggered gates:

- Foundation CI `35267813403`;
- Phase 1 Package CI `35267813439`;
- PHP Quality CI `35267813460`;
- WordPress Smoke CI `35267813453`;
- Self-contained Theme CI `35267813454`;
- Native Multilingual CI `35267813409`;
- Accessibility & Responsive CI `35267813412`;
- Performance Baseline CI `35267813446`.

### Microphase 4B — staged native prefix routing and request locale

Status: **complete**

Delivered on PR #18:

- opt-in `routing=prefix` mode with disabled-by-default backward compatibility;
- native ES/EN prefixed WordPress rewrites without changing existing `$matches[n]` capture numbering;
- validated route authority: query-string language values cannot activate locale by themselves;
- request-locale switching for matched configured prefixes;
- HTML `lang` alignment with the validated route locale;
- protection against WordPress canonical redirects stripping valid prefixes;
- reserved language-shaped namespace so unconfigured prefixes such as `/fr/` remain 404 instead of being guessed into unrelated canonical redirects;
- staged `noindex,follow` for localized prefixed routes until translation relationships exist;
- suppression of native canonical and Open Graph output for those staged routes through the existing indexability contract;
- unprefixed routes remain authoritative and indexable;
- dedicated real-HTTP zero-plugin routing smoke covering `/es/`, `/en/`, query-selector abuse and unknown prefixes;
- no `hreflang`, translated slug inference or fabricated translation relationship.

PR #18 passed all eight PR gates on final candidate `f6d4044018ced5e57b1d464815085dc6566ff6be` and was squash-merged as `ae799522e6ac27e2b77ae911d7160095e0ce2e7b`. Post-merge `main` passed all eight gates again:

- Foundation CI `35299884158`;
- Phase 1 Package CI `35299884173`;
- PHP Quality CI `35299884122`;
- WordPress Smoke CI `35299884170`;
- Self-contained Theme CI `35299884212`;
- Native Multilingual CI `35299884357`;
- Accessibility & Responsive CI `35299884124`;
- Performance Baseline CI `35299884131`.

Functional adoption findings are recorded as `ERR-2026-008` and `ERR-2026-009`. WPCS alignment and the PHPStan redundant type guard were fixed in code without suppressions.

### Microphase 4C — explicit translation relationships + indexable localized SEO

Status: **complete**

#### Microphase 4C1 — explicit reciprocal translation relationship contract

Status: **complete**

Delivered on PR #21:

- immutable `NativeTranslationRelationship` data contract;
- `NativeTranslationRegistry` exposed through `Runtime::translations()`;
- three explicit server-side post-meta fields for group, current language and reciprocal language-to-resource map;
- distinct WordPress resources required for distinct translations;
- every mapped member must be published, public/viewable, language-configured and assigned to the same group;
- every member must publish the same normalized translation map, making reciprocity directly verifiable;
- one resource cannot represent two languages in one relationship;
- drafts, private resources, malformed groups, unconfigured languages and non-reciprocal maps are rejected;
- no global `meta_key/meta_value` discovery query; validation reads only the explicitly mapped IDs;
- dedicated zero-plugin WordPress relationship acceptance added after the existing 4A/4B regressions;
- no indexability, canonical, Open Graph or `hreflang` promotion in 4C1.

PR #21 passed all eight required PR gates on final candidate `91fad9109456b72e622408ad6a3a44e8db436975` and was squash-merged as `bd5729ba6d785dff09d6176f0e92b2c45a5e0dba`. Post-merge `main` passed all eight gates again:

- Foundation CI `35301930887`;
- Phase 1 Package CI `35301930901`;
- PHP Quality CI `35301930866`;
- WordPress Smoke CI `35301930911`;
- Self-contained Theme CI `35301930854`;
- Native Multilingual CI `35301930897`;
- Accessibility & Responsive CI `35301930860`;
- Performance Baseline CI `35301930882`.

The first registry implementation triggered WPCS slow-meta-query signature `70735d839b0c`. It was redesigned rather than suppressed; the reusable performance lesson is recorded as `ERR-2026-010`.

#### Microphase 4C2 — localized SEO promotion

Status: **complete**

Delivered on PR #23:

- single `LocalizedSeoResolver` authority for route language, reciprocal relationship validation and localized URL resolution;
- promotion from staged `noindex,follow` to indexable only when the current singular resource has a valid reciprocal relationship and the active prefix matches its explicit language;
- localized self-referencing canonical URLs based on each translated resource's real WordPress permalink, preserving different slugs and hierarchies;
- reciprocal `hreflang` alternates for valid published relationship members;
- optional `x-default` only when an explicit configured language is supplied;
- absolute HTTP(S) validation for filtered hreflang URLs before presentation;
- Open Graph URL and locale aligned with the same localized canonical/request authority;
- safe translated-URL lookup that refuses unrelated resources instead of inventing paths;
- localized breadcrumb home, ancestor and current links without cross-language fallback;
- `Runtime::localized_seo()` exposed for later Schema/GEO consumers;
- non-authoritative routes — unprefixed translation members, wrong-language prefixes, missing/invalid relationships — remain `noindex,follow` and emit no native canonical, hreflang or Open Graph;
- real HTTP WordPress 7.1 / PHP 8.2 / zero-plugin acceptance using distinct ES/EN slugs and hierarchies.

PR #23 passed all eight required PR gates on final candidate `c77dd840b4b1bb6d5f2838eda2c630e42b4bd51e` and was squash-merged as `95a416e835e0d7490c70342d0acc053ed8276f81`. Post-merge `main` passed all eight gates again:

- Foundation CI `35304371532`;
- Phase 1 Package CI `35304371553`;
- PHP Quality CI `35304371657`;
- WordPress Smoke CI `35304371564`;
- Self-contained Theme CI `35304371524`;
- Native Multilingual CI `35304371584`;
- Accessibility & Responsive CI `35304371592`;
- Performance Baseline CI `35304371517`.

The final review hardened the public hreflang-filter contract so relative/non-HTTP(S) values cannot be emitted as alternate URLs. The candidate was fully revalidated after that correction.

Optional WPML/Polylang adapters remain outside the native baseline critical path and are not required dependencies.

### Phase 4 exit criteria

All exit criteria are satisfied:

- reciprocal valid alternates exist in the native baseline;
- every indexable localized singular page has the correct current-language canonical;
- HTML language and Open Graph language/URL signals agree with the active language authority;
- locale-aware breadcrumbs/internal URL helpers do not cross languages accidentally;
- invalid, missing, draft or private translations never appear as indexable alternates;
- explicit `x-default` does not appear unless configured;
- the complete ES/EN multilingual acceptance runs with zero active plugins;
- PHP quality, accessibility and performance gates remain green after localized SEO promotion.

## Phase 5 — Schema graph + entities

Status: **complete**

Phase 5 extends the same plugin-free, single-owner runtime with one coherent Schema.org JSON-LD graph. Nodes must reuse existing canonical, indexability, language and relationship authorities instead of reconstructing SEO state independently.

### Microphase 5A — native graph core + stable WebSite/WebPage IDs

Status: **complete**

Delivered on PR #25:

- explicit `schema` signal in `SeoOutputAuthority`;
- deterministic `SchemaNodeIds` based on authoritative public URLs;
- native `SchemaGraphBuilder` with one `@context=https://schema.org` + `@graph` payload;
- baseline `WebSite` and `WebPage` nodes;
- `WebPage.url` sourced from the existing canonical resolver;
- `WebPage.isPartOf` linked to the stable WebSite node;
- `inLanguage` sourced from the active WordPress locale through `LanguageManager` and normalized to BCP 47 form;
- graph omission for non-indexable requests through the existing indexability authority;
- one `SchemaPresenter` registered only when native Core owns Schema output;
- `Runtime::schema_graph()` for later entity-node reuse;
- zero frontend JavaScript;
- public contract in `docs/NATIVE_SCHEMA.md`;
- Foundation guardrails for the Schema sources and contract;
- self-contained zero-plugin acceptance for parseable JSON-LD, exact node count/types, deterministic IDs, canonical alignment and noindex suppression;
- localized ES/EN acceptance proving `inLanguage=es-ES` / `en-US` and no Schema on non-authoritative staged translation routes.

PR #25 passed all eight required gates on final candidate `6e6c3a7915a4799f98efe2cd762260176f0a3ee9` and was squash-merged as `281fbf7b6edbecc30832018431b78d667cf553d2`. Post-merge `main` passed all eight gates again:

- Foundation CI `35309620096`;
- Phase 1 Package CI `35309620132`;
- PHP Quality CI `35309620086`;
- WordPress Smoke CI `35309620184`;
- Self-contained Theme CI `35309620077`;
- Native Multilingual CI `35309620064`;
- Accessibility & Responsive CI `35309620296`;
- Performance Baseline CI `35309620030`.

No third-party Schema plugin is required.

### Microphase 5B — shared Organization / Person identity + ProfilePage

Status: **complete**

Delivered on PR #28:

- `SchemaIdentityResolver` as the server-side identity data authority for Schema graph construction;
- explicit opt-in Organization identity through `seo_geo_schema_identity.site_entity_type=organization`;
- Organization name and URL sourced only from the visible WordPress site name and authoritative home URL;
- stable Organization ID `{home_url}#organization`;
- front-page `WebSite.publisher` reference to the Organization;
- no inferred legal name, address, telephone, logo, social profile, tax identifier or other unsupported Organization properties;
- native WordPress author archives specialized from `WebPage` to `ProfilePage`;
- stable Person ID `{author_url}#person`;
- Person name, URL and optional biography sourced from the real WordPress author identity;
- reciprocal `ProfilePage.mainEntity` and `Person.mainEntityOfPage` references;
- ordinary posts retain the Phase 5A two-node baseline and do not receive Person nodes merely because they have an author;
- one JSON-LD script and one native graph owner preserved;
- Foundation contract expanded for the identity resolver;
- self-contained zero-plugin acceptance for baseline posts, Organization home identity and Person/ProfilePage author identity.

PR #28 passed all eight required PR gates on final candidate `471774b3d3992dee4155e5a6e0b40127d0611256` and was squash-merged as `2c8bf02b27d97768006f60a5258dc8ab1bc56266`. Post-merge `main` passed all eight gates again:

- Phase 1 Package CI `35311162368`;
- Foundation CI `35311162374`;
- PHP Quality CI `35311162405`;
- WordPress Smoke CI `35311162366`;
- Self-contained Theme CI `35311162371`;
- Native Multilingual CI `35311162393`;
- Accessibility & Responsive CI `35311162421`;
- Performance Baseline CI `35311162398`.

The acceptance remains plugin-free and preserves multilingual, accessibility and performance contracts. A recurrence of the known WP-CLI namespace-escaping fixture class was fixed in the test harness and recorded under `ERR-2026-006`; production Schema code did not require a workaround.

### Microphase 5C — native BlogPosting + author/publisher linkage

Status: **complete**

Delivered on PR #30:

- `SchemaArticleResolver` for singular, published built-in WordPress posts only;
- no automatic Article inference for pages, archives or arbitrary custom post types;
- stable BlogPosting ID `{canonical_url}#article`;
- reciprocal `WebPage.mainEntity` and `BlogPosting.mainEntityOfPage` references;
- `headline` from the visible WordPress post title;
- `datePublished` and `dateModified` from WordPress date-time APIs in ISO 8601 form;
- `inLanguage` reused from the existing language authority;
- article `author` linked only to a real WordPress user identity and reusing the stable Person ID from Phase 5B;
- article `publisher` emitted only when the site explicitly opted into the existing Organization identity;
- no fabricated image, publisher, keywords, section or NewsArticle classification;
- the same single native `@graph` and Schema owner preserved;
- Foundation contract expanded for `SchemaArticleResolver`;
- self-contained zero-plugin acceptance expanded for BlogPosting, Person and Organization references;
- public contract expanded in `docs/NATIVE_SCHEMA.md`.

PR #30 passed all eight required PR gates on final candidate `e14c4a98045d37f020348a509a13cd52486f5c9b` and was squash-merged as `744be92a3d69bad1a4711291c6be162273cd416f`. Post-merge `main` passed all eight gates again:

- Phase 1 Package CI `35315585281`;
- Foundation CI `35315585359`;
- PHP Quality CI `35315585319`;
- WordPress Smoke CI `35315585367`;
- Self-contained Theme CI `35315585378`;
- Native Multilingual CI `35315585405`;
- Accessibility & Responsive CI `35315585338`;
- Performance Baseline CI `35315585374`.

The acceptance remains plugin-free. The first BlogPosting fixture omitted an explicit post author under WP-CLI and therefore correctly produced no Person/author relationship instead of fabricating one. The fixture was fixed by assigning a real WordPress author, and the reusable lesson is recorded as `ERR-2026-011`.

### Microphase 5D — native BreadcrumbList from shared breadcrumb authority

Status: **complete**

Delivered on PR #32:

- `SchemaBreadcrumbResolver` as a Schema-only adapter over the existing reusable `BreadcrumbResolver`;
- no duplicate hierarchy, translated-slug or navigation discovery inside the Schema layer;
- stable BreadcrumbList ID `{canonical_url}#breadcrumb`;
- `WebPage.breadcrumb` reference to that stable node inside the same native `@graph`;
- ordered `ListItem` entries with sequential positions, existing breadcrumb labels and existing public URLs;
- at least two breadcrumb items required before markup is emitted;
- absolute HTTP(S) URL validation for breadcrumb items;
- non-final items require a valid URL; the final item may omit `item` only when the breadcrumb authority has no current-page URL;
- incomplete intermediate hierarchy suppresses the whole BreadcrumbList rather than fabricating a partial path;
- front-page one-item breadcrumb data does not produce BreadcrumbList markup;
- Foundation contract expanded for the new Schema resolver;
- self-contained zero-plugin acceptance expanded for WebPage/BreadcrumbList linkage, stable IDs, item order and the front-page negative fixture;
- public contract expanded in `docs/NATIVE_SCHEMA.md`.

PR #32 passed all eight required PR gates on final candidate `a6d410815b825386238b54573ac222c14fae953e` and was squash-merged as `51cfffb584d6fbf505ab08a7bf86eca95174e107`. Post-merge `main` passed all eight gates again:

- Phase 1 Package CI `35329966170`;
- Foundation CI `35329965927`;
- PHP Quality CI `35329965996`;
- WordPress Smoke CI `35329965932`;
- Self-contained Theme CI `35329965975`;
- Native Multilingual CI `35329965943`;
- Accessibility & Responsive CI `35329965938`;
- Performance Baseline CI `35329965901`.

The acceptance remains plugin-free and keeps one native JSON-LD graph owner. Fixture node-count drift found during adoption is recorded as `ERR-2026-012`; production BreadcrumbList output did not require a workaround.

### Microphase 5E — native LocalBusiness identity

Status: **complete**

Delivered on PR #34:

- explicit `local_business` site-identity selection, mutually exclusive with generic Organization;
- one stable `{home_url}#localbusiness` node in the existing native graph;
- required physical PostalAddress contract before LocalBusiness output;
- conservative supported subtype allowlist with safe fallback to generic `LocalBusiness`;
- validated optional telephone, price range, GeoCoordinates and OpeningHoursSpecification;
- front-page `WebSite.publisher` + `WebPage.mainEntity` linkage to the same LocalBusiness node;
- BlogPosting publisher reuse without duplicate Organization identity;
- zero-plugin positive acceptance for a typed physical business;
- zero-plugin negative acceptance proving incomplete required address data suppresses the LocalBusiness node;
- no inferred reviews, ratings, images, service areas or additional locations;
- Foundation guardrail for the native LocalBusiness resolver;
- public contract updates in `docs/NATIVE_SCHEMA.md` and `docs/PRESETS.md`.

PR #34 passed all ten PR workflows on final candidate `ff43b78a2eaaffd9a68b35baec8262ec0b7066f2` and was squash-merged as `6017c1e6c810478832a6413faa88d9853f4f3261`.

Post-merge `main` passed all ten workflows again:

- Design System CI `35333717672`;
- Pattern Contract CI `35333717636`;
- Foundation CI `35333717628`;
- Phase 1 Package CI `35333717718`;
- Native Multilingual CI `35333717698`;
- Performance Baseline CI `35333717629`;
- Accessibility & Responsive CI `35333717703`;
- WordPress Smoke CI `35333717689`;
- PHP Quality CI `35333717675`;
- Self-contained Theme CI `35333717649`.

Adoption findings were corrected without weakening contracts:

- WPCS alignment signature `3e90140896ff`;
- PHPStan return-contract signature `82719cd07023`;
- WP-CLI namespace-escaping recurrence signature `9505c31710a7`, recorded under `ERR-2026-006`.

### Microphase 5F — visible-content consistency + negative entity fixtures

Status: **complete**

Delivered on PR #36:

- reusable server-side `SchemaVisibleContentResolver` for conservative visible-content decisions;
- LocalBusiness requires a published static front page whose authored text visibly contains street, locality and postal code, plus region when configured;
- telephone and price range emit only when their configured values are visibly present;
- opening-hours entries require an explicit `visible_text` counterpart that occurs in public page copy;
- GeoCoordinates remain conditional on structurally valid coordinates after the physical-address visibility contract passes;
- hidden required address facts suppress the complete LocalBusiness node;
- unsupported business subtype falls back safely to generic `LocalBusiness`;
- LocalBusiness entity/publisher data no longer propagates onto BlogPosting pages where physical business facts are not visible;
- stored author biography is omitted from Person Schema while the native author archive does not expose that biography;
- zero-plugin negative fixtures cover hidden optional values, invalid coordinates, unsupported subtype, hidden required locality and cross-page entity leakage;
- built-theme acceptance now explicitly requires both LocalBusiness and visible-content resolver sources;
- public Schema/preset contracts document the visibility invariant.

PR #36 passed all ten PR workflows on final candidate `15a912454ca8c2479a048b92ba864dd2b1bd9bae` and was squash-merged as `53934f6f1aa61cb9df23865173c7e647a3e7b651`.

Post-merge `main` passed all ten workflows again:

- Phase 1 Package CI `35336316909`;
- Pattern Contract CI `35336316889`;
- Foundation CI `35336316975`;
- Design System CI `35336316899`;
- PHP Quality CI `35336316893`;
- WordPress Smoke CI `35336316956`;
- Accessibility & Responsive CI `35336316881`;
- Self-contained Theme CI `35336316835`;
- Performance Baseline CI `35336316892`;
- Native Multilingual CI `35336316966`.

The first candidate exposed only a WPCS assignment-alignment issue, signature `f17836464731`; it was corrected without changing behavior. PHPStan level 6 and the full zero-plugin runtime acceptance then passed.

### Phase 5 exit criteria

All Phase 5 exit criteria are satisfied:

- JSON-LD remains parseable across supported page/entity types;
- deterministic node IDs are reused instead of duplicated;
- exactly one native graph owner exists in the baseline fixture;
- WebSite/WebPage plus the planned identity/article/breadcrumb/local-business nodes are complete;
- language tests pass for locale-aware graph fields;
- visible-content consistency invariants pass with positive and negative zero-plugin fixtures;
- LocalBusiness support is complete for the native baseline needed by its later preset;
- no Schema plugin is required;
- required PR and post-merge validation is green on `main`.

## Phase 6 — GEO / agent-friendly layer

Status: **complete**

### Microphase 6A — explicit OpenAI crawler policy

Status: **complete**

Delivered on PR #39:

- server-side `seo_geo_crawler_policy` configuration contract;
- independent `inherit|allow|disallow` states for OAI-SearchBot and GPTBot;
- no crawler-specific directives by default;
- WordPress virtual `robots.txt` integration without replacing existing output;
- generated crawler block is idempotent and explicitly owned;
- global `blog_public=0` privacy takes precedence over crawler-specific allows;
- malformed, inherited and unknown crawler configuration emits no native group;
- `Runtime::crawler_policy()` exposes the crawler-policy authority for later UI/reporting;
- zero-plugin HTTP acceptance proves search/training controls are not coupled;
- distributable-theme acceptance requires both crawler-policy source files;
- `docs/GEO_CRAWLERS.md` records the contract, primary references and no-ranking/no-inclusion guarantee.

PR #39 passed all ten PR workflows on final candidate `47216b37bfcbd692a3f2733e56df43eb2d12a272` and was squash-merged as `da1c98d89771c34d0c9ad86e869b86fa4dab7ebb`.

PR validation:

- Foundation CI `35350353412`;
- Pattern Contract CI `35350353435`;
- PHP Quality CI `35350353447`;
- WordPress Smoke CI `35350353333`;
- Self-contained Theme CI `35350353324`;
- Phase 1 Package CI `35350353330`;
- Design System CI `35350353283`;
- Accessibility & Responsive CI `35350353323`;
- Native Multilingual CI `35350353276`;
- Performance Baseline CI `35350353356`.

Post-merge `main` passed all ten workflows again:

- Foundation CI `35350709389`;
- Pattern Contract CI `35350709402`;
- Phase 1 Package CI `35350709489`;
- Design System CI `35350709433`;
- WordPress Smoke CI `35350709495`;
- PHP Quality CI `35350709657`;
- Self-contained Theme CI `35350709442`;
- Accessibility & Responsive CI `35350709369`;
- Native Multilingual CI `35350709466`;
- Performance Baseline CI `35350709371`.

The first candidate exposed only the reserved-keyword parameter-name WPCS finding `27da5fd524a3`; the parameter was renamed without behavioral changes and the full matrix then passed. The reusable lesson is recorded as `ERR-2026-013`.

### Microphase 6B — optional llms.txt generator

Status: **complete**

Delivered on PR #41:

- disabled-by-default virtual site-root `/llms.txt` endpoint;
- llms.txt v2-compatible H1, optional summary and curated H2 link sections;
- explicit post-ID curation only — no automatic database enumeration;
- published/public/viewable/non-password-protected resource validation;
- same-host absolute URL validation and cross-section deduplication;
- global WordPress privacy disables the endpoint;
- translated resources reuse reciprocal relationship data and authoritative localized ES/EN URLs;
- staged/unprefixed translation URLs are excluded from generated output;
- GET/HEAD support with plain-text response and no physical file or rewrite flush;
- `Runtime::llms_txt()` exposes the resolver for later admin/UI work;
- zero-plugin acceptance covers disabled 404, public curation, duplicate suppression, private/draft/password leakage prevention and site-privacy precedence;
- multilingual acceptance proves authoritative ES/EN prefixed URLs;
- Foundation requires both LLMS source files plus `docs/LLMS_TXT.md`;
- documentation positions llms.txt as an optional interoperability convention, not a crawler permission, ranking signal or inclusion guarantee.

PR #41 passed all ten workflows on final candidate `55cd18815e5514709c263dd73ae3bc554e5764d3` and was squash-merged as `27e4b5a1fb657cbe6ea1dbfb71edd6aa448c56bf`.

Post-merge `main` passed all ten workflows again:

- Foundation CI `35353585312`;
- Phase 1 Package CI `35353585151`;
- Design System CI `35353585177`;
- Pattern Contract CI `35353585144`;
- PHP Quality CI `35353585197`;
- Self-contained Theme CI `35353585261`;
- WordPress Smoke CI `35353585174`;
- Accessibility & Responsive CI `35353585149`;
- Native Multilingual CI `35353585306`;
- Performance Baseline CI `35353585365`.

The first candidates exposed only PHPDoc/WPCS issues in the new LLMS classes; no functional failure was found. The reusable lesson is recorded as `ERR-2026-014`.

### Microphase 6C — localized Markdown alternates

Status: **complete**

Delivered on PR #43:

- disabled-by-default `seo_geo_markdown_alternates` contract;
- authoritative Markdown URLs derived from the public HTML URL;
- directory-style resources use `index.md`; non-directory URLs append `.md`;
- authoritative HTML advertises `rel="alternate" type="text/markdown"`;
- HTML and Markdown advertise `rel="describedby"` to llms.txt when Phase 6B is enabled;
- equivalent HTTP Link discovery headers;
- Markdown responses link back to the authoritative HTML source;
- conservative block-to-Markdown conversion without shortcode or dynamic-block execution;
- public-resource, password and global WordPress privacy guards;
- reciprocal ES/EN relationship enforcement;
- staged unprefixed and wrong-prefix translation routes expose no Markdown;
- llms.txt prefers valid localized Markdown URLs when Phase 6C is enabled;
- GET/HEAD support with `text/markdown` and no physical files or rewrite flush;
- `Runtime::markdown_alternates()` exposes the resolver for later administration/reporting;
- zero-plugin and multilingual acceptance cover discovery, source authority, leakage prevention and llms integration;
- Foundation requires both Markdown source files plus `docs/MARKDOWN_ALTERNATES.md`.

PR #43 passed all ten workflows on final candidate `27a9248e41f1fc379e1913bd951cd895db03d5df` and was squash-merged as `173793586a3a75ee4f0819e17485460bf91e47d2`.

PR validation:

- Phase 1 Package CI `35387083960`;
- Foundation CI `35387083879`;
- Pattern Contract CI `35387084021`;
- Design System CI `35387084062`;
- PHP Quality CI `35387084002`;
- WordPress Smoke CI `35387084019`;
- Self-contained Theme CI `35387083969`;
- Accessibility & Responsive CI `35387083992`;
- Performance Baseline CI `35387083885`;
- Native Multilingual CI `35387083876`.

Post-merge `main` passed all ten workflows again:

- Foundation CI `35387364465`;
- Phase 1 Package CI `35387364447`;
- Design System CI `35387364488`;
- PHP Quality CI `35387364422`;
- Pattern Contract CI `35387364439`;
- WordPress Smoke CI `35387364420`;
- Self-contained Theme CI `35387364552`;
- Accessibility & Responsive CI `35387364505`;
- Performance Baseline CI `35387364474`;
- Native Multilingual CI `35387364460`.

Adoption findings were corrected without weakening the contract:

- WPCS alignment/reserved-name findings in the first candidates;
- PHPStan redundant string guard signature `062948f679ee`;
- localized llms fixture false-negative signature `cdbc6628425d`, where the HTML URL was incorrectly tested as an arbitrary substring of its own `index.md` URL.

The reusable fixture lesson is recorded as `ERR-2026-015`.

### Microphase 6D — content provenance / author / source patterns

Status: **complete**

Delivered on PR #45:

- one reusable `ContentProvenanceResolver` for authoritative public built-in posts;
- reuse of the existing WordPress author identity and public author archive;
- visible single-post author name links to the same profile URL used by machine metadata;
- standard HTML `meta name="author"` + `rel="author"`;
- Open Graph `article:published_time`, `article:modified_time` and `article:author`;
- publication/modification values reuse real WordPress ISO-8601 date authorities already used by BlogPosting Schema;
- optional Markdown alternates reuse the same Author/Publisher/Published/Updated provenance;
- publisher remains limited to the existing explicit Organization identity;
- author archives and non-article contexts do not receive article provenance metadata;
- no second Schema graph or duplicate Person identity;
- zero-plugin acceptance proves exact author/date consistency across visible HTML, HTML metadata, Open Graph, Schema and Markdown;
- Foundation requires both provenance source files plus `docs/CONTENT_PROVENANCE.md`.

PR #45 passed all ten workflows on final candidate `660a7bb67019d6eb5ef05c4e28430e9d7fc46ba5` and was squash-merged as `4ee0c956ff1b0946ffad944c69779434e5cd7df1`.

PR validation:

- Foundation CI `35422241278`;
- Phase 1 Package CI `35422241324`;
- Pattern Contract CI `35422241174`;
- Design System CI `35422241210`;
- PHP Quality CI `35422241379`;
- WordPress Smoke CI `35422241172`;
- Self-contained Theme CI `35422241338`;
- Native Multilingual CI `35422241266`;
- Accessibility & Responsive CI `35422241232`;
- Performance Baseline CI `35422241277`.

Post-merge `main` passed all ten workflows again:

- Foundation CI `35422365734`;
- Phase 1 Package CI `35422365771`;
- Pattern Contract CI `35422365740`;
- Design System CI `35422365809`;
- PHP Quality CI `35422365806`;
- WordPress Smoke CI `35422365752`;
- Self-contained Theme CI `35422365830`;
- Native Multilingual CI `35422365725`;
- Accessibility & Responsive CI `35422365742`;
- Performance Baseline CI `35422365713`.

The first candidate exposed only WPCS alignment signature `3357a651e6fe`; no functional provenance failure was found. The reusable alignment lesson is recorded as `ERR-2026-016`.

### Microphase 6E — crawler policy administration and reporting

Status: **complete**

Delivered on PR #47:

- WordPress-native administration under **Appearance → SEO/GEO Crawlers**;
- server-authoritative `manage_options` access;
- WordPress Settings API persistence with nonce protection;
- shared `CrawlerPolicyResolver::sanitize_configuration()` authority for stored crawler states;
- unsupported crawler keys discarded and inherited/invalid states kept non-emitting;
- independent OAI-SearchBot and GPTBot controls;
- effective-policy reporting that surfaces WordPress `blog_public=0` precedence;
- direct public `robots.txt` verification link;
- no custom admin JavaScript/CSS and no new runtime dependency;
- English source strings plus bundled `es_ES` gettext catalog;
- zero-plugin built-theme acceptance for sanitization, admin rendering, privacy reporting and Spanish translation;
- Foundation requires the crawler admin source and the self-contained build requires the translation artifacts.

PR #47 passed all ten workflows on final candidate `111a58510e692ed2472923d8da08994bdd0c0596` and was squash-merged as `c78a442d0d1cdf72329bd7c6b658776ca0611456`.

PR validation:

- Foundation CI `35435508424`;
- Phase 1 Package CI `35435508369`;
- Pattern Contract CI `35435508400`;
- Design System CI `35435508422`;
- PHP Quality CI `35435508392`;
- WordPress Smoke CI `35435508415`;
- Self-contained Theme CI `35435508397`;
- Accessibility & Responsive CI `35435508434`;
- Native Multilingual CI `35435508413`;
- Performance Baseline CI `35435508375`.

Post-merge `main` passed all ten workflows again:

- Foundation CI `35435642085`;
- Phase 1 Package CI `35435642066`;
- Pattern Contract CI `35435642078`;
- Design System CI `35435642071`;
- PHP Quality CI `35435642075`;
- WordPress Smoke CI `35435642082`;
- Self-contained Theme CI `35435642077`;
- Accessibility & Responsive CI `35435642124`;
- Native Multilingual CI `35435642094`;
- Performance Baseline CI `35435642090`.

The first crawler-admin smoke candidate reproduced the existing WP-CLI namespace-escaping incident with signature `f9a2c20341e9`. Only the acceptance harness was corrected; the product implementation remained unchanged. The recurrence and passing evidence are recorded under `ERR-2026-006`.

### Microphase 6F — private/draft discovery leakage regression

Status: **complete**

Delivered on PR #49:

- dedicated `scripts/ci/discovery-privacy-acceptance.sh` sourced inside the existing disposable zero-plugin WordPress fixture;
- public control plus draft/private fixtures with unique title/content leak markers distinct from their slugs;
- direct unauthenticated draft/private HTML routes remain 404 and emit no native Schema, article provenance or Markdown discovery;
- native WordPress sitemap, feed, search and author archive expose the public control where applicable while excluding draft/private markers;
- unauthenticated REST requests reject draft/private resources without disclosing their title/content;
- llms.txt ignores explicitly configured draft/private IDs while retaining the published control;
- Markdown alternates resolve for the published control and remain 404 for draft/private resources;
- provenance and Markdown resolver-level guards return null for draft/private IDs;
- the matrix reuses the same WordPress/MariaDB/Docker fixture, avoiding a second runner environment;
- public privacy contract documented in `docs/DISCOVERY_PRIVACY.md`.

PR #49 passed all ten workflows on final candidate `99c01be435b0098482ff50ea771957eb7a8bee41` and was squash-merged as `feb3f50542e5e56da27d915b1a4e6efe3d73c115`.

PR validation:

- Foundation CI `35436517456`;
- Phase 1 Package CI `35436517524`;
- Pattern Contract CI `35436517476`;
- Design System CI `35436517489`;
- PHP Quality CI `35436517457`;
- WordPress Smoke CI `35436517473`;
- Self-contained Theme CI `35436517496`;
- Accessibility & Responsive CI `35436517529`;
- Native Multilingual CI `35436517466`;
- Performance Baseline CI `35436517459`.

Post-merge `main` passed all ten workflows again:

- Foundation CI `35436653734`;
- Phase 1 Package CI `35436653748`;
- Pattern Contract CI `35436653756`;
- Design System CI `35436653754`;
- PHP Quality CI `35436653749`;
- WordPress Smoke CI `35436653740`;
- Self-contained Theme CI `35436653739`;
- Accessibility & Responsive CI `35436653775`;
- Native Multilingual CI `35436653770`;
- Performance Baseline CI `35436653788`.

The first acceptance candidate completed every HTTP/discovery surface before the final direct resolver check failed because Bash expanded PHP variables under `set -u`. The harness-only failure signature `bd06327f2ae5` is recorded as `ERR-2026-017`; no product leak was observed.

### Microphase 6G — discovery cache and invalidation strategy

Status: **complete**

Delivered on PR #52:

- HTML SEO/Schema remains request-derived with no theme-owned full-page cache;
- no persistent rendered-payload cache was introduced for llms.txt or Markdown;
- one lightweight `DiscoveryCacheRevision` mutation authority backs public discovery validators;
- llms.txt and localized Markdown emit strong resource/surface-scoped ETags;
- discovery documents use `Cache-Control: public, no-cache, must-revalidate, max-age=0`;
- GET/HEAD `If-None-Match` supports conditional revalidation and HTTP 304;
- relevant post, native translation-meta, author/profile and SEO/GEO/site-option mutations advance the revision;
- unrelated option changes leave the revision stable;
- `seo_geo_discovery_cache_invalidated` exposes one vendor-neutral purge signal for optional CDN/page-cache integrations;
- no global object-cache flush, transient payload cache, cron, background worker, cache-plugin dependency or vendor credential was added;
- `docs/CACHE_INVALIDATION.md` is the single cache/invalidation authority;
- zero-plugin acceptance reuses the existing disposable WordPress/MariaDB fixture and proves stale validators stop matching after relevant mutations.

PR #52 passed all ten workflows on final candidate `42c85544ca535d617b052b9f1dc94bf9309a8c6e` and was squash-merged as `d7d79dd3f6fdd235ee891167fcc2a2814dba3705`.

PR validation:

- Foundation CI `35437874323`;
- Phase 1 Package CI `35437874376`;
- Pattern Contract CI `35437874352`;
- Design System CI `35437874387`;
- PHP Quality CI `35437874378`;
- WordPress Smoke CI `35437874421`;
- Self-contained Theme CI `35437874331`;
- Accessibility & Responsive CI `35437874353`;
- Native Multilingual CI `35437874319`;
- Performance Baseline CI `35437874330`.

Post-merge `main` passed all ten workflows again:

- Foundation CI `35438013349`;
- Phase 1 Package CI `35438013370`;
- Pattern Contract CI `35438013324`;
- Design System CI `35438013383`;
- PHP Quality CI `35438013287`;
- WordPress Smoke CI `35438013343`;
- Self-contained Theme CI `35438013353`;
- Accessibility & Responsive CI `35438013378`;
- Native Multilingual CI `35438013285`;
- Performance Baseline CI `35438013361`.

Phase 6G also closed three CI lessons without weakening product behavior:

- WP-CLI namespace escaping recurred in the discovery-cache helper with signature `2eb2e705da21`; it is recorded under the existing `ERR-2026-006`;
- raw `If-None-Match` input triggered WPCS signature `1834ed38c614`; the header is now sanitized before parsing and the lesson is recorded as `ERR-2026-018`;
- Cache-Control acceptance initially required one exact directive order and failed with signature `2c351b882155`; the smoke now validates HTTP semantics and records the lesson as `ERR-2026-019`.

Alternative PR #51 proposed persisting rendered discovery payloads in the WordPress Object Cache. It was closed as superseded after #52 because Phase 6G deliberately selected request-derived documents plus HTTP revalidation, avoiding a second competing cache/invalidation authority.

### Phase 6 completion

All Phase 6 deliverables are complete:

- explicit crawler policy UI/config with OAI-SearchBot and GPTBot controls kept distinct;
- optional native llms.txt generator;
- optional localized Markdown alternates;
- native provenance/author/source patterns;
- cross-surface private/draft leakage regression coverage;
- documented cache/revalidation/invalidation strategy.

Exit criteria are satisfied:

- optional GEO features disable cleanly;
- no competing canonical/indexability ownership was introduced;
- multilingual alternates remain authoritative and regression-covered;
- crawler rules are explicit and documented without ranking/inclusion guarantees;
- no GEO plugin is required for the baseline;
- zero-plugin self-contained acceptance and all ten post-merge workflows are green.

### Remaining Phase 6 work

None. Phase 7 may begin only from this closed, verified baseline.

## Phase 7 — Presets

Status: **complete**

Implement in this order:

1. Corporate
2. Local Business
3. Publisher
4. Ecommerce
5. SaaS / Digital Product

Each preset closes independently before the next begins.

### Microphase 7A — Corporate preset

Status: **complete**

Scope:

- keep the neutral theme/Core architecture intact; Corporate composes rather than forks;
- bundle a machine-readable Corporate manifest, EN/ES content map and Corporate-only pattern definitions;
- reuse the existing page/single/archive/index/404 templates and neutral patterns;
- add only the missing Corporate patterns: case-study teaser, verified metrics and testimonial placeholders;
- never fabricate clients, endorsements, metrics or other proof;
- recommend Organization identity but require explicit confirmation before Schema activation;
- add one allowlisted server-side preset registry through `seo_geo_active_preset`;
- default installation remains preset-neutral;
- unsupported preset IDs must resolve to no active preset;
- Corporate visible copy ships in EN/ES together;
- build bundles preset data inside the self-contained theme;
- zero-plugin WordPress acceptance proves default-off, activation, EN/ES copy and allowlist behavior;
- Foundation validates the declarative Corporate contract without adding another CI workflow.

7A is closed.

Evidence:

- implementation PR #54 passed all ten required workflows on final candidate `68a6c2161baffa8784d20a5a1663520a12ee379a`;
- PR #54 was squash-merged as `7f3499073d82fd362f88c6f3abd6949c52223057`;
- post-merge `main` passed all ten workflows, including Self-contained Theme CI `35446033925`, Native Multilingual CI `35446033952` and Performance Baseline CI `35446033965`.

### Microphase 7B — Local Business preset

Status: **complete**

Scope:

- compose the existing native LocalBusiness Schema authority rather than fork it;
- bundle a machine-readable Local Business manifest, EN/ES content map and Local Business-only pattern definitions;
- support both single-location and multi-location **content architecture** while preserving the native one-physical-entity Schema boundary;
- add Local Business patterns for visible NAP/contact/hours, genuine service areas and location-specific operational details;
- require explicit administrator confirmation before LocalBusiness Schema identity can activate;
- never guess addresses, coordinates, telephone numbers, opening hours, reviews, ratings or service areas;
- forbid automatic city-page generation and doorway-page token swapping;
- require unique location-specific value on physical-location pages;
- require genuine area-specific value on service-area pages;
- generalize the preset registry so pattern categories come from each preset manifest/document rather than Corporate hardcoding;
- keep Corporate and Local Business activation isolated;
- ship visible copy in EN/ES together;
- reuse Foundation and the existing Self-contained Theme fixture with no new workflow, Docker stack or runtime dependency.

7B is closed.

Evidence:

- implementation PR #56 passed all ten required workflows on final candidate `74a55c0efc2fbcc4163575f30afa61c3f83ce983`;
- Self-contained Theme CI `35448138086` proved built-package activation, declarative category ownership, Corporate/Local Business isolation, EN/ES copy and no automatic LocalBusiness Schema identity activation;
- PR #56 was squash-merged as `ef359337172e6a7fe54ff6e12e4b17ec0e44d14e`;
- post-merge `main` passed all ten workflows, including Self-contained Theme CI `35448283956`, Native Multilingual CI `35448283976`, Accessibility & Responsive CI `35448283930` and Performance Baseline CI `35448283928`.

No 7B CI/product incident met the error-register criteria; the first implementation candidate and post-merge candidate both passed without a corrective commit.

### Microphase 7C — Publisher preset

Status: **complete**

Scope:

- compose native WordPress post, author, provenance and BlogPosting/Person/ProfilePage authorities rather than fork them;
- bundle a machine-readable Publisher manifest, EN/ES content map and Publisher-only pattern definitions;
- distinguish singleton editorial pages from native dynamic article, category/topic and author-profile surfaces;
- add Publisher patterns for article summary, verified key facts, checked sources/references and genuinely related content;
- reuse the neutral author-profile pattern instead of creating a second author card;
- reuse native publication/modification provenance instead of inventing editorial date or reviewer metadata;
- recommend Organization publisher identity but require explicit confirmation and never mutate Schema identity on preset activation;
- forbid fabricated citations, inferred author expertise, automatic reviewed-by identity, guessed editorial dates and Article/BlogPosting Schema on normal pages;
- forbid automatic topic/category generation;
- keep Corporate, Local Business and Publisher activation isolated;
- ship visible copy in EN/ES together;
- reuse Foundation and the existing Self-contained Theme fixture with no new workflow, Docker stack or runtime dependency.

7C is closed.

Evidence:

- implementation PR #58 passed all ten required workflows on final candidate `6a9407a288f253544e9c43d47c727d3771b12116`;
- Foundation CI `35454815270` proved the Publisher declarative editorial contract;
- Self-contained Theme CI `35454815324` proved built-package activation, preset isolation, EN/ES copy and no Schema identity mutation;
- PR #58 was squash-merged as `6ef503ff03db934ef33170ad48d07bcc94ec45ac`;
- post-merge `main` passed all ten workflows, including Self-contained Theme CI `35454988460`, Native Multilingual CI `35454988462`, Accessibility & Responsive CI `35454988437` and Performance Baseline CI `35454988446`.

No 7C CI/product incident met the error-register criteria; the first implementation candidate and post-merge candidate both passed without a corrective commit.

### Microphase 7D — Ecommerce preset

Status: **complete**

Scope:

- keep the self-contained theme zero-plugin safe while declaring WooCommerce only as the preferred future commerce provider;
- bundle a machine-readable Ecommerce manifest, EN/ES content map and Ecommerce-only pattern definitions;
- separate theme-owned editorial/policy pages from provider-owned shop, product-category and product surfaces;
- keep Product, Offer, AggregateRating and Review Schema provider-owned;
- never infer price, stock/availability, offers, reviews or ratings;
- keep product/category/shop routing and multilingual commerce routing provider-owned;
- require an explicit indexability policy before faceted/filter URLs can be indexed;
- add Ecommerce patterns for category guidance, buying guidance, authoritative policy navigation and optional original brand/editorial pages;
- forbid mass-generated brand/category copy and manufacturer-copy duplication without verification;
- do not claim WooCommerce support until a dedicated adapter/compatibility phase passes;
- keep Corporate, Local Business, Publisher and Ecommerce activation isolated;
- ship visible copy in EN/ES together;
- reuse Foundation and the existing Self-contained Theme fixture with no new workflow, Docker stack or runtime dependency.

7D is closed.

Evidence:

- implementation PR #60 passed all ten required workflows on final candidate `57c42ca676dce8edad6a71c96fac1d3522b7daad`;
- Foundation CI `35455812984` proved provider-owned commerce, zero-plugin safety, faceted-indexing defaults and EN/ES parity;
- Self-contained Theme CI `35455813064` proved Ecommerce activation with WooCommerce absent, preset isolation, bilingual copy and no Schema identity mutation;
- PR #60 was squash-merged as `0b9ca331c5cad05734d2e8dd8e92a165e8dc58d5`;
- post-merge `main` passed all ten workflows, including Self-contained Theme CI `35456378271`, Native Multilingual CI `35456378290`, Accessibility & Responsive CI `35456378267` and Performance Baseline CI `35456378291`.

No 7D CI/product incident met the error-register criteria; the first implementation candidate and post-merge candidate both passed without a corrective commit.

### Phase 7A–7D verified baseline and roadmap extension

Corporate, Local Business, Publisher and Ecommerce were independently implemented, merged and post-merge verified before this roadmap extension.

Their completed status remains unchanged. The product decision taken before Phase 8 implementation extends the preset layer with one additional modern-site archetype rather than modifying or reopening the accepted work inside 7A–7D.

Shared verified guarantees from 7A–7D remain:

- one allowlisted server-side preset registry;
- preset-neutral default installation;
- EN/ES visible copy shipped together;
- zero required plugin baseline;
- preset-owned patterns isolated from other presets;
- native SEO/GEO/Core authorities composed rather than forked;
- no fabricated proof, local facts, editorial sources or commerce facts;
- Foundation validates every completed declarative preset contract;
- Self-contained Theme CI validates completed preset activation in the same disposable zero-plugin WordPress fixture;
- no extra preset-specific workflow or Docker stack.

### Microphase 7E — SaaS / Digital Product preset

Status: **complete**

Purpose:

- cover modern SaaS, software, AI-product, platform and digital-service sites that do not fit cleanly into Corporate or Ecommerce;
- remain a composition of the neutral theme/Core rather than a separate product stack;
- stay zero-plugin safe and independent from any SaaS backend, billing provider or CRM.

Required information architecture:

- Home;
- Product;
- Features;
- Solutions / use cases;
- Integrations;
- Pricing or plans when real public pricing exists;
- Comparisons only when editorially justified;
- Resources / blog;
- About;
- Contact / demo;
- Documentation link/surface when applicable;
- legal pages.

Preset-owned patterns may include:

- product hero + primary CTA;
- feature grid;
- product workflow/demo explanation;
- use-case cards;
- integrations directory teaser;
- pricing/plans presentation;
- comparison framework;
- proof/testimonial placeholders;
- FAQ;
- lead-generation / book-a-demo CTA;
- final conversion CTA.

SEO/Schema boundaries:

- Organization, WebSite, WebPage, BreadcrumbList, BlogPosting and Person continue to use existing native authorities;
- SoftwareApplication may be recommended only when the visible product genuinely satisfies that entity contract and the required facts are explicitly configured/visible;
- pricing, ratings, reviews, customer counts, awards, integrations and performance claims are never fabricated or inferred;
- comparison pages must contain original, useful comparison content and must not be mass-generated doorway pages;
- preset activation never creates external-service credentials or grants integration authority.

Acceptance:

- EN/ES content map and customer-facing copy ship together;
- activation is isolated from Corporate, Local Business, Publisher and Ecommerce;
- default installation remains preset-neutral;
- zero-plugin WordPress acceptance proves activation without a SaaS backend or third-party service;
- required Foundation and Self-contained Theme gates pass before Phase 7 may close again.

7E is closed.

Evidence:

- implementation PR #65 passed all eight required workflows on final candidate `8d0a0696fcc0bafb69bc47c855747aa1d941bf40`;
- Foundation CI `35460879952` validated the fifth preset declarative contract and EN/ES parity;
- Self-contained Theme CI `35460879957` proved default-off behavior, five-pattern activation, isolation from the four previous presets, EN/ES runtime copy, no Schema identity mutation and zero external product dependency;
- PR #65 was squash-merged as `dc3d15bd323c2894ae029b9fffb2667eb5df16af`;
- post-merge `main` passed all eight triggered workflows: Foundation `35461043434`, Phase 1 Package `35461043459`, PHP Quality `35461043417`, WordPress Smoke `35461043460`, Self-contained Theme `35461043470`, Native Multilingual `35461043444`, Accessibility & Responsive `35461043424` and Performance Baseline `35461043439`.

No 7E product/CI incident required a corrective commit.

### Phase 7 closure

Phase 7 is complete with five independently accepted presets:

1. Corporate;
2. Local Business;
3. Publisher;
4. Ecommerce;
5. SaaS / Digital Product.

Final Phase 7 guarantees:

- one allowlisted server-authoritative preset registry;
- preset-neutral default installation;
- EN/ES customer-facing copy ships together;
- zero required plugin baseline;
- every preset activation remains isolated;
- all preset-owned patterns use native WordPress blocks and shared design tokens;
- native SEO/GEO/Schema/multilingual authorities are composed, never forked;
- presets do not fabricate business facts, editorial sources, commerce facts, product proof, pricing or performance claims;
- SaaS external systems remain optional/provider-owned and no credentials are created by preset activation;
- Foundation validates all five declarative contracts;
- Self-contained Theme exercises all five preset activation contracts inside one disposable zero-plugin WordPress fixture;
- no preset-specific workflow, second Docker stack or runtime dependency was introduced.

The next roadmap step is Phase 8 — Existing-site adoption and safe migration.

## Phase 8 — Existing-site adoption and safe migration

Status: **complete**

Goal:

Support real client WordPress sites that already have a theme, page builder, plugins, content and accumulated SEO value. Migration must modernize the site without treating production as a disposable environment.

The accepted Phase 8 implementation uses a separate **SEO/GEO Migration Bridge** package because it must inspect the existing installation before the destination Theme is active. It is not a baseline dependency of the final Theme. The package remains the proven Theme 0.1.0 migration path; the two-product roadmap later absorbs its accepted capabilities into SEO/GEO Manager as an optional migration module.

### Microphase 8A — Site Analyzer

Status: **complete**

Implementation boundary:

- temporary `packages/seo-geo-migration-bridge` plugin, never a final theme dependency;
- `SiteAnalyzer` returns a non-persistent machine-readable report;
- builder detection is adapter-based and initially covers native blocks, Elementor and Divi;
- Phase 8A detection is environment/registration based; builder-content scanning is deferred to 8C;
- no arbitrary option values, credentials or private/page-builder content are exported;
- static mutation guards plus runtime protected-state fingerprints enforce the read-only contract.

Deliverables:

- read-only-by-default inventory of active/inactive theme and child theme;
- page-builder detection, initially including native blocks, Elementor and Divi, with an extensible adapter contract;
- active/inactive plugin inventory;
- custom post types, taxonomies, shortcodes, widgets, menus and template dependencies;
- WooCommerce and other business-system detection without claiming compatibility before dedicated acceptance;
- multilingual, SEO, Schema, redirects, analytics, forms, cache and security-provider detection;
- custom CSS/functions and other project-owned customization signals where safely detectable;
- machine-readable analysis report;
- no content/theme/plugin mutation during analysis.

8A is closed.

Evidence:

- implementation PR #67 passed all five required workflows on final candidate `494139b10d3c2cc57eafb4cf499017d49c7e5fdb`;
- Foundation CI `35462121902` validated the Migration Bridge package, read-only safety contract and semantic mutation guards;
- Phase 1 Package CI `35462121811` validated package headers, runtime baseline and PHP syntax;
- PHP Quality CI `35462121829` passed WPCS and PHPStan level 6 with no new suppressions/baseline;
- WordPress Smoke CI `35462121813` proved themes/plugins/builders/providers/content-model/customization inventory and identical protected-state fingerprints before/after analysis;
- Self-contained Theme CI `35462121856` proved the final built theme remains zero-plugin and independent from the Migration Bridge;
- PR #67 was squash-merged as `337cd161560b5fce9f0e250d59197546d0eee462`;
- post-merge `main` passed the same five gates: Foundation `35462308729`, Phase 1 Package `35462308676`, PHP Quality `35462308719`, WordPress Smoke `35462308695` and Self-contained Theme `35462308647`.

Adoption findings were fixed at root cause without suppressions: WordPress documentation/alignment findings, one formatting-sensitive safety assertion and PHPStan redundant list/type checks. No Phase 8A incident required a product rollback or error-register entry.

The next microphase is 8B — SEO/GEO baseline snapshot.

### Microphase 8B — SEO/GEO baseline snapshot

Status: **complete**

Implementation boundary:

- capture remains inside the temporary Migration Bridge and never becomes a final theme dependency;
- public requests are anonymous, same-origin only and do not follow redirects by default;
- inventory combines public WordPress resources with bounded same-origin robots.txt/sitemap discovery;
- private/draft content and authenticated page output are excluded;
- page bodies and raw JSON-LD payloads are not persisted; comparison-safe fingerprints are stored instead;
- the persisted legacy baseline is an acceptance reference only and is explicitly not SEO/GEO authority;
- persistence is limited to the dedicated non-autoloaded `seo_geo_migration_baseline_v1` option and requires an explicit call;
- an existing baseline is not overwritten without explicit replacement intent.

Delivered:

- public URL inventory for home, published public post types, archives, public taxonomy terms, author archives with public posts and sitemap-discovered URLs;
- bounded default capture of 500 URLs with a hard 5,000-URL ceiling and explicit truncation reporting;
- HTTP status, content type, redirect location and X-Robots-Tag capture;
- derived indexability state;
- title, meta description, canonical and robots snapshot;
- HTML language and hreflang snapshot;
- Open Graph property snapshot;
- JSON-LD block count, Schema types and deterministic SHA-256 fingerprints;
- H1-H6 outline, H1 count, breadcrumb signals and same-origin internal-link inventory;
- primary-content byte/word counts plus SHA-256 fingerprint without persisted body content;
- robots.txt and same-origin sitemap inventory/fingerprints;
- redirects observed while crawling inventoried URLs;
- explicit baseline persistence with overwrite protection;
- protected-state regression proof showing posts, terms, active plugins, active theme and permalink structure remain unchanged outside the dedicated bridge option;
- real WordPress 7.1 / PHP 8.2 acceptance;
- documentation in `docs/MIGRATION_BRIDGE.md` and the package README.

8B is closed.

Evidence:

- implementation PR #69 passed all four workflows triggered by the final candidate `4f98e330ab8f4bea3862677e93d792c77b259fed`;
- Foundation CI `35859818659` validated the Phase 8A/8B Migration Bridge static safety contract and required repository paths;
- Phase 1 Package CI `35859818690` validated package/runtime syntax and package contract;
- PHP Quality CI `35859818674` passed WPCS and PHPStan level 6 with no new baseline or error suppression;
- WordPress Smoke CI `35859818683` proved real public capture, sitemap discovery, metadata/Schema/content fingerprints, non-autoloaded persistence, overwrite rejection and unchanged protected client state;
- PR #69 was squash-merged as `e795d0acb4302d6fd143d81a7ab405cb15ad7894`;
- post-merge `main` passed all four triggered workflows again: Foundation `35860079163`, Phase 1 Package `35860079244`, PHP Quality `35860079101` and WordPress Smoke `35860079124`;
- Self-contained Theme CI was correctly not triggered because Phase 8B changed only the temporary Migration Bridge, migration acceptance harness and migration documentation; distributable theme/Core paths were unchanged.

Adoption findings were fixed at root cause without lowering standards: WPCS PHPDoc/alignment findings and PHPStan redundant type guards were corrected in code. The WordPress runtime acceptance had already passed before the final static-quality corrections and passed again on the final candidate. No Phase 8B finding met the error-register criteria.

The next microphase is 8C — Builder and plugin dependency graph.

### Microphase 8C — Builder and plugin dependency graph

Status: **complete**

Delivered:

- content-level dependency scanning for native blocks, Elementor, Divi and registered shortcodes;
- resource-level dependency records for public, private, draft, pending and future content without exporting post bodies;
- explicit resource-to-builder and resource-to-shortcode graph edges;
- conservative component classifications: KEEP, REPLACE, MIGRATE, OPTIONAL, REMOVE-CANDIDATE and UNKNOWN;
- builder classification that keeps native blocks, marks content-coupled Elementor/Divi for migration and never grants automatic removal;
- provider classification that preserves business/operational systems and treats SEO/Schema/multilingual ownership as an authority candidate requiring confirmation;
- correlation of Phase 8B observed public SEO/Schema/multilingual signals with detected provider families;
- automatic reuse of the persisted Phase 8B baseline when available;
- unknown/unmapped plugins surfaced for manual review rather than silently removed;
- explicit `auto_remove=false` for every classified component;
- privacy guards proving raw post bodies, Elementor payloads, Divi bodies and shortcode attributes are not exported;
- protected-state regression proving graph generation does not alter posts, postmeta, terms, active plugins, active theme or permalink configuration;
- Migration Bridge 0.3.0, technical documentation and real WordPress 7.1 / PHP 8.2 acceptance.

8C is closed.

Evidence:

- implementation PR #71 passed all four required workflows on final candidate `f39773e1ca3960dcbceb87af6d8b6885be56326c`;
- Foundation CI `35863681158` validated the Phase 8A/8B/8C static safety contract and repository paths;
- Phase 1 Package CI `35863681162` passed package/runtime contract validation;
- PHP Quality CI `35863681055` passed WPCS and PHPStan level 6 without a new baseline or global suppression;
- WordPress Smoke CI `35863681047` proved native/Elementor/Divi/shortcode dependency mapping, persisted-baseline reuse, provider classifications, no raw payload leakage and unchanged protected client state;
- PR #71 was squash-merged as `3e2d3d6c703b4dc48ca5f67c65354ed9cbfebc9d`;
- post-merge `main` passed all four triggered workflows again: Foundation `35863849260`, Phase 1 Package `35863849284`, PHP Quality `35863849264` and WordPress Smoke `35863849299`.

Adoption findings were resolved in code without lowering standards: WPCS alignment/PHPDoc issues and PHPStan redundant guards were removed at source. No Phase 8C finding required a product rollback or error-register entry.

No production plugin/theme is automatically removed because another component appears to cover similar behavior.

The next microphase is 8D — Sandbox Migration Lab.

### Microphase 8D — Sandbox Migration Lab

Status: **complete**

Delivered:

- provider-neutral clone/staging acceptance contract independent from any hosting vendor;
- explicit `SEO_GEO_MIGRATION_SANDBOX=true` marker required before sandbox runtime guards activate;
- WordPress search-engine visibility requirement (`blog_public=0`);
- marker-gated `noindex`, `nofollow` and `noarchive` robots directives;
- defense-in-depth `X-Robots-Tag: noindex, nofollow, noarchive`;
- destination `seo-geo-theme` activation required in sandbox before migration readiness;
- persisted Phase 8B baseline required and reused as comparison reference;
- Phase 8C dependency graph required before readiness;
- sandbox readiness blockers for missing marker, public search visibility, wrong theme, missing baseline or missing dependency graph;
- migration-state projection from 8C classifications into `migrate`, `manual-review`, `unchanged` and `blocked`;
- explicit declarations that production cutover, production mutation, indexing and canonical competition are not allowed in Phase 8D;
- protected-state regression proving report generation does not alter posts, postmeta, terms, active plugins, active theme, permalink state or the stored baseline;
- documented provider-neutral clone/staging workflow in `docs/MIGRATION_BRIDGE.md`;
- Migration Bridge 0.4.0 and real WordPress 7.1 / PHP 8.2 acceptance.

8D is closed.

Evidence:

- implementation PR #73 passed all four required workflows on final candidate `b31c70df3763435179fb22c78502b048fbd9592d`;
- Foundation CI `35864681343` validated the Phase 8A–8D static safety contract and required repository paths;
- Phase 1 Package CI `35864681268` passed package/runtime contract validation;
- PHP Quality CI `35864681148` passed WPCS and PHPStan level 6 with no new baseline or global suppression;
- WordPress Smoke CI `35864681238` proved the explicit sandbox marker, destination-theme/baseline/graph readiness, robots meta noindex, X-Robots-Tag and unchanged protected state;
- PR #73 was squash-merged as `9d9c171967113e7a1c2f98f202d307ef764b3f6d`;
- post-merge `main` passed all four triggered workflows again: Foundation `35864890150`, Phase 1 Package `35864890171`, PHP Quality `35864889933` and WordPress Smoke `35864890050`.

Adoption findings were resolved at source: WPCS report alignment was corrected without changing behavior, and the final candidate passed PHPStan level 6 without suppressions. No Phase 8D finding required a product rollback or error-register entry.

The repository continues to prefer provider-neutral sandbox contracts. Vendor-specific staging integrations remain optional adapters.

The next microphase is 8E — Migration Engine.

### Microphase 8E — Migration Engine

Status: **complete**

Delivered:

- plan-before-mutate Migration Engine exposed through the temporary Migration Bridge;
- Phase 8D sandbox readiness required before any transformation can be ready;
- explicit administrator authorization: `manage_options`, exact-resource `edit_post`, nonce and confirmation;
- static CI mutation boundary allowing post/meta writes only inside `src/Migration/MigrationEngine.php`;
- pre-write private rollback source in `_seo_geo_migration_backup_v1`;
- post-write state/hash marker in `_seo_geo_migration_state_v1`;
- immediate restore path when post-mutation resource identity/permalink invariants fail;
- object ID, slug and permalink-path preservation checks;
- media/featured-media ID preservation for supported Elementor image migrations;
- conservative Elementor adapter for heading, text editor, image, button, divider and spacer;
- conservative Divi adapter for text, button, image, divider and spacer inside section/row/column structure;
- unsupported Elementor widgets and Divi modules surfaced as explicit blockers, never silently dropped;
- destination preset validation across all five bundled presets without silently switching the active preset;
- Phase 8C `KEEP` business systems surfaced and preserved outside the mutation scope;
- authenticated admin-post entrypoint with repeated capability/nonce/explicit-confirmation enforcement;
- acceptance-report privacy tightened so private builder payloads are compared by SHA-256 rather than serialized;
- real WordPress 7.1 / PHP 8.2 acceptance proving supported Elementor/Divi migration and unsupported-content blocking.

8E is closed.

Evidence:

- implementation PR #75 passed all four required workflows on final candidate `042a463155adc24ed1f45405a914807a0cf39767`;
- Foundation CI `35867636883` validated the Phase 8A–8E static safety/mutation contract;
- Phase 1 Package CI `35867637009` passed package/runtime contract validation;
- PHP Quality CI `35867636901` passed WPCS and PHPStan level 6 without lowering standards or adding global suppressions;
- WordPress Smoke CI `35867637089` proved supported Elementor/Divi → native-block migration, object/slug/path/media preservation, WooCommerce KEEP preservation, nonce/confirmation rejection, preset mismatch rejection and unsupported Elementor form blocking;
- PR #75 was squash-merged as `030148d4ab063c11e9b1842b7323da0ee465cd27`;
- post-merge `main` passed all four triggered workflows again: Foundation `35867885927`, Phase 1 Package `35867885970`, PHP Quality `35867886666` and WordPress Smoke `35867886058`.

Engineering findings were resolved at source: brittle validator regexes were replaced by semantic/whitespace-tolerant guards, WPCS alignment/PHPDoc findings were corrected, PHPStan redundant Divi parser guards were removed, and private builder data was eliminated from the acceptance report. No Phase 8E finding required a product rollback or error-register entry.

No production cutover, plugin removal/deactivation, theme switch or term mutation exists in 8E.

The next microphase is 8F — SEO parity and regression engine.

### Microphase 8F — SEO parity and regression engine

Status: **complete**

Delivered:

- origin-neutral old-vs-new comparison keyed by public path/query rather than staging hostname;
- URL presence/status/indexability parity for every tracked snapshot resource;
- canonical, robots, title/meta, HTML language, hreflang and Open Graph comparison;
- Schema type/block-count comparison plus duplicate JSON-LD fingerprint conflict detection;
- H1, internal-link and primary-visible-content fingerprint comparison;
- single-owner evidence for title, meta description, canonical and robots plus duplicate hreflang language-key detection;
- hard non-allowable regressions for duplicate ownership, duplicate Schema and known broken internal links;
- redirect-map validation and sitemap topology consistency;
- exact intentional-difference allowlist bound to path + signal + before/after SHA-256 + non-empty reason;
- stale allowlist approvals automatically stop matching changed candidate values;
- parity reports expose hashes/decision metadata rather than raw before/after bodies;
- production cutover remains explicitly disallowed by the 8F report;
- representative post-migration native-block fixture added to browser and Lighthouse acceptance;
- Accessibility & Responsive CI expanded from EN/ES to EN/ES + migration and passed 36/36 Playwright/axe cases;
- Performance Baseline CI expanded to three pages × three Lighthouse samples;
- post-migration representative Lighthouse median remained performance 100, LCP 641.68 ms, CLS 0, TBT 0 ms, 18,829 B transfer, 5 requests, 0 third-party requests and 0 project-owned frontend JS;
- Migration Bridge version 0.6.0.

8F is closed.

Evidence:

- implementation PR #77 final candidate `080141158162f739ba6bf862720678f11f6e56a8` passed all six required workflows;
- Foundation CI `35870573572`;
- Phase 1 Package CI `35870573394`;
- PHP Quality CI `35870573512` passed WPCS + PHPStan level 6 without suppressions;
- WordPress Smoke CI `35870573493` proved identical parity, regression detection, exact approval, stale-approval rejection, hard ownership/Schema conflict blocking, broken-link blocking and invalid-snapshot blocking;
- Accessibility & Responsive CI `35870573412` passed 36/36 cases;
- Performance Baseline CI `35870573501` passed all enforced budgets;
- PR #77 was squash-merged as `20822daa6adb287212b287e3b83a0fe3e333e338`;
- post-merge `main` passed the same six gates again: Foundation `35871165650`, Phase 1 Package `35871165526`, PHP Quality `35871165428`, WordPress Smoke `35871165350`, Accessibility & Responsive `35871165421` and Performance Baseline `35871165554`;
- post-merge browser acceptance again passed 36/36 cases;
- post-merge migration Lighthouse median was performance 100, LCP 641.68 ms, CLS 0, TBT 0 ms, 18,829 B transfer, 5 requests, zero third-party requests and zero project-owned frontend JS.

Engineering findings were resolved at source: the initial wildcard/prefix allowlist divergence was replaced by exact fingerprint-bound approvals; WPCS alignment and PHPDoc findings were corrected; `serialize()` was removed from fingerprint fallback; and the single PHPStan redundant blocker comparison was removed without lowering level 6 or adding suppressions.

A migration still cannot be accepted merely because pages look correct. 8F provides evidence only and does not authorize production cutover.

The next microphase is 8G — Safe cutover and rollback.

### Microphase 8G — Safe cutover and rollback

Status: **complete**

Current implementation scope:

- recent SHA-256-bound external evidence is mandatory for both a full database backup and the uploads tree before any cutover mutation;
- recent passed Accessibility/Responsive and Performance evidence for the representative migrated page is mandatory alongside fresh 8F parity;
- append-only non-autoloaded cutover history records recovery metadata, pre-cutover theme/plugin state, protected options, baseline/redirect fingerprints and migration-backup fingerprints;
- production cutover is blocked while sandbox mode/search invisibility remains, parity is unaccepted, backup evidence is invalid, the target theme is missing or another cutover is active;
- requested plugin deactivations are explicit and dependency-aware: `REPLACE`, `REMOVE-CANDIDATE` and `OPTIONAL` are eligible, while `KEEP`, `UNKNOWN` and `MIGRATE` remain blocking;
- Migration Bridge self-deactivation, plugin/theme deletion, database reset and uploads reset are forbidden;
- required rewrite/object-cache/sitemap maintenance is bounded to actual runtime changes;
- post-cutover runtime health plus fresh SEO/GEO parity are mandatory and failure triggers automatic rollback;
- manual rollback refuses unrelated runtime drift and verifies parity after restoration;
- explicit final acceptance is the only state that closes runtime rollback, while recovery evidence remains retained;
- acceptance covers blocked backup/KEEP/MIGRATE/bridge cases, nonce/confirmation rejection, automatic rollback, manual rollback and final acceptance.

Deliverables:

- mandatory pre-cutover snapshot of database, uploads, themes/plugins/options relevant to recovery, redirect map and SEO baseline;
- controlled production activation/deactivation plan;
- cache purge and rewrite/sitemap refresh only where required;
- immediate health checks after cutover;
- SEO parity spot-check against the accepted migration report;
- deterministic rollback procedure;
- rollback remains available until the migration is explicitly accepted.

Destructive “reset everything first” behavior is forbidden.

8G is closed.

Evidence:

- final candidate `bffb8b1f8a4fec960d681e4e6effdad283aa37e5` passed Foundation `35875100904`, Package `35875100776`, PHP Quality `35875100797`, WordPress Smoke `35875100885`, Accessibility/Responsive `35875100967` and Performance `35875101125`;
- PR #79 was squash-merged as `4693f99b430b83f9039a0943575f13f45f7a82bf`;
- post-merge `main` passed the same six gates again: Foundation `35875813384`, Package `35875813344`, PHP Quality `35875813456`, WordPress Smoke `35875813345`, Accessibility/Responsive `35875813328` and Performance `35875813333`;
- WordPress Smoke proved backup + Accessibility/Performance evidence requirements, KEEP/MIGRATE protection, automatic rollback on failed parity, manual rollback and explicit acceptance;
- WPCS and PHPStan level 6 remained green without suppressions or reduced quality thresholds.

The next microphase is 8H — Migration report.

### Microphase 8H — Migration report

Status: **complete**

Current implementation scope:

- a read-only report engine consolidates the accepted 8B baseline, current 8C dependency graph, 8E migration fingerprints, fresh 8F parity and the accepted 8G cutover record;
- before/after dependency counts include active plugins and legacy-builder-coupled resources;
- kept, replaced/deactivated, removed and remove-candidate decisions are explicit; 8H never pretends deactivation equals deletion;
- URL/status/redirect parity is summarized separately from approved SEO/GEO signal improvements;
- Accessibility/Responsive and Performance evidence now retains bounded scalar before/after metric summaries for the final report;
- unresolved dependency, parity and quality-comparison items are separated into blocking and advisory review rows;
- cutover lifecycle and database/uploads recovery evidence are included as references/hashes only;
- the report determines whether the bridge can be removed, retained audit-only or is still operationally required because migration is not final;
- a final ready report may be explicitly persisted to non-autoloaded `seo_geo_migration_report_v1` for Phase 9 consumption;
- persisted reports contain no post bodies, builder payloads, credentials or raw backup artifacts and do not make the Migration Bridge a runtime dependency.

Deliverables:

- before/after dependency count;
- kept/replaced/removed components;
- URL and redirect parity;
- SEO/GEO signal parity and intentional improvements;
- representative performance/accessibility comparison;
- unresolved manual-review items;
- cutover/rollback evidence;
- final Migration Bridge disposition: remove, retain in audit-only mode or retain only when a documented operational feature requires it.

8H is closed.

Evidence:

- final candidate `62a3a9914469f74d5a88e586b3409b1b6388649f` passed Foundation `35878260227`, Package `35878260279`, PHP Quality `35878260020`, WordPress Smoke `35878260287`, Accessibility/Responsive `35878260087` and Performance `35878260054`;
- PR #81 was squash-merged as `20df50545f84a14cd172147259e1233447660424`;
- post-merge `main` passed the same six gates again: Foundation `35878908448`, Package `35878908405`, PHP Quality `35878908466`, WordPress Smoke `35878908657`, Accessibility/Responsive `35878908524` and Performance `35878908520`;
- the final report is non-autoloaded, privacy-bounded and explicitly not a runtime dependency.

A Phase 8 exit audit found one remaining unmet criterion: no administrator/operator screen currently exists for the Migration Bridge, so the EN/ES operator UI criterion is not yet satisfied.

### Microphase 8I — Operator UI and Phase 8 exit

Status: **complete**

Current implementation scope:

- a capability-gated Tools → SEO/GEO Migration screen is registered by the temporary Migration Bridge;
- one built-in key-complete catalog ships English and Spanish operator copy together;
- the screen is status-only: it exposes no POST form, admin-post mutation endpoint or report-persistence control;
- baseline, dependency classifications, accepted cutover and final handoff report status are summarized without private bodies or raw recovery artifacts;
- dependency status reads the persisted final report when available and falls back to live read-only analysis before handoff;
- blocking/advisory review counts and final bridge disposition are visible;
- server-side next-step guidance resolves baseline capture, sandbox/parity continuation, cutover acceptance, final report, review or bridge removal;
- page rendering has explicit safety flags and is runtime-tested against a protected-state fingerprint;
- runtime acceptance renders both EN and ES, verifies key completeness, semantic headings/tables, Tools registration and unauthorized-user rejection.

Deliverables:

- capability-gated Migration Bridge screen under WordPress Tools;
- English and Spanish operator copy shipped together from one key-complete catalog;
- locale-aware migration status for baseline, dependency planning, cutover and final report;
- visible blocking/advisory review counts and final bridge disposition;
- read-only rendering: opening the screen performs no migration, cutover or report persistence;
- semantic headings, status lists/tables and accessible operator guidance;
- no private post bodies, builder payloads, credentials or raw backup artifacts;
- WordPress runtime acceptance for EN and ES rendering plus capability enforcement;
- final Phase 8 exit-criteria audit after merge.

8I is closed.

Evidence:

- final candidate `783c3473363bf5a6dd95b6668db408ebbe0bbd59` passed Foundation `35881439395`, Package `35881439605`, PHP Quality `35881439382`, WordPress Smoke `35881439637`, Accessibility/Responsive `35881439748` and Performance `35881439439`;
- PR #84 was squash-merged as `e7f9c80ee0a8229917c0995f349ab49a4b0a00c3`;
- post-merge `main` passed the same six gates again: Foundation `35881964530`, Package `35881964347`, PHP Quality `35881964370`, WordPress Smoke `35881964477`, Accessibility/Responsive `35881964534` and Performance `35881964253`;
- WordPress Smoke proved EN/ES key completeness, Tools registration, capability enforcement, semantic rendering, privacy boundaries and unchanged protected state.

### Phase 8 exit criteria

Phase 8 is **closed**.

All exit criteria are satisfied:

- analysis is non-destructive by default;
- representative legacy fixtures migrate through sandbox rather than production-first;
- builder/plugin dependencies are explicitly classified;
- tracked SEO/GEO signals are parity-gated with explicit approvals;
- cutover and rollback are tested;
- the final destination theme is self-contained and Migration Bridge is not a runtime dependency;
- EN/ES operator UI ships together;
- security, PHP quality, WordPress runtime, accessibility and performance gates are green.

## Phase 9 — Theme onboarding and operator experience

Status: **complete**

The onboarding layer serves both clean installations and sites that arrived through Phase 8 migration. The theme owns onboarding and remains zero-required-plugin.

### Microphase 9A — Setup foundation and migration handoff

Status: **complete**

Current implementation scope:

- `seo_geo_theme_setup_plan()` produces a versioned read-only setup plan;
- `SetupConfigurationContract` defines future theme-owned option `seo_geo_theme_setup_v1` without persisting it in 9A;
- all five completed presets are read from the existing allowlisted registry;
- current languages come from `NativeLanguageConfiguration`;
- accepted `seo_geo_migration_report_v1` is consumed directly without loading Migration Bridge classes;
- clean vs migrated mode is resolved deterministically;
- external SEO/language providers plus WooCommerce/Elementor/Divi are advisory compatibility signals only;
- self-contained acceptance proves clean + migrated planning with zero active plugins and unchanged protected setup state;
- static CI forbids setup option writes, page creation, plugin mutation, scheduling, POST handlers and outbound POST calls inside the 9A setup layer.

Deliverables:

- versioned theme-owned setup configuration contract;
- read-only setup plan before any setup option mutation;
- allowlisted access to the completed five-preset catalog;
- read-only consumer for accepted `seo_geo_migration_report_v1` without loading Migration Bridge;
- clean-install versus migrated-site mode resolution;
- compatibility warnings from detected external systems without making them required;
- explicit 9A safety flags: no page creation, plugin installation, plugin activation/deactivation, external credentials or setup mutations.

9A is closed.

Evidence:

- final candidate `f89514cc7051da8917fece779d26a8331cd41a72` passed Foundation `35883921399`, Package `35883921389`, PHP Quality `35883921378`, WordPress Smoke `35883921384`, Self-contained Theme `35883921418`, Native Multilingual `35883921401`, Accessibility/Responsive `35883921391` and Performance `35883921405`;
- PR #86 was squash-merged as `17d4cc185addce336a3f2f86079b03dbf00a3468`;
- post-merge `main` passed the same eight gates again: Foundation `35884423588`, Package `35884423936`, PHP Quality `35884423746`, WordPress Smoke `35884423606`, Self-contained Theme `35884423758`, Native Multilingual `35884423933`, Accessibility/Responsive `35884423721` and Performance `35884423669`;
- self-contained runtime proved clean/migrated mode resolution, five-preset availability, handoff consumption without Migration Bridge runtime and mutation-free planning.

### Microphase 9B — Preset and language configuration

Status: **complete**

Current implementation scope:

- `seo_geo_theme_validate_preset_language_setup()` validates explicit choices without persistence;
- only the completed five-preset allowlist is accepted;
- `NativeLanguageConfiguration::from_array()` provides strict pre-persistence validation using the same Core rules as runtime;
- `NativeLanguageConfiguration::to_array()` exports normalized default/languages/routing/x-default state;
- duplicate locales, malformed language maps, unsupported presets and single-language prefix routing are rejected;
- preset baseline locales not selected are advisory only and are never auto-created;
- external language-provider ownership is reported as a warning rather than silently overridden;
- validation creates no translations/routes and mutates no setup/provider/plugin state;
- self-contained acceptance fingerprints setup state before/after valid and invalid cases.

Deliverables:

- explicit choice across all five completed presets;
- primary/additional language configuration through the existing native language authority;
- routing/x-default validation;
- no automatic translation or route fabrication.

9B is closed.

Evidence:

- final candidate `bf0f0530204a3ed8e84f575f6fb2b76a380ff6c5` passed Foundation `35889610406`, Package `35889610241`, PHP Quality `35889610245`, WordPress Smoke `35889610203`, Self-contained Theme `35889610206`, Native Multilingual `35889610240`, Accessibility/Responsive `35889610358` and Performance `35889610374`;
- PR #88 was squash-merged as `0bc511ccc175b63df842db2b7b9bf181f7c1fda3`;
- post-merge `main` passed the same eight gates again: Foundation `35890113366`, Package `35890113361`, PHP Quality `35890113304`, WordPress Smoke `35890113371`, Self-contained Theme `35890113329`, Native Multilingual `35890113300`, Accessibility/Responsive `35890113277` and Performance `35890113320`;
- self-contained runtime proved normalized explicit preset/language validation, invalid preset/locale/routing rejection and unchanged protected setup state.

### Microphase 9C — Entity and GEO configuration

Status: **complete**

Current implementation scope:

- `seo_geo_theme_validate_entity_geo_setup()` validates explicit entity/GEO choices without persistence;
- `SchemaIdentityResolver` exposes the same supported entity-type authority used by runtime;
- `SchemaLocalBusinessResolver` exposes address/coordinate candidate normalization backed by its runtime rules;
- Organization and LocalBusiness choices require explicit identity confirmation;
- LocalBusiness basics require a physical address; coordinates are optional but, when provided, must pass the Core precision/range authority;
- unsupported LocalBusiness fields such as rating/review claims are rejected rather than ignored into setup state;
- LocalBusiness validation never marks Schema output ready: the existing public-document visible-fact gate remains authoritative at render time;
- crawler choices are strictly validated against `CrawlerPolicyResolver` and then normalized through its sanitizer;
- `llms.txt` and Markdown alternates are explicit boolean opt-ins bound to their existing Core option authorities;
- provenance is reported as native behavior on eligible content, not as fabricated or separately configurable data;
- private-site discovery choices produce an advisory rather than a false crawler guarantee;
- self-contained acceptance fingerprints identity/crawler/discovery/plugin state before/after all valid and invalid cases.

Deliverables:

- explicit organization/local-business identity basics;
- existing visible-fact gates remain authoritative;
- crawler policy reuses Core;
- GEO/discovery opt-ins reuse existing llms.txt/Markdown/provenance authorities.

9C is closed.

Evidence:

- final candidate `f6592459f3da94353b317d4c7529ee262ca43def` passed Foundation `35891851206`, Package `35891851157`, PHP Quality `35891851148`, WordPress Smoke `35891851159`, Accessibility/Responsive `35891851222`, Performance `35891851165`, Self-contained Theme `35891851408` and Native Multilingual `35891851154`;
- PR #90 was squash-merged as `215f26e336b7772223a955f1284e500b935d19dd`;
- post-merge `main` repeated all eight gates successfully: Foundation `35892343305`, Package `35892343445`, PHP Quality `35892343430`, WordPress Smoke `35892343480`, Accessibility/Responsive `35892343290`, Performance `35892343401`, Self-contained Theme `35892343313` and Native Multilingual `35892343413`;
- zero-plugin runtime proved explicit entity/GEO validation, visible-fact authority retention, invalid claim rejection and unchanged protected setup state.

### Microphase 9D — Theme-owned wizard UI

Status: **complete**

Current implementation scope:

- Appearance → SEO/GEO Setup is theme-owned and requires `manage_options`;
- wizard copy ships EN/ES together from one key-complete catalog;
- preset/language/entity/GEO fields are composed from the 9A–9C authorities rather than duplicated;
- POST preview is nonce-verified and explicitly acknowledged as non-persistent;
- valid/invalid previews are rendered in an `aria-live` focus target;
- wizard-specific CSS is responsive at the WordPress 782px breakpoint and JS only manages result focus;
- static CI forbids option/content/plugin/cron/outbound mutation primitives in the Wizard layer;
- zero-plugin runtime acceptance proves page registration, EN/ES rendering, preview validation, asset loading, capability enforcement and unchanged protected setup state;
- Playwright/axe covers the admin wizard at 320/768/1440, including keyboard flow, responsive reflow and validation-error announcement.

Deliverables:

- WordPress-native theme setup screen;
- EN/ES operator copy together;
- capability, nonce and explicit confirmation for mutations;
- accessible keyboard/focus/error behavior;
- responsive administration layout.

9D is closed.

Evidence:

- final candidate `d5ecc25e5ac9506e5c965eef8fd892224218a8cf` passed Foundation `35896877290`, Package `35896877207`, PHP Quality `35896877228`, WordPress Smoke `35896877259`, Self-contained Theme `35896877277`, Native Multilingual `35896877196`, Accessibility/Responsive `35896877471` and Performance `35896877134`;
- PR #94 was squash-merged as `a841b3ccee453554e50826176e310c9d7c95310b`;
- post-merge `main` repeated all eight gates successfully: Foundation `35897366497`, Package `35897366553`, PHP Quality `35897366546`, WordPress Smoke `35897366570`, Self-contained Theme `35897366543`, Native Multilingual `35897366527`, Accessibility/Responsive `35897366647` and Performance `35897366562`;
- browser/runtime acceptance proved EN/ES rendering, keyboard/axe behavior, responsive reflow, nonce/capability enforcement, preview-only validation and unchanged protected setup state.

### Microphase 9E — Setup execution and generated report

Status: **complete**

Current implementation scope:

- `SetupExecutor` revalidates the full wizard candidate through the existing 9B/9C authorities before any write;
- all option mutations are isolated behind `SetupOptionWriterInterface`; only `WordPressSetupOptionWriter` may call `update_option`/`delete_option`;
- authoritative writes cover preset, native languages, explicit Schema identity, LocalBusiness basics, crawler policy, llms.txt, Markdown alternates and the theme-owned setup state;
- llms.txt/Markdown setup changes preserve existing bounded configuration and change only their explicit `enabled` flag;
- setup execution snapshots every affected option, verifies each write and rolls all changed options back in reverse order on any failure;
- rerunning an already-applied identical configuration is idempotent: no option/report rewrite occurs and the original report timestamp/hash remain stable;
- `seo_geo_theme_setup_report_v1` stores a non-sensitive generated report with configuration/report fingerprints, preset/language/entity/GEO summaries, compatibility warnings and bounded migration-handoff metadata;
- LocalBusiness address/telephone/coordinates never enter the generated report; they remain only in the authoritative LocalBusiness option;
- the wizard exposes separate Preview and Apply actions using the same capability/nonce boundary plus an explicit apply confirmation;
- zero-plugin acceptance injects a mid-write failure and proves protected state is byte-for-byte restored.

Deliverables:

- atomic application of validated setup choices;
- migrated-site handoff consumption without Migration Bridge runtime dependency;
- advisory compatibility warnings;
- non-sensitive generated setup report;
- idempotent rerun/update behavior.

9E is closed.

Evidence:

- final candidate `73a3a2d752241d50d0e3f968bbe0a69cb234bbea` passed Foundation `35937968265`, Package `35937968352`, PHP Quality `35937968217`, WordPress Smoke `35937968337`, Self-contained Theme `35937968239`, Native Multilingual `35937968360`, Accessibility/Responsive `35937968240` and Performance `35937968246`;
- PR #98 was squash-merged as `a95d325027a8e787f030e48fd82f4f626a7c3c65`;
- post-merge `main` repeated all eight gates successfully: Foundation `35938267798`, Package `35938267866`, PHP Quality `35938267816`, WordPress Smoke `35938267890`, Self-contained Theme `35938267878`, Native Multilingual `35938267963`, Accessibility/Responsive `35938267901` and Performance `35938267767`;
- runtime acceptance proved validator-backed atomic application, read-back verification, full rollback on injected failure, stable idempotent rerun, privacy-bounded reporting and zero required plugins.

### Microphase 9F — Onboarding acceptance

Status: **complete**

Deliverables:

- clean-install and migrated-site acceptance;
- zero-required-plugin acceptance;
- EN/ES browser accessibility/responsive acceptance;
- performance baseline;
- security/PHP quality/static contracts;
- proof that onboarding never instructs installation of an SEO/GEO plugin.

Current implementation scope:

- browser acceptance now builds and activates only the self-contained theme; the standalone Core plugin is neither copied nor activated;
- browser acceptance asserts zero active plugins before and after Playwright and verifies the embedded Core runtime path;
- the persistent wizard flow applies a clean Corporate EN/ES setup once, proves idempotent re-Apply, then proves the same persisted state through the Spanish operator UI;
- self-contained runtime acceptance applies and re-applies a clean setup with `site_mode=clean`, stable page counts and zero active plugins;
- the existing migrated LocalBusiness handoff acceptance remains in the same zero-plugin runtime and proves `site_mode=migrated`, rollback and privacy-bounded reporting;
- EN/ES wizard copy explicitly states that no SEO/GEO plugin is required for baseline setup;
- static CI requires that zero-plugin statement in both languages and rejects common install-plugin guidance phrases.

The onboarding flow must not install or require an SEO/GEO plugin to complete the baseline setup.

9F and Phase 9 are closed.

Evidence:

- final candidate `182fd363e452b99c10fa92a9ce7a762aeb1e576f` passed Foundation `35940051706`, Package `35940051681`, PHP Quality `35940051684`, WordPress Smoke `35940051804`, Self-contained Theme `35940051739`, Native Multilingual `35940051931`, Accessibility/Responsive `35940051597` and Performance `35940051711`;
- PR #101 was squash-merged as `d09355c1de026a63338691c68366df467f49f8b9`;
- post-merge `main` repeated all eight gates successfully: Foundation `35940301513`, Package `35940301507`, PHP Quality `35940301544`, WordPress Smoke `35940301522`, Self-contained Theme `35940301517`, Native Multilingual `35940301500`, Accessibility/Responsive `35940301551` and Performance `35940301533`;
- Phase 9F exposed and fixed the clean-install nullable migration-handoff regression before release;
- final browser acceptance uses only the self-contained theme with zero active plugins and proves EN/ES persistent/idempotent onboarding.

## Phase 10 — Distribution and production release

Status: **active**

### Microphase 10A — Reproducible release ZIP and runtime integrity

Status: **complete**

10A establishes the distributable artifact before versioning, upgrade and real-site release work.

Deliverables:

- deterministic single-theme ZIP from the self-contained theme build;
- stable ZIP root `seo-geo-theme/` with no repository-only files;
- SHA-256 artifact checksum;
- embedded SEO/GEO runtime integrity manifest/check;
- reproducibility acceptance proving two builds from the same source are byte-identical;
- release-artifact CI evidence without publishing a GitHub Release yet.

Current implementation scope:

- `scripts/build-theme-release.py` reuses the self-contained theme assembler and writes a deterministic ZIP with fixed timestamps, normalized file modes and sorted entries;
- every ZIP has the single root `seo-geo-theme/`;
- `release-integrity.json` records SHA-256 hashes for every embedded Core runtime file plus a canonical runtime-tree fingerprint;
- the builder emits a sibling `.sha256` file for the ZIP;
- `scripts/ci/release-artifact-acceptance.sh` performs two independent builds and requires byte-for-byte identity;
- acceptance rejects multiple roots, path traversal, repository-only paths, duplicate entries, unstable timestamps/modes and runtime/source hash drift;
- `Release Artifact CI` preserves the ZIP and checksum as a short-lived workflow artifact and does not publish a release.

10A is closed.

Evidence:

- final candidate `6fc2d5a490529f3c194800f28da0a927b0190e01` passed Foundation `35940973500` and Release Artifact CI `35940973445`;
- two candidate builds produced byte-identical ZIP SHA-256 `b560e8be3a1e7e1666842be28b19743e184a5119c58dfdb1f9f21bb3890b3838`;
- PR #103 was squash-merged as `342d0806e0778a0d83a91e526456396df2042d35`;
- post-merge `main` repeated Foundation `35941055453` and Release Artifact CI `35941055386` successfully;
- post-merge release builds reproduced the exact same ZIP SHA-256 `b560e8be3a1e7e1666842be28b19743e184a5119c58dfdb1f9f21bb3890b3838`.

### Microphase 10B — Versioning, changelog and upgrade acceptance

Status: **complete**

Deliverables:

- version source of truth and release metadata;
- changelog contract;
- clean-install and upgrade tests with zero required plugins;
- compatibility/rollback checks across supported WordPress/PHP baseline.

10B is closed.

Evidence:

- final candidate `cf63aaeae0e2bdb6ad02e3db78fdce8e2cb3dafb` passed Foundation `35944383852` and Release Artifact CI `35944383820`;
- Release Artifact CI proved synchronized `release/version.json` / `style.css` / `CHANGELOG.md`, deterministic release metadata and WordPress 7.1 / PHP 8.2 zero-plugin upgrade + rollback;
- PR #105 was squash-merged as `994138dfc7a7ff3a07aea07f6c951a159a639540`;
- post-merge `main` repeated Foundation `35944485076` and Release Artifact CI `35944485045` successfully;
- post-merge deterministic ZIP SHA-256: `dae8da490526fd3584387324bc1bc596d17ad5e681513927c0468b48e786ebba`;
- the upgrade acceptance preserved setup state, setup report and published sentinel content across `0.0.9 → 0.1.0 → 0.0.9` with zero active plugins and idempotent re-Apply.

### Microphase 10C — Client installation and cloning documentation

Status: **complete**

Deliverables:

- migration/install documentation for real client sites;
- documentation for project cloning and per-client customization;
- documented safe update path for customized client builds.

Current implementation scope:

- `docs/CLIENT_INSTALLATION.md` defines clean-install and existing-site paths around the deterministic release ZIP;
- existing production sites remain on the accepted legacy stack until sandbox, dependency, parity, quality and backup evidence are accepted;
- the final baseline remains one self-contained theme with zero required SEO/GEO plugins; Migration Bridge is temporary adoption tooling only;
- installation verification covers canonical/indexability, sitemap, hreflang, Schema, redirects, crawler policy, llms.txt/Markdown opt-ins, accessibility and performance;
- `docs/CLIENT_CLONING.md` separates upstream-owned runtime/release machinery from client presentation customizations;
- client-specific site values stay in onboarding/runtime configuration and secrets stay outside repository/release metadata;
- customized updates require a reviewable three-way merge, relevant CI, deterministic client ZIP, sandbox verification and preservation of the previous accepted client artifact;
- `scripts/ci/validate-client-delivery-docs.py` makes the operational invariants a Foundation contract;
- the root README now points to Phase 10C and the client delivery guides instead of the obsolete Phase 9E pointer.

10C is closed.

Evidence:

- final candidate `74a3e957b2a6e8fae7259aa201853c26254be4a0` passed Foundation CI `35944919439`;
- PR #107 was squash-merged as `d7d2a7e7273289c820abdddc09ddcd0ee1e0eda1`;
- post-merge `main` repeated Foundation CI successfully as `35944954245`;
- Foundation now requires both client delivery guides plus `validate-client-delivery-docs.py`, preventing future documentation edits from dropping sandbox/parity/backups, zero-plugin or safe-update invariants.

### Microphase 10D — Production verification and recovery

Status: **complete**

Deliverables:

- production verification checklist;
- documented rollback/recovery checklist;
- sandbox-to-production acceptance checklist.

Current implementation scope:

- `docs/SANDBOX_TO_PRODUCTION.md` defines sandbox exit, production entry, environment separation, change-window and go/no-go gates;
- `docs/PRODUCTION_VERIFICATION.md` verifies deployed release/checksum identity, runtime health, critical client behavior and material SEO/GEO output on the public production origin;
- production blockers include fatal/5xx failures, unavailable administration, wrong artifact identity, material canonical/indexability/sitemap/hreflang/redirect regressions, broken critical transaction flows, private-content exposure and security-impacting regressions;
- `docs/ROLLBACK_RECOVERY.md` distinguishes theme/runtime rollback from database/uploads recovery and requires the smallest safe recovery action;
- dynamic sites explicitly forbid restoring stale databases over newer orders, submissions or customer changes without an explicit data-recovery decision;
- Phase 8 Migration Bridge rollback remains authoritative only while its accepted cutover state still owns that operation; normal version rollback uses Phase 10 client artifacts;
- `docs/templates/PRODUCTION_ACCEPTANCE_RECORD.example.json` provides a bounded evidence shape linking release, sandbox, backups, quality evidence, production checks and final decision without storing secrets;
- `scripts/ci/validate-production-readiness-docs.py` makes the production-readiness and recovery invariants part of Foundation CI.

10D is closed.

Evidence:

- final candidate `05e12c7fbfc5469d4b2f09cadf9ba7832f65963d` passed Foundation CI `35945432595`;
- PR #109 was squash-merged as `fc4c863bd49628948acb69a3359198cfbf84b09f`;
- post-merge `main` repeated Foundation CI successfully as `35945470124`;
- Foundation now enforces sandbox exit, production abort/go-no-go, runtime-vs-data recovery, dynamic-site stale-database protection and the bounded production acceptance-record schema.

### Microphase 10E — Stable release decision

Status: **active — stable NO-GO; emmake.com sandbox/production acceptance pending**

Deliverables:

- decision on deprecating/removing the transitional standalone Core plugin wrapper;
- real-site production acceptance after sandbox validation;
- first stable release go/no-go evidence.

Current implementation scope:

- `release/stable-release-decision.json` records the machine-readable Phase 10E decision;
- current decision is `no-go`, target `0.1.0` remains `release_channel=prestable`, and the explicit blocker is `real-site-production-acceptance-pending`;
- `docs/STABLE_RELEASE_DECISION.md` defines the exact promotion path from no-go to go without fabricating real-site evidence;
- the standalone `packages/seo-geo-core/seo-geo-core.php` disposition is `deprecated-retained-nondistributed`: deprecated for installation, retained temporarily for compatibility/development, excluded from the release ZIP and not required by acceptance;
- `scripts/ci/validate-stable-release-decision.py` prevents `stable` promotion unless real-site acceptance is marked accepted with a bounded evidence reference and no blockers;
- Foundation CI and Release Artifact CI both enforce the decision gate;
- the changelog remains Unreleased while the decision is no-go;
- Phase 10E cannot close and no stable release may be published until the selected real site completes the Phase 10C/10D sandbox-to-production acceptance;
- PR #111 final candidate `27a353593af57d099d7172fe592a181138e20bdf` passed Foundation `35946063269`, Package `35946063174`, WordPress Smoke `35946063208`, Self-contained Theme `35946062983`, Accessibility/Responsive `35946063107`, Performance `35946063050` and Release Artifact `35946063090`;
- PR #111 was squash-merged as `d3ff8353c08cfce6c796837a74e372ba7daf0073`;
- post-merge `main` repeated all seven triggered gates successfully: Foundation `35946388953`, Package `35946389089`, WordPress Smoke `35946388965`, Self-contained Theme `35946389064`, Accessibility/Responsive `35946388958`, Performance `35946388935` and Release Artifact `35946388985`;
- the deterministic post-merge candidate ZIP reproduces SHA-256 `dae8da490526fd3584387324bc1bc596d17ad5e681513927c0468b48e786ebba`;
- `docs/REAL_SITE_PILOT.md` fixes `https://emmake.com` as the first real-site acceptance target without changing the stable decision until sandbox + production acceptance is actually completed;
- the two-product portfolio is now documented, but SEO/GEO Manager is **not** a new blocker for Theme 0.1.0 and does not change the Phase 10E acceptance decision.

### Microphase 10E.1 — Migration Bridge delivery artifact

Status: **complete**

Purpose:

Create the exact installable Migration Bridge artifact required to begin the real-site emmake.com pilot without waiting for SEO/GEO Manager.

Deliverables:

- deterministic WordPress plugin ZIP rooted at `seo-geo-migration-bridge/`;
- synchronized plugin header/runtime version validation;
- byte-identical repeated builds from the same source;
- normalized ZIP paths, timestamps and file modes;
- PHP syntax validation for every shipped PHP file;
- sibling SHA-256 checksum;
- short-lived GitHub Actions artifact containing the installable ZIP + checksum;
- operator documentation in `docs/MIGRATION_BRIDGE_RELEASE.md`.

Boundary:

- this artifact packages the already-accepted Phase 8 Migration Bridge v0.8.1;
- it does not make Migration Bridge a permanent Theme dependency;
- it does not start Phase 11;
- it is used first for read-only analysis/baseline on emmake.com;
- production Theme activation remains forbidden until sandbox/parity/quality acceptance passes.

10E remains the active stable-release phase; 10E.1 removed the delivery-artifact gap required to execute that acceptance.

Evidence:

- deterministic Migration Bridge release packaging was merged and repeatedly verified on main;
- the real Emmake pilot installed the Bridge and completed the resumable public baseline successfully;
- the Bridge evolved through real-site feedback to bounded resumable capture and configurable 1–20 page batches without changing production content/theme/plugins.

### Microphase 10E.2 — Emmake baseline review and sandbox handoff

Status: **active — production baseline + UNKNOWN review complete; Portable Clone Engine now precedes sandbox acceptance**

Purpose:

Turn the accepted production baseline into explicit dependency review and a privacy-bounded sandbox handoff before any destination-theme migration.

Current real-site evidence:

- emmake.com baseline reported `Ready` on 2026-09-24;
- dependency summary: KEEP=4, REPLACE=2, MIGRATE=1, OPTIONAL=0, REMOVE-CANDIDATE=0, UNKNOWN=13;
- first v0.8.5 sandbox handoff was generated/downloaded with 4,311 discovered resources, 500 captured, zero request failures and `truncated=true`;
- regenerated v0.8.6 handoff at `2026-09-24T19:56:28Z` completed UNKNOWN review: `reviewed_unknown=13`, `unreviewed_unknown=0`, `complete=true`;
- Migration Bridge v0.8.7 established the strict sandbox preflight; v0.8.8 extends it with an explicit same-origin `subdirectory` mode requiring a distinct non-root path plus confirmed storage/runtime isolation;
- operator decisions across the 13 UNKNOWN items are 10 `KEEP` and 3 `MIGRATE`; the three `MIGRATE` items are Classic Editor, Cookie Notice and Kairoseth AI Web Readiness;
- bounded handoff evidence SHA-256: `48b87fed7c8b09be9778f0e62045c0eb8bcf23b35186883ee9f0629a19431d4b`;
- v0.8.6 keeps operator review decisions stored separately from raw dependency-graph authority;
- production cutover remains missing/not authorized;
- final migration report remains missing;
- no production theme switch has occurred.

Deliverables:

- expose bounded dependency component rows, with UNKNOWN items first for review;
- persist explicit capability/nonce-gated UNKNOWN planning decisions without changing raw graph classifications or production cutover authority;
- export authenticated nonce/capability-gated sandbox handoff JSON with raw classifications + bounded review evidence;
- include runtime identity, baseline identity/counts, dependency classifications and sandbox requirements;
- exclude post bodies, builder payloads, credentials, arbitrary option values, database dumps, uploads and customer data;
- create a distinct sandbox clone using fresh backup references;
- mark the clone with `SEO_GEO_MIGRATION_SANDBOX=true`, disable indexing/outbound transactions and install the exact Theme candidate;
- execute dependency migration + parity + accessibility/performance acceptance only in sandbox.

Current execution pointer: **10E.2A.5.3.1 — private same-server package handoff in Migration Bridge 0.8.27.** Migration Bridge 0.8.26 is accepted on `main` at `dc782712540056f61113bcd15af3e28c0710f04b`: the isolated target now has verified WordPress core, Migration Bridge, same-database/different-prefix configuration and active sandbox hardening while client uploads/themes and destination tables remain absent. 0.8.27 reuses the existing private ZIP builder to assemble the already verified `local-clone` workspace into a same-server handoff artifact **without changing the frozen package manifest/hash contract** or exposing a public/download transport. Every batch revalidates ownership, sandbox control-file hashes and package identity; completion freezes archive bytes/SHA-256 and advances only to **10E.2A.5.3.2 — target intake + sandbox preflight**.

Migration Bridge v0.8.26 / 10E.2A.5.2.2.2 acceptance evidence:

- PR #151 squash-merged to `main` as `dc782712540056f61113bcd15af3e28c0710f04b`;
- Foundation CI `36220173552` passed;
- Phase 1 Package CI `36220173550` passed;
- PHP Quality CI `36220173538` passed;
- WordPress Smoke CI `36220173527` passed, including Bridge copy/reverification, isolated config/MU hardening, secret-free state, zero target client tables/content and post-completion control-file tamper invalidation;
- Accessibility & Responsive CI `36220173548` passed;
- Performance Baseline CI `36220173549` passed;
- Migration Bridge Release CI `36220173529` passed.

Migration Bridge v0.8.25 / 10E.2A.5.2.2.1 acceptance evidence:

- PR #149 merged to `main` as `3be6a7abbd3176e98a95307dd16fb2b60e1285e1`;
- Foundation CI `36217284995` passed;
- Phase 1 Package CI `36217284977` passed;
- PHP Quality CI `36217284848` passed;
- WordPress Smoke CI `36217284945` passed, including bounded WordPress core copy, independent topology/file/byte/SHA-256 replay, `wp-content`/`wp-config.php` exclusion and target-file tamper rejection;
- Accessibility & Responsive CI `36217284837` passed;
- Performance Baseline CI `36217284874` passed;
- Migration Bridge Release CI `36217284886` passed.

Migration Bridge v0.8.24 / 10E.2A.5.2.1 acceptance evidence:

- PR #148 squash-merged to `main` as `04f022d63f88f4fea48ad916ce3cc707a89adc77`;
- Foundation CI `36179090681` passed;
- Phase 1 Package CI `36179090765` passed;
- PHP Quality CI `36179090542` passed;
- WordPress Smoke CI `36179090545` passed, including claim/idempotency/safe-release and ownership-marker tamper rejection;
- Accessibility & Responsive CI `36179090489` passed;
- Performance Baseline CI `36179090638` passed;
- Migration Bridge Release CI `36179090708` passed.

Migration Bridge v0.8.23 / 10E.2A.5.1 acceptance evidence:

- PR #147 merged to `main` as `0da6ec5ed055ada635ad750cd684b7e76982205c`;
- Foundation CI `36176018343` passed;
- Phase 1 Package CI `36176018420` passed;
- PHP Quality CI `36176018314` passed;
- WordPress Smoke CI `36176018333` passed, including ready `/nuevaweb/` planning plus production/live-prefix/non-empty-target rejection;
- Accessibility & Responsive CI `36176018349` passed;
- Performance Baseline CI `36176018337` passed;
- Migration Bridge Release CI `36176018389` passed.

Migration Bridge v0.8.22 / 10E.2A.4.6.3 acceptance evidence:

- PR #146 squash-merged to `main` as `d985cf362d1b569ee4324c48a508facb32b8cb1b` after the source-runtime fixture cleanup was corrected;
- Foundation CI `36174452529` passed;
- Phase 1 Package CI `36174452619` passed;
- PHP Quality CI `36174452721` passed;
- WordPress Smoke CI `36174452415` passed, including reversible file promotion/final verification/rollback plus the retained negative rewrite safety case;
- Accessibility & Responsive CI `36174452327` passed;
- Performance Baseline CI `36174452245` passed;
- Migration Bridge Release CI `36174452520` passed.

Migration Bridge v0.8.21 / 10E.2A.4.6.2 acceptance evidence:

- accepted `main` commit `090e46c63b0818ed81d706c57aaa636bf3cf1ec8`;
- post-merge Foundation CI `36169680629` passed;
- post-merge Phase 1 Package CI `36169680581` passed;
- post-merge PHP Quality CI `36169680562` passed;
- post-merge WordPress Smoke CI `36169680502` passed, including prepare → activate → verify → rollback with noindex/control-plane/runtime preservation;
- post-merge Accessibility & Responsive CI `36169680518` passed;
- post-merge Performance Baseline CI `36169680579` passed;
- post-merge Migration Bridge Release CI `36169680611` passed.

Migration Bridge v0.8.19 / 10E.2A.4.5 acceptance evidence:

- accepted `main` commit `47e2d5718372c2b9217916503fbc77a6179893d0`;
- post-merge Foundation CI `36160169424` passed;
- post-merge Phase 1 Package CI `36160169445` passed;
- post-merge PHP Quality CI `36160169454` passed;
- post-merge WordPress Smoke CI `36160169450` passed;
- post-merge Accessibility & Responsive CI `36160169326` passed;
- post-merge Performance Baseline CI `36160169477` passed;
- post-merge Migration Bridge Release CI `36160169468` passed;
- deterministic installable v0.8.19 ZIP SHA-256: `c61c9e64372965febbbe411d0b46e165950e4f6426327e2a078dc523fd36b5c0`.

Migration Bridge v0.8.18 / 10E.2A.4.4 acceptance evidence:

- accepted `main` commit `b06a5f6ff424ca030f4fd78d092a6aa0670f85b9`;
- post-merge Foundation CI `36153619058` passed;
- post-merge Phase 1 Package CI `36153618823` passed;
- post-merge PHP Quality CI `36153618954` passed;
- post-merge WordPress Smoke CI `36153618941` passed, including private verified file staging, second SHA-256 pass, active-root preservation and staged-file tamper rejection;
- post-merge Accessibility & Responsive CI `36153618803` passed;
- post-merge Performance Baseline CI `36153618925` passed;
- post-merge Migration Bridge Release CI `36153618804` passed.

Migration Bridge v0.8.17 / 10E.2A.4.3 acceptance evidence:

- PR #137 squash-merged as `d17cea4b7dfac8df78a0e99da92a15712aef8fc9`;
- post-merge Foundation CI `36151118394` passed;
- post-merge Phase 1 Package CI `36151118291` passed;
- post-merge PHP Quality CI `36151118400` passed;
- post-merge WordPress Smoke CI `36151118299` passed, including transactional staging-table restore and active-table protection;
- post-merge Accessibility & Responsive CI `36151118398` passed;
- post-merge Performance Baseline CI `36151118286` passed;
- post-merge Migration Bridge Release CI `36151118412` passed.

Migration Bridge v0.8.16 / 10E.2A.4.2 acceptance evidence:

- PR #136 squash-merged as `f5b9eaabe124b3c46f971ac392b71e40be18192d`;
- post-merge Foundation CI `36145441243` passed;
- post-merge Phase 1 Package CI `36145441159` passed;
- post-merge PHP Quality CI `36145441176` passed;
- post-merge WordPress Smoke CI `36145441255` passed, including resumable private extraction, exact checksum replay, destination-drift rejection and checksum-mismatch rejection;
- post-merge Accessibility & Responsive CI `36145441143` passed;
- post-merge Performance Baseline CI `36145441244` passed;
- post-merge Migration Bridge Release CI `36145441130` passed.

Migration Bridge v0.8.15 / 10E.2A.4.1 acceptance evidence:

- PR #135 squash-merged as `744384bfad7ec12f8c9445b53ac362746679ff88`;
- post-merge Foundation CI `36141521870` passed;
- post-merge Phase 1 Package CI `36141521962` passed;
- post-merge PHP Quality CI `36141521960` passed;
- post-merge WordPress Smoke CI `36141521891` passed, including private staging, destination authorization blocker, valid preflight and child-manifest tamper rejection;
- post-merge Accessibility & Responsive CI `36141521964` passed;
- post-merge Performance Baseline CI `36141521877` passed;
- post-merge Migration Bridge Release CI `36141521885` passed;
- deterministic installable v0.8.15 ZIP SHA-256: `a678b8bee7644a0162860c82b751c600443db6a2ebff6bbf9f8c51276fece9d0`.

Migration Bridge v0.8.14 / 10E.2A.3.4 acceptance evidence:

- PR #133 merged as `2f7249e820a9e36c0d75fc95f43f695db017dbda`;
- post-merge Foundation CI `36137249879` passed;
- post-merge Phase 1 Package CI `36137249445` passed;
- post-merge PHP Quality CI `36137249424` passed;
- post-merge WordPress Smoke CI `36137249674` passed, including private resumable ZIP build, auth-only download boundary, archive hash verification, 24-hour expiry denial and bounded cleanup preserving unrelated files;
- post-merge Accessibility & Responsive CI `36137249673` passed;
- post-merge Performance Baseline CI `36137249588` passed;
- post-merge Migration Bridge Release CI `36137249573` passed;
- deterministic installable v0.8.14 ZIP SHA-256: `e81a412bafff42f1c8c5a41370313373a5b83ec5ac73b8ad454973c7e2d9bab0`.

Migration Bridge v0.8.13 / 10E.2A.3.3 acceptance evidence:

- PR #131 squash-merged as `896c449116980238e4163da6b15ee4caec20b67c`;
- post-merge Foundation CI `36133942803` passed;
- post-merge Phase 1 Package CI `36133942785` passed;
- post-merge PHP Quality CI `36133942874` passed;
- post-merge WordPress Smoke CI `36133942852` passed, including the positive two-pass checksum case and deliberate payload-tamper blocker;
- post-merge Accessibility & Responsive CI `36133942801` passed;
- post-merge Performance Baseline CI `36133942970` passed;
- post-merge Migration Bridge Release CI `36133942826` passed;
- deterministic installable v0.8.13 ZIP SHA-256: `d7bb4225712274b8fb5538addf053f5a6c2b635174f4b9cb17bbd08059d10954`.

Migration Bridge v0.8.7 acceptance evidence:

- PR #122 was squash-merged as `a6dfb95cdcbb33a823b8c8e12ff18556c47471f2`;
- post-merge Foundation CI `36054164606` passed;
- post-merge Phase 1 Package CI `36054164347` passed;
- post-merge PHP Quality CI `36054164247` passed;
- post-merge WordPress Smoke CI `36054164366` passed;
- post-merge Accessibility & Responsive CI `36054164383` passed;
- post-merge Performance Baseline CI `36054164515` passed;
- post-merge Migration Bridge Release CI `36054164341` passed;
- deterministic installable v0.8.7 ZIP SHA-256: `683d5a78c56b6c073963703153d2969f71f5b8f17e16dab7da4b05878133ebc9`.

### Microphase 10E.2A — Portable Clone Engine

Status: **active — 10E.2A.1 through 10E.2A.5.2.2.2 accepted; 10E.2A.5.3.1 private same-server package handoff is the 0.8.27 candidate**

Purpose:

Remove the external migration/staging-plugin dependency from the supported migration path. Migration Bridge must be able to create or transport the sandbox itself while preserving all existing baseline/dependency/privacy/cutover boundaries.

Authoritative contract:

- `docs/PORTABLE_CLONE_ENGINE.md`;
- `docs/PORTABLE_SANDBOX.md`;
- existing Phase 8 sandbox/migration/parity/cutover contracts.

Implementation sequence:

- **10E.2A.1 — Clone contract + persistent resumable jobs**: complete in Migration Bridge 0.8.9; PR #127 merged and all seven post-merge gates passed on `2c62082431344f2abaeb4bb80c6d618814b69e9d`;
- **10E.2A.2 — Read-only source inventory**: complete in Migration Bridge 0.8.10; PR #128 squash-merged as `2aec389db8cefb0a3e42e99751857a497c1b2ab8` and all seven post-merge gates passed;
- **10E.2A.3.1 — Database export**: complete in Migration Bridge 0.8.11 with a private temporary workspace, resumable schema/row chunks, primary-key or deterministic fallback cursors and per-chunk/database-manifest SHA-256;
- **10E.2A.3.2 — File export**: complete in Migration Bridge 0.8.12 with deterministic uploads/plugins/themes traversal, bounded file/byte batches, atomic private copies, per-file SHA-256 and exact source-inventory reconciliation;
- **10E.2A.3.3 — Package manifest + integrity**: complete in Migration Bridge 0.8.13; PR #131 squash-merged as `896c449116980238e4163da6b15ee4caec20b67c`, with exported-record revalidation, resumable full-workspace SHA-256 chaining, a second exact verification pass before `verified=true`, and tamper rejection;
- **10E.2A.3.4 — Authenticated package delivery + retention cleanup**: complete in Migration Bridge 0.8.14; PR #133 merged as `2f7249e820a9e36c0d75fc95f43f695db017dbda`, with resumable private ZIP assembly, replayed package checksum, archive SHA-256, administrator/job-nonce download only, 24-hour expiry and bounded cleanup; all seven post-merge gates passed;
- **10E.2A.3 — Resumable export**: complete through 10E.2A.3.4;
- **10E.2A.4.1 — Import intake + destination preflight**: complete in Migration Bridge 0.8.15; PR #135 squash-merged as `744384bfad7ec12f8c9445b53ac362746679ff88`, with private ZIP intake, archive/manifest validation, isolated-target preflight and restore disabled; all seven post-merge gates passed;
- **10E.2A.4.2 — Full payload verification + resumable extraction**: complete in Migration Bridge 0.8.16; exact extracted payload checksum replay + fresh destination revalidation accepted;\n- **10E.2A.4.3 — Database restore**: active in 0.8.17; restore only to deterministic job-owned transactional staging tables, transactionally persisting rows + resumable cursor while active destination tables remain untouched; extract only into the private import workspace in bounded resumable batches, replay `lexicographic-bfs-path+bytes+sha256-v1`, reject archive/package drift and unlock restore only after exact file/byte/checksum parity;
- **10E.2A.4.3 — Database restore**: complete in 0.8.17; verified rows restore only into deterministic job-owned transactional staging tables while active WordPress tables remain untouched;
- **10E.2A.4.4 — File restore**: complete in 0.8.18; copy verified uploads/plugins/themes only into job-owned private staging, then perform a second bounded SHA-256 pass before completion;
- **10E.2A.4.5 — Serialization-safe environment rewrite**: complete in 0.8.19 with structural PHP serialization/JSON rewrite, credential opacity and a second idempotence pass;
- **10E.2A.4.6.1 — Finalization preflight**: complete in 0.8.20 with bounded staging fingerprints and immutable activation/rollback plan;
- **10E.2A.4.6.2 — Reversible database activation**: complete in 0.8.21 with external recovery journal, noindex/control-plane preservation and atomic reverse rename;
- **10E.2A.4.6.3 — Reversible file promotion + final target verification**: complete in 0.8.22; PR #146 merged as `d985cf362d1b569ee4324c48a508facb32b8cb1b`;
- **10E.2A.4 — Portable import**: complete through guarded database/file activation and final integrity verification;
- **10E.2A.5.1 — Local clone destination plan + ownership contract**: complete in 0.8.23; PR #147 merged as `0da6ec5ed055ada635ad750cd684b7e76982205c`, binding a verified package to an immutable isolated same-server path/URL/table-prefix/capacity contract with zero target mutation;
- **10E.2A.5.2.1 — Target ownership + recovery marker**: complete in 0.8.24; PR #148 squash-merged as `04f022d63f88f4fea48ad916ce3cc707a89adc77`, with immutable-plan/package revalidation, deterministic ownership marker, safe empty-target release and tamper rejection;
- **10E.2A.5.2.2.1 — Resumable WordPress core runtime copy**: complete in 0.8.25; PR #149 merged as `3be6a7abbd3176e98a95307dd16fb2b60e1285e1`, with core-only allowlist copy, centralized `ExportWorkspace` mutations, `wp-content`/`wp-config.php` exclusion and an independent topology/bytes/SHA-256 verification pass before `runtime_core_ready=true`;
- **10E.2A.5.2.2.2 — Migration Bridge runtime + isolated configuration + enforced sandbox hardening**: complete in 0.8.26; PR #151 squash-merged as `dc782712540056f61113bcd15af3e28c0710f04b`, with independently verified Bridge control runtime, same-database/different-prefix config, active noindex/outbound hardening and control-file tamper invalidation while client content/target tables remain absent;
- **10E.2A.5.2.2 — Bounded independent WordPress runtime + sandbox config/hardening**: complete through 5.2.2.2;
- **10E.2A.5.2 — Isolated target bootstrap**: complete through 5.2.2.2;
- **10E.2A.5.3.1 — Private same-server package handoff**: active in 0.8.27; reuse the existing private ZIP builder for the verified `local-clone` workspace without rewriting the frozen package manifest, keep the job active, bind archive SHA-256/bytes to ownership + sandbox hashes + package identity and expose no public/download transport;
- **10E.2A.5.3.2 — Target intake + sandbox preflight**: planned; make the private handoff artifact consumable by the isolated target through the accepted Portable Import validation/extraction primitives and require a fresh sandbox preflight before any database/file restore;
- **10E.2A.5.3 — Local package handoff + sandbox preflight**: active through 5.3.1;
- **10E.2A.5 — Local clone orchestration**: active; direct production → isolated same-server clone using the same export/import primitives, including `/nuevaweb/`;
- **10E.2A.6 — Emmake real clone acceptance**: create/verify the actual `emmake.com/nuevaweb/` clone, preserve baseline/dependency/review evidence and require sandbox `ready=true`.

Non-negotiables:

- production export/source stages are read-only;
- clone/import packages never enter Git/repository evidence;
- no generic stale sandbox → production database overwrite operation exists;
- subdirectory URL difference alone is not storage isolation;
- all long-running work is resumable/batched;
- package integrity is verified before restore;
- import cannot target production accidentally;
- sandbox hardening/preflight remains mandatory before migration.

The existing manual/hosting clone path remains a fallback while the engine is being implemented, but the target product path must not require a third-party cloning plugin.

### Microphase 10E.2B — Emmake sandbox migration/parity acceptance

Status: **blocked by 10E.2A.6**

Purpose:

Run the already-accepted content/theme migration, SEO/GEO parity, accessibility/responsive, performance and client-critical functionality gates on the Engine-created `/nuevaweb/` sandbox.

10E.2B may start only after the Portable Clone Engine proves the clone is isolated, integrity-verified and sandbox-ready.

10E.2 closes only after the sandbox is actually created and accepted. Production remains unchanged until then.

Phase 10 overall deliverables:


- reproducible **single-theme ZIP** build;
- embedded SEO/GEO runtime integrity check;
- versioning/changelog;
- clean-install and upgrade tests with zero required plugins;
- migration/install documentation for real client sites;
- documentation for project cloning and per-client customization;
- production verification checklist;
- documented rollback/recovery checklist;
- decision on deprecating/removing the transitional standalone Core plugin wrapper;
- real-site production acceptance after sandbox validation before declaring the first stable release.

## Product-track rule after Phase 10

The roadmap now has two independently releasable products:

- **SEO/GEO Theme** — Phases 0–10. Phase 10E remains the current execution pointer until the real-site stable-release acceptance closes.
- **SEO/GEO Manager** — Phase 11 onward. It is planned now but must not be treated as active implementation while Phase 10E remains open under the project finish-before-advancing rule.

The products may share context-neutral source, but they have independent versioning, ZIP artifacts, changelogs, compatibility matrices and stable-release decisions.

The Manager roadmap does **not** reopen accepted Theme phases and does not make the Theme depend on a plugin.

## Phase 11 — SEO/GEO Manager product

Status: **planned — starts only after Phase 10E closes**

Goal:

Create a permanent, independently installable WordPress plugin that can analyze existing client sites, publish optimized landings/blogs, coordinate migration and continue operating after migration without requiring the SEO/GEO Theme or GitHub.

Authoritative contracts:

- `docs/PRODUCT_PORTFOLIO.md`;
- `docs/SEO_GEO_MANAGER.md`;
- `docs/CONTENT_PUBLISHING.md`;
- `docs/PORTABLE_SANDBOX.md`;
- accepted Phase 8 Migration Bridge behavior as migration regression baseline.

### Microphase 11A — Manager package and independent release boundary

Status: **planned**

Deliverables:

- create `packages/seo-geo-manager/` as a new plugin product, not a rename of `seo-geo-core.php`;
- independent plugin version source, changelog and package metadata;
- install/activate/deactivate with the Theme absent;
- install/activate with the Theme present;
- collision-safe packaging of any shared Core source needed by Manager;
- no frontend remote-service dependency for normal page rendering;
- EN/ES operator copy foundation;
- Manager-specific Foundation/static package contract;
- minimal WordPress smoke without changing client content.

Acceptance:

- Theme remains zero-required-plugin;
- Manager activates independently;
- Theme + Manager boots with no duplicate runtime initialization or public SEO/GEO output;
- deprecated Core wrapper remains deprecated and is not repurposed.

### Microphase 11B — Site Intelligence + Output Authority Resolver

Status: **planned**

Deliverables:

- reuse/port the accepted read-only site inventory and public baseline concepts;
- current theme/builder/plugin/business-system detection;
- external SEO/Schema/multilingual provider detection;
- per-signal authority decision for title/meta, canonical, robots, Open Graph, hreflang, Schema, sitemap, redirects and discovery surfaces;
- explicit states for Theme-native, Manager-native, external-provider, WordPress-core, manual-review and blocked-conflict;
- zero mutation while analyzing/deciding ownership.

Acceptance:

- Theme active → Theme remains default overlapping output authority;
- supported external provider → Manager does not duplicate that output;
- ambiguous/multiple providers → affected mutations are blocked;
- no provider/generic theme path is classified but does not silently enable Manager-native output.

### Microphase 11C — Secure content publication core

Status: **planned**

Deliverables:

- versioned publication manifest;
- authenticated/scoped publication endpoint;
- dry-run and diff;
- draft-first creation;
- explicit publish/schedule authorization;
- idempotency keys;
- stable resource IDs;
- expected-previous-fingerprint protection against stale overwrites;
- bounded Manager-owned metadata mutation;
- revision/change-set record;
- rollback of Manager-owned changes;
- public-output verification after publish/update;
- non-sensitive publication report.

Acceptance:

- duplicate retry cannot create duplicate content;
- payload mismatch on reused idempotency key is rejected;
- stale update cannot overwrite newer human edits;
- rollback does not restore unrelated site-wide/dynamic data;
- deactivation/uninstall never deletes client-created content by default.

### Microphase 11D — Blog Engine

Status: **planned**

Deliverables:

- native WordPress post draft/update/publish path;
- native-block content renderer first;
- explicit author/provenance;
- controlled category/tag policy;
- source/reference model;
- internal-link plan;
- featured-media references;
- localized article relationships;
- provider-aware SEO/GEO metadata application;
- real WordPress publication verification.

Acceptance:

- no fabricated authors, sources, quotes, dates or reviewer expertise;
- BlogPosting/Article behavior only for genuine article surfaces;
- retry/update/rollback acceptance;
- EN/ES content relationship acceptance;
- single-owner SEO/GEO output.

### Microphase 11E — Landing Engine and content adapters

Status: **planned**

Deliverables:

- landing intent/target registry;
- slug/path/canonical planning;
- native-block landing renderer first;
- reusable layout/pattern composition;
- unique value requirements for location/service/product landings;
- duplicate-intent/near-duplicate review;
- internal/external link plan;
- provider-aware SEO/GEO application;
- accepted write-adapter contract for future Elementor/Divi support.

Acceptance:

- no mass city/service token swapping;
- no doorway-page batch acceptance without unique value;
- no invented business facts, reviews, prices, certifications or performance claims;
- unsupported builder modules block rather than disappear;
- URL collisions and canonical/indexability changes are explicit.

### Microphase 11F — Migration module absorption

Status: **planned**

Deliverables:

- move/compose accepted Phase 8 analyzer, baseline, dependency graph, migration adapters, parity, cutover/rollback and final report into Manager module boundaries;
- preserve privacy/capability/nonce/backup contracts;
- migration mode can be enabled/disabled independently of publishing;
- compatibility layer for accepted migration handoff where needed;
- side-by-side regression acceptance against existing Migration Bridge fixtures.

Retirement rule:

The standalone `packages/seo-geo-migration-bridge` package cannot be removed until Manager proves equivalent or stronger acceptance for every supported Phase 8 contract. Package retirement must be its own reviewed change.

### Microphase 11G — Portable Sandbox coordinator

Status: **planned**

Deliverables:

- three supported modes: client staging, agency-managed portable sandbox, limited-access/export;
- provider-neutral provisioner interface;
- sandbox package/manifest;
- environment isolation checks;
- outbound email/payment/analytics safety guidance;
- dynamic-site classification;
- fresh-production re-read/data-sync rule before cutover;
- evidence freshness policy.

Acceptance:

- clients without staging can complete migration acceptance without production-first testing;
- sandbox origin cannot leak into canonical/hreflang/sitemap;
- stale sandbox DB can never be blindly restored over newer production orders/submissions/users;
- no credentials/backups/private payloads enter the repository.

### Microphase 11H — Commercial packaging and operator UX

Status: **planned**

Deliverables:

- deterministic Manager plugin ZIP + checksum/integrity;
- independent upgrade/rollback path;
- EN/ES operator dashboard for analysis, publishing and migration modules;
- module enable/disable boundaries;
- safe uninstall/deactivation behavior;
- update-channel contract;
- licensing/update-delivery boundary if commercial licensing is added;
- license-service outage must not break the public website or delete client content;
- client installation/admin guide.

### Microphase 11I — Manager real-site acceptance and first stable release

Status: **planned**

Required real-site matrix:

1. **Theme + Manager** — use emmake.com as the first integration pilot after Theme Phase 10E is already accepted;
2. **Manager without Theme** — at least one representative existing WordPress site retaining its current supported theme/provider stack.

Acceptance must include:

- analysis without destructive mutation;
- draft-first blog publication;
- draft-first landing publication;
- idempotent retry;
- update + stale-write rejection;
- rollback;
- authority ownership with no duplicate SEO/GEO output;
- portable-sandbox path where applicable;
- accessibility/responsive and performance impact;
- clean logs;
- bounded production evidence.

Only then may the Manager's own first stable release be promoted.

## Phase 12 — Content operations and agency scale

Status: **planned — only after Manager stable foundation**

Phase 12 scales the publishing product without weakening Phase 11 safety contracts.

### Microphase 12A — Editorial orchestration

- content brief/status workflow;
- content calendar;
- draft/review/approval queue;
- scheduled publication with observable retry semantics;
- provider-neutral generation adapters;
- generation and publication remain separate authorization steps.

### Microphase 12B — Content refresh and drift monitoring

- detect stale content/metadata/internal links;
- detect broken links and material SEO/GEO ownership drift;
- propose refreshes as reviewable change sets;
- never auto-delete or deindex content solely because traffic/rankings changed.

### Microphase 12C — Multi-client agency controller

- central inventory of connected Manager installations;
- scoped/revocable site credentials;
- queue/status/reporting across clients;
- per-site permissions and rate limits;
- no shared supercredential that grants unrestricted WordPress administration;
- client isolation and audit trail.

### Microphase 12D — Search/performance feedback integrations

- optional Search Console/Bing/analytics data adapters;
- connect performance data to editorial decisions;
- distinguish observed metrics from causal/ranking claims;
- no automatic content mutation based only on volatile ranking/traffic signals.

### Microphase 12E — Expanded compatibility matrix

- individually accepted adapters for additional SEO, multilingual, builders, forms, ecommerce and hosting environments;
- detection never equals support;
- each adapter receives its own fixture/runtime acceptance before being labeled supported.

## Cross-product non-negotiables

These rules apply permanently:

- Theme works with zero required plugins.
- Manager works without Theme on supported WordPress installations.
- Manager is not the deprecated Core wrapper.
- Exactly one owner exists per overlapping public SEO/GEO signal.
- Existing client sites are analyzed before mutation.
- Publishing is draft-first by default, idempotent and rollback-capable.
- Migration never assumes the client already has staging.
- Dynamic production data is never replaced by a stale sandbox database without an explicit data-recovery decision.
- Unsupported builders/providers are blockers/manual-review items, not silent data loss.
- Credentials/private client data/backups never enter repository evidence.
- Each product has its own version, release artifact and stable-release decision.

## Backlog rules

A feature only enters a phase when:

- it belongs to the product scope;
- its authoritative owner/module is known;
- multilingual impact is understood;
- SEO/performance/accessibility impact is known;
- acceptance can be tested;
- it does not turn an optional third-party plugin into a baseline dependency.