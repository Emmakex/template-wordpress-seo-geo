# Publisher final inner pages — DS-5B

DS-5B finishes the six editorial pages that sit between the accepted Publisher Home and the later system-surface phase.

## Final page set

- Articles — real published work, editorial priorities and maintained reading paths; never fabricated freshness or popularity.
- Topics — maintained taxonomy and useful subject hubs; no thin keyword-only archives.
- Authors — approved public WordPress users and real authored work only; no inferred credentials or ghost identities.
- About — sourced publication purpose, scope, governance and accountability.
- Editorial Policy — real operating standards, sourcing, corrections and conflict rules; no policy claims the publication does not actually operate.
- Contact — approved public editorial, contributor and business routes only; no invented departments, addresses or response times.

## Shared implementation

All six pages use one renderer: `packages/seo-geo-theme/preset-patterns/publisher-inner-final.php`.

Localized provisional content lives in `presets/publisher/mockup-inner-copy.json`. The renderer reuses the accepted Publisher visual system and shared Design System primitives; DS-5B adds no new stylesheet and no required frontend JavaScript.

The page template retains document-H1 ownership. Inner patterns start below the page title and expose stable provisional content slots for later hydration.

## Evidence and provenance boundary

DS-5B must preserve the existing Publisher authorities:

- public author identity comes from real approved WordPress users/profiles;
- publication and modification dates come from native WordPress post timestamps;
- references come only from editor-added real sources;
- author expertise, credentials, reviewer identities and source URLs are never inferred;
- public policy and contact routes must be maintained by the real publication;
- provisional content remains publication-blocking until hydrated.

## Out of scope

Single, Archive and 404 remain neutral Theme surfaces in DS-5B. Publisher receives preset-owned system templates only in DS-5C after inner-page acceptance.

## Acceptance

DS-5B may merge only when the existing Publisher semantic contract, the accepted Home contract, the dedicated inner-page contract and the complete global PHP, WordPress, self-contained, accessibility/responsive, multilingual and Lighthouse gates are green without relaxing performance budgets.
