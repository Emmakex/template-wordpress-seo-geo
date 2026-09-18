<?php
/**
 * SEO output authority contract.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

/**
 * Resolves the authoritative provider for overlapping SEO output.
 */
final class SeoOutputAuthority {
	public const SIGNAL_CANONICAL        = 'canonical';
	public const SIGNAL_META_DESCRIPTION = 'meta_description';
	public const SIGNAL_ROBOTS           = 'robots';
	public const SIGNAL_OPEN_GRAPH       = 'open_graph';
	public const SIGNAL_HREFLANG         = 'hreflang';

	/**
	 * Active provider identifier.
	 *
	 * @var string
	 */
	private string $provider;

	/**
	 * Supported overlapping output signals.
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_SIGNALS = array(
		self::SIGNAL_CANONICAL,
		self::SIGNAL_META_DESCRIPTION,
		self::SIGNAL_ROBOTS,
		self::SIGNAL_OPEN_GRAPH,
		self::SIGNAL_HREFLANG,
	);

	/**
	 * Create the authority resolver.
	 *
	 * @param string $provider Active SEO provider identifier.
	 */
	public function __construct( string $provider ) {
		$this->provider = '' !== $provider ? $provider : 'native';
	}

	/**
	 * Return the active provider identifier.
	 */
	public function provider(): string {
		return $this->provider;
	}

	/**
	 * Return whether the signal is part of the current authority contract.
	 *
	 * @param string $signal SEO output signal.
	 */
	public function supports( string $signal ): bool {
		return in_array( $signal, self::SUPPORTED_SIGNALS, true );
	}

	/**
	 * Return whether native Core owns the requested output signal.
	 *
	 * @param string $signal SEO output signal.
	 */
	public function native_owns( string $signal ): bool {
		return $this->supports( $signal ) && 'native' === $this->provider;
	}
}
