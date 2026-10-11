#!/usr/bin/env python3
"""Run bounded SEO/GEO Manager C9 field acceptance against real WordPress sites.

The active proof authenticates with a temporary WordPress Application Password,
verifies the exact installed Manager tree identity, and optionally proves one
canonical-only Preview -> Apply -> readback -> Rollback cycle on a dedicated
draft. Revocation is proved in a separate invocation after the credential has
been deleted. The finalize phase combines two sites without contacting them.

Application Passwords are read from an environment variable and are never
written to evidence or diagnostic output.
"""

from __future__ import annotations

import argparse
import base64
import json
import os
from pathlib import Path
import sys
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import unquote, urlencode, urlparse
from urllib.request import Request, urlopen
import uuid

SCHEMA_VERSION = 1
REST_ROOT = "/wp-json/seo-geo-manager/v1"
PASSWORD_ENV = "SEO_GEO_C9_APP_PASSWORD"
STACKS = {"theme-native", "yoast"}


class FieldProofError(RuntimeError):
    """Expected, safe-to-report field proof failure."""


def normalize_base_url(value: str) -> str:
    parsed = urlparse(value.strip())
    if parsed.scheme.lower() != "https" or not parsed.netloc:
        raise FieldProofError("C9 real field acceptance requires an absolute HTTPS base URL.")
    if parsed.username or parsed.password:
        raise FieldProofError("Credentials must not be embedded in the base URL.")
    if parsed.params or parsed.query or parsed.fragment:
        raise FieldProofError("Base URL must identify only the WordPress site root, without params, query or fragment.")

    raw_segments = [segment for segment in parsed.path.split("/") if segment]
    for raw_segment in raw_segments:
        decoded = unquote(raw_segment)
        if decoded in {".", ".."} or "/" in decoded or "\\" in decoded:
            raise FieldProofError("Base URL contains an ambiguous WordPress site-root path.")

    path = "/" + "/".join(raw_segments) if raw_segments else ""
    return f"https://{parsed.netloc}{path}"


def validate_fingerprint(value: str) -> str:
    normalized = value.strip().lower()
    if len(normalized) != 64 or any(ch not in "0123456789abcdef" for ch in normalized):
        raise FieldProofError("Expected package fingerprint must be 64 lowercase hexadecimal characters.")
    return normalized


def application_password() -> str:
    password = os.environ.get(PASSWORD_ENV, "")
    if not password.strip():
        raise FieldProofError(f"Temporary Application Password is required in {PASSWORD_ENV}.")
    return password.strip()


def auth_header(username: str, password: str) -> str:
    token = base64.b64encode(f"{username}:{password}".encode("utf-8")).decode("ascii")
    return f"Basic {token}"


def request_json(
    base_url: str,
    username: str,
    password: str,
    path: str,
    *,
    method: str = "GET",
    query: dict[str, str] | None = None,
    payload: dict[str, Any] | None = None,
    expected_status: int = 200,
) -> dict[str, Any]:
    url = base_url + REST_ROOT + path
    if query:
        url += "?" + urlencode(query)

    body = None
    headers = {
        "Accept": "application/json",
        "Authorization": auth_header(username, password),
        "User-Agent": "seo-geo-manager-c9-field-acceptance/1",
    }
    if payload is not None:
        body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
        headers["Content-Type"] = "application/json"

    request = Request(url, data=body, headers=headers, method=method)
    try:
        with urlopen(request, timeout=30) as response:  # noqa: S310 - HTTPS enforced above.
            status = int(response.status)
            raw = response.read()
    except HTTPError as error:
        status = int(error.code)
        raw = error.read()
    except URLError as error:
        raise FieldProofError(f"HTTPS request failed: {type(error.reason).__name__}.") from None

    if status != expected_status:
        raise FieldProofError(f"Manager endpoint returned HTTP {status}; expected HTTP {expected_status}.")
    if expected_status == 401:
        return {"http_status": status}

    try:
        decoded = json.loads(raw.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError):
        raise FieldProofError("Manager endpoint did not return valid JSON.") from None
    if not isinstance(decoded, dict):
        raise FieldProofError("Manager endpoint returned an unexpected JSON shape.")
    return decoded


def seo_paths(stack: str, resource_id: int) -> tuple[str, str, str, str]:
    if stack == "theme-native":
        base = f"/content/{resource_id}/seo"
        rollback = "/content/seo/changes/{operation_id}/rollback"
    elif stack == "yoast":
        base = f"/content/{resource_id}/seo/providers/yoast"
        rollback = "/content/seo/providers/yoast/changes/{operation_id}/rollback"
    else:
        raise FieldProofError("Unsupported C9 stack.")
    return base, base + "/changes/preview", base + "/changes/apply", rollback


