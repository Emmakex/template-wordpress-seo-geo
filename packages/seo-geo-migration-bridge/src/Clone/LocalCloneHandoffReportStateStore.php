<?php
/**
 * Portable Clone final local handoff report state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores one bounded final local-clone handoff report per parent job.
 */
final class LocalCloneHandoffReportStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_local_clone_handoff_report_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return one normalized report.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded report.
	 *
	 * @param string              $job_id Parent local-clone job identifier.
	 * @param array<string,mixed> $state  Raw report.
	 */
	public function save( string $job_id, array $state ): bool {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return false;
		}

		$normalized = $this->normalize( $job_id, $state );
		if ( null === $normalized ) {
			return false;
		}

		$states            = $this->all();
		$states[ $job_id ] = $normalized;
		$states            = $this->bounded( $states );

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			return add_option( self::OPTION_NAME, $states, '', false );
		}

		return update_option( self::OPTION_NAME, $states, false ) || $states === $this->all();
	}

	/**
	 * Return all normalized reports.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function all(): array {
		$value = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $value ) ) {
			return array();
		}

		$states = array();
		foreach ( $value as $job_id => $state ) {
			if ( ! is_string( $job_id ) || ! is_array( $state ) ) {
				continue;
			}

			$normalized = $this->normalize( $job_id, $state );
			if ( null !== $normalized ) {
				$states[ $job_id ] = $normalized;
			}
		}

		return $states;
	}

	/**
	 * Normalize one report.
	 *
	 * @param string              $job_id Parent job identifier.
	 * @param array<string,mixed> $state  Raw report.
	 * @return array<string,mixed>|null
	 */
	private function normalize( string $job_id, array $state ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'blocked';
		if ( ! in_array( $status, array( 'ready', 'blocked' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'               => self::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $status,
			'child_import_job_id'          => $this->job_id( $state['child_import_job_id'] ?? '' ),
			'destination_authority_sha256' => $this->hash( $state['destination_authority_sha256'] ?? '' ),
			'activation_plan_hash'         => $this->hash( $state['activation_plan_hash'] ?? '' ),
			'database_fingerprint'         => $this->hash( $state['database_fingerprint'] ?? '' ),
			'file_fingerprint'             => $this->hash( $state['file_fingerprint'] ?? '' ),
			'active_fingerprint'           => $this->hash( $state['active_fingerprint'] ?? '' ),
			'target_root_sha256'           => $this->hash( $state['target_root_sha256'] ?? '' ),
			'target_url_sha256'            => $this->hash( $state['target_url_sha256'] ?? '' ),
			'target_table_prefix'          => $this->identifier( $state['target_table_prefix'] ?? '' ),
			'table_count'                  => max( 0, (int) ( $state['table_count'] ?? 0 ) ),
			'row_count'                    => max( 0, (int) ( $state['row_count'] ?? 0 ) ),
			'file_count'                   => max( 0, (int) ( $state['file_count'] ?? 0 ) ),
			'file_bytes'                   => max( 0, (int) ( $state['file_bytes'] ?? 0 ) ),
			'home_matches'                 => true === ( $state['home_matches'] ?? false ),
			'siteurl_matches'              => true === ( $state['siteurl_matches'] ?? false ),
			'noindex_ready'                => true === ( $state['noindex_ready'] ?? false ),
			'runtime_matches'              => true === ( $state['runtime_matches'] ?? false ),
			'bridge_control_ready'         => true === ( $state['bridge_control_ready'] ?? false ),
			'database_verified'            => true === ( $state['database_verified'] ?? false ),
			'files_verified'               => true === ( $state['files_verified'] ?? false ),
			'rollback_available'           => true === ( $state['rollback_available'] ?? false ),
			'source_untouched'             => true === ( $state['source_untouched'] ?? false ),
			'handoff_ready'                => true === ( $state['handoff_ready'] ?? false ),
			'report_sha256'                => $this->hash( $state['report_sha256'] ?? '' ),
			'blockers'                     => $this->codes( $state['blockers'] ?? array() ),
			'advisories'                   => $this->codes( $state['advisories'] ?? array() ),
			'prepared_at'                  => $this->timestamp( $state['prepared_at'] ?? '' ),
			'updated_at'                   => $this->timestamp( $state['updated_at'] ?? '' ),
		);
	}

	/**
	 * Normalize SHA-256.
	 *
	 * @param mixed $value Raw hash.
	 */
	private function hash( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize job id.
	 *
	 * @param mixed $value Raw job id.
	 */
	private function job_id( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize one MySQL identifier/prefix.
	 *
	 * @param mixed $value Raw identifier.
	 */
	private function identifier( mixed $value ): string {
		return is_string( $value ) && 128 >= strlen( $value ) && 1 === preg_match( '/^[A-Za-z0-9_]+$/', $value ) ? $value : '';
	}

	/**
	 * Normalize bounded codes.
	 *
	 * @param mixed $values Raw codes.
	 * @return list<string>
	 */
	private function codes( mixed $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $values, 0, 60 ) as $value ) {
			if ( is_string( $value ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $value ) ) {
				$out[] = $value;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalize timestamp.
	 *
	 * @param mixed $value Raw timestamp.
	 */
	private function timestamp( mixed $value ): string {
		return is_string( $value ) ? substr( $value, 0, 40 ) : '';
	}

	/**
	 * Keep newest reports.
	 *
	 * @param array<string,array<string,mixed>> $states Reports.
	 * @return array<string,array<string,mixed>>
	 */
	private function bounded( array $states ): array {
		if ( count( $states ) <= self::MAX_STATES ) {
			return $states;
		}

		uasort(
			$states,
			static fn( array $left, array $right ): int => strcmp(
				(string) ( $right['updated_at'] ?? '' ),
				(string) ( $left['updated_at'] ?? '' )
			)
		);

		return array_slice( $states, 0, self::MAX_STATES, true );
	}

	/**
	 * Validate job id.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
