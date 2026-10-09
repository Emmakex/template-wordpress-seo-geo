<?php
/**
 * Site entity and GEO/discovery setup validation.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

use SeoGeo\Core\Geo\ContentProvenanceResolver;
use SeoGeo\Core\Geo\CrawlerPolicyResolver;
use SeoGeo\Core\Geo\LlmsTxtResolver;
use SeoGeo\Core\Geo\MarkdownAlternateResolver;
use SeoGeo\Core\Schema\SchemaIdentityResolver;
use SeoGeo\Core\Schema\SchemaLocalBusinessResolver;
use SeoGeo\Core\Schema\SchemaNodeIds;
use SeoGeo\Core\Schema\SchemaVisibleContentResolver;

/**
 * Validates explicit site identity and GEO choices without inventing facts.
 */
final class EntityGeoValidator {
	/**
	 * Crawler-policy authority.
	 *
	 * @var CrawlerPolicyResolver
	 */
	private CrawlerPolicyResolver $crawler_policy;

	/**
	 * LocalBusiness authority.
	 *
	 * @var SchemaLocalBusinessResolver
	 */
	private SchemaLocalBusinessResolver $local_business;

	/**
	 * Construct the validator.
	 *
	 * @param CrawlerPolicyResolver|null       $crawler_policy Optional crawler authority.
	 * @param SchemaLocalBusinessResolver|null $local_business Optional LocalBusiness authority.
	 */
	public function __construct(
		?CrawlerPolicyResolver $crawler_policy = null,
		?SchemaLocalBusinessResolver $local_business = null
	) {
		$this->crawler_policy = $crawler_policy ?? new CrawlerPolicyResolver();

		if ( null === $local_business ) {
			$ids             = new SchemaNodeIds();
			$identity        = new SchemaIdentityResolver( $ids );
			$visible_content = new SchemaVisibleContentResolver();
			$local_business  = new SchemaLocalBusinessResolver( $ids, $identity, $visible_content );
		}

		$this->local_business = $local_business;
	}

