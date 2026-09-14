# Template WordPress SEO + GEO

Reusable WordPress foundation for fast, technically clean, multilingual websites designed for classic search engines and modern AI-assisted discovery.

## ES

Este repositorio define una base reutilizable para lanzar sitios WordPress con cinco pilares obligatorios:

1. **Performance first**: Core Web Vitals, carga mínima de CSS/JS, imágenes optimizadas y HTML ligero.
2. **SEO técnico sólido**: indexabilidad, canonical, robots, sitemaps, metadatos, enlazado y Schema coherente.
3. **GEO (Generative Engine Optimization)**: contenido semántico, entidades, autores, fuentes, acceso de crawlers y formatos auxiliares para agentes, sin sustituir los fundamentos SEO.
4. **Multidioma desde el núcleo**: ES/EN como idiomas de primera clase, arquitectura compatible con WPML y Polylang, hreflang, canonical y Schema localizados.
5. **Accesibilidad**: HTML semántico, teclado, foco, contraste, movimiento reducido y patrones compatibles con WCAG.

La solución se divide en dos paquetes para evitar acoplar funcionalidad al diseño:

- `packages/seo-geo-theme`: block theme ligero; presentación, design tokens, templates, parts y patterns.
- `packages/seo-geo-core`: plugin funcional; SEO, Schema, GEO, idiomas, sitemaps, crawlers, markdown, rendimiento e integraciones.

Presets previstos: `corporate`, `local-business`, `ecommerce`, `publisher`.

## EN

This repository defines a reusable WordPress foundation built around five mandatory pillars:

1. **Performance first**: Core Web Vitals, minimal CSS/JS, optimized images and lean HTML.
2. **Strong technical SEO**: indexability, canonicals, robots, sitemaps, metadata, internal linking and coherent Schema.
3. **GEO (Generative Engine Optimization)**: semantic content, entities, authors, sources, crawler access and optional agent-friendly formats without replacing SEO fundamentals.
4. **Multilingual by design**: ES/EN are first-class languages, with WPML/Polylang adapters, localized hreflang, canonicals and Schema.
5. **Accessibility**: semantic HTML, keyboard support, focus states, contrast, reduced motion and WCAG-aware patterns.

The solution is split to avoid coupling functionality to presentation:

- `packages/seo-geo-theme`: lightweight block theme for presentation, design tokens, templates, parts and patterns.
- `packages/seo-geo-core`: functional plugin for SEO, Schema, GEO, language handling, sitemaps, crawlers, Markdown, performance and integrations.

Planned presets: `corporate`, `local-business`, `ecommerce`, `publisher`.

## Engineering workflow

After the unavoidable empty-repository bootstrap commit, all work follows:

`feature branch -> PR -> CI -> merge -> deployment/install verification`

No phase advances until its implementation, required gates, acceptance criteria, blockers and documentation are complete.

## Documentation

- `docs/PRODUCT_VISION.md`
- `docs/ARCHITECTURE.md`
- `docs/SEO_GEO_SPEC.md`
- `docs/MULTILINGUAL.md`
- `docs/PERFORMANCE.md`
- `docs/ACCESSIBILITY.md`
- `docs/PRESETS.md`
- `docs/CI_QUALITY_GATES.md`
- `docs/ROADMAP.md`
- `docs/engineering/GLOBAL_ENGINEERING_RULES.md`
- `docs/engineering/ERRORS_AND_SOLUTIONS.md`

## Authoritative references

The project tracks primary documentation rather than SEO folklore:

- WordPress Theme Handbook and `theme.json` reference.
- Google Search Central documentation for AI features, localized pages, structured data and Core Web Vitals.
- OpenAI publisher/developer crawler guidance.
- The `llms.txt` proposal as an optional interoperability mechanism, never as a claimed Google ranking requirement.
