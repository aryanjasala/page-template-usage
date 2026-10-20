# Reference

## WP-CLI

```sh
wp template-usage list [--post_type=<type>] [--status=<status>] [--format=<format>]
```

Lists every template with the number of items that use it. `--status` is `default`, `used`, `unused` or `missing`. `--format` is `table` (default), `csv`, `json`, `yaml` or `count`.

```sh
wp template-usage reassign <from> <to> [--post_type=<type>]
```

Moves every item from one template to another. `<from>` is a template file or slug, `default`, or `missing` for every template the theme no longer has. `<to>` is a template the theme offers, or `default`. `--post_type` defaults to `page`.

## PHP API

```php
$templates = AryanX\TemplateUsageColumn\Plugin::instance()->templates();

$templates->get_report( 'page' );                     // one row per template: slug, name, count, status
$templates->get_missing_counts( 'page' );             // [ 'templates/old.php' => 3 ]
$templates->reassign( 'page', 'templates/old.php', 'default' ); // number moved, or WP_Error
```

`Templates::MISSING` and `Templates::DEFAULT_TEMPLATE` are the special values for `reassign()` and `meta_query_for()`.

## Data

Nothing is stored. Templates come from `wp_get_theme()->get_page_templates()` and usage from the `_wp_page_template` post meta WordPress already keeps. Counts are cached against the `posts` last-changed key, so they are never stale.
