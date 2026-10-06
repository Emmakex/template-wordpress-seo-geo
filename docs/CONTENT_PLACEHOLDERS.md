# Semantic Placeholder / Content State Contract

## Purpose

The SEO/GEO rebuild must not become a manual form-filling exercise when an old WordPress site lacks enough usable copy for a modern preset. Missing editorial copy is therefore a **content-state problem**, not a structural migration blocker.

The normal priority is:

1. rescued authored content;
2. safe derived copy and links;
3. explicit semantic draft placeholders only for unresolved editorial slots.

The old site's layout is never used as the visual source.

## States

Every generated semantic slot is classified as one of:

- `source`: directly supported by rescued authored content;
- `derived`: deterministic Theme/Bridge copy or a value derived from known site structure, locale or links;
- `placeholder`: draft-only editorial guidance that still requires replacement or optimization;
- `authored`: a former placeholder that has been deliberately replaced through the reviewed Content Kit workflow.

The automatic Home plan exposes `content_state.slot_states`, `content_state.placeholder_slots` and `content_state.publishable`.

## Placeholder policy

Placeholders are semantic prompts rather than meaningless filler. For example, a missing service body asks the editor to explain the problem, audience and expected outcome instead of inserting generic Lorem Ipsum.

Lorem Ipsum remains acceptable for isolated visual component demos, but not for migrated client drafts because semantic placeholders are easier to review, map and replace automatically.

Placeholders must never:

- invent customers, awards, statistics, results or endorsements;
- claim a geography, certification or capability that was not established;
- auto-verify proof/evidence groups;
- be interpreted as final SEO/GEO content.

## Publish safety

A Home containing semantic placeholder slots is intentionally reviewable but not publishable.

`AutomaticHomeContentStateKit` stores the placeholder slot list on the clean Home draft. `PlaceholderPublishingGuard` intercepts WordPress public/scheduled status writes and keeps that draft as `draft` while unresolved placeholder slots remain.

`PlaceholderResolutionTracker` watches reviewed Corporate Home Content Kit updates. When a previously generated prompt is actually replaced, that slot becomes `authored` and is removed from the unresolved placeholder list. Publication becomes available only after the unresolved list is empty. Saving the same generated prompt does not count as resolution.

This gives the product three independent properties:

- the design can be generated and reviewed even with incomplete old content;
- incomplete migration prompts cannot accidentally become the public indexed Home;
- genuine reviewed replacements progressively unlock publication without requiring a hidden manual override.

The sandbox crawler policy remains a separate defense-in-depth control.

## Recoverable vs structural blockers

The placeholder engine may recover only editorial-content shortages such as:

- no usable source summary;
- fewer than three usable capabilities;
- fewer than three usable process statements;
- quality filtering reducing capabilities below the visual minimum.

It does **not** bypass structural or safety requirements such as:

- Rescue Manifest missing;
- clean Home draft missing;
- front-page source missing;
- sandbox/storage isolation requirements;
- contact target missing;
- theme/preset runtime unavailable.

## Relationship with the future Content Optimizer

The placeholder engine is not the copywriting product.

Its job is to keep the Design System complete and deterministic. The future SEO/GEO Content Optimizer can later use `slot_states` to prioritize work:

1. replace `placeholder` slots first;
2. improve weak `derived` copy where appropriate;
3. preserve or deliberately optimize `source` copy with provenance retained;
4. preserve reviewed `authored` copy unless the operator intentionally requests optimization;
5. validate intent, entities, internal links, geography and evidence before publication.

## Acceptance

For a content-poor legacy site the automatic flow must be able to produce a complete private Corporate Home without executing legacy builders. The plan must expose placeholder state, the generated Home must remain visually complete, evidence groups must remain unverified, and WordPress must refuse to make the placeholder-bearing draft public. Replacing placeholders through the reviewed Content Kit must shrink the unresolved list deterministically and unlock publication only when no placeholder remains.
