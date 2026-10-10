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
            ZIP_ROOT + "src/Plugin.php",
            ZIP_ROOT + "src/Rest/CapabilitiesController.php",
            ZIP_ROOT + "src/Support/CapabilityManifest.php",
            ZIP_ROOT + "src/Rest/ThemeModelController.php",
            ZIP_ROOT + "src/Intelligence/ThemeModelReader.php",
            ZIP_ROOT + "src/Rest/ContextualLinkController.php",
            ZIP_ROOT + "src/Intelligence/ContextualLinkReader.php",
            ZIP_ROOT + "src/Rest/ContextualLinkChangeController.php",
            ZIP_ROOT + "src/Changes/ContextualLinkChangeAdapter.php",
            ZIP_ROOT + "src/Rest/MediaReferenceController.php",
            ZIP_ROOT + "src/Intelligence/MediaReferenceReader.php",
            ZIP_ROOT + "src/Rest/MediaAltChangeController.php",
            ZIP_ROOT + "src/Changes/MediaAltChangeAdapter.php",
            ZIP_ROOT + "src/Rest/MediaContextChangeController.php",
            ZIP_ROOT + "src/Changes/MediaContextChangeAdapter.php",
            ZIP_ROOT + "src/Support/MediaFingerprint.php",
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
        theme_model_controller = archive.read(
            ZIP_ROOT + "src/Rest/ThemeModelController.php"
        ).decode("utf-8")
        theme_model_reader = archive.read(
            ZIP_ROOT + "src/Intelligence/ThemeModelReader.php"
        ).decode("utf-8")
        contextual_controller = archive.read(
            ZIP_ROOT + "src/Rest/ContextualLinkController.php"
        ).decode("utf-8")
        contextual_reader = archive.read(
            ZIP_ROOT + "src/Intelligence/ContextualLinkReader.php"
        ).decode("utf-8")
        contextual_change_controller = archive.read(
            ZIP_ROOT + "src/Rest/ContextualLinkChangeController.php"
        ).decode("utf-8")
        contextual_change_adapter = archive.read(
            ZIP_ROOT + "src/Changes/ContextualLinkChangeAdapter.php"
        ).decode("utf-8")
        media_controller = archive.read(
            ZIP_ROOT + "src/Rest/MediaReferenceController.php"
        ).decode("utf-8")
        media_reader = archive.read(
            ZIP_ROOT + "src/Intelligence/MediaReferenceReader.php"
        ).decode("utf-8")
        media_alt_controller = archive.read(
            ZIP_ROOT + "src/Rest/MediaAltChangeController.php"
        ).decode("utf-8")
        media_alt_adapter = archive.read(
            ZIP_ROOT + "src/Changes/MediaAltChangeAdapter.php"
        ).decode("utf-8")
        media_context_controller = archive.read(
            ZIP_ROOT + "src/Rest/MediaContextChangeController.php"
        ).decode("utf-8")
        media_context_adapter = archive.read(
            ZIP_ROOT + "src/Changes/MediaContextChangeAdapter.php"
        ).decode("utf-8")
        media_fingerprint = archive.read(
            ZIP_ROOT + "src/Support/MediaFingerprint.php"
        ).decode("utf-8")

        if "'/capabilities'" not in controller or "CapabilityManifest::build()" not in controller:
            raise SystemExit("Release ZIP capability endpoint contract is incomplete")
        if "'schema_version' => 1" not in manifest:
            raise SystemExit("Release ZIP capability manifest schema is missing")
        if "'generic_remote_shell'" not in manifest or "'secrets_returned'" not in manifest:
            raise SystemExit("Release ZIP capability safety declarations are missing")
        if "CapabilitiesController::register_routes();" not in plugin:
            raise SystemExit("Release ZIP does not register capability discovery")

        if "'/theme/models'" not in theme_model_controller:
            raise SystemExit("Release ZIP Theme model list route is missing")
        if "'/theme/models/(?P<model_id>[a-z0-9-]+)'" not in theme_model_controller:
            raise SystemExit("Release ZIP Theme model detail route is missing")
        if "ThemeModelReader::list_models()" not in theme_model_controller:
            raise SystemExit("Release ZIP Theme model list reader is not wired")
        if "ThemeModelReader::read_model( $model_id )" not in theme_model_controller:
            raise SystemExit("Release ZIP Theme model detail reader is not wired")
        if "ThemeModelController::register_routes();" not in plugin:
            raise SystemExit("Release ZIP does not register Theme model discovery")
        if "'theme_model_read'" not in manifest:
            raise SystemExit("Release ZIP capability manifest does not expose Theme model read")
        if "'theme.models.list'" not in manifest or "'theme.models.read'" not in manifest:
            raise SystemExit("Release ZIP capability operations omit Theme model discovery")
        if "'raw_post_content_returned' => false" not in theme_model_reader:
            raise SystemExit("Release ZIP Theme model reader does not enforce bounded content output")
        if "'contract_authority'" not in theme_model_reader or "active-theme-preset-page-models" not in theme_model_reader:
            raise SystemExit("Release ZIP Theme model reader lacks preset contract authority")
        if "ContentFingerprint::for_post( $post )" not in theme_model_reader:
            raise SystemExit("Release ZIP Theme model reader does not return mutation-safe fingerprints")

        if "'/links/contextual'" not in contextual_controller or "ContextualLinkReader::read" not in contextual_controller:
            raise SystemExit("Release ZIP contextual-link read route is incomplete")
        if "ContextualLinkController::register_routes();" not in plugin:
            raise SystemExit("Release ZIP does not register contextual-link discovery")
        if "'contextual_link_read'" not in manifest or "'links.contextual.read'" not in manifest:
            raise SystemExit("Release ZIP capability manifest omits contextual-link discovery")
        if "'raw_post_content_returned'" not in contextual_reader or "'mutation_supported'" not in contextual_reader:
            raise SystemExit("Release ZIP contextual-link reader lacks bounded read-only policy")
        if "environment-leakage-candidate" not in contextual_reader:
            raise SystemExit("Release ZIP contextual-link reader lacks environment leakage classification")
        if "ContentFingerprint::for_post( $post )" not in contextual_reader:
            raise SystemExit("Release ZIP contextual-link reader lacks source fingerprints")

        contextual_routes = (
            "'/links/contextual/changes/preview'",
            "'/links/contextual/changes/apply'",
            "'/links/contextual/changes/(?P<operation_id>[a-f0-9\\-]{36})/rollback'",
        )
        if any(marker not in contextual_change_controller for marker in contextual_routes):
            raise SystemExit("Release ZIP contextual-link write routes are incomplete")
        if "ContextualLinkChangeController::register_routes();" not in plugin:
            raise SystemExit("Release ZIP does not register contextual-link writes")
        if "'contextual_link_write'" not in manifest:
            raise SystemExit("Release ZIP capability manifest omits contextual-link writes")
        for marker in (
            "'links.contextual.preview'",
            "'links.contextual.apply'",
            "'links.contextual.rollback'",
        ):
            if marker not in manifest:
                raise SystemExit(f"Release ZIP contextual-link operation missing: {marker}")
        for marker in (
            "target_resource_id",
            "expected_fingerprint",
            "idempotency_key",
            "seo_geo_manager_contextual_link_rollback_stale",
        ):
            if marker not in contextual_change_adapter:
                raise SystemExit(f"Release ZIP contextual-link safety marker missing: {marker}")
        if "public_operation" not in contextual_change_controller:
            raise SystemExit("Release ZIP contextual-link controller lacks public operation filtering")
        if "before_content" not in contextual_change_controller or "after_content" not in contextual_change_controller:
            raise SystemExit("Release ZIP contextual-link controller does not filter rollback snapshots")

        if "'/media'" not in media_controller or "MediaReferenceReader::list_media()" not in media_controller:
            raise SystemExit("Release ZIP media list route is incomplete")
        if "MediaReferenceReader::read_media" not in media_controller:
            raise SystemExit("Release ZIP media detail route is incomplete")
        if "MediaReferenceController::register_routes();" not in plugin:
            raise SystemExit("Release ZIP does not register media reference discovery")
        if "'media_read'" not in manifest or "'media.read'" not in manifest:
            raise SystemExit("Release ZIP capability manifest omits media reference discovery")
        if "'raw_attachment_metadata_returned'" not in media_reader or "'fabricated_metadata'" not in media_reader:
            raise SystemExit("Release ZIP media reader lacks bounded non-fabrication policy")
        if "'_wp_attachment_image_alt'" not in media_reader or "MediaFingerprint::for_attachment" not in media_reader:
            raise SystemExit("Release ZIP media reader lacks alt-aware mutation-safe identity")
        if "'binary_mutation_supported'" not in media_reader:
            raise SystemExit("Release ZIP media reader does not declare binary mutation policy")
        if "'context_mutation_available'" not in media_reader or "'caption_truncated'" not in media_reader or "'description_truncated'" not in media_reader:
            raise SystemExit("Release ZIP media reader lacks bounded editorial-context discovery")

        media_alt_routes = (
            "'/media/(?P<media_id>\\d+)/alt/changes/preview'",
            "'/media/(?P<media_id>\\d+)/alt/changes/apply'",
            "'/media/alt/changes/(?P<operation_id>[a-f0-9\\-]{36})/rollback'",
        )
        if any(marker not in media_alt_controller for marker in media_alt_routes):
            raise SystemExit("Release ZIP media-alt routes are incomplete")
        if "MediaAltChangeController::register_routes();" not in plugin:
            raise SystemExit("Release ZIP does not register media-alt writes")
        if "'media_alt_write'" not in manifest:
            raise SystemExit("Release ZIP capability manifest omits media-alt writes")
        for marker in ("'media.alt.preview'", "'media.alt.apply'", "'media.alt.rollback'"):
            if marker not in manifest:
                raise SystemExit(f"Release ZIP media-alt operation missing: {marker}")
        for marker in (
            "_wp_attachment_image_alt",
            "MediaFingerprint::for_attachment",
            "allow_public_media",
            "idempotency_key",
            "seo_geo_manager_media_alt_rollback_stale",
        ):
            if marker not in media_alt_adapter:
                raise SystemExit(f"Release ZIP media-alt safety marker missing: {marker}")
        if "alt_exists" not in media_fingerprint or "_wp_attachment_image_alt" not in media_fingerprint:
            raise SystemExit("Release ZIP media fingerprint does not preserve exact alt-meta state")

        media_context_routes = (
            "'/media/(?P<media_id>\\d+)/context/changes/preview'",
            "'/media/(?P<media_id>\\d+)/context/changes/apply'",
            "'/media/context/changes/(?P<operation_id>[a-f0-9\\-]{36})/rollback'",
        )
        if any(marker not in media_context_controller for marker in media_context_routes):
            raise SystemExit("Release ZIP media-context routes are incomplete")
        if "MediaContextChangeController::register_routes();" not in plugin:
            raise SystemExit("Release ZIP does not register media-context writes")
        if "'media_context_write'" not in manifest:
            raise SystemExit("Release ZIP capability manifest omits media-context writes")
        for marker in (
            "'media.context.preview'",
            "'media.context.apply'",
            "'media.context.rollback'",
        ):
            if marker not in manifest:
                raise SystemExit(f"Release ZIP media-context operation missing: {marker}")
        for marker in (
            "expected_fingerprint",
            "allow_public_media",
            "idempotency_key",
            "title",
            "caption",
            "description",
            "seo_geo_manager_media_context_rollback_stale",
        ):
            if marker not in media_context_adapter:
                raise SystemExit(f"Release ZIP media-context safety marker missing: {marker}")
        if "public_operation" not in media_context_controller:
            raise SystemExit("Release ZIP media-context controller lacks public operation filtering")
        if "before_fields" not in media_context_controller or "after_fields" not in media_context_controller:
            raise SystemExit("Release ZIP media-context controller does not filter rollback snapshots")
        for marker in ("'caption'", "'description'", "'alt_exists'", "'_wp_attachment_image_alt'"):
            if marker not in media_fingerprint:
                raise SystemExit(f"Release ZIP media fingerprint omits context marker: {marker}")
        if "modified_gmt" in media_fingerprint:
            raise SystemExit("Release ZIP media fingerprint must not depend on volatile modified timestamps")

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
