<?php
/**
 * Phase 8G cutover snapshot history.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Cutover;

/**
 * Stores append-only cutover recovery records in a non-autoloaded option.
 */
final class CutoverSnapshotStore {
	/**
	 * Dedicated non-autoloaded cutover history option.
	 */
	public const OPTION_NAME = 'seo_geo_cutover_history_v1';

	/**
	 * Append one cutover recovery snapshot.
	 *
	 * @param array<string,mixed> $snapshot Recovery snapshot.
	 * @return array{saved:bool,id:string|null,reason:string|null}
	 */
	public function create( array $snapshot ): array {
		$history = $this->history();
		$latest  = $this->latest();

		if ( is_array( $latest ) && in_array( $latest['status'] ?? null, array( 'prepared', 'cutover-active' ), true ) ) {
			return array(
				'saved'  => false,
				'id'     => isset( $latest['id'] ) && is_string( $latest['id'] ) ? $latest['id'] : null,
				'reason' => 'active-cutover-record-exists',
			);
		}

		$id     = wp_generate_uuid4();
		$record = array_merge(
			$snapshot,
			array(
				'schema_version' => 1,
				'id'             => $id,
				'created_at'     => gmdate( DATE_ATOM ),
				'status'         => 'prepared',
				'events'         => array(
					array(
						'status' => 'prepared',
						'at'     => gmdate( DATE_ATOM ),
					),
				),
			)
		);

		$history[] = $record;
		$payload   = array(
			'schema_version' => 1,
			'records'        => $history,
		);

		$saved = array() === $history || false === get_option( self::OPTION_NAME, false )
			? add_option( self::OPTION_NAME, $payload, '', false )
			: update_option( self::OPTION_NAME, $payload, false );

		return array(
			'saved'  => $saved,
			'id'     => $saved ? $id : null,
			'reason' => $saved ? null : 'cutover-history-write-failed',
		);
	}

	/**
	 * Transition one record without replacing its recovery snapshot.
	 *
	 * @param string              $id              Cutover record ID.
	 * @param list<string>        $expected_status Allowed current statuses.
	 * @param string              $next_status     New status.
	 * @param array<string,mixed> $details         Bounded transition details.
	 */
	public function transition( string $id, array $expected_status, string $next_status, array $details = array() ): bool {
		$history = $this->history();
		$updated = false;

		foreach ( $history as &$record ) {
			if ( ! is_array( $record ) || ( $record['id'] ?? null ) !== $id ) {
				continue;
			}

			if ( ! in_array( $record['status'] ?? null, $expected_status, true ) ) {
				return false;
			}

			$record['status'] = $next_status;
			$record['events'] = isset( $record['events'] ) && is_array( $record['events'] ) ? $record['events'] : array();
			$record['events'][] = array(
				'status'  => $next_status,
				'at'      => gmdate( DATE_ATOM ),
				'details' => $details,
			);
			$updated = true;
			break;
		}
		unset( $record );

		if ( ! $updated ) {
			return false;
		}

		return update_option(
			self::OPTION_NAME,
			array(
				'schema_version' => 1,
				'records'        => $history,
			),
			false
		);
	}

	/**
	 * Return the latest record.
	 *
	 * @return array<string,mixed>|null
	 */
	public function latest(): ?array {
		$history = $this->history();
		if ( array() === $history ) {
			return null;
		}

		$latest = end( $history );
		return is_array( $latest ) ? $latest : null;
	}

	/**
	 * Return normalized history.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function history(): array {
		$value = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $value ) || 1 !== ( $value['schema_version'] ?? null ) || ! isset( $value['records'] ) || ! is_array( $value['records'] ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$value['records'],
				'is_array'
			)
		);
	}
}
