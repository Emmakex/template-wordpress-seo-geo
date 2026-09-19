<?php
/**
 * GEO discovery cache invalidation hooks.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

use SeoGeo\Core\Language\NativeLanguageConfiguration;
use SeoGeo\Core\Language\NativeTranslationRegistry;
use SeoGeo\Core\Schema\SchemaIdentityResolver;
use SeoGeo\Core\Schema\SchemaLocalBusinessResolver;

/**
 * Advances one revision when authoritative discovery inputs change.
 */
final class DiscoveryCacheInvalidator {
	/**
	 * Revision authority.
	 *
	 * @var DiscoveryCacheRevision
	 */
	private DiscoveryCacheRevision $revision;

	/**
	 * Create the invalidator.
	 *
	 * @param DiscoveryCacheRevision $revision Revision authority.
	 */
	public function __construct( DiscoveryCacheRevision $revision ) {
		$this->revision = $revision;
	}

	/**
	 * Register mutation hooks.
	 */
	public function register(): void {
		add_action( 'clean_post_cache', array( $this, 'post_changed' ), 10, 1 );

		add_action( 'added_post_meta', array( $this, 'post_meta_changed' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'post_meta_changed' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'post_meta_changed' ), 10, 3 );

		add_action( 'user_register', array( $this, 'user_changed' ), 10, 1 );
		add_action( 'profile_update', array( $this, 'user_changed' ), 10, 1 );
		add_action( 'deleted_user', array( $this, 'user_changed' ), 10, 1 );

		add_action( 'added_option', array( $this, 'option_changed' ), 10, 1 );
		add_action( 'updated_option', array( $this, 'option_changed' ), 10, 1 );
		add_action( 'deleted_option', array( $this, 'option_changed' ), 10, 1 );
	}

	/**
	 * Invalidate after a post cache mutation.
	 *
	 * @param int $post_id Affected WordPress post ID.
	 */
	public function post_changed( int $post_id ): void {
		if ( 0 >= $post_id || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->revision->bump( 'post', $post_id );
	}

	/**
	 * Invalidate translation/discovery metadata changes.
	 *
	 * @param mixed  $meta_id Meta row ID(s), unused.
	 * @param int    $post_id WordPress post ID.
	 * @param string $meta_key Meta key.
	 */
	public function post_meta_changed( $meta_id, int $post_id, string $meta_key ): void {
		unset( $meta_id );

		if ( ! in_array( $meta_key, $this->relevant_post_meta(), true ) ) {
			return;
		}

		$this->revision->bump( 'post-meta:' . $meta_key, $post_id );
	}

	/**
	 * Invalidate author/profile-dependent provenance.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public function user_changed( int $user_id ): void {
		if ( 0 >= $user_id ) {
			return;
		}

		$this->revision->bump( 'user', $user_id );
	}

	/**
	 * Invalidate changes to authoritative SEO/GEO/site options.
	 *
	 * @param string $option Option name.
	 */
	public function option_changed( string $option ): void {
		if ( ! in_array( $option, $this->relevant_options(), true ) ) {
			return;
		}

		$this->revision->bump( 'option:' . $option );
	}

	/**
	 * Return post metadata that can change public language/discovery identity.
	 *
	 * @return array<int, string>
	 */
	private function relevant_post_meta(): array {
		return array(
			NativeTranslationRegistry::META_GROUP,
			NativeTranslationRegistry::META_LANGUAGE,
			NativeTranslationRegistry::META_TRANSLATIONS,
		);
	}

	/**
	 * Return options whose changes can alter public SEO/GEO discovery output.
	 *
	 * @return array<int, string>
	 */
	private function relevant_options(): array {
		return array(
			CrawlerPolicyResolver::OPTION_NAME,
			LlmsTxtResolver::OPTION_NAME,
			MarkdownAlternateResolver::OPTION_NAME,
			NativeLanguageConfiguration::OPTION_NAME,
			SchemaIdentityResolver::OPTION_NAME,
			SchemaLocalBusinessResolver::OPTION_NAME,
			'blog_public',
			'blogname',
			'blogdescription',
			'home',
			'siteurl',
			'permalink_structure',
			'timezone_string',
			'gmt_offset',
			'WPLANG',
			'show_on_front',
			'page_on_front',
		);
	}
}
