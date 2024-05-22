<?php
/**
 * MainWP API addon.
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

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\User_Utils;
use WSAL\Entities\Base_Fields;
use WSAL\Controllers\Alert_Manager;
use WSAL\Entities\Occurrences_Entity;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Migration class
 */
if ( ! class_exists( '\WSAL\MainWP\MainWP_API' ) ) {

	/**
	 * Responsible for all the mainWP operations.
	 *
	 * @package WSAL\MainWP
	 *
	 * @since 5.0.0
	 */
	class MainWP_API {

		/**
		 * MainWP API Handler.
		 *
		 * @param array $info      – Information to return.
		 * @param array $post_data – Post data array from MainWP.
		 *
		 * @return mixed
		 *
		 * @since 5.0.0
		 */
		public static function retrieve_info_call_back( $info, $post_data ) {
			if ( isset( $post_data['action'] ) ) {
				switch ( $post_data['action'] ) {
					case 'check_wsal':
						return self::handle_wsal_info_check();

					case 'get_events':
						$limit  = isset( $post_data['events_count'] ) ? $post_data['events_count'] : false;
						$offset = isset( $post_data['events_offset'] ) ? $post_data['events_offset'] : false;
						return self::get_events_data( $limit, $offset, $post_data );

					case 'wsal_get_roles':
						return self::get_roles_data( $post_data );

					default:
						break;
				}
			}

			return $info;
		}

		/**
		 * Handles API call requesting info about WSAL plugin.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		protected static function handle_wsal_info_check(): array {
			$info                   = array();
			$info['wsal_installed'] = true;
			$info['is_premium']     = false;
			return $info;
		}

		/**
		 * Collects all the available roles and returns them.
		 *
		 * @param array $query_args - The passed query arguments (not in use currently).
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_roles_data( $query_args = array() ): array {

			$roles = WP_Helper::get_translated_roles();

			return $roles;
		}

		/**
		 * Return alerts for MainWP Extension.
		 *
		 * @param integer  $limit      - Number of alerts to retrieve.
		 * @param int|bool $offset     - Events offset, otherwise false.
		 * @param array    $query_args - Events query arguments, otherwise false.
		 *
		 * @return stdClass
		 *
		 * @since 5.0.0
		 */
		public static function get_events_data( $limit = 100, $offset = false, $query_args = array() ) {
			$mwp_events = array();

			// Check if limit is not empty.
			if ( empty( $limit ) ) {
				return $mwp_events;
			}

			$extra         = '';
			$search_string = '';

			if ( isset( $query_args['site_url'] ) && WP_Helper::is_multisite() ) {

				$sites_urls = WP_Helper::get_site_urls();

				if ( ! empty( $sites_urls ) && isset( $sites_urls[ $query_args['site_url'] ] ) ) {
					$site_id = $sites_urls[ $query_args['site_url'] ];

					$search_string .= ' site_id:' . (int) $site_id;
				}
			}

			$records = $limit;

			$where_clause = Base_Fields::string_to_search( $search_string );

			if ( '' !== trim( $where_clause ) ) {
				$extra = ' AND ' . $where_clause;
			}

			if ( isset( $query_args['newer_than'] ) ) {
				$extra = ' AND created_on > ' . $query_args['newer_than'] . $extra;
			}

			if ( isset( $query_args['older_than'] ) ) {
				$extra = ' AND created_on < ' . $query_args['older_than'] . $extra;
			}

			$events = Occurrences_Entity::load_array( '%d', array( 1 ), null, $extra . ' ORDER BY site_id, created_on DESC LIMIT ' . $records );

			$events = Occurrences_Entity::get_multi_meta_array( $events );

			if ( ! empty( $events ) && is_array( $events ) ) {
				foreach ( $events as &$event ) {
					// Get event meta.
					$event['meta_values']['UserData'] = Alert_Manager::get_event_user_data( User_Utils::get_username( $event['meta_values'] ) );
					$event['meta_data']               = $event['meta_values'];
					unset( $event['meta_values'] );
				}
				unset( $event );
			}

			return $events;
		}
	}
}
