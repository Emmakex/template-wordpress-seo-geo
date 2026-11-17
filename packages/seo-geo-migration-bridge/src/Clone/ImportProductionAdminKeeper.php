<?php
/**
 * Preserve the destination operator's current WordPress login during table swaps.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Transfers only the authenticated administrator's login/session to STAGING.
 *
 * No active destination user table is edited. The source site's other users remain
 * imported normally; a conflicting login keeps the source user ID and is reauthorized.
 */
final class ImportProductionAdminKeeper {
	/**
	 * Preserve the current administrator in staging before activation.
	 *
	 * @param string $job_id Import job identifier.
	 * @param array  $tables Activation table plan.
	 * @phpstan-param list<array<string,mixed>> $tables
	 */
	public function apply( string $job_id, array $tables ): bool {
		global $wpdb;

		if (
			! $wpdb instanceof wpdb
			|| ! current_user_can( 'manage_options' )
			|| 0 >= get_current_user_id()
			|| is_multisite()
		) {
			return false;
		}

		$import = ( new ImportStateStore() )->get( $job_id );
		if ( ! is_array( $import ) || ! ImportProductionGate::ready( $job_id, $import ) ) {
			return false;
		}

		$target_users    = $wpdb->users;
		$target_usermeta = $wpdb->usermeta;
		$staged_users    = '';
		$staged_usermeta = '';
		foreach ( $tables as $table ) {
			if ( ! isset( $table['target_table'], $table['staging_table'] ) ) {
				return false;
			}
			if ( (string) ( $table['target_table'] ?? '' ) === $target_users ) {
				$staged_users = (string) ( $table['staging_table'] ?? '' );
			} elseif ( (string) ( $table['target_table'] ?? '' ) === $target_usermeta ) {
				$staged_usermeta = (string) ( $table['staging_table'] ?? '' );
			}
		}

		if (
			! $this->table_name_valid( $staged_users )
			|| ! $this->table_name_valid( $staged_usermeta )
			|| ! $this->table_name_valid( $target_users )
			|| ! $this->table_name_valid( $target_usermeta )
		) {
			return false;
		}

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		if ( ! $user || '' === $user->user_login ) {
			return false;
		}

		$admin_meta_keys = array(
			$wpdb->prefix . 'capabilities',
			$wpdb->prefix . 'user_level',
			'session_tokens',
		);
		$meta            = array();
		// phpcs:disable WordPress.DB.SlowDBQuery -- This verified staging operation touches only one user_id and three exact metadata keys.
		foreach ( $admin_meta_keys as $key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the current authenticated user before replacing only staging tables.
			$records = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT meta_value FROM %i WHERE user_id = %d AND meta_key = %s',
					$target_usermeta,
					$user_id,
					$key
				)
			);
			if ( array() === $records || null === $records[0] ) {
				return false;
			}
			$meta[ $key ] = (string) $records[0];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Looks up the imported staging login; active tables remain untouched.
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ID FROM %i WHERE user_login = %s',
				$staged_users,
				$user->user_login
			)
		);

		if ( null === $existing_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Determines an unused ID in the staging users table.
			$maximum = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(ID) FROM %i', $staged_users ) );
			$new_id  = $maximum + 1;
		} else {
			$new_id = (int) $existing_id;
		}
		if ( 0 >= $new_id ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Every mutation is confined to verified staging tables inside one transaction.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		$attributes = array(
			'user_login'          => $user->user_login,
			'user_pass'           => $user->user_pass,
			'user_nicename'       => $user->user_nicename,
			'user_email'          => $user->user_email,
			'user_url'            => $user->user_url,
			'user_registered'     => $user->user_registered,
			'user_activation_key' => $user->user_activation_key,
			'user_status'         => (int) $user->user_status,
			'display_name'        => $user->display_name,
		);

		if ( null === $existing_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Inserts ONLY the destination administrator into staging users.
			$ok = false !== $wpdb->insert( $staged_users, array_merge( array( 'ID' => $new_id ), $attributes ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Restores current login credentials in the matching staging user.
			$ok = false !== $wpdb->update( $staged_users, $attributes, array( 'ID' => $new_id ) );
		}

		if ( $ok ) {
			foreach ( $meta as $key => $value ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deletes only session/capability entries for the imported staging user.
				$ok = false !== $wpdb->delete(
					$staged_usermeta,
					array(
						'user_id'  => $new_id,
						'meta_key' => $key,
					)
				);
				if ( ! $ok ) {
					break;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Restores the current operator session in staging metadata.
				$ok = false !== $wpdb->insert(
					$staged_usermeta,
					array(
						'user_id'    => $new_id,
						'meta_key'   => $key,
						'meta_value' => $value,
					)
				);
				if ( ! $ok ) {
					break;
				}
			}
		}

		if ( ! $ok ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Roll back staging-only changes on failure.
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit only destination-admin staging overlay.
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Best-effort transaction abort.
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// phpcs:enable WordPress.DB.SlowDBQuery
		return true;
	}

	/**
	 * Validate one SQL table identifier before quoting.
	 *
	 * @param string $name Table name.
	 */
	private function table_name_valid( string $name ): bool {
		return '' !== $name && 1 === preg_match( '/^[A-Za-z0-9_]+$/', $name );
	}
	// phpcs:ignore PSR2.Classes.ClassDeclaration.CloseBraceAfterBody -- Final WordPress migration guard class boundary is explicit.
}
