<?php
/**
 * Phase 8F SEO/GEO parity comparator.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Parity;

/**
 * Compares persisted legacy output with a candidate public-output snapshot.
 */
final class SeoParityEngine {
	/**
	 * Compare two public-output snapshots.
	 *
	 * @param array<string,mixed> $baseline        Persisted Phase 8B baseline snapshot.
	 * @param array<string,mixed> $candidate       Candidate public-output snapshot.
	 * @param array<int,mixed>    $allowlist_rules Explicit exact-difference approvals.
	 * @return array<string,mixed>
	 */
	public function compare( array $baseline, array $candidate, array $allowlist_rules = array() ): array {
		$allowlist = new ParityAllowlist( $allowlist_rules );
		$blockers  = array();

		if ( ! $this->snapshot_valid( $baseline ) ) {
			$blockers[] = 'baseline-snapshot-invalid';
		}
		if ( ! $this->snapshot_valid( $candidate ) ) {
			$blockers[] = 'candidate-snapshot-invalid';
		}

		if ( array() !== $blockers ) {
			return $this->blocked_report( $blockers );
		}

		$baseline_pages  = $this->pages_by_path( $baseline );
		$candidate_pages = $this->pages_by_path( $candidate );
		$paths           = array_values( array_unique( array_merge( array_keys( $baseline_pages ), array_keys( $candidate_pages ) ) ) );
		sort( $paths );

		$differences = array();
		$page_rows   = array();

		foreach ( $paths as $path ) {
			$before = $baseline_pages[ $path ] ?? null;
			$after  = $candidate_pages[ $path ] ?? null;

			if ( null === $before || null === $after ) {
				$this->record_difference(
					$differences,
					$allowlist,
					$path,
					'presence',
					null === $before ? 'missing' : 'present',
					null === $after ? 'missing' : 'present',
					'public-url-presence-changed'
				);
				$page_rows[] = array(
					'path'   => $path,
					'status' => $this->path_status( $differences, $path ),
				);
				continue;
			}

			foreach ( $this->page_signals( $before, $after ) as $signal => $values ) {
				if ( $values['before'] === $values['after'] ) {
					continue;
				}

				$this->record_difference(
					$differences,
					$allowlist,
					$path,
					$signal,
					$values['before'],
					$values['after'],
					'page-signal-changed'
				);
			}

			foreach ( $this->schema_conflicts( $after ) as $conflict ) {
				$this->record_difference(
					$differences,
					$allowlist,
					$path,
					$conflict['signal'],
					$conflict['before'],
					$conflict['after'],
					$conflict['reason']
				);
			}

			foreach ( $this->known_broken_links( $after, $candidate_pages ) as $broken_path ) {
				$this->record_difference(
					$differences,
					$allowlist,
					$path,
					'internal-link-broken:' . $broken_path,
					false,
					true,
					'candidate-internal-link-target-is-broken'
				);
			}

			$page_rows[] = array(
				'path'   => $path,
				'status' => $this->path_status( $differences, $path ),
			);
		}

		$this->compare_redirects( $baseline, $candidate, $allowlist, $differences );
		$this->compare_sitemaps( $baseline, $candidate, $allowlist, $differences );

		$summary = array(
			'passed'      => 0,
			'allowed'     => 0,
			'regressions' => 0,
			'unknown'     => 0,
		);

		foreach ( $differences as $difference ) {
			if ( 'allowed' === $difference['status'] ) {
				++$summary['allowed'];
			} elseif ( 'regression' === $difference['status'] ) {
				++$summary['regressions'];
			} else {
				++$summary['unknown'];
			}
		}

		foreach ( $page_rows as $page_row ) {
			if ( 'pass' === $page_row['status'] ) {
				++$summary['passed'];
			}
		}

		$accepted = 0 === $summary['regressions']
			&& 0 === $summary['unknown']
			&& array() === $blockers;

		return array(
			'schema_version' => 1,
			'mode'           => 'seo-geo-parity',
			'accepted'       => $accepted,
			'blockers'       => $blockers,
			'summary'        => $summary,
			'pages'          => $page_rows,
			'differences'    => $differences,
			'checks'         => array(
				'url_status_indexability' => true,
				'canonical_robots'        => true,
				'hreflang'                => true,
				'title_meta'              => true,
				'schema_conflicts'        => true,
				'redirect_map'            => true,
				'internal_links'          => true,
				'sitemap_consistency'     => true,
			),
			'external_gates' => array(
				'performance'   => 'required-on-representative-migrated-pages',
				'accessibility' => 'required-on-representative-migrated-pages',
			),
			'safety'         => array(
				'mutations_performed'                 => false,
				'production_cutover_allowed'          => false,
				'allowlist_requires_exact_fingerprints' => true,
				'legacy_output_is_authority'          => false,
			),
		);
	}

