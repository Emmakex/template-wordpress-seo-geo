# Corporate v5 A3.1 — visual closure plan

## Purpose

This document is the canonical execution plan for the final visual closure of the first Corporate master surface after the real `/nuevaweb/` review of Corporate v5 A3.

A3 already proves the strategic Theme-owned frontend, the lighter visual direction and the deterministic technical candidate. A3.1 is intentionally **small and bounded**: it closes the remaining visual/product gaps before Corporate can receive explicit premium/WOW approval and before A4 renderer generalization begins.

The project rule remains **finish before advancing**.

## Authority and safety boundary

- Real sandbox: `https://emmake.com/nuevaweb/`.
- Production: untouched.
- Corporate v5 A3 Theme identity remains the current technical baseline until implementation changes intentionally create a new frozen candidate.
- Do **not** rerun Reset, clone, rescue, hydration or Migration Bridge merely to execute A3.1.
- Do **not** modify URLs, rescued content, SEO/GEO authority, canonical/hreflang/sitemap behavior or the accepted semantic content model as part of visual closure.
- Do **not** start A4 renderer generalization or another preset master surface until A3.1 passes the real-site field gate.

## Real-site A3 review — 2026-10-07

The real desktop screenshot confirms that A3 is a substantial improvement over A2 and is now visually coherent enough for a focused closure pass.

Accepted direction:

- lighter teal/green palette is substantially better;
- header and footer now belong to the same visual system;
- capabilities/cards have useful visual separation;
- the light Method section avoids the previous dark-wall effect;
- Insights hierarchy and the final CTA are materially improved;
- the full Home reads as one Theme-owned system rather than inherited WordPress presentation.

Remaining field findings:

1. **Hero H1 is too large/tall.** The first viewport should expose the primary actions without requiring the user to scroll.
2. **The right side of the hero still needs a purposeful visual asset.** The current geometric placeholder proves the composition but not the final image language.
3. **The `01 / 02 / 03` Method cards are too flat.** They need a controlled A+B treatment: clean premium cards plus a visible sense of sequence/process.
4. **The image/visual system is not yet fully materialized.** Every intentional image slot must be defined now, then filled with production-ready local assets.

These four points define A3.1. No unrelated redesign belongs in this pass.

---

# A3.1 execution microphases

## MF-01 — Hero viewport closure

### Goal

Make the desktop hero communicate the proposition and expose both CTAs inside the first practical viewport while keeping the current premium hierarchy.

### Changes

- reduce desktop H1 font size;
- reduce H1 line-height slightly;
- constrain/retune the copy column so the line breaks remain deliberate;
- review vertical spacing between eyebrow, H1, supporting copy and CTAs;
- preserve clear separation between the copy column and the visual column;
- verify that the two CTAs are visible without scroll in the normal desktop acceptance viewport;
- retain responsive fluid sizing rather than introducing hard-coded desktop-only breakage.

### Acceptance

- headline remains dominant but no longer overwhelms the hero;
- both primary actions are visible in the first desktop screen;
- no orphaned or pathological title wrapping at `1440 / 1024 / 768 / 390`;
- the hero still has a strong visual anchor on the right;
- no accessibility, SEO/GEO or LCP regression.

Status: **next implementation microphase**.

---

## MF-02 — Final visual-slot map

### Goal

Define exactly where raster images, illustrations, decorative vectors and icons belong so that assets are created once and the Theme does not accumulate arbitrary media.

### Final section policy

- **Hero:** one major conceptual visual, plus one alternate candidate for evaluation.
- **Capabilities:** one graphic background/illustration for the featured Marketing card; two small reusable visual marks for the secondary service cards.
- **Method (`01 / 02 / 03`):** icons + process connector + numbered editorial treatment; no photography.
- **Insights:** one visual treatment for the featured article and a reusable image policy for normal blog posts.
- **Final CTA:** one lightweight abstract decorative asset if CSS/vector composition alone is insufficient.
- **Footer:** no large image; only an optional micro brand detail if it adds value.

### Acceptance

Every image slot has a declared purpose, rendering behavior, responsive rule and fallback before production assets are generated.

Status: **planned immediately after MF-01**.

---

## MF-03 — Method section A+B redesign

### Goal

Turn `Cómo trabajamos` from three flat cards into a premium but restrained process system.

### A+B design rule

**A — premium clarity**

- light cards;
- strong typographic hierarchy;
- subtle border and/or shadow;
- generous spacing;
- high readability;
- clear step number.

**B — modern process language**

- one simple icon per step;
- visible relationship between steps;
- controlled connector/flow line on wide layouts;
- small graphic accents rather than heavy decoration;
- modest hover/focus enhancement where appropriate.

### Step semantics

