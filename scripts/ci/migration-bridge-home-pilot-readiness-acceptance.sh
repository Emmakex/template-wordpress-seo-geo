#!/usr/bin/env bash
# Clean Home field-pilot readiness acceptance.

printf '[smoke] Checking clean Home field-pilot readiness gate.\n'

READINESS_RUNNER="$TMP_DIR/home-pilot-readiness-runner.php"
cat >"$READINESS_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Reset\CleanHomeRebuilder;
use SeoGeo\MigrationBridge\Reset\HomePilotReadiness;

wp_set_current_user( 1 );

$readiness = Plugin::home_pilot_readiness();
$builder   = Plugin::clean_home_rebuilder();

if ( ! $readiness instanceof HomePilotReadiness || ! $builder instanceof CleanHomeRebuilder ) {
	throw new RuntimeException( 'Home field-pilot readiness service did not initialize.' );
}

$before   = $readiness->report();
$home     = $builder->plan();
$draft_id = (int) ( $home['existing_draft'] ?? 0 );
$draft    = 0 < $draft_id ? get_post( $draft_id ) : null;
if ( ! $draft instanceof WP_Post ) {
	throw new RuntimeException( 'Clean Home draft is unavailable for readiness acceptance.' );
}

$original_content = (string) $draft->post_content;
$legacy_content   = $original_content . "\n<!-- wp:html --><div class=\"elementor-widget\">legacy probe</div><!-- /wp:html -->";
wp_update_post(
	array(
		'ID'           => $draft_id,
		'post_content' => $legacy_content,
	)
);

$legacy_blocked = $readiness->report();

wp_update_post(
	array(
		'ID'           => $draft_id,
		'post_content' => $original_content,
	)
);

$restored = $readiness->report();

echo wp_json_encode(
	array(
		'before'         => $before,
		'legacy_blocked' => $legacy_blocked,
		'restored'       => $restored,
		'draft_sha256'   => hash( 'sha256', (string) get_post_field( 'post_content', $draft_id ) ),
		'original_sha256'=> hash( 'sha256', $original_content ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$READINESS_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/home-pilot-readiness-runner.php \
  || fail_smoke "home-pilot-readiness-runner-copy" "Could not copy Home pilot readiness runner" "runner copied" "docker cp failed"

if ! READINESS_REPORT="$(wp_cli eval-file /var/www/html/wp-content/home-pilot-readiness-runner.php 2>"$TMP_DIR/home-pilot-readiness.stderr")"; then
  READINESS_ERROR="$(tr -d '\r' <"$TMP_DIR/home-pilot-readiness.stderr" | head -c 1800)"
  fail_smoke "home-pilot-readiness-runner" "Home pilot readiness runner failed" "JSON readiness report" "${READINESS_ERROR:-wp eval-file failed}"
fi

printf '%s' "$READINESS_REPORT" >"$TMP_DIR/home-pilot-readiness-report.json"

if ! python3 - "$TMP_DIR/home-pilot-readiness-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

before = report["before"]
legacy = report["legacy_blocked"]
restored = report["restored"]

assert before["mode"] == "clean-home-field-pilot-readiness"
assert before["ready_for_browser_qa"] is True
assert before["blockers"] == []
assert before["checks"]["sandbox_marker"] is True
assert before["checks"]["reset_completed"] is True
assert before["checks"]["theme_active"] is True
assert before["checks"]["draft_ready"] is True
assert before["checks"]["hydration_ready"] is True
assert before["checks"]["source_unchanged"] is True
assert before["checks"]["front_page_unchanged"] is True
assert before["checks"]["seo_handoff_applied"] is True
assert before["checks"]["seo_cutover_ready"] is True
assert before["checks"]["legacy_builder_free"] is True
assert before["checks"]["preset_placeholders_removed"] is True
assert before["manual_browser_checks"] == [
    "visual-layout",
    "responsive-behavior",
    "accessibility",
    "seo-geo-rendered-output",
    "performance",
]

assert legacy["ready_for_browser_qa"] is False
assert "legacy-builder-markup-detected" in legacy["blockers"]
assert legacy["checks"]["legacy_builder_free"] is False
assert "elementor" in legacy["legacy_markers"]

assert restored["ready_for_browser_qa"] is True
assert restored["blockers"] == []
assert restored["checks"]["legacy_builder_free"] is True
assert report["draft_sha256"] == report["original_sha256"]
PY
then
  fail_smoke "home-pilot-readiness-assertions" "Home pilot readiness gate violated clean-state, legacy-marker detection or exact restoration invariants" "all assertions pass" "assertion failure"
fi

printf '[smoke] Clean Home field-pilot readiness OK: machine preflight passes clean state, blocks injected legacy builder markup and returns to ready after exact restoration.\n'
