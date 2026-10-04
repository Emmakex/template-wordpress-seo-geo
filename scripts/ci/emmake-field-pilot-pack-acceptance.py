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


def main() -> int:
    with tempfile.TemporaryDirectory(prefix="emmake-pilot-pack-acceptance-") as temp_name:
        temp = Path(temp_name)
        first_path = temp / "first.zip"
        second_path = temp / "second.zip"
        first = build(first_path)
        second = build(second_path)

        if first != second:
            raise RuntimeError("Field-pilot pack is not byte-for-byte reproducible")

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
        if manifest["migration_bridge"]["version"] != "0.8.57":
            raise RuntimeError("Pilot pack must carry Migration Bridge 0.8.57")
        if manifest["migration_bridge"]["sha256"] != sha256(plugin_bytes):
            raise RuntimeError("Migration Bridge checksum mismatch")
        if manifest["theme"]["sha256"] != sha256(theme_bytes):
            raise RuntimeError("Theme checksum mismatch")
        if manifest["safety"] != {
            "sandbox_only": True,
            "production_front_page_mutation": False,
            "legacy_layout_reuse": False,
            "automatic_cutover": False,
        }:
            raise RuntimeError("Pilot safety contract drifted")

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
        if not checksum_line.startswith(sha256(first)):
            raise RuntimeError("Outer pack checksum mismatch")

    print("EMMAKE field-pilot pack acceptance OK.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
