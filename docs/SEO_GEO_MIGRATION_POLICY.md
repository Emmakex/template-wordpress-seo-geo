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

## Field decision — EMMAKE `/nuevaweb/`

The real field evidence that motivated this policy shows:

- 1,726 current published posts mapped to 1,726 historical posts;
- zero missing historical identities;
- 1,702 historical routes already under `/blog/%postname%/`;
- 18 routes under `/uncategorized/%postname%/`;
- 6 routes under isolated category prefixes such as `/redes-sociales/`, `/marketing-digital/`, `/consultoria/`, `/business-intelligence/`, `/analisis-de-datos/` and `/investigacion-de-mercado/`;
- 2 local slugs damaged by the migration marker and recovered safely by same-ID historical evidence.

The practical SEO/GEO target is therefore:

```text
/blog/%postname%/
```

The six category-prefixed routes are **not** a reason to restore legacy category assignments or keep `%category%` in the permanent structure. They are historical outliers to preserve with one-hop 301s.

The 18 `/uncategorized/` routes are likewise migration/legacy debt, not target architecture.

The two corrupted slugs remain a protected repair step before the final atomic permalink operation.

## Field workflow

```text
1. Migration Bridge clone/reset-first migration
2. Theme install
3. Manager Field Gate
4. Recover complete historical URL authority
5. Select SEO/GEO target architecture
6. Repair only bounded damaged local data
7. Build exact one-hop redirect map for intentional URL changes
8. Preview atomic target structure + redirect runtime
9. Explicitly confirm guarded Apply on clone/staging
10. Verify stored structure, public routes and redirects
11. Update internal links/canonicals/sitemap to final URLs
12. Continue Build / Finish content/media/SEO work
```

Do not rerun Migration Bridge Reset, clone or Theme hydration merely because Manager changes its target-planning logic.

## Product boundary

- **Migration Bridge** rescues/moves the useful source material and supports reset-first migration.
- **SEO/GEO Theme** owns the clean frontend architecture and rendering.
- **SEO/GEO Manager** decides, previews, mutates and verifies the bounded SEO/GEO transition.

The Manager must never make the new site dependent on bad legacy architecture in order to claim that SEO was preserved.

## Decision test

For every legacy feature, ask:

> Does keeping this feature preserve meaningful search/entity/user value, or does it only preserve old implementation debt?

If the value can be preserved safely through content, metadata, internal links, canonical targeting, Schema or a direct 301, prefer the cleaner new architecture.
