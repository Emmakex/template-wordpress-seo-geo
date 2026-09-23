<?php
/**
 * Public-provider authority resolver for migration planning.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

/**
 * Maps detected SEO/Schema/multilingual providers to observed public signals.
 *
 * This resolver is deliberately conservative: an exclusive active provider is
 * recorded as the authority candidate, not as proven callback-level ownership.
 */
final class ProviderAuthorityResolver {
	/**
	 * Resolve provider authority candidates.
	 *
	 * @param array<string, mixed>      $providers Site Analyzer provider report.
	 * @param array<string, mixed>|null $snapshot  Phase 8B baseline snapshot.
	 * @return list<array<string,mixed>>
	 */
	public function resolve( array $providers, ?array $snapshot ): array {
		$signals = $this->observed_signals( $snapshot );
		$rows    = array();

		foreach ( array( 'seo', 'schema', 'multilingual' ) as $category ) {
			$category_providers = $providers[ $category ] ?? array();
			if ( ! is_array( $category_providers ) ) {
				$category_providers = array();
			}

			$installed = array();
			$active    = array();

			foreach ( $category_providers as $provider ) {
				if ( ! is_array( $provider ) ) {
					continue;
				}

				$provider_id = $provider['id'] ?? null;
				if ( ! is_string( $provider_id ) || '' === $provider_id ) {
					continue;
				}

				$installed[] = $provider_id;
				if ( true === ( $provider['active'] ?? false ) ) {
					$active[] = $provider_id;
				}
			}

			sort( $installed );
			sort( $active );

			$status   = 'no-active-provider';
			$owner_id = null;

			if ( 1 === count( $active ) ) {
				$status   = 'exclusive-active-provider-candidate';
				$owner_id = $active[0];
			} elseif ( count( $active ) > 1 ) {
				$status = 'active-provider-conflict';
			} elseif ( array() !== $installed ) {
				$status = 'inactive-providers-only';
			}

			$rows[] = array(
				'category'                     => $category,
				'installed_providers'          => $installed,
				'active_providers'             => $active,
				'authority_status'             => $status,
				'owner_candidate'              => $owner_id,
				'observed_public_signals'      => $signals[ $category ],
				'requires_manual_confirmation' => null !== $owner_id || count( $active ) > 1,
			);
		}

		return $rows;
	}

	/**
	 * Summarize public signals present in the Phase 8B baseline.
	 *
	 * @param array<string, mixed>|null $snapshot Phase 8B snapshot.
	 * @return array{seo:list<string>,schema:list<string>,multilingual:list<string>}
	 */
	private function observed_signals( ?array $snapshot ): array {
		$found = array(
			'seo'          => array(),
			'schema'       => array(),
			'multilingual' => array(),
		);

		if ( null === $snapshot ) {
			return $found;
		}

		$pages = $snapshot['crawl']['pages'] ?? array();
		if ( ! is_array( $pages ) ) {
			return $found;
		}

		foreach ( $pages as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}

			if ( is_string( $page['title'] ?? null ) && '' !== trim( (string) $page['title'] ) ) {
				$found['seo'][] = 'title';
			}
			if ( is_string( $page['meta_description'] ?? null ) && '' !== trim( (string) $page['meta_description'] ) ) {
				$found['seo'][] = 'meta-description';
			}
			if ( is_string( $page['canonical'] ?? null ) && '' !== trim( (string) $page['canonical'] ) ) {
				$found['seo'][] = 'canonical';
			}
			if ( is_string( $page['robots'] ?? null ) && '' !== trim( (string) $page['robots'] ) ) {
				$found['seo'][] = 'robots';
			}
			if ( is_array( $page['open_graph'] ?? null ) && array() !== $page['open_graph'] ) {
				$found['seo'][] = 'open-graph';
			}

			$schema = $page['schema'] ?? null;
			if ( is_array( $schema ) && (int) ( $schema['block_count'] ?? 0 ) > 0 ) {
				$found['schema'][] = 'json-ld';
			}

			if ( is_string( $page['html_lang'] ?? null ) && '' !== trim( (string) $page['html_lang'] ) ) {
				$found['multilingual'][] = 'html-lang';
			}
			if ( is_array( $page['hreflang'] ?? null ) && array() !== $page['hreflang'] ) {
				$found['multilingual'][] = 'hreflang';
			}
		}

		foreach ( $found as $category => $category_signals ) {
			$category_signals = array_values( array_unique( $category_signals ) );
			sort( $category_signals );
			$found[ $category ] = $category_signals;
		}

		return $found;
	}
}
