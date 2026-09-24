# SEO GEO Core

Reusable source library for the native SEO/GEO runtime embedded in the installable `seo-geo-theme` package.

This directory is **not a required WordPress plugin dependency**. The product build copies `src/` into the theme so a clean WordPress installation can use the SEO/GEO baseline with zero active plugins.

`seo-geo-core.php` is **deprecated for installation** and retained temporarily only as an optional compatibility/development wrapper through the first stable-release decision. It delegates to the same context-neutral `SeoGeo\Core\Runtime`, is outside the required installation path and is not distributed in the self-contained theme ZIP. New deployments must use the self-contained theme.

## Runtime modules

```text
src/
├── Integrations/
├── Language/
├── Seo/
└── Runtime.php
```

Future native modules will include Schema, GEO, crawlers, sitemaps and other machine-discovery capabilities as their phases close.

## Core rules

- native SEO/GEO works without third-party plugins;
- exactly one authoritative owner per public SEO signal;
- server-side capability/provider decisions;
- no secrets/private content in discovery surfaces;
- EN/ES behavior is designed as a first-class contract;
- GEO mechanisms never override canonical/indexability truth;
- WordPress core primitives are reused before custom infrastructure;
- optional integrations prevent conflicts or enhance deliberate external setups, but never become baseline dependencies;
- the context-neutral runtime initializes only once even if more than one packaging wrapper is present during development.

## Distribution

`scripts/build-theme-package.sh` copies this source tree into:

```text
seo-geo-theme/inc/seo-geo-core/src/
```

`Self-contained Theme CI` proves the resulting theme boots the runtime from that location with zero active plugins.
