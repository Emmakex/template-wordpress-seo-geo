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

Implement in this order:

1. Corporate
2. Local Business
3. Publisher
4. Ecommerce

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

Status: **in progress**

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

7C closes only after implementation PR gates and post-merge `main` verification are green.

### Remaining Phase 7 work

4. Ecommerce

## Phase 8 — Theme onboarding

Deliverables:

- theme-owned setup wizard/admin screen;
- preset choice;
- primary/additional language configuration;
- organization/entity basics;
- crawler/GEO opt-ins;
- generated setup report;
- optional detection of external systems only for compatibility warnings or enhancements.

The onboarding flow must not instruct users to install an SEO/GEO plugin to complete the baseline setup.

## Phase 9 — Distribution

Deliverables:

- reproducible **single-theme ZIP** build;
- embedded SEO/GEO runtime integrity check;
- versioning/changelog;
- clean-install and upgrade tests with zero required plugins;
- documentation for project cloning and per-client customization;
- production verification checklist;
- decision on deprecating/removing the transitional standalone Core plugin wrapper.

## Backlog rules

A feature only enters a phase when:

- it belongs to the product scope;
- its authoritative owner/module is known;
- multilingual impact is understood;
- SEO/performance/accessibility impact is known;
- acceptance can be tested;
- it does not turn an optional third-party plugin into a baseline dependency.