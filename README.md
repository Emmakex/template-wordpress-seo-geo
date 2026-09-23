# Template WordPress SEO + GEO

Reusable **self-contained WordPress block theme** for fast, technically clean, multilingual websites designed for classic search engines and modern AI-assisted discovery.

The installable product is intentionally **plugin-independent**: its SEO/GEO foundation ships inside the theme package. Third-party plugins may be added by a project later, but none are required for the baseline to work.

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

Presets implementados: `corporate`, `local-business`, `publisher`, `ecommerce` y `saas-digital-product`. La capa de presets está cerrada y el siguiente bloque del roadmap es la migración segura de sitios WordPress existentes.

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

Implemented presets: `corporate`, `local-business`, `publisher`, `ecommerce` and `saas-digital-product`. The preset layer is closed and the next roadmap block is safe adoption of existing WordPress sites.

## Existing-site adoption

Phase 8 is implementing a temporary **SEO/GEO Migration Bridge** for real client WordPress installations that already depend on themes, builders and plugins. **Phases 8A, 8B and 8C are complete**: the bridge can inventory the legacy environment, preserve a public SEO/GEO baseline and map content/builder/plugin/provider dependencies into a non-destructive migration graph. The next step is **8D — Sandbox Migration Lab**. The bridge remains outside the final self-contained theme.

## Engineering workflow

After the unavoidable empty-repository bootstrap commit, all work follows:

`feature branch -> PR -> CI -> merge -> deployment/install verification`

No phase advances until its implementation, required gates, acceptance criteria, blockers and documentation are complete.

## Documentation

- `docs/PRODUCT_VISION.md`
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
