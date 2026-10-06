<?php
/**
 * Placeholder resolution tracking for reviewed Corporate Home content.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

/**
 * Shrinks the unresolved placeholder set when reviewed Content Kit values replace
 * the generated draft prompts. The publish guard therefore blocks only while real
 * unresolved placeholders remain.
 */
final class PlaceholderResolutionTracker {
	/** Register Content Kit change tracking. */
	public static function boot(): void {
		add_action( 'updated_option', array( self::class, 'after_option_update' ), 20, 3 );
	}

	/**
	 * Reconcile semantic placeholder state after the reviewed Home Content Kit changes.
	 *
	 * Automatic scaffold generation writes the Content Kit before placeholder post
	 * metadata exists, so the initial automatic save is intentionally ignored. Later
	 * reviewed saves can resolve individual slots without bypassing the publish guard.
	 *
	 * @param string $option    Updated option name.
	 * @param mixed  $old_value Previous option value.
	 * @param mixed  $value     New option value.
	 */
	public static function after_option_update( string $option, mixed $old_value, mixed $value ): void {
		if ( CorporateHomeContentKit::OPTION !== $option || ! is_array( $value ) ) {
			return;
		}

		$draft_id = max( 0, (int) ( $value['draft_id'] ?? 0 ) );
		if ( 0 >= $draft_id || 'page' !== get_post_type( $draft_id ) ) {
			return;
		}

		$placeholder_slots = get_post_meta( $draft_id, AutomaticHomeContentStateKit::PLACEHOLDER_META, true );
		if ( ! is_array( $placeholder_slots ) || array() === $placeholder_slots ) {
			return;
		}

		$old_values = is_array( $old_value ) && is_array( $old_value['values'] ?? null )
			? $old_value['values']
			: array();
		$new_values = is_array( $value['values'] ?? null ) ? $value['values'] : array();
		$remaining  = array();
		$resolved   = array();

		foreach ( $placeholder_slots as $slot_id ) {
			if ( ! is_string( $slot_id ) || '' === $slot_id ) {
				continue;
			}

			if ( ! array_key_exists( $slot_id, $new_values ) ) {
				$remaining[] = $slot_id;
				continue;
			}

			$before = $old_values[ $slot_id ] ?? null;
			$after  = $new_values[ $slot_id ];
			if ( self::canonical_value( $before ) === self::canonical_value( $after ) ) {
				$remaining[] = $slot_id;
				continue;
			}

			$resolved[] = $slot_id;
		}

		$remaining = array_values( array_unique( $remaining ) );
		update_post_meta( $draft_id, AutomaticHomeContentStateKit::PLACEHOLDER_META, $remaining );

		$state = get_post_meta( $draft_id, AutomaticHomeContentStateKit::STATE_META, true );
		if ( ! is_array( $state ) ) {
			return;
		}

		$slot_states = is_array( $state['slot_states'] ?? null ) ? $state['slot_states'] : array();
		foreach ( $resolved as $slot_id ) {
			$slot_states[ $slot_id ] = 'authored';
		}

		$state['slot_states']       = $slot_states;
		$state['placeholder_slots'] = $remaining;
		$state['publishable']       = array() === $remaining;
		if ( array() === $remaining ) {
			$state['mode'] = 'reviewed-content';
		}

		update_post_meta( $draft_id, AutomaticHomeContentStateKit::STATE_META, $state );
	}

	/**
	 * Convert one semantic slot value to a deterministic comparison string.
	 *
	 * @param mixed $value Slot value.
	 */
	private static function canonical_value( mixed $value ): string {
		if ( is_array( $value ) ) {
			return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
