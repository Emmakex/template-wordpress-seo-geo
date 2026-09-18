<?php
/**
 * Validated native translation relationship.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

/**
 * Immutable value object for one validated reciprocal translation set.
 */
final class TranslationRelationship {
	/**
	 * Current post ID.
	 *
	 * @var int
	 */
	private int $current_post_id;

	/**
	 * Current post language code.
	 *
	 * @var string
	 */
	private string $current_language_code;

	/**
	 * Language-code to translated post-ID map.
	 *
	 * @var array<string, int>
	 */
	private array $members;

	/**
	 * Create a validated relationship value.
	 *
	 * @param int                $current_post_id       Current post ID.
	 * @param string             $current_language_code Current post language code.
	 * @param array<string, int> $members               Reciprocal translation members.
	 */
	public function __construct( int $current_post_id, string $current_language_code, array $members ) {
		$this->current_post_id       = $current_post_id;
		$this->current_language_code = $current_language_code;
		$this->members               = $members;
	}

	/**
	 * Return the current post ID.
	 */
	public function current_post_id(): int {
		return $this->current_post_id;
	}

	/**
	 * Return the current post language code.
	 */
	public function current_language_code(): string {
		return $this->current_language_code;
	}

	/**
	 * Return the normalized translation members.
	 *
	 * @return array<string, int>
	 */
	public function members(): array {
		return $this->members;
	}

	/**
	 * Resolve a translated post ID for one language.
	 *
	 * @param string $language_code Language code.
	 */
	public function post_id_for_language( string $language_code ): ?int {
		$normalized = strtolower( trim( $language_code ) );

		return $this->members[ $normalized ] ?? null;
	}

	/**
	 * Report whether the validated relationship contains a language.
	 *
	 * @param string $language_code Language code.
	 */
	public function has_language( string $language_code ): bool {
		return null !== $this->post_id_for_language( $language_code );
	}
}
