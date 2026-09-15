#!/usr/bin/env bash
set -u -o pipefail

TARGET="${1:-}"
PIPELINE="${GITHUB_WORKFLOW:-php-lint}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-php-lint}"

signature() {
  printf '%s' "$1" | sha256sum | cut -c1-12
}

json_escape() {
  php -r 'echo json_encode($argv[1], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);' "$1"
}

if [[ -z "$TARGET" ]]; then
  printf 'Usage: %s <php-file>\n' "$0" >&2
  exit 64
fi

if [[ ! -f "$TARGET" ]]; then
  primary="PHP lint target does not exist."
  sig="$(signature "php-lint:${TARGET}:${primary}")"
  cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": "php-lint",
  "command": $(json_escape "php -l ${TARGET}"),
  "exit_code": 1,
  "primary_error": $(json_escape "$primary"),
  "file_line": $(json_escape "$TARGET"),
  "expected": "existing PHP file with valid syntax",
  "received": "missing file",
  "error_signature": $(json_escape "$sig"),
  "root_cause_status": "unknown"
}
JSON
  exit 1
fi

output="$(php -l "$TARGET" 2>&1)"
status=$?

if [[ $status -eq 0 ]]; then
  printf '%s\n' "$output"
  exit 0
fi

primary="$(printf '%s\n' "$output" | grep -m1 -E 'PHP (Parse|Fatal) error:|Parse error:|Fatal error:' | head -c 500)"
[[ -n "$primary" ]] || primary="$(printf '%s\n' "$output" | grep -m1 -v '^[[:space:]]*$' | head -c 500)"
[[ -n "$primary" ]] || primary="PHP syntax validation failed."

line="$(printf '%s\n' "$output" | sed -nE 's/.* on line ([0-9]+).*/\1/p' | head -n1)"
file_line="$TARGET"
if [[ -n "$line" ]]; then
  file_line="${TARGET}:${line}"
fi

sig="$(signature "php-lint:${primary}:${file_line}")"

printf '%s\n' "$output"
cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": "php-lint",
  "command": $(json_escape "php -l ${TARGET}"),
  "exit_code": $status,
  "primary_error": $(json_escape "$primary"),
  "file_line": $(json_escape "$file_line"),
  "expected": "valid PHP syntax",
  "received": "syntax error",
  "error_signature": $(json_escape "$sig"),
  "root_cause_status": "unknown"
}
JSON
exit "$status"
