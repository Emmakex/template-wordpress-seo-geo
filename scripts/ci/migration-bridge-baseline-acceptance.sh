#!/usr/bin/env bash
# Phase 8B SEO/GEO public baseline acceptance.
#
# Sourced by wordpress-smoke.sh after the Phase 8A legacy fixture exists.

printf '[smoke] Checking Phase 8B SEO/GEO baseline snapshot.\n'

wp_cli post update "$POST_ID" \
  --post_content='<h2>Baseline migration heading</h2><p>Public baseline body for fingerprinting and parity checks. <a href="/">Return home</a></p>' >/dev/null \
  || fail_smoke "baseline-fixture-content" "Could not enrich baseline fixture content" "fixture content updated" "wp post update failed"

wp_cli rewrite flush --hard >/dev/null \
  || fail_smoke "baseline-rewrite-flush" "Could not flush rewrite rules before baseline capture" "rewrite rules flushed" "wp rewrite flush failed"

if ! BASELINE_STATE_BEFORE="$(wp_cli eval 'global $wpdb; echo hash( "sha256", serialize( array( get_option( "active_plugins", array() ), get_option( "stylesheet" ), get_option( "template" ), get_option( "permalink_structure" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ) ) ) );' 2>"$TMP_DIR/baseline-state-before.stderr" | tr -d '\r\n')"; then
  BASELINE_STATE_BEFORE_ERROR="$(tr -d '\r' <"$TMP_DIR/baseline-state-before.stderr" | head -c 240)"
  fail_smoke "baseline-state-before" "Could not snapshot protected state before baseline capture" "sha256 state snapshot" "${BASELINE_STATE_BEFORE_ERROR:-wp eval failed}"
fi

BASELINE_RUNNER="$TMP_DIR/migration-baseline-runner.php"
cat >"$BASELINE_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\BaselineSnapshotter;
use SeoGeo\MigrationBridge\Http\HttpClientInterface;

final class MigrationBaselineMappedHttpClient implements HttpClientInterface {
	public function __construct(
		private string $mapped_origin,
		private string $public_host
	) {
	}

	public function get( string $url ): array {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return array(
				'status' => 0,
				'body' => '',
				'headers' => array(
					'content_type' => '',
					'location' => '',
					'x_robots_tag' => '',
				),
				'error' => 'invalid-url',
			);
		}

		$path = isset( $parts['path'] ) && '' !== (string) $parts['path'] ? (string) $parts['path'] : '/';
		$query = isset( $parts['query'] ) && '' !== (string) $parts['query'] ? '?' . (string) $parts['query'] : '';
		$response = wp_remote_get(
			$this->mapped_origin . $path . $query,
			array(
				'timeout' => 8,
				'redirection' => 0,
				'headers' => array(
					'Host' => $this->public_host,
					'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.8,*/*;q=0.5',
				),
				'cookies' => array(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 0,
				'body' => '',
				'headers' => array(
					'content_type' => '',
					'location' => '',
					'x_robots_tag' => '',
				),
				'error' => $response->get_error_code(),
			);
		}

		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => (string) wp_remote_retrieve_body( $response ),
			'headers' => array(
				'content_type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
				'location' => (string) wp_remote_retrieve_header( $response, 'location' ),
				'x_robots_tag' => (string) wp_remote_retrieve_header( $response, 'x-robots-tag' ),
			),
			'error' => null,
		);
	}
}

$client = new MigrationBaselineMappedHttpClient( '__MAPPED_ORIGIN__', '__PUBLIC_HOST__' );
$store = new BaselineSnapshotStore();
$snapshotter = new BaselineSnapshotter( null, null, $store, $client );
$snapshot = $snapshotter->capture();
$storage = $snapshotter->persist( $snapshot );
$second_storage = $snapshotter->persist( $snapshot );
$latest = $snapshotter->latest();