	/**
	 * Validate one entity + GEO candidate.
	 *
	 * @param array<string,mixed> $input Candidate setup values.
	 * @return array<string,mixed>
	 */
	public function validate( array $input ): array {
		$errors   = array();
		$warnings = array();

		$entity_type = SchemaIdentityResolver::normalize_site_entity_type( $input['site_entity_type'] ?? null );
		if ( null === $entity_type ) {
			$errors[] = 'unsupported-site-entity-type';
		}

		if ( true !== ( $input['confirm_identity'] ?? false ) ) {
			$errors[] = 'identity-confirmation-required';
		}

		if ( null !== $entity_type && '' === trim( wp_strip_all_tags( get_bloginfo( 'name' ), true ) ) ) {
			$errors[] = 'site-title-required-for-entity';
		}

		$preset          = isset( $input['preset'] ) && is_string( $input['preset'] )
			? sanitize_key( $input['preset'] )
			: '';
		$preset_document = '' !== $preset ? \seo_geo_theme_preset_document( $preset, 'preset.json' ) : null;

		if ( '' !== $preset && ! is_array( $preset_document ) ) {
			$errors[] = 'unsupported-preset';
		}

		if ( null !== $entity_type && is_array( $preset_document ) ) {
			$preset_schema = is_array( $preset_document['schema'] ?? null ) ? $preset_document['schema'] : array();
			$recommended   = is_string( $preset_schema['site_identity'] ?? null ) ? $preset_schema['site_identity'] : null;

			if ( null !== $recommended && $recommended !== $entity_type ) {
				$warnings[] = 'preset-site-identity-differs:' . $recommended;
			}
		}

		$local_business = null;
		$person         = null;

		if ( SchemaIdentityResolver::SITE_ENTITY_LOCAL_BUSINESS === $entity_type ) {
			$local_business = $this->validate_local_business( $input['local_business'] ?? null, $errors );
		} elseif ( isset( $input['local_business'] ) && is_array( $input['local_business'] ) && array() !== $input['local_business'] ) {
			$errors[] = 'local-business-fields-require-local-business-identity';
		}

		if ( SchemaIdentityResolver::SITE_ENTITY_PERSON === $entity_type ) {
			$person = $this->validate_person( $input['person'] ?? null, $errors );
		} elseif ( isset( $input['person'] ) && is_array( $input['person'] ) && array() !== $input['person'] ) {
			$errors[] = 'person-fields-require-person-identity';
		}

		$crawler_policy   = $this->validate_crawler_policy( $input['crawler_policy'] ?? array(), $errors );
		$llms_enabled     = $this->boolean_value( $input, 'llms_txt_enabled', $errors );
		$markdown_enabled = $this->boolean_value( $input, 'markdown_alternates_enabled', $errors );

		if (
			1 !== (int) get_option( 'blog_public', 1 )
			&& (
				true === $llms_enabled
				|| true === $markdown_enabled
				|| in_array( CrawlerPolicyResolver::STATE_ALLOW, $crawler_policy, true )
			)
		) {
			$warnings[] = 'site-not-public-discovery-settings-inactive';
		}

		$errors   = array_values( array_unique( $errors ) );
		$warnings = array_values( array_unique( $warnings ) );
		$valid    = array() === $errors && null !== $entity_type;

		$name_source = SchemaIdentityResolver::SITE_ENTITY_PERSON === $entity_type
			? 'explicit-person-name'
			: 'wordpress-site-title';

		return array(
			'schema_version' => 1,
			'mode'           => 'entity-geo-validation',
			'valid'          => $valid,
			'errors'         => $errors,
			'warnings'       => $warnings,
			'normalized'     => $valid
				? array(
					'entity' => array(
						'option_name'                => SchemaIdentityResolver::OPTION_NAME,
						'site_entity_type'           => $entity_type,
						'confirmed'                  => true,
						'name_source'                => $name_source,
						'url_source'                 => 'wordpress-home-url',
						'local_business_option_name' => SchemaIdentityResolver::SITE_ENTITY_LOCAL_BUSINESS === $entity_type
							? SchemaLocalBusinessResolver::OPTION_NAME
							: null,
						'local_business'             => $local_business,
						'person'                     => $person,
						'visible_fact_gate_required' => in_array(
							$entity_type,
							array( SchemaIdentityResolver::SITE_ENTITY_LOCAL_BUSINESS, SchemaIdentityResolver::SITE_ENTITY_PERSON ),
							true
						),
						'schema_output_ready'        => SchemaIdentityResolver::SITE_ENTITY_PERSON === $entity_type && is_array( $person ),
					),
					'geo'    => array(
						'crawler_policy' => array(
							'option_name' => CrawlerPolicyResolver::OPTION_NAME,
							'value'       => $crawler_policy,
						),
						'llms_txt'       => array(
							'option_name' => LlmsTxtResolver::OPTION_NAME,
							'enabled'     => $llms_enabled,
						),
						'markdown'       => array(
							'option_name' => MarkdownAlternateResolver::OPTION_NAME,
							'enabled'     => $markdown_enabled,
						),
						'provenance'     => array(
							'mode'         => 'native-eligible-content',
							'configurable' => false,
						),
					),
				)
				: null,
			'authorities'    => array(
				'identity'            => SchemaIdentityResolver::class,
				'local_business'      => SchemaLocalBusinessResolver::class,
				'crawler_policy'      => CrawlerPolicyResolver::class,
				'llms_txt'            => LlmsTxtResolver::class,
				'markdown_alternates' => MarkdownAlternateResolver::class,
				'content_provenance'  => ContentProvenanceResolver::class,
			),
			'safety'         => array(
				'options_persisted'        => false,
				'entity_facts_inferred'    => false,
				'address_inferred'         => false,
				'coordinates_inferred'     => false,
				'ratings_reviews_inferred' => false,
				'credentials_inferred'     => false,
				'affiliation_inferred'     => false,
				'content_selected'         => false,
				'crawler_guarantees_made'  => false,
				'plugins_mutated'          => false,
			),
		);
	}

