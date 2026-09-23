<?php
/**
 * Explicit migration parity difference allowlist.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Parity;

/**
 * Matches only explicitly documented path/field differences.
 */
final class ParityAllowlist {
	/**
	 * Normalized allowlist rules.
	 *
	 * @var list<array{path:string,field:string,reason:string}>
	 */
	private array $rules = array();

	/**
	 * Construct the allowlist.
	 *
	 * @param array<int,array<string,mixed>> $rules Raw allowlist rules.
	 */
	public function __construct( array $rules = array() ) {
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$path   = isset( $rule['path'] ) && is_string( $rule['path'] ) ? trim( $rule['path'] ) : '';
			$field  = isset( $rule['field'] ) && is_string( $rule['field'] ) ? trim( $rule['field'] ) : '';
			$reason = isset( $rule['reason'] ) && is_string( $rule['reason'] ) ? trim( $rule['reason'] ) : '';

			if ( '' === $path || '' === $field || '' === $reason ) {
				continue;
			}

			$this->rules[] = array(
				'path'   => $path,
				'field'  => $field,
				'reason' => $reason,
			);
		}
	}

	/**
	 * Return the explicit rule allowing one difference, if any.
	 *
	 * @return array{path:string,field:string,reason:string}|null
	 */
	public function match( string $path, string $field ): ?array {
		foreach ( $this->rules as $rule ) {
			if ( $this->matches( $rule['path'], $path ) && $this->matches( $rule['field'], $field ) ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Return normalized rules for reporting.
	 *
	 * @return list<array{path:string,field:string,reason:string}>
	 */
	public function rules(): array {
		return $this->rules;
	}

	/**
	 * Match exact value, global wildcard or trailing-prefix wildcard.
	 */
	private function matches( string $pattern, string $value ): bool {
		if ( '*' === $pattern || $pattern === $value ) {
			return true;
		}

		if ( str_ends_with( $pattern, '*' ) ) {
			return str_starts_with( $value, substr( $pattern, 0, -1 ) );
		}

		return false;
	}
}
