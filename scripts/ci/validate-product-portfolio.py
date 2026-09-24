#!/usr/bin/env python3
"""Validate the two-product Theme + Manager portfolio contracts."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

FILES = {
    "portfolio": ROOT / "docs/PRODUCT_PORTFOLIO.md",
    "manager": ROOT / "docs/SEO_GEO_MANAGER.md",
    "publishing": ROOT / "docs/CONTENT_PUBLISHING.md",
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
    sandbox = FILES["sandbox"].read_text(encoding="utf-8")
    architecture = FILES["architecture"].read_text(encoding="utf-8")
    roadmap = FILES["roadmap"].read_text(encoding="utf-8")
    stable = FILES["stable"].read_text(encoding="utf-8")
    pilot = FILES["pilot"].read_text(encoding="utf-8")

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
            "Clients without staging have a safe portable-sandbox path.",
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
            "### 6. Migration module",
            "### 7. Portable Sandbox coordinator",
            "Migration mode is optional.",
            "Manager receives its own installable ZIP and release lifecycle.",
        ),
        "SEO_GEO_MANAGER.md",
    )

    require(
        publishing,
        (
            "# Content publishing contract",
            "Draft-first is the default.",
            "idempotency key",
            "## Change set and rollback",
            "The publication engine never assumes it owns SEO output.",
            "no mass city/service token swapping",
            "native WordPress blocks first",
            "retrying an accepted request cannot create a second page/post",
            "clean uninstall/deactivation behavior that does not delete client content",
        ),
        "CONTENT_PUBLISHING.md",
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
            "stale sandbox databases",
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
        ),
        "ARCHITECTURE.md",
    )

    require(
        roadmap,
        (
            "## Phase 11 — SEO/GEO Manager product",
            "### Microphase 11C — Secure content publication core",
            "### Microphase 11F — Migration module absorption",
            "### Microphase 11G — Portable Sandbox coordinator",
            "### Microphase 11I — Manager real-site acceptance and first stable release",
            "## Phase 12 — Content operations and agency scale",
            "Phase 10E remains the current execution pointer",
            "Manager is not the deprecated Core wrapper.",
        ),
        "ROADMAP.md",
    )

    require(
        stable,
        (
            "## Product-portfolio boundary",
            "not** a new dependency or blocker for Theme 0.1.0",
            "deprecated standalone Core wrapper is not renamed or promoted into Manager",
        ),
        "STABLE_RELEASE_DECISION.md",
    )

    require(
        pilot,
        (
            "## Two-product scope",
            "Theme 0.1.0 stable gate does **not** wait for the future SEO/GEO Manager",
            "Theme + Manager",
        ),
        "REAL_SITE_PILOT.md",
    )

    print(
        "Product portfolio contract OK: independent Theme/Manager products, single-output authority, "
        "draft-first idempotent publishing, migration-module transition and portable sandbox roadmap are documented."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