	/**
	 * Validate explicit public Person identity values.
	 *
	 * @param mixed $value  Candidate Person map.
	 * @param array $errors Validation errors.
	 * @phpstan-param list<string> $errors
	 * @return array{name:string,description:string,same_as:list<string>}|null
	 */
	private function validate_person( mixed $value, array &$errors ): ?array {
		$error_count = count( $errors );

		if ( ! is_array( $value ) ) {
			$errors[] = 'person-configuration-required';
			return null;
		}

		$allowed = array( 'name', 'description', 'same_as' );
		foreach ( array_keys( $value ) as $key ) {
			if ( ! is_string( $key ) || ! in_array( $key, $allowed, true ) ) {
				$errors[] = 'unsupported-person-field:' . ( is_string( $key ) ? $key : 'non-string' );
			}
		}

		$name = $this->text_value( $value['name'] ?? null );
		if ( null === $name ) {
			$errors[] = 'person-name-required';
			return null;
		}

		if ( 160 < strlen( $name ) ) {
			$errors[] = 'person-name-too-long';
		}

		$description = $this->text_value( $value['description'] ?? null ) ?? '';
		if ( 1000 < strlen( $description ) ) {
			$errors[] = 'person-description-too-long';
		}

		$same_as = $this->validate_person_urls( $value['same_as'] ?? array(), $errors );

		if ( count( $errors ) > $error_count ) {
			return null;
		}

		return array(
			'name'        => $name,
			'description' => $description,
			'same_as'     => $same_as,
		);
	}

