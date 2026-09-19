# Compatibility Matrix

## Product baseline

This is a modern reusable foundation, so the supported baseline follows a currently maintained WordPress/PHP combination rather than preserving legacy compatibility before the product has users.

| Component | Baseline | Validation |
| --- | --- | --- |
| WordPress | 7.1.x | Runtime smoke uses `wordpress:7.1.0-php8.2-apache` |
| PHP | 8.2+ | Runtime smoke baseline is PHP 8.2 |
| WP-CLI | 2.12.x | Runtime smoke uses `wordpress:cli-2.12.0-php8.2` |
| Database | MariaDB 11.8.x | Runtime smoke uses `mariadb:11.8.9` |

Theme and plugin package headers declare the same tested runtime baseline:

```text
Requires at least: 7.1
Requires PHP: 8.2
```

## Why the baseline starts at PHP 8.2

The project is new and has no installed legacy customer base to preserve. Starting on an end-of-life PHP branch would create avoidable maintenance and security debt. Compatibility may expand only when there is a product reason and CI coverage for that additional matrix.

## Runtime smoke contract

The clean-fixture smoke test must:

1. start an isolated MariaDB container;
2. start WordPress 7.1 on PHP 8.2;
3. copy the repository theme, transitional Core wrapper and Migration Bridge into the fixture;
4. install WordPress through WP-CLI;
5. activate `seo-geo-core` and `seo-geo-migration-bridge`;
6. activate `seo-geo-theme`;
7. verify all expected packages remain active;
8. verify the Core language service resolves the native provider;
9. verify the clean SEO integration detector resolves `native`;
10. create synthetic legacy-site signals for the Phase 8A analyzer acceptance;
11. verify the Migration Bridge inventories themes/plugins/builders/providers/content-model/customization signals without changing protected state;
12. request the frontend;
13. request `/wp-admin/` and follow the expected login redirect;
14. inspect WordPress runtime/debug logs for PHP fatal errors, warnings, notices and uncaught errors;
15. destroy the disposable database/network/volume/container resources.

## Test credentials

CI uses synthetic disposable credentials only. They are not production secrets and the fixture is destroyed after every run.

## Compatibility expansion policy

A new WordPress/PHP/database combination is not considered supported because it "probably works". It becomes supported when:

- a documented product need exists;
- the runtime fixture covers it;
- minimum sufficient validation passes;
- failures are diagnosable through the project structured-diagnostic contract.

## External integrations

Yoast, Rank Math, AIOSEO, WPML, Polylang and WooCommerce are not yet declared supported combinations merely because Phase 1 can detect some of them. Each integration is promoted to supported only in the phase that implements and tests its adapter/ownership contract.

Phase 7D may declare WooCommerce as the Ecommerce preset's **preferred future provider**. That declaration is a product/preset contract only and does not promote WooCommerce to a supported combination. The baseline remains zero-plugin safe; live WooCommerce support still requires a dedicated adapter plus runtime, ownership, multilingual and Schema acceptance.


## Migration Bridge compatibility

The Phase 8A Migration Bridge uses the same WordPress 7.1 / PHP 8.2 baseline as the repository runtime fixture.

Its detection of third-party products is informational only. Detecting Elementor, Divi, WooCommerce, Yoast, WPML or another provider does not promote that provider to a supported migration adapter. Compatibility is declared only after the corresponding migration/ownership contract has dedicated acceptance.
