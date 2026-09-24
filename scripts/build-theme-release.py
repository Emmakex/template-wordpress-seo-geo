#!/usr/bin/env python3
"""Build a deterministic self-contained SEO/GEO theme release ZIP."""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import tempfile
import zipfile

REPOSITORY_ROOT = Path(__file__).resolve().parents[1]
THEME_ROOT = "seo-geo-theme"
RUNTIME_ROOT = Path("inc/seo-geo-core")
INTEGRITY_FILE = Path("release-integrity.json")
VERSION_FILE = REPOSITORY_ROOT / "release/version.json"
FIXED_ZIP_TIME = (1980, 1, 1, 0, 0, 0)
FILE_MODE = 0o100644


def sha256_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def canonical_json_bytes(value: object) -> bytes:
    return json.dumps(
        value,
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    ).encode("utf-8")


def release_metadata(build_dir: Path) -> dict[str, object]:
    if not VERSION_FILE.is_file():
        raise RuntimeError("release/version.json is missing")

    metadata = json.loads(VERSION_FILE.read_text(encoding="utf-8"))
    expected = {"schema_version", "theme_slug", "version", "release_channel"}
    if set(metadata) != expected:
        raise RuntimeError("release/version.json does not match the Phase 10B schema")
    if metadata["schema_version"] != 1 or metadata["theme_slug"] != THEME_ROOT:
        raise RuntimeError("release/version.json theme identity is invalid")
    if not isinstance(metadata["version"], str) or not re.fullmatch(
        r"(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?",
        metadata["version"],
    ):
        raise RuntimeError("release/version.json version is not valid SemVer")
    if metadata["release_channel"] not in {"prestable", "stable"}:
        raise RuntimeError("release/version.json release_channel is invalid")

    style_file = build_dir / "style.css"
    style = style_file.read_text(encoding="utf-8")
    versions = re.findall(r"^\s*Version:\s*(.+?)\s*$", style, re.MULTILINE)
    if versions != [metadata["version"]]:
        raise RuntimeError(
            f"Built style.css version {versions!r} does not match release version {metadata['version']!r}"
        )

    return metadata


def runtime_integrity(build_dir: Path) -> dict[str, object]:
    runtime_dir = build_dir / RUNTIME_ROOT
    if not runtime_dir.is_dir():
        raise RuntimeError(f"Embedded runtime directory missing: {runtime_dir}")

    files: dict[str, str] = {}
    for path in sorted(runtime_dir.rglob("*"), key=lambda item: item.as_posix()):
        if path.is_symlink():
            raise RuntimeError(f"Symlinks are not allowed in the release runtime: {path}")
        if path.is_file():
            relative = path.relative_to(build_dir).as_posix()
            files[relative] = sha256_bytes(path.read_bytes())

    runtime_file = "inc/seo-geo-core/src/Runtime.php"
    if runtime_file not in files:
        raise RuntimeError("Embedded Runtime.php is missing from release build")

    return {
        "schema_version": 1,
        "mode": "seo-geo-theme-release-integrity",
        "runtime_root": RUNTIME_ROOT.as_posix(),
        "runtime_file_count": len(files),
        "runtime_tree_sha256": sha256_bytes(canonical_json_bytes(files)),
        "runtime_files": files,
    }


def write_integrity_manifest(build_dir: Path) -> None:
    metadata = release_metadata(build_dir)
    manifest = runtime_integrity(build_dir)
    manifest["theme_slug"] = metadata["theme_slug"]
    manifest["theme_version"] = metadata["version"]
    manifest["release_channel"] = metadata["release_channel"]
    payload = json.dumps(
        manifest,
        ensure_ascii=False,
        sort_keys=True,
        indent=2,
    ) + "\n"
    (build_dir / INTEGRITY_FILE).write_text(payload, encoding="utf-8", newline="\n")


def release_files(build_dir: Path) -> list[Path]:
    files: list[Path] = []
    for path in build_dir.rglob("*"):
        if path.is_symlink():
            raise RuntimeError(f"Symlinks are not allowed in the release ZIP: {path}")
        if path.is_file():
            files.append(path)

    files.sort(key=lambda path: path.relative_to(build_dir).as_posix())
    return files


def write_deterministic_zip(build_dir: Path, output: Path) -> str:
    output.parent.mkdir(parents=True, exist_ok=True)
    if output.exists():
        output.unlink()

    with zipfile.ZipFile(
        output,
        mode="w",
        compression=zipfile.ZIP_DEFLATED,
        compresslevel=9,
        strict_timestamps=True,
    ) as archive:
        for path in release_files(build_dir):
            relative = path.relative_to(build_dir).as_posix()
            info = zipfile.ZipInfo(f"{THEME_ROOT}/{relative}", FIXED_ZIP_TIME)
            info.create_system = 3
            info.external_attr = FILE_MODE << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            info.extra = b""
            info.comment = b""
            archive.writestr(info, path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)

    digest = sha256_bytes(output.read_bytes())
    checksum_path = output.with_name(output.name + ".sha256")
    checksum_path.write_text(f"{digest}  {output.name}\n", encoding="utf-8", newline="\n")
    return digest


def build_release(output: Path) -> str:
    with tempfile.TemporaryDirectory(prefix="seo-geo-theme-release-") as temp_dir:
        build_dir = Path(temp_dir) / THEME_ROOT
        subprocess.run(
            ["bash", "scripts/build-theme-package.sh", str(build_dir)],
            cwd=REPOSITORY_ROOT,
            check=True,
        )
        write_integrity_manifest(build_dir)
        return write_deterministic_zip(build_dir, output)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("dist/seo-geo-theme.zip"),
        help="Release ZIP path (default: dist/seo-geo-theme.zip)",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    output = args.output
    if not output.is_absolute():
        output = REPOSITORY_ROOT / output

    digest = build_release(output)
    print(f"Deterministic theme release: {output}")
    print(f"SHA-256: {digest}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
