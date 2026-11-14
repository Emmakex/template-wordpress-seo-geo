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

## Measured migrated-blurb remediation — 12 Nov 2026

Read-only public DOM metrics show that the 10,393px-tall Nuestras Marcas
page contains ten native `sgmb-divi-blurb` groups, several with source images
rendering 900–1,210px tall. They are **not** spacer blocks. Instead of deleting
logos or altering the native Gutenberg source, the Theme now groups sequential
legacy blurbs into a 3/2/1 responsive card grid and caps the *rendered image*
height to 184px using `object-fit: contain` (no cropping). Headings, links,
images and text remain in WordPress.

The 7,558px Blog Transformación Digital page also contains multiple native
Latest Posts lists: lists of only one or two results now span an appropriate
reading width rather than leaving orphan narrow cards. Deduplicating or
consolidating multiple author-created lists is a separate governed editorial
operation, not a CSS side effect.

All changes are generic to the native migration block classes and restricted
to Corporate inner pages. Home and root production domain are untouched.
Only real post-install browser screenshots and numeric layout evidence may
certify visual readiness.

## Verified live state — 14 November 2026

The real public-only headless-Chromium audit in PR #295 (artifact `emmake-inner-pages-live-audit-20261114`, 18 screenshots) supersedes the provisional 12 November inventory above. Site Manager now reports **10 published pages**, not 12: `calculadora-de-donativos` and `casos-de-exito` must not be assumed published or reactivated without editorial approval.

At 1440px and 390px, all nine currently published inner pages return HTTP 200, present a single H1, retain staging noindex, display no broken image or horizontal overflow, and have native Gutenberg content. The page `servicios` is **no longer empty**: it has 1,620 visible characters, three service cards, a three-step method and a Contact CTA (desktop 2,060px, mobile 2,779px). The previous Services empty-page BLOCKER is resolved in the installed WordPress content.

| Published page | Desktop / mobile height | Editorial / functional follow-up |
| --- | ---: | --- |
| Contacto | 2,105 / 1,634 px | Native form 3 required fields; informational WordPress privacy link in proposed Theme cut, still verify a permitted end-to-end delivery without spamming |
| Nuestras Marcas | 5,424 / 8,072 px | 14 native blurbs (including service blurbs), 15 images, no broken media; some brands have no supplied logo and should not receive fabricated assets |
| Blog | 3,219 / 5,014 px | 12 native article cards, but the three-column desktop grid was cramped at 237px/card; new reviewed CSS proposal measures 371px/card, 2 columns tablet (344px), 1 mobile (350px) |
| Sobre Nosotros | 5,250 / 7,054 px | Native form 5 required fields, 14 images, 1 H1; avoid invented experience/proof statements |
| Política de privacidad | 4,102 / 4,935 px | Contains WordPress-era generic privacy text; controller, cookies/retention and legal claims require factual/legal approval before rewriting |
| Blog Transformación Digital | 3,460 / 5,313 px | Existing curated topic/category structure preserved; no source duplicates reintroduced |
| Trabaja con Nosotros | 1,748 / 1,522 px | Mentions CV email `rrhh@emmake.com` but no verified vacancies; do not fabricate job openings |
| Asistentes virtuales de voz | 2,862 / 2,964 px | Legacy public editorial page, preserve existing URLs pending content strategy |
| Servicios | 2,060 / 2,779 px | Editorial content complete at this scope; no new blank-page fix needed |

The 6 initial audit failures arose from a broad heuristic `content_links` on Contacto, Sobre Nosotros and Política de privacidad (the authors used plain email/text, not necessarily broken links). **No verified broken link or 404 was established** by that heuristic; do not label those pages as structurally broken. The optional newsletter invitation on the Blog currently has no form in the inspected main content; do not advertise that signup as an active feature without an integrated provider.

The CSS proposal is read-only previewed against actual Gutenberg DOM; only a separately provenance-verified release installed through Site Manager and byte-for-byte `site.theme_package_state` readback can establish it as live. No WP content, forms, SEO settings or production root are altered by these CI checks.

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
