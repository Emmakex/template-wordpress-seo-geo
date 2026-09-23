#!/usr/bin/env bash
# Phase 8C builder/plugin dependency graph acceptance.
#
# Sourced after 8A inventory and 8B persisted baseline.

printf '[smoke] Checking Phase 8C builder/plugin dependency graph.\n'

if ! DEPENDENCY_FIXTURE="$(wp_cli eval '
$native_id = wp_insert_post(
	array(
		"post_type" => "page",
		"post_status" => "publish",
		"post_title" => "Native Dependency Fixture",
		"post_name" => "native-dependency-fixture",
		"post_content" => "<!-- wp:paragraph --><p>Native dependency marker</p><!-- /wp:paragraph -->",
	)
);
$elementor_id = wp_insert_post(
	array(
		"post_type" => "page",
		"post_status" => "publish",
		"post_title" => "Elementor Dependency Fixture",
		"post_name" => "elementor-dependency-fixture",
		"post_content" => "Elementor public fallback.",
	)
);
update_post_meta( $elementor_id, "_elementor_edit_mode", "builder" );
update_post_meta( $elementor_id, "_elementor_data", "[{\"id\":\"private-elementor-payload-marker\"}]" );

$divi_id = wp_insert_post(
	array(
		"post_type" => "page",
		"post_status" => "draft",
		"post_title" => "Divi Dependency Fixture",
		"post_content" => "[et_pb_section]private-divi-body-marker[/et_pb_section]",
	)
);
update_post_meta( $divi_id, "et_pb_use_builder", "on" );

$shortcode_id = wp_insert_post(
	array(
		"post_type" => "page",
		"post_status" => "private",
		"post_title" => "Shortcode Dependency Fixture",
		"post_content" => "[legacy_cta secret=\"private-shortcode-attribute-marker\"]",
	)
);

echo implode( ",", array( $native_id, $elementor_id, $divi_id, $shortcode_id ) );
' 2>"$TMP_DIR/dependency-fixture.stderr")"; then
  DEPENDENCY_FIXTURE_ERROR="$(tr -d '\r' <"$TMP_DIR/dependency-fixture.stderr" | head -c 500)"
  fail_smoke "dependency-fixture" "Could not create Phase 8C dependency fixtures" "four fixture IDs" "${DEPENDENCY_FIXTURE_ERROR:-wp eval failed}"
fi

IFS=',' read -r NATIVE_DEP_ID ELEMENTOR_DEP_ID DIVI_DEP_ID SHORTCODE_DEP_ID <<<"$DEPENDENCY_FIXTURE"
for fixture_id in "$NATIVE_DEP_ID" "$ELEMENTOR_DEP_ID" "$DIVI_DEP_ID" "$SHORTCODE_DEP_ID"; do
  [[ "$fixture_id" =~ ^[0-9]+$ ]]     || fail_smoke "dependency-fixture-id" "Phase 8C fixture did not return numeric IDs" "numeric IDs" "$DEPENDENCY_FIXTURE"
done

if ! DEPENDENCY_STATE_BEFORE="$(wp_cli eval 'global $wpdb; echo hash( "sha256", serialize( array( get_option( "active_plugins", array() ), get_option( "stylesheet" ), get_option( "template" ), get_option( "permalink_structure" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ) ) ) );' 2>"$TMP_DIR/dependency-state-before.stderr" | tr -d '\r\n')"; then
  DEPENDENCY_STATE_BEFORE_ERROR="$(tr -d '\r' <"$TMP_DIR/dependency-state-before.stderr" | head -c 240)"
  fail_smoke "dependency-state-before" "Could not fingerprint protected state before dependency graph" "sha256 state snapshot" "${DEPENDENCY_STATE_BEFORE_ERROR:-wp eval failed}"
fi

