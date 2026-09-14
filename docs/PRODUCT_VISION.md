# Product Vision

## Purpose

Create a reusable WordPress starter kit that lets a team launch high-quality websites quickly without trading away performance, technical SEO, multilingual correctness, accessibility or future compatibility with AI-assisted discovery.

The product is not a page-builder bundle and not an SEO plugin clone. It is an opinionated foundation that supplies safe defaults, reusable patterns and automated quality gates.

## Product principles

1. **Performance is architecture, not a cleanup task.** Avoid unnecessary runtime dependencies, third-party assets and global CSS/JS.
2. **SEO fundamentals come before GEO extras.** Crawlability, indexability, canonicalization, internal linking, meaningful content and structured data must be correct first.
3. **GEO means making content understandable and retrievable, not gaming AI systems.** We optimize semantic structure, provenance, entities and machine-friendly access while avoiding unsupported ranking claims.
4. **Multilingual support is a core contract.** Language handling cannot be bolted on after templates, URLs, Schema or metadata are implemented.
5. **Presentation and functional SEO logic stay separate.** The theme can change without losing metadata, Schema, crawler controls or language logic.
6. **Accessible by default.** Reusable patterns must work with keyboard navigation, focus indication, semantic landmarks and reduced motion.
7. **Interoperable with the WordPress ecosystem.** Detect established SEO/multilingual/ecommerce plugins and avoid duplicate or conflicting output.
8. **Minimal sufficient validation.** Validate the changed contract deeply; do not waste CI on unrelated work.

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
WooCommerce-ready layouts, Product/Organization metadata coordination, category/product editorial support and multilingual commerce integration points.

### Publisher
Article, author/ProfilePage, topic/category, archive and editorial provenance patterns.

## Non-goals for the foundation

- Replacing WordPress core features that already solve the problem well.
- Shipping a mandatory visual page builder.
- Claiming that `llms.txt`, special AI markup or any proprietary file guarantees AI citations or rankings.
- Duplicating Yoast, Rank Math, AIOSEO, WPML or Polylang when they are deliberately selected for a project.
- Adding tracking, fonts, consent tools, analytics or third-party scripts by default.

## Success criteria

A new project should be able to start from this repository and reach a production-ready baseline with:

- a working block theme;
- a functional SEO/GEO core plugin;
- correct ES/EN capability from day one;
- measurable performance budgets;
- structured SEO/GEO validation;
- accessible core templates and patterns;
- a documented path to enable a preset rather than rebuilding common structures.
