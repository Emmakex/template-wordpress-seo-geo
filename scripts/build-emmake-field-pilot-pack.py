#!/usr/bin/env python3
"""Build a deterministic EMMAKE /nuevaweb/ Home field-pilot pack."""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
import re
import stat
import subprocess
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]
BLUEPRINT = ROOT / "examples" / "content-blueprints" / "emmake-home.es_ES.json"
RUNBOOK = ROOT / "docs" / "EMMAKE_HOME_FIELD_PILOT.md"
VERSION_FILE = ROOT / "release" / "version.json"
PLUGIN_FILE = ROOT / "packages" / "seo-geo-migration-bridge" / "seo-geo-migration-bridge.php"
ZIP_ROOT = "emmake-home-field-pilot"
FIXED_TIMESTAMP = (2020, 1, 1, 0, 0, 0)
FILE_MODE = stat.S_IFREG | 0o644


def sha256_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def sha256_file(path: Path) -> str:
    return sha256_bytes(path.read_bytes())


def plugin_version() -> str:
    source = PLUGIN_FILE.read_text(encoding="utf-8")
    match = re.search(r"^ \* Version:\s*([^\r\n]+)", source, re.MULTILINE)
    if not match:
        raise RuntimeError("Migration Bridge version header is missing")
    return match.group(1).strip()


def theme_release() -> dict[str, object]:
    data = json.loads(VERSION_FILE.read_text(encoding="utf-8"))
    if data.get("theme_slug") != "seo-geo-theme":
        raise RuntimeError("Theme release identity is invalid")
    return data


def blueprint_contract() -> dict[str, object]:
    data = json.loads(BLUEPRINT.read_text(encoding="utf-8"))
    forbidden = {
        "draft_id",
        "source_id",
        "plan_sha256",
        "kit_sha256",
        "saved_at",
        "blueprint_sha256",
    }
    if forbidden.intersection(data):
        raise RuntimeError("Portable Emmake blueprint contains runtime identity")
    if data.get("mode") != "corporate-home-content-blueprint":
        raise RuntimeError("Emmake blueprint mode is invalid")
    if data.get("model") != "corporate-home-v1" or data.get("locale") != "es_ES":
        raise RuntimeError("Emmake blueprint model/locale is invalid")
    groups = data.get("verified_groups")
    if groups != {"hero-proof": False, "proof": False, "case-study": False}:
        raise RuntimeError("Emmake evidence groups must remain disabled")
    return data


def build_components(directory: Path) -> tuple[Path, Path]:
    plugin_zip = directory / "seo-geo-migration-bridge.zip"
    theme_zip = directory / "seo-geo-theme.zip"
    subprocess.run(
        [sys.executable, "scripts/build-migration-bridge-release.py", "--output", str(plugin_zip)],
        cwd=ROOT,
        check=True,
    )
    subprocess.run(
        [sys.executable, "scripts/build-theme-release.py", "--output", str(theme_zip)],
        cwd=ROOT,
        check=True,
    )
    return plugin_zip, theme_zip


def write_json(path: Path, value: object) -> None:
    path.write_text(
        json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + "\n",
        encoding="utf-8",
        newline="\n",
    )


def write_zip(output: Path, files: dict[str, Path]) -> str:
    output.parent.mkdir(parents=True, exist_ok=True)
    if output.exists():
        output.unlink()

    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name in sorted(files):
            path = files[name]
            info = zipfile.ZipInfo(f"{ZIP_ROOT}/{name}", FIXED_TIMESTAMP)
            info.create_system = 3
            info.external_attr = FILE_MODE << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            info.extra = b""
            info.comment = b""
            archive.writestr(info, path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)

    return sha256_file(output)


def build_pack(output: Path) -> str:
    blueprint = blueprint_contract()
    theme = theme_release()
    bridge_version = plugin_version()

    with tempfile.TemporaryDirectory(prefix="emmake-home-field-pilot-") as temp_name:
        temp = Path(temp_name)
        plugin_zip, theme_zip = build_components(temp)

        blueprint_copy = temp / "emmake-home.es_ES.json"
        blueprint_copy.write_bytes(BLUEPRINT.read_bytes())
        runbook_copy = temp / "EMMAKE_HOME_FIELD_PILOT.md"
        runbook_copy.write_bytes(RUNBOOK.read_bytes())

        evidence = {
            "schema_version": 1,
            "mode": "emmake-home-field-pilot-evidence",
            "target_path": "/nuevaweb/",
            "migration_bridge_version": bridge_version,
            "theme_version": str(theme["version"]),
            "step_7": {
                "ready_for_browser_qa": None,
                "report_sha256": "",
                "blockers": [],
                "warnings": [],
            },
            "browser_qa": {
                "visual-layout": None,
                "responsive-behavior": None,
                "accessibility": None,
                "seo-geo-rendered-output": None,
                "performance": None,
            },
            "notes": [],
        }
        evidence_path = temp / "pilot-evidence-template.json"
        write_json(evidence_path, evidence)

        component_files = {
            "seo-geo-migration-bridge.zip": plugin_zip,
            "seo-geo-theme.zip": theme_zip,
            "emmake-home.es_ES.json": blueprint_copy,
            "EMMAKE_HOME_FIELD_PILOT.md": runbook_copy,
            "pilot-evidence-template.json": evidence_path,
        }

        manifest = {
            "schema_version": 1,
            "mode": "emmake-home-field-pilot-pack",
            "target": {
                "host": "emmake.com",
                "path": "/nuevaweb/",
                "scope": "sandbox-home-pilot",
            },
            "migration_bridge": {
                "version": bridge_version,
                "file": "seo-geo-migration-bridge.zip",
                "sha256": sha256_file(plugin_zip),
            },
            "theme": {
                "version": str(theme["version"]),
                "release_channel": str(theme["release_channel"]),
                "file": "seo-geo-theme.zip",
                "sha256": sha256_file(theme_zip),
            },
            "blueprint": {
                "file": "emmake-home.es_ES.json",
                "sha256": sha256_file(blueprint_copy),
                "locale": str(blueprint["locale"]),
                "model": str(blueprint["model"]),
                "verified_groups": blueprint["verified_groups"],
            },
            "runbook": {
                "file": "EMMAKE_HOME_FIELD_PILOT.md",
                "sha256": sha256_file(runbook_copy),
            },
            "evidence_template": {
                "file": "pilot-evidence-template.json",
                "sha256": sha256_file(evidence_path),
            },
            "execution_sequence": [
                "rescue-manifest",
                "clone-reset",
                "corporate-bootstrap",
                "clean-home-draft",
                "content-blueprint-import",
                "native-hydration",
                "native-seo-handoff",
                "field-pilot-readiness",
                "browser-qa",
            ],
            "safety": {
                "sandbox_only": True,
                "production_front_page_mutation": False,
                "legacy_layout_reuse": False,
                "automatic_cutover": False,
            },
        }
        manifest_path = temp / "pilot-manifest.json"
        write_json(manifest_path, manifest)
        component_files["pilot-manifest.json"] = manifest_path

        digest = write_zip(output, component_files)

    checksum = output.with_suffix(output.suffix + ".sha256")
    checksum.write_text(f"{digest}  {output.name}\n", encoding="utf-8", newline="\n")
    return digest


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--output",
        type=Path,
        default=ROOT / "dist-field-pilot" / "emmake-home-field-pilot-pack.zip",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    output = args.output if args.output.is_absolute() else ROOT / args.output
    digest = build_pack(output)
    print(f"EMMAKE Home field-pilot pack: {output}")
    print(f"SHA-256: {digest}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
