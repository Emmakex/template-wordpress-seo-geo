<?php
/**
 * Offline contract for the SEO/GEO Manager-owned remote bridge through R3.
 */
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$files = array(
	'credentials' => $root . '/packages/seo-geo-manager/src/Remote/Credentials.php',
	'tokens' => $root . '/packages/seo-geo-manager/src/Remote/TokenService.php',
	'guard' => $root . '/packages/seo-geo-manager/src/Remote/RequestGuard.php',
	'audit' => $root . '/packages/seo-geo-manager/src/Remote/Audit.php',
	'authenticator' => $root . '/packages/seo-geo-manager/src/Remote/Authenticator.php',
	'plans' => $root . '/packages/seo-geo-manager/src/Remote/PlanStore.php',
	'read_controller' => $root . '/packages/seo-geo-manager/src/Rest/RemoteReadController.php',
	'mutation_controller' => $root . '/packages/seo-geo-manager/src/Rest/RemoteMutationController.php',
	'admin' => $root . '/packages/seo-geo-manager/src/Admin/RemoteAccess.php',
	'plugin' => $root . '/packages/seo-geo-manager/src/Plugin.php',
	'package' => $root . '/packages/seo-geo-manager/seo-geo-manager.php',
);

$sources = array();
foreach ( $files as $key => $path ) {
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "Remote bridge contract missing {$key}: {$path}\n" );
		exit( 1 );
	}
	$sources[ $key ] = (string) file_get_contents( $path );
}

$required = array(
	'credentials' => array(
		"'site.read'",
		"'site.diagnostics'",
		"'content.write'",
		"'operations.read'",
		"'operations.apply'",
		"'operations.rollback'",
		"'credential_hash'",
		"'credential_prefix'",
		"'sgmc_'",
	),
	'tokens' => array(
		"'sgm1_'",
		'random_bytes( 32 )',
		'wp_hash_password( $token )',
		'wp_check_password( $token',
		'function revoke()',
		'function rotate(',
	),
	'guard' => array(
		'x-seo-geo-manager-request-id',
		'x-seo-geo-manager-nonce',
		'x-seo-geo-manager-timestamp',
		'CLOCK_SKEW_SECONDS',
		'REPLAY_TTL_SECONDS',
		'RATE_LIMIT_PER_WINDOW',
		'validate_idempotency',
		'remember_request',
	),
	'audit' => array(
		'MAX_RECORDS = 200',
		"'request_id'",
		"'connection_id'",
		"'scope'",
		"'outcome'",
		"'plan_id'",
		"'operation_id'",
	),
	'authenticator' => array(
		'function authorize(',
		'bearer_credential',
		'has_scope( $connection, $required_scope )',
		'enforce_rate_limit',
		'consume_nonce',
		'wp_set_current_user',
		"current_user_can( 'manage_options' )",
		'record_success',
	),
	'plans' => array(
		"'sgmp_'",
		'APPLY_TTL_SECONDS   = 15 * MINUTE_IN_SECONDS',
		'STORAGE_TTL_SECONDS = HOUR_IN_SECONDS',
		"'connection_id'",
		"'wordpress_user_id'",
		"'environment'",
		"'expires_at_unix'",
		"'checksum'",
		'EnvironmentPolicy::snapshot()',
		'function mark_applied(',
	),
	'read_controller' => array(
		"'/remote/status'",
		"'/remote/capabilities'",
		"'/remote/site-intelligence'",
		"'/remote/audit'",
		"'remote_milestone' => 'R3'",
		"'available' => true",
		"'supported_operations' => array( 'content-change' )",
		"'generic_proxy' => false",
		"'raw_metadata_write' => false",
	),
	'mutation_controller' => array(
		"'/remote/plans'",
		"'/remote/plans/(?P<plan_id>sgmp_[a-f0-9]{32})'",
		"'/remote/operations/(?P<operation_id>[a-f0-9\\-]{36})'",
		"'content.write'",
		"'operations.read'",
		"'operations.apply'",
		"'operations.rollback'",
		"'content-change'",
		'ChangeSetEngine::preview( $payload )',
		'ChangeSetEngine::apply( $payload )',
		'ChangeSetEngine::rollback( $operation_id )',
		'EnvironmentPolicy::validate_payload( $params )',
		'EnvironmentPolicy::validate_operation_environment( $operation )',
		"'remote_connection_id'",
		"'remote_plan_id'",
		"'remote_actor_user_id'",
		"'confirm'",
	),
	'admin' => array(
		'Conexión remota',
		'manage_options',
		'Generar / reemplazar credencial',
		'Rotar',
		'Revocar',
		'Guardar scopes',
		'delete_transient( self::token_key() )',
		'wp_nonce_field( \'seo_geo_manager_remote_connection\' )',
	),
	'plugin' => array(
		'use SeoGeo\\Manager\\Admin\\RemoteAccess;',
		'use SeoGeo\\Manager\\Rest\\RemoteReadController;',
		'use SeoGeo\\Manager\\Rest\\RemoteMutationController;',
		'RemoteAccess::register();',
		'RemoteReadController::register_routes();',
		'RemoteMutationController::register_routes();',
	),
	'package' => array(
		'Version: 0.3.51',
		"SEO_GEO_MANAGER_VERSION', '0.3.51'",
	),
);

foreach ( $required as $source_key => $markers ) {
	foreach ( $markers as $marker ) {
		if ( false === strpos( $sources[ $source_key ], $marker ) ) {
			fwrite( STDERR, "Remote bridge contract missing {$source_key} marker: {$marker}\n" );
			exit( 1 );
		}
	}
}

if ( preg_match( '/register_rest_route[\s\S]*WP_REST_Server::CREATABLE/', $sources['read_controller'] ) ) {
	fwrite( STDERR, "Remote read controller must remain read-only; mutations belong to the bounded R3 controller.\n" );
	exit( 1 );
}

foreach ( array( 'seo.write', 'media.write', 'navigation.write' ) as $forbidden_scope ) {
	if ( false !== strpos( $sources['mutation_controller'], "'{$forbidden_scope}'" ) ) {
		fwrite( STDERR, "Initial R3 controller must not expose speculative {$forbidden_scope} mutation support.\n" );
		exit( 1 );
	}
}

foreach ( array( 'generic_shell', 'generic_rest_proxy', 'raw_metadata_write' ) as $forbidden ) {
	if ( false !== strpos( strtolower( $sources['mutation_controller'] ), $forbidden ) ) {
		fwrite( STDERR, "R3 mutation controller contains forbidden generic transport concept: {$forbidden}\n" );
		exit( 1 );
	}
}

echo json_encode(
	array(
		'ok' => true,
		'version' => '0.3.51',
		'manager_owned_credential' => true,
		'hash_only_persistence' => true,
		'scopes' => true,
		'rotation_and_revocation' => true,
		'replay_rate_idempotency_guards' => true,
		'bounded_audit' => true,
		'local_admin_console' => true,
		'remote_read_routes_registered' => true,
		'r3_mutations_available' => true,
		'r3_operation_allowlist' => array( 'content-change' ),
		'plan_connection_binding' => true,
		'plan_environment_binding' => true,
		'plan_ttl_seconds' => 900,
		'delegates_to_change_set_engine' => true,
		'generic_proxy' => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
