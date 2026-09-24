#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-release-artifact}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-version-upgrade}"
COMMAND="bash scripts/ci/release-upgrade-acceptance.sh"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-$$"
NETWORK="seo-geo-upgrade-${SUFFIX}"
DB_CONTAINER="seo-geo-upgrade-db-${SUFFIX}"
WP_CONTAINER="seo-geo-upgrade-wp-${SUFFIX}"
WP_VOLUME="seo-geo-upgrade-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-upgrade-password"
DB_ROOT_PASSWORD="root-upgrade-password"

TMP_DIR="$(mktemp -d)"
CURRENT_ZIP="${TMP_DIR}/current/seo-geo-theme.zip"
PREVIOUS_ZIP="${TMP_DIR}/previous/seo-geo-theme.zip"
PREVIOUS_VERSION="0.0.9"

CURRENT_VERSION="$(python3 - <<'PY'
import json
with open("release/version.json", encoding="utf-8") as handle:
    print(json.load(handle)["version"])
PY
)"

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

fail_upgrade() {
  local step="$1"
  local message="$2"
  local expected="$3"
  local received="$4"

  python3 - "$step" "$message" "$expected" "$received" <<'PY'
import json
import sys

step, message, expected, received = sys.argv[1:]
print(
    json.dumps(
        {
            "schema_version": 1,
            "pipeline": "Release Artifact CI",
            "job": "version-upgrade",
            "step": step,
            "command": "bash scripts/ci/release-upgrade-acceptance.sh",
            "primary_error": message,
            "expected": expected,
            "received": received,
        },
        indent=2,
    )
)
PY
  exit 1
}

wait_for_db() {
  local attempt
  for attempt in $(seq 1 60); do
    if docker exec "$DB_CONTAINER" mariadb-admin ping -h 127.0.0.1 -uroot "-p${DB_ROOT_PASSWORD}" --silent >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  return 1
}

wait_for_wordpress_files() {
  local attempt
  for attempt in $(seq 1 60); do
    if docker exec "$WP_CONTAINER" test -f /var/www/html/wp-settings.php >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  return 1
}

wp_cli() {
  docker run --rm \
    --network "$NETWORK" \
    --volumes-from "$WP_CONTAINER" \
    --user 33:33 \
    -e HOME=/tmp \
    -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" \
    -e "WORDPRESS_DB_USER=${DB_USER}" \
    -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" \
    -e "WORDPRESS_DB_NAME=${DB_NAME}" \
    "$WPCLI_IMAGE" \
    wp "$@" --path=/var/www/html
}

[[ "$CURRENT_VERSION" != "$PREVIOUS_VERSION" ]] \
  || fail_upgrade "version-fixture" "Current version equals synthetic previous fixture version" "different versions" "$CURRENT_VERSION"

mkdir -p "$(dirname "$CURRENT_ZIP")" "$(dirname "$PREVIOUS_ZIP")"

printf '[upgrade] Building current release %s.\n' "$CURRENT_VERSION"
python3 scripts/build-theme-release.py --output "$CURRENT_ZIP" \
  || fail_upgrade "current-build" "Could not build current release ZIP" "release ZIP" "builder exited non-zero"

printf '[upgrade] Creating synthetic previous-version fixture %s from the same runtime baseline.\n' "$PREVIOUS_VERSION"
python3 - "$CURRENT_ZIP" "$PREVIOUS_ZIP" "$CURRENT_VERSION" "$PREVIOUS_VERSION" <<'PY'
import json
from pathlib import Path
import re
import sys
import zipfile

source = Path(sys.argv[1])
target = Path(sys.argv[2])
current = sys.argv[3]
previous = sys.argv[4]

