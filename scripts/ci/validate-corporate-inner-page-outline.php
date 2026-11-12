<?php
declare(strict_types=1);
// Test the render-only heading contract without a WordPress runtime.
namespace {
	$GLOBALS['page_outline_state'] = array( 'is_admin'=>false, 'page'=>true, 'front'=>false, 'id'=>50476 );
	$GLOBALS['page_outline_hooks'] = array();
	function add_filter( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['page_outline_hooks'][$tag] = array( $callback, $priority, $accepted_args );
	}
	function is_admin(): bool { return $GLOBALS['page_outline_state']['is_admin']; }
	function is_singular( string $type = '' ): bool {
		return $GLOBALS['page_outline_state']['page'] && ( '' === $type || 'page' === $type );
	}
	function is_front_page(): bool { return $GLOBALS['page_outline_state']['front']; }
	function get_queried_object_id(): int { return $GLOBALS['page_outline_state']['id']; }
}
namespace SeoGeo\Theme\Templates {
	require_once dirname( __DIR__, 2 ) . '/packages/seo-geo-theme/inc/Templates/InnerPageOutline.php';
	InnerPageOutline::register();
	assert( isset( $GLOBALS['page_outline_hooks']['render_block_core/post-title'] ) );
	assert( isset( $GLOBALS['page_outline_hooks']['render_block_core/post-content'] ) );
	$ctx = (object) array( 'context'=>array( 'postId'=>50476 ) );
	$conf = array( 'attrs'=>array( 'level'=>1 ) );
	$body = '<h1 class="legacy">Career <em>opportunities</em></h1><p>Paragraph unchanged</p><h1>Second</h1><a href="../contacto/">Contact</a><h1 data-x="y">Third</h1>';
	if ( '<h1 class="seo-geo-page-title">Careers</h1>' !== InnerPageOutline::record_template_title( '<h1 class="seo-geo-page-title">Careers</h1>', $conf, $ctx ) ) {
		throw new \RuntimeException( 'Native post-title altered' );
	}
	$normalized = InnerPageOutline::normalize_content_heading( $body, array(), $ctx );
	if ( 0 !== preg_match_all( '/<h1(?:\\s|>)/i', $normalized ) || 3 !== preg_match_all( '/<h2(?:\\s|>)/i', $normalized ) ||
		! str_contains( $normalized, '<em>opportunities</em>' ) || ! str_contains( $normalized, 'href="../contacto/"' ) ) {
		throw new \RuntimeException( 'Template H1 must demote all inner content H1 without losing content' );
	}
	$GLOBALS['page_outline_state']['id'] = 50344;
	$blog = '<section><h1>First heading</h1><p>Text</p><h1 class="two">Second</h1></section>';
	$blogResult = InnerPageOutline::normalize_content_heading( $blog, array(), (object) array( 'context'=>array( 'postId'=>50344 ) ) );
	if ( 1 !== preg_match_all( '/<h1(?:\\s|>)/i', $blogResult ) || 1 !== preg_match_all( '/<h2(?:\\s|>)/i', $blogResult ) ) {
		throw new \RuntimeException( 'Without a template title, retain exactly one content H1' );
	}
	$GLOBALS['page_outline_state']['front'] = true;
	if ( $blog !== InnerPageOutline::normalize_content_heading( $blog, array(), null ) ) {
		throw new \RuntimeException( 'Strategic homepage was changed' );
	}
	$GLOBALS['page_outline_state']['front'] = false;
	$GLOBALS['page_outline_state']['page'] = false;
	if ( $body !== InnerPageOutline::normalize_content_heading( $body, array(), $ctx ) ) {
		throw new \RuntimeException( 'Non-page document changed' );
	}
	$GLOBALS['page_outline_state']['page'] = true;
	if ( $blog !== InnerPageOutline::normalize_content_heading( $blog, array(), $ctx ) ) {
		throw new \RuntimeException( 'Queried post ID isolation failed' );
	}
	print( "PASS Corporate inner-page H1 render guard; WordPress content remains unchanged\n" );
}
