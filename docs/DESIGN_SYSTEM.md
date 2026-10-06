# Design System SEO/GEO 1.0

## Product decision

The Theme is no longer treated as a visual reconstruction of a legacy WordPress site. The legacy site is a **content and SEO source**, not a design source.

The product flow is now:

1. rescue useful content, URLs, SEO metadata, links, media and identity;
2. remove legacy runtime/presentation baggage;
3. apply a modern Theme-owned visual system;
4. map rescued content into semantic components;
5. fill genuine content gaps with clearly marked draft placeholders when needed;
6. let the content/optimization layer improve and expand copy later;
7. run SEO/GEO, accessibility, performance and publishing readiness gates.

This decision exists to avoid client-by-client design patching. We build a reusable product once, then feed it different client content.

## Core principles

1. **Design from proven patterns, not from the legacy site.** We study current high-quality corporate, SaaS, local-business, creative and commerce sites, extract recurring interaction/layout patterns, and implement our own system. We do not copy a third-party site pixel-for-pixel.
2. **Semantic HTML first.** Visual sophistication must sit on top of predictable headings, sections, articles, links, lists and landmarks.
3. **SEO/GEO is part of the component contract.** Components are designed so visible copy, internal links, entity context and structured data can agree with each other.
4. **Fast by construction.** System fonts by default, no remote design dependency, no page builder, zero JavaScript required for the static presentation layer, bounded CSS, responsive images and progressive enhancement only when useful.
5. **One engine, five product-facing presets.** Presets share tokens, primitives and semantic component contracts; they differ mainly in composition, art direction and density.
6. **Content and presentation are decoupled.** A weak or incomplete legacy site must not force a weak new layout.
7. **Evidence remains evidence-gated.** Metrics, client logos, testimonials, awards and case-study outcomes are never invented to make a design look complete.
8. **Draft placeholders are allowed; fake proof is not.** When a non-evidence content slot is missing, sandbox/draft output may use a semantic placeholder. Placeholders must never silently become publishable claims.

## Product-facing presets

The long-term product surface contains five presets:

| Product name | Runtime ID / compatibility | Primary use |
| --- | --- | --- |
| Corporate Premium | `corporate` | consultancies, agencies, engineering, professional B2B |
| Tech / SaaS | `saas-digital-product` | software, AI, platforms, startups |
| Local Pro | `local-business` | clinics, installers, legal, accounting, local services |
| Creative / Studio | `creative-studio` | architecture, design, photography, events, portfolios |
| Commerce / Product | `ecommerce` | brands, products, catalogues and ecommerce |

The existing `publisher` preset remains a compatibility surface during the transition. It is not one of the five product-facing 1.0 presets. Removal/aliasing must happen only after its current contracts and migrations have a safe replacement.

## Modern layout language

The shared design language is intentionally opinionated:

- large, fluid editorial typography;
- generous whitespace and strong section rhythm;
- asymmetric grids where hierarchy benefits from them;
- Bento/mosaic capability and feature layouts rather than repetitive equal cards;
- one dominant action per viewport/section, with secondary actions visibly subordinate;
- editorial content/Insights layouts with one featured item plus supporting items;
- process/method sections that read as a sequence or timeline, not three identical cards;
- high-contrast closing CTA sections;
- restrained motion and hover states that never carry essential meaning;
- responsive collapse that preserves hierarchy rather than merely stacking every desktop box.

## Design-token architecture

`theme.json` remains the authoritative WordPress source for the neutral semantic palette, typography scale, spacing scale and editor controls.

A small Theme-owned CSS design-system layer may expose runtime aliases and layout primitives that WordPress global styles cannot express cleanly. Presets consume those aliases rather than defining an entirely separate geometry system.

### Neutral semantic tokens

| Slug | Value | Intended use |
| --- | --- | --- |
| `base` | `#FFFFFF` | primary page background |
| `contrast` | `#111827` | main text/headings |
| `surface` | `#F8FAFC` | secondary sections/cards |
| `muted` | `#475569` | secondary text/captions |
| `border` | `#CBD5E1` | dividers and neutral borders |
| `accent` | `#1D4ED8` | links and primary actions |
| `accent-strong` | `#1E40AF` | interactive accent state |
| `accent-contrast` | `#FFFFFF` | text on accent surfaces |

Automated minimum contrast contract remains:

- `contrast` on `base`: >= 7:1;
- `muted` on `base`: >= 4.5:1;
- `accent` on `base`: >= 4.5:1;
- `accent-contrast` on `accent`: >= 4.5:1;
- `contrast` on `surface`: >= 7:1;
- `accent-contrast` on `accent-strong`: >= 4.5:1.

A branded preset may change values only if the same semantic pair contract remains valid.

## Typography

### Families

`system-sans` remains the default stack:

```text
-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif
```

`system-serif` remains available for editorial contrast:

```text
ui-serif, Georgia, Cambria, "Times New Roman", Times, serif
```

No remote font request is required by the product. A client may deliberately add licensed local WOFF2 faces later, but preset quality must not depend on them.

### Display strategy

The WordPress token scale remains the editor-safe baseline. Preset CSS may use bounded `clamp()` display scales for high-impact H1/H2 treatment, provided it preserves readable line length and mobile behavior.