with zipfile.ZipFile(source, "r") as src, zipfile.ZipFile(
    target,
    "w",
    compression=zipfile.ZIP_DEFLATED,
    compresslevel=9,
    strict_timestamps=True,
) as dst:
    for original in src.infolist():
        data = src.read(original.filename)

        if original.filename == "seo-geo-theme/style.css":
            text = data.decode("utf-8")
            text, count = re.subn(
                rf"(?m)^(\s*Version:\s*){re.escape(current)}\s*$",
                rf"\g<1>{previous}",
                text,
            )
            assert count == 1
            data = text.encode("utf-8")

        if original.filename == "seo-geo-theme/release-integrity.json":
            manifest = json.loads(data.decode("utf-8"))
            manifest["theme_version"] = previous
            data = (
                json.dumps(manifest, ensure_ascii=False, sort_keys=True, indent=2) + "\n"
            ).encode("utf-8")

        info = zipfile.ZipInfo(original.filename, original.date_time)
        info.create_system = original.create_system
        info.external_attr = original.external_attr
        info.compress_type = original.compress_type
        info.extra = b""
        info.comment = b""
        dst.writestr(info, data, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
PY

printf '[upgrade] Creating isolated WordPress baseline.\n'
docker network create "$NETWORK" >/dev/null \
  || fail_upgrade "network-create" "Could not create Docker network" "network created" "docker network failed"
docker volume create "$WP_VOLUME" >/dev/null \
  || fail_upgrade "volume-create" "Could not create WordPress volume" "volume created" "docker volume failed"

docker run -d \
  --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null \
  || fail_upgrade "database-start" "MariaDB did not start" "running database" "docker run failed"

wait_for_db \
  || fail_upgrade "database-ready" "MariaDB did not become ready" "mariadb-admin ping" "timeout"

docker run -d \
  --name "$WP_CONTAINER" \
  --network "$NETWORK" \
  -v "${WP_VOLUME}:/var/www/html" \
  -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" \
  -e "WORDPRESS_DB_USER=${DB_USER}" \
  -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" \
  -e "WORDPRESS_DB_NAME=${DB_NAME}" \
  -e WORDPRESS_DEBUG=1 \
  -e "WORDPRESS_CONFIG_EXTRA=define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false ); @ini_set( 'display_errors', '0' );" \
  "$WORDPRESS_IMAGE" >/dev/null \
  || fail_upgrade "wordpress-start" "WordPress did not start" "running WordPress" "docker run failed"

wait_for_wordpress_files \
  || fail_upgrade "wordpress-files" "WordPress files were not initialized" "wp-settings.php" "timeout"

wp_cli core install \
  --url=http://example.test \
  --title="SEO GEO Upgrade" \
  --admin_user=admin \
  --admin_password=wordpress-upgrade-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_upgrade "core-install" "Could not install WordPress" "wp core install succeeds" "failed"

docker cp "$PREVIOUS_ZIP" "$WP_CONTAINER":/var/www/html/wp-content/seo-geo-theme-previous.zip \
  || fail_upgrade "previous-copy" "Could not copy previous ZIP into fixture" "ZIP copied" "docker cp failed"
docker cp "$CURRENT_ZIP" "$WP_CONTAINER":/var/www/html/wp-content/seo-geo-theme-current.zip \
  || fail_upgrade "current-copy" "Could not copy current ZIP into fixture" "ZIP copied" "docker cp failed"

ACTIVE_INITIAL="$(wp_cli plugin list --status=active --field=name 2>/dev/null | tr -d '\r')"
[[ -z "$ACTIVE_INITIAL" ]] \
  || fail_upgrade "plugins-initial" "Upgrade baseline must start with zero active plugins" "empty active-plugin list" "$ACTIVE_INITIAL"

wp_cli theme install /var/www/html/wp-content/seo-geo-theme-previous.zip --force >/dev/null \
  || fail_upgrade "previous-install" "Could not install previous theme fixture" "$PREVIOUS_VERSION installed" "wp theme install failed"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_upgrade "previous-activate" "Could not activate previous theme fixture" "theme active" "wp theme activate failed"

PREVIOUS_INSTALLED="$(wp_cli theme get seo-geo-theme --field=version 2>/dev/null | tr -d '\r\n')"
[[ "$PREVIOUS_INSTALLED" == "$PREVIOUS_VERSION" ]] \
  || fail_upgrade "previous-version" "Previous fixture version mismatch" "$PREVIOUS_VERSION" "$PREVIOUS_INSTALLED"

SETUP_RUNNER="$TMP_DIR/setup-runner.php"
STATE_RUNNER="$TMP_DIR/state-runner.php"

cat >"$SETUP_RUNNER" <<'PHP'
<?php
wp_set_current_user( 1 );

$candidate = array(
	'preset'           => 'corporate',
	'default_language' => 'en',
	'languages'        => array(
		'en' => 'en_US',
		'es' => 'es_ES',
	),
	'routing'          => 'prefix',
	'x_default'        => 'en',
	'site_entity_type' => 'organization',
	'confirm_identity' => true,
	'local_business'   => array(),
	'crawler_policy'   => array(
		'oai_searchbot' => 'allow',
		'gptbot'        => 'disallow',
	),
	'llms_txt_enabled'            => true,
	'markdown_alternates_enabled' => true,
);

echo wp_json_encode(
	seo_geo_theme_apply_setup( $candidate, true ),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

cat >"$STATE_RUNNER" <<'PHP'
<?php
$manifest_path = get_template_directory() . '/release-integrity.json';
$manifest      = is_file( $manifest_path )
	? json_decode( (string) file_get_contents( $manifest_path ), true )
	: null;
$reflection    = new ReflectionClass( \SeoGeo\Core\Runtime::class );

echo wp_json_encode(
	array(
		'theme_version'    => wp_get_theme()->get( 'Version' ),
		'manifest_version' => is_array( $manifest ) ? ( $manifest['theme_version'] ?? null ) : null,
		'runtime_file'     => (string) $reflection->getFileName(),
		'plugins'          => get_option( 'active_plugins', array() ),
		'setup'            => get_option( 'seo_geo_theme_setup_v1', null ),
		'report'           => get_option( 'seo_geo_theme_setup_report_v1', null ),
		'page_count'       => (int) wp_count_posts( 'page' )->publish,
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$SETUP_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/upgrade-setup-runner.php \
  || fail_upgrade "setup-runner-copy" "Could not copy setup runner" "runner copied" "docker cp failed"
docker cp "$STATE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/upgrade-state-runner.php \
  || fail_upgrade "state-runner-copy" "Could not copy state runner" "runner copied" "docker cp failed"

FIRST_APPLY="$(wp_cli eval-file /var/www/html/wp-content/upgrade-setup-runner.php 2>/dev/null)" \
  || fail_upgrade "previous-setup" "Could not apply setup on previous fixture" "setup result JSON" "wp eval-file failed"

PAGE_ID="$(wp_cli post create --post_type=page --post_status=publish --post_title='Upgrade sentinel' --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$PAGE_ID" =~ ^[0-9]+$ ]] \
  || fail_upgrade "sentinel-create" "Could not create upgrade sentinel page" "numeric page ID" "$PAGE_ID"

STATE_BEFORE="$(wp_cli eval-file /var/www/html/wp-content/upgrade-state-runner.php 2>/dev/null)" \
  || fail_upgrade "state-before" "Could not capture pre-upgrade state" "state JSON" "wp eval-file failed"

printf '[upgrade] Upgrading active theme %s -> %s.\n' "$PREVIOUS_VERSION" "$CURRENT_VERSION"
wp_cli theme install /var/www/html/wp-content/seo-geo-theme-current.zip --force >/dev/null \
  || fail_upgrade "current-install" "Could not overwrite active theme with current release" "$CURRENT_VERSION installed" "wp theme install failed"

CURRENT_INSTALLED="$(wp_cli theme get seo-geo-theme --field=version 2>/dev/null | tr -d '\r\n')"
[[ "$CURRENT_INSTALLED" == "$CURRENT_VERSION" ]] \
  || fail_upgrade "current-version" "Current installed version mismatch" "$CURRENT_VERSION" "$CURRENT_INSTALLED"

STATE_CURRENT="$(wp_cli eval-file /var/www/html/wp-content/upgrade-state-runner.php 2>/dev/null)" \
  || fail_upgrade "state-current" "Could not capture post-upgrade state" "state JSON" "wp eval-file failed"
CURRENT_REAPPLY="$(wp_cli eval-file /var/www/html/wp-content/upgrade-setup-runner.php 2>/dev/null)" \
  || fail_upgrade "current-reapply" "Could not re-apply setup after upgrade" "idempotent setup result" "wp eval-file failed"

SENTINEL_CURRENT="$(wp_cli post get "$PAGE_ID" --field=post_status 2>/dev/null | tr -d '\r\n')"
[[ "$SENTINEL_CURRENT" == "publish" ]] \
  || fail_upgrade "sentinel-current" "Upgrade did not preserve published content" "publish" "$SENTINEL_CURRENT"

printf '[upgrade] Rolling back active theme %s -> %s.\n' "$CURRENT_VERSION" "$PREVIOUS_VERSION"
wp_cli theme install /var/www/html/wp-content/seo-geo-theme-previous.zip --force >/dev/null \
  || fail_upgrade "rollback-install" "Could not roll back to previous theme fixture" "$PREVIOUS_VERSION installed" "wp theme install failed"

ROLLBACK_INSTALLED="$(wp_cli theme get seo-geo-theme --field=version 2>/dev/null | tr -d '\r\n')"
[[ "$ROLLBACK_INSTALLED" == "$PREVIOUS_VERSION" ]] \
  || fail_upgrade "rollback-version" "Rollback installed version mismatch" "$PREVIOUS_VERSION" "$ROLLBACK_INSTALLED"

STATE_ROLLBACK="$(wp_cli eval-file /var/www/html/wp-content/upgrade-state-runner.php 2>/dev/null)" \
  || fail_upgrade "state-rollback" "Could not capture rollback state" "state JSON" "wp eval-file failed"
ROLLBACK_REAPPLY="$(wp_cli eval-file /var/www/html/wp-content/upgrade-setup-runner.php 2>/dev/null)" \
  || fail_upgrade "rollback-reapply" "Could not re-apply setup after rollback" "idempotent setup result" "wp eval-file failed"

SENTINEL_ROLLBACK="$(wp_cli post get "$PAGE_ID" --field=post_status 2>/dev/null | tr -d '\r\n')"
[[ "$SENTINEL_ROLLBACK" == "publish" ]] \
  || fail_upgrade "sentinel-rollback" "Rollback did not preserve published content" "publish" "$SENTINEL_ROLLBACK"

printf '%s' "$FIRST_APPLY" >"$TMP_DIR/first-apply.json"
printf '%s' "$CURRENT_REAPPLY" >"$TMP_DIR/current-reapply.json"
printf '%s' "$ROLLBACK_REAPPLY" >"$TMP_DIR/rollback-reapply.json"
printf '%s' "$STATE_BEFORE" >"$TMP_DIR/state-before.json"
printf '%s' "$STATE_CURRENT" >"$TMP_DIR/state-current.json"
printf '%s' "$STATE_ROLLBACK" >"$TMP_DIR/state-rollback.json"

if ! python3 - "$TMP_DIR" "$PREVIOUS_VERSION" "$CURRENT_VERSION" <<'PY'
import json
from pathlib import Path
import sys

root = Path(sys.argv[1])
previous_version = sys.argv[2]
current_version = sys.argv[3]

def load(name):
    return json.loads((root / name).read_text(encoding="utf-8"))

first = load("first-apply.json")
current_apply = load("current-reapply.json")
rollback_apply = load("rollback-reapply.json")
before = load("state-before.json")
current = load("state-current.json")
rollback = load("state-rollback.json")

assert first["valid"] is True
assert first["applied"] is True
assert first["idempotent"] is False
assert current_apply["valid"] is True
assert current_apply["applied"] is True
assert current_apply["idempotent"] is True
assert current_apply["changed_options"] == []
assert rollback_apply["valid"] is True
assert rollback_apply["applied"] is True
assert rollback_apply["idempotent"] is True
assert rollback_apply["changed_options"] == []

assert before["theme_version"] == previous_version
assert before["manifest_version"] == previous_version
assert current["theme_version"] == current_version
assert current["manifest_version"] == current_version
assert rollback["theme_version"] == previous_version
assert rollback["manifest_version"] == previous_version

for state in (before, current, rollback):
    assert state["plugins"] == []
    assert state["runtime_file"].endswith(
        "/wp-content/themes/seo-geo-theme/inc/seo-geo-core/src/Runtime.php"
    )
    assert state["setup"]["mode"] == "theme-setup-applied"
    assert state["report"]["mode"] == "theme-setup-report"

assert before["setup"] == current["setup"] == rollback["setup"]
assert before["report"] == current["report"] == rollback["report"]
assert before["page_count"] == current["page_count"] == rollback["page_count"]

print("ok")
PY
then
  fail_upgrade "upgrade-contract" "Version upgrade/rollback contract failed" "state preserved with zero plugins and idempotent setup" "python assertion failed"
fi

printf '[upgrade] Phase 10B OK: %s -> %s -> %s preserved setup/report/content with zero active plugins and idempotent re-Apply.\n' \
  "$PREVIOUS_VERSION" "$CURRENT_VERSION" "$PREVIOUS_VERSION"