if ! DEPENDENCY_REPORT="$(wp_cli eval '
$analyzer = \SeoGeo\MigrationBridge\Plugin::analyzer();
$graph = \SeoGeo\MigrationBridge\Plugin::dependency_graph();
if ( ! $analyzer || ! $graph ) {
	exit( 1 );
}
echo wp_json_encode( $graph->build( $analyzer->analyze() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
' 2>"$TMP_DIR/dependency-graph.stderr")"; then
  DEPENDENCY_GRAPH_ERROR="$(tr -d '\r' <"$TMP_DIR/dependency-graph.stderr" | head -c 500)"
  fail_smoke "dependency-graph-eval" "Could not build Phase 8C dependency graph" "JSON dependency graph" "${DEPENDENCY_GRAPH_ERROR:-wp eval failed}"
fi

printf '%s' "$DEPENDENCY_REPORT" >"$TMP_DIR/dependency-report.json"

if grep -Fq 'private-elementor-payload-marker' "$TMP_DIR/dependency-report.json"   || grep -Fq 'private-divi-body-marker' "$TMP_DIR/dependency-report.json"   || grep -Fq 'private-shortcode-attribute-marker' "$TMP_DIR/dependency-report.json"; then
  fail_smoke "dependency-private-payload" "Phase 8C exported raw builder/content payload" "no private fixture markers" "private marker found"
fi

if ! DEPENDENCY_ASSERTION="$(python3 - "$TMP_DIR/dependency-report.json" "$NATIVE_DEP_ID" "$ELEMENTOR_DEP_ID" "$DIVI_DEP_ID" "$SHORTCODE_DEP_ID" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

native_id, elementor_id, divi_id, shortcode_id = map(int, sys.argv[2:])

assert report["schema_version"] == 1
assert report["mode"] == "read-only-planning"
assert report["baseline"]["available"] is True
assert report["baseline"]["kind"] == "seo-geo-public-baseline"
assert report["safety"] == {
    "mutations_performed": False,
    "plugin_removal_performed": False,
    "theme_switch_performed": False,
    "raw_content_exported": False,
    "builder_payload_exported": False,
    "automatic_removal_allowed": False,
}

content = report["content"]
assert content["safety"]["content_scan_performed"] is True
assert content["safety"]["raw_content_exported"] is False
assert content["safety"]["builder_payload_exported"] is False
assert content["safety"]["private_body_exported"] is False

resources = {row["object_id"]: row for row in content["resources"]}
assert any(row["id"] == "native-blocks" for row in resources[native_id]["builders"])
assert any(row["id"] == "elementor" for row in resources[elementor_id]["builders"])
assert any(row["id"] == "divi" for row in resources[divi_id]["builders"])
assert "legacy_cta" in resources[shortcode_id]["shortcodes"]
assert resources[divi_id]["public_url"] is None
assert resources[shortcode_id]["public_url"] is None

components = {row["component_id"]: row for row in report["components"]}
assert components["builder:native-blocks"]["classification"] == "KEEP"
assert components["builder:elementor"]["classification"] == "MIGRATE"
assert components["builder:elementor"]["resource_count"] >= 1
assert components["builder:divi"]["classification"] == "MIGRATE"
assert components["builder:divi"]["resource_count"] >= 1
assert components["provider:business_systems:woocommerce"]["classification"] == "KEEP"
assert components["provider:seo:yoast"]["classification"] == "REMOVE-CANDIDATE"
assert components["plugin:legacy-site-fixture/legacy-site-fixture.php"]["classification"] == "UNKNOWN"
assert all(row["auto_remove"] is False for row in report["components"])

edges = {(row["from"], row["to"], row["kind"]) for row in report["edges"]}
assert (f"resource:{native_id}", "builder:native-blocks", "content-builder") in edges
assert (f"resource:{elementor_id}", "builder:elementor", "content-builder") in edges
assert (f"resource:{divi_id}", "builder:divi", "content-builder") in edges
assert (f"resource:{shortcode_id}", "shortcode:legacy_cta", "content-shortcode") in edges

authority = {row["category"]: row for row in report["authorities"]}
assert authority["seo"]["authority_status"] == "inactive-providers-only"
assert "canonical" in authority["seo"]["observed_public_signals"]
assert "json-ld" in authority["schema"]["observed_public_signals"]
assert "html-lang" in authority["multilingual"]["observed_public_signals"]

assert set(report["summary"]) == {
    "KEEP", "REPLACE", "MIGRATE", "OPTIONAL", "REMOVE-CANDIDATE", "UNKNOWN"
}
assert report["summary"]["MIGRATE"] >= 2
assert report["summary"]["KEEP"] >= 2

print("ok")
PY
)"; then
  fail_smoke "dependency-graph-contract" "Phase 8C dependency graph contract is invalid" "classified dependency graph without raw content" "${DEPENDENCY_ASSERTION:-python assertion failed}"
fi

if ! DEPENDENCY_STATE_AFTER="$(wp_cli eval 'global $wpdb; echo hash( "sha256", serialize( array( get_option( "active_plugins", array() ), get_option( "stylesheet" ), get_option( "template" ), get_option( "permalink_structure" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ) ) ) );' 2>"$TMP_DIR/dependency-state-after.stderr" | tr -d '\r\n')"; then
  DEPENDENCY_STATE_AFTER_ERROR="$(tr -d '\r' <"$TMP_DIR/dependency-state-after.stderr" | head -c 240)"
  fail_smoke "dependency-state-after" "Could not fingerprint protected state after dependency graph" "sha256 state snapshot" "${DEPENDENCY_STATE_AFTER_ERROR:-wp eval failed}"
fi

[[ "$DEPENDENCY_STATE_AFTER" == "$DEPENDENCY_STATE_BEFORE" ]]   || fail_smoke "dependency-read-only-regression" "Phase 8C dependency graph changed protected WordPress state" "$DEPENDENCY_STATE_BEFORE" "$DEPENDENCY_STATE_AFTER"

printf '[smoke] Phase 8C dependency graph OK: builders/content/providers classified without mutation, auto-removal or raw payload export.\n'
