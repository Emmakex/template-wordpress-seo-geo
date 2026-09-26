#!/usr/bin/env python3
"""Validate the bounded Phase 10E.2A.6 Emmake real-clone acceptance record."""

from __future__ import annotations

import json
import re
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
RECORD_FILE = ROOT / "release/emmake-real-clone-acceptance.json"
STABLE_FILE = ROOT / "release/stable-release-decision.json"

EXPECTED_BRIDGE = {
    "version": "0.8.36",
    "main_commit": "b78f38016ac0848cd37b75fb608262b289d32ceb",
    "zip_sha256": "1da3ce1fbadf28c379c8f4cb2272b17e223c36ea348d892aa5fbb110837fc792",
}
EXPECTED_SOURCE = {
    "baseline_ready": True,
    "dependency_review_complete": True,
    "reviewed_unknown": 13,
    "unreviewed_unknown": 0,
    "bounded_handoff_sha256": "48b87fed7c8b09be9778f0e62045c0eb8bcf23b35186883ee9f0629a19431d4b",
}
SHA256_RE = re.compile(r"^[a-f0-9]{64}$")


def fail(message: str) -> None:
    raise SystemExit(message)


def exact_keys(value: Any, expected: set[str], label: str) -> dict[str, Any]:
    if not isinstance(value, dict) or set(value) != expected:
        fail(f"{label} has an unexpected schema")
    return value


def nonempty_string(value: Any, label: str, minimum: int = 1) -> str:
    if not isinstance(value, str) or len(value.strip()) < minimum:
        fail(f"{label} must be a non-empty string")
    return value.strip()


def sha256(value: Any, label: str) -> str:
    text = nonempty_string(value, label)
    if not SHA256_RE.fullmatch(text):
        fail(f"{label} must be a lowercase SHA-256")
    return text


def positive_int(value: Any, label: str) -> int:
    if isinstance(value, bool) or not isinstance(value, int) or value <= 0:
        fail(f"{label} must be a positive integer")
    return value


