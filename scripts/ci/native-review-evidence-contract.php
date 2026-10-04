<?php
/**
 * Standalone contract test for privacy-bounded Native Replatform review evidence.
 */

declare(strict_types=1);

namespace {
	final class WP_Post {
		public function __construct(
			public int $ID,
			public string $post_type,
			public string $post_status,
			public string $post_content
		) {
		}
	}
}

namespace SeoGeo\MigrationBridge\Replatform {
	final class NativeCompositionService {
		public const SOURCE_ID_META      = '_source_id';
		public const SOURCE_SHA_META     = '_source_sha';
		public const PRESET_META         = '_preset';
		public const PAGE_KEY_META       = '_page_key';
		public const PLAN_SHA_META       = '_plan_sha';
		public const REMAP_PLAN_SHA_META = '_remap_sha';

		/**
		 * @param array<string,mixed> $plan Current plan.
		 */
		public function __construct( private array $plan ) {
		}

		/**
		 * @return array<string,mixed>
		 */
		public function plan( string $page_key, string $preset ): array {
			unset( $page_key, $preset );
			return $this->plan;
		}
	}

	final class ReviewedRemapApplier {
		public const LEDGER_META   = '_ledger';
		public const BACKUP_META   = '_backup';
		public const ROLLBACK_META = '_rollback';
	}

	/** @var array<int,\WP_Post> */
	$posts = array();
	/** @var array<int,array<string,mixed>> */
	$meta = array();

	function get_post( int $post_id ): ?\WP_Post {
		global $posts;
		return $posts[ $post_id ] ?? null;
	}

