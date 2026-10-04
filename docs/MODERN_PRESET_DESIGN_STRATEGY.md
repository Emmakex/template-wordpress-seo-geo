# Modern Functional Design Strategy — 5 Presets

Last trend review: **2026-10-04**

## Purpose

The SEO/GEO Theme must look and behave like a modern product website, not like a traditional WordPress theme and not like a preserved legacy builder site.

Modernity is not defined by copying a fashionable visual effect. A trend is adopted only when it improves at least one of:

- comprehension;
- task completion / conversion;
- information hierarchy;
- trust;
- responsive reuse;
- accessibility;
- perceived quality;
- maintainability;

without weakening SEO/GEO, semantic HTML, Core Web Vitals or the zero-JavaScript-by-default runtime contract.

## Trend adoption filter

Every proposed design trend must pass this filter before it enters a preset:

1. **Functional value** — does it help the visitor understand, decide or act?
2. **SEO/GEO safety** — is important content still server-rendered, visible, crawlable and semantically correct?
3. **Performance safety** — does it stay inside the preset Lighthouse budget?
4. **Accessibility safety** — keyboard, focus, contrast, reduced motion and touch targets remain valid.
5. **Responsive reuse** — the component works in different containers, not only at one viewport.
6. **Brand adaptability** — it can express different client brands without every site looking identical.
7. **Operational reuse** — Manager/GitHub-driven publishing can create or update it without bespoke page-builder work.

If a trend fails the filter, it stays out even if it looks impressive in a showcase.

## Shared 2026 design language

Current design research points toward deliberate, distinctive visual systems rather than generic AI-produced sameness; stronger typography and brand-owned treatments; clearer product storytelling; reusable systems; responsive component behavior; and motion used to support comprehension rather than decorate every screen.

The Theme interprets that direction with a **CSS-first / HTML-first** implementation:

- strong typographic hierarchy with fluid `clamp()` scales;
- distinctive but tokenized brand accents;
- large visual statements paired with concise copy;
- modular card/grid systems only where grouping helps comprehension;
- editorial whitespace balanced against information density;
- native CSS container queries for reusable components where appropriate;
- subtle state/hover/entry effects only when they improve affordance;
- `prefers-reduced-motion` support for every motion-capable component;
- no JS-only content, navigation or essential interaction;
- native `details/summary`, CSS and progressive enhancement before JavaScript;
- real imagery/product UI/evidence preferred over decorative stock filler;
- dark/light surfaces selected by brand and content need, not forced globally;
- zero third-party visual runtime by default.

## Design primitives shared by all presets

All five presets may compose from one reusable component vocabulary:

- announcement / trust bar;
- compact sticky-capable header;
- hero variants;
- proof/logo strip;
- KPI/evidence band;
- feature/service cards;
- media + text split;
- comparison table;
- process/steps;
- case-study/result card;
- testimonial/proof block;
- FAQ using native disclosure;
- article/topic cards;
- CTA band;
- contact/conversion form;
- breadcrumbs;
- author/provenance block;
- related-content/internal-link module;
- footer with clear navigation and entity/contact context.

Presets choose and style these components differently; they should not fork the underlying semantic contract without a real functional reason.

---

## Preset 1 — Corporate

### North star

**Premium authority + clarity + proof.** Modern consultancy, B2B, professional-services and company websites should feel intentionally designed, credible and concise rather than like a generic corporate template.

### Functional home flow

1. concise value proposition;
2. primary CTA + secondary evidence path;
3. trust/logo/proof strip when real;
4. service/capability overview;
5. selected outcomes/case studies;
6. method/process;
7. organization/expertise;
8. insights/thought leadership;
9. final CTA/contact.

### Modern visual direction

- strong editorial typography;
- asymmetrical but disciplined grid;
- generous whitespace with bounded section height;
- selective oversized statements;
- restrained brand accent;
- high-quality real imagery or abstract brand-owned visual language;
- compact evidence bands rather than decorative counters;
- case studies shown as outcomes, not gallery filler;
- subtle hover/microinteraction only on actionable components.

### Functional requirements

- service discovery in <=2 navigation levels;
- proof connected contextually to services;
- clear contact/demo/request path;
- Organization facts and provenance visible where relevant;
- article/thought-leadership system ready for ongoing publishing.

### Avoid

- generic icon soup;
- unexplained KPI counters;
- huge empty bands;
- decorative carousels;
- autoplay video backgrounds;
- visual similarity to the legacy client theme.

---

## Preset 2 — Local Business

### North star

**Immediate local trust + action.** The user should understand what the business does, where it is, whether it is available and how to contact/book it within seconds.

### Functional home flow

1. service + location value proposition;
2. primary local CTA: call / book / request quote / directions;
3. rating/review proof only when real and allowed;
4. main services;
5. service area / location / opening information;
6. real photos;
7. process or what-to-expect;
8. local FAQs;
9. map/contact/directions;
10. final action.

### Modern visual direction

- mobile-first action surfaces;
- warm, distinctive brand presentation;
- prominent real photography;
- compact sticky mobile action bar where appropriate;
- service cards optimized for scanning;
- location/context chips;
- clear hours/status/contact panels;
- minimal navigation depth.

### SEO/GEO functional requirements

- LocalBusiness subtype when truthful;
- consistent visible NAP and structured data;
- service-area/location content without doorway-page spam;
- local internal-link hierarchy;
- real reviews/testimonials only;
- location and hours easy to parse by users and machines.

### Avoid

- hiding phone/address/hours deep in the footer;
- fake review stars;
- multiple near-duplicate city pages with thin content;
- map embeds that destroy performance before user intent requires them.

---