Large type is a layout device, not permission for verbose copy. Hero H1 and lead text must remain short enough to scan before scrolling.

## Spacing and geometry

The WordPress spacing scale remains:

```text
2xs  0.25rem
xs   0.5rem
sm   0.75rem
md   1rem
lg   1.5rem
xl   2rem
2xl  3rem
3xl  4.5rem
```

The design-system runtime layer adds fluid section/shell aliases for cases where fixed editor steps are not sufficient. Arbitrary page-specific spacing remains prohibited.

## Component contracts

Visual components are not free-form page fragments. Each one owns a semantic content contract.

### Hero

Required concepts:

- category/positioning eyebrow (optional);
- one H1 owned by the document/page;
- one concise lead;
- primary CTA;
- optional secondary CTA;
- optional verified proof only when evidence exists.

### Capability / service item

Conceptual contract:

```text
service.name
service.description
service.url
service.image? / service.icon?
service.serviceType?
service.areaServed?
```

The visual representation can be a Bento tile, list row or featured panel. The semantic representation must still be understandable as a service/capability with a real link when a destination exists.

### Process / method

Each step requires:

```text
process.position
process.name
process.description
```

Steps describe the organisation's actual working method. Unrelated legacy educational/article copy must not be selected merely because it contains process-like vocabulary.

### Proof / cases / testimonials

These are evidence groups. Empty design space is preferable to invented claims.

### Insights

The preferred pattern is editorial rather than three identical cards:

- first item featured;
- remaining items supporting;
- titles stay readable without forced narrow columns;
- print/PDF output avoids orphaned section headings where practical.

### Final CTA

The final CTA is a high-contrast conversion section with one obvious primary action. It must remain visually separate from the footer.

## Content states

Every semantic slot conceptually belongs to one of these states:

- `rescued`: derived from the legacy source;
- `authored`: deliberately written/edited for the new site;
- `generated-draft`: generated by the content layer and awaiting review;
- `placeholder`: design-only fallback in a non-public draft/sandbox;
- `verified-evidence`: factual proof explicitly approved for publication.

The Theme must not infer that a placeholder or generated draft is verified evidence.

### Placeholder policy

Lorem Ipsum is permitted only as an internal design fallback, but **semantic placeholders are preferred** because they test realistic line length and information density.

Examples:

- good: `Describe the primary service outcome in one concise sentence.`
- acceptable for pure visual prototyping: `Lorem ipsum...`
- prohibited: invented metrics, client names, awards, testimonials or factual outcomes.

Placeholder content must be easy for readiness tooling to detect and must block final publication readiness until replaced or explicitly removed.

## SEO/GEO component rule

The visible page is the source of truth. Structured data must describe visible, supportable content rather than add invisible marketing claims.

The component architecture should make it straightforward for the SEO/GEO layer to emit or derive appropriate metadata for concepts such as organisation, service, article, FAQ, person, place and product without coupling the Theme to one SEO plugin.

The Migration Bridge therefore preserves provider signals only as input; the Theme/Core own the provider-neutral output contract.

## Accessibility contract

Modern presentation does not weaken accessibility requirements:

- keyboard-visible focus;
- semantic landmarks and heading order;
- sufficient color contrast;
- touch targets suitable for mobile use;
- no hover-only information;
- `prefers-reduced-motion` respected;
- content remains usable without animation;
- responsive layouts preserve logical reading order.

Existing Accessibility & Responsive CI remains authoritative.

## Performance contract

The 1.0 design system adds no required project JavaScript and no remote design dependency.

Performance acceptance is governed by the repository's existing Performance Baseline CI and real-browser field checks. In addition:

- visual effects should prefer CSS over JavaScript;
- decorative assets must not block the main content;
- raster images use responsive dimensions and modern formats where the delivery stack supports them;
- below-the-fold media is lazy-loaded;
- layout must reserve image/media space to avoid avoidable CLS;
- preset CSS must remain bounded and reusable rather than accumulating client-specific patches.

## Migration contract

The legacy site contributes:

- authored content;
- URL identity;
- useful metadata;
- internal/external links;
- media references;
- organisation identity;
- verifiable evidence.

It does **not** contribute:

- builder layout;
- theme styling;
- obsolete widgets;
- plugin presentation runtime;
- arbitrary legacy spacing/color decisions;
- visual debt.

## Corporate Premium 1.0 composition

Corporate Premium is the first reference implementation and the EMMAKE pilot target.

Default Home composition:

1. editorial Hero;
2. asymmetric/Bento capabilities;
3. optional high-contrast proof/value band (only when evidence exists);
4. method/process as a visual sequence;
5. optional featured case/work block;
6. editorial Insights (1 featured + supporting items);
7. high-contrast final CTA;
8. minimal footer.

This structure is a default composition, not a requirement to display empty sections. Missing evidence sections collapse cleanly.

## Engineering rule

We no longer approve a preset by repeatedly patching one customer's page until it looks acceptable. A visual defect found during a field pilot must be fixed at the **token, primitive, component or preset** level whenever the issue can recur across customers.

EMMAKE is the reference acceptance site, not the source of the visual design.
