<?php
/**
 * Serialization-safe Portable Clone environment value rewriting.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use RuntimeException;

/**
 * Rewrites environment-bound strings without corrupting PHP serialized payloads.
 */
final class SerializedValueRewriter {
	private const MAX_SERIALIZED_BYTES = 4194304;
	private const MAX_DEPTH            = 64;
	private const MAX_ITEMS            = 100000;

	/**
	 * Source home URL.
	 *
	 * @var string
	 */
	private string $source_home;

	/**
	 * Source site URL.
	 *
	 * @var string
	 */
	private string $source_site;

	/**
	 * Destination home URL.
	 *
	 * @var string
	 */
	private string $destination_home;

	/**
	 * Destination site URL.
	 *
	 * @var string
	 */
	private string $destination_site;

	/**
	 * Source database prefix.
	 *
	 * @var string
	 */
	private string $source_prefix;

	/**
	 * Destination database prefix.
	 *
	 * @var string
	 */
	private string $destination_prefix;

	/**
	 * Traversed item counter for one value.
	 *
	 * @var int
	 */
	private int $items = 0;

	/**
	 * Construct transformer.
	 */
	public function __construct(
		string $source_home,
		string $source_site,
		string $destination_home,
		string $destination_site,
		string $source_prefix,
		string $destination_prefix
	) {
		$this->source_home        = untrailingslashit( $source_home );
		$this->source_site        = untrailingslashit( $source_site );
		$this->destination_home   = untrailingslashit( $destination_home );
		$this->destination_site   = untrailingslashit( $destination_site );
		$this->source_prefix      = $source_prefix;
		$this->destination_prefix = $destination_prefix;
	}

	/**
	 * Rewrite one database value.
	 *
	 * @param mixed $value Source value.
	 * @return array{value:mixed,changed:bool,serialized:bool,key_changes:int}
	 *
	 * @throws RuntimeException When serialized content is unsafe or structurally invalid.
	 */
	public function rewrite( mixed $value ): array {
		$this->items = 0;

		if ( ! is_string( $value ) ) {
			return array(
				'value'       => $value,
				'changed'     => false,
				'serialized'  => false,
				'key_changes' => 0,
			);
		}

		if ( ! is_serialized( $value ) ) {
			$rewritten = $this->rewrite_string( $value );

			return array(
				'value'       => $rewritten,
				'changed'     => $rewritten !== $value,
				'serialized'  => false,
				'key_changes' => 0,
			);
		}

		if ( self::MAX_SERIALIZED_BYTES < strlen( $value ) ) {
			throw new RuntimeException( 'serialized-value-too-large' );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Package data is trusted only after cryptographic verification; classes remain disabled and objects are rejected below.
		$decoded = @unserialize( $value, array( 'allowed_classes' => false ) );
		if ( false === $decoded && 'b:0;' !== $value ) {
			throw new RuntimeException( 'serialized-value-invalid' );
		}

		if ( is_object( $decoded ) || is_resource( $decoded ) ) {
			throw new RuntimeException( 'serialized-object-not-supported' );
		}

		$key_changes = 0;
		$rewritten   = $this->rewrite_recursive( $decoded, 0, $key_changes );

		if ( $rewritten === $decoded ) {
			return array(
				'value'       => $value,
				'changed'     => false,
				'serialized'  => true,
				'key_changes' => 0,
			);
		}

		return array(
			'value'       => serialize( $rewritten ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Re-serialization is required to repair string lengths after safe recursive rewriting.
			'changed'     => true,
			'serialized'  => true,
			'key_changes' => $key_changes,
		);
	}

	/**
	 * Recursively rewrite arrays/scalars while preserving serialized structure.
	 *
	 * @param mixed $value       Value.
	 * @param int   $depth       Current recursion depth.
	 * @param int   $key_changes Rewritten array-key counter.
	 * @return mixed
	 *
	 * @throws RuntimeException On unsupported/cyclic or excessive structures.
	 */
	private function rewrite_recursive( mixed $value, int $depth, int &$key_changes ): mixed {
		if ( self::MAX_DEPTH < $depth ) {
			throw new RuntimeException( 'serialized-depth-limit' );
		}
		if ( ++$this->items > self::MAX_ITEMS ) {
			throw new RuntimeException( 'serialized-item-limit' );
		}

		if ( is_object( $value ) || is_resource( $value ) ) {
			throw new RuntimeException( 'serialized-object-not-supported' );
		}
		if ( is_string( $value ) ) {
			return $this->rewrite_string( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$result = array();
		foreach ( $value as $key => $child ) {
			$new_key = $key;
			if ( is_string( $key ) ) {
				$new_key = $this->rewrite_key( $key );
				if ( $new_key !== $key ) {
					++$key_changes;
				}
			}
			if ( array_key_exists( $new_key, $result ) ) {
				throw new RuntimeException( 'serialized-key-collision' );
			}
			$result[ $new_key ] = $this->rewrite_recursive( $child, $depth + 1, $key_changes );
		}

		return $result;
	}

	/**
	 * Rewrite a string payload.
	 */
	private function rewrite_string( string $value ): string {
		$map = $this->replacement_map();
		$value = str_replace( array_keys( $map ), array_values( $map ), $value );

		return $this->rewrite_prefix_token( $value );
	}

	/**
	 * Rewrite array keys conservatively.
	 */
	private function rewrite_key( string $key ): string {
		$key = $this->rewrite_string( $key );

		return $this->rewrite_prefix_token( $key );
	}

	/**
	 * Rewrite database-prefix tokens only when the whole string is identifier-like.
	 */
	private function rewrite_prefix_token( string $value ): string {
		if (
			'' === $this->source_prefix
			|| $this->source_prefix === $this->destination_prefix
			|| ! str_starts_with( $value, $this->source_prefix )
		) {
			return $value;
		}

		$suffix = substr( $value, strlen( $this->source_prefix ) );
		if ( '' === $suffix || 1 !== preg_match( '/^[A-Za-z0-9_:-]+$/', $suffix ) ) {
			return $value;
		}

		return $this->destination_prefix . $suffix;
	}

	/**
	 * Build longest-first URL replacement map, including JSON-escaped slash forms.
	 *
	 * @return array<string,string>
	 */
	private function replacement_map(): array {
		$map = array();
		foreach (
			array(
				$this->source_home => $this->destination_home,
				$this->source_site => $this->destination_site,
			) as $source => $destination
		) {
			if ( '' === $source || $source === $destination ) {
				continue;
			}

			$map[ $source . '/' ] = $destination . '/';
			$map[ $source ]       = $destination;

			$escaped_source      = str_replace( '/', '\\/', $source );
			$escaped_destination = str_replace( '/', '\\/', $destination );
			$map[ $escaped_source . '\\/' ] = $escaped_destination . '\\/';
			$map[ $escaped_source ]            = $escaped_destination;
		}

		uksort(
			$map,
			static fn( string $left, string $right ): int => strlen( $right ) <=> strlen( $left )
		);

		return $map;
	}
}
