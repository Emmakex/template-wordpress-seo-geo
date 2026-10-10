#!/usr/bin/env python3
"""Validate deterministic, installable SEO/GEO Manager release ZIP."""

from __future__ import annotations

import hashlib
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "packages" / "seo-geo-manager"
BUILDER = ROOT / "scripts" / "build-seo-geo-manager-release.py"
PLUGIN = SOURCE / "seo-geo-manager.php"
ZIP_ROOT = "seo-geo-manager/"


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def source_members() -> set[str]:
    return {
        ZIP_ROOT + path.relative_to(SOURCE).as_posix()
        for path in SOURCE.rglob("*")
        if path.is_file()
        and path.name != ".DS_Store"
        and "__pycache__" not in path.parts
    }


def parse_version() -> str:
    content = PLUGIN.read_text(encoding="utf-8")
    header = re.search(r"^ \* Version:\s*([^\r\n]+)", content, re.MULTILINE)
    constant = re.search(
        r"define\(\s*'SEO_GEO_MANAGER_VERSION'\s*,\s*'([^']+)'\s*\)",
        content,
    )
    if not header or not constant:
        raise SystemExit("Version metadata missing from SEO/GEO Manager plugin")
    if header.group(1).strip() != constant.group(1).strip():
        raise SystemExit("Plugin header version does not match runtime version constant")
    return header.group(1).strip()


def inspect_zip(path: Path) -> None:
    expected = source_members()
    with zipfile.ZipFile(path) as archive:
        names = archive.namelist()
        if len(names) != len(set(names)):
            raise SystemExit("Release ZIP contains duplicate entries")
        if any(name.startswith("/") or ".." in Path(name).parts for name in names):
            raise SystemExit("Release ZIP contains unsafe paths")
        if any(not name.startswith(ZIP_ROOT) for name in names):
            raise SystemExit("Release ZIP contains more than one plugin root")
        actual = {name for name in names if not name.endswith("/")}
        if actual != expected:
            missing = sorted(expected - actual)
            extra = sorted(actual - expected)
            raise SystemExit(
                f"Release ZIP source mismatch: missing={missing} extra={extra}"
            )

        required = {
            ZIP_ROOT + "seo-geo-manager.php",
            ZIP_ROOT + "src/Rest/CapabilitiesController.php",
            ZIP_ROOT + "src/Support/CapabilityManifest.php",
        }
        missing_required = sorted(required - actual)
        if missing_required:
            raise SystemExit(
                f"Release ZIP is missing required Manager runtime files: {missing_required}"
            )

        controller = archive.read(
            ZIP_ROOT + "src/Rest/CapabilitiesController.php"
        ).decode("utf-8")
        manifest = archive.read(
            ZIP_ROOT + "src/Support/CapabilityManifest.php"
        ).decode("utf-8")
        plugin = archive.read(ZIP_ROOT + "src/Plugin.php").decode("utf-8")

        if "'/capabilities'" not in controller or "CapabilityManifest::build()" not in controller:
            raise SystemExit("Release ZIP capability endpoint contract is incomplete")
        if "'schema_version' => 1" not in manifest:
            raise SystemExit("Release ZIP capability manifest schema is missing")
        if "'generic_remote_shell'" not in manifest or "'secrets_returned'" not in manifest:
            raise SystemExit("Release ZIP capability safety declarations are missing")
        if "CapabilitiesController::register_routes();" not in plugin:
            raise SystemExit("Release ZIP does not register capability discovery")

        for info in archive.infolist():
            if info.is_dir():
                continue
            mode = (info.external_attr >> 16) & 0o777
            if mode != 0o644:
                raise SystemExit(
                    f"Unexpected file mode for {info.filename}: {oct(mode)}"
                )
            if info.date_time != (2020, 1, 1, 0, 0, 0):
                raise SystemExit(f"Unstable ZIP timestamp for {info.filename}")


def main() -> int:
    version = parse_version()
    with tempfile.TemporaryDirectory(prefix="seo-geo-manager-") as tmp:
        tmpdir = Path(tmp)
        first = tmpdir / "first.zip"
        second = tmpdir / "second.zip"
        subprocess.run(
            [sys.executable, str(BUILDER), "--output", str(first)],
            cwd=ROOT,
            check=True,
        )
        subprocess.run(
            [sys.executable, str(BUILDER), "--output", str(second)],
            cwd=ROOT,
            check=True,
        )
        if first.read_bytes() != second.read_bytes():
            raise SystemExit("Two SEO/GEO Manager builds are not byte-identical")
        inspect_zip(first)
        inspect_zip(second)
        print(
            f"SEO/GEO Manager release acceptance OK: version={version}, "
            f"files={len(source_members())}, sha256={digest(first)}"
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
