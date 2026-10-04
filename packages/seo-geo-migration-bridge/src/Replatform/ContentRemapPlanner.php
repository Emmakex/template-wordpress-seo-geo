<?php
/**
 * Read-only content remap intelligence for Native Replatform.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Replatform;

use WP_HTML_Tag_Processor;
use WP_Post;

/**
 * Extracts preserved source assets and proposes bounded semantic-slot candidates.
 *
 * This planner never generates factual copy and never mutates WordPress state.
 */
final class ContentRemapPlanner {
	/**
	 * Maximum source units retained in one plan.
	 */
	private const MAX_UNITS = 120;

	/**
	 * Maximum links retained in one plan.
	 */
	private const MAX_LINKS = 80;

	/**
	 * Maximum media assets retained in one plan.
	 */
	private const MAX_MEDIA = 40;

	/**
	 * Maximum normalized text retained per source unit.
	 */
	private const MAX_UNIT_CHARS = 4000;

	/**
	 * Build a deterministic, read-only remap plan.
	 *
	 * @param WP_Post             $source Preserved source page.
	 * @param array<string,mixed> $page   Trusted preset page definition.
	 * @return array<string,mixed>
	 */
	public function plan( WP_Post $source, array $page ): array {
		$units = array();
		$links = array();
		$media = array();

		$this->append_unit( $units, 'title', 'post-title', get_the_title( $source ) );

		$excerpt = trim( (string) $source->post_excerpt );
		if ( '' !== $excerpt ) {
			$this->append_unit( $units, 'excerpt', 'post-excerpt', $excerpt );
		}

		$blocks = parse_blocks( (string) $source->post_content );
		$this->collect_blocks( $blocks, $units, $links, $media );

		$required_sections = $this->required_sections( $page );
		$slots             = array();
		$manual_review     = array();

		foreach ( $required_sections as $section ) {
			$slot = $this->slot_candidates( $section, $units, $links, $media );

			$slots[] = $slot;

			if ( 'manual-review' === $slot['status'] ) {
				$manual_review[] = $section;
			}
		}

		$source_assets = array(
			'units' => $units,
			'links' => array_values( $links ),
			'media' => array_values( $media ),
		);
		$assets_sha    = hash(
			'sha256',
			(string) wp_json_encode( $source_assets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
		$plan_material = array(
			'source_id'         => (int) $source->ID,
			'source_sha256'     => hash( 'sha256', (string) $source->post_content ),
			'source_title_sha'  => hash( 'sha256', get_the_title( $source ) ),
			'page_key'          => isset( $page['key'] ) && is_string( $page['key'] ) ? $page['key'] : '',
			'required_sections' => $required_sections,
			'assets_sha256'     => $assets_sha,
			'slots'             => $slots,
		);

		return array(
			'schema_version'    => 1,
			'mode'              => 'content-remap-plan',
			'status'            => array() === $required_sections ? 'no-contract' : 'review-required',
			'source_id'         => (int) $source->ID,
			'source_sha256'     => hash( 'sha256', (string) $source->post_content ),
			'assets_sha256'     => $assets_sha,
			'plan_sha256'       => hash(
				'sha256',
				(string) wp_json_encode( $plan_material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			),
			'counts'            => array(
				'units'                  => count( $units ),
				'links'                  => count( $links ),
				'media'                  => count( $media ),
				'required_sections'      => count( $required_sections ),
				'manual_review_sections' => count( $manual_review ),
			),
			'assets'            => $source_assets,
			'required_sections' => $required_sections,
			'slots'             => $slots,
			'manual_review'     => array_values( array_unique( $manual_review ) ),
			'safety'            => array(
				'read_only'                    => true,
				'source_post_mutation'         => false,
				'generated_factual_copy'       => false,
				'legacy_layout_preservation'   => false,
				'evidence_requires_review'     => true,
				'uncertain_mapping_auto_apply' => false,
			),
		);
	}

	/**
	 * Extract leaf-block assets recursively without rendering dynamic blocks.
	 *
	 * @param array<int,mixed>                    $blocks Parsed WordPress blocks.
	 * @param array<int,array<string,mixed>>      $units  Text units.
	 * @param array<string,array<string,mixed>>   $links  Links keyed by normalized identity.
	 * @param array<string,array<string,mixed>>   $media  Media keyed by normalized identity.
	 */
	private function collect_blocks( array $blocks, array &$units, array &$links, array &$media ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$inner_blocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
				? $block['innerBlocks']
				: array();

			if ( array() !== $inner_blocks ) {
				$this->collect_blocks( $inner_blocks, $units, $links, $media );
				continue;
			}

			$block_name = isset( $block['blockName'] ) && is_string( $block['blockName'] )
				? $block['blockName']
				: 'core/freeform';
			$html       = isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] )
				? $block['innerHTML']
				: '';

			$text = $this->normalize_text( $html );
			if ( '' !== $text ) {
				$this->append_unit( $units, $this->unit_kind( $block_name ), $block_name, $text );
			}

			$this->collect_links( $html, $links );
			$this->collect_media( $html, $block, $media );

			if ( count( $units ) >= self::MAX_UNITS && count( $links ) >= self::MAX_LINKS && count( $media ) >= self::MAX_MEDIA ) {
				return;
			}
		}
	}

