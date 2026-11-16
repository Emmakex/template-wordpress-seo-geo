<?php
/**
 * Authenticated, bounded browser-driven Portable Export batches.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\Clone\CloneInventory;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\DatabaseExporter;
use SeoGeo\MigrationBridge\Clone\DeliveryStateStore;
use SeoGeo\MigrationBridge\Clone\ExportStateStore;
use SeoGeo\MigrationBridge\Clone\FileExporter;
use SeoGeo\MigrationBridge\Clone\FileExportStateStore;
use SeoGeo\MigrationBridge\Clone\PackageBuilder;
use SeoGeo\MigrationBridge\Clone\PackageDelivery;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;
use Throwable;

/**
 * One AJAX request = one bounded, sequential batch. No server-side loop.
 */
final class AdminAutoExportController {
	public const ACTION       = 'seo_geo_migration_auto_export_batch';
	public const NONCE_ACTION = 'seo_geo_migration_auto_export_batch';

	/**
	 * Register authenticated AJAX only; unauthenticated requests are not accepted.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Execute only the next export step. Import, clone activation and cutover are excluded.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'code' => 'forbidden' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID is only used to scope the nonce below.
		$job_id = isset( $_POST['clone_job_id'] ) && is_string( $_POST['clone_job_id'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_ajax_referer( self::NONCE_ACTION . ':' . $job_id, 'nonce' );

		$job = ( new CloneJobStore() )->get( $job_id );
		if ( ! is_array( $job ) || 'export' !== ( $job['operation'] ?? '' ) || 'active' !== ( $job['status'] ?? '' ) ) {
			wp_send_json_error( array( 'code' => 'not-active-export' ), 409 );
		}

		$before = self::snapshot( $job );
		if ( 'running' !== $before['status'] ) {
			wp_send_json_success( $before );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$expected = isset( $_POST['expected_phase'] ) && is_string( $_POST['expected_phase'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already authenticated above.
			? sanitize_key( wp_unslash( $_POST['expected_phase'] ) )
			: '';
		if ( $expected !== $before['phase'] ) {
			wp_send_json_success( $before );
		}

		$lock_key = 'seo_geo_export_auto_' . md5( $job_id );
		$token    = wp_generate_uuid4();
		$locked   = add_option( $lock_key, array( 'expires' => time() + 180, 'token' => $token ), '', false );
		if ( ! $locked ) {
			$old = get_option( $lock_key );
			if ( is_array( $old ) && (int) ( $old['expires'] ?? 0 ) < time() ) {
				delete_option( $lock_key );
				$locked = add_option( $lock_key, array( 'expires' => time() + 180, 'token' => $token ), '', false );
			}
		}
		if ( ! $locked ) {
			wp_send_json_error( array( 'code' => 'busy' ), 409 );
		}

		$failed = false;
		$after  = $before;
		try {
			$old_state = self::phase_state( $job_id, $before['phase'] );
			$result    = match ( $before['phase'] ) {
				'inventory' => ( new CloneInventory() )->advance( $job_id, 100 ),
				'database'  => ( new DatabaseExporter() )->advance( $job_id, 100 ),
				'files'     => ( new FileExporter() )->advance( $job_id, 25, 8 * 1024 * 1024 ),
				'package'   => ( new PackageBuilder() )->advance( $job_id, 25, 8 * 1024 * 1024 ),
				'delivery'  => ( new PackageDelivery() )->advance( $job_id, 25, 8 * 1024 * 1024 ),
				default     => null,
			};
			$failed    = ! is_array( $result );
			$fresh     = ( new CloneJobStore() )->get( $job_id );
			$after     = is_array( $fresh ) ? self::snapshot( $fresh ) : $before;
			if ( ! $failed && 'running' === $after['status'] && $before['phase'] === $after['phase'] ) {
				$new_state = self::phase_state( $job_id, $after['phase'] );
				$failed    = $old_state === $new_state;
			}
		} catch ( Throwable $error ) {
			$failed = true;
		} finally {
			$current_lock = get_option( $lock_key );
			if ( is_array( $current_lock ) && $token === ( $current_lock['token'] ?? null ) ) {
				delete_option( $lock_key );
			}
		}

		if ( $failed ) {
			wp_send_json_error( array( 'code' => 'batch-stalled-or-failed' ), 409 );
		}
		wp_send_json_success( $after );
	}

	/**
	 * Return public progress counters only, never source rows, paths or secrets.
	 *
	 * @param array<string,mixed> $job Normalized job.
	 * @return array<string,mixed>
	 */
	public static function snapshot( array $job ): array {
		$job_id     = (string) ( $job['job_id'] ?? '' );
		$checkpoint = MigrationWizardNavigator::next( $job );
		$anchor     = $checkpoint['anchor'];
		$phase      = match ( $anchor ) {
			'seo-geo-guide-clone_inventory'        => 'inventory',
			'seo-geo-guide-clone_database_export'  => 'database',
			'seo-geo-guide-clone_file_export'      => 'files',
			'seo-geo-guide-clone_package'          => 'package',
			'seo-geo-guide-clone_delivery'         => 'delivery',
			default                                => '',
		};

		$state = self::phase_state( $job_id, $phase );
		$processed = 0;
		$total = null;
		switch ( $phase ) {
			case 'inventory':
				$processed = (int) ( $state['file_count'] ?? 0 );
				break;
			case 'database':
				$processed = (int) ( $state['row_count'] ?? 0 );
				break;
			case 'files':
				$processed = (int) ( $state['file_count'] ?? 0 );
				$total = (int) ( $state['inventory_file_count'] ?? 0 );
				break;
			case 'package':
				$processed = 'verify' === ( $state['stage'] ?? '' )
					? (int) ( $state['verify_file_count'] ?? 0 )
					: (int) ( $state['payload_file_count'] ?? 0 );
				break;
			case 'delivery':
				$processed = (int) ( $state['archive_file_count'] ?? 0 );
				break;
		}
		$blocked = true === ( $checkpoint['blocked'] ?? false )
			|| 'blocked' === ( $state['status'] ?? '' );
		$active = 'active' === ( $job['status'] ?? '' ) && 'export' === ( $job['operation'] ?? '' );

		$status = 'running';
		if ( true === $checkpoint['done'] ) {
			$status = 'ready';
		} elseif ( ! $active || $blocked ) {
			$status = 'paused';
		} elseif ( '' === $phase ) {
			$status = 'manual';
		}

		$completed = (int) $checkpoint['completed'];
		$steps     = (int) $checkpoint['total'];
		$percent   = 0 < $steps ? ( 100 * $completed / $steps ) : 0.0;
		if ( is_int( $total ) && 0 < $total && 'running' === $status ) {
			$percent += ( 100 / $steps ) * min( 1.0, $processed / $total );
		}

		return array(
			'status'       => $status,
			'phase'        => $phase,
			'label'        => ( new OperatorCopy() )->text( $checkpoint['label_key'] ),
			'completed'    => $completed,
			'steps'        => $steps,
			'processed'    => $processed,
			'unit'         => 'database' === $phase ? 'rows' : 'files',
			'total'        => $total,
			'percent'      => round( min( 100.0, $percent ), 1 ),
			'updated_at'   => (string) ( $job['updated_at'] ?? '' ),
		);
	}

	/**
	 * Read normalized state of exactly one permitted phase.
	 *
	 * @param string $job_id Valid job ID.
	 * @param string $phase  Export phase.
	 * @return array<string,mixed>|null
	 */
	private static function phase_state( string $job_id, string $phase ): ?array {
		return match ( $phase ) {
			'inventory' => ( new CloneInventoryStore() )->get( $job_id ),
			'database'  => ( new ExportStateStore() )->get( $job_id ),
			'files'     => ( new FileExportStateStore() )->get( $job_id ),
			'package'   => ( new \SeoGeo\MigrationBridge\Clone\PackageStateStore() )->get( $job_id ),
			'delivery'  => ( new DeliveryStateStore() )->get( $job_id ),
			default     => null,
		};
	}
}
