#!/usr/bin/env bash
# Phase 8D provider-neutral Sandbox Migration Lab acceptance.
#
# Sourced after 8A/8B/8C acceptance so the legacy inventory, baseline and
# dependency graph fixture already exist.

printf '[smoke] Checking Phase 8D Sandbox Migration Lab.\n'

wp_cli config set SEO_GEO_MIGRATION_SANDBOX true --raw >/dev/null   || fail_smoke "sandbox-marker-config" "Could not mark the fixture as an explicit migration sandbox" "SEO_GEO_MIGRATION_SANDBOX=true" "wp config set failed"

wp_cli option update blog_public 0 >/dev/null   || fail_smoke "sandbox-search-visibility" "Could not disable search-engine visibility in sandbox fixture" "blog_public=0" "wp option update failed"

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

assert report["schema_version"] == 1
assert report["mode"] == "sandbox-migration-lab"
assert report["ready"] is True
assert report["blockers"] == []

env = report["environment"]
assert env["sandbox_marker"] is True
assert env["search_engine_visibility"] == "discouraged"
assert env["destination_theme"] == "seo-geo-theme"
assert env["destination_theme_active"] is True
assert env["baseline_available"] is True
assert env["dependency_graph_ready"] is True

safety = report["safety"]
assert safety == {
    "production_cutover_allowed": False,
    "production_mutation_allowed": False,
    "indexing_allowed": False,
    "canonical_competition_allowed": False,
    "baseline_is_reference_only": True,
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

print("ok")
PY
)"; then
  fail_smoke "sandbox-report-contract" "Phase 8D sandbox report contract is invalid" "ready non-production migration lab with state summary" "${SANDBOX_ASSERTION:-python assertion failed}"
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

printf '[smoke] Phase 8D sandbox lab OK: explicit marker + noindex defenses + destination theme + baseline + dependency graph, with no protected-state mutation.\n'
