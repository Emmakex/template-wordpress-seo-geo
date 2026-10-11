#!/usr/bin/env bash
set -euo pipefail

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-1}-$$"
NETWORK="seo-geo-manager-remote-${SUFFIX}"
DB_CONTAINER="seo-geo-manager-remote-db-${SUFFIX}"
WP_CONTAINER="seo-geo-manager-remote-wp-${SUFFIX}"
WP_VOLUME="seo-geo-manager-remote-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="remote-acceptance-password"
DB_ROOT_PASSWORD="remote-acceptance-root"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
READ_FIXTURE="$ROOT/scripts/ci/seo-geo-manager-remote-read-wordpress-acceptance.php"
MUTATION_FIXTURE="$ROOT/scripts/ci/seo-geo-manager-remote-mutation-wordpress-acceptance.php"
READ_LOG="$(mktemp)"
MUTATION_LOG="$(mktemp)"

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -f "$READ_LOG" "$MUTATION_LOG"
}
trap cleanup EXIT

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
    "$WPCLI_IMAGE" wp --path=/var/www/html "$@"
}

wait_for_db() {
  for _ in $(seq 1 60); do
    if docker exec "$DB_CONTAINER" mariadb-admin ping -h 127.0.0.1 -uroot "-p${DB_ROOT_PASSWORD}" --silent >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  return 1
}

wait_for_wordpress() {
  for _ in $(seq 1 60); do
    if docker exec "$WP_CONTAINER" test -f /var/www/html/wp-settings.php >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  return 1
}

printf '[remote] Creating pinned fixture network and volume.\n'
docker network create "$NETWORK" >/dev/null
docker volume create "$WP_VOLUME" >/dev/null

printf '[remote] Starting MariaDB fixture.\n'
docker run -d --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null

wait_for_db || { echo '[remote] MariaDB fixture did not become ready.' >&2; exit 1; }

printf '[remote] Starting WordPress fixture with current Manager package mounted.\n'
docker run -d --name "$WP_CONTAINER" \
  --network "$NETWORK" \
  -v "$WP_VOLUME:/var/www/html" \
  -v "$ROOT/packages/seo-geo-manager:/var/www/html/wp-content/plugins/seo-geo-manager:ro" \
  -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" \
  -e "WORDPRESS_DB_USER=${DB_USER}" \
  -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" \
  -e "WORDPRESS_DB_NAME=${DB_NAME}" \
  -e WORDPRESS_DEBUG=1 \
  "$WORDPRESS_IMAGE" >/dev/null

wait_for_wordpress || { echo '[remote] WordPress files did not become ready.' >&2; exit 1; }

printf '[remote] Installing WordPress fixture.\n'
wp_cli core install \
  --url='https://manager.test' \
  --title='SEO GEO Manager Remote Acceptance' \
  --admin_user='manageradmin' \
  --admin_password='manager-acceptance-local-only' \
  --admin_email='manager@example.test' \
  --skip-email >/dev/null

wp_cli plugin activate seo-geo-manager >/dev/null

docker cp "$READ_FIXTURE" "$WP_CONTAINER:/var/www/html/seo-geo-manager-remote-read-acceptance.php"
docker cp "$MUTATION_FIXTURE" "$WP_CONTAINER:/var/www/html/seo-geo-manager-remote-mutation-acceptance.php"
docker exec "$WP_CONTAINER" chown www-data:www-data \
  /var/www/html/seo-geo-manager-remote-read-acceptance.php \
  /var/www/html/seo-geo-manager-remote-mutation-acceptance.php

printf '[remote] Running Manager-owned R2 read/scope/replay/revocation acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-remote-read-acceptance.php >"$READ_LOG" 2>&1; then
  cat "$READ_LOG"
  exit 1
fi
cat "$READ_LOG"

grep -q '"ok":true' "$READ_LOG" || { echo '[remote] R2/R3 read acceptance did not emit success marker.' >&2; exit 1; }
grep -q '"authenticated_read":true' "$READ_LOG" || { echo '[remote] Authenticated read proof missing.' >&2; exit 1; }
grep -q '"r3_capability_advertised":true' "$READ_LOG" || { echo '[remote] R3 capability discovery proof missing.' >&2; exit 1; }
grep -q '"scope_denial":true' "$READ_LOG" || { echo '[remote] Scope denial proof missing.' >&2; exit 1; }
grep -q '"nonce_replay_rejected":true' "$READ_LOG" || { echo '[remote] Replay rejection proof missing.' >&2; exit 1; }
grep -q '"credential_revocation":true' "$READ_LOG" || { echo '[remote] Revocation proof missing.' >&2; exit 1; }
grep -q '"remote_mutation_performed":false' "$READ_LOG" || { echo '[remote] Read acceptance unexpectedly performed a mutation.' >&2; exit 1; }

printf '[remote] Running R3 content-change Plan -> Apply -> Verify -> Rollback acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-remote-mutation-acceptance.php >"$MUTATION_LOG" 2>&1; then
  cat "$MUTATION_LOG"
  exit 1
fi
cat "$MUTATION_LOG"

grep -q '"ok":true' "$MUTATION_LOG" || { echo '[remote] R3 mutation acceptance did not emit success marker.' >&2; exit 1; }
grep -q '"operation":"content-change"' "$MUTATION_LOG" || { echo '[remote] R3 content-change operation proof missing.' >&2; exit 1; }
grep -q '"plan_created":true' "$MUTATION_LOG" || { echo '[remote] R3 plan proof missing.' >&2; exit 1; }
grep -q '"preview_no_mutation":true' "$MUTATION_LOG" || { echo '[remote] R3 preview purity proof missing.' >&2; exit 1; }
grep -q '"apply_exact":true' "$MUTATION_LOG" || { echo '[remote] R3 apply proof missing.' >&2; exit 1; }
grep -q '"apply_idempotent_replay":true' "$MUTATION_LOG" || { echo '[remote] R3 request idempotency proof missing.' >&2; exit 1; }
grep -q '"verify_exact":true' "$MUTATION_LOG" || { echo '[remote] R3 verify proof missing.' >&2; exit 1; }
grep -q '"operation_bounded":true' "$MUTATION_LOG" || { echo '[remote] R3 bounded operation proof missing.' >&2; exit 1; }
grep -q '"rollback_exact":true' "$MUTATION_LOG" || { echo '[remote] R3 rollback proof missing.' >&2; exit 1; }
grep -q '"audit_secret_free":true' "$MUTATION_LOG" || { echo '[remote] R3 secret-free audit proof missing.' >&2; exit 1; }
grep -q '"generic_proxy":false' "$MUTATION_LOG" || { echo '[remote] R3 generic proxy guard proof missing.' >&2; exit 1; }

DEBUG_LOG="$(wp_cli eval 'echo WP_CONTENT_DIR . "/debug.log";' 2>/dev/null | tr -d '\r\n')"
if [[ -n "$DEBUG_LOG" ]]; then
  docker exec "$WP_CONTAINER" sh -lc "test ! -s '$DEBUG_LOG'" || {
    docker exec "$WP_CONTAINER" sh -lc "tail -n 80 '$DEBUG_LOG'" || true
    echo '[remote] WordPress debug.log contains runtime output.' >&2
    exit 1
  }
fi

printf '[remote] SEO/GEO Manager R1-R3 WordPress acceptance OK.\n'
