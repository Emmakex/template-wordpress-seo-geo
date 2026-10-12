#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-seo-geo-manager-application-password-acceptance}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-manager-application-password-acceptance}"
COMMAND="bash scripts/ci/seo-geo-manager-application-password-acceptance.sh"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-app-pass-$$"
NETWORK="seo-geo-manager-${SUFFIX}"
DB_CONTAINER="seo-geo-manager-db-${SUFFIX}"
WP_CONTAINER="seo-geo-manager-wp-${SUFFIX}"
WP_VOLUME="seo-geo-manager-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="manager-app-pass-db"
DB_ROOT_PASSWORD="manager-app-pass-root"

REMOTE_LOGIN="manager-remote"
REMOTE_EMAIL="manager-remote@example.test"
APP_NAME="seo-geo-manager-ci"

TMP_DIR="$(mktemp -d)"
AUTH_BODY="${TMP_DIR}/authenticated-capabilities.json"
REVOKED_BODY="${TMP_DIR}/revoked-capabilities.json"

signature() {
  printf '%s' "$1" | sha256sum | cut -c1-12
}

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

fail_acceptance() {
  local code="$1"
  local primary="$2"
  local expected="$3"
  local received="$4"
  local sig
  sig="$(signature "${code}:${primary}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": "${PIPELINE}",
  "run_id": "${RUN_ID}",
  "run_attempt": "${RUN_ATTEMPT}",
  "job": "${JOB}",
  "step": "manager-application-password-runtime",
  "command": "${COMMAND}",
  "exit_code": 1,
  "primary_error": "${primary}",
  "expected": "${expected}",
  "received": "${received}",
  "error_signature": "${sig}"
}
JSON
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
    wp \
    "$@" \
    --path=/var/www/html
}

printf '[manager-app-pass] Creating isolated fixture.\n'
docker network create "$NETWORK" >/dev/null \
  || fail_acceptance "network-create" "Could not create Docker network" "network created" "failed"
docker volume create "$WP_VOLUME" >/dev/null \
  || fail_acceptance "volume-create" "Could not create WordPress volume" "volume created" "failed"

docker run -d \
  --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null \
  || fail_acceptance "database-start" "MariaDB did not start" "running database" "docker run failed"

wait_for_db \
  || fail_acceptance "database-ready" "MariaDB did not become ready" "ready database" "timeout"

docker run -d \
  --name "$WP_CONTAINER" \
  --network "$NETWORK" \
  -p 127.0.0.1::80 \
  -v "${WP_VOLUME}:/var/www/html" \
  -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" \
  -e "WORDPRESS_DB_USER=${DB_USER}" \
  -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" \
  -e "WORDPRESS_DB_NAME=${DB_NAME}" \
  "$WORDPRESS_IMAGE" >/dev/null \
  || fail_acceptance "wordpress-start" "WordPress did not start" "running WordPress" "docker run failed"

wait_for_wordpress_files \
  || fail_acceptance "wordpress-files" "WordPress files did not initialize" "wp-settings.php exists" "timeout"

HOST_PORT="$(docker port "$WP_CONTAINER" 80/tcp | awk -F: 'NR == 1 {print $NF}')"
[[ -n "$HOST_PORT" ]] \
  || fail_acceptance "wordpress-port" "Could not resolve WordPress host port" "non-empty port" "empty"
BASE_URL="http://127.0.0.1:${HOST_PORT}"
CAPABILITIES_URL="${BASE_URL}/index.php?rest_route=/seo-geo-manager/v1/capabilities"

printf '[manager-app-pass] Installing WordPress and Manager.\n'
docker exec "$WP_CONTAINER" mkdir -p \
  /var/www/html/wp-content/plugins/seo-geo-manager \
  /var/www/html/wp-content/mu-plugins \
  || fail_acceptance "package-dirs" "Could not create package directories" "directories created" "mkdir failed"

docker cp packages/seo-geo-manager/. "$WP_CONTAINER":/var/www/html/wp-content/plugins/seo-geo-manager/ \
  || fail_acceptance "manager-copy" "Could not copy Manager" "plugin copied" "docker cp failed"
