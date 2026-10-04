<?php
/**
 * Reviewed Content Remap application for Native Replatform drafts.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Replatform;

use WP_Error;
use WP_Post;

/**
 * Applies explicitly reviewed source assets to deterministic native remap slots.
 */
final class ReviewedRemapApplier {
	public const LEDGER_META   = '_seo_geo_replatform_reviewed_apply_v1';
	public const BACKUP_META   = '_seo_geo_replatform_reviewed_apply_backup_v1';
	public const ROLLBACK_META = '_seo_geo_replatform_reviewed_apply_rollback_v1';

	/**
	 * Construct the reviewed applier.
	 *
	 * @param NativeCompositionService $composer Native composition authority.
	 */
	public function __construct( private NativeCompositionService $composer ) {
	}

	/**
	 * Build a non-mutating reviewed-apply plan.
	 *
	 * @param int                 $draft_id          Native draft ID.
	 * @param array<string,mixed> $selections        Reviewed asset selections by semantic section.
	 * @param array<int,string>   $verified_sections Sections explicitly verified by an administrator.
	 * @return array<string,mixed>
	 */
	public function plan( int $draft_id, array $selections, array $verified_sections = array() ): array {
		$blockers        = array();
		$marker_blockers = array();
		$draft           = get_post( $draft_id );

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

		if ( 0 >= $source_id || '' === $stored_source_sha || '' === $preset || '' === $page_key || '' === $stored_plan_sha || '' === $stored_remap_sha ) {
			$blockers[] = 'draft-replatform-metadata-incomplete';
		}

		$current_plan = '' !== $page_key && '' !== $preset ? $this->composer->plan( $page_key, $preset ) : array();
		if ( true !== ( $current_plan['ready'] ?? false ) ) {
			$blockers[] = 'current-native-plan-not-ready';
		}

		$current_source = is_array( $current_plan['source'] ?? null ) ? $current_plan['source'] : array();
		$current_remap  = is_array( $current_plan['content_remap'] ?? null ) ? $current_plan['content_remap'] : array();

		if ( (int) ( $current_source['id'] ?? 0 ) !== $source_id ) {
			$blockers[] = 'source-identity-drift';
		}
		if ( '' !== $stored_source_sha && ! hash_equals( $stored_source_sha, (string) ( $current_source['content_sha256'] ?? '' ) ) ) {
			$blockers[] = 'source-content-drift';
		}
		if ( '' !== $stored_plan_sha && ! hash_equals( $stored_plan_sha, (string) ( $current_plan['plan_sha256'] ?? '' ) ) ) {
			$blockers[] = 'native-plan-drift';
		}
		if ( '' !== $stored_remap_sha && ! hash_equals( $stored_remap_sha, (string) ( $current_remap['plan_sha256'] ?? '' ) ) ) {
			$blockers[] = 'content-remap-plan-drift';
		}

		$normalized     = $this->normalize_selections( $selections );
		$verified       = array_values( array_unique( array_map( 'sanitize_key', $verified_sections ) ) );
		$slot_map       = $this->slot_map( is_array( $current_plan['remap_slots'] ?? null ) ? $current_plan['remap_slots'] : array() );
		$remap_slot_map = $this->content_slot_map( is_array( $current_remap['slots'] ?? null ) ? $current_remap['slots'] : array() );
		$asset_maps     = $this->asset_maps( is_array( $current_remap['assets'] ?? null ) ? $current_remap['assets'] : array() );
		$operations     = array();
		$selection_ids  = array(
			'units' => array(),
			'links' => array(),
			'media' => array(),
		);

		if ( array() === $normalized ) {
			$blockers[] = 'reviewed-selection-empty';
		}

		foreach ( $normalized as $section => $selection ) {
			if ( ! isset( $slot_map[ $section ] ) ) {
				$blockers[] = 'section-has-no-native-slot:' . $section;
				continue;
			}

			$content_slot = $remap_slot_map[ $section ] ?? null;
			if ( ! is_array( $content_slot ) ) {
				$blockers[] = 'section-has-no-content-plan:' . $section;
				continue;
			}

			if ( true === ( $content_slot['requires_verification'] ?? false ) && ! in_array( $section, $verified, true ) ) {
				$blockers[] = 'section-verification-required:' . $section;
			}

			$selected_count = 0;
			foreach ( array( 'units', 'links', 'media' ) as $asset_type ) {
				$candidates = is_array( $content_slot['candidates'][ $asset_type ] ?? null )
					? array_map( 'strval', $content_slot['candidates'][ $asset_type ] )
					: array();

				foreach ( $selection[ $asset_type ] as $asset_id ) {
					++$selected_count;
					if ( ! in_array( $asset_id, $candidates, true ) ) {
						$blockers[] = 'asset-not-candidate:' . $section . ':' . $asset_id;
						continue;
					}
					if ( ! isset( $asset_maps[ $asset_type ][ $asset_id ] ) ) {
						$blockers[] = 'asset-missing-from-inventory:' . $section . ':' . $asset_id;
						continue;
					}
					$selection_ids[ $asset_type ][] = $asset_id;
				}
			}

			if ( 0 === $selected_count ) {
				$blockers[] = 'section-selection-empty:' . $section;
			}

			$marker = NativeCompositionService::slot_marker( $section );
			$count  = $draft instanceof WP_Post ? substr_count( (string) $draft->post_content, $marker ) : 0;
			if ( 1 !== $count ) {
				$marker_blockers[] = 'native-slot-marker-count:' . $section . ':' . $count;
			}

			$operations[] = array(
				'section'       => $section,
				'after_pattern' => $slot_map[ $section ],
				'marker'        => $marker,
				'selection'     => $selection,
				'verified'      => in_array( $section, $verified, true ),
			);
		}

		foreach ( $selection_ids as &$ids ) {
			$ids = array_values( array_unique( $ids ) );
			sort( $ids );
		}
		unset( $ids );

		$selection_material = array(
			'draft_id'          => $draft_id,
			'source_id'         => $source_id,
			'preset'            => $preset,
			'page_key'          => $page_key,
			'native_plan_sha'   => $stored_plan_sha,
			'content_remap_sha' => $stored_remap_sha,
			'selections'        => $normalized,
			'verified_sections' => $verified,
		);
		$selection_sha      = hash(
			'sha256',
			(string) wp_json_encode( $selection_material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		$current_ledger = get_post_meta( $draft_id, self::LEDGER_META, true );
		$existing       = is_array( $current_ledger )
			&& hash_equals( (string) ( $current_ledger['selection_sha256'] ?? '' ), $selection_sha )
			&& $draft instanceof WP_Post
			&& hash_equals( (string) ( $current_ledger['after_sha256'] ?? '' ), hash( 'sha256', (string) $draft->post_content ) );

		if ( ! $existing ) {
			$blockers = array_merge( $blockers, $marker_blockers );
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		return array(
			'schema_version'    => 1,
			'mode'              => 'reviewed-remap-apply-plan',
			'ready'             => array() === $blockers,
			'draft_id'          => $draft_id,
			'source_id'         => $source_id,
			'preset'            => $preset,
			'page_key'          => $page_key,
			'native_plan_sha'   => $stored_plan_sha,
			'content_remap_sha' => $stored_remap_sha,
			'selection_sha256'  => $selection_sha,
			'existing'          => $existing,
			'selections'        => $normalized,
			'verified_sections' => $verified,
			'selected_assets'   => $selection_ids,
			'operations'        => $operations,
			'blockers'          => $blockers,
			'safety'            => array(
				'sandbox_only'                    => true,
				'draft_only'                      => true,
				'source_post_mutation'            => false,
				'candidate_only'                  => true,
				'sensitive_requires_verification' => true,
				'public_post_creation'            => false,
			),
		);
	}

	/**
	 * Apply reviewed selections to native slot markers.
	 *
	 * @param int                 $draft_id          Native draft ID.
	 * @param array<string,mixed> $selections        Reviewed asset selections by semantic section.
	 * @param array<int,string>   $verified_sections Sections explicitly verified by an administrator.
	 * @return array<string,mixed>|WP_Error
	 */
	public function apply( int $draft_id, array $selections, array $verified_sections = array() ): array|WP_Error {
		if ( ! $this->sandbox_active() ) {
			return new WP_Error( 'seo_geo_reviewed_remap_sandbox_required', 'Reviewed remap application may only run inside an accepted sandbox.' );
		}
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $draft_id ) ) {
			return new WP_Error( 'seo_geo_reviewed_remap_forbidden', 'Administrator edit capability is required.' );
		}

		$plan = $this->plan( $draft_id, $selections, $verified_sections );
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_reviewed_remap_not_ready',
				'Reviewed remap plan is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$draft = get_post( $draft_id );
		if ( ! $draft instanceof WP_Post || 'draft' !== $draft->post_status ) {
			return new WP_Error( 'seo_geo_reviewed_remap_draft_drift', 'Native draft became unavailable or publishable before reviewed application.' );
		}

		$current_ledger = get_post_meta( $draft_id, self::LEDGER_META, true );
		if (
			is_array( $current_ledger )
			&& hash_equals( (string) ( $current_ledger['selection_sha256'] ?? '' ), (string) $plan['selection_sha256'] )
			&& hash_equals( (string) ( $current_ledger['after_sha256'] ?? '' ), hash( 'sha256', (string) $draft->post_content ) )
		) {
			return array(
				'schema_version'   => 1,
				'mode'             => 'reviewed-remap-apply',
				'status'           => 'existing',
				'draft_id'         => $draft_id,
				'selection_sha256' => (string) $plan['selection_sha256'],
				'ledger'           => $current_ledger,
			);
		}

		$current_plan = $this->composer->plan( (string) $plan['page_key'], (string) $plan['preset'] );
		$remap        = is_array( $current_plan['content_remap'] ?? null ) ? $current_plan['content_remap'] : array();
		$asset_maps   = $this->asset_maps( is_array( $remap['assets'] ?? null ) ? $remap['assets'] : array() );
		$content      = (string) $draft->post_content;
		$before_sha   = hash( 'sha256', $content );

		foreach ( $plan['operations'] as $operation ) {
			if ( ! is_array( $operation ) ) {
				continue;
			}

			$section   = (string) ( $operation['section'] ?? '' );
			$selection = is_array( $operation['selection'] ?? null ) ? $operation['selection'] : array();
			$marker    = (string) ( $operation['marker'] ?? '' );
			$rendered  = $this->render_section( $section, $selection, $asset_maps, (string) $plan['selection_sha256'] );

			$content = str_replace( $marker, $rendered, $content, $replacements );
			if ( 1 !== $replacements ) {
				return new WP_Error( 'seo_geo_reviewed_remap_marker_drift', 'A native remap slot changed before reviewed application.' );
			}
		}

		if ( ! is_array( $current_ledger ) ) {
			update_post_meta(
				$draft_id,
				self::BACKUP_META,
				array(
					'schema_version' => 1,
					'created_at'     => gmdate( DATE_ATOM ),
					'content'        => (string) $draft->post_content,
					'sha256'         => $before_sha,
				)
			);
		}

		$updated = wp_update_post(
			array(
				'ID'           => $draft_id,
				'post_content' => $content,
			),
			true
		);
		if ( $updated instanceof WP_Error ) {
			return $updated;
		}

		$after = get_post( $draft_id );
		if ( ! $after instanceof WP_Post || 'draft' !== $after->post_status ) {
			return new WP_Error( 'seo_geo_reviewed_remap_verification_failed', 'Reviewed native draft could not be reloaded safely.' );
		}

		$current_source_sha  = hash( 'sha256', (string) get_post_field( 'post_content', (int) $plan['source_id'] ) );
		$expected_source_sha = (string) ( $current_plan['source']['content_sha256'] ?? '' );
		if ( '' === $expected_source_sha || ! hash_equals( $expected_source_sha, $current_source_sha ) ) {
			wp_update_post(
				array(
					'ID'           => $draft_id,
					'post_content' => (string) $draft->post_content,
				)
			);
			return new WP_Error( 'seo_geo_reviewed_remap_source_drift', 'Preserved source changed during reviewed application; draft mutation was reverted.' );
		}

		$after_sha = hash( 'sha256', (string) $after->post_content );
		$ledger    = array(
			'schema_version'    => 1,
			'applied_at'        => gmdate( DATE_ATOM ),
			'draft_id'          => $draft_id,
			'source_id'         => (int) $plan['source_id'],
			'preset'            => (string) $plan['preset'],
			'page_key'          => (string) $plan['page_key'],
			'native_plan_sha'   => (string) $plan['native_plan_sha'],
			'content_remap_sha' => (string) $plan['content_remap_sha'],
			'selection_sha256'  => (string) $plan['selection_sha256'],
			'before_sha256'     => $before_sha,
			'after_sha256'      => $after_sha,
			'verified_sections' => $plan['verified_sections'],
			'selected_assets'   => $plan['selected_assets'],
			'unmapped_assets'   => $this->unmapped_assets( $asset_maps, is_array( $plan['selected_assets'] ?? null ) ? $plan['selected_assets'] : array() ),
		);
		update_post_meta( $draft_id, self::LEDGER_META, $ledger );

		return array(
			'schema_version'   => 1,
			'mode'             => 'reviewed-remap-apply',
			'status'           => 'applied',
			'draft_id'         => $draft_id,
			'source_id'        => (int) $plan['source_id'],
			'selection_sha256' => (string) $plan['selection_sha256'],
			'before_sha256'    => $before_sha,
			'after_sha256'     => $after_sha,
			'ledger'           => $ledger,
			'safety'           => array(
				'source_unchanged' => true,
				'draft_only'       => 'draft' === get_post_status( $draft_id ),
				'public_mutation'  => false,
			),
		);
	}

	/**
	 * Restore the draft body captured immediately before the latest reviewed apply.
	 *
	 * The preserved public source is never modified. The active reviewed ledger is
	 * archived as rollback evidence and then cleared so a new reviewed selection
	 * can be applied from the restored deterministic slot markers.
	 *
	 * @param int $draft_id Native draft ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function rollback( int $draft_id ): array|WP_Error {
		if ( ! $this->sandbox_active() ) {
			return new WP_Error( 'seo_geo_reviewed_remap_rollback_sandbox_required', 'Reviewed remap rollback may only run inside an accepted sandbox.' );
		}
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $draft_id ) ) {
			return new WP_Error( 'seo_geo_reviewed_remap_rollback_forbidden', 'Administrator edit capability is required.' );
		}

		$draft = get_post( $draft_id );
		if ( ! $draft instanceof WP_Post || 'page' !== $draft->post_type || 'draft' !== $draft->post_status ) {
			return new WP_Error( 'seo_geo_reviewed_remap_rollback_draft_invalid', 'Reviewed remap rollback requires the bound native page draft.' );
		}

		$ledger = get_post_meta( $draft_id, self::LEDGER_META, true );
		$backup = get_post_meta( $draft_id, self::BACKUP_META, true );
		if ( ! is_array( $ledger ) || ! is_array( $backup ) ) {
			return new WP_Error( 'seo_geo_reviewed_remap_rollback_unavailable', 'No active reviewed apply and rollback backup are available for this draft.' );
		}

		$current_sha  = hash( 'sha256', (string) $draft->post_content );
		$ledger_after = (string) ( $ledger['after_sha256'] ?? '' );
		$ledger_before = (string) ( $ledger['before_sha256'] ?? '' );
		$backup_sha   = (string) ( $backup['sha256'] ?? '' );
		$backup_body  = isset( $backup['content'] ) && is_string( $backup['content'] ) ? $backup['content'] : '';

		if ( '' === $ledger_after || ! hash_equals( $ledger_after, $current_sha ) ) {
			return new WP_Error( 'seo_geo_reviewed_remap_rollback_draft_drift', 'The native draft changed after reviewed apply; automatic rollback is blocked.' );
		}
		if ( '' === $backup_sha || '' === $ledger_before || ! hash_equals( $ledger_before, $backup_sha ) || ! hash_equals( $backup_sha, hash( 'sha256', $backup_body ) ) ) {
			return new WP_Error( 'seo_geo_reviewed_remap_rollback_backup_invalid', 'Reviewed remap rollback backup does not match the recorded pre-apply draft state.' );
		}

		$updated = wp_update_post(
			array(
				'ID'           => $draft_id,
				'post_content' => $backup_body,
			),
			true
		);
		if ( $updated instanceof WP_Error ) {
			return $updated;
		}

		$restored = get_post( $draft_id );
		if (
			! $restored instanceof WP_Post
			|| 'draft' !== $restored->post_status
			|| ! hash_equals( $backup_sha, hash( 'sha256', (string) $restored->post_content ) )
		) {
			return new WP_Error( 'seo_geo_reviewed_remap_rollback_verification_failed', 'The native draft could not be verified after rollback.' );
		}

		$source_id         = (int) get_post_meta( $draft_id, NativeCompositionService::SOURCE_ID_META, true );
		$stored_source_sha = (string) get_post_meta( $draft_id, NativeCompositionService::SOURCE_SHA_META, true );
		$current_source_sha = 0 < $source_id
			? hash( 'sha256', (string) get_post_field( 'post_content', $source_id ) )
			: '';

		$rollback = array(
			'schema_version'    => 1,
			'rolled_back_at'    => gmdate( DATE_ATOM ),
			'draft_id'          => $draft_id,
			'source_id'         => $source_id,
			'preset'            => (string) ( $ledger['preset'] ?? '' ),
			'page_key'          => (string) ( $ledger['page_key'] ?? '' ),
			'native_plan_sha'   => (string) ( $ledger['native_plan_sha'] ?? '' ),
			'content_remap_sha' => (string) ( $ledger['content_remap_sha'] ?? '' ),
			'selection_sha256'  => (string) ( $ledger['selection_sha256'] ?? '' ),
			'from_sha256'       => $current_sha,
			'to_sha256'         => $backup_sha,
			'selected_assets'   => is_array( $ledger['selected_assets'] ?? null ) ? $ledger['selected_assets'] : array(),
			'unmapped_assets'   => is_array( $ledger['unmapped_assets'] ?? null ) ? $ledger['unmapped_assets'] : array(),
			'verified_sections' => is_array( $ledger['verified_sections'] ?? null ) ? $ledger['verified_sections'] : array(),
		);
		update_post_meta( $draft_id, self::ROLLBACK_META, $rollback );
		delete_post_meta( $draft_id, self::LEDGER_META );

		return array(
			'schema_version'   => 1,
			'mode'             => 'reviewed-remap-rollback',
			'status'           => 'rolled-back',
			'draft_id'         => $draft_id,
			'source_id'        => $source_id,
			'selection_sha256' => (string) ( $ledger['selection_sha256'] ?? '' ),
			'from_sha256'      => $current_sha,
			'to_sha256'        => $backup_sha,
			'rollback'         => $rollback,
			'safety'           => array(
				'source_unchanged' => '' !== $stored_source_sha && hash_equals( $stored_source_sha, $current_source_sha ),
				'draft_only'       => 'draft' === get_post_status( $draft_id ),
				'public_mutation'  => false,
			),
		);
	}

	/**
	 * Normalize selections into a stable section/type/ID structure.
	 *
	 * @param array<string,mixed> $selections Raw selections.
	 * @return array<string,array{units:list<string>,links:list<string>,media:list<string>}>
	 */
	private function normalize_selections( array $selections ): array {
		$result = array();

		foreach ( $selections as $section => $selection ) {
			if ( ! is_array( $selection ) ) {
				continue;
			}

			$section = sanitize_key( $section );
			if ( '' === $section ) {
				continue;
			}

			$normalized = array(
				'units' => $this->normalize_ids( $selection['units'] ?? array() ),
				'links' => $this->normalize_ids( $selection['links'] ?? array() ),
				'media' => $this->normalize_ids( $selection['media'] ?? array() ),
			);

			if ( array() !== $normalized['units'] || array() !== $normalized['links'] || array() !== $normalized['media'] ) {
				$result[ $section ] = $normalized;
			}
		}

		ksort( $result );

		return $result;
	}

	/**
	 * Normalize a list of asset IDs.
	 *
	 * @param mixed $ids Raw asset IDs.
	 * @return list<string>
	 */
	private function normalize_ids( mixed $ids ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$result = array();
		foreach ( $ids as $id ) {
			if ( is_string( $id ) && '' !== trim( $id ) ) {
				$result[] = sanitize_text_field( $id );
			}
		}

		$result = array_values( array_unique( $result ) );
		sort( $result );

		return $result;
	}

	/**
	 * Index native remap slots by semantic section.
	 *
	 * @param array<int,mixed> $slots Native remap slots.
	 * @return array<string,string>
	 */
	private function slot_map( array $slots ): array {
		$result = array();
		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}
			$section       = isset( $slot['section'] ) && is_string( $slot['section'] ) ? sanitize_key( $slot['section'] ) : '';
			$after_pattern = isset( $slot['after_pattern'] ) && is_string( $slot['after_pattern'] ) ? (string) $slot['after_pattern'] : '';
			if ( '' !== $section && '' !== $after_pattern ) {
				$result[ $section ] = $after_pattern;
			}
		}

