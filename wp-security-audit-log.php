<?php
/**
 * WP Activity Log.
 *
 * @copyright Copyright (C) 2013-2022, WP White Security - support@wpwhitesecurity.com
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name: WP Activity Log
 * Version:     4.4.1
 * Plugin URI:  https://wpactivitylog.com/
 * Description: Identify WordPress security issues before they become a problem. Keep track of everything happening on your WordPress, including users activity. Similar to Linux Syslog, WP Activity Log generates an activity log with a record of everything that happens on your WordPress websites.
 * Author:      WP White Security
 * Author URI:  https://www.wpwhitesecurity.com/
 * Text Domain: wp-security-audit-log
 * Domain Path: /languages/
 * License:     GPL v3
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Network: true
 *
 * @package wsal
 *
 * @fs_premium_only /extensions/, /third-party/woocommerce/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! function_exists( 'wsal_freemius' ) ) {

	if ( ! class_exists( 'WpSecurityAuditLog' ) ) {

		/**
		 * WSAL Main Class.
		 *
		 * @package wsal
		 */
		class WpSecurityAuditLog {

			/**
			 * Plugin version.
			 *
			 * @var string
			 */
			public $version = '4.4.1';

			/**
			 * Plugin constants.
			 *
			 * @var string
			 */
			const PLG_CLS_PRFX = 'WSAL_';

			/**
			 * Minimal PHP version.
			 *
			 * @var string
			 */
			const MIN_PHP_VERSION = '7.0.0';

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
			 * Settings manager.
			 *
			 * @var WSAL_Settings
			 */
			protected $settings;

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
			protected $cleanup_hooks = array();

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
			 * List of third party extensions.
			 *
			 * @var WSAL_AbstractExtension[]
			 * @since 4.3.2
			 */
			public $third_party_extensions = array();

			/**
			 * Standard singleton pattern.
			 *
			 * WARNING! To ensure the system always works as expected, AVOID using this method.
			 * Instead, make use of the plugin instance provided by 'wsal_init' action.
			 *
			 * @return WpSecurityAuditLog Returns the current plugin instance.
			 */
			public static function get_instance() {
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
				$bootstrap_hook = array( 'plugins_loaded', 9 );

				// Plugin should be initialised later in the WordPress bootstrap process to minimize overhead.
				if ( self::is_frontend() ) {
					// To track sessions on frontend logins we need to attach the tracker and all the interfaces and
					// classes it depends on.
					add_action( $bootstrap_hook[0], array( $this, 'maybe_add_sessions_trackers_early' ), $bootstrap_hook[1] );
					$bootstrap_hook = array( 'wp_loaded', 0 );
				}

				add_action( $bootstrap_hook[0], array( $this, 'setup' ), $bootstrap_hook[1] );

				// Register plugin specific activation hook.
				register_activation_hook( __FILE__, array( $this, 'install' ) );

				// Plugin Deactivation Actions.
				register_deactivation_hook( __FILE__, array( $this, 'deactivate_actions' ) );

				// Add custom schedules for WSAL early otherwise they won't work.
				add_filter( 'cron_schedules', array( $this, 'recurring_schedules' ) );

				// Make the options' helper class available.
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

				// To track sessions from the frontend we need to load the session tracking class and init it's hooks
				// plus make available all the supporting classes it needs to operate.
				$base_path = plugin_dir_path( __FILE__ );
				if ( file_exists( $base_path . 'extensions/user-sessions/user-sessions.php' ) ) {
					spl_autoload_register( array( __CLASS__, 'autoloader' ) );

					// Classes below don't follow any naming convention handled by the autoloader.
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
				if ( ! isset( $this->settings ) ) {
					$this->settings = new WSAL_Settings( $this );
				}

				return $this->settings;
			}

			/**
			 * Gets and instantiates the options' helper.
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
					$rest_url_path = trim( parse_url( home_url( '/wp-json/' ), PHP_URL_PATH ), '/' ); // phpcs:ignore
					$request_path  = trim( $_SERVER['REQUEST_URI'], '/' ); // phpcs:ignore

					/*
					 * If we have both a url and a request patch check if this is
					 * a rest request.
					 *
					 * @since 4.0.3
					 */
					if ( $rest_url_path && $request_path ) {
						$is_rest = ( strpos( $request_path, $rest_url_path ) === 0 ) || isset( $_GET['rest_route'] ); // phpcs:ignore
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
				// Always load on the admin, except for the scenario when this plugin is being updated.
				if ( is_admin() ) {
					$acceptable_slugs = array(
						'wp-security-audit-log',
						'wp-activity-log',
					);
					// @codingStandardsIgnoreStart
					// Check if this plugin is being updated from the plugin list.
					if ( isset( $_REQUEST['action'] ) && 'update-plugin' === wp_unslash( trim( $_REQUEST['action'] ) )
						&& in_array( wp_unslash( trim( $_REQUEST['slug'] ) ), $acceptable_slugs ) ) {
						return false;
					}

					// Check if this plugin is being updated using the file upload method.
					if ( isset( $_REQUEST['action'] ) && 'upload-plugin' === wp_unslash( trim( $_REQUEST['action'] ) )
						&& isset( $_REQUEST['overwrite'] ) && 'update-plugin' === wp_unslash( trim( $_REQUEST['overwrite'] ) )
						&& isset( $_REQUEST['package'] ) ) {
						/**
						 * Request doesn't contain the file name, but a numeric package ID.
						 *
						 * @see File_Upload_Upgrader::__construct()
						 */
						$post_id    = (int) $_REQUEST['package'];
						$attachment = get_post( $post_id );
						if ( ! empty( $attachment ) ) {
							$filename = $attachment->post_title;
							foreach ( $acceptable_slugs as $acceptable_slug ) {
								if ( false !== strpos( $filename, $acceptable_slug ) ) {
									return false;
								}
							}
						}
					}

					// Check if this plugin is being updated from the WordPress updated screen (update-core.php).
					if ( isset( $_REQUEST['action'] ) && 'do-plugin-upgrade' === wp_unslash( trim( $_REQUEST['action'] ) ) ) {
						$selected_plugins = $_REQUEST['checked'];
						if ( ! empty( $selected_plugins ) ) {
							foreach ( $selected_plugins as $selected_plugin ) {
								if ( 'wp-security-audit-log.php' === basename( $selected_plugin ) ) {
									return false;
								}
							}
						}
					}
					// @codingStandardsIgnoreEnd
					return true;
				}

				// Check conditions for frontend.
				if ( self::is_frontend() && ! is_user_logged_in() && ! self::should_load_frontend() ) {
					// User isn't logged in, and we aren't logging visitor events on front-end.
					return false;
				}

				// Other contexts/scenarios.
				if ( self::is_rest_api() ) {
					return is_user_logged_in();
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
				$should_load     = ! empty( $frontend_events['register'] ) || ! empty( $frontend_events['login'] ) || ! empty( $frontend_events['woocommerce'] );

				// Allow extensions to manually allow a sensor to load.
				return apply_filters( 'wsal_load_on_frontend', $should_load, $frontend_events );
			}

			/**
			 * Include Plugin Files.
			 *
			 * @since 3.3
			 */
			public function includes() {
				require_once 'classes/Models/ActiveRecord.php';

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
					require_once 'classes/ExtensionPlaceholderView.php';
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
					require_once 'classes/Utilities/RequestUtils.php';

					// Third party extensions.
					require_once 'classes/ThirdPartyExtensions/AbstractExtension.php';
					require_once 'classes/ThirdPartyExtensions/YoastSeoExtension.php';
					require_once 'classes/ThirdPartyExtensions/BBPressExtension.php';
					require_once 'classes/ThirdPartyExtensions/WPFormsExtension.php';
					require_once 'classes/ThirdPartyExtensions/WooCommerceExtension.php';
					require_once 'classes/ThirdPartyExtensions/GravityFormsExtension.php';
					require_once 'classes/ThirdPartyExtensions/TablePressExtension.php';
					require_once 'classes/ThirdPartyExtensions/WFCMExtension.php';
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

				// Utilities.
				require_once 'classes/Utilities/UserUtils.php';

				// Third party extensions with public sensors.
				require_once 'classes/ThirdPartyExtensions/AbstractExtension.php';
				require_once 'classes/ThirdPartyExtensions/WooCommerceExtension.php';

				// Only include these if we are in multisite environment.
				if ( $this->is_multisite() ) {
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
				$this->autoloader->register( self::PLG_CLS_PRFX, $this->get_base_dir() . 'classes' . DIRECTORY_SEPARATOR );
			}

			/**
			 * Initialize Plugin Hooks.
			 *
			 * @since 3.3
			 */
			public function init_hooks() {
				add_action( 'init', array( $this, 'init' ), 5 );

				// Listen for cleanup event.
				add_action( 'wsal_cleanup', array( $this, 'clean_up' ) );

				add_action( 'shutdown', array( $this, 'close_external_connection' ), 999 );

				// Render wsal footer.
				add_action( 'admin_footer', array( $this, 'render_footer' ) );

				// Plugin redirect on activation.
				if ( current_user_can( 'manage_options' ) ) {
					add_action( 'admin_init', array( $this, 'wsal_plugin_redirect' ) );
				}

				// Handle admin Disable Custom Field.
				add_action( 'wp_ajax_AjaxDisableCustomField', array( $this, 'ajax_disable_custom_field' ) );

				// Handle admin Disable Alerts.
				add_action( 'wp_ajax_AjaxDisableByCode', array( $this, 'ajax_disable_by_code' ) );

				// Render Login Page Notification.
				add_filter( 'login_message', array( $this, 'render_login_page_message' ), 10, 1 );

				// Cron job to delete alert 1003 for the last day.
				add_action( 'wsal_delete_logins', array( $this, 'delete_failed_logins' ) );
				if ( ! wp_next_scheduled( 'wsal_delete_logins' ) ) {
					wp_schedule_event( time(), 'daily', 'wsal_delete_logins' );
				}

				add_filter( 'mainwp_child_extra_execution', array( new WSAL_MainWpApi( $this ), 'handle_callback' ), 10, 2 );

				add_action( 'admin_init', array( $this, 'maybe_sync_premium_freemius' ) );

				add_action( 'wsal_freemius_loaded', array( $this, 'adjust_freemius_strings' ) );

				$this->init_freemius();

				// Extensions which are only admin based.
				if ( is_admin() ) {
					$plugin_installer_ajax = new WSAL_PluginInstallerAction();
					$plugin_installer_ajax->register();

					$yoast_seo_addon    = new WSAL_YoastSeoExtension();
					$bbpress_addon      = new WSAL_BBPressExtension();
					$wpforms_addon      = new WSAL_WPFormsExtension();
					$gravityforms_addon = new WSAL_GravityFormsExtension();
					$tablepress_addon   = new WSAL_TablePressExtension();
					$wfcm_addon         = new WSAL_WFCMExtension();
				}

				// Extensions which are both admin and frontend based.
				$woocommerce_addon = new WSAL_WooCommerceExtension( $this );

				// Dequeue conflicting scripts.
				add_action( 'wp_print_scripts', array( $this, 'dequeue_conflicting_scripts' ) );

			}


			/**
			 * Whether the current page is the login screen.
			 *
			 * @return bool
			 */
			public static function is_login_screen() {
				return parse_url( site_url( 'wp-login.php' ), PHP_URL_PATH ) === parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ); // phpcs:ignore
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

				if ( ! class_exists( 'WSAL_PluginInstallAndActivate' ) ) {
					require_once 'classes/Utilities/PluginInstallAndActivate.php';
				}

				// Additional checks for our 3rd party extensions.
				if ( class_exists( 'WSAL_PluginInstallAndActivate' ) ) {
					$our_plugins = array_column( WSAL_PluginInstallAndActivate::get_installable_plugins(), 'plugin_basename' );
					// Check if we are dealing with one of our extensions.
					if ( in_array( basename( $plugin ), $our_plugins, true ) ) {
						// This IS one of our extensions, so lets check a little deeper as folder
						// name can differ.
						if ( function_exists( 'is_multisite' ) && is_multisite() ) {
							$current_plugins = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );
						} else {
							$current_plugins = get_option( 'active_plugins' );
						}
						// Loop through active plugins to compare file names.
						foreach ( $current_plugins as $active_plugin ) {
							if ( basename( $plugin ) === basename( $active_plugin ) ) {
								// Plugin basename is in active plugins, so return true.
								return true;
							}
						}
					}
				}

				return is_plugin_active( $plugin );
			}

			/**
			 * Check if BBPress plugin is active or not.
			 *
			 * @return boolean
			 */
			public static function is_bbpress_active() {
				return ( self::is_plugin_active( 'bbpress/bbpress.php' )
					&& ( self::is_plugin_active( 'wsal-bbpress.php' ) )
				);
			}

			/**
			 * Check if WooCommerce plugin is active or not.
			 *
			 * @return boolean
			 */
			public static function is_woocommerce_active() {
				return ( self::is_plugin_active( 'woocommerce/woocommerce.php' )
					&& ( self::is_plugin_active( 'wsal-woocommerce.php' ) )
				);
			}

			/**
			 * Check if Yoast SEO plugin is active or not.
			 *
			 * @return boolean
			 */
			public static function is_wpseo_active() {
				return ( ( self::is_plugin_active( 'wordpress-seo/wp-seo.php' ) || self::is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) )
					&& ( self::is_plugin_active( 'activity-log-wp-seo.php' ) )
				);
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
				if ( self::is_frontend() && self::is_premium_freemius() && file_exists( WSAL_BASE_DIR . '/extensions/class-wsal-extension-manager.php' ) ) {
					require_once WSAL_BASE_DIR . '/extensions/class-wsal-extension-manager.php';

					if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
						WSAL_Extension_Manager::include_extension( 'reports' );
						WSAL_Extension_Manager::include_extension( 'usersessions' );
						WSAL_Extension_Manager::include_extension( 'external-db' );
					} elseif ( $this->should_load() ) {
						WSAL_Extension_Manager::include_extension( 'notifications' );
					}

					if ( ! $is_admin_blocking_plugins_support_enabled ) {
						// We only stop here if the support for admin blocking plugins is enabled.
						return;
					}
				}

				if ( $is_admin_blocking_plugins_support_enabled || is_admin() || self::is_login_screen() || self::is_rest_api() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {

					self::load_freemius();
					if ( ! apply_filters( 'wsal_disable_freemius_sdk', false ) ) {
						// Add filters to customize freemius welcome message.
						wsal_freemius()->add_filter( 'connect_message', array( $this, 'wsal_freemius_connect_message' ), 10, 6 );
						wsal_freemius()->add_filter( 'connect_message_on_update', array( $this, 'wsal_freemius_update_connect_message' ), 10, 6 );
						wsal_freemius()->add_filter( 'show_admin_notice', array( $this, 'freemius_show_admin_notice' ), 10, 2 );
						wsal_freemius()->add_filter( 'show_delegation_option', '__return_false' );
						wsal_freemius()->add_filter( 'enable_per_site_activation', '__return_false' );
						wsal_freemius()->add_filter( 'show_trial', '__return_false' );
						wsal_freemius()->add_filter( 'opt_in_error_message', array( $this, 'limited_license_activation_error' ), 10, 1 );
						wsal_freemius()->add_action( 'after_account_plan_sync', array( $this, 'sync_premium_freemius' ), 10, 1 );
						wsal_freemius()->add_action( 'after_premium_version_activation', array( $this, 'on_freemius_premium_version_activation' ) );
						wsal_freemius()->add_filter(
							'plugin_icon',
							function( $plugin_icon ) {
								return WSAL_BASE_DIR . 'img/wsal-logo@2x.png';
							}
						);
						wsal_freemius()->add_action( 'is_submenu_visible', array( $this, 'hide_freemius_submenu_items' ), 10, 2 );
						wsal_freemius()->add_filter(
							'freemius_pricing_js_path',
							function ( $default_pricing_js_path ) {
								return WSAL_BASE_DIR . 'js/freemius-pricing/freemius-pricing.js';
							}
						);
						wsal_freemius()->add_filter( 'default_to_anonymous_feedback', '__return_true' );
					}
				}
			}

			/**
			 * Whether visitor events should be logged.
			 *
			 * @return bool
			 */
			public function load_for_visitor_events() {
				return 'no' === \WSAL\Helpers\Options::get_option_value_ignore_prefix( self::OPTIONS_PREFIX . 'disable-visitor-events' );
			}

			/**
			 * Whether an alert is enabled. For use before loading the settings.
			 *
			 * @param string|int $alert The alert to check.
			 * @return bool Whether the alert is enabled.
			 */
			public static function raw_alert_is_enabled( $alert ) {
				$alerts = \WSAL\Helpers\Options::get_option_value_ignore_prefix( self::OPTIONS_PREFIX . 'disabled-alerts' );
				$alerts = explode( ',', $alerts );
				return ! in_array( $alert, $alerts ); // phpcs:ignore
			}

			/**
			 * Method: WSAL plugin redirect.
			 */
			public function wsal_plugin_redirect() {
				// WSAL State.
				$wsal_state = $this->get_global_setting( 'freemius_state', 'anonymous' );

				if (
						$this->get_global_setting( 'redirect_on_activate', false ) // Redirect flag.
						&& in_array( $wsal_state, array( 'anonymous', 'skipped' ), true )
				) {
					// If the redirect option is true, then continue.
					$this->include_options_helper();
					$this->options_helper->delete_option( 'wsal_redirect_on_activate' ); // Delete redirect option.

					// Redirect URL.
					$redirect = '';

					// If current site is multisite and user is super-admin then redirect to network audit log.
					if ( $this->is_multisite() && $this->settings()->current_user_can( 'edit' ) && is_super_admin() ) {
						$redirect = add_query_arg( 'page', 'wsal-auditlog', network_admin_url( 'admin.php' ) );
					} else {
						// Otherwise, redirect to main audit log view.
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
				$result = sprintf(
				/* translators: User's first name */
					esc_html__( 'Hey %s', 'wp-security-audit-log' ),
					$user_first_name
				);
				$result .= ',<br>';
				$result .= esc_html__( 'Never miss an important update! Opt-in to our security and feature updates notifications, and non-sensitive diagnostic tracking with freemius.com.', 'wp-security-audit-log' ) .
				$result .= '<br /><br /><strong>' . esc_html__( 'Note: ', 'wp-security-audit-log' ) . '</strong>';
				$result .= esc_html__( 'NO ACTIVITY LOG ACTIVITY & DATA IS SENT BACK TO OUR SERVERS.', 'wp-security-audit-log' );

				return $result;
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
			 *
			 * @return string
			 *
			 * @since 4.3.4
			 */
			public static function wsal_freemius_update_connect_message( $message, $user_first_name, $plugin_title, $user_login, $site_link, $_freemius_link ) {
				$result = sprintf(
				/* translators: User's first name */
					esc_html__( 'Hey %s', 'wp-security-audit-log' ),
					$user_first_name
				);
				$result .= ',<br>';
				$result .= sprintf(
				/* translators: 1: Plugin name. 2: Plugin name. 2: Freemius link. 4: Plugin name. */
					esc_html__( 'Please help us improve %1$s! If you opt-in, some non-sensitive data about your usage of %2$s will be sent to %3$s, a diagnostic tracking service we use. If you skip this, that\'s okay! %2$s will still work just fine.', 'wp-security-audit-log' ) .
					'<strong>' . $plugin_title . '</strong>',
					'<strong>' . $plugin_title . '</strong>',
					'<a href="https://wpactivitylog.com/support/kb/non-sensitive-diagnostic-data/" target="_blank" tabindex="1">freemius.com</a>',
					'<strong>' . $plugin_title . '</strong>'
				);
				$result .= '<br /><br /><strong>' . esc_html__( 'Note: ', 'wp-security-audit-log' ) . '</strong>';
				$result .= esc_html__( 'NO ACTIVITY LOG ACTIVITY & DATA IS SENT BACK TO OUR SERVERS.', 'wp-security-audit-log' );

				return $result;
			}

			/**
			 * Freemius Admin Notice View Permission.
			 *
			 * Check to see if the user has permission to view Freemius admin notices or not.
			 *
			 * @param bool  $show – If show then set to true, otherwise false.
			 * @param array $msg  Message related data.
			 *
			 * @return bool
			 * @since 3.3
			 */
			public function freemius_show_admin_notice( $show, $msg ) {
				if ( $this->settings()->current_user_can( 'edit' ) ) {
					return $show;
				}
				return false;
			}

			/**
			 * Changes some of the strings that Freemius outputs with our own.
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
						'few-plugin-tweaks' => esc_html__( 'You need to activate the licence key to use WP Activity Log Premium. %s', 'wp-security-audit-log' ), // phpcs:ignore
						'optin-x-now'       => esc_html__( 'Activate the licence key now', 'wp-security-audit-log' ),
					)
				);
			}

			/**
			 * Limited License Activation Error.
			 *
			 * @param string $error - Error Message.
			 *
			 * @return string
			 */
			public function limited_license_activation_error( $error ) {
				// We only process error if it's some sort of string message.
				if ( ! is_string( $error ) ) {
					return $error;
				}

				$site_count = null;
				preg_match( '!\d+!', $error, $site_count );

				// Check if this is an expired error.
				if ( strpos( $error, 'expired' ) !== false ) {
					/* Translators: Expired message and time */
					$error = sprintf( esc_html__( '%s You need to renew your license to continue using premium features.', 'wp-security-audit-log' ), preg_replace( '/\([^)]+\)/', '', $error ) );
				} elseif ( ! empty( $site_count[0] ) ) {
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
					$this->views   = new WSAL_ViewManager( $this );
					$this->widgets = new WSAL_WidgetManager( $this );
				}

				// Start listening to events.
				if ( ! empty( $this->sensors ) && $this->sensors instanceof WSAL_SensorManager ) {
					$this->sensors->hook_events();
				}

				if ( is_admin() ) {

					// Hide plugin.
					if ( $this->settings()->is_incognito() ) {
						add_action( 'admin_head', array( $this, 'hide_plugin' ) );
						add_filter( 'all_plugins', array( $this, 'wsal_hide_plugin' ) );
					}

					// Update routine.
					$old_version = $this->get_old_version();
					$new_version = $this->get_new_version();
					if ( $old_version !== $new_version ) {
						$this->update( $old_version, $new_version );
					}

				}

				/**
				 * Action: `wsal_init`
				 *
				 * Action hook to mark that WSAL has initialized.
				 *
				 * @param WpSecurityAuditLog $this – Instance of main plugin class.
				 */
				do_action( 'wsal_init', $this );

				// Background job for metadata migration.
				new WSAL_Upgrade_MetadataMigration();

				// Allow registration of custom alert formatters (must be called after wsal_init action).
				WSAL_AlertFormatterFactory::bootstrap();
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
				 * Allow short-circuiting of the deactivation email sending by using
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
			public function ajax_disable_custom_field() {
				// Die if user does not have permission to disable.
				if ( ! $this->settings()->current_user_can( 'edit' ) ) {
					echo '<p>' . esc_html__( 'Error: You do not have sufficient permissions to disable this custom field.', 'wp-security-audit-log' ) . '</p>';
					die();
				}

				// Set filter input args.
				$filter_input_args = array(
					'disable_nonce' => FILTER_SANITIZE_STRING,
					'notice'        => FILTER_SANITIZE_STRING,
					'object_type'   => FILTER_SANITIZE_STRING,
				);

				// Filter $_POST array for security.
				$post_array = filter_input_array( INPUT_POST, $filter_input_args );

				if ( ! isset( $post_array['disable_nonce'] ) || ! wp_verify_nonce( $post_array['disable_nonce'], 'disable-custom-nonce' . $post_array['notice'] ) ) {
					die();
				}

				$object_type = 'post';
				if ( array_key_exists( 'object_type', $post_array ) && 'user' === $post_array['object_type'] ) {
					$object_type = 'user';
				}

				$excluded_meta = array();
				if ( 'post' === $object_type ) {
					$excluded_meta = $this->settings()->get_excluded_post_meta_fields();
				} elseif ( 'user' === $object_type ) {
					$excluded_meta = $this->settings()->get_excluded_user_meta_fields();
				}

				array_push( $excluded_meta, esc_html( $post_array['notice'] ) );

				if ( 'post' === $object_type ) {
					$excluded_meta = $this->settings()->set_excluded_post_meta_fields( $excluded_meta );
				} elseif ( 'user' === $object_type ) {
					$excluded_meta = $this->settings()->set_excluded_user_meta_fields( $excluded_meta );
				}

				// Exclude object link.
				$exclude_objects_link = add_query_arg(
					array(
						'page' => 'wsal-settings',
						'tab'  => 'exclude-objects',
					),
					network_admin_url( 'admin.php' )
				);

				echo wp_sprintf(
					/* translators: name of meta field (in bold) */
					'<p>' . esc_html__( 'Custom field %s is no longer being monitored.', 'wp-security-audit-log' ) . '</p>',
					'<strong>' . $post_array['notice'] . '</strong>' // phpcs:ignore
				);

				echo wp_sprintf(
					/* translators: setting tab name "Excluded Objects" */
					'<p>' . esc_html__( 'Enable the monitoring of this custom field again from the %s tab in the plugin settings.', 'wp-security-audit-log' ) . '</p>',
					'<a href="' . $exclude_objects_link . '">' . esc_html__( 'Excluded Objects', 'wp-security-audit-log' ) . '</a>'  // phpcs:ignore
				);
				die;
			}

			/**
			 * Disable Alert through ajax.
			 *
			 * @internal
			 */
			public function ajax_disable_by_code() {
				// Die if user does not have permission to disable.
				if ( ! $this->settings()->current_user_can( 'edit' ) ) {
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
				if ( ! isset( $post_array['disable_nonce'] ) || ! wp_verify_nonce( $post_array['disable_nonce'], 'disable-alert-nonce' . $post_array['code'] ) ) {
					die();
				}

				$s_alerts = $this->get_global_setting( 'disabled-alerts' );
				if ( isset( $s_alerts ) && '' != $s_alerts ) { // phpcs:ignore
					$s_alerts .= ',' . esc_html( $post_array['code'] );
				} else {
					$s_alerts = esc_html( $post_array['code'] );
				}
				$this->set_global_setting( 'disabled-alerts', $s_alerts );

				echo wp_sprintf( '<p>' . esc_html__( 'Alert %1$s is no longer being monitored.<br /> %2$s', 'wp-security-audit-log' ) . '</p>', esc_html( $post_array['code'] ), esc_html__( 'You can enable this alert again from the Enable/Disable Alerts node in the plugin menu.', 'wp-security-audit-log' ) ); // phpcs:ignore
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
					$this->get_base_url() . '/js/common.js',
					array( 'jquery' ),
					filemtime( $this->get_base_dir() . '/js/common.js' ),
					true
				);

				// Live events disabled in free version of the plugin.
				$live_events_enabled = false;
				// Set data array for common script.
				$script_data = array(
					'ajaxURL'           => admin_url( 'admin-ajax.php' ),
					'liveEvents'        => $live_events_enabled,
					'installing'        => esc_html__( 'Installing, please wait', 'wp-security-audit-log' ),
					'already_installed' => esc_html__( 'Already installed', 'wp-security-audit-log' ),
					'installed'         => esc_html__( 'Extension installed', 'wp-security-audit-log' ),
					'activated'         => esc_html__( 'Extension activated', 'wp-security-audit-log' ),
					'failed'            => esc_html__( 'Install failed', 'wp-security-audit-log' ),
					'reloading_page'    => esc_html__( 'Reloading page', 'wp-security-audit-log' ),
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
				require_once 'classes/Settings.php';

				if ( is_admin() ) {
					// Initiate settings object if not set.
					if ( ! $this->settings ) {
						$this->settings = new WSAL_Settings( $this );
					}

					// Setting the pruning date with the old value or the default value.
					$pruning_date = $this->settings()->get_pruning_date();
					$this->settings()->set_pruning_date( $pruning_date );

					// Load translations.
					load_plugin_textdomain( 'wp-security-audit-log', false, basename( dirname( __FILE__ ) ) . '/languages/' );
				}
			}

			/**
			 * Installs all assets required for a usable system.
			 */
			public function install() {

				$installation_errors = false;

				// Check for minimum PHP version.
				if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION ) < 0 ) {
					/* Translators: %s: PHP Version */
					$installation_errors  = sprintf( esc_html__( 'You are using a version of PHP that is older than %s, which is no longer supported.', 'wp-security-audit-log' ), esc_html( self::MIN_PHP_VERSION ) );
					$installation_errors .= '<br />';
					$installation_errors .= __( 'Contact us on <a href="mailto:plugins@wpwhitesecurity.com">plugins@wpwhitesecurity.com</a> to help you switch the version of PHP you are using.', 'wp-security-audit-log' );
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

				// Update licensing info in case we're swapping from free to premium or vice-versa.
				$this->sync_premium_freemius();

				// Disable database sensor during the creation of tables.
				WSAL_Sensors_Database::$enabled = false;

				// On first install this won't be loaded because not premium, add it
				// now so it installs.
				$this->load_sessions_extension_db_adapter();

				// run any installs.
				self::get_connector()->install_all();
				self::get_connector()->get_adapter( 'Occurrence' )->create_indexes();
				self::get_connector()->get_adapter( 'Meta' )->create_indexes();


				// If system already installed, do updates now (if any).
				$old_version = $this->get_old_version();
				$new_version = $this->get_new_version();

				if ( $old_version !== $new_version ) {
					$this->update( $old_version, $new_version );
				}

				// Install cleanup hook (remove older one if it exists).
				wp_clear_scheduled_hook( 'wsal_cleanup' );
				wp_schedule_event( current_time( 'timestamp' ) + 600, 'hourly', 'wsal_cleanup' ); // phpcs:ignore

				// WSAL Audit Log page redirect option in anonymous mode.
				if ( 'anonymous' === $this->get_global_setting( 'freemius_state', 'anonymous' ) ) {
					$this->set_global_setting( 'redirect_on_activate', true );
				}

				// Run on each install to check MainWP Child plugin.
				$this->settings()->set_mainwp_child_stealth_mode();

				// Re-enable the database sensor after the tables are created.
				WSAL_Sensors_Database::$enabled = true;
			}

			/**
			 * Run some code that updates critical components required for a newer version.
			 *
			 * @param string $old_version The old version.
			 * @param string $new_version The new version.
			 */
			public function update( $old_version, $new_version ) {
				// Update version in db.
				$this->set_global_setting( 'version', $new_version, true );

				// Keep track of the initial db version. This gets updated multiple times during the upgrade process
				// and we need to know what was the starting point.
				$initial_db_version = $this->settings()->get_database_version();

				if ( '0.0.0' === $old_version ) {
					// Set some initial plugins settings (only the ones that bypass the regular settings retrieval at
					// some point) - e.g. disabled events.
					$this->set_global_setting( 'disabled-alerts', implode( ',', $this->settings()->always_disabled_alerts ) );

					// We set the database version to the latest if this is a freshly installed plugin.
					$this->settings()->set_database_version( 44400 );

					// We stop here as no further updates are needed for a freshly installed plugin.
					return;
				}

				// Do version-to-version specific changes.
				if ( '0.0.0' !== $old_version && -1 === version_compare( $old_version, $new_version ) ) {
					// Dismiss privacy notice.
					if ( empty( $this->views ) ) {
						$this->views = new WSAL_ViewManager( $this );
					}
					$this->views->find_by_class_name( 'WSAL_Views_AuditLog' )->dismiss_notice( 'wsal-privacy-notice-3.2' );

					/**
					 * Delete advert transient on every update.
					 *
					 * @since 3.2.4
					 */
					if ( wsal_freemius()->is_free_plan() ) {
						$delete_transient_fn = $this->is_multisite() ? 'delete_site_transient' : 'delete_transient'; // Check for multisite.
						$delete_transient_fn( 'wsal-is-advert-dismissed' ); // Delete advert transient.
					}

					/**
					 * MainWP Child Stealth Mode Update
					 *
					 * This update only needs to run if the stealth mode option
					 * does not exist on free version.
					 *
					 * @since 3.2.3.3
					 */
					if ( ! $this->get_global_boolean_setting( 'mwp-child-stealth-mode', false ) ) {
						$this->settings()->set_mainwp_child_stealth_mode();
					}

					// Remove obsolete options from the database.
					if ( version_compare( $new_version, '4.1.4', '>=' ) ) {
						$this->delete_global_setting( 'addon_available_notice_dismissed' );

						// Remove old file scanning options.
						global $wpdb;
						$plugin_options = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'wsal_local_files_%'" ); // phpcs:ignore
						if ( ! empty( $plugin_options ) ) {
							foreach ( $plugin_options as $option ) {
								$this->delete_global_setting( $option->option_name );
							}
						}
					}

					if ( version_compare( $new_version, '4.1.5', '>=' ) ) {
						// Remove 'system' entry from the front-end events array as it was removed along with 404 tracking.
						$frontend_events = WSAL_Settings::get_frontend_events();
						if ( array_key_exists( 'system', $frontend_events ) ) {
							unset( $frontend_events['system'] );
							WSAL_Settings::set_frontend_events( $frontend_events );
						}

						// Remove all settings related to 404 tracking.
						$not_found_page_related_settings = array(
							'log-404',
							'purge-404-log',
							'log-404-referrer',
							'log-visitor-404',
							'purge-visitor-404-log',
							'log-visitor-404-referrer',
							'excluded-urls',
						);
						foreach ( $not_found_page_related_settings as $setting_name ) {
							$this->delete_global_setting( $setting_name );
						}

						// Remove cron job for purging 404 logs.
						$schedule_time = wp_next_scheduled( 'wsal_log_files_pruning' );
						if ( $schedule_time ) {
							wp_unschedule_event( $schedule_time, 'wsal_log_files_pruning', array() );
						}
					}

					if ( version_compare( $new_version, '4.2.0', '>=' ) ) {
						// Delete custom logging dir path from the settings.
						$this->delete_global_setting( 'custom-logging-dir' );
						// Delete dev options from the settings.
						$this->delete_global_setting( 'dev-options' );
					}

					if ( version_compare( $new_version, '4.3.2', '>=' ) ) {
						$this->settings()->set_database_version( 43200 );

						// Change the name of the option storing excluded post meta fields.
						$excluded_custom_fields = $this->get_global_setting( 'excluded-custom', null );
						if ( ! is_null( $excluded_custom_fields ) ) {
							$this->set_global_setting( 'excluded-post-meta', $excluded_custom_fields );
							$this->delete_global_setting( 'excluded-custom' );
						}
					}

					if ( version_compare( $new_version, '4.4.0', '>=' ) ) {
						$should_440_upgrade_run = true;
						if ( 44400 === $initial_db_version ) {
							// Database version is 44400 if someone already upgraded from any version to 4.4.0.
							$should_440_upgrade_run = false;
						} elseif ( 0 === $initial_db_version ) {
							// Database version is 0 if the plugin was never upgraded. This could be an upgrade from
							// 4.3.6, 4.4.0 or any other lower version.
							$should_440_upgrade_run = false;
							if ( version_compare( $old_version, '4.4.0', '<' ) ) {
								// We are upgrading from pre-4.4.0 version.
								$should_440_upgrade_run = true;
							}
						}

						if ( $should_440_upgrade_run ) {
							require_once 'classes/Upgrade/Upgrade_43000_To_44400.php';
							$upgrader = new WSAL_Upgrade_43000_To_44400( $this );
							$upgrader->run();
						}

						$this->settings()->set_database_version( 44400 );
					}
				}
			}

			/**
			 * The current plugin version (according to plugin file metadata).
			 *
			 * @return string
			 */
			public function get_new_version() {
				$version = get_plugin_data( __FILE__, false, false );
				return isset( $version['Version'] ) ? $version['Version'] : '0.0.0';
			}

			/**
			 * The plugin version as stored in DB (will be the old version during an update/install).
			 *
			 * @return string
			 */
			public function get_old_version() {
				return $this->get_global_setting( 'version', '0.0.0' );
			}

			/**
			 * To be called in admin header for hiding plugin form Plugins list.
			 *
			 * @internal
			 */
			public function hide_plugin() {
				if ( ! $this->settings()->current_user_can( 'edit' ) ) {
					$selectr = '';
					$plugins = array( 'wp-security-audit-log', 'wp-security-audit-log-premium' );
					foreach ( $plugins as $value ) {
						$selectr .= '.wp-list-table.plugins tr[data-slug="' . $value . '"], ';
					}
					?>
					<style type="text/css">
						<?php echo rtrim( $selectr, ', ' ); // phpcs:ignore ?> { display: none; }
					</style>
					<?php
				}
			}

			/**
			 * Returns the class name of a particular file that contains the class.
			 *
			 * @param string $file - File name.
			 * @return string - Class name.
			 * @deprecated since 1.2.5 Use autoloader->GetClassFileClassName() instead.
			 *
			 * @phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
			 */
			public function GetClassFileClassName( $file ) {
				return $this->autoloader->get_class_file_class_name( $file );
			}

			/**
			 * Return whether we are running on multisite or not.
			 *
			 * @return boolean
			 */
			public function is_multisite() {
				return function_exists( 'is_multisite' ) && is_multisite();
			}

			/**
			 * Get a global option.
			 *
			 * Deprecated function. It is only kept for the extension plugins. Nothing in the main plugin uses it, not even
			 * the upgrade process.
			 *
			 * @param string $option  - Option name.
			 * @param mixed  $default - (Optional) Value returned when option is not set (defaults to false).
			 * @param string $prefix  - (Optional) A prefix used before option name.
			 *
			 * @return mixed - Option's value or $default if option not set.
			 *
			 * @deprecated 4.1.3 Use WpSecurityAuditLog::GetGlobalSetting instead
			 * @see WpSecurityAuditLog::get_global_setting()
			 *
			 * @phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
			 */
			public function GetGlobalOption( $option, $default = false, $prefix = '' ) {
				return $this->get_global_setting( $option, $default );
			}

			/**
			 * Get a global setting.
			 *
			 * @param string $option - Option name.
			 * @param mixed  $default - (Optional) Value returned when option is not set (defaults to false).
			 *
			 * @return mixed - Option's value or $default if option not set.
			 */
			public function get_global_setting( $option, $default = false ) {
				$this->include_options_helper();
				return $this->options_helper->get_option_value( $option, $default );
			}

			/**
			 * Set a global option.
			 *
			 * Deprecated function. It is only kept for the extension plugins. Nothing in the main plugin uses it, not even
			 * the upgrade process.
			 *
			 * @param string $option - Option name.
			 * @param mixed  $value - New value for option.
			 * @param string $prefix - (Optional) A prefix used before option name.
			 *
			 * @deprecated 4.1.3 Use WpSecurityAuditLog::SetGlobalSetting instead
			 * @see WpSecurityAuditLog::set_global_setting()
			 *
			 * @phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
			 */
			public function SetGlobalOption( $option, $value, $prefix = '' ) {
				$this->set_global_setting( $option, $value );
			}

			/**
			 * Set a global setting.
			 *
			 * @param string $option - Option name.
			 * @param mixed  $value - New value for option.
			 * @param bool   $autoload Whether or not to autoload this option.
			 *
			 * @return bool
			 */
			public function set_global_setting( $option, $value, $autoload = false ) {
				$this->include_options_helper();

				return $this->options_helper->set_option_value( $option, $value, $autoload );
			}

			/**
			 * Deletes a global setting.
			 *
			 * @param string $option - Option name without the prefix.
			 *
			 * @return bool
			 * @since 4.2.1
			 */
			public function delete_global_setting( $option ) {
				$this->include_options_helper();

				return $this->options_helper->delete_option( $option );
			}

			/**
			 * Get a global boolean setting. It takes care of the conversion between string and boolean.
			 *
			 * @param string  $option  - Option name.
			 * @param boolean $default - (Optional) Value returned when option is not set (defaults to false).
			 * @return boolean - Option's value or $default if option not set.
			 * @since 4.1.3
			 */
			public function get_global_boolean_setting( $option, $default = false ) {
				$result = $this->get_global_setting( $option, \WSAL\Helpers\Options::string_to_bool( $default ) );
				return \WSAL\Helpers\Options::string_to_bool( $result );
			}

			/**
			 * Sets a global boolean setting. It takes care of the conversion between string and boolean.
			 *
			 * @param string $option - Option name.
			 * @param mixed  $value - New value for option.
			 * @param bool   $autoload Whether or not to autoload this option.
			 *
			 * @since 4.1.3
			 */
			public function set_global_boolean_setting( $option, $value, $autoload = false ) {
				$boolean_value = \WSAL\Helpers\Options::string_to_bool( $value );
				$this->set_global_setting( $option, \WSAL\Helpers\Options::bool_to_string( $boolean_value ), $autoload );
			}

			/**
			 * Run cleanup routines.
			 */
			public function clean_up() {
				foreach ( $this->cleanup_hooks as $hook ) {
					call_user_func( $hook );
				}
			}

			/**
			 * Clear last 30 day's failed login alert usernames.
			 */
			public function delete_failed_logins() {
				// Set the dates.
				list( $y, $m, $d ) = explode( '-', date( 'Y-m-d' ) ); // phpcs:ignore

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

				// Alerts exist then continue.
				if ( ! empty( $alerts ) ) {
					foreach ( $alerts as $alert ) {
						// Flush the usernames metadata.
						$alert->update_meta_value( 'Users', array() );
					}
				}
			}

			/**
			 * Add callback to be called when a cleanup operation is required.
			 *
			 * @param callable $hook - Hook name.
			 */
			public function add_cleanup_hook( $hook ) {
				$this->cleanup_hooks[] = $hook;
			}

			/**
			 * Remove a callback from the cleanup callbacks list.
			 *
			 * @param callable $hook - Hook name.
			 */
			public function remove_cleanup_hook( $hook ) {
				while ( ( $pos = array_search( $hook, $this->cleanup_hooks ) ) !== false ) { // phpcs:ignore
					unset( $this->cleanup_hooks[ $pos ] );
				}
			}

			/**
			 * DB connection.
			 *
			 * @param string|array $config DB configuration array, db alias or empty to use default connection.
			 * @param bool         $reset - True if reset.
			 *
			 * @return WSAL_Connector_ConnectorInterface
			 */
			public static function get_connector( $config = null, $reset = false ) {
				return WSAL_Connector_ConnectorFactory::get_connector( $config, $reset );
			}

			/**
			 * Do we have an existing installation? This only applies for version 1.0 onwards.
			 *
			 * @return boolean
			 */
			public function is_installed() {
				return self::get_connector()->is_installed();
			}

			/**
			 * Absolute URL to plugin directory WITHOUT final slash.
			 *
			 * @return string
			 */
			public function get_base_url() {
				return plugins_url( '', __FILE__ );
			}

			/**
			 * Full path to plugin directory WITH final slash.
			 *
			 * @return string
			 */
			public function get_base_dir() {
				return plugin_dir_path( __FILE__ );
			}

			/**
			 * Plugin directory name.
			 *
			 * @return string
			 */
			public function get_base_name() {
				return plugin_basename( __FILE__ );
			}

			/**
			 * Load default configuration / data.
			 */
			public function load_defaults() {
				require_once 'defaults.php';
			}
			/**
			 * Update global option.
			 *
			 * Deprecated function. It is only kept for the extension plugins. Nothing in the main plugin uses it, not even
			 * the upgrade process.
			 *
			 * @param string $option - Option name.
			 * @param mixed  $value - Option value.
			 *
			 * @return bool|int
			 *
			 * @deprecated 4.1.3 Use WpSecurityAuditLog::SetGlobalSetting instead
			 * @see WpSecurityAuditLog::set_global_setting()
			 */
			public function UpdateGlobalOption( $option, $value ) {
				return $this->set_global_setting( $option, $value );
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
				if ( $login_message_enabled ) {
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
					trigger_error( sprintf( esc_html__( 'Method %1$s is deprecated since version %2$s!', 'wp-security-audit-log' ), $method, $version ) ); // phpcs:ignore
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
			 * Function runs Freemius license check only if our Freemius licensing transient has already expired. This is
			 * intended to run on admin_init action.
			 *
			 * @since 4.3.0
			 */
			public function maybe_sync_premium_freemius() {
				// We don't want to slow down any AJAX requests.
				if ( wp_doing_ajax() ) {
					return;
				}

				$freemius_transient = get_transient( 'fs_wsalp' );
				if ( false === $freemius_transient || ! in_array( $freemius_transient, array( 'yes', 'no' ), true ) ) {
					// Transient expired or invalid.
					$this->sync_premium_freemius();
				}
			}

			/**
			 * Runs Freemius license check, updates our db option if necessary and creates/extends a transient we use to
			 * optimize the check. Should run only on couple of Freemius actions related to account sync and plugin activation.
			 *
			 * It might be also called by WpSecurityAuditLog::maybe_sync_premium_freemius() if the transient is not set or valid.
			 *
			 * @see WpSecurityAuditLog::maybe_sync_premium_freemius()
			 */
			public function sync_premium_freemius() {
				$option_name = 'fs_wsalp';
				$old_value   = get_option( $option_name );

				// Determine new value via Freemius SDK.
				$new_value = wsal_freemius()->has_active_valid_license() ? 'yes' : 'no';

				// Update the db option only if the value changed.
				if ( $new_value != $old_value ) { // phpcs:ignore
					update_option( $option_name, $new_value );
				}

				// Always update the transient to extend the expiration window.
				set_transient( $option_name, $new_value, DAY_IN_SECONDS );
			}

			/**
			 * Resource cautious function to check if the premium license is active and valid. It only checks if WordPress
			 * option "fs_wsalp" is present and set to true.
			 *
			 * Function is intended for quick check during initial stages of plugin bootstrap, especially on front-end.
			 *
			 * @see WpSecurityAuditLog::sync_premium_freemius()
			 *
			 * @return boolean
			 */
			public static function is_premium_freemius() {
				return 'yes' === get_option( 'fs_wsalp' );
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
						error_log( print_r( $message, true ) ); // phpcs:ignore
					} else {
						error_log( $message ); // phpcs:ignore
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

				$predefined_plugins = WSAL_PluginInstallAndActivate::get_installable_plugins();

				// Find WSAL by plugin basename.
				if ( array_key_exists( WSAL_BASE_NAME, $plugins ) ) {
					// Remove WSAL plugin from plugin list page.
					unset( $plugins[ WSAL_BASE_NAME ] );
				}

				// Find and hide addons.
				foreach ( $predefined_plugins as $extension_plugin ) {
					if ( array_key_exists( $extension_plugin['plugin_slug'], $plugins ) ) {
						if ( 'website-file-changes-monitor/website-file-changes-monitor.php' !== $extension_plugin['plugin_slug'] ) {
							unset( $plugins[ $extension_plugin['plugin_slug'] ] );
						}
					}
				}

				return $plugins;
			}

			/**
			 * Use filter to hide freemius submenu items.
			 *
			 * @param  boolean $is_visible Default visibility.
			 * @param  string  $submenu_id Menu slug.
			 *
			 * @return boolean             New visibility.
			 */
			public function hide_freemius_submenu_items( $is_visible, $submenu_id ) {
				if ( 'contact' === $submenu_id ) {
					$is_visible = false;
				}
				return $is_visible;
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
					// Remove first (WSAL) and last one (actual file name).
					array_shift( $matches );
					array_pop( $matches );
					$subfolders = $matches;

					// Workaround for MySQL adapter classes.
					if ( count( $subfolders ) === 2 && 'Adapters' === $subfolders[0] && 'MySQL' === $subfolders[1] ) {
						$class .= 'Adapter';
					}
				}

				// Use last part of the class name as the actual file name to look for.
				$file_name = substr( $class, strrpos( $class, '_' ) + 1 );

				// Try the main "classes" folder first.
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
			 * Checks if the admin blocking plugins support is enabled.
			 *
			 * @see https://trello.com/c/1OCd5iKc/589-wieserdk-al4mwp-cannot-retrieve-events-when-admin-url-is-changed
			 * @return bool
			 */
			private static function is_admin_blocking_plugins_support_enabled() {

				// Only meant for 404 pages, but may run before is_404 can be used.
				$is_404 = ! did_action( 'wp' ) || is_404();
				if ( ! $is_404 ) {
					return false;
				}

				// This is called very early so we need to load some settings manually.
				spl_autoload_register( array( __CLASS__, 'autoloader' ) );
				require_once 'classes/Helpers/Options.php';

				/*
				 * We assume settings have already been migrated (in version 4.1.3) to WordPress options table. We might
				 * miss some 404 events until the plugin upgrade runs, but that is a very rare edge case. The same applies
				 * to loading of 'admin-blocking-plugins-support' option further down.
				 *
				 * We do not need to worry about the missed 404s after version 4.1.5 as they were completely removed.
				 */
				$options_helper  = new \WSAL\Helpers\Options( self::OPTIONS_PREFIX );
				$is_stealth_mode = $options_helper->get_option_value( 'mwp-child-stealth-mode', 'no' );

				if ( 'yes' !== $is_stealth_mode ) {
					// Unly intended if MainWP stealth mode is active.
					return false;
				}

				// Allow if the admin blocking support settings is active.
				return ( 'yes' === $options_helper->get_option_value( 'admin-blocking-plugins-support', 'no' ) );
			}

			/**
			 * Loads everything necessary to use DB adapter from the sessions' extension.
			 *
			 * @since 4.1.4.1
			 */
			public function load_sessions_extension_db_adapter() {
				if ( file_exists( plugin_dir_path( __FILE__ ) . 'extensions/user-sessions/user-sessions.php' ) ) {
					$this->maybe_add_sessions_trackers_early();
					require_once plugin_dir_path( __FILE__ ) . 'extensions/user-sessions/user-sessions.php';
					$sessions = new WSAL_UserSessions_Plugin();
					$sessions->require_adapter_classes();
				}
			}

			/**
			 * Runs on premium version activation and installs database tables of the premium extensions (sessions table at
			 * the time of introducing this function).
			 *
			 * @since 4.1.4.1
			 */
			public function on_freemius_premium_version_activation() {
				$this->sync_premium_freemius();
				$this->load_sessions_extension_db_adapter();
				self::get_connector()->install_single( 'WSAL_Adapters_MySQL_Session' );
			}

			/**
			 * Dequeue JS files which have been added by other plugin to all admin pages and cause conflicts.
			 * See https://github.com/WPWhiteSecurity/wp-security-audit-log-premium/issues/1246 and
			 * https://trello.com/c/pWrQn1Be/742-koenhavelaertsflintgrpcom-reports-ui-does-not-load-with-plugin-installed
			 *
			 * @since 4.1.5
			 */
			public function dequeue_conflicting_scripts() {
				global $current_screen;
				// Only dequeue on our admin pages.
				if ( isset( $current_screen->base ) && strpos( $current_screen->base, 'wp-activity-log' ) === 0 ) {
					wp_deregister_script( 'dateformat' );
				}
			}

			/**
			 * Closes external connection if it's being used.
			 *
			 * @since 4.3.1
			 */
			public function close_external_connection() {
				// If the adapter type options is not empty, it means we're using the external database.
				$database_type = $this->get_global_setting( 'adapter-type' );
				if ( strlen( $database_type ) > 0 ) {
					$this->get_connector()->close_connection();
				}
			}

			/**
			 * Deprecated placeholder function.
			 *
			 * @return WpSecurityAuditLog Returns the current plugin instance.
			 *
			 * @deprecated 4.4.1 Replaced by function get_instance.
			 * @see        WpSecurityAuditLog::get_instance()
			 *
			 * @phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
			 */
			public static function GetInstance() {
				return self::get_instance();
			}

			/**
			 * Deprecated placeholder function.
			 *
			 * @param string  $option  Option name.
			 * @param boolean $default (Optional) Value returned when option is not set (defaults to false).
			 *
			 * @return boolean Option's value or $default if option not set.
			 *
			 * @deprecated 4.4.1 Replaced by function get_instance.
			 * @see        WpSecurityAuditLog::get_global_boolean_setting()
			 *
			 * @phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
			 */
			public function GetGlobalBooleanSetting( $option, $default = false ) {
				return $this->get_global_boolean_setting( $option, $default );
			}

			/**
			 * Deprecated placeholder function.
			 *
			 * @param string $option   Option name.
			 * @param mixed  $value    New value for option.
			 * @param bool   $autoload Whether or not to autoload this option.
			 *
			 * @deprecated 4.4.1 Replaced by function get_instance.
			 * @see        WpSecurityAuditLog::set_global_boolean_setting()
			 *
			 * @phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
			 */
			public function SetGlobalBooleanSetting( $option, $value, $autoload = false ) {
				$this->set_global_boolean_setting( $option, $value, $autoload );
			}
		}

		$prefixed_autoloader_file_path = plugin_dir_path( __FILE__ ) . implode(
			DIRECTORY_SEPARATOR,
			array(
				'third-party',
				'vendor',
				'autoload.php',
			)
		);

		if ( file_exists( $prefixed_autoloader_file_path ) ) {
			require_once $prefixed_autoloader_file_path;
		}

		// Begin load sequence.
		WpSecurityAuditLog::get_instance();

		if ( is_admin() && ! WpSecurityAuditLog::is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			WpSecurityAuditLog::load_freemius();

			if ( ! apply_filters( 'wsal_disable_freemius_sdk', false ) ) {
				wsal_freemius()->add_action( 'after_uninstall', array( 'WpSecurityAuditLog', 'uninstall' ) );
			}
		}
	}
} else {
	wsal_freemius()->set_basename( true, __FILE__ );
}
if ( ! function_exists( 'wsal_free_on_plugin_activation' ) ) {
	/**
	 * Takes care of deactivation of the premium plugin when the free plugin is activated. The opposite direction is handled
	 * by Freemius SDK.
	 *
	 * Note: This code MUST NOT be present in the premium version an is removed automatically during the build process.
	 *
	 * @since 4.3.2
	 */
	function wsal_free_on_plugin_activation() {
		$premium_version_slug = 'wp-security-audit-log-premium/wp-security-audit-log.php';
		if ( WpSecurityAuditLog::is_plugin_active( $premium_version_slug ) ) {
			deactivate_plugins( $premium_version_slug, true );
		}
	}

	register_activation_hook( __FILE__, 'wsal_free_on_plugin_activation' );
}
