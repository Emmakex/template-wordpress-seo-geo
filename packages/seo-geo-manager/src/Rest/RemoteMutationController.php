<?php
/**
 * Authenticated R3.2 remote mutation lifecycle for bounded semantic Manager operations.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\ChangeSetEngine;
use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Authenticator;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\MutationOperations;
use SeoGeo\Manager\Remote\PlanStore;
use SeoGeo\Manager\Remote\RequestGuard;
use SeoGeo\Manager\Remote\TokenService;
use SeoGeo\Manager\Support\ContentFingerprint;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class RemoteMutationController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	private static ?Credentials $credentials = null;
	private static ?Audit $audit = null;
	private static ?RequestGuard $guard = null;
	private static ?Authenticator $authenticator = null;
	private static ?PlanStore $plans = null;

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/remote/plans',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( self::class, 'create_plan' ),
				'permission_callback' => static fn ( WP_REST_Request $request ): bool|WP_Error => self::authenticator()->authorize( $request, 'content.write', 'remote-plan' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/remote/plans/(?P<plan_id>sgmp_[a-f0-9]{32})',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( self::class, 'show_plan' ),
				'permission_callback' => static fn ( WP_REST_Request $request ): bool|WP_Error => self::authenticator()->authorize( $request, 'operations.read', 'remote-plan-read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/remote/plans/(?P<plan_id>sgmp_[a-f0-9]{32})/apply',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( self::class, 'apply_plan' ),
				'permission_callback' => static fn ( WP_REST_Request $request ): bool|WP_Error => self::authenticator()->authorize( $request, 'operations.apply', 'remote-apply' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/remote/operations/(?P<operation_id>[a-f0-9\-]{36})',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( self::class, 'show_operation' ),
				'permission_callback' => static fn ( WP_REST_Request $request ): bool|WP_Error => self::authenticator()->authorize( $request, 'operations.read', 'remote-operation-read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/remote/operations/(?P<operation_id>[a-f0-9\-]{36})/verify',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( self::class, 'verify_operation' ),
				'permission_callback' => static fn ( WP_REST_Request $request ): bool|WP_Error => self::authenticator()->authorize( $request, 'operations.read', 'remote-verify' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/remote/operations/(?P<operation_id>[a-f0-9\-]{36})/rollback',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( self::class, 'rollback_operation' ),
				'permission_callback' => static fn ( WP_REST_Request $request ): bool|WP_Error => self::authenticator()->authorize( $request, 'operations.rollback', 'remote-rollback' ),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_plan( WP_REST_Request $request ) {
		$replay = self::idempotency_state( $request );
		if ( is_wp_error( $replay ) ) {
			return self::failure( $request, $replay );
		}
		if ( is_array( $replay ) ) {
			$plan_id = (string) ( $replay['result']['plan_id'] ?? '' );
			$plan = self::plans()->get( $plan_id );
			return is_wp_error( $plan ) ? self::failure( $request, $plan ) : self::response( $request, self::bounded_plan( $plan ), true, array( 'plan_id' => $plan_id ) );
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$operation = sanitize_key( (string) ( $params['operation'] ?? '' ) );
		if ( ! MutationOperations::supports( $operation ) ) {
			return self::failure(
				$request,
				new WP_Error(
					'seo_geo_manager_remote_operation_unsupported',
					'The requested remote mutation operation is not supported.',
					array(
						'status'               => 400,
						'supported_operations' => MutationOperations::supported(),
					)
				)
			);
		}

		$payload = isset( $params['payload'] ) && is_array( $params['payload'] ) ? $params['payload'] : array();
		$preview = MutationOperations::preview( $operation, $payload );
		if ( is_wp_error( $preview ) ) {
			return self::failure( $request, $preview );
		}

		$context = self::authenticator()->context( $request );
		$connection = is_array( $context['connection'] ?? null ) ? $context['connection'] : array();
		$plan = self::plans()->create(
			(string) ( $connection['connection_id'] ?? '' ),
			(int) ( $connection['wordpress_user_id'] ?? 0 ),
			$operation,
			$payload,
			$preview
		);
		if ( is_wp_error( $plan ) ) {
			return self::failure( $request, $plan );
		}

		$plan_id = (string) $plan['plan_id'];
		self::remember_request( $request, array( 'plan_id' => $plan_id ) );

		return self::response( $request, self::bounded_plan( $plan ), false, array( 'plan_id' => $plan_id ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function show_plan( WP_REST_Request $request ) {
		$plan = self::owned_plan( $request, (string) $request->get_param( 'plan_id' ) );
		if ( is_wp_error( $plan ) ) {
			return self::failure( $request, $plan );
		}

		return self::response( $request, self::bounded_plan( $plan ), false, array( 'plan_id' => (string) $plan['plan_id'] ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function apply_plan( WP_REST_Request $request ) {
		$replay = self::idempotency_state( $request );
		if ( is_wp_error( $replay ) ) {
			return self::failure( $request, $replay );
		}
		if ( is_array( $replay ) ) {
			$operation_id = (string) ( $replay['result']['operation_id'] ?? '' );
			$operation = self::owned_operation( $request, $operation_id );
			return is_wp_error( $operation ) ? self::failure( $request, $operation ) : self::response( $request, self::bounded_operation( $operation ), true, array( 'operation_id' => $operation_id ) );
		}

		$plan = self::owned_plan( $request, (string) $request->get_param( 'plan_id' ) );
		if ( is_wp_error( $plan ) ) {
			return self::failure( $request, $plan );
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		if ( true !== ( $params['confirm'] ?? false ) ) {
			return self::failure( $request, new WP_Error( 'seo_geo_manager_remote_confirmation_required', 'Remote plan apply requires confirm=true.', array( 'status' => 400 ) ) );
		}

		$status = (string) ( $plan['status'] ?? '' );
		if ( 'applied' === $status ) {
			$operation = self::owned_operation( $request, (string) ( $plan['operation_id'] ?? '' ) );
			if ( is_wp_error( $operation ) ) {
				return self::failure( $request, $operation );
			}
			self::remember_request( $request, array( 'operation_id' => (string) $plan['operation_id'] ) );
			return self::response( $request, self::bounded_operation( $operation ), true, array( 'plan_id' => (string) $plan['plan_id'], 'operation_id' => (string) $plan['operation_id'] ) );
		}
		if ( 'prepared' !== $status ) {
			return self::failure( $request, new WP_Error( 'seo_geo_manager_remote_plan_not_applicable', 'Remote Manager plan is not in an applicable state.', array( 'status' => 409, 'plan_status' => $status ) ) );
		}

		$environment_guard = EnvironmentPolicy::validate_payload( $params );
		if ( is_wp_error( $environment_guard ) ) {
			return self::failure( $request, $environment_guard );
		}
		$planned_environment = isset( $plan['environment'] ) && is_array( $plan['environment'] ) ? $plan['environment'] : array();
		$current_environment = EnvironmentPolicy::snapshot();
		$planned_fingerprint = (string) ( $planned_environment['fingerprint'] ?? '' );
		$current_fingerprint = (string) ( $current_environment['fingerprint'] ?? '' );
		if ( '' === $planned_fingerprint || ! hash_equals( $planned_fingerprint, $current_fingerprint ) ) {
			return self::failure( $request, new WP_Error( 'seo_geo_manager_remote_plan_environment_changed', 'The environment changed after the remote plan was prepared.', array( 'status' => 409 ) ) );
		}

		$payload = isset( $plan['payload'] ) && is_array( $plan['payload'] ) ? $plan['payload'] : array();
		$payload['idempotency_key'] = 'remote-plan:' . (string) $plan['plan_id'];
		$operation_type = isset( $plan['operation'] ) && is_string( $plan['operation'] ) ? $plan['operation'] : '';
		$result = MutationOperations::apply( $operation_type, $payload );
		if ( is_wp_error( $result ) ) {
			return self::failure( $request, $result, (string) $plan['plan_id'] );
		}

		$operation_id = isset( $result['operation_id'] ) && is_string( $result['operation_id'] ) ? $result['operation_id'] : '';
		$stored = OperationStore::get( $operation_id );
		if ( ! is_array( $stored ) ) {
			return self::failure( $request, new WP_Error( 'seo_geo_manager_remote_operation_missing', 'Applied Manager operation could not be reloaded.', array( 'status' => 500 ) ), (string) $plan['plan_id'], $operation_id );
		}

		if ( ! isset( $stored['environment'] ) ) {
			$stored = EnvironmentPolicy::bind_operation( $stored );
		}
		$stored['remote_connection_id'] = (string) $plan['connection_id'];
		$stored['remote_plan_id']       = (string) $plan['plan_id'];
		$stored['remote_actor_user_id'] = (int) $plan['wordpress_user_id'];
		$stored['remote_operation_type'] = $operation_type;
		if ( isset( $result['structured_model'] ) && is_array( $result['structured_model'] ) ) {
			$stored['structured_model'] = $result['structured_model'];
		}
		if ( ! OperationStore::save( $operation_id, $stored ) ) {
			return self::failure( $request, new WP_Error( 'seo_geo_manager_remote_operation_bind_failed', 'Applied Manager operation could not be bound to the remote connection.', array( 'status' => 500 ) ), (string) $plan['plan_id'], $operation_id );
		}

		$applied_plan = self::plans()->mark_applied( $plan, $operation_id );
		if ( is_wp_error( $applied_plan ) ) {
			return self::failure( $request, $applied_plan, (string) $plan['plan_id'], $operation_id );
		}

		self::remember_request( $request, array( 'operation_id' => $operation_id ) );

		return self::response( $request, self::bounded_operation( $stored ), false, array( 'plan_id' => (string) $plan['plan_id'], 'operation_id' => $operation_id ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function show_operation( WP_REST_Request $request ) {
		$operation = self::owned_operation( $request, (string) $request->get_param( 'operation_id' ) );
		if ( is_wp_error( $operation ) ) {
			return self::failure( $request, $operation );
		}

		return self::response( $request, self::bounded_operation( $operation ), false, array( 'operation_id' => (string) $operation['operation_id'] ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function verify_operation( WP_REST_Request $request ) {
		$operation = self::owned_operation( $request, (string) $request->get_param( 'operation_id' ) );
		if ( is_wp_error( $operation ) ) {
			return self::failure( $request, $operation );
		}

		$target_id = isset( $operation['target_id'] ) ? (int) $operation['target_id'] : 0;
		$post = get_post( $target_id );
		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $target_id ) ) {
			return self::failure( $request, new WP_Error( 'seo_geo_manager_remote_verify_target_unavailable', 'Remote operation target is unavailable to the execution identity.', array( 'status' => 403 ) ), '', (string) $operation['operation_id'] );
		}

		$expected = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		$current = ContentFingerprint::for_post( $post );
		if ( '' === $expected || ! hash_equals( $expected, $current ) ) {
			return self::failure( $request, new WP_Error( 'seo_geo_manager_remote_verification_stale', 'The resource no longer matches the applied remote operation.', array( 'status' => 409, 'operation_id' => (string) $operation['operation_id'] ) ), '', (string) $operation['operation_id'] );
		}

		$data = self::bounded_operation( $operation );
		$data['verified'] = true;
		$data['verified_at'] = gmdate( DATE_W3C );
		$data['current_fingerprint'] = $current;

		return self::response( $request, $data, false, array( 'operation_id' => (string) $operation['operation_id'] ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function rollback_operation( WP_REST_Request $request ) {
		$replay = self::idempotency_state( $request );
		if ( is_wp_error( $replay ) ) {
			return self::failure( $request, $replay );
		}
		if ( is_array( $replay ) ) {
			$operation_id = (string) ( $replay['result']['operation_id'] ?? '' );
			$operation = self::owned_operation( $request, $operation_id );
			return is_wp_error( $operation ) ? self::failure( $request, $operation ) : self::response( $request, self::bounded_operation( $operation ), true, array( 'operation_id' => $operation_id ) );
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		if ( true !== ( $params['confirm'] ?? false ) ) {
			return self::failure( $request, new WP_Error( 'seo_geo_manager_remote_confirmation_required', 'Remote rollback requires confirm=true.', array( 'status' => 400 ) ) );
		}
		$environment_guard = EnvironmentPolicy::validate_payload( $params );
		if ( is_wp_error( $environment_guard ) ) {
			return self::failure( $request, $environment_guard );
		}

		$operation_id = (string) $request->get_param( 'operation_id' );
		$operation = self::owned_operation( $request, $operation_id );
		if ( is_wp_error( $operation ) ) {
			return self::failure( $request, $operation );
		}
		$operation_environment = EnvironmentPolicy::validate_operation_environment( $operation );
		if ( is_wp_error( $operation_environment ) ) {
			return self::failure( $request, $operation_environment, '', $operation_id );
		}

		$result = ChangeSetEngine::rollback( $operation_id );
		if ( is_wp_error( $result ) ) {
			return self::failure( $request, $result, '', $operation_id );
		}
		self::remember_request( $request, array( 'operation_id' => $operation_id ) );

		return self::response( $request, self::bounded_operation( $result ), false, array( 'operation_id' => $operation_id ) );
	}

	/** @return array<string, mixed>|WP_Error */
	private static function owned_plan( WP_REST_Request $request, string $plan_id ) {
		$plan = self::plans()->get( $plan_id );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$connection_id = self::connection_id( $request );
		if ( '' === $connection_id || ! hash_equals( (string) ( $plan['connection_id'] ?? '' ), $connection_id ) ) {
			return new WP_Error( 'seo_geo_manager_remote_plan_forbidden', 'Remote Manager plan belongs to another connection.', array( 'status' => 403 ) );
		}

		return $plan;
	}

	/** @return array<string, mixed>|WP_Error */
	private static function owned_operation( WP_REST_Request $request, string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) ) {
			return new WP_Error( 'seo_geo_manager_operation_not_found', 'Manager operation not found.', array( 'status' => 404 ) );
		}
		$connection_id = self::connection_id( $request );
		$owner = isset( $operation['remote_connection_id'] ) && is_string( $operation['remote_connection_id'] ) ? $operation['remote_connection_id'] : '';
		if ( '' === $connection_id || '' === $owner || ! hash_equals( $owner, $connection_id ) ) {
			return new WP_Error( 'seo_geo_manager_remote_operation_forbidden', 'Remote Manager operation belongs to another connection or was not created remotely.', array( 'status' => 403 ) );
		}

		return $operation;
	}

	/** @return bool|array<string, mixed>|WP_Error */
	private static function idempotency_state( WP_REST_Request $request ) {
		$context = self::authenticator()->context( $request );
		$metadata = is_array( $context['metadata'] ?? null ) ? $context['metadata'] : array();
		$connection = is_array( $context['connection'] ?? null ) ? $context['connection'] : array();
		$connection_id = (string) ( $connection['connection_id'] ?? '' );
		$request_id = (string) ( $metadata['request_id'] ?? '' );

		return self::guard()->validate_idempotency( $connection_id, $request_id, self::guard()->request_fingerprint( $request ) );
	}

	/**
	 * @param array<string, mixed> $result Non-secret idempotency result metadata.
	 */
	private static function remember_request( WP_REST_Request $request, array $result ): void {
		$context = self::authenticator()->context( $request );
		$metadata = is_array( $context['metadata'] ?? null ) ? $context['metadata'] : array();
		$connection = is_array( $context['connection'] ?? null ) ? $context['connection'] : array();
		self::guard()->remember_request(
			(string) ( $connection['connection_id'] ?? '' ),
			(string) ( $metadata['request_id'] ?? '' ),
			self::guard()->request_fingerprint( $request ),
			$result
		);
	}

	private static function connection_id( WP_REST_Request $request ): string {
		$context = self::authenticator()->context( $request );
		$connection = is_array( $context['connection'] ?? null ) ? $context['connection'] : array();
		return (string) ( $connection['connection_id'] ?? '' );
	}

	/**
	 * @param array<string, mixed> $plan Remote plan record.
	 * @return array<string, mixed>
	 */
	private static function bounded_plan( array $plan ): array {
		return array(
			'plan_id' => (string) ( $plan['plan_id'] ?? '' ),
			'operation' => (string) ( $plan['operation'] ?? '' ),
			'status' => (string) ( $plan['status'] ?? '' ),
			'preview' => is_array( $plan['preview'] ?? null ) ? $plan['preview'] : array(),
			'environment' => is_array( $plan['environment'] ?? null ) ? $plan['environment'] : array(),
			'expires_at' => (string) ( $plan['expires_at'] ?? '' ),
			'operation_id' => (string) ( $plan['operation_id'] ?? '' ),
		);
	}

	/**
	 * @param array<string, mixed> $operation Private Manager operation record.
	 * @return array<string, mixed>
	 */
	private static function bounded_operation( array $operation ): array {
		$changes = isset( $operation['changes'] ) && is_array( $operation['changes'] ) ? array_keys( $operation['changes'] ) : array();
		return array(
			'operation_id' => (string) ( $operation['operation_id'] ?? '' ),
			'operation' => isset( $operation['remote_operation_type'] ) && is_string( $operation['remote_operation_type'] )
				? $operation['remote_operation_type']
				: 'content-change',
			'status' => (string) ( $operation['status'] ?? '' ),
			'target_id' => (int) ( $operation['target_id'] ?? 0 ),
			'target_type' => (string) ( $operation['target_type'] ?? '' ),
			'changed_fields' => array_values( array_filter( array_map( 'sanitize_key', $changes ) ) ),
			'before_fingerprint' => (string) ( $operation['before_fingerprint'] ?? '' ),
			'after_fingerprint' => (string) ( $operation['after_fingerprint'] ?? '' ),
			'verification' => is_array( $operation['verification'] ?? null ) ? $operation['verification'] : array(),
			'environment' => is_array( $operation['environment'] ?? null ) ? $operation['environment'] : array(),
			'remote_plan_id' => (string) ( $operation['remote_plan_id'] ?? '' ),
			'structured_model' => self::bounded_structured_model( $operation ),
			'created_at_gmt' => (string) ( $operation['created_at_gmt'] ?? '' ),
			'rolled_back_at_gmt' => (string) ( $operation['rolled_back_at_gmt'] ?? '' ),
		);
	}

	/**
	 * @param array<string, mixed> $operation Stored operation.
	 * @return array<string, mixed>
	 */
	private static function bounded_structured_model( array $operation ): array {
		$model = isset( $operation['structured_model'] ) && is_array( $operation['structured_model'] )
			? $operation['structured_model']
			: array();
		if ( array() === $model ) {
			return array();
		}

		return array(
			'preset'           => (string) ( $model['preset'] ?? '' ),
			'page_key'         => (string) ( $model['page_key'] ?? '' ),
			'model_id'         => (string) ( $model['model_id'] ?? '' ),
			'verified_groups'  => isset( $model['verified_groups'] ) && is_array( $model['verified_groups'] ) ? array_values( $model['verified_groups'] ) : array(),
			'layout_preserved' => true === ( $model['layout_preserved'] ?? false ),
		);
	}

	/**
	 * @param array<string, mixed>  $data       Bounded response data.
	 * @param array<string, scalar> $audit_meta Bounded audit metadata.
	 */
	private static function response( WP_REST_Request $request, array $data, bool $idempotent_replay, array $audit_meta = array() ): WP_REST_Response {
		$context = self::authenticator()->context( $request );
		$metadata = is_array( $context['metadata'] ?? null ) ? $context['metadata'] : array();
		self::authenticator()->record_success( $request, $audit_meta );

		return new WP_REST_Response(
			array(
				'ok' => true,
				'request_id' => (string) ( $metadata['request_id'] ?? '' ),
				'idempotent_replay' => $idempotent_replay,
				'data' => $data,
			),
			200
		);
	}

	private static function failure( WP_REST_Request $request, WP_Error $error, string $plan_id = '', string $operation_id = '' ): WP_Error {
		$context = self::authenticator()->context( $request );
		$metadata = is_array( $context['metadata'] ?? null ) ? $context['metadata'] : array();
		$connection = is_array( $context['connection'] ?? null ) ? $context['connection'] : array();
		self::audit_store()->record(
			array(
				'request_id' => (string) ( $metadata['request_id'] ?? '' ),
				'connection_id' => (string) ( $connection['connection_id'] ?? '' ),
				'wordpress_user_id' => (int) ( $connection['wordpress_user_id'] ?? 0 ),
				'method' => $request->get_method(),
				'route' => $request->get_route(),
				'scope' => (string) ( $context['scope'] ?? '' ),
				'event' => (string) ( $context['event'] ?? 'remote-mutation' ),
				'outcome' => 'rejected',
				'error_code' => $error->get_error_code(),
				'plan_id' => $plan_id,
				'operation_id' => $operation_id,
				'reason' => $error->get_error_message(),
			)
		);
		return $error;
	}

	private static function credentials(): Credentials {
		return self::$credentials ??= new Credentials();
	}

	private static function audit_store(): Audit {
		return self::$audit ??= new Audit();
	}

	private static function guard(): RequestGuard {
		return self::$guard ??= new RequestGuard();
	}

	private static function plans(): PlanStore {
		return self::$plans ??= new PlanStore();
	}

	private static function authenticator(): Authenticator {
		if ( null === self::$authenticator ) {
			$credentials = self::credentials();
			self::$authenticator = new Authenticator( $credentials, new TokenService( $credentials ), self::guard(), self::audit_store() );
		}
		return self::$authenticator;
	}
}