	/**
	 * Add one bounded text unit.
	 *
	 * @param array<int,array<string,mixed>> $units  Existing units.
	 * @param string                         $kind   Semantic source kind.
	 * @param string                         $origin Source origin.
	 * @param string                         $text   Source text.
	 */
	private function append_unit( array &$units, string $kind, string $origin, string $text ): void {
		if ( count( $units ) >= self::MAX_UNITS ) {
			return;
		}

		$text = $this->normalize_text( $text );
		if ( '' === $text ) {
			return;
		}

		$text = mb_substr( $text, 0, self::MAX_UNIT_CHARS );
		$sha  = hash( 'sha256', $kind . "\n" . $origin . "\n" . $text );

		foreach ( $units as $existing ) {
			if ( ( $existing['sha256'] ?? null ) === $sha ) {
				return;
			}
		}

		$units[] = array(
			'id'     => sprintf( 'u-%03d-%s', count( $units ) + 1, substr( $sha, 0, 8 ) ),
			'kind'   => $kind,
			'origin' => $origin,
			'text'   => $text,
			'sha256' => $sha,
		);
	}

	/**
	 * Extract bounded links from one static HTML fragment.
	 *
	 * @param string                                $html  Static block HTML.
	 * @param array<string,array<string,mixed>>     $links Link store.
	 */
	private function collect_links( string $html, array &$links ): void {
		if ( '' === $html || count( $links ) >= self::MAX_LINKS || ! class_exists( WP_HTML_Tag_Processor::class ) ) {
			return;
		}

		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( 'a' ) ) {
			if ( count( $links ) >= self::MAX_LINKS ) {
				break;
			}

			$href = $processor->get_attribute( 'href' );
			if ( ! is_string( $href ) || '' === trim( $href ) ) {
				continue;
			}

			$href = trim( $href );
			$key  = hash( 'sha256', $href );
			if ( isset( $links[ $key ] ) ) {
				continue;
			}

			$links[ $key ] = array(
				'id'       => 'l-' . substr( $key, 0, 10 ),
				'url'      => $href,
				'kind'     => $this->link_kind( $href ),
				'internal' => $this->is_internal_link( $href ),
			);
		}
	}

	/**
	 * Extract bounded image/media references from one static HTML fragment.
	 *
	 * @param string                                $html  Static block HTML.
	 * @param array<string,mixed>                   $block Parsed source block.
	 * @param array<string,array<string,mixed>>     $media Media store.
	 */
	private function collect_media( string $html, array $block, array &$media ): void {
		if ( '' === $html || count( $media ) >= self::MAX_MEDIA || ! class_exists( WP_HTML_Tag_Processor::class ) ) {
			return;
		}

		$attrs         = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$attachment_id = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;
		$processor     = new WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag( 'img' ) ) {
			if ( count( $media ) >= self::MAX_MEDIA ) {
				break;
			}

			$src = $processor->get_attribute( 'src' );
			$alt = $processor->get_attribute( 'alt' );
			$url = is_string( $src ) ? trim( $src ) : '';

			if ( '' === $url && 0 < $attachment_id ) {
				$attachment_url = wp_get_attachment_url( $attachment_id );
				$url            = is_string( $attachment_url ) ? $attachment_url : '';
			}
			if ( '' === $url && 0 >= $attachment_id ) {
				continue;
			}

			$key = hash( 'sha256', $attachment_id . '|' . $url );
			if ( isset( $media[ $key ] ) ) {
				continue;
			}

			$media[ $key ] = array(
				'id'            => 'm-' . substr( $key, 0, 10 ),
				'attachment_id' => 0 < $attachment_id ? $attachment_id : null,
				'url'           => $url,
				'alt'           => is_string( $alt ) ? trim( $alt ) : '',
			);
		}
	}

	/**
	 * Build candidates for one preset-required semantic section.
	 *
	 * @param string                                $section Required semantic section.
	 * @param array<int,array<string,mixed>>        $units   Extracted text units.
	 * @param array<string,array<string,mixed>>     $links   Extracted links.
	 * @param array<string,array<string,mixed>>     $media   Extracted media.
	 * @return array<string,mixed>
	 */
	private function slot_candidates( string $section, array $units, array $links, array $media ): array {
		$text_ids = array();
		foreach ( $units as $unit ) {
			if ( in_array( $unit['kind'] ?? '', array( 'heading', 'paragraph', 'list-item', 'quote', 'excerpt', 'body' ), true ) ) {
				$text_ids[] = (string) $unit['id'];
			}
			if ( 8 <= count( $text_ids ) ) {
				break;
			}
		}

		$link_ids  = array_slice( array_values( array_map( static fn( array $link ): string => (string) $link['id'], $links ) ), 0, 12 );
		$media_ids = array_slice( array_values( array_map( static fn( array $item ): string => (string) $item['id'], $media ) ), 0, 8 );

		$section_key  = strtolower( str_replace( '_', '-', $section ) );
		$is_link      = str_contains( $section_key, 'link' )
			|| str_contains( $section_key, 'cta' )
			|| str_contains( $section_key, 'contact' );
		$is_media     = str_contains( $section_key, 'media' )
			|| str_contains( $section_key, 'image' );
		$is_sensitive = $this->requires_factual_verification( $section_key );

		$candidates = array(
			'units' => $is_media ? array_slice( $text_ids, 0, 3 ) : $text_ids,
			'links' => $is_link || $is_sensitive ? $link_ids : array_slice( $link_ids, 0, 6 ),
			'media' => $is_media || $is_sensitive ? $media_ids : array_slice( $media_ids, 0, 3 ),
		);

		$has_candidates = array() !== $candidates['units'] || array() !== $candidates['links'] || array() !== $candidates['media'];

		$status = $is_sensitive ? 'manual-review' : ( $has_candidates ? 'candidate' : 'missing' );

		return array(
			'section'               => $section,
			'status'                => $status,
			'candidates'            => $candidates,
			'requires_verification' => $is_sensitive,
			'auto_apply'            => false,
		);
	}

	/**
	 * Return preset-required semantic sections.
	 *
	 * @param array<string,mixed> $page Preset page definition.
	 * @return list<string>
	 */
	private function required_sections( array $page ): array {
		$contract = isset( $page['content_contract'] ) && is_array( $page['content_contract'] )
			? $page['content_contract']
			: array();
		$sections = isset( $contract['required_sections'] ) && is_array( $contract['required_sections'] )
			? $contract['required_sections']
			: array();

		$result = array();

		foreach ( $sections as $section ) {
			if ( is_string( $section ) && '' !== trim( $section ) ) {
				$result[] = sanitize_key( $section );
			}
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * Classify source block to a stable unit kind.
	 *
	 * @param string $block_name Source WordPress block name.
	 */
	private function unit_kind( string $block_name ): string {
		return match ( $block_name ) {
			'core/heading'   => 'heading',
			'core/paragraph' => 'paragraph',
			'core/list-item' => 'list-item',
			'core/quote'     => 'quote',
			'core/button'    => 'cta',
			default          => 'body',
		};
	}

	/**
	 * Normalize source text without changing factual meaning.
	 *
	 * @param string $value Source HTML or text.
	 */
	private function normalize_text( string $value ): string {
		$text       = wp_strip_all_tags( $value, true );
		$normalized = preg_replace( '/\s+/u', ' ', $text );

		return trim( is_string( $normalized ) ? $normalized : $text );
	}

	/**
	 * Return a stable link kind.
	 *
	 * @param string $href Preserved link target.
	 */
	private function link_kind( string $href ): string {
		$lower = strtolower( $href );
		if ( str_starts_with( $lower, 'mailto:' ) ) {
			return 'email';
		}
		if ( str_starts_with( $lower, 'tel:' ) ) {
			return 'phone';
		}
		if ( str_starts_with( $href, '#' ) ) {
			return 'anchor';
		}

		return $this->is_internal_link( $href ) ? 'internal' : 'external';
	}

	/**
	 * Determine whether one preserved link targets the current site.
	 *
	 * @param string $href Preserved link target.
	 */
	private function is_internal_link( string $href ): bool {
		if ( str_starts_with( $href, '/' ) || str_starts_with( $href, '#' ) ) {
			return true;
		}

		$scheme = wp_parse_url( $href, PHP_URL_SCHEME );
		if ( is_string( $scheme ) && in_array( strtolower( $scheme ), array( 'mailto', 'tel' ), true ) ) {
			return false;
		}

		$host      = wp_parse_url( $href, PHP_URL_HOST );
		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		if ( null === $host || false === $host || '' === $host ) {
			return true;
		}

		return is_string( $site_host ) && strtolower( (string) $host ) === strtolower( $site_host );
	}

	/**
	 * Mark evidence-sensitive sections that must never be auto-applied.
	 *
	 * @param string $section Normalized semantic section key.
	 */
	private function requires_factual_verification( string $section ): bool {
		foreach ( array( 'proof', 'evidence', 'outcome', 'result', 'testimonial', 'credential', 'provenance', 'attribution' ) as $term ) {
			if ( str_contains( $section, $term ) ) {
				return true;
			}
		}

		return false;
	}
}
