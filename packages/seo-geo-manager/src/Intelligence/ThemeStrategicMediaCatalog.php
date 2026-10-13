<?php
/**
 * Bounded strategic-media catalog for the active Theme preset.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use WP_Error;

/** Discover editable strategic-media models without coupling to page-models. */
final class ThemeStrategicMediaCatalog {
	private const SCHEMA_VERSION = 1;
	private const MAX_MODELS     = 50;
	private const MAX_SLOTS      = 100;

	/**
	 * List resolved Theme pages that declare strategic image slots.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function list_models() {
		if ( ! function_exists( 'seo_geo_theme_strategic_media_contract' ) ) {
			return new WP_Error(
				'seo_geo_manager_theme_media_catalog_unsupported',
				'The active Theme does not expose the strategic-media contract required by Manager.',
				array( 'status' => 409 )
			);
		}

		$scan = ThemeContractScanner::scan();
		if ( true !== ( $scan['applicable'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_theme_media_catalog_unavailable',
				'The active Theme preset contract is unavailable.',
				array( 'status' => 409 )
			);
		}

		$models = array();
		$pages  = isset( $scan['pages'] ) && is_array( $scan['pages'] ) ? $scan['pages'] : array();

		foreach ( $pages as $page ) {
			if ( self::MAX_MODELS <= count( $models ) || ! is_array( $page ) || true !== ( $page['resolved'] ?? false ) ) {
				continue;
			}

			$resource = isset( $page['resource'] ) && is_array( $page['resource'] ) ? $page['resource'] : array();
			$model    = isset( $page['model'] ) && is_array( $page['model'] ) ? $page['model'] : array();
			$id       = isset( $resource['id'] ) ? absint( $resource['id'] ) : 0;
			$model_id = isset( $model['model_id'] ) && is_string( $model['model_id'] ) ? sanitize_key( $model['model_id'] ) : '';
			if ( 1 > $id || '' === $model_id || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}

			$declared = call_user_func( 'seo_geo_theme_strategic_media_contract', $model_id );
			if ( ! is_array( $declared ) || array() === $declared ) {
				continue;
			}

			$slots = array();
			foreach ( $declared as $slot_id => $definition ) {
				if ( self::MAX_SLOTS <= count( $slots ) || ! is_string( $slot_id ) || ! is_array( $definition ) ) {
					continue;
				}

				$slot_id = sanitize_key( $slot_id );
				if ( '' === $slot_id || 'media' !== ( $definition['type'] ?? null ) || 'image' !== ( $definition['accept'] ?? null ) ) {
					continue;
				}

				$slots[] = array(
					'id'       => $slot_id,
					'type'     => 'media',
					'required' => true === ( $definition['required'] ?? false ),
					'accept'   => 'image',
				);
			}

			if ( array() === $slots ) {
				continue;
			}

			$models[] = array(
				'model_id' => $model_id,
				'page_key' => isset( $page['key'] ) && is_string( $page['key'] ) ? sanitize_key( $page['key'] ) : '',
				'resource' => array(
					'id'     => $id,
					'title'  => isset( $resource['title'] ) && is_string( $resource['title'] ) ? $resource['title'] : '',
					'status' => isset( $resource['status'] ) && is_string( $resource['status'] ) ? $resource['status'] : '',
				),
				'slots'    => $slots,
			);
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'applicable'     => true,
			'preset'         => isset( $scan['preset'] ) && is_string( $scan['preset'] ) ? $scan['preset'] : null,
			'model_count'    => count( $models ),
			'models'         => $models,
			'policy'         => array(
				'contract_authority'      => 'active-theme-strategic-media-contract',
				'resolved_pages_only'     => true,
				'image_slots_only'        => true,
				'raw_content_returned'    => false,
				'raw_theme_meta_returned' => false,
				'max_models'              => self::MAX_MODELS,
				'max_slots_per_model'     => self::MAX_SLOTS,
			),
		);
	}
}
