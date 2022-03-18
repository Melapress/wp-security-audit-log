<?php
/**
 * Widget Manager
 *
 * Manager class for WSAL's WP Dashboard widget.
 *
 * @since   1.0.0
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget Manager
 *
 * Plugin Widget used in the WordPress Dashboard.
 *
 * @package wsal
 */
class WSAL_WidgetManager {

	/**
	 * Instance of WpSecurityAuditLog.
	 *
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;
		add_action( 'wp_dashboard_setup', array( $this, 'add_widgets' ) );
	}

	/**
	 * Method: Add widgets.
	 */
	public function add_widgets() {
		global $pagenow;

		if (
				$this->plugin->settings()->is_widgets_enabled() // If widget is enabled.
				&& $this->plugin->settings()->current_user_can( 'view' ) // If user has permission to view.
				&& 'index.php' === $pagenow // If the current page is dashboard.
		) {
			wp_add_dashboard_widget(
				'wsal',
				esc_html__( 'Latest Events', 'wp-security-audit-log' ) . ' | WP Activity Log',
				array( $this, 'render_widget' )
			);
		}
	}

	/**
	 * Method: Render widget.
	 */
	public function render_widget() {
		// get the events for the dashboard widget.
		$query   = $this->get_dashboard_widget_query();
		$results = $query->get_adapter()->execute_query( $query );

		?><div>
		<?php if ( ! count( $results ) ) : ?>
			<p><?php esc_html_e( 'No events found.', 'wp-security-audit-log' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat" cellspacing="0" cellpadding="0"
				style="display: block; overflow-x: auto;">
				<thead>
					<th class="manage-column" style="width: 10%;" scope="col"><?php esc_html_e( 'User', 'wp-security-audit-log' ); ?></th>
					<th class="manage-column" style="width: 10%;" scope="col"><?php esc_html_e( 'Object', 'wp-security-audit-log' ); ?></th>
					<th class="manage-column" style="width: 10%;" scope="col"><?php esc_html_e( 'Event Type', 'wp-security-audit-log' ); ?></th>
					<th class="manage-column" style="width: 70%;" scope="col"><?php esc_html_e( 'Description', 'wp-security-audit-log' ); ?></th>
				</thead>
				<tbody>
					<?php
					$url = 'admin.php?page=' . $this->plugin->views->views[0]->get_safe_view_name();
					foreach ( $results as $entry ) :
						$event_meta = $entry->get_meta_array();
						$username   = WSAL_Utilities_UsersUtils::get_username( $event_meta );
						?>
						<tr>
							<td><?php echo ( $username ) ? esc_html( $username ) : '<i>unknown</i>'; ?></td>
							<td><?php echo ( $event_meta['Object'] ) ? esc_html( $event_meta['Object'] ) : '<i>unknown</i>'; ?></td>
							<td><?php echo ( $event_meta['EventType'] ) ? esc_html( $event_meta['EventType'] ) : '<i>unknown</i>'; ?></td>
							<td>
								<a href="<?php echo esc_url( $url ) . '#Event' . esc_attr( $entry->get_id() ); ?>">
									<?php echo wp_kses( $entry->get_message( $event_meta, 'dashboard-widget' ), $this->plugin->allowed_html_tags ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Gets the query for the events displayed in the dashboard widget.
	 *
	 * @method get_dashboard_widget_query
	 * @since  4.0.3
	 * @return WSAL_Models_OccurrenceQuery
	 */
	public function get_dashboard_widget_query() {
		$query = new WSAL_Models_OccurrenceQuery();
		// get the site we are on (of multisite).
		$bid = (int) $this->get_view_site_id();
		if ( $bid ) {
			$query->add_condition( 'site_id = %s ', $bid );
		}
		// order by date of creation.
		$query->add_order_by( 'created_on', true );
		// set the limit based on the limit option for dashboard alerts.
		$query->set_limit( $this->plugin->settings()->get_dashboard_widget_max_alerts() );
		return $query;
	}

	/**
	 * Method: Get view site id.
	 */
	protected function get_view_site_id() {
		if ( is_super_admin() ) {
			return 0;
		} else {
			return get_current_blog_id();
		}
	}
}
