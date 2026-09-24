# Portable sandbox strategy

## Problem

Many WordPress clients do not have a staging environment. Safe migration therefore cannot depend on a hosting feature the client may not own.

The product rule is:

> **Staging is a capability of our migration workflow, not a prerequisite the client must already have.**

## Supported operating modes

### Mode A — Client staging exists

Use the client staging/clone feature, then independently enforce SEO/GEO sandbox acceptance:

- non-production origin;
- search-engine visibility disabled;
- explicit sandbox marker;
- no public canonical/hreflang/sitemap competition;
- accepted backups;
- exact candidate artifact;
- parity, accessibility, performance and functionality checks.

A hosting provider saying staging is not sufficient evidence by itself.

### Mode B — No staging, but WordPress/hosting access exists

Manager coordinates a **portable sandbox**.

The environment may be created by:

- agency-managed VPS/container infrastructure;
- local/Docker environment;
- a supported temporary hosting provider;
- a client-hosted isolated subdomain/container.

Manager provides the analysis/export/acceptance contract; the environment provisioner remains replaceable.

### Mode C — Limited WordPress access

Manager runs the safest available read-only analysis and creates an export/diagnostic package that can be used to construct the sandbox elsewhere.

Minimum useful access may include:

- WordPress administrator for plugin installation/analysis; or
- hosting backup/export; or
- a client-supplied WordPress/database/uploads copy.

With no WordPress/hosting/export access at all, the system may perform public external auditing only; it cannot claim a safe migration.

## Portable sandbox package

The migration workflow should produce a versioned manifest describing:

- source site identity;
- WordPress/PHP versions;
- active theme/plugins/builders;
- relevant CPT/taxonomy registrations;
- public SEO/GEO baseline reference;
- file/uploads backup reference;
- database backup/reference;
- required environment variables/secrets **by name only**, never secret values;
- selected destination artifact/checksum;
- migration plan;
- dynamic-data classification;
- recovery references.

Actual database dumps, uploads archives and credentials remain in protected storage and are never committed to the repository.

## Data minimization

The sandbox needs enough data to reproduce layout/content/integration behavior, but the workflow should avoid copying unnecessary sensitive data.

Where technically appropriate:

- redact/anonymize customer/user/order/form data in development copies;
- disable outbound email/SMS/payment/webhooks;
- use sandbox credentials for external services;
- prevent search indexing;
- prevent production analytics contamination.

A sanitized sandbox may be insufficient for some client-critical integrations; such exceptions require explicit acceptance.

## Dynamic-site rule

WooCommerce, bookings, memberships, forms, CRM sync and other changing systems require a different cutover model from a static brochure site.

Forbidden pattern:

    clone production Monday
    migrate/test for days
    restore Monday database over Friday production

That can destroy newer orders, submissions, users and operational state.

Instead:

1. use the sandbox copy to prove the transformation;
2. retain production as data authority;
3. at cutover, re-read current production state;
4. apply only the accepted structural/content/runtime changes;
5. use an explicit maintenance/data-sync plan when a mutable dataset must move;
6. recover only the smallest required dataset if a failure occurs.

## Sandbox isolation

A portable sandbox must default to:

- non-production hostname/origin;
- WordPress blog_public=0;
- SEO_GEO_MIGRATION_SANDBOX=true;
- noindex,nofollow,noarchive;
- X-Robots-Tag equivalent;
- disabled/redirected outbound transactional email;
- disabled live payment mutations unless a safe sandbox provider is configured;
- separate analytics or analytics disabled;
- robots/sitemap/canonical/hreflang checks proving no staging-origin discovery leak.

## Provisioner boundary

SEO/GEO Manager should not hard-code one hosting vendor as architecture.

Define a provisioner interface with capabilities such as:

- create environment;
- upload/restore site package;
- inject environment configuration;
- return origin/runtime metadata;
- destroy environment;
- report evidence references.

Initial implementations may be manual/agency-operated. Automation can be added per provider without changing migration acceptance.

## Cutover evidence

Before production entry, require:

- fresh DB and uploads recovery references;
- exact destination artifact/checksum;
- accepted dependency plan;
- accepted SEO/GEO parity;
- accessibility/responsive pass;
- performance pass;
- client-critical functionality pass;
- rollback owner/path;
- dynamic-data decision.

No sandbox evidence can authorize production if it is stale relative to material production changes.

## Relationship to products

- SEO/GEO Theme does not create sandboxes.
- SEO/GEO Manager coordinates portable-sandbox/migration operations.
- The Theme remains installable and usable without Manager.
- Manager remains useful after migration because publishing/operations are separate from sandbox mode.
- Sandbox/migration modules may be disabled when no migration is active.

## First real-site target

emmake.com remains the first Theme stable-release pilot. Its current Phase 10E acceptance may use the already-accepted Migration Bridge implementation.

After SEO/GEO Manager reaches the relevant roadmap phase, emmake.com should also be used as the first **Manager + Theme** integration pilot, with content publication performed draft-first before any public publish acceptance.
