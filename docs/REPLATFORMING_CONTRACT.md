# Replatforming Contract — Preserve the asset, replace the presentation

## Product intent

Existing client sites are **not** visually cloned into the SEO/GEO Theme.

The migration path preserves the valuable digital asset — content, URLs, SEO signals, internal/external links, media, entities, forms/business behavior that is still required, and other verified facts — then rebuilds the public presentation with the modern SEO/GEO Theme and the selected reusable preset.

The old theme, page-builder layout, visual composition, CSS, spacing, widgets, builder grids and decorative structure are **reference material only**. They are not parity targets.

## Preserve by default

- public URL/slugs and redirect intent;
- canonical/indexability intent;
- titles and meta descriptions when valid;
- headings/content meaning, while allowing semantic restructuring;
- body copy, FAQs, service/product facts, legal copy and editorial material worth keeping;
- internal and external links;
- media worth keeping, with responsive/native WordPress output;
- structured-data facts that are truthful and still applicable;
- multilingual relationships;
- forms/business workflows that are still required;
- analytics/conversion requirements as explicit integrations, never as visual baggage.

## Replace by default

- legacy theme and child-theme presentation;
- Elementor/Divi visual layout and builder CSS;
- legacy columns, rows, spacers, decorative wrappers and widget positioning;
- obsolete header/footer/navigation presentation;
- inherited animation and visual effects;
- old responsive hacks;
- visual page-builder dependencies;
- layout-specific shortcodes and presentation-only modules;
- arbitrary legacy CSS whose only purpose is to reproduce the old design.

## Rebuild target

The destination should behave like a modern product website: fast, semantic, component-based, responsive, accessible and visually cohesive.

The design philosophy is the same one used for the project's modern product-style sites such as **iaempleado.com** and **kairoseth.com**: clear information architecture, reusable components, strong hierarchy, fast delivery and centralized iteration. The WordPress implementation must achieve that outcome with native WordPress/Theme primitives rather than copying those sites or introducing a JavaScript-app dependency.

The Theme supplies the reusable design system and SEO/GEO runtime. Presets define the information architecture and visual language. Client content is remapped into those native structures rather than styled to resemble the old builder output.

### North-star rule

When a migration decision is ambiguous, prefer the option that:

1. preserves search equity and valuable factual content;
2. reduces legacy runtime/design dependencies;
3. moves the page toward reusable Theme/preset-native components;
4. improves semantic HTML, accessibility and performance;
5. makes future publishing/optimization easier through Manager/GitHub-driven product development.

Do **not** choose an option merely because it looks more similar to the old site.

## Migration Bridge role

Migration Bridge/Manager is responsible for:

1. clone/sandbox creation;
2. URL and SEO baseline capture;
3. content/link/media/business-dependency inventory;
4. preservation backup;
5. extraction/normalization of reusable content;
6. dependency removal planning;
7. handoff into Theme/preset-native structures;
8. SEO/GEO regression checks;
9. controlled cutover and rollback evidence.

It is **not** responsible for preserving builder-era visual parity.

## End-to-end client workflow

1. **Analyze** the existing WordPress site and capture its SEO/GEO/public-output baseline.
2. **Clone** it into an isolated sandbox with the product-owned portable clone path.
3. **Inventory** content, URLs, links, media, entities, redirects and required business integrations.
4. **Classify** each dependency as preserve, replace, migrate, optional/manual-review or remove candidate.
5. **Extract/normalize** reusable content out of legacy builder structures.
6. **Select** the appropriate reusable preset.
7. **Rebuild** the information architecture and presentation with Theme-native components.
8. **Remap** preserved content into the new native structures.
9. **Optimize** headings, internal linking, Schema, metadata, media and content semantics without changing factual meaning.
10. **Validate** SEO/GEO regression, accessibility, responsive behavior and performance.
11. **Cut over** only after acceptance evidence is green.
12. **Operate continuously** through Manager/GitHub-driven publishing and optimization: new landing pages, blog posts, internal-link improvements, Search Console/Bing learnings and iterative SEO/GEO enhancements.

This workflow is the commercial product path. Migration is the one-time bridge into a continuously improvable WordPress platform.

## Acceptance model

A replatform is accepted when:

- important URLs and redirects are preserved;
- SEO/GEO authority is correct and non-duplicated;
- valuable content is present and semantically improved;
- links and media are preserved or intentionally replaced;
- required business functions still work;
- the new preset passes performance/accessibility/responsive gates;
- the destination no longer depends on legacy visual-builder runtime for accepted migrated surfaces.

Visual comparison against the old site may be used to confirm that content was not accidentally lost, but visual similarity itself is not a success criterion.
