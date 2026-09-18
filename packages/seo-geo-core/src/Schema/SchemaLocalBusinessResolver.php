<?php
/**
 * Native LocalBusiness Schema resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Schema;

/**
 * Resolves one explicitly configured physical LocalBusiness identity.
 */
final class SchemaLocalBusinessResolver {
	/**
	 * WordPress option containing explicit LocalBusiness fields.
	 */
	public const OPTION_NAME = 'seo_geo_schema_local_business';

	/**
	 * Supported conservative LocalBusiness types for the native baseline.
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_TYPES = array(
		'LocalBusiness',
		'AnimalShelter',
		'AutomotiveBusiness',
		'AutoBodyShop',
		'AutoDealer',
		'AutoPartsStore',
		'AutoRental',
		'AutoRepair',
		'AutoWash',
		'GasStation',
		'MotorcycleDealer',
		'MotorcycleRepair',
		'ChildCare',
		'Dentist',
		'DryCleaningOrLaundry',
		'EmergencyService',
		'EmploymentAgency',
		'EntertainmentBusiness',
		'FinancialService',
		'FoodEstablishment',
		'GovernmentOffice',
		'HealthAndBeautyBusiness',
		'HomeAndConstructionBusiness',
		'Electrician',
		'GeneralContractor',
		'HVACBusiness',
		'HousePainter',
		'Locksmith',
		'MovingCompany',
		'Plumber',
		'RoofingContractor',
		'InternetCafe',
		'LegalService',
		'Library',
		'LodgingBusiness',
		'MedicalBusiness',
		'ProfessionalService',
		'RadioStation',
		'RealEstateAgent',
		'RecyclingCenter',
		'SelfStorage',
		'ShoppingCenter',
		'SportsActivityLocation',
		'Store',
		'TelevisionStation',
		'TouristInformationCenter',
		'TravelAgency',
	);

	/**
	 * Stable node-ID generator.
	 *
	 * @var SchemaNodeIds
	 */
	private SchemaNodeIds $ids;

	/**
	 * Site identity selection authority.
	 *
	 * @var SchemaIdentityResolver
	 */
	private SchemaIdentityResolver $identity;

	/**
	 * Create the resolver.
	 *
	 * @param SchemaNodeIds          $ids      Stable Schema node-ID generator.
	 * @param SchemaIdentityResolver $identity Site identity selection authority.
	 */
	public function __construct( SchemaNodeIds $ids, SchemaIdentityResolver $identity ) {
		$this->ids      = $ids;
		$this->identity = $identity;
	}

