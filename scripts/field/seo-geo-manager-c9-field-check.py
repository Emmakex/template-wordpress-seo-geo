#!/usr/bin/env python3
"""Run secret-safe C9 field evidence checks against one real Manager installation."""

from __future__ import annotations

import argparse
import base64
import json
import os
from pathlib import Path
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid

USER_ENV = "SEO_GEO_MANAGER_USER"
PASSWORD_ENV = "SEO_GEO_MANAGER_APP_PASSWORD"


class FieldCheckError(RuntimeError):
    pass


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", required=True)
    parser.add_argument("--expected-stack", choices=("theme-native", "yoast"), required=True)
    parser.add_argument("--expected-version", required=True)
    parser.add_argument("--expected-package-fingerprint", required=True)
    parser.add_argument("--mode", choices=("preflight", "exercise", "revoked"), default="preflight")
    parser.add_argument("--resource-id", type=int)
    parser.add_argument("--evidence-output", type=Path)
    return parser.parse_args()


def credential_header() -> str:
    username = os.environ.get(USER_ENV, "").strip()
    password = os.environ.get(PASSWORD_ENV, "")
    if not username or not password:
        raise FieldCheckError(
            f"Set {USER_ENV} and {PASSWORD_ENV}; credentials are read only from environment variables."
        )
    token = base64.b64encode(f"{username}:{password}".encode("utf-8")).decode("ascii")
    return f"Basic {token}"


def normalized_base_url(value: str) -> str:
    value = value.strip().rstrip("/")
    parsed = urllib.parse.urlparse(value)
    if parsed.scheme != "https" or not parsed.netloc:
        raise FieldCheckError("C9 field acceptance requires an absolute HTTPS base URL.")
    if parsed.username or parsed.password:
        raise FieldCheckError("Do not embed credentials in --base-url.")
    return value


def request_json(
    base_url: str,
    path: str,
    auth_header: str,
    *,
    method: str = "GET",
    payload: dict[str, object] | None = None,
    expected_status: int = 200,
) -> tuple[int, object]:
    url = f"{base_url}/wp-json/seo-geo-manager/v1{path}"
    data = None
    headers = {
        "Accept": "application/json",
        "Authorization": auth_header,
        "User-Agent": "seo-geo-manager-c9-field-check/1",
    }
    if payload is not None:
        data = json.dumps(payload, separators=(",", ":")).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30, context=ssl.create_default_context()) as response:
            status = int(response.status)
            body = response.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        status = int(exc.code)
        body = exc.read().decode("utf-8", errors="replace")
    except urllib.error.URLError as exc:
        raise FieldCheckError(f"Field request failed: {exc.reason}") from exc

    if status != expected_status:
        raise FieldCheckError(f"Expected HTTP {expected_status} from {path}, received HTTP {status}.")

    if not body:
        return status, None
    try:
        return status, json.loads(body)
    except json.JSONDecodeError as exc:
        raise FieldCheckError(f"Endpoint {path} returned non-JSON content.") from exc


def preflight_path(args: argparse.Namespace) -> str:
    query = urllib.parse.urlencode(
        {
            "expected_stack": args.expected_stack,
            "expected_manager_version": args.expected_version,
            "expected_package_fingerprint": args.expected_package_fingerprint.lower(),
        }
    )
    return f"/field/c9/preflight?{query}"


def require_mapping(value: object, label: str) -> dict[str, object]:
    if not isinstance(value, dict):
        raise FieldCheckError(f"{label} response is not a JSON object.")
    return value


def build_changed_values(stack: str, before: dict[str, object], base_url: str) -> dict[str, object]:
    after = dict(before)
    if stack == "theme-native":
        canonical = before.get("canonical")
        after["canonical"] = None if isinstance(canonical, str) and canonical else f"{base_url}/c9-manager-acceptance-{uuid.uuid4().hex}/"
        return after

    nofollow = before.get("nofollow")
    if not isinstance(nofollow, bool):
        raise FieldCheckError("Yoast bounded state does not expose boolean nofollow.")
    after["nofollow"] = not nofollow
    return after


def endpoint_paths(stack: str, resource_id: int) -> tuple[str, str, str, str]:
    if stack == "theme-native":
        base = f"/content/{resource_id}/seo"
        return (
            base,
            f"{base}/changes/preview",
            f"{base}/changes/apply",
            "/content/seo/changes/{operation_id}/rollback",
        )

    base = f"/content/{resource_id}/seo/providers/yoast"
    return (
        base,
        f"{base}/changes/preview",
        f"{base}/changes/apply",
        "/content/seo/providers/yoast/changes/{operation_id}/rollback",
    )


def write_evidence(payload: dict[str, object], output: Path | None) -> None:
    rendered = json.dumps(payload, indent=2, sort_keys=True, ensure_ascii=False) + "\n"
    if output is None:
        sys.stdout.write(rendered)
        return
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(rendered, encoding="utf-8")
    print(f"C9 evidence written to {output}")


