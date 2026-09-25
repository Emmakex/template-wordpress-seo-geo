<?php
/**
 * Portable Clone reversible file-promotion planner.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;

/**
 * Freezes the sibling candidate/rollback map before active WordPress file roots move.
 */
final class ImportFilePromotionPlanner {
	/**
	 * Promotion state store.
	 *
	 * @var ImportFilePromotionStateStore
	 */
	private ImportFilePromotionStateStore $store;

	/**
	 * Database activation journal.
	 *
	 * @var ImportDatabaseActivationStateStore
	 */
	private ImportDatabaseActivationStateStore $database_activation;

	/**
	 * Verified staging-file state.
	 *
	 * @var ImportFileStateStore
	 */
	private ImportFileStateStore $file_state;

	/**
	 * Accepted finalization state.
	 *
	 * @var ImportFinalizeStateStore
	 */
	private ImportFinalizeStateStore $finalize_state;

	/**
	 * Private workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct the read-only promotion planner.
	 *
	 * @param ImportFilePromotionStateStore|null      $store               Optional promotion journal.
	 * @param ImportDatabaseActivationStateStore|null $database_activation Optional database activation journal.
	 * @param ImportFileStateStore|null               $file_state          Optional file staging state.
	 * @param ImportFinalizeStateStore|null           $finalize_state      Optional finalization state.
	 * @param ExportWorkspace|null                    $workspace           Optional private workspace.
	 */
	public function __construct(
		?ImportFilePromotionStateStore $store = null,
		?ImportDatabaseActivationStateStore $database_activation = null,
		?ImportFileStateStore $file_state = null,
		?ImportFinalizeStateStore $finalize_state = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store               = $store ?? new ImportFilePromotionStateStore();
		$this->database_activation = $database_activation ?? new ImportDatabaseActivationStateStore();
		$this->file_state          = $file_state ?? new ImportFileStateStore();
		$this->finalize_state      = $finalize_state ?? new ImportFinalizeStateStore();
		$this->workspace           = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return the external promotion journal.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Freeze deterministic same-filesystem candidate and rollback paths.
	 *
	 * This step does not copy or rename any active file root.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function prepare( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		if ( ! $this->sandbox_ready() ) {
			return null;
		}

		$database = $this->database_activation->get( $job_id );
		$files    = $this->file_state->get( $job_id );
		$finalize = $this->finalize_state->get( $job_id );
		if (
			! is_array( $database )
			|| 'activated' !== ( $database['status'] ?? null )
			|| true !== ( $database['database_swapped'] ?? false )
			|| true !== ( $database['rollback_available'] ?? false )
			|| true === ( $database['handoff_ready'] ?? false )
			|| array() !== ( $database['blockers'] ?? array() )
			|| ! is_array( $files )
			|| 'complete' !== ( $files['status'] ?? null )
			|| true !== ( $files['active_roots_untouched'] ?? false )
			|| ! is_array( $finalize )
			|| 'ready' !== ( $finalize['status'] ?? null )
			|| true !== ( $finalize['activation_allowed'] ?? false )
			|| true === ( $finalize['handoff_ready'] ?? false )
			|| array() !== ( $finalize['blockers'] ?? array() )
			|| ! $this->same_hash( $database['activation_plan_hash'] ?? '', $finalize['activation_plan_hash'] ?? '' )
		) {
			return null;
		}

		$roots = $this->manifest_roots( $job_id );
		if ( null === $roots ) {
			return null;
		}

		$staging_base = $this->workspace->import_file_staging_root( $job_id );
		if ( null === $staging_base ) {
			return null;
		}

		$active_roots = $this->active_roots();
		if ( null === $active_roots ) {
			return null;
		}

		$key  = substr( hash( 'sha256', $job_id ), 0, 16 );
		$plan = array();
		foreach ( $roots as $root ) {
			$id      = $root['id'];
			$active  = $active_roots[ $id ];
			$parent  = trailingslashit( wp_normalize_path( dirname( untrailingslashit( $active ) ) ) );
			$base    = basename( untrailingslashit( $active ) );
			$staging = trailingslashit( wp_normalize_path( $staging_base . $id ) );
			$candidate = $parent . '.seo-geo-' . $key . '-candidate-' . $base;
			$rollback  = $parent . '.seo-geo-' . $key . '-rollback-' . $base;

			if (
				! is_dir( $parent )
				|| ! is_writable( $parent )
				|| is_link( untrailingslashit( $active ) )
				|| is_link( untrailingslashit( $staging ) )
				|| file_exists( $candidate )
				|| file_exists( $rollback )
				|| ( 0 < $root['file_count'] && ! is_dir( $staging ) )
				|| $this->paths_overlap( $staging, $active )
				|| $this->paths_overlap( $staging, $candidate )
				|| $this->paths_overlap( $staging, $rollback )
			) {
				return null;
			}

			$plan[] = array(
				'id'              => $id,
				'staging_path'    => $staging,
				'active_path'     => trailingslashit( wp_normalize_path( $active ) ),
				'candidate_path'  => trailingslashit( wp_normalize_path( $candidate ) ),
				'rollback_path'   => trailingslashit( wp_normalize_path( $rollback ) ),
				'file_count'      => $root['file_count'],
				'byte_count'      => $root['byte_count'],
				'copied_files'    => 0,
				'copied_bytes'    => 0,
				'verified_files'  => 0,
				'verified_bytes'  => 0,
				'status'          => 'pending',
				'active_existed'  => is_dir( untrailingslashit( $active ) ),
				'rollback_ready'  => false,
				'candidate_ready' => false,
			);
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'       => ImportFilePromotionStateStore::SCHEMA_VERSION,
			'job_id'               => $job_id,
			'status'               => 'prepared',
			'activation_plan_hash' => (string) $database['activation_plan_hash'],
			'file_fingerprint'     => (string) ( $finalize['file_fingerprint'] ?? '' ),
			'copy_fingerprint'     => hash( 'sha256', 'seo-geo-import-finalize-files-v1' ),
			'active_fingerprint'   => hash( 'sha256', 'seo-geo-import-finalize-files-v1' ),
			'roots'                => $plan,
			'root_index'           => 0,
			'pending_dirs'         => array( '' ),
			'current_dir'          => '',
			'after_name'           => '',
			'file_count'           => 0,
			'byte_count'           => 0,
			'verify_file_count'    => 0,
			'verify_byte_count'    => 0,
			'database_activated'   => true,
			'rollback_available'   => true,
			'handoff_ready'        => false,
			'blockers'             => array(),
			'prepared_at'          => $now,
			'promoted_at'          => '',
			'verified_at'          => '',
			'rolled_back_at'       => '',
			'updated_at'           => $now,
		);

		return $this->store->save( $job_id, $state ) ? $this->store->get( $job_id ) : null;
	}

	/**
	 * Load and validate root summaries from the verified files manifest.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return list<array{id:string,file_count:int,byte_count:int}>|null
	 */
	private function manifest_roots( string $job_id ): ?array {
		$json = $this->workspace->read_import_extracted_file( $job_id, 'files/manifest.json' );
		if ( ! is_string( $json ) ) {
			return null;
		}

		$manifest = json_decode( $json, true );
		$roots    = is_array( $manifest['roots'] ?? null ) ? array_values( $manifest['roots'] ) : array();
		if ( ! is_array( $manifest ) || 1 !== ( $manifest['schema_version'] ?? null ) || 'files' !== ( $manifest['payload_class'] ?? null ) ) {
			return null;
		}

		$out      = array();
		$seen     = array();
		$file_sum = 0;
		$byte_sum = 0;
		foreach ( $roots as $root ) {
			if ( ! is_array( $root ) || ! is_string( $root['id'] ?? null ) ) {
				return null;
			}
			$id = $root['id'];
			if ( ! in_array( $id, array( 'uploads', 'plugins', 'themes' ), true ) || isset( $seen[ $id ] ) ) {
				return null;
			}

			$files = max( 0, (int) ( $root['file_count'] ?? 0 ) );
			$bytes = max( 0, (int) ( $root['byte_count'] ?? 0 ) );
			$out[] = array(
				'id'         => $id,
				'file_count' => $files,
				'byte_count' => $bytes,
			);
			$seen[ $id ] = true;
			$file_sum   += $files;
			$byte_sum   += $bytes;
		}

		if (
			(int) ( $manifest['file_count'] ?? -1 ) !== $file_sum
			|| (int) ( $manifest['payload_bytes'] ?? -1 ) !== $byte_sum
		) {
			return null;
		}

		return $out;
	}

	/**
	 * Return current WordPress destination roots.
	 *
	 * @return array{uploads:string,plugins:string,themes:string}|null
	 */
	private function active_roots(): ?array {
		$uploads = wp_upload_dir( null, false );
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || ! is_string( $uploads['basedir'] ?? null ) ) {
			return null;
		}

		$plugins = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '';
		$themes  = get_theme_root();
		if ( ! is_string( $plugins ) || '' === $plugins || ! is_string( $themes ) || '' === $themes ) {
			return null;
		}

		return array(
			'uploads' => trailingslashit( wp_normalize_path( $uploads['basedir'] ) ),
			'plugins' => trailingslashit( wp_normalize_path( $plugins ) ),
			'themes'  => trailingslashit( wp_normalize_path( $themes ) ),
		);
	}

