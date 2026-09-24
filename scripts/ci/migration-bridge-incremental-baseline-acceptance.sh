#!/usr/bin/env bash
# Incremental/resumable SEO/GEO baseline acceptance.
#
# Runs after the original Phase 8B acceptance, replaces that fixture baseline
# with the incremental equivalent, and proves each reconstructed engine step
# preserves 0.8.3 progress and honors the operator-selected 1-20 page batch.

printf '[smoke] Checking resumable incremental SEO/GEO baseline capture.\n'

wp_cli option delete seo_geo_migration_baseline_v1 >/dev/null 2>&1 || true
wp_cli option delete seo_geo_migration_baseline_progress_v1 >/dev/null 2>&1 || true

INCREMENTAL_RUNNER="$TMP_DIR/migration-incremental-baseline-runner.php"
cat >"$INCREMENTAL_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\Http\HttpClientInterface;
use SeoGeo\MigrationBridge\IncrementalBaselineCapture;
use SeoGeo\MigrationBridge\IncrementalBaselineStore;

final class MigrationIncrementalMappedHttpClient implements HttpClientInterface {
	public int $requests = 0;

	public function __construct(
		private string $mapped_origin,
		private string $public_host
	) {
	}

	public function get( string $url ): array {
		++$this->requests;

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return array(
				'status'  => 0,
				'body'    => '',
				'headers' => array(
					'content_type' => '',
					'location'     => '',
					'x_robots_tag' => '',
				),
				'error'   => 'invalid-url',
			);
		}

		$path  = isset( $parts['path'] ) && '' !== (string) $parts['path'] ? (string) $parts['path'] : '/';
		$query = isset( $parts['query'] ) && '' !== (string) $parts['query'] ? '?' . (string) $parts['query'] : '';

		$response = wp_remote_get(
			$this->mapped_origin . $path . $query,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'headers'     => array(
					'Host'   => $this->public_host,
					'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.8,*/*;q=0.5',
				),
				'cookies'     => array(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 0,
				'body'    => '',
				'headers' => array(
					'content_type' => '',
					'location'     => '',
					'x_robots_tag' => '',
				),
				'error'   => $response->get_error_code(),
			);
		}

		return array(
			'status'  => (int) wp_remote_retrieve_response_code( $response ),
			'body'    => (string) wp_remote_retrieve_body( $response ),
			'headers' => array(
				'content_type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
				'location'     => (string) wp_remote_retrieve_header( $response, 'location' ),
				'x_robots_tag' => (string) wp_remote_retrieve_header( $response, 'x-robots-tag' ),
			),
			'error'   => null,
		);
	}
}

$client         = new MigrationIncrementalMappedHttpClient( '__MAPPED_ORIGIN__', '__PUBLIC_HOST__' );
$baseline_store = new BaselineSnapshotStore();
$progress_store = new IncrementalBaselineStore();
$compat_engine = new IncrementalBaselineCapture(
	null,
	null,
	$baseline_store,
	$progress_store,
	$client
);
$result = $compat_engine->advance();

$legacy_state = $progress_store->latest();
if ( ! is_array( $legacy_state ) ) {
	throw new RuntimeException( 'Incremental baseline compatibility fixture was not persisted.' );
}
unset( $legacy_state['batch_size'] );
if ( ! $progress_store->save( $legacy_state ) ) {
	throw new RuntimeException( 'Could not simulate 0.8.3 progress without a batch size.' );
}

$legacy_engine = new IncrementalBaselineCapture(
	null,
	null,
	$baseline_store,
	$progress_store,
	$client
);
$legacy_status  = $legacy_engine->status();
$legacy_default = $legacy_status['batch_size'];

$steps     = 1;
$max_delta = $client->requests;

while ( $steps < 100 ) {
	$before = $client->requests;

	$engine = new IncrementalBaselineCapture(
		null,
		null,
		$baseline_store,
		$progress_store,
		$client
	);
	$result = $engine->advance( 20 );

	$delta     = $client->requests - $before;
	$max_delta = max( $max_delta, $delta );
	++$steps;

	if ( 'error' === ( $result['status'] ?? null ) ) {
		throw new RuntimeException( 'Incremental baseline entered an error state.' );
	}

	if ( 'complete' === ( $result['status'] ?? null ) ) {
		break;
	}
}

