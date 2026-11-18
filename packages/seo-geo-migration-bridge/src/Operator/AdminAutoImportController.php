<?php
/**
 * One-click, bounded browser-driven Portable ZIP import orchestration.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseActivator;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseActivationStateStore;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseRestorer;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseStateStore;
use SeoGeo\MigrationBridge\Clone\ImportEnvironmentRewriter;
use SeoGeo\MigrationBridge\Clone\ImportFilePromotionStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFilePromoter;
use SeoGeo\MigrationBridge\Clone\ImportFileRestorer;
use SeoGeo\MigrationBridge\Clone\ImportFileStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFinalizationPlanner;
use SeoGeo\MigrationBridge\Clone\ImportFinalizeStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadVerifier;
use SeoGeo\MigrationBridge\Clone\ImportProductionGate;
use SeoGeo\MigrationBridge\Clone\ImportRewriteStateStore;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use Throwable;

/**
 * Progresses exactly one isolated import batch per authenticated AJAX request.
 *
 * Network interruptions stop new batches; restarting resumes persisted checkpoints.
 * Production database/file activation requires a separate explicit confirmation.
 */
final class AdminAutoImportController {
	public const ACTION       = 'seo_geo_migration_auto_import_batch';
	public const NONCE_ACTION = 'seo_geo_migration_auto_import_batch';

	private const LOCK_OPTION = 'seo_geo_migration_import_process_lock';

