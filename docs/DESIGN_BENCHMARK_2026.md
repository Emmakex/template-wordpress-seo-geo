# Design benchmark 2026

## Why this exists

The Theme should not invent visual patterns in isolation and should not copy one fashionable website. We maintain a lightweight benchmark of current public design systems and templates to identify **recurring patterns that already work** across categories.

The benchmark is directional. It does not grant permission to copy proprietary layouts, assets, source code, copywriting or brand identity.

## Sources reviewed

Current references reviewed during the Design System SEO/GEO 1.0 decision include:

- Framer: *12 Tech and SaaS Website Examples (2026)* — https://www.framer.com/blog/tech-website-design-examples/
- Framer Marketplace: Bento Grid component — https://www.framer.com/marketplace/components/bento-grid/
- Framer Marketplace: Studio Lumina editorial agency/portfolio — https://www.framer.com/marketplace/templates/studio-lumina/
- Framer Marketplace: FRAME® TEAM creative Bento portfolio — https://www.framer.com/marketplace/templates/frame-team/
- Framer Marketplace: Mika Kujo editorial portfolio — https://www.framer.com/marketplace/templates/mika-kujo/
- Webflow Made in Webflow: Bento Grid showcase — https://webflow.com/made-in-webflow/bento%20grid
- Webflow marketplace examples for modern SaaS/Bento systems — https://webflow.com/templates/

These sources are used to identify recurring design conventions, not to reproduce any individual work.

## Repeated patterns worth adopting

### 1. Explain the offer immediately

High-quality SaaS/B2B examples consistently reduce the time needed to understand the product or service. The Hero should answer, in a few seconds:

- what this is;
- who it is for;
- what outcome it creates;
- what the next action is.

Implication for our Theme: large typography alone is not enough. H1, lead and CTA must form one concise information unit.

### 2. One clear next step per stage

The strongest product sites repeatedly expose a single primary next step, while secondary actions are visually subordinate.

Implication: avoid multiple equally loud buttons. Primary CTA uses the preset's strongest action treatment; secondary CTA uses outline/text treatment.

### 3. Bento / asymmetric grids create hierarchy

Bento grids are common in 2026 SaaS, AI, agency and portfolio work. Their useful property is not the rounded box aesthetic itself; it is **unequal information weight**.

Implication: capabilities/features should not default to three identical columns. The most important capability may span more columns/rows or receive a different surface treatment. Mobile collapse must preserve order and meaning.

### 4. Editorial typography replaces decorative complexity

Modern agency/creative references often rely on strong type scale, whitespace, imagery and grid discipline rather than heavy effects.

Implication: we can achieve a premium result with system/local fonts, CSS Grid/Flex and almost no JavaScript.

### 5. Motion is restrained

Useful contemporary motion is usually subtle: reveal, hover, sticky context, small transforms, smooth state changes. The page must remain complete without it.

Implication: presentation remains CSS-first. JavaScript motion is optional progressive enhancement and must respect reduced-motion preferences.

### 6. Product/service proof is contextual

Modern B2B and SaaS designs show interfaces, real work, outcomes, metrics or client proof when they have it. They do not need a generic testimonial carousel on every page.

Implication: our proof/case components are optional evidence groups, not mandatory decorative sections.

### 7. Insights/content behaves like editorial material

Strong content sites increasingly distinguish a featured story from supporting stories instead of presenting three equal cards.

Implication: Corporate Premium uses a featured-first Insights layout and keeps long titles readable.

### 8. Global style controls matter

Current template systems repeatedly emphasize global color, type and component controls.

Implication: our differentiation must live in tokens + preset composition, not in one-off client CSS.

## What we explicitly do not copy

We do not copy:

- proprietary HTML/CSS/JS;
- exact section sequencing from one site;
- proprietary illustrations, 3D assets or photographs;
- third-party typography licences;
- brand palettes;
- marketing copy;
- pixel-identical interaction design.

We copy **principles and proven pattern categories**, then implement them independently using WordPress block markup and our own contracts.

## Patterns by preset

### Corporate Premium

Adopt:

- editorial Hero;
- asymmetric capability Bento;
- strong proof/value band;
- numbered/timeline method;
- featured case/work;
- editorial Insights;
- high-contrast CTA;
- minimal footer.

Avoid:

- generic equal-card grids everywhere;
- excessive gradients;
- startup-style decorative dashboards unless the business actually has a product UI.

### Tech / SaaS

Adopt:

- product-first Hero;
- interface/workflow visual;
- Bento features;
- problem/solution sequence;
- use cases;
- integrations or architecture when relevant;
- pricing only when applicable;
- FAQ and final CTA.

Avoid:

- abstract AI imagery without product context;
- animation that delays comprehension.

### Local Pro

Adopt:

- service + location clarity above the fold;
- trust/contact visibility;
- service areas;
- service grid;
- review/proof only when real;
- process;
- FAQ;
- map/contact details where appropriate.

Avoid:

- hiding locality in visual effects;
- long brand storytelling before service/location intent is answered.

### Creative / Studio

Adopt:

- full-bleed or editorial Hero;
- selected-work grid;
- large media;
- restrained copy;
- services as numbered/list composition;
- sticky or subtle editorial interactions;
- high-quality case-study templates.

Avoid:

- generic B2B card density;
- excessive motion that competes with work imagery.

### Commerce / Product

Adopt:

- product/category clarity;
- strong imagery;
- benefit/specification hierarchy;
- trust/logistics/payment information where relevant;
- editorial product storytelling;
- related products/content.

Avoid:

- visual experimentation that damages product discovery, price clarity or checkout intent.

## Acceptance rule

A new visual idea enters the core system only if it satisfies all of the following:

1. it improves comprehension, hierarchy, trust or conversion;
2. it can be implemented accessibly;
3. it does not materially damage performance;
4. it is reusable across more than one client or preset scenario;
5. it does not require copying protected third-party implementation/assets;
6. it can survive missing content gracefully.

If it only makes one pilot screenshot look nicer, it is not yet a design-system primitive.
