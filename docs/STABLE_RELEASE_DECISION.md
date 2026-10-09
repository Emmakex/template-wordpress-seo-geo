# Stable release decision

Phase 10E is the final gate for promoting the self-contained SEO/GEO Theme from `prestable` to the first stable release.

## Current decision

**NO-GO for stable release.**

Target release: `0.1.1`.

Release channel: `prestable`.

Repository CI, deterministic packaging, accessibility, responsive and performance evidence are necessary, but they do not replace the real `/nuevaweb/` product gate. Production remains untouched until an explicit later decision.

## Canonical Phase 10E candidate

The machine-readable source of truth is:

`release/emmake-phase10e-candidate.json`

Current MF-08 field identity:

- Theme implementation source commit: `eb3c523e2302f464ed253b4693831967b7be4c8a`;
- Theme: `0.1.1` / `prestable`;
- Theme ZIP SHA-256: `c19935f45b7997090cf5fbcae85da584e159be66608820d0030eff3c9f7a92cc`;
- Migration Bridge: `0.8.60`;
- Migration Bridge ZIP SHA-256: `f51a3123218c2ba92521c3a4c41bd65e52b27dbd6aa697715e9cad36f5ff2183`;
- deterministic EMMAKE field-pilot pack SHA-256: `d444e196dd2addca512ba9cc286bc8e094d2aca0cab52c0adccb48181a365561`.

The candidate record intentionally stays `stable_decision=no-go` and `acceptance_status=pending`. The metadata commit may be newer than `source_commit`; `source_commit` identifies the implementation bytes frozen by the deterministic package contract.

The JSON candidate is the hash authority. `EMMAKE Field Pilot Pack CI` rebuilds Theme, Migration Bridge and the outer field pack byte-for-byte and rejects any mismatch against that record.

## Candidate evolution

Corporate v5 moved the strategic master surface out of Gutenberg layout authority and into a Theme-owned semantic server renderer.

- **A1** proved the renderer/model boundary.
- **A2** established Theme-owned strategic header/footer/chrome.
- **A3** refined the real-site palette and visual separation.
- **A3.1** closed the bounded hero/media/method/Insights presentation baseline and preserved the eight-request / zero-project-JS performance contract.
- **MF-08** adds the optional real-media field layer plus the mobile corrections found in real iPhone QA without changing content, URLs, SEO/GEO ownership, Reset, hydration or Migration Bridge state.

MF-08 is the current technical field candidate. A1/A2/A3/A3.1 remain historical evidence, not the current install authority.

## MF-08 repository contract

The field candidate preserves:

- semantic server-rendered strategic HTML;
- one strategic H1 and Theme-owned header/main/footer;
- WordPress as CMS/resource authority;
- Theme-owned frontend layout and performance;
- existing rescued/hydrated content and SEO/GEO state;
- no required third-party frontend visual dependency;
- zero external project JavaScript request for the strategic surface;
- deterministic self-contained Theme packaging;
- exact Migration Bridge `0.8.60` boundary for this pilot.

MF-08 specifically verifies:

- optional local media-atlas rendering without shipping client media in generic source;
- X/toggle, outside tap, menu-link and Escape close behavior on compact mobile;
- centered mobile `Contacto` CTA;
- no capability-media overlap with headings at compact widths;
- preservation of the Corporate request/performance budget;
- no SEO/GEO, accessibility, responsive or semantic regression.

## Architecture ownership

The permanent rule remains:

> **Gutenberg provides editorial autonomy. SEO/GEO Manager provides automation and growth. SEO/GEO Theme provides frontend rendering, strategic chrome, design, semantic HTML and performance.**

WordPress remains the CMS/resource authority.

Canonical architecture: `docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md`.

The Corporate strategic master does not rely on Gutenberg `contentSize` / `wideSize`, `is-layout-constrained`, `wp-block-post-content`, nested layout blocks or database-overridable template parts as its frontend master contract.

## SEO/GEO Manager relationship

SEO/GEO Manager is a separate plugin product and is not required for Theme stable runtime.

The renderer boundary is compatible with Manager because Manager writes structured content/model state while Theme remains the public renderer. Manager may create/update strategic models and normal WordPress posts, while Theme owns strategic presentation and exactly one accepted SEO/GEO output authority remains active per signal.

## Selected real-site pilot

Production origin: `https://emmake.com/`.

Sandbox: `https://emmake.com/nuevaweb/`.

The sandbox already has rescued/hydrated semantic content and prior Step 7 readiness evidence. Presentation changes do **not** justify rerunning Reset, regenerating Home, rehydrating content or replacing Migration Bridge.

The next field action is one bounded Theme-only installation of the exact MF-08 candidate on `/nuevaweb/`, followed by browser/product QA.

## Remaining stable-release blocker

Phase 10E remains `no-go` until all of the following are real and recorded:

1. the deterministic current Theme candidate is frozen and repository gates pass;
2. that exact candidate is installed only on `/nuevaweb/`;
3. existing Reset/Rebuild and hydrated semantic content state remains intact;
4. native SEO/GEO handoff has no unresolved blocker;
5. machine readiness remains valid;
6. real browser QA passes at `1440 / 1024 / 768 / 390` for visual layout, responsive behavior, accessibility, SEO/GEO rendered output and performance;
7. mobile menu close behavior and compact-layout fixes pass on the real site;
8. the Corporate Home receives explicit premium/WOW product-quality approval;
9. the sandbox is explicitly accepted before any production-entry decision;
10. later production verification completes without a rollback trigger.

Until production acceptance exists, `release/stable-release-decision.json` remains `decision=no-go`, real-site acceptance remains `pending`, its reference remains `null`, and blocker `real-site-production-acceptance-pending` stays present.

## Product boundary

Migration Bridge `0.8.60` remains frozen for this field candidate. The standalone Core wrapper remains `deprecated-retained-nondistributed` and is not included in the self-contained Theme release ZIP.

No production evidence is fabricated or inferred from repository CI.

## Automated decision contract

`scripts/ci/validate-stable-release-decision.py` enforces decision/version consistency, candidate identity, pending real-site evidence for NO-GO, changelog state and the Core wrapper distribution boundary.

`EMMAKE Field Pilot Pack CI` is the byte-level authority for Theme, Migration Bridge and outer field-pack hashes against `release/emmake-phase10e-candidate.json`.

## Promotion from no-go to go

Only after real sandbox and production acceptance: record bounded evidence, clear blockers, change real-site acceptance to `accepted`, move the release channel to `stable`, release the changelog entry, rerun all required gates and publish only if they remain green.
