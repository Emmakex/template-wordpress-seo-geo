<?php
/**
 * Serialization-safe environment value rewriting.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Rewrites strings inside PHP serialized payloads without instantiating objects.
 */
final class SerializationSafeRewriter {
	private const MAX_DEPTH       = 128;
	private const MAX_ITEMS       = 200000;
	private const MAX_VALUE_BYTES = 67108864;

	/**
	 * Rewrite one database value.
	 *
	 * @param string               $value        Raw database value.
	 * @param array<string,string> $replacements Exact byte replacements.
	 * @return array{value:string,changed:bool,serialized:bool,unsupported:bool}
	 */
	public function rewrite( string $value, array $replacements ): array {
		$replacements = $this->normalize_replacements( $replacements );
		$serialized   = ( function_exists( 'is_serialized' ) && is_serialized( $value, true ) )
			|| $this->looks_serialized( $value );

		if ( array() === $replacements || ! $this->contains_search( $value, $replacements ) ) {
			return array(
				'value'       => $value,
				'changed'     => false,
				'serialized'  => $serialized,
				'unsupported' => false,
			);
		}

		if ( self::MAX_VALUE_BYTES < strlen( $value ) ) {
			return array(
				'value'       => $value,
				'changed'     => false,
				'serialized'  => $serialized,
				'unsupported' => $serialized,
			);
		}

		if ( ! $serialized ) {
			$rewritten = str_replace( array_keys( $replacements ), array_values( $replacements ), $value );

			return array(
				'value'       => $rewritten,
				'changed'     => $rewritten !== $value,
				'serialized'  => false,
				'unsupported' => false,
			);
		}

		$offset = 0;
		$parsed = $this->rewrite_token( $value, $offset, $replacements, 0 );
		if ( null === $parsed || strlen( $value ) !== $offset || true === $parsed['unsupported'] ) {
			return array(
				'value'       => $value,
				'changed'     => false,
				'serialized'  => true,
				'unsupported' => true,
			);
		}

		return array(
			'value'       => $parsed['value'],
			'changed'     => $parsed['changed'],
			'serialized'  => true,
			'unsupported' => false,
		);
	}

	/**
	 * Return whether one value still contains any source token.
	 *
	 * @param string               $value        Raw value.
	 * @param array<string,string> $replacements Exact byte replacements.
	 */
	public function contains_source( string $value, array $replacements ): bool {
		return $this->contains_search( $value, $this->normalize_replacements( $replacements ) );
	}

	/**
	 * Parse and rewrite one PHP serialization token.
	 *
	 * @param string               $input        Complete serialized input.
	 * @param int                  $offset       Mutable byte cursor.
	 * @param array<string,string> $replacements Exact replacements.
	 * @param int                  $depth        Recursion depth.
	 * @return array{value:string,changed:bool,unsupported:bool}|null
	 */
	private function rewrite_token( string $input, int &$offset, array $replacements, int $depth ): ?array {
		if ( self::MAX_DEPTH < $depth || $offset >= strlen( $input ) ) {
			return null;
		}

		$type = $input[ $offset ];
		if ( 'N' === $type ) {
			if ( 'N;' !== substr( $input, $offset, 2 ) ) {
				return null;
			}
			$offset += 2;

			return array(
				'value'       => 'N;',
				'changed'     => false,
				'unsupported' => false,
			);
		}

		if ( in_array( $type, array( 'b', 'i', 'd', 'r', 'R' ), true ) ) {
			$remaining = substr( $input, $offset );
			if ( 1 !== preg_match( '/^[bidrR]:[^;]+;/', $remaining, $match ) ) {
				return null;
			}
			$token   = $match[0];
			$offset += strlen( $token );

			return array(
				'value'       => $token,
				'changed'     => false,
				'unsupported' => false,
			);
		}

		if ( 's' === $type ) {
			return $this->rewrite_string_token( $input, $offset, $replacements );
		}

		if ( 'E' === $type ) {
			return $this->copy_string_like_token( $input, $offset, 'E' );
		}

		if ( 'a' === $type ) {
			return $this->rewrite_array_token( $input, $offset, $replacements, $depth );
		}

		if ( 'O' === $type ) {
			return $this->rewrite_object_token( $input, $offset, $replacements, $depth );
		}

		if ( 'C' === $type ) {
			return $this->rewrite_custom_object_token( $input, $offset, $replacements );
		}

		return null;
	}

