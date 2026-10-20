# Template Usage Column

Shows which template every page uses. Adds a **Template** column and a template filter to the Pages list (and to any post type with templates), plus an **Appearance > Template Usage** report.

- The filter lists every template the theme offers with the number of pages on it, so a count of 0 means the template is unused
- The column sorts by template
- Pages whose template no longer exists (after a theme switch, or a renamed or deleted template file) are flagged in red, with a notice above the list
- The report moves every page off a template in one click, for example from a missing template to the default one
- Works with classic themes (PHP page templates) and block themes (custom templates from `theme.json` and the Site Editor)

User-facing docs are in [`readme.txt`](readme.txt), which is what WordPress.org shows.

## Requirements

WordPress 6.6+, PHP 7.4+.

## Development

```sh
composer install
npm ci
```

| Command | What it runs |
| --- | --- |
| `composer lint` | PHPCS with the WordPress standard and PHPCompatibility from PHP 7.4 |
| `composer analyse` | PHPStan level 6 with WordPress and WP-CLI stubs |
| `composer test` | PHPUnit unit tests. WordPress functions are mocked with Brain Monkey, so no database is needed |
| `npm run env:start` | WordPress and MariaDB in Docker on http://localhost:8895 |
| `npm run test:e2e` | Playwright end-to-end tests against that site, in headless Chrome |
| `npm run env:stop` | Removes the site and its database |

The plugin is mounted read-only into the Docker site, so nothing in WordPress can change or delete the source. The end-to-end run empties the site's content before it starts, so point it only at this throwaway site.

## Tests

- **10 unit tests**: default and missing template detection, usage counts (trashed posts excluded), report rows, the filter queries, reassigning, and column order
- **6 end-to-end tests**: the column and the missing-template notice, the filter and its counts, sorting, Quick Edit keeping the column, moving pages off a missing template from the report, and the WP-CLI commands

## CI

| Workflow | Runs on | What it checks |
| --- | --- | --- |
| PHPCS | push to `main`, pull requests | WordPress coding standards |
| PHPStan | push to `main`, pull requests | Static analysis, level 6 |
| Unit tests | push to `main`, pull requests | PHPUnit on PHP 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4 |
| E2E tests | push to `main`, pull requests | Playwright against the latest WordPress in Docker |
| Plugin Check | push to `main`, pull requests | The official WordPress.org Plugin Check, on the built zip |
| Release | tags `v*` | Builds the zip and publishes a GitHub release |

## Releasing

1. Update the version in three places: the plugin header, the `VERSION` constant and `Stable tag` in `readme.txt`. Add a `= x.y.z =` entry under `== Changelog ==`.
2. Push a tag: `git tag v1.2.0 && git push origin v1.2.0`.

The release workflow fails if the tag does not match all three versions or the changelog has no entry for it. Otherwise it runs PHPCS and PHPUnit, builds the zip with `bin/build-zip.sh` (leaving out everything in `.distignore`), and publishes a GitHub release with the changelog entry as notes.

Deploying to WordPress.org is off until the repository variable `WPORG_DEPLOY` is `true` and the secrets `SVN_USERNAME` and `SVN_PASSWORD` are set.

## Layout

```
template-usage-column.php   plugin header and PSR-4 autoloader
src/
  Plugin.php                creates the services and registers their hooks
  Templates.php             templates the theme offers, usage counts, reassigning
  Admin/ListColumn.php      column, filter, sorting and the missing-template notice
  Admin/ReportPage.php      Appearance > Template Usage
  Cli/Command.php           wp template-usage list|reassign
tests/
  Unit/                     PHPUnit
  e2e/                      Playwright specs and the Docker site
```

## License

GPL-2.0-or-later
