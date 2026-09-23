<?php
/**
 * Migration quality-evidence validator for Phase 8G.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Cutover;

/**
 * Requires recent accessibility and performance evidence before cutover.
 */
final class QualityEvidenceValidator {
	/**
	 * Maximum accepted evidence age.
	 */
	private const MAX_AGE_SECONDS = 7 * DAY_IN_SECONDS;

	/**
	 * Validate representative migration quality evidence.
	 *
	 * @param array<string,mixed> $evidence Raw quality evidence.
	 * @return array{valid:bool,evidence:array<string,array<string,mixed>>,errors:list<string>}
	 */
	public function validate( array $evidence ): array {
		$errors     = array();
		$normalized = array();

		foreach ( array( 'accessibility', 'performance' ) as $key ) {
			$row = $evidence[ $key ] ?? null;
			if ( ! is_array( $row ) ) {
				$errors[] = 'quality-evidence-missing:' . $key;
				continue;
			}

			$reference  = isset( $row['reference'] ) && is_string( $row['reference'] ) ? sanitize_text_field( $row['reference'] ) : '';
			$sha256     = isset( $row['sha256'] ) && is_string( $row['sha256'] ) ? strtolower( trim( $row['sha256'] ) ) : '';
			$created_at = isset( $row['created_at'] ) && is_string( $row['created_at'] ) ? trim( $row['created_at'] ) : '';
			$passed     = true === ( $row['passed'] ?? false );

			if ( '' === $reference || 200 < strlen( $reference ) ) {
				$errors[] = 'quality-reference-invalid:' . $key;
			}
			if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
				$errors[] = 'quality-sha256-invalid:' . $key;
			}
			if ( ! $passed ) {
				$errors[] = 'quality-gate-not-passed:' . $key;
			}

			$timestamp = '' !== $created_at ? strtotime( $created_at ) : false;
			$now       = time();
			if (
				false === $timestamp
				|| $timestamp > ( $now + 300 )
				|| $timestamp < ( $now - self::MAX_AGE_SECONDS )
			) {
				$errors[] = 'quality-created-at-invalid-or-stale:' . $key;
			}

			$comparison = isset( $row['comparison'] ) && is_array( $row['comparison'] )
				? $this->comparison( $row['comparison'] )
				: null;

			$normalized[ $key ] = array(
				'reference'  => $reference,
				'sha256'     => $sha256,
				'created_at' => $created_at,
				'passed'     => $passed,
				'comparison' => $comparison,
			);
		}

		$errors = array_values( array_unique( $errors ) );
		sort( $errors );

		return array(
			'valid'    => array() === $errors,
			'evidence' => $normalized,
			'errors'   => $errors,
		);
	}

	/**
	 * Normalize a compact before/after quality comparison.
	 *
	 * @param array<string,mixed> $comparison Raw comparison.
	 * @return array{before:array<string,int|float|string|bool>,after:array<string,int|float|string|bool>}|null
	 */
	private function comparison( array $comparison ): ?array {
		$before = $this->metrics( $comparison['before'] ?? null );
		$after  = $this->metrics( $comparison['after'] ?? null );

		if ( array() === $before || array() === $after ) {
			return null;
		}

		return array(
			'before' => $before,
			'after'  => $after,
		);
	}

	/**
	 * Keep only bounded scalar quality metrics.
	 *
	 * @param mixed $metrics Candidate metric map.
	 * @return array<string,int|float|string|bool>
	 */
	private function metrics( mixed $metrics ): array {
		if ( ! is_array( $metrics ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $metrics as $key => $value ) {
			if ( ! is_string( $key ) || ! is_scalar( $value ) ) {
				continue;
			}

			$key = sanitize_key( $key );
			if ( '' === $key || 30 < strlen( $key ) || 50 <= count( $normalized ) ) {
				continue;
			}

			if ( is_string( $value ) ) {
				$value = sanitize_text_field( $value );
				if ( 100 < strlen( $value ) ) {
					continue;
				}
			}

			$normalized[ $key ] = $value;
		}

		ksort( $normalized );
		return $normalized;
	}
}
