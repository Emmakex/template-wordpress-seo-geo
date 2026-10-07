# Corporate master preset contract — active iteration v4

## Decision

Corporate is the only active visual preset until the real EMMAKE `/nuevaweb/` sandbox approves it at the premium/WOW product bar.

The active visual iteration is **Corporate v4**. Corporate v3 remains historical evidence: it proved the engineering direction but was explicitly rejected in real browser QA as visually insufficient.

SaaS, Local Pro, Publisher and Ecommerce remain frozen at their current implementation level. They are not visually iterated in parallel. Once Corporate is accepted, the reusable design primitives, responsive rules, composition patterns and QA gates proven here become the starting point for the other presets.

## Why

The EMMAKE field pilot proved the migration/rebuild engine end to end. It also proved that repository CI, perfect technical rendering and even Lighthouse `100` do not by themselves make a sellable visual product.

Corporate therefore remains the reference product that must prove what the Theme is meant to sell: a modern WordPress site that feels deliberately designed by a strong digital studio rather than assembled from generic theme sections.

## Non-negotiable product standard

Corporate must:

- create an immediate premium/WOW first impression rather than merely look clean;
- look credible and near-finished before client-specific refinement;
- use real composition, hierarchy and art direction instead of repeating generic cards;
- keep legacy presentation completely out of the design source;
- hydrate rescued content into semantic slots without letting old content dictate layout;
- adapt gracefully when proof, case studies, testimonials or media are unavailable;
- avoid fabricated commercial evidence;
- remain server-rendered, crawlable, accessible and fast;
- require no heavy frontend framework or remote visual dependency;
- preserve Theme / Migration Bridge / SEO-GEO Manager responsibility boundaries.

## Active v4 visual direction

Corporate v4 deliberately moves further away from a conventional WordPress-template composition:

- dominant full-bleed campaign hero rather than a hero card;
- much larger editorial typography and deliberate scale contrast;
- stronger navigation and brand presence;
- asymmetric statement panels instead of repeated equal cards;
- full-bleed method scene with oversized deterministic step numbering;
- magazine-style Insights hierarchy;
- near-screen-height final CTA that closes the page decisively;
- CSS-created geometry and depth without remote assets or required frontend JavaScript;
- mobile/tablet layouts recomposed intentionally, not merely stacked.

The direction is not approved because it exists in code. It is approved only if the hydrated real Home creates the intended product reaction in the browser.

## Content hydration rule

Design owns the slots; rescued content fills them.

The hydrator may map useful factual content, URLs, links, metadata and media into the new system, but it must not reproduce legacy layout decisions. Missing evidence must collapse cleanly or use an intentionally designed neutral state.

The already hydrated `/nuevaweb/` Home is reused for visual iterations. Do not rerun Reset, hydration or SEO handoff merely because the presentation layer changes.

## Approval gates

Corporate is not approved until the same real hydrated Home passes all of these viewport gates:

- 1440 px desktop;
- 1024 px compact desktop/tablet landscape;
- 768 px tablet;
- 390 px mobile.

The automated 320 px check remains an additional compact-mobile stress case.

At each viewport the following must pass:

1. premium visual hierarchy, composition and first impression;
2. no overflow or pathological word wrapping;
3. navigation usability and sufficient brand presence;
4. readable typography, deliberate spacing and useful visual rhythm;
5. contrast, keyboard behavior and visible focus;
6. content order and decisive CTA clarity;
7. no legacy-builder debris or placeholder copy exposed;
8. acceptable performance without loosening budgets;
9. preserved SEO/GEO rendered output and semantic structure.

A result described only as “better”, “clean”, “correct” or “modern enough” does **not** pass the visual gate.

## Engineering guardrails learned from v4

Corporate v4 keeps the strict technical baseline while increasing visual impact:

- Lighthouse performance `100` on the frozen v4 source;
- FCP/LCP median around `903 ms`;
- CLS `0` and TBT `0`;
- zero project JavaScript bytes and zero third-party requests in the Corporate fixture;
- transferred Corporate CSS `8087` bytes against an `8192` byte browser budget;
- static CSS proxy ceiling tightened to `7100` bytes so CI predicts real browser headroom rather than passing a looser approximation;
- accessibility/responsive automation covers the product acceptance widths.

The engineering budget is not relaxed to buy the WOW effect.

## EMMAKE field pilot role

`/nuevaweb/` is the reference acceptance environment. EMMAKE provides real rescued content and real migration constraints, but must not receive one-off CSS hacks. Any reusable defect found there must be fixed in Corporate and then re-tested in the sandbox.

Do not continue the Services rebuild or move toward production cutover until the Corporate Home itself is explicitly approved.

## Derivation rule for the remaining presets

Only after Corporate is approved:

1. extract proven reusable primitives from the accepted Corporate system;
2. keep structural/accessibility/performance behavior shared where appropriate;
3. derive SaaS, Local Pro, Publisher and Ecommerce from those proven primitives;
4. give each preset its own composition and commercial logic instead of cloning Corporate visually;
5. require the same `1440 / 1024 / 768 / 390` acceptance gates for every derived preset.

Corporate is the engineering and quality reference, not a visual skin that the other presets must copy literally.
