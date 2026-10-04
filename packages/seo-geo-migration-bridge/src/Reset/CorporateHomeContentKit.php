<?php
/**
 * Structured Corporate Home content kit.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Error;

/**
 * Validates and persists reviewed Home content independently from legacy layout.
 */
final class CorporateHomeContentKit {
	public const OPTION = 'seo_geo_corporate_home_content_kit_v1';
	public const MODEL  = 'corporate-home-v1';

	/**
	 * Return the Theme-owned content model.
	 *
	 * @return array<string,mixed>|null
	 */
	public function model(): ?array {
		if ( ! function_exists( 'seo_geo_theme_preset_document' ) ) {
			return null;
		}

		$document = \seo_geo_theme_preset_document( 'corporate', 'content-map.json' );
		$model    = is_array( $document ) ? ( $document['native_content_models'][ self::MODEL ] ?? null ) : null;

		return is_array( $model ) ? $model : null;
	}

	/**
	 * Return the saved reviewed kit.
	 *
	 * @return array<string,mixed>|null
	 */
	public function saved(): ?array {
		$value = get_option( self::OPTION, null );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Validate one candidate without writing it.
	 *
	 * @param array<string,mixed> $candidate Submitted content candidate.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate( array $candidate ): array|WP_Error {
		$model = $this->model();
		if ( ! is_array( $model ) || 1 !== ( $model['schema_version'] ?? null ) ) {
			return new WP_Error( 'seo_geo_home_content_model_unavailable', 'Corporate Home content model v1 is unavailable.' );
		}

		$draft_id = max( 0, (int) ( $candidate['draft_id'] ?? 0 ) );
		if ( 0 >= $draft_id || 'page' !== get_post_type( $draft_id ) || 'draft' !== get_post_status( $draft_id ) ) {
			return new WP_Error( 'seo_geo_home_content_draft_required', 'A private clean Home draft is required.' );
		}

		$plan_sha = (string) get_post_meta( $draft_id, CleanHomeRebuilder::PLAN_SHA_META, true );
		if ( '' === $plan_sha || ! hash_equals( $plan_sha, (string) ( $candidate['plan_sha256'] ?? '' ) ) ) {
			return new WP_Error( 'seo_geo_home_content_plan_drift', 'The Content Kit does not match the clean Home plan.' );
		}

		$raw_values = is_array( $candidate['values'] ?? null ) ? $candidate['values'] : array();
		$raw_groups = is_array( $candidate['verified_groups'] ?? null ) ? $candidate['verified_groups'] : array();
		$values     = array();
		$groups     = array(
			'hero-proof' => true === ( $raw_groups['hero-proof'] ?? false ),
			'proof'      => true === ( $raw_groups['proof'] ?? false ),
			'case-study' => true === ( $raw_groups['case-study'] ?? false ),
		);
		$errors     = array();

		$slots = is_array( $model['slots'] ?? null ) ? $model['slots'] : array();
		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) || ! is_string( $slot['id'] ?? null ) || ! is_string( $slot['type'] ?? null ) ) {
				return new WP_Error( 'seo_geo_home_content_model_invalid', 'Corporate Home content model contains an invalid slot.' );
			}

			$id                = $slot['id'];
			$type              = $slot['type'];
			$verification      = true === ( $slot['requires_verification'] ?? false );
			$verification_group = is_string( $slot['verification_group'] ?? null ) ? $slot['verification_group'] : '';
			$group_verified    = '' !== $verification_group && true === ( $groups[ $verification_group ] ?? false );
			$required          = true === ( $slot['required'] ?? false ) || ( $verification && $group_verified );
			$value             = $this->normalize_value( $type, $raw_values[ $id ] ?? null );

			if ( $required && $this->empty_value( $type, $value ) ) {
				$errors[] = 'required:' . $id;
			}

			if ( ! $this->empty_value( $type, $value ) ) {
				$values[ $id ] = $value;
			}
		}

		if ( array() !== $errors ) {
			return new WP_Error(
				'seo_geo_home_content_incomplete',
				'Corporate Home Content Kit is incomplete: ' . implode( ', ', $errors )
			);
		}

		$material = array(
			'schema_version'  => 1,
			'mode'            => 'corporate-home-content-kit',
			'model'           => self::MODEL,
			'draft_id'        => $draft_id,
			'source_id'       => (int) get_post_meta( $draft_id, CleanHomeRebuilder::SOURCE_ID_META, true ),
			'plan_sha256'     => $plan_sha,
			'locale'          => function_exists( 'seo_geo_theme_preset_locale' ) ? (string) \seo_geo_theme_preset_locale() : get_locale(),
			'values'          => $values,
			'verified_groups' => $groups,
		);

		$material['kit_sha256'] = hash(
			'sha256',
			(string) wp_json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		return $material;
	}

	/**
	 * Validate and persist one reviewed kit.
	 *
	 * @param array<string,mixed> $candidate Submitted content candidate.
	 * @return array<string,mixed>|WP_Error
	 */
	public function save( array $candidate ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_home_content_forbidden', 'Administrator capability is required.' );
		}

		$validated = $this->validate( $candidate );
		if ( $validated instanceof WP_Error ) {
			return $validated;
		}

		$validated['saved_at'] = gmdate( DATE_ATOM );
		update_option( self::OPTION, $validated, false );

		return $validated;
	}

	/**
	 * Normalize one typed slot value.
	 *
	 * @param string $type  Slot type.
	 * @param mixed  $value Raw slot value.
	 * @return mixed
	 */
	private function normalize_value( string $type, mixed $value ): mixed {
		if ( 'text' === $type ) {
			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}

		if ( 'link' === $type ) {
			$value = is_array( $value ) ? $value : array();
			$label = is_scalar( $value['label'] ?? null ) ? sanitize_text_field( (string) $value['label'] ) : '';
			$url   = is_scalar( $value['url'] ?? null ) ? trim( (string) $value['url'] ) : '';
			$url   = $this->safe_url( $url );

			return array(
				'label' => $label,
				'url'   => $url,
			);
		}

		if ( 'list' === $type ) {
			$items = is_array( $value ) ? $value : array();
			$items = array_values(
				array_filter(
					array_map(
						static fn( mixed $item ): string => is_scalar( $item ) ? sanitize_text_field( (string) $item ) : '',
						$items
					),
					static fn( string $item ): bool => '' !== $item
				)
			);

			return $items;
		}

		return null;
	}

	/**
	 * Whether one normalized slot value is empty.
	 *
	 * @param string $type  Slot type.
	 * @param mixed  $value Normalized slot value.
	 */
	private function empty_value( string $type, mixed $value ): bool {
		if ( 'text' === $type ) {
			return ! is_string( $value ) || '' === $value;
		}
		if ( 'link' === $type ) {
			return ! is_array( $value ) || '' === (string) ( $value['label'] ?? '' ) || '' === (string) ( $value['url'] ?? '' );
		}
		if ( 'list' === $type ) {
			return ! is_array( $value ) || array() === $value;
		}

		return true;
	}

	/**
	 * Accept only HTTP(S), root-relative and fragment links.
	 */
	private function safe_url( string $url ): string {
		if ( '' === $url || 1 !== preg_match( '#^(?:https?://|/|\#)#i', $url ) ) {
			return '';
		}

		return esc_url_raw( $url );
	}
}