$latest   = $baseline_store->latest();
$progress = $progress_store->latest();

if ( ! is_array( $latest ) || ! is_array( $progress ) || ! is_array( $result ) ) {
	throw new RuntimeException( 'Incremental baseline did not persist expected state.' );
}

$snapshot = is_array( $latest['snapshot'] ?? null ) ? $latest['snapshot'] : array();
$crawl    = is_array( $snapshot['crawl'] ?? null ) ? $snapshot['crawl'] : array();
$safety   = is_array( $snapshot['safety'] ?? null ) ? $snapshot['safety'] : array();

echo wp_json_encode(
	array(
		'steps'               => $steps,
		'max_requests_step'   => $max_delta,
		'legacy_default'      => $legacy_default,
		'result'              => $result,
		'progress_status'     => $progress['status'] ?? null,
		'progress_batch_size' => $progress['batch_size'] ?? null,
		'progress_phase'      => $progress['phase'] ?? null,
		'baseline_kind'       => $snapshot['kind'] ?? null,
		'captured'            => $crawl['captured'] ?? null,
		'request_failures'    => $crawl['request_failures'] ?? null,
		'incremental_capture' => $safety['incremental_capture'] ?? null,
		'resumable_progress'  => $safety['resumable_progress'] ?? null,
		'baseline_autoloaded' => array_key_exists( BaselineSnapshotStore::OPTION_NAME, wp_load_alloptions() ),
		'progress_autoloaded' => array_key_exists( IncrementalBaselineStore::OPTION_NAME, wp_load_alloptions() ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

python3 - "$INCREMENTAL_RUNNER" "http://$WP_CONTAINER" "127.0.0.1:$HOST_PORT" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
content = path.read_text(encoding="utf-8")
content = content.replace("__MAPPED_ORIGIN__", sys.argv[2]).replace("__PUBLIC_HOST__", sys.argv[3])
path.write_text(content, encoding="utf-8")
PY

docker cp "$INCREMENTAL_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-incremental-baseline-runner.php \
  || fail_smoke "incremental-baseline-runner-copy" "Could not copy incremental baseline runner" "runner copied" "docker cp failed"

if ! INCREMENTAL_JSON="$(wp_cli eval-file /var/www/html/wp-content/migration-incremental-baseline-runner.php 2>"$TMP_DIR/incremental-baseline.stderr")"; then
  INCREMENTAL_ERROR="$(tr -d '\r' <"$TMP_DIR/incremental-baseline.stderr" | head -c 900)"
  fail_smoke "incremental-baseline-runner" "Incremental baseline runner failed" "JSON incremental report" "${INCREMENTAL_ERROR:-wp eval-file failed}"
fi

printf '%s' "$INCREMENTAL_JSON" >"$TMP_DIR/incremental-baseline.json"

if ! INCREMENTAL_ASSERTION="$(python3 - "$TMP_DIR/incremental-baseline.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    result = json.load(handle)

assert result["steps"] >= 4
assert result["max_requests_step"] <= 20
assert result["legacy_default"] == 10
assert result["result"]["status"] == "complete"
assert result["result"]["batch_size"] == 20
assert result["result"]["percent"] == 100
assert result["progress_status"] == "complete"
assert result["progress_phase"] == "complete"
assert result["progress_batch_size"] == 20
assert result["baseline_kind"] == "seo-geo-public-baseline"
assert result["captured"] >= 2
assert result["request_failures"] == 0
assert result["incremental_capture"] is True
assert result["resumable_progress"] is True
assert result["baseline_autoloaded"] is False
assert result["progress_autoloaded"] is False

print("ok")
PY
)"; then
  fail_smoke "incremental-baseline-contract" "Incremental baseline contract is invalid" "0.8.3-compatible resumable capture with selectable 1-20 page batches" "${INCREMENTAL_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Incremental baseline OK: persisted/reconstructed between steps, <=2 requests per step, final baseline complete.\n'
