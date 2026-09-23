<?php
/**
 * Backup-evidence validator for Phase 8G.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Cutover;

/**
 * Validates external full-database and uploads-tree backup evidence.
 */
final class BackupEvidenceValidator {
	/**
	 * Maximum accepted backup age.
	 */
	private const MAX_AGE_SECONDS = DAY_IN_SECONDS;

	/**
	 * Validate and normalize required external backup evidence.
	 *
	 * @param array<string,mixed> $evidence Raw backup evidence.
	 * @return array{valid:bool,evidence:array<string,array<string,mixed>>,errors:list<string>}
	 */
	public function validate( array $evidence ): array {
		$errors     = array();
		$normalized = array();

		foreach (
			array(
				'database' => 'full-database',
				'uploads'  => 'uploads-tree',
			) as $key => $expected_scope
		) {
			$row = $evidence[ $key ] ?? null;
			if ( ! is_array( $row ) ) {
				$errors[] = 'backup-evidence-missing:' . $key;
				continue;
			}

			$reference  = isset( $row['reference'] ) && is_string( $row['reference'] ) ? sanitize_text_field( $row['reference'] ) : '';
			$sha256     = isset( $row['sha256'] ) && is_string( $row['sha256'] ) ? strtolower( trim( $row['sha256'] ) ) : '';
			$created_at = isset( $row['created_at'] ) && is_string( $row['created_at'] ) ? trim( $row['created_at'] ) : '';
			$scope      = isset( $row['scope'] ) && is_string( $row['scope'] ) ? sanitize_key( $row['scope'] ) : '';
			$size_bytes = isset( $row['size_bytes'] ) ? (int) $row['size_bytes'] : 0;

			if ( '' === $reference || 200 < strlen( $reference ) ) {
				$errors[] = 'backup-reference-invalid:' . $key;
			}
			if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
				$errors[] = 'backup-sha256-invalid:' . $key;
			}
			if ( $scope !== $expected_scope ) {
				$errors[] = 'backup-scope-invalid:' . $key;
			}
			if ( 0 >= $size_bytes ) {
				$errors[] = 'backup-size-invalid:' . $key;
			}

			$timestamp = '' !== $created_at ? strtotime( $created_at ) : false;
			$now       = time();
			if (
				false === $timestamp
				|| $timestamp > ( $now + 300 )
				|| $timestamp < ( $now - self::MAX_AGE_SECONDS )
			) {
				$errors[] = 'backup-created-at-invalid-or-stale:' . $key;
			}

			$normalized[ $key ] = array(
				'reference'  => $reference,
				'sha256'     => $sha256,
				'created_at' => $created_at,
				'scope'      => $scope,
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
