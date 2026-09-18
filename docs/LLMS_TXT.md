# Native llms.txt

## Status

Phase 6B adds an **optional** virtual `/llms.txt` endpoint to the self-contained theme.

The implementation follows the llms.txt v2 proposal current as of 2026-09-18 while treating it as an interoperability convention, not as an SEO ranking signal, crawler permission mechanism or inclusion guarantee.

It does not replace:

- `robots.txt` crawler policy;
- `sitemap.xml`;
- canonical/indexability rules;
- `hreflang`;
- Schema.org structured data;
- normal human-readable HTML content.

## Default behavior

The endpoint is disabled by default.

With no explicit configuration:

```text
GET /llms.txt -> normal WordPress 404
```

No file is auto-created on disk and no rewrite-rule flush is required.

When enabled, the runtime intercepts only the exact site-root `/llms.txt` path, including WordPress installations whose home URL lives in a subdirectory.

## Configuration contract

WordPress option:

```php
seo_geo_llms_txt
```

Baseline structure:

```php
array(
    'enabled' => true,
    'summary' => 'Short factual site summary.',
    'sections' => array(
        array(
            'title' => 'Primary resources',
            'post_ids' => array( 12, 34, 56 ),
        ),
    ),
)
```

The site title becomes the required H1.

The configured summary becomes the optional blockquote. When no explicit summary is provided, the WordPress site description/tagline is used if non-empty.

Every configured section becomes an H2 followed by a Markdown file list.

## Explicit curation only

Phase 6B intentionally does **not** crawl the database or automatically enumerate every public URL.

Only explicitly configured WordPress post IDs can enter the document.

A resource is emitted only when all of the following are true:

- the ID is valid;
- the object exists;
- status is `publish`;
- the post type is publicly viewable;
- the object is not an attachment;
- the object is not password-protected;
- a same-host absolute HTTP(S) public URL can be resolved;
- the public title is non-empty.

Draft, pending, private, trashed, password-protected and malformed resources are omitted.

Duplicate IDs are emitted at most once across the complete document.

Malformed sections or sections with no valid public resources are omitted.

## Multilingual URLs

The generator reuses the existing native translation relationship and localized SEO authorities.

When a configured resource belongs to a validated reciprocal translation relationship:

- its relationship language is read from the existing registry;
- its link uses the authoritative localized prefixed URL;
- the unprefixed staged/noindex copy is never used;
- the file-list note records the language code.

Example:

```text
## Guides

- [SEO técnico](https://example.com/es/servicios/seo-tecnico/): Language: es
- [Technical SEO](https://example.com/en/services/technical-seo/): Language: en
```

Resources without a validated native translation relationship use their normal public WordPress permalink.

Phase 6C may add Markdown alternates for individual localized pages; Phase 6B does not fabricate them.

## WordPress privacy precedence

The endpoint is available only when both are true:

- `enabled === true`;
- WordPress `blog_public=1`.

If the site owner enables **Discourage search engines from indexing this site**, native `llms.txt` stops resolving and the request falls through to normal WordPress handling.

This mirrors the privacy precedence already used by the Phase 6A crawler policy.

## HTTP behavior

When enabled and resolvable:

- exact `GET /llms.txt` returns HTTP 200;
- content type is `text/plain` with the WordPress charset;
- `X-Content-Type-Options: nosniff` is emitted;
- HEAD requests return headers without a document body;
- the baseline uses no-cache response headers until the later Phase 6 cache/invalidation contract is implemented.

No physical file is written.

A physical server-level `llms.txt`, reverse proxy or CDN rule may take precedence before WordPress receives the request. Compatibility detection/warnings belong to the later onboarding layer.

## Format alignment

The current llms.txt v2 proposal defines:

1. one required H1;
2. an optional blockquote summary;
3. optional non-heading informational content;
4. zero or more H2 sections whose items are Markdown links with optional descriptions.

Phase 6B emits the required H1 plus the optional summary and curated H2 link sections.

The proposal recommends concise files and links to LLM-friendly content. Until native per-page Markdown alternates arrive in Phase 6C, Phase 6B links to authoritative public HTML resources rather than inventing alternate content.

## Relationship to crawler policy

`llms.txt` and `robots.txt` have different purposes.

Phase 6A controls whether specific crawlers are explicitly allowed, disallowed or inherited.

Phase 6B provides a curated map of public information when an agent chooses to retrieve it.

Publishing `llms.txt` does not override a crawler disallow. A crawler/user agent remains responsible for respecting the applicable access policy.

## Acceptance contract

Zero-plugin acceptance proves:

- disabled by default -> normal 404;
- enabled endpoint -> HTTP 200 and plain text;
- required H1, summary and valid H2/link-list structure;
- only explicitly selected published/public/unprotected resources appear;
- drafts, private pages and password-protected resources never leak;
- duplicate IDs are deduplicated;
- malformed sections are ignored;
- WordPress global privacy disables the endpoint;
- HEAD resolves without needing a body;
- translated resources use authoritative ES/EN prefixed URLs rather than staged unprefixed copies;
- existing SEO, Schema, crawler, accessibility and performance contracts remain green.

## References

Checked 2026-09-18:

- llms.txt v2 proposal: https://llmstxt.org/
- llms.txt v2 changes: https://llmstxt.org/changes.html
- OpenAI Publishers and Developers FAQ: https://help.openai.com/en/articles/12627856

The llms.txt proposal and external agent behavior can change. Future implementation changes must re-check current primary documentation before changing the contract.
