<?php
/**
 * Plugin Name: WP Activity Log
 * Plugin URI: http://wpactivitylog.com/
 * Description: Identify WordPress security issues before they become a problem. Keep track of everything happening on your WordPress including WordPress users activity. Similar to Windows Event Log and Linux Syslog, WP Activity Log generates a security alert for everything that happens on your WordPress blogs and websites. Use the Activity log viewer included in the plugin to see all the security alerts.
 * Author: WP White Security
 * Version: 4.1.3.2
 * Text Domain: wp-security-audit-log
 * Author URI: http://www.wpwhitesecurity.com/
 * License: GPL2
 *
 * @package Wsal
 *
 * @fs_premium_only /extensions/, /sdk/twilio-php/
 */

/*
	WP Activity Log
	Copyright(c) 2020  WP White Security  (email : info@wpwhitesecurity.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! function_exists( 'wsal_freemius' ) ) {

	/**
	 * WSAL Main Class.
	 *
	 * @package Wsal
	 */
	class WpSecurityAuditLog {

		/**
         * Plugin version.
         *
		 * @var string
		 */
		public $version = '4.1.3.2';

		/**
         * Plugin constants.
         *
		 * @var string
		 */
		const PLG_CLS_PRFX    = 'WSAL_';

		/**
         * Minimal PHP version.
         *
		 * @var string
		 */
		const MIN_PHP_VERSION = '5.5.0';

        /**
         * Old option name prefix.
         *
         * @var string
         * @deprecated 4.1.3
         *
         * @todo Remove in future versions.
         */
		const OPT_PRFX        = 'wsal-';

		/**
         * New option name prefix.
         *
		 * @var string
		 */
		const OPTIONS_PREFIX = 'wsal_';

		/**
		 * Views supervisor.
		 *
		 * @var WSAL_ViewManager
		 */
		public $views;

		/**
		 * Logger supervisor.
		 *
		 * @var WSAL_AlertManager
		 */
		public $alerts;

		/**
		 * Sensors supervisor.
		 *
		 * @var WSAL_SensorManager
		 */
		public $sensors;

		/**
		 * Settings manager. Accessed via $this->settings, which lazy-loads it.
		 *
		 * @var WSAL_Settings
		 */
		protected $_settings;

		/**
		 * Class loading manager.
		 *
		 * @var WSAL_Autoloader
		 */
		public $autoloader;

		/**
		 * Constants manager.
		 *
		 * @var WSAL_ConstantManager
		 */
		public $constants;

		/**
		 * Options.
		 *
		 * @var WSAL_Models_Option
		 */
		public $options;

		/**
		 * WP Options table options handler.
		 *
		 * @var WSAL\Helpers\Options;
		 */
		public $options_helper;

		/**
		 * Contains a list of cleanup callbacks.
		 *
		 * @var callable[]
		 */
		protected $_cleanup_hooks = array();

		/**
		 * Add-ons Manager.
		 *
		 * @var WSAL_Extension_Manager
		 */
		public $extensions;

		/**
		 * Allowed HTML Tags for strings.
		 *
		 * @var array
		 */
		public $allowed_html_tags = array();

		/**
		 * Load WSAL on Front-end?
		 *
		 * @var boolean
		 */
		public $load_for_404s = null;

		/**
		 * Standard singleton pattern.
		 * WARNING! To ensure the system always works as expected, AVOID using this method.
		 * Instead, make use of the plugin instance provided by 'wsal_init' action.
		 *
		 * @return WpSecurityAuditLog Returns the current plugin instance.
		 */
		public static function GetInstance() {
			static $instance = null;
			if ( ! $instance ) {
				$instance = new self();
			}
			return $instance;
		}

		/**
		 * Initialize plugin.
		 */
		public function __construct() {
			$bootstrap_hook = [ 'plugins_loaded', 9 ];

			// Frontend requests should only log for certain 404 requests.
			// For that to happen, we need to delay until template_redirect.
			if ( self::is_frontend() ) {
				// to track sessions on frontend logins we need to attach the
				// the tracker and all the interfaces and classes it depends on.
				add_action( $bootstrap_hook[0], array( $this, 'maybe_add_sessions_trackers_early' ), $bootstrap_hook[1] );
				$bootstrap_hook = [ 'wp_loaded', 0 ];
				add_action( 'wp', array( $this, 'setup_404' ) );
			}

			add_action( $bootstrap_hook[0], array( $this, 'setup' ), $bootstrap_hook[1] );

			// Register plugin specific activation hook.
			register_activation_hook( __FILE__, array( $this, 'Install' ) );

			// Plugin Deactivation Actions.
			register_deactivation_hook( __FILE__, array( $this, 'deactivate_actions' ) );

			// Add custom schedules for WSAL early otherwise they won't work.
			add_filter( 'cron_schedules', array( $this, 'recurring_schedules' ) );
			// make the options helper class available.
			$this->include_options_helper();
		}

		/**
		 * For frontend loading only - adds all dependency classes, interfaces,
		 * and helpers from sessions tracking and hooks the tracking methods in
		 * when frontend login sensors are enabled.
		 *
		 * @method add_sessions_trackers
		 * @since  4.x.x
		 */
		public function maybe_add_sessions_trackers_early() {

			/**
			 * If the frontend login tracking is not enabled don't add anything.
			 */
			$frontend_events = WSAL_Settings::get_frontend_events();
			if ( empty( $frontend_events['login'] ) ) {
				return;
			}

			// To track sessions from the frontend we need to load the session
			// tracking class and init it's hooks plus make available all of the
			// supporting classes it needs to operate.
			$base_path = plugin_dir_path( __FILE__ );
			if ( file_exists( $base_path . 'extensions/user-sessions/user-sessions.php' ) ) {
				spl_autoload_register( array( __CLASS__, 'autoloader' ) );

				//  classes below don't follow any naming convention handled by the autoloader
				require_once $base_path . 'extensions/user-sessions/classes/Adapters/SessionInterface.php';
				require_once $base_path . 'extensions/user-sessions/classes/Adapters/SessionAdapter.php';
				require_once $base_path . 'extensions/user-sessions/classes/Models/Session.php';
				require_once $base_path . 'extensions/user-sessions/classes/Helpers.php';
				require_once $base_path . 'extensions/user-sessions/user-sessions.php';

				$session_tracking = new WSAL_Sensors_UserSessionsTracking( $this );
				$session_tracking->init();
			}
		}

		/**
		 * PHP magic __get function to get class properties.
		 *
		 * @param string $property - Class property.
		 * @return object
		 */
		public function __get( $property ) {
			if ( 'settings' === $property ) {
				return $this->settings();
			}
		}

		/**
		 * Return the settings object, lazily instantiating, if needed.
		 *
		 * @return WSAL_Settings
		 */
		public function settings() {
			if ( ! isset( $this->_settings ) ) {
				$this->_settings = new WSAL_Settings( $this );
			}

			return $this->_settings;
		}

		/**
		 * Gets and instantiates the options helper.
		 *
		 * @method include_options_helper
		 * @since  4.0.3
		 * @return \WSAL\Helpers\Options
		 */
		public function include_options_helper() {
			require_once 'classes/Helpers/Options.php';
			if ( ! isset( $this->options_helper ) ) {
				$this->options_helper = new \WSAL\Helpers\Options( self::OPTIONS_PREFIX );
			}
			return $this->options_helper;
		}

		/**
		 * Whether the current request is a REST API request.
		 *
		 * @return bool
		 */
		public static function is_rest_api() {
			$is_rest = false;

			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$rest_url_path = trim( parse_url( home_url( '/wp-json/' ), PHP_URL_PATH ), '/' );
				$request_path  = trim( $_SERVER['REQUEST_URI'], '/' );

				/*
				 * If we have both a url and a request patch check if this is
				 * a rest request.
				 *
				 * @since 4.0..3
				 */
				if ( $rest_url_path && $request_path ) {
					$is_rest = ( strpos( $request_path, $rest_url_path ) === 0 ) || isset( $_GET['rest_route'] );
				}
			}

			return $is_rest;
		}

		/**
		 * Whether the current request is a frontend request.
		 *
		 * @return bool
		 */
		public static function is_frontend() {
			return ! is_admin()
                   && ! self::is_login_screen()
                   && ( ! defined( 'WP_CLI' ) || ! WP_CLI )
                   && ( ! defined( 'DOING_CRON' ) || ! DOING_CRON )
                   && ! self::is_rest_api()
                   && ! self::is_admin_blocking_plugins_support_enabled();
		}

		/**
		 * Decides if the plugin should run for 404 events on `wp` hook
		 * IF not already loaded on `wp_loaded` hook for frontend request.
		 */
		public function setup_404() {
		    $admin_blocking_plugins_support_enabled = $this->is_admin_blocking_plugins_support_enabled();
		    if (!$admin_blocking_plugins_support_enabled) {
                // If a user is logged in OR if the frontend sensors are allowed to load, then bail.
                if ( is_user_logged_in() || self::should_load_frontend() ) {
                    return;
                }

                // If the current page is not 404 OR if the loading of 404 frontend sensor is not allowed, then bail.
                if ( ! is_404() || ! $this->load_for_404s() ) {
                    return;
                }
            }

			if ($admin_blocking_plugins_support_enabled) {
				//  setup freemius in stealth mode
				$this->init_freemius();
			}

			// Otherwise load WSAL on wp hook.
			$this->setup();
		}

		/**
		 * Decides if the plugin should run, sets up constants, includes, inits hooks, etc.
		 *
		 * @return bool
		 */
		public function setup() {
			if ( ! $this->should_load() ) {
				return;
			}

			$this->define_constants();
			$this->set_allowed_html_tags();
			$this->includes();
			$this->init_hooks();
			$this->load_defaults();
			$this->load_wsal();

			if ( did_action( 'init' ) ) {
				$this->init();
			}
		}

		/**
		 * Returns whether the plugin should load.
		 *
		 * @return bool Whether the plugin should load.
		 */
		public function should_load() {
			// Always load on the admin.
			if ( is_admin() ) {
				return true;
			}

			// If this is a frontend request, it's a 404, and 404 logging is disabled.
			if ( self::is_frontend() ) {
				if ( is_404() ) {
					if ( ! $this->load_for_404s() ) {
						// This is a frontend request, and it's a 404, but we are not logging 404s.
						return false;
					}
				} elseif ( ! is_user_logged_in() && ! self::should_load_frontend() ) {
					// This is not a 404, and the user isn't logged in, and we aren't logging visitor events.
					return false;
				}
			}

			// If this is a rest API request and the user is not logged in, bail.
			if ( self::is_rest_api() && ! is_user_logged_in() ) {
				return false;
			}

			return true;
		}

		/**
		 * Checks to see if WSAL should be loaded for register, login, and comment events.
		 *
		 * @return bool
		 */
		public static function should_load_frontend() {
			$frontend_events = WSAL_Settings::get_frontend_events();
			return ! empty( $frontend_events['register'] ) || ! empty( $frontend_events['login'] ) || ! empty( $frontend_events['woocommerce'] );
		}

		/**
		 * Include Plugin Files.
		 *
		 * @since 3.3
		 */
		public function includes() {
			require_once 'classes/Models/ActiveRecord.php';
			require_once 'classes/Models/Option.php';

			if ( is_admin() ) {
				// Models.
				require_once 'classes/Models/Meta.php';
				require_once 'classes/Models/Occurrence.php';
				require_once 'classes/Models/Query.php';
				require_once 'classes/Models/OccurrenceQuery.php';
				require_once 'classes/Models/TmpUser.php';

				// Data helper.
				require_once 'classes/Helpers/DataHelper.php';

				// Managers.
				require_once 'classes/ViewManager.php';
				require_once 'classes/WidgetManager.php';

				// Views.
				require_once 'classes/AbstractView.php';
				require_once 'classes/AuditLogListView.php';
				require_once 'classes/AuditLogGridView.php';
				require_once 'classes/Views/AuditLog.php';
				require_once 'classes/Views/EmailNotifications.php';
				require_once 'classes/Views/ExternalDB.php';
				require_once 'classes/Views/Help.php';
				require_once 'classes/Views/LogInUsers.php';
				require_once 'classes/Views/Reports.php';
				require_once 'classes/Views/Search.php';
				require_once 'classes/Views/Settings.php';
				require_once 'classes/Views/ToggleAlerts.php';

				// Utilities.
				require_once 'classes/Utilities/PluginInstallAndActivate.php';
				require_once 'classes/Utilities/PluginInstallerAction.php';
			}

			// Connectors.
			require_once 'classes/Connector/AbstractConnector.php';
			require_once 'classes/Connector/ConnectorInterface.php';
			require_once 'classes/Connector/ConnectorFactory.php';
			require_once 'classes/Connector/MySQLDB.php';

			// Adapters.
			require_once 'classes/Adapters/ActiveRecordInterface.php';
			require_once 'classes/Adapters/MetaInterface.php';
			require_once 'classes/Adapters/OccurrenceInterface.php';
			require_once 'classes/Adapters/QueryInterface.php';

			// Only include these if we are in multisite envirnoment.
			if ( $this->isMultisite() ) {
				require_once 'classes/Multisite/NetworkWide/TrackerInterface.php';
				require_once 'classes/Multisite/NetworkWide/AbstractTracker.php';
				require_once 'classes/Multisite/NetworkWide/CPTsTracker.php';
				// setup the CPT tracker across the network.
				$cpts_tracker = new \WSAL\Multisite\NetworkWide\CPTsTracker( $this );
				$cpts_tracker->setup();
			}

			// Load autoloader and register base paths.
			require_once 'classes/Autoloader.php';
			$this->autoloader = new WSAL_Autoloader( $this );
			$this->autoloader->Register( self::PLG_CLS_PRFX, $this->GetBaseDir() . 'classes' . DIRECTORY_SEPARATOR );
		}

		/**
		 * Initialize Plugin Hooks.
		 *
		 * @since 3.3
		 */
		public function init_hooks() {
			add_action( 'init', array( $this, 'init' ), 5 );

			// Listen for cleanup event.
			add_action( 'wsal_cleanup', array( $this, 'CleanUp' ) );

			// Render wsal footer.
			add_action( 'admin_footer', array( $this, 'render_footer' ) );

			// Plugin redirect on activation.
			if ( current_user_can( 'manage_options' ) ) {
				add_action( 'admin_init', array( $this, 'wsal_plugin_redirect' ) );
			}

			// Handle admin Disable Custom Field.
			add_action( 'wp_ajax_AjaxDisableCustomField', array( $this, 'AjaxDisableCustomField' ) );

			// Handle admin Disable Alerts.
			add_action( 'wp_ajax_AjaxDisableByCode', array( $this, 'AjaxDisableByCode' ) );

			// Render Login Page Notification.
			add_filter( 'login_message', array( $this, 'render_login_page_message' ), 10, 1 );

			// Cron job to delete alert 1003 for the last day.
			add_action( 'wsal_delete_logins', array( $this, 'delete_failed_logins' ) );
			if ( ! wp_next_scheduled( 'wsal_delete_logins' ) ) {
				wp_schedule_event( time(), 'daily', 'wsal_delete_logins' );
			}

			add_filter( 'mainwp_child_extra_execution', array( $this, 'mainwp_dashboard_callback' ), 10, 2 );

			add_action( 'admin_init', array( $this, 'sync_premium_freemius' ) );

			add_action( 'wsal_freemius_loaded', array( $this, 'adjust_freemius_strings' ) );

			$this->init_freemius();

			if ( is_admin() ) {
				$plugin_installer_ajax = new WSAL_PluginInstallerAction();
				$plugin_installer_ajax->register();
			}
		}

		/**
		 * Whether the current page is the login screen.
		 *
		 * @return bool
		 */
		public static function is_login_screen() {
			return parse_url( site_url( 'wp-login.php' ), PHP_URL_PATH ) === parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		}

		/**
		 * Load Freemius SDK.
		 */
		public static function load_freemius() {
			require_once plugin_dir_path( __FILE__ ) . '/sdk/wsal-freemius.php';
		}

		/**
		 * Determines whether a plugin is active.
		 *
		 * @uses is_plugin_active() Uses this WP core function after making sure that this function is available.
		 * @param string $plugin Path to the main plugin file from plugins directory.
		 * @return bool True, if in the active plugins list. False, not in the list.
		 */
		public static function is_plugin_active( $plugin ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			return is_plugin_active( $plugin );
		}

		/**
		 * Check if BBPress plugin is active or not.
		 *
		 * @return boolean
		 */
		public static function is_bbpress_active() {
			return self::is_plugin_active( 'bbpress/bbpress.php' );
		}

		/**
		 * Check if WooCommerce plugin is active or not.
		 *
		 * @return boolean
		 */
		public static function is_woocommerce_active() {
			// Check for WC extensional also, as the sensor should not be loaded without it.
			if ( function_exists( 'wsal_woocommerce_extension_init_actions' ) && self::is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Check if Yoast SEO plugin is active or not.
		 *
		 * @return boolean
		 */
		public static function is_wpseo_active() {
			return self::is_plugin_active( 'wordpress-seo/wp-seo.php' ) || self::is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' );
		}

		/**
		 * Check if MainWP plugin is active or not.
		 *
		 * @return boolean
		 */
		public static function is_mainwp_active() {
			return self::is_plugin_active( 'mainwp-child/mainwp-child.php' );
		}

		/**
		 * Check if Two Factor plugin is active or not.
		 *
		 * @return boolean
		 */
		public static function is_twofactor_active() {
			return self::is_plugin_active( 'two-factor/two-factor.php' );
		}

		/**
		 * Initializes Freemius and its hooks, conditionally.
		 *
		 * @return void
		 */
		public function init_freemius() {

            $is_admin_blocking_plugins_support_enabled = $this->is_admin_blocking_plugins_support_enabled();
			if ( self::is_frontend() && 'no' !== self::is_premium_freemius() && file_exists( WSAL_BASE_DIR . '/extensions/class-wsal-extension-manager.php' ) ) {
				require_once WSAL_BASE_DIR . '/extensions/class-wsal-extension-manager.php';

				if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
					WSAL_Extension_Manager::include_extension( 'reports' );
					WSAL_Extension_Manager::include_extension( 'usersessions' );
					WSAL_Extension_Manager::include_extension( 'external-db' );
				} elseif ( $this->should_load() ) {
					WSAL_Extension_Manager::include_extension( 'notifications' );
				}

				if (!$is_admin_blocking_plugins_support_enabled) {
					//  we only stop here if the support for admin blocking plugins is enabled
                    return;
                }
			}

			if ( $is_admin_blocking_plugins_support_enabled || is_admin() || self::is_login_screen() || self::is_rest_api() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {

				self::load_freemius();
				if ( ! apply_filters( 'wsal_disable_freemius_sdk', false ) ) {
					// Add filters to customize freemius welcome message.
					wsal_freemius()->add_filter( 'connect_message', array( $this, 'wsal_freemius_connect_message' ), 10, 6 );
					wsal_freemius()->add_filter( 'connect_message_on_update', array( $this, 'wsal_freemius_update_connect_message' ), 10, 6 );
					wsal_freemius()->add_filter( 'trial_promotion_message', array( $this, 'freemius_trial_promotion_message' ), 10, 1 );
					wsal_freemius()->add_filter( 'show_first_trial_after_n_sec', array( $this, 'change_show_first_trial_period' ), 10, 1 );
					wsal_freemius()->add_filter( 'reshow_trial_after_every_n_sec', array( $this, 'change_reshow_trial_period' ), 10, 1 );
					wsal_freemius()->add_filter( 'show_admin_notice', array( $this, 'freemius_show_admin_notice' ), 10, 2 );
					wsal_freemius()->add_filter( 'show_delegation_option', '__return_false' );
					wsal_freemius()->add_filter( 'enable_per_site_activation', '__return_false' );
					wsal_freemius()->add_filter( 'show_trial', '__return_false' );
					wsal_freemius()->add_filter( 'opt_in_error_message', array( $this, 'limited_license_activation_error' ), 10, 1 );
					wsal_freemius()->add_action( 'after_account_plan_sync', array( $this, 'sync_premium_freemius' ), 10, 1 );
				}
			}
		}

		/**
		 * Check if WSAL should be loaded for logged-in 404s.
		 *
		 * @since 3.3
		 *
		 * @return boolean
		 */
		public function load_for_404s() {
			if ( null === $this->load_for_404s ) {
				if ( ! is_user_logged_in() ) {
					// Get the frontend sensors setting.
					$frontend_events = WSAL_Settings::get_frontend_events();

					// This overrides the setting.
					$this->load_for_404s = ! empty( $frontend_events['system'] ) ? true : false;
				} else {
					// We are doing a raw lookup here because The WSAL options system might not be loaded.
					$this->load_for_404s = self::raw_alert_is_enabled( 6007 );
				}

                if ($this->is_admin_blocking_plugins_support_enabled()) {
                    //  also load if the support for admin blocking plugins is enabled
	                $this->load_for_404s = true;
                }
			}

			return $this->load_for_404s;
		}

		/**
		 * Whether visitor events should be logged.
		 *
		 * @return bool
		 */
		public function load_for_visitor_events() {
			return 'no' === self::get_raw_option( 'disable-visitor-events', 'no' );
		}

		/**
		 * Query option from the WSAL options table directly.
		 *
		 * @param string $name    - Option name.
		 * @param mixed  $default - Option default value.
		 * @return mixed
		 */
		public static function get_raw_option( $name, $default = false ) {
			global $wpdb;
			$table_name = $wpdb->base_prefix . 'wsal_options'; // Using base_prefix because we don't have multiple tables on multisite.
			$name       = 'wsal-' . $name;
			$value      = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $table_name WHERE option_name = %s", $name ) );
			return $value ? $value : $default;
		}

		/**
		 * Whether an alert is enabled. For use before loading the settings.
		 *
		 * @since 4.0.2 - updated to use get_option instead of self::get_raw_option.
		 *
		 * @param string|int $alert The alert to check.
		 * @return bool Whether the alert is enabled.
		 */
		public static function raw_alert_is_enabled( $alert ) {
			$alerts = \WSAL\Helpers\Options::get_option_value_ignore_prefix( self::OPTIONS_PREFIX . 'disabled-alerts' );
			$alerts = explode( ',', $alerts );
			return ! in_array( $alert, $alerts );
		}

		/**
		 * MainWP Dashboard Handler.
		 *
		 * @since 3.2.5
		 *
		 * @param array $info      – Information to return.
		 * @param array $post_data – Post data array from MainWP.
		 * @return mixed
		 */
		public function mainwp_dashboard_callback( $info, $post_data ) {
			if ( isset( $post_data['action'] ) ) {
				switch ( $post_data['action'] ) {
					case 'check_wsal':
						$info                 = new stdClass();
						$info->wsal_installed = true;
						$info->is_premium     = false;
						break;

					case 'get_events':
						$limit      = isset( $post_data['events_count'] ) ? $post_data['events_count'] : false;
						$offset     = isset( $post_data['events_offset'] ) ? $post_data['events_offset'] : false;
						$query_args = isset( $post_data['query_args'] ) ? $post_data['query_args'] : false;
						$info       = $this->alerts->get_mainwp_extension_events( $limit, $offset, $query_args );
						break;

					case 'get_report':
						$filters     = isset( $post_data['filters'] ) ? $post_data['filters'] : array();
						$report_type = isset( $post_data['report_type'] ) ? $post_data['report_type'] : false;
						$info        = $this->alerts->get_mainwp_extension_report( $filters, $report_type );
						break;

					case 'latest_event':
						// run the query and return it.
						$event = $this->query_for_latest_event();
						$event = $event->getAdapter()->Execute( $event );

						// Set the return object.
						if ( isset( $event[0] ) ) {
							$info             = new stdClass();
							$info->alert_id   = $event[0]->alert_id;
							$info->created_on = $event[0]->created_on;
						} else {
							$info = false;
						}
						break;

					default:
						break;
				}
			}
			return $info;
		}

		/**
		 * Performs a query to retrieve the latest event in the logs.
		 *
		 * @method query_for_latest_event
		 * @since  4.0.3
		 * @return array
		 */
		public function query_for_latest_event() {
			$event_query = new WSAL_Models_OccurrenceQuery();
			// order by creation.
			$event_query->addOrderBy( 'created_on', true );
			// only request 1 item.
			$event_query->setLimit( 1 );
			return $event_query;
		}

		/**
		 * Method: WSAL plugin redirect.
		 */
		public function wsal_plugin_redirect() {
			// WSAL State.
			$wsal_state = $this->GetGlobalSetting( 'freemius_state', 'anonymous' );

			if (
				$this->GetGlobalSetting( 'redirect_on_activate', false ) // Redirect flag.
				&& in_array( $wsal_state, array( 'anonymous', 'skipped' ), true )
			) {
				// If the redirect option is true, then continue.
				$this->include_options_helper();
				$this->options_helper->delete_option( 'wsal_redirect_on_activate' ); // Delete redirect option.

				// Redirect URL.
				$redirect = '';

				// If current site is multisite and user is super-admin then redirect to network audit log.
				if ( $this->IsMultisite() && $this->settings()->CurrentUserCan( 'edit' ) && is_super_admin() ) {
					$redirect = add_query_arg( 'page', 'wsal-auditlog', network_admin_url( 'admin.php' ) );
				} else {
					// Otherwise redirect to main audit log view.
					$redirect = add_query_arg( 'page', 'wsal-auditlog', admin_url( 'admin.php' ) );
				}
				wp_safe_redirect( $redirect );
				exit();
			}

		}

		/**
		 * Method: Set allowed  HTML tags.
		 *
		 * @since 3.0.0
		 */
		public function set_allowed_html_tags() {
			// Set allowed HTML tags.
			$this->allowed_html_tags = array(
				'a'      => array(
					'href'   => array(),
					'title'  => array(),
					'target' => array(),
				),
				'br'     => array(),
				'code'   => array(),
				'em'     => array(),
				'strong' => array(),
				'p'      => array(
					'class' => array(),
				),
			);
		}

		/**
		 * Method: Define constants.
		 *
		 * @since 2.6.6
		 */
		public function define_constants() {
			// Plugin version.
			if ( ! defined( 'WSAL_VERSION' ) ) {
				define( 'WSAL_VERSION', $this->version );
			}
			// Plugin Name.
			if ( ! defined( 'WSAL_BASE_NAME' ) ) {
				define( 'WSAL_BASE_NAME', plugin_basename( __FILE__ ) );
			}
			// Plugin Directory URL.
			if ( ! defined( 'WSAL_BASE_URL' ) ) {
				define( 'WSAL_BASE_URL', plugin_dir_url( __FILE__ ) );
			}
			// Plugin Directory Path.
			if ( ! defined( 'WSAL_BASE_DIR' ) ) {
				define( 'WSAL_BASE_DIR', plugin_dir_path( __FILE__ ) );
			}
			// Plugin Docs URL.
			if ( ! defined( 'WSAL_DOCS_URL' ) ) {
				define( 'WSAL_DOCS_URL', 'https://wpactivitylog.com/support/' );
			}
			// Plugin Issue Reporting URL.
			if ( ! defined( 'WSAL_ISSUE_URL' ) ) {
				define( 'WSAL_ISSUE_URL', 'https://wordpress.org/support/plugin/wp-security-audit-log' );
			}
			// Plugin Classes Prefix.
			if ( ! defined( 'WSAL_CLASS_PREFIX' ) ) {
				define( 'WSAL_CLASS_PREFIX', 'WSAL_' );
			}
		}
		/**
		 * Customize Freemius connect message for new users.
		 *
		 * @param string $message - Connect message.
		 * @param string $user_first_name - User first name.
		 * @param string $plugin_title - Plugin title.
		 * @param string $user_login - Username.
		 * @param string $site_link - Site link.
		 * @param string $_freemius_link - Freemius link.
		 * @return string
		 */
		public function wsal_freemius_connect_message( $message, $user_first_name, $plugin_title, $user_login, $site_link, $_freemius_link ) {
			$freemius_link = '<a href="https://wpactivitylog.com/support/kb/non-sensitive-diagnostic-data/" target="_blank" tabindex="1">freemius.com</a>';
			return sprintf(
				/* translators: Username */
				esc_html__( 'Hey %1$s', 'wp-security-audit-log' ) . ',<br>' .
				esc_html__( 'Never miss an important update! Opt-in to our security and feature updates notifications, and non-sensitive diagnostic tracking with freemius.com.', 'wp-security-audit-log' ) .
				'<br /><br /><strong>' . esc_html__( 'Note: ', 'wp-security-audit-log' ) . '</strong>' .
				esc_html__( 'NO ACTIVITY LOG ACTIVITY & DATA IS SENT BACK TO OUR SERVERS.', 'wp-security-audit-log' ),
				$user_first_name,
				'<b>' . $plugin_title . '</b>',
				'<b>' . $user_login . '</b>',
				$site_link,
				$freemius_link
			);
		}

		/**
		 * Customize Freemius connect message on update.
		 *
		 * @param string $message - Connect message.
		 * @param string $user_first_name - User first name.
		 * @param string $plugin_title - Plugin title.
		 * @param string $user_login - Username.
		 * @param string $site_link - Site link.
		 * @param string $_freemius_link - Freemius link.
		 * @return string
		 */
		public function wsal_freemius_update_connect_message( $message, $user_first_name, $plugin_title, $user_login, $site_link, $_freemius_link ) {
			$freemius_link = '<a href="https://wpactivitylog.com/support/kb/non-sensitive-diagnostic-data/" target="_blank" tabindex="1">freemius.com</a>';
			return sprintf(
				/* translators: Username */
				esc_html__( 'Hey %1$s', 'wp-security-audit-log' ) . ',<br>' .
				/* translators: 1: Plugin name. 2: Freemius link. */
				esc_html__( 'Please help us improve %2$s! If you opt-in, some non-sensitive data about your usage of %2$s will be sent to %5$s, a diagnostic tracking service we use. If you skip this, that\'s okay! %2$s will still work just fine.', 'wp-security-audit-log' ) .
				'<br /><br /><strong>' . esc_html__( 'Note: ', 'wp-security-audit-log' ) . '</strong>' .
				esc_html__( 'NO ACTIVITY LOG ACTIVITY & DATA IS SENT BACK TO OUR SERVERS.', 'wp-security-audit-log' ),
				$user_first_name,
				'<b>' . $plugin_title . '</b>',
				'<b>' . $user_login . '</b>',
				$site_link,
				$freemius_link
			);
		}

		/**
		 * Filter trial message of Freemius.
		 *
		 * @param string $_message – Trial message.
		 * @return string
		 * @since 3.2.3
		 */
		public function freemius_trial_promotion_message( $_message ) {
			// Message.
			$message = sprintf(
				/* translators: Plugin name */
				__( 'Get a free 7-day trial of the premium edition of %s. No credit card required, no commitments!', 'wp-security-audit-log' ),
				'<strong>' . __( 'WP Activity Log', 'wp-security-audit-log' ) . '</strong>'
			);

			// Trial link.
			$message .= '<a style="margin-left: 10px; vertical-align: super;" href="' . wsal_freemius()->get_trial_url() . '"><button class="button button-primary">' . __( 'Start free trial', 'wp-security-audit-log' ) . ' &nbsp;&#10140;</button></a>';
			return $message;
		}

		/**
		 * Filter the time period to show the first trial message.
		 * Display it after 20 days.
		 *
		 * @param int $day_in_sec – Time period in seconds.
		 * @return int
		 * @since 3.2.3
		 */
		public function change_show_first_trial_period( $day_in_sec ) {
			return 20 * DAY_IN_SECONDS;
		}

		/**
		 * Filter the time period to re-show the trial message.
		 * Display it after 60 days.
		 *
		 * @param int $thirty_days_in_sec – Time period in seconds.
		 * @return int
		 * @since 3.2.3
		 */
		public function change_reshow_trial_period( $thirty_days_in_sec ) {
			return 60 * DAY_IN_SECONDS;
		}

		/**
		 * Fremius Admin Notice View Permission.
		 *
		 * Check to see if the user has permission to view freemius
		 * admin notices or not.
		 *
		 * @since 3.3
		 *
		 * @param bool  $show – If show then set to true, otherwise false.
		 * @param array $msg {
		 *     @var string $message The actual message.
		 *     @var string $title An optional message title.
		 *     @var string $type The type of the message ('success', 'update', 'warning', 'promotion').
		 *     @var string $id The unique identifier of the message.
		 *     @var string $manager_id The unique identifier of the notices manager. For plugins it would be the plugin's slug, for themes - `<slug>-theme`.
		 *     @var string $plugin The product's title.
		 *     @var string $wp_user_id An optional WP user ID that this admin notice is for.
		 * }
		 * @return bool
		 */
		public function freemius_show_admin_notice( $show, $msg ) {
			if ( $this->settings()->CurrentUserCan( 'edit' ) ) {
				return $show;
			}
			return false;
		}

		/**
		 * Changes some of the strings that freemius outputs with out own.
		 *
		 * @method adjust_freemius_strings
		 * @since  4.0.0
		 */
		public function adjust_freemius_strings() {
			// only update these messages if using premium plugin.
			if ( ( ! wsal_freemius()->is_premium() ) || ( ! method_exists( wsal_freemius(), 'override_il8n' ) ) ) {
				return;
			}
			wsal_freemius()->override_i18n(
				array(
					'few-plugin-tweaks' => __( 'You need to activate the licence key to use WP Activity Log Premium. %2$s', 'wp-security-audit-log' ),
					'optin-x-now'       => __( 'Activate the licence key now', 'wp-security-audit-log' ),
				)
			);
		}

		/**
		 * Limited License Activation Error.
		 *
		 * @param string $error - Error Message.
		 * @return string
		 */
		public function limited_license_activation_error( $error ) {
			$site_count = null;
			preg_match( '!\d+!', $error, $site_count );

			if ( ! empty( $site_count[0] ) ) {
				/* Translators: Number of sites */
				$error = sprintf( esc_html__( 'The license is limited to %s sub-sites. You need to upgrade your license to cover all the sub-sites on this network.', 'wp-security-audit-log' ), $site_count[0] );
			}
			return $error;
		}

		/**
		 * Start to trigger the events after installation.
		 *
		 * @internal
		 */
		public function init() {
			// Load dependencies.
			if ( ! isset( $this->alerts ) ) {
				$this->alerts = new WSAL_AlertManager( $this );
			}

			if ( ! isset( $this->constants ) ) {
				$this->constants = new WSAL_ConstantManager();
			}

			$this->sensors = new WSAL_SensorManager( $this );

			if ( is_admin() ) {
				$this->views     = new WSAL_ViewManager( $this );
				$this->widgets   = new WSAL_WidgetManager( $this );
			}

			// Start listening to events.
			if ( ! empty( $this->sensors ) && $this->sensors instanceof WSAL_SensorManager ) {
				$this->sensors->HookEvents();
			}

			if ( is_admin() ) {
				if ( $this->settings()->IsArchivingEnabled() ) {
					// Check the current page.
					$get_page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
					if ( ( ! isset( $get_page ) || 'wsal-auditlog' !== $get_page ) && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
						$selected_db      = get_transient( 'wsal_wp_selected_db' );
						$selected_db_user = (int) get_transient( 'wsal_wp_selected_db_user' );
						if ( $selected_db && ( get_current_user_id() === $selected_db_user ) ) {
							// Delete the transient.
							delete_transient( 'wsal_wp_selected_db' );
							delete_transient( 'wsal_wp_selected_db_user' );
						}
					}
				}

				// Hide plugin.
				if ( $this->settings()->IsIncognito() ) {
					add_action( 'admin_head', array( $this, 'HidePlugin' ) );
					add_filter( 'all_plugins', array( $this, 'wsal_hide_plugin' ) );
				}

				// Update routine.
				$old_version = $this->GetOldVersion();
				$new_version = $this->GetNewVersion();
				if ( $old_version !== $new_version ) {
					$this->Update( $old_version, $new_version );
				}

				// Generate index.php for uploads directory.
				$this->settings()->generate_index_files();
			}

			/**
			 * Action: `wsal_init`
			 *
			 * Action hook to mark that WSAL has initialized.
			 *
			 * @param WpSecurityAuditLog $this – Instance of main plugin class.
			 */
			do_action( 'wsal_init', $this );
		}

		/**
		 * Plugin Deactivation Actions.
		 *
		 * This function runs on plugin deactivation to send
		 * deactivation email.
		 *
		 * @since 3.3.1
		 */
		public function deactivate_actions() {
			/**
			 * Allow short circuiting of the deactivation email sending by using
			 * this filter to return true here instead of default false.
			 *
			 * @since 3.5.2
			 *
			 * @var bool
			 */
			if ( apply_filters( 'wsal_filter_prevent_deactivation_email_delivery', false ) ) {
				return;
			}

			// Send deactivation email.
			if ( class_exists( 'WSAL_Utilities_Emailer' ) ) {
				// Get email template.
				WSAL_Utilities_Emailer::send_deactivation_email();
			}
		}

		/**
		 * Disable Custom Field through ajax.
		 *
		 * @internal
		 */
		public function AjaxDisableCustomField() {
			// Die if user does not have permission to disable.
			if ( ! $this->settings()->CurrentUserCan( 'edit' ) ) {
				echo '<p>' . esc_html__( 'Error: You do not have sufficient permissions to disable this custom field.', 'wp-security-audit-log' ) . '</p>';
				die();
			}

			// Set filter input args.
			$filter_input_args = array(
				'disable_nonce' => FILTER_SANITIZE_STRING,
				'notice'        => FILTER_SANITIZE_STRING,
			);

			// Filter $_POST array for security.
			$post_array = filter_input_array( INPUT_POST, $filter_input_args );

			if ( isset( $post_array['disable_nonce'] ) && ! wp_verify_nonce( $post_array['disable_nonce'], 'disable-custom-nonce' . $post_array['notice'] ) ) {
				die();
			}

			$fields = $this->GetGlobalSetting( 'excluded-custom' );
			if ( isset( $fields ) && '' != $fields ) {
				$fields .= ',' . esc_html( $post_array['notice'] );
			} else {
				$fields = esc_html( $post_array['notice'] );
			}
			$this->SetGlobalSetting( 'excluded-custom', $fields );

			// Exclude object link.
			$exclude_objects_link = add_query_arg(
				array(
					'page' => 'wsal-settings',
					'tab'  => 'exclude-objects',
				),
				admin_url( 'admin.php' )
			);
			echo wp_sprintf( '<p>' . __( 'Custom Field %1$s is no longer being monitored.<br />Enable the monitoring of this custom field again from the', 'wp-security-audit-log' ) . ' <a href="%2$s">%3$s</a>%4$s</p>', $post_array['notice'], $exclude_objects_link, __( 'Excluded Objects', 'wp-security-audit-log' ), __( ' tab in the plugin settings', 'wp-security-audit-log' ) );
			die;
		}

		/**
		 * Disable Alert through ajax.
		 *
		 * @internal
		 */
		public function AjaxDisableByCode() {
			// Die if user does not have permission to disable.
			if ( ! $this->settings()->CurrentUserCan( 'edit' ) ) {
				echo '<p>' . esc_html__( 'Error: You do not have sufficient permissions to disable this alert.', 'wp-security-audit-log' ) . '</p>';
				die();
			}

			// Set filter input args.
			$filter_input_args = array(
				'disable_nonce' => FILTER_SANITIZE_STRING,
				'code'          => FILTER_SANITIZE_STRING,
			);

			// Filter $_POST array for security.
			$post_array = filter_input_array( INPUT_POST, $filter_input_args );

			if ( isset( $post_array['disable_nonce'] ) && ! wp_verify_nonce( $post_array['disable_nonce'], 'disable-alert-nonce' . $post_array['code'] ) ) {
				die();
			}

			$s_alerts = $this->GetGlobalSetting( 'disabled-alerts' );
			if ( isset( $s_alerts ) && '' != $s_alerts ) {
				$s_alerts .= ',' . esc_html( $post_array['code'] );
			} else {
				$s_alerts = esc_html( $post_array['code'] );
			}
			$this->SetGlobalSetting( 'disabled-alerts', $s_alerts );
			echo wp_sprintf( '<p>' . __( 'Alert %1$s is no longer being monitored.<br /> %2$s', 'wp-security-audit-log' ) . '</p>', esc_html( $post_array['code'] ), __( 'You can enable this alert again from the Enable/Disable Alerts node in the plugin menu.', 'wp-security-audit-log' ) );
			die;
		}

		/**
		 * Render plugin stuff in page footer.
		 *
		 * @internal
		 */
		public function render_footer() {
			// Register common script.
			wp_register_script(
				'wsal-common',
				$this->GetBaseUrl() . '/js/common.js',
				array( 'jquery' ),
				filemtime( $this->GetBaseDir() . '/js/common.js' ),
				true
			);

			//  live events disabled in free version of the plugin
			$live_events_enabled = false;
			// Set data array for common script.
			$script_data = array(
				'ajaxURL'           => admin_url( 'admin-ajax.php' ),
				'liveEvents'        => $live_events_enabled,
				'installing'        => __( 'Installing, please wait', 'wp-security-audit-log' ),
				'already_installed' => __( 'Already installed', 'wp-security-audit-log' ),
				'installed'         => __( 'Extension installed', 'wp-security-audit-log' ),
				'activated'         => __( 'Extension activated', 'wp-security-audit-log' ),
				'failed'            => __( 'Install failed', 'wp-security-audit-log' ),
			);

			wp_localize_script( 'wsal-common', 'wsalCommonData', $script_data );

			// Enqueue script.
			wp_enqueue_script( 'wsal-common' );
		}

		/**
		 * Load the rest of the system.
		 *
		 * @internal
		 */
		public function load_wsal() {
			require_once 'classes/Alert.php';
			require_once 'classes/AbstractLogger.php';
			require_once 'classes/AbstractSensor.php';
			require_once 'classes/AbstractMetaDataSensor.php';
			require_once 'classes/AlertManager.php';
			require_once 'classes/ConstantManager.php';
			require_once 'classes/Loggers/Database.php';
			require_once 'classes/SensorManager.php';
			require_once 'classes/Sensors/Public.php';
			require_once 'classes/Settings.php';

			if ( is_admin() ) {
				$this->options = new WSAL_Models_Option();
				if ( ! $this->options->IsInstalled() ) {
					$this->options->Install();

					// Initiate settings object if not set.
					if ( ! $this->settings ) {
						$this->settings = new WSAL_Settings( $this );
					}

					// Setting the prunig date with the old value or the default value.
					$pruning_date = $this->settings()->GetPruningDate();
					$this->settings()->SetPruningDate( $pruning_date );
				}

				$log_404 = $this->GetGlobalSetting( 'log-404' );
				// If old setting is empty enable 404 logging by default.
				if ( false === $log_404 ) {
					$this->SetGlobalBooleanSetting( 'log-404', true );
				}

				$purge_log_404 = $this->GetGlobalSetting( 'purge-404-log' );
				// If old setting is empty enable 404 purge log by default.
				if ( false === $purge_log_404 ) {
					$this->SetGlobalBooleanSetting( 'purge-404-log', true );
				}

				// Load translations.
				load_plugin_textdomain( 'wp-security-audit-log', false, basename( dirname( __FILE__ ) ) . '/languages/' );
			}
		}

		/**
		 * Install all assets required for a useable system.
		 * @throws Freemius_Exception
		 */
		public function Install() {
			$installation_errors = false;

			// Check for minimum PHP version.
			if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION ) < 0 ) {
				/* Translators: %s: PHP Version */
				$installation_errors  = sprintf( esc_html__( 'You are using a version of PHP that is older than %s, which is no longer supported.', 'wp-security-audit-log' ), esc_html( self::MIN_PHP_VERSION ) );
				$installation_errors .= '<br />';
				$installation_errors .= __( 'Contact us on <a href="mailto:plugins@wpwhitesecurity.com">plugins@wpwhitesecurity.com</a> to help you switch the version of PHP you are using.', 'wp-security-audit-log' );
			} elseif ( $this->IsMultisite() && is_super_admin() && ! is_network_admin() ) {
				$installation_errors  = esc_html__( 'The WP Activity Log plugin is a multisite network tool, so it has to be activated at network level.', 'wp-security-audit-log' );
				$installation_errors .= '<br />';
				$installation_errors .= '<a href="javascript:;" onclick="window.top.location.href=\'' . esc_url( network_admin_url( 'plugins.php' ) ) . '\'">' . esc_html__( 'Redirect me to the network dashboard', 'wp-security-audit-log' ) . '</a> ';
			}

			if ( self::is_plugin_active( 'mainwp/mainwp.php' ) ) {
				/* Translators: %s: Activity Log for MainWP plugin hyperlink */
				$installation_errors = sprintf( __( 'Please install the %s plugin on the MainWP dashboard.', 'wp-security-audit-log' ), '<a href="https://wordpress.org/plugins/activity-log-mainwp/" target="_blank">' . __( 'Activity Log for MainWP', 'wp-security-audit-log' ) . '</a>' ) . ' ';
				/* Translators: %s: Getting started guide hyperlink */
				$installation_errors .= sprintf( __( 'The WP Activity Log should be installed on the child sites only. Refer to the %s for more information.', 'wp-security-audit-log' ), '<a href="https://wpactivitylog.com/support/kb/gettting-started-activity-log-mainwp-extension/" target="_blank">' . __( 'getting started guide', 'wp-security-audit-log' ) . '</a>' );
			}

			if ( $installation_errors ) {
				?>
				<html>
					<head><style>body{margin:0;}.warn-icon-tri{top:7px;left:5px;position:absolute;border-left:16px solid #FFF;border-right:16px solid #FFF;border-bottom:28px solid #C33;height:3px;width:4px}.warn-icon-chr{top:10px;left:18px;position:absolute;color:#FFF;font:26px Georgia}.warn-icon-cir{top:4px;left:0;position:absolute;overflow:hidden;border:6px solid #FFF;border-radius:32px;width:34px;height:34px}.warn-wrap{position:relative;font-size:13px;font-family:sans-serif;padding:6px 48px;line-height:1.4;}</style></head>
					<body><div class="warn-wrap"><div class="warn-icon-tri"></div><div class="warn-icon-chr">!</div><div class="warn-icon-cir"></div><span><?php echo $installation_errors; // @codingStandardsIgnoreLine ?></span></div></body>
				</html>
				<?php
				die( 1 );
			}

			// Fully set up the plugin.
			$this->setup();
			$this->init();

			// Ensure that the system is installed and schema is correct.
			$pre_installed = $this->IsInstalled();

			// On first install this won't be loaded because not premium, add it
			// now so it installs.
			if ( file_exists( plugin_dir_path( __FILE__ ) . 'extensions/user-sessions/user-sessions.php' ) ) {
				$this->maybe_add_sessions_trackers_early();
				require_once plugin_dir_path( __FILE__ ) . 'extensions/user-sessions/user-sessions.php';
				$sessions = new WSAL_UserSessions_Plugin();
				$sessions->require_adapter_classes();
			}

			// run any installs.
			self::getConnector()->installAll();

			if ( ! $pre_installed ) {
				self::getConnector()->getAdapter( 'Occurrence' )->create_indexes();
				self::getConnector()->getAdapter( 'Meta' )->create_indexes();
				self::getConnector()->getAdapter( 'Option' )->create_indexes();

				if ( $this->settings()->IsArchivingEnabled() ) {
					$this->settings()->SwitchToArchiveDB();
					self::getConnector()->getAdapter( 'Occurrence' )->create_indexes();
					self::getConnector()->getAdapter( 'Meta' )->create_indexes();
				}
			}

			// If system already installed, do updates now (if any).
			$old_version = $this->GetOldVersion();
			$new_version = $this->GetNewVersion();

			if ( $pre_installed && $old_version !== $new_version ) {
				$this->Update( $old_version, $new_version );
			}

			// If system wasn't installed, try migration now.
			if ( ! $pre_installed && $this->CanMigrate() ) {
				$this->Migrate();
			}

			// Setting the prunig date with the old value or the default value.
			$old_disabled = $this->GetGlobalSetting( 'disabled-alerts' );

			// If old setting is empty disable alert 2099 by default.
			if ( empty( $old_disabled ) ) {
				$this->settings()->SetDisabledAlerts( array( 2099, 2126 ) );
			}

			$log_404 = $this->GetGlobalSetting( 'log-404' );
			// If old setting is empty enable 404 logging by default.
			if ( false === $log_404 ) {
				$this->SetGlobalBooleanSetting( 'log-404', true );
			}

			$purge_log_404 = $this->GetGlobalSetting( 'purge-404-log' );
			// If old setting is empty enable 404 purge log by default.
			if ( false === $purge_log_404 ) {
				$this->SetGlobalBooleanSetting( 'purge-404-log', true );
			}

			// Install cleanup hook (remove older one if it exists).
			wp_clear_scheduled_hook( 'wsal_cleanup' );
			wp_schedule_event( current_time( 'timestamp' ) + 600, 'hourly', 'wsal_cleanup' );

			// WSAL Audit Log page redirect option in anonymous mode.
			if ( 'anonymous' === $this->GetGlobalSetting( 'freemius_state', 'anonymous' ) ) {
				$this->SetGlobalSetting( 'redirect_on_activate', true );
			}

			// Run on each install to check MainWP Child plugin.
			$this->settings()->set_mainwp_child_stealth_mode();

			// If plugin tables have not installed correctly then don't activate the plugin.
			if ( ! $this->IsInstalled() ) :
				?>
				<html>
					<head><style>body{margin:0;}.warn-icon-tri{top:7px;left:5px;position:absolute;border-left:16px solid #FFF;border-right:16px solid #FFF;border-bottom:28px solid #C33;height:3px;width:4px}.warn-icon-chr{top:10px;left:18px;position:absolute;color:#FFF;font:26px Georgia}.warn-icon-cir{top:4px;left:0;position:absolute;overflow:hidden;border:6px solid #FFF;border-radius:32px;width:34px;height:34px}.warn-wrap{position:relative;color:#A00;font-size:13px;font-family:sans-serif;padding:6px 48px;line-height:1.4;}.warn-wrap a,.warn-wrap a:hover{color:#F56}</style></head>
					<body>
						<div class="warn-wrap">
							<div class="warn-icon-tri"></div><div class="warn-icon-chr">!</div><div class="warn-icon-cir"></div>
							<?php esc_html_e( 'This plugin uses 3 tables in the WordPress database to store the activity log and settings. It seems that these tables were not created.', 'wp-security-audit-log' ); ?>
							<br />
							<?php esc_html_e( 'This could happen because the database user does not have the right privileges to create the tables in the database. We recommend you to update the privileges and try enabling the plugin again.', 'wp-security-audit-log' ); ?>
							<br />
							<?php /* Translators: %s: Support Hyperlink */ echo sprintf( esc_html__( 'If after doing so you still have issues, please send us an email on %s for assistance.', 'wp-security-audit-log' ), '<a href="mailto:support@wpsecurityauditlog.com" target="_blank">' . esc_html__( 'support@wpsecurityauditlog.com', 'wp-security-audit-log' ) . '</a>' ); ?>
						</div>
					</body>
				</html>
				<?php
				die( 1 );
			endif;
		}

		/**
		 * Run some code that updates critical components required for a newer version.
		 *
		 * @param string $old_version The old version.
		 * @param string $new_version The new version.
		 *
		 * @throws Freemius_Exception
		 */
		public function Update( $old_version, $new_version ) {
			// Update version in db.
			$this->SetGlobalSetting( 'version', $new_version );

			// Do version-to-version specific changes.
			if ( '0.0.0' !== $old_version && -1 === version_compare( $old_version, $new_version ) ) {
				// Update pruning alerts option if purning limit is enabled for backwards compatibility.
				if ( $this->settings()->IsPruningLimitEnabled() ) {
					$pruning_date = '6';
					$pruning_unit = 'months';
					$this->settings()->SetPruningDate( $pruning_date . ' ' . $pruning_unit );
					$this->settings()->SetPruningDateEnabled( true );
					$this->settings()->SetPruningLimitEnabled( false );
				}

				// Dismiss privacy notice.
				if ( empty( $this->views ) ) {
					$this->views = new WSAL_ViewManager( $this );
				}
				$this->views->FindByClassName( 'WSAL_Views_AuditLog' )->DismissNotice( 'wsal-privacy-notice-3.2' );

				/**
				 * Delete advert transient on every update.
				 *
				 * @since 3.2.4
				 */
				if ( wsal_freemius()->is_free_plan() ) {
					$delete_transient_fn = $this->IsMultisite() ? 'delete_site_transient' : 'delete_transient'; // Check for multisite.
					$delete_transient_fn( 'wsal-is-advert-dismissed' ); // Delete advert transient.
				}

				/**
				 * IMPORTANT: VERSION SPECIFIC UPDATE
				 *
				 * It only needs to run when old version of the plugin is later than 3.0.0.
				 *
				 * @since 3.2.2.2
				 */
				if ( version_compare( $old_version, '3.0.0', '>' ) ) {
					// If the freemius state option does not exists then run this update.
					if ( false === get_site_option( 'wsal_freemius_state', false ) ) {
						// Check if the user has opted-in.
						if ( wsal_freemius()->is_registered() ) {
							// Update freemius state.
							update_site_option( 'wsal_freemius_state', 'in' );
						} else {
							// Update freemius state.
							update_site_option( 'wsal_freemius_state', 'skipped' );
						}

						// Remove connect account notice of Freemius.
						FS_Admin_Notices::instance( 'wp-security-audit-log' )->remove_sticky( 'connect_account' );
					}
				}

				/**
				 * MainWP Child Stealth Mode Update
				 *
				 * This update only needs to run if the stealth mode option
				 * does not exist on free version.
				 *
				 * @since 3.2.3.3
				 */
				if ( ! $this->GetGlobalBooleanSetting( 'mwp-child-stealth-mode', false ) ) {
					$this->settings()->set_mainwp_child_stealth_mode();
				}

				/**
				 * IMPORTANT: VERSION SPECIFIC UPDATE
				 *
				 * It only needs to run when old version of the plugin is less than 3.4.2
				 * & the new version is later than 3.4.1.1.
				 *
				 * @since 3.4.2
				 */
				if ( version_compare( $old_version, '3.4.2', '<' ) && version_compare( $new_version, '3.4.1.1', '>' ) ) {
					self::getConnector()->getAdapter( 'Occurrence' )->create_indexes();
					self::getConnector()->getAdapter( 'Meta' )->create_indexes();
					self::getConnector()->getAdapter( 'Option' )->create_indexes();

					if ( $this->settings()->IsArchivingEnabled() ) {
						$this->settings()->SwitchToArchiveDB();
						self::getConnector()->getAdapter( 'Occurrence' )->create_indexes();
						self::getConnector()->getAdapter( 'Meta' )->create_indexes();
					}
				}

				/**
				 * IMPORTANT: VERSION SPECIFIC UPDATE
				 *
				 * It only needs to run when old version of the plugin is less than 3.5
				 * & the new version is later than 3.4.3.1.
				 *
				 * @since 3.5
				 */
				if ( version_compare( $old_version, '3.5', '<' ) && version_compare( $new_version, '3.4.3.1', '>' ) ) {
					$frontend_events = array(
						'register'    => true, // Enabled by default to ensure users to not loose any functionality.
						'login'       => true, // Enabled by default to ensure users to not loose any functionality.
						'system'      => false,
						'woocommerce' => self::is_woocommerce_active(),
					);

					// If event 6023 is enabled.
					if ( self::raw_alert_is_enabled( 6023 ) ) {
						$frontend_events['system'] = true; // Then enable it for the frontend.
					}

					if ( self::is_woocommerce_active() ) {
						$frontend_events['woocommerce'] = true;
					}

					$this->settings()->set_frontend_events( $frontend_events );
				}

				if ( version_compare( $old_version, '4.0.0', '<=' ) ) {
					/*
					 * Ensure that the grid view 'info' column is set to display.
					 */
					add_action(
						'init',
						function() {
							$cols = $this->settings()->GetColumns();
							// if the `info` col does not exist in the array then add it now.
							if ( ! isset( $cols['info'] ) ) {
								// add this at position 3 in the array.
								$cols = array_slice( $cols, 0, 2, true ) + array( 'info' => '1' ) + array_slice( $cols, 2, null, true );
								$this->settings()->SetColumns( $cols );
							} else {
								$cols['info'] = '1';
								$this->settings()->SetColumns( $cols );
							}
						}
					);
				}

				/*
				 * From version 4.0.2 onwards options have been getting moved
				 * over to the standard WP options table. This handles migrating
				 * any for each new version we release.
				 *
				 * @since 4.0.3
				 * @since 4.1.0 - updated the compare tag.
				 */
				if ( version_compare( $old_version, '4.1.0', '<=' ) ) {

					require_once 'classes/Update/Task/MoveSettingsToOptionsTable.php';
					// run the update routine.
					$settings_mover = new WSAL\Update\Task\MoveSettingsToOptionsTable( self::GetInstance() );
					$settings_mover->set_versions( $old_version, $new_version );
					$settings_mover->run();

				}

				/*
				 * Remove legacy options related to plugin permissions.
				 *
				 * @since 4.1.3
				 */
				if ( version_compare( $old_version, '4.1.3', '<=' ) ) {

					/*
					 * Rename 'wsal-frontend-events' to 'wsal_frontend-events' to follow the naming convention of all
					 * other options.
					 */
					if ( function_exists( 'switch_to_blog' ) ) {
						switch_to_blog( 1 );
					}

					global $wpdb;
					$wpdb->update( $wpdb->options, [
						'option_name' => 'wsal_frontend-events'
					], [
						'option_name' => 'wsal-frontend-events'
					] );

					if ( is_multisite() ) {
						$wpdb->update( $wpdb->sitemeta, [
							'meta_key' => 'wsal_frontend-events'
						], [
							'meta_key' => 'wsal-frontend-events'
						] );
					}

					//  rename couple of legacy options with duplicated prefix
					/** @var WSAL_Adapters_MySQL_Option $options_adapter */
					$options_adapter = $this->options->getAdapter();
					$options_adapter->Rename( 'wsal-wsal-setup-modal-dismissed', 'wsal-setup-modal-dismissed' );
					$options_adapter->Rename( 'wsal-wsal-setup-complete', 'wsal-setup-complete' );

					//  migrate all options from sitemeta to the options table on master site
					if ( is_multisite() ) {
						$site_meta_data = $wpdb->get_results( "SELECT * FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'wsal%' AND meta_key != 'wsal_networkwide_tracker_cpts';" );
						if ( ! empty( $site_meta_data ) ) {
							foreach ( $site_meta_data as $site_meta_entry ) {
								$setting_name = $site_meta_entry->meta_key;

								// the wsal- prefix needs stripped from this option.
								if ( false !== ( 'wsal-' === substr( $setting_name, 0, 5 ) ) ) {
									$setting_name = str_replace( 'wsal-', '', $setting_name );
								}

								//  we don't mind overriding the value if its already there
								$this->SetGlobalSetting( $setting_name, $site_meta_entry->meta_value );

								delete_network_option( $site_meta_entry->site_id, $site_meta_entry->meta_key );
							}
						}
					}

					//  use first existing user from the list of editors as the user id with access in case "only me"
					//  access option is selected
					$plugin_editors = $this->GetGlobalSetting( 'plugin-editors', [] );
					if ( ! empty( $plugin_editors ) ) {
						foreach ( explode( ',', $plugin_editors ) as $user_login ) {
							$user = get_user_by( 'login', trim( $user_login ) );
							if ( $user instanceof WP_User ) {
								$this->settings()->set_only_me_user_id( $user->ID );
								break;
							}
						}
					}

					$this->DeleteByName( 'wsal-plugin-editors' );
					$this->DeleteByName( 'wsal-restrict-admins' );
                    $this->DeleteSettingByName( 'wsal_plugin-editors' );
                    $this->DeleteSettingByName( 'wsal_restrict-admins' );

					//  migrate remaining settings to WordPress options table
					require_once 'classes/Update/Task/MoveSettingsToOptionsTable.php';
					$settings_mover = new WSAL\Update\Task\MoveSettingsToOptionsTable( self::GetInstance() );
					$settings_mover->set_versions( $old_version, $new_version );
					$settings_mover->run();

					// Delete options transient.
					delete_transient( 'wsal_options' );

					if ( function_exists( 'restore_current_blog' ) ) {
						restore_current_blog();
					}

					//  delete the custom options table
					WSAL_Sensors_Database::$enabled = false;
					$options_adapter->Uninstall();
					WSAL_Sensors_Database::$enabled = true;

					//  delete settings from option on other sub sites on multisite (only leave settings on the main site)
					if (is_multisite()) {
						$sites = get_sites([
							'number' => 0,
							'offset' => 0
						]);
						if (!empty($sites)) {
							foreach ( $sites as $site ) {
								$wpdb->query("DELETE FROM {$wpdb->prefix}{$site->blog_id}_options WHERE option_name LIKE 'wsal%' AND option_name != 'wsal_networkwide_tracker_cpts';");
							}
						}
					}

					wp_cache_flush();

					//  delete the sessions table and recreate it from scratch (session token is used as primary key
					//  instead of an autoincrement ID column
					$extensions_manager = $this->extensions;
					//  we need to check if the extension manager is set for this to work in free version of the plugin
					if ( $extensions_manager != null ) {
						$extensions = $extensions_manager->extensions;
						if ( ! empty( $extensions ) ) {
							foreach ( $extensions as $extension ) {
								if ( 'WSAL_UserSessions_Plugin' === get_class( $extension ) ) {
									WSAL_Sensors_Database::$enabled = false;
									$adapter                        = WSAL_UserSessions_Plugin::get_sessions_adapter();
									$adapter->Uninstall();
									$adapter->Install();
									WSAL_Sensors_Database::$enabled = true;
								}
							}
						}
					}

					//  convert all boolean style settings to 'yes'/'no' values
                    //  on/off/0/1/true/false/enable/disable => yes/no
					$yes_no_settings = [
                        'adapter-use-buffer',
                        'admin-blocking-plugins-support',
                        'delete-data',
                        'disable-widgets',
                        'hide-plugin',
                        'is-url-shortner',
                        'log-404',
                        'log-404-referrer',
                        'log-visitor-404',
                        'log-visitor-404-referrer',
                        'login_page_notification',
                        'mwp-child-stealth-mode',
                        'pruning-date-e',
                        'pruning-limit-e',
                        'purge-404-log',
                        'purge-visitor-404-log',
                        'setup-complete',
                        'setup-modal-dismissed',
                        'show_milliseconds',
                        'use-proxy-ip',
                        'wc-all-stock-changes',
                        'wp-backend'
                    ];

					foreach ($yes_no_settings as $option_name) {
					    $current_value = $this->GetGlobalSetting($option_name);
                        $bool_value = \WSAL\Helpers\Options::string_to_bool($current_value);
                        $new_value = \WSAL\Helpers\Options::bool_to_string($bool_value);
                        $this->SetGlobalSetting($option_name, $new_value);
					}

					//  delete unused setting related to file changes scanner
					$this->DeleteSettingByName('wsal-scan-file-changes');
				}
			}
		}

		/**
		 * Migrate data from old plugin.
		 */
		public function Migrate() {
			global $wpdb;
			static $mig_types = array(
				3000 => 5006,
			);

			// Load data.
			$sql    = 'SELECT * FROM ' . $wpdb->base_prefix . 'wordpress_auditlog_events';
			$events = array();
			foreach ( $wpdb->get_results( $sql, ARRAY_A ) as $item ) {
				$events[ $item['EventID'] ] = $item;
			}
			$sql      = 'SELECT * FROM ' . $wpdb->base_prefix . 'wordpress_auditlog';
			$auditlog = $wpdb->get_results( $sql, ARRAY_A );

			// Migrate using db logger.
			foreach ( $auditlog as $entry ) {
				$data = array(
					'ClientIP'      => $entry['UserIP'],
					'UserAgent'     => '',
					'CurrentUserID' => $entry['UserID'],
				);
				if ( $entry['UserName'] ) {
					$data['Username'] = base64_decode( $entry['UserName'] );
				}
				$mesg = $events[ $entry['EventID'] ]['EventDescription'];
				$date = strtotime( $entry['EventDate'] );
				$type = $entry['EventID'];
				if ( isset( $mig_types[ $type ] ) ) {
					$type = $mig_types[ $type ];
				}
				// Convert message from '<strong>%s</strong>' to '%Arg1%' format.
				$c = 0;
				$n = '<strong>%s</strong>';
				$l = strlen( $n );
				while ( ( $pos = strpos( $mesg, $n ) ) !== false ) {
					$mesg = substr_replace( $mesg, '%MigratedArg' . ( $c++ ) . '%', $pos, $l );
				}
				$data['MigratedMesg'] = $mesg;
				// Generate new meta data args.
				$temp = unserialize( base64_decode( $entry['EventData'] ) );
				foreach ( (array) $temp as $i => $item ) {
					$data[ 'MigratedArg' . $i ] = $item;
				}
				// send event data to logger!
				foreach ( $this->alerts->GetLoggers() as $logger ) {
					$logger->Log( $type, $data, $date, $entry['BlogId'], true );
				}
			}

			// Migrate settings.
			$this->settings()->SetAllowedPluginViewers(
				get_option( 'WPPH_PLUGIN_ALLOW_ACCESS' )
			);
			$s = get_option( 'wpph_plugin_settings' );
			$this->settings()->SetViewPerPage( max( $s->showEventsViewList, 5 ) );
			$this->settings()->SetWidgetsEnabled( ! ! $s->showDW );
		}

		/**
		 * The current plugin version (according to plugin file metadata).
		 *
		 * @return string
		 */
		public function GetNewVersion() {
			$version = get_plugin_data( __FILE__, false, false );
			return isset( $version['Version'] ) ? $version['Version'] : '0.0.0';
		}

		/**
		 * The plugin version as stored in DB (will be the old version during an update/install).
		 *
		 * @return string
		 */
		public function GetOldVersion() {
			$old_version = $this->GetGlobalOption( 'version', '0.0.0' );

			/*
			 * This GetGlobalOption is retained for back compatibility for
			 * update routines before version 4.0.2.
			 *
			 * TODO: remove this AFTER version 4.1.0.
			 */
			if ( ! $old_version || '0.0.0' === $old_version ) {
				$old_version = $this->GetGlobalSetting( 'version', '0.0.0' );
			}
			return $old_version;
		}

		/**
		 * To be called in admin header for hiding plugin form Plugins list.
		 *
		 * @internal
		 */
		public function HidePlugin() {
			$selectr = '';
			$plugins = array( 'wp-security-audit-log', 'wp-security-audit-log-premium' );
			foreach ( $plugins as $value ) {
				$selectr .= '.wp-list-table.plugins tr[data-slug="' . $value . '"], ';
			}
			?>
			<style type="text/css">
				<?php echo rtrim( $selectr, ', ' ); ?> { display: none; }
			</style>
			<?php
		}

		/**
		 * Returns the class name of a particular file that contains the class.
		 *
		 * @param string $file - File name.
		 * @return string - Class name.
		 * @deprecated since 1.2.5 Use autoloader->GetClassFileClassName() instead.
		 */
		public function GetClassFileClassName( $file ) {
			return $this->autoloader->GetClassFileClassName( $file );
		}

		/**
		 * Return whether we are running on multisite or not.
		 *
		 * @return boolean
		 */
		public function IsMultisite() {
			return function_exists( 'is_multisite' ) && is_multisite();
		}

		/**
		 * Get a global option.
         *
         * Deprecated function. It is only kept for the upgrade routine. It should be removed in future releases.
		 *
		 * @param string $option  - Option name.
		 * @param mixed  $default - (Optional) Value returned when option is not set (defaults to false).
		 * @param string $prefix  - (Optional) A prefix used before option name.
		 * @return mixed - Option's value or $default if option not set.
         *
         * @deprecated 4.1.3 Use WpSecurityAuditLog::GetGlobalSetting instead
         * @see WpSecurityAuditLog::GetGlobalSetting()
		 */
		public function GetGlobalOption( $option, $default = false, $prefix = self::OPT_PRFX ) {
			if ( empty( $this->options ) ) {
				$this->options = new WSAL_Models_Option();
			}
			return $this->options->GetOptionValue( $prefix . $option, $default );
		}

		/**
		 * Get a global setting.
		 *
		 * @param string $option  - Option name.
		 * @param mixed  $default - (Optional) Value returned when option is not set (defaults to false).
		 * @param string $prefix  - (Optional) A prefix used before option name. Not used. Present only for API compatibility.
		 * @return mixed - Option's value or $default if option not set.
		 */
		public function GetGlobalSetting( $option, $default = false, $prefix = self::OPT_PRFX ) {
		    $this->include_options_helper();
			return $this->options_helper->get_option_value( $option, $default );
		}

		/**
		 * Set a global option.
		 *
         * Deprecated function. It is only kept for the upgrade routine. It should be removed in future releases.
         *
		 * @param string $option - Option name.
		 * @param mixed  $value - New value for option.
		 * @param string $prefix - (Optional) A prefix used before option name.
         *
         * @deprecated 4.1.3 Use WpSecurityAuditLog::SetGlobalSetting instead
		 * @see WpSecurityAuditLog::SetGlobalSetting()
		 */
		public function SetGlobalOption( $option, $value, $prefix = self::OPT_PRFX ) {
			if ( empty( $this->options ) ) {
				$this->options = new WSAL_Models_Option();
			}
			$this->options->SetOptionValue( $prefix . $option, $value );

			// Delete options transient.
			delete_transient( 'wsal_options' );
		}

		/**
		 * Set a global setting.
		 *
		 * @param string $option - Option name.
		 * @param mixed $value - New value for option.
		 * @param string $prefix - (Optional) A prefix used before option name. Not used. Present only for API compatibility.
		 *
		 * @return bool
		 */
		public function SetGlobalSetting( $option, $value, $prefix = self::OPT_PRFX ) {
            $this->include_options_helper();
			return $this->options_helper->set_option_value( $option, $value );
		}

		/**
		 * Get a global boolean setting. It takes care of the conversion between string and boolean.
		 *
		 * @param string $option  - Option name.
		 * @param boolean  $default - (Optional) Value returned when option is not set (defaults to false).
		 * @return boolean - Option's value or $default if option not set.
         * @since 4.1.3
		 */
		public function GetGlobalBooleanSetting( $option, $default = false ) {
			$result = $this->GetGlobalSetting( $option, \WSAL\Helpers\Options::string_to_bool( $default ) );
            return \WSAL\Helpers\Options::string_to_bool( $result );
		}
		/**
		 * Sets a global boolean setting. It takes care of the conversion between string and boolean.
		 *
		 * @param string $option - Option name.
		 * @param mixed $value - New value for option.
		 * @since 4.1.3
		 */
		public function SetGlobalBooleanSetting( $option, $value ) {
			$boolean_value = \WSAL\Helpers\Options::string_to_bool( $value );
			$this->SetGlobalSetting( $option, \WSAL\Helpers\Options::bool_to_string( $boolean_value ) );
		}

		/**
		 * Run cleanup routines.
		 */
		public function CleanUp() {
			foreach ( $this->_cleanup_hooks as $hook ) {
				call_user_func( $hook );
			}
		}

		/**
		 * Clear last 30 day's failed login alert usernames.
		 */
		public function delete_failed_logins() {
			// Set the dates.
			list( $y, $m, $d ) = explode( '-', date( 'Y-m-d' ) );

			// Site id.
			$site_id = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;

			// New occurrence object.
			$occurrence = new WSAL_Models_Occurrence();
			$alerts     = $occurrence->check_alert_1003(
				array(
					1003,
					$site_id,
					mktime( 0, 0, 0, $m - 1, $d, $y ) + 1,
					mktime( 0, 0, 0, $m, $d, $y ),
				)
			);

			// Alerts exists then continue.
			if ( ! empty( $alerts ) ) {
				foreach ( $alerts as $alert ) {
					// Flush the usernames meta data.
					$alert->UpdateMetaValue( 'Users', array() );
				}
			}
		}

		/**
		 * Add callback to be called when a cleanup operation is required.
		 *
		 * @param callable $hook - Hook name.
		 */
		public function AddCleanupHook( $hook ) {
			$this->_cleanup_hooks[] = $hook;
		}

		/**
		 * Remove a callback from the cleanup callbacks list.
		 *
		 * @param callable $hook - Hook name.
		 */
		public function RemoveCleanupHook( $hook ) {
			while ( ( $pos = array_search( $hook, $this->_cleanup_hooks ) ) !== false ) {
				unset( $this->_cleanup_hooks[ $pos ] );
			}
		}

		/**
		 * DB connection.
		 *
		 * @param mixed $config DB configuration.
		 * @param bool $reset - True if reset.
		 *
		 * @return WSAL_Connector_ConnectorInterface
		 * @throws Freemius_Exception
		 */
		public static function getConnector( $config = null, $reset = false ) {
			return WSAL_Connector_ConnectorFactory::getConnector( $config, $reset );
		}

		/**
		 * Do we have an existing installation? This only applies for version 1.0 onwards.
		 *
		 * @return boolean
		 */
		public function IsInstalled() {
			return self::getConnector()->isInstalled();
		}

		/**
		 * Whether the old plugin was present or not.
		 *
		 * @return boolean
		 */
		public function CanMigrate() {
			return self::getConnector()->canMigrate();
		}

		/**
		 * Absolute URL to plugin directory WITHOUT final slash.
		 *
		 * @return string
		 */
		public function GetBaseUrl() {
			return plugins_url( '', __FILE__ );
		}

		/**
		 * Full path to plugin directory WITH final slash.
		 *
		 * @return string
		 */
		public function GetBaseDir() {
			return plugin_dir_path( __FILE__ );
		}

		/**
		 * Plugin directory name.
		 *
		 * @return string
		 */
		public function GetBaseName() {
			return plugin_basename( __FILE__ );
		}

		/**
		 * Load default configuration / data.
		 */
		public function load_defaults() {
			require_once 'defaults.php';
		}

		/**
		 * WSAL-Notifications-Extension Functions.
		 *
		 * @param string $opt_prefix - Option prefix.
		 *
		 * @return array|null
		 */
		public function GetNotificationsSetting( $opt_prefix ) {
			$this->include_options_helper();
			return $this->options_helper->GetNotificationsSetting( self::OPTIONS_PREFIX . $opt_prefix );
		}

		/**
		 * Get notification.
		 *
		 * @param int $id - Option ID.
		 *
		 * @return string|null
		 */
		public function GetNotification( $id ) {
			$this->include_options_helper();
			return $this->options_helper->GetNotification($id);
		}

		/**
		 * Deletes option by name.
		 *
         * Deprecated function. It is only kept for the upgrade routine. It should be removed in future releases.
         *
		 * @param string $name - Option name.
		 *
		 * @return bool
         *
         * @deprecated 4.1.3 Use WpSecurityAuditLog::DeleteSettingByName() instead
         * @see WpSecurityAuditLog::DeleteSettingByName()
		 */
		public function DeleteByName( $name ) {
			$this->options = new WSAL_Models_Option();
			return $this->options->DeleteByName( $name );
		}

		/**
		 * Deletes setting by name.
		 *
		 * @param string $name - Option name not including the plugin prefix.
		 *
		 * @return bool
		 */
		public function DeleteSettingByName( $name ) {
			if ( empty( $this->options_helper ) ) {
				$this->include_options_helper();
			}
			return $this->options_helper->delete_option( $name );
		}

		/**
		 * Deletes options by prefix.
		 *
		 * Deprecated function. It is only kept for the upgrade routine. It should be removed in future releases.
		 *
		 * @param string $opt_prefix - Option prefix.
		 *
		 * @return bool
         *
         * @deprecated Replacement not implemented.
		 */
		public function DeleteByPrefix( $opt_prefix ) {
			$this->options = new WSAL_Models_Option();
			return $this->options->DeleteByPrefix( self::OPT_PRFX . $opt_prefix );
		}

		/**
		 * Count notifications.
		 *
		 * @param string $opt_prefix - Option prefix.
		 *
		 * @return int
		 */
		public function CountNotifications( $opt_prefix ) {
			$this->include_options_helper();
			return $this->options_helper->CountNotifications( $opt_prefix );
		}

		/**
		 * Update global option.
		 *
         * Deprecated function. It is only kept for the upgrade routine. It should be removed in future releases.
         *
		 * @param string $option - Option name.
		 * @param mixed  $value - Option value.
		 *
		 * @return bool|int
         *
         * @deprecated 4.1.3 Use WpSecurityAuditLog::UpdateGlobalSetting instead
		 * @see WpSecurityAuditLog::UpdateGlobalSetting()
		 */
		public function UpdateGlobalOption( $option, $value ) {
			$this->options = new WSAL_Models_Option();
			return $this->options->SetOptionValue( $option, $value );
		}

		/**
		 * Update global setting.
		 *
		 * @param string $option - Option name.
		 * @param mixed  $value - Option value.
		 *
		 * @return bool|int
		 */
		public function UpdateGlobalSetting( $option, $value ) {
		    $this->include_options_helper();
			return $this->options_helper->set_option_value( $option, $value );
		}

		/**
		 * Method: Render login page message.
		 *
		 * @param string $message - Login message.
		 *
		 * @return string
		 */
		public function render_login_page_message( $message ) {
			// Set WSAL Settings.
			$wsal_settings = new WSAL_Settings( $this );

			// Check if the option is enabled.
			$login_message_enabled = $wsal_settings->is_login_page_notification();
			if ( 'true' === $login_message_enabled ) {
				// Get login message.
				$message = $wsal_settings->get_login_page_notification_text();

				// Default message.
				if ( ! $message ) {
					$message = '<p class="message">' . wp_kses( __( 'For security and auditing purposes, a record of all of your logged-in actions and changes within the WordPress dashboard will be recorded in an activity log with the <a href="https://wpactivitylog.com/" target="_blank">WP Activity Log plugin</a>. The audit log also includes the IP address where you accessed this site from.', 'wp-security-audit-log' ), $this->allowed_html_tags ) . '</p>';
				} else {
					$message = '<p class="message">' . $message . '</p>';
				}
			}

			// Return message.
			return $message;
		}

		/**
		 * Extend WP cron time intervals for scheduling.
		 *
		 * @param  array $schedules - Array of schedules.
		 * @return array
		 */
		public function recurring_schedules( $schedules ) {
			$schedules['sixhours']         = array(
				'interval' => 21600,
				'display'  => __( 'Every 6 hours', 'wp-security-audit-log' ),
			);
			$schedules['fortyfiveminutes'] = array(
				'interval' => 2700,
				'display'  => __( 'Every 45 minutes', 'wp-security-audit-log' ),
			);
			$schedules['thirtyminutes']    = array(
				'interval' => 1800,
				'display'  => __( 'Every 30 minutes', 'wp-security-audit-log' ),
			);
			$schedules['fifteenminutes']   = array(
				'interval' => 900,
				'display'  => __( 'Every 15 minutes', 'wp-security-audit-log' ),
			);
			$schedules['tenminutes']       = array(
				'interval' => 600,
				'display'  => __( 'Every 10 minutes', 'wp-security-audit-log' ),
			);
			$schedules['oneminute']        = array(
				'interval' => 60,
				'display'  => __( 'Every 1 minute', 'wp-security-audit-log' ),
			);
			return $schedules;
		}

		/**
		 * Prints error for deprecated functions.
		 *
		 * @param string $method  — Method deprecated.
		 * @param string $version — Version since deprecated.
		 */
		public function wsal_deprecate( $method, $version ) {
			if ( WP_DEBUG ) {
				/* translators: 1. Deprecated method name 2. Version since deprecated */
				trigger_error( sprintf( esc_html__( 'Method %1$s is deprecated since version %2$s!', 'wp-security-audit-log' ), $method, $version ) );
			}
		}

		/**
		 * Uninstall routine for the plugin.
		 */
		public static function uninstall() {
			if ( ! class_exists( 'WSAL_Uninstall' ) ) {
				require_once trailingslashit( plugin_dir_path( __FILE__ ) ) . 'classes/Uninstall.php';
			}

			WSAL_Uninstall::uninstall();
		}

		/**
		 * Sync premium freemius transient on daily basis.
		 */
		public function sync_premium_freemius() {
			$is_fs_premium_opt = 'fs_wsalp';
			$is_fs_premium     = get_option( $is_fs_premium_opt );

			if ( ! wsal_freemius()->is_registered() ) {
				if ( 'no' !== $is_fs_premium ) {
					update_option( $is_fs_premium_opt, 'no' );
				}
			} else {
				$has_active_valid_license = wsal_freemius()->has_active_valid_license() ? 'yes' : 'no';

				if ( $has_active_valid_license !== $is_fs_premium ) {
					update_option( $is_fs_premium_opt, $has_active_valid_license );
				}
			}
		}

		/**
		 * Get premium freemius transient.
		 *
		 * @return boolean
		 */
		public static function is_premium_freemius() {
			return get_option( 'fs_wsalp' );
		}

		/**
		 * Error Logger
		 *
		 * Logs given input into debug.log file in debug mode.
		 *
		 * @param mixed $message - Error message.
		 */
		public function wsal_log( $message ) {
			if ( WP_DEBUG === true ) {
				if ( is_array( $message ) || is_object( $message ) ) {
					error_log( print_r( $message, true ) );
				} else {
					error_log( $message );
				}
			}
		}

		/**
		 * Hide WSAL plugin from plugin list
		 *
		 * @param  array $plugins All plugins.
		 * @return array
		 */
		public function wsal_hide_plugin( $plugins ) {
			global $pagenow;

			// Check current page, bail early if this isn't the plugins page.
			if ( 'plugins.php' !== $pagenow ) {
				return $plugins;
			}

			// Find WSAL by plugin basename.
			if ( array_key_exists( WSAL_BASE_NAME, $plugins ) ) {
				// Remove WSAL plugin from plugin list page.
				unset( $plugins[ WSAL_BASE_NAME ] );
			}

			return $plugins;
		}

		/**
		 * Temporary autoloader for WSAL classes that somehow bypassed regular means of including
		 * them during the plugin runtime.
		 *
		 * As far as we know, only the UserSessionsTracking object will fall into this autoloader.
		 *
		 * We could optimize the code below by caching the list of extension folders.
		 *
		 * @param string $class Fully qualified class name.
		 *
		 * @return bool
		 */
		public static function autoloader( $class ) {
			if ( ! preg_match( '/^WSAL_/', $class ) ) {
				return false;
			}

			$base_path  = plugin_dir_path( __FILE__ );
			$subfolders = array();
			$matches    = explode( '_', $class );
			if ( count( $matches ) > 2 ) {
				//  remove first (WSAL) and last one (actual file name)
				array_shift( $matches );
				array_pop( $matches );
				$subfolders = $matches;

				//  workaround for MySQL adapter classes
				if ( count( $subfolders ) == 2 && $subfolders[0] === 'Adapters' && $subfolders[1] === 'MySQL' ) {
					$class .= 'Adapter';
				}
			}

			//  use last part of the class name as the actual file name to look for
			$file_name = substr( $class, strrpos( $class, '_' ) + 1 );

			//  try the main "classes" folder first
			$partial_path_to_file = 'classes' . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $subfolders ) . DIRECTORY_SEPARATOR . $file_name . '.php';
			$path_to_file         = $base_path . $partial_path_to_file;
			if ( file_exists( $path_to_file ) ) {
				require_once $path_to_file;

				return true;
			}

			if ( ! function_exists( 'list_files' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			if ( file_exists( $base_path . 'extensions' ) ) {
			$extension_folders = list_files( $base_path . 'extensions', 1 );
				foreach ( $extension_folders as $extension_folder ) {
					if ( ! is_dir( $extension_folder ) ) {
						continue;
					}

					$path_to_file = $extension_folder . $partial_path_to_file;
					if ( file_exists( $path_to_file ) ) {
						require_once $path_to_file;

						return true;
					}
				}
			}
		}

        /**
         * @return bool
         */
		private static function is_admin_blocking_plugins_support_enabled() {

		    //  only meant for 404 pages, but may run before is_404 can be used
		    $is_404 = did_action('wp') ? is_404() : true;
		    if (!$is_404) {
		        return false;
		    }

			//  this is called very early so we need to load some settings manually
			spl_autoload_register( array( __CLASS__, 'autoloader' ) );
			require_once 'classes/Helpers/Options.php';

			/*
			 * We assume settings have already been migrated (in version 4.1.3) to WordPress options table. We might
			 * miss some 404 events until the plugin upgrade runs, but that is a very rare edge case. The same applies
			 * to loading of 'admin-blocking-plugins-support' option further down.
			 */
			$options_helper = new \WSAL\Helpers\Options( self::OPTIONS_PREFIX );
			$is_stealth_mode = $options_helper->get_option_value('mwp-child-stealth-mode', 'no');

			if ('yes' !== $is_stealth_mode ) {
			    //  only intended if MainWP stealth mode is active
			    return false;
			}

			//  allow if the admin blocking support settings is active
			return ('yes' === $options_helper->get_option_value( 'admin-blocking-plugins-support', 'no' ) );
        }
	}

	// Begin load sequence.
	WpSecurityAuditLog::GetInstance();

	if ( is_admin() && ! WpSecurityAuditLog::is_plugin_active( plugin_basename( __FILE__ ) ) ) {
		WpSecurityAuditLog::load_freemius();

		if ( ! apply_filters( 'wsal_disable_freemius_sdk', false ) ) {
			wsal_freemius()->add_action( 'after_uninstall', array( 'WpSecurityAuditLog', 'uninstall' ) );
		}
	}
} else {
	wsal_freemius()->set_basename( true, __FILE__ );
}
