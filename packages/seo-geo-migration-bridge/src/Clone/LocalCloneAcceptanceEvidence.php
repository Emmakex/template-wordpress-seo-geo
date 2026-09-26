<?php
/**
 * Portable Clone real-clone acceptance evidence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Builds one bounded, deterministic acceptance payload from a still-verified local handoff.
 */
final class LocalCloneAcceptanceEvidence {
	public const SCHEMA_VERSION = 1;
	public const EVIDENCE_TYPE  = 'local-clone-real-acceptance';

	/**
	 * Final local handoff reporter.
	 *
	 * @var LocalCloneHandoffReporter
	 */
	private LocalCloneHandoffReporter $reporter;

	/**
	 * Child import states.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $imports;

	/**
	 * Construct evidence service.
	 *
	 * @param LocalCloneHandoffReporter|null $reporter Optional verified handoff reporter.
	 * @param ImportStateStore|null           $imports  Optional child import state store.
	 */
	public function __construct(
		?LocalCloneHandoffReporter $reporter = null,
		?ImportStateStore $imports = null
	) {
		$this->reporter = $reporter ?? new LocalCloneHandoffReporter();
		$this->imports  = $imports ?? new ImportStateStore();
	}

	/**
	 * Return deterministic bounded acceptance evidence while final handoff authority remains valid.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_export( string $job_id ): ?array {
		$handoff = $this->reporter->verified_snapshot( $job_id );
		if (
			! is_array( $handoff )
			|| 'ready' !== ( $handoff['status'] ?? null )
			|| true !== ( $handoff['handoff_ready'] ?? false )
			|| array() !== ( $handoff['blockers'] ?? array() )
		) {
			return null;
		}

		$child_id = is_string( $handoff['child_import_job_id'] ?? null )
			? $handoff['child_import_job_id']
			: '';
		$import   = '' !== $child_id ? $this->imports->get( $child_id ) : null;
		if (
			! is_array( $import )
			|| 'private-same-server' !== ( $import['transport'] ?? null )
			|| (string) ( $import['local_handoff_parent_job_id'] ?? '' ) !== $job_id
			|| true !== ( $import['destination_storage_isolated'] ?? false )
			|| true !== ( $import['search_visibility_disabled'] ?? false )
			|| true !== ( $import['outbound_safe'] ?? false )
			|| true !== ( $import['backups_ready'] ?? false )
			|| true !== ( $import['target_authorized'] ?? false )
		) {
			return null;
		}

		$source_url = is_string( $import['source_home_url'] ?? null ) ? trailingslashit( $import['source_home_url'] ) : '';
		$target_url = is_string( $handoff['target_url'] ?? null ) ? trailingslashit( $handoff['target_url'] ) : '';
		if (
			'' === $source_url
			|| '' === $target_url
			|| untrailingslashit( $source_url ) === untrailingslashit( $target_url )
		) {
			return null;
		}

		$payload = array(
			'schema_version'                 => self::SCHEMA_VERSION,
			'evidence_type'                  => self::EVIDENCE_TYPE,
			'job_id'                         => $job_id,
			'child_import_job_id'            => $child_id,
			'source_url_sha256'              => hash( 'sha256', $source_url ),
			'target_url'                     => $target_url,
			'target_url_sha256'              => (string) ( $handoff['target_url_sha256'] ?? '' ),
			'target_table_prefix'            => (string) ( $handoff['target_table_prefix'] ?? '' ),
			'destination_authority_sha256'   => (string) ( $handoff['destination_authority_sha256'] ?? '' ),
			'activation_plan_hash'           => (string) ( $handoff['activation_plan_hash'] ?? '' ),
			'database_fingerprint'           => (string) ( $handoff['database_fingerprint'] ?? '' ),
			'file_fingerprint'               => (string) ( $handoff['file_fingerprint'] ?? '' ),
			'active_fingerprint'             => (string) ( $handoff['active_fingerprint'] ?? '' ),
			'target_root_sha256'             => (string) ( $handoff['target_root_sha256'] ?? '' ),
			'runtime_sha256'                 => (string) ( $handoff['runtime_sha256'] ?? '' ),
			'handoff_report_sha256'          => (string) ( $handoff['report_sha256'] ?? '' ),
			'table_count'                    => (int) ( $handoff['table_count'] ?? 0 ),
			'row_count'                      => (int) ( $handoff['row_count'] ?? 0 ),
			'file_count'                     => (int) ( $handoff['file_count'] ?? 0 ),
			'file_bytes'                     => (int) ( $handoff['file_bytes'] ?? 0 ),
			'home_matches'                   => true === ( $handoff['home_matches'] ?? false ),
			'siteurl_matches'                => true === ( $handoff['siteurl_matches'] ?? false ),
			'noindex_ready'                  => true === ( $handoff['noindex_ready'] ?? false ),
			'runtime_matches'                => true === ( $handoff['runtime_matches'] ?? false ),
			'bridge_control_ready'           => true === ( $handoff['bridge_control_ready'] ?? false ),
			'database_verified'              => true === ( $handoff['database_verified'] ?? false ),
			'files_verified'                 => true === ( $handoff['files_verified'] ?? false ),
			'rollback_available'             => true === ( $handoff['rollback_available'] ?? false ),
			'source_untouched'               => true === ( $handoff['source_untouched'] ?? false ),
			'handoff_ready'                  => true,
			'production_cutover_authorized'  => false,
			'sandbox_hardening_preserved'    => true,
			'blockers'                       => array(),
			'advisories'                     => array_values( is_array( $handoff['advisories'] ?? null ) ? $handoff['advisories'] : array() ),
		);

		if ( ! $this->payload_valid( $payload ) ) {
			return null;
		}

		$canonical = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $canonical ) ) {
			return null;
		}

		return array(
			'schema_version'    => self::SCHEMA_VERSION,
			'evidence_type'     => self::EVIDENCE_TYPE,
			'evidence'          => $payload,
			'acceptance_sha256' => hash( 'sha256', $canonical ),
			'exported_at'       => gmdate( DATE_ATOM ),
		);
	}

	/**
	 * Validate one deterministic evidence payload before export.
	 *
	 * @param array<string,mixed> $payload Acceptance payload.
	 */
	private function payload_valid( array $payload ): bool {
		foreach (
			array(
				'source_url_sha256',
				'target_url_sha256',
				'destination_authority_sha256',
				'activation_plan_hash',
				'database_fingerprint',
				'file_fingerprint',
				'active_fingerprint',
				'target_root_sha256',
				'runtime_sha256',
				'handoff_report_sha256',
			) as $field
		) {
			if ( ! is_string( $payload[ $field ] ?? null ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $payload[ $field ] ) ) {
				return false;
			}
		}

		if (
			! is_string( $payload['target_url'] ?? null )
			|| ! in_array( wp_parse_url( $payload['target_url'], PHP_URL_SCHEME ), array( 'http', 'https' ), true )
			|| ! is_string( $payload['target_table_prefix'] ?? null )
			|| 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $payload['target_table_prefix'] )
			|| 0 >= (int) ( $payload['table_count'] ?? 0 )
			|| 0 >= (int) ( $payload['row_count'] ?? 0 )
			|| 0 >= (int) ( $payload['file_count'] ?? 0 )
			|| 0 >= (int) ( $payload['file_bytes'] ?? 0 )
		) {
			return false;
		}

		foreach (
			array(
				'home_matches',
				'siteurl_matches',
				'noindex_ready',
				'runtime_matches',
				'bridge_control_ready',
				'database_verified',
				'files_verified',
				'rollback_available',
				'source_untouched',
				'handoff_ready',
				'sandbox_hardening_preserved',
			) as $field
		) {
			if ( true !== ( $payload[ $field ] ?? false ) ) {
				return false;
			}
		}

		return false === ( $payload['production_cutover_authorized'] ?? true )
			&& array() === ( $payload['blockers'] ?? null );
	}
}
