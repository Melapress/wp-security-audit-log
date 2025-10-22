<?php
/**
 * Custom Alerts for Paid Memberships Pro
 *
 * Class file for alert manager.
 *
 * @since   5.5.2
 *
 * @package wsal
 * @subpackage wsal-paid-memberships-pro
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\Paid_Memberships_Pro_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\Paid_Memberships_Pro_Custom_Alerts' ) ) {
	/**
	 * Custom Alerts for Paid Memberships Pro
	 *
	 * @since 5.5.2
	 */
	class Paid_Memberships_Pro_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for this plugin extension.
		 *
		 * @return array
		 *
		 * @since 5.5.2
		 */
		public static function get_custom_alerts(): array {

			if ( ( \method_exists( Paid_Memberships_Pro_Helper::class, 'load_alerts_for_sensor' ) && Paid_Memberships_Pro_Helper::load_alerts_for_sensor() ) || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					esc_html__( 'Paid Memberships Pro', 'wp-security-audit-log' ) => array(
						esc_html__( 'Paid Memberships Pro', 'wp-security-audit-log' ) =>
						self::get_alerts_array(),
					),
				);
			}

			return array();
		}

		/**
		 * Returns array with all the events attached to the sensor. If there are different types of events, this method will merge them into one array.
		 * The events ids will be used as keys.
		 *
		 * @return array
		 *
		 * @since 5.5.2
		 */
		public static function get_alerts_array(): array {
			return array(
				9501 => array(
					9501,
					WSAL_MEDIUM,
					esc_html__( 'A membership level was created', 'wp-security-audit-log' ),
					esc_html__( 'Membership level %LevelName% was created.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Membership ID', 'wp-security-audit-log' )      => '%MembershipID%',
						esc_html__( 'Membership Billing', 'wp-security-audit-log' ) => '%MembershipCostText%',
						esc_html__( 'Membership Expires', 'wp-security-audit-log' ) => '%ExpirationText%',
					),
					array(
						esc_html__( 'View Membership', 'wp-security-audit-log' )    => '%ViewLink%',
					),
					'pmpro_membership_levels',
					'created',
				),
				9502 => array(
					9502,
					WSAL_HIGH,
					esc_html__( 'A membership level was permanently deleted.', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the membership level: %LevelName%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Membership ID', 'wp-security-audit-log' ) => '%MembershipID%',
					),
					array(),
					'pmpro_membership_levels',
					'deleted',
				),
				9504 => array(
					9504,
					WSAL_MEDIUM,
					esc_html__( 'A membership level was assigned to a user.', 'wp-security-audit-log' ),
					esc_html__( 'Membership level %LevelName% was assigned to user %UserName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'User ID', 'wp-security-audit-log' ) => '%UserID%',
						esc_html__( 'Level ID', 'wp-security-audit-log' ) => '%LevelId%',
						esc_html__( 'Email', 'wp-security-audit-log' ) => '%Email%',
						esc_html__( 'First Name', 'wp-security-audit-log' ) => '%FirstName%',
						esc_html__( 'Last Name', 'wp-security-audit-log' ) => '%LastName%',
						esc_html__( 'Role', 'wp-security-audit-log' ) => '%Role%',
					),
					array(
						esc_html__( 'View Member', 'wp-security-audit-log' ) => '%ViewMember%',
					),
					'pmpro_members',
					'assigned',
				),
				9505 => array(
					9505,
					WSAL_MEDIUM,
					esc_html__( 'A membership level was removed from a user.', 'wp-security-audit-log' ),
					esc_html__( 'Membership level %LevelName% was removed from user %UserName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'User ID', 'wp-security-audit-log' ) => '%UserID%',
						esc_html__( 'Level ID', 'wp-security-audit-log' ) => '%LevelID%',
						esc_html__( 'Email', 'wp-security-audit-log' ) => '%Email%',
						esc_html__( 'First Name', 'wp-security-audit-log' ) => '%FirstName%',
						esc_html__( 'Last Name', 'wp-security-audit-log' ) => '%LastName%',
						esc_html__( 'Role', 'wp-security-audit-log' ) => '%Role%',
					),
					array(
						esc_html__( 'View Member', 'wp-security-audit-log' ) => '%ViewMember',
					),
					'pmpro_members',
					'removed',
				),
			);
		}
	}
}