		return $result;
	}

	/**
	 * Index content-remap slot plans by semantic section.
	 *
	 * @param array<int,mixed> $slots Content-remap slots.
	 * @return array<string,array<string,mixed>>
	 */
	private function content_slot_map( array $slots ): array {
		$result = array();
		foreach ( $slots as $slot ) {
			if ( is_array( $slot ) && isset( $slot['section'] ) && is_string( $slot['section'] ) ) {
				$result[ sanitize_key( $slot['section'] ) ] = $slot;
			}
		}

		return $result;
	}

	/**
	 * Index source assets by asset type and ID.
	 *
	 * @param array<string,mixed> $assets Content-remap assets.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	private function asset_maps( array $assets ): array {
		$result = array(
			'units' => array(),
			'links' => array(),
			'media' => array(),
		);

		foreach ( array_keys( $result ) as $type ) {
			$items = isset( $assets[ $type ] ) && is_array( $assets[ $type ] ) ? $assets[ $type ] : array();
			foreach ( $items as $item ) {
				if ( is_array( $item ) && isset( $item['id'] ) && is_string( $item['id'] ) ) {
					$result[ $type ][ $item['id'] ] = $item;
				}
			}
		}

		return $result;
	}

	/**
	 * Render one reviewed semantic slot using native core blocks only.
	 *
	 * @param string                                          $section       Semantic section.
	 * @param array<string,mixed>                             $selection     Selected asset IDs.
	 * @param array<string,array<string,array<string,mixed>>> $asset_maps    Asset lookup maps.
	 * @param string                                          $selection_sha Selection fingerprint.
	 */
	private function render_section( string $section, array $selection, array $asset_maps, string $selection_sha ): string {
		$parts = array();

		foreach ( $selection['units'] ?? array() as $asset_id ) {
			$asset = $asset_maps['units'][ $asset_id ] ?? null;
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$text = isset( $asset['text'] ) && is_string( $asset['text'] ) ? $asset['text'] : '';
			$kind = isset( $asset['kind'] ) && is_string( $asset['kind'] ) ? $asset['kind'] : 'paragraph';
			if ( '' === $text ) {
				continue;
			}

			if ( 'heading' === $kind ) {
				$parts[] = '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html( $text ) . '</h3><!-- /wp:heading -->';
			} elseif ( 'quote' === $kind ) {
				$parts[] = '<!-- wp:quote --><blockquote class="wp-block-quote"><p>' . esc_html( $text ) . '</p></blockquote><!-- /wp:quote -->';
			} else {
				$parts[] = '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
			}
		}

		foreach ( $selection['links'] ?? array() as $asset_id ) {
			$asset = $asset_maps['links'][ $asset_id ] ?? null;
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$url = isset( $asset['url'] ) && is_string( $asset['url'] ) ? $asset['url'] : '';
			if ( '' !== $url ) {
				$parts[] = '<!-- wp:paragraph --><p><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></p><!-- /wp:paragraph -->';
			}
		}

		foreach ( $selection['media'] ?? array() as $asset_id ) {
			$asset = $asset_maps['media'][ $asset_id ] ?? null;
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$url = isset( $asset['url'] ) && is_string( $asset['url'] ) ? $asset['url'] : '';
			$alt = isset( $asset['alt'] ) && is_string( $asset['alt'] ) ? $asset['alt'] : '';
			$id  = isset( $asset['attachment_id'] ) ? (int) $asset['attachment_id'] : 0;
			if ( '' === $url ) {
				continue;
			}

			$attrs   = 0 < $id ? ' {"id":' . $id . ',"sizeSlug":"full","linkDestination":"none"}' : ' {"sizeSlug":"full","linkDestination":"none"}';
			$class   = 0 < $id ? ' class="wp-image-' . $id . '"' : '';
			$parts[] = '<!-- wp:image' . $attrs . ' --><figure class="wp-block-image size-full"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"' . $class . '/></figure><!-- /wp:image -->';
		}

		$class_name = 'seo-geo-reviewed-remap seo-geo-reviewed-remap--' . sanitize_html_class( $section );
		$provenance = '<!-- seo-geo-reviewed-remap:' . sanitize_key( $section ) . ':' . substr( $selection_sha, 0, 16 ) . ' -->';

		return '<!-- wp:group {"tagName":"section","className":"' . esc_attr( $class_name ) . '","layout":{"type":"constrained"}} -->'
			. '<section class="wp-block-group ' . esc_attr( $class_name ) . '">'
			. $provenance
			. implode( "\n", $parts )
			. '</section><!-- /wp:group -->';
	}

	/**
	 * Return source assets not selected by the reviewed apply operation.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $asset_maps Selected source inventory.
	 * @param array<string,mixed>                             $selected   Selected IDs by type.
	 * @return array<string,array<int,string>>
	 */
	private function unmapped_assets( array $asset_maps, array $selected ): array {
		$result = array(
			'units' => array(),
			'links' => array(),
			'media' => array(),
		);
		foreach ( array_keys( $result ) as $type ) {
			$selected_ids    = isset( $selected[ $type ] ) && is_array( $selected[ $type ] ) ? array_map( 'strval', $selected[ $type ] ) : array();
			$result[ $type ] = array_values( array_diff( array_keys( $asset_maps[ $type ] ), $selected_ids ) );
			sort( $result[ $type ] );
		}

		return $result;
	}

	/**
	 * Confirm accepted sandbox authority.
	 */
	private function sandbox_active(): bool {
		return defined( 'SEO_GEO_MIGRATION_SANDBOX' ) && true === SEO_GEO_MIGRATION_SANDBOX;
	}
}
