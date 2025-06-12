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

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\User_Helper;
use WSAL\MainWP\MainWP_Addon;
use WSAL\MainWP\MainWP_Helper;
use WSAL\MainWP\MainWP_Settings;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use MainWP\Dashboard\MainWP_DB_Client;
use WSAL\WP_Sensors\Helpers\MainWP_Server_Helper;

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
		 * Class cache for old client data.
		 *
		 * @var \StdClass|null
		 *
		 * @since 5.4.0
		 */
		private static $old_client_data = null;

		/**
		 * Class cache for old sites.
		 *
		 * @var array
		 *
		 * @since 5.4.0
		 */
		private static $old_sites = null;

		/**
		 * Class cache for new sites.
		 *
		 * @var array
		 *
		 * @since 5.4.0
		 */
		private static $new_sites = null;

		/**
		 * Class cache for old REST API keys.
		 *
		 * @var array
		 *
		 * @since 5.4.0
		 */
		private static $old_api_keys = array();

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
				\add_filter( 'upgrader_process_complete', array( __CLASS__, 'mwp_extension_installed' ), 10, 2 );

				\add_action( 'mainwp_client_deleted', array( __CLASS__, 'client_deleted' ) );
				\add_action( 'mainwp_client_suspend', array( __CLASS__, 'client_suspended' ), 10, 2 );
				\add_action( 'mainwp_client_updated', array( __CLASS__, 'client_updated' ), 10, 2 );

				\add_action( 'wp_ajax_mainwp_clients_add_client', array( __CLASS__, 'store_old_cliet_data' ), -1 );

				// Check if Advanced Uptime Monitor Extension is active.
				if ( WP_Helper::is_plugin_active( 'advanced-uptime-monitor-extension/advanced-uptime-monitor-extension.php' ) ) {
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
			\add_filter(
				'wsal_event_objects',
				array( MainWP_Server_Helper::class, 'wsal_mainwp_server_add_custom_event_objects' ),
				10,
				2
			);

			\add_filter(
				'wsal_event_type_data',
				array( MainWP_Server_Helper::class, 'wsal_mainwp_server_add_custom_event_type' ),
				10,
				2
			);
			\add_filter(
				'wsal_database_site_id_value',
				array( MainWP_Server_Helper::class, 'wsal_mainwp_server_event_store_proper_site_id' ),
				10,
				2
			);
			\add_filter(
				'wsal_get_site_details',
				array( MainWP_Server_Helper::class, 'wsal_mainwp_server_check_site_id_and_get_info' ),
				10,
				3
			);
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
						'SiteID'        => $website->id,
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
			$is_global_sync = isset( $_POST['isGlobalSync'] ) ? \sanitize_text_field( \wp_unslash( $_POST['isGlobalSync'] ) ) : false;

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
			if ( ! empty( $slug ) && WP_Helper::is_plugin_active( $slug ) ) {
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
		 * @param \Plugin_Upgrader $plugin_upgrader_instance Plugin_Upgrader instance. In other contexts, $this, might.
		 *
		 * @since 5.0.0
		 */
		public static function mwp_extension_installed( $plugin_upgrader_instance ) {
			self::extension_log_event( 7705, $plugin_upgrader_instance, $plugin_upgrader_instance->result );
		}

		/**
		 * MainWP Extension Activated
		 *
		 * @param string $extension – Extension file path.
		 *
		 * @since 5.0.0
		 */
		public static function mwp_extension_activated( $extension ) {
			self::extension_log_other_event( 7706, $extension );
		}

		/**
		 * MainWP Extension Deactivated
		 *
		 * @param string $extension - Extension file path.
		 *
		 * @since 5.0.0
		 */
		public static function mwp_extension_deactivated( $extension ) {
			self::extension_log_other_event( 7707, $extension );
		}

		/**
		 * MainWP Extension Deleted
		 *
		 * @param string $extension - Extension file path.
		 *
		 * @since 5.0.0
		 */
		public static function mwp_extension_deleted( $extension ) {
			self::extension_log_other_event( 7708, $extension );
		}

		/**
		 * Add Extension Event
		 *
		 * @param string $event     – Event ID.
		 * @param string $extension – Name of extension.
		 *
		 * @since 5.0.0
		 */
		private static function extension_log_other_event( $event, $extension ) {
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
					if ( \is_wp_error( \validate_plugin( $extension ) ) ) {
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
		 * Add Extension Event
		 *
		 * @param string           $event     – Event ID.
		 * @param \Plugin_Upgrader $plugin_upgrader_instance Plugin_Upgrader instance. In other contexts, $this, might.
		 * @param string           $extension – Name of extension.
		 *
		 * @since 5.0.0
		 */
		private static function extension_log_event( $event, $plugin_upgrader_instance, $extension ) {
			// $extension_dir = explode( '/', $extension );

			if ( ! \is_array( $extension ) || ! \key_exists( 'destination_name', $extension ) ) {
				return;
			}
			$extension_dir = $extension['destination_name'];

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

				// Ensure to pass with leading slash.
				$plugin = get_plugins( '/' . $extension_dir );
				if ( empty( $plugin ) ) {
					return false;
				}

				// Assume the requested plugin is the first in the list.
				$pluginfiles = array_keys( $plugin );

				$plugin = $extension_dir . '/' . $pluginfiles[0];

				$plugin_path = \WP_PLUGIN_DIR . \DIRECTORY_SEPARATOR . $plugin;

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

					$plugin_data = get_plugin_data( $plugin_path );
					$event_data  = array(
						'mainwp_dash'    => true,
						'extension_name' => isset( $mwp_extensions[ $extension_dir ]['title'] ) ? $mwp_extensions[ $extension_dir ]['title'] : false,
						'Plugin'         => (object) array(
							'Name'            => $plugin_data['Name'],
							'PluginURI'       => $plugin_data['PluginURI'],
							'Version'         => $plugin_data['Version'],
							'Author'          => $plugin_data['Author'],
							'Network'         => $plugin_data['Network'] ? 'True' : 'False',
							'plugin_dir_path' => $plugin_path,
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

		/**
		 * Called when a client is deleted.
		 *
		 * @param \StdClass $current - The object containing the current client data.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function client_deleted( $current ) {
			$alert_id = 7721;

			$data = array(
				'ClientName'  => $current->name,
				'ClientEmail' => ( isset( $current->client_email ) && ! empty( $current->client_email ) ) ? $current->client_email : __( 'not set', 'wp-security-audit-log' ),
			);

			Alert_Manager::trigger_event(
				$alert_id,
				$data,
			);
		}

		/**
		 * Called when client is updated.
		 *
		 * @param \StdClass $current - The object containing the current client data.
		 * @param bool      $add_new - Set to true when a new client is added. Otherwise the event is triggered when a client is updated.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function client_updated( $current, $add_new ) {

			$event_type = 'updated';

			if ( $add_new ) {
				$alert_id   = 7718;
				$event_type = 'created';
			}

			$data = array(
				'ClientName'   => $current->name,
				'ClientEmail'  => $current->client_email,
				'EventType'    => $event_type,
				'ClientStatus' => self::get_client_status( $current->suspended ),
				'ClientUrl'    => self::get_client_url( (int) $current->client_id ),
			);

			if ( ! $add_new ) {
				$data['OldEmail'] = '';
				if ( null !== self::$old_client_data && \property_exists( self::$old_client_data, 'client_email' ) ) {
					$data['OldEmail'] = self::$old_client_data->client_email;
				}

				if ( $current->client_email !== $data['OldEmail'] ) {
					$alert_id = 7719;
				}
			}

			if ( isset( $alert_id ) ) {
				Alert_Manager::trigger_event(
					$alert_id,
					$data,
				);
			}

			if ( ! $add_new ) {

				if ( null !== self::$old_client_data && \property_exists( self::$old_client_data, 'suspended' ) ) {

					if ( $current->suspended !== self::$old_client_data->suspended ) {
						$alert_id          = 7720;
						$data['OldStatus'] = self::get_client_status( self::$old_client_data->suspended );
						Alert_Manager::trigger_event(
							$alert_id,
							$data,
						);
					}
				}
			}

			if ( ! $add_new ) {
				$old_sites = self::$old_sites;
				$new_sites = self::$new_sites;

				if ( ! empty( $old_sites ) || ! empty( $new_sites ) ) {
					$added_sites   = array_diff( $new_sites, $old_sites );
					$removed_sites = array_diff( $old_sites, $new_sites );

					if ( ! empty( $added_sites ) ) {
						foreach ( $added_sites as $key => $site_id ) {
							$site = MainWP_Settings::get_mwp_site_by( (string) $site_id, 'id' );
							if ( false !== $site ) {
								$data['AddedSite'] = $site['url'];

								Alert_Manager::trigger_event(
									7722,
									$data,
								);
							}
						}
					}

					if ( ! empty( $removed_sites ) ) {
						foreach ( $removed_sites as $key => $site_id ) {
							$site = MainWP_Settings::get_mwp_site_by( (string) $site_id, 'id' );
							if ( false !== $site ) {
								$data['RemovedSite'] = $site['url'];
								Alert_Manager::trigger_event(
									7723,
									$data,
								);
							}
						}
					}
				}
			}
		}

		/**
		 * Retusns the client status based on the status code.
		 *
		 * @param int $status - The integer representation of the client status.
		 *
		 * @return string
		 *
		 * @since 5.4.0
		 */
		private static function get_client_status( $status ) {
			switch ( $status ) {
				default:
				case 0:
					return __( 'Active', 'wp-security-audit-log' );
				case 1:
					return __( 'Suspended', 'wp-security-audit-log' );
				case 2:
					return __( 'Lead', 'wp-security-audit-log' );
				case 3:
					return __( 'Lost', 'wp-security-audit-log' );
			}
		}

		/**
		 * Called when a client is suspended.
		 *
		 * @param \StdClass $current - The object containing the current client data.
		 * @param [type]    $suspended
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function client_suspended( $current, $suspended ) {
		}

		/**
		 * Tries to extract the old client data from the request.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function store_old_cliet_data() {
			$selected_sites = ( isset( $_POST['selected_sites'] ) && is_array( $_POST['selected_sites'] ) ) ? array_map( 'sanitize_text_field', \wp_unslash( $_POST['selected_sites'] ) ) : array(); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$client_fields  = isset( $_POST['client_fields'] ) ? \wp_unslash( $_POST['client_fields'] ) : array(); //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$client_id = isset( $client_fields['client_id'] ) ? intval( $client_fields['client_id'] ) : 0;

			self::$new_sites = array_unique( $selected_sites );

			self::$new_sites = \array_map(
				function ( $site ) {
					return (int) $site;
				},
				self::$new_sites
			);

			if ( $client_id ) {
				self::$old_client_data = MainWP_DB_Client::instance()->get_wp_client_by( 'client_id', $client_id );

				self::$old_sites = \array_keys( MainWP_DB_Client::instance()->get_websites_by_client_ids( array( $client_id ) ) );
			}
		}

		/**
		 * Builds the client URL.
		 *
		 * @param int $client_id - The client ID.
		 *
		 * @return string
		 *
		 * @since 5.4.0
		 */
		public static function get_client_url( int $client_id ): string {
			return \add_query_arg(
				array(
					'page'      => 'ClientAddNew',
					'client_id' => $client_id,
				),
				\network_admin_url( 'admin.php' )
			);
		}
	}
}
