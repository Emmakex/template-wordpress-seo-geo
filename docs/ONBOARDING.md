# Theme onboarding

## Status

Phase 8A introduces the theme-owned onboarding foundation and the first operator step: explicit preset selection.

The onboarding screen is part of the self-contained theme. It does not require or recommend installing an SEO/GEO plugin to complete the baseline.

## Admin location

The screen is registered under:

```text
Appearance -> SEO/GEO Setup
```

The page requires the WordPress `manage_options` capability.

The preset-save action uses a WordPress admin-post endpoint plus a nonce. Browser state, hidden fields or JavaScript never grant authority.

## Source-of-truth rule

Onboarding is an operator interface over existing authoritative configuration. It must not create shadow copies of settings.

Phase 8A writes only:

```text
seo_geo_active_preset
```

This is the same option already consumed by the Phase 7 preset registry.

There is no `seo_geo_onboarding_preset` state option and no persistent wizard-step option.

## Preset selection

The allowed values come from the shared server-side registry:

- Corporate;
- Local Business;
- Publisher;
- Ecommerce.

An empty value means no preset selected.

Unknown or malformed values are rejected without mutating the current configuration.

Preset labels come from each preset's declarative pattern-category labels rather than a second onboarding label registry.

## Default behavior

A clean installation remains preset-neutral.

Opening the onboarding page does not:

- activate a preset;
- create pages;
- create posts/products/categories;
- configure Schema identity;
- change crawler policy;
- enable GEO alternates;
- install or activate plugins;
- infer business/editorial/commerce facts.

Saving a preset changes only the authoritative preset option.

## Phase 8A current-status report

The first screen reports:

- active preset;
- WordPress site locale;
- the self-contained zero-required-plugin baseline.

This is intentionally a small live report rather than a stored snapshot. Later Phase 8 microphases will extend the same report with effective language, identity, crawler/GEO and compatibility state.

## EN/ES admin copy

Project-owned onboarding copy ships in English and Spanish together.

The admin-user locale is authoritative for the onboarding UI:

- Spanish admin locale -> ES copy;
- all other locales -> EN fallback.

This is independent from the public site's active content locale.

Preset labels reuse the existing EN/ES labels bundled with each preset.

## Phase 8 sequence

Phase 8 is implemented as independently closable microphases:

1. **8A — onboarding foundation + preset choice**;
2. **8B — native primary/additional language configuration**;
3. **8C — organization/entity basics using existing Schema authorities**;
4. **8D — crawler/GEO opt-ins using existing resolver options**;
5. **8E — effective setup report + optional compatibility warnings/detection**;
6. **8F — onboarding acceptance/closure and operator documentation**.

A later microphase may refine this sequence only when the repository contract is updated in the same PR.

## Security contract

Every privileged mutation must:

- run server-side;
- require `manage_options`;
- require a valid nonce;
- sanitize/validate input;
- write only the authoritative target option;
- redirect after POST;
- expose no credentials or private metadata.

The onboarding baseline uses no AJAX and no custom JavaScript.

## Dependency contract

Phase 8A introduces:

- no runtime dependency;
- no plugin installation flow;
- no external request;
- no additional Docker fixture;
- no new CI workflow.

The same self-contained WordPress fixture used by the existing baseline validates onboarding.

## Acceptance

Foundation must prove:

- the onboarding module and authoritative documentation exist;
- theme bootstrap loads the module;
- capability, nonce and admin-post authority are present;
- preset selection uses the shared allowlist;
- no shadow onboarding option exists;
- no plugin installation/activation or AJAX path is introduced;
- EN/ES admin copy exists.

Self-contained Theme CI must prove on the built distribution:

- onboarding functions load with zero active plugins;
- all four preset IDs validate and unknown IDs fail;
- the Appearance submenu registers;
- a clean site remains preset-neutral;
- the rendered form contains admin-post action + nonce;
- current effective preset/status renders from the real option;
- English and Spanish project-owned copy are both available.

## Boundaries

8A does not yet provide editable language, identity or crawler controls. Those remain owned by their existing Core authorities until the corresponding Phase 8 microphase adds an onboarding adapter.

The onboarding screen is not evidence that an optional provider such as WooCommerce, WPML or an SEO plugin is supported. Compatibility remains governed by `docs/COMPATIBILITY.md`.
