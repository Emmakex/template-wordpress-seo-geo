# Discovery privacy acceptance

## Status

Phase 6F is **complete** and turns the global engineering rule **private content never leaks into discovery surfaces** into a concrete zero-plugin regression contract.

The acceptance is intentionally executed inside the existing disposable self-contained WordPress fixture. It does not start a second Docker stack, database or WordPress instance.

## Threat model

A non-public resource can leak even when its normal page is inaccessible if another public surface exposes its title, body, URL, metadata or machine-readable representation.

The regression matrix therefore uses synthetic draft and private posts with unique markers that are distinct from their URL slugs. This lets the test distinguish a real content leak from an expected echo of a requested URL.

The contract protects:

- draft post titles, excerpts and bodies;
- private post titles, excerpts and bodies;
- native Schema output;
- article provenance metadata;
- optional Markdown alternates;
- optional llms.txt curation;
- native WordPress sitemaps;
- feeds;
- public search results;
- public author archives;
- unauthenticated WordPress REST responses.

## Public control

The matrix also creates one published public control resource.

Positive assertions are required where relevant:

- the public control appears in the native post sitemap;
- the public control may be curated into llms.txt;
- the public control Markdown alternate resolves when the optional feature is enabled.

This prevents a privacy test from passing merely because an entire discovery surface is broken or empty.

## Direct HTML behavior

Unauthenticated requests to the synthetic draft/private pretty URLs must remain non-public.

The current acceptance requires HTTP 404 and additionally verifies that the returned body contains none of the synthetic title/content markers.

Those responses must not contain project-owned:

- `seo-geo-schema-graph` JSON-LD;
- `meta name="author"`;
- `rel="author"`;
- `article:author`;
- `article:published_time`;
- `article:modified_time`;
- `text/markdown` alternate discovery.

## WordPress discovery surfaces

The matrix checks the real WordPress runtime rather than inferring safety from resolver code.

### XML sitemap

The native post sitemap must contain the public control URL and must not contain either non-public slug or any private/draft marker.

### Feed

The public feed must not expose draft/private title or content markers.

### Search

A public search request targeting the synthetic marker namespace must not expose draft/private title or content markers.

### Author archive

The public archive of the shared fixture author must not expose draft/private title or content markers.

### REST API

Unauthenticated requests for the draft/private post IDs may be rejected with HTTP 401, 403 or 404 according to WordPress behavior, but the response body must not disclose the synthetic title/content markers.

## GEO discovery surfaces

### llms.txt

When llms.txt is enabled and its configured post list deliberately contains public, draft and private IDs, only the public resource may be rendered.

### Markdown alternates

When Markdown alternates are enabled:

- the public control alternate must resolve;
- draft/private alternates must remain 404;
- rejected bodies must not expose private/draft title or content markers.

### Resolver-level guard

The runtime authorities themselves are checked directly:

- `ContentProvenanceResolver::for_post()` returns `null` for draft/private posts;
- `MarkdownAlternateResolver::url_for_post()` returns `null` for draft/private posts.

This provides a focused guard beneath the HTTP assertions.

## Runner efficiency

The Phase 6F module is sourced from `scripts/ci/self-contained-theme-smoke.sh`.

It reuses:

- the already-running MariaDB container;
- the already-running WordPress container;
- the built self-contained theme;
- the existing `wp_cli` helper;
- the existing structured `fail_smoke` diagnostic.

No extra WordPress environment is created for this matrix.

## Failure handling

Any leak stops on the first failing surface and uses the existing structured diagnostic contract.

A confirmed product leak must be fixed at its authoritative resolver/query/output owner and receive regression coverage before Phase 6F can close. The test must not be weakened to accept leaked content.

## Scope boundary

Phase 6F proves the current baseline for built-in WordPress posts and the project's existing discovery outputs.

It does not claim to audit:

- arbitrary third-party plugin endpoints;
- CDN caches;
- reverse proxies;
- external search-engine indexes;
- hosting snapshots or backups;
- authenticated editorial/admin responses.

Those systems must preserve the same privacy invariant when integrated later.


## Verified evidence

PR #49 final candidate `99c01be435b0098482ff50ea771957eb7a8bee41` passed the complete ten-workflow matrix and was squash-merged as `feb3f50542e5e56da27d915b1a4e6efe3d73c115`.

Self-contained Theme CI passed the discovery privacy matrix before merge in run `35436517496` and again after merge on `main` in run `35436653739`.

The full post-merge ten-workflow matrix was green. No draft/private title or content leak was detected across the surfaces in this contract.
