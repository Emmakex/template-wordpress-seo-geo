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
  "docs/NATIVE_SEO.md"
  "docs/NATIVE_SCHEMA.md"
  "docs/DISCOVERY_METADATA.md"
  "docs/MULTILINGUAL.md"
  "docs/NATIVE_MULTILINGUAL.md"
  "docs/PERFORMANCE.md"
  "docs/ACCESSIBILITY.md"
  "docs/DESIGN_SYSTEM.md"
  "docs/PATTERNS.md"
  "docs/PRESETS.md"
  "docs/COMPATIBILITY.md"
  "docs/CI_QUALITY_GATES.md"
  "docs/ROADMAP.md"
  "docs/engineering/GLOBAL_ENGINEERING_RULES.md"
  "docs/engineering/ERRORS_AND_SOLUTIONS.md"
  "packages/seo-geo-theme/README.md"
  "packages/seo-geo-theme/inc/seo-geo-core/bootstrap.php"
  "packages/seo-geo-core/README.md"
  "packages/seo-geo-core/src/Runtime.php"
  "packages/seo-geo-core/src/Language/NativeLanguageConfiguration.php"
  "packages/seo-geo-core/src/Language/NativeLanguageRouter.php"
  "packages/seo-geo-core/src/Language/NativeTranslationRelationship.php"
  "packages/seo-geo-core/src/Language/NativeTranslationRegistry.php"
  "packages/seo-geo-core/src/Seo/OpenGraphResolver.php"
  "packages/seo-geo-core/src/Seo/HreflangResolver.php"
  "packages/seo-geo-core/src/Seo/LocalizedSeoResolver.php"
  "packages/seo-geo-core/src/Seo/BreadcrumbResolver.php"
  "packages/seo-geo-core/src/Schema/SchemaNodeIds.php"
  "packages/seo-geo-core/src/Schema/SchemaBreadcrumbResolver.php"
  "packages/seo-geo-core/src/Schema/SchemaIdentityResolver.php"
  "packages/seo-geo-core/src/Schema/SchemaLocalBusinessResolver.php"
  "packages/seo-geo-core/src/Schema/SchemaArticleResolver.php"
  "packages/seo-geo-core/src/Schema/SchemaGraphBuilder.php"
  "packages/seo-geo-core/src/Schema/SchemaPresenter.php"
  "scripts/build-theme-package.sh"
  "scripts/ci/self-contained-theme-smoke.sh"
  "scripts/ci/native-language-smoke.sh"
  "scripts/ci/native-routing-smoke.sh"
  "scripts/ci/native-translation-relations-smoke.sh"
  "scripts/ci/native-localized-seo-smoke.sh"
  ".github/workflows/self-contained-theme.yml"
  ".github/workflows/native-multilingual.yml"
  "presets/README.md"
)

for path in "${required_paths[@]}"; do
  [[ -e "$path" ]] || fail_missing "$path"
done

printf 'Foundation contract OK: %d required paths present.\n' "${#required_paths[@]}"