	function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
		global $meta;
		unset( $single );
		return $meta[ $post_id ][ $key ] ?? '';
	}

	function get_post_field( string $field, int $post_id ): string {
		global $posts;
		if ( 'post_content' !== $field || ! isset( $posts[ $post_id ] ) ) {
			return '';
		}
		return $posts[ $post_id ]->post_content;
	}

	function sanitize_key( string $value ): string {
		$value = strtolower( $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '';
	}

	function wp_json_encode( mixed $value, int $flags = 0 ): string|false {
		return json_encode( $value, $flags );
	}

	require dirname( __DIR__, 2 ) . '/packages/seo-geo-migration-bridge/src/Replatform/NativeReviewEvidence.php';

	$source_id = 10;
	$draft_id  = 20;
	$source    = 'Preserved source content';
	$draft     = 'Reviewed private draft';
	$before    = 'Native draft before Reviewed Apply';

	$source_sha = hash( 'sha256', $source );
	$draft_sha  = hash( 'sha256', $draft );
	$before_sha = hash( 'sha256', $before );
	$plan_sha   = str_repeat( 'a', 64 );
	$remap_sha  = str_repeat( 'b', 64 );
	$selection  = str_repeat( 'c', 64 );

	$posts = array(
		$source_id => new \WP_Post( $source_id, 'page', 'publish', $source ),
		$draft_id  => new \WP_Post( $draft_id, 'page', 'draft', $draft ),
	);
	$meta = array(
		$draft_id => array(
			NativeCompositionService::SOURCE_ID_META      => $source_id,
			NativeCompositionService::SOURCE_SHA_META     => $source_sha,
			NativeCompositionService::PRESET_META         => 'corporate',
			NativeCompositionService::PAGE_KEY_META       => 'home',
			NativeCompositionService::PLAN_SHA_META       => $plan_sha,
			NativeCompositionService::REMAP_PLAN_SHA_META => $remap_sha,
			ReviewedRemapApplier::LEDGER_META             => array(
				'selection_sha256'  => $selection,
				'before_sha256'     => $before_sha,
				'after_sha256'      => $draft_sha,
				'verified_sections' => array( 'verified-proof' ),
				'selected_assets'   => array(
					'units' => array( 'u-001' ),
					'links' => array( 'l-001', 'l-002' ),
					'media' => array(),
				),
				'unmapped_assets'   => array(
					'units' => array( 'u-002', 'u-003' ),
					'links' => array(),
					'media' => array( 'm-001' ),
				),
			),
			ReviewedRemapApplier::BACKUP_META             => array(
				'sha256'  => $before_sha,
				'content' => $before,
			),
			ReviewedRemapApplier::ROLLBACK_META           => array(
				'selection_sha256' => str_repeat( 'd', 64 ),
			),
		),
	);

	$plan = array(
		'ready'         => true,
		'plan_sha256'   => $plan_sha,
		'source'        => array(
			'id'             => $source_id,
			'path'           => '/',
			'content_sha256' => $source_sha,
		),
		'content_remap' => array(
			'plan_sha256' => $remap_sha,
		),
	);

	$service = new NativeReviewEvidence( new NativeCompositionService( $plan ) );

	$without_sandbox = $service->snapshot( $draft_id );
	assert( 'blocked' === $without_sandbox['status'] );
	assert( in_array( 'sandbox-required', $without_sandbox['blockers'], true ) );
	assert( false === $without_sandbox['safety']['sandbox_active'] );

	define( 'SEO_GEO_MIGRATION_SANDBOX', true );

	$ready = $service->snapshot( $draft_id );

	assert( 'ready' === $ready['status'] );
	assert( array() === $ready['blockers'] );
	assert( 'native-replatform-review-evidence' === $ready['mode'] );
	assert( true === $ready['safety']['sandbox_only'] );
	assert( true === $ready['safety']['sandbox_active'] );
	assert( false === $ready['safety']['content_included'] );
	assert( false === $ready['safety']['private_payload_included'] );
	assert( false === $ready['safety']['production_cutover_authorized'] );
	assert( true === $ready['source']['unchanged'] );
	assert( true === $ready['rollback']['available'] );
	assert( true === $ready['rollback']['prior_rollback_exists'] );
	assert( array( 'units' => 1, 'links' => 2, 'media' => 0 ) === $ready['selected_counts'] );
	assert( array( 'units' => 2, 'links' => 0, 'media' => 1 ) === $ready['unmapped_counts'] );
	assert( array( 'verified-proof' ) === $ready['verified_sections'] );
	assert( 64 === strlen( $ready['evidence_sha256'] ) );

	$encoded = json_encode( $ready, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	assert( is_string( $encoded ) );
	assert( ! str_contains( $encoded, $source ) );
	assert( ! str_contains( $encoded, $draft ) );
	assert( ! str_contains( $encoded, $before ) );
	assert( ! str_contains( $encoded, 'u-001' ) );
	assert( ! str_contains( $encoded, 'l-001' ) );
	assert( ! str_contains( $encoded, 'm-001' ) );

	$again = $service->snapshot( $draft_id );
	assert( $ready['evidence_sha256'] === $again['evidence_sha256'] );

	$posts[ $source_id ]->post_content = 'Source changed after review';
	$source_drift = $service->snapshot( $draft_id );
	assert( 'blocked' === $source_drift['status'] );
	assert( in_array( 'source-content-drift', $source_drift['blockers'], true ) );
	$posts[ $source_id ]->post_content = $source;

	$posts[ $draft_id ]->post_content = 'Draft changed after reviewed apply';
	$draft_drift = $service->snapshot( $draft_id );
	assert( 'blocked' === $draft_drift['status'] );
	assert( in_array( 'reviewed-draft-drift', $draft_drift['blockers'], true ) );
	$posts[ $draft_id ]->post_content = $draft;

	$meta[ $draft_id ][ ReviewedRemapApplier::LEDGER_META ] = '';
	$missing_ledger = $service->snapshot( $draft_id );
	assert( 'blocked' === $missing_ledger['status'] );
	assert( in_array( 'reviewed-apply-ledger-missing', $missing_ledger['blockers'], true ) );

	echo "Native review evidence contract OK\n";
}