def exact_package(preflight: dict[str, Any], version: str, fingerprint: str) -> dict[str, Any]:
    package = preflight.get("package")
    checks = preflight.get("checks")
    if not isinstance(package, dict) or not isinstance(checks, dict):
        raise FieldProofError("C9 preflight omitted package/check evidence.")
    if checks.get("exact_manager_package") is not True:
        raise FieldProofError("Remote Manager package does not match the expected field candidate.")
    if package.get("version") != version or package.get("fingerprint") != fingerprint:
        raise FieldProofError("Remote package identity disagrees with the requested candidate.")
    if package.get("algorithm") != "sha256-tree-v1":
        raise FieldProofError("Remote package identity uses an unsupported fingerprint algorithm.")
    return package


def require_remote_ready(preflight: dict[str, Any]) -> None:
    if preflight.get("status") != "ready-for-remote-proof":
        blocking = preflight.get("blocking_checks")
        safe_blocking = blocking if isinstance(blocking, list) else []
        raise FieldProofError("C9 preflight is blocked: " + ",".join(str(item) for item in safe_blocking))
    checks = preflight.get("checks")
    if not isinstance(checks, dict) or not all(value is True for value in checks.values()):
        raise FieldProofError("C9 preflight did not prove every prerequisite.")


def proof_canonical(base_url: str) -> str:
    return f"{base_url}/?seo-geo-c9-proof={uuid.uuid4().hex}"


def reversible_draft_proof(
    *,
    base_url: str,
    username: str,
    password: str,
    stack: str,
    resource_id: int,
    environment_fingerprint: str,
) -> dict[str, Any]:
    inspect_path, preview_path, apply_path, rollback_template = seo_paths(stack, resource_id)
    before = request_json(base_url, username, password, inspect_path)

    resource = before.get("resource")
    values = before.get("values")
    fingerprint = before.get("fingerprint")
    if not isinstance(resource, dict) or resource.get("status") != "draft":
        raise FieldProofError("C9 mutation proof requires a dedicated resource with status=draft.")
    if not isinstance(values, dict) or not isinstance(fingerprint, str) or not fingerprint:
        raise FieldProofError("SEO inspection omitted mutation-safe values/fingerprint.")

    desired = dict(values)
    desired["canonical"] = proof_canonical(base_url)
    if desired == values:
        raise FieldProofError("C9 proof could not create a distinct canonical state.")

    payload: dict[str, Any] = {
        "values": desired,
        "expected_fingerprint": fingerprint,
        "allow_published_target": False,
    }
    preview = request_json(
        base_url,
        username,
        password,
        preview_path,
        method="POST",
        payload=payload,
    )
    change = preview.get("change")
    if preview.get("mode") != "preview" or preview.get("has_changes") is not True or not isinstance(change, dict):
        raise FieldProofError("SEO Preview did not return a reversible change plan.")
    if change.get("before") != values or change.get("after") != desired:
        raise FieldProofError("SEO Preview readback does not match the requested bounded change.")

    idempotency_key = f"c9-{uuid.uuid4()}"
    apply_payload = dict(payload)
    apply_payload["idempotency_key"] = idempotency_key
    apply_payload["environment_fingerprint"] = environment_fingerprint
    applied = request_json(
        base_url,
        username,
        password,
        apply_path,
        method="POST",
        payload=apply_payload,
    )
    operation_id = applied.get("operation_id")
    if applied.get("status") != "applied" or not isinstance(operation_id, str) or not operation_id:
        raise FieldProofError("SEO Apply did not produce an applied operation.")

    after = request_json(base_url, username, password, inspect_path)
    if after.get("values") != desired:
        raise FieldProofError("SEO Apply readback differs from the Preview-approved state.")

    rollback_path = rollback_template.format(operation_id=operation_id)
    rolled_back = request_json(
        base_url,
        username,
        password,
        rollback_path,
        method="POST",
        payload={"environment_fingerprint": environment_fingerprint},
    )
    if rolled_back.get("status") != "rolled-back":
        raise FieldProofError("SEO rollback did not report rolled-back state.")

    restored = request_json(base_url, username, password, inspect_path)
    if restored.get("values") != values or restored.get("fingerprint") != fingerprint:
        raise FieldProofError("SEO rollback did not restore the exact original draft state.")

    return {
        "resource_id": resource_id,
        "resource_status": "draft",
        "adapter": before.get("adapter"),
        "preview": True,
        "apply": True,
        "readback_exact": True,
        "rollback": True,
        "restored_exact": True,
        "operation_id": operation_id,
    }


