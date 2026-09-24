#!/usr/bin/env bash
# Phase 8D provider-neutral Sandbox Migration Lab acceptance.
#
# Sourced after 8A/8B/8C acceptance so the legacy inventory, baseline and
# dependency graph fixture already exist.

printf '[smoke] Checking Phase 8D Sandbox Migration Lab.\n'

wp_cli config set SEO_GEO_MIGRATION_SANDBOX true --raw >/dev/null \
  || fail_smoke "sandbox-marker-config" "Could not mark the fixture as an explicit migration sandbox" "SEO_GEO_MIGRATION_SANDBOX=true" "wp config set failed"
wp_cli config set SEO_GEO_MIGRATION_OUTBOUND_SAFE true --raw >/dev/null \
  || fail_smoke "sandbox-outbound-config" "Could not confirm sandbox outbound safety" "SEO_GEO_MIGRATION_OUTBOUND_SAFE=true" "wp config set failed"
wp_cli config set SEO_GEO_MIGRATION_BACKUPS_READY true --raw >/dev/null \
  || fail_smoke "sandbox-backup-config" "Could not confirm fresh sandbox backup references" "SEO_GEO_MIGRATION_BACKUPS_READY=true" "wp config set failed"

wp_cli option update blog_public 0 >/dev/null \
  || fail_smoke "sandbox-search-visibility" "Could not disable search-engine visibility in sandbox fixture" "blog_public=0" "wp option update failed"

if ! SANDBOX_PLAN_SETUP="$(wp_cli eval '
$baseline = get_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, null );
if ( ! is_array( $baseline ) || ! is_array( $baseline["snapshot"] ?? null ) ) {
	throw new RuntimeException( "Sandbox fixture baseline is unavailable." );
}
$baseline["snapshot"]["site"]["home_url"] = "https://production.example.test/";
$encoded = wp_json_encode( $baseline["snapshot"], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$baseline["sha256"] = hash( "sha256", false === $encoded ? "" : $encoded );
update_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, $baseline, false );

$analyzer = \SeoGeo\MigrationBridge\Plugin::analyzer();
$graph_builder = \SeoGeo\MigrationBridge\Plugin::dependency_graph();
if ( ! $analyzer || ! $graph_builder ) {
	throw new RuntimeException( "Sandbox dependency services are unavailable." );
}
$graph = $graph_builder->build( $analyzer->analyze(), $baseline["snapshot"] );
$reviews = new \SeoGeo\MigrationBridge\Review\DependencyReviewStore();
$count = 0;
foreach ( $graph["components"] ?? array() as $component ) {
	if ( ! is_array( $component ) || "UNKNOWN" !== ( $component["classification"] ?? null ) ) {
		continue;
	}
	$component_id = $component["component_id"] ?? null;
	if ( ! is_string( $component_id ) ) {
		continue;
	}
	$decision = "plugin:legacy-site-fixture/legacy-site-fixture.php" === $component_id ? "MIGRATE" : "KEEP";
	if ( ! $reviews->save( $component_id, $decision ) ) {
		throw new RuntimeException( "Could not save sandbox dependency review." );
	}
	++$count;
}
echo (string) $count;
' 2>"$TMP_DIR/sandbox-plan-setup.stderr" | tr -d '\r\n')"; then
  SANDBOX_PLAN_SETUP_ERROR="$(tr -d '\r' <"$TMP_DIR/sandbox-plan-setup.stderr" | head -c 500)"
  fail_smoke "sandbox-plan-setup" "Could not prepare reviewed sandbox dependency plan" "all UNKNOWN items reviewed" "${SANDBOX_PLAN_SETUP_ERROR:-wp eval failed}"
fi
[[ "$SANDBOX_PLAN_SETUP" =~ ^[1-9][0-9]*$ ]] \
  || fail_smoke "sandbox-plan-review-count" "Sandbox fixture did not expose reviewed UNKNOWN dependencies" "positive review count" "$SANDBOX_PLAN_SETUP"

if ! SANDBOX_STATE_BEFORE="$(wp_cli eval 'global $wpdb; echo hash( "sha256", serialize( array( get_option( "active_plugins", array() ), get_option( "stylesheet" ), get_option( "template" ), get_option( "permalink_structure" ), get_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, null ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ) ) ) );' 2>"$TMP_DIR/sandbox-state-before.stderr" | tr -d '\r\n')"; then
  SANDBOX_STATE_BEFORE_ERROR="$(tr -d '\r' <"$TMP_DIR/sandbox-state-before.stderr" | head -c 240)"
  fail_smoke "sandbox-state-before" "Could not fingerprint protected sandbox state before lab report" "sha256 state snapshot" "${SANDBOX_STATE_BEFORE_ERROR:-wp eval failed}"
