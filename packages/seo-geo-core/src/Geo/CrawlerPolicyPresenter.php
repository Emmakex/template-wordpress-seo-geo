<?php
/**
 * Native robots.txt crawler-policy presenter.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

/**
 * Adds explicit crawler policy blocks to WordPress's virtual robots.txt.
 */
final class CrawlerPolicyPresenter {
	/**
	 * Validated crawler policy.
	 *
	 * @var CrawlerPolicy
	 */
	private CrawlerPolicy $policy;

	/**
	 * Create the presenter.
	 *
	 * @param CrawlerPolicy $policy Validated crawler policy.
	 */
	public function __construct( CrawlerPolicy $policy ) {
		$this->policy = $policy;
	}

	/**
	 * Register WordPress integration.
	 */
	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );
	}

	/**
	 * Append explicit crawler directives without overriding site-wide privacy.
	 *
	 * Existing crawler-specific blocks are preserved and treated as externally
	 * owned so the native runtime never creates competing duplicate directives.
	 *
	 * @param string $output Existing WordPress robots.txt content.
	 * @param bool   $is_public Whether WordPress marks the site as public.
	 */
	public function filter_robots_txt( string $output, bool $is_public ): string {
		if ( ! $is_public ) {
			return $output;
		}

		$blocks = array();

		foreach ( array( CrawlerPolicy::OAI_SEARCHBOT, CrawlerPolicy::GPTBOT ) as $user_agent ) {
			if ( $this->has_user_agent_block( $output, $user_agent ) ) {
				continue;
			}

			$state = $this->policy->state_for( $user_agent );
			if ( CrawlerPolicy::INHERIT === $state ) {
				continue;
			}

			$blocks[] = $this->block( $user_agent, $state );
		}

		if ( array() === $blocks ) {
			return $output;
		}

		$output = rtrim( $output );

		return ( '' !== $output ? $output . "\n\n" : '' )
			. implode( "\n\n", $blocks )
			. "\n";
	}

	/**
	 * Build one exact robots.txt user-agent block.
	 *
	 * @param string $user_agent Supported user agent.
	 * @param string $state      Validated policy state.
	 */
	private function block( string $user_agent, string $state ): string {
		$directive = CrawlerPolicy::ALLOW === $state ? 'Allow' : 'Disallow';

		return 'User-agent: ' . $user_agent . "\n" . $directive . ': /';
	}

	/**
	 * Check whether another owner already emitted a block for the crawler.
	 *
	 * @param string $output     Existing robots.txt content.
	 * @param string $user_agent Supported user agent.
	 */
	private function has_user_agent_block( string $output, string $user_agent ): bool {
		$pattern = '/^\s*User-agent:\s*' . preg_quote( $user_agent, '/' ) . '\s*$/mi';

		return 1 === preg_match( $pattern, $output );
	}
}
