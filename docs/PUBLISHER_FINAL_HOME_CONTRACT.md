# Publisher final Home contract

DS-5A turns the existing `publisher` preset into a finished editorial Home without changing the preset identifier or weakening its editorial provenance model.

## Product boundary

The Home owns layout, hierarchy, responsive behavior, visual rhythm and safe provisional presentation. It does not invent or infer publishable editorial facts.

Hydration may replace provisional story titles, summaries, topics, visuals and follow routes, but must preserve the accepted composition unless a later product revision explicitly changes it.

## Evidence boundary

The final Publisher surface must never invent:

- author identity, biography, role or expertise;
- citations, references or source URLs;
- publication or modification dates;
- read counts, subscriber counts or awards;
- breaking-news or recency claims;
- Article/BlogPosting eligibility outside the existing semantic authority.

Authors remain sourced from approved public WordPress users, dates from native WordPress post timestamps, and references from real editor-added sources.

## Rendering boundary

- `front-page.html` owns the document H1.
- The final Home requires no project JavaScript.
- No remote font, map, image or visual dependency is required.
- Shared Design System primitives are opt-in for Publisher only when the Publisher preset is active.
- Publisher-specific presentation stays in `assets/css/presets/publisher.css`.
- Provisional copy and media remain visibly marked as placeholders and block publication readiness until hydrated.

## DS-5A acceptance

DS-5A is complete only when the existing Publisher semantic contract, the dedicated final-Home contract and the global PHP, WordPress, self-contained, accessibility/responsive, multilingual and Lighthouse gates are all green without relaxing performance budgets.