	/**
	 * Rewrite one serialized string and recompute its byte length.
	 *
	 * @param string               $input        Serialized input.
	 * @param int                  $offset       Mutable cursor.
	 * @param array<string,string> $replacements Exact replacements.
	 * @return array{value:string,changed:bool,unsupported:bool}|null
	 */
	private function rewrite_string_token( string $input, int &$offset, array $replacements ): ?array {
		$remaining = substr( $input, $offset );
		if ( 1 !== preg_match( '/^s:(\d+):"/', $remaining, $match ) ) {
			return null;
		}

		$header_length = strlen( $match[0] );
		$length        = (int) $match[1];
		$data_start    = $offset + $header_length;
		$data_end      = $data_start + $length;
		if ( $data_end + 2 > strlen( $input ) || '";' !== substr( $input, $data_end, 2 ) ) {
			return null;
		}

		$data      = substr( $input, $data_start, $length );
		$rewritten = str_replace( array_keys( $replacements ), array_values( $replacements ), $data );
		$offset    = $data_end + 2;

		return array(
			'value'       => 's:' . strlen( $rewritten ) . ':"' . $rewritten . '";',
			'changed'     => $rewritten !== $data,
			'unsupported' => false,
		);
	}

	/**
	 * Copy one string-like serialization token whose payload must not be rewritten.
	 *
	 * @param string $input  Serialized input.
	 * @param int    $offset Mutable cursor.
	 * @param string $type   Token type.
	 * @return array{value:string,changed:bool,unsupported:bool}|null
	 */
	private function copy_string_like_token( string $input, int &$offset, string $type ): ?array {
		$remaining = substr( $input, $offset );
		$pattern   = '/^' . preg_quote( $type, '/' ) . ':(\d+):"/';
		if ( 1 !== preg_match( $pattern, $remaining, $match ) ) {
			return null;
		}

		$length     = (int) $match[1];
		$data_start = $offset + strlen( $match[0] );
		$data_end   = $data_start + $length;
		if ( $data_end + 2 > strlen( $input ) || '";' !== substr( $input, $data_end, 2 ) ) {
			return null;
		}

		$token  = substr( $input, $offset, ( $data_end + 2 ) - $offset );
		$offset = $data_end + 2;

		return array(
			'value'       => $token,
			'changed'     => false,
			'unsupported' => false,
		);
	}

	/**
	 * Rewrite one serialized array recursively.
	 *
	 * @param string               $input        Serialized input.
	 * @param int                  $offset       Mutable cursor.
	 * @param array<string,string> $replacements Exact replacements.
	 * @param int                  $depth        Recursion depth.
	 * @return array{value:string,changed:bool,unsupported:bool}|null
	 */
	private function rewrite_array_token( string $input, int &$offset, array $replacements, int $depth ): ?array {
		$remaining = substr( $input, $offset );
		if ( 1 !== preg_match( '/^a:(\d+):{/', $remaining, $match ) ) {
			return null;
		}

		$count = (int) $match[1];
		if ( self::MAX_ITEMS < $count ) {
			return null;
		}

		$offset += strlen( $match[0] );
		$output  = $match[0];
		$changed = false;

		for ( $index = 0; $index < $count * 2; ++$index ) {
			$child = $this->rewrite_token( $input, $offset, $replacements, $depth + 1 );
			if ( null === $child || true === $child['unsupported'] ) {
				return null;
			}
			$output .= $child['value'];
			$changed = $changed || $child['changed'];
		}

		if ( '}' !== ( $input[ $offset ] ?? null ) ) {
			return null;
		}
		++$offset;

		return array(
			'value'       => $output . '}',
			'changed'     => $changed,
			'unsupported' => false,
		);
	}

