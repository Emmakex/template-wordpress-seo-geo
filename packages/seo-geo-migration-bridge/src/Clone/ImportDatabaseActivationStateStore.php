<?php
/**
 * Portable Clone database-activation journal stored outside the destination database.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Persists the database activation state in the private job workspace.
 *
 * The journal deliberately does not use wp_options because 10E.2A.4.6.2 can
 * atomically replace the destination options table.
 */
final class ImportDatabaseActivationStateStore {
	public const SCHEMA_VERSION = 1;
	public const RELATIVE_PATH  = 'import/activation/database-state.json';

	private ExportWorkspace $workspace;

	public function __construct( ?ExportWorkspace $workspace = null ) {
		$this->workspace = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one normalized database-activation state.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$json = $this->workspace->read( $job_id, self::RELATIVE_PATH );
		if ( ! is_string( $json ) ) {
			return null;
		}

		$state = json_decode( $json, true );

		return is_array( $state ) ? $this->normalize( $job_id, $state ) : null;
	}

	/**
	 * Atomically persist one normalized state outside the database.
	 *
	 * @param array<string,mixed> $state State.
	 */
	public function save( string $job_id, array $state ): bool {
		$normalized = $this->normalize( $job_id, $state );
		if ( null === $normalized ) {
			return false;
		}

		$json = wp_json_encode(
			$normalized,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
		if ( ! is_string( $json ) ) {
			return false;
		}

		return is_array( $this->workspace->write( $job_id, self::RELATIVE_PATH, $json . "\n" ) );
	}

	/**
	 * @param array<string,mixed> $state Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize( string $job_id, array $state ): ?array {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{7,127}$/', $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'prepared';
		if ( ! in_array( $status, array( 'prepared', 'activating', 'activated', 'rolling-back', 'rolled-back', 'blocked' ), true ) ) {
			return null;
		}

		$tables = array();
		foreach ( is_array( $state['tables'] ?? null ) ? array_slice( $state['tables'], 0, 500 ) : array() as $table ) {
			if ( ! is_array( $table ) ) {
				continue;
			}
			$staging  = $this->table_name( $table['staging_table'] ?? '' );
			$target   = $this->table_name( $table['target_table'] ?? '' );
			$rollback = $this->table_name( $table['rollback_table'] ?? '' );
			if ( '' === $staging || '' === $target || '' === $rollback ) {
				continue;
			}
			$tables[] = array(
				'staging_table'  => $staging,
				'target_table'   => $target,
				'rollback_table' => $rollback,
				'target_exists'  => true === ( $table['target_exists'] ?? false ),
				'row_count'      => max( 0, (int) ( $table['row_count'] ?? 0 ) ),
			);
		}

		$controls = array();
		foreach ( is_array( $state['control_options'] ?? null ) ? array_slice( $state['control_options'], 0, 40 ) : array() as $control ) {
			if ( ! is_array( $control ) || ! is_string( $control['name'] ?? null ) ) {
				continue;
			}
			$name = substr( sanitize_key( $control['name'] ), 0, 191 );
			$hash = $this->hash( $control['sha256'] ?? '' );
			if ( '' === $name || '' === $hash ) {
				continue;
			}
			$controls[] = array(
				'name'       => $name,
				'sha256'     => $hash,
				'byte_count' => max( 0, (int) ( $control['byte_count'] ?? 0 ) ),
			);
		}

		return array(
			'schema_version'       => self::SCHEMA_VERSION,
			'job_id'               => $job_id,
			'status'               => $status,
			'activation_plan_hash' => $this->hash( $state['activation_plan_hash'] ?? '' ),
			'finalize_db_hash'     => $this->hash( $state['finalize_db_hash'] ?? '' ),
			'tables'               => $tables,
			'control_options'      => $controls,
			'options_target'       => $this->table_name( $state['options_target'] ?? '' ),
			'options_staging'      => $this->table_name( $state['options_staging'] ?? '' ),
			'database_swapped'     => true === ( $state['database_swapped'] ?? false ),
			'rollback_available'   => true === ( $state['rollback_available'] ?? false ),
			'active_files_untouched' => true === ( $state['active_files_untouched'] ?? true ),
			'handoff_ready'        => true === ( $state['handoff_ready'] ?? false ),
			'blockers'             => $this->codes( $state['blockers'] ?? array() ),
			'prepared_at'          => $this->timestamp( $state['prepared_at'] ?? '' ),
			'activated_at'         => $this->timestamp( $state['activated_at'] ?? '' ),
			'verified_at'          => $this->timestamp( $state['verified_at'] ?? '' ),
			'rolled_back_at'       => $this->timestamp( $state['rolled_back_at'] ?? '' ),
			'updated_at'           => $this->timestamp( $state['updated_at'] ?? '' ),
		);
	}

	private function table_name( mixed $name ): string {
		return is_string( $name )
			&& '' !== $name
			&& 64 >= strlen( $name )
			&& 1 === preg_match( '/^[A-Za-z0-9_$-]+$/', $name )
			? $name
			: '';
	}

	private function hash( mixed $hash ): string {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : '';
	}

	/**
	 * @return list<string>
	 */
	private function codes( mixed $codes ): array {
		if ( ! is_array( $codes ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $codes, 0, 40 ) as $code ) {
			if ( is_string( $code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $code ) ) {
				$out[] = $code;
			}
		}

		return array_values( array_unique( $out ) );
	}

	private function timestamp( mixed $value ): string {
		return is_string( $value ) ? substr( $value, 0, 40 ) : '';
	}
}
