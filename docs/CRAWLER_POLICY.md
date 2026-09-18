# Native Crawler Policy

## Scope

Phase 6A introduces the server-side crawler-policy engine for the self-contained theme.

The policy deliberately keeps **search/discovery** and **potential training collection** separate:

- `OAI-SearchBot` controls OpenAI search/discovery crawling;
- `GPTBot` controls crawling that publishers may disallow for potential model training use.

These controls are independent. Allowing search does not imply allowing training, and blocking training does not require blocking search.

OpenAI's current publisher guidance says that sites should avoid blocking `OAI-SearchBot` when they want content eligible to be crawled for ChatGPT search summaries/snippets, while publishers can disallow `GPTBot` for pages they want excluded from potential training. This project does not turn those statements into ranking or citation guarantees.

Authoritative references:

- https://help.openai.com/en/articles/12627856
- https://help.openai.com/en/articles/20001243-advertiser-guidance-for-allowing-openai-web-crawlers

## Configuration contract

The server-side option is:

```php
seo_geo_crawler_policy
```

Supported keys:

```php
array(
    'oai_searchbot' => 'inherit', // inherit | allow | disallow
    'gptbot'        => 'inherit', // inherit | allow | disallow
)
```

Invalid, missing or non-string values normalize to `inherit`.

The default is intentionally neutral: the theme does not change crawler access until the site explicitly chooses a policy.

## robots.txt output

The native presenter uses WordPress's virtual `robots.txt` filter.

Examples:

```text
User-agent: OAI-SearchBot
Allow: /

User-agent: GPTBot
Disallow: /
```

or independently:

```text
User-agent: OAI-SearchBot
Disallow: /

User-agent: GPTBot
Allow: /
```

Rules:

- `inherit` emits no crawler-specific block;
- `allow` emits `Allow: /`;
- `disallow` emits `Disallow: /`;
- when WordPress marks the site as non-public, the presenter emits no crawler-specific overrides;
- if another owner already emitted an exact `User-agent` block for a managed crawler, the native presenter does not add a duplicate competing block;
- this mechanism affects WordPress's **virtual** `robots.txt`; a physical web-server `robots.txt` file is outside WordPress and must be managed separately.

## robots.txt is not noindex

Crawler permission and indexing/search presentation are separate controls.

OpenAI's current publisher guidance notes that a blocked page URL may still be known from other sources and may be surfaced as a link/title in some circumstances. If a publisher wants a page excluded from indexing/presentation, the page-level indexing policy remains the relevant control. A crawler must be able to access a page in order to read a `noindex` meta directive.

The native crawler policy therefore does not rewrite the existing SEO `noindex` authority.

## Safety and ownership

- crawler policy is server-authoritative;
- only fixed supported crawler names are emitted;
- client/model output cannot add crawler permissions;
- site-wide WordPress privacy is never overridden;
- crawler controls do not expose credentials or private content;
- no crawler policy is described as a guaranteed SEO/GEO ranking factor;
- the baseline requires no third-party GEO plugin.

## Phase boundary

Phase 6A owns the crawler policy engine and virtual robots output only.

A later Phase 6 microphase will add the customer-facing admin/onboarding controls. Optional `llms.txt`, localized Markdown alternates, provenance/source patterns and cache/invalidation remain separate work so each contract can be validated independently.