def active_proof(args: argparse.Namespace) -> dict[str, Any]:
    base_url = normalize_base_url(args.base_url)
    fingerprint = validate_fingerprint(args.expected_package_fingerprint)
    stack = args.stack
    if stack not in STACKS:
        raise FieldProofError("Stack must be theme-native or yoast.")
    password = application_password()

    preflight = request_json(
        base_url,
        args.username,
        password,
        "/field/c9/preflight",
        query={
            "expected_stack": stack,
            "expected_manager_version": args.expected_version,
            "expected_package_fingerprint": fingerprint,
        },
    )
    package = exact_package(preflight, args.expected_version, fingerprint)
    require_remote_ready(preflight)

    environment = preflight.get("environment")
    if not isinstance(environment, dict) or not isinstance(environment.get("fingerprint"), str):
        raise FieldProofError("C9 preflight omitted environment binding evidence.")

    mutation: dict[str, Any] | None = None
    if args.resource_id is not None:
        if args.resource_id < 1:
            raise FieldProofError("Resource ID must be a positive integer.")
        mutation = reversible_draft_proof(
            base_url=base_url,
            username=args.username,
            password=password,
            stack=stack,
            resource_id=args.resource_id,
            environment_fingerprint=environment["fingerprint"],
        )

    return {
        "schema_version": SCHEMA_VERSION,
        "phase": "active",
        "site": base_url,
        "stack": stack,
        "manager_version": package.get("version"),
        "package_identity": {
            "algorithm": package.get("algorithm"),
            "fingerprint": package.get("fingerprint"),
            "file_count": package.get("file_count"),
        },
        "application_password_authentication": True,
        "least_privilege_principal": True,
        "preflight_ready": True,
        "reversible_draft_seo_operation": mutation is not None,
        "mutation": mutation,
        "application_password_revocation": False,
        "field_acceptance_complete": False,
        "secrets_recorded": False,
    }


def revoked_proof(args: argparse.Namespace) -> dict[str, Any]:
    base_url = normalize_base_url(args.base_url)
    fingerprint = validate_fingerprint(args.expected_package_fingerprint)
    password = application_password()
    response = request_json(
        base_url,
        args.username,
        password,
        "/field/c9/preflight",
        query={
            "expected_stack": args.stack,
            "expected_manager_version": args.expected_version,
            "expected_package_fingerprint": fingerprint,
        },
        expected_status=401,
    )
    if response.get("http_status") != 401:
        raise FieldProofError("Revoked Application Password was not rejected with HTTP 401.")

    return {
        "schema_version": SCHEMA_VERSION,
        "phase": "revoked",
        "site": base_url,
        "stack": args.stack,
        "manager_version": args.expected_version,
        "package_identity": {
            "algorithm": "sha256-tree-v1",
            "fingerprint": fingerprint,
        },
        "application_password_revocation": True,
        "authentication_rejected_http_status": 401,
        "field_acceptance_complete": False,
        "secrets_recorded": False,
    }


def load_evidence(path: str) -> dict[str, Any]:
    try:
        data = json.loads(Path(path).read_text(encoding="utf-8"))
    except (OSError, UnicodeDecodeError, json.JSONDecodeError) as error:
        raise FieldProofError(f"Could not read evidence file {Path(path).name}: {type(error).__name__}.") from None
    if not isinstance(data, dict):
        raise FieldProofError(f"Evidence file {Path(path).name} must contain one JSON object.")
    return data


