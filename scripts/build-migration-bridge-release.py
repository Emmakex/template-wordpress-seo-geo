#!/usr/bin/env python3
"""Build a deterministic installable ZIP for SEO/GEO Migration Bridge."""

from __future__ import annotations

import argparse
import hashlib
from pathlib import Path
import re
import stat
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "packages" / "seo-geo-migration-bridge"
PLUGIN_FILE = SOURCE / "seo-geo-migration-bridge.php"
ZIP_ROOT = "seo-geo-migration-bridge"
FIXED_TIMESTAMP = (2020, 1, 1, 0, 0, 0)


def plugin_version() -> str:
    source = PLUGIN_FILE.read_text(encoding="utf-8")
    header = re.search(r"^ \* Version:\s*([^\r\n]+)", source, re.MULTILINE)
    constant = re.search(
        r"define\(\s*'SEO_GEO_MIGRATION_BRIDGE_VERSION'\s*,\s*'([^']+)'\s*\)",
        source,
    )
    if not header or not constant:
        raise SystemExit("Migration Bridge version metadata is missing")
    header_version = header.group(1).strip()
    constant_version = constant.group(1).strip()
    if header_version != constant_version:
        raise SystemExit(
            f"Migration Bridge version mismatch: header={header_version} constant={constant_version}"
        )
    return header_version


def source_files() -> list[Path]:
    files = [
        path
        for path in SOURCE.rglob("*")
        if path.is_file()
        and path.name not in {".DS_Store"}
        and "__pycache__" not in path.parts
    ]
    if PLUGIN_FILE not in files:
        raise SystemExit("Main plugin file is missing from package source")
    return sorted(files, key=lambda path: path.relative_to(SOURCE).as_posix())


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def write_zip(output: Path) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)
    if output.exists():
        output.unlink()

    with zipfile.ZipFile(
        output,
        "w",
        compression=zipfile.ZIP_DEFLATED,
        compresslevel=9,
    ) as archive:
        for source in source_files():
            relative = source.relative_to(SOURCE).as_posix()
            info = zipfile.ZipInfo(f"{ZIP_ROOT}/{relative}", FIXED_TIMESTAMP)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            archive.writestr(info, source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--output",
        type=Path,
        default=ROOT / "dist-migration-bridge" / "seo-geo-migration-bridge.zip",
    )
    args = parser.parse_args()
    output = args.output.resolve()

    version = plugin_version()
    write_zip(output)
    digest = sha256(output)
    checksum = output.with_suffix(output.suffix + ".sha256")
    checksum.write_text(f"{digest}  {output.name}\n", encoding="utf-8")

    print(f"Migration Bridge {version} ZIP: {output}")
    print(f"SHA-256: {digest}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
