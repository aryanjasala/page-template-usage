<?php
/**
 * Appearance > Template Usage report.
 *
 * @package AryanX\TemplateUsageColumn
 */

namespace AryanX\TemplateUsageColumn\Admin;

use AryanX\TemplateUsageColumn\Service;
use AryanX\TemplateUsageColumn\Templates;
use const AryanX\TemplateUsageColumn\PLUGIN_FILE;
use const AryanX\TemplateUsageColumn\VERSION;

defined( 'ABSPATH' ) || exit;

/**
 * Lists every template with its usage, and moves posts off a template in one go.
 */
final class ReportPage implements Service {

	public const SLUG = 'template-usage';

	private const ACTION = 'template_usage_reassign';

	/**
	 * Template data.
	 *
	 * @var Templates
	 */
	private Templates $templates;

	/**
	 * Sets up the report.
	 *
	 * @param Templates $templates Template data.
	 */
	public function __construct( Templates $templates ) {
		$this->templates = $templates;
	}

	/**
	 * Adds the menu entry and the form handler.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_reassign' ) );
	}

	/**
	 * Most posts one click in wp-admin moves. WP-CLI has no limit.
	 */
	private const MOVE_LIMIT = 500;

	/**
	 * Items one move handles, from the screen or an ability.
	 *
	 * @param string $post_type Post type being moved.
	 * @return int
	 */
	public static function move_limit( string $post_type ): int {
		/**
		 * Filters how many items one Move click, or one reassign ability run, handles.
		 *
		 * @param int    $limit     Default 500.
		 * @param string $post_type Post type being moved.
		 */
		return max( 1, (int) apply_filters( 'template_usage_column_move_limit', self::MOVE_LIMIT, $post_type ) );
	}

	/**
	 * Capability needed to open Appearance > Template Usage.
	 *
	 * Moving posts also needs edit_posts on the post type, and edit_post on each post.
	 *
	 * @return string
	 */
	public static function capability(): string {
		/**
		 * Filters the capability needed to open Appearance > Template Usage.
		 *
		 * @param string $capability Default edit_pages.
		 */
		return (string) apply_filters( 'template_usage_column_capability', 'edit_pages' );
	}

	/**
	 * Registers Appearance > Template Usage.
	 */
	public function add_page(): void {
		$hook = add_theme_page(
			__( 'Template Usage', 'template-usage-column' ),
			__( 'Template Usage', 'template-usage-column' ),
			self::capability(),
			self::SLUG,
			array( $this, 'render' )
		);

		if ( $hook ) {
			add_action( "admin_print_styles-{$hook}", array( $this, 'enqueue_styles' ) );
		}
	}

	/**
	 * Report styles.
	 */
	public function enqueue_styles(): void {
		wp_enqueue_style( 'template-usage-column', plugins_url( 'assets/admin.css', PLUGIN_FILE ), array(), VERSION );
	}

