<?php
/**
 * Paid Memberships Pro Member Panel class.
 *
 * Used to display a list of WSAL events in individual member edit pages, filtered to that user.
 *
 * @package    wsal
 * @subpackage wsal-paid-memberships-pro
 *
 * @since 5.5.2
 */

namespace WSAL\PMP_Addon_Member_Edit_Panel;

use WSAL\Controllers\Alert;
use WSAL\Controllers\Constants;
use WSAL\Controllers\Alert_Manager;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Helpers\DateTime_Formatter_Helper;
use WSAL\WP_Sensors\Helpers\Paid_Memberships_Pro_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Proceed only if this class doesn't exist and if the PMPro_Member_Edit_Panel class exists.
 */
if ( ! class_exists( '\WSAL\MainWP\PMP_Addon_Member_Edit_Panel' ) && class_exists( 'PMPro_Member_Edit_Panel' ) ) {

	/**
	 * Paid Memberships Pro Member Panel class.
	 *
	 * @since 5.5.2
	 */
	class PMP_Addon_Member_Edit_Panel extends \PMPro_Member_Edit_Panel {

		/**
		 * Constructor
		 *
		 * @since 5.5.2
		 */
		public function __construct() {
			// The slug for this panel.
			$this->slug = 'pmp-wp-activity-log';

			// Displayed title for this panel.
			$this->title = \__( 'Member Activity', 'wp-security-audit-log' );
		}

		/**
		 * Events data
		 *
		 * Some PMP events can be triggered only by Admins and will not be returned in this panel. We get only the events that this specific user has triggered.
		 *
		 * @param int $user_id The user ID to fetch events for.
		 *
		 * @return array List of events for this user.
		 *
		 * @since 5.5.2
		 */
		private static function pmp_panel_events( int $user_id ) {

			$events = Paid_Memberships_Pro_Helper::get_plugin_events();

			// Short-circuit if no events are configured to avoid invalid SQL.
			if ( empty( $events ) ) {
				return array();
			}

			// Placeholders to spread events in the SQL query.
			$placeholders = implode( ',', array_fill( 0, count( $events ), '%d' ) );

			$sql = Occurrences_Entity::get_connection()->prepare(
				'SELECT * FROM ' . Occurrences_Entity::get_table_name() . ' WHERE user_id = %d AND alert_id IN (' . $placeholders . ') ORDER BY created_on DESC LIMIT 10',
				$user_id,
				...$events
			);

			$response = Occurrences_Entity::load_query( $sql );

			// Return events with metadata.
			return Occurrences_Entity::get_multi_meta_array( $response );
		}

		/**
		 * Gets the audit log URL filtered by user.
		 *
		 * @param int $user_id - The user ID to use in the filter for the activity log.
		 *
		 * @return string - The audit log URL with user filter applied.
		 *
		 * @since 5.6.0
		 */
		private static function get_user_activity_log_url( int $user_id ): string {
			$username = '';

			if ( $user_id > 0 ) {
				$user_data = \get_userdata( $user_id );
				if ( $user_data ) {
					$username = $user_data->user_login;
				}
			}

			$audit_log_url = \admin_url( '?page=wsal-auditlog' );

			if ( ! empty( $username ) ) {
				$audit_log_url = \add_query_arg( array( 'filters[0]' => 'username:' . $username ), $audit_log_url );
			}

			return $audit_log_url;
		}

		/**
		 * Display the panel contents.
		 *
		 * @since 5.5.2
		 */
		protected function display_panel_contents() {

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only panel view, no nonce provided by PMPro. Capability check below.
			$user_id = isset( $_REQUEST['user_id'] ) ? (int) $_REQUEST['user_id'] : 0;

			if ( ! \current_user_can( 'edit_users' ) && \get_current_user_id() !== $user_id ) {
				return array();
			}

			if ( $user_id <= 0 ) {
				return array();
			}

			$events = self::pmp_panel_events( $user_id );

			?>
			<p><?php \esc_html_e( 'Here are the most recent user activity records for this member.', 'wp-security-audit-log' ); ?></p>
			<table class="widefat striped fixed">
				<thead>
					<tr>
						<th><?php \esc_html_e( 'Alert ID', 'wp-security-audit-log' ); ?></th>
						<th><?php \esc_html_e( 'Severity', 'wp-security-audit-log' ); ?></th>
						<th><?php \esc_html_e( 'Date', 'wp-security-audit-log' ); ?></th>
						<th><?php \esc_html_e( 'Object', 'wp-security-audit-log' ); ?></th>
						<th><?php \esc_html_e( 'Event Type', 'wp-security-audit-log' ); ?></th>
						<th><?php \esc_html_e( 'Message', 'wp-security-audit-log' ); ?></th>
					</tr>
				</thead>
				<tbody>
			<?php

			if ( is_array( $events ) && count( $events ) > 0 ) {
				foreach ( $events as $event ) {

					// The specific ID of this occurrence record in the DB.
					$occurence_id = (int) $event['id'];

					$event_metadata = $event['meta_values'] ?? null;

					if ( ! $event_metadata ) {
						continue;
					}

					// The generic ID of this event type, NOT the ID of the occurrence record in the DB.
					$event_id           = $event['alert_id'];
					$event_severity     = Constants::get_severity_name_by_code( $event['severity'] );
					$event_time_created = DateTime_Formatter_Helper::get_formatted_date_time( $event['created_on'], 'datetime', true, true );
					$event_object       = Alert_Manager::get_event_objects_data( $event['object'] );
					$event_type         = Alert_Manager::get_event_type_data( $event['event_type'] );
					$event_message      = Alert::get_message( $event['meta_values'], null, $event_id, $occurence_id, 'default' );

					?>
				<tr>
					<td><?php echo (int) $event_id; ?></td>
					<td><?php echo \esc_html( $event_severity ); ?></td>
					<td><?php echo \wp_kses( $event_time_created, array( 'br' => array() ) ); ?></td>
					<td><?php echo \esc_html( $event_object ); ?></td>
					<td><?php echo \esc_html( $event_type ); ?></td>
					<td><?php echo \wp_kses_post( $event_message ); ?></td>
				</tr>
					<?php
				}
			} else {
				echo '<p>' . \esc_html__( 'No events found for this user.', 'wp-security-audit-log' ) . '</p>';
			}

			?>
				</tbody>
			</table>
			<p>
				<a class="button button-primary" href="<?php echo \esc_url( self::get_user_activity_log_url( $user_id ) ); ?>">
					<?php \esc_html_e( 'View WP Activity Logs', 'wp-security-audit-log' ); ?>
				</a>
			</p>

			<?php
		}
	}
}
