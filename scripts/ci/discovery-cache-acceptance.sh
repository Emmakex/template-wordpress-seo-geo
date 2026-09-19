#!/usr/bin/env bash
# Phase 6G discovery cache/invalidation acceptance.
#
# This module is sourced by self-contained-theme-smoke.sh and reuses its
# disposable WordPress environment and structured diagnostics.

printf '[self-contained] Checking discovery cache generation and invalidation.\n'

CACHE_EVAL_ERROR="${TMP_DIR}/discovery-cache-eval.stderr"
CACHE_EVAL_CODE="$(cat <<'PHP'
$cache = \SeoGeo\Core\Runtime::discovery_cache();
$llms = \SeoGeo\Core\Runtime::llms_txt();
$markdown = \SeoGeo\Core\Runtime::markdown_alternates();

if ( ! $cache || ! $llms || ! $markdown ) {
	throw new RuntimeException( 'Discovery cache authorities are unavailable.' );
}

$baseline_key = $cache->key( 'acceptance', 'generation' );
$cache->set_text( $baseline_key, 'baseline-value' );
$baseline_found = false;
$baseline_value = $cache->get_text( $baseline_key, $baseline_found );

if ( ! $baseline_found || 'baseline-value' !== $baseline_value ) {
	throw new RuntimeException( 'Baseline cache round-trip failed.' );
}

update_option( 'seo_geo_phase6g_unrelated', 'one', false );
$unrelated_key = $cache->key( 'acceptance', 'generation' );
delete_option( 'seo_geo_phase6g_unrelated' );

if ( $baseline_key !== $unrelated_key ) {
	throw new RuntimeException( 'Unrelated option invalidated discovery cache.' );
}

$original_description = (string) get_option( 'blogdescription', '' );
usleep( 1000 );
update_option( 'blogdescription', $original_description . ' Phase 6G invalidation' );
$option_key = $cache->key( 'acceptance', 'generation' );

if ( $baseline_key === $option_key ) {
	throw new RuntimeException( 'Relevant WordPress option did not invalidate discovery cache.' );
}

$old_found = false;
$old_value = $cache->get_text( $baseline_key, $old_found );
if ( ! $old_found || 'baseline-value' !== $old_value ) {
	throw new RuntimeException( 'Generation invalidation unexpectedly flushed the old cache entry.' );
}

update_option( 'blogdescription', $original_description );

$user_id = wp_insert_user(
	array(
		'user_login'   => 'phase6g-cache-author',
		'user_pass'    => 'phase6g-cache-pass',
		'user_email'   => 'phase6g-cache-author@example.test',
		'display_name' => 'Phase 6G Cache Author A',
		'role'         => 'author',
	)
);

if ( is_wp_error( $user_id ) ) {
	throw new RuntimeException( 'Could not create Phase 6G author fixture.' );
}

$post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Phase 6G Cached Article',
		'post_name'    => 'phase-6g-cached-article',
		'post_excerpt' => 'Phase 6G cached excerpt.',
		'post_content' => '<!-- wp:paragraph --><p>Phase 6G cached body A.</p><!-- /wp:paragraph -->',
		'post_author'  => (int) $user_id,
	),
	true
);

if ( is_wp_error( $post_id ) ) {
	throw new RuntimeException( 'Could not create Phase 6G post fixture.' );
}

$post_key_before = $cache->key( 'acceptance', 'post' );
$cache->set_text( $post_key_before, 'post-before' );
usleep( 1000 );
wp_update_post(
	array(
		'ID'         => (int) $post_id,
		'post_title' => 'Phase 6G Cached Article Updated',
	)
);
$post_key_after = $cache->key( 'acceptance', 'post' );

if ( $post_key_before === $post_key_after ) {
	throw new RuntimeException( 'Post update did not invalidate discovery cache.' );
}

$meta_key_before = $cache->key( 'acceptance', 'post-meta' );
usleep( 1000 );
update_post_meta( (int) $post_id, '_phase6g_cache_probe', 'updated' );
$meta_key_after = $cache->key( 'acceptance', 'post-meta' );

if ( $meta_key_before === $meta_key_after ) {
	throw new RuntimeException( 'Post metadata update did not invalidate discovery cache.' );
}

$user_key_before = $cache->key( 'acceptance', 'user' );
usleep( 1000 );
$user_update = wp_update_user(
	array(
		'ID'           => (int) $user_id,
		'display_name' => 'Phase 6G Cache Author B',
	)
);

if ( is_wp_error( $user_update ) ) {
	throw new RuntimeException( 'Could not update Phase 6G author fixture.' );
}

$user_key_after = $cache->key( 'acceptance', 'user' );
if ( $user_key_before === $user_key_after ) {
	throw new RuntimeException( 'User update did not invalidate discovery cache.' );
}

update_option(
	\SeoGeo\Core\Geo\LlmsTxtResolver::OPTION_NAME,
	array(
		'enabled'  => true,
		'summary'  => 'Phase 6G summary A.',
		'sections' => array(
			array(
				'title'    => 'Phase 6G resources',
				'post_ids' => array( (int) $post_id ),
			),
		),
	),
	false
);

$llms_a = $llms->resolve();
if ( ! is_string( $llms_a ) || ! str_contains( $llms_a, 'Phase 6G summary A.' ) ) {
	throw new RuntimeException( 'Initial llms.txt document did not resolve.' );
}

