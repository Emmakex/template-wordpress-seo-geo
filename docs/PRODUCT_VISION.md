# Product Vision

## Purpose

Create a reusable **self-contained WordPress block theme** that lets a team launch high-quality websites quickly without trading away performance, technical SEO, multilingual correctness, accessibility or future compatibility with AI-assisted discovery.

The product is not a page-builder bundle and not a wrapper around third-party SEO plugins. It is an opinionated foundation that supplies native SEO/GEO behavior, safe defaults, reusable patterns and automated quality gates in one installable theme package.

## Product principles

1. **Performance is architecture, not a cleanup task.** Avoid unnecessary runtime dependencies, third-party assets and global CSS/JS.
2. **SEO fundamentals come before GEO extras.** Crawlability, indexability, canonicalization, internal linking, meaningful content and structured data must be correct first.
3. **GEO means making content understandable and retrievable, not gaming AI systems.** We optimize semantic structure, provenance, entities and machine-friendly access while avoiding unsupported ranking claims.
4. **Plugin independence is a product requirement.** A clean WordPress installation plus the built theme must provide the baseline SEO/GEO experience with zero required plugins.
5. **Multilingual support is a core contract.** ES/EN behavior must be designed into URLs, metadata, Schema and visible content rather than bolted on later.
6. **Accessible by default.** Reusable patterns must work with keyboard navigation, focus indication, semantic landmarks and reduced motion.
7. **WordPress-native first.** Use WordPress core APIs for robots, sitemaps, block templates, responsive images and other solved problems before adding custom infrastructure.
8. **Optional ecosystem compatibility never becomes a dependency.** Yoast, Rank Math, AIOSEO, WPML, Polylang, WooCommerce or builders may be supported later, but the core product cannot require them.
9. **Minimal sufficient validation.** Validate the changed contract deeply; do not waste CI on unrelated work.

## Primary users

- Agencies that need a repeatable base for client WordPress sites.
- Developers who need a clean starting point rather than a heavy multipurpose theme.
- Businesses that require SEO, multilingual publishing and strong performance from launch.

## Initial presets

### Corporate
Home, services, about, case studies, blog and contact.

### Local Business
Home, services, locations/service areas, local landing pages, FAQ and contact, with LocalBusiness/Organization configuration.

### Ecommerce
WooCommerce-compatible layouts, Product/Organization metadata coordination, category/product editorial support and optional commerce integration points.

### Publisher
Article, author/ProfilePage, topic/category, archive and editorial provenance patterns.

## Non-goals for the foundation

- Replacing WordPress core features that already solve the problem well.
- Shipping a mandatory visual page builder.
- Requiring an SEO, Schema, caching, multilingual or GEO plugin for the baseline product to function.
- Claiming that `llms.txt`, special AI markup or any proprietary file guarantees AI citations or rankings.
- Adding tracking, fonts, consent tools, analytics or third-party scripts by default.
- Reimplementing an external plugin inside the theme merely to copy its UI or proprietary workflow.

## Success criteria

A new project should be able to install the built theme on clean WordPress and reach a production-ready baseline with:

- **one installable self-contained block theme**;
- **zero required plugins** for baseline SEO/GEO behavior;
- native canonical/meta/robots/indexability behavior;
- correct ES/EN capability from day one;
- measurable performance budgets;
- structured SEO/GEO validation;
- accessible core templates and patterns;
- a documented path to enable a preset rather than rebuilding common structures;
- optional integrations that enhance the product without becoming prerequisites.
