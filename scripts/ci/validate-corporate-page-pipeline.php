<?php
/**
 * Validate the completed Corporate inner-page + Insights pipeline contract.
 */

declare(strict_types=1);

const CORPORATE_PAGE_MODELS = 'presets/corporate/page-models.json';
const THEME_DIR             = 'packages/seo-geo-theme';
const BRIDGE_DIR            = 'packages/seo-geo-migration-bridge';

/** Fail with one actionable message. */
function fail_corporate_pages( string $message, string $path, mixed $received = null ): never {
	fwrite(
		STDERR,
		json_encode(
			array(
				'schema_version' => 1,
				'step'           => 'corporate-page-pipeline-contract',
				'primary_error'  => $message,
				'file_line'      => $path,
				'received'       => is_scalar( $received ) || null === $received ? $received : wp_json_fallback( $received ),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . PHP_EOL
	);
	exit( 1 );
}

/** JSON fallback usable outside WordPress. */
function wp_json_fallback( mixed $value ): string {
	$result = json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	return is_string( $result ) ? $result : 'unencodable';
}

/** Decode one required JSON object. */
function corporate_page_json( string $path ): array {
	if ( ! is_file( $path ) ) {
		fail_corporate_pages( 'Required JSON document is missing.', $path );
	}
	try {
		$value = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_corporate_pages( 'JSON document is invalid.', $path, $exception->getMessage() );
	}
	if ( ! is_array( $value ) ) {
		fail_corporate_pages( 'JSON document must decode to an object.', $path, gettype( $value ) );
	}

	return $value;
}

$document = corporate_page_json( CORPORATE_PAGE_MODELS );
if ( 1 !== ( $document['schema_version'] ?? null ) || 'corporate' !== ( $document['preset'] ?? null ) ) {
	fail_corporate_pages( 'Corporate page-model identity is invalid.', CORPORATE_PAGE_MODELS, $document );
}

$expected_map = array(
	'work'    => 'corporate-work-v1',
	'about'   => 'corporate-about-v1',
	'contact' => 'corporate-contact-v1',
);
if ( $expected_map !== ( $document['models_by_page'] ?? null ) ) {
	fail_corporate_pages( 'Corporate page-model map is not canonical.', CORPORATE_PAGE_MODELS . '#models_by_page', $document['models_by_page'] ?? null );
}

$models = is_array( $document['native_content_models'] ?? null ) ? $document['native_content_models'] : array();
foreach ( $expected_map as $page_key => $model_name ) {
	$model = $models[ $model_name ] ?? null;
	if (
		! is_array( $model )
		|| 1 !== ( $model['schema_version'] ?? null )
		|| $page_key !== ( $model['page_key'] ?? null )
		|| 'seo-geo-content-slot--' !== ( $model['slot_prefix'] ?? null )
	) {
		fail_corporate_pages( 'Corporate page model identity is invalid.', CORPORATE_PAGE_MODELS . '#native_content_models.' . $model_name, $model );
	}

	$slots       = is_array( $model['slots'] ?? null ) ? $model['slots'] : array();
	$slot_ids    = array();
	$verify      = array();
	foreach ( $slots as $slot ) {
		if (
			! is_array( $slot )
			|| ! is_string( $slot['id'] ?? null )
			|| ! in_array( $slot['type'] ?? null, array( 'text', 'link', 'list' ), true )
			|| ! is_bool( $slot['required'] ?? null )
			|| isset( $slot_ids[ $slot['id'] ] )
		) {
			fail_corporate_pages( 'Corporate page model contains an invalid or duplicated slot.', CORPORATE_PAGE_MODELS . '#' . $model_name, $slot );
		}
		$slot_ids[ $slot['id'] ] = true;
		if ( true === ( $slot['requires_verification'] ?? false ) ) {
			$group = $slot['verification_group'] ?? null;
			if ( ! is_string( $group ) || '' === $group ) {
				fail_corporate_pages( 'Verified slot is missing a verification group.', CORPORATE_PAGE_MODELS . '#' . $model_name . '.' . $slot['id'], $slot );
			}
			$verify[ $group ] = true;
		}
	}

	foreach ( is_array( $model['required_verified_groups'] ?? null ) ? $model['required_verified_groups'] : array() as $group ) {
		if ( ! is_string( $group ) || ! isset( $verify[ $group ] ) ) {
			fail_corporate_pages( 'Required verification group is not backed by model slots.', CORPORATE_PAGE_MODELS . '#' . $model_name . '.required_verified_groups', $group );
		}
	}
	foreach ( is_array( $model['required_any_slots'] ?? null ) ? $model['required_any_slots'] : array() as $required_any ) {
		if ( ! is_array( $required_any ) || array() === $required_any ) {
			fail_corporate_pages( 'required_any_slots entry must be a non-empty slot list.', CORPORATE_PAGE_MODELS . '#' . $model_name, $required_any );
		}
		foreach ( $required_any as $slot_id ) {
			if ( ! is_string( $slot_id ) || ! isset( $slot_ids[ $slot_id ] ) ) {
				fail_corporate_pages( 'required_any_slots references an unknown slot.', CORPORATE_PAGE_MODELS . '#' . $model_name, $slot_id );
			}
		}
	}

	$safety = $model['safety'] ?? null;
	if ( ! is_array( $safety ) || false !== ( $safety['legacy_layout_input'] ?? null ) || false !== ( $safety['fabricated_evidence_allowed'] ?? null ) ) {
		fail_corporate_pages( 'Corporate page models must forbid legacy-layout input and fabricated evidence.', CORPORATE_PAGE_MODELS . '#' . $model_name . '.safety', $safety );
	}
}

$work = $models['corporate-work-v1'];
if ( array( 'case-study' ) !== ( $work['required_verified_groups'] ?? null ) ) {
	fail_corporate_pages( 'Work must require a verified case study before readiness.', CORPORATE_PAGE_MODELS . '#corporate-work-v1.required_verified_groups', $work['required_verified_groups'] ?? null );
}

$contact = $models['corporate-contact-v1'];
if ( array( array( 'contact-email', 'contact-phone' ) ) !== ( $contact['required_any_slots'] ?? null ) ) {
	fail_corporate_pages( 'Contact must require at least one real direct contact method.', CORPORATE_PAGE_MODELS . '#corporate-contact-v1.required_any_slots', $contact['required_any_slots'] ?? null );
}

$slot_sources = array(
	THEME_DIR . '/patterns/author-profile.php' => array( 'author-name', 'author-role', 'author-bio', 'author-profile-link' ),
	THEME_DIR . '/patterns/contact.php'        => array( 'contact-heading', 'contact-intro', 'contact-email', 'contact-phone', 'contact-area', 'contact-primary-cta' ),
);
foreach ( $slot_sources as $path => $required_slots ) {
	$content = is_file( $path ) ? (string) file_get_contents( $path ) : '';
	foreach ( $required_slots as $slot_id ) {
		if ( ! str_contains( $content, 'seo-geo-content-slot--' . $slot_id ) ) {
			fail_corporate_pages( 'Base pattern is missing a required semantic slot marker.', $path, $slot_id );
		}
	}
}

$home_template = THEME_DIR . '/templates/home.html';
$home_markup   = is_file( $home_template ) ? (string) file_get_contents( $home_template ) : '';
foreach ( array( 'wp:seo-geo/insights-title', 'wp:query', 'wp:post-title', 'wp:post-excerpt', 'wp:query-pagination' ) as $marker ) {
	if ( ! str_contains( $home_markup, $marker ) ) {
		fail_corporate_pages( 'Native Insights template is incomplete.', $home_template, $marker );
	}
}
if ( preg_match( '/<h1\b/i', $home_markup ) ) {
	fail_corporate_pages( 'Insights template must leave H1 ownership to the dynamic title block.', $home_template, 'hard-coded h1' );
}

$required_files = array(
	THEME_DIR . '/inc/Insights/InsightsIndexRuntime.php',
	BRIDGE_DIR . '/src/Reset/CorporatePageContentKit.php',
	BRIDGE_DIR . '/src/Reset/NativeCorporatePageHydrator.php',
	BRIDGE_DIR . '/src/Reset/CorporatePageSeoHandoff.php',
	BRIDGE_DIR . '/src/Reset/CorporatePageReadiness.php',
	BRIDGE_DIR . '/src/Reset/AdminCorporatePagePipelineController.php',
	BRIDGE_DIR . '/src/Reset/CorporateInsightsManager.php',
	BRIDGE_DIR . '/src/Reset/AdminCorporateInsightsController.php',
	'examples/content-blueprints/emmake-services.es_ES.json',
);
foreach ( $required_files as $path ) {
	if ( ! is_file( $path ) ) {
		fail_corporate_pages( 'Required Corporate page pipeline file is missing.', $path );
	}
}

$bootstrap = (string) file_get_contents( BRIDGE_DIR . '/seo-geo-migration-bridge.php' );
foreach ( array( 'AdminCorporatePagePipelineController::boot_from_plugin()', 'AdminCorporateInsightsController::boot_from_plugin()' ) as $marker ) {
	if ( ! str_contains( $bootstrap, $marker ) ) {
		fail_corporate_pages( 'Migration Bridge bootstrap is missing a Corporate page pipeline entrypoint.', BRIDGE_DIR . '/seo-geo-migration-bridge.php', $marker );
	}
}

$services = corporate_page_json( 'examples/content-blueprints/emmake-services.es_ES.json' );
if (
	'corporate-page-content-blueprint' !== ( $services['mode'] ?? null )
	|| 'services' !== ( $services['page_key'] ?? null )
	|| 'corporate-services-v1' !== ( $services['model'] ?? null )
	|| true === ( $services['verified_groups']['proof'] ?? false )
) {
	fail_corporate_pages( 'Emmake Services blueprint must stay portable and evidence-conservative.', 'examples/content-blueprints/emmake-services.es_ES.json', $services );
}

printf( "Corporate page pipeline contract OK: Services + Work/About/Contact models + native Insights index are complete.\n" );