	/**
	 * Rewrite serialized object properties without instantiating the object.
	 *
	 * @param string               $input        Serialized input.
	 * @param int                  $offset       Mutable cursor.
	 * @param array<string,string> $replacements Exact replacements.
	 * @param int                  $depth        Recursion depth.
	 * @return array{value:string,changed:bool,unsupported:bool}|null
	 */
	private function rewrite_object_token( string $input, int &$offset, array $replacements, int $depth ): ?array {
		$remaining = substr( $input, $offset );
		if ( 1 !== preg_match( '/^O:(\d+):"/', $remaining, $match ) ) {
			return null;
		}

		$class_length = (int) $match[1];
		$class_start  = $offset + strlen( $match[0] );
		$class_end    = $class_start + $class_length;
		if ( $class_end + 2 > strlen( $input ) || '":' !== substr( $input, $class_end, 2 ) ) {
			return null;
		}

		$after_class = substr( $input, $class_end + 2 );
		if ( 1 !== preg_match( '/^(\d+):{/', $after_class, $count_match ) ) {
			return null;
		}

		$count = (int) $count_match[1];
		if ( self::MAX_ITEMS < $count ) {
			return null;
		}

		$prefix_end = $class_end + 2 + strlen( $count_match[0] );
		$output     = substr( $input, $offset, $prefix_end - $offset );
		$offset     = $prefix_end;
		$changed    = false;

		for ( $index = 0; $index < $count * 2; ++$index ) {
			$child = $this->rewrite_token( $input, $offset, $replacements, $depth + 1 );
			if ( null === $child || true === $child['unsupported'] ) {
				return null;
			}
			$output .= $child['value'];
			$changed = $changed || $child['changed'];
		}

		if ( '}' !== ( $input[ $offset ] ?? null ) ) {
			return null;
		}
		++$offset;

		return array(
			'value'       => $output . '}',
			'changed'     => $changed,
			'unsupported' => false,
		);
	}

	/**
	 * Copy a custom-serialized object only when its opaque payload needs no rewrite.
	 *
	 * @param string               $input        Serialized input.
	 * @param int                  $offset       Mutable cursor.
	 * @param array<string,string> $replacements Exact replacements.
	 * @return array{value:string,changed:bool,unsupported:bool}|null
	 */
	private function rewrite_custom_object_token( string $input, int &$offset, array $replacements ): ?array {
		$remaining = substr( $input, $offset );
		if ( 1 !== preg_match( '/^C:(\d+):"/', $remaining, $match ) ) {
			return null;
		}

		$class_length = (int) $match[1];
		$class_start  = $offset + strlen( $match[0] );
		$class_end    = $class_start + $class_length;
		if ( $class_end + 2 > strlen( $input ) || '":' !== substr( $input, $class_end, 2 ) ) {
			return null;
		}

		$after_class = substr( $input, $class_end + 2 );
		if ( 1 !== preg_match( '/^(\d+):{/', $after_class, $payload_match ) ) {
			return null;
		}

		$payload_length = (int) $payload_match[1];
		$payload_start  = $class_end + 2 + strlen( $payload_match[0] );
		$payload_end    = $payload_start + $payload_length;
		if ( $payload_end >= strlen( $input ) || '}' !== $input[ $payload_end ] ) {
			return null;
		}

		$payload = substr( $input, $payload_start, $payload_length );
		$token   = substr( $input, $offset, ( $payload_end + 1 ) - $offset );
		$offset  = $payload_end + 1;

		return array(
			'value'       => $token,
			'changed'     => false,
			'unsupported' => $this->contains_search( $payload, $replacements ),
		);
	}

	/**
	 * Conservatively recognize complete PHP serialization token prefixes that WordPress
	 * may not classify consistently across versions (notably custom Serializable objects).
	 *
	 * Full structural validity is still proven by rewrite_token() consuming all bytes.
	 *
	 * @param string $value Raw candidate value.
	 */
	private function looks_serialized( string $value ): bool {
		if ( 'N;' === $value ) {
			return true;
		}

		return 1 === preg_match( '/^(?:[abisdOCErR]):/', $value );
	}

	/**
	 * Normalize replacement pairs longest-source-first.
	 *
	 * @param array<string,string> $replacements Raw replacements.
	 * @return array<string,string>
	 */
	private function normalize_replacements( array $replacements ): array {
		$normalized = array();
		foreach ( $replacements as $search => $replace ) {
			if ( ! is_string( $search ) || ! is_string( $replace ) || '' === $search || $search === $replace ) {
				continue;
			}
			$normalized[ $search ] = $replace;
		}

		uksort(
			$normalized,
			static fn( string $left, string $right ): int => strlen( $right ) <=> strlen( $left )
		);

		return $normalized;
	}

	/**
	 * Whether any replacement source exists in a value.
	 *
	 * @param string               $value        Value.
	 * @param array<string,string> $replacements Normalized replacements.
	 */
	private function contains_search( string $value, array $replacements ): bool {
		foreach ( array_keys( $replacements ) as $search ) {
			if ( str_contains( $value, $search ) ) {
				return true;
			}
		}

		return false;
	}
}
