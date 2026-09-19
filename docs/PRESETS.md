# Presets

Presets speed up delivery without turning the foundation into a bloated multipurpose theme. A preset composes templates, patterns, Schema defaults and optional integrations. It must not fork the Core plugin.

## Common preset contract

Every preset declares:

- intended site type;
- required templates;
- recommended patterns;
- expected Schema entities;
- navigation defaults;
- sample content map;
- multilingual considerations;
- SEO acceptance checks;
- optional integrations;
- features explicitly not enabled.

Presets must work in ES and EN and remain translatable to additional locales.

## Corporate

### Core pages
- Home
- Services index
- Service detail
- About
- Case studies / work
- Blog / insights
- Contact
- Legal pages

### Typical Schema
- Organization
- WebSite
- WebPage
- BreadcrumbList
- Article/BlogPosting
- Person/ProfilePage for authors/team where applicable

### Patterns
- Hero
- Trust/logos
- Service cards
- Benefits/features
- Case study teaser
- Stats
- Testimonials
- FAQ where useful
- CTA
- Contact block

### Phase 7A implementation contract

The Corporate preset is bundled under `presets/corporate/` and remains inactive until the server-side option `seo_geo_active_preset` resolves to the allowlisted value `corporate`.

The preset ships three declarative documents:

- `preset.json`: site type, template dependencies, base/preset patterns, Schema expectations, navigation defaults, multilingual baseline, SEO acceptance and explicit non-features;
- `content-map.json`: matching EN/ES information architecture for Home, Services, Work, About, Insights, Contact and legal pages, plus repeatable service-detail/case-study guidance;
- `patterns.json`: Corporate-only case-study, metrics and testimonial patterns in EN/ES.

The neutral theme patterns remain untouched. Corporate adds only the missing archetype-specific compositions:

- case-study teaser;
- verified metrics;
- testimonial placeholders.

Preset patterns are editor scaffolding, not factual claims. They explicitly require authors to replace prompts with real, verifiable information before publication. The preset never generates client names, endorsements, performance figures, certifications or other proof.

Corporate recommends `Organization` as the site identity, but `requires_confirmation=true` is mandatory. Activating the visual/information-architecture preset does not automatically enable Organization Schema. Phase 8 onboarding may offer the choice, but the existing server-side Schema identity authority remains responsible for the final configuration.

The distributable theme bundles preset data under `/presets`. `inc/presets.php` is the runtime registry used by later onboarding:

- preset IDs are allowlisted server-side;
- unsupported IDs resolve to no active preset;
- the default installation has no active preset;
- Corporate patterns register only when Corporate is active;
- preset-owned copy selects ES or EN from the active WordPress locale, with English fallback;
- no preset can fork or replace the SEO/GEO Core runtime.

Phase 7A does not create pages automatically. It establishes the validated content map and runtime registry that Phase 8 onboarding will use to create/configure site content with explicit administrator intent.

## Local Business

### Core pages
- Home
- Services
- Service detail
- Locations or service areas
- Service + location landing page when genuinely useful
- About
- FAQ
- Contact

### Typical Schema
- Organization
- most specific applicable LocalBusiness subtype
- PostalAddress
- GeoCoordinates when truthful/available
- opening hours where applicable
- BreadcrumbList
- WebPage

### Native LocalBusiness entity contract
- Site identity must be explicitly selected as `local_business`; the theme never guesses it from content.
- Business name and URL reuse visible/authoritative WordPress site state.
- Physical address is mandatory and must be visibly present on the public front page before LocalBusiness Schema is emitted.
- The subtype is selected from the supported LocalBusiness allowlist and falls back safely to generic `LocalBusiness`.
- Telephone and price range are emitted only when the same configured values are visible to readers.
- Opening-hours entries require an explicit visible-text counterpart in the public page; coordinates require a valid visible physical-address context.
- Reviews, ratings, images and service areas are not fabricated from unrelated content.
- One physical location is the Phase 5 baseline; multi-location modeling belongs to the Local Business preset implementation in Phase 7.

### Rules
- Never generate thin city pages automatically.
- NAP/contact data comes from one authoritative entity configuration.
- Location pages must contain real location-specific value.
- `areaServed` is not a license to create doorway pages.

### Phase 7B implementation contract

The Local Business preset is bundled under `presets/local-business/` and activates only when `seo_geo_active_preset=local-business`.

It composes the existing native LocalBusiness Schema authority instead of creating a second local-SEO stack. Activating the preset **does not** set `seo_geo_schema_identity`; the administrator must still explicitly confirm `local_business` and provide visible authoritative business facts before Schema can emit.

The preset ships:

- `preset.json`: site type, Schema expectations, anti-doorway rules, navigation, multilingual baseline and an explicit single/multi-location content model;
- `content-map.json`: matching EN/ES singleton pages plus repeatable service, physical-location and genuine service-area page guidance;
- `patterns.json`: visible NAP/contact/hours scaffolding, real service-area guidance and location-specific operational details.

The location model supports more than one real location at the **content architecture** level without fabricating extra Schema entities. The native Phase 5 resolver remains authoritative for one explicitly configured physical LocalBusiness entity until a separate multi-location Schema authority is deliberately designed and accepted.

Local Business adds three preset-owned patterns:

- visible NAP/contact/hours details;
- real service-area coverage;
- physical-location details.

All three are prompts, not business facts. They must be replaced with real public information. The preset never guesses addresses, coordinates, telephone numbers, opening hours, ratings, reviews or service areas.

The multi-location/content rules are mandatory:

- no automatic city-page generation;
- no city-name token swapping;
- one locations index may link to real distinct locations or service areas;
- every physical-location page must contain unique operational value;
- every service-area page must contain genuine area-specific availability/logistics/constraints;
- Schema visible-fact gates continue to apply independently of the preset.

