#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-phase-1-package}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-package-contract}"
STEP="phase-1-package-contract"
COMMAND="bash scripts/ci/validate-phase1.sh"

signature() {
  printf '%s' "$1" | sha256sum | cut -c1-12
}

fail_contract() {
  local code="$1"
  local primary="$2"
  local expected="$3"
  local received="$4"
  local path="${5:-}"
  local sig
  sig="$(signature "${code}:${path}")"

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
  "expected": "${expected}",
  "received": "${received}",
  "error_signature": "${sig}",
  "root_cause_status": "unknown"
}
JSON
  exit 1
}

required_paths=(
  "packages/seo-geo-theme/style.css"
  "packages/seo-geo-theme/theme.json"
  "packages/seo-geo-theme/functions.php"
  "packages/seo-geo-theme/parts/header.html"
  "packages/seo-geo-theme/parts/footer.html"
  "packages/seo-geo-theme/templates/index.html"
  "packages/seo-geo-theme/templates/page.html"
  "packages/seo-geo-theme/templates/single.html"
  "packages/seo-geo-theme/templates/archive.html"
  "packages/seo-geo-theme/templates/404.html"
  "packages/seo-geo-core/seo-geo-core.php"
  "packages/seo-geo-core/src/Plugin.php"
  "packages/seo-geo-core/src/Language/LanguageProviderInterface.php"
  "packages/seo-geo-core/src/Language/LanguageManager.php"
  "packages/seo-geo-core/src/Language/NativeWordPressAdapter.php"
  "packages/seo-geo-core/src/Integrations/IntegrationDetectorInterface.php"
  "packages/seo-geo-core/src/Integrations/RuntimeIntegrationDetector.php"
)

for path in "${required_paths[@]}"; do
  [[ -f "$path" ]] || fail_contract "missing-path" "Required Phase 1 file is missing: ${path}" "file exists" "missing" "$path"
done

while IFS= read -r -d '' file; do
  if ! lint_output="$(php -l "$file" 2>&1)"; then
    fail_contract "php-syntax" "PHP syntax validation failed: ${file}" "valid PHP syntax" "invalid PHP syntax" "$file"
  fi
done < <(find packages/seo-geo-theme packages/seo-geo-core -type f -name '*.php' -print0)

if ! php -r '$data = json_decode(file_get_contents("packages/seo-geo-theme/theme.json"), true, 512, JSON_THROW_ON_ERROR); exit((isset($data["version"]) && 3 === $data["version"]) ? 0 : 2);'; then
  fail_contract "theme-json" "theme.json is invalid or is not version 3" "valid JSON with version=3" "invalid contract" "packages/seo-geo-theme/theme.json"
fi

if ! grep -q '^Theme Name: SEO GEO Starter$' packages/seo-geo-theme/style.css; then
  fail_contract "theme-header" "Theme header is missing the expected Theme Name" "SEO GEO Starter theme header" "missing or changed" "packages/seo-geo-theme/style.css"
fi

if ! grep -q '^Text Domain: seo-geo-theme$' packages/seo-geo-theme/style.css; then
  fail_contract "theme-text-domain" "Theme text domain does not match the project contract" "seo-geo-theme" "missing or changed" "packages/seo-geo-theme/style.css"
fi

if ! grep -q '^ \* Plugin Name: SEO GEO Core$' packages/seo-geo-core/seo-geo-core.php; then
  fail_contract "plugin-header" "Plugin header is missing the expected Plugin Name" "SEO GEO Core plugin header" "missing or changed" "packages/seo-geo-core/seo-geo-core.php"
fi

if ! grep -q '^ \* Text Domain: seo-geo-core$' packages/seo-geo-core/seo-geo-core.php; then
  fail_contract "plugin-text-domain" "Plugin text domain does not match the project contract" "seo-geo-core" "missing or changed" "packages/seo-geo-core/seo-geo-core.php"
fi

for template in packages/seo-geo-theme/templates/*.html; do
  if ! grep -q '"tagName":"main"' "$template"; then
    fail_contract "semantic-main" "Block template has no semantic main landmark: ${template}" "tagName=main" "missing" "$template"
  fi
done

printf 'Phase 1 package contract OK: %d required files; PHP syntax, theme.json and package headers validated.\n' "${#required_paths[@]}"