	/**
	 * Verify the minimum snapshot contract.
	 *
	 * @param array<string,mixed> $snapshot Snapshot candidate.
	 */
	private function snapshot_valid( array $snapshot ): bool {
		return 1 === ( $snapshot['schema_version'] ?? null )
			&& isset( $snapshot['crawl']['pages'] )
			&& is_array( $snapshot['crawl']['pages'] );
	}

	/**
	 * Build page map keyed by origin-neutral path/query.
	 *
	 * @param array<string,mixed> $snapshot Public-output snapshot.
	 * @return array<string,array<string,mixed>>
	 */
	private function pages_by_path( array $snapshot ): array {
		$pages = array();
		$rows  = isset( $snapshot['crawl']['pages'] ) && is_array( $snapshot['crawl']['pages'] ) ? $snapshot['crawl']['pages'] : array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['url'] ) || ! is_string( $row['url'] ) ) {
				continue;
			}

			$path = ParityAllowlist::normalize_path( $row['url'] );
			if ( '' !== $path ) {
				$pages[ $path ] = $row;
			}
		}

		ksort( $pages, SORT_STRING );
		return $pages;
	}

	/**
	 * Normalize comparison signals from one matched page.
	 *
	 * @param array<string,mixed> $before Baseline page.
	 * @param array<string,mixed> $after  Candidate page.
	 * @return array<string,array{before:mixed,after:mixed}>
	 */
	private function page_signals( array $before, array $after ): array {
		return array(
			'http-status' => array(
				'before' => (int) ( $before['http']['status'] ?? 0 ),
				'after'  => (int) ( $after['http']['status'] ?? 0 ),
			),
			'indexability' => array(
				'before' => $this->normalize_indexability( $before['indexability'] ?? null ),
				'after'  => $this->normalize_indexability( $after['indexability'] ?? null ),
			),
			'canonical' => array(
				'before' => $this->normalize_optional_url( $before['canonical'] ?? null ),
				'after'  => $this->normalize_optional_url( $after['canonical'] ?? null ),
			),
			'robots' => array(
				'before' => $this->normalize_directives( $before['robots'] ?? null ),
				'after'  => $this->normalize_directives( $after['robots'] ?? null ),
			),
			'title' => array(
				'before' => $before['title'] ?? null,
				'after'  => $after['title'] ?? null,
			),
			'meta-description' => array(
				'before' => $before['meta_description'] ?? null,
				'after'  => $after['meta_description'] ?? null,
			),
			'html-lang' => array(
				'before' => $before['html_lang'] ?? null,
				'after'  => $after['html_lang'] ?? null,
			),
			'hreflang' => array(
				'before' => $this->normalize_hreflang( $before['hreflang'] ?? array() ),
				'after'  => $this->normalize_hreflang( $after['hreflang'] ?? array() ),
			),
			'open-graph' => array(
				'before' => $this->normalize_open_graph( $before['open_graph'] ?? array() ),
				'after'  => $this->normalize_open_graph( $after['open_graph'] ?? array() ),
			),
			'schema-types' => array(
				'before' => $this->normalize_string_list( $before['schema']['types'] ?? array() ),
				'after'  => $this->normalize_string_list( $after['schema']['types'] ?? array() ),
			),
			'schema-block-count' => array(
				'before' => (int) ( $before['schema']['block_count'] ?? 0 ),
				'after'  => (int) ( $after['schema']['block_count'] ?? 0 ),
			),
			'h1-count' => array(
				'before' => (int) ( $before['h1_count'] ?? 0 ),
				'after'  => (int) ( $after['h1_count'] ?? 0 ),
			),
			'internal-links' => array(
				'before' => $this->normalize_url_list( $before['internal_links'] ?? array() ),
				'after'  => $this->normalize_url_list( $after['internal_links'] ?? array() ),
			),
			'primary-content-sha256' => array(
				'before' => $before['primary_content']['sha256'] ?? null,
				'after'  => $after['primary_content']['sha256'] ?? null,
			),
		);
	}

	/**
	 * Detect exact duplicate JSON-LD blocks in candidate output.
	 *
	 * @param array<string,mixed> $page Candidate page.
	 * @return list<array{signal:string,before:mixed,after:mixed,reason:string}>
	 */
	private function schema_conflicts( array $page ): array {
		$blocks = isset( $page['schema']['blocks'] ) && is_array( $page['schema']['blocks'] ) ? $page['schema']['blocks'] : array();
		$hashes = array();

		foreach ( $blocks as $block ) {
			if ( is_array( $block ) && isset( $block['sha256'] ) && is_string( $block['sha256'] ) && '' !== $block['sha256'] ) {
				$hashes[] = $block['sha256'];
			}
		}

		$counts     = array_count_values( $hashes );
		$duplicates = array();
		foreach ( $counts as $hash => $count ) {
			if ( 1 < $count ) {
				$duplicates[] = (string) $hash;
			}
		}
		sort( $duplicates );

		if ( array() === $duplicates ) {
			return array();
		}

		return array(
			array(
				'signal' => 'schema-duplicate-blocks',
				'before' => array(),
				'after'  => $duplicates,
				'reason' => 'candidate-schema-owner-conflict-or-duplicate-output',
			),
		);
	}

	/**
	 * Return candidate internal links that resolve to known broken tracked pages.
	 *
	 * @param array<string,mixed>               $page            Candidate source page.
	 * @param array<string,array<string,mixed>> $candidate_pages Candidate pages by path.
	 * @return list<string>
	 */
	private function known_broken_links( array $page, array $candidate_pages ): array {
		$links  = $this->normalize_url_list( $page['internal_links'] ?? array() );
		$broken = array();

		foreach ( $links as $link_path ) {
			$target = $candidate_pages[ $link_path ] ?? null;
			if ( ! is_array( $target ) ) {
				continue;
			}

			$status = (int) ( $target['http']['status'] ?? 0 );
			if ( 400 <= $status || 0 === $status ) {
				$broken[] = $link_path;
			}
		}

		return array_values( array_unique( $broken ) );
	}

	/**
	 * Compare observed redirect maps.
	 *
	 * @param array<string,mixed>        $baseline    Baseline snapshot.
	 * @param array<string,mixed>        $candidate   Candidate snapshot.
	 * @param ParityAllowlist            $allowlist   Explicit approvals.
	 * @param list<array<string,mixed>>  $differences Difference accumulator.
	 */
	private function compare_redirects( array $baseline, array $candidate, ParityAllowlist $allowlist, array &$differences ): void {
		$before = $this->redirect_map( $baseline );
		$after  = $this->redirect_map( $candidate );
		$paths  = array_values( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) );
		sort( $paths );

		foreach ( $paths as $path ) {
			$before_value = $before[ $path ] ?? null;
			$after_value  = $after[ $path ] ?? null;
			if ( $before_value === $after_value ) {
				continue;
			}

			$this->record_difference( $differences, $allowlist, $path, 'redirect-map', $before_value, $after_value, 'redirect-map-changed' );
		}
	}

	/**
	 * Compare sitemap status/location-count topology.
	 *
	 * @param array<string,mixed>       $baseline    Baseline snapshot.
	 * @param array<string,mixed>       $candidate   Candidate snapshot.
	 * @param ParityAllowlist           $allowlist   Explicit approvals.
	 * @param list<array<string,mixed>> $differences Difference accumulator.
	 */
	private function compare_sitemaps( array $baseline, array $candidate, ParityAllowlist $allowlist, array &$differences ): void {
		$before = $this->sitemap_map( $baseline );
		$after  = $this->sitemap_map( $candidate );
		$paths  = array_values( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) );
		sort( $paths );

		foreach ( $paths as $path ) {
			$before_value = $before[ $path ] ?? null;
			$after_value  = $after[ $path ] ?? null;
			if ( $before_value === $after_value ) {
				continue;
			}

			$this->record_difference( $differences, $allowlist, $path, 'sitemap-topology', $before_value, $after_value, 'sitemap-topology-changed' );
		}
	}

	/**
	 * Record one mismatch as approved or regression.
	 *
	 * @param list<array<string,mixed>> $differences Difference accumulator.
	 * @param ParityAllowlist           $allowlist   Explicit exact approvals.
	 * @param string                    $path        Resource path.
	 * @param string                    $signal      Signal identifier.
	 * @param mixed                     $before      Baseline normalized value.
	 * @param mixed                     $after       Candidate normalized value.
	 * @param string                    $reason      Difference reason.
	 */
	private function record_difference(
		array &$differences,
		ParityAllowlist $allowlist,
		string $path,
		string $signal,
		mixed $before,
		mixed $after,
		string $reason
	): void {
		$approval = $allowlist->approval( $path, $signal, $before, $after );

		$differences[] = array(
			'path'          => ParityAllowlist::normalize_path( $path ),
			'signal'        => sanitize_key( str_replace( '.', '-', $signal ) ),
			'status'        => null !== $approval ? 'allowed' : 'regression',
			'reason'        => $reason,
			'before_sha256' => ParityAllowlist::fingerprint( $before ),
			'after_sha256'  => ParityAllowlist::fingerprint( $after ),
			'allowlist'     => $approval,
		);
	}

	/**
	 * Return aggregate path status from accumulated differences.
	 *
	 * @param list<array<string,mixed>> $differences Difference rows.
	 * @param string                    $path        Resource path.
	 */
	private function path_status( array $differences, string $path ): string {
		$status = 'pass';

		foreach ( $differences as $difference ) {
			if ( ( $difference['path'] ?? null ) !== $path ) {
				continue;
			}

			if ( 'regression' === ( $difference['status'] ?? null ) ) {
				return 'regression';
			}
			if ( 'allowed' === ( $difference['status'] ?? null ) ) {
				$status = 'allowed';
			}
		}

		return $status;
	}

	/**
	 * Normalize indexability.
	 *
	 * @param mixed $value Raw indexability structure.
	 * @return array{state:string,indexable:bool|null}
	 */
	private function normalize_indexability( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array(
				'state'     => 'unknown',
				'indexable' => null,
			);
		}

		return array(
			'state'     => isset( $value['state'] ) && is_string( $value['state'] ) ? $value['state'] : 'unknown',
			'indexable' => isset( $value['indexable'] ) && is_bool( $value['indexable'] ) ? $value['indexable'] : null,
		);
	}

	/**
	 * Normalize robots directives independent of ordering/casing.
	 *
	 * @param mixed $value Raw robots string.
	 * @return list<string>
	 */
	private function normalize_directives( mixed $value ): array {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return array();
		}

		$directives = array_map( 'trim', explode( ',', strtolower( $value ) ) );
		$directives = array_values( array_unique( array_filter( $directives, static fn( string $item ): bool => '' !== $item ) ) );
		sort( $directives );

		return $directives;
	}

	/**
	 * Normalize one optional URL to path/query.
	 *
	 * @param mixed $value Raw URL value.
	 */
	private function normalize_optional_url( mixed $value ): ?string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}

		return ParityAllowlist::normalize_path( $value );
	}

	/**
	 * Normalize hreflang rows.
	 *
	 * @param mixed $rows Raw hreflang rows.
	 * @return list<array{lang:string,path:string}>
	 */
	private function normalize_hreflang( mixed $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['lang'], $row['url'] ) || ! is_string( $row['lang'] ) || ! is_string( $row['url'] ) ) {
				continue;
			}

			$normalized[] = array(
				'lang' => strtolower( trim( $row['lang'] ) ),
				'path' => ParityAllowlist::normalize_path( $row['url'] ),
			);
		}

		usort(
			$normalized,
			static fn( array $left, array $right ): int => strcmp( $left['lang'] . '|' . $left['path'], $right['lang'] . '|' . $right['path'] )
		);

		return $normalized;
	}

	/**
	 * Normalize Open Graph values while making URL properties origin-neutral.
	 *
	 * @param mixed $rows Raw Open Graph rows.
	 * @return list<array{property:string,content:string}>
	 */
	private function normalize_open_graph( mixed $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['property'], $row['content'] ) || ! is_string( $row['property'] ) || ! is_string( $row['content'] ) ) {
				continue;
			}

			$property = strtolower( trim( $row['property'] ) );
			$content  = trim( $row['content'] );
			if ( 'og:url' === $property && '' !== $content ) {
				$content = ParityAllowlist::normalize_path( $content );
			}

			$normalized[] = array(
				'property' => $property,
				'content'  => $content,
			);
		}

		usort(
			$normalized,
			static fn( array $left, array $right ): int => strcmp( $left['property'] . '|' . $left['content'], $right['property'] . '|' . $right['content'] )
		);

		return $normalized;
	}

	/**
	 * Normalize a string list.
	 *
	 * @param mixed $values Raw string list.
	 * @return list<string>
	 */
	private function normalize_string_list( mixed $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$normalized = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( mixed $value ): string => is_string( $value ) ? trim( $value ) : '',
						$values
					),
					static fn( string $value ): bool => '' !== $value
				)
			)
		);
		sort( $normalized );

		return $normalized;
	}

	/**
	 * Normalize a URL list to path/query values.
	 *
	 * @param mixed $values Raw URL list.
	 * @return list<string>
	 */
	private function normalize_url_list( mixed $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $values as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$normalized[] = ParityAllowlist::normalize_path( $value );
			}
		}

		$normalized = array_values( array_unique( $normalized ) );
		sort( $normalized );

		return $normalized;
	}

	/**
	 * Build redirect map keyed by origin-neutral source path.
	 *
	 * @param array<string,mixed> $snapshot Public-output snapshot.
	 * @return array<string,array{status:int,to:string}>
	 */
	private function redirect_map( array $snapshot ): array {
		$rows = isset( $snapshot['redirects']['entries'] ) && is_array( $snapshot['redirects']['entries'] ) ? $snapshot['redirects']['entries'] : array();
		$map  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['from'], $row['to'] ) || ! is_string( $row['from'] ) || ! is_string( $row['to'] ) ) {
				continue;
			}

			$map[ ParityAllowlist::normalize_path( $row['from'] ) ] = array(
				'status' => (int) ( $row['status'] ?? 0 ),
				'to'     => ParityAllowlist::normalize_path( $row['to'] ),
			);
		}

		ksort( $map, SORT_STRING );
		return $map;
	}

	/**
	 * Build sitemap topology map keyed by origin-neutral sitemap path.
	 *
	 * @param array<string,mixed> $snapshot Public-output snapshot.
	 * @return array<string,array{status:int,location_count:int}>
	 */
	private function sitemap_map( array $snapshot ): array {
		$rows = isset( $snapshot['sitemaps']['entries'] ) && is_array( $snapshot['sitemaps']['entries'] ) ? $snapshot['sitemaps']['entries'] : array();
		$map  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['url'] ) || ! is_string( $row['url'] ) ) {
				continue;
			}

			$map[ ParityAllowlist::normalize_path( $row['url'] ) ] = array(
				'status'         => (int) ( $row['status'] ?? 0 ),
				'location_count' => (int) ( $row['location_count'] ?? 0 ),
			);
		}

		ksort( $map, SORT_STRING );
		return $map;
	}

	/**
	 * Return a blocked parity report.
	 *
	 * @param list<string> $blockers Blocking contract errors.
	 * @return array<string,mixed>
	 */
	private function blocked_report( array $blockers ): array {
		return array(
			'schema_version' => 1,
			'mode'           => 'seo-geo-parity',
			'accepted'       => false,
			'blockers'       => $blockers,
			'summary'        => array(
				'passed'      => 0,
				'allowed'     => 0,
				'regressions' => 0,
				'unknown'     => count( $blockers ),
			),
			'pages'          => array(),
			'differences'    => array(),
			'checks'         => array(),
			'external_gates' => array(
				'performance'   => 'required-on-representative-migrated-pages',
				'accessibility' => 'required-on-representative-migrated-pages',
			),
			'safety'         => array(
				'mutations_performed'                 => false,
				'production_cutover_allowed'          => false,
				'allowlist_requires_exact_fingerprints' => true,
				'legacy_output_is_authority'          => false,
			),
		);
	}
}
