<?php
/**
 * Structured Corporate inner-page content kit.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Error;

/**
 * Validates and persists reviewed inner-page content independently from legacy layout.
 */
final class CorporatePageContentKit {
	public const OPTION                   = 'seo_geo_corporate_page_content_kits_v1';
	public const BLUEPRINT_MODE           = 'corporate-page-content-blueprint';
	public const BLUEPRINT_SCHEMA_VERSION = 1;

	/**
	 * Return the Theme-owned content model for one Corporate page key.
	 *
	 * @param string $page_key Corporate page key.
	 * @return array<string,mixed>|null
	 */
	public function model( string $page_key ): ?array {
		$name = $this->model_name( $page_key );
		if ( '' === $name || ! function_exists( 'seo_geo_theme_preset_document' ) ) {
			return null;
		}

		$page_models = \seo_geo_theme_preset_document( 'corporate', 'page-models.json' );
		$model       = is_array( $page_models ) ? ( $page_models['native_content_models'][ $name ] ?? null ) : null;
		if ( is_array( $model ) ) {
			return $model;
		}

		$content_map = \seo_geo_theme_preset_document( 'corporate', 'content-map.json' );
		$model       = is_array( $content_map ) ? ( $content_map['native_content_models'][ $name ] ?? null ) : null;

		return is_array( $model ) ? $model : null;
	}

	/**
	 * Return one page's native model name.
	 *
	 * @param string $page_key Corporate page key.
	 */
	public function model_name( string $page_key ): string {
		$page_key = sanitize_key( $page_key );
		if ( function_exists( 'seo_geo_theme_preset_document' ) ) {
			$page_models = \seo_geo_theme_preset_document( 'corporate', 'page-models.json' );
			$name        = is_array( $page_models ) ? ( $page_models['models_by_page'][ $page_key ] ?? null ) : null;
			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}

		$definition = $this->page_definition( $page_key );
		if ( ! is_array( $definition ) || ! is_array( $definition['content_contract'] ?? null ) ) {
			return '';
		}

		return (string) ( $definition['content_contract']['native_content_model'] ?? '' );
	}

	/**
	 * Return one saved reviewed kit.
	 *
	 * @param string $page_key Corporate page key.
	 * @return array<string,mixed>|null
	 */
	public function saved( string $page_key ): ?array {
		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) {
			return null;
		}

		$value = $all[ sanitize_key( $page_key ) ] ?? null;

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Validate one portable reviewed Content Blueprint.
	 *
	 * @param string              $page_key  Corporate page key.
	 * @param array<string,mixed> $blueprint Portable reviewed content.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate_blueprint( string $page_key, array $blueprint ): array|WP_Error {
		$page_key = sanitize_key( $page_key );
		$model    = $this->model( $page_key );
		$name     = $this->model_name( $page_key );
		if ( ! is_array( $model ) || 1 !== ( $model['schema_version'] ?? null ) || '' === $name ) {
			return new WP_Error( 'seo_geo_page_content_model_unavailable', 'Corporate page content model v1 is unavailable.' );
		}

		$allowed_keys = array( 'schema_version', 'mode', 'page_key', 'model', 'locale', 'values', 'verified_groups' );
		foreach ( array_keys( $blueprint ) as $key ) {
			if ( ! in_array( $key, $allowed_keys, true ) ) {
				return new WP_Error( 'seo_geo_page_blueprint_runtime_identity', 'Content Blueprint contains an unsupported or runtime-bound top-level field.' );
			}
		}

		if (
			self::BLUEPRINT_SCHEMA_VERSION !== ( $blueprint['schema_version'] ?? null )
			|| self::BLUEPRINT_MODE !== ( $blueprint['mode'] ?? null )
			|| $page_key !== sanitize_key( (string) ( $blueprint['page_key'] ?? '' ) )
			|| $name !== (string) ( $blueprint['model'] ?? '' )
		) {
			return new WP_Error( 'seo_geo_page_blueprint_contract_invalid', 'Content Blueprint schema, page key, mode or model is invalid.' );
		}

		$locale        = is_scalar( $blueprint['locale'] ?? null ) ? sanitize_text_field( (string) $blueprint['locale'] ) : '';
		$active_locale = $this->active_locale();
		if ( '' === $locale || ! hash_equals( $active_locale, $locale ) ) {
			return new WP_Error( 'seo_geo_page_blueprint_locale_mismatch', 'Content Blueprint locale does not match the active Corporate preset locale.' );
		}

		$normalized = $this->normalize_values_and_groups(
			$model,
			is_array( $blueprint['values'] ?? null ) ? $blueprint['values'] : array(),
			is_array( $blueprint['verified_groups'] ?? null ) ? $blueprint['verified_groups'] : array()
		);
		if ( $normalized instanceof WP_Error ) {
			return $normalized;
		}

		$material = array(
			'schema_version'  => self::BLUEPRINT_SCHEMA_VERSION,
			'mode'            => self::BLUEPRINT_MODE,
			'page_key'        => $page_key,
			'model'           => $name,
			'locale'          => $locale,
			'values'          => $normalized['values'],
			'verified_groups' => $normalized['verified_groups'],
		);
		$material['blueprint_sha256'] = $this->hash_material( $material );

		return $material;
	}