fi

if ! SANDBOX_REPORT="$(wp_cli eval '
$analyzer = \SeoGeo\MigrationBridge\Plugin::analyzer();
$lab = \SeoGeo\MigrationBridge\Plugin::sandbox_lab();
if ( ! $analyzer || ! $lab ) {
	exit( 1 );
}
echo wp_json_encode( $lab->report( $analyzer->analyze() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
' 2>"$TMP_DIR/sandbox-report.stderr")"; then
  SANDBOX_REPORT_ERROR="$(tr -d '\r' <"$TMP_DIR/sandbox-report.stderr" | head -c 500)"
  fail_smoke "sandbox-report" "Could not build Phase 8D sandbox migration report" "JSON sandbox report" "${SANDBOX_REPORT_ERROR:-wp eval failed}"
fi

printf '%s' "$SANDBOX_REPORT" >"$TMP_DIR/sandbox-report.json"

if ! SANDBOX_ASSERTION="$(python3 - "$TMP_DIR/sandbox-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

assert report["schema_version"] == 3
assert report["mode"] == "sandbox-migration-lab"
assert report["ready"] is True
assert report["blockers"] == []

env = report["environment"]
assert env["sandbox_marker"] is True
assert env["sandbox_mode"] == "origin"
assert env["source_origin"] == "https://production.example.test:443"
assert env["current_origin"].startswith("http://127.0.0.1:")
assert env["source_base_path"] == "/"
assert env["current_base_path"] == "/"
assert env["distinct_origin"] is True
assert env["distinct_subdirectory"] is False
assert env["storage_isolation_confirmed"] is False
assert env["location_isolated"] is True
assert env["search_engine_visibility"] == "discouraged"
assert env["outbound_safety_confirmed"] is True
assert env["fresh_backups_confirmed"] is True
assert env["destination_theme"] == "seo-geo-theme"
assert env["destination_theme_active"] is True
assert env["baseline_available"] is True
assert env["dependency_graph_ready"] is True
assert env["dependency_review_complete"] is True
assert env["reviewed_unknown"] >= 1
assert env["unreviewed_unknown"] == 0

safety = report["safety"]
assert safety == {
    "production_cutover_allowed": False,
    "production_mutation_allowed": False,
    "indexing_allowed": False,
    "canonical_competition_allowed": False,
    "baseline_is_reference_only": True,
    "review_decisions_are_planning": True,
}

summary = report["migration"]["summary"]
assert summary["migrate"] >= 2
assert summary["manual-review"] >= 1
assert summary["unchanged"] >= 1
assert summary["blocked"] == 0

states = report["migration"]["states"]
assert any(row["component_id"] == "builder:elementor" and row["state"] == "migrate" for row in states)
assert any(row["component_id"] == "builder:divi" and row["state"] == "migrate" for row in states)
assert any(row["component_id"] == "builder:native-blocks" and row["state"] == "unchanged" for row in states)
assert any(
    row["component_id"] == "plugin:legacy-site-fixture/legacy-site-fixture.php"
    and row["classification"] == "UNKNOWN"
    and row["review_decision"] == "MIGRATE"
    and row["state"] == "migrate"
    for row in states
)

print("ok")
PY
)"; then
  fail_smoke "sandbox-report-contract" "Phase 8D sandbox report contract is invalid" "ready non-production migration lab with state summary" "${SANDBOX_ASSERTION:-python assertion failed}"
fi

# Same-origin subdirectory mode must be explicit and storage-isolated.
ORIGINAL_HOME="$(wp_cli option get home 2>"$TMP_DIR/sandbox-home.stderr" | tr -d '\r\n')" \
  || fail_smoke "sandbox-home-read" "Could not read sandbox fixture home URL" "current home URL" "$(cat "$TMP_DIR/sandbox-home.stderr" | head -c 240)"
[[ -n "$ORIGINAL_HOME" ]] \
  || fail_smoke "sandbox-home-empty" "Sandbox fixture home URL is empty" "non-empty home URL" "empty"

wp_cli config set SEO_GEO_MIGRATION_SANDBOX_MODE subdirectory >/dev/null \
  || fail_smoke "sandbox-mode-config" "Could not enable same-origin subdirectory sandbox mode" "SEO_GEO_MIGRATION_SANDBOX_MODE=subdirectory" "wp config set failed"
wp_cli config set SEO_GEO_MIGRATION_STORAGE_ISOLATED true --raw >/dev/null \
  || fail_smoke "sandbox-storage-config" "Could not confirm isolated storage for subdirectory sandbox" "SEO_GEO_MIGRATION_STORAGE_ISOLATED=true" "wp config set failed"

if ! wp_cli eval '
$baseline = get_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, null );
if ( ! is_array( $baseline ) || ! is_array( $baseline["snapshot"] ?? null ) ) {
	throw new RuntimeException( "Sandbox fixture baseline is unavailable." );
}
$baseline["snapshot"]["site"]["home_url"] = trailingslashit( (string) get_option( "home" ) );
$encoded = wp_json_encode( $baseline["snapshot"], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$baseline["sha256"] = hash( "sha256", false === $encoded ? "" : $encoded );
update_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, $baseline, false );
' >/dev/null 2>"$TMP_DIR/sandbox-subdir-baseline.stderr"; then
  fail_smoke "sandbox-subdir-baseline" "Could not prepare same-origin production baseline fixture" "baseline source at original root" "$(cat "$TMP_DIR/sandbox-subdir-baseline.stderr" | head -c 500)"
