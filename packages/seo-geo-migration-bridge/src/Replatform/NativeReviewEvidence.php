<?php
/**
 * Privacy-bounded review evidence for Native Replatform drafts.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Replatform;

use WP_Post;

/**
 * Builds a read-only acceptance snapshot without source or draft body content.
 */
final class NativeReviewEvidence {
	/**
	 * Construct the evidence service.
	 *
	 * @param NativeCompositionService $composer Native composition authority.
	 */
	public function __construct( private NativeCompositionService $composer ) {
	}

	/**
	 * Build bounded, deterministic review evidence for one native draft.
	 *
	 * @param int $draft_id Native draft ID.
	 * @return array<string,mixed>
	 */
	public function snapshot( int $draft_id ): array {
		$blockers      = array();
		$draft         = get_post( $draft_id );
		$sandbox_active = defined( 'SEO_GEO_MIGRATION_SANDBOX' )
			&& true === constant( 'SEO_GEO_MIGRATION_SANDBOX' );

		if ( ! $sandbox_active ) {
			$blockers[] = 'sandbox-required';
		}
		if ( ! $draft instanceof WP_Post || 'page' !== $draft->post_type ) {
			$blockers[] = 'draft-not-found';
		} elseif ( 'draft' !== $draft->post_status ) {
			$blockers[] = 'draft-status-not-allowed';
		}

		$source_id         = (int) get_post_meta( $draft_id, NativeCompositionService::SOURCE_ID_META, true );
		$stored_source_sha = (string) get_post_meta( $draft_id, NativeCompositionService::SOURCE_SHA_META, true );
		$preset            = sanitize_key( (string) get_post_meta( $draft_id, NativeCompositionService::PRESET_META, true ) );
		$page_key          = sanitize_key( (string) get_post_meta( $draft_id, NativeCompositionService::PAGE_KEY_META, true ) );
		$stored_plan_sha   = (string) get_post_meta( $draft_id, NativeCompositionService::PLAN_SHA_META, true );
		$stored_remap_sha  = (string) get_post_meta( $draft_id, NativeCompositionService::REMAP_PLAN_SHA_META, true );
		$ledger            = get_post_meta( $draft_id, ReviewedRemapApplier::LEDGER_META, true );
		$backup            = get_post_meta( $draft_id, ReviewedRemapApplier::BACKUP_META, true );
		$rollback          = get_post_meta( $draft_id, ReviewedRemapApplier::ROLLBACK_META, true );

		if ( 0 >= $source_id || '' === $stored_source_sha || '' === $preset || '' === $page_key || '' === $stored_plan_sha || '' === $stored_remap_sha ) {
			$blockers[] = 'draft-replatform-metadata-incomplete';
		}
		if ( ! is_array( $ledger ) ) {
			$blockers[] = 'reviewed-apply-ledger-missing';
		}

		$current_plan = '' !== $page_key && '' !== $preset ? $this->composer->plan( $page_key, $preset ) : array();
		if ( true !== ( $current_plan['ready'] ?? false ) ) {
			$blockers[] = 'current-native-plan-not-ready';
		}

		$current_source     = is_array( $current_plan['source'] ?? null ) ? $current_plan['source'] : array();
		$current_remap      = is_array( $current_plan['content_remap'] ?? null ) ? $current_plan['content_remap'] : array();
		$current_draft_sha  = $draft instanceof WP_Post ? hash( 'sha256', (string) $draft->post_content ) : '';
		$current_source_sha = 0 < $source_id ? hash( 'sha256', (string) get_post_field( 'post_content', $source_id ) ) : '';

		if ( (int) ( $current_source['id'] ?? 0 ) !== $source_id ) {
			$blockers[] = 'source-identity-drift';
		}
		if ( '' !== $stored_source_sha && ! hash_equals( $stored_source_sha, $current_source_sha ) ) {
			$blockers[] = 'source-content-drift';
		}
		if ( '' !== $stored_plan_sha && ! hash_equals( $stored_plan_sha, (string) ( $current_plan['plan_sha256'] ?? '' ) ) ) {
			$blockers[] = 'native-plan-drift';
		}
		if ( '' !== $stored_remap_sha && ! hash_equals( $stored_remap_sha, (string) ( $current_remap['plan_sha256'] ?? '' ) ) ) {
			$blockers[] = 'content-remap-plan-drift';
		}

		$ledger_after  = is_array( $ledger ) ? (string) ( $ledger['after_sha256'] ?? '' ) : '';
		$ledger_before = is_array( $ledger ) ? (string) ( $ledger['before_sha256'] ?? '' ) : '';
		if ( '' === $ledger_after || '' === $current_draft_sha || ! hash_equals( $ledger_after, $current_draft_sha ) ) {
			$blockers[] = 'reviewed-draft-drift';
		}

		$backup_sha         = is_array( $backup ) ? (string) ( $backup['sha256'] ?? '' ) : '';
		$backup_body        = is_array( $backup ) && isset( $backup['content'] ) && is_string( $backup['content'] )
			? $backup['content']
			: '';
		$rollback_available = '' !== $backup_sha
			&& '' !== $ledger_before
			&& hash_equals( $ledger_before, $backup_sha )
			&& hash_equals( $backup_sha, hash( 'sha256', $backup_body ) );
		if ( ! $rollback_available ) {
			$blockers[] = 'reviewed-rollback-evidence-invalid';
		}

		$selected_assets   = is_array( $ledger ) && is_array( $ledger['selected_assets'] ?? null )
			? $ledger['selected_assets']
			: array();
		$unmapped_assets   = is_array( $ledger ) && is_array( $ledger['unmapped_assets'] ?? null )
			? $ledger['unmapped_assets']
			: array();
		$verified_sections = is_array( $ledger ) && is_array( $ledger['verified_sections'] ?? null )
			? array_values( array_map( 'strval', $ledger['verified_sections'] ) )
			: array();
		sort( $verified_sections );

		$evidence = array(
			'schema_version'    => 1,
			'mode'              => 'native-replatform-review-evidence',
			'draft'             => array(
				'id'     => $draft_id,
				'status' => $draft instanceof WP_Post ? $draft->post_status : '',
				'sha256' => $current_draft_sha,
			),
			'source'            => array(
				'id'        => $source_id,
				'path'      => (string) ( $current_source['path'] ?? '' ),
				'sha256'    => $current_source_sha,
				'unchanged' => '' !== $stored_source_sha && hash_equals( $stored_source_sha, $current_source_sha ),
			),
			'preset'            => $preset,
			'page_key'          => $page_key,
			'native_plan_sha'   => $stored_plan_sha,
			'content_remap_sha' => $stored_remap_sha,
			'selection_sha256'  => is_array( $ledger ) ? (string) ( $ledger['selection_sha256'] ?? '' ) : '',
			'verified_sections' => $verified_sections,
			'selected_counts'   => $this->asset_counts( $selected_assets ),
			'unmapped_counts'   => $this->asset_counts( $unmapped_assets ),
			'rollback'          => array(
				'available'             => $rollback_available,
				'pre_apply_sha256'      => $backup_sha,
				'prior_rollback_exists' => is_array( $rollback ),
			),
			'safety'            => array(
				'sandbox_only'                  => true,
				'sandbox_active'                => $sandbox_active,
				'content_included'              => false,
				'private_payload_included'      => false,
				'source_post_mutation'          => false,
				'production_cutover_authorized' => false,
				'draft_only'                    => $draft instanceof WP_Post && 'draft' === $draft->post_status,
			),
		);

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		$evidence['status']          = array() === $blockers ? 'ready' : 'blocked';
		$evidence['blockers']        = $blockers;
		$evidence['evidence_sha256'] = hash(
			'sha256',
			(string) wp_json_encode( $evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		return $evidence;
	}

	/**
	 * Count only asset IDs by type; never emit source content.
	 *
	 * @param array<string,mixed> $assets Asset-ID lists keyed by type.
	 * @return array{units:int,links:int,media:int}
	 */
	private function asset_counts( array $assets ): array {
		return array(
			'units' => is_array( $assets['units'] ?? null ) ? count( $assets['units'] ) : 0,
			'links' => is_array( $assets['links'] ?? null ) ? count( $assets['links'] ) : 0,
			'media' => is_array( $assets['media'] ?? null ) ? count( $assets['media'] ) : 0,
		);
	}
}
