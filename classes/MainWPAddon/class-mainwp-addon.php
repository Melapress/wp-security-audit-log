<?php
/**
 * MainWP server addon.
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
use WSAL\MainWP\MainWP_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\ListAdminEvents\List_Events;
use WSAL\Actions\Plugin_Installer;
use WSAL\Entities\Metadata_Entity;
use WSAL\Entities\Occurrences_Entity;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Migration class
 */
if ( ! class_exists( '\WSAL\MainWP\MainWP_Addon' ) ) {

	/**
	 * Responsible for all the mainWP operations.
	 *
	 * @package WSAL\MainWP
	 *
	 * @since 5.0.0
	 */
	class MainWP_Addon {
		public const MWPAL_BASE_URL = WSAL_BASE_URL . 'classes/MainWPAddon';

		/**
		 * All the extension tab pages
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $mwpal_extension_tabs = array();

		/**
		 * Current tab page
		 *
		 * @var string
		 *
		 * @since 5.0.0
		 */
		private static $current_tab = '';

		/**
		 * Holds the status of the MainWP plugin - active or not.
		 *
		 * @var boolean
		 *
		 * @since 5.0.0
		 */
		private static $mainwp_main_activated = null;

		/**
		 * Holds the kye for the child plugin
		 *
		 * @var string
		 *
		 * @since 5.0.0
		 */
		private static $child_key = null;

		/**
		 * The child plugin array with the properties from mainWP
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $child_enabled = array();

		/**
		 * Inits the class and sets the hooks
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function init() {
			if ( self::check_mainwp_plugin_active() ) {

				if ( Plugin_Installer::is_plugin_installed( 'activity-log-mainwp/activity-log-mainwp.php' ) && WP_Helper::is_plugin_active( 'activity-log-mainwp/activity-log-mainwp.php' ) ) {
					Plugin_Installer::deactivate_plugin( 'activity-log-mainwp/activity-log-mainwp.php' );

					Occurrences_Entity::drop_table();
					Metadata_Entity::drop_table();
				}

				MainWP_Helper::init();

				// Plugin Extension Name.
				if ( ! defined( 'MWPAL_EXTENSION_NAME' ) ) {
					$filename = basename( WSAL_BASE_DIR );
					$filename = str_replace( '-', ' ', $filename );
					$filename = ucwords( $filename );
					$filename = str_replace( ' ', '-', $filename );
					define( 'MWPAL_EXTENSION_NAME', 'Extensions-' . $filename );
				}

				\add_action( 'admin_init', array( __CLASS__, 'setup_extension_tabs' ), 10 );

				self::check_mainwp_active();

				\add_filter( 'mainwp_getextensions', array( __CLASS__, 'get_this_extension' ) );

				\add_filter( 'mainwp_main_menu', array( __CLASS__, 'mwpal_main_menu' ), 10, 1 );
				\add_filter( 'mainwp_main_menu_submenu', array( __CLASS__, 'mwpal_main_menu_submenu' ), 10, 1 );

				// Render header.
				\add_action( 'mainwp_pageheader_extensions', array( '\WSAL_Views_AuditLog', 'header' ) );
				\add_action( 'mainwp_pagefooter_extensions', array( '\WSAL_Views_AuditLog', 'footer' ), 20 );

				\add_filter( 'wsal_custom_view_page', array( __CLASS__, 'is_that_auditlog_view' ), 20 );

				\add_filter( 'wsal_add_site_filter', '__return_true', 20 );

				\add_action( 'mainwp_header_left', array( __CLASS__, 'custom_page_title' ) );
			}
		}

		/**
		 * Sets a custom page title for our extension plugin.
		 *
		 * @param string $title Page title.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function custom_page_title( $title ) {
			if ( isset( $_REQUEST['page'] ) && 'Extensions-Wp-Security-Audit-Log-Premium' === $_REQUEST['page'] || isset( $_REQUEST['page'] ) && 'Extensions-Wp-Security-Audit-Log' === $_REQUEST['page'] ) {
				$title = esc_html__( 'WP Activity Log', 'wp-security-audit-log' );
			}

			return $title;
		}

		/**
		 * Checks if the MainWp server plugin is activated
		 *
		 * @return bool
		 *
		 * @since 5.0.0
		 */
		public static function check_mainwp_plugin_active(): bool {
			if ( ! is_plugin_active( 'mainwp/mainwp.php' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Hooks the the MainWp server activation. Collects the keys needed to interact with the API calls
		 *
		 * @return bool
		 *
		 * @since 5.0.0
		 */
		public static function check_mainwp_active(): bool {
			if ( \is_null( self::$mainwp_main_activated ) ) {

				self::$mainwp_main_activated = (bool) apply_filters( 'mainwp_activated_check', false );

				if ( self::$mainwp_main_activated ) {
					self::get_child_key();
				}
				if ( false === self::$mainwp_main_activated ) {
					// Because sometimes our main plugin is activated after the extension plugin is activated we also have a second step,
					// listening to the 'mainwp_activated' action. This action is triggered by MainWP after initialization.
					add_action( 'mainwp_activated', array( __CLASS__, 'activate_this_plugin' ) );
				}
			}

			return self::$mainwp_main_activated;
		}

		/**
		 * The function "activate_this_plugin" is called when the main is initialized.
		 *
		 * TODO: Check if that is needed
		 *
		 * @since 5.0.0
		 */
		public static function activate_this_plugin() {
			self::$mainwp_main_activated = true;

			self::get_child_key();
		}

		/**
		 * Collects the MainWp child key assigned to the plugin
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_child_key(): string {

			if ( null === self::$child_key ) {
				// The 'mainwp_extension_enabled_check' hook. If the plugin is not enabled this will return false,
				// if the plugin is enabled, an array will be returned containing a key.
				// This key is used for some data requests to our main.
				self::$child_enabled = (array) apply_filters( 'mainwp_extension_enabled_check', WSAL_BASE_FILE_NAME );

				self::$child_key = '';

				if ( isset( self::$child_enabled['key'] ) ) {
					self::$child_key = (string) self::$child_enabled['key'];
				}
			}

			return self::$child_key;
		}

		/**
		 * Add extension to MainWP.
		 *
		 * @param array $plugins – Array of plugins.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_this_extension( $plugins ) {
			$plugins[] = array(
				'plugin'   => WSAL_BASE_FILE_NAME,
				'api'      => basename( __FILE__, '.php' ),
				'mainwp'   => false,
				'callback' => array( __CLASS__, 'display_extension' ),
				'icon'     => trailingslashit( self::MWPAL_BASE_URL ) . 'assets/img/activity-log-mainwp-500x500.jpg',
			);

			return $plugins;
		}

		/**
		 * Sets the proper view type so it can trigger the proper list of operations needed for this to work.
		 *
		 * @param bool $view - The boolean status of the view.
		 *
		 * @return boolean
		 *
		 * @since 5.0.0
		 */
		public static function is_that_auditlog_view( $view ): bool {
			// TODO: check page and return bool based on that - that is for the search filters firing.

			$view = true;

			return $view;
		}

		/**
		 * Extension Display on MainWP Dashboard.
		 *
		 * @since 5.0.0
		 */
		public static function display_extension() {
			// The "mainwp_pageheader_extensions" action is used to render the tabs on the Extensions screen.
			// It's used together with mainwp_pagefooter_extensions and mainwp-getextensions.
			do_action( 'mainwp_pageheader_extensions', __FILE__ );

			\WSAL_Views_AuditLog::header();

			\add_filter( 'wsal_override_is_multisite', '__return_true' );

			$events_list = new List_Events( \WSAL_Views_AuditLog::get_page_arguments() );

			\remove_filter( 'wsal_override_is_multisite', '__return_true' );

			$events_list->prepare_items();
			$view_input_value = 'list';
			$site_id          = MainWP_Settings::get_view_site_id();
			?>
			<form id="audit-log-viewer" method="get">
				<div id="audit-log-viewer-content">
					<input type="hidden" name="page" value="<?php echo esc_attr( \WSAL_Views_AuditLog::get_page_arguments()['page'] ); ?>" />
					<input type="hidden" id="mwpal-site-id" name="mwpal-site-id" value="<?php echo esc_attr( $site_id ); ?>" />
					<input type="hidden" id="wsal-cbid" name="wsal-cbid" value="<?php echo esc_attr( empty( \WSAL_Views_AuditLog::get_page_arguments()['site_id'] ) ? '0' : \WSAL_Views_AuditLog::get_page_arguments()['site_id'] ); ?>" />
					<input type="hidden" id="view" name="view" value="<?php echo esc_attr( $view_input_value ); ?>" />
					<?php
					/**
					 * Hook: `wsal_auditlog_before_view`
					 *
					 * This action hook is triggered before displaying the audit log view.
					 *
					 * @param WSAL_AuditLogListView $this->_view - Audit log view object.
					 */
					do_action( 'wsal_auditlog_before_view', $events_list );

					/**
					 * Action: `wsal_search_filters_list`
					 *
					 * Display list of search filters of WSAL.
					 *
					 * @param string $which – Navigation position; value is either top or bottom.
					 * @since 3.2.3
					 */
					do_action( 'wsal_search_filters_list', 'top' );

					?>
						<?php
							// Display the audit log list.
							$events_list->display();

							/**
							 * Hook: `wsal_auditlog_after_view`
							 *
							 * This action hook is triggered after displaying the audit log view.
							 *
							 * @param WSAL_AuditLogListView $this->_view - Audit log view object.
							 */
							do_action( 'wsal_auditlog_after_view', $events_list );
						?>
				</div>
			</form>
			<?php
			do_action( 'mainwp_pagefooter_extensions', __FILE__ );

			?>
			<script type="text/javascript">
				jQuery( document ).ready( function() {
					window['WsalAuditLogRefreshed']();
				} );
			</script>
					<?php
		}

		/**
		 * Extension left menu for MainWP v4 or later.
		 *
		 * @param array $mwpal_left_menu - Left menu array.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function mwpal_main_menu( $mwpal_left_menu ) {
			$sub_menu_after = array_splice( $mwpal_left_menu['leftbar'], 2 );

			$activity_log   = array();
			$activity_log[] = __( 'Activity Log', 'wp-security-audit-log' );
			$activity_log[] = MWPAL_EXTENSION_NAME;
			$activity_log[] = self::$mwpal_extension_tabs['activity-log']['link'];

			$mwpal_left_menu['leftbar'][] = $activity_log;
			$mwpal_left_menu['leftbar']   = array_merge( $mwpal_left_menu['leftbar'], $sub_menu_after );

			return $mwpal_left_menu;
		}

		/**
		 * Extension sub left menu for MainWP v4 or later.
		 *
		 * @param array $mwpal_sub_left_menu - Left menu array.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function mwpal_main_menu_submenu( $mwpal_sub_left_menu ) {
			$extension_url = add_query_arg( 'page', 'wsal-settings', \network_admin_url( 'admin.php' ) );

			$mwpal_sub_left_menu[ MWPAL_EXTENSION_NAME ] = apply_filters(
				'mwpal_main_menu_submenu',
				array(
					// array(
					// __( 'Child Sites Settings', 'wp-security-audit-log' ),
					// self::$mwpal_extension_tabs['child_site_settings']['link'],
					// 'manage_options',
					// ),
					array(
						__( 'Plugin Settings', 'wp-security-audit-log' ),
						$extension_url,
						'manage_options',
					),
				)
			);

			return $mwpal_sub_left_menu;
		}

		/**
		 * Add extension tabs to extension page.
		 *
		 * @param array $page_tabs - Array of page tabs.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function mwpal_extension_tabs( $page_tabs ) {
			global $pagenow;

			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false;

			if ( 'admin.php' !== $pagenow ) {
				return $page_tabs;
			} elseif ( MWPAL_EXTENSION_NAME !== $page ) {
				return $page_tabs;
			}

			$page_tabs[1]['active'] = 'activity-log' === self::$current_tab;

			$extension_tabs = apply_filters(
				'mwpal_page_navigation',
				array(
					array(
						'title'  => __( 'Extension Settings', 'wp-security-audit-log' ),
						'href'   => self::$mwpal_extension_tabs['settings']['link'],
						'active' => 'settings' === self::$current_tab,
					),
				)
			);

			foreach ( $extension_tabs as $tab ) {
				$page_tabs[] = $tab;
			}

			return $page_tabs;
		}

		/**
		 * Sets the extension tabs in the navigation.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function setup_extension_tabs() {
			global $_mainwp_menu_active_slugs;
			$_mainwp_menu_active_slugs[ MWPAL_EXTENSION_NAME ] = MWPAL_EXTENSION_NAME;

			// Extension view URL.
			$extension_url = add_query_arg( 'page', MWPAL_EXTENSION_NAME, \network_admin_url( 'admin.php' ) );

			// Tab links.
			$mwpal_extension_tabs = array(
				'activity-log' => array(
					'name'   => __( 'Activity Log', 'wp-security-audit-log' ),
					'link'   => $extension_url,
					'render' => array( __CLASS__, 'tab_activity_log' ),
					'save'   => array( __CLASS__, 'tab_activity_log_save' ),
				),
			);

			/**
			 * `mwpal_extension_tabs`
			 *
			 * This filter is used to filter the tabs of WSAL settings page.
			 *
			 * Setting tabs structure:
			 *     $mwpal_extension_tabs['unique-tab-id'] = array(
			 *         'name'   => Name of the tab,
			 *         'link'   => Link of the tab,
			 *         'render' => This function is used to render HTML elements in the tab,
			 *         'name'   => This function is used to save the related setting of the tab,
			 *     );
			 *
			 * @param array  $mwpal_extension_tabs - Array of extension tabs.
			 * @param string $extension_url        - URL of the extension.
			 */
			self::$mwpal_extension_tabs = apply_filters( 'mwpal_extension_tabs', $mwpal_extension_tabs, $extension_url );

			// Get the current tab.
			$current_tab       = ( isset( $_GET['tab'] ) ) ? \sanitize_text_field( \wp_unslash( $_GET['tab'] ) ) : null;
			self::$current_tab = empty( $current_tab ) ? 'activity-log' : $current_tab;
		}

		/**
		 * Get WSAL Child Sites.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_wsal_child_sites() {
			// Check if the WSAL child sites option exists.
			$child_sites = Settings_Helper::get_option_value( 'wsal-child-sites' );

			// Get MainWP Child sites.
			$mwp_sites = MainWP_Settings::get_mwp_child_sites();

			if ( empty( $child_sites ) && ! empty( $mwp_sites ) ) {
				foreach ( $mwp_sites as $site ) {
					// Call to child sites to check if WSAL is installed on them or not.
					$results[ $site['id'] ] = self::make_api_call( $site['id'], 'check_wsal' );
				}

				if ( ! empty( $results ) && is_array( $results ) ) {
					$child_sites = array();

					foreach ( $results as $site_id => $site_array ) {
						if ( empty( $site_array ) || ! is_array( $site_array ) ) {
							continue;
						} elseif ( is_array( $site_array ) && isset( $site_array['wsal_installed'] ) && true === $site_array['wsal_installed'] ) {
							$child_sites[ $site_id ] = $site_array;
						}
					}
					Settings_Helper::set_option_value( 'wsal-child-sites', $child_sites );
				}
			}

			return $child_sites;
		}

		/**
		 * Makes an API call to a child site.
		 *
		 * @param int    $site_id    Site ID.
		 * @param string $action     Action attribute.
		 * @param array  $extra_data Extra arguments.
		 *
		 * @return false|stdClass
		 *
		 * @since 2.0.0
		 */
		public static function make_api_call( $site_id, $action, $extra_data = array() ) {
			// Return if site id is empty.
			if ( empty( $site_id ) ) {
				return false;
			}

			// Post data for child sites.
			$post_data = array(
				'action' => $action,
			);

			if ( is_array( $extra_data ) && ! empty( $extra_data ) ) {
				$post_data = array_merge( $post_data, $extra_data );
			}

			// Call the child site.
			return apply_filters(
				'mainwp_fetchurlauthed',
				WSAL_BASE_FILE_NAME,
				self::get_child_key(),
				$site_id,
				'extra_excution',
				$post_data
			);
		}
	}
}