	/**
	 * Import one portable blueprint and bind it to one clean inner-page draft.
	 *
	 * @param string              $page_key  Corporate page key.
	 * @param array<string,mixed> $blueprint Portable reviewed content.
	 * @param int                 $draft_id  Clean draft ID.
	 * @param string              $plan_sha  Current clean-page plan SHA-256.
	 * @return array<string,mixed>|WP_Error
	 */
	public function import_blueprint( string $page_key, array $blueprint, int $draft_id, string $plan_sha ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_page_content_forbidden', 'Administrator capability is required.' );
		}

		$portable = $this->validate_blueprint( $page_key, $blueprint );
		if ( $portable instanceof WP_Error ) {
			return $portable;
		}

		$validated = $this->validate(
			$page_key,
			array(
				'draft_id'        => $draft_id,
				'plan_sha256'     => $plan_sha,
				'values'          => $portable['values'],
				'verified_groups' => $portable['verified_groups'],
			)
		);
		if ( $validated instanceof WP_Error ) {
			return $validated;
		}

		unset( $validated['kit_sha256'] );
		$validated['input_mode']       = 'blueprint';
		$validated['blueprint_sha256'] = (string) $portable['blueprint_sha256'];
		$validated['kit_sha256']       = $this->hash_material( $validated );

