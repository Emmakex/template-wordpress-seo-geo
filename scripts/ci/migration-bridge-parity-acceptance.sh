#!/usr/bin/env bash
# Phase 8F SEO/GEO parity and regression engine acceptance.
#
# Sourced after 8B has persisted a baseline and 8E has proven controlled
# transformations. This acceptance exercises parity semantics without exposing
# private content or weakening the sandbox indexing guard.

printf '[smoke] Checking Phase 8F SEO/GEO parity engine.\n'

PARITY_RUNNER="$TMP_DIR/migration-parity-runner.php"
cat >"$PARITY_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Parity\ParityAllowlist;
use SeoGeo\MigrationBridge\Parity\SeoParityEngine;
use SeoGeo\MigrationBridge\Plugin;

$baseline_envelope = Plugin::baseline_snapshotter()?->latest();
$baseline = is_array( $baseline_envelope ) && isset( $baseline_envelope['snapshot'] ) && is_array( $baseline_envelope['snapshot'] )
	? $baseline_envelope['snapshot']
	: null;

$engine = Plugin::parity_engine();
if ( ! $engine instanceof SeoParityEngine || ! is_array( $baseline ) ) {
	throw new RuntimeException( 'Phase 8F prerequisites are unavailable.' );
}

$pages = isset( $baseline['crawl']['pages'] ) && is_array( $baseline['crawl']['pages'] )
	? $baseline['crawl']['pages']
	: array();

$target_index = null;
foreach ( $pages as $index => $page ) {
	if (
		is_array( $page )
		&& isset( $page['url'] )
		&& is_string( $page['url'] )
		&& str_ends_with( $page['url'], '/native-seo-fixture/' )
	) {
		$target_index = $index;
		break;
	}
}

if ( null === $target_index || ! isset( $pages[ $target_index ] ) || ! is_array( $pages[ $target_index ] ) ) {
	throw new RuntimeException( 'Parity target fixture was not present in the baseline.' );
}

$target_path = ParityAllowlist::normalize_path( (string) $pages[ $target_index ]['url'] );

$identical = $engine->compare( $baseline, $baseline );

$title_candidate = $baseline;
$title_before = $title_candidate['crawl']['pages'][ $target_index ]['title'] ?? null;
$title_after = 'Approved migrated title';
$title_candidate['crawl']['pages'][ $target_index ]['title'] = $title_after;

$title_regression = $engine->compare( $baseline, $title_candidate );

$title_rule = array(
	'id'            => 'approved-title-improvement',
	'path'          => $target_path,
	'signal'        => 'title',
	'before_sha256' => ParityAllowlist::fingerprint( $title_before ),
	'after_sha256'  => ParityAllowlist::fingerprint( $title_after ),
	'reason'        => 'Approved editorial title improvement.',
);
$title_allowed = $engine->compare( $baseline, $title_candidate, array( $title_rule ) );

$stale_rule = $title_rule;
$stale_rule['after_sha256'] = ParityAllowlist::fingerprint( 'Different unapproved title' );
$title_stale = $engine->compare( $baseline, $title_candidate, array( $stale_rule ) );

$owner_candidate = $baseline;
$owner_candidate['crawl']['pages'][ $target_index ]['ownership']['canonical_count'] = 2;
$owner_rule = array(
	'id'            => 'attempted-canonical-duplicate-approval',
	'path'          => $target_path,
	'signal'        => 'canonical-owner-conflict',
	'before_sha256' => ParityAllowlist::fingerprint( 1 ),
	'after_sha256'  => ParityAllowlist::fingerprint( 2 ),
	'reason'        => 'This must never override a hard ownership conflict.',
);
$owner_conflict = $engine->compare( $baseline, $owner_candidate, array( $owner_rule ) );

$schema_candidate = $baseline;
$schema_blocks = $schema_candidate['crawl']['pages'][ $target_index ]['schema']['blocks'] ?? array();
if ( ! is_array( $schema_blocks ) || array() === $schema_blocks || ! is_array( $schema_blocks[0] ) ) {
	throw new RuntimeException( 'Parity Schema fixture is unavailable.' );
}
$schema_candidate['crawl']['pages'][ $target_index ]['schema']['blocks'][] = $schema_blocks[0];
$duplicate_hash = isset( $schema_blocks[0]['sha256'] ) && is_string( $schema_blocks[0]['sha256'] )
	? $schema_blocks[0]['sha256']
	: '';