def main() -> int:
    args = parse_args()
    base_url = normalized_base_url(args.base_url)
    fingerprint = args.expected_package_fingerprint.strip().lower()
    if len(fingerprint) != 64 or any(char not in "0123456789abcdef" for char in fingerprint):
        raise FieldCheckError("--expected-package-fingerprint must be a 64-character lowercase/uppercase SHA-256 value.")
    auth_header = credential_header()
    path = preflight_path(args)

    if args.mode == "revoked":
        status, _ = request_json(base_url, path, auth_header, expected_status=401)
        write_evidence(
            {
                "schema_version": 1,
                "phase": "application-password-revocation",
                "base_url": base_url,
                "expected_stack": args.expected_stack,
                "expected_version": args.expected_version,
                "expected_package_fingerprint": fingerprint,
                "http_status": status,
                "revocation_proven": True,
                "secrets_recorded": False,
            },
            args.evidence_output,
        )
        return 0

    _, raw_preflight = request_json(base_url, path, auth_header)
    preflight = require_mapping(raw_preflight, "C9 preflight")
    if preflight.get("status") != "ready-for-remote-proof":
        raise FieldCheckError(
            "C9 runtime preflight is not ready: "
            + json.dumps(preflight.get("blocking_checks", []), ensure_ascii=False)
        )

    evidence: dict[str, object] = {
        "schema_version": 1,
        "phase": "authenticated-preflight" if args.mode == "preflight" else "authenticated-reversible-operation",
        "base_url": base_url,
        "expected_stack": args.expected_stack,
        "expected_version": args.expected_version,
        "expected_package_fingerprint": fingerprint,
        "preflight_status": preflight.get("status"),
        "package": preflight.get("package"),
        "environment": preflight.get("environment"),
        "principal": preflight.get("principal"),
        "seo_authority": preflight.get("seo_authority"),
        "application_password_authentication": True,
        "secrets_recorded": False,
    }

    if args.mode == "preflight":
        write_evidence(evidence, args.evidence_output)
        return 0

    if args.resource_id is None or args.resource_id < 1:
        raise FieldCheckError("--mode exercise requires --resource-id for a dedicated draft test resource.")

    read_path, preview_path, apply_path, rollback_template = endpoint_paths(args.expected_stack, args.resource_id)
    _, raw_before = request_json(base_url, read_path, auth_header)
    before = require_mapping(raw_before, "SEO inspection")
    resource = require_mapping(before.get("resource"), "SEO resource")
    if resource.get("status") != "draft":
        raise FieldCheckError("C9 reversible exercise is restricted to a dedicated draft resource.")
    values = require_mapping(before.get("values"), "SEO values")
    before_fingerprint = before.get("fingerprint")
    if not isinstance(before_fingerprint, str) or not before_fingerprint:
        raise FieldCheckError("SEO inspection did not return a fingerprint.")

    changed_values = build_changed_values(args.expected_stack, values, base_url)
    environment = require_mapping(preflight.get("environment"), "C9 environment")
    environment_fingerprint = environment.get("fingerprint")
    if not isinstance(environment_fingerprint, str) or not environment_fingerprint:
        raise FieldCheckError("C9 preflight did not return the environment fingerprint.")
    operation_key = f"c9-field-{uuid.uuid4()}"
    mutation_payload: dict[str, object] = {
        "values": changed_values,
        "expected_fingerprint": before_fingerprint,
        "idempotency_key": operation_key,
        "environment_fingerprint": environment_fingerprint,
    }

    _, raw_preview = request_json(base_url, preview_path, auth_header, method="POST", payload=mutation_payload)
    preview = require_mapping(raw_preview, "SEO Preview")
    if preview.get("has_changes") is not True:
        raise FieldCheckError("C9 SEO Preview did not report a change.")

    _, raw_applied = request_json(base_url, apply_path, auth_header, method="POST", payload=mutation_payload)
    applied = require_mapping(raw_applied, "SEO Apply")
    operation_id = applied.get("operation_id")
    if applied.get("status") != "applied" or not isinstance(operation_id, str) or not operation_id:
        raise FieldCheckError("C9 SEO Apply did not return an applied operation ID.")

    _, raw_after = request_json(base_url, read_path, auth_header)
    after = require_mapping(raw_after, "SEO readback")
    if after.get("values") != changed_values:
        raise FieldCheckError("C9 SEO readback does not match the applied bounded state.")

    rollback_path = rollback_template.format(operation_id=urllib.parse.quote(operation_id, safe=""))
    _, raw_rollback = request_json(
        base_url,
        rollback_path,
        auth_header,
        method="POST",
        payload={"environment_fingerprint": environment_fingerprint},
    )
    rollback = require_mapping(raw_rollback, "SEO rollback")
    if rollback.get("status") != "rolled-back":
        raise FieldCheckError("C9 SEO rollback did not report rolled-back state.")

    _, raw_restored = request_json(base_url, read_path, auth_header)
    restored = require_mapping(raw_restored, "SEO restored readback")
    if restored.get("values") != values or restored.get("fingerprint") != before_fingerprint:
        raise FieldCheckError("C9 SEO rollback did not restore the exact initial bounded state.")

    evidence.update(
        {
            "resource_id": args.resource_id,
            "resource_status": "draft",
            "operation_id": operation_id,
            "preview": True,
            "apply": True,
            "exact_readback": True,
            "rollback": True,
            "exact_restoration": True,
            "revocation_proof_pending": True,
        }
    )
    write_evidence(evidence, args.evidence_output)
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except FieldCheckError as exc:
        print(f"C9 field check failed: {exc}", file=sys.stderr)
        raise SystemExit(1)
