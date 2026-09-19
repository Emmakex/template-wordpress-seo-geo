# Native content provenance

## Status

Phase 6D adds one reusable provenance authority for public built-in WordPress posts.

The goal is not to invent a proprietary GEO metadata vocabulary. The native baseline reuses existing public signals that search engines, agents and browsers already understand:

- WordPress post author identity;
- public author archive URL;
- first publication date;
- last modification date;
- authoritative canonical source URL;
- explicitly configured Organization publisher when present.

The same values are reused across HTML, Open Graph, Schema.org and optional Markdown alternates.

## Public author identity

For a published built-in post, the author comes from the real WordPress `post_author` relationship and the existing `SchemaIdentityResolver`.

The public identity contains:

- visible display name;
- public author archive URL;
- stable Person Schema ID derived from that profile URL.

The single-post template renders `core/post-author-name` with `isLink=true`, so the visible byline links to the same public author archive used by machine-readable metadata.

The HTML head also emits:

```html
<meta name="author" content="Author Name" />
<link rel="author" href="https://example.com/author/author-name/" />
```

The HTML `author` link relation identifies a resource that provides more information about the author of the current document.

## Publication and modification dates

WordPress remains the source of truth:

- publication: `get_post_datetime( $post, 'date' )`;
- modification: `get_post_datetime( $post, 'modified' )`.

Both values use ISO 8601 with timezone information.

The native BlogPosting graph already emits:

- `datePublished`;
- `dateModified`.

Phase 6D reuses the same WordPress values in Open Graph:

- `article:published_time`;
- `article:modified_time`.

No request time, build time or guessed editorial date is used.

## Open Graph article provenance

For an indexable built-in post owned by native SEO output, Phase 6D adds:

```text
article:published_time
article:modified_time
article:author
```

`article:author` is the public author profile URL, not a display-name string.

These properties are emitted only for the article context. Author archives, pages, searches and other non-article requests do not receive article provenance properties.

## Schema alignment

Phase 6D does not add a second Schema graph or duplicate author entities.

The existing native BlogPosting already contains:

- stable article ID;
- canonical URL;
- headline;
- `datePublished`;
- `dateModified`;
- author reference to the stable Person node;
- publisher reference only when an Organization was explicitly configured.

Google's current Article guidance recommends author identity, an author URL/profile, publication date and modification date when those values apply. Internal author profile pages are suitable author URLs and may themselves use ProfilePage structured data.

## Publisher

Publisher provenance is conservative.

Only the existing explicit Organization opt-in can become article publisher provenance. A site title alone is not enough to infer Organization identity.

LocalBusiness is not propagated onto an article when its physical business facts are absent from that article page.

The Markdown provenance block may include Publisher only when that explicit Organization identity already resolves.

## Markdown provenance

When Phase 6C Markdown alternates are enabled for a built-in post, the document includes the same provenance values:

```text
Source: [https://example.com/article/](https://example.com/article/)
Language: en-US
Author: [Author Name](https://example.com/author/author-name/)
Publisher: [Example Organization](https://example.com/)
Published: 2026-09-18T10:00:00+02:00
Updated: 2026-09-18T12:00:00+02:00
```

The source URL is the authoritative HTML URL already resolved by the Markdown layer.

Pages that are not built-in posts do not receive fabricated article authors, publisher or publication dates.

## Output ownership

The provenance presenter registers only when native Core is the active SEO provider.

This prevents the theme from adding a parallel article metadata layer when another supported provider owns overlapping SEO output.

The existing native Open Graph property filter remains the owner of `og:*` and `article:*` metadata.

## Privacy and negative behavior

Provenance resolves only for:

- built-in WordPress `post`;
- status `publish`;
- no post password;
- current route indexable when rendering current-page metadata;
- valid public author identity when author metadata is emitted.

Draft, private, password-protected, noindex and non-article resources do not gain article provenance.

## Acceptance contract

Zero-plugin acceptance must prove:

- the visible post byline links to the same public author profile used by metadata;
- exactly one standard `meta[name=author]` is emitted for the article;
- `rel=author` points to that same profile;
- Open Graph `article:author` uses that profile URL;
- Open Graph publication/modification times equal BlogPosting `datePublished/dateModified`;
- Markdown Author/Published/Updated values equal HTML/Schema provenance;
- explicit Organization publisher is reused in Markdown when configured;
- author archives do not receive article provenance metadata;
- existing Schema Person/BlogPosting identities remain unchanged;
- all zero-plugin, multilingual, accessibility and performance gates remain green.

## References

Checked 2026-09-19:

- Google Article structured data: https://developers.google.com/search/docs/appearance/structured-data/article
- Google structured-data policies: https://developers.google.com/search/docs/appearance/structured-data/sd-policies
- Open Graph protocol article properties: https://ogp.me/
- HTML `rel=author`: https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/rel
- HTML `meta name=author`: https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/meta/name
- WordPress Author Name block: https://developer.wordpress.org/block-editor/reference-guides/core-blocks/core-blocks-theme/core-block-post-author-name/

These signals improve clarity and machine-readable provenance but do not guarantee rankings, citations, inclusion or rich-result display.