$schema_rule = array(
	'id'            => 'attempted-schema-duplicate-approval',
	'path'          => $target_path,
	'signal'        => 'schema-duplicate-blocks',
	'before_sha256' => ParityAllowlist::fingerprint( array() ),
	'after_sha256'  => ParityAllowlist::fingerprint( array( $duplicate_hash ) ),
	'reason'        => 'This must never override duplicate Schema output.',
);
$schema_conflict = $engine->compare( $baseline, $schema_candidate, array( $schema_rule ) );

$broken_candidate = $baseline;
$home_index = null;
foreach ( $broken_candidate['crawl']['pages'] as $index => $page ) {
	if ( is_array( $page ) && isset( $page['url'] ) && is_string( $page['url'] ) && '/' === ParityAllowlist::normalize_path( $page['url'] ) ) {
		$home_index = $index;
		break;
	}
}
if ( null === $home_index ) {
	throw new RuntimeException( 'Parity home fixture is unavailable.' );
}

$broken_url = home_url( '/phase-8f-broken-target/' );
$broken_path = ParityAllowlist::normalize_path( $broken_url );
$broken_candidate['crawl']['pages'][ $home_index ]['internal_links'][] = $broken_url;
$broken_candidate['crawl']['pages'][] = array(
	'url'              => $broken_url,
	'http'             => array(
		'status'       => 404,
		'content_type' => 'text/html',
		'location'     => '',
		'x_robots_tag' => '',
	),
	'indexability'     => array(
		'state'     => 'not-found',
		'indexable' => false,
		'reasons'   => array( 'http-404' ),
	),
	'title'            => null,
	'meta_description' => null,
	'canonical'        => null,
	'robots'           => null,
	'html_lang'        => null,
	'hreflang'         => array(),
	'open_graph'       => array(),
	'schema'           => array(
		'block_count' => 0,
		'types'       => array(),
		'blocks'      => array(),
	),
	'headings'         => array(),
	'h1_count'         => 0,
	'breadcrumbs'      => array(),
	'internal_links'   => array(),
	'primary_content'  => array(
		'bytes'      => 0,
		'word_count' => 0,
		'sha256'     => null,
	),
	'ownership'        => array(
		'title_count'            => 0,
		'meta_description_count' => 0,
		'canonical_count'        => 0,
		'robots_count'           => 0,
		'hreflang_duplicates'    => array(),
	),
);

$broken_presence_rule = array(
	'id'            => 'approved-fixture-presence',
	'path'          => $broken_path,
	'signal'        => 'presence',
	'before_sha256' => ParityAllowlist::fingerprint( 'missing' ),
	'after_sha256'  => ParityAllowlist::fingerprint( 'present' ),
	'reason'        => 'Acceptance-only synthetic target.',
);
$home_links_before = $baseline['crawl']['pages'][ $home_index ]['internal_links'] ?? array();
$home_links_after = $broken_candidate['crawl']['pages'][ $home_index ]['internal_links'] ?? array();
sort( $home_links_before );
sort( $home_links_after );
$broken_link_change_rule = array(
	'id'            => 'approved-fixture-link-addition',
	'path'          => '/',
	'signal'        => 'internal-links',
	'before_sha256' => ParityAllowlist::fingerprint(
		array_map(
			static fn( string $url ): string => ParityAllowlist::normalize_path( $url ),
			$home_links_before
		)
	),
	'after_sha256'  => ParityAllowlist::fingerprint(
		array_map(
			static fn( string $url ): string => ParityAllowlist::normalize_path( $url ),
			$home_links_after
		)
	),
	'reason'        => 'Acceptance-only synthetic link addition.',
);
$broken_link = $engine->compare(
	$baseline,
	$broken_candidate,
	array(
		$broken_presence_rule,
		$broken_link_change_rule,
	)
);

$invalid = $engine->compare( array(), $baseline );

