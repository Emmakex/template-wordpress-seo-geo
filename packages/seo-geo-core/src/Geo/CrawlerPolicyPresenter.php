<?php
/**
 * Native robots.txt crawler-policy presenter.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

/**
 * Adds explicit GEO crawler policy to WordPress's virtual robots.txt.
 */
final class CrawlerPolicyPresenter {
	private const BLOCK_START = '# BEGIN SEO GEO crawler policy';
	private const BLOCK_END   = '# END SEO GEO crawler policy';

	/**
	 * Policy resolver.
	 *
	 * @var CrawlerPolicyResolver
	 */
	private CrawlerPolicyResolver $resolver;

	/**
	 * Create the presenter.
	 *
	 * @param CrawlerPolicyResolver $resolver Crawler-policy authority.
	 */
	public function __construct( CrawlerPolicyResolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Register robots.txt integration.
	 */
	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'filter' ), 50, 2 );
	}

	/**
	 * Append independent crawler groups to WordPress's virtual robots.txt.
	 *
	 * A globally private WordPress site must remain private. Explicit crawler
	 * allows are therefore never appended when blog_public is disabled.
	 *
	 * @param string $output Existing WordPress robots.txt output.
	 * @param mixed  $public WordPress blog_public value.
	 */
	public function filter( string $output, $public ): string {
		$output = $this->without_owned_block( $output );

		if ( 1 !== (int) $public ) {
			return $this->normalize_trailing_newline( $output );
		}

		$directives = $this->resolver->explicit_directives();
		if ( array() === $directives ) {
			return $this->normalize_trailing_newline( $output );
		}

		$lines = array( self::BLOCK_START );

		foreach ( $directives as $directive ) {
			$lines[] = 'User-agent: ' . $directive['user_agent'];
			$lines[] = $directive['directive'];
			$lines[] = '';
		}

		if ( '' === end( $lines ) ) {
			array_pop( $lines );
		}

		$lines[] = self::BLOCK_END;

		$base = rtrim( $output );

		return ( '' !== $base ? $base . "\n\n" : '' ) . implode( "\n", $lines ) . "\n";
	}

	/**
	 * Remove a previously generated owned block for idempotent filtering.
	 *
	 * @param string $output Existing robots.txt output.
	 */
	private function without_owned_block( string $output ): string {
		$pattern = '/(?:\r?\n)?' . preg_quote( self::BLOCK_START, '/' ) . '.*?' . preg_quote( self::BLOCK_END, '/' ) . '(?:\r?\n)?/s';
		$clean   = preg_replace( $pattern, "\n", $output );

		return is_string( $clean ) ? $clean : $output;
	}

	/**
	 * Preserve one trailing newline without otherwise rewriting WordPress output.
	 *
	 * @param string $output Existing robots.txt output.
	 */
	private function normalize_trailing_newline( string $output ): string {
		return rtrim( $output ) . "\n";
	}
}
