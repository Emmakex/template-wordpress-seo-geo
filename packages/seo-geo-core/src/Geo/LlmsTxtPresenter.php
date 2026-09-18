<?php
/**
 * Optional llms.txt presenter.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

/**
 * Serves the native llms.txt document without rewrite-rule dependencies.
 */
final class LlmsTxtPresenter {
	/**
	 * LLMS.txt data authority.
	 *
	 * @var LlmsTxtResolver
	 */
	private LlmsTxtResolver $resolver;

	/**
	 * Create the presenter.
	 *
	 * @param LlmsTxtResolver $resolver llms.txt data authority.
	 */
	public function __construct( LlmsTxtResolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Register the virtual endpoint.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 0 );
	}

	/**
	 * Render only the exact site-root llms.txt endpoint when enabled.
	 */
	public function maybe_render(): void {
		if ( ! $this->resolver->enabled() || ! $this->is_llms_request() ) {
			return;
		}

		$content = $this->resolver->resolve();
		if ( null === $content ) {
			return;
		}

		status_header( 200 );
		nocache_headers();

		$charset = get_bloginfo( 'charset' );
		header( 'Content-Type: text/plain; charset=' . ( '' !== $charset ? $charset : 'UTF-8' ) );
		header( 'X-Content-Type-Options: nosniff' );

		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		if ( 'HEAD' !== $method ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text Markdown assembled only from validated and sanitized server-side values.
			echo $content;
		}

		exit;
	}

	/**
	 * Match the exact llms.txt path for root or subdirectory installations.
	 */
	private function is_llms_request(): bool {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request     = wp_parse_url( $request_uri, PHP_URL_PATH );
		$target      = wp_parse_url( home_url( '/llms.txt' ), PHP_URL_PATH );

		return is_string( $request ) && is_string( $target ) && $request === $target;
	}
}
