# Template WordPress SEO + GEO

This repository now defines a **two-product WordPress SEO/GEO portfolio** built for technically clean, multilingual websites designed for classic search engines and modern AI-assisted discovery.

**SEO/GEO Theme** remains the self-contained presentation product: its SEO/GEO foundation ships inside the theme package and requires zero SEO/GEO plugins for the documented baseline.

**SEO/GEO Manager** is the planned permanent, independently installable plugin for site analysis, controlled landing/blog publication, migration and ongoing operations. It must work without the Theme, and the Theme must work without Manager. Migration is a module of Manager, not the whole product.

## ES

Este repositorio define una base reutilizable para lanzar sitios WordPress con cinco pilares obligatorios:

1. **Performance first**: Core Web Vitals, carga mínima de CSS/JS, imágenes optimizadas y HTML ligero.
2. **SEO técnico nativo**: indexabilidad, canonical, robots, sitemaps, metadatos, Open Graph, breadcrumbs reutilizables, enlazado y Schema coherente sin depender de plugins SEO externos.
3. **GEO (Generative Engine Optimization)**: contenido semántico, entidades, autores, fuentes, acceso de crawlers y formatos auxiliares para agentes, sin sustituir los fundamentos SEO.
4. **Multidioma desde el núcleo**: ES/EN como idiomas de primera clase, con una base nativa que puede ampliarse mediante integraciones opcionales.
5. **Accesibilidad**: HTML semántico, teclado, foco, contraste, movimiento reducido y patrones compatibles con WCAG.

### Producto instalable

El objetivo de distribución es **un único theme instalable**:

- `packages/seo-geo-theme`: block theme ligero con presentación, design tokens, templates, parts, patterns y bootstrap del runtime SEO/GEO nativo.
- `packages/seo-geo-core/src`: fuente reutilizable de la lógica SEO/GEO que se **empaqueta dentro del theme** durante el build; no es un plugin obligatorio.
- `scripts/build-theme-package.sh`: construye el paquete autosuficiente y embebe el runtime en `inc/seo-geo-core/src`.

Presets implementados: `corporate`, `local-business`, `publisher`, `ecommerce` y `saas-digital-product`. Las fases de migración segura y onboarding están cerradas; la distribución está en su tramo final de documentación operativa para clientes.

## EN

This repository defines a reusable WordPress foundation built around five mandatory pillars:

1. **Performance first**: Core Web Vitals, minimal CSS/JS, optimized images and lean HTML.
2. **Native technical SEO**: indexability, canonicals, robots, sitemaps, metadata, Open Graph, reusable breadcrumbs, internal linking and coherent Schema without a required SEO plugin.
3. **GEO (Generative Engine Optimization)**: semantic content, entities, authors, sources, crawler access and optional agent-friendly formats without replacing SEO fundamentals.
4. **Multilingual by design**: ES/EN are first-class languages with a native baseline that can be extended through optional integrations.
5. **Accessibility**: semantic HTML, keyboard support, focus states, contrast, reduced motion and WCAG-aware patterns.

### Installable product

The distribution target is **one installable theme**:

- `packages/seo-geo-theme`: lightweight block theme containing presentation, design tokens, templates, parts, patterns and the native SEO/GEO runtime bootstrap.
- `packages/seo-geo-core/src`: reusable SEO/GEO source that is **bundled into the theme** during the build; it is not a required plugin.
- `scripts/build-theme-package.sh`: assembles the self-contained theme and embeds the runtime under `inc/seo-geo-core/src`.

Implemented presets: `corporate`, `local-business`, `publisher`, `ecommerce` and `saas-digital-product`. Safe migration and onboarding are complete; distribution is in its final client-operations documentation stage.

## Existing-site adoption

**Phase 8 — Existing-site adoption and safe migration is complete.** The existing Migration Bridge package covers read-only analysis, public SEO/GEO baseline capture, dependency classification, isolated sandbox migration, controlled Elementor/Divi conversion, strict parity, reversible production cutover, a privacy-bounded final handoff report and a capability-gated EN/ES operator screen. It remains outside the self-contained Theme and is not a Theme runtime dependency. Long-term, those accepted migration capabilities are absorbed into the permanent SEO/GEO Manager plugin as an optional migration module; Manager may remain installed for publishing after migration.

**Phase 9 — Theme onboarding and operator experience is complete.** **Phase 10C — Client installation and cloning documentation** and **Phase 10D — Production verification and recovery** are complete (with 10A–10B already closed). The active roadmap step is **Phase 10E — Stable release decision**. The current stable decision is **NO-GO** while the selected pilot `emmake.com` completes sandbox-to-production acceptance; the target remains `0.1.0` / `prestable`.

## Engineering workflow

After the unavoidable empty-repository bootstrap commit, all work follows:

`feature branch -> PR -> CI -> merge -> deployment/install verification`

No phase advances until its implementation, required gates, acceptance criteria, blockers and documentation are complete.

## Documentation

- `docs/PRODUCT_VISION.md`
- `docs/PRODUCT_PORTFOLIO.md`
- `docs/SEO_GEO_MANAGER.md`
- `docs/CONTENT_PUBLISHING.md`
- `docs/PORTABLE_SANDBOX.md`
- `docs/ARCHITECTURE.md`
- `docs/SEO_GEO_SPEC.md`
- `docs/NATIVE_SEO.md`
- `docs/NATIVE_SCHEMA.md`
- `docs/DISCOVERY_METADATA.md`
- `docs/GEO_CRAWLERS.md`
- `docs/LLMS_TXT.md`
- `docs/MARKDOWN_ALTERNATES.md`
- `docs/CONTENT_PROVENANCE.md`
- `docs/DISCOVERY_PRIVACY.md`
- `docs/CACHE_INVALIDATION.md`
- `docs/MULTILINGUAL.md`
- `docs/PERFORMANCE.md`
- `docs/ACCESSIBILITY.md`
- `docs/DESIGN_SYSTEM.md`
- `docs/PATTERNS.md`
- `docs/PRESETS.md`
- `docs/COMPATIBILITY.md`
- `docs/MIGRATION_BRIDGE.md`
- `docs/ONBOARDING.md`
- `docs/RELEASE_ARTIFACT.md`
- `docs/RELEASE_VERSIONING.md`
- `docs/CLIENT_INSTALLATION.md`
- `docs/CLIENT_CLONING.md`
- `docs/SANDBOX_TO_PRODUCTION.md`
- `docs/PRODUCTION_VERIFICATION.md`
- `docs/ROLLBACK_RECOVERY.md`
- `docs/STABLE_RELEASE_DECISION.md`
- `docs/REAL_SITE_PILOT.md`
- `docs/templates/PRODUCTION_ACCEPTANCE_RECORD.example.json`
- `docs/CI_QUALITY_GATES.md`
- `docs/ROADMAP.md`
- `docs/engineering/GLOBAL_ENGINEERING_RULES.md`
- `docs/engineering/ERRORS_AND_SOLUTIONS.md`

## Authoritative references

The project tracks primary documentation rather than SEO folklore:

- WordPress Theme Handbook and `theme.json` reference.
- Google Search Central documentation for AI features, localized pages, structured data and Core Web Vitals.
- OpenAI publisher/developer crawler guidance.
- The Open Graph protocol for social discovery metadata.
- The `llms.txt` proposal as an optional interoperability mechanism, never as a claimed Google ranking requirement.
