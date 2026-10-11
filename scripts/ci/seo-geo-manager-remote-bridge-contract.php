<?php
/**
 * Offline contract for the SEO/GEO Manager-owned remote bridge.
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$files = array(
	'credentials'   => $root . '/packages/seo-geo-manager/src/Remote/Credentials.php',
	'tokens'        => $root . '/packages/seo-geo-manager/src/Remote/TokenService.php',
	'guard'         => $root . '/packages/seo-geo-manager/src/Remote/RequestGuard.php',
	'audit'         => $root . '/packages/seo-geo-manager/src/Remote/Audit.php',
	'authenticator' => $root . '/packages/seo-geo-manager/src/Remote/Authenticator.php',
	'controller'    => $root . '/packages/seo-geo-manager/src/Rest/RemoteReadController.php',
	'admin'         => $root . '/packages/seo-geo-manager/src/Admin/RemoteAccess.php',
	'plugin'        => $root . '/packages/seo-geo-manager/src/Plugin.php',
	'package'       => $root . '/packages/seo-geo-manager/seo-geo-manager.php',
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
		"'seo.write'",
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
	),
	'audit' => array(
		'MAX_RECORDS = 200',
		"'request_id'",
		"'connection_id'",
		"'scope'",
		"'outcome'",
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
	'controller' => array(
		"'/remote/status'",
		"'/remote/capabilities'",
		"'/remote/site-intelligence'",
		"'/remote/audit'",
		"'site.read'",
		"'site.diagnostics'",
		"'available' => false",
		"'planned_milestone' => 'R3'",
		"'generic_proxy' => false",
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
		'RemoteAccess::register();',
		'RemoteReadController::register_routes();',
	),
	'package' => array(
		'Version: 0.3.50',
		"SEO_GEO_MANAGER_VERSION', '0.3.50'",
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

if ( preg_match( '/register_rest_route[\s\S]*WP_REST_Server::CREATABLE/', $sources['controller'] ) ) {
	fwrite( STDERR, "R2 remote controller must remain read-only; mutation routes belong to R3.\n" );
	exit( 1 );
}

foreach ( array( 'human_password', 'generic_shell', 'generic_rest_proxy' ) as $forbidden ) {
	if ( false !== strpos( strtolower( $sources['credentials'] . $sources['tokens'] ), $forbidden ) ) {
		fwrite( STDERR, "Remote bridge credential layer contains forbidden concept: {$forbidden}\n" );
		exit( 1 );
	}
}

echo json_encode(
	array(
		'ok' => true,
		'version' => '0.3.50',
		'manager_owned_credential' => true,
		'hash_only_persistence' => true,
		'scopes' => true,
		'rotation_and_revocation' => true,
		'replay_rate_idempotency_guards' => true,
		'bounded_audit' => true,
		'local_admin_console' => true,
		'remote_routes_registered' => true,
		'remote_read_only' => true,
		'remote_mutations_available' => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
