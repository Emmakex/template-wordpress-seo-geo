#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
COMMAND="bash scripts/ci/release-artifact-acceptance.sh"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

cd "$ROOT_DIR"

fail_release() {
  local step="$1"
  local message="$2"
  local expected="$3"
  local received="$4"

  python3 - "$step" "$message" "$expected" "$received" <<'PY'
import json
import sys

step, message, expected, received = sys.argv[1:]
print(
    json.dumps(
        {
            "schema_version": 1,
            "pipeline": "Release Artifact CI",
            "step": step,
            "command": "bash scripts/ci/release-artifact-acceptance.sh",
            "primary_error": message,
            "expected": expected,
            "received": received,
        },
        indent=2,
    )
)
PY
  exit 1
}

BUILD_A="$TMP_DIR/a/seo-geo-theme.zip"
BUILD_B="$TMP_DIR/b/seo-geo-theme.zip"

mkdir -p "$(dirname "$BUILD_A")" "$(dirname "$BUILD_B")"

printf '[release-artifact] Building deterministic release A.\n'
python3 scripts/build-theme-release.py --output "$BUILD_A"   || fail_release "build-a" "First deterministic release build failed" "release ZIP" "builder exited non-zero"

printf '[release-artifact] Building deterministic release B.\n'
python3 scripts/build-theme-release.py --output "$BUILD_B"   || fail_release "build-b" "Second deterministic release build failed" "release ZIP" "builder exited non-zero"

[[ -f "$BUILD_A" && -f "$BUILD_A.sha256" ]]   || fail_release "artifact-a" "First release artifact/checksum missing" "ZIP + .sha256" "missing file"
[[ -f "$BUILD_B" && -f "$BUILD_B.sha256" ]]   || fail_release "artifact-b" "Second release artifact/checksum missing" "ZIP + .sha256" "missing file"

if ! cmp -s "$BUILD_A" "$BUILD_B"; then
  fail_release "reproducibility" "Same-source release builds are not byte-identical" "cmp success" "ZIP bytes differ"
fi

SHA_A="$(sha256sum "$BUILD_A" | awk '{print $1}')"
SHA_B="$(sha256sum "$BUILD_B" | awk '{print $1}')"
[[ "$SHA_A" == "$SHA_B" ]]   || fail_release "checksum-equality" "Same-source release hashes differ" "$SHA_A" "$SHA_B"

CHECKSUM_A="$(awk '{print $1}' "$BUILD_A.sha256")"
CHECKSUM_B="$(awk '{print $1}' "$BUILD_B.sha256")"
[[ "$CHECKSUM_A" == "$SHA_A" && "$CHECKSUM_B" == "$SHA_B" ]]   || fail_release "checksum-files" "Generated checksum files do not match ZIP bytes" "$SHA_A / $SHA_B" "$CHECKSUM_A / $CHECKSUM_B"

if ! python3 - "$BUILD_A" "packages/seo-geo-core/src/Runtime.php" <<'PY'
import hashlib
import json
from pathlib import Path, PurePosixPath
import stat
import sys
import zipfile

zip_path = Path(sys.argv[1])
source_runtime = Path(sys.argv[2])
root = "seo-geo-theme/"
manifest_name = root + "release-integrity.json"
runtime_path = "inc/seo-geo-core/src/Runtime.php"
fixed_time = (1980, 1, 1, 0, 0, 0)

def sha256(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()

with zipfile.ZipFile(zip_path, "r") as archive:
    infos = archive.infolist()
    names = [info.filename for info in infos]

    assert names, "release ZIP is empty"
    assert len(names) == len(set(names)), "release ZIP contains duplicate paths"
    assert names == sorted(names), "release ZIP entries are not sorted"
    assert all(name.startswith(root) for name in names), "release ZIP has multiple roots"

    forbidden = (
        root + ".git/",
        root + ".github/",
        root + "tests/",
        root + "scripts/",
        root + "packages/",
        root + "node_modules/",
    )
    assert not any(name.startswith(forbidden) for name in names), "repository-only path leaked into release"

    for info in infos:
        relative = info.filename[len(root):]
        parts = PurePosixPath(relative).parts
        assert ".." not in parts, "path traversal entry present"
        assert not relative.startswith("/"), "absolute ZIP entry present"
        assert info.date_time == fixed_time, f"non-deterministic timestamp: {info.filename}"
        mode = (info.external_attr >> 16) & 0o777
        assert mode == 0o644, f"non-normalized mode {oct(mode)} for {info.filename}"

    required = {
        root + "style.css",
        root + "functions.php",
        root + "theme.json",
        root + "inc/seo-geo-core/bootstrap.php",
        root + runtime_path,
        manifest_name,
    }
    assert required.issubset(set(names)), "release ZIP is missing required theme/runtime files"

    manifest = json.loads(archive.read(manifest_name).decode("utf-8"))
    assert manifest["schema_version"] == 1
    assert manifest["mode"] == "seo-geo-theme-release-integrity"
    assert manifest["runtime_root"] == "inc/seo-geo-core"

    actual_runtime = {}
    runtime_prefix = root + "inc/seo-geo-core/"
    for name in names:
        if name.startswith(runtime_prefix):
            relative = name[len(root):]
            actual_runtime[relative] = sha256(archive.read(name))

    expected_runtime = manifest["runtime_files"]
    assert actual_runtime == expected_runtime, "embedded runtime file hashes do not match manifest"
    assert manifest["runtime_file_count"] == len(actual_runtime)

    canonical = json.dumps(
        actual_runtime,
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    ).encode("utf-8")
    assert manifest["runtime_tree_sha256"] == sha256(canonical), "runtime tree fingerprint mismatch"

    source_hash = sha256(source_runtime.read_bytes())
    assert actual_runtime[runtime_path] == source_hash, "embedded Runtime.php differs from Core source"

print("ok")
PY
then
  fail_release "zip-contract" "Release ZIP/root/integrity contract failed" "deterministic single-theme ZIP with verified embedded runtime" "python assertion failed"
fi

printf '[release-artifact] Reproducibility OK: %s\n' "$SHA_A"
printf '[release-artifact] Single-theme root and embedded runtime integrity manifest verified.\n'
