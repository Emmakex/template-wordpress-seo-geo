#!/usr/bin/env bash
set -euo pipefail

PIPELINE="${GITHUB_WORKFLOW:-foundation}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-foundation}"
STEP="foundation-contract"
COMMAND="bash scripts/ci/validate-foundation.sh"

signature() {
  printf '%s' "$1" | sha256sum | cut -c1-12
}

fail_missing() {
  local path="$1"
  local primary="Required foundation path is missing: ${path}"
  local sig
  sig="$(signature "missing:${path}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": "${PIPELINE}",
  "run_id": "${RUN_ID}",
  "run_attempt": "${RUN_ATTEMPT}",
  "job": "${JOB}",
  "step": "${STEP}",
  "command": "${COMMAND}",
  "exit_code": 1,
  "primary_error": "${primary}",
  "file_line": null,
  "expected": "path exists",
  "received": "missing",
  "error_signature": "${sig}",
  "root_cause_status": "unknown"
}
JSON
  exit 1
}

required_paths=(
  "README.md"
  "CONTRIBUTING.md"
  "docs/PRODUCT_VISION.md"
  "docs/ARCHITECTURE.md"
  "docs/SEO_GEO_SPEC.md"
  "docs/MULTILINGUAL.md"
  "docs/PERFORMANCE.md"
  "docs/ACCESSIBILITY.md"
  "docs/DESIGN_SYSTEM.md"
  "docs/PRESETS.md"
  "docs/COMPATIBILITY.md"
  "docs/CI_QUALITY_GATES.md"
  "docs/ROADMAP.md"
  "docs/engineering/GLOBAL_ENGINEERING_RULES.md"
  "docs/engineering/ERRORS_AND_SOLUTIONS.md"
  "packages/seo-geo-theme/README.md"
  "packages/seo-geo-core/README.md"
  "presets/README.md"
)

for path in "${required_paths[@]}"; do
  [[ -e "$path" ]] || fail_missing "$path"
done

printf 'Foundation contract OK: %d required paths present.\n' "${#required_paths[@]}"
