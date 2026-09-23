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

			$normalized[ $key ] = array(
				'reference'  => $reference,
				'sha256'     => $sha256,
				'created_at' => $created_at,
				'passed'     => $passed,
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
}
