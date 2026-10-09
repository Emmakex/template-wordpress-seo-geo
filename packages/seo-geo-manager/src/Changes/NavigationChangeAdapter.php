<?php
/**
 * Bounded adapter for WordPress navigation-menu URL corrections.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use WP_Error;

final class NavigationChangeAdapter {
	private const SCHEMA_VERSION = 1;
	private const ADAPTER        = 'navigation-menu';

	/**
	 * Preview a bounded navigation-menu URL correction.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload ) {
		$prepared = self::prepare( $payload, false );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'preview',
			'adapter'        => self::ADAPTER,
			'menu_id'        => $prepared['menu_id'],
			'menu_name'      => $prepared['menu_name'],
			'fingerprint'    => $prepared['before_fingerprint'],
			'matches'        => self::preview_items( $prepared['items'] ),
			'expected'       => $prepared['expected_occurrences'],
			'suggested_url'  => $prepared['suggested_url'],
			'has_changes'    => true,
			'policy'         => array(
				'custom_links_only'           => true,
				'exact_occurrence_accounting' => true,
				'public_navigation_blocked'   => true !== ( $payload['allow_public_navigation'] ?? false ),
				'allow_public_navigation'     => true === ( $payload['allow_public_navigation'] ?? false ),
			),
		);
	}

	/**
	 * Apply a bounded navigation-menu URL correction.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( '' === $idempotency_key || 128 < strlen( $idempotency_key ) ) {
			return new WP_Error(
				'seo_geo_manager_navigation_idempotency_required',
				'Navigation apply requests require an idempotency key of at most 128 characters.',
				array( 'status' => 400 )
			);
		}

		$payload_hash = self::payload_hash( $payload );
		$lookup       = OperationStore::lookup( $idempotency_key, $payload_hash );
		if ( is_wp_error( $lookup ) ) {
			return $lookup;
		}

		if ( true === $lookup['existing'] ) {
			return self::replay_operation( $lookup['operation_id'] );
		}

		if ( true !== ( $payload['allow_public_navigation'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_navigation_public_approval_required',
				'Public navigation writes require explicit approval after preview.',
				array( 'status' => 409 )
			);
		}

		$prepared = self::prepare( $payload, true );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$operation_id = wp_generate_uuid4();
		$reservation  = OperationStore::reserve( $idempotency_key, $payload_hash, $operation_id );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( true === $reservation['existing'] ) {
			return self::replay_operation( $reservation['operation_id'] );
		}

		$updated = array();
		foreach ( $prepared['items'] as $item ) {
			$result = wp_update_nav_menu_item( $prepared['menu_id'], $item['id'], $item['after_args'] );
			if ( is_wp_error( $result ) ) {
				self::restore_items( $prepared['menu_id'], array_reverse( $updated ) );
				self::save_failed_operation( $operation_id, $prepared );
				return $result;
			}
			$updated[] = $item;
		}

		$after_fingerprint = self::menu_fingerprint( $prepared['menu_id'] );
		if ( '' === $after_fingerprint || hash_equals( $prepared['before_fingerprint'], $after_fingerprint ) ) {
			self::restore_items( $prepared['menu_id'], array_reverse( $updated ) );
			self::save_failed_operation( $operation_id, $prepared );
			return new WP_Error(
				'seo_geo_manager_navigation_verify_failed',
				'Navigation apply did not produce the expected fingerprint change and was compensated.',
				array( 'status' => 409 )
			);
		}

		$operation = array(
			'operation_id'       => $operation_id,
			'adapter'            => self::ADAPTER,
			'status'             => 'applied',
			'menu_id'            => $prepared['menu_id'],
			'target_id'          => $prepared['menu_id'],
			'menu_name'          => $prepared['menu_name'],
			'before_fingerprint' => $prepared['before_fingerprint'],
			'after_fingerprint'  => $after_fingerprint,
			'items'              => $prepared['items'],
			'suggested_url'      => $prepared['suggested_url'],
			'created_at_gmt'     => gmdate( 'c' ),
			'idempotent_replay'  => false,
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::restore_items( $prepared['menu_id'], array_reverse( $updated ) );
			return new WP_Error(
				'seo_geo_manager_navigation_operation_store_failed',
				'Navigation changed but the operation record could not be persisted; changes were compensated.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * Roll back one navigation-menu operation when the menu is unchanged since Apply.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_navigation_operation_not_found', 'Navigation operation not found.', array( 'status' => 404 ) );
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_navigation_rollback_unavailable', 'Only applied navigation operations can be rolled back.', array( 'status' => 409 ) );
		}

		$menu_id = isset( $operation['menu_id'] ) ? absint( $operation['menu_id'] ) : 0;
		$current = self::menu_fingerprint( $menu_id );
		$after   = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		if ( '' === $current || '' === $after || ! hash_equals( $after, $current ) ) {
			return new WP_Error(
				'seo_geo_manager_navigation_rollback_stale',
				'Navigation changed after Apply; rollback is blocked to avoid overwriting newer edits.',
				array( 'status' => 409 )
			);
		}

		$items    = isset( $operation['items'] ) && is_array( $operation['items'] ) ? $operation['items'] : array();
		$restored = array();
		foreach ( array_reverse( $items ) as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['id'], $item['before_args'], $item['after_args'] ) ) {
				return new WP_Error( 'seo_geo_manager_navigation_rollback_record_invalid', 'Navigation rollback record is incomplete.', array( 'status' => 409 ) );
			}
			$result = wp_update_nav_menu_item( $menu_id, (int) $item['id'], $item['before_args'] );
			if ( is_wp_error( $result ) ) {
				self::reapply_items( $menu_id, array_reverse( $restored ) );
				return $result;
			}
			$restored[] = $item;
		}

		$rolled_back_fingerprint = self::menu_fingerprint( $menu_id );
		$before                  = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] ) ? $operation['before_fingerprint'] : '';
		if ( '' === $rolled_back_fingerprint || '' === $before || ! hash_equals( $before, $rolled_back_fingerprint ) ) {
			self::reapply_items( $menu_id, array_reverse( $restored ) );
			return new WP_Error(
				'seo_geo_manager_navigation_rollback_verify_failed',
				'Navigation rollback verification failed; the adapter attempted to restore the applied state.',
				array( 'status' => 409 )
			);
		}

		$operation['status']               = 'rolled-back';
		$operation['rolled_back_at_gmt']   = gmdate( 'c' );
		$operation['rollback_fingerprint'] = $rolled_back_fingerprint;
		OperationStore::save( $operation_id, $operation );

		return $operation;
	}

	/**
	 * Prepare and validate a menu mutation.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function prepare( array $payload, bool $for_apply ) {
		$schema_version = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION;
		$menu_id        = isset( $payload['menu_id'] ) ? absint( $payload['menu_id'] ) : 0;
		$expected       = isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '';
		$occurrences    = isset( $payload['expected_occurrences'] ) ? absint( $payload['expected_occurrences'] ) : 0;
		$urls           = self::url_list( $payload['current_urls'] ?? array() );
		$suggested      = isset( $payload['suggested_url'] ) && is_string( $payload['suggested_url'] ) ? esc_url_raw( $payload['suggested_url'] ) : '';

		if ( self::SCHEMA_VERSION !== $schema_version || 1 > $menu_id || 1 > $occurrences || array() === $urls || '' === $suggested || ( $for_apply && '' === $expected ) ) {
			return new WP_Error(
				'seo_geo_manager_navigation_request_invalid',
				$for_apply
					? 'Navigation apply requires menu_id, expected_fingerprint, current_urls, expected_occurrences and suggested_url.'
					: 'Navigation preview requires menu_id, current_urls, expected_occurrences and suggested_url.',
				array( 'status' => 400 )
			);
		}
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot modify WordPress navigation menus.', array( 'status' => 403 ) );
		}

		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! is_object( $menu ) || ! isset( $menu->term_id ) ) {
			return new WP_Error( 'seo_geo_manager_navigation_menu_missing', 'Navigation menu not found.', array( 'status' => 404 ) );
		}
		if ( ! self::destination_allowed( $suggested ) ) {
			return new WP_Error(
				'seo_geo_manager_navigation_destination_invalid',
				'Navigation correction target must stay inside the current WordPress home environment.',
				array( 'status' => 409 )
			);
		}

		$current_fingerprint = self::menu_fingerprint( $menu_id );
		if ( '' === $current_fingerprint ) {
			return new WP_Error( 'seo_geo_manager_navigation_fingerprint_unavailable', 'Navigation fingerprint could not be calculated.', array( 'status' => 409 ) );
		}
		if ( '' !== $expected && ! hash_equals( $expected, $current_fingerprint ) ) {
			return new WP_Error(
				'seo_geo_manager_navigation_fingerprint_mismatch',
				'Navigation changed after inspection; prepare a fresh preview.',
				array( 'status' => 409 )
			);
		}

		$targets = array();
		foreach ( $urls as $url ) {
			$canonical = self::canonical_url( $url );
			if ( '' !== $canonical ) {
				$targets[ $canonical ] = true;
			}
		}
		if ( array() === $targets ) {
			return new WP_Error( 'seo_geo_manager_navigation_urls_invalid', 'No valid navigation URL variants were provided.', array( 'status' => 400 ) );
		}

		$menu_items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
		$menu_items = is_array( $menu_items ) ? $menu_items : array();
		$items      = array();
		foreach ( $menu_items as $item ) {
			if ( ! is_object( $item ) || ! isset( $item->ID, $item->url ) ) {
				continue;
			}
			$canonical = self::canonical_url( (string) $item->url );
			if ( '' === $canonical || ! isset( $targets[ $canonical ] ) ) {
				continue;
			}
			if ( 'custom' !== (string) ( $item->type ?? '' ) ) {
				return new WP_Error(
					'seo_geo_manager_navigation_item_type_unsupported',
					'Only custom-link menu items can be corrected automatically; object-backed items require manual review.',
					array( 'status' => 409, 'item_id' => (int) $item->ID )
				);
			}

			$items[] = array(
				'id'          => (int) $item->ID,
				'title'       => (string) ( $item->title ?? '' ),
				'before_url'  => (string) $item->url,
				'after_url'   => $suggested,
				'before_args' => self::menu_item_args( $item, (string) $item->url ),
				'after_args'  => self::menu_item_args( $item, $suggested ),
			);
		}

		if ( count( $items ) !== $occurrences ) {
			return new WP_Error(
				'seo_geo_manager_navigation_occurrence_mismatch',
				'Navigation occurrence count no longer matches Site Intelligence; prepare a fresh diagnostic.',
				array( 'status' => 409, 'expected' => $occurrences, 'found' => count( $items ) )
			);
		}
		if ( $for_apply && true !== ( $payload['allow_public_navigation'] ?? false ) ) {
			return new WP_Error( 'seo_geo_manager_navigation_public_approval_required', 'Public navigation writes require explicit approval.', array( 'status' => 409 ) );
		}

		return array(
			'menu_id'              => $menu_id,
			'menu_name'            => isset( $menu->name ) ? (string) $menu->name : 'Menu #' . $menu_id,
			'before_fingerprint'   => $current_fingerprint,
			'expected_occurrences' => $occurrences,
			'current_urls'         => $urls,
			'suggested_url'        => $suggested,
			'items'                => $items,
		);
	}

	/**
	 * Read an idempotent navigation operation.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function replay_operation( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_navigation_idempotency_unavailable',
				'The navigation idempotency key points to an incompatible operation.',
				array( 'status' => 409 )
			);
		}
		if ( 'failed' === ( $operation['status'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_navigation_previous_attempt_failed',
				'This idempotency key belongs to a failed navigation attempt. Prepare a fresh preview before retrying.',
				array( 'status' => 409 )
			);
		}
		$operation['idempotent_replay'] = true;
		return $operation;
	}

	/**
	 * Persist a failed operation record after compensation.
	 *
	 * @param array<string, mixed> $prepared Prepared mutation.
	 */
	private static function save_failed_operation( string $operation_id, array $prepared ): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'       => $operation_id,
				'adapter'            => self::ADAPTER,
				'status'             => 'failed',
				'menu_id'            => $prepared['menu_id'],
				'target_id'          => $prepared['menu_id'],
				'before_fingerprint' => $prepared['before_fingerprint'],
				'created_at_gmt'     => gmdate( 'c' ),
			)
		);
	}

	/**
	 * Return bounded menu-item details for Preview.
	 *
	 * @param array<int, array<string, mixed>> $items Prepared items.
	 * @return array<int, array<string, mixed>>
	 */
	private static function preview_items( array $items ): array {
		$result = array();
		foreach ( $items as $item ) {
			$result[] = array(
				'id'         => (int) ( $item['id'] ?? 0 ),
				'title'      => (string) ( $item['title'] ?? '' ),
				'before_url' => (string) ( $item['before_url'] ?? '' ),
				'after_url'  => (string) ( $item['after_url'] ?? '' ),
			);
		}
		return $result;
	}

	/**
	 * Normalize a list of URL variants.
	 *
	 * @param mixed $value Raw URL list.
	 * @return list<string>
	 */
	private static function url_list( $value ): array {
		$values = is_array( $value ) ? $value : array();
		$result = array();
		foreach ( $values as $url ) {
			if ( ! is_string( $url ) ) {
				continue;
			}
			$url = esc_url_raw( trim( $url ) );
			if ( '' !== $url ) {
				$result[] = $url;
			}
		}
		return array_values( array_unique( $result ) );
	}

	/**
	 * Confirm the destination remains inside the current WordPress home path.
	 */
	private static function destination_allowed( string $url ): bool {
		$target = wp_parse_url( $url );
		$home   = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $target ) || ! is_array( $home ) ) {
			return false;
		}
		$target_host = strtolower( (string) ( $target['host'] ?? '' ) );
		$home_host   = strtolower( (string) ( $home['host'] ?? '' ) );
		if ( '' === $target_host || '' === $home_host || $target_host !== $home_host ) {
			return false;
		}
		$target_path = self::normalize_path( (string) ( $target['path'] ?? '/' ) );
		$home_path   = self::normalize_path( (string) ( $home['path'] ?? '/' ) );
		return '/' === $home_path || $target_path === $home_path || str_starts_with( $target_path, trailingslashit( $home_path ) );
	}

	/**
	 * Canonicalize one URL for deterministic comparison.
	 */
	private static function canonical_url( string $url ): string {
		$url = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || '' === (string) ( $parts['host'] ?? '' ) ) {
			if ( str_starts_with( $url, '/' ) ) {
				$home = wp_parse_url( home_url( '/' ) );
				if ( ! is_array( $home ) ) {
					return '';
				}
				$scheme = (string) ( $home['scheme'] ?? 'https' );
				$host   = (string) ( $home['host'] ?? '' );
				$port   = isset( $home['port'] ) ? ':' . (int) $home['port'] : '';
				$url    = $scheme . '://' . $host . $port . $url;
			} else {
				$url = trailingslashit( home_url( '/' ) ) . ltrim( $url, '/' );
			}
			$parts = wp_parse_url( $url );
		}
		if ( ! is_array( $parts ) || '' === (string) ( $parts['host'] ?? '' ) ) {
			return '';
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
		$host   = strtolower( (string) $parts['host'] );
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path   = self::normalize_path( (string) ( $parts['path'] ?? '/' ) );
		$query  = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';
		return $scheme . '://' . $host . $port . $path . $query;
	}

	/**
	 * Normalize a URL path for comparison.
	 */
	private static function normalize_path( string $path ): string {
		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' );
		}
		return '' === $path ? '/' : $path;
	}

	/**
	 * Fingerprint the complete menu state used by this adapter.
	 */
	private static function menu_fingerprint( int $menu_id ): string {
		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
		if ( ! is_array( $items ) ) {
			return '';
		}
		$state = array();
		foreach ( $items as $item ) {
			if ( ! is_object( $item ) || ! isset( $item->ID ) ) {
				continue;
			}
			$state[] = array(
				'id'          => (int) $item->ID,
				'order'       => (int) ( $item->menu_order ?? 0 ),
				'parent'      => (int) ( $item->menu_item_parent ?? 0 ),
				'type'        => (string) ( $item->type ?? '' ),
				'object'      => (string) ( $item->object ?? '' ),
				'object_id'   => (int) ( $item->object_id ?? 0 ),
				'title'       => (string) ( $item->title ?? '' ),
				'url'         => (string) ( $item->url ?? '' ),
				'target'      => (string) ( $item->target ?? '' ),
				'attr_title'  => (string) ( $item->attr_title ?? '' ),
				'description' => (string) ( $item->description ?? '' ),
				'classes'     => array_values( array_filter( (array) ( $item->classes ?? array() ), 'is_string' ) ),
				'xfn'         => (string) ( $item->xfn ?? '' ),
				'status'      => (string) get_post_status( (int) $item->ID ),
			);
		}
		return hash( 'sha256', (string) wp_json_encode( $state ) );
	}

	/**
	 * Capture complete menu-item arguments while replacing only the URL.
	 *
	 * @return array<string, mixed>
	 */
	private static function menu_item_args( object $item, string $url ): array {
		return array(
			'menu-item-db-id'       => (int) $item->ID,
			'menu-item-object-id'   => (int) ( $item->object_id ?? 0 ),
			'menu-item-object'      => (string) ( $item->object ?? '' ),
			'menu-item-parent-id'   => (int) ( $item->menu_item_parent ?? 0 ),
			'menu-item-position'    => (int) ( $item->menu_order ?? 0 ),
			'menu-item-type'        => (string) ( $item->type ?? 'custom' ),
			'menu-item-title'       => (string) ( $item->title ?? '' ),
			'menu-item-url'         => $url,
			'menu-item-description' => (string) ( $item->description ?? '' ),
			'menu-item-attr-title'  => (string) ( $item->attr_title ?? '' ),
			'menu-item-target'      => (string) ( $item->target ?? '' ),
			'menu-item-classes'     => implode( ' ', array_values( array_filter( (array) ( $item->classes ?? array() ), 'is_string' ) ) ),
			'menu-item-xfn'         => (string) ( $item->xfn ?? '' ),
			'menu-item-status'      => (string) get_post_status( (int) $item->ID ),
		);
	}

	/**
	 * Restore previously updated items after a failed group.
	 *
	 * @param array<int, array<string, mixed>> $items Prepared items.
	 */
	private static function restore_items( int $menu_id, array $items ): void {
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['id'], $item['before_args'] ) ) {
				wp_update_nav_menu_item( $menu_id, (int) $item['id'], $item['before_args'] );
			}
		}
	}

	/**
	 * Reapply items after a rollback verification failure.
	 *
	 * @param array<int, array<string, mixed>> $items Prepared items.
	 */
	private static function reapply_items( int $menu_id, array $items ): void {
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['id'], $item['after_args'] ) ) {
				wp_update_nav_menu_item( $menu_id, (int) $item['id'], $item['after_args'] );
			}
		}
	}

	/**
	 * Hash the bounded apply contract for idempotency.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 */
	private static function payload_hash( array $payload ): string {
		$urls = self::url_list( $payload['current_urls'] ?? array() );
		sort( $urls );
		$normalized = array(
			'schema_version'          => isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION,
			'menu_id'                 => isset( $payload['menu_id'] ) ? absint( $payload['menu_id'] ) : 0,
			'expected_fingerprint'    => isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '',
			'current_urls'            => $urls,
			'suggested_url'           => isset( $payload['suggested_url'] ) && is_string( $payload['suggested_url'] ) ? esc_url_raw( $payload['suggested_url'] ) : '',
			'expected_occurrences'    => isset( $payload['expected_occurrences'] ) ? absint( $payload['expected_occurrences'] ) : 0,
			'allow_public_navigation' => true === ( $payload['allow_public_navigation'] ?? false ),
			'environment_fingerprint' => isset( $payload['environment_fingerprint'] ) && is_string( $payload['environment_fingerprint'] ) ? trim( $payload['environment_fingerprint'] ) : '',
		);
		return hash( 'sha256', (string) wp_json_encode( $normalized ) );
	}
}
