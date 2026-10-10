<?php
/**
 * Guarded direct image upload engine for external orchestration.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Support\MediaFingerprint;
use WP_Error;
use WP_Post;

final class MediaUploadEngine {
	private const SCHEMA_VERSION         = 1;
	private const ADAPTER                = 'media-upload';
	private const MAX_UPLOAD_BYTES       = 20971520;
	private const MAX_WIDTH              = 12000;
	private const MAX_HEIGHT             = 12000;
	private const MAX_PIXELS             = 60000000;
	private const MAX_TITLE_BYTES        = 500;
	private const MAX_ALT_BYTES          = 1000;
	private const MAX_CAPTION_BYTES      = 4000;
	private const MAX_DESCRIPTION_BYTES  = 12000;
	private const MAX_IDEMPOTENCY_LENGTH = 128;

	/**
	 * Preview a direct image upload without moving or persisting the file.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @param array<string, mixed> $file Uploaded file params.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload, array $file ) {
		$prepared = self::prepare( $payload, $file, false );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'preview',
			'operation_type' => 'media-upload',
			'will_create'    => true,
			'resource'       => self::public_plan( $prepared ),
			'policy'         => self::policy( $prepared['upload_limit_bytes'] ),
		);
	}

	/**
	 * Persist one direct image upload with idempotency and verification.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @param array<string, mixed> $file Uploaded file params.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload, array $file ) {
		$prepared = self::prepare( $payload, $file, true );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$payload_hash = self::payload_hash( $prepared );
		$lookup       = OperationStore::lookup( $prepared['idempotency_key'], $payload_hash );
		if ( is_wp_error( $lookup ) ) {
			return $lookup;
		}
		if ( true === $lookup['existing'] ) {
			return self::replay_operation( $lookup['operation_id'] );
		}

		$operation_id = wp_generate_uuid4();
		$reservation  = OperationStore::reserve( $prepared['idempotency_key'], $payload_hash, $operation_id );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( true === $reservation['existing'] ) {
			return self::replay_operation( $reservation['operation_id'] );
		}

		self::load_media_dependencies();

		$sideload = wp_handle_sideload(
			array(
				'name'     => $prepared['file']['name'],
				'type'     => $prepared['file']['mime_type'],
				'tmp_name' => $prepared['file']['tmp_name'],
				'error'    => UPLOAD_ERR_OK,
				'size'     => $prepared['file']['bytes'],
			),
			array(
				'test_form' => false,
				'mimes'     => get_allowed_mime_types(),
			)
		);

		if ( isset( $sideload['error'] ) ) {
			$error_message = is_string( $sideload['error'] ) ? $sideload['error'] : 'WordPress rejected the image upload.';
			self::save_failed_operation( $operation_id, $prepared, $payload_hash, $error_message );
			return new WP_Error( 'seo_geo_manager_media_upload_move_failed', $error_message, array( 'status' => 400 ) );
		}

		$uploaded_file = isset( $sideload['file'] ) && is_string( $sideload['file'] ) ? $sideload['file'] : '';
		$uploaded_url  = isset( $sideload['url'] ) && is_string( $sideload['url'] ) ? $sideload['url'] : '';
		$uploaded_type = isset( $sideload['type'] ) && is_string( $sideload['type'] ) ? $sideload['type'] : '';
		$uploaded_hash = '' !== $uploaded_file && is_readable( $uploaded_file ) ? hash_file( 'sha256', $uploaded_file ) : false;

		if (
			'' === $uploaded_file
			|| '' === $uploaded_url
			|| '' === $uploaded_type
			|| ! is_string( $uploaded_hash )
			|| ! hash_equals( $prepared['file']['sha256'], $uploaded_hash )
			|| $prepared['file']['mime_type'] !== $uploaded_type
		) {
			self::delete_uploaded_file( $uploaded_file );
			self::save_failed_operation( $operation_id, $prepared, $payload_hash, 'Uploaded file verification failed before attachment creation.' );
			return new WP_Error(
				'seo_geo_manager_media_upload_binary_verify_failed',
				'Uploaded image bytes or MIME type changed before attachment creation.',
				array( 'status' => 409 )
			);
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $uploaded_type,
				'guid'           => $uploaded_url,
				'post_parent'    => $prepared['parent_id'],
				'post_title'     => $prepared['title'],
				'post_excerpt'   => $prepared['caption'],
				'post_content'   => $prepared['description'],
				'post_status'    => 'inherit',
				'post_author'    => get_current_user_id(),
			),
			$uploaded_file,
			$prepared['parent_id'],
			true
		);

		if ( is_wp_error( $attachment_id ) ) {
			self::delete_uploaded_file( $uploaded_file );
			self::save_failed_operation( $operation_id, $prepared, $payload_hash, $attachment_id->get_error_message() );
			return $attachment_id;
		}

		$attachment_id = (int) $attachment_id;
		$metadata      = wp_generate_attachment_metadata( $attachment_id, $uploaded_file );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
		if ( $prepared['alt_provided'] ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $prepared['alt'] );
		}

		$attachment = get_post( $attachment_id );
		$verification = self::verify_created_attachment( $attachment, $prepared );
		if ( is_wp_error( $verification ) ) {
			wp_delete_attachment( $attachment_id, true );
			self::save_failed_operation( $operation_id, $prepared, $payload_hash, $verification->get_error_message() );
			return $verification;
		}

		$stored_file = get_attached_file( $attachment_id, true );
		$stored_file = is_string( $stored_file ) ? $stored_file : '';
		$stored_size = '' !== $stored_file && is_file( $stored_file ) ? filesize( $stored_file ) : false;
		$url         = wp_get_attachment_url( $attachment_id );
		$url         = is_string( $url ) ? $url : '';

		$operation = array(
			'operation_id'       => $operation_id,
			'operation_type'     => 'media-upload',
			'adapter'            => self::ADAPTER,
			'schema_version'     => self::SCHEMA_VERSION,
			'status'             => 'created',
			'target_id'          => $attachment_id,
			'target_type'        => 'attachment',
			'idempotency_key'    => $prepared['idempotency_key'],
			'payload_hash'       => $payload_hash,
			'after_fingerprint'  => MediaFingerprint::for_attachment( $attachment ),
			'changes'            => self::change_markers( $prepared ),
			'file'               => array(
				'name'       => wp_basename( $stored_file ),
				'mime_type'  => (string) $attachment->post_mime_type,
				'bytes'      => is_int( $stored_size ) ? $stored_size : 0,
				'sha256'     => $prepared['file']['sha256'],
				'dimensions' => array(
					'width'  => $prepared['file']['width'],
					'height' => $prepared['file']['height'],
				),
				'url'        => $url,
			),
			'policy'             => self::policy( $prepared['upload_limit_bytes'] ),
			'created_at_gmt'     => gmdate( 'c' ),
			'idempotent_replay'  => false,
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			wp_delete_attachment( $attachment_id, true );
			return new WP_Error(
				'seo_geo_manager_media_upload_operation_store_failed',
				'The image was created but its operation record could not be persisted; the new attachment was removed.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @param array<string, mixed> $file Uploaded file params.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function prepare( array $payload, array $file, bool $for_apply ) {
		if ( self::has_remote_source_fields( $payload ) ) {
			return new WP_Error(
				'seo_geo_manager_media_upload_remote_fetch_unsupported',
				'Remote URL fetching is not supported. Send the image bytes directly as multipart file data.',
				array( 'status' => 400 )
			);
		}

		$schema_version = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION;
		$title          = isset( $payload['title'] ) && is_string( $payload['title'] ) ? sanitize_text_field( $payload['title'] ) : '';
		$parent_id      = isset( $payload['parent_id'] ) ? absint( $payload['parent_id'] ) : 0;
		$alt_provided   = array_key_exists( 'alt', $payload ) && is_string( $payload['alt'] );
		$caption_set    = array_key_exists( 'caption', $payload ) && is_string( $payload['caption'] );
		$description_set = array_key_exists( 'description', $payload ) && is_string( $payload['description'] );
		$alt            = $alt_provided ? sanitize_text_field( $payload['alt'] ) : '';
		$caption        = $caption_set ? sanitize_textarea_field( $payload['caption'] ) : '';
		$description    = $description_set ? wp_kses_post( $payload['description'] ) : '';

		if ( self::SCHEMA_VERSION !== $schema_version ) {
			return new WP_Error( 'seo_geo_manager_media_upload_schema_invalid', 'Unsupported media upload schema version.', array( 'status' => 400 ) );
		}
		if ( '' === trim( $title ) || self::MAX_TITLE_BYTES < strlen( $title ) ) {
			return new WP_Error( 'seo_geo_manager_media_upload_title_invalid', 'Media upload title is required and must be at most 500 bytes.', array( 'status' => 400 ) );
		}
		if ( self::MAX_ALT_BYTES < strlen( $alt ) || self::MAX_CAPTION_BYTES < strlen( $caption ) || self::MAX_DESCRIPTION_BYTES < strlen( $description ) ) {
			return new WP_Error( 'seo_geo_manager_media_upload_metadata_too_large', 'Media upload metadata exceeds the bounded field limits.', array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot upload media.', array( 'status' => 403 ) );
		}
		if ( 0 < $parent_id ) {
			$parent = get_post( $parent_id );
			if ( ! $parent instanceof WP_Post ) {
				return new WP_Error( 'seo_geo_manager_media_upload_parent_missing', 'Requested media parent was not found.', array( 'status' => 404 ) );
			}
			if ( ! current_user_can( 'edit_post', $parent_id ) ) {
				return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot attach media to this resource.', array( 'status' => 403 ) );
			}
		}

		$file_info = self::inspect_file( $file );
		if ( is_wp_error( $file_info ) ) {
			return $file_info;
		}

		$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		$expected_hash   = isset( $payload['expected_file_sha256'] ) && is_string( $payload['expected_file_sha256'] ) ? strtolower( trim( $payload['expected_file_sha256'] ) ) : '';
		if ( $for_apply ) {
			if ( '' === $idempotency_key || self::MAX_IDEMPOTENCY_LENGTH < strlen( $idempotency_key ) ) {
				return new WP_Error( 'seo_geo_manager_media_upload_idempotency_required', 'Media upload Apply requires an idempotency key of at most 128 characters.', array( 'status' => 400 ) );
			}
			if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) || ! hash_equals( $file_info['sha256'], $expected_hash ) ) {
				return new WP_Error( 'seo_geo_manager_media_upload_hash_mismatch', 'Uploaded image does not match the SHA-256 returned by Preview.', array( 'status' => 409 ) );
			}
			if ( true !== self::truthy( $payload['allow_public_media'] ?? false ) ) {
				return new WP_Error( 'seo_geo_manager_media_upload_public_approval_required', 'Creating public media requires explicit approval after Preview.', array( 'status' => 409 ) );
			}
		}

		return array(
			'schema_version'     => self::SCHEMA_VERSION,
			'title'              => $title,
			'alt'                => $alt,
			'alt_provided'       => $alt_provided,
			'caption'            => $caption,
			'caption_provided'   => $caption_set,
			'description'        => $description,
			'description_provided' => $description_set,
			'parent_id'          => $parent_id,
			'file'               => $file_info,
			'upload_limit_bytes' => self::upload_limit_bytes(),
			'idempotency_key'    => $idempotency_key,
			'allow_public_media' => true === self::truthy( $payload['allow_public_media'] ?? false ),
		);
	}

	/**
	 * @param array<string, mixed> $file Uploaded file params.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function inspect_file( array $file ) {
		$name     = isset( $file['name'] ) && is_string( $file['name'] ) ? sanitize_file_name( wp_basename( $file['name'] ) ) : '';
		$tmp_name = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		$error    = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error || '' === $name || '' === $tmp_name || ! is_file( $tmp_name ) || ! is_readable( $tmp_name ) ) {
			return new WP_Error( 'seo_geo_manager_media_upload_file_invalid', 'A readable multipart image file is required.', array( 'status' => 400 ) );
		}

		$bytes = filesize( $tmp_name );
		$limit = self::upload_limit_bytes();
		if ( ! is_int( $bytes ) || 1 > $bytes || $limit < $bytes ) {
			return new WP_Error( 'seo_geo_manager_media_upload_size_invalid', 'Image file size is empty or exceeds the upload limit.', array( 'status' => 413 ) );
		}

		$sha256 = hash_file( 'sha256', $tmp_name );
		if ( ! is_string( $sha256 ) ) {
			return new WP_Error( 'seo_geo_manager_media_upload_hash_failed', 'Could not hash the uploaded image.', array( 'status' => 400 ) );
		}

		$checked       = wp_check_filetype_and_ext( $tmp_name, $name, get_allowed_mime_types() );
		$extension     = isset( $checked['ext'] ) && is_string( $checked['ext'] ) ? strtolower( $checked['ext'] ) : '';
		$mime_type     = isset( $checked['type'] ) && is_string( $checked['type'] ) ? strtolower( $checked['type'] ) : '';
		$proper_name   = isset( $checked['proper_filename'] ) && is_string( $checked['proper_filename'] ) ? sanitize_file_name( $checked['proper_filename'] ) : '';
		if ( '' === $extension || '' === $mime_type || ! str_starts_with( $mime_type, 'image/' ) ) {
			return new WP_Error( 'seo_geo_manager_media_upload_type_invalid', 'Only WordPress-allowed image file types are accepted.', array( 'status' => 415 ) );
		}
		if ( '' !== $proper_name && $proper_name !== $name ) {
			return new WP_Error( 'seo_geo_manager_media_upload_filename_mime_mismatch', 'Image filename extension does not match its detected type.', array( 'status' => 415 ) );
		}

		$image_size = wp_getimagesize( $tmp_name );
		$width      = is_array( $image_size ) && isset( $image_size[0] ) ? (int) $image_size[0] : 0;
		$height     = is_array( $image_size ) && isset( $image_size[1] ) ? (int) $image_size[1] : 0;
		$pixels     = $width * $height;
		if ( 1 > $width || 1 > $height || self::MAX_WIDTH < $width || self::MAX_HEIGHT < $height || self::MAX_PIXELS < $pixels ) {
			return new WP_Error( 'seo_geo_manager_media_upload_dimensions_invalid', 'Image dimensions are invalid or exceed the bounded pixel limits.', array( 'status' => 413 ) );
		}

		return array(
			'name'      => $name,
			'tmp_name'  => $tmp_name,
			'bytes'     => $bytes,
			'sha256'    => $sha256,
			'extension' => $extension,
			'mime_type' => $mime_type,
			'width'     => $width,
			'height'    => $height,
		);
	}

	/**
	 * @param WP_Post|mixed       $attachment Created attachment.
	 * @param array<string, mixed> $prepared Prepared upload.
	 * @return true|WP_Error
	 */
	private static function verify_created_attachment( $attachment, array $prepared ) {
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'seo_geo_manager_media_upload_reload_failed', 'Created image attachment could not be reloaded.', array( 'status' => 500 ) );
		}
		if ( $prepared['title'] !== (string) $attachment->post_title || $prepared['caption'] !== (string) $attachment->post_excerpt || $prepared['description'] !== (string) $attachment->post_content || $prepared['parent_id'] !== (int) $attachment->post_parent ) {
			return new WP_Error( 'seo_geo_manager_media_upload_metadata_verify_failed', 'Created image attachment metadata does not match the requested values.', array( 'status' => 409 ) );
		}
		if ( $prepared['file']['mime_type'] !== strtolower( (string) $attachment->post_mime_type ) ) {
			return new WP_Error( 'seo_geo_manager_media_upload_mime_verify_failed', 'Created image MIME type does not match Preview.', array( 'status' => 409 ) );
		}
		if ( $prepared['alt_provided'] ) {
			$alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
			$alt = is_string( $alt ) ? $alt : '';
			if ( $prepared['alt'] !== $alt ) {
				return new WP_Error( 'seo_geo_manager_media_upload_alt_verify_failed', 'Created image alt text does not match the requested value.', array( 'status' => 409 ) );
			}
		}

		$attached_file = get_attached_file( $attachment->ID, true );
		$attached_file = is_string( $attached_file ) ? $attached_file : '';
		$stored_hash   = '' !== $attached_file && is_readable( $attached_file ) ? hash_file( 'sha256', $attached_file ) : false;
		if ( ! is_string( $stored_hash ) || ! hash_equals( $prepared['file']['sha256'], $stored_hash ) ) {
			return new WP_Error( 'seo_geo_manager_media_upload_binary_verify_failed', 'Stored image bytes do not match the previewed upload.', array( 'status' => 409 ) );
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $prepared Prepared upload.
	 * @return array<string, mixed>
	 */
	private static function public_plan( array $prepared ): array {
		return array(
			'title'       => $prepared['title'],
			'alt'         => $prepared['alt_provided'] ? $prepared['alt'] : null,
			'caption'     => $prepared['caption_provided'] ? $prepared['caption'] : null,
			'description' => $prepared['description_provided'] ? $prepared['description'] : null,
			'parent_id'   => $prepared['parent_id'],
			'file'        => self::public_file( $prepared['file'] ),
		);
	}

	/**
	 * @param array<string, mixed> $file Prepared file.
	 * @return array<string, mixed>
	 */
	private static function public_file( array $file ): array {
		return array(
			'name'       => $file['name'],
			'bytes'      => $file['bytes'],
			'sha256'     => $file['sha256'],
			'extension'  => $file['extension'],
			'mime_type'  => $file['mime_type'],
			'dimensions' => array(
				'width'  => $file['width'],
				'height' => $file['height'],
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function policy( int $upload_limit_bytes ): array {
		return array(
			'direct_upload_only'                    => true,
			'remote_fetch_supported'                => false,
			'image_only'                            => true,
			'public_media_approval_required'        => true,
			'idempotent_apply'                      => true,
			'rollback_supported'                    => false,
			'existing_binary_replacement_supported' => false,
			'max_upload_bytes'                      => $upload_limit_bytes,
			'max_width'                             => self::MAX_WIDTH,
			'max_height'                            => self::MAX_HEIGHT,
			'max_pixels'                            => self::MAX_PIXELS,
		);
	}

	/**
	 * @param array<string, mixed> $prepared Prepared upload.
	 * @return array<string, bool>
	 */
	private static function change_markers( array $prepared ): array {
		$changes = array(
			'file'      => true,
			'title'     => true,
			'parent_id' => true,
		);
		if ( $prepared['alt_provided'] ) {
			$changes['alt'] = true;
		}
		if ( $prepared['caption_provided'] ) {
			$changes['caption'] = true;
		}
		if ( $prepared['description_provided'] ) {
			$changes['description'] = true;
		}
		return $changes;
	}

	/**
	 * @param array<string, mixed> $prepared Prepared upload.
	 */
	private static function payload_hash( array $prepared ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'schema_version' => self::SCHEMA_VERSION,
					'file_sha256'    => $prepared['file']['sha256'],
					'file_name'      => $prepared['file']['name'],
					'title'          => $prepared['title'],
					'alt'            => $prepared['alt_provided'] ? $prepared['alt'] : null,
					'caption'        => $prepared['caption_provided'] ? $prepared['caption'] : null,
					'description'    => $prepared['description_provided'] ? $prepared['description'] : null,
					'parent_id'      => $prepared['parent_id'],
					'allow_public_media' => $prepared['allow_public_media'],
				)
			)
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function replay_operation( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_media_upload_idempotency_unavailable', 'Idempotency key points to an incompatible operation.', array( 'status' => 409 ) );
		}
		if ( 'failed' === ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_media_upload_previous_attempt_failed', 'This idempotency key belongs to a failed media upload attempt.', array( 'status' => 409 ) );
		}
		$operation['idempotent_replay'] = true;
		return $operation;
	}

	/**
	 * @param array<string, mixed> $prepared Prepared upload.
	 */
	private static function save_failed_operation( string $operation_id, array $prepared, string $payload_hash, string $message ): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'      => $operation_id,
				'operation_type'    => 'media-upload',
				'adapter'           => self::ADAPTER,
				'schema_version'    => self::SCHEMA_VERSION,
				'status'            => 'failed',
				'target_id'         => 0,
				'target_type'       => 'attachment',
				'idempotency_key'   => $prepared['idempotency_key'],
				'payload_hash'      => $payload_hash,
				'changes'           => self::change_markers( $prepared ),
				'error'             => sanitize_text_field( $message ),
				'created_at_gmt'    => gmdate( 'c' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 */
	private static function has_remote_source_fields( array $payload ): bool {
		foreach ( array( 'url', 'remote_url', 'source_url', 'fetch_url' ) as $field ) {
			if ( array_key_exists( $field, $payload ) ) {
				return true;
			}
		}
		return false;
	}

	private static function upload_limit_bytes(): int {
		$wordpress_limit = wp_max_upload_size();
		if ( 0 < $wordpress_limit ) {
			return min( self::MAX_UPLOAD_BYTES, $wordpress_limit );
		}
		return self::MAX_UPLOAD_BYTES;
	}

	/**
	 * @param mixed $value Value.
	 */
	private static function truthy( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === strtolower( (string) $value );
	}

	private static function load_media_dependencies(): void {
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	private static function delete_uploaded_file( string $path ): void {
		if ( '' !== $path && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
