<?php
/**
 * Optional localized Markdown alternate presenter.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

/**
 * Publishes Markdown alternates and advertises them from authoritative HTML pages.
 */
final class MarkdownAlternatePresenter {
	/**
	 * Markdown alternate authority.
	 *
	 * @var MarkdownAlternateResolver
	 */
	private MarkdownAlternateResolver $resolver;

	/**
	 * LLMS.txt authority used for describedby discovery.
	 *
	 * @var LlmsTxtResolver
	 */
	private LlmsTxtResolver $llms_txt;

	/**
	 * Discovery cache policy.
	 *
	 * @var DiscoveryCachePolicy
	 */
	private DiscoveryCachePolicy $cache_policy;

	/**
	 * Create the presenter.
	 *
	 * @param MarkdownAlternateResolver $resolver     Markdown alternate authority.
	 * @param LlmsTxtResolver           $llms_txt     LLMS.txt authority.
	 * @param DiscoveryCachePolicy      $cache_policy Discovery cache policy.
	 */
	public function __construct(
		MarkdownAlternateResolver $resolver,
		LlmsTxtResolver $llms_txt,
		DiscoveryCachePolicy $cache_policy
	) {
		$this->resolver     = $resolver;
		$this->llms_txt     = $llms_txt;
		$this->cache_policy = $cache_policy;
	}

	/**
	 * Register endpoint and discovery output.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_render_markdown' ), 0 );
		add_action( 'template_redirect', array( $this, 'send_discovery_headers' ), 1 );
		add_action( 'wp_head', array( $this, 'render_head_links' ), 8 );
	}

	/**
	 * Render one exact Markdown alternate request.
	 */
	public function maybe_render_markdown(): void {
		$resource = $this->resolver->current_markdown_request();
		if ( null === $resource ) {
			return;
		}

		$post_id = (int) $resource['post']->ID;

		if ( $this->cache_policy->send_revalidation_headers( 'markdown', $post_id ) ) {
			exit;
		}

		$content = $this->resolver->render_markdown( $resource );

		status_header( 200 );

		$charset = get_bloginfo( 'charset' );
		header( 'Content-Type: text/markdown; charset=' . ( '' !== $charset ? $charset : 'UTF-8' ) );
		header( 'X-Content-Type-Options: nosniff' );

		$links = array(
			'<' . $resource['html_url'] . '>; rel="alternate"; type="text/html"',
		);

		if ( $this->llms_txt->enabled() ) {
			$links[] = '<' . home_url( '/llms.txt' ) . '>; rel="describedby"';
		}

		header( 'Link: ' . implode( ', ', $links ) );

		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		if ( 'HEAD' !== $method ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown assembled from validated public resources and escaped authored text.
			echo $content;
		}

		exit;
	}

	/**
	 * Advertise Markdown and llms.txt discovery through HTTP Link headers.
	 */
	public function send_discovery_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		$alternate = $this->resolver->current_alternate_url();
		if ( null === $alternate ) {
			return;
		}

		$links = array(
			'<' . $alternate . '>; rel="alternate"; type="text/markdown"',
		);

		if ( $this->llms_txt->enabled() ) {
			$links[] = '<' . home_url( '/llms.txt' ) . '>; rel="describedby"';
		}

		header( 'Link: ' . implode( ', ', $links ), false );
	}

	/**
	 * Advertise Markdown and llms.txt discovery in HTML head output.
	 */
	public function render_head_links(): void {
		$alternate = $this->resolver->current_alternate_url();
		if ( null === $alternate ) {
			return;
		}

		echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $alternate ) . '" />' . "\n";

		if ( $this->llms_txt->enabled() ) {
			echo '<link rel="describedby" href="' . esc_url( home_url( '/llms.txt' ) ) . '" />' . "\n";
		}
	}
}