fi

SUBDIRECTORY_HOME="${ORIGINAL_HOME%/}/nuevaweb"
wp_cli option update home "$SUBDIRECTORY_HOME" >/dev/null \
  || fail_smoke "sandbox-subdir-home" "Could not move sandbox fixture home URL to isolated subdirectory" "$SUBDIRECTORY_HOME" "wp option update failed"

if ! SUBDIRECTORY_REPORT="$(wp_cli eval '
$analyzer = \SeoGeo\MigrationBridge\Plugin::analyzer();
$lab = \SeoGeo\MigrationBridge\Plugin::sandbox_lab();
if ( ! $analyzer || ! $lab ) {
	exit( 1 );
}
echo wp_json_encode( $lab->report( $analyzer->analyze() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
' 2>"$TMP_DIR/sandbox-subdir-report.stderr")"; then
  fail_smoke "sandbox-subdir-report" "Could not build same-origin subdirectory sandbox report" "JSON sandbox report" "$(cat "$TMP_DIR/sandbox-subdir-report.stderr" | head -c 500)"
fi
printf '%s' "$SUBDIRECTORY_REPORT" >"$TMP_DIR/sandbox-subdir-report.json"

if ! SUBDIRECTORY_ASSERTION="$(python3 - "$TMP_DIR/sandbox-subdir-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

assert report["schema_version"] == 3
assert report["ready"] is True
assert report["blockers"] == []

env = report["environment"]
assert env["sandbox_mode"] == "subdirectory"
assert env["source_origin"] == env["current_origin"]
assert env["source_base_path"] == "/"
assert env["current_base_path"] == "/nuevaweb/"
assert env["distinct_origin"] is False
assert env["distinct_subdirectory"] is True
assert env["storage_isolation_confirmed"] is True
assert env["location_isolated"] is True

print("ok")
PY
)"; then
  fail_smoke "sandbox-subdir-contract" "Same-origin subdirectory sandbox contract is invalid" "ready isolated /nuevaweb/ sandbox" "${SUBDIRECTORY_ASSERTION:-python assertion failed}"
fi

wp_cli config delete SEO_GEO_MIGRATION_STORAGE_ISOLATED >/dev/null 2>&1 \
  || fail_smoke "sandbox-storage-marker-delete" "Could not remove storage isolation marker for negative acceptance" "marker absent" "wp config delete failed"

if ! SUBDIRECTORY_BLOCKED_REPORT="$(wp_cli eval '
$analyzer = \SeoGeo\MigrationBridge\Plugin::analyzer();
$lab = \SeoGeo\MigrationBridge\Plugin::sandbox_lab();
echo wp_json_encode( $lab->report( $analyzer->analyze() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
' 2>"$TMP_DIR/sandbox-subdir-blocked.stderr")"; then
  fail_smoke "sandbox-subdir-blocked-report" "Could not build subdirectory negative report" "JSON sandbox report" "$(cat "$TMP_DIR/sandbox-subdir-blocked.stderr" | head -c 500)"
fi
printf '%s' "$SUBDIRECTORY_BLOCKED_REPORT" >"$TMP_DIR/sandbox-subdir-blocked.json"

if ! SUBDIRECTORY_BLOCKED_ASSERTION="$(python3 - "$TMP_DIR/sandbox-subdir-blocked.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

assert report["ready"] is False
assert "sandbox-storage-isolation-not-confirmed" in report["blockers"]
assert report["environment"]["location_isolated"] is False
assert report["environment"]["storage_isolation_confirmed"] is False

print("ok")
PY
)"; then
  fail_smoke "sandbox-subdir-storage-guard" "Subdirectory sandbox did not block missing storage isolation confirmation" "storage blocker" "${SUBDIRECTORY_BLOCKED_ASSERTION:-python assertion failed}"
fi

wp_cli option update home "$ORIGINAL_HOME" >/dev/null \
  || fail_smoke "sandbox-home-restore" "Could not restore sandbox fixture home URL" "$ORIGINAL_HOME" "wp option update failed"
wp_cli config delete SEO_GEO_MIGRATION_SANDBOX_MODE >/dev/null 2>&1 \
  || fail_smoke "sandbox-mode-cleanup" "Could not restore default origin sandbox mode" "mode marker absent" "wp config delete failed"

if ! wp_cli eval '
$baseline = get_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, null );
$baseline["snapshot"]["site"]["home_url"] = "https://production.example.test/";
$encoded = wp_json_encode( $baseline["snapshot"], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$baseline["sha256"] = hash( "sha256", false === $encoded ? "" : $encoded );
update_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, $baseline, false );
' >/dev/null 2>"$TMP_DIR/sandbox-baseline-restore.stderr"; then
  fail_smoke "sandbox-baseline-restore" "Could not restore distinct-origin baseline fixture" "production.example.test baseline" "$(cat "$TMP_DIR/sandbox-baseline-restore.stderr" | head -c 500)"
fi

SANDBOX_HEADERS="$TMP_DIR/sandbox-headers.txt"
SANDBOX_BODY="$TMP_DIR/sandbox-body.html"
curl -fsS -D "$SANDBOX_HEADERS" "$BASE_URL/native-seo-fixture/" -o "$SANDBOX_BODY"   || fail_smoke "sandbox-public-request" "Sandbox public fixture request failed" "HTTP 2xx" "curl failure"

if ! grep -Eqi '^X-Robots-Tag:[[:space:]]*noindex,[[:space:]]*nofollow,[[:space:]]*noarchive' "$SANDBOX_HEADERS"; then
  SANDBOX_X_ROBOTS="$(grep -Ei '^X-Robots-Tag:' "$SANDBOX_HEADERS" | head -n 1 | tr -d '\r' || true)"
  fail_smoke "sandbox-x-robots" "Explicit sandbox did not emit the mandatory X-Robots-Tag guard" "noindex, nofollow, noarchive" "${SANDBOX_X_ROBOTS:-missing}"
fi

if ! grep -Eqi 'name=.robots.[^>]*noindex|noindex[^>]*name=.robots.' "$SANDBOX_BODY"; then
  fail_smoke "sandbox-robots-meta" "Explicit sandbox did not expose a noindex robots meta directive" "robots meta contains noindex" "no noindex robots meta found"
fi

if ! SANDBOX_STATE_AFTER="$(wp_cli eval 'global $wpdb; echo hash( "sha256", serialize( array( get_option( "active_plugins", array() ), get_option( "stylesheet" ), get_option( "template" ), get_option( "permalink_structure" ), get_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, null ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ) ) ) );' 2>"$TMP_DIR/sandbox-state-after.stderr" | tr -d '\r\n')"; then
  SANDBOX_STATE_AFTER_ERROR="$(tr -d '\r' <"$TMP_DIR/sandbox-state-after.stderr" | head -c 240)"
  fail_smoke "sandbox-state-after" "Could not fingerprint protected sandbox state after lab report" "sha256 state snapshot" "${SANDBOX_STATE_AFTER_ERROR:-wp eval failed}"
fi

[[ "$SANDBOX_STATE_AFTER" == "$SANDBOX_STATE_BEFORE" ]]   || fail_smoke "sandbox-read-only-regression" "Phase 8D lab report changed protected sandbox state" "$SANDBOX_STATE_BEFORE" "$SANDBOX_STATE_AFTER"

printf '[smoke] Phase 8D sandbox lab OK: distinct-origin and isolated same-origin subdirectory modes accepted; noindex + outbound/backups + reviewed dependency plan + destination theme enforced.\n'