	/**
	 * Revalidate the sandbox safety boundary after database activation.
	 */
	private function sandbox_ready(): bool {
		$authorized = defined( ImportPreflight::TARGET_AUTHORIZED_MARKER )
			&& true === constant( ImportPreflight::TARGET_AUTHORIZED_MARKER );

		return SandboxGuard::enabled()
			&& 'invalid' !== SandboxGuard::mode()
			&& SandboxGuard::outbound_safe()
			&& SandboxGuard::backups_ready()
			&& $authorized
			&& ( 'subdirectory' !== SandboxGuard::mode() || SandboxGuard::storage_isolated() )
			&& '0' === (string) get_option( 'blog_public', '1' );
	}

	/**
	 * Whether two normalized filesystem paths overlap.
	 *
	 * @param string $left  First path.
	 * @param string $right Second path.
	 */
	private function paths_overlap( string $left, string $right ): bool {
		$left  = trailingslashit( wp_normalize_path( $left ) );
		$right = trailingslashit( wp_normalize_path( $right ) );

		return str_starts_with( $left, $right ) || str_starts_with( $right, $left );
	}

	/**
	 * Constant-time compare two SHA-256 values.
	 *
	 * @param mixed $left  First candidate.
	 * @param mixed $right Second candidate.
	 */
	private function same_hash( mixed $left, mixed $right ): bool {
		return is_string( $left )
			&& is_string( $right )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $left )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $right )
			&& hash_equals( $left, $right );
	}
}
