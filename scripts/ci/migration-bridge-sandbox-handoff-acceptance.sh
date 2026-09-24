#!/usr/bin/env bash
# Bounded dependency-detail + sandbox handoff acceptance.

printf '[smoke] Checking dependency detail and sandbox handoff manifest.\n'

HANDOFF_RUNNER="$TMP_DIR/migration-sandbox-handoff-runner.php"
cat >"$HANDOFF_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Operator\OperatorStatus;
use SeoGeo\MigrationBridge\Sandbox\SandboxHandoffManifest;

$status   = ( new OperatorStatus() )->snapshot();
$manifest = ( new SandboxHandoffManifest() )->build();

echo wp_json_encode(
	array(
		'status'   => $status,
		'manifest' => $manifest,
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$HANDOFF_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-sandbox-handoff-runner.php \
  || fail_smoke "sandbox-handoff-runner-copy" "Could not copy sandbox handoff runner" "runner copied" "docker cp failed"

if ! HANDOFF_JSON="$(wp_cli eval-file /var/www/html/wp-content/migration-sandbox-handoff-runner.php 2>"$TMP_DIR/sandbox-handoff.stderr")"; then
  HANDOFF_ERROR="$(tr -d '\r' <"$TMP_DIR/sandbox-handoff.stderr" | head -c 900)"
  fail_smoke "sandbox-handoff-runner" "Sandbox handoff runner failed" "JSON handoff report" "${HANDOFF_ERROR:-wp eval-file failed}"
fi

printf '%s' "$HANDOFF_JSON" >"$TMP_DIR/sandbox-handoff.json"

if ! HANDOFF_ASSERTION="$(python3 - "$TMP_DIR/sandbox-handoff.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

status = payload["status"]
manifest = payload["manifest"]

components = status["dependency_plan"]["components"]
assert isinstance(components, list)
assert len(components) > 0
assert all("component_id" in row for row in components)
assert all("classification" in row for row in components)
assert all("reason" in row for row in components)

assert manifest["mode"] == "seo-geo-sandbox-handoff"
assert manifest["baseline"]["available"] is True
assert manifest["target"]["theme_stylesheet"] == "seo-geo-theme"
assert manifest["target"]["release_version"] == "0.1.0"
assert manifest["sandbox"]["requires_distinct_origin"] is True
assert manifest["sandbox"]["requires_marker"] == "SEO_GEO_MIGRATION_SANDBOX"
assert manifest["sandbox"]["production_mutation_allowed"] is False

safety = manifest["safety"]
for key in [
    "post_bodies_exported",
    "builder_payloads_exported",
    "credentials_exported",
    "option_values_exported",
    "raw_database_exported",
    "raw_uploads_exported",
    "customer_data_exported",
    "baseline_body_content",
]:
    assert safety[key] is False

serialized = json.dumps(manifest).lower()
for forbidden in ["post_content", "password", "secret", "api_key", "private_key"]:
    assert forbidden not in serialized

print("ok")
PY
)"; then
  fail_smoke "sandbox-handoff-contract" "Sandbox handoff contract is invalid" "bounded dependency detail + privacy-safe manifest" "${HANDOFF_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Sandbox handoff OK: dependency detail bounded; manifest excludes private content/credentials and requires isolated sandbox.\n'