echo wp_json_encode(
	array(
		'snapshot' => $snapshot,
		'storage' => $storage,
		'second_storage' => $second_storage,
		'latest' => is_array( $latest )
			? array(
				'id' => $latest['id'] ?? null,
				'sha256' => $latest['sha256'] ?? null,
				'snapshot_kind' => isset( $latest['snapshot']['kind'] ) ? $latest['snapshot']['kind'] : null,
			)
			: null,
		'autoloaded' => array_key_exists( BaselineSnapshotStore::OPTION_NAME, wp_load_alloptions() ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

python3 - "$BASELINE_RUNNER" "http://$WP_CONTAINER" "127.0.0.1:$HOST_PORT" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
content = path.read_text(encoding="utf-8")
content = content.replace("__MAPPED_ORIGIN__", sys.argv[2]).replace("__PUBLIC_HOST__", sys.argv[3])
path.write_text(content, encoding="utf-8")
PY

docker cp "$BASELINE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-baseline-runner.php \
  || fail_smoke "baseline-runner-copy" "Could not copy Phase 8B acceptance runner" "runner copied" "docker cp failed"

if ! BASELINE_REPORT="$(wp_cli eval-file /var/www/html/wp-content/migration-baseline-runner.php 2>"$TMP_DIR/baseline-runner.stderr")"; then
  BASELINE_RUNNER_ERROR="$(tr -d '\r' <"$TMP_DIR/baseline-runner.stderr" | head -c 500)"
  fail_smoke "baseline-runner" "Could not capture/persist the Phase 8B baseline" "JSON baseline report" "${BASELINE_RUNNER_ERROR:-wp eval-file failed}"
fi

printf '%s' "$BASELINE_REPORT" >"$TMP_DIR/baseline-report.json"

if ! BASELINE_ASSERTION="$(python3 - "$TMP_DIR/baseline-report.json" "$BASE_URL" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    result = json.load(handle)

base_url = sys.argv[2].rstrip("/")
snapshot = result["snapshot"]

assert snapshot["schema_version"] == 1
assert snapshot["kind"] == "seo-geo-public-baseline"
assert snapshot["safety"] == {
    "same_origin_only": True,
    "authenticated_requests": False,
    "private_content_collected": False,
    "body_content_persisted": False,
    "legacy_output_is_authority": False,
}
assert snapshot["crawl"]["captured"] >= 2
assert snapshot["crawl"]["request_failures"] == 0
assert snapshot["crawl"]["truncated"] is False

pages = {row["url"]: row for row in snapshot["crawl"]["pages"]}
target = f"{base_url}/native-seo-fixture/"
assert target in pages, sorted(pages)
page = pages[target]
assert page["http"]["status"] == 200
assert page["indexability"]["indexable"] is True
assert page["title"] == "Native SEO Fixture – SEO GEO Smoke"
assert page["meta_description"] == "Native SEO fixture description."
assert page["canonical"] == target
assert page["h1_count"] == 1
assert any(row["level"] == 2 and row["text"] == "Baseline migration heading" for row in page["headings"])
assert f"{base_url}/" in page["internal_links"]
assert page["primary_content"]["sha256"]
assert page["primary_content"]["word_count"] > 0
assert "body" not in page["primary_content"]
assert any(row["property"] == "og:url" and row["content"] == target for row in page["open_graph"])
assert page["schema"]["block_count"] >= 1
assert page["schema"]["types"]
assert all("sha256" in block and "raw" not in block for block in page["schema"]["blocks"])

sitemaps = {row["url"]: row for row in snapshot["sitemaps"]["entries"]}
core_sitemap = f"{base_url}/wp-sitemap.xml"
assert core_sitemap in sitemaps, sorted(sitemaps)
assert sitemaps[core_sitemap]["status"] == 200
assert sitemaps[core_sitemap]["location_count"] >= 1
assert snapshot["sitemaps"]["completeness"] == "same-origin-discovered"
assert snapshot["redirects"]["mode"] == "observed-during-baseline"

storage = result["storage"]
assert storage["saved"] is True
assert storage["id"]
assert storage["sha256"]
assert storage["reason"] is None
assert storage["replaced"] is False

second = result["second_storage"]
assert second["saved"] is False
assert second["reason"] == "baseline-exists"
assert second["id"] == storage["id"]

assert result["latest"]["id"] == storage["id"]
assert result["latest"]["sha256"] == storage["sha256"]
assert result["latest"]["snapshot_kind"] == "seo-geo-public-baseline"
assert result["autoloaded"] is False

print("ok")
PY
)"; then
  fail_smoke "baseline-contract" "Phase 8B baseline report contract is invalid" "public SEO/GEO snapshot + persisted non-autoloaded envelope" "${BASELINE_ASSERTION:-python assertion failed}"
fi

if ! BASELINE_STATE_AFTER="$(wp_cli eval 'global $wpdb; echo hash( "sha256", serialize( array( get_option( "active_plugins", array() ), get_option( "stylesheet" ), get_option( "template" ), get_option( "permalink_structure" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ) ) ) );' 2>"$TMP_DIR/baseline-state-after.stderr" | tr -d '\r\n')"; then
  BASELINE_STATE_AFTER_ERROR="$(tr -d '\r' <"$TMP_DIR/baseline-state-after.stderr" | head -c 240)"
  fail_smoke "baseline-state-after" "Could not snapshot protected state after baseline persistence" "sha256 state snapshot" "${BASELINE_STATE_AFTER_ERROR:-wp eval failed}"
fi

[[ "$BASELINE_STATE_AFTER" == "$BASELINE_STATE_BEFORE" ]] \
  || fail_smoke "baseline-protected-state" "Phase 8B changed protected WordPress/client state outside its dedicated baseline option" "$BASELINE_STATE_BEFORE" "$BASELINE_STATE_AFTER"

BASELINE_OPTION_EXISTS="$(wp_cli eval 'echo false === get_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, false ) ? "missing" : "present";' 2>/dev/null | tr -d '\r\n')"
[[ "$BASELINE_OPTION_EXISTS" == "present" ]] \
  || fail_smoke "baseline-persistence" "Phase 8B baseline option was not persisted" "present" "$BASELINE_OPTION_EXISTS"

printf '[smoke] Phase 8B baseline OK: same-origin public signals captured, bodies excluded, snapshot persisted non-autoloaded, protected client state unchanged.\n'
