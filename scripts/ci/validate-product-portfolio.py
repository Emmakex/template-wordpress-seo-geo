#!/usr/bin/env python3
"""Validate the three-product WordPress SEO/GEO operating architecture."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

FILES = {
    "operating_model": ROOT / "docs/THREE_PRODUCT_OPERATING_MODEL.md",
    "documentation_authority": ROOT / "docs/DOCUMENTATION_AUTHORITY.md",
    "current_roadmap": ROOT / "docs/CURRENT_THREE_PRODUCT_ROADMAP.md",
    "vision": ROOT / "docs/PRODUCT_VISION.md",
    "portfolio": ROOT / "docs/PRODUCT_PORTFOLIO.md",
    "manager": ROOT / "docs/SEO_GEO_MANAGER.md",
    "manager_modes": ROOT / "docs/SEO_GEO_MANAGER_PRODUCT_MODES.md",
    "theme_manager": ROOT / "docs/THEME_MANAGER_CONTENT_ARCHITECTURE.md",
    "reset_rebuild": ROOT / "docs/RESET_REBUILD_CONTRACT.md",
    "publishing": ROOT / "docs/CONTENT_PUBLISHING.md",
    "sandbox": ROOT / "docs/PORTABLE_SANDBOX.md",
    "historical_roadmap": ROOT / "docs/ROADMAP.md",
    "stable": ROOT / "docs/STABLE_RELEASE_DECISION.md",
    "pilot": ROOT / "docs/REAL_SITE_PILOT.md",
}


def require(source: str, values: tuple[str, ...], label: str) -> None:
    for value in values:
        if value not in source:
            raise SystemExit(f"{label} is missing required product contract text: {value}")


def forbid(source: str, values: tuple[str, ...], label: str) -> None:
    for value in values:
        if value in source:
            raise SystemExit(f"{label} contains superseded product contract text: {value}")


def read(label: str) -> str:
    path = FILES[label]
    if not path.is_file():
        raise SystemExit(f"Required product portfolio document is missing: {path.relative_to(ROOT)}")
    if path.stat().st_size == 0:
        raise SystemExit(f"Product portfolio document is empty: {path.relative_to(ROOT)}")
    return path.read_text(encoding="utf-8")


def main() -> int:
    docs = {label: read(label) for label in FILES}

    north_star = (
        "Migration Bridge brings the asset. Theme builds the experience. "
        "Manager gives us safe control. We provide the intelligence."
    )

    require(
        docs["operating_model"],
        (
            "# Three-product operating model",
            "three independently sellable WordPress products",
            "Product 1 — SEO/GEO Migration Bridge",
            "Product 2 — SEO/GEO Theme",
            "Product 3 — SEO/GEO Manager",
            "External orchestration — the brain",
            "Manager is not the strategic brain",
            "Inspect / Site Intelligence",
            "Preview",
            "Execute",
            "Publish / Schedule",
            "Verify",
            "History / Rollback",
            north_star,
        ),
        "THREE_PRODUCT_OPERATING_MODEL.md",
    )

    require(
        docs["documentation_authority"],
        (
            "# Documentation authority",
            "three independently sellable products",
            '"Two-product portfolio"',
            '"Migration Bridge package will be retired after Manager absorbs migration"',
            '"Manager owns the strategic Landing/Blog/Optimizer/Growth intelligence"',
            "Level 3 — historical roadmap / phase evidence",
            "do **not** override Level 1 architecture",
        ),
        "DOCUMENTATION_AUTHORITY.md",
    )

    require(
        docs["current_roadmap"],
        (
            "# Current three-product roadmap",
            "Track A — SEO/GEO Migration Bridge",
            "Track B — SEO/GEO Theme",
            "Track C — SEO/GEO Manager",
            "C0 — Product boundary freeze",
            "C1 — Site Intelligence / eyes",
            "C2 — Authentication / capability contract",
            "0.3.34 capability-discovery slice implemented",
            "C3 — Generic change-set engine / hands",
            "C4 — WordPress content operations",
            "C5 — Theme semantic-model operations",
            "External orchestration workflows after Manager primitives",
            "EMMAKE immediate decision",
            "Commercial packaging target",
            "Anti-drift review",
        ),
        "CURRENT_THREE_PRODUCT_ROADMAP.md",
    )

    require(
        docs["vision"],
        (
            "# Product Vision",
            "three independently sellable products",
            "SEO/GEO Migration Bridge",
            "SEO/GEO Theme",
            "SEO/GEO Manager",
            "strategic brain lives outside WordPress",
            "External orchestration — our operating layer",
            "three independent installable products",
        ),
        "PRODUCT_VISION.md",
    )
    forbid(
        docs["vision"],
        ("The portfolio has two independently sellable products",),
        "PRODUCT_VISION.md",
    )

    require(
        docs["portfolio"],
        (
            "# Product portfolio",
            "three-product portfolio",
            "Product A — SEO/GEO Migration Bridge",
            "Product B — SEO/GEO Theme",
            "Product C — SEO/GEO Manager",
            "External orchestration — not an installable WordPress product",
            "not the strategic brain",
            "Migration Bridge remains a separately sellable product",
            "Manager capability roadmap after the architectural correction",
        ),
        "PRODUCT_PORTFOLIO.md",
    )
    forbid(
        docs["portfolio"],
        (
            "The WordPress SEO/GEO project is a **two-product portfolio**",
            "The **bridge package** may eventually be retired",
        ),
        "PRODUCT_PORTFOLIO.md",
    )

    require(
        docs["manager"],
        (
            "# SEO/GEO Manager",
            "WordPress bridge/control agent",
            "not the strategic brain",
            "Site Intelligence / Inspect",
            "Output Authority Resolver",
            "Preview / Change-set Core",
            "Execute / Apply",
            "Publish / Schedule",
            "Verify",
            "Operation history and rollback",
            "External orchestration contract",
            "wp-admin UI role",
            "Migration Bridge remains a separate sellable product",
            "first stable Manager does **not** require an embedded autonomous keyword strategist",
        ),
        "SEO_GEO_MANAGER.md",
    )

    require(
        docs["manager_modes"],
        (
            "# SEO/GEO Manager product modes",
            "External orchestration is the brain",
            "Mode 1 — Build / Finish",
            "Mode 2 — Optimize",
            "Mode 3 — Grow",
            "Manager owns",
            "External orchestration owns",
            "Manager MVP roadmap after this correction",
            "Anti-drift test",
        ),
        "SEO_GEO_MANAGER_PRODUCT_MODES.md",
    )

    require(
        docs["theme_manager"],
        (
            "# Theme-owned frontend + Manager bridge + external orchestration architecture",
            "Manager-as-bridge",
            "external-orchestration-as-brain",
            "content is not layout, and execution is not strategy",
            "Strategic landing workflow",
            "Blog/editorial workflow",
            "Optimization workflow",
            "Growth loop",
            "EMMAKE reference implementation",
        ),
        "THEME_MANAGER_CONTENT_ARCHITECTURE.md",
    )

    require(
        docs["reset_rebuild"],
        (
            "# Reset & Rebuild Contract",
            "Migration Bridge is the dedicated transition/migration product",
            "Manager handoff boundary",
            "Manager does not become the strategic brain",
            "external Build / Finish",
            "external Optimize / Grow through Manager",
        ),
        "RESET_REBUILD_CONTRACT.md",
    )

    require(
        docs["publishing"],
        (
            "# Content publishing contract",
            "Draft-first is the baseline.",
            "idempotency key",
            "## Change set and rollback",
            "The publication engine never assumes it owns SEO output.",
            "no mass city/service token swapping",
            "Manager-created articles become **normal WordPress posts**.",
            "remain editable by authorized client users in Gutenberg",
        ),
        "CONTENT_PUBLISHING.md",
    )

    require(
        docs["sandbox"],
        (
            "# Portable sandbox strategy",
            "Staging is a capability of our migration workflow",
            "retain production as data authority",
            "Stale sandbox databases",
        ),
        "PORTABLE_SANDBOX.md",
    )

    require(
        docs["historical_roadmap"],
        (
            "## Phase 11 — SEO/GEO Manager product",
            "## Phase 12 — Content operations and agency scale",
        ),
        "ROADMAP.md",
    )

    require(
        docs["stable"],
        (
            "## Product boundary",
            "SEO/GEO Manager is a separate plugin product.",
            "Corporate v5 A3",
        ),
        "STABLE_RELEASE_DECISION.md",
    )

    require(
        docs["pilot"],
        (
            "## Stable promotion boundary",
            "Theme stable promotion remains **NO-GO**.",
            "Corporate v5 — Theme-owned frontend",
        ),
        "REAL_SITE_PILOT.md",
    )

    print(
        "Three-product portfolio contract OK: Migration Bridge owns transition, Theme owns rendering, "
        "Manager owns safe WordPress control, external orchestration owns strategy/intelligence, and "
        "historical phase documents cannot override the current architecture."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