	/**
	 * Register WordPress authenticated AJAX endpoint only.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Perform one bounded step or approve the final replacement after verification.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'code' => 'forbidden' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the verified nonce checked just below.
		$job_id = isset( $_POST['clone_job_id'] ) && is_string( $_POST['clone_job_id'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_ajax_referer( self::NONCE_ACTION . ':' . $job_id, 'nonce' );

		$job    = ( new CloneJobStore() )->get( $job_id );
		$import = ( new ImportStateStore() )->get( $job_id );
		if (
			! is_array( $job )
			|| 'import' !== ( $job['operation'] ?? '' )
			|| ! is_array( $import )
			|| 'replace-production' !== ( $import['destination_policy'] ?? '' )
		) {
			wp_send_json_error( array( 'code' => 'not-a-production-import' ), 409 );
		}

		$before = self::snapshot( $job_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read only after nonce verification above.
		$expected = isset( $_POST['expected_phase'] ) && is_string( $_POST['expected_phase'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
			? sanitize_key( wp_unslash( $_POST['expected_phase'] ) )
			: '';

		if ( $expected !== $before['phase'] || in_array( $before['status'], array( 'ready', 'blocked' ), true ) ) {
			wp_send_json_success( $before );
		}

		// A single global lock prevents concurrent target mutation from two jobs.
		$token  = wp_generate_uuid4();
		$locked = add_option(
			self::LOCK_OPTION,
			array(
				'token'   => $token,
				'expires' => time() + 180,
			),
			'',
			false
		);
		if ( ! $locked ) {
			$prior = get_option( self::LOCK_OPTION );
			if ( is_array( $prior ) && (int) ( $prior['expires'] ?? 0 ) < time() ) {
				delete_option( self::LOCK_OPTION );
				$locked = add_option(
					self::LOCK_OPTION,
					array(
						'token'   => $token,
						'expires' => time() + 180,
					),
					'',
					false
				);
			}
		}
		if ( ! $locked ) {
			wp_send_json_error( array( 'code' => 'another-import-is-running' ), 409 );
		}

		$failed = false;
		try {
			$phase  = $before['phase'];
			$result = null;
			if ( 'review' === $phase ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX nonce verified above.
				$confirmed = isset( $_POST['confirm_override'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['confirm_override'] ) );
				if ( $confirmed ) {
					$result = ImportProductionGate::approve( $job_id ) ? array( 'status' => 'complete' ) : null;
				}
			} else {
				$result = match ( $phase ) {
					'payload'     => ( new ImportPayloadVerifier() )->advance( $job_id, 30, 16 * 1024 * 1024 ),
					'database'    => ( new ImportDatabaseRestorer() )->advance( $job_id, 200 ),
					'files'       => ( new ImportFileRestorer() )->advance( $job_id, 30, 16 * 1024 * 1024 ),
					'rewrite'     => ( new ImportEnvironmentRewriter() )->advance( $job_id, 200 ),
					'finalize'    => ( new ImportFinalizationPlanner() )->advance( $job_id, 200, 30, 16 * 1024 * 1024 ),
					'db-activate' => ( new ImportDatabaseActivator() )->activate( $job_id ),
					'candidates'  => ( new ImportFilePromoter() )->advance_candidates( $job_id, 30, 16 * 1024 * 1024 ),
					'promote'     => ( new ImportFilePromoter() )->promote( $job_id ),
					'verify'      => ( new ImportFilePromoter() )->advance_verification( $job_id, 30, 16 * 1024 * 1024 ),
					default       => null,
				};
			}
			$failed = ! is_array( $result );
		} catch ( Throwable $error ) {
			$failed = true;
		} finally {
			$held = get_option( self::LOCK_OPTION );
			if ( is_array( $held ) && ( $held['token'] ?? '' ) === $token ) {
				delete_option( self::LOCK_OPTION );
			}
		}

		if ( $failed ) {
			wp_send_json_error( array( 'code' => 'import-step-failed-check-progress' ), 409 );
		}

		$after = self::snapshot( $job_id );
		if ( $after['phase'] === $before['phase'] && $after['checkpoint'] === $before['checkpoint'] ) {
			wp_send_json_error( array( 'code' => 'import-batch-stalled' ), 409 );
		}
		wp_send_json_success( $after );
	}

	/**
	 * Return safe progress without database rows, file paths or credentials.
	 *
	 * @param string $job_id Import job.
	 * @return array{status:string,phase:string,label:string,percent:float,processed:int,total:int,checkpoint:string}
	 */
	public static function snapshot( string $job_id ): array {
		$import = ( new ImportStateStore() )->get( $job_id );
		$stores = array(
			'payload'     => ( new ImportPayloadStateStore() )->get( $job_id ),
			'database'    => ( new ImportDatabaseStateStore() )->get( $job_id ),
			'files'       => ( new ImportFileStateStore() )->get( $job_id ),
			'rewrite'     => ( new ImportRewriteStateStore() )->get( $job_id ),
			'finalize'    => ( new ImportFinalizeStateStore() )->get( $job_id ),
			'db-activate' => ( new ImportDatabaseActivationStateStore() )->get( $job_id ),
			'candidates'  => ( new ImportFilePromotionStateStore() )->get( $job_id ),
		);

		$sequence = array( 'payload', 'database', 'files', 'rewrite', 'finalize', 'review', 'db-activate', 'candidates', 'promote', 'verify' );
		$phase    = 'payload';
		$status   = 'running';

		if ( ! is_array( $import ) || 'replace-production' !== ( $import['destination_policy'] ?? '' ) ) {
			$status = 'blocked';
		} elseif ( 'blocked' === ( $import['status'] ?? '' ) || array() !== ( $import['blockers'] ?? array() ) ) {
			$status = 'blocked';
		} else {
			foreach ( array( 'payload', 'database', 'files', 'rewrite', 'finalize' ) as $step ) {
				if ( 'complete' !== ( $stores[ $step ]['status'] ?? '' ) && ! ( 'finalize' === $step && 'ready' === ( $stores[ $step ]['status'] ?? '' ) ) ) {
					$phase = $step;
					break;
				}
				$phase = 'review';
			}

			if ( 'review' === $phase && ! ImportProductionGate::ready( $job_id, $import ) ) {
				$status = 'review';
			} elseif ( 'review' === $phase ) {
				$activation = $stores['db-activate'];
				$promotion  = $stores['candidates'];
				$phase      = 'db-activate';
				if ( 'activated' === ( $activation['status'] ?? '' ) ) {
					$phase = 'candidates';
					if ( in_array( (string) ( $promotion['status'] ?? '' ), array( 'candidate-ready', 'promoting' ), true ) ) {
						$phase = 'promote';
					} elseif ( 'verifying' === ( $promotion['status'] ?? '' ) ) {
						$phase = 'verify';
					} elseif ( 'verified' === ( $promotion['status'] ?? '' ) ) {
						$phase  = 'ready';
						$status = 'ready';
					}
				}
			}

			$current = $stores[ $phase ] ?? ( in_array( $phase, array( 'promote', 'verify' ), true ) ? $stores['candidates'] : null );
			if ( 'blocked' === ( $current['status'] ?? '' ) || in_array( (string) ( $current['status'] ?? '' ), array( 'rolled-back', 'rolling-back' ), true ) ) {
				$status = 'blocked';
			}
		}

		$index      = array_search( $phase, $sequence, true );
		$index      = false === $index ? count( $sequence ) : $index;
		$current    = $stores[ $phase ] ?? ( in_array( $phase, array( 'promote', 'verify' ), true ) ? $stores['candidates'] : null );
		$processed       = is_array( $current ) ? (int) ( $current['file_count'] ?? $current['rows_restored'] ?? $current['verify_file_count'] ?? 0 ) : 0;
		$total           = is_array( $current ) ? (int) ( $current['expected_file_count'] ?? $current['table_count'] ?? 0 ) : 0;
		$phase_progress  = 0.0;
		$payload_stage   = 'extract';
		if ( 'payload' === $phase && is_array( $current ) ) {
			$payload_stage = 'verify' === ( $current['stage'] ?? '' ) ? 'verify' : 'extract';
			$total         = max( 0, (int) ( $current['expected_file_count'] ?? 0 ) );
			if ( 'extract' === $payload_stage ) {
				// Extraction and checksum verification have independent persisted counters.
				// Never show verification=0 while the ZIP is actively being extracted.
				$processed      = max( 0, (int) ( $current['extract_file_count'] ?? 0 ) );
				$archive_total  = max( 0, (int) ( $current['archive_entry_count'] ?? 0 ) );
				$archive_cursor = max( 0, (int) ( $current['archive_cursor'] ?? 0 ) );
				$phase_progress = 0 < $archive_total ? 0.5 * min( 1.0, $archive_cursor / $archive_total ) : 0.0;
			} else {
				$processed      = max( 0, (int) ( $current['verify_file_count'] ?? 0 ) );
				$phase_progress = 0 < $total ? 0.5 + 0.5 * min( 1.0, $processed / $total ) : 0.5;
			}
		}
		$checkpoint = hash(
			'sha256',
			wp_json_encode(
				array(
					$phase,
					$status,
					is_array( $current ) ? $current : array(),
					$import['production_confirmed_at'] ?? '',
				)
			)
		);

		$copy = new OperatorCopy();
		$key  = 'payload' === $phase
			? 'auto_import_phase_payload_' . $payload_stage
			: 'auto_import_phase_' . str_replace( '-', '_', $phase );
		return array(
			'status'     => $status,
			'phase'      => $phase,
			'label'      => $copy->text( $key ),
			'percent'    => 'ready' === $status ? 100.0 : round( 100.0 * ( $index + $phase_progress ) / count( $sequence ), 1 ),
			'processed'  => $processed,
			'total'      => $total,
			'checkpoint' => $checkpoint,
		);
	}
}