	/**
	 * Prints the report.
	 */
	public function render(): void {
		$post_types = $this->templates->get_post_types();
		?>
		<div class="wrap tuc-report">
			<h1><?php esc_html_e( 'Template Usage', 'template-usage-column' ); ?></h1>
			<p class="tuc-report__intro">
				<?php esc_html_e( 'Every template your theme offers, how many items use it, and items that point at a template the theme no longer has.', 'template-usage-column' ); ?>
			</p>

			<?php $this->render_notice(); ?>

			<?php if ( ! $post_types ) : ?>
				<p><?php esc_html_e( 'The active theme has no templates to choose from.', 'template-usage-column' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $post_types as $post_type ) : ?>
				<?php $this->render_post_type( $post_type ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Prints the summary and table for one post type.
	 *
	 * @param \WP_Post_Type $post_type Post type.
	 */
	private function render_post_type( \WP_Post_Type $post_type ): void {
		$rows        = $this->templates->get_report( $post_type->name );
		$templates   = $this->templates->get_templates( $post_type->name );
		$can_edit    = current_user_can( $post_type->cap->edit_posts );
		$list_column = new ListColumn( $this->templates );
		$unused      = count( wp_list_filter( $rows, array( 'status' => 'unused' ) ) );
		$missing     = array_sum( wp_list_pluck( wp_list_filter( $rows, array( 'status' => 'missing' ) ), 'count' ) );
		$labels      = array(
			'default' => __( 'Default', 'template-usage-column' ),
			'used'    => __( 'In use', 'template-usage-column' ),
			'unused'  => __( 'Unused', 'template-usage-column' ),
			'missing' => __( 'Missing', 'template-usage-column' ),
		);
		?>
		<section class="tuc-report__section" aria-labelledby="tuc-<?php echo esc_attr( $post_type->name ); ?>">
			<h2 id="tuc-<?php echo esc_attr( $post_type->name ); ?>"><?php echo esc_html( $post_type->labels->name ); ?></h2>

			<ul class="tuc-stats">
				<?php /* translators: %s: number of templates. */ ?>
				<li><?php $this->stat( count( $templates ), _n( '%s template', '%s templates', count( $templates ), 'template-usage-column' ) ); ?></li>
				<?php /* translators: %s: number of unused templates. */ ?>
				<li class="<?php echo $unused ? 'is-notice' : ''; ?>"><?php $this->stat( $unused, _n( '%s unused', '%s unused', $unused, 'template-usage-column' ) ); ?></li>
				<li class="<?php echo $missing ? 'is-error' : ''; ?>">
					<?php
					if ( 'page' === $post_type->name ) {
						/* translators: %s: number of pages. */
						$this->stat( $missing, _n( '%s page on missing templates', '%s pages on missing templates', $missing, 'template-usage-column' ) );
					} elseif ( 'post' === $post_type->name ) {
						/* translators: %s: number of posts. */
						$this->stat( $missing, _n( '%s post on missing templates', '%s posts on missing templates', $missing, 'template-usage-column' ) );
					} else {
						/* translators: %s: number of items. */
						$this->stat( $missing, _n( '%s item on missing templates', '%s items on missing templates', $missing, 'template-usage-column' ) );
					}
					?>
				</li>
			</ul>

			<table class="widefat striped tuc-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Template', 'template-usage-column' ); ?></th>
						<th scope="col" class="num"><?php echo esc_html( $post_type->labels->name ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'template-usage-column' ); ?></th>
						<?php if ( $can_edit ) : ?>
							<th scope="col"><?php esc_html_e( 'Move everything to', 'template-usage-column' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr class="tuc-row tuc-row--<?php echo esc_attr( $row['status'] ); ?>" data-template="<?php echo esc_attr( $row['slug'] ); ?>">
							<td>
								<strong><?php echo esc_html( $row['name'] ); ?></strong>
								<?php if ( 'default' !== $row['status'] && 'missing' !== $row['status'] ) : ?>
									<code class="tuc-slug"><?php echo esc_html( $row['slug'] ); ?></code>
								<?php endif; ?>
							</td>
							<td class="num">
								<?php if ( $row['count'] ) : ?>
									<a href="<?php echo esc_url( $list_column->filter_url( $post_type->name, $row['slug'] ) ); ?>"><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></a>
								<?php else : ?>
									0
								<?php endif; ?>
							</td>
							<td><span class="tuc-badge tuc-badge--<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $labels[ $row['status'] ] ); ?></span></td>
							<?php if ( $can_edit ) : ?>
								<td>
									<?php if ( $row['count'] ) : ?>
										<?php $this->render_reassign_form( $post_type->name, $row['slug'], $templates ); ?>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	/**
	 * Prints one summary number with its label.
	 *
	 * @param int    $number Number.
	 * @param string $format Translated text with %s where the number goes.
	 */
	private function stat( int $number, string $format ): void {
		// The number goes inside the translated sentence, wrapped in <strong>.
		echo wp_kses( sprintf( esc_html( $format ), '<strong>' . esc_html( number_format_i18n( $number ) ) . '</strong>' ), array( 'strong' => array() ) );
	}

	/**
	 * Prints the "Move everything to" form for one template.
	 *
	 * @param string                $post_type Post type name.
	 * @param string                $from      Template to move away from.
	 * @param array<string, string> $templates Templates to move to.
	 */
	private function render_reassign_form( string $post_type, string $from, array $templates ): void {
		$targets = array( Templates::DEFAULT_TEMPLATE => __( 'Default', 'template-usage-column' ) ) + $templates;
		unset( $targets[ $from ] );
		$field_id = 'tuc-to-' . md5( $post_type . $from );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tuc-reassign">
			<?php wp_nonce_field( self::ACTION ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( $post_type ); ?>">
			<input type="hidden" name="from" value="<?php echo esc_attr( $from ); ?>">
			<?php /* translators: %s: template name or file. */ ?>
			<label class="screen-reader-text" for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( sprintf( __( 'New template for everything on %s', 'template-usage-column' ), $this->templates->get_name( $post_type, $from ) ?? $from ) ); ?></label>
			<select name="to" id="<?php echo esc_attr( $field_id ); ?>">
				<?php foreach ( $targets as $slug => $name ) : ?>
					<option value="<?php echo esc_attr( (string) $slug ); ?>"><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button" aria-describedby="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Move', 'template-usage-column' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Prints the result of the last move.
	 */
	private function render_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only values set by our own redirect.
		if ( isset( $_GET['tuc_error'] ) ) {
			// Only known codes, so a crafted link cannot put its own text in a notice.
			$errors = array(
				'unknown_template' => __( 'The theme does not have that template.', 'template-usage-column' ),
			);
			$code   = sanitize_key( wp_unslash( $_GET['tuc_error'] ) );
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $errors[ $code ] ?? __( 'The templates could not be changed.', 'template-usage-column' ) ) );
			return;
		}

		if ( ! isset( $_GET['tuc_moved'] ) ) {
			return;
		}

		$moved   = absint( $_GET['tuc_moved'] );
		$left    = isset( $_GET['tuc_left'] ) ? absint( $_GET['tuc_left'] ) : 0;
		$skipped = isset( $_GET['tuc_skipped'] ) ? absint( $_GET['tuc_skipped'] ) : 0;
		$type    = isset( $_GET['tuc_type'] ) ? sanitize_key( wp_unslash( $_GET['tuc_type'] ) ) : 'page';
		$to      = isset( $_GET['tuc_to'] ) ? sanitize_text_field( wp_unslash( $_GET['tuc_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$lines = array(
			sprintf(
				/* translators: 1: number of items, 2: template name. */
				_n( 'Moved %1$s item to "%2$s".', 'Moved %1$s items to "%2$s".', $moved, 'template-usage-column' ),
				number_format_i18n( $moved ),
				(string) ( $this->templates->get_name( $type, $to ) ?? $to )
			),
		);
		if ( $skipped ) {
			/* translators: %s: number of items. */
			$lines[] = sprintf( _n( 'Skipped %s item you are not allowed to edit.', 'Skipped %s items you are not allowed to edit.', $skipped, 'template-usage-column' ), number_format_i18n( $skipped ) );
		}
		if ( $left ) {
			/* translators: %s: number of items. */
			$lines[] = sprintf( _n( '%s more is left. Click Move again, or run wp template-usage reassign for all of them at once.', '%s more are left. Click Move again, or run wp template-usage reassign for all of them at once.', $left, 'template-usage-column' ), number_format_i18n( $left ) );
		}

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( implode( ' ', $lines ) ) );
	}

	/**
	 * Moves every post from one template to another.
	 */
	public function handle_reassign(): void {
		check_admin_referer( self::ACTION );

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$from      = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to        = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		$object    = get_post_type_object( $post_type );
		$back      = admin_url( 'themes.php?page=' . self::SLUG );

		if ( ! $object || ! current_user_can( self::capability() ) || ! current_user_can( $object->cap->edit_posts ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change templates for these items.', 'template-usage-column' ), 403 );
		}

		$result = $this->templates->reassign( $post_type, $from, $to, self::move_limit( $post_type ), true );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'tuc_error', 'unknown_template', $back ) );
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'tuc_moved'   => $result['moved'],
					'tuc_skipped' => $result['skipped'],
					'tuc_left'    => $result['remaining'],
					'tuc_type'    => $post_type,
					'tuc_to'      => rawurlencode( $to ),
				),
				$back
			)
		);
		exit;
	}
}
