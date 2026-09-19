#!/usr/bin/env bash
set -euo pipefail

# Guard the repository's minimum-sufficient-CI contract.
# Global status/governance documents belong to Foundation only. Specialized
# workflows may still opt into their own technical contract documentation.

fail_scope() {
  local workflow="$1"
  local path="$2"
  local message="$3"
  local signature

  signature="$(printf '%s' "${workflow}:${path}:${message}" | sha256sum | cut -c1-12)"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": "${GITHUB_WORKFLOW:-foundation}",
  "run_id": "${GITHUB_RUN_ID:-local}",
  "run_attempt": "${GITHUB_RUN_ATTEMPT:-1}",
  "job": "${GITHUB_JOB:-foundation}",
  "step": "ci-path-scope",
  "command": "bash scripts/ci/validate-ci-path-scope.sh",
  "exit_code": 1,
  "primary_error": "${message}",
  "file_line": "${workflow}",
  "expected": "global governance/status docs trigger Foundation only",
  "received": "${path}",
  "error_signature": "${signature}",
  "root_cause_status": "confirmed"
}
JSON

  exit 1
}

protected_global_docs=(
  "docs/ROADMAP.md"
  "docs/CI_QUALITY_GATES.md"
  "docs/engineering/GLOBAL_ENGINEERING_RULES.md"
  "docs/engineering/ERRORS_AND_SOLUTIONS.md"
)

for workflow in .github/workflows/*.yml; do
  [[ "${workflow}" == ".github/workflows/foundation.yml" ]] && continue

  for protected_path in "${protected_global_docs[@]}"; do
    if grep -Fq -- "- '${protected_path}'" "${workflow}"; then
      fail_scope         "${workflow}"         "${protected_path}"         "A global governance/status document is triggering a specialized CI workflow."
    fi
  done
done

if grep -Eq '^[[:space:]]+paths:' .github/workflows/foundation.yml; then
  fail_scope     ".github/workflows/foundation.yml"     "paths:"     "Foundation must remain the unconditional repository-level contract gate."
fi

printf 'CI path scope OK: global governance/status docs trigger Foundation only.\n'
