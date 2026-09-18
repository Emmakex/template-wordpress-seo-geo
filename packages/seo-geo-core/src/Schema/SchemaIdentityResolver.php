<?php
/**
 * Native Schema identity resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Schema;

use WP_User;

/**
 * Resolves truthful site/author identity data before graph construction.
 */
final class SchemaIdentityResolver {
	/**
	 * WordPress option used to opt the site into one explicit Schema identity.
	 */
	public const OPTION_NAME = 'seo_geo_schema_identity';

	/**
	 * Supported site entity type for Phase 5B.
	 */
	public const SITE_ENTITY_ORGANIZATION = 'organization';

	/**
	 * Supported site entity type for one physical LocalBusiness location.
	 */
	public const SITE_ENTITY_LOCAL_BUSINESS = 'local_business';

	/**
	 * Stable node-ID generator.
	 *
	 * @var SchemaNodeIds
	 */
	private SchemaNodeIds $ids;

	/**
	 * Create the identity resolver.
	 *
	 * @param SchemaNodeIds $ids Stable Schema node-ID generator.
	 */
	public function __construct( SchemaNodeIds $ids ) {
		$this->ids = $ids;
	}

	/**
	 * Resolve the explicitly configured site Organization identity.
	 *
	 * The site name and home URL remain the authoritative baseline values.
	 * Merely having a site title never implies that the site represents an
	 * Organization; the option must opt in explicitly.
	 *
	 * @return array{id:string,name:string,url:string}|null
	 */
	public function organization(): ?array {
		if ( self::SITE_ENTITY_ORGANIZATION !== $this->site_entity_type() ) {
			return null;
		}

		$name = $this->text( get_bloginfo( 'name' ) );
		if ( '' === $name ) {
			return null;
		}

		return array(
			'id'   => $this->ids->organization(),
			'name' => $name,
			'url'  => home_url( '/' ),
		);
	}

	/**
	 * Resolve the explicit site entity type.
	 */
	public function site_entity_type(): ?string {
		$configuration = get_option( self::OPTION_NAME, null );

		if ( ! is_array( $configuration ) ) {
			return null;
		}

		$type = $configuration['site_entity_type'] ?? null;
		if ( ! is_string( $type ) ) {
			return null;
		}

		if ( ! in_array( $type, array( self::SITE_ENTITY_ORGANIZATION, self::SITE_ENTITY_LOCAL_BUSINESS ), true ) ) {
			return null;
		}

		return $type;
	}

	/**
	 * Resolve the current WordPress author archive as a Person identity.
	 *
	 * @return array{id:string,name:string,url:string,description:string}|null
	 */
	public function current_author(): ?array {
		if ( ! is_author() ) {
			return null;
		}

		$author = get_queried_object();
		if ( ! $author instanceof WP_User ) {
			return null;
		}

		return $this->author( $author->ID );
	}

	/**
	 * Resolve one real WordPress user as a public author identity.
	 *
	 * @param int $author_id WordPress user ID.
	 * @return array{id:string,name:string,url:string,description:string}|null
	 */
	public function author( int $author_id ): ?array {
		$author = get_userdata( $author_id );
		if ( ! $author instanceof WP_User ) {
			return null;
		}

		$name = $this->text( $author->display_name );
		if ( '' === $name ) {
			return null;
		}

		$url = get_author_posts_url( $author->ID );
		if ( '' === $url ) {
			return null;
		}

		return array(
			'id'          => $this->ids->person( $url ),
			'name'        => $name,
			'url'         => $url,
			'description' => $this->text( (string) get_user_meta( $author->ID, 'description', true ) ),
		);
	}

	/**
	 * Normalize visible WordPress text for identity use.
	 *
	 * @param string $value Raw visible text.
	 */
	private function text( string $value ): string {
		return trim( wp_strip_all_tags( $value, true ) );
	}
}
