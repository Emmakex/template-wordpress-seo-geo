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
	 * Supported site entity type for an Organization site identity.
	 */
	public const SITE_ENTITY_ORGANIZATION = 'organization';

	/**
	 * Supported site entity type for one physical LocalBusiness location.
	 */
	public const SITE_ENTITY_LOCAL_BUSINESS = 'local_business';

	/**
	 * Supported site entity type for one explicit public Person identity.
	 */
	public const SITE_ENTITY_PERSON = 'person';

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
	 * Return supported explicit site entity types.
	 *
	 * @return list<string>
	 */
	public static function supported_site_entity_types(): array {
		return array(
			self::SITE_ENTITY_ORGANIZATION,
			self::SITE_ENTITY_LOCAL_BUSINESS,
			self::SITE_ENTITY_PERSON,
		);
	}

	/**
	 * Normalize one explicit site entity type.
	 *
	 * @param mixed $value Candidate entity type.
	 */
	public static function normalize_site_entity_type( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = sanitize_key( $value );

		return in_array( $value, self::supported_site_entity_types(), true ) ? $value : null;
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
	 * Resolve one explicit public Person as the site identity.
	 *
	 * Unlike Organization identity, the Person name is never inferred from the
	 * site title. It must exist in the validated identity option. The canonical
	 * public URL remains the WordPress home URL so the graph cannot silently
	 * point at an unrelated identity page.
	 *
	 * @return array{id:string,name:string,url:string,description:string,same_as:list<string>}|null
	 */
	public function site_person(): ?array {
		if ( self::SITE_ENTITY_PERSON !== $this->site_entity_type() ) {
			return null;
		}

		$configuration = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $configuration ) || ! is_array( $configuration['person'] ?? null ) ) {
			return null;
		}

		$person = $configuration['person'];
		$name   = isset( $person['name'] ) && is_string( $person['name'] ) ? $this->text( $person['name'] ) : '';
		if ( '' === $name ) {
			return null;
		}

		$home_url = home_url( '/' );

		return array(
			'id'          => $this->ids->person( $home_url ),
			'name'        => $name,
			'url'         => $home_url,
			'description' => isset( $person['description'] ) && is_string( $person['description'] )
				? $this->text( $person['description'] )
				: '',
			'same_as'     => $this->same_as( $person['same_as'] ?? array() ),
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

		return self::normalize_site_entity_type( $configuration['site_entity_type'] ?? null );
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
	 * Defensively normalize explicit public identity URLs from persisted state.
	 *
	 * @param mixed $value Candidate sameAs URL list.
	 * @return list<string>
	 */
	private function same_as( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$urls = array();
		foreach ( array_slice( $value, 0, 20 ) as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				continue;
			}

			$url = esc_url_raw( trim( $candidate ), array( 'http', 'https' ) );
			if ( '' === $url || null === wp_parse_url( $url, PHP_URL_HOST ) ) {
				continue;
			}

			$urls[] = $url;
		}

		return array_values( array_unique( $urls ) );
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
