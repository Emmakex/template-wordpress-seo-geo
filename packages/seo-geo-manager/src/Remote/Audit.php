<?php
/**
 * Bounded non-secret audit trail for the authenticated SEO/GEO Manager bridge.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Remote;

final class Audit {
	private const OPTION      = 'seo_geo_manager_remote_audit';
	private const MAX_RECORDS = 200;

	/**
	 * Store one bounded audit event without credentials or request bodies.
	 *
	 * @param array<string, mixed> $record Event fields.
	 * @return array<string, mixed>
	 */
	public function record( array $record ): array {
		$entry = array(
			'event_id'          => 'sgma_' . str_replace( '-', '', wp_generate_uuid4() ),
			'timestamp'         => gmdate( DATE_W3C ),
			'request_id'        => $this->text( $record['request_id'] ?? '' ),
			'connection_id'     => $this->text( $record['connection_id'] ?? '' ),
			'wordpress_user_id' => max( 0, (int) ( $record['wordpress_user_id'] ?? 0 ) ),
			'method'            => strtoupper( substr( $this->text( $record['method'] ?? '' ), 0, 12 ) ),
			'route'             => substr( $this->text( $record['route'] ?? '' ), 0, 220 ),
			'scope'             => substr( $this->text( $record['scope'] ?? '' ), 0, 80 ),
			'event'             => substr( $this->text( $record['event'] ?? 'request' ), 0, 80 ),
			'outcome'           => substr( $this->text( $record['outcome'] ?? '' ), 0, 40 ),
			'error_code'        => substr( sanitize_key( (string) ( $record['error_code'] ?? '' ) ), 0, 80 ),
			'operation_id'      => substr( $this->text( $record['operation_id'] ?? '' ), 0, 100 ),
			'reason'            => substr( $this->text( $record['reason'] ?? '' ), 0, 500 ),
		);

		$records = get_option( self::OPTION, array() );
		$records = is_array( $records ) ? array_values( $records ) : array();
		array_unshift( $records, $entry );
		$records = array_slice( $records, 0, self::MAX_RECORDS );

		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $records, '', false );
		} else {
			update_option( self::OPTION, $records, false );
		}

		return $entry;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function recent( int $limit = 50 ): array {
		$records = get_option( self::OPTION, array() );
		if ( ! is_array( $records ) ) {
			return array();
		}

		$limit = max( 1, min( self::MAX_RECORDS, $limit ) );

		return array_slice( array_values( $records ), 0, $limit );
	}

	public function clear(): void {
		delete_option( self::OPTION );
	}

	private function text( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return sanitize_text_field( (string) $value );
	}
}
