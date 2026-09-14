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

### Rules
- Never generate thin city pages automatically.
- NAP/contact data comes from one authoritative entity configuration.
- Location pages must contain real location-specific value.
- `areaServed` is not a license to create doorway pages.

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

## Future presets

Potential presets such as travel, SaaS, professional services or events should only be added when they express a repeatable information architecture and acceptance contract. They should compose primitives from the existing theme/Core rather than introduce parallel SEO stacks.
