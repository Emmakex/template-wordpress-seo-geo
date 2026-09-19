# SEO/GEO Migration Bridge

Temporary WordPress migration tooling for adopting existing client sites without treating production as disposable.

## Phase 8A scope

The first implementation is **read-only**. It inventories:

- active/inactive themes and child-theme relationships;
- active/inactive/must-use plugins;
- native blocks, Elementor and Divi presence through an extensible builder-detector contract;
- registered post types and taxonomies;
- shortcode/widget/menu/template signals;
- WooCommerce and other known provider families;
- SEO, Schema, multilingual, redirects, analytics, forms, cache and security providers;
- custom CSS and active-theme `functions.php` signals without exporting their contents.

The machine-readable report is available from PHP through:

```php
$report = \SeoGeo\MigrationBridge\Plugin::analyzer()?->analyze();
```

The report includes hashes/counts/signals where useful and deliberately avoids arbitrary option values, credentials and private content.

## Safety boundary

Phase 8A does not:

- write options;
- create/update/delete posts or terms;
- activate/deactivate plugins;
- switch themes;
- rewrite URLs;
- scan or convert builder content;
- persist the report.

Content-level dependency mapping belongs to Phase 8C. Persisted SEO/GEO baselines belong to Phase 8B. Production mutation belongs only to later explicitly authorized migration/cutover phases.

The plugin is a temporary adoption tool and is never required by the final self-contained theme.
