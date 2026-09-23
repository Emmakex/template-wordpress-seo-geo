<?php
/**
 * Exact-difference allowlist for Phase 8F parity.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Parity;

/**
 * Approves only explicitly fingerprinted old-vs-new parity differences.
 */
final class ParityAllowlist {
	/**
	 * Normalized allowlist rules.
	 *
	 * @var list<array{id:string,path:string,signal:string,before_sha256:string,after_sha256:string,reason:string}>
	 */
	private array $rules = array();

	/**
	 * Construct an exact-difference allowlist.
	 *
	 * @param array<int,mixed> $rules Candidate allowlist rules.
	 */
	public function __construct( array $rules = array() ) {
		foreach ( $rules as $index => $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$path          = isset( $rule['path'] ) && is_string( $rule['path'] ) ? self::normalize_path( $rule['path'] ) : '';
			$signal        = isset( $rule['signal'] ) && is_string( $rule['signal'] ) ? self::normalize_signal( $rule['signal'] ) : '';
			$before_sha256 = isset( $rule['before_sha256'] ) && is_string( $rule['before_sha256'] ) ? strtolower( trim( $rule['before_sha256'] ) ) : '';
			$after_sha256  = isset( $rule['after_sha256'] ) && is_string( $rule['after_sha256'] ) ? strtolower( trim( $rule['after_sha256'] ) ) : '';
			$reason        = isset( $rule['reason'] ) && is_string( $rule['reason'] ) ? trim( $rule['reason'] ) : '';
			$id            = isset( $rule['id'] ) && is_string( $rule['id'] ) ? sanitize_key( $rule['id'] ) : 'rule-' . ( $index + 1 );

			if (
				'' === $path
				|| '' === $signal
				|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $before_sha256 )
				|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $after_sha256 )
				|| '' === $reason
			) {
				continue;
			}

			$this->rules[] = array(
				'id'            => $id,
				'path'          => $path,
				'signal'        => $signal,
				'before_sha256' => $before_sha256,
				'after_sha256'  => $after_sha256,
				'reason'        => $reason,
			);
		}
	}

	/**
	 * Return the matching exact approval for one difference.
	 *
	 * @param string $path   Resource path.
	 * @param string $signal Signal identifier.
	 * @param mixed  $before Baseline normalized value.
	 * @param mixed  $after  Candidate normalized value.
	 * @return array{id:string,reason:string}|null
	 */
	public function approval( string $path, string $signal, mixed $before, mixed $after ): ?array {
		$path          = self::normalize_path( $path );
		$signal        = self::normalize_signal( $signal );
		$before_sha256 = self::fingerprint( $before );
		$after_sha256  = self::fingerprint( $after );

		foreach ( $this->rules as $rule ) {
			if (
				$rule['path'] === $path
				&& $rule['signal'] === $signal
				&& hash_equals( $rule['before_sha256'], $before_sha256 )
				&& hash_equals( $rule['after_sha256'], $after_sha256 )
			) {
				return array(
					'id'     => $rule['id'],
					'reason' => $rule['reason'],
				);
			}
		}

		return null;
	}

	/**
	 * Normalize a signal identifier while preserving semantic separators.
	 *
	 * @param string $signal Candidate signal.
	 */
	public static function normalize_signal( string $signal ): string {
		$signal = strtolower( trim( $signal ) );
		$signal = str_replace( '.', '-', $signal );
		$signal = preg_replace( '/[^a-z0-9_-]+/', '-', $signal );
		$signal = is_string( $signal ) ? trim( $signal, '-' ) : '';

		return sanitize_key( $signal );
	}

	/**
	 * Fingerprint one normalized comparison value.
	 *
	 * @param mixed $value Comparison value.
	 */
	public static function fingerprint( mixed $value ): string {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			$encoded = serialize( $value );
		}

		return hash( 'sha256', $encoded );
	}

	/**
	 * Normalize a path/query identity without accepting wildcards.
	 *
	 * @param string $path Candidate path or URL.
	 */
	public static function normalize_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}

		$parsed_path = wp_parse_url( $path, PHP_URL_PATH );
		$query       = wp_parse_url( $path, PHP_URL_QUERY );

		$normalized = is_string( $parsed_path ) && '' !== $parsed_path ? $parsed_path : '/';
		if ( '/' !== $normalized ) {
			$normalized = '/' . ltrim( $normalized, '/' );
		}

		if ( is_string( $query ) && '' !== $query ) {
			$normalized .= '?' . $query;
		}

		return $normalized;
	}
}
