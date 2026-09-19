# Compatibility Matrix

## Product baseline

This is a modern reusable foundation, so the supported baseline follows a currently maintained WordPress/PHP combination rather than preserving legacy compatibility before the product has users.

| Component | Baseline | Validation |
| --- | --- | --- |
| WordPress | 7.1.x | Runtime smoke uses `wordpress:7.1.0-php8.2-apache` |
| PHP | 8.2+ | Runtime smoke baseline is PHP 8.2 |
| WP-CLI | 2.12.x | Runtime smoke uses `wordpress:cli-2.12.0-php8.2` |
| Database | MariaDB 11.8.x | Runtime smoke uses `mariadb:11.8.9` |

Theme and plugin package headers declare:

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
3. copy the repository theme and plugin into the fixture;
4. install WordPress through WP-CLI;
5. activate `seo-geo-core`;
6. activate `seo-geo-theme`;
7. verify both remain active;
8. verify the Core language service resolves the native provider;
9. verify the clean SEO integration detector resolves `native`;
10. request the frontend;
11. request `/wp-admin/` and follow the expected login redirect;
12. inspect WordPress runtime/debug logs for PHP fatal errors, warnings, notices and uncaught errors;
13. destroy the disposable database/network/volume/container resources.

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
