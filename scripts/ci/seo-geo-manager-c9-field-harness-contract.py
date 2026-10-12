#!/usr/bin/env python3
"""Offline contract for the external SEO/GEO Manager C9 field harness."""

from __future__ import annotations

import importlib.util
import json
from pathlib import Path
import tempfile

ROOT = Path(__file__).resolve().parents[2]
HARNESS = ROOT / "scripts" / "field" / "seo-geo-manager-c9-field-acceptance.py"
PLUGIN = ROOT / "packages" / "seo-geo-manager" / "src" / "Plugin.php"
CONTROLLER = ROOT / "packages" / "seo-geo-manager" / "src" / "Rest" / "C9FieldPreflightController.php"


def load_harness():
    spec = importlib.util.spec_from_file_location("seo_geo_manager_c9_field", HARNESS)
    if spec is None or spec.loader is None:
        raise SystemExit("Could not load C9 field harness")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def expect_failure(callable_, message: str) -> None:
    try:
        callable_()
    except Exception as error:  # Contract deliberately catches the harness' typed failure.
        if error.__class__.__name__ != "FieldProofError":
            raise
        return
    raise SystemExit(message)


def evidence(site: str, stack: str, fingerprint: str, phase: str) -> dict:
    common = {
        "schema_version": 1,
        "phase": phase,
        "site": site,
        "stack": stack,
        "manager_version": "0.3.49-test",
        "package_identity": {
            "algorithm": "sha256-tree-v1",
            "fingerprint": fingerprint,
        },
        "field_acceptance_complete": False,
        "secrets_recorded": False,
    }
    if phase == "active":
        common.update(
            {
                "application_password_authentication": True,
                "least_privilege_principal": True,
                "preflight_ready": True,
                "reversible_draft_seo_operation": True,
                "application_password_revocation": False,
            }
        )
    else:
        common.update(
            {
                "application_password_revocation": True,
                "authentication_rejected_http_status": 401,
            }
        )
    return common


def main() -> int:
    module = load_harness()
    fingerprint = "b" * 64

    assert module.normalize_base_url("https://example.test/") == "https://example.test"
    assert module.normalize_base_url("https://example.test/nuevaweb/") == "https://example.test/nuevaweb"
    assert module.normalize_base_url("https://example.test//nuevaweb//") == "https://example.test/nuevaweb"
    expect_failure(
        lambda: module.normalize_base_url("http://example.test"),
        "C9 harness accepted non-HTTPS field URL",
    )
    expect_failure(
        lambda: module.normalize_base_url("https://user:secret@example.test"),
        "C9 harness accepted credentials embedded in URL",
    )
    expect_failure(
        lambda: module.normalize_base_url("https://example.test/nuevaweb?x=1"),
        "C9 harness accepted query in WordPress site root",
    )
    expect_failure(
        lambda: module.normalize_base_url("https://example.test/nuevaweb#fragment"),
        "C9 harness accepted fragment in WordPress site root",
    )
    expect_failure(
        lambda: module.normalize_base_url("https://example.test/a/../nuevaweb"),
        "C9 harness accepted ambiguous dot-segment WordPress site root",
    )
    assert module.validate_fingerprint(fingerprint) == fingerprint
    expect_failure(
        lambda: module.validate_fingerprint("bad"),
        "C9 harness accepted invalid package fingerprint",
    )

    with tempfile.TemporaryDirectory() as temporary:
        directory = Path(temporary)
        active_paths = []
        revoked_paths = []
        for index, (site, stack) in enumerate(
            (
                ("https://example.test/nuevaweb", "theme-native"),
                ("https://example.test", "yoast"),
            )
        ):
            active = directory / f"active-{index}.json"
            revoked = directory / f"revoked-{index}.json"
            active.write_text(json.dumps(evidence(site, stack, fingerprint, "active")), encoding="utf-8")
            revoked.write_text(json.dumps(evidence(site, stack, fingerprint, "revoked")), encoding="utf-8")
            active_paths.append(str(active))
            revoked_paths.append(str(revoked))

        final = module.finalize_proof(active_paths, revoked_paths)
        assert final["field_acceptance_complete"] is True
        assert final["same_manager_package_on_both_sites"] is True
        assert final["client_specific_fork"] is False
        assert final["package_identity"]["fingerprint"] == fingerprint
        assert {item["stack"] for item in final["sites"]} == {"theme-native", "yoast"}
        assert {item["site"] for item in final["sites"]} == {
            "https://example.test",
            "https://example.test/nuevaweb",
        }

        mismatched = evidence("https://example.test", "yoast", "c" * 64, "active")
        Path(active_paths[1]).write_text(json.dumps(mismatched), encoding="utf-8")
        expect_failure(
            lambda: module.finalize_proof(active_paths, revoked_paths),
            "C9 finalize accepted different Manager package fingerprints",
        )

    plugin_source = PLUGIN.read_text(encoding="utf-8")
    controller_source = CONTROLLER.read_text(encoding="utf-8")
    if "C9FieldPreflightController::register_routes();" not in plugin_source:
        raise SystemExit("Manager plugin does not register C9 preflight route")
    for marker in (
        "'/field/c9/preflight'",
        "expected_stack",
        "expected_manager_version",
        "expected_package_fingerprint",
    ):
        if marker not in controller_source:
            raise SystemExit(f"C9 preflight controller contract missing: {marker}")

    print(
        json.dumps(
            {
                "ok": True,
                "https_required": True,
                "credentials_not_in_url": True,
                "subdirectory_site_roots": True,
                "same_host_distinct_site_roots": True,
                "two_distinct_sites": True,
                "theme_and_yoast_required": True,
                "same_package_required": True,
                "active_and_revoked_evidence_required": True,
                "route_wired": True,
            },
            separators=(",", ":"),
        )
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
