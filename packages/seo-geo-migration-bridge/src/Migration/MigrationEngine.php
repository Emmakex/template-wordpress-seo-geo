<?php
/**
 * Controlled Phase 8E sandbox migration engine.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Migration;

use RuntimeException;
use SeoGeo\MigrationBridge\DependencyGraphBuilder;
use SeoGeo\MigrationBridge\Sandbox\SandboxMigrationLab;
use SeoGeo\MigrationBridge\SiteAnalyzer;
use WP_Error;
use WP_Post;

/**
 * Executes explicitly authorized builder migrations inside an accepted sandbox.
 */
final class MigrationEngine {
	/**
	 * Private rollback source retained on the migrated resource.
	 */
	public const BACKUP_META = '_seo_geo_migration_backup_v1';

	/**
	 * Safe migration marker retained on the migrated resource.
	 */
	public const STATE_META = '_seo_geo_migration_state_v1';

	/**
	 * Registered builder adapters.
	 *
	 * @var array<string, BuilderMigrationAdapterInterface>
	 */
	private array $adapters;

	/**
	 * Destination preset resolver.
	 *
	 * @var MigrationPresetResolver
	 */
	private MigrationPresetResolver $preset_resolver;

	/**
	 * Construct the engine.
	 *
	 * @param array<int, BuilderMigrationAdapterInterface>|null $adapters Optional adapter override.
	 */
	public function __construct( ?array $adapters = null ) {
		$adapters ??= array(
			new ElementorMigrationAdapter(),
			new DiviMigrationAdapter(),
		);

		$this->adapters = array();
		foreach ( $adapters as $adapter ) {
			$this->adapters[ $adapter->id() ] = $adapter;
		}

		$this->preset_resolver = new MigrationPresetResolver();
	}

