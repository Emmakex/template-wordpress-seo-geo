#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-php-quality}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-php-quality}"
COMMAND_ROOT="bash scripts/ci/php-quality.sh"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

signature() {
  printf '%s' "$1" | sha256sum | cut -c1-12
}

json_escape() {
  php -r 'echo json_encode($argv[1], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);' "$1"
}

fail_quality() {
  local step="$1"
  local command_name="$2"
  local primary="$3"
  local file_line="$4"
  local expected="$5"
  local received="$6"
  local sig
  sig="$(signature "${step}:${primary}:${file_line}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": $(json_escape "$step"),
  "command": $(json_escape "$command_name"),
  "exit_code": 1,
  "primary_error": $(json_escape "$primary"),
  "file_line": $(json_escape "$file_line"),
  "expected": $(json_escape "$expected"),
  "received": $(json_escape "$received"),
  "error_signature": $(json_escape "$sig"),
  "root_cause_status": "unknown"
}
JSON
  exit 1
}

install_dependencies() {
  local output="${TMP_DIR}/composer.txt"

  if ! composer validate --no-check-publish >"$output" 2>&1; then
    local primary
    primary="$(grep -m1 -v '^[[:space:]]*$' "$output" | head -c 300)"
    cat "$output"
    fail_quality "composer-validate" "composer validate --no-check-publish" "${primary:-Composer configuration validation failed}" "composer.json" "valid Composer configuration" "validation failed"
  fi

  if [[ -f composer.lock ]]; then
    if ! composer install --no-interaction --no-progress --prefer-dist >"$output" 2>&1; then
      local primary
      primary="$(grep -m1 -v '^[[:space:]]*$' "$output" | head -c 300)"
      cat "$output"
      fail_quality "composer-install" "composer install --no-interaction --no-progress --prefer-dist" "${primary:-Composer install failed}" "composer.lock" "locked dev dependencies install" "install failed"
    fi
  else
    if ! composer update --no-interaction --no-progress --prefer-dist >"$output" 2>&1; then
      local primary
      primary="$(grep -m1 -v '^[[:space:]]*$' "$output" | head -c 300)"
      cat "$output"
      fail_quality "composer-resolve" "composer update --no-interaction --no-progress --prefer-dist" "${primary:-Composer dependency resolution failed}" "composer.json" "exactly pinned dev dependencies resolve" "resolution failed"
    fi
  fi

  cat "$output"
  printf 'Composer dependency contract OK.\n'
}

run_phpcs() {
  local report="${TMP_DIR}/phpcs.json"
  local stderr="${TMP_DIR}/phpcs.stderr"

  if vendor/bin/phpcs --standard=phpcs.xml.dist --report=json -q >"$report" 2>"$stderr"; then
    printf 'WordPress Coding Standards OK.\n'
    return 0
  fi

  local parsed
  parsed="$(php -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($data)) { exit(2); }
    foreach (($data["files"] ?? []) as $file => $details) {
        foreach (($details["messages"] ?? []) as $message) {
            echo ($message["message"] ?? "PHPCS violation") . "\t" . $file . ":" . ($message["line"] ?? 0) . ":" . ($message["column"] ?? 0) . "\t" . ($message["source"] ?? "unknown");
            exit(0);
        }
    }
    exit(3);
  ' "$report" 2>/dev/null || true)"

  local primary file_line source
  IFS=$'\t' read -r primary file_line source <<<"$parsed"
  [[ -n "$primary" ]] || primary="$(grep -m1 -v '^[[:space:]]*$' "$stderr" | head -c 300)"
  [[ -n "$primary" ]] || primary="WordPress Coding Standards violation"
  [[ -n "$file_line" ]] || file_line="unknown"
  [[ -n "$source" ]] || source="unknown"

  cat "$stderr" >&2
  vendor/bin/phpcs --standard=phpcs.xml.dist --report=full -q || true
  fail_quality "phpcs" "vendor/bin/phpcs --standard=phpcs.xml.dist" "$primary" "$file_line" "zero WPCS errors and warnings" "$source"
}

run_phpstan() {
  local report="${TMP_DIR}/phpstan.json"
  local stderr="${TMP_DIR}/phpstan.stderr"

  if vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --error-format=json >"$report" 2>"$stderr"; then
    printf 'PHPStan level 6 OK.\n'
    return 0
  fi

  local parsed
  parsed="$(php -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($data)) { exit(2); }
    foreach (($data["files"] ?? []) as $file => $details) {
        foreach (($details["messages"] ?? []) as $message) {
            echo ($message["message"] ?? "PHPStan error") . "\t" . $file . ":" . ($message["line"] ?? 0) . "\t" . ($message["identifier"] ?? "unknown");
            exit(0);
        }
    }
    foreach (($data["errors"] ?? []) as $error) {
        echo $error . "\tunknown\tgeneral";
        exit(0);
    }
    exit(3);
  ' "$report" 2>/dev/null || true)"

  local primary file_line identifier
  IFS=$'\t' read -r primary file_line identifier <<<"$parsed"
  [[ -n "$primary" ]] || primary="$(grep -m1 -v '^[[:space:]]*$' "$stderr" | head -c 300)"
  [[ -n "$primary" ]] || primary="PHPStan analysis failed"
  [[ -n "$file_line" ]] || file_line="unknown"
  [[ -n "$identifier" ]] || identifier="unknown"

  cat "$stderr" >&2
  vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --error-format=table || true
  fail_quality "phpstan" "vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress" "$primary" "$file_line" "zero PHPStan level 6 errors" "$identifier"
}

case "${1:-all}" in
  deps)
    install_dependencies
    ;;
  phpcs)
    run_phpcs
    ;;
  phpstan)
    run_phpstan
    ;;
  all)
    install_dependencies
    run_phpcs
    run_phpstan
    ;;
  *)
    printf 'Usage: %s [deps|phpcs|phpstan|all]\n' "$COMMAND_ROOT" >&2
    exit 64
    ;;
esac
