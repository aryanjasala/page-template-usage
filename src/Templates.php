<?php
/**
 * Template choices and usage counts.
 *
 * @package AryanX\TemplateUsageColumn
 */

namespace AryanX\TemplateUsageColumn;

defined( 'ABSPATH' ) || exit;

/**
 * Reads which templates the theme offers and which posts use them, and moves
 * posts from one template to another.
 */
final class Templates {

	public const META_KEY         = '_wp_page_template';
	public const DEFAULT_TEMPLATE = 'default';
	public const MISSING          = '__missing';

	private const CACHE_GROUP = 'template-usage-column';

	/**
	 * Templates the active theme offers for a post type, keyed by file or slug.
	 *
	 * Covers PHP templates in classic themes and custom templates in block
	 * themes, including ones created in the Site Editor.
	 *
	 * @param string $post_type Post type name.
	 * @return array<string, string> Template file or slug => display name.
	 */
	public function get_templates( string $post_type ): array {
		$templates = wp_get_theme()->get_page_templates( null, $post_type );
		asort( $templates, SORT_NATURAL | SORT_FLAG_CASE );

		return $templates;
	}

	/**
	 * Whether a stored template value means "use the default template".
	 *
	 * @param string $slug Stored template value.
	 * @return bool
	 */
	public function is_default( string $slug ): bool {
		return '' === $slug || self::DEFAULT_TEMPLATE === $slug;
	}

	/**
	 * Display name of a stored template value.
	 *
	 * @param string $post_type Post type name.
	 * @param string $slug      Stored template value.
	 * @return string|null Name, or null when the theme does not have the template.
	 */
	public function get_name( string $post_type, string $slug ): ?string {
		if ( $this->is_default( $slug ) ) {
			return __( 'Default', 'template-usage-column' );
		}

		$templates = $this->get_templates( $post_type );

		return $templates[ $slug ] ?? null;
	}