	/**
	 * Build a non-mutating migration plan.
	 *
	 * @return array<string,mixed>
	 */
	public function plan( int $object_id, string $adapter_id, ?string $preset_id = null ): array {
		$post       = get_post( $object_id );
		$adapter_id = sanitize_key( $adapter_id );
		$adapter    = $this->adapters[ $adapter_id ] ?? null;
		$preset     = $this->preset_resolver->resolve( $preset_id );

		$blockers = array();
		if ( ! $post instanceof WP_Post ) {
			$blockers[] = 'resource-not-found';
		}
		if ( ! $adapter instanceof BuilderMigrationAdapterInterface ) {
			$blockers[] = 'unsupported-adapter';
		}
		if ( ! $preset['valid'] ) {
			$blockers[] = 'preset:' . $preset['reason'];
		}

		$analysis = ( new SiteAnalyzer() )->analyze();
		$graph    = ( new DependencyGraphBuilder() )->build( $analysis );
		$lab      = ( new SandboxMigrationLab() )->report( $analysis, $graph );

		foreach ( $lab['blockers'] as $lab_blocker ) {
			if ( is_string( $lab_blocker ) ) {
				$blockers[] = 'sandbox:' . $lab_blocker;
			}
		}

		$adapter_plan = array(
			'supported'  => false,
			'source'     => $adapter_id,
			'target'     => 'wordpress-core-blocks',
			'operations' => array(),
			'blockers'   => array(),
			'warnings'   => array(),
			'media_ids'  => array(),
		);

		if ( $post instanceof WP_Post && $adapter instanceof BuilderMigrationAdapterInterface ) {
			$adapter_plan = $adapter->plan( $post );
			foreach ( $adapter_plan['blockers'] as $adapter_blocker ) {
				$blockers[] = 'adapter:' . $adapter_blocker;
			}

			if ( metadata_exists( 'post', $post->ID, self::BACKUP_META ) ) {
				$blockers[] = 'resource-already-has-migration-backup';
			}
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		$preservation = null;
		if ( $post instanceof WP_Post ) {
			$permalink    = get_permalink( $post );
			$permalink    = is_string( $permalink ) ? $permalink : '';
			$featured_id  = get_post_thumbnail_id( $post );
			$preservation = array(
				'object_id'         => (int) $post->ID,
				'post_type'         => (string) $post->post_type,
				'post_status'       => (string) $post->post_status,
				'post_name'         => (string) $post->post_name,
				'parent_id'         => (int) $post->post_parent,
				'permalink_path'    => $this->url_path( $permalink ),
				'featured_media_id' => 0 < $featured_id ? $featured_id : null,
				'adapter_media_ids' => $adapter_plan['media_ids'],
			);
		}

		return array(
			'schema_version' => 1,
			'mode'           => 'migration-plan',
			'ready'          => array() === $blockers && true === $adapter_plan['supported'] && true === $lab['ready'],
			'object_id'      => $object_id,
			'adapter'        => $adapter_id,
			'preset'         => $preset,
			'preservation'   => $preservation,
			'adapter_plan'   => $adapter_plan,
			'business_systems' => $this->preserved_business_systems( $graph ),
			'blockers'       => $blockers,
			'authorization'  => array(
				'requires_manage_options' => true,
				'requires_edit_post'      => true,
				'requires_nonce'          => true,
				'requires_confirmation'   => true,
			),
			'safety'         => array(
				'sandbox_only'               => true,
				'plugin_mutation_allowed'    => false,
				'theme_mutation_allowed'     => false,
				'url_change_allowed'         => false,
				'object_id_change_allowed'   => false,
				'unsupported_content_dropped' => false,
			),
		);
	}

	/**
	 * Execute one explicitly confirmed migration.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute(
		int $object_id,
		string $adapter_id,
		string $nonce,
		?string $preset_id = null,
		bool $confirmed = false
	): array|WP_Error {
		$adapter_id = sanitize_key( $adapter_id );

		if ( ! $confirmed ) {
			return new WP_Error( 'seo_geo_migration_confirmation_required', 'Explicit migration confirmation is required.' );
		}
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $object_id ) ) {
			return new WP_Error( 'seo_geo_migration_forbidden', 'Administrator migration capability is required.' );
		}
		if ( ! wp_verify_nonce( $nonce, self::nonce_action( $object_id, $adapter_id ) ) ) {
			return new WP_Error( 'seo_geo_migration_invalid_nonce', 'Migration nonce is invalid or expired.' );
		}

		$plan = $this->plan( $object_id, $adapter_id, $preset_id );
		if ( true !== $plan['ready'] ) {
			return new WP_Error(
				'seo_geo_migration_not_ready',
				'Migration plan is blocked: ' . implode( ', ', $plan['blockers'] )
			);
		}

		$post    = get_post( $object_id );
		$adapter = $this->adapters[ $adapter_id ] ?? null;
		if ( ! $post instanceof WP_Post || ! $adapter instanceof BuilderMigrationAdapterInterface ) {
			return new WP_Error( 'seo_geo_migration_resource_unavailable', 'Migration resource or adapter became unavailable.' );
		}

		try {
			$payload = $adapter->transform( $post );
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'seo_geo_migration_transform_failed', $exception->getMessage() );
		}

		$before_permalink = get_permalink( $post );
		$before_permalink = is_string( $before_permalink ) ? $before_permalink : '';
		$before = array(
			'post_content' => (string) $post->post_content,
			'post_name'    => (string) $post->post_name,
			'permalink'    => $before_permalink,
			'meta'         => $this->capture_meta( $post->ID, $payload ),
		);

		$backup = array(
			'schema_version' => 1,
			'created_at'     => gmdate( DATE_ATOM ),
			'adapter'        => $adapter_id,
			'preset'         => $plan['preset']['selected'],
			'post_content'   => $before['post_content'],
			'post_name'      => $before['post_name'],
			'permalink_path' => $this->url_path( $before['permalink'] ),
			'meta'           => $before['meta'],
			'sha256'         => hash( 'sha256', $before['post_content'] ),
		);

		if ( ! add_post_meta( $post->ID, self::BACKUP_META, $backup, true ) ) {
			return new WP_Error( 'seo_geo_migration_backup_failed', 'Could not create the required migration backup.' );
		}

		$updated = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => $payload['content'],
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			delete_post_meta( $post->ID, self::BACKUP_META );
			return $updated;
		}

		$this->apply_meta_operations( $post->ID, $payload );

		$after = get_post( $post->ID );
		if ( ! $after instanceof WP_Post ) {
			$this->restore( $post->ID, $before );
			return new WP_Error( 'seo_geo_migration_verification_failed', 'Migrated resource could not be reloaded.' );
		}

		$after_permalink = get_permalink( $after );
		$after_permalink = is_string( $after_permalink ) ? $after_permalink : '';
		$preserved       = (int) $after->ID === (int) $post->ID
			&& (string) $after->post_name === $before['post_name']
			&& $this->url_path( $after_permalink ) === $this->url_path( $before['permalink'] );

		if ( ! $preserved ) {
			$this->restore( $post->ID, $before );
			return new WP_Error( 'seo_geo_migration_identity_changed', 'Migration changed a protected resource identity or URL path.' );
		}

		$state = array(
			'schema_version' => 1,
			'migrated_at'    => gmdate( DATE_ATOM ),
			'adapter'        => $adapter_id,
			'preset'         => $plan['preset']['selected'],
			'before_sha256'  => hash( 'sha256', $before['post_content'] ),
			'after_sha256'   => hash( 'sha256', (string) $after->post_content ),
		);
		update_post_meta( $post->ID, self::STATE_META, $state );

		return array(
			'schema_version' => 1,
			'mode'           => 'migration-execution',
			'status'         => 'migrated',
			'object_id'      => (int) $after->ID,
			'adapter'        => $adapter_id,
			'preset'         => $plan['preset']['selected'],
			'preserved'      => array(
				'object_id'      => true,
				'post_name'      => true,
				'permalink_path' => true,
				'media_ids'      => $plan['adapter_plan']['media_ids'],
			),
			'business_systems' => $plan['business_systems'],
			'backup_created'  => true,
			'safety'          => array(
				'sandbox_only'            => true,
				'plugins_changed'         => false,
				'theme_changed'           => false,
				'unsupported_content_lost' => false,
			),
		);
	}

	/**
	 * Return nonce action for one exact migration.
	 */
	public static function nonce_action( int $object_id, string $adapter_id ): string {
		return 'seo_geo_migrate_' . $object_id . '_' . sanitize_key( $adapter_id );
	}

	/**
	 * Capture only metadata that the adapter may change.
	 *
	 * @param array{content:string,delete_meta:list<string>,update_meta:array<string,mixed>} $payload Mutation payload.
	 * @return array<string,array{exists:bool,value:mixed}>
	 */
	private function capture_meta( int $object_id, array $payload ): array {
		$keys = array_values( array_unique( array_merge( $payload['delete_meta'], array_keys( $payload['update_meta'] ) ) ) );
		$meta = array();

		foreach ( $keys as $key ) {
			$exists       = metadata_exists( 'post', $object_id, $key );
			$meta[ $key ] = array(
				'exists' => $exists,
				'value'  => $exists ? get_post_meta( $object_id, $key, true ) : null,
			);
		}

		return $meta;
	}

	/**
	 * Apply adapter-owned meta operations.
	 *
	 * @param array{content:string,delete_meta:list<string>,update_meta:array<string,mixed>} $payload Mutation payload.
	 */
	private function apply_meta_operations( int $object_id, array $payload ): void {
		foreach ( $payload['delete_meta'] as $key ) {
			delete_post_meta( $object_id, $key );
		}

		foreach ( $payload['update_meta'] as $key => $value ) {
			update_post_meta( $object_id, $key, $value );
		}
	}

	/**
	 * Restore the resource after a failed post-mutation invariant.
	 *
	 * @param array{post_content:string,post_name:string,permalink:string,meta:array<string,array{exists:bool,value:mixed}>} $before Original state.
	 */
	private function restore( int $object_id, array $before ): void {
		wp_update_post(
			array(
				'ID'           => $object_id,
				'post_content' => $before['post_content'],
				'post_name'    => $before['post_name'],
			)
		);

		foreach ( $before['meta'] as $key => $state ) {
			if ( $state['exists'] ) {
				update_post_meta( $object_id, $key, $state['value'] );
			} else {
				delete_post_meta( $object_id, $key );
			}
		}

		delete_post_meta( $object_id, self::BACKUP_META );
		delete_post_meta( $object_id, self::STATE_META );
	}

	/**
	 * Return KEEP business-system components from the dependency graph.
	 *
	 * @param array<string,mixed> $graph Phase 8C dependency graph.
	 * @return list<string>
	 */
	private function preserved_business_systems( array $graph ): array {
		$components = isset( $graph['components'] ) && is_array( $graph['components'] ) ? $graph['components'] : array();
		$systems    = array();

		foreach ( $components as $component ) {
			if (
				is_array( $component )
				&& 'provider' === ( $component['type'] ?? null )
				&& 'business_systems' === ( $component['category'] ?? null )
				&& 'KEEP' === ( $component['classification'] ?? null )
				&& is_string( $component['id'] ?? null )
			) {
				$systems[] = $component['id'];
			}
		}

		$systems = array_values( array_unique( $systems ) );
		sort( $systems );

		return $systems;
	}

	/**
	 * Normalize a URL to its path for cross-origin sandbox parity.
	 */
	private function url_path( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) && '' !== $path ? $path : '/';
	}
}
