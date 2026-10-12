<?php
/**
 * Bounded same-origin frontend verification for Manager content operations.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

use WP_Post;

final class RenderedResourceVerifier {
	private const MAX_RESPONSE_BYTES = 2097152;

	/**
	 * Describe whether a WordPress resource can be verified on its public route.
	 *
	 * @return array<string, mixed>
	 */
	public static function capability( WP_Post $post ): array {
		$permalink = get_permalink( $post );
		$url       = is_string( $permalink ) ? $permalink : '';
		$reason    = '';

		if ( 'publish' !== $post->post_status ) {
			$reason = 'target-not-published';
		} elseif ( '' === $url ) {
			$reason = 'permalink-unavailable';
		} elseif ( ! self::same_site_url( $url ) ) {
			$reason = 'permalink-outside-current-site';
		}

		return array(
			'applicable' => '' === $reason,
			'url'        => $url,
			'reason'     => $reason,
		);
	}

	/**
	 * Verify that the exact current public permalink serves a bounded HTML document.
	 *
	 * No response body is persisted. Only compact evidence and a SHA-256 digest are
	 * returned to the operation record.
	 *
	 * @return array<string, mixed>
	 */
	public static function verify( WP_Post $post ): array {
		$capability = self::capability( $post );
		$url        = isset( $capability['url'] ) && is_string( $capability['url'] ) ? $capability['url'] : '';
		$checked_at = gmdate( 'c' );

		if ( true !== ( $capability['applicable'] ?? false ) ) {
			return self::failed(
				$url,
				$checked_at,
				'rendered-verification-unavailable',
				isset( $capability['reason'] ) && is_string( $capability['reason'] ) ? $capability['reason'] : 'unavailable'
			);
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_RESPONSE_BYTES,
				'headers'             => array(
					'User-Agent'    => 'SEO-GEO-Manager/' . ( defined( 'SEO_GEO_MANAGER_VERSION' ) ? SEO_GEO_MANAGER_VERSION : 'dev' ),
					'Cache-Control' => 'no-cache',
					'Pragma'        => 'no-cache',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::failed( $url, $checked_at, $response->get_error_code(), $response->get_error_message() );
		}

		$http_status = (int) wp_remote_retrieve_response_code( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$content_type = is_string( $content_type ) ? strtolower( trim( $content_type ) ) : '';
		$body         = (string) wp_remote_retrieve_body( $response );
		$body_bytes   = strlen( $body );

		if ( 200 > $http_status || 300 <= $http_status ) {
			return self::failed( $url, $checked_at, 'unexpected-http-status', 'HTTP ' . $http_status, $http_status, $content_type, $body_bytes );
		}

		if ( '' === $content_type || ( ! str_starts_with( $content_type, 'text/html' ) && ! str_starts_with( $content_type, 'application/xhtml+xml' ) ) ) {
			return self::failed( $url, $checked_at, 'unexpected-content-type', '' !== $content_type ? $content_type : 'missing', $http_status, $content_type, $body_bytes );
		}

		if ( 0 === $body_bytes || false === stripos( $body, '<html' ) ) {
			return self::failed( $url, $checked_at, 'rendered-html-missing', 'The public response did not contain an HTML document.', $http_status, $content_type, $body_bytes );
		}

		return array(
			'requested'      => true,
			'applicable'     => true,
			'status'         => 'passed',
			'url'            => $url,
			'http_status'    => $http_status,
			'content_type'   => $content_type,
			'body_bytes'     => $body_bytes,
			'body_sha256'    => hash( 'sha256', $body ),
			'response_limit' => self::MAX_RESPONSE_BYTES,
			'checked_at_gmt' => $checked_at,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function failed( string $url, string $checked_at, string $error_code, string $message, int $http_status = 0, string $content_type = '', int $body_bytes = 0 ): array {
		return array(
			'requested'      => true,
			'applicable'     => '' !== $url,
			'status'         => 'failed',
			'url'            => $url,
			'http_status'    => $http_status,
			'content_type'   => $content_type,
			'body_bytes'     => max( 0, $body_bytes ),
			'body_sha256'    => '',
			'response_limit' => self::MAX_RESPONSE_BYTES,
			'checked_at_gmt' => $checked_at,
			'error_code'     => sanitize_key( $error_code ),
			'error'          => sanitize_text_field( $message ),
		);
	}

	private static function same_site_url( string $url ): bool {
		$home = home_url( '/' );
		foreach ( array( $home, $url ) as $candidate ) {
			$scheme = strtolower( (string) wp_parse_url( $candidate, PHP_URL_SCHEME ) );
			$host   = strtolower( (string) wp_parse_url( $candidate, PHP_URL_HOST ) );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
				return false;
			}
		}

		$home_scheme = strtolower( (string) wp_parse_url( $home, PHP_URL_SCHEME ) );
		$url_scheme  = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$home_host   = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$url_host    = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$home_port   = self::normalized_port( $home );
		$url_port    = self::normalized_port( $url );

		if ( $home_scheme !== $url_scheme || $home_host !== $url_host || $home_port !== $url_port ) {
			return false;
		}

		$home_path = self::normalize_path( (string) wp_parse_url( $home, PHP_URL_PATH ) );
		$url_path  = self::normalize_path( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '/' === $home_path ) {
			return true;
		}

		return $url_path === $home_path || str_starts_with( $url_path, trailingslashit( $home_path ) );
	}

	private static function normalized_port( string $url ): int {
		$port = wp_parse_url( $url, PHP_URL_PORT );
		if ( is_int( $port ) ) {
			return $port;
		}

		return 'https' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ? 443 : 80;
	}

	private static function normalize_path( string $path ): string {
		$path = '/' . trim( preg_replace( '#/+#', '/', $path ) ?? '/', '/' );

		return '/' === $path ? '/' : untrailingslashit( $path );
	}
}
