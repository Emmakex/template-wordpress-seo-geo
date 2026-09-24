<?php
/**
 * Administrator dependency-review planning endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Review;

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\DependencyGraphBuilder;
use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;
use SeoGeo\MigrationBridge\SiteAnalyzer;
use Throwable;

/**
 * Persists an explicit planning-only review decision for a live UNKNOWN component.
 */
final class AdminDependencyReviewController {
	/**
	 * Admin-post action name.
	 */
	public const ACTION = 'seo_geo_migration_review_dependency';

	/**
	 * Nonce action prefix.
	 */
	public const NONCE_ACTION = 'seo_geo_migration_review_dependency';

	/**
	 * Register the authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Persist one bounded operator review decision.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to review migration dependencies.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$component_id = isset( $_POST['component_id'] )
			? sanitize_text_field( wp_unslash( $_POST['component_id'] ) )
			: '';
		$decision     = isset( $_POST['review_decision'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_POST['review_decision'] ) ) )
			: '';

		check_admin_referer( self::NONCE_ACTION . ':' . $component_id );

		if ( ! $this->is_current_unknown_component( $component_id ) ) {
			wp_die(
				esc_html__( 'This dependency is not a current UNKNOWN review item.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 409 )
			);
		}

		$store  = new DependencyReviewStore();
		$result = 'UNREVIEWED' === $decision
			? $store->clear( $component_id )
			: $store->save( $component_id, $decision );

		if ( ! $result ) {
			wp_die(
				esc_html__( 'The dependency review decision could not be stored.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			'seo_geo_dependency_review',
			'UNREVIEWED' === $decision ? 'cleared' : 'saved',
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Confirm the submitted identifier still belongs to one live UNKNOWN component.
	 */
	private function is_current_unknown_component( string $component_id ): bool {
		if ( '' === $component_id ) {
			return false;
		}

		try {
			$analysis          = ( new SiteAnalyzer() )->analyze();
			$baseline          = ( new BaselineSnapshotStore() )->latest();
			$baseline_snapshot = is_array( $baseline['snapshot'] ?? null ) ? $baseline['snapshot'] : null;
			$graph             = ( new DependencyGraphBuilder() )->build( $analysis, $baseline_snapshot );
			$components        = is_array( $graph['components'] ?? null ) ? $graph['components'] : array();

			foreach ( $components as $component ) {
				if (
					is_array( $component )
					&& $component_id === ( $component['component_id'] ?? null )
					&& 'UNKNOWN' === ( $component['classification'] ?? null )
					&& true === ( $component['manual_review'] ?? false )
				) {
					return true;
				}
			}
		} catch ( Throwable ) {
			return false;
		}

		return false;
	}
}
