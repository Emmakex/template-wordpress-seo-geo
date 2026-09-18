<?php
/**
 * Native crawler policy configuration.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

/**
 * Keeps discovery and training crawler choices explicit and independent.
 */
final class CrawlerPolicy {
	/**
	 * WordPress option containing crawler policy choices.
	 */
	public const OPTION_NAME = 'seo_geo_crawler_policy';

	/**
	 * Supported policy states.
	 */
	public const INHERIT  = 'inherit';
	public const ALLOW    = 'allow';
	public const DISALLOW = 'disallow';

	/**
	 * OpenAI search/discovery crawler user agent.
	 */
	public const OAI_SEARCHBOT = 'OAI-SearchBot';

	/**
	 * OpenAI crawler used for potential model training collection.
	 */
	public const GPTBOT = 'GPTBot';

	/**
	 * OAI-SearchBot policy state.
	 *
	 * @var string
	 */
	private string $oai_searchbot;

	/**
	 * GPTBot policy state.
	 *
	 * @var string
	 */
	private string $gptbot;

	/**
	 * Create one validated policy.
	 *
	 * @param string $oai_searchbot OAI-SearchBot policy.
	 * @param string $gptbot        GPTBot policy.
	 */
	private function __construct( string $oai_searchbot, string $gptbot ) {
		$this->oai_searchbot = $oai_searchbot;
		$this->gptbot        = $gptbot;
	}

	/**
	 * Load crawler policy from WordPress options.
	 */
	public static function from_wordpress(): self {
		$configuration = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $configuration ) ) {
			$configuration = array();
		}

		return new self(
			self::normalize_state( $configuration['oai_searchbot'] ?? null ),
			self::normalize_state( $configuration['gptbot'] ?? null )
		);
	}

	/**
	 * Return the configured state for one supported crawler.
	 *
	 * Unknown agents always inherit existing robots.txt behavior.
	 *
	 * @param string $user_agent Exact crawler user agent.
	 */
	public function state_for( string $user_agent ): string {
		if ( self::OAI_SEARCHBOT === $user_agent ) {
			return $this->oai_searchbot;
		}

		if ( self::GPTBOT === $user_agent ) {
			return $this->gptbot;
		}

		return self::INHERIT;
	}

	/**
	 * Normalize untrusted option values.
	 *
	 * @param mixed $value Candidate policy state.
	 */
	private static function normalize_state( $value ): string {
		if ( ! is_string( $value ) ) {
			return self::INHERIT;
		}

		return in_array( $value, array( self::INHERIT, self::ALLOW, self::DISALLOW ), true )
			? $value
			: self::INHERIT;
	}
}
