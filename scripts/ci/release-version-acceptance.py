#!/usr/bin/env python3
"""Validate the Phase 10B release version and changelog contract."""

from __future__ import annotations

import json
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
VERSION_FILE = ROOT / "release/version.json"
STYLE_FILE = ROOT / "packages/seo-geo-theme/style.css"
CHANGELOG_FILE = ROOT / "CHANGELOG.md"
SEMVER = re.compile(
    r"^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)"
    r"(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$"
)


def fail(message: str) -> None:
    raise SystemExit(message)


def style_header_value(source: str, key: str) -> str:
    matches = re.findall(rf"^\s*{re.escape(key)}:\s*(.+?)\s*$", source, re.MULTILINE)
    if len(matches) != 1:
        fail(f"Expected exactly one {key} header in style.css; found {len(matches)}")
    return matches[0]


def main() -> int:
    if not VERSION_FILE.is_file():
        fail("release/version.json is missing")
    if not STYLE_FILE.is_file():
        fail("packages/seo-geo-theme/style.css is missing")
    if not CHANGELOG_FILE.is_file():
        fail("CHANGELOG.md is missing")

    metadata = json.loads(VERSION_FILE.read_text(encoding="utf-8"))
    expected_keys = {"schema_version", "theme_slug", "version", "release_channel"}
    if set(metadata) != expected_keys:
        fail(f"release/version.json keys must be exactly {sorted(expected_keys)}")
    if metadata["schema_version"] != 1:
        fail("release/version.json schema_version must be 1")
    if metadata["theme_slug"] != "seo-geo-theme":
        fail("release/version.json theme_slug must be seo-geo-theme")

    version = metadata["version"]
    if not isinstance(version, str) or not SEMVER.fullmatch(version):
        fail("release/version.json version must be valid SemVer")
    if metadata["release_channel"] not in {"prestable", "stable"}:
        fail("release_channel must be prestable or stable")

    style = STYLE_FILE.read_text(encoding="utf-8")
    style_version = style_header_value(style, "Version")
    if style_version != version:
        fail(f"style.css Version {style_version!r} does not match release version {version!r}")

    changelog = CHANGELOG_FILE.read_text(encoding="utf-8")
    target_heading = f"## [Unreleased] — target {version}"
    if changelog.count(target_heading) != 1:
        fail(f"CHANGELOG.md must contain exactly one heading: {target_heading}")
    if "`release/version.json` is the authoritative target version source." not in changelog:
        fail("CHANGELOG.md must document release/version.json authority")
    if "The WordPress `style.css` theme header must match that version exactly." not in changelog:
        fail("CHANGELOG.md must document style.css synchronization")

    print(
        "Release version contract OK: "
        f"{metadata['theme_slug']} {version} ({metadata['release_channel']}); "
        "style.css and CHANGELOG.md are synchronized."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
