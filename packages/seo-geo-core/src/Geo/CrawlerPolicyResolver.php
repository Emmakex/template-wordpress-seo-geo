<?php
/**
 * GEO crawler-policy resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

/**
 * Resolves explicit crawler policy without conflating search and training.
 */
final class CrawlerPolicyResolver {
	public const OPTION_NAME = 'seo_geo_crawler_policy';

	public const CRAWLER_OAI_SEARCHBOT = 'oai_searchbot';
	public const CRAWLER_GPTBOT        = 'gptbot';

	public const STATE_INHERIT  = 'inherit';
	public const STATE_ALLOW    = 'allow';
	public const STATE_DISALLOW = 'disallow';

	/**
	 * Supported crawler keys mapped to their public user-agent names.
	 *
	 * @var array<string, string>
	 */
	private const USER_AGENTS = array(
		self::CRAWLER_OAI_SEARCHBOT => 'OAI-SearchBot',
		self::CRAWLER_GPTBOT        => 'GPTBot',
	);

	/**
	 * Return the supported crawler registry.
	 *
	 * @return array<string, string>
	 */
	public function supported_crawlers(): array {
		return self::USER_AGENTS;
	}

	/**
	 * Normalize a submitted configuration.
	 *
	 * Unknown crawler keys are discarded. Invalid states resolve to inherit,
	 * and inherited entries are omitted so an all-inherit submission remains
	 * equivalent to the native disabled/default state.
	 *
	 * @param mixed $configuration Submitted crawler configuration.
	 * @return array<string, string>
	 */
	public function sanitize_configuration( $configuration ): array {
		if ( ! is_array( $configuration ) ) {
			return array();
		}

		$normalized = array();

		foreach ( array_keys( self::USER_AGENTS ) as $crawler ) {
			$state = $this->normalize_state( $configuration[ $crawler ] ?? self::STATE_INHERIT );
			if ( self::STATE_INHERIT !== $state ) {
				$normalized[ $crawler ] = $state;
			}
		}

		return $normalized;
	}

	/**
	 * Return normalized state for one supported crawler.
	 *
	 * Invalid or missing configuration always falls back to inherit so Core
	 * does not silently change robots policy.
	 *
	 * @param string $crawler Supported crawler key.
	 */
	public function state( string $crawler ): string {
		if ( ! isset( self::USER_AGENTS[ $crawler ] ) ) {
			return self::STATE_INHERIT;
		}

		$configuration = $this->sanitize_configuration( get_option( self::OPTION_NAME, null ) );

		return $configuration[ $crawler ] ?? self::STATE_INHERIT;
	}

	/**
	 * Resolve explicit robots directives only.
	 *
	 * Inherited crawlers are intentionally omitted.
	 *
	 * @return array<int, array{crawler:string,user_agent:string,state:string,directive:string}>
	 */
	public function explicit_directives(): array {
		$directives = array();

		foreach ( self::USER_AGENTS as $crawler => $user_agent ) {
			$state = $this->state( $crawler );

			if ( self::STATE_INHERIT === $state ) {
				continue;
			}

			$directives[] = array(
				'crawler'    => $crawler,
				'user_agent' => $user_agent,
				'state'      => $state,
				'directive'  => self::STATE_ALLOW === $state ? 'Allow: /' : 'Disallow: /',
			);
		}

		return $directives;
	}

	/**
	 * Normalize one state value.
	 *
	 * @param mixed $state Candidate state.
	 */
	private function normalize_state( $state ): string {
		if ( ! is_string( $state ) ) {
			return self::STATE_INHERIT;
		}

		$state = strtolower( trim( $state ) );

		if ( ! in_array( $state, array( self::STATE_INHERIT, self::STATE_ALLOW, self::STATE_DISALLOW ), true ) ) {
			return self::STATE_INHERIT;
		}

		return $state;
	}
}
