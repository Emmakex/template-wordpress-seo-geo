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
  "packages/seo-geo-migration-bridge/README.md"
  "packages/seo-geo-migration-bridge/seo-geo-migration-bridge.php"
  "packages/seo-geo-migration-bridge/src/Plugin.php"
  "packages/seo-geo-migration-bridge/src/SiteAnalyzer.php"
  "packages/seo-geo-migration-bridge/src/PublicUrlInventory.php"
  "packages/seo-geo-migration-bridge/src/HtmlSnapshotExtractor.php"
  "packages/seo-geo-migration-bridge/src/BaselineSnapshotter.php"
  "packages/seo-geo-migration-bridge/src/BaselineSnapshotStore.php"
  "packages/seo-geo-migration-bridge/src/Http/HttpClientInterface.php"
  "packages/seo-geo-migration-bridge/src/Http/WordPressHttpClient.php"
  "packages/seo-geo-migration-bridge/src/DependencyGraphBuilder.php"
  "packages/seo-geo-migration-bridge/src/ProviderAuthorityResolver.php"
  "packages/seo-geo-migration-bridge/src/Content/ContentDependencyDetectorInterface.php"
  "packages/seo-geo-migration-bridge/src/Content/ContentDependencyScanner.php"
  "packages/seo-geo-migration-bridge/src/Content/NativeBlocksContentDetector.php"
  "packages/seo-geo-migration-bridge/src/Content/ElementorContentDetector.php"
  "packages/seo-geo-migration-bridge/src/Content/DiviContentDetector.php"
  "packages/seo-geo-migration-bridge/src/Sandbox/SandboxGuard.php"
  "packages/seo-geo-migration-bridge/src/Sandbox/SandboxMigrationLab.php"
  "packages/seo-geo-migration-bridge/src/Migration/BuilderMigrationAdapterInterface.php"
  "packages/seo-geo-migration-bridge/src/Migration/ElementorMigrationAdapter.php"
  "packages/seo-geo-migration-bridge/src/Migration/DiviMigrationAdapter.php"
  "packages/seo-geo-migration-bridge/src/Migration/MigrationPresetResolver.php"
  "packages/seo-geo-migration-bridge/src/Migration/MigrationEngine.php"
  "packages/seo-geo-migration-bridge/src/Migration/AdminMigrationController.php"
  "packages/seo-geo-migration-bridge/src/Builders/BuilderDetectorInterface.php"
  "packages/seo-geo-migration-bridge/src/Builders/NativeBlocksDetector.php"
  "packages/seo-geo-migration-bridge/src/Builders/ElementorDetector.php"
  "packages/seo-geo-migration-bridge/src/Builders/DiviDetector.php"
  "docs/MIGRATION_BRIDGE.md"
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
  "scripts/ci/saas-digital-product-preset-acceptance.sh"
  "scripts/ci/validate-saas-digital-product-preset.php"
  "scripts/ci/validate-migration-bridge.php"
  "scripts/ci/migration-bridge-site-analyzer-acceptance.sh"
  "scripts/ci/migration-bridge-baseline-acceptance.sh"
  "scripts/ci/migration-bridge-dependency-graph-acceptance.sh"
  "scripts/ci/migration-bridge-sandbox-lab-acceptance.sh"
  "scripts/ci/migration-bridge-migration-engine-acceptance.sh"
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
  "presets/saas-digital-product/preset.json"
  "presets/saas-digital-product/content-map.json"
  "presets/saas-digital-product/patterns.json"
)

for path in "${required_paths[@]}"; do
  [[ -e "$path" ]] || fail_missing "$path"
done

php scripts/ci/validate-corporate-preset.php
php scripts/ci/validate-local-business-preset.php
php scripts/ci/validate-publisher-preset.php
php scripts/ci/validate-ecommerce-preset.php
php scripts/ci/validate-saas-digital-product-preset.php
php scripts/ci/validate-migration-bridge.php
bash scripts/ci/validate-ci-path-scope.sh

printf 'Foundation contract OK: %d required paths present plus all five Phase 7 preset contracts, the Phase 8A/8B/8C/8D/8E Migration Bridge safety contract and minimum-sufficient CI scope.\n' "${#required_paths[@]}"