def finalize_proof(active_paths: list[str], revoked_paths: list[str]) -> dict[str, Any]:
    if len(active_paths) != 2 or len(revoked_paths) != 2:
        raise FieldProofError("Finalize requires exactly two active and two revoked evidence files.")

    active = [load_evidence(path) for path in active_paths]
    revoked = [load_evidence(path) for path in revoked_paths]
    active_by_site = {item.get("site"): item for item in active if isinstance(item.get("site"), str)}
    revoked_by_site = {item.get("site"): item for item in revoked if isinstance(item.get("site"), str)}
    if len(active_by_site) != 2 or set(active_by_site) != set(revoked_by_site):
        raise FieldProofError("Active and revoked evidence must describe the same two distinct sites.")

    stacks = {item.get("stack") for item in active}
    if stacks != STACKS:
        raise FieldProofError("C9 requires one Theme-native site and one Yoast site.")

    versions = {item.get("manager_version") for item in active}
    identities = {
        (
            item.get("package_identity", {}).get("algorithm") if isinstance(item.get("package_identity"), dict) else None,
            item.get("package_identity", {}).get("fingerprint") if isinstance(item.get("package_identity"), dict) else None,
        )
        for item in active
    }
    if len(versions) != 1 or len(identities) != 1:
        raise FieldProofError("The two sites do not prove the same Manager package identity.")
    algorithm, fingerprint = next(iter(identities))
    if algorithm != "sha256-tree-v1" or not isinstance(fingerprint, str) or len(fingerprint) != 64:
        raise FieldProofError("Final evidence contains an invalid package fingerprint.")

    site_results: list[dict[str, Any]] = []
    for site, active_item in sorted(active_by_site.items()):
        revoked_item = revoked_by_site[site]
        checks = {
            "application_password_authentication": active_item.get("application_password_authentication") is True,
            "preflight_ready": active_item.get("preflight_ready") is True,
            "reversible_draft_seo_operation": active_item.get("reversible_draft_seo_operation") is True,
            "application_password_revocation": revoked_item.get("application_password_revocation") is True,
            "revoked_http_401": revoked_item.get("authentication_rejected_http_status") == 401,
            "secrets_not_recorded": active_item.get("secrets_recorded") is False and revoked_item.get("secrets_recorded") is False,
        }
        if not all(checks.values()):
            failed = [name for name, passed in checks.items() if not passed]
            raise FieldProofError(f"Site evidence is incomplete for {site}: {','.join(failed)}")
        if active_item.get("stack") != revoked_item.get("stack"):
            raise FieldProofError(f"Stack changed between active/revoked evidence for {site}.")
        if active_item.get("manager_version") != revoked_item.get("manager_version"):
            raise FieldProofError(f"Manager version changed between active/revoked evidence for {site}.")
        revoked_identity = revoked_item.get("package_identity")
        if not isinstance(revoked_identity, dict) or revoked_identity.get("fingerprint") != fingerprint:
            raise FieldProofError(f"Package fingerprint changed between active/revoked evidence for {site}.")
        site_results.append({"site": site, "stack": active_item.get("stack"), "checks": checks})

    return {
        "schema_version": SCHEMA_VERSION,
        "phase": "final",
        "field_acceptance_complete": True,
        "same_manager_package_on_both_sites": True,
        "manager_version": next(iter(versions)),
        "package_identity": {"algorithm": algorithm, "fingerprint": fingerprint},
        "sites": site_results,
        "required_stacks": sorted(STACKS),
        "client_specific_fork": False,
        "secrets_recorded": False,
    }


def emit(evidence: dict[str, Any], output: str | None) -> None:
    rendered = json.dumps(evidence, indent=2, sort_keys=True) + "\n"
    if output:
        Path(output).write_text(rendered, encoding="utf-8")
    sys.stdout.write(rendered)


def add_remote_arguments(parser: argparse.ArgumentParser) -> None:
    parser.add_argument("--base-url", required=True)
    parser.add_argument("--username", required=True)
    parser.add_argument("--stack", required=True, choices=sorted(STACKS))
    parser.add_argument("--expected-version", required=True)
    parser.add_argument("--expected-package-fingerprint", required=True)
    parser.add_argument("--output")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="command", required=True)

    active = sub.add_parser("active", help="Prove authenticated C9 preflight and optional draft rollback.")
    add_remote_arguments(active)
    active.add_argument("--resource-id", type=int, help="Dedicated draft resource for reversible SEO proof.")

    revoked = sub.add_parser("revoked", help="After deleting the Application Password, require HTTP 401.")
    add_remote_arguments(revoked)

    final = sub.add_parser("finalize", help="Combine two active and two revoked site evidence files.")
    final.add_argument("--active", nargs=2, required=True, metavar=("THEME_ACTIVE", "YOAST_ACTIVE"))
    final.add_argument("--revoked", nargs=2, required=True, metavar=("THEME_REVOKED", "YOAST_REVOKED"))
    final.add_argument("--output")
    return parser


def main() -> int:
    args = build_parser().parse_args()
    try:
        if args.command == "active":
            evidence = active_proof(args)
        elif args.command == "revoked":
            evidence = revoked_proof(args)
        else:
            evidence = finalize_proof(args.active, args.revoked)
        emit(evidence, getattr(args, "output", None))
        return 0
    except FieldProofError as error:
        sys.stderr.write(f"C9 field acceptance failed: {error}\n")
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
