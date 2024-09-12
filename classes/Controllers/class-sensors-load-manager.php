<?php
/**
 * Controller: Sensors Load Manager
 *
 * @since     4.6.0
 * @package   wsal
 *
 * @subpackage controllers
 */

declare(strict_types=1);

namespace WSAL\Controllers;

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\Classes_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Controllers\Sensors_Load_Manager' ) ) {

	/**
	 * Responsible for loading Sensors
	 *
	 * @since 4.6.0
	 */
	class Sensors_Load_Manager {

		// WSAL views array.
		const WSAL_VIEWS = array(
			'wsal-auditlog',
			'wsal-togglealerts',
			'wsal-settings',
			'wsal-emailnotifications',
			'wsal-loginusers',
			'wsal-reports',
			'wsal-search',
			'wsal-externaldb',
			'wsal-user-management-views',
			'wsal-rep-views-main',
			'wsal-np-notifications',
			'wsal-np-addnotification',
			'wsal-np-editnotification',
			'wsal-ext-settings',
			'wsal-help',
			'wsal-auditlog-account',
			'wsal-auditlog-contact',
			'wsal-auditlog-pricing',
		);

		/**
		 * Cached sensors array
		 *
		 * @var array
		 *
		 * @since 5.1.1
		 */
		public static $sensors = array();

		/**
		 * Some of the sensors need to load data / attach events earlier - lets give them a chance
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function load_early_sensors() {

			$sensors = self::get_sensors();

			foreach ( $sensors as $sensor ) {
				if ( method_exists( $sensor, 'early_init' ) ) {
					call_user_func_array( array( $sensor, 'early_init' ), array() );
				}
			}
		}

		/**
		 * Loads all the sensors
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function load_sensors() {

			if ( is_admin() ) {
				global $pagenow;
				// Get current page query argument via $_GET array.
				$current_page = isset( $_GET['page'] ) ? \sanitize_text_field( \wp_unslash( $_GET['page'] ) ) : false;

				// Check these conditions before loading sensors.
				if ( $current_page && (
				in_array( $current_page, self::WSAL_VIEWS, true ) // WSAL Views.
				|| 'index.php' === $pagenow  // Dashboard.
				|| 'tools.php' === $pagenow  // Tools page.
				|| 'export.php' === $pagenow // Export page.
				|| 'import.php' === $pagenow // Import page.
				)
				) {
					return;
				}
			}

			$sensors = self::get_sensors();

			\do_action( 'wsal_sensors_manager_add' );

			$plugin_sensors = Classes_Helper::get_classes_by_namespace( '\WSAL\Plugin_Sensors' );

			if ( ! empty( $plugin_sensors ) ) {
				$sensors = \array_merge( $sensors, $plugin_sensors );
			}

			if ( WP_Helper::is_login_screen() && ! \is_user_logged_in() ) {
				// Here we need to load only the Sensors which are login enabled.
				foreach ( $sensors as $key => &$sensor ) {
					// Check if that sensor is for login or not.
					if ( method_exists( $sensor, 'is_login_sensor' ) ) {
						$is_login_sensor = call_user_func_array( array( $sensor, 'is_login_sensor' ), array() );

						if ( ! $is_login_sensor ) {
							unset( $sensors[ $key ] );
						}
					} else {
						unset( $sensors[ $key ] );
					}
				}
				unset( $sensor );

				/**
				 * WSAL Filter: `wsal_load_login_sensors`
				 *
				 * Filter for the list of sensors to be loaded for visitors
				 * or public. No sensor is allowed to load on the front-end
				 * except the ones in this array.
				 *
				 * @since 4.5.0
				 *
				 * @param array $sensors - List of sensors to be loaded for visitors.
				 */
				$sensors = \apply_filters( 'wsal_load_login_sensors', $sensors );
			} else {
				// Load all the frontend sensors.
				if ( \WpSecurityAuditLog::is_frontend() && ! \is_user_logged_in() ) {
					// Here we need to load only the Sensors which are frontend enabled.
					foreach ( $sensors as $key => &$sensor ) {
						// Check if that sensor is for frontend or not.
						if ( method_exists( $sensor, 'is_frontend_sensor' ) ) {
							$is_frontend_sensor = call_user_func_array( array( $sensor, 'is_frontend_sensor' ), array() );

							if ( ! $is_frontend_sensor ) {
								unset( $sensors[ $key ] );
							}
						} else {
							unset( $sensors[ $key ] );
						}
					}
					unset( $sensor );

					/**
					 * WSAL Filter: `wsal_load_frontend_sensors`
					 *
					 * Filter for the list of sensors to be loaded for visitors
					 * or public. No sensor is allowed to load on the front-end
					 * except the ones in this array.
					 *
					 * @since 4.5.0
					 *
					 * @param array $sensors - List of sensors to be loaded for visitors.
					 */
					$sensors = \apply_filters( 'wsal_load_frontend_sensors', $sensors );
				}
				// If we are on some frontend page, we don't want to load the sensors.
				if ( ! \WpSecurityAuditLog::is_frontend() ) {
					// Not a frontend page? Let remove the ones which are not frontend enabled.
					foreach ( $sensors as $key => &$sensor ) {
						// Check if that sensor is for frontend only or not.
						if ( method_exists( $sensor, 'is_frontend_only_sensor' ) ) {
							$is_frontend_only_sensor = call_user_func_array( array( $sensor, 'is_frontend_only_sensor' ), array() );

							if ( $is_frontend_only_sensor ) {
								unset( $sensors[ $key ] );
							}
						}
					}
					unset( $sensor );
				}
			}

			foreach ( $sensors as $sensor ) {
				if ( method_exists( $sensor, 'init' ) ) {
					call_user_func_array( array( $sensor, 'init' ), array() );
				}
			}
		}

		/**
		 * Caches the sensors classes
		 *
		 * @return array
		 *
		 * @since 5.1.1
		 */
		public static function get_sensors(): array {
			if ( empty( self::$sensors ) ) {
				self::$sensors = Classes_Helper::get_classes_by_namespace( '\WSAL\WP_Sensors' );
			}

			return self::$sensors;
		}
	}
}
