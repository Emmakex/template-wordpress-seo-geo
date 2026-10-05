<?php
/**
 * Native Insights/posts-index runtime.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Insights;

/**
 * Provides a zero-JavaScript dynamic title for the WordPress posts page.
 */
final class InsightsIndexRuntime {
	public const TITLE_BLOCK = 'seo-geo/insights-title';

	/** Register the server-rendered Insights title block. */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/** Register the dynamic block. */
	public function register_block(): void {
		register_block_type(
			self::TITLE_BLOCK,
			array(
				'api_version'     => '3',
				'render_callback' => array( $this, 'render_title' ),
			)
		);
	}

	/**
	 * Render the real title of the page assigned as WordPress posts page.
	 */
	public function render_title(): string {
		$page_id = (int) get_option( 'page_for_posts', 0 );
		$title   = 0 < $page_id ? trim( (string) get_the_title( $page_id ) ) : '';
		if ( '' === $title ) {
			$title = __( 'Insights', 'seo-geo-theme' );
		}

		return '<h1 class="wp-block-heading seo-geo-insights-title">' . esc_html( $title ) . '</h1>';
	}
}
