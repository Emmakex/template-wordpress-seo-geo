# EMMAKE — Corporate inner surfaces v1

## Contract

The existing Corporate v5 strategic homepage remains the one and only strategic
surface owned by the Theme. Inner pages remain native WordPress pages and posts;
the Theme provides shared CSS presentation, not an invented website, article
corpus, legal content or landing copy.

The new `corporate-inner-v1.css` is enqueued only for `corporate` preset
requests that are **not** `is_front_page()`, after `corporate.css`. It uses
content-derived CSS cache busting and does not affect other preset identities,
the 28-slot Site Manager model, Gutenberg source data or SEO ownership.

## Existing EMMAKE WordPress published-page inventory

Read-only Site Manager content-target inventory from 12 November 2026:

| Page | WP ID | Editorial acceptance |
| --- | ---: | --- |
| EMMAKE / Home | 7 | A3.4 approved; not in scope here |
| Servicios | 54267 | **BLOCKER** — HTTP 200 but effectively empty (H1/footer only); requires authored governed content update |
| Sobre nosotros | 50097 | Needs inner responsive/contrast and factual-copy review |
| Nuestras marcas | 50089 | Needs accessible product/brand listing and verified destination links |
| Blog | 50093 | Needs modern index cards, newest-first ordering and mobile spacing |
| Blog Transformación Digital | 50344 | Needs archive/index duplication check and clear destination |
| Contacto | 50085 | Preserve native form, test real submission and privacy notice |
| Trabaja con nosotros | 50476 | Needs current recruitment copy/CTA; no invented vacancies |
| Calculadora de donativos | 50464 | Verify calculator actually works and security/privacy; do not invent functionality |
| Asistentes virtuales de voz | 52879 | Legacy informational page: retain or consolidate only after value/SEO review |
| Casos de éxito | 54268 | Do not publish invented client stories, numerical outcomes or unverified endorsements |
| Política de privacidad | 50324 | Preserve legal text, validate controller/cookies and update only with approved factual information |

Also 40 published blog posts; no wholesale deletion or copy rewriting in this visual cut.

## Visual acceptance

- Non-front Corporate pages share the header/footer's v5 dark teal/mint,
  reading width and accessible CTA/links, without duplicating the homepage
  styles or modifying front-page CSS.
- The post-index grid is three columns on desktop, two on tablets and one on
  mobile. Article cards have readable title, teaser, date and image. All
  content and images continue coming from WordPress.
- The reading layouts respect 760px text line width, 1280px page shell,
  modest internal padding, focus states and reduced motion.
- Services authors may optionally use `seo-geo-inner-service-grid`,
  `seo-geo-inner-service-card` and `seo-geo-inner-cta` block classes. The
  Theme never creates content automatically.
- Empty content must not be disguised with template placeholders. Services and
  Cases remain editorial tasks for Site Manager's Plan/Preview/Apply/Verify.
- Production root `emmake.com` is untouched; staging stays `noindex`.

## Post-install verification

Confirm exact new asset hash and WordPress Site Manager theme model, capture
desktop/mobile pages, check labels/navigation/buttons, min tap targets, H1,
sitemap/robots/canonical, article 200 and no horizontal overflow. Confirm
privacy/legal content and forms separately. No root cutover until all editorial
and functional checks pass.