	/**
	 * Number of posts per stored template value, excluding trashed posts.
	 *
	 * Cached against the `posts` last-changed key, which core bumps whenever
	 * a post or its meta changes, so counts are never stale.
	 *
	 * @param string $post_type Post type name.
	 * @return array<string, int> Stored template value => post count.
	 */
	public function get_usage_counts( string $post_type ): array {
		global $wpdb;

		$cache_key = 'counts:' . $post_type . ':' . wp_cache_get_last_changed( 'posts' );
		$counts    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $counts ) ) {
			return $counts;
		}

		// A grouped count across postmeta has no WP_Query equivalent.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS template, COUNT(*) AS total
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				GROUP BY pm.meta_value",
				self::META_KEY,
				$post_type
			)
		);

		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (string) $row->template ] = (int) $row->total;
		}

		wp_cache_set( $cache_key, $counts, self::CACHE_GROUP );

		return $counts;
	}

	/**
	 * Number of posts that use the default template, with or without the meta set.
	 *
	 * @param string $post_type Post type name.
	 * @return int
	 */
	public function get_default_count( string $post_type ): int {
		$all = 0;
		foreach ( (array) wp_count_posts( $post_type ) as $status => $total ) {
			if ( ! in_array( $status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) {
				$all += (int) $total;
			}
		}

		$custom = 0;
		foreach ( $this->get_usage_counts( $post_type ) as $slug => $total ) {
			if ( ! $this->is_default( (string) $slug ) ) {
				$custom += $total;
			}
		}

		return max( 0, $all - $custom );
	}

	/**
	 * Stored template values the theme does not have any more, with post counts.
	 *
	 * @param string $post_type Post type name.
	 * @return array<string, int>
	 */
	public function get_missing_counts( string $post_type ): array {
		$templates = $this->get_templates( $post_type );
		$missing   = array();

		foreach ( $this->get_usage_counts( $post_type ) as $slug => $total ) {
			$slug = (string) $slug;
			if ( ! $this->is_default( $slug ) && ! isset( $templates[ $slug ] ) ) {
				$missing[ $slug ] = $total;
			}
		}

		return $missing;
	}

	/**
	 * Whether a post type has templates to choose from, or posts still pointing at one.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public function uses_templates( string $post_type ): bool {
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return false;
		}

		return $this->get_templates( $post_type ) || $this->get_missing_counts( $post_type );
	}

	/**
	 * Post types with an admin screen that use templates.
	 *
	 * @return \WP_Post_Type[]
	 */
	public function get_post_types(): array {
		return array_values(
			array_filter(
				get_post_types( array( 'show_ui' => true ), 'objects' ),
				function ( \WP_Post_Type $post_type ) {
					return 'attachment' !== $post_type->name && 0 !== strpos( $post_type->name, 'wp_' ) && $this->uses_templates( $post_type->name );
				}
			)
		);
	}

	/**
	 * One row per template for the report: offered templates, then missing ones.
	 *
	 * @param string $post_type Post type name.
	 * @return array<int, array{slug: string, name: string, count: int, status: string}>
	 */
	public function get_report( string $post_type ): array {
		$counts = $this->get_usage_counts( $post_type );
		$rows   = array(
			array(
				'slug'   => self::DEFAULT_TEMPLATE,
				'name'   => __( 'Default', 'template-usage-column' ),
				'count'  => $this->get_default_count( $post_type ),
				'status' => 'default',
			),
		);

		foreach ( $this->get_templates( $post_type ) as $slug => $name ) {
			$count  = $counts[ $slug ] ?? 0;
			$rows[] = array(
				'slug'   => (string) $slug,
				'name'   => $name,
				'count'  => $count,
				'status' => $count ? 'used' : 'unused',
			);
		}

		foreach ( $this->get_missing_counts( $post_type ) as $slug => $count ) {
			$rows[] = array(
				'slug'   => $slug,
				'name'   => $slug,
				'count'  => $count,
				'status' => 'missing',
			);
		}

		return $rows;
	}

	/**
	 * Meta query that matches posts using a template.
	 *
	 * @param string $post_type Post type name.
	 * @param string $template  Template value, DEFAULT_TEMPLATE, or MISSING for every missing template.
	 * @return array
	 */
	public function meta_query_for( string $post_type, string $template ): array {
		if ( self::DEFAULT_TEMPLATE === $template ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => self::META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META_KEY,
					'value'   => array( '', self::DEFAULT_TEMPLATE ),
					'compare' => 'IN',
				),
			);
		}

		if ( self::MISSING === $template ) {
			return array(
				array(
					'key'     => self::META_KEY,
					'value'   => array_merge( array_keys( $this->get_templates( $post_type ) ), array( '', self::DEFAULT_TEMPLATE ) ),
					'compare' => 'NOT IN',
				),
			);
		}

		return array(
			array(
				'key'   => self::META_KEY,
				'value' => $template,
			),
		);
	}

	/**
	 * Moves every post from one template to another.
	 *
	 * @param string $post_type Post type name.
	 * @param string $from      Template value, DEFAULT_TEMPLATE, or MISSING.
	 * @param string $to        Template the theme offers, or DEFAULT_TEMPLATE.
	 * @return int|\WP_Error Number of posts moved.
	 */
	public function reassign( string $post_type, string $from, string $to ) {
		if ( ! $this->is_default( $to ) && ! isset( $this->get_templates( $post_type )[ $to ] ) ) {
			return new \WP_Error( 'template_usage_unknown_template', __( 'The theme does not have that template.', 'template-usage-column' ) );
		}

		$post_ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Templates are only stored in meta.
				'meta_query'     => $this->meta_query_for( $post_type, $from ),
			)
		);

		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, self::META_KEY, $this->is_default( $to ) ? self::DEFAULT_TEMPLATE : $to );
		}

		return count( $post_ids );
	}
}
