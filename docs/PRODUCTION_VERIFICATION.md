# Production verification checklist

Phase 10D defines the verification gate after a client candidate reaches the production origin. The checklist is intentionally stricter than “the page loads”: production acceptance must prove runtime health, client functionality and material SEO/GEO signals.

## Deployment identity

Before checking the site, record:

- client/site identifier;
- production origin;
- deployed release version;
- release ZIP SHA-256;
- upstream/client commit;
- deployment timestamp;
- previous accepted release/ZIP;
- operator responsible for the deployment;
- linked sandbox acceptance record;
- linked database/uploads recovery references.

If the deployed artifact cannot be identified unambiguously, production verification fails.

## Pre-flight gate

Do not begin a production cutover unless the sandbox-to-production checklist is accepted.

At minimum require:

- deterministic release ZIP and checksum;
- fresh database and uploads backup evidence where the site is non-disposable;
- accepted SEO parity for existing-site migration;
- accepted Accessibility/Responsive evidence;
- accepted Performance/Lighthouse evidence;
- known rollback artifact;
- explicit list of intended theme/plugin mutations;
- production origin and cache/CDN ownership confirmed.

## Immediate production checks

Check the public production origin immediately after deployment.

- representative homepage returns the intended 2xx response;
- representative page/post/archive/localized URLs return expected status;
- WordPress admin remains reachable for authorized operators;
- active theme is `seo-geo-theme`;
- baseline SEO/GEO runtime resolves from the theme bundle;
- no unexpected SEO/GEO plugin is activated;
- no PHP fatal, warning, notice or uncaught exception is emitted by project code;
- page templates render without missing critical blocks/assets;
- navigation and primary calls to action remain usable;
- cache/CDN serves the intended production origin and current artifact.

## SEO/GEO checks

For representative public URLs verify:

- canonical URL;
- robots/indexability;
- XML sitemap availability and expected entries;
- hreflang and x-default when multilingual routing is enabled;
- Open Graph metadata;
- Schema identity plus page-specific nodes;
- LocalBusiness facts against visible content when applicable;
- redirect targets and absence of loops/chains introduced by the deployment;
- `robots.txt` crawler policy;
- `llms.txt` when enabled;
- Markdown alternate headers/links when enabled;
- no private/draft/protected content leaked into public discovery output.

For migrated sites, unexplained loss versus the accepted baseline is a production blocker.

## Functional checks

Verify client-critical behavior that can be affected by theme/runtime changes.

Examples include:

- forms submit successfully and confirmation behavior is correct;
- ecommerce catalog/cart/checkout/account paths remain functional;
- consent/analytics hooks still load according to the client's policy;
- search, menus and pagination work;
- media/images render;
- authentication/account flows remain reachable where applicable.

A client integration not represented by the reusable repository must have its own focused acceptance evidence.

## Accessibility and responsive checks

On representative pages:

- keyboard navigation reaches interactive controls;
- visible focus remains usable;
- layout does not horizontally overflow expected viewports;
- critical text/control contrast remains acceptable;
- no new automated WCAG A/AA violations are introduced by the deployment.

Use the accepted browser evidence from sandbox as the reference and spot-check production output after deployment.

## Performance checks

Confirm that production does not materially exceed the accepted sandbox budget.

At minimum compare:

- Lighthouse performance evidence;
- LCP/CLS/INP-sensitive layout/script regressions where measurable;
- page weight/request count for representative templates;
- cache/CDN behavior.

Transient network variance alone is not a release decision. Investigate repeatable regressions.

## Runtime and log checks

Review:

- WordPress/PHP error log for project fatal/warning/notice signatures;
- web-server 5xx responses;
- application monitoring if the client has it;
- unexpected cron/background failures caused by changed theme code;
- cache purge errors;
- failed redirect or rewrite behavior.

Do not place credentials or raw private logs in the repository acceptance record.

## Abort criteria

Stop verification and enter rollback/recovery when any of these occur:

- sustained or representative 5xx/fatal error;
- wp-admin becomes unavailable to authorized operators;
- production renders the wrong theme/runtime version;
- canonical, robots, sitemap, hreflang or redirect behavior has an unexplained material regression;
- critical forms, checkout or account flows break;
- private content is exposed;
- rollback evidence/artifact is missing when a rollback is required;
- the deployed artifact cannot be tied to its expected checksum;
- a security-impacting regression is discovered.

A visual mismatch alone may be reviewable; material search, security, data or transaction regressions are blockers.

## Acceptance record

Complete `docs/templates/PRODUCTION_ACCEPTANCE_RECORD.example.json` (or the client-owned equivalent) with bounded references, not secrets.

The final production decision is one of:

- `accepted`;
- `rollback-required`;
- `recovery-required`;
- `review-required`.

A deployment is not accepted until all required production checks are recorded as passed or an explicit documented exception exists.

## Follow-up

Search-engine recrawl, indexing and external cache propagation may take time and are not guaranteed by this project. Record later observations separately without rewriting the original deployment evidence.
