#!/usr/bin/env python3
"""Acceptance for the deterministic EMMAKE Home field-pilot pack."""

from __future__ import annotations

import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[2]
ZIP_ROOT = "emmake-home-field-pilot"
CANDIDATE_FILE = ROOT / "release" / "emmake-phase10e-candidate.json"
EXPECTED_FILES = {
    f"{ZIP_ROOT}/EMMAKE_HOME_FIELD_PILOT.md",
    f"{ZIP_ROOT}/emmake-home.es_ES.json",
    f"{ZIP_ROOT}/pilot-evidence-template.json",
    f"{ZIP_ROOT}/pilot-manifest.json",
    f"{ZIP_ROOT}/seo-geo-migration-bridge.zip",
    f"{ZIP_ROOT}/seo-geo-theme.zip",
}


def sha256(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def build(path: Path) -> bytes:
    subprocess.run(
        [sys.executable, "scripts/build-emmake-field-pilot-pack.py", "--output", str(path)],
        cwd=ROOT,
        check=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
    )
    return path.read_bytes()


def candidate_contract() -> dict[str, object]:
    if not CANDIDATE_FILE.is_file():
        raise RuntimeError("Canonical Phase 10E candidate record is missing")

    candidate = json.loads(CANDIDATE_FILE.read_text(encoding="utf-8"))
    expected_keys = {
        "schema_version",
        "mode",
        "site_id",
        "target",
        "target_version",
        "release_channel",
        "source_commit",
        "theme",
        "migration_bridge",
        "field_pilot_pack",
        "stable_decision",
        "acceptance_status",
        "candidate_frozen_on",
    }
    if set(candidate) != expected_keys:
        raise RuntimeError("Canonical Phase 10E candidate schema drifted")
    if candidate["schema_version"] != 1 or candidate["mode"] != "emmake-phase10e-candidate":
        raise RuntimeError("Canonical Phase 10E candidate identity is invalid")
    if candidate["site_id"] != "emmake-com":
        raise RuntimeError("Canonical Phase 10E candidate site identity is invalid")
    if candidate["target"] != {
        "production_origin": "https://emmake.com",
        "sandbox_origin": "https://emmake.com/nuevaweb/",
    }:
        raise RuntimeError("Canonical Phase 10E target drifted")
    return candidate


def main() -> int:
    candidate = candidate_contract()

    with tempfile.TemporaryDirectory(prefix="emmake-pilot-pack-acceptance-") as temp_name:
        temp = Path(temp_name)
        first_path = temp / "first.zip"
        second_path = temp / "second.zip"
        first = build(first_path)
        second = build(second_path)

        if first != second:
            raise RuntimeError("Field-pilot pack is not byte-for-byte reproducible")

        pack_sha256 = sha256(first)
        if candidate["field_pilot_pack"]["file"] != "emmake-home-field-pilot-pack.zip":
            raise RuntimeError("Canonical field-pilot pack filename is invalid")
        if candidate["field_pilot_pack"]["sha256"] != pack_sha256:
            raise RuntimeError(
                "Built field-pilot pack does not match canonical Phase 10E SHA-256: "
                f"expected={candidate['field_pilot_pack']['sha256']} received={pack_sha256}"
            )

        with zipfile.ZipFile(first_path) as archive:
            names = set(archive.namelist())
            if names != EXPECTED_FILES:
                raise RuntimeError(f"Unexpected field-pilot pack members: {sorted(names)}")

            manifest = json.loads(archive.read(f"{ZIP_ROOT}/pilot-manifest.json"))
            blueprint = json.loads(archive.read(f"{ZIP_ROOT}/emmake-home.es_ES.json"))
            evidence = json.loads(archive.read(f"{ZIP_ROOT}/pilot-evidence-template.json"))
            runbook = archive.read(f"{ZIP_ROOT}/EMMAKE_HOME_FIELD_PILOT.md").decode("utf-8")
            plugin_bytes = archive.read(f"{ZIP_ROOT}/seo-geo-migration-bridge.zip")
            theme_bytes = archive.read(f"{ZIP_ROOT}/seo-geo-theme.zip")

        if manifest["mode"] != "emmake-home-field-pilot-pack":
            raise RuntimeError("Pilot manifest mode is invalid")
        if manifest["target"] != {
            "host": "emmake.com",
            "path": "/nuevaweb/",
            "scope": "sandbox-home-pilot",
        }:
            raise RuntimeError("Pilot manifest target is invalid")

        bridge = candidate["migration_bridge"]
        theme = candidate["theme"]
        if manifest["migration_bridge"]["version"] != bridge["version"]:
            raise RuntimeError("Migration Bridge version drifted from canonical Phase 10E candidate")
        if manifest["migration_bridge"]["file"] != bridge["file"]:
            raise RuntimeError("Migration Bridge filename drifted from canonical Phase 10E candidate")
        if manifest["migration_bridge"]["sha256"] != bridge["sha256"]:
            raise RuntimeError("Migration Bridge manifest SHA-256 drifted from canonical Phase 10E candidate")
        if bridge["sha256"] != sha256(plugin_bytes):
            raise RuntimeError("Migration Bridge built ZIP does not match canonical Phase 10E SHA-256")

        if manifest["theme"]["version"] != theme["version"]:
            raise RuntimeError("Theme version drifted from canonical Phase 10E candidate")
        if manifest["theme"]["release_channel"] != candidate["release_channel"]:
            raise RuntimeError("Theme release channel drifted from canonical Phase 10E candidate")
        if manifest["theme"]["file"] != theme["file"]:
            raise RuntimeError("Theme filename drifted from canonical Phase 10E candidate")
        if manifest["theme"]["sha256"] != theme["sha256"]:
            raise RuntimeError("Theme manifest SHA-256 drifted from canonical Phase 10E candidate")
        if theme["sha256"] != sha256(theme_bytes):
            raise RuntimeError("Theme built ZIP does not match canonical Phase 10E SHA-256")
        if theme["version"] != candidate["target_version"]:
            raise RuntimeError("Canonical Theme version must match Phase 10E target version")

        if manifest["safety"] != {
            "sandbox_only": True,
            "production_front_page_mutation": False,
            "legacy_layout_reuse": False,
            "automatic_cutover": False,
        }:
            raise RuntimeError("Pilot safety contract drifted")

        if evidence["migration_bridge_version"] != manifest["migration_bridge"]["version"]:
            raise RuntimeError("Evidence template Migration Bridge version drifted from manifest")
        if evidence["theme_version"] != manifest["theme"]["version"]:
            raise RuntimeError("Evidence template Theme version drifted from manifest")

        runbook_bridge_marker = f"Migration Bridge {manifest['migration_bridge']['version']}"
        if runbook_bridge_marker not in runbook:
            raise RuntimeError("Runbook Migration Bridge version drifted from manifest")
        runbook_theme_marker = (
            f"SEO/GEO Theme {manifest['theme']['version']} "
            f"{manifest['theme']['release_channel']} release candidate"
        )
        if runbook_theme_marker not in runbook:
            raise RuntimeError("Runbook Theme version/channel drifted from manifest")

        forbidden = {"draft_id", "source_id", "plan_sha256", "kit_sha256", "saved_at", "blueprint_sha256"}
        if forbidden.intersection(blueprint):
            raise RuntimeError("Blueprint carries runtime identity")
        if blueprint["locale"] != "es_ES" or blueprint["model"] != "corporate-home-v1":
            raise RuntimeError("Blueprint locale/model drifted")
        if blueprint["verified_groups"] != {
            "hero-proof": False,
            "proof": False,
            "case-study": False,
        }:
            raise RuntimeError("Unverified evidence was enabled")

        if evidence["step_7"]["ready_for_browser_qa"] is not None:
            raise RuntimeError("Evidence template must begin unresolved")
        if set(evidence["browser_qa"]) != {
            "visual-layout",
            "responsive-behavior",
            "accessibility",
            "seo-geo-rendered-output",
            "performance",
        }:
            raise RuntimeError("Browser QA evidence contract is incomplete")

        for marker in (
            "SEO_GEO_MIGRATION_SANDBOX",
            "SEO_GEO_MIGRATION_SANDBOX_MODE",
            "SEO_GEO_MIGRATION_STORAGE_ISOLATED",
            "Step 1 — Rescue Manifest",
            "Step 7 — Field-pilot readiness",
            "ready_for_browser_qa=true",
            "Production cutover requires a separate explicit decision",
        ):
            if marker not in runbook:
                raise RuntimeError(f"Runbook contract marker missing: {marker}")

        with zipfile.ZipFile(first_path) as archive:
            plugin_path = temp / "plugin.zip"
            theme_path = temp / "theme.zip"
            plugin_path.write_bytes(archive.read(f"{ZIP_ROOT}/seo-geo-migration-bridge.zip"))
            theme_path.write_bytes(archive.read(f"{ZIP_ROOT}/seo-geo-theme.zip"))

        with zipfile.ZipFile(plugin_path) as plugin_archive:
            if "seo-geo-migration-bridge/seo-geo-migration-bridge.php" not in plugin_archive.namelist():
                raise RuntimeError("Nested Migration Bridge ZIP is not installable")

        with zipfile.ZipFile(theme_path) as theme_archive:
            if "seo-geo-theme/style.css" not in theme_archive.namelist():
                raise RuntimeError("Nested Theme ZIP is not installable")

        checksum_line = first_path.with_suffix(".zip.sha256").read_text(encoding="utf-8").strip()
        if not checksum_line.startswith(pack_sha256):
            raise RuntimeError("Outer pack checksum mismatch")

    print(
        "EMMAKE field-pilot pack acceptance OK: "
        f"theme={theme['version']} bridge={bridge['version']} pack_sha256={candidate['field_pilot_pack']['sha256']}."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