	/**
	 * Resolve the configured LocalBusiness entity.
	 *
	 * Name and URL are taken from visible WordPress site state. A physical
	 * address is mandatory; optional fields are emitted only when individually
	 * valid. No review/rating/image data is inferred.
	 *
	 * @return array<string, mixed>|null
	 */
	public function resolve(): ?array {
		if ( SchemaIdentityResolver::SITE_ENTITY_LOCAL_BUSINESS !== $this->identity->site_entity_type() ) {
			return null;
		}

		$configuration = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $configuration ) ) {
			return null;
		}

		$name = $this->text( get_bloginfo( 'name' ) );
		if ( '' === $name ) {
			return null;
		}

		$address = $this->address( $configuration );
		if ( null === $address ) {
			return null;
		}

		$entity = array(
			'@type'   => $this->business_type( $configuration['type'] ?? null ),
			'@id'     => $this->ids->local_business(),
			'name'    => $name,
			'url'     => home_url( '/' ),
			'address' => $address,
		);

		$telephone = $this->text_value( $configuration['telephone'] ?? null );
		if ( null !== $telephone ) {
			$entity['telephone'] = $telephone;
		}

		$price_range = $this->text_value( $configuration['price_range'] ?? null );
		if ( null !== $price_range && strlen( $price_range ) < 100 ) {
			$entity['priceRange'] = $price_range;
		}

		$geo = $this->geo( $configuration );
		if ( null !== $geo ) {
			$entity['geo'] = $geo;
		}

		$opening_hours = $this->opening_hours( $configuration['opening_hours'] ?? null );
		if ( array() !== $opening_hours ) {
			$entity['openingHoursSpecification'] = $opening_hours;
		}

		return $entity;
	}

	/**
	 * Resolve one supported LocalBusiness type.
	 *
	 * @param mixed $value Configured type.
	 */
	private function business_type( $value ): string {
		$type = $this->text_value( $value );

		if ( null === $type || ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			return 'LocalBusiness';
		}

		return $type;
	}

	/**
	 * Resolve the mandatory physical PostalAddress.
	 *
	 * @param array<string, mixed> $configuration LocalBusiness configuration.
	 * @return array<string, string>|null
	 */
	private function address( array $configuration ): ?array {
		$street   = $this->text_value( $configuration['street_address'] ?? null );
		$city     = $this->text_value( $configuration['address_locality'] ?? null );
		$postcode = $this->text_value( $configuration['postal_code'] ?? null );
		$country  = $this->text_value( $configuration['address_country'] ?? null );

		if ( null === $street || null === $city || null === $postcode || null === $country ) {
			return null;
		}

		$address = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $street,
			'addressLocality' => $city,
			'postalCode'      => $postcode,
			'addressCountry'  => $country,
		);

		$region = $this->text_value( $configuration['address_region'] ?? null );
		if ( null !== $region ) {
			$address['addressRegion'] = $region;
		}

		return $address;
	}

	/**
	 * Resolve optional coordinates only when both are precise and valid.
	 *
	 * @param array<string, mixed> $configuration LocalBusiness configuration.
	 * @return array<string, float>|null
	 */
	private function geo( array $configuration ): ?array {
		$latitude  = $this->coordinate( $configuration['latitude'] ?? null, -90.0, 90.0 );
		$longitude = $this->coordinate( $configuration['longitude'] ?? null, -180.0, 180.0 );

		if ( null === $latitude || null === $longitude ) {
			return null;
		}

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $latitude,
			'longitude' => $longitude,
		);
	}

	/**
	 * Normalize one coordinate with at least five decimal places.
	 *
	 * @param mixed $value Candidate coordinate.
	 * @param float $min   Minimum accepted value.
	 * @param float $max   Maximum accepted value.
	 */
	private function coordinate( $value, float $min, float $max ): ?float {
		if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}

		$text = trim( (string) $value );
		if ( 1 !== preg_match( '/^-?\d+\.\d{5,}$/', $text ) ) {
			return null;
		}

		$number = (float) $text;
		if ( $number < $min || $number > $max ) {
			return null;
		}

		return $number;
	}

	/**
	 * Resolve valid opening-hours specifications.
	 *
	 * @param mixed $value Configured opening-hours array.
	 * @return array<int, array<string, mixed>>
	 */
	private function opening_hours( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$resolved = array();

		foreach ( $value as $specification ) {
			if ( ! is_array( $specification ) ) {
				continue;
			}

			$days   = $this->days( $specification['days'] ?? null );
			$opens  = $this->time( $specification['opens'] ?? null );
			$closes = $this->time( $specification['closes'] ?? null );

			if ( array() === $days || null === $opens || null === $closes ) {
				continue;
			}

			$resolved[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => array_map(
					static fn( string $day ): string => 'https://schema.org/' . $day,
					$days
				),
				'opens'     => $opens,
				'closes'    => $closes,
			);
		}

		return $resolved;
	}

	/**
	 * Normalize configured day names.
	 *
	 * @param mixed $value Candidate day list.
	 * @return array<int, string>
	 */
	private function days( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$valid = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
		$days  = array();

		foreach ( $value as $day ) {
			if ( is_string( $day ) && in_array( $day, $valid, true ) && ! in_array( $day, $days, true ) ) {
				$days[] = $day;
			}
		}

		return $days;
	}

	/**
	 * Normalize Schema Time values to HH:MM:SS.
	 *
	 * @param mixed $value Candidate time.
	 */
	private function time( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$time = trim( $value );
		if ( 1 !== preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time ) ) {
			return null;
		}

		return 5 === strlen( $time ) ? $time . ':00' : $time;
	}

	/**
	 * Normalize optional text.
	 *
	 * @param mixed $value Raw value.
	 */
	private function text_value( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$text = $this->text( $value );

		return '' !== $text ? $text : null;
	}

	/**
	 * Normalize visible/configured text for Schema output.
	 *
	 * @param string $value Raw text.
	 */
	private function text( string $value ): string {
		return trim( wp_strip_all_tags( $value, true ) );
	}
}
