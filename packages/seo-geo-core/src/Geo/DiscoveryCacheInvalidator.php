<?php
/**
 * Discovery cache invalidation.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

use SeoGeo\Core\Language\NativeLanguageConfiguration;
use SeoGeo\Core\Schema\SchemaIdentityResolver;
use SeoGeo\Core\Schema\SchemaLocalBusinessResolver;

/**
 * Invalidates cached discovery documents when their authorities change.
 */
final class DiscoveryCacheInvalidator {
	/**
	 * WordPress/project options that can alter cached discovery output.
	 *
	 * @var array<int, string>
	 */
	private const RELEVANT_OPTIONS = array(
		'blogname',
		'blogdescription',
		'blog_public',
		'home',
		'siteurl',
		'permalink_structure',
		'WPLANG',
		'timezone_string',
		'gmt_offset',
		'show_on_front',
		'page_on_front',
		NativeLanguageConfiguration::OPTION_NAME,
		SchemaIdentityResolver::OPTION_NAME,
		SchemaLocalBusinessResolver::OPTION_NAME,
		LlmsTxtResolver::OPTION_NAME,
		MarkdownAlternateResolver::OPTION_NAME,
	);

	/**
	 * Discovery cache.
	 *
	 * @var DiscoveryCache
	 */
	private DiscoveryCache $cache;

	/**
	 * Create the invalidator.
	 *
	 * @param DiscoveryCache $cache Discovery cache.
	 */
	public function __construct( DiscoveryCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Register invalidation hooks.
	 */
	public function register(): void {
		add_action( 'clean_post_cache', array( $this->cache, 'invalidate' ), 10, 0 );
		add_action( 'added_post_meta', array( $this->cache, 'invalidate' ), 10, 0 );
		add_action( 'updated_post_meta', array( $this->cache, 'invalidate' ), 10, 0 );
		add_action( 'deleted_post_meta', array( $this->cache, 'invalidate' ), 10, 0 );
		add_action( 'clean_user_cache', array( $this->cache, 'invalidate' ), 10, 0 );
		add_action( 'updated_option', array( $this, 'invalidate_option' ), 10, 1 );
		add_action( 'added_option', array( $this, 'invalidate_option' ), 10, 1 );
		add_action( 'deleted_option', array( $this, 'invalidate_option' ), 10, 1 );
	}

	/**
	 * Invalidate only when a relevant option changed.
	 *
	 * @param string $option WordPress option name.
	 */
	public function invalidate_option( string $option ): void {
		if ( in_array( $option, self::RELEVANT_OPTIONS, true ) ) {
			$this->cache->invalidate();
		}
	}
}
