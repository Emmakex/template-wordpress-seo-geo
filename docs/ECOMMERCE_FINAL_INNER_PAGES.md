# Ecommerce final inner pages — DS-6B

## Goal

DS-6B turns the accepted Ecommerce Home into a coherent store-facing preset without making the Theme a commerce engine.

The finished preset owns the visual and editorial composition. The supported commerce provider owns product identity, price, availability, variants, ratings/reviews, cart, checkout, account and Product/Offer/Review structured data.

## Candidate pages

- **Shop** — a provider-aware discovery entry, not a fake PLP.
- **Categories** — maintained taxonomy and useful category context, not keyword-variant doorway pages.
- **Buying Guides** — durable comparison and selection guidance grounded in real knowledge.
- **About** — verified store purpose, selection approach, identity and provenance.
- **Support** — maintained policies and real support routes without invented service levels.
- **Contact** — verified public channels and fact-gated operating information.

All six use one renderer (`preset-patterns/ecommerce-inner-final.php`) plus localized provisional copy (`presets/ecommerce/inner-copy.json`). They reuse the accepted Ecommerce visual system; DS-6B adds no stylesheet and no required frontend JavaScript.

## H1 and hydration

The WordPress page template owns the document H1. The DS-6B renderer starts below the title and exposes stable copy/card/final-CTA slots.

Provisional copy remains `publication_ready=false`. Shop and Categories additionally mark catalog-sensitive surfaces with `seo-geo-placeholder--commerce` so a finished-looking layout cannot masquerade as a live catalog.

## Commerce authority

DS-6B must not:

- invent a product, price, discount, stock state, availability, rating or review;
- invent delivery times, return windows, warranty terms or payment methods;
- emit Product, Offer, Review or AggregateRating Schema;
- create fake WooCommerce product blocks or claim a supported WooCommerce combination before an accepted adapter exists;
- duplicate cart, checkout or account as SEO landing pages;
- make faceted URLs indexable by default.

WooCommerce remains the preferred provider, but live commerce support still requires a supported adapter.

## Acceptance

DS-6B is mergeable only when:

1. the accepted DS-6A Home contract remains green;
2. all six candidate pages have EN/ES parity and stable hydration slots;
3. Single / Archive / 404 remain explicitly pending for DS-6C;
4. WPCS and PHPStan pass;
5. the Theme remains self-contained and zero-plugin safe;
6. native multilingual, accessibility/responsive and WordPress activation smoke pass;
7. Lighthouse budgets pass without raising limits.
