# Replatforming Contract — reset-first rebuild

## Product intent

A full redesign is a **new website built from a cloned copy**, not a careful preservation of the legacy runtime.

The authoritative reset workflow is defined in `docs/RESET_REBUILD_CONTRACT.md`.

The principle is:

> **Rescue the asset, reset the clone, rebuild the product.**

The old website is useful as a source of content, URLs, SEO equity, links, selected media and business facts. Its theme, builder, CSS, widgets, plugin baggage and presentation architecture are not assets we want to carry forward.

## What survives

Preserve only what gives the new site real value:

- useful factual content;
- URLs/slugs and required redirect intent;
- SEO metadata/indexability/canonical intent worth retaining;
- internal/external links worth retaining;
- selected media;
- business/entity/contact facts;
- required legal content;
- required forms/business integrations;
- multilingual relationships when the rebuilt site needs them.

Preservation is **selective**, not exhaustive.

## What is reset

Replace/remove by default:

- legacy theme and child theme;
- Divi/Elementor/other builder presentation runtime;
- layout metadata, builder CSS and generated assets;
- presentation-only shortcodes;
- old widgets/Customizer state;
- obsolete theme options;
- redundant frontend plugins;
- legacy responsive hacks;
- duplicate SEO/rendering ownership once the Theme takes control;
- caches/transients and stale generated files;
- plugins/media/assets that the rebuilt site no longer needs.

A migration that leaves the new site carrying the old visual/runtime stack has failed the product goal.

## Product roles

### Migration Bridge

One-time transition tool:

**Scan → Clone → Rescue Manifest → Reset → Theme/preset bootstrap → Rebuild handoff**

Once a usable clone already exists, the Bridge must not force the operator to repeat non-essential legacy analysis before rebuilding.

UNKNOWN legacy dependencies are relevant only when they represent a function the rebuilt site genuinely needs.

### SEO/GEO Theme

The Theme becomes the new presentation and technical SEO/GEO authority:

- modern design system;
- preset information architecture;
- semantic/native WordPress components;
- responsive/accessibility behavior;
- technical SEO/GEO;
- Schema/discovery;
- multilingual behavior;
- performance budgets.

### SEO/GEO Manager

Permanent post-rebuild operations layer:

- optimized landing/blog creation;
- content refresh and improvement;
- editorial workflows;
- internal-link optimization;
- Search Console/Bing/analytics feedback;
- controlled publish/update/rollback;
- continuous SEO/GEO improvement.

The Manager is not required to preserve the old builder architecture.

## End-to-end full-redesign workflow

1. **Scan** enough of the original site to identify the digital asset.
2. **Clone** the site into the working destination.
3. **Create a Rescue Manifest** containing the content/URL/SEO/link/media/business material worth keeping.
4. **Reset the clone** by removing the legacy presentation/runtime baggage.
5. **Install/activate the SEO/GEO Theme**.
6. **Apply the selected preset**.
7. **Rebuild pages** using Theme-native patterns/components.
8. **Reinsert/refactor the rescued asset** into the new information architecture.
9. **Optimize** copy, headings, links, media, Schema and metadata without inventing facts.
10. **Validate** URLs/redirects, SEO/GEO, accessibility, responsive behavior, performance and required business functions.
11. **Cut over** only after acceptance.
12. **Operate continuously** through SEO/GEO Manager.

## Native Replatform Composer / Reviewed Remap

The existing Composer, Content Remap and Reviewed Apply features remain useful as **optional extraction/rebuild helpers**.

They are not the architecture of the finished site and they are not required to preserve every legacy asset.

Use them when they accelerate the Rescue Manifest or page reconstruction. Skip or discard legacy candidates that do not belong in the new site.

Their safety rules still apply when used:

- never fabricate factual proof;
- never silently change public production content;
- preserve provenance for reused facts;
- keep draft operations reversible.

But the reset-first path is allowed to intentionally leave old content/assets unmapped and remove them from the rebuilt clone.

## Emmake current path

For `emmake.com/nuevaweb/`:

- the clone already exists;
- the clone itself is the working reset environment;
- Corporate is the selected preset;
- the remaining UNKNOWN dependency is not a blocker unless it maps to a business function we choose to keep;
- we do **not** need another baseline/analyzer cycle simply to begin the redesign;
- the immediate work is:
  1. build the minimal Rescue Manifest;
  2. reset the old theme/builder/plugin baggage;
  3. activate/apply the SEO/GEO Theme + Corporate preset;
  4. rebuild Home and the remaining core pages from scratch;
  5. reuse only the content/URLs/SEO/links/media that improve the new site;
  6. remove everything else;
  7. validate the clean result.

## Acceptance model

The rebuilt site is accepted when:

- the necessary search equity is retained;
- required URLs redirect or resolve correctly;
- the new Theme/preset fully owns presentation;
- legacy builder/runtime dependencies are gone from accepted surfaces;
- required business functionality works;
- duplicate SEO/GEO ownership is eliminated;
- accessibility/responsive/performance gates pass;
- the final site is materially lighter and simpler than the clone it started from.

Visual similarity to the old site is explicitly **not** an acceptance criterion.
