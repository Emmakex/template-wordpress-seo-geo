#!/usr/bin/env bash
# Phase 8I Migration Bridge operator UI acceptance.
#
# Sourced after 8H so the fixture has an accepted cutover and persisted final
# handoff report. Rendering must remain capability-gated and strictly read-only.

printf '[smoke] Checking Phase 8I EN/ES operator UI.\n'

# Phase 8G restores production posture before this acceptance. Re-enable the
# explicit sandbox posture only for the sandbox-readiness UI fixture, then
# restore production posture after the assertions.
wp_cli config set SEO_GEO_MIGRATION_SANDBOX true --raw >/dev/null \
  || fail_smoke "operator-sandbox-marker" "Could not enable sandbox marker for operator readiness fixture" "SEO_GEO_MIGRATION_SANDBOX=true" "wp config set failed"
wp_cli option update blog_public 0 >/dev/null \
  || fail_smoke "operator-sandbox-search" "Could not disable search visibility for operator readiness fixture" "blog_public=0" "wp option update failed"

OPERATOR_RUNNER="$TMP_DIR/migration-operator-runner.php"
cat >"$OPERATOR_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Operator\AdminBaselineCaptureController;
use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;
use SeoGeo\MigrationBridge\Operator\AdminMigrationWizard;
use SeoGeo\MigrationBridge\Operator\AdminSandboxHandoffController;
use SeoGeo\MigrationBridge\Operator\OperatorCopy;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Operator\MigrationWizardNavigator;
use SeoGeo\MigrationBridge\Operator\OperatorStatus;
use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Review\AdminDependencyReviewController;
use SeoGeo\MigrationBridge\Review\DependencyReviewStore;

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
		'dependency_reviews' => get_option( DependencyReviewStore::OPTION_NAME, null ),
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

// Empty synthetic jobs exercise navigation without altering stored state.
$guide_jobs = array();
foreach ( array( 'local-clone', 'export', 'import' ) as $operation ) {
	$guide_jobs[ $operation ] = MigrationWizardNavigator::next(
		array(
			'job_id'    => 'clone-wizard-fixture-000001',
			'operation' => $operation,
			'status'    => 'active',
		)
	);
}

$guide_safe_actions = array(
	'inventory' => AdminMigrationWizard::safe_batch_action( $guide_jobs['export'] ),
	'import'    => AdminMigrationWizard::safe_batch_action( $guide_jobs['import'] ),
	'database'  => AdminMigrationWizard::safe_batch_action( array( 'anchor' => 'seo-geo-guide-clone_database_export' ) ),
	'files'     => AdminMigrationWizard::safe_batch_action( array( 'anchor' => 'seo-geo-guide-clone_file_export' ) ),
	'activate'  => AdminMigrationWizard::safe_batch_action( array( 'anchor' => 'seo-geo-guide-clone_database_activation' ) ),
);

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

// Inject a normalized job only into reads: no migration state is persisted.
$fixture_id     = 'clone-wizard-fixture-000001';
$fixture_mode   = 'export';
$fixture_status = 'active';
$fixture_jobs   = static function ( $pre ) use ( $fixture_id, &$fixture_mode, &$fixture_status ): array {
	return array(
		$fixture_id => array(
			'schema_version' => CloneJobStore::SCHEMA_VERSION,
			'job_id'         => $fixture_id,
			'operation'      => $fixture_mode,
			'phase'          => 'created',
			'status'         => $fixture_status,
			'cursor'         => null,
			'counters'       => array( 'completed' => 0, 'total' => null ),
			'error_code'     => null,
			'created_at'     => gmdate( DATE_ATOM ),
			'updated_at'     => gmdate( DATE_ATOM ),
		),
	);
};
add_filter( 'pre_option_' . CloneJobStore::OPTION_NAME, $fixture_jobs );
$_GET['clone_job_id'] = $fixture_id;

ob_start();
$english_screen->render_page();
$wizard_export_html = (string) ob_get_clean();

$fixture_mode = 'import';
ob_start();
$english_screen->render_page();
$wizard_import_html = (string) ob_get_clean();

$fixture_mode   = 'export';
$fixture_status = 'paused';
ob_start();
$english_screen->render_page();
$wizard_paused_html = (string) ob_get_clean();

remove_filter( 'pre_option_' . CloneJobStore::OPTION_NAME, $fixture_jobs );
unset( $_GET['clone_job_id'] );

