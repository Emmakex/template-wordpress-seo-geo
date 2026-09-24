#!/usr/bin/env python3
"""Validate deterministic, installable SEO/GEO Migration Bridge release ZIP."""

from __future__ import annotations

import hashlib
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "packages" / "seo-geo-migration-bridge"
BUILDER = ROOT / "scripts" / "build-migration-bridge-release.py"
PLUGIN = SOURCE / "seo-geo-migration-bridge.php"
ZIP_ROOT = "seo-geo-migration-bridge/"


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def source_members() -> set[str]:
    return {
        ZIP_ROOT + path.relative_to(SOURCE).as_posix()
        for path in SOURCE.rglob("*")
        if path.is_file() and path.name != ".DS_Store" and "__pycache__" not in path.parts
    }


def parse_version() -> str:
    content = PLUGIN.read_text(encoding="utf-8")
    header = re.search(r"^ \* Version:\s*([^\r\n]+)", content, re.MULTILINE)
    constant = re.search(
        r"define\(\s*'SEO_GEO_MIGRATION_BRIDGE_VERSION'\s*,\s*'([^']+)'\s*\)",
        content,
    )
    if not header or not constant:
        raise SystemExit("Version metadata missing from Migration Bridge plugin")
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
            raise SystemExit(f"Release ZIP source mismatch: missing={missing} extra={extra}")
        required = ZIP_ROOT + "seo-geo-migration-bridge.php"
        if required not in actual:
            raise SystemExit("Release ZIP is missing the main WordPress plugin file")
        for info in archive.infolist():
            if info.is_dir():
                continue
            mode = (info.external_attr >> 16) & 0o777
            if mode != 0o644:
                raise SystemExit(f"Unexpected file mode for {info.filename}: {oct(mode)}")
            if info.date_time != (2020, 1, 1, 0, 0, 0):
                raise SystemExit(f"Unstable ZIP timestamp for {info.filename}")


def main() -> int:
    version = parse_version()
    with tempfile.TemporaryDirectory(prefix="seo-geo-migration-bridge-") as tmp:
        tmpdir = Path(tmp)
        first = tmpdir / "first.zip"
        second = tmpdir / "second.zip"
        subprocess.run([sys.executable, str(BUILDER), "--output", str(first)], cwd=ROOT, check=True)
        subprocess.run([sys.executable, str(BUILDER), "--output", str(second)], cwd=ROOT, check=True)
        if first.read_bytes() != second.read_bytes():
            raise SystemExit("Two Migration Bridge builds are not byte-identical")
        inspect_zip(first)
        inspect_zip(second)
        print(
            f"Migration Bridge release acceptance OK: version={version}, "
            f"files={len(source_members())}, sha256={digest(first)}"
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
