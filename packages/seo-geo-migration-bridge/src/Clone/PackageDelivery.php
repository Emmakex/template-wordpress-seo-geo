<?php
/**
 * Portable Clone authenticated package delivery and retention lifecycle.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Builds private ZIP delivery artifacts in bounded resumable batches.
 */
final class PackageDelivery {
	public const DEFAULT_BATCH_FILES  = 100;
	public const MAX_BATCH_FILES      = 500;
	public const DEFAULT_BATCH_BYTES  = 16777216;
	public const MAX_BATCH_BYTES      = 134217728;
	public const RETENTION_HOURS      = 24;
	public const DEFAULT_CLEANUP_BATCH = 250;

	private const CHECKSUM_SEED           = 'seo-geo-portable-clone-package-v1';
	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS    = 8000;

	/**
	 * Delivery state.
	 *
	 * @var DeliveryStateStore
	 */
	private DeliveryStateStore $store;

	/**
	 * Package integrity state.
	 *
	 * @var PackageStateStore
	 */
	private PackageStateStore $package_store;

	/**
	 * Clone inventory state.
	 *
	 * @var CloneInventoryStore
	 */
	private CloneInventoryStore $inventory_store;

	/**
	 * Database export state.
	 *
	 * @var ExportStateStore
	 */
	private ExportStateStore $database_store;

	/**
	 * File export state.
	 *
	 * @var FileExportStateStore
	 */
	private FileExportStateStore $file_store;

