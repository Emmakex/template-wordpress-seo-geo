#!/usr/bin/env bash
# Phase 8I Migration Bridge operator UI acceptance.
#
# Sourced after 8H so the fixture has an accepted cutover and persisted final
# handoff report. Rendering must remain capability-gated and strictly read-only.

printf '[smoke] Checking Phase 8I EN/ES operator UI.\n'

OPERATOR_RUNNER="$TMP_DIR/migration-operator-runner.php"
cat >"$OPERATOR_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Operator\AdminBaselineCaptureController;
use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;
use SeoGeo\MigrationBridge\Operator\OperatorCopy;
use SeoGeo\MigrationBridge\Operator\OperatorStatus;
use SeoGeo\MigrationBridge\Plugin;

wp_set_current_user( 1 );

$status_service = Plugin::operator_status();
if ( ! $status_service instanceof OperatorStatus ) {
	throw new RuntimeException( 'Phase 8I operator status service is unavailable.' );
}

$catalogs = OperatorCopy::catalogs();
$en_keys  = isset( $catalogs['en'] ) && is_array( $catalogs['en'] ) ? array_keys( $catalogs['en'] ) : array();
$es_keys  = isset( $catalogs['es'] ) && is_array( $catalogs['es'] ) ? array_keys( $catalogs['es'] ) : array();
sort( $en_keys );
sort( $es_keys );

$protected_state = static function (): array {
	$active_plugins = get_option( 'active_plugins', array() );
	if ( ! is_array( $active_plugins ) ) {
		$active_plugins = array();
	}
	sort( $active_plugins );

	return array(
		'baseline'       => get_option( 'seo_geo_migration_baseline_v1', null ),
		'cutover'        => get_option( 'seo_geo_cutover_history_v1', null ),
		'final_report'   => get_option( 'seo_geo_migration_report_v1', null ),
		'active_plugins' => $active_plugins,
		'stylesheet'     => get_option( 'stylesheet', '' ),
		'template'       => get_option( 'template', '' ),
		'blog_public'    => get_option( 'blog_public', null ),
		'active_preset'  => get_option( 'seo_geo_active_preset', null ),
	);
};

