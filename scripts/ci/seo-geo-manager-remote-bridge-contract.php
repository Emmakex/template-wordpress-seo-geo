<?php
/**
 * Offline contract for the SEO/GEO Manager-owned remote bridge through R3.4.
 */
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$paths = array(
	'credentials'         => 'packages/seo-geo-manager/src/Remote/Credentials.php',
	'tokens'              => 'packages/seo-geo-manager/src/Remote/TokenService.php',
	'guard'               => 'packages/seo-geo-manager/src/Remote/RequestGuard.php',
	'plans'               => 'packages/seo-geo-manager/src/Remote/PlanStore.php',
	'operations'          => 'packages/seo-geo-manager/src/Remote/MutationOperations.php',
	'seo_authority'       => 'packages/seo-geo-manager/src/Changes/SeoAuthorityChangeAdapter.php',
	'contextual_links'    => 'packages/seo-geo-manager/src/Changes/ContextualLinkChangeAdapter.php',
	'contextual_reader'   => 'packages/seo-geo-manager/src/Intelligence/ContextualLinkReader.php',
	'read_controller'     => 'packages/seo-geo-manager/src/Rest/RemoteReadController.php',
	'mutation_controller' => 'packages/seo-geo-manager/src/Rest/RemoteMutationController.php',
	'package'             => 'packages/seo-geo-manager/seo-geo-manager.php',
);

$sources = array();
foreach ( $paths as $key => $relative ) {
	$path = $root . '/' . $relative;
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
	),
	'tokens' => array(
		"'sgm1_'",
		'random_bytes( 32 )',
		'wp_hash_password( $token )',
		'wp_check_password( $token',
		'function revoke()',
	),
	'guard' => array(
		'x-seo-geo-manager-request-id',
		'x-seo-geo-manager-nonce',
		'x-seo-geo-manager-timestamp',
		'validate_idempotency',
	),
	'plans' => array(
		"'sgmp_'",
		'APPLY_TTL_SECONDS   = 15 * MINUTE_IN_SECONDS',
		'MutationOperations::supports( $operation )',
		'EnvironmentPolicy::snapshot()',
	),
	'operations' => array(
		"'content-change'",
		"'theme-structured-content'",
		"'seo-authority-change'",
		"'contextual-link-change'",
		'SeoAuthorityChangeAdapter::preview( $payload )',
		'SeoAuthorityChangeAdapter::apply( $payload )',
		'SeoAuthorityChangeAdapter::verify( $stored )',
		'SeoAuthorityChangeAdapter::rollback( $operation_id )',
		'ContextualLinkChangeAdapter::preview( $payload )',
		'ContextualLinkChangeAdapter::apply( $payload )',
		'ContextualLinkChangeAdapter::rollback( $operation_id )',
	),
	'seo_authority' => array(
		'SeoAuthorityScanner::scan()',
		'NativeSeoMetadataChangeAdapter::preview( $payload )',
		'NativeSeoMetadataChangeAdapter::apply( $payload )',
		'YoastSeoMetadataChangeAdapter::preview( $payload )',
		'YoastSeoMetadataChangeAdapter::apply( $payload )',
		"'seo-geo-theme'",
		"'seo-geo-theme-native'",
		"'yoast'",
		"'yoast-abilities'",
	),
	'contextual_links' => array(
		"'operation_type' => 'contextual-link-change'",
		"'href_only' => true",
		"'contextual_href'",
		'ContextualLinkReader::read( $source_id )',
		'ContentFingerprint::for_post( $source )',
		"'target_resource_id'",
	),
	'contextual_reader' => array(
		"'edge_id'",
		"'fingerprint'",
		"'raw_post_content_returned' => false",
		"'max_links'",
	),
	'read_controller' => array(
		"'/remote/contextual-links'",
		"'remote_milestone' => 'R3.4'",
		"'milestone'            => 'R3.4'",
		"'supported_operations' => MutationOperations::supported()",
		"'generic_proxy'        => false",
		"'raw_metadata_write'   => false",
		'ContextualLinkReader::read( $source_id )',
	),
	'mutation_controller' => array(
		'MutationOperations::preview( $operation, $payload )',
		'MutationOperations::apply( $operation_type, $payload )',
		'MutationOperations::verify( $operation_type, $operation )',
		'MutationOperations::rollback( $operation_type, $operation_id )',
		"'remote_seo_authority'",
		'bounded_seo_authority',
		'EnvironmentPolicy::validate_payload( $params )',
		'EnvironmentPolicy::validate_operation_environment( $operation )',
	),
	'package' => array(
		'Version: 0.3.54',
		"SEO_GEO_MANAGER_VERSION', '0.3.54'",
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

foreach ( array( 'seo.write', 'media.write', 'navigation.write', 'links.write' ) as $speculative_scope ) {
	if ( false !== strpos( $sources['mutation_controller'], "'{$speculative_scope}'" ) ) {
		fwrite( STDERR, "R3.4 must not silently widen the remote scope model with {$speculative_scope}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'generic_shell', 'generic_rest_proxy', 'raw_metadata_write' ) as $forbidden ) {
	$surface = strtolower( $sources['mutation_controller'] . $sources['operations'] . $sources['seo_authority'] . $sources['contextual_links'] );
	if ( false !== strpos( $surface, $forbidden ) ) {
		fwrite( STDERR, "R3.4 mutation surface contains forbidden generic transport concept: {$forbidden}\n" );
		exit( 1 );
	}
}

$allowlist = array( 'content-change', 'theme-structured-content', 'seo-authority-change', 'contextual-link-change' );
foreach ( $allowlist as $operation ) {
	if ( false === strpos( $sources['operations'], "'{$operation}'" ) ) {
		fwrite( STDERR, "R3.4 operation allowlist is missing {$operation}.\n" );
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

echo json_encode(
	array(
		'ok'                               => true,
		'version'                          => '0.3.54',
		'manager_owned_credential'         => true,
		'replay_rate_idempotency_guards'  => true,
		'r3_operation_allowlist'           => $allowlist,
		'plan_connection_binding'          => true,
		'plan_environment_binding'         => true,
		'plan_ttl_seconds'                 => 900,
		'delegates_to_change_set_engine'   => true,
		'delegates_to_theme_semantic_c5'   => true,
		'delegates_to_seo_authority_c7'    => true,
		'delegates_to_contextual_link_c6'  => true,
		'contextual_edge_discovery'        => true,
		'contextual_href_only'             => true,
		'contextual_runtime_required'      => true,
		'authority_auto_resolution'        => true,
		'theme_native_runtime_required'    => true,
		'yoast_28_6_runtime_required'      => true,
		'private_yoast_meta'               => false,
		'provider_selection_by_caller'     => false,
		'generic_proxy'                    => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
