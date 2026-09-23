<?php
/**
 * Existing-site migration dependency graph.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

use SeoGeo\MigrationBridge\Content\ContentDependencyScanner;

/**
 * Classifies detected site dependencies before any destructive migration action.
 */
final class DependencyGraphBuilder {
	/**
	 * Build a dependency graph from Phase 8A inventory and Phase 8B baseline.
	 *
	 * @param array<string, mixed>      $analysis Phase 8A Site Analyzer report.
	 * @param array<string, mixed>|null $baseline Phase 8B baseline snapshot.
	 * @return array<string,mixed>
	 */
	public function build( array $analysis, ?array $baseline = null ): array {
		if ( null === $baseline ) {
			$envelope = ( new BaselineSnapshotStore() )->latest();
			if ( is_array( $envelope ) && isset( $envelope['snapshot'] ) && is_array( $envelope['snapshot'] ) ) {
				$baseline = $envelope['snapshot'];
			}
		}

		$scanner     = new ContentDependencyScanner();
		$content     = $scanner->scan();
		$providers   = isset( $analysis['providers'] ) && is_array( $analysis['providers'] ) ? $analysis['providers'] : array();
		$authorities = ( new ProviderAuthorityResolver() )->resolve( $providers, $baseline );

		$components = array();

		foreach ( $this->builder_components( $analysis, $content ) as $component ) {
			$components[] = $component;
		}
		foreach ( $this->provider_components( $providers, $authorities ) as $component ) {
			$components[] = $component;
		}
		foreach ( $this->plugin_components( $analysis, $providers ) as $component ) {
			$components[] = $component;
		}

		usort(
			$components,
			static fn( array $left, array $right ): int => strcmp( (string) $left['component_id'], (string) $right['component_id'] )
		);

		return array(
			'schema_version' => 1,
			'mode'           => 'read-only-planning',
			'generated_at'   => gmdate( DATE_ATOM ),
			'components'     => $components,
			'content'        => $content,
			'edges'          => $this->dependency_edges( $content ),
			'authorities'    => $authorities,
			'baseline'       => array(
				'available' => null !== $baseline,
				'kind'      => is_array( $baseline ) && is_string( $baseline['kind'] ?? null ) ? $baseline['kind'] : null,
			),
			'summary'        => $this->summary( $components ),
			'safety'         => array(
				'mutations_performed'       => false,
				'plugin_removal_performed'  => false,
				'theme_switch_performed'    => false,
				'raw_content_exported'      => false,
				'builder_payload_exported'  => false,
				'automatic_removal_allowed' => false,
			),
		);
	}

	/**
	 * Build page-builder component decisions.
	 *
	 * @param array<string, mixed> $analysis Phase 8A report.
	 * @param array<string, mixed> $content  Content dependency scan.
	 * @return list<array<string,mixed>>
	 */
	private function builder_components( array $analysis, array $content ): array {
		$rows           = array();
		$builders       = isset( $analysis['builders'] ) && is_array( $analysis['builders'] ) ? $analysis['builders'] : array();
		$builder_counts = isset( $content['builder_counts'] ) && is_array( $content['builder_counts'] ) ? $content['builder_counts'] : array();

		foreach ( $builders as $builder ) {
			if ( ! is_array( $builder ) ) {
				continue;
			}

			$id = $builder['id'] ?? null;
			if ( ! is_string( $id ) || '' === $id ) {
				continue;
			}

			$installed     = true === ( $builder['installed'] ?? false );
			$active        = true === ( $builder['active'] ?? false );
			$resource_count = isset( $builder_counts[ $id ] ) ? (int) $builder_counts[ $id ] : 0;

			if ( 'native-blocks' === $id ) {
				$classification = 'KEEP';
				$reason         = 'destination-native-authority';
			} elseif ( $resource_count > 0 ) {
				$classification = 'MIGRATE';
				$reason         = 'content-coupled';
			} elseif ( $installed && $active ) {
				$classification = 'OPTIONAL';
				$reason         = 'active-without-detected-content-coupling';
			} elseif ( $installed ) {
				$classification = 'REMOVE-CANDIDATE';
				$reason         = 'installed-without-detected-content-coupling';
			} else {
				continue;
			}

			$rows[] = array(
				'component_id'    => 'builder:' . $id,
				'type'            => 'builder',
				'id'              => $id,
				'installed'       => $installed,
				'active'          => $active,
				'classification'  => $classification,
				'reason'          => $reason,
				'resource_count'  => $resource_count,
				'auto_remove'     => false,
				'manual_review'   => 'REMOVE-CANDIDATE' === $classification || 'OPTIONAL' === $classification,
			);
		}

		return $rows;
	}

