<?php
/**
 * Portable Import sandbox-activation recovery evidence validator.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Requires fresh external recovery evidence before sandbox staging can be promoted.
 */
final class ImportRecoveryEvidenceValidator {
	private const MAX_AGE_SECONDS = DAY_IN_SECONDS;

	/**
	 * Validate database + wp-content recovery evidence.
	 *
	 * @param array<string,mixed> $evidence Raw evidence.
	 * @return array{valid:bool,evidence:array<string,array<string,mixed>>,errors:list<string>}
	 */
	public function validate( array $evidence ): array {
		$errors     = array();
		$normalized = array();

		foreach (
			array(
				'database'   => 'full-database',
				'wp_content' => 'wp-content-tree',
			) as $key => $scope
		) {
			$row = is_array( $evidence[ $key ] ?? null ) ? $evidence[ $key ] : array();

			$reference  = is_string( $row['reference'] ?? null ) ? sanitize_text_field( $row['reference'] ) : '';
			$sha256     = is_string( $row['sha256'] ?? null ) ? strtolower( trim( $row['sha256'] ) ) : '';
			$created_at = is_string( $row['created_at'] ?? null ) ? trim( $row['created_at'] ) : '';
			$row_scope  = is_string( $row['scope'] ?? null ) ? sanitize_key( $row['scope'] ) : '';
			$size_bytes = max( 0, (int) ( $row['size_bytes'] ?? 0 ) );

			if ( '' === $reference || 200 < strlen( $reference ) ) {
				$errors[] = 'recovery-reference-invalid:' . $key;
			}
			if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
				$errors[] = 'recovery-sha256-invalid:' . $key;
			}
			if ( $scope !== $row_scope ) {
				$errors[] = 'recovery-scope-invalid:' . $key;
			}
			if ( 0 >= $size_bytes ) {
				$errors[] = 'recovery-size-invalid:' . $key;
			}

			$timestamp = '' === $created_at ? false : strtotime( $created_at );
			$now       = time();
			if (
				false === $timestamp
				|| $timestamp > ( $now + 300 )
				|| $timestamp < ( $now - self::MAX_AGE_SECONDS )
			) {
				$errors[] = 'recovery-created-at-invalid-or-stale:' . $key;
			}

			$normalized[ $key ] = array(
				'reference'  => $reference,
				'sha256'     => $sha256,
				'created_at' => $created_at,
				'scope'      => $row_scope,
				'size_bytes' => $size_bytes,
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
