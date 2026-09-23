<?php
/**
 * WordPress HTTP transport for migration snapshots.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Http;

/**
 * Uses the WordPress HTTP API without cookies, credentials or redirect following.
 */
final class WordPressHttpClient implements HttpClientInterface {
	/**
	 * Fetch one public URL.
	 *
	 * @return array{
	 *     status:int,
	 *     body:string,
	 *     headers:array{content_type:string,location:string,x_robots_tag:string},
	 *     error:string|null
	 * }
	 */
	public function get( string $url ): array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array(
					'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.8,*/*;q=0.5',
					'User-Agent' => 'SEO-GEO-Migration-Bridge/' . SEO_GEO_MIGRATION_BRIDGE_VERSION,
				),
				'cookies'     => array(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 0,
				'body'    => '',
				'headers' => array(
					'content_type'  => '',
					'location'      => '',
					'x_robots_tag'  => '',
				),
				'error'   => $response->get_error_code(),
			);
		}

		return array(
			'status'  => (int) wp_remote_retrieve_response_code( $response ),
			'body'    => (string) wp_remote_retrieve_body( $response ),
			'headers' => array(
				'content_type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
				'location'     => (string) wp_remote_retrieve_header( $response, 'location' ),
				'x_robots_tag' => (string) wp_remote_retrieve_header( $response, 'x-robots-tag' ),
			),
			'error'   => null,
		);
	}
}