	/**
	 * Build provider component decisions.
	 *
	 * @param array<string, mixed>              $providers   Provider catalog.
	 * @param list<array<string,mixed>>         $authorities Authority candidates.
	 * @return list<array<string,mixed>>
	 */
	private function provider_components( array $providers, array $authorities ): array {
		$rows             = array();
		$authority_by_cat = array();

		foreach ( $authorities as $authority ) {
			if ( is_array( $authority ) && is_string( $authority['category'] ?? null ) ) {
				$authority_by_cat[ $authority['category'] ] = $authority;
			}
		}

		foreach ( $providers as $category => $category_providers ) {
			if ( ! is_string( $category ) || ! is_array( $category_providers ) ) {
				continue;
			}

			foreach ( $category_providers as $provider ) {
				if ( ! is_array( $provider ) ) {
					continue;
				}

				$id = $provider['id'] ?? null;
				if ( ! is_string( $id ) || '' === $id ) {
					continue;
				}

				$active    = true === ( $provider['active'] ?? false );
				$authority = $authority_by_cat[ $category ] ?? null;
				$owner     = is_array( $authority ) ? ( $authority['owner_candidate'] ?? null ) : null;
				$conflict  = is_array( $authority ) && 'active-provider-conflict' === ( $authority['authority_status'] ?? null );

				$classification = $this->provider_classification( $category, $id, $active, is_string( $owner ) ? $owner : null, $conflict );

				$rows[] = array(
					'component_id'   => 'provider:' . $category . ':' . $id,
					'type'           => 'provider',
					'category'       => $category,
					'id'             => $id,
					'active'         => $active,
					'classification' => $classification,
					'reason'         => $this->provider_reason( $classification, $conflict ),
					'auto_remove'    => false,
					'manual_review'  => true,
				);
			}
		}

		return $rows;
	}

	/**
	 * Classify one provider conservatively.
	 */
	private function provider_classification( string $category, string $id, bool $active, ?string $owner, bool $conflict ): string {
		if ( $conflict ) {
			return 'UNKNOWN';
		}

		if ( in_array( $category, array( 'business_systems', 'forms', 'analytics', 'cache', 'security' ), true ) ) {
			return $active ? 'KEEP' : 'OPTIONAL';
		}

		if ( in_array( $category, array( 'seo', 'schema', 'multilingual', 'redirects' ), true ) ) {
			if ( $active && $owner === $id ) {
				return 'REPLACE';
			}
			return $active ? 'UNKNOWN' : 'REMOVE-CANDIDATE';
		}

		return $active ? 'UNKNOWN' : 'OPTIONAL';
	}

	/**
	 * Return a stable reason for a provider classification.
	 */
	private function provider_reason( string $classification, bool $conflict ): string {
		if ( $conflict ) {
			return 'multiple-active-authority-candidates';
		}

		return match ( $classification ) {
			'KEEP'             => 'business-or-operational-system-authority',
			'REPLACE'          => 'public-seo-geo-authority-must-be-migrated-before-removal',
			'REMOVE-CANDIDATE' => 'inactive-or-redundant-candidate-requires-review',
			'OPTIONAL'         => 'not-required-by-baseline-destination',
			default            => 'insufficient-evidence',
		};
	}