- `01 — Entender`: diagnosis, listening, analysis, understanding.
- `02 — Definir`: strategy, prioritization, roadmap, decision.
- `03 — Medir y mejorar`: metrics, iteration, optimization, growth.

### Responsive rule

- desktop: horizontal process relationship may be visible;
- tablet: connector may simplify;
- mobile: cards stack and the sequence remains obvious without relying on a horizontal line;
- the process must still make sense with CSS disabled or decorative vectors unavailable.

### Acceptance

- no longer reads as three identical generic boxes;
- each step has semantic individuality;
- sequence is understandable instantly;
- no decorative element is required to understand the content;
- keyboard/focus and reduced-motion behavior remain correct.

Status: **planned immediately after MF-02**.

---

## MF-04 — Canonical asset inventory

The following IDs are the canonical asset backlog for A3.1. Do not create duplicate alternatives outside this list without updating this document.

| ID | Section | Asset | Type | Priority | Required for Corporate approval |
| --- | --- | --- | --- | --- | --- |
| `A3IMG-01` | Hero | Primary hero visual | conceptual illustration/composition | P0 | yes |
| `A3IMG-02` | Hero | Alternate hero visual | conceptual illustration/composition | P1 | evaluation asset |
| `A3IMG-03` | Capabilities | Marketing Digital featured-card visual | abstract background/illustration | P0 | yes |
| `A3IMG-04` | Capabilities | Investigación de mercado mark | icon/micro-illustration | P0 | yes |
| `A3IMG-05` | Capabilities | IA y automatización mark | icon/micro-illustration | P0 | yes |
| `A3IMG-06` | Method | Entender icon | vector icon | P0 | yes |
| `A3IMG-07` | Method | Definir icon | vector icon | P0 | yes |
| `A3IMG-08` | Method | Medir y mejorar icon | vector icon | P0 | yes |
| `A3IMG-09` | Method | Process connector | CSS/SVG decorative connector | P0 | yes |
| `A3IMG-10` | Method | `01 / 02 / 03` number treatment | CSS/SVG editorial system | P0 | yes |
| `A3IMG-11` | Insights | Featured-article visual | editorial image/illustration | P0 | yes |
| `A3IMG-12` | Insights/blog | Marketing digital base article visual | editorial image | P1 | before blog system closure |
| `A3IMG-13` | Insights/blog | IA base article visual | editorial image | P1 | before blog system closure |
| `A3IMG-14` | Insights/blog | Automatización/datos base article visual | editorial image | P1 | before blog system closure |
| `A3IMG-15` | Insights/blog | Strategy/content base article visual | editorial image | P2 | optional initial expansion |
| `A3IMG-16` | Insights/blog | Analytics/research base article visual | editorial image | P2 | optional initial expansion |
| `A3IMG-17` | Final CTA | CTA abstract support graphic | lightweight SVG/illustration | P1 | only if final CSS composition needs it |
| `A3IMG-18` | Footer | Brand micro-detail | lightweight SVG/pattern | P2 | no |

### Minimum asset set to approve the Home

The Home may not be called visually complete until at least the following are integrated or deliberately rejected as unnecessary after field review:

`A3IMG-01`, `A3IMG-03`, `A3IMG-04`, `A3IMG-05`, `A3IMG-06`, `A3IMG-07`, `A3IMG-08`, `A3IMG-09`, `A3IMG-10`, `A3IMG-11`.

---

## MF-05 — Asset production strategy

### Generate/design as visual compositions

- `A3IMG-01` primary hero;
- `A3IMG-02` hero alternate;
- `A3IMG-03` featured service graphic;
- `A3IMG-11` featured article visual;
- `A3IMG-12` to `A3IMG-16` reusable editorial/blog visuals;
- `A3IMG-17` CTA support only when necessary.

### Build as reusable local design-system assets

- `A3IMG-04`, `A3IMG-05` service marks;
- `A3IMG-06`, `A3IMG-07`, `A3IMG-08` Method icons;
- `A3IMG-09` connector;
- `A3IMG-10` number treatment;
- `A3IMG-18` optional footer detail.

Reusable icons should prefer local SVG/CSS rather than raster imagery.

---

## MF-06 — Theme integration contract for media

### Goal

Prepare stable slots so visual assets can be replaced without changing semantic page architecture.

### Required integration points

1. hero visual slot;
2. featured capability/card visual slot;
3. secondary capability icon slots;
4. Method icon slots and decorative process connector;
5. featured Insights media slot;
6. final CTA decorative slot if used.

### Media rules

