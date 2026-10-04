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

The current design benchmark and trend-adoption filter live in `docs/MODERN_PRESET_DESIGN_STRATEGY.md`. A replatform must therefore be not only cleaner than the legacy site, but intentionally **modern and functionally appropriate for its preset category**.

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

## Native Replatform Composer

The first write-capable rebuild step is intentionally **draft-only**.

For an accepted sandbox, the composer:

- reads the active preset's page composition;
- resolves the preserved source page;
- records source ID, URL path and content fingerprint;
- assembles only registered Theme/preset-native patterns;
- creates a non-public draft replacement;
- records a deterministic plan fingerprint;
- reuses an equivalent draft instead of creating duplicates;
- never rewrites the source page, its slug or its public URL.

The draft is a destination canvas. Content remapping is a separate step and must distinguish preserved facts from proposed presentation copy. Missing evidence must remain missing/manual-review; the system must not fabricate credentials, outcomes, testimonials, locations, prices or other factual claims.

## Content Remap Intelligence

Content Remap Intelligence is intentionally provenance-first and non-generative.

For each preserved source page it may:

- extract bounded source units from the post title, excerpt and static WordPress block content;
- inventory crawlable links and media references without rendering dynamic blocks;
- assign deterministic hashes/IDs so every candidate can be traced back to the exact source snapshot;
- read the selected preset's `content_contract.required_sections`;
- propose bounded source candidates for each required semantic section;
- flag evidence-sensitive sections such as proof, outcomes, credentials, testimonials, provenance or attribution for mandatory manual verification;
- persist only the remap plan identity and bounded review summary on the destination draft.

It must **not**:

- generate or infer factual claims that are absent from the preserved source;
- auto-apply uncertain semantic mappings;
- treat a candidate as verified evidence merely because related words or links exist;
- rewrite the public source page;
- reintroduce builder-era visual layout as part of the remap.

A candidate is therefore not publication approval. The next write-capable remap stage must require explicit reviewed selections and retain unmapped assets in the review ledger rather than silently discarding them.

## Reviewed Remap Application

Reviewed Apply is the first content-remap stage allowed to mutate the destination draft.

The preset must explicitly bind a semantic section to a stable native composition point. The composer materializes that binding as an inert slot marker and includes the slot map in the deterministic native plan hash.

Reviewed Apply must:

- operate only inside an accepted sandbox;
- require administrator capability and an explicit nonce-confirmed action;
- target only a private native draft that is still bound to the preserved source;
- revalidate source ID/hash, preset/page identity, native plan hash and content-remap plan hash immediately before mutation;
- accept only asset IDs already present in the current slot candidate set;
- require explicit verification for evidence-sensitive sections;
- replace exactly one deterministic slot marker per selected semantic section;
- use native WordPress blocks for the applied material;
- store the current pre-apply draft body as rollback evidence before each active reviewed selection cycle;
- persist selected and unmapped asset IDs, verification decisions, selection hash and before/after draft hashes in a review ledger;
- support an explicit administrator-only rollback that restores only the hash-verified private draft, refuses rollback after unreviewed draft drift, archives bounded rollback evidence and clears the active ledger for a revised selection;
- be idempotent for an identical reviewed selection.

Reviewed Apply must **not**:

- mutate the preserved public source;
- publish the destination draft;
- create factual copy or silently reinterpret a source asset;
- accept arbitrary text/URLs supplied outside the current source inventory;
- bypass evidence verification because a candidate exists;
- drop unmapped source assets from the audit trail;
- apply when a slot marker, source snapshot or plan hash has drifted.

This stage is deliberately narrower than final editorial optimization. It moves verified preserved material into the native information architecture; later optimization may improve hierarchy, linking and wording only under the separate SEO/GEO content rules without changing factual meaning.

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
