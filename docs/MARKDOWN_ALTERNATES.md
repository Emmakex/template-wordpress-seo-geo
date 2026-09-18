# Localized Markdown alternates

## Status

Phase 6C adds **optional** Markdown alternates for authoritative public WordPress resources.

The feature is disabled by default and follows the llms.txt v2 discovery proposal checked on 2026-09-18:

- an HTML page may advertise a Markdown representation with `rel="alternate" type="text/markdown"`;
- the Markdown representation uses the same logical URL with `index.md` appended for directory-style URLs;
- a page may advertise the covering `llms.txt` document with `rel="describedby"`;
- HTTP `Link` headers mirror the HTML discovery links.

This remains an interoperability convention, not a ranking, citation or inclusion guarantee.

## Configuration

WordPress option:

```php
seo_geo_markdown_alternates
```

Baseline structure:

```php
array(
    'enabled' => true,
)
```

The endpoint is active only when:

- `enabled === true`;
- WordPress `blog_public=1`;
- the source resource is published;
- the post type is publicly viewable;
- the resource is not an attachment;
- the resource is not password-protected;
- the request targets the authoritative public route.

## URL contract

Directory-style authoritative HTML URLs use `index.md`.

Examples:

```text
https://example.com/about/
https://example.com/about/index.md

https://example.com/es/servicios/seo-tecnico/
https://example.com/es/servicios/seo-tecnico/index.md
```

Non-directory URLs use the proposal's append form:

```text
https://example.com/page.html
https://example.com/page.html.md
```

No physical Markdown files are written and no rewrite flush is required.

## Multilingual authority

Markdown alternates reuse the existing reciprocal translation registry and localized SEO resolver.

For a validated ES/EN relationship:

- `/es/.../` can expose only the Spanish resource's Markdown alternate;
- `/en/.../` can expose only the English resource's Markdown alternate;
- the staged unprefixed copy remains non-authoritative and exposes no Markdown alternate;
- a wrong language prefix does not resolve a Markdown representation;
- `llms.txt` prefers the localized Markdown URL when Phase 6C is enabled.

This keeps Markdown, canonical, `hreflang`, Open Graph, Schema and the native translation relationship aligned to the same source of truth.

## Discovery

An authoritative HTML page emits:

```html
<link rel="alternate" type="text/markdown" href="https://example.com/es/page/index.md" />
```

When `llms.txt` is also enabled:

```html
<link rel="describedby" href="https://example.com/llms.txt" />
```

Equivalent HTTP `Link` headers are emitted for clients that do not parse HTML.

Markdown responses link back to their authoritative HTML source and, when available, to the covering `llms.txt` document.

## Markdown content

The generated document contains:

- one H1 from the public WordPress title;
- the authoritative HTML source URL;
- the relationship language code when localized, otherwise the WordPress site language;
- the manual post excerpt when present;
- conservative Markdown derived from authored WordPress block content.

The converter does **not** execute shortcodes or dynamic block callbacks. This prevents Markdown generation from accidentally executing personalized, third-party or runtime-only content.

The baseline preserves authored text and selected structural semantics:

- headings;
- quotes;
- paragraph/list/general authored text.

Later phases may improve the converter, but must preserve the same privacy and authority rules.

## Privacy and leakage prevention

Markdown alternates are never emitted for:

- drafts;
- private posts/pages;
- trashed resources;
- attachments;
- password-protected resources;
- invalid translation relationships;
- staged unprefixed translation copies;
- wrong-prefix localized routes;
- globally private WordPress sites.

Disabling the feature cleanly returns normal WordPress handling for `.md` requests.

## Relationship to llms.txt

Phase 6B can operate without Phase 6C and link directly to authoritative public HTML.

When Phase 6C is enabled, the same curated `llms.txt` entries prefer Markdown alternate URLs when a valid alternate exists.

This follows the v2 recommendation that `llms.txt` link to LLM-friendly detailed content while keeping the root file concise.

## HTTP behavior

Valid Markdown alternates return:

- HTTP 200;
- `Content-Type: text/markdown` with the WordPress charset;
- `X-Content-Type-Options: nosniff`;
- no-cache headers until the later cache/invalidation microphase;
- GET body output;
- HEAD headers without a response body.

Invalid or disabled alternate requests fall through to normal WordPress handling.

## Acceptance contract

Zero-plugin acceptance must prove:

- Markdown is absent by default;
- enabling the feature exposes the exact derived `index.md` resource;
- HTML advertises `rel="alternate" type="text/markdown"`;
- HTML and Markdown advertise `llms.txt` through `describedby` when enabled;
- Markdown links back to authoritative HTML;
- draft/private/password-protected resources remain unavailable;
- global WordPress privacy disables Markdown;
- `llms.txt` prefers Markdown over HTML when available;
- ES/EN resources expose only their matching localized Markdown URLs;
- unprefixed and wrong-prefix translation Markdown routes remain unavailable;
- staged HTML routes never advertise Markdown;
- existing SEO, Schema, crawler, accessibility and performance contracts remain green.

## References

Checked 2026-09-18:

- llms.txt v2 proposal: https://llmstxt.org/
- llms.txt v2 changes: https://llmstxt.org/changes.html
- OpenAI Publishers and Developers FAQ: https://help.openai.com/en/articles/12627856

OpenAI's current publisher guidance continues to describe crawler access through OAI-SearchBot/robots.txt; Markdown alternates do not replace that access policy.
