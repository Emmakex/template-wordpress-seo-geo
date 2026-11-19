#!/usr/bin/env bash
# Production preflight must accept a legitimate non-sandbox destination for
# READ-ONLY validation, without ever authorizing database or file replacement.
printf '[smoke] Checking fail-closed production ZIP destination policy.\n'

PRODUCTION_POLICY_RUNNER="$TMP_DIR/migration-production-policy-runner.php"
cat > "$PRODUCTION_POLICY_RUNNER" <<'PHP'
<?php
use SeoGeo\MigrationBridge\Clone\ImportPreflight;
use SeoGeo\MigrationBridge\Clone\ImportDestinationPlan;

$target = home_url( '/' );
$site = site_url( '/' );
$package = array(
    'source' => array('home_url' => 'https://emmake.com/nuevaweb/', 'site_url' => 'https://emmake.com/nuevaweb/'),
    'integrity' => array('payload_bytes' => 1024),
);
$database = array('source' => array('table_prefix' => 'wp_'));
$before = array(
    'home' => get_option('home'), 'siteurl' => get_option('siteurl'),
    'blog_public' => get_option('blog_public'), 'active_plugins' => get_option('active_plugins'),
);
$method = new ReflectionMethod(ImportPreflight::class, 'destination_report');
$preflight = new ImportPreflight();
$production = $method->invoke($preflight, $package, $database, 2048, null, 'replace-production');
$legacy = $method->invoke($preflight, $package, $database, 2048, null, 'sandbox');

$after = array(
    'home' => get_option('home'), 'siteurl' => get_option('siteurl'),
    'blog_public' => get_option('blog_public'), 'active_plugins' => get_option('active_plugins'),
);
echo wp_json_encode(array(
    'unchanged' => $before === $after,
    'production' => $production,
    'legacy' => $legacy,
    'plan' => ImportDestinationPlan::inspect(
        'https://emmake.com/nuevaweb/', 'https://emmake.com/nuevaweb/', $target, $site
    ),
), JSON_UNESCAPED_SLASHES);
PHP

docker cp "$PRODUCTION_POLICY_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-production-policy-runner.php \
  || fail_smoke "production_policy-copy" "Could not copy production import test runner" "runner accessible inside WordPress" "docker cp failed"

if ! PRODUCTION_POLICY_JSON="$(wp_cli eval-file /var/www/html/wp-content/migration-production-policy-runner.php)"; then
  fail_smoke "production-policy-runner" "Could not execute production preflight policy" "read-only policy response" "wp-cli error"
fi
if ! PRODUCTION_POLICY_JSON="$PRODUCTION_POLICY_JSON" python3 - <<'PY'
import json, os
j=json.loads(os.environ["PRODUCTION_POLICY_JSON"])
p=j["production"]
s=j["legacy"]
assert j["unchanged"] is True
assert j["plan"]["status"] == "requires-protected-production-import"
assert p["mode"] == "replace-production"
assert p["scenario"] == "different-domain"
assert p["plan_status"] == "requires-protected-production-import"
assert p["backups_ready"] is False
assert p["target_authorized"] is False
assert "import-sandbox-marker-missing" not in p["blockers"]
assert "import-search-visibility-not-disabled" not in p["blockers"]
assert "import-target-authorization-missing" not in p["blockers"]
assert "import-production-final-confirmation-required" in p["advisories"]
assert s["mode"] != "replace-production"
assert "import-sandbox-marker-missing" in s["blockers"]
assert "import-target-authorization-missing" in s["blockers"]
print("ok")
PY
then
  fail_smoke "production-policy-contract" "Production preflight changed safety semantics" "no sandbox requirement, restore still closed, legacy blocked" "assertion failed"
fi

printf '[smoke] Production read-only policy OK; legacy sandbox policy unchanged.\n'
