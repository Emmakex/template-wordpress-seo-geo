# GEO crawler policy

## Status

Phase 6A defines the native crawler-policy contract for the self-contained WordPress theme.

The baseline is deliberately conservative:

- no OpenAI-specific robots directives are emitted unless the site owner explicitly configures them;
- OpenAI search discovery and potential model-training access are separate decisions;
- WordPress's global site-visibility setting remains authoritative over crawler-specific allows;
- the runtime modifies only WordPress's virtual `robots.txt`; it does not create a physical robots file;
- no crawler policy promises ranking, inclusion, citation, traffic or model behavior.

## OpenAI crawler roles

The native baseline currently models two independently configurable user agents.

### OAI-SearchBot

Purpose in this contract: public-content discovery for ChatGPT search experiences.

OpenAI's current publisher guidance says sites that want their content eligible to be included in ChatGPT search summaries/snippets should not block OAI-SearchBot.

### GPTBot

Purpose in this contract: control whether public site content may be crawled for potential model training.

OpenAI's current publisher guidance says publishers that want pages excluded from potential training should disallow GPTBot.

These controls are intentionally independent. Allowing OAI-SearchBot does not imply allowing GPTBot, and disallowing GPTBot does not imply opting out of ChatGPT search discovery.

## Configuration contract

WordPress option:

```php
seo_geo_crawler_policy
```

Supported keys:

```php
array(
    'oai_searchbot' => 'inherit|allow|disallow',
    'gptbot'        => 'inherit|allow|disallow',
)
```

Supported states:

- `inherit`: emit no native group for that crawler and preserve existing WordPress robots behavior;
- `allow`: emit `Allow: /` for that crawler;
- `disallow`: emit `Disallow: /` for that crawler.

Missing, malformed or unsupported keys resolve to `inherit`.

Example — discoverable in ChatGPT search while opting out of potential training:

```text
# BEGIN SEO GEO crawler policy
User-agent: OAI-SearchBot
Allow: /

User-agent: GPTBot
Disallow: /
# END SEO GEO crawler policy
```

## WordPress privacy precedence

When WordPress's **Discourage search engines from indexing this site** setting is active (`blog_public=0`), native crawler-specific rules are not appended.

This prevents an explicit `allow` for OAI-SearchBot or GPTBot from creating a more specific robots group that could weaken the site's global private/non-public crawl intent.

The global WordPress robots output remains authoritative.

## Ownership and compatibility

The SEO/GEO runtime owns only the block delimited by:

```text
# BEGIN SEO GEO crawler policy
# END SEO GEO crawler policy
```

Existing WordPress robots output is preserved. The presenter removes its own previous block before rendering, making the native filter idempotent.

Phase 6A does not attempt to rewrite or silently override crawler-specific directives injected by third-party plugins, reverse proxies, CDNs or physical `robots.txt` files. Compatibility detection/warnings belong to the later onboarding/compatibility layer.

## Security and infrastructure

A `robots.txt` user-agent string is not an authentication mechanism. Infrastructure that needs to distinguish legitimate crawler traffic from spoofed user agents should additionally use provider-published IP/range verification or a trusted verified-bot facility at the CDN/WAF layer.

The theme does not modify firewall, CDN or hosting allowlists.

## Acceptance contract

The zero-plugin WordPress acceptance must prove:

- no OAI-SearchBot or GPTBot group is emitted when configuration is absent;
- OAI-SearchBot can be `allow` while GPTBot is `disallow`;
- the inverse states are not accidentally emitted;
- malformed values and unknown crawler keys are ignored;
- `inherit` emits no native crawler group;
- global WordPress privacy prevents crawler-specific allow groups;
- the distributable theme contains the crawler resolver and presenter;
- existing SEO, Schema, multilingual, accessibility and performance gates remain green.

## References

Primary references checked 2026-09-18:

- OpenAI Publishers and Developers FAQ: https://help.openai.com/en/articles/12627856
- OpenAI ChatGPT Search guidance: https://help.openai.com/en/articles/9237897
- OpenAI advertiser crawler guidance: https://help.openai.com/en/articles/20001243

These external crawler roles and recommendations can change. Future changes must be verified against current primary documentation before altering the native policy contract.
