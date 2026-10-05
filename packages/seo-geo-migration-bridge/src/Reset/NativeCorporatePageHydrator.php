<?php
/**
 * Native Corporate inner-page content hydrator.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Error;
use WP_Post;

/**
 * Applies reviewed Content Kits to semantic slots of clean Corporate page drafts.
 */
final class NativeCorporatePageHydrator {
	public const BACKUP_META      = '_seo_geo_clean_page_pre_hydration_content_v1';
	public const BACKUP_SHA_META  = '_seo_geo_clean_page_pre_hydration_sha256_v1';
	public const KIT_SHA_META     = '_seo_geo_clean_page_content_kit_sha256_v1';
	public const APPLIED_SHA_META = '_seo_geo_clean_page_hydrated_sha256_v1';
	public const APPLIED_AT_META  = '_seo_geo_clean_page_hydrated_at_v1';
	public const CONTENT_STATE    = 'content-hydrated-v1';
	public const SCAFFOLD_STATE   = 'preset-scaffold';

	/**
	 * Construct the native page hydrator.
	 *
	 * @param CleanCorporatePageRebuilder $builder Clean Corporate page builder.
	 * @param CorporatePageContentKit     $kit     Reviewed Corporate page content service.
	 */
	public function __construct(
		private CleanCorporatePageRebuilder $builder,
		private CorporatePageContentKit $kit
	) {
	}

	/**
	 * Build a read-only hydration plan.
	 *
	 * @param string $page_key Corporate page key.
	 * @return array<string,mixed>
	 */
	public function plan( string $page_key ): array {
		$page_key  = sanitize_key( $page_key );
		$blockers  = array();
		$saved     = $this->kit->saved( $page_key );
		$saved_kit = is_array( $saved ) ? $saved : array();
		$page      = $this->builder->plan( $page_key );
		$model     = $this->kit->model( $page_key );

		if ( true !== ( $page['ready'] ?? false ) ) {
			$blockers[] = 'clean-page-plan-not-ready';
		}
		if (
			! is_array( $saved )
			|| 1 !== ( $saved['schema_version'] ?? null )
			|| 'corporate-page-content-kit' !== ( $saved['mode'] ?? null )
			|| (string) ( $saved['page_key'] ?? '' ) !== $page_key
		) {
			$blockers[] = 'content-kit-required';
		}
		if ( ! is_array( $model ) ) {
			$blockers[] = 'content-model-required';
		}

		$draft_id = (int) ( $saved_kit['draft_id'] ?? 0 );
		$draft    = 0 < $draft_id ? get_post( $draft_id ) : null;
		if ( ! $draft instanceof WP_Post || 'page' !== $draft->post_type || 'draft' !== $draft->post_status ) {
			$blockers[] = 'clean-page-draft-required';
		}
		if ( $draft instanceof WP_Post && (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::PAGE_KEY_META, true ) !== $page_key ) {
			$blockers[] = 'clean-page-key-mismatch';
		}
		if (
			$draft instanceof WP_Post
			&& ! hash_equals(
				(string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::PLAN_SHA_META, true ),
				(string) ( $saved_kit['plan_sha256'] ?? '' )
			)
		) {
			$blockers[] = 'content-kit-plan-drift';
		}

		$current_content = $draft instanceof WP_Post ? (string) $draft->post_content : '';
		$current_sha     = hash( 'sha256', $current_content );
		$state           = 0 < $draft_id ? (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::CONTENT_STATE_META, true ) : '';
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
			$blockers[] = 'clean-page-content-state-invalid';
		}

		$scaffold = self::CONTENT_STATE === $state ? $backup : $current_content;
		if ( '' === $scaffold ) {
			$blockers[] = 'clean-page-scaffold-missing';
		}

