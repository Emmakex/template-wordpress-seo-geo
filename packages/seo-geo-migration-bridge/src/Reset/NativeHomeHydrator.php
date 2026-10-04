<?php
/**
 * Native Corporate Home content hydrator.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Error;
use WP_Post;

/**
 * Applies a reviewed Content Kit to the semantic slots of the clean Home draft.
 */
final class NativeHomeHydrator {
	public const BACKUP_META      = '_seo_geo_clean_home_pre_hydration_content_v1';
	public const BACKUP_SHA_META  = '_seo_geo_clean_home_pre_hydration_sha256_v1';
	public const KIT_SHA_META     = '_seo_geo_clean_home_content_kit_sha256_v1';
	public const APPLIED_SHA_META = '_seo_geo_clean_home_hydrated_sha256_v1';
	public const APPLIED_AT_META  = '_seo_geo_clean_home_hydrated_at_v1';
	public const CONTENT_STATE    = 'content-hydrated-v1';
	public const SCAFFOLD_STATE   = 'preset-scaffold';

	/**
	 * Construct the native Home hydrator.
	 *
	 * @param CleanHomeRebuilder      $builder Clean Home builder.
	 * @param CorporateHomeContentKit $kit     Structured Content Kit.
	 */
	public function __construct(
		private CleanHomeRebuilder $builder,
		private CorporateHomeContentKit $kit
	) {
	}