		return $this->persist( $page_key, $validated );
	}

	/**
	 * Validate one runtime candidate without writing it.
	 *
	 * @param string              $page_key  Corporate page key.
	 * @param array<string,mixed> $candidate Submitted content candidate.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate( string $page_key, array $candidate ): array|WP_Error {
		$page_key = sanitize_key( $page_key );
		$model    = $this->model( $page_key );
		$name     = $this->model_name( $page_key );
		if ( ! is_array( $model ) || 1 !== ( $model['schema_version'] ?? null ) || '' === $name ) {
			return new WP_Error( 'seo_geo_page_content_model_unavailable', 'Corporate page content model v1 is unavailable.' );
		}

		$draft_id = max( 0, (int) ( $candidate['draft_id'] ?? 0 ) );
		if ( 0 >= $draft_id || 'page' !== get_post_type( $draft_id ) || 'draft' !== get_post_status( $draft_id ) ) {
			return new WP_Error( 'seo_geo_page_content_draft_required', 'A private clean Corporate page draft is required.' );
		}
		if ( $page_key !== (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::PAGE_KEY_META, true ) ) {
			return new WP_Error( 'seo_geo_page_content_key_mismatch', 'The clean draft does not belong to the requested Corporate page key.' );
		}

		$plan_sha = (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::PLAN_SHA_META, true );
		if ( '' === $plan_sha || ! hash_equals( $plan_sha, (string) ( $candidate['plan_sha256'] ?? '' ) ) ) {
			return new WP_Error( 'seo_geo_page_content_plan_drift', 'The Content Kit does not match the clean page plan.' );
		}

		$normalized = $this->normalize_values_and_groups(
			$model,
			is_array( $candidate['values'] ?? null ) ? $candidate['values'] : array(),
			is_array( $candidate['verified_groups'] ?? null ) ? $candidate['verified_groups'] : array()
		);
		if ( $normalized instanceof WP_Error ) {
			return $normalized;
		}

		$material = array(
			'schema_version'  => 1,
			'mode'            => 'corporate-page-content-kit',
			'page_key'        => $page_key,
			'model'           => $name,
			'draft_id'        => $draft_id,
			'source_id'       => (int) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_ID_META, true ),
			'plan_sha256'     => $plan_sha,
			'locale'          => $this->active_locale(),
			'values'          => $normalized['values'],
			'verified_groups' => $normalized['verified_groups'],
		);
		$material['kit_sha256'] = $this->hash_material( $material );

		return $material;
	}

	/**
	 * Validate and persist one reviewed runtime kit.
	 *
	 * @param string              $page_key  Corporate page key.
	 * @param array<string,mixed> $candidate Submitted content candidate.
	 * @return array<string,mixed>|WP_Error
	 */
	public function save( string $page_key, array $candidate ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_page_content_forbidden', 'Administrator capability is required.' );
		}

		$validated = $this->validate( $page_key, $candidate );
		if ( $validated instanceof WP_Error ) {
			return $validated;
		}

		return $this->persist( $page_key, $validated );
	}

	/**
	 * Normalize semantic values and verification groups against one model.
	 *
	 * @param array<string,mixed> $model      Theme-owned content model.
	 * @param array<string,mixed> $raw_values Candidate values.
	 * @param array<string,mixed> $raw_groups Candidate verification groups.
	 * @return array{values:array<string,mixed>,verified_groups:array<string,bool>}|WP_Error
	 */
	private function normalize_values_and_groups( array $model, array $raw_values, array $raw_groups ): array|WP_Error {
		$slots          = is_array( $model['slots'] ?? null ) ? $model['slots'] : array();
		$slot_types     = array();
		$allowed_groups = array();

		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) || ! is_string( $slot['id'] ?? null ) || ! is_string( $slot['type'] ?? null ) ) {
				return new WP_Error( 'seo_geo_page_content_model_invalid', 'Corporate page content model contains an invalid slot.' );
			}
			$slot_types[ $slot['id'] ] = $slot['type'];
			if ( true === ( $slot['requires_verification'] ?? false ) && is_string( $slot['verification_group'] ?? null ) ) {
				$allowed_groups[ $slot['verification_group'] ] = true;
			}
		}

		foreach ( array_keys( $raw_values ) as $slot_id ) {
			if ( ! is_string( $slot_id ) || ! array_key_exists( $slot_id, $slot_types ) ) {
				return new WP_Error( 'seo_geo_page_blueprint_unknown_slot', 'Content Blueprint contains an unknown semantic slot.' );
			}
		}
		foreach ( array_keys( $raw_groups ) as $group ) {
			if ( ! is_string( $group ) || ! isset( $allowed_groups[ $group ] ) ) {
				return new WP_Error( 'seo_geo_page_blueprint_unknown_group', 'Content Blueprint contains an unknown evidence verification group.' );
			}
		}

		$groups = array();
		foreach ( array_keys( $allowed_groups ) as $group ) {
			$groups[ $group ] = true === ( $raw_groups[ $group ] ?? false );
		}
		ksort( $groups );

		$values = array();
		$errors = array();
		foreach ( $slots as $slot ) {
			$id                 = (string) $slot['id'];
			$type               = (string) $slot['type'];
			$verification       = true === ( $slot['requires_verification'] ?? false );
			$verification_group = is_string( $slot['verification_group'] ?? null ) ? $slot['verification_group'] : '';
			$group_verified     = '' !== $verification_group && true === ( $groups[ $verification_group ] ?? false );
			$required           = true === ( $slot['required'] ?? false ) || ( $verification && $group_verified );
			$value              = $this->normalize_value( $type, $raw_values[ $id ] ?? null );

			if ( $required && $this->empty_value( $type, $value ) ) {
				$errors[] = 'required:' . $id;
			}
			if ( ! $this->empty_value( $type, $value ) ) {
				$values[ $id ] = $value;
			}
		}

		foreach ( is_array( $model['required_verified_groups'] ?? null ) ? $model['required_verified_groups'] : array() as $group ) {
			if ( ! is_string( $group ) || ! isset( $allowed_groups[ $group ] ) || true !== ( $groups[ $group ] ?? false ) ) {
				$errors[] = 'verified-group:' . ( is_scalar( $group ) ? (string) $group : 'invalid' );
			}
		}

		foreach ( is_array( $model['required_any_slots'] ?? null ) ? $model['required_any_slots'] : array() as $slot_group ) {
			if ( ! is_array( $slot_group ) ) {
				$errors[] = 'required-any:invalid';
				continue;
			}
			$present = false;
			$labels  = array();
			foreach ( $slot_group as $slot_id ) {
				if ( ! is_string( $slot_id ) || ! isset( $slot_types[ $slot_id ] ) ) {
					continue;
				}
				$labels[] = $slot_id;
				if ( array_key_exists( $slot_id, $values ) ) {
					$present = true;
				}
			}
			if ( ! $present ) {
				$errors[] = 'required-any:' . implode( '|', $labels );
			}
		}

		$this->validate_faq_pairs( $values, $errors );
		if ( array() !== $errors ) {
			return new WP_Error(
				'seo_geo_page_content_incomplete',
				'Corporate page content is incomplete: ' . implode( ', ', array_values( array_unique( $errors ) ) )
			);
		}

		return array(
			'values'          => $values,
			'verified_groups' => $groups,
		);
	}

	/**
	 * Validate optional FAQ as complete question/answer pairs.
	 *
	 * @param array<string,mixed> $values Normalized semantic values.
	 * @param array               $errors Validation findings.
	 * @phpstan-param list<string> $errors
	 */
	private function validate_faq_pairs( array $values, array &$errors ): void {
		$pair_count = 0;
		for ( $index = 1; 3 >= $index; ++$index ) {
			$question = trim( (string) ( $values[ 'faq-' . $index . '-question' ] ?? '' ) );
			$answer   = trim( (string) ( $values[ 'faq-' . $index . '-answer' ] ?? '' ) );
			if ( '' === $question && '' === $answer ) {
				continue;
			}
			if ( '' === $question || '' === $answer ) {
				$errors[] = 'faq-pair:' . $index;
				continue;
			}
			++$pair_count;
		}

		$faq_present = 0 < $pair_count
			|| '' !== trim( (string) ( $values['faq-heading'] ?? '' ) )
			|| '' !== trim( (string) ( $values['faq-intro'] ?? '' ) );
		if ( $faq_present && 0 === $pair_count ) {
			$errors[] = 'faq-question-answer-required';
		}
		if ( 0 < $pair_count && '' === trim( (string) ( $values['faq-heading'] ?? '' ) ) ) {
			$errors[] = 'faq-heading-required';
		}
	}

	/**
	 * Persist one already validated kit.
	 *
	 * @param string              $page_key  Corporate page key.
	 * @param array<string,mixed> $validated Validated Content Kit.
	 * @return array<string,mixed>
	 */
	private function persist( string $page_key, array $validated ): array {
		$validated['saved_at'] = gmdate( DATE_ATOM );
		$all                   = get_option( self::OPTION, array() );
		$all                   = is_array( $all ) ? $all : array();
		$all[ sanitize_key( $page_key ) ] = $validated;
		ksort( $all );
		update_option( self::OPTION, $all, false );

		return $validated;
	}

	/**
	 * Resolve one locale-aware Corporate page definition.
	 *
	 * @param string $page_key Corporate page key.
	 * @return array<string,mixed>|null
	 */
	private function page_definition( string $page_key ): ?array {
		if ( ! function_exists( 'seo_geo_theme_preset_document' ) || ! function_exists( 'seo_geo_theme_preset_locale' ) ) {
			return null;
		}
		$document = \seo_geo_theme_preset_document( 'corporate', 'content-map.json' );
		if ( ! is_array( $document ) ) {
			return null;
		}
		$locale = \seo_geo_theme_preset_locale();
		$pages  = $document['locales'][ $locale ]['pages'] ?? $document['locales']['en_US']['pages'] ?? null;
		if ( ! is_array( $pages ) ) {
			return null;
		}
		foreach ( $pages as $page ) {
			if ( is_array( $page ) && sanitize_key( (string) ( $page['key'] ?? '' ) ) === sanitize_key( $page_key ) ) {
				return $page;
			}
		}

		return null;
	}

	/** Hash deterministic reviewed-content material. */
	private function hash_material( array $material ): string {
		return hash(
			'sha256',
			(string) wp_json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}

	/** Resolve active Corporate preset locale. */
	private function active_locale(): string {
		return function_exists( 'seo_geo_theme_preset_locale' ) ? (string) \seo_geo_theme_preset_locale() : get_locale();
	}

	/** Normalize one typed slot value. */
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

			return array_values(
				array_filter(
					array_map(
						static fn( mixed $item ): string => is_scalar( $item ) ? sanitize_text_field( (string) $item ) : '',
						$items
					),
					static fn( string $item ): bool => '' !== $item
				)
			);
		}

		return null;
	}

	/** Whether one normalized slot value is empty. */
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

	/** Accept only HTTP(S), root-relative and fragment links. */
	private function safe_url( string $url ): string {
		if ( '' === $url || 1 !== preg_match( '#^(?:https?://|/|\#)#i', $url ) ) {
			return '';
		}

		return esc_url_raw( $url );
	}
}
