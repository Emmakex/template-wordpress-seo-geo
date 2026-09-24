<?php
/**
 * Bounded operator dependency-review planning store.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Review;

/**
 * Persists explicit operator review decisions without changing dependency graph authority.
 */
final class DependencyReviewStore {
	/**
	 * Non-autoloaded WordPress option.
	 */
	public const OPTION_NAME = 'seo_geo_migration_dependency_reviews_v1';

	/**
	 * Return classifications an operator may record for an UNKNOWN component.
	 *
	 * @return list<string>
	 */
	public static function allowed_decisions(): array {
		return array( 'KEEP', 'REPLACE', 'MIGRATE', 'OPTIONAL', 'REMOVE-CANDIDATE' );
	}

	/**
	 * Return all normalized review decisions.
	 *
	 * @return array<string,array{classification:string,reason:string,reviewed_at:string}>
	 */
	public function all(): array {
		$value = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $value ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $value as $component_id => $decision ) {
			if ( ! is_string( $component_id ) || ! $this->valid_component_id( $component_id ) || ! is_array( $decision ) ) {
				continue;
			}

			$classification = $decision['classification'] ?? null;
			$reason         = $decision['reason'] ?? null;
			$reviewed_at    = $decision['reviewed_at'] ?? null;

			if (
				! is_string( $classification )
				|| ! in_array( $classification, self::allowed_decisions(), true )
				|| ! is_string( $reason )
				|| ! is_string( $reviewed_at )
			) {
				continue;
			}

			$normalized[ $component_id ] = array(
				'classification' => $classification,
				'reason'         => $reason,
				'reviewed_at'    => $reviewed_at,
			);
		}

		ksort( $normalized );

		return $normalized;
	}

	/**
	 * Return one decision when it exists.
	 *
	 * @param string $component_id Dependency component identifier.
	 * @return array{classification:string,reason:string,reviewed_at:string}|null
	 */
	public function decision_for( string $component_id ): ?array {
		$all = $this->all();

		return $all[ $component_id ] ?? null;
	}

	/**
	 * Persist one bounded review decision.
	 *
	 * @param string $component_id   Dependency component identifier.
	 * @param string $classification Fixed planning classification.
	 */
	public function save( string $component_id, string $classification ): bool {
		if ( ! $this->valid_component_id( $component_id ) || ! in_array( $classification, self::allowed_decisions(), true ) ) {
			return false;
		}

		$all                  = $this->all();
		$all[ $component_id ] = array(
			'classification' => $classification,
			'reason'         => $this->reason_for( $classification ),
			'reviewed_at'    => gmdate( DATE_ATOM ),
		);
		ksort( $all );

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			return add_option( self::OPTION_NAME, $all, '', false );
		}

		update_option( self::OPTION_NAME, $all, false );

		return true;
	}

	/**
	 * Clear one review decision without deleting unrelated review state.
	 *
	 * @param string $component_id Dependency component identifier.
	 */
	public function clear( string $component_id ): bool {
		if ( ! $this->valid_component_id( $component_id ) ) {
			return false;
		}

		$all = $this->all();
		if ( ! isset( $all[ $component_id ] ) ) {
			return true;
		}

		unset( $all[ $component_id ] );
		update_option( self::OPTION_NAME, $all, false );

		return true;
	}

	/**
	 * Convert a review classification to one stable non-free-text reason code.
	 *
	 * @param string $classification Fixed planning classification.
	 */
	private function reason_for( string $classification ): string {
		return match ( $classification ) {
			'KEEP'             => 'operator-review-retain-operational',
			'REPLACE'          => 'operator-review-replace-after-parity',
			'MIGRATE'          => 'operator-review-migrate-before-cutover',
			'OPTIONAL'         => 'operator-review-optional-after-sandbox',
			'REMOVE-CANDIDATE' => 'operator-review-remove-candidate-after-sandbox',
			default            => 'operator-review-unresolved',
		};
	}

	/**
	 * Validate one bounded dependency component identifier.
	 *
	 * @param string $component_id Dependency component identifier.
	 */
	private function valid_component_id( string $component_id ): bool {
		if ( '' === $component_id || 220 < strlen( $component_id ) ) {
			return false;
		}

		return 1 === preg_match( '/^(?:plugin|provider|builder):[A-Za-z0-9._:\/-]+$/', $component_id );
	}
}