def main() -> int:
    for path in (RECORD_FILE, STABLE_FILE):
        if not path.is_file():
            fail(f"Required Phase 10E.2A.6 path is missing: {path.relative_to(ROOT)}")

    record = json.loads(RECORD_FILE.read_text(encoding="utf-8"))
    stable = json.loads(STABLE_FILE.read_text(encoding="utf-8"))

    exact_keys(
        record,
        {
            "schema_version",
            "mode",
            "site_id",
            "source_origin",
            "target_origin",
            "migration_bridge",
            "source_evidence",
            "backups",
            "clone",
            "sandbox_migration_lab",
            "public_leak_checks",
            "blockers",
            "decision",
            "evidence_captured_at",
            "operator",
            "notes",
        },
        "Real-clone acceptance record",
    )
    if record["schema_version"] != 1:
        fail("Real-clone acceptance schema_version must be 1")
    if record["mode"] != "seo-geo-emmake-real-clone-acceptance":
        fail("Real-clone acceptance mode is invalid")
    if record["site_id"] != "emmake-com":
        fail("Real-clone acceptance site_id must remain emmake-com")
    if record["source_origin"] != "https://emmake.com/":
        fail("Real-clone acceptance source_origin is invalid")
    if record["target_origin"] != "https://emmake.com/nuevaweb/":
        fail("Real-clone acceptance target_origin is invalid")

    bridge = exact_keys(
        record["migration_bridge"],
        {"version", "main_commit", "zip_sha256"},
        "Migration Bridge identity",
    )
    if bridge != EXPECTED_BRIDGE:
        fail("Real-clone acceptance must use the exact accepted Migration Bridge 0.8.36 artifact")

    source = exact_keys(
        record["source_evidence"],
        {
            "baseline_ready",
            "dependency_review_complete",
            "reviewed_unknown",
            "unreviewed_unknown",
            "bounded_handoff_sha256",
        },
        "Source evidence",
    )
    if source != EXPECTED_SOURCE:
        fail("Source baseline/dependency/review evidence identity has drifted")

    backups = exact_keys(
        record["backups"],
        {"database_reference", "uploads_reference", "captured_at"},
        "Backup evidence",
    )
    clone = exact_keys(
        record["clone"],
        {
            "job_id",
            "final_handoff_report_sha256",
            "database_independent",
            "files_independent",
            "source_untouched",
            "blog_public_zero",
            "noindex_ready",
            "storage_isolated",
            "outbound_safe",
            "backups_ready",
            "rollback_available",
            "target_table_prefix_sha256",
            "target_runtime_sha256",
            "target_active_file_fingerprint",
            "table_count",
            "row_count",
            "file_count",
            "file_bytes",
        },
        "Clone evidence",
    )
    lab = exact_keys(
        record["sandbox_migration_lab"],
        {"ready", "reference"},
        "Sandbox Migration Lab evidence",
    )
    leaks = exact_keys(
        record["public_leak_checks"],
        {
            "sandbox_canonical_leak_absent",
            "sandbox_hreflang_leak_absent",
            "sandbox_sitemap_leak_absent",
        },
        "Public leak checks",
    )

    if not isinstance(record["blockers"], list) or not all(
        isinstance(item, str) and item for item in record["blockers"]
    ):
        fail("Real-clone blockers must be a list of non-empty machine codes")
    if not isinstance(record["notes"], list) or not all(isinstance(item, str) for item in record["notes"]):
        fail("Real-clone notes must be a list of strings")

    decision = record["decision"]
    if decision not in {"pending", "accepted"}:
        fail("Real-clone decision must be pending or accepted")

    stable_real_site = stable.get("real_site_acceptance")
    if not isinstance(stable_real_site, dict):
        fail("Stable-release real_site_acceptance is invalid")

    if decision == "pending":
        if "real-clone-execution-pending" not in record["blockers"]:
            fail("Pending real-clone acceptance must retain real-clone-execution-pending")
        if stable.get("decision") != "no-go":
            fail("Pending real-clone acceptance requires stable decision=no-go")
        if stable_real_site.get("status") != "pending" or stable_real_site.get("reference") is not None:
            fail("Pending real-clone acceptance cannot fabricate a stable real-site reference")
        print(
            "Emmake real-clone acceptance gate OK: decision=pending, "
            "accepted Engine=0.8.36, live clone execution still required."
        )
        return 0

    nonempty_string(backups["database_reference"], "database backup reference", 8)
    nonempty_string(backups["uploads_reference"], "uploads backup reference", 8)
    nonempty_string(backups["captured_at"], "backup captured_at", 10)

    nonempty_string(clone["job_id"], "clone job_id", 8)
    sha256(clone["final_handoff_report_sha256"], "final handoff report SHA-256")
    sha256(clone["target_table_prefix_sha256"], "target table-prefix SHA-256")
    sha256(clone["target_runtime_sha256"], "target runtime SHA-256")
    sha256(clone["target_active_file_fingerprint"], "target active-file fingerprint")

    for field in (
        "database_independent",
        "files_independent",
        "source_untouched",
        "blog_public_zero",
        "noindex_ready",
        "storage_isolated",
        "outbound_safe",
        "backups_ready",
        "rollback_available",
    ):
        if clone[field] is not True:
            fail(f"Accepted real clone requires clone.{field}=true")

    for field in ("table_count", "row_count", "file_count", "file_bytes"):
        positive_int(clone[field], f"clone.{field}")

    if lab["ready"] is not True:
        fail("Accepted real clone requires Sandbox Migration Lab ready=true")
    nonempty_string(lab["reference"], "Sandbox Migration Lab reference", 8)

    for field in (
        "sandbox_canonical_leak_absent",
        "sandbox_hreflang_leak_absent",
        "sandbox_sitemap_leak_absent",
    ):
        if leaks[field] is not True:
            fail(f"Accepted real clone requires public_leak_checks.{field}=true")

    if record["blockers"] != []:
        fail("Accepted real clone cannot retain blockers")
    nonempty_string(record["evidence_captured_at"], "evidence_captured_at", 10)
    nonempty_string(record["operator"], "operator", 2)

    stable_state = stable.get("decision")
    if stable_state == "go":
        if stable_real_site.get("status") != "accepted":
            fail("Stable go requires accepted real-site status")
        if stable_real_site.get("reference") != "release/emmake-real-clone-acceptance.json":
            fail("Stable go must reference the bounded Emmake real-clone acceptance record")
    elif stable_state == "no-go":
        if stable_real_site.get("status") != "pending" or stable_real_site.get("reference") is not None:
            fail("Before the stable decision transition, real-site reference must remain pending/null")
    else:
        fail("Stable-release decision must be no-go or go")

    print(
        "Emmake real-clone acceptance gate OK: decision=accepted, "
        "isolated clone + Sandbox Migration Lab evidence is complete."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
