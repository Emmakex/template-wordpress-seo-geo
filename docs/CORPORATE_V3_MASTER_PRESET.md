# Corporate v3 — Master preset contract

## Decision

Corporate v3 is the only active visual preset until it is approved on the real EMMAKE `/nuevaweb/` sandbox.

SaaS, Local Pro, Publisher and Ecommerce remain frozen at their current implementation level. They are not visually iterated in parallel. Once Corporate v3 is accepted, the reusable design primitives, responsive rules, composition patterns and QA gates proven here become the starting point for the other presets.

## Why

The first EMMAKE field pilot proved the migration/rebuild engine end to end, but the current Corporate presentation did not meet the product bar. The failure is visual/product-level, not a reason to discard the rescue/reset/hydration/SEO architecture.

Corporate v3 therefore becomes the reference product that must prove what the Theme is meant to sell: a modern WordPress site that feels deliberately designed rather than assembled from generic theme sections.

## Non-negotiable product standard

Corporate v3 must:

- look credible and near-finished before client-specific refinement;
- use real composition, hierarchy and art direction instead of repeating generic cards;
- keep legacy presentation completely out of the design source;
- hydrate rescued content into semantic slots without letting old content dictate layout;
- adapt gracefully when proof, case studies, testimonials or media are unavailable;
- avoid fabricated commercial evidence;
- remain server-rendered, crawlable and fast;
- require no heavy frontend framework or remote visual dependency;
- preserve Theme / Migration Bridge / SEO-GEO Manager responsibility boundaries.

## Visual direction

The reference should feel closer to a bespoke modern digital studio / consultancy site than to a stock WordPress template.

Required characteristics:

- high-impact hero with a strong value proposition and meaningful visual layer;
- clear but compact navigation;
- stronger typography and more deliberate scale/rhythm;
- asymmetrical/editorial composition where useful;
- fewer repeated equal-width card grids;
- image/media treatment integrated into layout rather than appended as generic blocks;
- clear section-to-section visual rhythm;
- restrained CSS motion only where it improves orientation/quality;
- polished CTA and footer;
- mobile composition designed intentionally rather than merely stacked.

## Content hydration rule

Design owns the slots; rescued content fills them.

The hydrator may map useful factual content, URLs, links, metadata and media into the new system, but it must not reproduce legacy layout decisions. Missing evidence must collapse cleanly or use an intentionally designed neutral state.

## Approval gates

Corporate v3 is not approved until the same real hydrated Home passes all of these viewport gates:

- 1440 px desktop;
- 1024 px compact desktop/tablet landscape;
- 768 px tablet;
- 390 px mobile.

At each viewport the following must pass:

1. visual hierarchy and composition;
2. no overflow or pathological word wrapping;
3. navigation usability;
4. readable typography and spacing;
5. contrast and visible focus;
6. content order and CTA clarity;
7. no legacy-builder debris or placeholder copy exposed;
8. acceptable performance without loosening budgets.

The field pilot additionally requires the existing SEO/GEO, accessibility and machine-readiness gates.

## EMMAKE field pilot role

`/nuevaweb/` is the reference acceptance environment. EMMAKE provides real rescued content and real migration constraints, but must not receive one-off CSS hacks. Any reusable defect found there must be fixed in Corporate v3 and then re-tested in the sandbox.

Do not continue the Services rebuild or move toward production cutover until the Corporate Home itself is approved.

## Derivation rule for the remaining presets

Only after Corporate v3 is approved:

1. extract proven reusable primitives from Corporate v3;
2. keep structural/accessibility/performance behavior shared where appropriate;
3. derive SaaS, Local Pro, Publisher and Ecommerce from those proven primitives;
4. give each preset its own composition and commercial logic instead of cloning Corporate visually;
5. require the same 1440 / 1024 / 768 / 390 acceptance gates for every derived preset.

Corporate is the engineering and quality reference, not a visual skin that the other presets must copy literally.
