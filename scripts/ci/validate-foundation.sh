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
  "docs/GEO_CRAWLERS.md"
  "docs/DISCOVERY_PRIVACY.md"
  "docs/CACHE_INVALIDATION.md"
  "docs/LLMS_TXT.md"
  "docs/MARKDOWN_ALTERNATES.md"
  "docs/CONTENT_PROVENANCE.md"
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
  "packages/seo-geo-theme/inc/presets.php"
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
  "packages/seo-geo-core/src/Schema/SchemaVisibleContentResolver.php"
  "packages/seo-geo-core/src/Schema/SchemaArticleResolver.php"
  "packages/seo-geo-core/src/Schema/SchemaGraphBuilder.php"
  "packages/seo-geo-core/src/Schema/SchemaPresenter.php"
  "packages/seo-geo-core/src/Geo/ContentProvenanceResolver.php"
  "packages/seo-geo-core/src/Geo/ContentProvenancePresenter.php"
  "packages/seo-geo-core/src/Geo/CrawlerPolicyResolver.php"
  "packages/seo-geo-core/src/Geo/CrawlerPolicyPresenter.php"
  "packages/seo-geo-core/src/Geo/CrawlerPolicyAdmin.php"
  "packages/seo-geo-core/src/Geo/DiscoveryCacheRevision.php"
  "packages/seo-geo-core/src/Geo/DiscoveryCachePolicy.php"
  "packages/seo-geo-core/src/Geo/DiscoveryCacheInvalidator.php"
  "packages/seo-geo-core/src/Geo/LlmsTxtResolver.php"
  "packages/seo-geo-core/src/Geo/LlmsTxtPresenter.php"
  "packages/seo-geo-core/src/Geo/MarkdownAlternateResolver.php"
  "packages/seo-geo-core/src/Geo/MarkdownAlternatePresenter.php"
  "scripts/build-theme-package.sh"
  "scripts/ci/self-contained-theme-smoke.sh"
  "scripts/ci/discovery-privacy-acceptance.sh"
  "scripts/ci/discovery-cache-acceptance.sh"
  "scripts/ci/corporate-preset-acceptance.sh"
  "scripts/ci/validate-corporate-preset.php"
  "scripts/ci/local-business-preset-acceptance.sh"
  "scripts/ci/validate-local-business-preset.php"
  "scripts/ci/publisher-preset-acceptance.sh"
  "scripts/ci/validate-publisher-preset.php"
  "scripts/ci/ecommerce-preset-acceptance.sh"
  "scripts/ci/validate-ecommerce-preset.php"
  "scripts/ci/native-language-smoke.sh"
  "scripts/ci/native-routing-smoke.sh"
  "scripts/ci/native-translation-relations-smoke.sh"
  "scripts/ci/native-localized-seo-smoke.sh"
  ".github/workflows/self-contained-theme.yml"
  ".github/workflows/native-multilingual.yml"
  "presets/README.md"
  "presets/corporate/preset.json"
  "presets/corporate/content-map.json"
  "presets/corporate/patterns.json"
  "presets/local-business/preset.json"
  "presets/local-business/content-map.json"
  "presets/local-business/patterns.json"
  "presets/publisher/preset.json"
  "presets/publisher/content-map.json"
  "presets/publisher/patterns.json"
  "presets/ecommerce/preset.json"
  "presets/ecommerce/content-map.json"
  "presets/ecommerce/patterns.json"
)

for path in "${required_paths[@]}"; do
  [[ -e "$path" ]] || fail_missing "$path"
done

php scripts/ci/validate-corporate-preset.php
php scripts/ci/validate-local-business-preset.php
php scripts/ci/validate-publisher-preset.php
php scripts/ci/validate-ecommerce-preset.php

printf 'Foundation contract OK: %d required paths present plus all four Phase 7 preset contracts.\n' "${#required_paths[@]}"
