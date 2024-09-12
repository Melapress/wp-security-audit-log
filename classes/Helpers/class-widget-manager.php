<?php
/**
 * Class: Widget manager.
 *
 * Helper class to show the widget in dashboard area.
 *
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Helpers;

use WSAL\Helpers\User_Utils;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Helpers\Plugin_Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Helpers\Widget_Manager' ) ) {

	/**
	 * Widget Manager
	 *
	 * Plugin Widget used in the WordPress Dashboard.
	 *
	 * @package wsal
	 */
	class Widget_Manager {

		/**
		 * Method: Constructor.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function init() {
			add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_widgets' ) );
		}

		/**
		 * Method: Add widgets.
		 *
		 * @since 5.0.0
		 */
		public static function add_widgets() {
			global $pagenow;

			if (
				! Settings_Helper::get_boolean_option_value( 'disable-widgets' ) // If widget is enabled.
				&& Settings_Helper::current_user_can( 'view' ) // If user has permission to view.
				&& 'index.php' === $pagenow // If the current page is dashboard.
			) {
				wp_add_dashboard_widget(
					'wsal',
					esc_html__( 'Latest Events', 'wp-security-audit-log' ) . ' | WP Activity Log',
					array( __CLASS__, 'render_widget' )
				);
			}
		}

		/**
		 * Method: Render widget.
		 *
		 * @since 5.0.0
		 */
		public static function render_widget() {
			$results = (array) Alert_Manager::get_latest_events( Settings_Helper::DASHBOARD_WIDGET_MAX_ALERTS, true );

			if ( empty( $results ) || ( isset( $results[0] ) && false === $results[0] ) ) {
				return;
			}

			?><div>
			<?php if ( ! count( $results ) ) { ?>
			<p><?php esc_html_e( 'No events found.', 'wp-security-audit-log' ); ?></p>
			<?php } else { ?>
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
					$url = 'admin.php?page=' . \call_user_func( array( View_Manager::get_views()[0], 'get_safe_view_name' ) );
					foreach ( $results as $entry ) :
						$event_meta = $entry['meta_values'];
						$username   = User_Utils::get_username( $event_meta );
						?>
						<tr>
							<td><?php echo ( $username ) ? esc_html( $username ) : '<i>unknown</i>'; ?></td>
							<td><?php echo ( $event_meta['Object'] ) ? esc_html( $event_meta['Object'] ) : '<i>unknown</i>'; ?></td>
							<td><?php echo ( $event_meta['EventType'] ) ? esc_html( $event_meta['EventType'] ) : '<i>unknown</i>'; ?></td>
							<td>
								<a href="<?php echo esc_url( $url ) . '#Event' . esc_attr( $entry['id'] ); ?>">
									<?php echo wp_kses( Occurrences_Entity::get_alert_message( $entry, 'dashboard-widget' ), Plugin_Settings_Helper::get_allowed_html_tags() ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php } ?>
			</div>
			<?php
		}
	}
}