	/**
	 * Clone jobs.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Private export workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;


	/**
	 * Construct delivery service.
	 *
	 * @param DeliveryStateStore|null   $store           Optional delivery-state store.
	 * @param PackageStateStore|null    $package_store   Optional package-state store.
	 * @param CloneInventoryStore|null  $inventory_store Optional inventory-state store.
	 * @param ExportStateStore|null     $database_store  Optional database-export store.
	 * @param FileExportStateStore|null $file_store      Optional file-export store.
	 * @param CloneJobStore|null        $jobs            Optional clone-job store.
	 * @param ExportWorkspace|null      $workspace       Optional private workspace.
	 */
	public function __construct(
		?DeliveryStateStore $store = null,
		?PackageStateStore $package_store = null,
		?CloneInventoryStore $inventory_store = null,
		?ExportStateStore $database_store = null,
		?FileExportStateStore $file_store = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store           = $store ?? new DeliveryStateStore();
		$this->package_store   = $package_store ?? new PackageStateStore();
		$this->inventory_store = $inventory_store ?? new CloneInventoryStore();
		$this->database_store  = $database_store ?? new ExportStateStore();
		$this->file_store      = $file_store ?? new FileExportStateStore();
		$this->jobs            = $jobs ?? new CloneJobStore();
		$this->workspace       = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one delivery state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Start authenticated delivery preparation for one completed export package.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) && 'cleaned' !== ( $existing['status'] ?? null ) ) {
			return $existing;
		}
		if ( ! $this->store->can_create( $job_id ) ) {
			return null;
		}

		$job     = $this->jobs->get( $job_id );
		$package = $this->package_store->get( $job_id );
		$root    = $this->workspace->root_path( $job_id );
		if (
			! is_array( $job )
			|| 'export' !== ( $job['operation'] ?? null )
			|| ! is_array( $package )
			|| 'complete' !== ( $package['status'] ?? null )
			|| 'complete' !== ( $package['stage'] ?? null )
			|| null === $root
		) {
			return null;
		}

		$manifest_json = $this->workspace->read( $job_id, 'package/manifest.json' );
		if ( null === $manifest_json ) {
			return null;
		}

		$current_manifest_hash = hash( 'sha256', $manifest_json );
		if (
			'' === (string) ( $package['package_manifest_hash'] ?? '' )
			|| ! hash_equals( (string) $package['package_manifest_hash'], $current_manifest_hash )
		) {
			return null;
		}

		$manifest = json_decode( $manifest_json, true );
		if ( ! is_array( $manifest ) || true !== ( $manifest['integrity']['verified'] ?? false ) ) {
			return null;
		}

		$now = gmdate( DATE_ATOM );
		$manifest['delivery'] = array(
			'format'             => 'zip',
			'authenticated_only' => true,
			'public_url'         => false,
			'retention_hours'    => self::RETENTION_HOURS,
		);
		$manifest['safety']['delivery_ready'] = true;
		$manifest['delivery_prepared_at']     = $now;

		$json = wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) ) {
			return null;
		}

		$written = $this->workspace->write( $job_id, 'package/manifest.json', $json . "\n" );
		if ( null === $written ) {
			return null;
		}

		$package['package_manifest_hash'] = (string) $written['sha256'];
		if ( ! $this->package_store->save( $job_id, $package ) || ! $this->workspace->reset_delivery_archive( $job_id ) ) {
			return null;
		}

		$state = array(
			'schema_version'         => DeliveryStateStore::SCHEMA_VERSION,
			'job_id'                 => $job_id,
			'status'                 => 'building',
			'directory_active'       => false,
			'pending_dirs'           => array( '' ),
			'current_dir'            => '',
			'after_name'             => '',
			'archive_file_count'     => 0,
			'archive_source_bytes'   => 0,
			'verified_file_count'    => 0,
			'verified_byte_count'    => 0,
			'verification_checksum'  => hash( 'sha256', self::CHECKSUM_SEED ),
			'package_checksum'       => (string) ( $package['package_checksum'] ?? '' ),
			'package_manifest_hash'  => (string) $written['sha256'],
			'archive_sha256'         => '',
			'archive_bytes'          => 0,
			'retention_hours'        => self::RETENTION_HOURS,
			'expires_at'             => time() + ( self::RETENTION_HOURS * HOUR_IN_SECONDS ),
			'download_count'         => 0,
			'cleanup_deleted_count'  => 0,
			'blockers'               => array(),
			'started_at'             => $now,
			'updated_at'             => $now,
			'ready_at'               => '',
			'downloaded_at'          => '',
			'cleaned_at'             => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'integrity',
			'delivery:0',
			array(
				'completed' => 0,
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded archive-construction batch.
	 *
	 * @param string $job_id      Clone job identifier.
	 * @param int    $batch_files Maximum files archived this request.
	 * @param int    $batch_bytes Soft maximum source bytes archived this request.
	 * @return array<string,mixed>|null
	 */
	public function advance(
		string $job_id,
		int $batch_files = self::DEFAULT_BATCH_FILES,
		int $batch_bytes = self::DEFAULT_BATCH_BYTES
	): ?array {
		$batch_files = max( 1, min( self::MAX_BATCH_FILES, $batch_files ) );
		$batch_bytes = max( 1048576, min( self::MAX_BATCH_BYTES, $batch_bytes ) );
		$state       = $this->store->get( $job_id ) ?? $this->start( $job_id );

		if ( ! is_array( $state ) || 'building' !== ( $state['status'] ?? null ) ) {
			return $state;
		}

		if ( $this->is_expired( $state ) ) {
			$state['status']     = 'expired';
			$state['updated_at'] = gmdate( DATE_ATOM );
			$this->store->save( $job_id, $state );

			return $this->store->get( $job_id );
		}

		$root = $this->workspace->root_path( $job_id );
		if ( null === $root ) {
			return $this->block( $job_id, $state, 'delivery-workspace-unavailable', false );
		}

		$batch          = array();
		$processed      = 0;
		$processed_bytes= 0;
		$operations     = 0;

		while ( $processed < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( true !== ( $state['directory_active'] ?? false ) ) {
				if ( array() === $pending ) {
					if ( array() !== $batch ) {
						break;
					}

					return $this->complete_delivery( $job_id, $state );
				}

				$state['current_dir']      = (string) array_shift( $pending );
				$state['pending_dirs']     = $pending;
				$state['after_name']       = '';
				$state['directory_active'] = true;
			}

			$current_dir = (string) ( $state['current_dir'] ?? '' );
			$absolute    = $this->join_path( $root, $current_dir );
			$entries     = scandir( $absolute, SCANDIR_SORT_ASCENDING );
			if ( false === $entries ) {
				return $this->block( $job_id, $state, 'delivery-directory-read-failed', true );
			}

			$after_name   = (string) ( $state['after_name'] ?? '' );
			$dir_finished = true;

			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) ) {
					continue;
				}

				++$operations;
				$relative = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$path     = $this->join_path( $root, $relative );

				if ( is_link( $path ) ) {
					return $this->block( $job_id, $state, 'delivery-workspace-symlink', false );
				}

				if ( is_dir( $path ) ) {
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'delivery-directory-queue-limit', false );
					}
					$pending[]             = $relative;
					$state['pending_dirs'] = $pending;
					$state['after_name']   = $entry;

					if ( $operations >= self::MAX_ENTRY_OPERATIONS ) {
						$dir_finished = false;
						break;
					}
					continue;
				}

				if ( ! is_file( $path ) || ! is_readable( $path ) ) {
					return $this->block( $job_id, $state, 'delivery-source-unreadable', true );
				}

				$bytes = filesize( $path );
				$hash  = hash_file( 'sha256', $path );
				if ( false === $bytes || false === $hash ) {
					return $this->block( $job_id, $state, 'delivery-source-hash-failed', true );
				}

				$batch[] = array(
					'relative' => $relative,
					'path'     => $path,
					'bytes'    => (int) $bytes,
					'sha256'   => $hash,
				);
				$state['after_name'] = $entry;
				++$processed;
				$processed_bytes += (int) $bytes;

				if (
					$processed >= $batch_files
					|| $processed_bytes >= $batch_bytes
					|| $operations >= self::MAX_ENTRY_OPERATIONS
				) {
					$dir_finished = false;
					break;
				}
			}

			if ( $dir_finished ) {
				$state['directory_active'] = false;
				$state['current_dir']      = '';
				$state['after_name']       = '';
			}

			if ( 0 < $processed && $processed_bytes >= $batch_bytes ) {
				break;
			}
		}

		if ( array() !== $batch ) {
			$relative_paths = array_map(
				static fn( array $record ): string => (string) $record['relative'],
				$batch
			);
			if ( ! $this->workspace->append_delivery_archive_files( $job_id, $relative_paths ) ) {
				return $this->block( $job_id, $state, 'delivery-archive-write-failed', true );
			}

			foreach ( $batch as $record ) {
				$current_hash = hash_file( 'sha256', (string) $record['path'] );
				$current_size = filesize( (string) $record['path'] );
				if (
					false === $current_hash
					|| false === $current_size
					|| (int) $record['bytes'] !== (int) $current_size
					|| ! hash_equals( (string) $record['sha256'], $current_hash )
				) {
					return $this->block( $job_id, $state, 'delivery-source-changed-during-archive', false );
				}

				$state['archive_file_count']   = (int) ( $state['archive_file_count'] ?? 0 ) + 1;
				$state['archive_source_bytes'] = (int) ( $state['archive_source_bytes'] ?? 0 ) + (int) $record['bytes'];

				if ( 'package' === (string) $record['relative'] || str_starts_with( (string) $record['relative'], 'package/' ) ) {
					continue;
				}

				$canonical = 'payload|' . wp_normalize_path( (string) $record['relative'] )
					. '|' . (string) (int) $record['bytes']
					. '|' . (string) $record['sha256'];
				$state['verification_checksum'] = $this->chain_hash( (string) $state['verification_checksum'], $canonical );
				$state['verified_file_count']   = (int) ( $state['verified_file_count'] ?? 0 ) + 1;
				$state['verified_byte_count']   = (int) ( $state['verified_byte_count'] ?? 0 ) + (int) $record['bytes'];
			}
		}

		return $this->persist_progress( $job_id, $state );
	}

	/**
	 * Return verified private download metadata for one ready, unexpired archive.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{path:string,name:string,bytes:int,sha256:string}|null
	 */
	public function download_info( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if ( ! is_array( $state ) || 'ready' !== ( $state['status'] ?? null ) ) {
			return null;
		}

		if ( $this->is_expired( $state ) ) {
			$state['status']     = 'expired';
			$state['updated_at'] = gmdate( DATE_ATOM );
			$this->store->save( $job_id, $state );

			return null;
		}

		$info = $this->workspace->delivery_archive_info( $job_id );
		if (
			! is_array( $info )
			|| (int) ( $state['archive_bytes'] ?? 0 ) !== (int) $info['bytes']
			|| ! hash_equals( (string) ( $state['archive_sha256'] ?? '' ), (string) $info['sha256'] )
		) {
			return null;
		}

		return array(
			'path'   => (string) $info['path'],
			'name'   => $this->workspace->delivery_download_name( $job_id ),
			'bytes'  => (int) $info['bytes'],
			'sha256' => (string) $info['sha256'],
		);
	}

	/**
	 * Record one successful authenticated download.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function mark_downloaded( string $job_id ): bool {
		$state = $this->store->get( $job_id );
		if ( ! is_array( $state ) || 'ready' !== ( $state['status'] ?? null ) || $this->is_expired( $state ) ) {
			return false;
		}

		$state['download_count'] = (int) ( $state['download_count'] ?? 0 ) + 1;
		$state['downloaded_at']  = gmdate( DATE_ATOM );
		$state['updated_at']     = $state['downloaded_at'];

		return $this->store->save( $job_id, $state );
	}

	/**
	 * Run one bounded cleanup batch for a delivery artifact and its private workspace.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param int    $limit  Maximum workspace entries removed this request.
	 * @return array<string,mixed>|null
	 */
	public function cleanup( string $job_id, int $limit = self::DEFAULT_CLEANUP_BATCH ): ?array {
		$state = $this->store->get( $job_id );
		if ( ! is_array( $state ) ) {
			return null;
		}
		if ( 'cleaned' === ( $state['status'] ?? null ) ) {
			return $state;
		}

		$state['status']     = 'cleaning';
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		if ( ! $this->workspace->delete_delivery_archive( $job_id ) ) {
			return $this->block( $job_id, $state, 'delivery-archive-cleanup-failed', true );
		}

		$result = $this->workspace->cleanup_batch( $job_id, $limit );
		$state['cleanup_deleted_count'] = (int) ( $state['cleanup_deleted_count'] ?? 0 ) + (int) $result['deleted'];
		$state['updated_at']            = gmdate( DATE_ATOM );

		if ( true !== $result['complete'] ) {
			$state['status'] = 'cleaning';
			$this->store->save( $job_id, $state );

			return $this->store->get( $job_id );
		}

		$this->inventory_store->delete( $job_id );
		$this->database_store->delete( $job_id );
		$this->file_store->delete( $job_id );
		$this->package_store->delete( $job_id );

		$state['status']           = 'cleaned';
		$state['pending_dirs']     = array();
		$state['current_dir']      = '';
		$state['after_name']       = '';
		$state['archive_sha256']   = '';
		$state['archive_bytes']    = 0;
		$state['updated_at']       = gmdate( DATE_ATOM );
		$state['cleaned_at']       = $state['updated_at'];

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Opportunistically clean a bounded number of expired/abandoned delivery jobs.
	 *
	 * @param int $max_jobs Maximum jobs advanced this request.
	 * @param int $limit    Maximum workspace entries removed per job.
	 */
	public function cleanup_expired( int $max_jobs = 1, int $limit = self::DEFAULT_CLEANUP_BATCH ): void {
		$processed = 0;
		foreach ( $this->store->all() as $job_id => $state ) {
			if ( $processed >= max( 1, min( 5, $max_jobs ) ) ) {
				break;
			}

			$status  = (string) ( $state['status'] ?? '' );
			$expired = $this->is_expired( $state );
			if ( ! $expired && ! in_array( $status, array( 'expired', 'cleaning' ), true ) ) {
				continue;
			}

			if ( $expired && ! in_array( $status, array( 'cleaning', 'cleaned' ), true ) ) {
				$state['status']     = 'expired';
				$state['updated_at'] = gmdate( DATE_ATOM );
				$this->store->save( $job_id, $state );
			}

			$this->cleanup( $job_id, $limit );
			++$processed;
		}
	}

	/**
	 * Complete delivery after proving archive source still matches package integrity.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Delivery state.
	 * @return array<string,mixed>|null
	 */
	private function complete_delivery( string $job_id, array $state ): ?array {
		$package = $this->package_store->get( $job_id );
		if (
			! is_array( $package )
			|| 'complete' !== ( $package['status'] ?? null )
			|| (int) ( $package['payload_file_count'] ?? -1 ) !== (int) ( $state['verified_file_count'] ?? -2 )
			|| (int) ( $package['payload_byte_count'] ?? -1 ) !== (int) ( $state['verified_byte_count'] ?? -2 )
			|| ! hash_equals( (string) ( $package['package_checksum'] ?? '' ), (string) ( $state['verification_checksum'] ?? '' ) )
			|| ! hash_equals( (string) ( $package['package_checksum'] ?? '' ), (string) ( $state['package_checksum'] ?? '' ) )
			|| ! hash_equals( (string) ( $package['package_manifest_hash'] ?? '' ), (string) ( $state['package_manifest_hash'] ?? '' ) )
		) {
			return $this->block( $job_id, $state, 'delivery-package-integrity-mismatch', false );
		}

		$archive = $this->workspace->finalize_delivery_archive( $job_id );
		if ( ! is_array( $archive ) ) {
			return $this->block( $job_id, $state, 'delivery-archive-finalize-failed', true );
		}

		$now = gmdate( DATE_ATOM );
		$state['status']         = 'ready';
		$state['archive_sha256'] = (string) $archive['sha256'];
		$state['archive_bytes']  = (int) $archive['bytes'];
		$state['expires_at']     = time() + ( (int) $state['retention_hours'] * HOUR_IN_SECONDS );
		$state['updated_at']     = $now;
		$state['ready_at']       = $now;

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'completed' );

		return $this->store->get( $job_id );
	}

	/**
	 * Persist resumable delivery progress.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Delivery state.
	 * @return array<string,mixed>|null
	 */
	private function persist_progress( string $job_id, array $state ): ?array {
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$cursor = 'delivery:' . (string) ( $state['current_dir'] ?? '' ) . ':' . (string) ( $state['after_name'] ?? '' );
		$this->jobs->update_progress(
			$job_id,
			'integrity',
			substr( $cursor, 0, 160 ),
			array(
				'completed' => (int) ( $state['archive_file_count'] ?? 0 ),
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist one bounded delivery blocker.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Delivery state.
	 * @param string              $code      Blocker code.
	 * @param bool                $retryable Retry allowed.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers          = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]        = $code;
		$state['blockers'] = array_values( array_unique( $blockers ) );
		$state['status']   = 'blocked';
		$state['updated_at'] = gmdate( DATE_ATOM );

		$this->store->save( $job_id, $state );
		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Return whether one delivery has reached its retention deadline.
	 *
	 * @param array<string,mixed> $state Delivery state.
	 */
	private function is_expired( array $state ): bool {
		$expires = (int) ( $state['expires_at'] ?? 0 );
		if ( 0 < $expires ) {
			return time() >= $expires;
		}

		$started = strtotime( (string) ( $state['started_at'] ?? '' ) );
		return false !== $started && time() >= $started + ( self::RETENTION_HOURS * HOUR_IN_SECONDS );
	}

	/**
	 * Chain one deterministic package record.
	 *
	 * @param string $previous Previous chain hash.
	 * @param string $record   Canonical payload record.
	 */
	private function chain_hash( string $previous, string $record ): string {
		return hash( 'sha256', $previous . "\n" . $record );
	}

	/**
	 * Join normalized filesystem paths.
	 *
	 * @param string $base     Absolute base path.
	 * @param string $relative Relative path.
	 */
	private function join_path( string $base, string $relative ): string {
		return rtrim( wp_normalize_path( $base ), '/' )
			. ( '' === $relative ? '' : '/' . ltrim( wp_normalize_path( $relative ), '/' ) );
	}
}
