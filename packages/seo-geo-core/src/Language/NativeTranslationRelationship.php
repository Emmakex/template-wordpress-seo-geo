<?php
/**
 * Validated native translation relationship.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

/**
 * Immutable view of one validated translation group.
 */
final class NativeTranslationRelationship {
	/**
	 * Explicit translation group identifier.
	 *
	 * @var string
	 */
	private string $group_id;

	/**
	 * Language code assigned to the current resource.
	 *
	 * @var string
	 */
	private string $current_language_code;

	/**
	 * Published translations keyed by configured language code.
	 *
	 * @var array<string, int>
	 */
	private array $translations;

	/**
	 * Create a validated relationship.
	 *
	 * @param string             $group_id              Explicit group identifier.
	 * @param string             $current_language_code Current resource language.
	 * @param array<string, int> $translations          Published translations.
	 */
	public function __construct( string $group_id, string $current_language_code, array $translations ) {
		$this->group_id              = $group_id;
		$this->current_language_code = $current_language_code;
		$this->translations          = $translations;
	}

	/**
	 * Return the explicit group identifier.
	 */
	public function group_id(): string {
		return $this->group_id;
	}

	/**
	 * Return the language assigned to the current resource.
	 */
	public function current_language_code(): string {
		return $this->current_language_code;
	}

	/**
	 * Return published translations keyed by language.
	 *
	 * @return array<string, int>
	 */
	public function translations(): array {
		return $this->translations;
	}

	/**
	 * Resolve the translated resource ID for a language.
	 *
	 * @param string $language_code Configured language code.
	 */
	public function post_id_for( string $language_code ): ?int {
		return $this->translations[ strtolower( trim( $language_code ) ) ] ?? null;
	}
}