$baseline_action_registered = false !== has_action(
	'admin_post_' . AdminBaselineCaptureController::ACTION
);
$handoff_action_registered = false !== has_action(
	'admin_post_' . AdminSandboxHandoffController::ACTION
);
$review_action_registered = false !== has_action(
	'admin_post_' . AdminDependencyReviewController::ACTION
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
		'guide_jobs'        => $guide_jobs,
		'guide_safe_actions' => $guide_safe_actions,
		'baseline_action_registered' => $baseline_action_registered,
		'handoff_action_registered'  => $handoff_action_registered,
		'review_action_registered'   => $review_action_registered,
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
		'wizard_fixture'    => array(
			'export' => $wizard_export_html,
			'import' => $wizard_import_html,
			'paused' => $wizard_paused_html,
		),
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
assert payload["handoff_action_registered"] is True
assert payload["review_action_registered"] is True
assert payload["catalog_keys"]["en"] == payload["catalog_keys"]["es"]
assert len(payload["catalog_keys"]["en"]) >= 35
assert payload["catalog_empty"] == {"en": [], "es": []}
assert payload["state_fingerprint"]["before"] == payload["state_fingerprint"]["after"]
for operation, expected in (("local-clone", "clone_inventory"), ("export", "clone_inventory"), ("import", "clone_import")):
    step = payload["guide_jobs"][operation]
    assert step["anchor"] == "seo-geo-guide-" + expected
    assert step["done"] is False
    assert step["completed"] == 0
    assert step["total"] >= 5
    assert step["blocked"] is False

safe = payload["guide_safe_actions"]
assert safe["inventory"]["action"] == "seo_geo_migration_clone_inventory"
assert safe["inventory"]["nonce"] == "seo_geo_migration_clone_inventory"
assert safe["inventory"]["fields"] == {"inventory_batch_size": 100}
assert safe["database"]["action"] == "seo_geo_migration_clone_database_export"
assert safe["database"]["fields"] == {"database_batch_rows": 100}
assert safe["files"]["action"] == "seo_geo_migration_clone_file_export"
assert safe["files"]["fields"] == {"file_batch_size": 25, "file_batch_megabytes": 8}
assert safe["import"] is None
assert safe["activate"] is None

# The simple view must wire its POST form only for an active source job.
def simple_section(html):
    return html.split('<section class="seo-geo-migration-simple"', 1)[1].split("</section>", 1)[0]

fixture = payload["wizard_fixture"]
export_ui = simple_section(fixture["export"])
import_ui = simple_section(fixture["import"])
paused_ui = simple_section(fixture["paused"])
assert 'name="action" value="seo_geo_migration_clone_inventory"' in export_ui
assert 'name="clone_job_id" value="clone-wizard-fixture-000001"' in export_ui
assert "Continue one safe batch" in export_ui
assert 'name="_wpnonce"' in export_ui
assert "Continue one safe batch" not in import_ui
assert 'name="action" value="seo_geo_migration_clone_inventory"' not in import_ui
assert "Continue one safe batch" not in paused_ui
assert 'name="action" value="seo_geo_migration_clone_inventory"' not in paused_ui

status = payload["status"]
assert status["schema_version"] == 1
assert status["mode"] == "operator-status-read-only"
assert status["baseline"]["available"] is True
assert status["dependency_plan"]["available"] is True
assert status["dependency_plan"]["source"] == "final-report"
assert isinstance(status["dependency_plan"]["components"], list)
assert len(status["dependency_plan"]["components"]) > 0
assert isinstance(status["dependency_plan"]["reviewed_unknown_count"], int)
assert isinstance(status["dependency_plan"]["unreviewed_unknown_count"], int)
assert status["dependency_plan"]["review_complete"] == (status["dependency_plan"]["unreviewed_unknown_count"] == 0)
assert status["cutover"]["available"] is True
assert status["cutover"]["accepted"] is True
assert status["final_report"]["available"] is True
assert status["final_report"]["ready_for_handoff"] is True
assert status["final_report"]["runtime_dependency_required"] is False
assert status["sandbox"]["active"] is True
assert status["sandbox"]["ready"] is True
assert status["sandbox"]["blockers"] == []
assert status["sandbox"]["environment"]["sandbox_marker"] is True
assert status["sandbox"]["environment"]["sandbox_mode"] == "origin"
assert status["sandbox"]["environment"]["distinct_origin"] is True
assert status["sandbox"]["environment"]["location_isolated"] is True
assert status["sandbox"]["environment"]["source_base_path"] == "/"
assert status["sandbox"]["environment"]["current_base_path"] == "/"
assert status["sandbox"]["environment"]["outbound_safety_confirmed"] is True
assert status["sandbox"]["environment"]["fresh_backups_confirmed"] is True
assert status["sandbox"]["environment"]["dependency_review_complete"] is True
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
    "<h2>Sandbox readiness</h2>",
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
    "<h2>Preparación del sandbox</h2>",
    "<h2>Siguiente paso recomendado</h2>",
    "<h2>Seguridad y privacidad</h2>",
):
    assert required in es