		$hydrated = '';
		if ( array() === $blockers ) {
			$hydrated = $this->hydrate_content( $scaffold, $saved_kit, is_array( $model ) ? $model : array() );
			if ( '' === $hydrated ) {
				$blockers[] = 'hydration-empty';
			}
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		return array(
			'schema_version'   => 1,
			'mode'             => 'corporate-page-native-hydration-plan',
			'page_key'         => $page_key,
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
	 * Apply the saved Content Kit to one clean Corporate page draft.
	 *
	 * @param string $page_key Corporate page key.
	 * @return array<string,mixed>|WP_Error
	 */
	public function apply( string $page_key ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_page_hydration_forbidden', 'Administrator capability is required.' );
		}

		$plan = $this->plan( $page_key );
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_page_hydration_not_ready',
				'Corporate page hydration is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$draft_id = (int) ( $plan['draft_id'] ?? 0 );
		$draft    = get_post( $draft_id );
		if ( ! $draft instanceof WP_Post ) {
			return new WP_Error( 'seo_geo_page_hydration_draft_missing', 'Clean Corporate page draft is unavailable.' );
		}

		$hydrated       = (string) $plan['hydrated_content'];
		$hydrated_sha   = (string) $plan['hydrated_sha256'];
		$current_sha    = hash( 'sha256', (string) $draft->post_content );
		$current_kit    = (string) get_post_meta( $draft_id, self::KIT_SHA_META, true );
		$front_before   = (int) get_option( 'page_on_front', 0 );
		$plugins_before = $this->active_plugins();

		if ( hash_equals( $current_sha, $hydrated_sha ) && hash_equals( $current_kit, (string) $plan['kit_sha256'] ) ) {
			return array(
				'schema_version' => 1,
				'mode'           => 'corporate-page-native-hydration',
				'status'         => 'existing',
				'page_key'       => sanitize_key( $page_key ),
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
		update_post_meta( $draft_id, CleanCorporatePageRebuilder::CONTENT_STATE_META, self::CONTENT_STATE );

		return array(
			'schema_version' => 1,
			'mode'           => 'corporate-page-native-hydration',
			'status'         => 'applied',
			'page_key'       => sanitize_key( $page_key ),
			'draft_id'       => $draft_id,
			'kit_sha256'     => (string) $plan['kit_sha256'],
			'content_sha256' => $hydrated_sha,
			'safety'         => array(
				'draft_only'               => 'draft' === get_post_status( $draft_id ),
				'front_page_id_unchanged'  => (int) get_option( 'page_on_front', 0 ) === $front_before,
				'plugins_unchanged'        => $this->active_plugins() === $plugins_before,
				'source_content_unchanged' => $this->source_unchanged( $draft_id ),
				'rollback_available'       => '' !== (string) get_post_meta( $draft_id, self::BACKUP_META, true ),
			),
		);
	}

	/**
	 * Restore the original preset scaffold.
	 *
	 * @param string $page_key Corporate page key.
	 * @return array<string,mixed>|WP_Error
	 */
	public function rollback( string $page_key ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_page_hydration_forbidden', 'Administrator capability is required.' );
		}

		$saved    = $this->kit->saved( $page_key );
		$draft_id = is_array( $saved ) ? (int) ( $saved['draft_id'] ?? 0 ) : 0;
		$backup   = 0 < $draft_id ? get_post_meta( $draft_id, self::BACKUP_META, true ) : '';
		$backup   = is_string( $backup ) ? $backup : '';
		if ( '' === $backup ) {
			return new WP_Error( 'seo_geo_page_hydration_backup_missing', 'No clean Corporate page hydration backup is available.' );
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
		update_post_meta( $draft_id, CleanCorporatePageRebuilder::CONTENT_STATE_META, self::SCAFFOLD_STATE );

		return array(
			'schema_version' => 1,
			'mode'           => 'corporate-page-native-hydration',
			'status'         => 'rolled-back',
			'page_key'       => sanitize_key( $page_key ),
			'draft_id'       => $draft_id,
			'content_sha256' => hash( 'sha256', $backup ),
		);
	}

	/**
	 * Hydrate the scaffold using semantic block classes and section policies.
	 *
	 * @param string              $content Preset scaffold.
	 * @param array<string,mixed> $kit     Reviewed Content Kit.
	 * @param array<string,mixed> $model   Theme-owned content model.
	 */
	private function hydrate_content( string $content, array $kit, array $model ): string {
		$blocks   = parse_blocks( $content );
		$values   = is_array( $kit['values'] ?? null ) ? $kit['values'] : array();
		$groups   = is_array( $kit['verified_groups'] ?? null ) ? $kit['verified_groups'] : array();
		$policies = is_array( $model['section_policy'] ?? null ) ? $model['section_policy'] : array();
		$output   = array();

		foreach ( $blocks as $block ) {
			$transformed = $this->transform_block( $block, $values, $groups, $policies );
			if ( is_array( $transformed ) ) {
				$output[] = $transformed;
			}
		}

		return serialize_blocks( $output );
	}

	/**
	 * Transform one block recursively.
	 *
	 * @param array<string,mixed> $block    Parsed block.
	 * @param array<string,mixed> $values   Reviewed values.
	 * @param array<string,mixed> $groups   Verification groups.
	 * @param array               $policies Section policies.
	 * @phpstan-param list<array<string,mixed>> $policies
	 * @return array<string,mixed>|null
	 */
	private function transform_block( array $block, array $values, array $groups, array $policies ): ?array {
		$class = $this->block_class_names( $block );

		foreach ( $policies as $policy ) {
			if ( ! is_array( $policy ) || ! is_string( $policy['section_class'] ?? null ) || ! str_contains( $class, $policy['section_class'] ) ) {
				continue;
			}
			if (
				'omit-unless-verified' === ( $policy['mode'] ?? null )
				&& is_string( $policy['verification_group'] ?? null )
				&& true !== ( $groups[ $policy['verification_group'] ] ?? false )
			) {
				return null;
			}
			if (
				'omit-unless-populated' === ( $policy['mode'] ?? null )
				&& is_string( $policy['content_group'] ?? null )
				&& ! $this->content_group_populated( $policy['content_group'], $values )
			) {
				return null;
			}
		}

		$faq_index = $this->faq_index( $class );
		if ( null !== $faq_index && 'core/details' === ( $block['blockName'] ?? null ) ) {
			$question = (string) ( $values[ 'faq-' . $faq_index . '-question' ] ?? '' );
			$answer   = (string) ( $values[ 'faq-' . $faq_index . '-answer' ] ?? '' );
			if ( '' === $question || '' === $answer ) {
				return null;
			}
			$block = $this->replace_details( $block, $question, $answer );
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

				$transformed = $this->transform_block( $child, $values, $groups, $policies );
				if ( is_array( $transformed ) ) {
					$new_blocks[]  = $transformed;
					$new_content[] = null;
				}
			}

			$block['innerBlocks']  = $new_blocks;
			$block['innerContent'] = $new_content;
		}

		$slot = $this->slot_id( $class );
		if ( null === $slot || ! array_key_exists( $slot, $values ) ) {
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
	 * Replace one Details block's summary and answer content.
	 *
	 * @param array<string,mixed> $block    Parsed Details block.
	 * @param string              $question Reviewed question.
	 * @param string              $answer   Reviewed answer.
	 * @return array<string,mixed>
	 */
	private function replace_details( array $block, string $question, string $answer ): array {
		$html                  = is_string( $block['innerHTML'] ?? null ) ? $block['innerHTML'] : '';
		$html                  = (string) preg_replace(
			'#(<summary\b[^>]*>).*?(</summary>)#is',
			'$1' . esc_html( $question ) . '$2',
			$html,
			1
		);
		$html                  = (string) preg_replace(
			'#(<p\b[^>]*class=(["\'])[^"\']*seo-geo-content-slot--faq-[1-3]-answer[^"\']*\2[^>]*>).*?(</p>)#is',
			'$1' . esc_html( $answer ) . '$3',
			$html,
			1
		);
		$block['innerHTML']    = $html;
		$block['innerContent'] = array( $html );
		$block['innerBlocks']  = array();

		return $block;
	}

	/**
	 * Resolve all own block classes.
	 *
	 * @param array<string,mixed> $block Parsed block.
	 */
	private function block_class_names( array $block ): string {
		$classes = array();
		if ( is_array( $block['attrs'] ?? null ) && is_string( $block['attrs']['className'] ?? null ) ) {
			$classes[] = trim( $block['attrs']['className'] );
		}
		$html = is_string( $block['innerHTML'] ?? null ) ? ltrim( $block['innerHTML'] ) : '';
		if ( '' !== $html && 1 === preg_match( '/^<[^>]*\bclass=(["\'])(.*?)\1/is', $html, $matches ) ) {
			$classes[] = trim( $matches[2] );
		}

		return implode( ' ', array_values( array_unique( array_filter( $classes ) ) ) );
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
	 * Resolve an optional FAQ pair index from a Details block class list.
	 *
	 * @param string $class_names Block class list.
	 */
	private function faq_index( string $class_names ): ?int {
		if ( 1 !== preg_match( '/(?:^|\s)seo-geo-content-slot--faq-([1-3])(?:\s|$)/', $class_names, $matches ) ) {
			return null;
		}

		return (int) $matches[1];
	}

	/**
	 * Whether one optional content group has reviewed values.
	 *
	 * @param string              $group  Content group.
	 * @param array<string,mixed> $values Reviewed semantic values.
	 */
	private function content_group_populated( string $group, array $values ): bool {
		$prefix = $group . '-';
		foreach ( $values as $key => $value ) {
			if ( is_string( $key ) && str_starts_with( $key, $prefix ) && '' !== trim( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replace textual contents of one leaf HTML element.
	 *
	 * @param string $html Leaf HTML fragment.
	 * @param string $text Reviewed text.
	 */
	private function replace_element_text( string $html, string $text ): string {
		return (string) preg_replace(
			'#^(\s*<([a-z0-9]+)\b[^>]*>).*?(</\2>\s*)$#is',
			'$1' . esc_html( $text ) . '$3',
			$html,
			1
		);
	}

	/**
	 * Replace one semantic link label and destination.
	 *
	 * @param string $html  Link HTML fragment.
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
	 * @param string           $html  List HTML fragment.
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
			'#^(\s*<ul\b[^>]*>).*?(</ul>\s*)$#is',
			'$1' . $list . '$2',
			$html,
			1
		);
	}

	/**
	 * Confirm rescued source content still matches the clean page provenance.
	 *
	 * @param int $draft_id Clean Corporate page draft ID.
	 */
	private function source_unchanged( int $draft_id ): bool {
		$source_id  = (int) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_ID_META, true );
		$source_sha = (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_SHA_META, true );
		$content    = 0 < $source_id ? get_post_field( 'post_content', $source_id ) : null;

		return is_string( $content ) && '' !== $source_sha && hash_equals( $source_sha, hash( 'sha256', $content ) );
	}

	/**
	 * Return the active plugin set in deterministic order.
	 *
	 * @return array
	 * @phpstan-return list<string>
	 */
	private function active_plugins(): array {
		$plugins = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
		sort( $plugins );

		return $plugins;
	}
}
