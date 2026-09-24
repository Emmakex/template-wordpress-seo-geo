#!/usr/bin/env python3
"""Validate Phase 10C client installation/cloning documentation contracts."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
INSTALL = ROOT / "docs/CLIENT_INSTALLATION.md"
CLONING = ROOT / "docs/CLIENT_CLONING.md"
README = ROOT / "README.md"
ROADMAP = ROOT / "docs/ROADMAP.md"


def require(source: str, required: tuple[str, ...], label: str) -> None:
    for value in required:
        if value not in source:
            raise SystemExit(f"{label} is missing required contract text: {value}")


def forbid(source: str, forbidden: tuple[str, ...], label: str) -> None:
    lowered = source.lower()
    for value in forbidden:
        if value.lower() in lowered:
            raise SystemExit(f"{label} contains forbidden unsafe guidance: {value}")


def main() -> int:
    for path in (INSTALL, CLONING, README, ROADMAP):
        if not path.is_file():
            raise SystemExit(f"Required Phase 10C document is missing: {path.relative_to(ROOT)}")

    install = INSTALL.read_text(encoding="utf-8")
    cloning = CLONING.read_text(encoding="utf-8")
    readme = README.read_text(encoding="utf-8")
    roadmap = ROADMAP.read_text(encoding="utf-8")

    require(
        install,
        (
            "# Client installation and migration",
            "## Distribution invariant",
            "## Clean WordPress installation",
            "## Existing production site",
            "## Sandbox gate",
            "## Cutover prerequisites",
            "## Post-install SEO/GEO verification",
            "zero active plugins",
            "No SEO/GEO plugin is required for baseline setup or runtime.",
            "database and uploads backups",
            "SEO parity",
            "Production remains on the accepted legacy stack",
            "SEO_GEO_MIGRATION_SANDBOX=true",
            "release version and ZIP SHA-256",
            "docs/MIGRATION_BRIDGE.md",
        ),
        "CLIENT_INSTALLATION.md",
    )

    require(
        cloning,
        (
            "# Client cloning, customization and safe updates",
            "## Repository clone contract",
            "## Customization boundaries",
            "## Client-specific configuration",
            "## Safe update path",
            "## Merge conflict policy",
            "## Client update acceptance",
            "packages/seo-geo-core/src/",
            "packages/seo-geo-theme/templates/",
            "release/version.json",
            "three-way merge",
            "Never blindly overwrite a customized client theme directory",
            "Never resolve a conflict by deleting an acceptance test solely to make CI green.",
            "immediately previous accepted client ZIP",
        ),
        "CLIENT_CLONING.md",
    )

    require(
        readme,
        (
            "docs/CLIENT_INSTALLATION.md",
            "docs/CLIENT_CLONING.md",
        ),
        "README.md",
    )

    require(
        roadmap,
        (
            "### Microphase 10C — Client installation and cloning documentation",
            "10C is closed.",
        ),
        "ROADMAP.md",
    )

    forbid(
        install + "\n" + cloning,
        (
            "disable all ci",
            "skip parity",
            "delete the tests",
            "activate directly in production without",
        ),
        "Phase 10C docs",
    )

    print(
        "Phase 10C client delivery docs OK: clean/existing-site install, sandbox/parity, "
        "zero-plugin baseline, client customization boundaries and safe upstream update path are documented."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