- public HTML remains server-rendered;
- critical copy, headings and calls to action never live inside images;
- image failure must not make content incomprehensible;
- meaningful images require semantic `alt` text derived from their actual communicative purpose;
- decorative assets use empty alt / appropriate presentational handling;
- intrinsic `width` and `height` or equivalent aspect-ratio reservation must prevent layout shift;
- only the actual LCP hero image may receive high fetch priority when measurement proves it is the LCP element;
- below-the-fold raster media uses lazy loading;
- local assets only for the baseline: no required third-party visual requests;
- responsive `srcset`/`sizes` must be used for raster media where appropriate;
- prefer AVIF/WebP with an accepted fallback path when the WordPress media pipeline supports it;
- SVG icons must be optimized, local and free of embedded scripts/external resources;
- no raster asset should be used when CSS/SVG communicates the same decorative function more efficiently.

---

## MF-07 — Asset production batches

### Batch 1 — visual blockers

- `A3IMG-01` primary hero visual;
- `A3IMG-02` alternate hero candidate;
- `A3IMG-06` / `07` / `08` Method icons;
- `A3IMG-09` process connector;
- `A3IMG-10` number treatment.

### Batch 2 — service + editorial closure

- `A3IMG-03` featured Marketing card graphic;
- `A3IMG-04` Investigación mark;
- `A3IMG-05` IA/automatización mark;
- `A3IMG-11` featured Insights visual.

### Batch 3 — reusable content library

- `A3IMG-12` to `A3IMG-16` blog/editorial base images;
- `A3IMG-17` CTA support if still needed;
- `A3IMG-18` optional footer detail.

No Batch 3 work blocks Home acceptance unless field QA identifies one of those assets as necessary.

---

## MF-08 — Real-site QA and Corporate close

### Viewports

Field QA must review, in order:

1. `1440` desktop;
2. `1024` compact desktop/tablet landscape;
3. `768` tablet;
4. `390` mobile.

The automated `320` stress check remains part of repository CI.

### Focus areas

- hero height and first-screen CTA visibility;
- H1 wrapping and readable line length;
- hero visual balance and LCP behavior;
- service visual hierarchy;
- Method A+B sequence clarity;
- featured Insights image and card balance;
- final CTA hierarchy;
- footer density and navigation;
- no overflow, CLS or weak recomposition;
- no SEO/GEO or accessibility regression.

### Corporate Definition of Done

Corporate v5 A3.1 is complete only when all of the following are true:

- Hero CTAs are visible in the accepted first desktop viewport.
- Hero visual is intentional, production-ready and responsive.
- `01 / 02 / 03` Method section no longer appears flat and retains semantic clarity on mobile.
- Required P0 assets are integrated and optimized.
- Important media has correct semantic/fallback behavior.
- Accessibility/responsive/performance/WordPress smoke gates remain green.
- Home passes human premium/WOW acceptance on `/nuevaweb/`.
- Existing URLs, content, links and SEO/GEO output remain correct.
- No Reset/rehydration/migration rerun was required.
- Exact final Theme candidate is frozen after the last implementation byte change.

Only then may the roadmap move to **A4 — Generalize renderer contract**.

---

# Visual language for generated/created assets

All new Corporate visuals should look like parts of the same product, not stock imagery added section-by-section.

## Direction

- contemporary B2B digital/technology;
- premium but not luxury-fashion;
- teal / mint / green / off-white family aligned with the Theme;
- abstract systems, data, strategy, connection, intelligence and transformation;
- geometric depth and controlled gradients are acceptable;
- avoid cliché robots, glowing brains, generic handshake photography and visual noise;
- avoid tiny decorative details that disappear after responsive optimization;
- never embed important text in the artwork.

## Hero image direction

The hero visual should imply the intersection of:

- marketing/digital growth;
- intelligence/automation;
- research/data;
- business transformation.

It should work as a strong object/composition on the right side without competing with the H1.

## Editorial image direction

Blog/Insights imagery should use a coherent reusable visual grammar so automated or future Manager-created content does not turn the site into an inconsistent stock-photo collection.

---

# SEO/GEO image policy

A3.1 must strengthen, not weaken, the SEO/GEO-first product promise.

- Use meaningful file names where WordPress asset workflow permits it.
- Keep important topical meaning in HTML text, not only in pixels.
- Use descriptive alt text only when the image communicates content; decorative images should not create keyword-stuffed alt noise.
- Preserve image dimensions and responsive sources for Core Web Vitals.
- Avoid generating separate near-duplicate city/service graphics that could encourage thin doorway-style content patterns.
- Blog visual templates may be reusable, but each article's visible copy and semantic content remains unique.
- Image optimization must not introduce a mandatory external CDN or JavaScript runtime.

---

# Stop/go rule

Current execution sequence is:

**MF-01 hero → MF-02 visual slots → MF-03 Method A+B → MF-04/05 asset lock → MF-06 media slots → MF-07 asset batches → MF-08 field QA → Corporate approval → freeze exact final candidate → A4 renderer generalization.**

Do not start SaaS, Local Pro, Publisher, Ecommerce master-surface rollout or Manager implementation while this closure sequence is open.
