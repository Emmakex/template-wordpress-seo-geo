<?php
/**
 * Migration destination preset resolver.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Migration;

/**
 * Resolves the accepted destination information-architecture preset.
 */
final class MigrationPresetResolver {
	/**
	 * Supported preset IDs.
	 *
	 * @var list<string>
	 */
	private const PRESETS = array(
		'corporate',
		'local-business',
		'publisher',
		'ecommerce',
		'saas-digital-product',
	);

	/**
	 * Resolve one explicitly requested or currently active preset.
	 *
	 * @param string|null $requested Optional explicitly selected preset.
	 * @return array{selected:string|null,valid:bool,reason:string}
	 */
	public function resolve( ?string $requested = null ): array {
		$active = get_option( 'seo_geo_active_preset', '' );
		$active = is_string( $active ) ? sanitize_key( $active ) : '';
		$active = in_array( $active, self::PRESETS, true ) ? $active : '';

		if ( null === $requested || '' === trim( $requested ) ) {
			return array(
				'selected' => '' !== $active ? $active : null,
				'valid'    => true,
				'reason'   => '' !== $active ? 'active-preset-reused' : 'no-preset-selected',
			);
		}

		$requested = sanitize_key( $requested );
		if ( ! in_array( $requested, self::PRESETS, true ) ) {
			return array(
				'selected' => null,
				'valid'    => false,
				'reason'   => 'unsupported-preset',
			);
		}

		if ( '' !== $active && $active !== $requested ) {
			return array(
				'selected' => $requested,
				'valid'    => false,
				'reason'   => 'requested-preset-does-not-match-active-theme-preset',
			);
		}

		return array(
			'selected' => $requested,
			'valid'    => true,
			'reason'   => '' === $active ? 'explicit-preset-selected' : 'active-preset-confirmed',
		);
	}
}
