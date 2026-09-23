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

A temporary **SEO/GEO Migration Bridge** is permitted as a separate plugin/tool because it must inspect the existing installation before the destination theme is active. It is not a baseline dependency of the final theme and must be removable after migration.

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

Status: **active**

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

Status: **active**

Deliverables:

- WordPress-native theme setup screen;
- EN/ES operator copy together;
- capability, nonce and explicit confirmation for mutations;
- accessible keyboard/focus/error behavior;
- responsive administration layout.

### Microphase 9E — Setup execution and generated report

Status: **planned**

Deliverables:

- atomic application of validated setup choices;
- migrated-site handoff consumption without Migration Bridge runtime dependency;
- advisory compatibility warnings;
- non-sensitive generated setup report;
- idempotent rerun/update behavior.

### Microphase 9F — Onboarding acceptance

Status: **planned**

Deliverables:

- clean-install and migrated-site acceptance;
- zero-required-plugin acceptance;
- EN/ES browser accessibility/responsive acceptance;
- performance baseline;
- security/PHP quality/static contracts;
- proof that onboarding never instructs installation of an SEO/GEO plugin.

The onboarding flow must not install or require an SEO/GEO plugin to complete the baseline setup.

## Phase 10 — Distribution and production release

Status: **planned**

Deliverables:

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

## Backlog rules

A feature only enters a phase when:

- it belongs to the product scope;
- its authoritative owner/module is known;
- multilingual impact is understood;
- SEO/performance/accessibility impact is known;
- acceptance can be tested;
- it does not turn an optional third-party plugin into a baseline dependency.