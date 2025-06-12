<?php
/**
 * Memberpress Sensor helper.
 *
 * @since     4.6.0
 *
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

use WSAL\Helpers\WP_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\MemberPress_Helper' ) ) {
	/**
	 * Helper Sensor class for Memberpress.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 *
	 * @since      4.6.0
	 */
	class MemberPress_Helper {

		/**
		 * Class cache to store the state of the plugin.
		 *
		 * @var bool
		 *
		 * @since 5.3.4.1
		 */
		private static $plugin_active = null;

		/**
		 * Class cache to store the state of the plugin for sensors.
		 *
		 * @var bool
		 *
		 * @since 5.3.4.1
		 */
		private static $plugin_active_for_sensors = null;

		/**
		 * Added our event types to the available list.
		 *
		 * @param array $types - Current event types.
		 *
		 * @return array $types - Altered list.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_memberpress_add_custom_event_type( $types ) {
			$new_types = array(
				'expired' => esc_html__( 'Expired', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$types = array_merge( $types, $new_types );

			return $types;
		}

		/**
		 * Register a custom event object within WSAL.
		 *
		 * @param array $objects array of objects current registered within WSAL.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_memberpress_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'memberpress_memberships'   => esc_html__( 'Memberships in MemberPress', 'wp-security-audit-log' ),
				'memberpress_groups'        => esc_html__( 'Groups in MemberPress', 'wp-security-audit-log' ),
				'memberpress_rules'         => esc_html__( 'Rules in MemberPress', 'wp-security-audit-log' ),
				'memberpress_settings'      => esc_html__( 'Settings in MemberPress', 'wp-security-audit-log' ),
				'memberpress_roles'         => esc_html__( 'Roles in MemberPress', 'wp-security-audit-log' ),
				'memberpress_subscriptions' => esc_html__( 'Subscriptions in MemberPress', 'wp-security-audit-log' ),
				'memberpress_transactions'  => esc_html__( 'Transactions in MemberPress', 'wp-security-audit-log' ),
				'memberpress_members'       => esc_html__( 'Members in MemberPress', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Adds new ignored CPT for our plugin.
		 *
		 * @method wsal_memberpress_extension_add_custom_event_object_text
		 *
		 * @param array $post_types An array of default post_types.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function wsal_memberpress_add_custom_ignored_cpt( $post_types ) {
			$new_post_types = array(
				'memberpressproduct',
				'memberpressgroup',
				'memberpressrule',
			);

			// combine the two arrays.
			$post_types = array_merge( $post_types, $new_post_types );

			return $post_types;
		}

		/**
		 * Ensure values are not overly lengthy.
		 *
		 * @param string $value - The value to truncate.
		 * @param string $expression - The to check for.
		 * @param int    $length - The length of the expression when truncated.
		 * @param string $ellipses_sequence - The sequence of ellipses.
		 *
		 * @return string
		 *
		 * @since 4.6.0
		 */
		public static function data_truncate( $value, $expression, $length = 100, $ellipses_sequence = '...' ) {
			$length = 200;

			switch ( $expression ) {
				case '%previous_value%':
				case '%value%':
					$value = mb_strlen( $value ) > $length ? ( mb_substr( $value, 0, $length ) . $ellipses_sequence ) : $value;

					break;
				default:
					break;
			}

			return $value;
		}

		/**
		 * Checks if the Memberpress is active.
		 *
		 * @return boolean
		 *
		 * @since 4.6.0
		 */
		public static function is_memberpress_active() {
			if ( null === self::$plugin_active ) {
				// self::$plugin_active = ( WP_Helper::is_plugin_active( 'memberpress/memberpress.php' ) );

				// if ( WP_Helper::is_multisite() ) {
					// Check if the plugin is active on the main site.
				if ( defined( 'MEPR_PLUGIN_SLUG' ) ) {
					// Plugin is enabled, run your code...
					self::$plugin_active = true;
				} else {
					self::$plugin_active = false;
				}
				// }
			}

			return self::$plugin_active;
		}

		/**
		 * Shall we load custom alerts for sensors?
		 *
		 * @return boolean
		 *
		 * @since 5.3.4.1
		 */
		public static function load_alerts_for_sensor(): bool {
			if ( null === self::$plugin_active_for_sensors ) {
				self::$plugin_active_for_sensors = ( WP_Helper::is_plugin_active( 'memberpress/memberpress.php' ) );
			}

			return self::$plugin_active_for_sensors;
		}

		/**
		 * Returns a list of custom post types associated with particular extension.
		 *
		 * @return array List of custom post types.
		 *
		 * @since 4.5.0
		 */
		public static function get_custom_post_types(): array {
			return array( 'memberpressproduct', 'memberpressgroup', 'memberpressrule' );
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'activity-log-memberpress/wsal-memberpress.php';
		}
	}
}
