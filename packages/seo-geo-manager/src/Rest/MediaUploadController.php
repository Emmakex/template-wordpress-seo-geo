<?php
/**
 * REST endpoints for guarded direct image uploads.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\MediaUploadEngine;
use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class MediaUploadController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/media/uploads/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( self::class, 'can_upload' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media/uploads/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'apply' ),
				'permission_callback' => array( self::class, 'can_upload' ),
			)
		);
	}

	public static function can_upload(): bool {
		return current_user_can( 'upload_files' );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function preview( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$file    = self::file( $request );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$result = MediaUploadEngine::preview( $payload, $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['environment'] = EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function apply( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$file = self::file( $request );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$result = MediaUploadEngine::apply( $payload, $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result = self::bind_environment( $result );

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function payload( WP_REST_Request $request ): array {
		$payload = $request->get_body_params();
		if ( is_array( $payload ) && array() !== $payload ) {
			return $payload;
		}

		$json = $request->get_json_params();
		return is_array( $json ) ? $json : array();
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function file( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$file  = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : array();
		if ( array() === $file ) {
			return new WP_Error(
				'seo_geo_manager_media_upload_file_required',
				'Multipart field "file" is required for media upload operations.',
				array( 'status' => 400 )
			);
		}

		return $file;
	}

	/**
	 * Persist environment evidence onto the private operation record.
	 *
	 * @param array<string, mixed> $result Operation result.
	 * @return array<string, mixed>
	 */
	private static function bind_environment( array $result ): array {
		$environment           = EnvironmentPolicy::snapshot();
		$result['environment'] = $environment;
		$operation_id          = isset( $result['operation_id'] ) && is_string( $result['operation_id'] ) ? $result['operation_id'] : '';

		if ( '' !== $operation_id ) {
			$stored = OperationStore::get( $operation_id );
			if ( is_array( $stored ) && ! isset( $stored['environment'] ) ) {
				OperationStore::save( $operation_id, EnvironmentPolicy::bind_operation( $stored ) );
			}
		}

		return $result;
	}
}