	/**
	 * Build a read-only hydration plan.
	 *
	 * @return array<string,mixed>
	 */
	public function plan(): array {
		$blockers  = array();
		$saved     = $this->kit->saved();
		$saved_kit = is_array( $saved ) ? $saved : array();
		$home      = $this->builder->plan();

		if ( true !== ( $home['ready'] ?? false ) ) {
			$blockers[] = 'clean-home-plan-not-ready';
		}
		if ( ! is_array( $saved ) || 1 !== ( $saved['schema_version'] ?? null ) || 'corporate-home-content-kit' !== ( $saved['mode'] ?? null ) ) {
			$blockers[] = 'content-kit-required';
		}

		$draft_id = (int) ( $saved_kit['draft_id'] ?? 0 );
		$draft    = 0 < $draft_id ? get_post( $draft_id ) : null;
		if ( ! $draft instanceof WP_Post || 'page' !== $draft->post_type || 'draft' !== $draft->post_status ) {
			$blockers[] = 'clean-home-draft-required';
		}

		if (
			$draft instanceof WP_Post
			&& ! hash_equals(
				(string) get_post_meta( $draft_id, CleanHomeRebuilder::PLAN_SHA_META, true ),
				(string) ( $saved_kit['plan_sha256'] ?? '' )
			)
		) {
			$blockers[] = 'content-kit-plan-drift';
		}

		$current_content = $draft instanceof WP_Post ? (string) $draft->post_content : '';
		$current_sha     = hash( 'sha256', $current_content );
		$state           = 0 < $draft_id ? (string) get_post_meta( $draft_id, CleanHomeRebuilder::CONTENT_STATE_META, true ) : '';
		$applied_sha     = 0 < $draft_id ? (string) get_post_meta( $draft_id, self::APPLIED_SHA_META, true ) : '';
		$backup          = 0 < $draft_id ? get_post_meta( $draft_id, self::BACKUP_META, true ) : '';
		$backup          = is_string( $backup ) ? $backup : '';

		if ( self::CONTENT_STATE === $state && ( '' === $applied_sha || ! hash_equals( $applied_sha, $current_sha ) ) ) {
			$blockers[] = 'hydrated-draft-drift';
		}
		if ( self::SCAFFOLD_STATE === $state && '' !== $backup ) {
			$blockers[] = 'unexpected-hydration-backup';
		}
		if ( ! in_array( $state, array( self::SCAFFOLD_STATE, self::CONTENT_STATE ), true ) ) {
			$blockers[] = 'clean-home-content-state-invalid';
		}

		$scaffold = self::CONTENT_STATE === $state ? $backup : $current_content;
		if ( '' === $scaffold ) {
			$blockers[] = 'clean-home-scaffold-missing';
		}

		$hydrated = '';
		if ( array() === $blockers ) {
			$hydrated = $this->hydrate_content( $scaffold, $saved_kit );
			if ( '' === $hydrated ) {
				$blockers[] = 'hydration-empty';
			}
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		return array(
			'schema_version'   => 1,
			'mode'             => 'corporate-home-native-hydration-plan',
			'ready'            => array() === $blockers,
			'blockers'         => $blockers,
			'draft_id'         => $draft_id,
			'kit_sha256'       => (string) ( $saved_kit['kit_sha256'] ?? '' ),
			'state'            => $state,
			'scaffold_sha256'  => hash( 'sha256', $scaffold ),
			'hydrated_sha256'  => hash( 'sha256', $hydrated ),
			'hydrated_content' => $hydrated,
			'verified_groups'  => is_array( $saved_kit['verified_groups'] ?? null ) ? $saved_kit['verified_groups'] : array(),
			'safety'           => array(
				'source_post_mutation'         => false,
				'front_page_assignment_change' => false,
				'legacy_layout_input'          => false,
				'rollback_available'           => true,
				'draft_only'                   => true,
			),
		);
	}

	/**
	 * Apply the saved Content Kit to the clean Home draft.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function apply(): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_home_hydration_forbidden', 'Administrator capability is required.' );
		}

		$plan = $this->plan();
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_home_hydration_not_ready',
				'Corporate Home hydration is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$draft_id = (int) ( $plan['draft_id'] ?? 0 );
		$draft    = get_post( $draft_id );
		if ( ! $draft instanceof WP_Post ) {
			return new WP_Error( 'seo_geo_home_hydration_draft_missing', 'Clean Home draft is unavailable.' );
		}

		$hydrated     = (string) $plan['hydrated_content'];
		$hydrated_sha = (string) $plan['hydrated_sha256'];
		$current_sha  = hash( 'sha256', (string) $draft->post_content );
		$current_kit  = (string) get_post_meta( $draft_id, self::KIT_SHA_META, true );

		if ( hash_equals( $current_sha, $hydrated_sha ) && hash_equals( $current_kit, (string) $plan['kit_sha256'] ) ) {
			return array(
				'schema_version' => 1,
				'mode'           => 'corporate-home-native-hydration',
				'status'         => 'existing',
				'draft_id'       => $draft_id,
				'kit_sha256'     => (string) $plan['kit_sha256'],
				'content_sha256' => $hydrated_sha,
			);
		}

		$backup = get_post_meta( $draft_id, self::BACKUP_META, true );
		if ( ! is_string( $backup ) || '' === $backup ) {
			$backup = (string) $draft->post_content;
			update_post_meta( $draft_id, self::BACKUP_META, $backup );
			update_post_meta( $draft_id, self::BACKUP_SHA_META, hash( 'sha256', $backup ) );
		}

		$result = wp_update_post(
			array(
				'ID'           => $draft_id,
				'post_content' => $hydrated,
			),
			true
		);
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		update_post_meta( $draft_id, self::KIT_SHA_META, (string) $plan['kit_sha256'] );
		update_post_meta( $draft_id, self::APPLIED_SHA_META, $hydrated_sha );
		update_post_meta( $draft_id, self::APPLIED_AT_META, gmdate( DATE_ATOM ) );
		update_post_meta( $draft_id, CleanHomeRebuilder::CONTENT_STATE_META, self::CONTENT_STATE );

		return array(
			'schema_version' => 1,
			'mode'           => 'corporate-home-native-hydration',
			'status'         => 'applied',
			'draft_id'       => $draft_id,
			'kit_sha256'     => (string) $plan['kit_sha256'],
			'content_sha256' => $hydrated_sha,
			'safety'         => array(
				'draft_only'               => 'draft' === get_post_status( $draft_id ),
				'front_page_id_unchanged'  => (int) get_option( 'page_on_front', 0 ) === (int) get_post_meta( $draft_id, CleanHomeRebuilder::SOURCE_ID_META, true ),
				'source_content_unchanged' => $this->source_unchanged( $draft_id ),
				'rollback_available'       => '' !== (string) get_post_meta( $draft_id, self::BACKUP_META, true ),
			),
		);
	}

	/**
	 * Restore the original preset scaffold.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function rollback(): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_home_hydration_forbidden', 'Administrator capability is required.' );
		}

		$saved    = $this->kit->saved();
		$draft_id = is_array( $saved ) ? (int) ( $saved['draft_id'] ?? 0 ) : 0;
		$backup   = 0 < $draft_id ? get_post_meta( $draft_id, self::BACKUP_META, true ) : '';
		$backup   = is_string( $backup ) ? $backup : '';
		if ( '' === $backup ) {
			return new WP_Error( 'seo_geo_home_hydration_backup_missing', 'No clean Home hydration backup is available.' );
		}

		$result = wp_update_post(
			array(
				'ID'           => $draft_id,
				'post_content' => $backup,
			),
			true
		);
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		delete_post_meta( $draft_id, self::KIT_SHA_META );
		delete_post_meta( $draft_id, self::APPLIED_SHA_META );
		delete_post_meta( $draft_id, self::APPLIED_AT_META );
		delete_post_meta( $draft_id, self::BACKUP_META );
		delete_post_meta( $draft_id, self::BACKUP_SHA_META );
		update_post_meta( $draft_id, CleanHomeRebuilder::CONTENT_STATE_META, self::SCAFFOLD_STATE );

		return array(
			'schema_version' => 1,
			'mode'           => 'corporate-home-native-hydration',
			'status'         => 'rolled-back',
			'draft_id'       => $draft_id,
			'content_sha256' => hash( 'sha256', $backup ),
		);
	}

	/**
	 * Hydrate the scaffold using semantic block classes.
	 *
	 * @param string              $content Preset scaffold.
	 * @param array<string,mixed> $kit     Reviewed Content Kit.
	 */
	private function hydrate_content( string $content, array $kit ): string {
		$blocks = parse_blocks( $content );
		$values = is_array( $kit['values'] ?? null ) ? $kit['values'] : array();
		$groups = is_array( $kit['verified_groups'] ?? null ) ? $kit['verified_groups'] : array();
		$output = array();

		foreach ( $blocks as $block ) {
			$transformed = $this->transform_block( $block, $values, $groups );
			if ( is_array( $transformed ) ) {
				$output[] = $transformed;
			}
		}

		return serialize_blocks( $output );
	}

	/**
	 * Transform one block recursively.
	 *
	 * @param array<string,mixed> $block  Parsed block.
	 * @param array<string,mixed> $values Reviewed slot values.
	 * @param array<string,mixed> $groups Verification groups.
	 * @return array<string,mixed>|null
	 */
	private function transform_block( array $block, array $values, array $groups ): ?array {
		$class = is_array( $block['attrs'] ?? null ) && is_string( $block['attrs']['className'] ?? null )
			? $block['attrs']['className']
			: '';

		if (
			'core/column' === ( $block['blockName'] ?? null )
			&& true !== ( $groups['hero-proof'] ?? false )
			&& $this->contains_class( $block, 'seo-geo-corporate-native-hero__proof' )
		) {
			return null;
		}

		if ( str_contains( $class, 'seo-geo-corporate-native-proof' ) && true !== ( $groups['proof'] ?? false ) ) {
			return null;
		}
		if ( str_contains( $class, 'seo-geo-corporate-case-study' ) && true !== ( $groups['case-study'] ?? false ) ) {
			return null;
		}
		if ( str_contains( $class, 'seo-geo-corporate-native-hero__proof' ) && true !== ( $groups['hero-proof'] ?? false ) ) {
			return null;
		}

		$inner_blocks  = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
		$inner_content = is_array( $block['innerContent'] ?? null ) ? $block['innerContent'] : array();
		if ( array() !== $inner_blocks ) {
			$new_blocks  = array();
			$new_content = array();
			$child_index = 0;

			foreach ( $inner_content as $chunk ) {
				if ( is_string( $chunk ) ) {
					$new_content[] = $chunk;
					continue;
				}

				$child = $inner_blocks[ $child_index ] ?? null;
				++$child_index;
				if ( ! is_array( $child ) ) {
					continue;
				}

				$transformed = $this->transform_block( $child, $values, $groups );
				if ( is_array( $transformed ) ) {
					$new_blocks[]  = $transformed;
					$new_content[] = null;
				}
			}

			$block['innerBlocks']  = $new_blocks;
			$block['innerContent'] = $new_content;

			if ( 'core/column' === ( $block['blockName'] ?? null ) && array() === $new_blocks && '' === trim( implode( '', array_filter( $new_content, 'is_string' ) ) ) ) {
				return null;
			}
		}

		$slot = $this->slot_id( $class );
		if ( null === $slot || ! array_key_exists( $slot, $values ) ) {
			if ( 'hero-secondary-cta' === $slot ) {
				return null;
			}

			return $block;
		}

		$value      = $values[ $slot ];
		$inner_html = is_string( $block['innerHTML'] ?? null ) ? $block['innerHTML'] : '';
		$block_name = is_string( $block['blockName'] ?? null ) ? $block['blockName'] : '';

		if ( is_string( $value ) ) {
			$inner_html = $this->replace_element_text( $inner_html, $value );
		} elseif ( is_array( $value ) && isset( $value['label'], $value['url'] ) ) {
			$inner_html = $this->replace_link( $inner_html, (string) $value['label'], (string) $value['url'] );
		} elseif ( is_array( $value ) && 'core/list' === $block_name ) {
			$inner_html = $this->replace_list( $inner_html, $value );
		}

		$block['innerHTML']    = $inner_html;
		$block['innerContent'] = array( $inner_html );

		return $block;
	}

	/**
	 * Whether a block tree contains one semantic class.
	 *
	 * @param array<string,mixed> $block Parsed block.
	 * @param string              $needle Class fragment.
	 */
	private function contains_class( array $block, string $needle ): bool {
		$class = is_array( $block['attrs'] ?? null ) && is_string( $block['attrs']['className'] ?? null )
			? $block['attrs']['className']
			: '';
		if ( str_contains( $class, $needle ) ) {
			return true;
		}

		foreach ( is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array() as $child ) {
			if ( is_array( $child ) && $this->contains_class( $child, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve one semantic slot ID from a class list.
	 *
	 * @param string $class_names Block class list.
	 */
	private function slot_id( string $class_names ): ?string {
		if ( 1 !== preg_match( '/(?:^|\s)seo-geo-content-slot--([a-z0-9-]+)(?:\s|$)/', $class_names, $matches ) ) {
			return null;
		}

		return $matches[1];
	}

	/**
	 * Replace the textual contents of one leaf HTML element.
	 *
	 * @param string $html Leaf block HTML.
	 * @param string $text Reviewed text.
	 */
	private function replace_element_text( string $html, string $text ): string {
		return (string) preg_replace(
			'#^(<([a-z0-9]+)\b[^>]*>).*?(</\2>)$#is',
			'$1' . esc_html( $text ) . '$3',
			$html,
			1
		);
	}

	/**
	 * Replace one semantic link label and destination.
	 *
	 * @param string $html  Leaf block HTML.
	 * @param string $label Reviewed link label.
	 * @param string $url   Reviewed link URL.
	 */
	private function replace_link( string $html, string $label, string $url ): string {
		return (string) preg_replace_callback(
			'#<a\b([^>]*)>(.*?)</a>#is',
			static function ( array $matches ) use ( $label, $url ): string {
				$attrs = (string) $matches[1];
				if ( 1 === preg_match( '/\bhref=(["\']).*?\1/i', $attrs ) ) {
					$attrs = (string) preg_replace( '/\bhref=(["\']).*?\1/i', 'href="' . esc_attr( $url ) . '"', $attrs, 1 );
				} else {
					$attrs .= ' href="' . esc_attr( $url ) . '"';
				}

				return '<a' . $attrs . '>' . esc_html( $label ) . '</a>';
			},
			$html,
			1
		);
	}

	/**
	 * Replace one semantic list.
	 *
	 * @param string           $html  Leaf list HTML.
	 * @param array<int,mixed> $items Reviewed list items.
	 */
	private function replace_list( string $html, array $items ): string {
		$list = implode(
			'',
			array_map(
				static fn( mixed $item ): string => '<li>' . esc_html( (string) $item ) . '</li>',
				$items
			)
		);

		return (string) preg_replace(
			'#^(<ul\b[^>]*>).*?(</ul>)$#is',
			'$1' . $list . '$2',
			$html,
			1
		);
	}

	/**
	 * Confirm the rescued source content is still unchanged.
	 *
	 * @param int $draft_id Clean Home draft ID.
	 */
	private function source_unchanged( int $draft_id ): bool {
		$source_id  = (int) get_post_meta( $draft_id, CleanHomeRebuilder::SOURCE_ID_META, true );
		$source_sha = (string) get_post_meta( $draft_id, CleanHomeRebuilder::SOURCE_SHA_META, true );
		$content    = 0 < $source_id ? get_post_field( 'post_content', $source_id ) : null;

		return is_string( $content ) && '' !== $source_sha && hash_equals( $source_sha, hash( 'sha256', $content ) );
	}
}