docker cp scripts/ci/seo-geo-manager-application-passwords-mu-plugin.php "$WP_CONTAINER":/var/www/html/wp-content/mu-plugins/seo-geo-manager-ci-application-passwords.php \
  || fail_acceptance "mu-plugin-copy" "Could not copy CI Application Password transport fixture" "mu-plugin copied" "docker cp failed"
docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/seo-geo-manager \
  /var/www/html/wp-content/mu-plugins/seo-geo-manager-ci-application-passwords.php \
  || fail_acceptance "package-permissions" "Could not set package permissions" "www-data owns fixture" "chown failed"

wp_cli core install \
  --url="$BASE_URL" \
  --title="SEO GEO Manager Application Password Acceptance" \
  --admin_user=admin \
  --admin_password=manager-app-pass-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_acceptance "core-install" "Could not install WordPress" "core install succeeds" "failed"

wp_cli plugin activate seo-geo-manager >/dev/null \
  || fail_acceptance "manager-activate" "Could not activate Manager" "plugin active" "failed"

REMOTE_USER_ID="$(wp_cli user create "$REMOTE_LOGIN" "$REMOTE_EMAIL" --role=editor --user_pass=manager-remote-local-password --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$REMOTE_USER_ID" =~ ^[0-9]+$ ]] \
  || fail_acceptance "remote-user" "Could not create remote editor fixture" "numeric user ID" "$REMOTE_USER_ID"

APP_PASSWORD="$(wp_cli user application-password create "$REMOTE_USER_ID" "$APP_NAME" --porcelain 2>/dev/null | tr -d '\r\n')"
[[ -n "$APP_PASSWORD" ]] \
  || fail_acceptance "app-password-create" "Could not create Application Password" "non-empty credential" "empty"

APP_UUID="$(wp_cli user application-password list "$REMOTE_USER_ID" --field=uuid 2>/dev/null | head -n 1 | tr -d '\r\n')"
[[ -n "$APP_UUID" ]] \
  || fail_acceptance "app-password-uuid" "Could not resolve Application Password UUID" "non-empty UUID" "empty"

printf '[manager-app-pass] Authenticating capability discovery with Application Password.\n'
AUTH_STATUS="$(curl --silent --show-error --user "${REMOTE_LOGIN}:${APP_PASSWORD}" --output "$AUTH_BODY" --write-out '%{http_code}' "$CAPABILITIES_URL")"
[[ "200" == "$AUTH_STATUS" ]] \
  || fail_acceptance "app-password-auth" "Application Password could not authenticate capability discovery" "HTTP 200" "HTTP ${AUTH_STATUS}"

python3 - "$AUTH_BODY" "$REMOTE_USER_ID" <<'PY'
import json
import sys
from pathlib import Path

payload = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
user_id = int(sys.argv[2])
assert payload["principal"]["authenticated"] is True
assert int(payload["principal"]["user_id"]) == user_id
assert payload["capabilities"]["content_change_set"]["available"] is True
assert payload["capabilities"]["site_wide_operations"]["available"] is False
assert payload["capabilities"]["permalink_administration"]["available"] is False
assert payload["authentication"]["secrets_returned"] is False
assert payload["authentication"]["generic_remote_shell"] is False
PY
if [[ 0 -ne $? ]]; then
  fail_acceptance "app-password-manifest" "Authenticated manifest violates least-privilege contract" "editor content capability without site-wide capability" "manifest assertion failed"
fi

printf '[manager-app-pass] Revoking credential and proving access stops.\n'
wp_cli user application-password delete "$REMOTE_USER_ID" "$APP_UUID" >/dev/null \
  || fail_acceptance "app-password-delete" "Could not revoke Application Password" "credential revoked" "delete failed"

REVOKED_STATUS="$(curl --silent --show-error --user "${REMOTE_LOGIN}:${APP_PASSWORD}" --output "$REVOKED_BODY" --write-out '%{http_code}' "$CAPABILITIES_URL")"
[[ "401" == "$REVOKED_STATUS" ]] \
  || fail_acceptance "app-password-revocation" "Revoked Application Password retained Manager access" "HTTP 401" "HTTP ${REVOKED_STATUS}"

printf '%s\n' '{"ok":true,"application_password_auth":true,"least_privilege":true,"revocation":true,"secrets_logged":false}'
