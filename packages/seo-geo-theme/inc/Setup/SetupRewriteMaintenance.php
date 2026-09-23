<?php
/**
 * One-shot setup rewrite maintenance.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

/**
 * Flushes rewrite rules only after the next request has booted the new language configuration.
 */
final class SetupRewriteMaintenance {
	/**
	 * Non-autoloaded one-shot maintenance marker.
	 */
	public const OPTION_NAME = 'seo_geo_theme_setup_rewrite_flush_v1';

	/**
	 * Option writer.
	 *
	 * @var SetupOptionWriterInterface
	 */
	private SetupOptionWriterInterface $writer;

	/**
	 * Construct the maintenance service.
	 *
	 * @param SetupOptionWriterInterface|null $writer Optional option boundary.
	 */
	public function __construct( ?SetupOptionWriterInterface $writer = null ) {
		$this->writer = $writer ?? new WordPressSetupOptionWriter();
	}

	/**
	 * Register maintenance after Core/runtime hooks are available.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'run' ), 99 );
	}

	/**
	 * Flush rewrite rules once when a validated setup changed language routing.
	 */
	public function run(): void {
		$pending = $this->writer->read( self::OPTION_NAME );
		if ( true !== $pending['exists'] || ! is_array( $pending['value'] ) ) {
			return;
		}

		$value = $pending['value'];
		if (
			1 !== ( $value['schema_version'] ?? null )
			|| ! is_string( $value['configuration_sha256'] ?? null )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $value['configuration_sha256'] )
		) {
			return;
		}

		flush_rewrite_rules( false );

		$this->writer->delete( self::OPTION_NAME );
	}
}
