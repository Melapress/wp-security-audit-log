<?php
/**
 * Custom Alerts for WP 2FA plugin.
 *
 * Class file for alert manager.
 *
 * @since   5.0.0
 *
 * @package wsal
 * @subpackage wsal-wp-2fa
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\WP_2FA_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\WP_2FA_Custom_Alerts' ) ) {

	/**
	 * Custom sensor for WP 2FA plugin.
	 *
	 * @since 5.0.0
	 */
	class WP_2FA_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array - Currentl registered alers.
		 *
		 * @since 5.0.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( WP_2FA_Helper::is_wp2fa_active() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					esc_html__( 'WP 2FA', 'wp-security-audit-log' ) => array(
						esc_html__( 'WP 2FA Settings', 'wp-security-audit-log' )    =>
						self::get_settings_changes_array(),
						esc_html__( '2FA', 'wp-security-audit-log' ) => self::get_2fa_changes_array(),
					),
				);
			}

			return array();
		}

		/**
		 * Returns array with all the events attached to the sensor (if there are different types of events, that method will merge them into one array - the events ids will be uses as keys)
		 *
		 * @return array - Modified array of alerts.
		 *
		 * @since 5.0.0
		 */
		public static function get_alerts_array(): array {
			return self::get_settings_changes_array() +
			self::get_2fa_changes_array();
		}

		/**
		 * Returns the array with 2FA changes alerts
		 *
		 * @return array - Our array of alerts.
		 *
		 * @since 5.0.0
		 */
		private static function get_2fa_changes_array(): array {
			return array(
				7808 => array(
					7808,
					WSAL_MEDIUM,
					esc_html__( 'A WP 2FA user configured a method', 'wp-security-audit-log' ),
					__( 'Configured the 2FA method %method%.', 'wp-security-audit-log' ),
					array(),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'2fa',
					'enabled',
				),
				7809 => array(
					7809,
					WSAL_MEDIUM,
					esc_html__( 'A WP 2FA user policy was enabled / disabled a method', 'wp-security-audit-log' ),
					__( 'Changed the 2FA method to %new_method%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous method', 'wp-security-audit-log' ) => '%old_method%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'2fa',
					'enabled',
				),
				7810 => array(
					7810,
					WSAL_MEDIUM,
					esc_html__( 'A WP 2FA user is no longer using 2FA', 'wp-security-audit-log' ),
					__( 'Removed the configure 2FA method. User is no longer using 2FA.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous configured 2FA method', 'wp-security-audit-log' ) => '%old_method%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'2fa',
					'disabled',
				),
				7811 => array(
					7811,
					WSAL_MEDIUM,
					esc_html__( 'A WP 2FA User has been locked for not configuring 2FA', 'wp-security-audit-log' ),
					__( 'The user has been locked by WP 2FA for not configuring 2FA. The website administrators can unlock the user from the Users page in wordPress.', 'wp-security-audit-log' ),
					array(),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'2fa',
					'blocked',
				),
				7812 => array(
					7812,
					WSAL_MEDIUM,
					esc_html__( 'A WP 2FA User has been unblocked', 'wp-security-audit-log' ),
					__( 'The user %user% which was blocked by WP 2FA was unblocked. The user can proceed to log in and configure 2FA.', 'wp-security-audit-log' ),
					array(),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'2fa',
					'unblocked',
				),
			);
		}

		/**
		 * Returns the array with 2FA settings changes alerts
		 *
		 * @return array - Our array of alerts.
		 *
		 * @since 5.0.0
		 */
		private static function get_settings_changes_array(): array {
			return array(
				7800 => array(
					7800,
					WSAL_HIGH,
					esc_html__( 'WP 2FA Enforcement policy updated', 'wp-security-audit-log' ),
					__( 'Has set the 2FA policies to %new_policy%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-2fa-settings',
					'enabled',
				),
				7801 => array(
					7801,
					WSAL_CRITICAL,
					esc_html__( 'WP 2FA enforcement policies have been disabled', 'wp-security-audit-log' ),
					__( 'The 2FA policies have been disabled so 2FA is not enforced on any user or user role. Any user can configure and use 2FA, but it is optional.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-2fa-settings',
					'disabled',
				),
				7802 => array(
					7802,
					WSAL_MEDIUM,
					esc_html__( 'WP 2FA enforcement list was modified', 'wp-security-audit-log' ),
					__( 'Changed the list of %changed_list% on which 2FA is enforced.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list', 'wp-security-audit-log' ) => '%old_list%',
						esc_html__( 'New list', 'wp-security-audit-log' ) => '%new_list%',
					),
					array(),
					'wp-2fa-settings',
					'modified',
				),
				7803 => array(
					7803,
					WSAL_MEDIUM,
					esc_html__( 'WP 2FA exclusion list was modified', 'wp-security-audit-log' ),
					__( 'Changed the list of %changed_list% on which 2FA is excluded.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list', 'wp-security-audit-log' ) => '%old_list%',
						esc_html__( 'New list', 'wp-security-audit-log' ) => '%new_list%',
					),
					array(),
					'wp-2fa-settings',
					'modified',
				),
				7804 => array(
					7804,
					WSAL_INFORMATIONAL,
					esc_html__( 'WP 2FA Enforcement policy updated', 'wp-security-audit-log' ),
					__( 'The 2FA method %method%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-2fa-settings',
					'enabled',
				),
				7805 => array(
					7805,
					WSAL_INFORMATIONAL,
					esc_html__( 'WP 2FA Trusted device was enabled / disabled', 'wp-security-audit-log' ),
					__( 'The Trusted devices (Remember this device) feature in WP 2FA.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-2fa-settings',
					'enabled',
				),
				7806 => array(
					7806,
					WSAL_INFORMATIONAL,
					esc_html__( 'WP 2FA trusted device remember length modified', 'wp-security-audit-log' ),
					__( 'The duration for how long should a trusted device be remembered.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%old_value%',
						esc_html__( 'New settings', 'wp-security-audit-log' ) => '%new_value%',
					),
					array(),
					'wp-2fa-settings',
					'modified',
				),
				7807 => array(
					7807,
					WSAL_MEDIUM,
					esc_html__( 'WP 2FA require password resets on unblock was enabled / disabled', 'wp-security-audit-log' ),
					__( 'The setting <strong>require 2FA for password resets.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-2fa-settings',
					'modified',
				),
			);
		}
	}
}