	/**
	 * Validate explicit public identity URLs without outbound requests.
	 *
	 * @param mixed $value  Candidate URL list.
	 * @param array $errors Validation errors.
	 * @phpstan-param list<string> $errors
	 * @return list<string>
	 */
	private function validate_person_urls( mixed $value, array &$errors ): array {
		if ( ! is_array( $value ) ) {
			$errors[] = 'person-same-as-must-be-list';
			return array();
		}

		if ( 20 < count( $value ) ) {
			$errors[] = 'person-same-as-limit-exceeded';
		}

		$urls = array();
		foreach ( array_slice( $value, 0, 20 ) as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				$errors[] = 'person-same-as-invalid';
				continue;
			}

			$url    = esc_url_raw( trim( $candidate ), array( 'http', 'https' ) );
			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			$host   = wp_parse_url( $url, PHP_URL_HOST );
			if ( '' === $url || ! in_array( $scheme, array( 'http', 'https' ), true ) || ! is_string( $host ) || '' === $host ) {
				$errors[] = 'person-same-as-invalid';
				continue;
			}

			$urls[] = $url;
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Validate basic LocalBusiness option values.
	 *
	 * @param mixed $value  Candidate LocalBusiness map.
	 * @param array $errors Validation errors.
	 * @phpstan-param list<string> $errors
	 * @return array<string,mixed>|null
	 */
	private function validate_local_business( mixed $value, array &$errors ): ?array {
		if ( ! is_array( $value ) ) {
			$errors[] = 'local-business-configuration-required';
			return null;
		}

		$allowed = array(
			'type',
			'street_address',
			'address_locality',
			'address_region',
			'postal_code',
			'address_country',
			'telephone',
			'price_range',
			'latitude',
			'longitude',
		);

		foreach ( array_keys( $value ) as $key ) {
			if ( ! is_string( $key ) || ! in_array( $key, $allowed, true ) ) {
				$errors[] = 'unsupported-local-business-field:' . ( is_string( $key ) ? $key : 'non-string' );
			}
		}

		$type = isset( $value['type'] ) && is_string( $value['type'] )
			? sanitize_text_field( $value['type'] )
			: 'LocalBusiness';

		if ( ! in_array( $type, SchemaLocalBusinessResolver::supported_types(), true ) ) {
			$errors[] = 'unsupported-local-business-type';
		}

		$address = $this->local_business->normalize_address_candidate( $value );
		if ( null === $address ) {
			$errors[] = 'local-business-physical-address-required';
		}

		$has_latitude  = $this->has_coordinate_input( $value, 'latitude' );
		$has_longitude = $this->has_coordinate_input( $value, 'longitude' );
		$geo           = $this->local_business->normalize_geo_candidate( $value );

		if ( $has_latitude !== $has_longitude || ( $has_latitude && null === $geo ) ) {
			$errors[] = 'local-business-coordinates-invalid';
		}

		if ( null === $address || ! in_array( $type, SchemaLocalBusinessResolver::supported_types(), true ) ) {
			return null;
		}

		$normalized = array(
			'type'             => $type,
			'street_address'   => $address['streetAddress'],
			'address_locality' => $address['addressLocality'],
			'postal_code'      => $address['postalCode'],
			'address_country'  => $address['addressCountry'],
		);

		if ( isset( $address['addressRegion'] ) ) {
			$normalized['address_region'] = $address['addressRegion'];
		}

		foreach ( array( 'telephone', 'price_range' ) as $field ) {
			$text = $this->text_value( $value[ $field ] ?? null );
			if ( null !== $text ) {
				$normalized[ $field ] = $text;
			}
		}

		if ( null !== $geo ) {
			$normalized['latitude']  = $geo['latitude'];
			$normalized['longitude'] = $geo['longitude'];
		}

		return $normalized;
	}

	/**
	 * Validate crawler settings strictly before using Core normalization.
	 *
	 * @param mixed $value  Candidate crawler policy.
	 * @param array $errors Validation errors.
	 * @phpstan-param list<string> $errors
	 * @return array<string,string>
	 */
	private function validate_crawler_policy( mixed $value, array &$errors ): array {
		if ( ! is_array( $value ) ) {
			$errors[] = 'crawler-policy-must-be-map';
			return array();
		}

		$supported = $this->crawler_policy->supported_crawlers();
		$states    = array(
			CrawlerPolicyResolver::STATE_INHERIT,
			CrawlerPolicyResolver::STATE_ALLOW,
			CrawlerPolicyResolver::STATE_DISALLOW,
		);

		foreach ( $value as $crawler => $state ) {
			if ( ! is_string( $crawler ) || ! isset( $supported[ $crawler ] ) ) {
				$errors[] = 'unsupported-crawler:' . ( is_string( $crawler ) ? $crawler : 'non-string' );
				continue;
			}

			if ( ! is_string( $state ) || ! in_array( strtolower( trim( $state ) ), $states, true ) ) {
				$errors[] = 'invalid-crawler-state:' . $crawler;
			}
		}

		return $this->crawler_policy->sanitize_configuration( $value );
	}

	/**
	 * Require one explicit boolean opt-in.
	 *
	 * @param array<string,mixed> $input  Candidate input.
	 * @param string              $key    Boolean key.
	 * @param array               $errors Validation errors.
	 * @phpstan-param list<string> $errors
	 */
	private function boolean_value( array $input, string $key, array &$errors ): bool {
		if ( ! array_key_exists( $key, $input ) ) {
			return false;
		}

		if ( ! is_bool( $input[ $key ] ) ) {
			$errors[] = 'invalid-boolean:' . $key;
			return false;
		}

		return $input[ $key ];
	}

	/**
	 * Normalize one optional public text value.
	 *
	 * @param mixed $value Candidate text.
	 */
	private function text_value( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( wp_strip_all_tags( $value, true ) );
		return '' !== $value ? $value : null;
	}

	/**
	 * Report whether one coordinate field contains a scalar candidate value.
	 *
	 * @param array<string,mixed> $value Candidate LocalBusiness map.
	 * @param string              $key   Coordinate key.
	 */
	private function has_coordinate_input( array $value, string $key ): bool {
		if ( ! array_key_exists( $key, $value ) ) {
			return false;
		}

		$candidate = $value[ $key ];
		if ( ! is_string( $candidate ) && ! is_int( $candidate ) && ! is_float( $candidate ) ) {
			return false;
		}

		return '' !== trim( (string) $candidate );
	}
}
