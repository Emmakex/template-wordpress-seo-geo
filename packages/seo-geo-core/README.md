# SEO GEO Core

This package will become the installable WordPress plugin that owns durable SEO/GEO behavior independent of the active theme.

## Planned modules

```text
src/
├── Crawlers/
├── Geo/
├── Integrations/
├── Language/
├── Markdown/
├── Performance/
├── Schema/
├── Seo/
└── Sitemaps/
```

## Core rules

- exactly one authoritative provider per public SEO signal;
- server-side capability/provider decisions;
- no secrets/private content in discovery surfaces;
- language resolution behind adapters;
- EN/ES customer-facing strings together;
- optional GEO mechanisms never override canonical/indexability truth;
- integrations delegate rather than duplicate output.

## Phase 1 target

The first installable version will contain a safe plugin bootstrap, module registration skeleton, language-provider interface, integration detection boundary and activation smoke tests. SEO output will be added in later closed phases rather than prematurely mixed into scaffolding.
