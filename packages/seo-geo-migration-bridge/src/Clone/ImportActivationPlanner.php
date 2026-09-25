<?php
/**
 * Portable Import sandbox activation planner.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use wpdb;

/**
 * Builds a non-mutating activation + rollback plan for verified import staging.
 */
final class ImportActivationPlanner {
	/**
	 * Activation-plan persistence.
	 *
	 * @var ImportActivationPlanStore
	 */
	private ImportActivationPlanStore $store;

	/**
	 * Fresh import preflight service.
	 *
	 * @var ImportPreflight
	 */
	private ImportPreflight $preflight;

	/**
	 * Verified payload state.
	 *
	 * @var ImportPayloadStateStore
	 */
	private ImportPayloadStateStore $payload_state;

	/**
	 * Database staging state.
	 *
	 * @var ImportDatabaseStateStore
	 */
	private ImportDatabaseStateStore $database_state;

	/**
	 * File staging state.
	 *
	 * @var ImportFileStateStore
	 */
	private ImportFileStateStore $file_state;

	/**
	 * Environment rewrite state.
	 *
	 * @var ImportRewriteStateStore
	 */
	private ImportRewriteStateStore $rewrite_state;

	/**
	 * Database staging-plan source.
	 *
	 * @var ImportDatabaseRestorer
	 */
	private ImportDatabaseRestorer $database_restorer;

	/**
	 * Private import workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * External recovery evidence validator.
	 *
	 * @var ImportRecoveryEvidenceValidator
	 */
	private ImportRecoveryEvidenceValidator $recovery_validator;

	/**
	 * Clone job persistence.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Construct activation planner.
	 *
	 * @param ImportActivationPlanStore|null       $store              Optional activation-plan store.
	 * @param ImportPreflight|null                 $preflight          Optional fresh import preflight.
	 * @param ImportPayloadStateStore|null         $payload_state      Optional payload state store.
	 * @param ImportDatabaseStateStore|null        $database_state     Optional database staging state.
	 * @param ImportFileStateStore|null            $file_state         Optional file staging state.
	 * @param ImportRewriteStateStore|null         $rewrite_state      Optional environment rewrite state.
	 * @param ImportDatabaseRestorer|null          $database_restorer  Optional database staging planner.
	 * @param ExportWorkspace|null                 $workspace          Optional private import workspace.
	 * @param ImportRecoveryEvidenceValidator|null $recovery_validator Optional recovery validator.
	 * @param CloneJobStore|null                   $jobs               Optional clone job store.
	 */
	public function __construct(
		?ImportActivationPlanStore $store = null,
		?ImportPreflight $preflight = null,
		?ImportPayloadStateStore $payload_state = null,
		?ImportDatabaseStateStore $database_state = null,
		?ImportFileStateStore $file_state = null,
		?ImportRewriteStateStore $rewrite_state = null,
		?ImportDatabaseRestorer $database_restorer = null,
		?ExportWorkspace $workspace = null,
		?ImportRecoveryEvidenceValidator $recovery_validator = null,
		?CloneJobStore $jobs = null
	) {
		$this->store              = $store ?? new ImportActivationPlanStore();
		$this->jobs               = $jobs ?? new CloneJobStore();
		$this->workspace          = $workspace ?? new ExportWorkspace();
		$this->preflight          = $preflight ?? new ImportPreflight( null, $this->jobs, $this->workspace );
		$this->payload_state      = $payload_state ?? new ImportPayloadStateStore();
		$this->database_state     = $database_state ?? new ImportDatabaseStateStore();
		$this->file_state         = $file_state ?? new ImportFileStateStore();
		$this->rewrite_state      = $rewrite_state ?? new ImportRewriteStateStore();
		$this->database_restorer  = $database_restorer ?? new ImportDatabaseRestorer();
		$this->recovery_validator = $recovery_validator ?? new ImportRecoveryEvidenceValidator();
	}

