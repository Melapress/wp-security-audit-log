<?php
/**
 * MainWP settings for addon.
 *
 * @package    wsal
 * @subpackage mainwp
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 *
 * @since 5.0.0
 */

namespace WSAL\MainWP;

use WSAL\Helpers\Validator;
use WSAL\Helpers\User_Helper;
use WSAL\Helpers\Settings_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * MainWP settings Helper class
 *
 * @since 5.0.0
 */
if ( ! class_exists( '\WSAL\MainWP\MainWP_Settings' ) ) {

	/**
	 * Put all you migration methods here
	 *
	 * @package WSAL\MainWP
	 *
	 * @since 5.0.0
	 */
	class MainWP_Settings {

		public const MWPAL_OPT_PREFIX = 'mwpal-';

		/**
		 * Get MainWP Child Sites.
		 *
		 * @return array
		 */
		public static function get_mwp_child_sites() {
			return \apply_filters( 'mainwp_getsites', WSAL_BASE_FILE_NAME, MainWP_Addon::get_child_key(), null );
		}

		/**
		 * Get MainWP child site by site ID.
		 *
		 * @param int $site_id Site ID.
		 *
		 * @return array|null
		 */
		public static function get_mwp_child_site_by_id( $site_id ) {
			// Get MainWP child sites.
			$mwp_sites = self::get_mwp_child_sites();

			// Search for the site data.
			$key = array_search( $site_id, array_column( $mwp_sites, 'id' ), false );

			if ( false !== $key && isset( $mwp_sites[ $key ] ) ) {
				return $mwp_sites[ $key ];
			}

			return null;
		}

		/**
		 * Return Site ID.
		 *
		 * @return integer
		 *
		 * @since 5.0.0
		 */
		public static function get_view_site_id() {
			$site_id = isset( $_REQUEST['mwpal-site-id'] ) ? sanitize_text_field( \wp_unslash( $_REQUEST['mwpal-site-id'] ) ) : -1; // Site ID.

			if ( -1 === $site_id ) {
				$site_id = isset( $_REQUEST['wsal-cbid'] ) ? sanitize_text_field( \wp_unslash( $_REQUEST['wsal-cbid'] ) ) : -1; // Site ID.
			}

			if ( 'dashboard' !== $site_id ) {
				return (int) ( (int) $site_id );
			}

			return $site_id;
		}

		/**
		 * Get number of child site events.
		 *
		 * @return integer
		 *
		 * @since 5.0.0
		 */
		public static function get_child_site_events() {
			return Settings_Helper::get_option_value( 'child_site_events', 300 );
		}

		/**
		 * Get Current User Roles.
		 *
		 * @param array $base_roles – Base roles.
		 * @return array
		 */
		public function get_current_user_roles( $base_roles = null ) {
			if ( null === $base_roles ) {
				$base_roles = User_Helper::get_current_user()->roles;
			}
			if ( function_exists( 'is_super_admin' ) && is_super_admin() ) {
				$base_roles[] = 'superadmin';
			}
			return $base_roles;
		}

		/**
		 * Get Server IP.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_server_ip() {
			return self::retrieve_ip_address_for( 'SERVER_ADDR' );
		}

		/**
		 * Get Client IP.
		 *
		 * @return string
		 */
		public static function get_main_client_ip() {
			return self::retrieve_ip_address_for( 'REMOTE_ADDR' );
		}

		/**
		 * Retrieves ip address from global server array based on string value.
		 *
		 * @param string $which - String value to retrieve from server array.
		 *
		 * @return null|string
		 *
		 * @since 5.0.0
		 */
		private static function retrieve_ip_address_for( string $which ) {
			$result = null;
			if ( isset( $_SERVER[ $which ] ) ) {
				$result = Settings_Helper::normalize_ip( sanitize_text_field( wp_unslash( $_SERVER[ $which ] ) ) );
				if ( ! Validator::validate_ip( $result ) ) {
					$result = 'Error : Invalid IP Address';
				}
			}

			return $result;
		}

		/**
		 * Search & Return MainWP site.
		 *
		 * @param string $value – Column value.
		 * @param string $column – Column name.
		 *
		 * @return mixed
		 */
		public static function get_mwp_site_by( $value, $column = 'id' ) {
			// Get MainWP sites.
			$mwp_sites = self::get_mwp_child_sites();

			// Search by column name.
			$key = array_search( $value, array_column( $mwp_sites, $column ), true );
			if ( false !== $key ) {
				return $mwp_sites[ $key ];
			}
			return false;
		}
	}
}
