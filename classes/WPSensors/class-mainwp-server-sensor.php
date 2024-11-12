<?php
/**
 * Sensor Class: MainWP Server
 *
 * MainWP sensor class file of the extension.
 *
 * @package wsal
 * @since 5.0.0
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\User_Helper;
use WSAL\MainWP\MainWP_Addon;
use WSAL\MainWP\MainWP_Helper;
use WSAL\MainWP\MainWP_Settings;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\MainWP_Server_Sensor' ) ) {

	/**
	 * MainWP Sensor Class.
	 *
	 * 7700 Critical    User added the child site
	 * 7701 Critical    User removed the child site
	 * 7702 Medium  User edited the child site
	 * 7703 Informational   User synced data with the child site
	 * 7704 Informational   User synced data with all the child sites
	 * 7705 Critical    User installed the extension
	 * 7706 High    User activated the extension
	 * 7707 High    User deactivated the extension
	 * 7708 Critical    User uninstalled the extension
	 * 7709 Informational   User added/removed extension to/from the menu
	 * 7710 Low Extension failed to retrieve the activity log of a child site
	 * 7711 Informational   Extension started retrieving activity logs from the child sites
	 * 7712 Informational   Extension is ready retrieving activity logs from the child sites
	 * 7713 Medium  Changed the enforcement settings of the Child sites activity log settings
	 * 7714 Medium  Added or removed a child site from the Child sites activity log settings
	 * 7715 Medium  Modified the Child sites activity log settings that are propagated to the child sites
	 * 7716 Medium  Started or finished propagating the configured Child sites activity log settings to the child sites
	 * 7717 High    The propagation of the Child sites activity log settings failed on a child site site
	 * 7750 Informational   User added a monitor for site
	 * 7751 Medium  User deleted a monitor for site
	 * 7752 Informational   User started the monitor for the site
	 * 7753 Medium  User stopped the monitor for the site
	 * 7754 Informational   User created monitors for all child sites
	 * 7700
	 * 7701
	 * 7702
	 * 7703
	 * 7704
	 * 7705
	 * 7706
	 * 7707
	 * 7708
	 * 7709
	 * 7750
	 * 7751
	 * 7752
	 * 7753
	 * 7754
	 */
	class MainWP_Server_Sensor {

		/**
		 * Current User Object.
		 *
		 * @var WP_User
		 */
		private static $current_user = null;

		/**
		 * Hook Events
		 *
		 * Listening to events using hooks.
		 *
		 * @since 5.0.0
		 */
		public static function init() {
			if ( MainWP_Addon::check_mainwp_plugin_active() ) {

				\add_action( 'clear_auth_cookie', array( __CLASS__, 'get_current_user' ), 10 );
				\add_action( 'mainwp_added_new_site', array( __CLASS__, 'site_added' ), 10, 1 ); // Site added.
				\add_action( 'mainwp_delete_site', array( __CLASS__, 'site_removed' ), 10, 1 ); // Site removed.
				\add_action( 'mainwp_update_site', array( __CLASS__, 'site_edited' ), 10, 1 ); // Site edited.
				\add_action( 'mainwp_site_synced', array( __CLASS__, 'site_synced' ), 10, 1 ); // Site synced.
				\add_action( 'mainwp_synced_all_sites', array( __CLASS__, 'synced_all_sites' ) ); // All sites synced.
				\add_action( 'mainwp_added_extension_menu', array( __CLASS__, 'added_extension_menu' ), 10, 1 ); // Extension added to MainWP menu.
				\add_action( 'mainwp_removed_extension_menu', array( __CLASS__, 'removed_extension_menu' ), 10, 1 ); // Extension removed from MainWP menu.
				\add_action( 'activated_plugin', array( __CLASS__, 'mwp_extension_activated' ), 10, 2 );
				\add_action( 'deactivated_plugin', array( __CLASS__, 'mwp_extension_deactivated' ), 10, 2 );
				\add_action( 'deleted_plugin', array( __CLASS__, 'mwp_extension_deleted' ), 10, 2 );
				\add_filter( 'upgrader_post_install', array( __CLASS__, 'mwp_extension_installed' ), 10, 3 );

				// Check if Advanced Uptime Monitor Extension is active.
				if ( is_plugin_active( 'advanced-uptime-monitor-extension/advanced-uptime-monitor-extension.php' ) ) {
					add_action( 'mainwp_aum_monitor_created', array( __CLASS__, 'aum_monitor_created' ), 10, 1 );
					add_action( 'mainwp_aum_monitor_deleted', array( __CLASS__, 'aum_monitor_deleted' ), 10, 1 );
					add_action( 'mainwp_aum_monitor_started', array( __CLASS__, 'aum_monitor_started' ), 10, 1 );
					add_action( 'mainwp_aum_monitor_paused', array( __CLASS__, 'aum_monitor_paused' ), 10, 1 );
					add_action( 'mainwp_aum_auto_add_sites', array( __CLASS__, 'aum_monitor_auto_add' ), 10, 1 );
				}
			}
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function early_init() {
			add_filter(
				'wsal_event_objects',
				array( '\WSAL\WP_Sensors\Helpers\MainWP_Server_Helper', 'wsal_mainwp_server_add_custom_event_objects' ),
				10,
				2
			);
			if ( MainWP_Addon::check_mainwp_plugin_active() ) {
				add_filter(
					'wsal_event_type_data',
					array( '\WSAL\WP_Sensors\Helpers\MainWP_Server_Helper', 'wsal_mainwp_server_add_custom_event_type' ),
					10,
					2
				);
				add_filter(
					'wsal_database_site_id_value',
					array( '\WSAL\WP_Sensors\Helpers\MainWP_Server_Helper', 'wsal_mainwp_server_event_store_proper_site_id' ),
					10,
					2
				);
				add_filter(
					'wsal_get_site_details',
					array( '\WSAL\WP_Sensors\Helpers\MainWP_Server_Helper', 'wsal_mainwp_server_check_site_id_and_get_info' ),
					10,
					3
				);
			}
		}

		/**
		 * Sets current user.
		 *
		 * @since 5.0.0
		 */
		public static function get_current_user() {
			self::$current_user = User_Helper::get_current_user();
		}

		/**
		 * MainWP Site Added
		 *
		 * Site added to MainWP dashboard.
		 *
		 * @param int $new_site_id – New site id.
		 *
		 * @since 5.0.0
		 */
		public static function site_added( $new_site_id ) {
			if ( empty( $new_site_id ) ) {
				return;
			}

			$new_site = MainWP_Settings::get_mwp_child_site_by_id( $new_site_id );
			if ( null !== $new_site ) {
				Alert_Manager::trigger_event(
					7700,
					array(
						'friendly_name' => $new_site['name'],
						'site_url'      => $new_site['url'],
						'site_id'       => $new_site['id'],
						'mainwp_dash'   => true,
					)
				);

				Settings_Helper::delete_option_value( 'wsal-child-sites' );
			}
		}

		/**
		 * MainWP Site Removed
		 *
		 * Site removed from MainWP dashboard.
		 *
		 * @param stdClass $website – Removed website.
		 *
		 * @since 5.0.0
		 */
		public static function site_removed( $website ) {
			if ( empty( $website ) ) {
				return;
			}

			if ( isset( $website->name ) ) {
				Alert_Manager::trigger_event(
					7701,
					array(
						'friendly_name' => $website->name,
						'site_url'      => $website->url,
						'site_id'       => $website->id,
						'mainwp_dash'   => true,
					)
				);

				Settings_Helper::delete_option_value( 'wsal-child-sites' );
			}
		}

		/**
		 * MainWP Site Edited
		 *
		 * Site edited from MainWP dashboard.
		 *
		 * @param int $site_id – Site id.
		 *
		 * @since 5.0.0
		 */
		public static function site_edited( $site_id ) {
			if ( empty( $site_id ) ) {
				return;
			}

			// Get MainWP child sites.
			$mwp_sites = MainWP_Settings::get_mwp_child_sites();

			// Search for the site data.
			$key = array_search( $site_id, array_column( $mwp_sites, 'id' ), false );

			if ( false !== $key && isset( $mwp_sites[ $key ] ) ) {
				Alert_Manager::trigger_event(
					7702,
					array(
						'friendly_name' => $mwp_sites[ $key ]['name'],
						'site_url'      => $mwp_sites[ $key ]['url'],
						'site_id'       => $mwp_sites[ $key ]['id'],
						'mainwp_dash'   => true,
					)
				);
			}
		}

		/**
		 * MainWP Site Synced
		 *
		 * Site synced from MainWP dashboard.
		 *
		 * @param stdClass $website – Removed website.
		 *
		 * @since 5.0.0
		 */
		public static function site_synced( $website ) {
			if ( empty( $website ) ) {
				return;
			}

			$is_global_sync = isset( $_POST['isGlobalSync'] ) ? sanitize_text_field( wp_unslash( $_POST['isGlobalSync'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( property_exists( $website, 'id' ) ) {
				MainWP_Helper::sync_site( (int) $website->id );
			}

			if ( 'true' === $is_global_sync ) { // Check if not global sync.
				return;
			}

			if ( isset( $website->name ) ) {
				Alert_Manager::trigger_event(
					7703,
					array(
						'friendly_name' => $website->name,
						'site_url'      => $website->url,
						'SiteID'       => $website->id,
						'mainwp_dash'   => true,
					)
				);
			}
		}

		/**
		 * MainWP Sites Synced
		 *
		 * Log event when MainWP sites are synced altogether.
		 *
		 * @since 5.0.0
		 */
		public static function synced_all_sites() {
			$is_global_sync = isset( $_POST['isGlobalSync'] ) ? sanitize_text_field( wp_unslash( $_POST['isGlobalSync'] ) ) : false;

			// make sure this is global sync.
			if ( ! in_array( $is_global_sync, array( 'true', '1' ) ) ) {
				return;
			}

			MainWP_Helper::retrieve_events_manually();

			// Trigger global sync event.
			Alert_Manager::trigger_event(
				7704,
				array( 'mainwp_dash' => true )
			);
		}

		/**
		 * MainWP Extension Added
		 *
		 * MainWP extension added to menu.
		 *
		 * @param string $slug – Extension slug.
		 *
		 * @since 5.0.0
		 */
		public static function added_extension_menu( $slug ) {
			self::extension_menu_edited( $slug, 'Added' );
		}

		/**
		 * MainWP Extension Removed
		 *
		 * MainWP extension removed from menu.
		 *
		 * @param string $slug – Extension slug.
		 *
		 * @since 5.0.0
		 */
		public static function removed_extension_menu( $slug ) {
			self::extension_menu_edited( $slug, 'Removed' );
		}

		/**
		 * MainWP Menu Edited
		 *
		 * Extension added/removed from MainWP menu.
		 *
		 * @param string $slug   – Slug of the extension.
		 * @param string $action – Added or Removed action.
		 *
		 * @since 5.0.0
		 */
		public static function extension_menu_edited( $slug, $action ) {
			// Check if the slug is not empty and it is active.
			if ( ! empty( $slug ) && \is_plugin_active( $slug ) ) {
				Alert_Manager::trigger_event(
					7709,
					array(
						'mainwp_dash' => true,
						'extension'   => $slug,
						'action'      => $action,
						'option'      => 'Added' === $action ? 'to' : 'from',
						'EventType'   => $action,
					)
				);
			}
		}

		/**
		 * MainWP Extension Activated
		 *
		 * @param bool  $response   Installation response.
		 * @param array $hook_extra Extra arguments passed to hooked filters.
		 * @param array $result     Installation result data.
		 *
		 * @since 5.0.0
		 */
		public static function mwp_extension_installed( $response, $hook_extra, $result ) {
			self::extension_log_event( 7705, $result['destination_name'] );
		}

		/**
		 * MainWP Extension Activated
		 *
		 * @param string $extension – Extension file path.
		 *
		 * @since 5.0.0
		 */
		public static function mwp_extension_activated( $extension ) {
			self::extension_log_event( 7706, $extension );
		}

		/**
		 * MainWP Extension Deactivated
		 *
		 * @param string $extension - Extension file path.
		 *
		 * @since 5.0.0
		 */
		public static function mwp_extension_deactivated( $extension ) {
			self::extension_log_event( 7707, $extension );
		}

		/**
		 * MainWP Extension Deleted
		 *
		 * @param string $extension - Extension file path.
		 *
		 * @since 5.0.0
		 */
		public static function mwp_extension_deleted( $extension ) {
			self::extension_log_event( 7708, $extension );
		}

		/**
		 * Add Extension Event
		 *
		 * @param string $event     – Event ID.
		 * @param string $extension – Name of extension.
		 *
		 * @since 5.0.0
		 */
		private static function extension_log_event( $event, $extension ) {
			$extension_dir = explode( '/', $extension );
			$extension_dir = isset( $extension_dir[0] ) ? $extension_dir[0] : false;

			if ( ! $extension_dir ) {
				return;
			}

			// Get MainWP extensions data.
			$mwp_extensions = \MainWP\Dashboard\MainWP_Extensions_View::get_available_extensions( 'all' );
			$extension_ids  = array_keys( $mwp_extensions );
			if ( ! in_array( $extension_dir, $extension_ids, true ) ) {
				return;
			}

			if ( $event ) {
				// Event data.
				$event_data = array();

				if ( 7708 === $event ) {
					// Get extension data.
					$plugin_file = trailingslashit( WP_PLUGIN_DIR ) . $extension;
					$event_data  = array(
						'mainwp_dash'    => true,
						'extension_name' => isset( $mwp_extensions[ $extension_dir ]['title'] ) ? $mwp_extensions[ $extension_dir ]['title'] : false,
						'PluginFile'     => $plugin_file,
						'PluginData'     => (object) array(
							'Name' => isset( $mwp_extensions[ $extension_dir ]['title'] ) ? $mwp_extensions[ $extension_dir ]['title'] : false,
						),
					);
				} else {
					// Get extension data.
					$plugin_file = trailingslashit( WP_PLUGIN_DIR ) . $extension;
					if ( \is_wp_error( \validate_plugin( $plugin_file ) ) ) {
						return;
					}
					$plugin_data = get_plugin_data( $plugin_file );
					$event_data  = array(
						'mainwp_dash'    => true,
						'extension_name' => isset( $mwp_extensions[ $extension_dir ]['title'] ) ? $mwp_extensions[ $extension_dir ]['title'] : false,
						'Plugin'         => (object) array(
							'Name'            => $plugin_data['Name'],
							'PluginURI'       => $plugin_data['PluginURI'],
							'Version'         => $plugin_data['Version'],
							'Author'          => $plugin_data['Author'],
							'Network'         => $plugin_data['Network'] ? 'True' : 'False',
							'plugin_dir_path' => $plugin_file,
						),
					);
				}

				// Log the event.
				Alert_Manager::trigger_event( $event, $event_data );
			}
		}

		/**
		 * Advanced Uptime Monitor Created.
		 *
		 * @param array $monitor_site – Monitor Site data array.
		 *
		 * @since 5.0.0
		 */
		public static function aum_monitor_created( $monitor_site ) {
			// Get monitor site url.
			$site_url = isset( $monitor_site['url_address'] ) ? trailingslashit( $monitor_site['url_address'] ) : ( isset( $monitor_site['url_friendly_name'] ) ? trailingslashit( $monitor_site['url_friendly_name'] ) : false );

			// Report event.
			self::report_aum_monitor_event( 7750, $site_url );
		}

		/**
		 * Advanced Uptime Monitor Deleted.
		 *
		 * @param object $monitor_site – Monitor site object.
		 *
		 * @since 5.0.0
		 */
		public static function aum_monitor_deleted( $monitor_site ) {
			// Get monitor site url.
			$site_url = isset( $monitor_site->url_address ) ? trailingslashit( $monitor_site->url_address ) : ( isset( $monitor_site->url_friendly_name ) ? trailingslashit( $monitor_site->url_friendly_name ) : false );

			// Report event.
			self::report_aum_monitor_event( 7751, $site_url );
		}

		/**
		 * Advanced Uptime Monitor Started.
		 *
		 * @param object $monitor_site – Monitor site object.
		 *
		 * @since 5.0.0
		 */
		public static function aum_monitor_started( $monitor_site ) {
			// Get monitor site url.
			$site_url = isset( $monitor_site->url_address ) ? trailingslashit( $monitor_site->url_address ) : ( isset( $monitor_site->url_friendly_name ) ? trailingslashit( $monitor_site->url_friendly_name ) : false );

			// Report event.
			self::report_aum_monitor_event( 7752, $site_url );
		}

		/**
		 * Advanced Uptime Monitor Paused.
		 *
		 * @param object $monitor_site – Monitor site object.
		 *
		 * @since 5.0.0
		 */
		public static function aum_monitor_paused( $monitor_site ) {
			// Get monitor site url.
			$site_url = isset( $monitor_site->url_address ) ? trailingslashit( $monitor_site->url_address ) : ( isset( $monitor_site->url_friendly_name ) ? trailingslashit( $monitor_site->url_friendly_name ) : false );

			// Report event.
			self::report_aum_monitor_event( 7753, $site_url );
		}

		/**
		 * Report Advanced Uptime Monitor Event.
		 *
		 * @param integer $event_id – Event ID.
		 * @param string  $site_url – Site URL.
		 *
		 * @since 5.0.0
		 */
		public static function report_aum_monitor_event( $event_id, $site_url ) {
			if ( ! empty( $event_id ) && ! empty( $site_url ) ) {
				// Search for site in MainWP sites.
				$site = MainWP_Settings::get_mwp_site_by( $site_url, 'url' );

				// If site is found then report it as MainWP child site.
				if ( false !== $site ) {
					Alert_Manager::trigger_event(
						$event_id,
						array(
							'friendly_name' => $site['name'],
							'site_url'      => $site['url'],
							'site_id'       => $site['id'],
							'mainwp_dash'   => true,
						)
					);
				} else {
					// Else report as other site.
					Alert_Manager::trigger_event(
						$event_id,
						array(
							'friendly_name' => $site_url,
							'site_url'      => $site_url,
							'mainwp_dash'   => true,
						)
					);
				}
			}
		}

		/**
		 * Report Advanced Uptime Monitor Auto Add Sites.
		 *
		 * @since 5.0.0
		 */
		public static function aum_monitor_auto_add() {
			Alert_Manager::trigger_event(
				7754,
				array( 'mainwp_dash' => true )
			);
		}
	}
}
