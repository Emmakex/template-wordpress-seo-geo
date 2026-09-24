#!/usr/bin/env bash
# Phase 10E bounded UNKNOWN dependency-review acceptance.

printf '[smoke] Checking bounded UNKNOWN dependency review planning.\n'

REVIEW_RUNNER="$TMP_DIR/migration-dependency-review-runner.php"
cat >"$REVIEW_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\DependencyGraphBuilder;
use SeoGeo\MigrationBridge\Operator\OperatorStatus;
use SeoGeo\MigrationBridge\Review\DependencyReviewStore;
use SeoGeo\MigrationBridge\Sandbox\SandboxHandoffManifest;
use SeoGeo\MigrationBridge\SiteAnalyzer;

$component_id = 'plugin:legacy-site-fixture/legacy-site-fixture.php';
$store        = new DependencyReviewStore();
$before       = get_option( DependencyReviewStore::OPTION_NAME, null );

$analysis          = ( new SiteAnalyzer() )->analyze();
$baseline          = ( new BaselineSnapshotStore() )->latest();
$baseline_snapshot = is_array( $baseline['snapshot'] ?? null ) ? $baseline['snapshot'] : null;
$raw_graph          = ( new DependencyGraphBuilder() )->build( $analysis, $baseline_snapshot );

$raw_component = null;
foreach ( $raw_graph['components'] ?? array() as $component ) {
	if ( is_array( $component ) && $component_id === ( $component['component_id'] ?? null ) ) {
		$raw_component = $component;
		break;
	}
}

if ( ! is_array( $raw_component ) || 'UNKNOWN' !== ( $raw_component['classification'] ?? null ) ) {
	throw new RuntimeException( 'Expected live UNKNOWN fixture component is unavailable.' );
}

if ( ! $store->save( $component_id, 'KEEP' ) ) {
	throw new RuntimeException( 'Could not save bounded review decision.' );
}

$status   = ( new OperatorStatus() )->snapshot();
$manifest = ( new SandboxHandoffManifest() )->build();
$stored   = get_option( DependencyReviewStore::OPTION_NAME, null );

$status_component = null;
foreach ( $status['dependency_plan']['components'] ?? array() as $component ) {
	if ( is_array( $component ) && $component_id === ( $component['component_id'] ?? null ) ) {
		$status_component = $component;
		break;
	}
}

$manifest_component = null;
foreach ( $manifest['dependencies']['components'] ?? array() as $component ) {
	if ( is_array( $component ) && $component_id === ( $component['component_id'] ?? null ) ) {
		$manifest_component = $component;
		break;
	}
}

$store->clear( $component_id );

echo wp_json_encode(
	array(
		'raw_component'      => $raw_component,
		'status_component'   => $status_component,
		'manifest_component' => $manifest_component,
		'status_review'      => array(
			'reviewed_unknown_count'   => $status['dependency_plan']['reviewed_unknown_count'] ?? null,
			'unreviewed_unknown_count' => $status['dependency_plan']['unreviewed_unknown_count'] ?? null,
			'review_complete'          => $status['dependency_plan']['review_complete'] ?? null,
		),
		'manifest_review'    => $manifest['dependencies']['review'] ?? null,
		'manifest_safety'    => $manifest['safety'] ?? null,
		'stored'             => $stored,
		'after_clear'        => get_option( DependencyReviewStore::OPTION_NAME, null ),
		'before'             => $before,
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$REVIEW_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-dependency-review-runner.php \
  || fail_smoke "dependency-review-runner-copy" "Could not copy dependency review runner" "runner copied" "docker cp failed"

if ! REVIEW_JSON="$(wp_cli eval-file /var/www/html/wp-content/migration-dependency-review-runner.php 2>"$TMP_DIR/dependency-review.stderr")"; then
  REVIEW_ERROR="$(tr -d '\r' <"$TMP_DIR/dependency-review.stderr" | head -c 900)"
  fail_smoke "dependency-review-runner" "Dependency review runner failed" "JSON review report" "${REVIEW_ERROR:-wp eval-file failed}"
fi

printf '%s' "$REVIEW_JSON" >"$TMP_DIR/migration-dependency-review.json"

if ! REVIEW_ASSERTION="$(python3 - "$TMP_DIR/migration-dependency-review.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

raw = payload["raw_component"]
status = payload["status_component"]
manifest = payload["manifest_component"]

assert raw["classification"] == "UNKNOWN"
assert raw["reason"] == "plugin-not-yet-mapped-to-known-authority"
assert status["classification"] == "UNKNOWN"
assert status["reviewed"] is True
assert status["review_decision"] == "KEEP"
assert status["review_reason"] == "operator-review-retain-operational"
assert isinstance(status["reviewed_at"], str) and status["reviewed_at"]

assert manifest["classification"] == "UNKNOWN"
assert manifest["reviewed"] is True
assert manifest["review_decision"] == "KEEP"
assert manifest["review_reason"] == "operator-review-retain-operational"

review = payload["manifest_review"]
assert review["reviewed_unknown"] >= 1
assert review["unreviewed_unknown"] >= 0
assert review["complete"] == (review["unreviewed_unknown"] == 0)

status_review = payload["status_review"]
assert status_review["reviewed_unknown_count"] >= 1
assert status_review["unreviewed_unknown_count"] >= 0
assert status_review["review_complete"] == (status_review["unreviewed_unknown_count"] == 0)

stored = payload["stored"]
decision = stored["plugin:legacy-site-fixture/legacy-site-fixture.php"]
assert set(decision) == {"classification", "reason", "reviewed_at"}
assert decision["classification"] == "KEEP"
assert decision["reason"] == "operator-review-retain-operational"

assert payload["manifest_safety"]["review_decisions_execute_mutations"] is False

after = payload["after_clear"]
assert "plugin:legacy-site-fixture/legacy-site-fixture.php" not in (after or {})
assert raw["classification"] == "UNKNOWN"

print("ok")
PY
)"; then
  fail_smoke "dependency-review-contract" "Dependency review contract is invalid" "planning-only review evidence without graph authority mutation" "${REVIEW_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Dependency review OK: UNKNOWN remains raw graph authority; bounded operator decision is stored/exported separately and can be cleared.\n'
