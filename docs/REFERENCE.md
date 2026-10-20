# Reference

## WP-CLI

```sh
wp template-usage list [--post_type=<type>] [--status=<status>] [--format=<format>]
```

Lists every template with the number of items that use it. `--status` is `default`, `used`, `unused` or `missing`. `--format` is `table` (default), `csv`, `json`, `yaml` or `count`.

```sh
wp template-usage reassign <from> <to> [--post_type=<type>]
```

Moves every item from one template to another, 100 at a time, with no limit. `<from>` is a template file or slug, `default`, or `missing` for every template the theme no longer has. `<to>` is a template the theme offers, or `default`. `--post_type` defaults to `page`.

The Move button on Appearance > Template Usage moves at most 500 items per click, skips items you are not allowed to edit, and says how many are left.

## Hooks

| Hook | Type | Use |
| --- | --- | --- |
| `template_usage_column_post_types` | filter | `( string[] $post_types )`. Post types to look at. Types without templates are left out on their own |
| `template_usage_column_sortable` | filter | `( bool $sortable, string $post_type )`. Return `false` to turn off sorting by template, which joins postmeta and is slow on very large sites |
| `template_usage_column_capability` | filter | `( string $capability )`. Who may open Appearance > Template Usage. Default `edit_pages` |
| `template_usage_column_reassigned` | action | `( string $post_type, string $from, string $to, int[] $post_ids )`. Fires for every batch of items moved |

## PHP API

```php
$templates = AryanX\TemplateUsageColumn\Plugin::instance()->templates();

$templates->get_report( 'page' );                     // one row per template: slug, name, count, status
$templates->get_missing_counts( 'page' );             // [ 'templates/old.php' => 3 ]
$templates->reassign( 'page', 'templates/old.php', 'default' );        // [ 'moved' => n, 'skipped' => 0, 'remaining' => 0 ] or WP_Error
$templates->reassign( 'page', 'templates/old.php', 'default', 500, true ); // at most 500, skipping items the current user cannot edit
```

`Templates::MISSING` and `Templates::DEFAULT_TEMPLATE` are the special values for `reassign()` and `meta_query_for()`.

## Data

Nothing is stored. Templates come from `wp_get_theme()->get_page_templates()` and usage from the `_wp_page_template` post meta WordPress already keeps.

Counts come from one grouped query per post type and stay in the object cache until a template or a post status changes, and an hour at most. Busy sites that save posts all day keep their cache.
