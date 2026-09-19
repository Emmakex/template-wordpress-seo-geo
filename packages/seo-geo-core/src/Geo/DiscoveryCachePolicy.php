<?php
/**
 * GEO discovery HTTP cache policy.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

/**
 * Sends safe public revalidation headers for dynamic discovery documents.
 */
final class DiscoveryCachePolicy {
	/**
	 * Revision authority.
	 *
	 * @var DiscoveryCacheRevision
	 */
	private DiscoveryCacheRevision $revision;

	/**
	 * Create the cache policy.
	 *
	 * @param DiscoveryCacheRevision $revision Revision authority.
	 */
	public function __construct( DiscoveryCacheRevision $revision ) {
		$this->revision = $revision;
	}

	/**
	 * Send revalidation headers and report whether a 304 response is complete.
	 *
	 * @param string   $surface     Stable surface key.
	 * @param int|null $resource_id Optional WordPress resource ID.
	 */
	public function send_revalidation_headers( string $surface, ?int $resource_id = null ): bool {
		if ( headers_sent() ) {
			return false;
		}

		$etag = $this->revision->etag( $surface, $resource_id );

		header( 'Cache-Control: public, no-cache, must-revalidate, max-age=0', true );
		header( 'ETag: ' . $etag, true );

		if ( ! $this->supports_conditional_request() || ! $this->etag_matches_request( $etag ) ) {
			return false;
		}

		status_header( 304 );

		return true;
	}

	/**
	 * Accept conditional revalidation only for safe read methods.
	 */
	private function supports_conditional_request(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		return in_array( $method, array( 'GET', 'HEAD' ), true );
	}

	/**
	 * Compare If-None-Match tokens with the current strong ETag.
	 *
	 * @param string $etag Current ETag.
	 */
	private function etag_matches_request( string $etag ): bool {
		if ( ! isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) || ! is_string( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			return false;
		}

		$header = wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] );
		if ( 2048 < strlen( $header ) ) {
			return false;
		}

		foreach ( explode( ',', $header ) as $candidate ) {
			$candidate = trim( $candidate );

			if ( '*' === $candidate ) {
				return true;
			}

			if ( str_starts_with( $candidate, 'W/' ) ) {
				$candidate = trim( substr( $candidate, 2 ) );
			}

			if ( $etag === $candidate ) {
				return true;
			}
		}

		return false;
	}
}
