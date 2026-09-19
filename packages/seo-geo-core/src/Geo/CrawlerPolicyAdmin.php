<?php
/**
 * Native crawler-policy administration.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

/**
 * Provides a small WordPress-native settings and reporting screen.
 */
final class CrawlerPolicyAdmin {
	private const PAGE_SLUG      = 'seo-geo-crawler-policy';
	private const SETTINGS_GROUP = 'seo_geo_crawler_policy';

	/**
	 * Crawler-policy authority.
	 *
	 * @var CrawlerPolicyResolver
	 */
	private CrawlerPolicyResolver $resolver;

	/**
	 * Create the administration adapter.
	 *
	 * @param CrawlerPolicyResolver $resolver Crawler-policy authority.
	 */
	public function __construct( CrawlerPolicyResolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Register WordPress admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Register the theme-owned crawler-policy screen.
	 */
	public function register_page(): void {
		add_theme_page(
			__( 'SEO/GEO crawler policy', 'seo-geo-core' ),
			__( 'SEO/GEO Crawlers', 'seo-geo-core' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the server-authoritative option sanitizer.
	 */
	public function register_setting(): void {
		register_setting(
			self::SETTINGS_GROUP,
			CrawlerPolicyResolver::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_option' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize submitted crawler policy through the resolver contract.
	 *
	 * @param mixed $value Submitted option value.
	 * @return array<string, string>
	 */
	public function sanitize_option( $value ): array {
		return $this->resolver->sanitize_configuration( $value );
	}

	/**
	 * Render the settings and effective-policy report.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage crawler policy.', 'seo-geo-core' ) );
		}

		$site_public = 1 === (int) get_option( 'blog_public', 1 );
		$crawlers    = $this->resolver->supported_crawlers();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'SEO/GEO crawler policy', 'seo-geo-core' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'Control how supported OpenAI crawlers may access this public site. Search discovery and potential model training are separate controls.',
					'seo-geo-core'
				);
				?>
			</p>

			<?php if ( ! $site_public ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						echo esc_html__(
							'WordPress site visibility is currently set to discourage search engines. This global setting takes precedence, so crawler-specific allow rules are not emitted.',
							'seo-geo-core'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( $crawlers as $crawler => $user_agent ) : ?>
						<?php $field_id = 'seo-geo-crawler-' . str_replace( '_', '-', $crawler ); ?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_id ); ?>">
									<code><?php echo esc_html( $user_agent ); ?></code>
								</label>
							</th>
							<td>
								<select
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( CrawlerPolicyResolver::OPTION_NAME . '[' . $crawler . ']' ); ?>"
								>
									<?php $this->render_state_options( $this->resolver->state( $crawler ) ); ?>
								</select>
								<p class="description"><?php echo esc_html( $this->crawler_description( $crawler ) ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php echo esc_html__( 'Current report', 'seo-geo-core' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Crawler', 'seo-geo-core' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Configured policy', 'seo-geo-core' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Effective robots behavior', 'seo-geo-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $crawlers as $crawler => $user_agent ) : ?>
					<?php $state = $this->resolver->state( $crawler ); ?>
					<tr>
						<th scope="row"><code><?php echo esc_html( $user_agent ); ?></code></th>
						<td><?php echo esc_html( $this->state_label( $state ) ); ?></td>
						<td><?php echo esc_html( $this->effective_behavior( $state, $site_public ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html__( 'View robots.txt', 'seo-geo-core' ); ?>
				</a>
			</p>
			<p class="description">
				<?php
				echo esc_html__(
					'These controls do not guarantee ranking, inclusion, citation, traffic, or model behavior.',
					'seo-geo-core'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render sanitized select options.
	 *
	 * @param string $current Current crawler state.
	 */
	private function render_state_options( string $current ): void {
		$options = array(
			CrawlerPolicyResolver::STATE_INHERIT  => __( 'Inherit existing robots policy', 'seo-geo-core' ),
			CrawlerPolicyResolver::STATE_ALLOW    => __( 'Allow', 'seo-geo-core' ),
			CrawlerPolicyResolver::STATE_DISALLOW => __( 'Disallow', 'seo-geo-core' ),
		);

		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
	}

	/**
	 * Human-readable crawler purpose.
	 *
	 * @param string $crawler Supported crawler key.
	 */
	private function crawler_description( string $crawler ): string {
		if ( CrawlerPolicyResolver::CRAWLER_OAI_SEARCHBOT === $crawler ) {
			return __( 'OAI-SearchBot controls access for ChatGPT search discovery and snippets.', 'seo-geo-core' );
		}

		if ( CrawlerPolicyResolver::CRAWLER_GPTBOT === $crawler ) {
			return __( 'GPTBot controls access for potential model training.', 'seo-geo-core' );
		}

		return '';
	}

	/**
	 * Human-readable configured state.
	 *
	 * @param string $state Normalized crawler state.
	 */
	private function state_label( string $state ): string {
		if ( CrawlerPolicyResolver::STATE_ALLOW === $state ) {
			return __( 'Allow', 'seo-geo-core' );
		}

		if ( CrawlerPolicyResolver::STATE_DISALLOW === $state ) {
			return __( 'Disallow', 'seo-geo-core' );
		}

		return __( 'Inherit existing robots policy', 'seo-geo-core' );
	}

	/**
	 * Explain the effective native robots behavior.
	 *
	 * @param string $state       Normalized crawler state.
	 * @param bool   $site_public Whether WordPress considers the site public.
	 */
	private function effective_behavior( string $state, bool $site_public ): string {
		if ( ! $site_public ) {
			return __( 'Controlled by WordPress site visibility', 'seo-geo-core' );
		}

		if ( CrawlerPolicyResolver::STATE_ALLOW === $state ) {
			return 'Allow: /';
		}

		if ( CrawlerPolicyResolver::STATE_DISALLOW === $state ) {
			return 'Disallow: /';
		}

		return __( 'No native crawler-specific rule', 'seo-geo-core' );
	}
}
