#!/usr/bin/env python3
"""Validate the Phase 10E stable-release decision gate."""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DECISION_FILE = ROOT / "release/stable-release-decision.json"
VERSION_FILE = ROOT / "release/version.json"
CHANGELOG_FILE = ROOT / "CHANGELOG.md"
DECISION_DOC = ROOT / "docs/STABLE_RELEASE_DECISION.md"
CORE_WRAPPER = ROOT / "packages/seo-geo-core/seo-geo-core.php"
CORE_README = ROOT / "packages/seo-geo-core/README.md"
ARCHITECTURE = ROOT / "docs/ARCHITECTURE.md"
BUILD_SCRIPT = ROOT / "scripts/build-theme-package.sh"


def fail(message: str) -> None:
    raise SystemExit(message)


def main() -> int:
    for path in (
        DECISION_FILE,
        VERSION_FILE,
        CHANGELOG_FILE,
        DECISION_DOC,
        CORE_README,
        ARCHITECTURE,
        BUILD_SCRIPT,
    ):
        if not path.is_file():
            fail(f"Required Phase 10E path is missing: {path.relative_to(ROOT)}")

    decision = json.loads(DECISION_FILE.read_text(encoding="utf-8"))
    version = json.loads(VERSION_FILE.read_text(encoding="utf-8"))
    changelog = CHANGELOG_FILE.read_text(encoding="utf-8")
    decision_doc = DECISION_DOC.read_text(encoding="utf-8")
    core_readme = CORE_README.read_text(encoding="utf-8")
    architecture = ARCHITECTURE.read_text(encoding="utf-8")
    build_script = BUILD_SCRIPT.read_text(encoding="utf-8")

    expected_keys = {
        "schema_version",
        "mode",
        "target_version",
        "decision",
        "wrapper_disposition",
        "real_site_acceptance",
        "blockers",
    }
    if set(decision) != expected_keys:
        fail("Stable-release decision has an unexpected top-level schema")
    if decision["schema_version"] != 1 or decision["mode"] != "seo-geo-stable-release-decision":
        fail("Stable-release decision identity is invalid")
    if decision["target_version"] != version["version"]:
        fail("Stable-release target version must match release/version.json")

    real_site = decision["real_site_acceptance"]
    if set(real_site) != {"required", "status", "reference"}:
        fail("Real-site acceptance has an unexpected schema")
    if real_site["required"] is not True:
        fail("Real-site production acceptance must remain required")

    wrapper = decision["wrapper_disposition"]
    if wrapper not in {"deprecated-retained-nondistributed", "removed"}:
        fail("Unsupported Core wrapper disposition")

    if wrapper == "deprecated-retained-nondistributed":
        if not CORE_WRAPPER.is_file():
            fail("Wrapper disposition says retained but seo-geo-core.php is missing")
        if "deprecated for installation" not in core_readme.lower():
            fail("Core README must document wrapper deprecation for installation")
        if "deprecated-retained-nondistributed" not in architecture:
            fail("Architecture must record the accepted wrapper disposition")
        if "seo-geo-core.php" in build_script:
            fail("Standalone Core wrapper must not enter the self-contained theme build")
    elif CORE_WRAPPER.exists():
        fail("Wrapper disposition says removed but seo-geo-core.php still exists")

    state = decision["decision"]
    if state not in {"no-go", "go"}:
        fail("Stable-release decision must be no-go or go")

    target = decision["target_version"]
    unreleased_heading = f"## [Unreleased] — target {target}"
    released_heading = f"## [{target}]"

    if state == "no-go":
        if version["release_channel"] != "prestable":
            fail("A no-go stable decision must keep release_channel=prestable")
        if real_site["status"] != "pending" or real_site["reference"] is not None:
            fail("A no-go decision must keep real-site acceptance pending with no fabricated reference")
        if not decision["blockers"]:
            fail("A no-go decision must retain at least one explicit blocker")
        if "real-site-production-acceptance-pending" not in decision["blockers"]:
            fail("Current no-go must identify the real-site acceptance blocker")
        if unreleased_heading not in changelog:
            fail("A no-go decision must keep the target changelog entry Unreleased")
        if "**NO-GO for stable release.**" not in decision_doc:
            fail("Stable-release document must state the no-go decision")
    else:
        if version["release_channel"] != "stable":
            fail("A go decision requires release_channel=stable")
        if real_site["status"] != "accepted":
            fail("A go decision requires accepted real-site production evidence")
        if not isinstance(real_site["reference"], str) or len(real_site["reference"].strip()) < 8:
            fail("A go decision requires a bounded real-site acceptance reference")
        if decision["blockers"] != []:
            fail("A go decision cannot retain blockers")
        if released_heading not in changelog:
            fail("A go decision requires a released changelog heading for the target version")

    print(
        "Phase 10E stable-release decision OK: "
        f"{target} decision={state}, channel={version['release_channel']}, "
        f"wrapper={wrapper}, real-site={real_site['status']}."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
