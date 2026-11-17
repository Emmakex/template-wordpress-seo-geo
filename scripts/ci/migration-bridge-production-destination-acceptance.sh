#!/usr/bin/env bash
# Read-only acceptance for production ZIP import target identity.
#
# This is not a restore acceptance. A production restore remains blocked until
# an end-to-end backup + reversible DB/files journal is implemented and proven.

printf '[smoke] Checking production ZIP import destination classification.\n'

PRODUCTION_TARGET_RUNNER="$TMP_DIR/migration-production-target-runner.php"
cat > "$PRODUCTION_TARGET_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\ImportDestinationPlan;

$before = array(
	'home'           => get_option( 'home' ),
	'siteurl'        => get_option( 'siteurl' ),
	'active_plugins' => get_option( 'active_plugins' ),
	'blog_public'    => get_option( 'blog_public' ),
	'template'       => get_option( 'template' ),
	'stylesheet'     => get_option( 'stylesheet' ),
);
$cases = array(
	'child_to_root' => ImportDestinationPlan::inspect(
		'https://emmake.com/nuevaweb/',
		'https://emmake.com/nuevaweb/',
		'https://emmake.com/',
		'https://emmake.com/'
	),
	'new_domain' => ImportDestinationPlan::inspect(
		'https://origin.example/blog/',
		'https://origin.example/blog/',
		'https://new.example/',
		'https://new.example/'
	),
	'same_site' => ImportDestinationPlan::inspect(
		'https://emmake.com/nuevaweb/',
		'https://emmake.com/nuevaweb/',
		'https://emmake.com/nuevaweb/',
		'https://emmake.com/nuevaweb/'
	),
	'different_siteurl_only' => ImportDestinationPlan::inspect(
		'https://emmake.com/',
		'https://emmake.com/nuevaweb/',
		'https://emmake.com/',
		'https://emmake.com/'
	),
	'invalid_url' => ImportDestinationPlan::inspect(
		'https://emmake.com/?unsafe=1',
		'https://emmake.com/',
		'https://other.example/',
		'https://other.example/'
	),
);
$after = array(
	'home'           => get_option( 'home' ),
	'siteurl'        => get_option( 'siteurl' ),
	'active_plugins' => get_option( 'active_plugins' ),
	'blog_public'    => get_option( 'blog_public' ),
	'template'       => get_option( 'template' ),
	'stylesheet'     => get_option( 'stylesheet' ),
);
$payload = array( 'cases' => $cases, 'unchanged' => $before === $after );
echo wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
PHP

docker cp "$PRODUCTION_TARGET_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-production-target-runner.php \
  || fail_smoke "production_target-copy" "Could not copy production import test runner" "runner accessible inside WordPress" "docker cp failed"

if ! PRODUCTION_TARGET_JSON="$(wp_cli eval-file /var/www/html/wp-content/migration-production-target-runner.php)"; then
  fail_smoke "production-target-runner" "Could not classify production import destination" "read-only class loading" "wp-cli error"
fi

if ! PRODUCTION_TARGET_JSON="$PRODUCTION_TARGET_JSON" python3 - <<'PY'
import json, os
payload = json.loads(os.environ["PRODUCTION_TARGET_JSON"])
cases = payload["cases"]
assert payload["unchanged"] is True
assert cases["child_to_root"]["scenario"] == "same-domain-different-path"
assert cases["child_to_root"]["status"] == "requires-protected-production-import"
assert cases["new_domain"]["scenario"] == "different-domain"
assert cases["new_domain"]["status"] == "requires-protected-production-import"
assert cases["same_site"]["status"] == "same-site-replacement-not-automatic"
assert cases["different_siteurl_only"]["status"] == "same-home-different-siteurl-review"
assert cases["invalid_url"]["status"] == "invalid-site-identity"
for plan in cases.values():
    assert plan["show_external_backup_warning"] is True
    assert plan["requires_confirmation"] is True
    assert plan["restore_authorized"] is False
print("ok")
PY
then
  fail_smoke "production-target-contract" "Production replacement planner broke its read-only safety contract" "identity classified, backup/approval required, no restore authorization" "python assertion failed"
fi

printf '[smoke] Production ZIP target identity OK (no mutations, no restore).\n'
