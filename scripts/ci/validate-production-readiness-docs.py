#!/usr/bin/env python3
"""Validate Phase 10D production verification and recovery documentation."""

from __future__ import annotations

import json
from pathlib import Path
import re
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[2]
PRODUCTION = ROOT / "docs/PRODUCTION_VERIFICATION.md"
ROLLBACK = ROOT / "docs/ROLLBACK_RECOVERY.md"
SANDBOX = ROOT / "docs/SANDBOX_TO_PRODUCTION.md"
RECORD = ROOT / "docs/templates/PRODUCTION_ACCEPTANCE_RECORD.example.json"


def require(source: str, required: tuple[str, ...], label: str) -> None:
    for value in required:
        if value not in source:
            raise SystemExit(f"{label} is missing required contract text: {value}")


def main() -> int:
    for path in (PRODUCTION, ROLLBACK, SANDBOX, RECORD):
        if not path.is_file():
            raise SystemExit(f"Required Phase 10D path is missing: {path.relative_to(ROOT)}")

    production = PRODUCTION.read_text(encoding="utf-8")
    rollback = ROLLBACK.read_text(encoding="utf-8")
    sandbox = SANDBOX.read_text(encoding="utf-8")

    require(
        production,
        (
            "# Production verification checklist",
            "## Deployment identity",
            "## Immediate production checks",
            "## SEO/GEO checks",
            "## Abort criteria",
            "release ZIP SHA-256",
            "canonical URL",
            "XML sitemap",
            "hreflang",
            "Schema",
            "rollback-required",
            "recovery-required",
        ),
        "PRODUCTION_VERIFICATION.md",
    )
    require(
        rollback,
        (
            "# Rollback and recovery",
            "## Decision tree",
            "## Runtime rollback",
            "## Data recovery",
            "## Dynamic-site caution",
            "## Post-rollback verification",
            "Do not restore a stale database over new orders, form submissions or customer changes",
            "immediately previous accepted client ZIP",
            "rolled-back-review-required",
        ),
        "ROLLBACK_RECOVERY.md",
    )
    require(
        sandbox,
        (
            "# Sandbox-to-production acceptance",
            "## Sandbox exit criteria",
            "## Production entry criteria",
            "## Environment separation",
            "## Go/no-go decision",
            "SEO_GEO_MIGRATION_SANDBOX=true",
            "Sandbox success does not mutate production automatically.",
            "The operator must not convert a failed mandatory check into a warning",
            "Phase 10E",
        ),
        "SANDBOX_TO_PRODUCTION.md",
    )

    record = json.loads(RECORD.read_text(encoding="utf-8"))
    expected_top = {
        "schema_version",
        "mode",
        "site_id",
        "production_origin",
        "release",
        "sandbox",
        "backups",
        "rollback",
        "production_checks",
        "decision",
        "operator",
        "notes",
    }
    if set(record) != expected_top:
        raise SystemExit("Production acceptance example has an unexpected top-level schema")
    if record["schema_version"] != 1 or record["mode"] != "seo-geo-production-acceptance":
        raise SystemExit("Production acceptance example identity is invalid")

    production_url = urlparse(record["production_origin"])
    sandbox_url = urlparse(record["sandbox"]["origin"])
    if production_url.scheme not in {"http", "https"} or not production_url.netloc:
        raise SystemExit("Production origin must be an absolute HTTP(S) URL")
    if sandbox_url.scheme not in {"http", "https"} or not sandbox_url.netloc:
        raise SystemExit("Sandbox origin must be an absolute HTTP(S) URL")
    production_origin = (production_url.scheme, production_url.netloc.lower())
    sandbox_origin = (sandbox_url.scheme, sandbox_url.netloc.lower())
    if production_origin == sandbox_origin:
        raise SystemExit("Sandbox and production origins must be distinct")
    if not re.fullmatch(r"[0-9a-f]{64}", record["release"]["zip_sha256"]):
        raise SystemExit("Production acceptance release SHA-256 must be 64 lowercase hex chars")
    if not re.fullmatch(r"[0-9a-f]{40}", record["release"]["upstream_commit"]):
        raise SystemExit("Production acceptance upstream commit must be 40 lowercase hex chars")
    if not re.fullmatch(r"[0-9a-f]{64}", record["rollback"]["previous_zip_sha256"]):
        raise SystemExit("Rollback ZIP SHA-256 must be 64 lowercase hex chars")
    if not record["backups"]["database_reference"]:
        raise SystemExit("Production acceptance must include a database recovery reference")
    if not record["rollback"]["artifact_reference"]:
        raise SystemExit("Production acceptance must include a rollback artifact reference")
    expected_checks = {
        "runtime",
        "seo_geo",
        "functionality",
        "accessibility",
        "performance",
        "logs",
    }
    if set(record["production_checks"]) != expected_checks:
        raise SystemExit("Production acceptance checks do not match the Phase 10D schema")
    if record["sandbox"]["migration_marker"] is not True:
        raise SystemExit("Sandbox example must retain the migration marker")
    if record["sandbox"]["search_visibility"] is not False:
        raise SystemExit("Sandbox example must remain non-indexable")
    if record["decision"] != "pending":
        raise SystemExit("Example production decision must start pending")
    if set(record["production_checks"].values()) != {False}:
        raise SystemExit("Example production checks must start unaccepted")

    print(
        "Phase 10D production readiness docs OK: sandbox exit, production verification, "
        "rollback/data recovery and bounded acceptance-record contracts are present."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