## Preset 3 — Publisher

### North star

**Fast editorial discovery + excellent reading.** The design should feel like a modern publication rather than a WordPress blog archive.

### Functional home flow

1. lead story / topic focus;
2. latest or priority stories;
3. topic/cluster navigation;
4. curated editorial modules;
5. newsletter/follow CTA when relevant;
6. evergreen/resource content;
7. author/topic discovery.

### Article experience

- readable line length and fluid typography;
- clear title/deck/byline/date;
- author/provenance;
- optional table of contents for long content;
- media with captions/credits where applicable;
- contextual internal links;
- related coverage;
- visible update history when editorially relevant.

### Modern visual direction

- editorial grids;
- mixed card scales to express priority;
- strong type-led identity;
- image crops controlled by aspect-ratio;
- calm reading surfaces;
- optional dark reading theme only if maintainable and accessible;
- minimal motion inside article bodies.

### SEO/GEO functional requirements

- Article/BlogPosting + author identity;
- topic clusters and crawlable archives;
- freshness/update semantics;
- provenance and sources where applicable;
- clean pagination/archive behavior;
- no infinite-scroll dependence for discovery.

### Avoid

- feed-only homepage with no hierarchy;
- intrusive interstitials;
- excessive sticky UI;
- infinite scroll as the only navigation;
- animations that distract from reading.

---

## Preset 4 — Ecommerce

### North star

**Product discovery + decision confidence + low-friction purchase.** Modern visual polish is subordinate to helping the shopper find, evaluate and buy.

### Functional architecture

1. focused homepage/category entry points;
2. powerful search and product finding;
3. scannable PLP/category grid;
4. information-complete PDP;
5. visible variation/availability/price/shipping context;
6. trust and returns/help information;
7. friction-minimized cart/checkout;
8. useful account/self-service where the stack supports it.

### Modern visual direction

- product-first photography;
- clean grids with strong scan rhythm;
- restrained badges;
- sticky purchase controls on smaller screens where useful;
- comparison and specification surfaces;
- progressive disclosure for secondary detail;
- editorial commerce blocks for collections/use cases;
- tactile microstates for selection/availability.

### Functional requirements

- filters/search remain understandable and crawl strategy is explicit;
- variant controls are visible and touch-friendly;
- price/availability are never hidden behind unnecessary interaction;
- checkout avoids decorative distraction;
- mobile PDP receives equal design attention;
- Product/Offer structured data reflects visible facts only.

### Avoid

- visual novelty that obscures price/variant/CTA;
- hidden sizes/options in hard-to-scan controls when buttons/chips work better;
- excessive popups;
- autoplay media above core product information;
- JS-heavy filtering without crawl/indexability strategy.

---

## Preset 5 — SaaS / Digital Product

### North star

**Explain the product fast, show it in action, make the next step obvious.** The strongest modern SaaS sites combine product clarity, real interface/workflow evidence, distinctive brand language and focused conversion.

### Functional home flow

1. plain-language outcome/value proposition;
2. one obvious primary CTA;
3. real product UI/workflow preview;
4. customer/proof layer when real;
5. features framed as jobs/outcomes;
6. how-it-works;
7. integrations/security/technical proof as relevant;
8. case studies;
9. pricing or buying path;
10. FAQ;
11. final CTA.

### Modern visual direction

- product UI as a primary visual asset;
- editorial + interface composition;
- dark, light or mixed surfaces depending on brand;
- bento/grid modules only when they clarify feature groups;
- selective gradient/glow/3D motifs as brand accents, never as performance-heavy background machinery;
- subtle product-led motion with reduced-motion fallback;
- crisp comparison/pricing surfaces;
- changelog/blog/docs connectivity.

### Functional requirements

- value understood before feature jargon;
- screenshots/workflows are real or clearly illustrative;
- conversion path remains consistent throughout page;
- pricing/plan differences scannable;
- trust/security/integration information discoverable;
- docs/changelog/blog support ongoing product communication.

### Avoid

- generic AI/SaaS gradient template look;
- vague “revolutionize your workflow” copy without product explanation;
- fake interface mockups presented as evidence;
- motion that delays interaction;
- oversized animation bundles.

---

## Trend research cadence

Trend research is a **maintenance input**, not a one-time phase.

Review at least:

- before a major preset release;
- after major browser/CSS platform changes;
- when performance or usability research materially changes;
- otherwise at least quarterly during active product development.

Each review should sample:

- contemporary high-quality product sites;
- Webflow/Framer/Awwwards-style visual trend sources for aesthetic direction;
- web.dev and browser-platform sources for modern CSS/performance capabilities;
- Nielsen Norman / Baymard / comparable usability research for functional validation;
- Google Search documentation for search/structured-data constraints.

Do not copy individual sites. Extract reusable patterns, test them against the adoption filter, then implement them natively in our WordPress design system.

## 2026 research notes

The current review found:

- Webflow's 2026 trend analysis emphasizes intentional, ownable visual systems and differentiation from generic algorithmic sameness.
- Framer's 2026 SaaS examples emphasize explaining the product quickly, showing it in context and keeping one clear next step.
- Framer's current small-business/local examples emphasize immediate value clarity, focused navigation and distinctive but trust-building identity.
- web.dev positions container queries as a way for components to adapt to the space available to them, which aligns with reusable preset components.
- Baymard's current ecommerce research continues to show substantial product-page usability problems across leading stores, supporting a conversion/usability-first Ecommerce preset.
- Google Search documentation reinforces visible, truthful Organization/LocalBusiness facts rather than decorative or fabricated structured data.

These are inputs, not automatic requirements. The functional filter above remains authoritative.