$llms_cache_key = $cache->key( 'llms_txt', 'document' );
$llms_found = false;
$llms_cached = $cache->get_text( $llms_cache_key, $llms_found );

if ( ! $llms_found || $llms_a !== $llms_cached ) {
	throw new RuntimeException( 'llms.txt output was not stored in discovery cache.' );
}

usleep( 1000 );
update_option(
	\SeoGeo\Core\Geo\LlmsTxtResolver::OPTION_NAME,
	array(
		'enabled'  => true,
		'summary'  => 'Phase 6G summary B.',
		'sections' => array(
			array(
				'title'    => 'Phase 6G resources',
				'post_ids' => array( (int) $post_id ),
			),
		),
	),
	false
);

$llms_b = $llms->resolve();
if (
	! is_string( $llms_b )
	|| ! str_contains( $llms_b, 'Phase 6G summary B.' )
	|| str_contains( $llms_b, 'Phase 6G summary A.' )
) {
	throw new RuntimeException( 'llms.txt cache remained stale after option update.' );
}

update_option(
	\SeoGeo\Core\Geo\MarkdownAlternateResolver::OPTION_NAME,
	array( 'enabled' => true ),
	false
);

$post = get_post( (int) $post_id );
if ( ! $post instanceof WP_Post ) {
	throw new RuntimeException( 'Phase 6G post fixture disappeared.' );
}

$html_url = get_permalink( $post );
if ( ! is_string( $html_url ) ) {
	throw new RuntimeException( 'Phase 6G post permalink is unavailable.' );
}

$resource = array(
	'post'         => $post,
	'html_url'     => $html_url,
	'markdown_url' => trailingslashit( $html_url ) . 'index.md',
	'language'     => null,
);

$markdown_a = $markdown->render_markdown( $resource );
if (
	! str_contains( $markdown_a, 'Phase 6G Cached Article Updated' )
	|| ! str_contains( $markdown_a, 'Phase 6G Cache Author B' )
) {
	throw new RuntimeException( 'Initial Markdown cache fixture did not render expected content.' );
}

$markdown_identity = (string) $post->ID . '|' . $html_url . '|';
$markdown_cache_key = $cache->key( 'markdown', $markdown_identity );
$markdown_found = false;
$markdown_cached = $cache->get_text( $markdown_cache_key, $markdown_found );

if ( ! $markdown_found || $markdown_a !== $markdown_cached ) {
	throw new RuntimeException( 'Markdown output was not stored in discovery cache.' );
}

usleep( 1000 );
wp_update_post(
	array(
		'ID'           => (int) $post_id,
		'post_title'   => 'Phase 6G Refreshed Article',
		'post_content' => '<!-- wp:paragraph --><p>Phase 6G cached body B.</p><!-- /wp:paragraph -->',
	)
);

$post = get_post( (int) $post_id );
if ( ! $post instanceof WP_Post ) {
	throw new RuntimeException( 'Phase 6G refreshed post fixture is unavailable.' );
}
$resource['post'] = $post;

$markdown_b = $markdown->render_markdown( $resource );
if (
	! str_contains( $markdown_b, 'Phase 6G Refreshed Article' )
	|| ! str_contains( $markdown_b, 'Phase 6G cached body B.' )
	|| str_contains( $markdown_b, 'Phase 6G Cached Article Updated' )
) {
	throw new RuntimeException( 'Markdown cache remained stale after post update.' );
}

usleep( 1000 );
$user_update = wp_update_user(
	array(
		'ID'           => (int) $user_id,
		'display_name' => 'Phase 6G Cache Author C',
	)
);

if ( is_wp_error( $user_update ) ) {
	throw new RuntimeException( 'Could not update Phase 6G author provenance fixture.' );
}

$markdown_c = $markdown->render_markdown( $resource );
if (
	! str_contains( $markdown_c, 'Phase 6G Cache Author C' )
	|| str_contains( $markdown_c, 'Phase 6G Cache Author B' )
) {
	throw new RuntimeException( 'Markdown cache remained stale after author update.' );
}

delete_option( \SeoGeo\Core\Geo\LlmsTxtResolver::OPTION_NAME );
delete_option( \SeoGeo\Core\Geo\MarkdownAlternateResolver::OPTION_NAME );

echo 'ok';
PHP
)"

if ! CACHE_RESULT="$(wp_cli eval "$CACHE_EVAL_CODE" 2>"$CACHE_EVAL_ERROR" | tr -d '\r\n')"; then
  CACHE_ERROR="$(tr -d '\r' <"$CACHE_EVAL_ERROR" | head -c 320)"
  fail_smoke \
    "discovery-cache-eval" \
    "Discovery cache/invalidation acceptance failed" \
    "complete generation, hook and cached-document contract passes" \
    "${CACHE_ERROR:-wp eval failed}" \
    "wp eval Phase 6G cache acceptance"
fi

[[ "$CACHE_RESULT" == "ok" ]] \
  || fail_smoke "discovery-cache-result" "Discovery cache acceptance returned an unexpected result" "ok" "$CACHE_RESULT"

printf '[self-contained] Phase 6G discovery cache/invalidation OK.\n'
