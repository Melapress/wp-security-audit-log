<?php
/**
 * Gravity forms sensor helper
 *
 * @since     4.6.0
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\GravityForms_Helper' ) ) {

	/**
	 * Helper Sensor class for YOAST.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 * @since      4.6.0
	 */
	class GravityForms_Helper {

		/**
		 * Addes our plugin to the list of allowed public sensors.
		 *
		 * @param  array $value - Allowed sensors.
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function wsal_gravityforms_extension_load_public_sensors( $value ) {
			$value[] = 'Gravity_Forms';
			return $value;
		}

		/**
		 * Ensures front end sensor can load when needed.
		 *
		 * @param bool  $default_value - Current loading situation.
		 * @param array $frontend_events - Array of current front end events.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function wsal_gravityforms_allow_sensor_on_frontend( $default_value, $frontend_events ) {
			if ( ! isset( $frontend_events['gravityforms'] ) ) {
				return $default_value;
			} else {
				return ( $default_value || ! false === $frontend_events['gravityforms'] );
			}
		}

		/**
		 * Append some extra content below an event in the ToggleAlerts view.
		 *
		 * @param int $alert_id - Event ID.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function append_content_to_toggle( $alert_id ) {

			if ( 5709 === $alert_id ) {
				$frontend_events     = Settings_Helper::get_frontend_events();
				$enable_for_visitors = ( isset( $frontend_events['gravityforms'] ) && $frontend_events['gravityforms'] ) ? true : false;
				?>
				<tr class="alert-wrapper" data-alert-cat="Gravity Forms" data-alert-subcat="Monitor Gravity Forms" data-is-attached-to-alert="5709">
					<td></td>
					<td>
					<input name="frontend-events[gravityforms]" type="checkbox" id="frontend-events[gravityforms]" value="1" <?php checked( $enable_for_visitors ); ?> />
					</td>
					<td colspan="2"><?php esc_html_e( 'Keep a log when website visitors submits a form (by default the plugin only keeps a log when logged in users submit a form).', 'wp-security-audit-log' ); ?></td>
				</tr>
				<?php
			}
		}

		/**
		 * Register a custom event object within WSAL.
		 *
		 * @param array $objects array of objects current registered within WSAL.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_gravityforms_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'gravityforms_forms'         => esc_html__( 'Forms in Gravity Forms', 'wp-security-audit-log' ),
				'gravityforms_confirmations' => esc_html__( 'Confirmations in Gravity Forms', 'wp-security-audit-log' ),
				'gravityforms_notifications' => esc_html__( 'Notifications in Gravity Forms', 'wp-security-audit-log' ),
				'gravityforms_entries'       => esc_html__( 'Entries in Gravity Forms', 'wp-security-audit-log' ),
				'gravityforms_fields'        => esc_html__( 'Fields in Gravity Forms', 'wp-security-audit-log' ),
				'gravityforms_settings'      => esc_html__( 'Settings in Gravity Forms', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Added our event types to the available list.
		 *
		 * @param  array $types - Current event types.
		 *
		 * @return array $types - Altered list.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_gravityforms_add_custom_event_type( $types ) {
			$new_types = array(
				'starred'   => esc_html__( 'Starred', 'wp-security-audit-log' ),
				'unstarred' => esc_html__( 'Unstarred', 'wp-security-audit-log' ),
				'read'      => esc_html__( 'Read', 'wp-security-audit-log' ),
				'unread'    => esc_html__( 'Unread', 'wp-security-audit-log' ),
				'submitted' => esc_html__( 'Submitted', 'wp-security-audit-log' ),
				'imported'  => esc_html__( 'Imported', 'wp-security-audit-log' ),
				'exported'  => esc_html__( 'Exported', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$types = array_merge( $types, $new_types );

			return $types;
		}

		/**
		 * Lets WSAL know which events should have a sub category.
		 *
		 * @param  array $sub_category_events - Current list of events.
		 *
		 * @return array $sub_category_events - Appended list of events.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_gravityforms_extension_togglealerts_sub_category_events( $sub_category_events ) {
			$new_events          = array( 5700, 5705, 5706, 5710, 5716 );
			$sub_category_events = array_merge( $sub_category_events, $new_events );
			return $sub_category_events;
		}

		/**
		 * Adds the titles to the ToggleEvents view for the relevent events.
		 *
		 * @param string $title - Default title for this event.
		 * @param int    $alert_id - Alert ID we are determining the title for.
		 *
		 * @return string $title - Our new title.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_gravityforms_extension_togglealerts_sub_category_titles( $title, $alert_id ) {
			if ( 5700 === $alert_id ) {
				$title = esc_html_e( 'Forms', 'wp-security-audit-log' );
			}
			if ( 5705 === $alert_id ) {
				$title = esc_html_e( 'Form confirmations', 'wp-security-audit-log' );
			}
			if ( 5706 === $alert_id ) {
				$title = esc_html_e( 'Form notifications', 'wp-security-audit-log' );
			}
			if ( 5710 === $alert_id ) {
				$title = esc_html_e( 'Entries', 'wp-security-audit-log' );
			}
			if ( 5716 === $alert_id ) {
				$title = esc_html_e( 'Settings', 'wp-security-audit-log' );
			}
			return $title;
		}

		/**
		 * Checks if the YOAST is active.
		 *
		 * @return boolean
		 *
		 * @since 4.6.0
		 */
		public static function is_gravityforms_active() {
			return ( WP_Helper::is_plugin_active( 'gravityforms/gravityforms.php' ) );
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'activity-log-gravity-forms/activity-log-gravity-forms.php';
		}

		/**
		 * Further process the $_POST data upon saving events in the ToggleAlerts view.
		 *
		 * @param array  $disabled          Empty array which we will fill if needed.
		 * @param object $registered_alerts Currently registered alerts.
		 * @param array  $frontend_events   Array of currently enabled frontend events, taken from POST data.
		 * @param array  $enabled           Currently enabled events.
		 *
		 * @return array Disabled events.
		 *
		 * @since 4.6.3
		 */
		public static function save_settings_disabled_events( $disabled, $registered_alerts, $frontend_events, $enabled ) {
			// Now we check all registered events for further processing.
			foreach ( $disabled as $alert ) {
				// Disable Visitor events if the user disabled the event there are "tied to" in the UI.
				if ( 5709 === $alert ) {
					$frontend_events = array_merge( $frontend_events, array( 'gravityforms' => false ) );
					Settings_Helper::set_frontend_events( $frontend_events );
				}
			}

			return $disabled;
		}
	}
}
