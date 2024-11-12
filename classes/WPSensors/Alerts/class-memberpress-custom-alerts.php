<?php
/**
 * Custom Alerts for Member Press plugin.
 *
 * Class file for alert manager.
 *
 * @since   4.6.0
 *
 * @package wsal
 * @subpackage wsal-memberpress-forms
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\MemberPress_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\Memberpress_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Gravity Forms plugin.
	 *
	 * @since 4.6.0
	 */
	class Memberpress_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( MemberPress_Helper::is_memberpress_active() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					esc_html__( 'Memberpress', 'wp-security-audit-log' ) => array(
						esc_html__( 'Memberships', 'wp-security-audit-log' )      =>
						self::get_alerts_array(),
					),
				);
			}
			return array();
		}

		/**
		 * Returns array with all the events attached to the sensor (if there are different types of events, that method will merge them into one array - the events ids will be uses as keys)
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_alerts_array(): array {
			return array(
				6200 => array(
					6200,
					WSAL_HIGH,
					esc_html__( 'A membership was created, deleted or restored.', 'wp-security-audit-log' ),
					esc_html__( 'Membership name %name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Membership ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(
						esc_html__( 'View Membership', 'wp-security-audit-log' ) => '%ViewLink%',
					),
					'memberpress_memberships',
					'created',
				),
				6201 => array(
					6201,
					WSAL_HIGH,
					esc_html__( 'A membership was modified.', 'wp-security-audit-log' ),
					esc_html__( 'Membership Option was modified in %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Membership ID', 'wp-security-audit-log' ) => '%ID%',
						esc_html__( 'Option name', 'wp-security-audit-log' ) => '%option_name%',
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%previous_value%',
						esc_html__( 'New value', 'wp-security-audit-log' ) => '%value%',
					),
					array(
						esc_html__( 'View Membership', 'wp-security-audit-log' ) => '%ViewLink%',
					),
					'memberpress_memberships',
					'modified',
				),
				6202 => array(
					6202,
					WSAL_HIGH,
					esc_html__( 'A membership was permanently deleted.', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the Membership %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Membership ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(),
					'memberpress_memberships',
					'deleted',
				),
				6203 => array(
					6203,
					WSAL_HIGH,
					esc_html__( 'A group was created, deleted or restored.', 'wp-security-audit-log' ),
					esc_html__( 'Group name %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Group ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(
						esc_html__( 'View Group', 'wp-security-audit-log' ) => '%ViewLink%',
					),
					'memberpress_groups',
					'created',
				),
				6204 => array(
					6204,
					WSAL_HIGH,
					esc_html__( 'A group option was modified.', 'wp-security-audit-log' ),
					esc_html__( 'Group Option was modified in %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Group ID', 'wp-security-audit-log' ) => '%ID%',
						esc_html__( 'Option name', 'wp-security-audit-log' ) => '%option_name%',
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%previous_value%',
						esc_html__( 'New value', 'wp-security-audit-log' ) => '%value%',
					),
					array(
						esc_html__( 'View  Group', 'wp-security-audit-log' ) => '%ViewLink%',
					),
					'memberpress_groups',
					'modified',
				),
				6205 => array(
					6205,
					WSAL_HIGH,
					esc_html__( 'A group was permanently deleted.', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the Group %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Group ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(),
					'memberpress_groups',
					'deleted',
				),
				6206 => array(
					6206,
					WSAL_HIGH,
					esc_html__( 'A rule was created, deleted or restored.', 'wp-security-audit-log' ),
					esc_html__( 'Rule %name% .', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Rule ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(
						esc_html__( 'View Rule', 'wp-security-audit-log' ) => '%ViewLink%',
					),
					'memberpress_rules',
					'created',
				),
				6207 => array(
					6207,
					WSAL_HIGH,
					esc_html__( 'A rule option was modified.', 'wp-security-audit-log' ),
					esc_html__( 'Rule Option was modified in %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Rule ID', 'wp-security-audit-log' ) => '%ID%',
						esc_html__( 'Option name', 'wp-security-audit-log' ) => '%option_name%',
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%previous_value%',
						esc_html__( 'New value', 'wp-security-audit-log' ) => '%value%',
					),
					array(
						esc_html__( 'View Rule', 'wp-security-audit-log' ) => '%ViewLink%',
					),
					'memberpress_rules',
					'modified',
				),
				6208 => array(
					6208,
					WSAL_HIGH,
					esc_html__( 'A rule was permanently deleted.', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the Rule %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Rule ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(),
					'memberpress_rules',
					'deleted',
				),
				6210 => array(
					6210,
					WSAL_HIGH,
					esc_html__( 'A setting was modified.', 'wp-security-audit-log' ),
					esc_html__( 'Setting %setting_name% was modified.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%previous_value%',
						esc_html__( 'New value', 'wp-security-audit-log' ) => '%value%',
					),
					array(),
					'memberpress_settings',
					'modified',
				),

				6211 => array(
					6211,
					WSAL_HIGH,
					esc_html__( 'A role was created, modified or deleted.', 'wp-security-audit-log' ),
					esc_html__( 'Role name: %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(
						esc_html__( 'View role', 'wp-security-audit-log' ) => '%RoleLink%',
					),
					'memberpress_roles',
					'modified',
				),
				6212 => array(
					6212,
					WSAL_HIGH,
					esc_html__( 'A role was modified.', 'wp-security-audit-log' ),
					esc_html__( 'Role name: %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(
						esc_html__( 'View role', 'wp-security-audit-log' ) => '%RoleLink%',
					),
					'memberpress_roles',
					'created',
				),

				6250 => array(
					6250,
					WSAL_HIGH,
					esc_html__( 'A subscription was created, cancelled or deleted.', 'wp-security-audit-log' ),
					esc_html__( 'Subscription number: %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Subscription ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(
						esc_html__( 'View Subscription', 'wp-security-audit-log' ) => '%SubscriptionLink%',
					),
					'memberpress_subscriptions',
					'created',
				),
				6251 => array(
					6251,
					WSAL_HIGH,
					esc_html__( 'A subscription number was modified.', 'wp-security-audit-log' ),
					esc_html__( 'Made changes to the subscription number %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Subscription ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(
						esc_html__( 'View Subscription', 'wp-security-audit-log' ) => '%SubscriptionLink%',
					),
					'memberpress_subscriptions',
					'modified',
				),
				6252 => array(
					6252,
					WSAL_HIGH,
					esc_html__( 'A subscription was expired.', 'wp-security-audit-log' ),
					esc_html__( 'Subscription number: %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Subscription ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(
						esc_html__( 'View Subscription', 'wp-security-audit-log' ) => '%SubscriptionLink%',
					),
					'memberpress_subscriptions',
					'expired',
				),
				6253 => array(
					6253,
					WSAL_HIGH,
					esc_html__( 'A transaction was created or deleted.', 'wp-security-audit-log' ),
					esc_html__( 'Transaction number: %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Transaction ID', 'wp-security-audit-log' ) => '%ID%',
					),
					array(
						esc_html__( 'View Transaction', 'wp-security-audit-log' ) => '%TransactionLink%',
					),
					'memberpress_transactions',
					'modified',
				),
				6254 => array(
					6254,
					WSAL_HIGH,
					esc_html__( 'A transaction was modified.', 'wp-security-audit-log' ),
					esc_html__( 'Transaction number: %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Transaction ID', 'wp-security-audit-log' ) => '%ID%',
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%previous_value%',
						esc_html__( 'New value', 'wp-security-audit-log' ) => '%value%',
					),
					array(
						esc_html__( 'View Transaction', 'wp-security-audit-log' ) => '%TransactionLink%',
					),
					'memberpress_transactions',
					'created',
				),
				6255 => array(
					6255,
					WSAL_HIGH,
					esc_html__( 'A member transaction was created or deleted.', 'wp-security-audit-log' ),
					esc_html__( 'Made changes to the transaction number %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Transaction ID', 'wp-security-audit-log' ) => '%ID%',
						esc_html__( 'Membership', 'wp-security-audit-log' ) => '%membershipname%',
					),
					array(
						esc_html__( 'View Members profile page', 'wp-security-audit-log' ) => '%MemberLink%',
					),
					'memberpress_members',
					'created',
				),
			);
		}
	}
}
