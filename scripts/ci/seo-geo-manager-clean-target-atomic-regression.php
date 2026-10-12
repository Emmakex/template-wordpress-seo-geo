<?php
/**
 * Static regression guard for the real EMMAKE clean-target atomic field failure.
 *
 * The runtime atomic acceptance already exercises the authoritative-301 path and
 * clean-target acceptance exercises one-hop planning. This guard binds both
 * contracts so the atomic verifier cannot silently reject the clean-target mode.
 */

declare(strict_types=1);

$root       = dirname( __DIR__, 2 );
$engine     = file_get_contents( $root . '/packages/seo-geo-manager/src/Changes/PermalinkRedirectChangeEngine.php' );
$finalizer  = file_get_contents( $root . '/packages/seo-geo-manager/assets/admin/finalization-actions.js' );
$history_ui = file_get_contents( $root . '/packages/seo-geo-manager/assets/admin/operation-history.js' );

if ( ! is_string( $engine ) || ! is_string( $finalizer ) || ! is_string( $history_ui ) ) {
	throw new RuntimeException( 'Could not read Manager regression targets.' );
}

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$assert(
	false !== strpos( $engine, "array( 'authoritative-301', 'one-hop-301-to-clean-target' )" ),
	'Atomic verifier does not accept the clean-target 301 preservation mode.'
);
$assert(
	false !== strpos( $engine, '0 === strcmp( $original_mode, $verified_mode )' ),
	'Atomic verifier no longer requires preservation mode stability after Apply.'
);
$assert(
	false !== strpos( $engine, "'seo_preservation_mode'        => (string) ( \$plan['seo_preservation_mode']" ),
	'Applied operation does not persist the actual verified SEO preservation mode.'
);
$assert(
	false !== strpos( $finalizer, 'error?.data?.verification' ) && false !== strpos( $finalizer, 'operation_id' ),
	'Focused UI no longer exposes exact atomic verification evidence.'
);
$assert(
	false !== strpos( $finalizer, 'technicalBody.appendChild( fieldEvidence )' ),
	'Field evidence is no longer moved out of the focused operator flow.'
);
$assert(
	false === strpos( $history_ui, ".seo-geo-manager-admin__technical-body" ),
	'Operation Evidence regressed back into advanced technical details.'
);
$assert(
	false !== strpos( $history_ui, "'[data-seo-geo-media-alt-workspace]'" )
	&& false !== strpos( $history_ui, "insertionAnchor.insertAdjacentElement( 'afterend', section )" ),
	'Operation Evidence is no longer anchored in the main Manager workflow.'
);
$assert(
	false !== strpos( $history_ui, 'response.privacy_safe' )
	&& false !== strpos( $history_ui, "'operation-history-summary' !== response.mode" ),
	'Operation Evidence no longer verifies the privacy-bounded history response.'
);

echo json_encode(
	array(
		'ok'                              => true,
		'clean_target_atomic_mode'        => true,
		'preservation_mode_stable'        => true,
		'verification_evidence_visible'   => true,
		'operation_evidence_main_flow'    => true,
		'operation_evidence_privacy_safe' => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