	/**
	 * Add unclassified plugins that were not represented by known providers.
	 *
	 * @param array<string, mixed> $analysis  Phase 8A report.
	 * @param array<string, mixed> $providers Provider catalog.
	 * @return list<array<string,mixed>>
	 */
	private function plugin_components( array $analysis, array $providers ): array {
		$plugins = isset( $analysis['plugins'] ) && is_array( $analysis['plugins'] ) ? $analysis['plugins'] : array();
		$known   = array();

		foreach ( $providers as $category_providers ) {
			if ( ! is_array( $category_providers ) ) {
				continue;
			}
			foreach ( $category_providers as $provider ) {
				if ( ! is_array( $provider ) || ! is_array( $provider['matched_plugins'] ?? null ) ) {
					continue;
				}
				foreach ( $provider['matched_plugins'] as $basename ) {
					if ( is_string( $basename ) ) {
						$known[ $basename ] = true;
					}
				}
			}
		}

		$rows = array();
		foreach ( $plugins as $plugin ) {
			if ( ! is_array( $plugin ) ) {
				continue;
			}

			$basename = $plugin['basename'] ?? null;
			if ( ! is_string( $basename ) || '' === $basename || isset( $known[ $basename ] ) || str_starts_with( $basename, 'seo-geo-migration-bridge/' ) ) {
				continue;
			}

			$rows[] = array(
				'component_id'   => 'plugin:' . $basename,
				'type'           => 'plugin',
				'id'             => $basename,
				'active'         => true === ( $plugin['active'] ?? false ),
				'classification' => 'UNKNOWN',
				'reason'         => 'plugin-not-yet-mapped-to-known-authority',
				'auto_remove'    => false,
				'manual_review'  => true,
			);
		}

		return $rows;
	}

	/**
	 * Build explicit resource dependency edges.
	 *
	 * @param array<string, mixed> $content Content dependency scan.
	 * @return list<array{from:string,to:string,kind:string}>
	 */
	private function dependency_edges( array $content ): array {
		$edges     = array();
		$resources = isset( $content['resources'] ) && is_array( $content['resources'] ) ? $content['resources'] : array();

		foreach ( $resources as $resource ) {
			if ( ! is_array( $resource ) || ! isset( $resource['object_id'] ) ) {
				continue;
			}

			$from     = 'resource:' . (int) $resource['object_id'];
			$builders = isset( $resource['builders'] ) && is_array( $resource['builders'] ) ? $resource['builders'] : array();
			foreach ( $builders as $builder ) {
				$id = is_array( $builder ) ? ( $builder['id'] ?? null ) : null;
				if ( is_string( $id ) && '' !== $id ) {
					$edges[] = array(
						'from' => $from,
						'to'   => 'builder:' . $id,
						'kind' => 'content-builder',
					);
				}
			}

			$shortcodes = isset( $resource['shortcodes'] ) && is_array( $resource['shortcodes'] ) ? $resource['shortcodes'] : array();
			foreach ( $shortcodes as $shortcode ) {
				if ( is_string( $shortcode ) && '' !== $shortcode ) {
					$edges[] = array(
						'from' => $from,
						'to'   => 'shortcode:' . $shortcode,
						'kind' => 'content-shortcode',
					);
				}
			}
		}

		usort(
			$edges,
			static fn( array $left, array $right ): int => strcmp(
				$left['from'] . '|' . $left['to'] . '|' . $left['kind'],
				$right['from'] . '|' . $right['to'] . '|' . $right['kind']
			)
		);

		return $edges;
	}

	/**
	 * Summarize classifications.
	 *
	 * @param list<array<string,mixed>> $components Classified components.
	 * @return array<string,int>
	 */
	private function summary( array $components ): array {
		$summary = array(
			'KEEP'             => 0,
			'REPLACE'          => 0,
			'MIGRATE'          => 0,
			'OPTIONAL'         => 0,
			'REMOVE-CANDIDATE' => 0,
			'UNKNOWN'          => 0,
		);

		foreach ( $components as $component ) {
			$classification = $component['classification'] ?? null;
			if ( is_string( $classification ) && isset( $summary[ $classification ] ) ) {
				++$summary[ $classification ];
			}
		}

		return $summary;
	}
}
