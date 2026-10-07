#!/usr/bin/env python3
"""Validate the Theme + Manager + Gutenberg product architecture contracts."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

FILES = {
    "portfolio": ROOT / "docs/PRODUCT_PORTFOLIO.md",
    "manager": ROOT / "docs/SEO_GEO_MANAGER.md",
    "publishing": ROOT / "docs/CONTENT_PUBLISHING.md",
    "theme_manager": ROOT / "docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md",
    "current_roadmap": ROOT / "docs/CURRENT_EXECUTION_ROADMAP.md",
    "sandbox": ROOT / "docs/PORTABLE_SANDBOX.md",
    "architecture": ROOT / "docs/ARCHITECTURE.md",
    "roadmap": ROOT / "docs/ROADMAP.md",
    "stable": ROOT / "docs/STABLE_RELEASE_DECISION.md",
    "pilot": ROOT / "docs/REAL_SITE_PILOT.md",
}


def require(source: str, values: tuple[str, ...], label: str) -> None:
    for value in values:
        if value not in source:
            raise SystemExit(f"{label} is missing required product contract text: {value}")


def main() -> int:
    for label, path in FILES.items():
        if not path.is_file():
            raise SystemExit(f"Required product portfolio document is missing: {path.relative_to(ROOT)}")
        if path.stat().st_size == 0:
            raise SystemExit(f"Product portfolio document is empty: {path.relative_to(ROOT)}")

    portfolio = FILES["portfolio"].read_text(encoding="utf-8")
    manager = FILES["manager"].read_text(encoding="utf-8")
    publishing = FILES["publishing"].read_text(encoding="utf-8")
    theme_manager = FILES["theme_manager"].read_text(encoding="utf-8")
    current_roadmap = FILES["current_roadmap"].read_text(encoding="utf-8")
    sandbox = FILES["sandbox"].read_text(encoding="utf-8")
    architecture = FILES["architecture"].read_text(encoding="utf-8")
    roadmap = FILES["roadmap"].read_text(encoding="utf-8")
    stable = FILES["stable"].read_text(encoding="utf-8")
    pilot = FILES["pilot"].read_text(encoding="utf-8")

    ownership_rule = (
        "Gutenberg provides editorial autonomy. SEO/GEO Manager provides automation and growth. "
        "SEO/GEO Theme provides frontend rendering, design, semantic HTML and performance."
    )

    require(
        portfolio,
        (
            "# Product portfolio",
            "Product A — SEO/GEO Theme",
            "Product B — SEO/GEO Manager",
            "The Theme must remain fully usable when SEO/GEO Manager is not installed.",
            "works without requiring the SEO/GEO Theme",
            "The Manager is not the deprecated standalone Core wrapper.",
            "Product versions and release channels are independent.",
            "Landings/blogs can be previewed, published idempotently and rolled back.",
            "complete blog posts can be created automatically by Manager",
            "Manager-created blog posts remain editable in Gutenberg",
            "Corporate v5 — Theme-owned frontend",
        ),
        "PRODUCT_PORTFOLIO.md",
    )

    require(
        manager,
        (
            "# SEO/GEO Manager",
            "must not require GitHub",
            "### 1. Site Intelligence",
            "### 2. Output Authority Resolver",
            "### 3. Content Publishing Core",
            "### 4. Landing Engine",
            "### 5. Blog Engine",
            "Automated blog publication is a **first-class Manager feature**.",
            "Manager-created blog article becomes a **normal WordPress post**",
            "Growth / Opportunity Engine",
            "Migration mode is optional.",
            "Manager receives its own installable ZIP and release lifecycle.",
            "Corporate v5 — Theme-owned frontend",
        ),
        "SEO_GEO_MANAGER.md",
    )

    require(
        publishing,
        (
            "# Content publishing contract",
            "Draft-first is the baseline.",
            "idempotency key",
            "## Change set and rollback",
            "The publication engine never assumes it owns SEO output.",
            "no mass city/service token swapping",
            "Theme-owned landing renderer contract",
            "Automated blog publication is a **first-class SEO/GEO Manager capability**.",
            "Manager-created articles become **normal WordPress posts**.",
            "remain editable by authorized client users in Gutenberg",
            "retrying an accepted request cannot create a second page/post",
            "clean uninstall/deactivation behavior that does not delete client content",
        ),
        "CONTENT_PUBLISHING.md",
    )

    require(
        theme_manager,
        (
            "# Theme-owned frontend + SEO/GEO Manager content architecture",
            ownership_rule,
            "## Strategic-page rendering",
            "## Gutenberg boundary",
            "## SEO/GEO Manager: automated landing creation",
            "## SEO/GEO Manager: automated blog creation",
            "Manager-created articles must remain normal WordPress posts",
            "Corporate v5 — Theme-owned frontend",
            "Do not continue visual rollout of SaaS, Local Pro, Publisher or Ecommerce",
        ),
        "THEME_MANAGER_CONTENT_ARCHITECTURE.md",
    )

    require(
        current_roadmap,
        (
            "# Current execution roadmap",
            "## Track A — Corporate v5 Theme-owned frontend",
            ownership_rule,
            "Track C — SEO/GEO Manager Landing Engine",
            "Track D — SEO/GEO Manager Automated Blog Engine",
            "Manager-created articles become normal WordPress posts.",
            "remain editable by authorized users in Gutenberg",
            "Do **not** begin A4 renderer generalization until the exact A3 field candidate passes `/nuevaweb/` visual/browser acceptance.",
        ),
        "CURRENT_EXECUTION_ROADMAP.md",
    )

    require(
        sandbox,
        (
            "# Portable sandbox strategy",
            "Staging is a capability of our migration workflow",
            "### Mode A — Client staging exists",
            "### Mode B — No staging, but WordPress/hosting access exists",
            "### Mode C — Limited WordPress access",
            "retain production as data authority",
            "Stale sandbox databases",
            "SEO/GEO Manager coordinates portable-sandbox/migration operations.",
        ),
        "PORTABLE_SANDBOX.md",
    )

    require(
        architecture,
        (
            "two-product portfolio",
            "## SEO/GEO Manager ownership",
            "Manager must not become a second uncontrolled public-output owner.",
            "publishing must be draft-first by default, idempotent, authority-aware and rollback-capable",
            "Gutenberg is not the layout authority for strategic SEO/GEO surfaces",
            "Manager-created blog posts are normal WordPress posts",
            "Corporate v5 — Theme-owned frontend",
        ),
        "ARCHITECTURE.md",
    )

    # The historical/global roadmap remains required as accepted phase evidence.
    # Current execution direction is validated separately by CURRENT_EXECUTION_ROADMAP.md.
    require(
        roadmap,
        (
            "## Phase 11 — SEO/GEO Manager product",
            "## Phase 12 — Content operations and agency scale",
            "Manager is not the deprecated Core wrapper.",
        ),
        "ROADMAP.md",
    )

    require(
        stable,
        (
            "## Product boundary",
            "SEO/GEO Manager is a separate plugin product.",
            "not a mandatory dependency or blocker for Theme `0.1.1` stable runtime",
            "Migration Bridge is the accepted migration/reset implementation for this Theme pilot.",
            "deprecated-retained-nondistributed",
            "Corporate v4.3 passed repository-side technical gates",
            "failed the human visual/architectural acceptance gate",
            "Corporate v5 A3",
        ),
        "STABLE_RELEASE_DECISION.md",
    )

    require(
        pilot,
        (
            "## Stable promotion boundary",
            "Theme stable promotion remains **NO-GO**.",
            "Corporate v5 — Theme-owned frontend",
            "Corporate v4.3 remains historical evidence: technically valid but **visual/architectural NO-GO**.",
            "Do **not** rerun Reset, regenerate the Home, rehydrate content",
        ),
        "REAL_SITE_PILOT.md",
    )

    print(
        "Product portfolio contract OK: WordPress CMS ownership, Gutenberg editorial autonomy, "
        "Theme-owned strategic rendering, automated Manager landing/blog operations, single SEO/GEO authority, "
        "rollback-safe publishing and Corporate v5 A3 execution direction are documented."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
