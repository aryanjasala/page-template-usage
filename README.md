# Template Usage Column

[![PHPCS](https://github.com/aryanjasala/template-usage-column/actions/workflows/phpcs.yml/badge.svg)](https://github.com/aryanjasala/template-usage-column/actions/workflows/phpcs.yml) [![PHPStan](https://github.com/aryanjasala/template-usage-column/actions/workflows/phpstan.yml/badge.svg)](https://github.com/aryanjasala/template-usage-column/actions/workflows/phpstan.yml) [![Unit tests](https://github.com/aryanjasala/template-usage-column/actions/workflows/unit-tests.yml/badge.svg)](https://github.com/aryanjasala/template-usage-column/actions/workflows/unit-tests.yml) [![E2E tests](https://github.com/aryanjasala/template-usage-column/actions/workflows/e2e-tests.yml/badge.svg)](https://github.com/aryanjasala/template-usage-column/actions/workflows/e2e-tests.yml) [![Plugin Check](https://github.com/aryanjasala/template-usage-column/actions/workflows/plugin-check.yml/badge.svg)](https://github.com/aryanjasala/template-usage-column/actions/workflows/plugin-check.yml)

Shows which template every page uses. Adds a **Template** column and a template filter to the Pages list, plus an **Appearance > Template Usage** report that finds pages pointing at templates the theme no longer has.

![The Template column and filter on the Pages screen](.wordpress-org/screenshot-1.png)

## Features

- **Template** column on Pages, and on any post type with templates, linked to the list of everything else on that template
- A filter that lists every template with its page count, so a count of 0 means the template is unused
- Sort the list by template
- Pages on a **missing template** (after a theme switch, or a renamed or deleted template file) are flagged in red, with a notice above the list
- **Appearance > Template Usage** shows every template on one screen and moves all pages off a template in one click
- Works with classic themes (PHP page templates, including `Template Post Type`) and block themes (custom templates from `theme.json` and the Site Editor)
- No settings and nothing stored in the database

## Installation

1. Download `template-usage-column.zip` from the [latest release](https://github.com/aryanjasala/template-usage-column/releases/latest).
2. In WordPress, go to **Plugins > Add New Plugin > Upload Plugin**, choose the zip and activate it.

Or with WP-CLI:

```sh
wp plugin install https://github.com/aryanjasala/template-usage-column/releases/latest/download/template-usage-column.zip --activate
```

Needs WordPress 6.6+ and PHP 7.4+.

## Usage

The column and filter appear on the Pages screen as soon as the plugin is active. **Missing template** in the filter lists the pages that fall back to the default template without any warning from WordPress:

![Pages whose template no longer exists](.wordpress-org/screenshot-2.png)

**Appearance > Template Usage** shows every template, how many items use it, and a **Move everything to** control for each one:

![Appearance > Template Usage](.wordpress-org/screenshot-3.png)

### WP-CLI

```sh
wp template-usage list [--post_type=<type>] [--status=default|used|unused|missing] [--format=table|csv|json]
wp template-usage reassign <from|missing> <to|default> [--post_type=page]
```

### For developers

`AryanX\TemplateUsageColumn\Plugin::instance()->templates()` returns the template data: `get_report( $post_type )` for the rows on the report screen, and `reassign( $post_type, $from, $to )` to move posts between templates.

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
| `npm run screenshots` | Regenerates the screenshots in `.wordpress-org/` |
| `npm run env:stop` | Removes the site and its database |

The plugin is mounted read-only into the Docker site, so nothing in WordPress can change or delete the source. The end-to-end run empties the site's content before it starts, so point it only at this throwaway site.

## Tests

- **10 unit tests**: default and missing template detection, usage counts (trashed posts excluded), report rows, the filter queries, reassigning, and the column order
- **6 end-to-end tests**: the column and the missing-template notice, the filter and its counts, sorting, Quick Edit keeping the column, moving pages off a missing template from the report, and the WP-CLI commands

## Contributing

`main` is protected: every change goes through a pull request, and it can only be merged once these pass:

- PHPCS and PHPStan
- PHPUnit on PHP 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4
- the end-to-end suite against the latest WordPress
- the WordPress.org Plugin Check on the built zip
- the PR title check

Pull requests are squash-merged, so the **PR title becomes the commit on `main`** and decides the next release. Use a [Conventional Commit](https://www.conventionalcommits.org/) title:

| Title | Release |
| --- | --- |
| `feat: add bulk restore` | minor (1.1.0 to 1.2.0) |
| `fix: keep the notice on narrow screens`, `perf: ...` | patch (1.1.0 to 1.1.1) |
| `feat!: drop PHP 7.4`, or a `BREAKING CHANGE:` footer | major (1.1.0 to 2.0.0) |
| `docs:`, `ci:`, `chore:`, `test:`, `refactor:`, `build:` | none |

## Releases

Releases are automatic:

1. After each merge to `main`, the **Prepare release** workflow checks for `feat`, `fix`, `perf` or breaking commits since the last tag. If there are any, it opens (or updates) a `chore(release): x.y.z` pull request that bumps the version in the plugin header, the `VERSION` constant and the readme `Stable tag`, and adds the changelog entry to `readme.txt`.
2. Merging that pull request runs the **Release** workflow: it tags `vx.y.z`, builds the zip with `bin/build-zip.sh` (leaving out everything in `.distignore`) and publishes the GitHub release with the changelog entry as notes.

`bin/release.sh next` and `bin/release.sh notes` show locally what the next release would contain.

Deploying to WordPress.org is off until the repository variable `WPORG_DEPLOY` is `true` and the secrets `SVN_USERNAME` and `SVN_PASSWORD` are set. The deploy uses `.wordpress-org/` for the screenshots.

## License

GPL-2.0-or-later
