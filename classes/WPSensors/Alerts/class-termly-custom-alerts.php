<?php
/**
 * Custom Alerts for Termly plugin.
 *
 * Class file for alert manager.
 *
 * @since 5.4.0
 *
 * @package wsal
 * @subpackage wsal-termly
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\Termly_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\Termly_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Termly plugin.
	 *
	 * @since 5.4.0
	 */
	class Termly_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 5.4.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( \method_exists( Termly_Helper::class, 'load_alerts_for_sensor' ) && Termly_Helper::load_alerts_for_sensor() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					__( 'Termly', 'wp-security-audit-log' ) => array(
						__( 'Monitor termly', 'wp-security-audit-log' ) =>
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
		 * @since 5.4.0
		 */
		public static function get_alerts_array(): array {
			return array(

				10901 => array(
					10901,
					WSAL_HIGH,
					__( 'Disconnected the site from Termly account.', 'wp-security-audit-log' ),
					__( 'Disconnected the site from <strong>Termly</strong> account.', 'wp-security-audit-log' ),
					array(),
					array(),
					'termly',
					'deactivated',
				),

				10902 => array(
					10902,
					WSAL_LOW,
					__( 'Changed the status of the Scheduled Automatic Scans.', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Scheduled Automatic Scans</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'termly',
					'disabled',
				),

				10903 => array(
					10903,
					WSAL_MEDIUM,
					__( 'Changed the status of the setting Add Termly Scanner to robotx.txt Allow list.', 'wp-security-audit-log' ),
					__( 'Changed the status of the setting <strong>Add Termly Scanner to robotx.txt Allow list</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'termly',
					'disabled',
				),

				10904 => array(
					10904,
					\WSAL_HIGH,
					__( 'Changed the status of the Consent banner.', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Consent banner</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'termly',
					'disabled',
				),

				10905 => array(
					10905,
					\WSAL_HIGH,
					__( 'Changed the status of the Auto Blocker in the Banner settings.', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Auto Blocker<strong> in the <strong>Banner settings</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'termly',
					'disabled',
				),

				10911 => array(
					10911,
					\WSAL_HIGH,
					__( 'Activated a new API key and connected the site to a Termly account.', 'wp-security-audit-log' ),
					__( 'Activated a new API key and connected the site to a <strong>Termly</strong> account.', 'wp-security-audit-log' ),
					array(),
					array(),
					'termly',
					'activated',
				),
			);
		}
	}
}