$fingerprint = static function ( array $state ): string {
	$encoded = wp_json_encode( $state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return hash( 'sha256', false === $encoded ? '' : $encoded );
};

$before = $fingerprint( $protected_state() );

$status = $status_service->snapshot();

do_action( 'admin_menu' );
global $submenu;
$tools_rows = isset( $submenu['tools.php'] ) && is_array( $submenu['tools.php'] ) ? $submenu['tools.php'] : array();
$registered = false;
foreach ( $tools_rows as $row ) {
	if (
		is_array( $row )
		&& isset( $row[1], $row[2] )
		&& 'manage_options' === $row[1]
		&& AdminOperatorScreen::PAGE_SLUG === $row[2]
	) {
		$registered = true;
		break;
	}
}

$english_screen = new AdminOperatorScreen( $status_service, new OperatorCopy( 'en_US' ) );
ob_start();
$english_screen->render_page();
$english_html = (string) ob_get_clean();

$spanish_screen = new AdminOperatorScreen( $status_service, new OperatorCopy( 'es_ES' ) );
ob_start();
$spanish_screen->render_page();
$spanish_html = (string) ob_get_clean();

$baseline_action_registered = false !== has_action(
	'admin_post_' . AdminBaselineCaptureController::ACTION
);

$saved_baseline = get_option( 'seo_geo_migration_baseline_v1', null );
if ( ! is_array( $saved_baseline ) ) {
	throw new RuntimeException( 'Phase 8I fixture baseline is unavailable for missing-baseline UI acceptance.' );
}
delete_option( 'seo_geo_migration_baseline_v1' );

$missing_spanish_screen = new AdminOperatorScreen( $status_service, new OperatorCopy( 'es_ES' ) );
ob_start();
$missing_spanish_screen->render_page();
$missing_spanish_html = (string) ob_get_clean();

if ( ! add_option( 'seo_geo_migration_baseline_v1', $saved_baseline, '', false ) ) {
	throw new RuntimeException( 'Could not restore Phase 8I baseline after missing-baseline UI acceptance.' );
}

$after = $fingerprint( $protected_state() );

echo wp_json_encode(
	array(
		'registered'        => $registered,
		'baseline_action_registered' => $baseline_action_registered,
		'catalog_keys'      => array(
			'en' => $en_keys,
			'es' => $es_keys,
		),
		'catalog_empty'     => array(
			'en' => array_keys( array_filter( $catalogs['en'] ?? array(), static fn( mixed $value ): bool => ! is_string( $value ) || '' === trim( $value ) ) ),
			'es' => array_keys( array_filter( $catalogs['es'] ?? array(), static fn( mixed $value ): bool => ! is_string( $value ) || '' === trim( $value ) ) ),
		),
		'status'            => $status,
		'english_html'      => $english_html,
		'spanish_html'      => $spanish_html,
		'missing_spanish_html' => $missing_spanish_html,
		'state_fingerprint' => array(
			'before' => $before,
			'after'  => $after,
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$OPERATOR_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-operator-runner.php \
  || fail_smoke "operator-runner-copy" "Could not copy Phase 8I runner" "runner copied" "docker cp failed"

if ! OPERATOR_JSON="$(wp_cli eval-file /var/www/html/wp-content/migration-operator-runner.php 2>"$TMP_DIR/migration-operator.stderr")"; then
  OPERATOR_ERROR="$(tr -d '\r' <"$TMP_DIR/migration-operator.stderr" | head -c 900)"
  fail_smoke "operator-runner" "Phase 8I operator runner failed" "JSON operator report" "${OPERATOR_ERROR:-wp eval-file failed}"
fi

printf '%s' "$OPERATOR_JSON" >"$TMP_DIR/migration-operator.json"

if grep -Fq '"post_content"' "$TMP_DIR/migration-operator.json" \
  || grep -Fq '_elementor_data' "$TMP_DIR/migration-operator.json" \
  || grep -Fq '_et_pb_use_builder' "$TMP_DIR/migration-operator.json" \
  || grep -Fq 'phase-8g-database-backup' "$TMP_DIR/migration-operator.json" \
  || grep -Fq 'phase-8g-uploads-backup' "$TMP_DIR/migration-operator.json"; then
  fail_smoke "operator-private-data" "Phase 8I operator output leaked private migration/recovery content" "bounded status metadata only" "private marker found"
fi

if ! OPERATOR_ASSERTION="$(python3 - "$TMP_DIR/migration-operator.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

assert payload["registered"] is True
assert payload["baseline_action_registered"] is True
assert payload["catalog_keys"]["en"] == payload["catalog_keys"]["es"]
assert len(payload["catalog_keys"]["en"]) >= 35
assert payload["catalog_empty"] == {"en": [], "es": []}
assert payload["state_fingerprint"]["before"] == payload["state_fingerprint"]["after"]

status = payload["status"]
assert status["schema_version"] == 1
assert status["mode"] == "operator-status-read-only"
assert status["baseline"]["available"] is True
assert status["dependency_plan"]["available"] is True
assert status["dependency_plan"]["source"] == "final-report"
assert status["cutover"]["available"] is True
assert status["cutover"]["accepted"] is True
assert status["final_report"]["available"] is True
assert status["final_report"]["ready_for_handoff"] is True
assert status["final_report"]["runtime_dependency_required"] is False
assert status["next_step"] in ("resolve-blockers", "review-advisories", "remove-bridge")
assert status["safety"] == {
    "mutations_performed": False,
    "private_content_exported": False,
    "builder_payload_exported": False,
    "credentials_exported": False,
    "raw_recovery_exported": False,
    "page_render_executes_actions": False,
}

en = payload["english_html"]
es = payload["spanish_html"]

for required in (
    "<h1>SEO/GEO Migration Bridge</h1>",
    "<h2>Migration status</h2>",
    "<h2>Dependency classifications</h2>",
    "<h2>Review status</h2>",
    "<h2>Recommended next step</h2>",
    "<h2>Safety and privacy</h2>",
    "<code>KEEP</code>",
    "<code>REPLACE</code>",
    "<code>MIGRATE</code>",
    "<code>UNKNOWN</code>",
):
    assert required in en

for required in (
    "<h1>Puente de migración SEO/GEO</h1>",
    "<h2>Estado de la migración</h2>",
    "<h2>Clasificaciones de dependencias</h2>",
    "<h2>Estado de revisión</h2>",
    "<h2>Siguiente paso recomendado</h2>",
    "<h2>Seguridad y privacidad</h2>",
):
    assert required in es

assert "<form" not in en.lower()
assert "<form" not in es.lower()
assert "name=" not in en.lower()
assert "name=" not in es.lower()
assert "post_content" not in en
assert "post_content" not in es

missing_es = payload["missing_spanish_html"]
assert "<form" in missing_es.lower()
assert 'admin-post.php' in missing_es
assert 'name="action"' in missing_es
assert 'value="seo_geo_migration_capture_baseline"' in missing_es
assert 'name="_wpnonce"' in missing_es
assert "Capturar línea base SEO/GEO" in missing_es
assert "No cambia el tema, plugins, contenido" in missing_es

print("ok")
PY
)"; then
  fail_smoke "operator-contract" "Phase 8I operator UI contract is invalid" "read-only, EN/ES key-complete capability-gated status screen" "${OPERATOR_ASSERTION:-python assertion failed}"
fi

UNAUTHORIZED_STDOUT="$TMP_DIR/migration-operator-unauthorized.out"
UNAUTHORIZED_STDERR="$TMP_DIR/migration-operator-unauthorized.err"
if wp_cli eval 'wp_set_current_user( 0 ); $screen = new \SeoGeo\MigrationBridge\Operator\AdminOperatorScreen( null, new \SeoGeo\MigrationBridge\Operator\OperatorCopy( "en_US" ) ); $screen->render_page();' >"$UNAUTHORIZED_STDOUT" 2>"$UNAUTHORIZED_STDERR"; then
  fail_smoke "operator-capability" "Unauthorized user could render the Phase 8I operator screen" "wp_die / non-zero exit" "render succeeded"
fi

UNAUTHORIZED_TEXT="$(cat "$UNAUTHORIZED_STDOUT" "$UNAUTHORIZED_STDERR" | tr -d '\r' | head -c 1000)"
[[ "$UNAUTHORIZED_TEXT" == *"You do not have permission to view Migration Bridge status."* ]] \
  || fail_smoke "operator-capability-message" "Unauthorized operator response did not use bounded localized guidance" "permission message" "${UNAUTHORIZED_TEXT:-empty}"

printf '[smoke] Phase 8I operator UI OK: Tools screen registered; EN/ES catalogs complete; viewing is mutation-free; missing baseline exposes one nonce/capability-gated capture action.\n'