The preset registry is shared with Corporate. Pattern categories are declarative per preset, activation is mutually isolated, and an unsupported preset ID registers neither Corporate nor Local Business patterns.

Phase 7B does not create pages automatically. Phase 8 onboarding may consume this validated map only after explicit administrator intent.

## Ecommerce

### Core pages
- Home
- Shop/categories
- Product
- Brand/editorial landing pages where useful
- Buying guides
- FAQ/support
- About/contact/legal

### Integrations
- WooCommerce first.
- Product Schema ownership coordinated with WooCommerce/selected SEO provider.
- Multilingual commerce handled by selected provider integration, not custom duplicated product routing.

### Rules
- Product structured data must reflect visible price/availability facts.
- Category content remains useful and crawlable.
- Faceted/filter URLs require explicit indexability policy.

### Phase 7D implementation contract

The Ecommerce preset is bundled under `presets/ecommerce/` and activates only when `seo_geo_active_preset=ecommerce`.

The preset is deliberately **zero-plugin safe**. WooCommerce is the preferred future commerce provider, but Phase 7D does not declare WooCommerce a supported compatibility combination. Live commerce requires a separately implemented and accepted adapter/ownership contract.

Commerce ownership is explicit:

- the theme may own Organization/WebSite/WebPage/BreadcrumbList where already supported;
- Product, Offer, AggregateRating and Review remain commerce/provider-owned;
- price, stock/availability, reviews, ratings and offers are never inferred by the preset;
- product/category/shop routing remains provider-owned;
- multilingual commerce routing remains provider/integration-owned;
- faceted/filter URLs remain non-assumed and require an explicit provider/SEO indexability policy;
- checkout, cart and account are provider surfaces, not SEO landing pages created by the preset.

The preset ships:

- `preset.json`: provider boundaries, Product Schema ownership, anti-inference rules, navigation, multilingual contract and optional WooCommerce preference;
- `content-map.json`: matching EN/ES editorial/policy pages plus provider-owned shop, product-category and product surfaces;
- `patterns.json`: category guide, buying guide, policy navigation and optional brand/editorial landing scaffolding.

All Ecommerce preset patterns use native WordPress core blocks. They intentionally contain no WooCommerce block dependency so the theme remains installable and usable with zero plugins.

The dynamic commerce surfaces are declarative only until a supported adapter exists:

- **Shop** → provider-owned;
- **Product category** → provider-owned;
- **Product** → provider-owned.

The optional brand/editorial page is a normal WordPress page and is created only when it adds original value. It must not be mass-generated for every brand and must not copy manufacturer claims without verification.

Phase 7D does not create products, prices, stock, reviews, offers, categories, facets or commerce routes. Phase 8 onboarding may activate the preset and create only theme-owned editorial/policy scaffolding; commerce surfaces remain integration-gated.

## Publisher

### Core pages
- Home
- Article
- Topic/category
- Author profile
- Archive
- About/editorial policy

### Typical Schema
- Organization
- WebSite
- Article/BlogPosting
- Person
- ProfilePage
- BreadcrumbList

### Patterns
- Article summary
- Key facts
- Source/reference list
- Author card
- Related content
- Updated/reviewed metadata

### Phase 7C implementation contract

The Publisher preset is bundled under `presets/publisher/` and activates only when `seo_geo_active_preset=publisher`.

Publisher composes the existing editorial authorities instead of creating a parallel publishing stack:

- built-in WordPress `post` remains the article authority;
- the real WordPress `post_author` relationship and public author archive remain the author authority;
- native BlogPosting/Person/ProfilePage Schema remains owned by SEO/GEO Core;
- publication and modification dates remain the WordPress post timestamps already reused by provenance;
- Organization publisher identity remains an explicit opt-in and is never enabled by preset activation.

The preset ships:

- `preset.json`: editorial authority boundaries, Schema expectations, navigation, multilingual baseline and explicit anti-inference rules;
- `content-map.json`: matching EN/ES singleton pages plus native dynamic article, category/topic and author-profile surfaces;
- `patterns.json`: article summary, verified key facts, checked source/reference list and genuinely related internal content.

The patterns listed in the generic Publisher concept map to implementation as follows:

- **Article summary** → Publisher preset pattern;
- **Key facts** → Publisher preset pattern;
- **Source/reference list** → Publisher preset pattern;
- **Author card** → reuse neutral `seo-geo-theme/author-profile`;
- **Related content** → Publisher preset pattern;
- **Updated/reviewed metadata** → reuse native provenance for published/modified dates; no visual pattern invents editorial dates or a reviewer.

Publisher editorial safeguards are mandatory:

- no fabricated citations, source URLs or primary-source claims;
- no inferred author expertise, credentials or biography;
- no automatic `reviewed by` identity;
- no guessed publication/update dates;
- no Article/BlogPosting Schema on normal WordPress pages;
- no automatic topic/category generation;
- source lists contain only references that editors actually used and checked;
- related-content blocks link only to genuinely relevant published material;
- author profile pages resolve from real WordPress users with public authored content.

The EN/ES content map distinguishes **singleton pages** from **native dynamic surfaces**. Article, topic/category archive and author profile are not automatically created as duplicate pages during onboarding.

Phase 7C does not create editorial content, authors, categories or sources automatically. Phase 8 onboarding may activate the preset and create only the declared singleton scaffolding after explicit administrator intent.

## Future presets

Potential presets such as travel, SaaS, professional services or events should only be added when they express a repeatable information architecture and acceptance contract. They should compose primitives from the existing theme/Core rather than introduce parallel SEO stacks.
