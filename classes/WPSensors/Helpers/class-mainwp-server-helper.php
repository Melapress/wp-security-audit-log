<?php
/**
 * Gravity forms sensor helper
 *
 * @since     latest
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

use WSAL\WP_Sensors\Alerts\MainWP_Server_Custom_Alerts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\MainWP_Server_Helper' ) ) {

	/**
	 * Helper Sensor class for YOAST.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 * @since      latest
	 */
	class MainWP_Server_Helper {

		/**
		 * Register a custom event object within WSAL.
		 *
		 * @param array $objects array of objects current registered within WSAL.
		 *
		 * @since 5.0.0
		 */
		public static function wsal_mainwp_server_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'activity-logs'  => __( 'Activity Logs', 'wp-security-audit-log' ),
				'child-site'     => __( 'Child Site', 'wp-security-audit-log' ),
				'extension'      => __( 'Extension', 'wp-security-audit-log' ),
				'mainwp'         => __( 'MainWP', 'wp-security-audit-log' ),
				'uptime-monitor' => __( 'Uptime Monitor', 'wp-security-audit-log' ),
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
		 * @since 5.0.0
		 */
		public static function wsal_mainwp_server_add_custom_event_type( $types ) {
			$new_types = array(
				'failed'   => __( 'Failed', 'wp-security-audit-log' ),
				'finished' => __( 'Finished', 'wp-security-audit-log' ),
				'synced'   => __( 'Synced', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$types = array_merge( $types, $new_types );

			return $types;
		}

		public static function wsal_mainwp_server_event_store_proper_site_id( $current_site_id, $alert_id ) {
			if ( isset( MainWP_Server_Custom_Alerts::get_alerts_array()[ (int) $alert_id ] ) ) {
				$current_site_id = 0; // Meaning that this request is coming from the mainWP server itself - its wrong but lets stored that way for now i can not thing of a better solution.
			}

			return $current_site_id;
		}

		/**
		 * Sets the proper blog object if the MainWP server is active.
		 *
		 * @param object|null $blog_info - Blog object extracted from WP (if exist) or null if not.
		 * @param int         $site_id - The stored site id, could be 0 or positive.
		 * @param array       $alert - Array with the alert properties.
		 *
		 * @return null|object
		 *
		 * @since 5.0.0
		 */
		public static function wsal_mainwp_server_check_site_id_and_get_info( $blog_info, $site_id, $alert ) {
			if ( 0 === (int) $site_id ) {
				return ( new class(){
					/**
					 * If site id is 0 then that is the default name for the blog info object we return.
					 *
					 * @var string
					 *
					 * @since 5.0.0
					 */
					public $blogname = 'MainWP Server';

					/**
					 * Direct link to the MainWP server page.
					 *
					 * @var string
					 *
					 * @since 5.0.0
					 */
					public $siteurl = '';

					/**
					 * Default class constructor
					 *
					 * @since 5.0.0
					 */
					public function __construct() {
						$this->siteurl = \esc_url( admin_url( 'admin.php?page=mainwp_tab' ) );
					}
				} );
			}

			return $blog_info;
		}
	}
}
