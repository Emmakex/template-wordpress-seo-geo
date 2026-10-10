# SEO/GEO migration policy — preserve authority, not legacy debt

Status: **canonical architecture decision** for SEO/GEO Migration Bridge + SEO/GEO Theme + SEO/GEO Manager.

## Core principle

A migrated site is not a restoration project.

The old site is an **evidence and authority source**, not the architectural template for the new site.

We preserve what carries SEO/GEO value:

- historical public URLs and their external authority;
- useful content and entity/topic coverage;
- internal-link relationships that remain valuable;
- media worth retaining;
- titles, metadata and structured evidence worth migrating;
- backlinks, indexed routes and known search-engine signals;
- business facts and editorial history that remain correct.

We do **not** preserve technical or information-architecture debt merely because it exists:

- obsolete themes/builders;
- plugin baggage;
- broken placeholder tokens;
- accidental category URL dependencies;
- `uncategorized` routes;
- duplicate/obsolete layouts;
- inherited URL complexity with no preservation benefit;
- legacy structures that make future content maintenance fragile.

The decision order is:

```text
historical evidence -> SEO/GEO target architecture -> preservation/301 plan -> guarded mutation -> verification
```

Not:

```text
historical architecture -> reproduce everything -> optimize later
```

## URL architecture rule

Historical URLs answer the question:

> What must be preserved directly or redirected so authority and traffic are not lost?

They do **not** automatically answer:

> What should the permanent URL architecture of the new site be?

SEO/GEO Manager therefore separates:

1. **historical URL authority** — complete mapping of old public routes;
2. **target architecture** — the stable structure chosen for the rebuilt site;
3. **redirect preservation** — direct one-hop 301s for historical routes that intentionally change.

## Preferred post URL characteristics

A good new post structure should be:

- stable over time;
- short and predictable;
- independent of editorial taxonomy changes when possible;
- free from temporary migration paths;
- free from low-quality taxonomy labels such as `uncategorized`;
- compatible with direct canonical/internal-link targeting;
- safe to preserve through one-hop 301s when a historical route changes.

Categories remain useful for:

- topical hubs;
- content clusters;
- breadcrumbs;
- internal linking;
- taxonomy archives when intentionally exposed;
- semantic organization;
- Schema and navigation context.

A category does **not** need to be embedded in every post URL in order to provide those signals.

## Dominant clean-target rule — Manager 0.3.29

Manager may propose a clean category-independent target automatically when the historical URL map is complete and one stable literal post prefix strongly dominates the corpus.

Current automatic guardrails:

- historical URL mapping must be complete and unambiguous;
- target candidate must contain exactly one `%postname%` token;
- target candidate must not depend on `%category%` or another dynamic taxonomy token;
- target candidate must represent at least **90%** of mapped posts;
- at least **10** posts must support the candidate;
- low-quality candidates such as `/uncategorized/%postname%/` are not eligible as automatic clean targets;
- outliers remain explicit evidence and are never silently discarded;
- no write occurs during target selection.

This is intentionally conservative: a dominant clean target is an evidence-backed recommendation, not an excuse to guess.

## Redirect policy

When the clean target intentionally changes a historical route:

- use **301**;
- use **one hop only**;
- old URL -> final new URL;
- no redirect chains;
- no redirect loops;
- target must be unique;
- source must be historical evidence, not an arbitrary public redirect supplied by a client request;
- query strings remain preserved by the existing runtime policy;
- activation happens only as part of the guarded atomic permalink + redirect operation.

After migration:

- internal links point directly to final URLs;
- canonicals point directly to final URLs;
- sitemap contains final URLs only;
- breadcrumbs and Schema express hierarchy independently of whether category is present in the post URL.

Redirects exist for historical search-engine/index/backlink compatibility, not as the normal internal navigation layer.

## Corrupted local data remains a separate repair step

Historical identity recovery does not authorize silently changing damaged local values.

