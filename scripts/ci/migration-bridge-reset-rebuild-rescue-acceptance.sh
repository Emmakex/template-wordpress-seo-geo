#!/usr/bin/env bash
# Reset/Rebuild Rescue Manifest acceptance.

printf '[smoke] Checking reset-first Rescue Manifest.\n'

RESCUE_RUNNER="$TMP_DIR/reset-rebuild-rescue-runner.php"
cat >"$RESCUE_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Reset\RescueManifest;

wp_set_current_user( 1 );

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Rescue Manifest Page',
		'post_name'    => 'rescue-manifest-page',
		'post_content' => '<!-- wp:paragraph --><p>Keep this useful factual content. <a href="/services/">Services</a></p><!-- /wp:paragraph --><figure><img src="https://example.org/media/keep.jpg" alt="Keep"></figure>',
	),
	true
);
if ( is_wp_error( $page_id ) ) {
	throw new RuntimeException( $page_id->get_error_message() );
}

$post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'Rescue Manifest Draft Post',
		'post_name'    => 'rescue-manifest-draft-post',
		'post_content' => '<!-- wp:paragraph --><p>Draft editorial source.</p><!-- /wp:paragraph -->',
	),
	true
);
if ( is_wp_error( $post_id ) ) {
	throw new RuntimeException( $post_id->get_error_message() );
}

update_post_meta( $page_id, '_yoast_wpseo_title', 'Preserved SEO title' );
update_post_meta( $page_id, '_yoast_wpseo_metadesc', 'Preserved SEO description' );
update_post_meta( $page_id, '_yoast_wpseo_canonical', 'https://example.org/canonical-source/' );

update_option( 'show_on_front', 'page', false );
update_option( 'page_on_front', $page_id, false );

$active_plugins_before = get_option( 'active_plugins', array() );
$stylesheet_before     = get_option( 'stylesheet', '' );
$template_before       = get_option( 'template', '' );
$page_before           = get_post( $page_id );
$page_before_sha       = $page_before instanceof WP_Post ? hash( 'sha256', (string) $page_before->post_content ) : '';

$service = Plugin::rescue_manifest();
if ( ! $service instanceof RescueManifest ) {
	throw new RuntimeException( 'Reset/Rebuild Rescue Manifest did not initialize.' );
}

$manifest = $service->capture();
$saved    = $service->saved();
$page_after = get_post( $page_id );

echo wp_json_encode(
	array(
		'manifest' => $manifest,
		'saved_sha256' => is_array( $saved ) ? (string) ( $saved['manifest_sha256'] ?? '' ) : '',
		'state' => array(
			'active_plugins_unchanged' => $active_plugins_before === get_option( 'active_plugins', array() ),
			'stylesheet_unchanged'     => $stylesheet_before === get_option( 'stylesheet', '' ),
			'template_unchanged'       => $template_before === get_option( 'template', '' ),
			'content_unchanged'        => $page_after instanceof WP_Post
				&& hash_equals( $page_before_sha, hash( 'sha256', (string) $page_after->post_content ) ),
		),
		'fixture' => array(
			'page_id' => $page_id,
			'post_id' => $post_id,
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$RESCUE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/reset-rebuild-rescue-runner.php \
  || fail_smoke "reset-rebuild-rescue-runner-copy" "Could not copy Reset/Rebuild Rescue runner" "runner copied" "docker cp failed"

if ! RESCUE_REPORT="$(wp_cli eval-file /var/www/html/wp-content/reset-rebuild-rescue-runner.php 2>"$TMP_DIR/reset-rebuild-rescue.stderr")"; then
  RESCUE_ERROR="$(tr -d '\r' <"$TMP_DIR/reset-rebuild-rescue.stderr" | head -c 900)"
  fail_smoke "reset-rebuild-rescue-runner" "Reset/Rebuild Rescue Manifest runner failed" "JSON rescue report" "${RESCUE_ERROR:-wp eval-file failed}"
fi

printf '%s' "$RESCUE_REPORT" >"$TMP_DIR/reset-rebuild-rescue-report.json"

if ! python3 - "$TMP_DIR/reset-rebuild-rescue-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

manifest = report["manifest"]
assert manifest["schema_version"] == 1
assert manifest["mode"] == "reset-rebuild-rescue-manifest"
assert len(manifest["manifest_sha256"]) == 64
assert report["saved_sha256"] == manifest["manifest_sha256"]
assert manifest["policy"] == {
    "legacy_theme_preserved": False,
    "legacy_builder_preserved": False,
    "legacy_plugins_preserved": False,
    "dependency_review_required": False,
    "unknown_components_blocking": False,
}

resources = {item["id"]: item for item in manifest["resources"]}
page = resources[report["fixture"]["page_id"]]
post = resources[report["fixture"]["post_id"]]

assert page["post_type"] == "page"
assert page["status"] == "publish"
assert page["slug"] == "rescue-manifest-page"
assert page["path"].endswith("/rescue-manifest-page/")
assert len(page["content_sha256"]) == 64
assert "/services/" in page["links"]
assert "https://example.org/media/keep.jpg" in page["media"]
assert page["seo"]["_yoast_wpseo_title"] == "Preserved SEO title"
assert page["seo"]["_yoast_wpseo_metadesc"] == "Preserved SEO description"
assert page["seo"]["_yoast_wpseo_canonical"] == "https://example.org/canonical-source/"

assert post["post_type"] == "post"
assert post["status"] == "draft"

assert manifest["site"]["front_page_id"] == report["fixture"]["page_id"]
assert manifest["counts"]["resources"] >= 2
assert manifest["counts"]["pages"] >= 1
assert manifest["counts"]["posts"] >= 1

assert report["state"] == {
    "active_plugins_unchanged": True,
    "stylesheet_unchanged": True,
    "template_unchanged": True,
    "content_unchanged": True,
}
PY
then
  fail_smoke "reset-rebuild-rescue-assertions" "Reset/Rebuild Rescue Manifest violated selective-rescue/read-only invariants" "all assertions pass" "assertion failure"
fi

printf '[smoke] Reset/Rebuild Rescue Manifest OK: content/URL/SEO/link/media identities captured without dependency-review gating or theme/plugin/content mutation.\n'
