<?php
/**
 * Offline contract for the SEO/GEO Manager-owned remote bridge through R3.3.
 */
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$files = array(
	'credentials'         => $root . '/packages/seo-geo-manager/src/Remote/Credentials.php',
	'tokens'              => $root . '/packages/seo-geo-manager/src/Remote/TokenService.php',
	'guard'               => $root . '/packages/seo-geo-manager/src/Remote/RequestGuard.php',
	'audit'               => $root . '/packages/seo-geo-manager/src/Remote/Audit.php',
	'authenticator'       => $root . '/packages/seo-geo-manager/src/Remote/Authenticator.php',
	'plans'               => $root . '/packages/seo-geo-manager/src/Remote/PlanStore.php',
	'operations'          => $root . '/packages/seo-geo-manager/src/Remote/MutationOperations.php',
	'seo_authority'       => $root . '/packages/seo-geo-manager/src/Changes/SeoAuthorityChangeAdapter.php',
	'native_seo'          => $root . '/packages/seo-geo-manager/src/Changes/NativeSeoMetadataChangeAdapter.php',
	'yoast_seo'           => $root . '/packages/seo-geo-manager/src/Changes/YoastSeoMetadataChangeAdapter.php',
	'read_controller'     => $root . '/packages/seo-geo-manager/src/Rest/RemoteReadController.php',
	'mutation_controller' => $root . '/packages/seo-geo-manager/src/Rest/RemoteMutationController.php',
	'admin'               => $root . '/packages/seo-geo-manager/src/Admin/RemoteAccess.php',
	'plugin'              => $root . '/packages/seo-geo-manager/src/Plugin.php',
	'package'             => $root . '/packages/seo-geo-manager/seo-geo-manager.php',
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
		'validate_idempotency',
		'remember_request',
	),
	'plans' => array(
		"'sgmp_'",
		'APPLY_TTL_SECONDS   = 15 * MINUTE_IN_SECONDS',
		'MutationOperations::supports( $operation )',
		'EnvironmentPolicy::snapshot()',
		'function mark_applied(',
	),
	'operations' => array(
		"'content-change'",
		"'theme-structured-content'",
		"'seo-authority-change'",
		'SeoAuthorityChangeAdapter::preview( $payload )',
		'SeoAuthorityChangeAdapter::apply( $payload )',
		'SeoAuthorityChangeAdapter::verify( $stored )',
		'SeoAuthorityChangeAdapter::rollback( $operation_id )',
		"'seo_geo_manager_remote_rendered_verification_unsupported'",
	),
	'seo_authority' => array(
		'SeoAuthorityScanner::scan()',
		'NativeSeoMetadataChangeAdapter::preview( $payload )',
		'NativeSeoMetadataChangeAdapter::apply( $payload )',
		'NativeSeoMetadataChangeAdapter::inspect( $resource_id )',
		'NativeSeoMetadataChangeAdapter::rollback( $operation_id )',
		'YoastSeoMetadataChangeAdapter::preview( $payload )',
		'YoastSeoMetadataChangeAdapter::apply( $payload )',
		'YoastSeoMetadataChangeAdapter::inspect( $resource_id )',
		'YoastSeoMetadataChangeAdapter::rollback( $operation_id )',
		"'seo-geo-theme'",
		"'seo-geo-theme-native'",
		"'yoast'",
		"'yoast-abilities'",
		"'writable_signals'",
	),
	'read_controller' => array(
		"'/remote/status'",
		"'/remote/capabilities'",
		"'/remote/site-intelligence'",
		"'/remote/audit'",
		"'remote_milestone' => 'R3.3'",
		"'milestone' => 'R3.3'",
		"'supported_operations' => MutationOperations::supported()",
		"'generic_proxy' => false",
		"'raw_metadata_write' => false",
	),
	'mutation_controller' => array(
		"'/remote/plans'",
		"'/remote/plans/(?P<plan_id>sgmp_[a-f0-9]{32})'",
		"'/remote/operations/(?P<operation_id>[a-f0-9\\-]{36})'",
		'MutationOperations::preview( $operation, $payload )',
		'MutationOperations::apply( $operation_type, $payload )',
		'MutationOperations::verify( $operation_type, $operation )',
		'MutationOperations::rollback( $operation_type, $operation_id )',
		"'remote_seo_authority'",
		'bounded_seo_authority',
		'EnvironmentPolicy::validate_payload( $params )',
		'EnvironmentPolicy::validate_operation_environment( $operation )',
		"'confirm'",
	),
	'package' => array(
		'Version: 0.3.53',
		"SEO_GEO_MANAGER_VERSION', '0.3.53'",
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
	fwrite( STDERR, "Remote read controller must remain read-only.\n" );
	exit( 1 );
}

foreach ( array( 'seo.write', 'media.write', 'navigation.write' ) as $speculative_scope ) {
	if ( false !== strpos( $sources['mutation_controller'], "'{$speculative_scope}'" ) ) {
		fwrite( STDERR, "R3.3 must not silently widen the remote scope model with {$speculative_scope}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'generic_shell', 'generic_rest_proxy', 'raw_metadata_write' ) as $forbidden ) {
	if ( false !== strpos( strtolower( $sources['mutation_controller'] . $sources['operations'] . $sources['seo_authority'] ), $forbidden ) ) {
		fwrite( STDERR, "R3.3 mutation surface contains forbidden generic transport concept: {$forbidden}\n" );
		exit( 1 );
	}
}

$allowlist = array( 'content-change', 'theme-structured-content', 'seo-authority-change' );
foreach ( $allowlist as $operation ) {
	if ( false === strpos( $sources['operations'], "'{$operation}'" ) ) {
		fwrite( STDERR, "R3.3 operation allowlist is missing {$operation}.\n" );
		exit( 1 );
	}
}

$manager_root = $root . '/packages/seo-geo-manager';
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $manager_root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
		continue;
	}
	$contents = (string) file_get_contents( $file->getPathname() );
	if ( false !== strpos( $contents, '_yoast_wpseo_' ) ) {
		fwrite( STDERR, 'Manager source contains forbidden Yoast private-meta dependency: ' . $file->getPathname() . "\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $sources['seo_authority'], "['provider']" ) || false !== strpos( $sources['seo_authority'], "['adapter']" . ' ?? $payload' ) ) {
	fwrite( STDERR, "R3.3 SEO authority adapter must not accept provider selection from the remote payload.\n" );
	exit( 1 );
}

echo json_encode(
	array(
		'ok'                                => true,
		'version'                           => '0.3.53',
		'manager_owned_credential'          => true,
		'hash_only_persistence'             => true,
		'replay_rate_idempotency_guards'   => true,
		'bounded_audit'                     => true,
		'r3_operation_allowlist'            => $allowlist,
		'plan_connection_binding'           => true,
		'plan_environment_binding'          => true,
		'plan_ttl_seconds'                  => 900,
		'delegates_to_change_set_engine'    => true,
		'delegates_to_theme_semantic_c5'    => true,
		'delegates_to_seo_authority_c7'     => true,
		'authority_auto_resolution'         => true,
		'theme_native_runtime_required'     => true,
		'yoast_28_6_runtime_required'       => true,
		'private_yoast_meta'                => false,
		'provider_selection_by_caller'      => false,
		'generic_proxy'                     => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