If migration damage affected `post_name`:

1. prove historical identity;
2. expose the exact damaged local value and historical evidence;
3. repair the local slug through a bounded reversible operation;
4. rerun the Field Gate;
5. only then approve the final permalink + redirect plan.

This prevents a damaged local slug from becoming a permanent redirect target.

### Protected slug repair contract — Manager 0.3.30

The local-slug repair is a **separate operation** from permalink migration. It never changes `permalink_structure`, never activates the redirect runtime and never flushes rewrite rules.

A repair candidate is eligible only when:

- the complete historical authority map has already identified that exact post through the guarded migration-corruption fallback;
- the local `post_name` still exactly matches the damaged value seen during preview;
- the target slug is derived only from the verified historical slug;
- WordPress confirms that the target slug is unique for the post;
- no unrelated permalink-plan collision exists;
- the authority, plan and repair fingerprints still match the current state;
- production environment acknowledgement is current.

Apply is idempotent and records one reversible Manager operation. The operation snapshots the exact pre-repair slug and the existing `_wp_old_slug` metadata. Rollback is stale-safe: if a repaired slug was changed later, rollback is refused rather than overwriting newer state.

After Apply, Manager must regenerate the authoritative permalink plan and confirm that:

- historical mapping is still complete;
- zero local slug repairs remain;
- the selected SEO/GEO target structure is unchanged.

Even a successful slug repair **does not authorize permalink Apply**. A fresh Field Gate is mandatory before reviewing the subsequent atomic target-structure + 301 operation.

## Field decision — EMMAKE `/nuevaweb/`

The real Manager 0.3.29 Field Gate evidence confirms:

- 1,726 current published posts mapped to 1,726 historical posts;
- zero missing historical identities;
- 1,702 historical routes support `/blog/%postname%/`;
- support ratio `0.986095` (98.6095%);
- 24 historical outliers need one-hop 301 preservation under the clean target;
- 2 local slugs are damaged by the migration marker and were recovered safely by same-ID historical evidence;
- the current permalink template itself is still malformed and remains untouched during slug repair.

The practical SEO/GEO target is therefore:

```text
/blog/%postname%/
```

The category-prefixed and `/uncategorized/` routes are **not** a reason to restore legacy category assignments or keep `%category%` in the permanent structure. They are historical outliers to preserve with one-hop 301s.

The two corrupted slugs are the only local-data prerequisite before the final permalink plan can become eligible for atomic review.

## Field workflow

```text
1. Migration Bridge clone/reset-first migration
2. Theme install
3. Manager Field Gate
4. Recover complete historical URL authority
5. Select SEO/GEO target architecture
6. Preview protected local-slug repair
7. Explicitly confirm protected local-slug Apply
8. Verify repaired local state and keep permalink/runtime untouched
9. Rerun Field Gate
10. Build exact one-hop redirect map for intentional URL changes
11. Preview atomic target structure + redirect runtime
12. Explicitly confirm guarded Apply on clone/staging
13. Verify stored structure, public routes and redirects
14. Update internal links/canonicals/sitemap to final URLs
15. Continue Build / Finish content/media/SEO work
```

Do not rerun Migration Bridge Reset, clone or Theme hydration merely because Manager changes its target-planning or protected-repair logic.

## Product boundary

- **Migration Bridge** rescues/moves the useful source material and supports reset-first migration.
- **SEO/GEO Theme** owns the clean frontend architecture and rendering.
- **SEO/GEO Manager** decides, previews, mutates and verifies the bounded SEO/GEO transition.

The Manager must never make the new site dependent on bad legacy architecture in order to claim that SEO was preserved.

## Decision test

For every legacy feature, ask:

> Does keeping this feature preserve meaningful search/entity/user value, or does it only preserve old implementation debt?

If the value can be preserved safely through content, metadata, internal links, canonical targeting, Schema or a direct 301, prefer the cleaner new architecture.