	/**
	 * Return one stored activation plan.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Build and persist a non-mutating sandbox activation plan.
	 *
	 * @param string              $job_id            Clone job identifier.
	 * @param array<string,mixed> $recovery_evidence External recovery evidence.
	 * @return array<string,mixed>|null
	 */
	public function plan( string $job_id, array $recovery_evidence ): ?array {
		$job      = $this->jobs->get( $job_id );
		$fresh    = $this->preflight->validate( $job_id );
		$payload  = $this->payload_state->get( $job_id );
		$database = $this->database_state->get( $job_id );
		$files    = $this->file_state->get( $job_id );
		$rewrite  = $this->rewrite_state->get( $job_id );

		if (
			! is_array( $job )
			|| 'import' !== ( $job['operation'] ?? null )
			|| ! is_array( $fresh )
			|| ! is_array( $payload )
			|| ! is_array( $database )
			|| ! is_array( $files )
			|| ! is_array( $rewrite )
		) {
			return null;
		}

		$blockers   = array();
		$advisories = array();

		if (
			'payload-verified' !== ( $fresh['status'] ?? null )
			|| true !== ( $fresh['full_payload_verified'] ?? false )
			|| true !== ( $fresh['restore_allowed'] ?? false )
			|| array() !== ( $fresh['blockers'] ?? array() )
		) {
			$blockers[] = 'activation-preflight-not-ready';
		}
		if (
			'complete' !== ( $payload['status'] ?? null )
			|| 'complete' !== ( $payload['stage'] ?? null )
			|| ! hash_equals( (string) ( $fresh['archive_sha256'] ?? '' ), (string) ( $payload['archive_sha256'] ?? '' ) )
		) {
			$blockers[] = 'activation-payload-not-verified';
		}
		if (
			'complete' !== ( $database['status'] ?? null )
			|| 'complete' !== ( $database['stage'] ?? null )
			|| true !== ( $database['active_tables_untouched'] ?? false )
		) {
			$blockers[] = 'activation-database-staging-not-ready';
		}
		if (
			'complete' !== ( $files['status'] ?? null )
			|| 'complete' !== ( $files['stage'] ?? null )
			|| true !== ( $files['active_roots_untouched'] ?? false )
		) {
			$blockers[] = 'activation-file-staging-not-ready';
		}
		if (
			'complete' !== ( $rewrite['status'] ?? null )
			|| 'complete' !== ( $rewrite['stage'] ?? null )
			|| 0 !== (int) ( $rewrite['verify_source_urls'] ?? -1 )
			|| true !== ( $rewrite['active_tables_untouched'] ?? false )
			|| true !== ( $rewrite['active_roots_untouched'] ?? false )
		) {
			$blockers[] = 'activation-environment-rewrite-not-ready';
		}

		$recovery = $this->recovery_validator->validate( $recovery_evidence );
		foreach ( $recovery['errors'] as $error ) {
			$blockers[] = 'activation-' . $error;
		}

		$sandbox  = $this->sandbox_report();
		$blockers = array_merge( $blockers, $sandbox['blockers'] );

		$database_plan = $this->database_plan( $job_id );
		if ( null === $database_plan ) {
			$blockers[]   = 'activation-database-plan-invalid';
			$table_rows   = array();
			$table_prefix = '';
		} else {
			$table_rows   = $database_plan['tables'];
			$table_prefix = $database_plan['destination_prefix'];
		}

		$file_report = $this->file_plan( $job_id, $files );
		$blockers    = array_merge( $blockers, $file_report['blockers'] );
		$advisories  = array_merge( $advisories, $file_report['advisories'] );

		if (
			! hash_equals(
				(string) ( $database['database_manifest_sha256'] ?? '' ),
				(string) ( $rewrite['database_manifest_sha256'] ?? '' )
			)
			|| ! hash_equals(
				(string) ( $files['files_manifest_sha256'] ?? '' ),
				(string) ( $rewrite['file_manifest_sha256'] ?? '' )
			)
		) {
			$blockers[] = 'activation-manifest-identity-drift';
		}

		$blockers   = array_values( array_unique( $blockers ) );
		$advisories = array_values( array_unique( $advisories ) );
		sort( $blockers );
		sort( $advisories );

		$now  = gmdate( DATE_ATOM );
		$plan = array(
			'schema_version'              => ImportActivationPlanStore::SCHEMA_VERSION,
			'job_id'                      => $job_id,
			'status'                      => array() === $blockers ? 'ready' : 'blocked',
			'plan_sha256'                 => '',
			'archive_sha256'              => (string) ( $payload['archive_sha256'] ?? '' ),
			'database_manifest_sha256'    => (string) ( $database['database_manifest_sha256'] ?? '' ),
			'files_manifest_sha256'       => (string) ( $files['files_manifest_sha256'] ?? '' ),
			'table_count'                 => count( $table_rows ),
			'staging_file_count'          => (int) $file_report['file_count'],
			'staging_file_bytes'          => (int) $file_report['byte_count'],
			'destination_home_url'        => home_url( '/' ),
			'destination_site_url'        => site_url( '/' ),
			'destination_table_prefix'    => $table_prefix,
			'sandbox_mode'                => SandboxGuard::mode(),
			'target_authorized'           => $sandbox['target_authorized'],
			'search_visibility_disabled'  => $sandbox['search_visibility_disabled'],
			'outbound_safe'               => $sandbox['outbound_safe'],
			'backups_ready'               => $sandbox['backups_ready'],
			'storage_isolated'            => $sandbox['storage_isolated'],
			'recovery_valid'              => $recovery['valid'],
			'active_tables_untouched'     => true === ( $database['active_tables_untouched'] ?? false ),
			'active_roots_untouched'      => true === ( $files['active_roots_untouched'] ?? false ),
			'rewrite_verified'            => 0 === (int) ( $rewrite['verify_source_urls'] ?? -1 ),
			'activation_ready'            => array() === $blockers,
			'database_activation_allowed' => false,
			'file_activation_allowed'     => false,
			'mutations_performed'         => false,
			'tables'                      => $table_rows,
			'file_roots'                  => $file_report['roots'],
			'recovery'                    => $recovery['evidence'],
			'blockers'                    => $blockers,
			'advisories'                  => $advisories,
			'planned_at'                  => $now,
			'updated_at'                  => $now,
		);

		$plan['plan_sha256'] = $this->fingerprint_plan( $plan );
		if ( ! $this->store->save( $job_id, $plan ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'harden-sandbox',
			array() === $blockers ? 'activation-plan-ready' : 'activation-plan-blocked',
			array(
				'completed' => array() === $blockers ? 1 : 0,
				'total'     => 1,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Build strict sandbox/authorization report.
	 *
	 * @return array<string,mixed>
	 */
	private function sandbox_report(): array {
		$blockers = array();

		$enabled       = SandboxGuard::enabled();
		$mode          = SandboxGuard::mode();
		$outbound      = SandboxGuard::outbound_safe();
		$backups       = SandboxGuard::backups_ready();
		$storage       = SandboxGuard::storage_isolated();
		$search_hidden = '0' === (string) get_option( 'blog_public', '1' );
		$authorized    = defined( ImportPreflight::TARGET_AUTHORIZED_MARKER )
			&& true === constant( ImportPreflight::TARGET_AUTHORIZED_MARKER );

		if ( ! $enabled ) {
			$blockers[] = 'activation-sandbox-marker-missing';
		}
		if ( 'invalid' === $mode ) {
			$blockers[] = 'activation-sandbox-mode-invalid';
		}
		if ( ! $outbound ) {
			$blockers[] = 'activation-outbound-safety-not-confirmed';
		}
		if ( ! $backups ) {
			$blockers[] = 'activation-backups-ready-not-confirmed';
		}
		if ( ! $search_hidden ) {
			$blockers[] = 'activation-search-visibility-not-disabled';
		}
		if ( ! $authorized ) {
			$blockers[] = 'activation-target-authorization-missing';
		}
		if ( 'subdirectory' === $mode && ! $storage ) {
			$blockers[] = 'activation-subdirectory-storage-not-isolated';
		}

		return array(
			'target_authorized'          => $authorized,
			'search_visibility_disabled' => $search_hidden,
			'outbound_safe'              => $outbound,
			'backups_ready'              => $backups,
			'storage_isolated'           => 'origin' === $mode ? true : $storage,
			'blockers'                   => $blockers,
		);
	}

	/**
	 * Build exact table swap/rollback names without mutating SQL.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{destination_prefix:string,tables:list<array<string,mixed>>}|null
	 */
	private function database_plan( string $job_id ): ?array {
		$staging = $this->database_restorer->staging_plan( $job_id );
		if ( ! is_array( $staging ) || ! is_array( $staging['tables'] ?? null ) ) {
			return null;
		}

		$rows = array();
		foreach ( $staging['tables'] as $table ) {
			if ( ! is_array( $table ) ) {
				return null;
			}

			$source       = is_string( $table['source_table'] ?? null ) ? $table['source_table'] : '';
			$target       = is_string( $table['target_table'] ?? null ) ? $table['target_table'] : '';
			$staging_name = is_string( $table['staging_table'] ?? null ) ? $table['staging_table'] : '';
			$rollback     = $this->rollback_table_name( $job_id, $target, (string) ( $staging['destination_prefix'] ?? '' ) );
			if (
				! $this->valid_table( $source )
				|| ! $this->valid_table( $target )
				|| ! $this->valid_table( $staging_name )
				|| ! $this->valid_table( $rollback )
				|| $target === $staging_name
				|| $target === $rollback
				|| $staging_name === $rollback
				|| $this->table_exists( $rollback )
			) {
				return null;
			}

			$target_exists = $this->table_exists( $target );
			$rows[]        = array(
				'source_table'   => $source,
				'target_table'   => $target,
				'staging_table'  => $staging_name,
				'rollback_table' => $rollback,
				'target_exists'  => $target_exists,
				'target_rows'    => $target_exists ? $this->table_rows( $target ) : 0,
				'staging_rows'   => max( 0, (int) ( $table['row_count'] ?? 0 ) ),
			);
		}

		return array(
			'destination_prefix' => is_string( $staging['destination_prefix'] ?? null ) ? $staging['destination_prefix'] : '',
			'tables'             => $rows,
		);
	}

	/**
	 * Verify staged file tree still matches accepted 0.8.18 totals and active roots remain distinct.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  File-staging state.
	 * @return array{file_count:int,byte_count:int,roots:list<array<string,mixed>>,blockers:list<string>,advisories:list<string>}
	 */
	private function file_plan( string $job_id, array $state ): array {
		$staging = $this->workspace->import_file_staging_root( $job_id );
		if ( null === $staging ) {
			return array(
				'file_count' => 0,
				'byte_count' => 0,
				'roots'      => array(),
				'blockers'   => array( 'activation-file-staging-root-missing' ),
				'advisories' => array(),
			);
		}

		$blockers   = array();
		$roots      = array();
		$file_total = 0;
		$byte_total = 0;
		foreach ( $this->active_roots() as $id => $active_path ) {
			$root_path = trailingslashit( wp_normalize_path( $staging ) ) . $id;
			$scan      = $this->scan_directory( $root_path );
			if ( null === $scan ) {
				$blockers[] = 'activation-staging-root-invalid:' . $id;
				continue;
			}

			$active_path = wp_normalize_path( $active_path );
			if (
				str_starts_with( trailingslashit( $active_path ), trailingslashit( wp_normalize_path( $staging ) ) )
				|| str_starts_with( trailingslashit( wp_normalize_path( $staging ) ), trailingslashit( $active_path ) )
			) {
				$blockers[] = 'activation-active-root-overlaps-staging:' . $id;
			}
			if ( is_link( $active_path ) ) {
				$blockers[] = 'activation-active-root-symlink-unsupported:' . $id;
			}

			$file_total += $scan['files'];
			$byte_total += $scan['bytes'];
			$roots[]     = array(
				'id'                 => $id,
				'staging_file_count' => $scan['files'],
				'staging_bytes'      => $scan['bytes'],
				'active_exists'      => is_dir( $active_path ),
				'active_is_symlink'  => is_link( $active_path ),
			);
		}

		if (
			(int) ( $state['verify_file_count'] ?? -1 ) !== $file_total
			|| (int) ( $state['verify_byte_count'] ?? -1 ) !== $byte_total
			|| (int) ( $state['expected_file_count'] ?? -2 ) !== $file_total
			|| (int) ( $state['expected_byte_count'] ?? -2 ) !== $byte_total
		) {
			$blockers[] = 'activation-file-staging-totals-drift';
		}

		return array(
			'file_count' => $file_total,
			'byte_count' => $byte_total,
			'roots'      => $roots,
			'blockers'   => array_values( array_unique( $blockers ) ),
			'advisories' => array(),
		);
	}

	/**
	 * Resolve active WordPress file roots without creating directories.
	 *
	 * @return array{uploads:string,plugins:string,themes:string}
	 */
	private function active_roots(): array {
		return array(
			'uploads' => $this->uploads_root(),
			'plugins' => wp_normalize_path( WP_PLUGIN_DIR ),
			'themes'  => wp_normalize_path( get_theme_root() ),
		);
	}

	/**
	 * Resolve uploads root without wp_upload_dir() side effects.
	 */
	private function uploads_root(): string {
		if ( defined( 'UPLOADS' ) && is_string( UPLOADS ) && '' !== UPLOADS ) {
			return wp_normalize_path(
				str_starts_with( UPLOADS, '/' ) ? UPLOADS : trailingslashit( ABSPATH ) . ltrim( UPLOADS, '/' )
			);
		}

		$upload_path = get_option( 'upload_path', '' );
		if ( is_string( $upload_path ) && '' !== trim( $upload_path ) ) {
			$upload_path = trim( $upload_path );
			return wp_normalize_path(
				str_starts_with( $upload_path, '/' )
					? $upload_path
					: trailingslashit( ABSPATH ) . ltrim( $upload_path, '/' )
			);
		}

		return wp_normalize_path( trailingslashit( WP_CONTENT_DIR ) . 'uploads' );
	}

	/**
	 * Read-only recursive directory totals. Missing roots are valid empty roots.
	 *
	 * @param string $path Root path.
	 * @return array{files:int,bytes:int}|null
	 */
	private function scan_directory( string $path ): ?array {
		$path = wp_normalize_path( $path );
		if ( ! file_exists( $path ) ) {
			return array(
				'files' => 0,
				'bytes' => 0,
			);
		}
		if ( ! is_dir( $path ) || is_link( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		$files    = 0;
		$bytes    = 0;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $iterator as $item ) {
			if ( $item->isLink() || ! $item->isFile() || ! $item->isReadable() ) {
				return null;
			}
			$size = $item->getSize();
			if ( 0 > $size ) {
				return null;
			}
			++$files;
			$bytes += $size;
		}

		return array(
			'files' => $files,
			'bytes' => $bytes,
		);
	}

	/**
	 * Build deterministic rollback table name under MySQL's identifier limit.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $target Future active target table.
	 * @param string $prefix Destination prefix.
	 */
	private function rollback_table_name( string $job_id, string $target, string $prefix ): string {
		$stem = preg_replace( '/[^A-Za-z0-9_]/', '_', $prefix ) ?? '';
		$stem = substr( $stem, 0, 20 );

		return substr( $stem . 'sgr_' . substr( hash( 'sha256', $job_id . '|' . $target ), 0, 32 ), 0, 64 );
	}

	/**
	 * Whether one table exists.
	 *
	 * @param string $table Table identifier.
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_table( $table ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only activation planning query.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Return one table row count.
	 *
	 * @param string $table Table identifier.
	 */
	private function table_rows( string $table ): int {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_table( $table ) ) {
			return 0;
		}

		$quoted = chr( 96 ) . $table . chr( 96 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Validated identifier only; read-only count.
		$value = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $quoted );

		return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
	}

	/**
	 * Validate one MySQL identifier.
	 *
	 * @param string $table Table name.
	 */
	private function valid_table( string $table ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9_]{1,64}$/', $table );
	}

	/**
	 * Fingerprint a plan without self-reference.
	 *
	 * @param array<string,mixed> $plan Plan payload.
	 */
	private function fingerprint_plan( array $plan ): string {
		$plan['plan_sha256'] = '';
		$json                = wp_json_encode( $plan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return is_string( $json ) ? hash( 'sha256', $json ) : '';
	}
}