echo wp_json_encode(
	array(
		'target_path'       => $target_path,
		'identical'         => $identical,
		'title_regression'  => $title_regression,
		'title_allowed'     => $title_allowed,
		'title_stale'       => $title_stale,
		'owner_conflict'    => $owner_conflict,
		'schema_conflict'   => $schema_conflict,
		'broken_link'       => $broken_link,
		'invalid'           => $invalid,
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$PARITY_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-parity-runner.php   || fail_smoke "parity-runner-copy" "Could not copy Phase 8F runner" "runner copied" "docker cp failed"

if ! PARITY_REPORT="$(wp_cli eval-file /var/www/html/wp-content/migration-parity-runner.php 2>"$TMP_DIR/migration-parity.stderr")"; then
  PARITY_ERROR="$(tr -d '\r' <"$TMP_DIR/migration-parity.stderr" | head -c 700)"
  fail_smoke "parity-runner" "Phase 8F parity runner failed" "JSON parity report" "${PARITY_ERROR:-wp eval-file failed}"
fi

printf '%s' "$PARITY_REPORT" >"$TMP_DIR/migration-parity-report.json"

if ! PARITY_ASSERTION="$(python3 - "$TMP_DIR/migration-parity-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

identical = report["identical"]
assert identical["accepted"] is True
assert identical["summary"]["regressions"] == 0
assert identical["summary"]["unknown"] == 0
assert identical["differences"] == []
assert identical["checks"] == {
    "url_status_indexability": True,
    "canonical_robots": True,
    "hreflang": True,
    "title_meta": True,
    "schema_conflicts": True,
    "redirect_map": True,
    "internal_links": True,
    "sitemap_consistency": True,
}
assert identical["external_gates"] == {
    "performance": "required-on-representative-migrated-pages",
    "accessibility": "required-on-representative-migrated-pages",
}
assert identical["safety"]["mutations_performed"] is False
assert identical["safety"]["production_cutover_allowed"] is False
assert identical["safety"]["allowlist_requires_exact_fingerprints"] is True
assert identical["safety"]["legacy_output_is_authority"] is False

title_regression = report["title_regression"]
assert title_regression["accepted"] is False
title_diff = next(row for row in title_regression["differences"] if row["signal"] == "title")
assert title_diff["status"] == "regression"
assert title_diff["allowable"] is True
assert title_diff["allowlist"] is None

title_allowed = report["title_allowed"]
assert title_allowed["accepted"] is True
allowed_diff = next(row for row in title_allowed["differences"] if row["signal"] == "title")
assert allowed_diff["status"] == "allowed"
assert allowed_diff["allowlist"]["id"] == "approved-title-improvement"
assert allowed_diff["allowable"] is True

title_stale = report["title_stale"]
assert title_stale["accepted"] is False
stale_diff = next(row for row in title_stale["differences"] if row["signal"] == "title")
assert stale_diff["status"] == "regression"
assert stale_diff["allowlist"] is None

owner = report["owner_conflict"]
assert owner["accepted"] is False
owner_diff = next(row for row in owner["differences"] if row["signal"] == "canonical-owner-conflict")
assert owner_diff["status"] == "regression"
assert owner_diff["allowable"] is False
assert owner_diff["allowlist"] is None

schema = report["schema_conflict"]
assert schema["accepted"] is False
schema_diff = next(row for row in schema["differences"] if row["signal"] == "schema-duplicate-blocks")
assert schema_diff["status"] == "regression"
assert schema_diff["allowable"] is False
assert schema_diff["allowlist"] is None

broken = report["broken_link"]
assert broken["accepted"] is False
broken_diff = next(row for row in broken["differences"] if row["signal"].startswith("internal-link-broken-"))
assert broken_diff["status"] == "regression"
assert broken_diff["allowable"] is False
assert broken_diff["allowlist"] is None

invalid = report["invalid"]
assert invalid["accepted"] is False
assert "baseline-snapshot-invalid" in invalid["blockers"]
assert invalid["summary"]["unknown"] >= 1

for section in (
    "identical",
    "title_regression",
    "title_allowed",
    "title_stale",
    "owner_conflict",
    "schema_conflict",
    "broken_link",
):
    payload = report[section]
    assert "post_content" not in json.dumps(payload)
    for row in payload["differences"]:
        assert len(row["before_sha256"]) == 64
        assert len(row["after_sha256"]) == 64
        assert "before" not in row
        assert "after" not in row

print("ok")
PY
)"; then
  fail_smoke "parity-contract" "Phase 8F parity contract is invalid" "strict regression detection + exact allowlist + non-allowable hard conflicts" "${PARITY_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Phase 8F parity engine OK: identical baseline passes; exact approved title change passes; stale approval, duplicate ownership/Schema and broken links remain blocking regressions.\n'