assert 'name="clone_operation" value="export"' in en
assert 'name="clone_operation" value="local-clone"' in en
assert 'name="clone_operation" value="import"' in en
assert 'name="clone_operation" value="export"' in es
assert 'name="clone_operation" value="export"' not in payload["missing_spanish_html"]
assert 'id="seo-geo-simple-heading"' in en
assert "WordPress migration: simple view" in en
assert 'id="seo-geo-advanced-controls"' in en
assert 'Advanced migration controls and technical details' in en
assert 'id="seo-geo-simple-heading"' in es
assert "Migración WordPress: vista sencilla" in es
assert 'id="seo-geo-advanced-controls"' in es
assert "Controles avanzados y detalles técnicos de migración" in es

assert 'value="seo_geo_migration_sandbox_handoff"' in en
assert 'value="seo_geo_migration_sandbox_handoff"' in es
assert "<h2>Sandbox handoff</h2>" in en
assert "<h2>Entrega al sandbox</h2>" in es
assert "<h2>Dependency detail</h2>" in en
assert "<h2>Detalle de dependencias</h2>" in es
assert "Download sandbox handoff JSON" in en
assert "Descargar JSON de sandbox" in es
assert "Reviewed UNKNOWN items" in en
assert "UNKNOWN revisados" in es
assert "Production baseline origin" in en
assert "Sandbox mode" in en
assert "Sandbox location isolated" in en
assert "Outbound transactions safe" in en
assert "Origen de la línea base de producción" in es
assert "Modo del sandbox" in es
assert "Ubicación del sandbox aislada" in es
assert "Transacciones salientes seguras" in es
if status["dependency_plan"]["summary"]["UNKNOWN"] > 0:
    assert 'value="seo_geo_migration_review_dependency"' in en
    assert 'name="component_id"' in en
    assert 'name="review_decision"' in en
    assert "Save review" in en
    assert 'value="seo_geo_migration_review_dependency"' in es
    assert "Guardar revisión" in es
assert "post_content" not in en
assert "post_content" not in es

missing_es = payload["missing_spanish_html"]
assert "<form" in missing_es.lower()
assert 'admin-post.php' in missing_es
assert 'name="action"' in missing_es
assert 'value="seo_geo_migration_capture_baseline"' in missing_es
assert 'name="_wpnonce"' in missing_es
assert "Iniciar captura segura de línea base SEO/GEO" in missing_es
assert "No cambia el tema, plugins, contenido" in missing_es
assert 'name="batch_size"' in missing_es
assert 'id="seo-geo-baseline-batch-size"' in missing_es
assert 'value="1"' in missing_es
assert 'value="20"' in missing_es
assert "Tamaño de lote (1-20)" in missing_es
assert "Recomendado: 10 en hosting compartido." in missing_es
assert "window.setTimeout" not in missing_es
assert 'value="seo_geo_migration_sandbox_handoff"' not in missing_es

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

wp_cli config delete SEO_GEO_MIGRATION_SANDBOX >/dev/null 2>&1 \
  || fail_smoke "operator-sandbox-marker-cleanup" "Could not restore production marker posture after operator UI fixture" "sandbox marker absent" "wp config delete failed"
wp_cli option update blog_public 1 >/dev/null \
  || fail_smoke "operator-sandbox-search-cleanup" "Could not restore production search visibility after operator UI fixture" "blog_public=1" "wp option update failed"

printf '[smoke] Phase 8I operator UI OK: Tools screen registered; EN/ES catalogs complete; sandbox readiness + bounded dependency detail rendered safely; missing baseline exposes capture only.\n'
