#!/usr/bin/env bash
# Phase 8H final migration report acceptance.
#
# Sourced after 8G has completed an accepted cutover. The report is read-only;
# only the dedicated report store may persist the final handoff artifact.

printf '[smoke] Checking Phase 8H migration report.\n'

REPORT_RUNNER="$TMP_DIR/migration-report-runner.php"
cat >"$REPORT_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Cutover\PublicSnapshotProviderInterface;
use SeoGeo\MigrationBridge\Parity\ParityAllowlist;
use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Report\MigrationReportEngine;
use SeoGeo\MigrationBridge\Report\MigrationReportStore;

final class Phase8HStaticSnapshotProvider implements PublicSnapshotProviderInterface {
	/**
	 * @var array<string,mixed>
	 */
	private array $snapshot;

	/**
	 * @param array<string,mixed> $snapshot Public snapshot.
	 */
	public function __construct( array $snapshot ) {
		$this->snapshot = $snapshot;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function capture(): array {
		return $this->snapshot;
	}
}

$baseline_envelope = Plugin::baseline_snapshotter()?->latest();
if (
	! is_array( $baseline_envelope )
	|| ! isset( $baseline_envelope['snapshot'] )
	|| ! is_array( $baseline_envelope['snapshot'] )
) {
	throw new RuntimeException( 'Persisted baseline is unavailable for Phase 8H.' );
}

if ( ! Plugin::migration_report() instanceof MigrationReportEngine ) {
	throw new RuntimeException( 'Phase 8H report service is unavailable from the plugin bootstrap.' );
}
if ( ! Plugin::migration_report_store() instanceof MigrationReportStore ) {
	throw new RuntimeException( 'Phase 8H report store is unavailable from the plugin bootstrap.' );
}

$engine = new MigrationReportEngine(
	null,
	null,
	null,
	null,
	new Phase8HStaticSnapshotProvider( $baseline_envelope['snapshot'] )
);

$report = $engine->generate();

$wrong_allowlist = array(
	array(
		'id'            => 'phase-8h-wrong-allowlist',
		'path'          => '/',
		'signal'        => 'title',
		'before_sha256' => ParityAllowlist::fingerprint( 'before' ),
		'after_sha256'  => ParityAllowlist::fingerprint( 'after' ),
		'reason'        => 'Acceptance-only mismatched allowlist.',
	),
);
$blocked = $engine->generate( $wrong_allowlist );

$store        = Plugin::migration_report_store();
$blocked_save = $store->save( $blocked );
$saved        = $store->save( $report );
$duplicate    = $store->save( $report );
$latest       = $store->latest();

$alloptions = wp_load_alloptions();

echo wp_json_encode(
	array(
		'report'       => $report,
		'blocked'      => array(
			'ready_for_handoff' => $blocked['ready_for_handoff'] ?? null,
			'blockers'          => $blocked['blockers'] ?? array(),
			'save'              => $blocked_save,
		),
		'saved'        => $saved,
		'duplicate'    => $duplicate,
		'latest'       => is_array( $latest )
			? array(
				'id'       => $latest['id'] ?? null,
				'saved_at' => $latest['saved_at'] ?? null,
				'sha256'   => $latest['sha256'] ?? null,
				'report_sha256' => is_array( $latest['report'] ?? null ) ? $latest['report']['report_sha256'] ?? null : null,
			)
			: null,
		'autoloaded'   => array_key_exists( MigrationReportStore::OPTION_NAME, $alloptions ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$REPORT_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-report-runner.php \
  || fail_smoke "migration-report-runner-copy" "Could not copy Phase 8H runner" "runner copied" "docker cp failed"

if ! REPORT_JSON="$(wp_cli eval-file /var/www/html/wp-content/migration-report-runner.php 2>"$TMP_DIR/migration-report.stderr")"; then
  REPORT_ERROR="$(tr -d '\r' <"$TMP_DIR/migration-report.stderr" | head -c 900)"
  fail_smoke "migration-report-runner" "Phase 8H report runner failed" "JSON migration report" "${REPORT_ERROR:-wp eval-file failed}"
fi

printf '%s' "$REPORT_JSON" >"$TMP_DIR/migration-report.json"

if grep -Fq '"post_content"' "$TMP_DIR/migration-report.json" \
  || grep -Fq '_elementor_data' "$TMP_DIR/migration-report.json" \
  || grep -Fq 'phase-8g-database-backup' "$TMP_DIR/migration-report.json" \
  || grep -Fq 'phase-8g-uploads-backup' "$TMP_DIR/migration-report.json"; then
  fail_smoke "migration-report-private-data" "Phase 8H report leaked private migration or backup content" "metadata/hashes only" "private marker found"
fi

if ! REPORT_ASSERTION="$(python3 - "$TMP_DIR/migration-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

report = payload["report"]

assert report["schema_version"] == 1
assert report["mode"] == "migration-report"
assert report["ready_for_handoff"] is True
assert report["blockers"] == []
assert len(report["report_sha256"]) == 64

deps = report["dependencies"]
assert deps["before"]["active_plugin_count"] > deps["after"]["active_plugin_count"]
assert deps["delta"]["active_plugins"] < 0
assert deps["before"]["legacy_builder_resources"] >= deps["after"]["legacy_builder_resources"]
assert deps["delta"]["legacy_builder_resources"] <= 0
assert "wordpress-seo/wp-seo.php" in deps["decisions"]["replaced_deactivated"]
assert deps["decisions"]["removed"] == []
assert deps["decisions"]["deletion_performed"] is False
assert "builder:native-blocks" in deps["decisions"]["kept"]

migrations = report["migrations"]
assert migrations["count"] >= 2
for row in migrations["resources"]:
    assert isinstance(row["object_id"], int)
    for key in ("backup_sha256", "before_sha256", "after_sha256"):
        value = row[key]
        assert value is None or len(value) == 64

parity = report["parity"]
assert parity["accepted"] is True
assert parity["summary"]["regressions"] == 0
assert parity["summary"]["unknown"] == 0
assert parity["url_redirect"]["presence_changes"] == 0
assert parity["url_redirect"]["status_changes"] == 0
assert parity["url_redirect"]["redirect_changes"] == 0
assert len(parity["allowlist_sha256"]) == 64

quality = report["quality"]
assert quality["accessibility"]["passed"] is True
assert quality["accessibility"]["comparison_available"] is True
assert quality["accessibility"]["comparison"]["before"]["violations"] == 2
assert quality["accessibility"]["comparison"]["after"]["violations"] == 0
assert quality["performance"]["passed"] is True
assert quality["performance"]["comparison_available"] is True
assert quality["performance"]["comparison"]["before"]["performance_score"] == 98
assert quality["performance"]["comparison"]["after"]["performance_score"] == 100
assert quality["performance"]["comparison"]["after"]["lcp_ms"] == 641.68

cutover = report["cutover"]
assert cutover["status"] == "accepted"
assert cutover["events"][-1]["status"] == "accepted"
assert sorted(cutover["backup_evidence"]) == ["database", "uploads"]
for row in cutover["backup_evidence"].values():
    assert len(row["sha256"]) == 64

manual = report["manual_review"]
assert isinstance(manual["blocking"], list)
assert isinstance(manual["advisory"], list)

disposition = report["bridge_disposition"]
assert disposition["decision"] in ("remove", "retain-audit-only")
assert disposition["runtime_dependency_required"] is False

safety = report["safety"]
assert safety == {
    "mutations_performed": False,
    "private_content_exported": False,
    "raw_backup_content_exported": False,
    "credentials_exported": False,
    "report_is_runtime_dependency": False,
}

blocked = payload["blocked"]
assert blocked["ready_for_handoff"] is False
assert "parity-allowlist-hash-mismatch" in blocked["blockers"]
assert blocked["save"]["saved"] is False
assert blocked["save"]["reason"] == "report-not-ready-for-handoff"

saved = payload["saved"]
assert saved["saved"] is True
assert saved["replaced"] is False
assert len(saved["sha256"]) == 64

duplicate = payload["duplicate"]
assert duplicate["saved"] is False
assert duplicate["reason"] == "report-exists"

latest = payload["latest"]
assert latest["id"] == saved["id"]
assert latest["sha256"] == saved["sha256"]
assert latest["report_sha256"] == report["report_sha256"]
assert payload["autoloaded"] is False

serialized = json.dumps(report)
assert "post_content" not in serialized
assert "_elementor_data" not in serialized
assert "_et_pb_use_builder" not in serialized

print("ok")
PY
)"; then
  fail_smoke "migration-report-contract" "Phase 8H migration report contract is invalid" "final read-only handoff report with privacy-safe persistence" "${REPORT_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Phase 8H report OK: before/after dependencies, migrations, parity, quality, manual review and cutover evidence consolidated; final report persisted non-autoloaded without private bodies.\n'
