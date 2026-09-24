# Product Vision

## Purpose

Create a reusable WordPress SEO/GEO portfolio that lets a team build, migrate and operate high-quality client websites without trading away performance, technical SEO, multilingual correctness, accessibility or future compatibility with AI-assisted discovery.

The portfolio has two independently sellable products:

1. **SEO/GEO Theme** — the self-contained block theme that supplies the baseline presentation and native SEO/GEO runtime with zero required SEO/GEO plugins.
2. **SEO/GEO Manager** — the permanent plugin product for analysis, controlled landing/blog publication, migration and ongoing operations on both Theme and non-Theme WordPress sites.

Neither product may become a mandatory runtime dependency of the other. The Theme is not a page-builder bundle or a wrapper around third-party SEO plugins. Manager is not the deprecated Core wrapper and is not limited to migration.

## Product principles

1. **Performance is architecture, not a cleanup task.** Avoid unnecessary runtime dependencies, third-party assets and global CSS/JS.
2. **SEO fundamentals come before GEO extras.** Crawlability, indexability, canonicalization, internal linking, meaningful content and structured data must be correct first.
3. **GEO means making content understandable and retrievable, not gaming AI systems.** We optimize semantic structure, provenance, entities and machine-friendly access while avoiding unsupported ranking claims.
4. **Theme plugin independence is a product requirement.** A clean WordPress installation plus the built Theme must provide the baseline SEO/GEO experience with zero required plugins.
5. **Multilingual support is a core contract.** ES/EN behavior must be designed into URLs, metadata, Schema and visible content rather than bolted on later.
6. **Accessible by default.** Reusable patterns must work with keyboard navigation, focus indication, semantic landmarks and reduced motion.
7. **WordPress-native first.** Use WordPress core APIs for robots, sitemaps, block templates, responsive images and other solved problems before adding custom infrastructure.
8. **Optional ecosystem compatibility never becomes a dependency.** Yoast, Rank Math, AIOSEO, WPML, Polylang, WooCommerce or builders may be supported later, but the core product cannot require them.
9. **Minimal sufficient validation.** Validate the changed contract deeply; do not waste CI on unrelated work.
10. **Manager is independently deployable.** SEO/GEO Manager must support accepted WordPress sites without requiring the SEO/GEO Theme or GitHub.
11. **One output authority per signal.** Theme, Manager and external SEO providers must never emit competing canonical, robots, hreflang, Schema, sitemap or social metadata.
12. **Publishing is reversible.** Landing/blog publication is draft-first, idempotent, auditable and rollback-capable.
13. **Staging is a workflow capability.** Clients without staging must have a portable-sandbox path rather than being forced to test migrations in production.

## Primary users

- Agencies that need a repeatable base for client WordPress sites.
- Developers who need a clean starting point rather than a heavy multipurpose theme.
- Businesses that require SEO, multilingual publishing and strong performance from launch.
- Agencies that need controlled landing/blog publication on existing client WordPress sites without GitHub.
- Existing WordPress owners who want SEO/GEO operations without replacing their current theme.

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
- optional integrations that enhance the Theme without becoming prerequisites;
- a separate Manager product that can analyze and publish to supported non-Theme WordPress sites;
- Theme + Manager integration with exactly one authority per SEO/GEO signal;
- draft-first idempotent landing/blog publication with rollback;
- a portable-sandbox migration path for clients that do not already have staging.
