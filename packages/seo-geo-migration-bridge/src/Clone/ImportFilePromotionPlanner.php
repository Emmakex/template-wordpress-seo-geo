<?php
/**
 * Portable Clone reversible file-promotion planner.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use wpdb;

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
	 * Child import state.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $import_state;

	/**
	 * Construct the read-only promotion planner.
	 *
	 * @param ImportFilePromotionStateStore|null      $store               Optional promotion journal.
	 * @param ImportDatabaseActivationStateStore|null $database_activation Optional database activation journal.
	 * @param ImportFileStateStore|null               $file_state          Optional file staging state.
	 * @param ImportFinalizeStateStore|null           $finalize_state      Optional finalization state.
	 * @param ExportWorkspace|null                    $workspace           Optional private workspace.
	 * @param ImportStateStore|null                    $import_state        Optional child import state.
	 */
	public function __construct(
		?ImportFilePromotionStateStore $store = null,
		?ImportDatabaseActivationStateStore $database_activation = null,
		?ImportFileStateStore $file_state = null,
		?ImportFinalizeStateStore $finalize_state = null,
		?ExportWorkspace $workspace = null,
		?ImportStateStore $import_state = null
	) {
		$this->store               = $store ?? new ImportFilePromotionStateStore();
		$this->database_activation = $database_activation ?? new ImportDatabaseActivationStateStore();
		$this->file_state          = $file_state ?? new ImportFileStateStore();
		$this->finalize_state      = $finalize_state ?? new ImportFinalizeStateStore();
		$this->workspace           = $workspace ?? new ExportWorkspace();
		$this->import_state        = $import_state ?? new ImportStateStore();
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

		if ( ! $this->sandbox_ready( $job_id ) ) {
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

		$active_roots = $this->active_roots( $job_id );
		if ( null === $active_roots ) {
			return null;
		}

		$key  = substr( hash( 'sha256', $job_id ), 0, 16 );
		$plan = array();
		foreach ( $roots as $root ) {
			$id        = $root['id'];
			$active    = $active_roots[ $id ];
			$parent    = trailingslashit( wp_normalize_path( dirname( untrailingslashit( $active ) ) ) );
			$base      = basename( untrailingslashit( $active ) );
			$staging   = trailingslashit( wp_normalize_path( $staging_base . $id ) );
			$candidate = $parent . '.seo-geo-' . $key . '-candidate-' . $base;
			$rollback  = $parent . '.seo-geo-' . $key . '-rollback-' . $base;

			if (
				! is_dir( $parent )
				|| ! wp_is_writable( $parent )
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

		$runtime_before = $this->current_runtime( $job_id );
		$runtime_target = $this->source_runtime( $job_id );
		if ( null === $runtime_before || null === $runtime_target ) {
			return null;
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
			'runtime_before'       => $runtime_before,
			'runtime_target'       => $runtime_target,
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

			$files       = max( 0, (int) ( $root['file_count'] ?? 0 ) );
			$bytes       = max( 0, (int) ( $root['byte_count'] ?? 0 ) );
			$out[]       = array(
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
	 * Return the currently runnable sandbox plugin/theme runtime for rollback.
	 *
	 * @return array{active_plugins:list<string>,template:string,stylesheet:string}|null
	 */
	private function current_runtime( string $job_id ): ?array {
		$import = $this->private_same_server_import( $job_id );
		if ( is_array( $import ) ) {
			return $this->local_current_runtime( $import );
		}

		$plugins    = get_option( 'active_plugins', array() );
		$template   = get_option( 'template', '' );
		$stylesheet = get_option( 'stylesheet', '' );
		if ( ! is_array( $plugins ) || ! is_string( $template ) || ! is_string( $stylesheet ) ) {
			return null;
		}

		$normalized = array();
		foreach ( $plugins as $plugin ) {
			if (
				! is_string( $plugin )
				|| '' === $plugin
				|| 512 < strlen( $plugin )
				|| str_contains( $plugin, '../' )
				|| str_starts_with( $plugin, '/' )
			) {
				return null;
			}
			$normalized[] = wp_normalize_path( $plugin );
		}

		if (
			'' === $template
			|| '' === $stylesheet
			|| 1 !== preg_match( '/^[A-Za-z0-9._-]+$/', $template )
			|| 1 !== preg_match( '/^[A-Za-z0-9._-]+$/', $stylesheet )
		) {
			return null;
		}

		return array(
			'active_plugins' => array_values( array_unique( $normalized ) ),
			'template'       => $template,
			'stylesheet'     => $stylesheet,
		);
	}

	/**
	 * Recover the source plugin/theme runtime from the checksum-verified DB payload.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{active_plugins:list<string>,template:string,stylesheet:string}|null
	 */
	private function source_runtime( string $job_id ): ?array {
		$json = $this->workspace->read_import_extracted_file( $job_id, 'database/manifest.json' );
		if ( ! is_string( $json ) ) {
			return null;
		}

		$manifest = json_decode( $json, true );
		$source   = is_array( $manifest['source'] ?? null ) ? $manifest['source'] : array();
		$prefix   = is_string( $source['table_prefix'] ?? null ) ? $source['table_prefix'] : '';
		$tables   = is_array( $manifest['tables'] ?? null ) ? $manifest['tables'] : array();
		if (
			! is_array( $manifest )
			|| 1 !== ( $manifest['schema_version'] ?? null )
			|| 'database' !== ( $manifest['payload_class'] ?? null )
			|| '' === $prefix
		) {
			return null;
		}

		$options_meta = null;
		foreach ( $tables as $table ) {
			if ( is_array( $table ) && 0 === strcmp( $prefix . 'options', (string) ( $table['name'] ?? '' ) ) ) {
				$options_meta = $table;
				break;
			}
		}
		if ( ! is_array( $options_meta ) ) {
			return null;
		}

		$columns = is_array( $options_meta['columns'] ?? null ) ? array_values( $options_meta['columns'] ) : array();
		$name_i  = array_search( 'option_name', $columns, true );
		$value_i = array_search( 'option_value', $columns, true );
		if ( false === $name_i || false === $value_i ) {
			return null;
		}

		$wanted = array(
			'active_plugins' => null,
			'template'       => null,
			'stylesheet'     => null,
		);
		$chunks = is_array( $options_meta['chunks'] ?? null ) ? array_values( $options_meta['chunks'] ) : array();
		if ( 500 < count( $chunks ) ) {
			return null;
		}

		foreach ( $chunks as $index => $chunk_meta ) {
			if ( ! is_array( $chunk_meta ) || ! is_string( $chunk_meta['path'] ?? null ) ) {
				return null;
			}
			$path       = $chunk_meta['path'];
			$info       = $this->workspace->import_extracted_file_info( $job_id, $path );
			$chunk_json = $this->workspace->read_import_extracted_file( $job_id, $path );
			if (
				! is_array( $info )
				|| ! is_string( $chunk_json )
				|| (int) ( $chunk_meta['byte_count'] ?? -1 ) !== (int) $info['bytes']
				|| ! $this->same_hash( $chunk_meta['sha256'] ?? '', $info['sha256'] )
			) {
				return null;
			}

			$chunk = json_decode( $chunk_json, true );
			$rows  = is_array( $chunk['rows'] ?? null ) ? $chunk['rows'] : array();
			if (
				! is_array( $chunk )
				|| 1 !== ( $chunk['schema_version'] ?? null )
				|| (int) ( $chunk['chunk_index'] ?? -1 ) !== $index
				|| 'base64-or-null' !== ( $chunk['value_encoding'] ?? null )
				|| array_values( is_array( $chunk['columns'] ?? null ) ? $chunk['columns'] : array() ) !== $columns
			) {
				return null;
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || ! isset( $row[ $name_i ] ) || ! isset( $row[ $value_i ] ) ) {
					continue;
				}
				$name  = $this->decode_transport_value( $row[ $name_i ] );
				$value = $this->decode_transport_value( $row[ $value_i ] );
				if ( ! is_string( $name ) || ! array_key_exists( $name, $wanted ) || ! is_string( $value ) ) {
					continue;
				}
				if ( null !== $wanted[ $name ] ) {
					return null;
				}
				$wanted[ $name ] = $value;
			}
			if ( ! in_array( null, $wanted, true ) ) {
				break;
			}
		}

		if (
			! is_string( $wanted['active_plugins'] )
			|| ! is_string( $wanted['template'] )
			|| ! is_string( $wanted['stylesheet'] )
			|| '' === $wanted['template']
			|| '' === $wanted['stylesheet']
			|| 1 !== preg_match( '/^[A-Za-z0-9._-]+$/', $wanted['template'] )
			|| 1 !== preg_match( '/^[A-Za-z0-9._-]+$/', $wanted['stylesheet'] )
			|| ! is_serialized( $wanted['active_plugins'], false )
		) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged -- Verified WordPress active_plugins payload; classes remain disabled.
		$plugins = @unserialize( $wanted['active_plugins'], array( 'allowed_classes' => false ) );
		if ( ! is_array( $plugins ) ) {
			return null;
		}

		$normalized = array();
		foreach ( $plugins as $plugin ) {
			if (
				! is_string( $plugin )
				|| '' === $plugin
				|| 512 < strlen( $plugin )
				|| str_contains( $plugin, '../' )
				|| str_starts_with( $plugin, '/' )
			) {
				return null;
			}
			$normalized[] = wp_normalize_path( $plugin );
		}

		$bridge       = defined( 'SEO_GEO_MIGRATION_BRIDGE_DIR' )
			? plugin_basename( SEO_GEO_MIGRATION_BRIDGE_DIR . 'seo-geo-migration-bridge.php' )
			: 'seo-geo-migration-bridge/seo-geo-migration-bridge.php';
		$normalized[] = $bridge;

		return array(
			'active_plugins' => array_values( array_unique( $normalized ) ),
			'template'       => $wanted['template'],
			'stylesheet'     => $wanted['stylesheet'],
		);
	}

	/**
	 * Decode one base64-or-null transport value.
	 *
	 * @param mixed $value Encoded value.
	 */
	private function decode_transport_value( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary-safe migration transport decoding.
		$decoded = base64_decode( $value, true );

		return false === $decoded ? null : $decoded;
	}

	/**
	 * Return current WordPress destination roots.
	 *
	 * @return array{uploads:string,plugins:string,themes:string}|null
	 */
	private function active_roots( string $job_id ): ?array {
		$import = $this->private_same_server_import( $job_id );
		if ( is_array( $import ) ) {
			$root = $this->private_same_server_root( $import );
			if ( null === $root ) {
				return null;
			}

			$content = trailingslashit( wp_normalize_path( $root . '/wp-content' ) );

			return array(
				'uploads' => $content . 'uploads/',
				'plugins' => $content . 'plugins/',
				'themes'  => $content . 'themes/',
			);
		}

		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || '' === $uploads['basedir'] ) {
			return null;
		}

		$plugins = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '';
		$themes  = get_theme_root();
		if ( '' === $plugins || '' === $themes ) {
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
	 *
	 * @param string $job_id Child import job identifier.
	 */
	private function sandbox_ready( string $job_id ): bool {
		$import = $this->private_same_server_import( $job_id );
		if ( is_array( $import ) ) {
			$root = $this->private_same_server_root( $import );
			if (
				null === $root
				|| 'subdirectory' !== ( $import['destination_mode'] ?? null )
				|| true !== ( $import['destination_storage_isolated'] ?? false )
				|| true !== ( $import['search_visibility_disabled'] ?? false )
				|| true !== ( $import['outbound_safe'] ?? false )
				|| true !== ( $import['backups_ready'] ?? false )
				|| true !== ( $import['target_authorized'] ?? false )
				|| true !== ( $import['full_payload_verified'] ?? false )
				|| true !== ( $import['restore_allowed'] ?? false )
				|| array() !== ( $import['blockers'] ?? array() )
			) {
				return false;
			}

			foreach (
				array(
					'wp-config.php',
					'wp-content',
					'wp-content/plugins/seo-geo-migration-bridge/seo-geo-migration-bridge.php',
					'wp-content/mu-plugins/seo-geo-migration-sandbox-bootstrap.php',
				) as $relative
			) {
				$path = wp_normalize_path( $root . '/' . $relative );
				if ( is_link( $path ) || ( 'wp-content' === $relative ? ! is_dir( $path ) : ! is_file( $path ) ) ) {
					return false;
				}
			}

			return '0' === $this->local_option_value( $import, 'blog_public' );
		}

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
	 * Resolve one private same-server child import.
	 *
	 * @param string $job_id Child import job identifier.
	 * @return array<string,mixed>|null
	 */
	private function private_same_server_import( string $job_id ): ?array {
		$import = $this->import_state->get( $job_id );
		if (
			! is_array( $import )
			|| 'private-same-server' !== ( $import['transport'] ?? null )
			|| ! is_string( $import['local_handoff_parent_job_id'] ?? null )
			|| '' === $import['local_handoff_parent_job_id']
		) {
			return null;
		}

		return $import;
	}

	/**
	 * Resolve the isolated local-clone root.
	 *
	 * @param array<string,mixed> $import Child import state.
	 */
	private function private_same_server_root( array $import ): ?string {
		$root = is_string( $import['destination_root_path'] ?? null )
			? untrailingslashit( wp_normalize_path( $import['destination_root_path'] ) )
			: '';
		if (
			'' === $root
			|| ! is_dir( $root )
			|| is_link( $root )
			|| untrailingslashit( wp_normalize_path( ABSPATH ) ) === $root
		) {
			return null;
		}

		return $root;
	}

	/**
	 * Return the currently active runtime from the isolated target options table.
	 *
	 * @param array<string,mixed> $import Child import state.
	 * @return array{active_plugins:list<string>,template:string,stylesheet:string}|null
	 */
	private function local_current_runtime( array $import ): ?array {
		$plugins_raw = $this->local_option_value( $import, 'active_plugins' );
		$template    = $this->local_option_value( $import, 'template' );
		$stylesheet  = $this->local_option_value( $import, 'stylesheet' );
		if (
			null === $plugins_raw
			|| null === $template
			|| null === $stylesheet
			|| '' === $template
			|| '' === $stylesheet
			|| ! is_serialized( $plugins_raw, false )
			|| 1 !== preg_match( '/^[A-Za-z0-9._-]+$/', $template )
			|| 1 !== preg_match( '/^[A-Za-z0-9._-]+$/', $stylesheet )
		) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged -- Verified WordPress plugin list; classes remain disabled.
		$plugins = @unserialize( $plugins_raw, array( 'allowed_classes' => false ) );
		if ( ! is_array( $plugins ) ) {
			return null;
		}

		$normalized = array();
		foreach ( $plugins as $plugin ) {
			if (
				! is_string( $plugin )
				|| '' === $plugin
				|| 512 < strlen( $plugin )
				|| str_contains( $plugin, '../' )
				|| str_starts_with( $plugin, '/' )
			) {
				return null;
			}
			$normalized[] = wp_normalize_path( $plugin );
		}

		return array(
			'active_plugins' => array_values( array_unique( $normalized ) ),
			'template'       => $template,
			'stylesheet'     => $stylesheet,
		);
	}

	/**
	 * Read one option directly from the isolated activated options table.
	 *
	 * @param array<string,mixed> $import Child import state.
	 * @param string              $name   Option name.
	 */
	private function local_option_value( array $import, string $name ): ?string {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$prefix = is_string( $import['destination_table_prefix'] ?? null )
			? $import['destination_table_prefix']
			: '';
		$table = $prefix . 'options';
		if (
			1 !== preg_match( '/^[A-Za-z0-9_]+$/', $prefix )
			|| 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table )
		) {
			return null;
		}

		$quoted = chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $table ) . chr( 96 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only target option lookup against a validated isolated table.
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$quoted} WHERE option_name = %s LIMIT 1",
				$name
			)
		);

		return is_string( $value ) ? $value : null;
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
