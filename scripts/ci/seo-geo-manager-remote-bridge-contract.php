<?php
/**
 * Offline contract for the SEO/GEO Manager-owned remote bridge foundation.
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$files = array(
	'credentials' => $root . '/packages/seo-geo-manager/src/Remote/Credentials.php',
	'tokens'      => $root . '/packages/seo-geo-manager/src/Remote/TokenService.php',
	'guard'       => $root . '/packages/seo-geo-manager/src/Remote/RequestGuard.php',
	'audit'       => $root . '/packages/seo-geo-manager/src/Remote/Audit.php',
	'admin'       => $root . '/packages/seo-geo-manager/src/Admin/RemoteAccess.php',
	'plugin'      => $root . '/packages/seo-geo-manager/src/Plugin.php',
	'package'     => $root . '/packages/seo-geo-manager/seo-geo-manager.php',
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
		'RemoteAccess::register();',
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
		'remote_routes_registered' => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
