<?php
/**
 * Exact-ZIP, exact-destination confirmation for replacement imports.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Separates non-destructive staging from explicitly approved target replacement.
 *
 * This is NOT a backup service; existing provider-level backups remain external.
 */
final class ImportProductionGate {
	/**
	 * Determine whether the verified import may run against the current target.
	 *
	 * @param string              $job_id           Import job.
	 * @param array<string,mixed> $import           Fresh normalized import state.
	 * @param bool                $require_approval Require final confirmation.
	 */
	public static function ready( string $job_id, array $import, bool $require_approval = true ): bool {
		global $wpdb;

		if (
			! $wpdb instanceof wpdb
			|| '' === $job_id
			|| (string) ( $import['job_id'] ?? '' ) !== $job_id
			|| 'replace-production' !== ( $import['destination_policy'] ?? '' )
			|| 'portable-upload' !== ( $import['transport'] ?? '' )
			|| 'requires-protected-production-import' !== ( $import['destination_plan_status'] ?? '' )
			|| ! in_array( (string) ( $import['destination_scenario'] ?? '' ), array( 'same-domain-different-path', 'different-domain' ), true )
			|| 'payload-verified' !== ( $import['status'] ?? '' )
			|| true !== ( $import['full_payload_verified'] ?? false )
			|| true !== ( $import['restore_allowed'] ?? false )
			|| array() !== ( $import['blockers'] ?? array() )
			|| ! is_string( $import['archive_sha256'] ?? null )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $import['archive_sha256'] )
			|| (string) ( $import['destination_table_prefix'] ?? '' ) !== $wpdb->prefix
			|| untrailingslashit( (string) ( $import['destination_home_url'] ?? '' ) ) !== untrailingslashit( home_url( '/' ) )
			|| untrailingslashit( (string) ( $import['destination_site_url'] ?? '' ) ) !== untrailingslashit( site_url( '/' ) )
		) {
			return false;
		}

		if ( ! $require_approval ) {
			return true;
		}

		return hash_equals(
			(string) $import['archive_sha256'],
			(string) ( $import['production_confirmed_archive_sha256'] ?? '' )
		)
			&& (string) ( $import['destination_home_url'] ?? '' ) === (string) ( $import['production_confirmed_destination'] ?? '' )
			&& 0 < (int) ( $import['production_confirmed_user_id'] ?? 0 )
			&& '' !== (string) ( $import['production_confirmed_at'] ?? '' );
	}

	/**
	 * Record irreversible replacement approval after full staging/rewrite checks.
	 *
	 * @param string $job_id Import job.
	 * @return bool Whether the approval was persisted.
	 */
	public static function approve( string $job_id ): bool {
		if ( ! current_user_can( 'manage_options' ) || 0 >= get_current_user_id() ) {
			return false;
		}

		$store  = new ImportStateStore();
		$import = ( new ImportPreflight() )->validate( $job_id );
		if ( ! is_array( $import ) || ! self::ready( $job_id, $import, false ) ) {
			return false;
		}

		$finalize = ( new ImportFinalizeStateStore() )->get( $job_id );
		if (
			! is_array( $finalize )
			|| 'ready' !== ( $finalize['status'] ?? '' )
			|| true !== ( $finalize['activation_allowed'] ?? false )
			|| array() !== ( $finalize['blockers'] ?? array() )
		) {
			return false;
		}

		$import['production_confirmed_archive_sha256'] = $import['archive_sha256'];
		$import['production_confirmed_destination']    = $import['destination_home_url'];
		$import['production_confirmed_user_id']        = get_current_user_id();
		$import['production_confirmed_at']             = gmdate( DATE_ATOM );

		return $store->save( $job_id, $import );
	}
}
