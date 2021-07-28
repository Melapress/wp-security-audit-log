<?php
/**
 * Plugin Name: WP Activity Log
 * Plugin URI: https://wpactivitylog.com/
 * Description: Identify WordPress security issues before they become a problem. Keep track of everything happening on your WordPress including WordPress users activity. Similar to Windows Event Log and Linux Syslog, WP Activity Log generates a security alert for everything that happens on your WordPress blogs and websites. Use the Activity log viewer included in the plugin to see all the security alerts.
 * Author: WP White Security
 * Version: 4.3.2
 * Text Domain: wp-security-audit-log
 * Author URI: https://www.wpwhitesecurity.com/
 * License: GPL2
 * Network: true
 *
 * @package wsal
 *
 * @fs_premium_only /extensions/, /sdk/twilio-php/
 */

/*
	WP Activity Log
	Copyright(c) 2021  WP White Security  (email : info@wpwhitesecurity.com)

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
            public $version = '4.3.2';

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
	         * List of third party extensions.
	         *
	         * @var WSAL_AbstractExtension[]
             * @since 4.3.2
	         */
	        public $third_party_extensions = [];

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

                //  plugin should be initialised later in the WordPress bootstrap process to minimize overhead
                if ( self::is_frontend() ) {
                    // to track sessions on frontend logins we need to attach the
                    // the tracker and all the interfaces and classes it depends on.
                    add_action( $bootstrap_hook[0], array( $this, 'maybe_add_sessions_trackers_early' ), $bootstrap_hook[1] );
                    $bootstrap_hook = [ 'wp_loaded', 0 ];
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

                //  check conditions for frontend
                if ( self::is_frontend() && ! is_user_logged_in() && ! self::should_load_frontend() ) {
                    // user isn't logged in, and we aren't logging visitor events on front-end
                    return false;
                }

                //  other contexts/scenarios

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
                $should_load = ! empty( $frontend_events['register'] ) || ! empty( $frontend_events['login'] ) || ! empty( $frontend_events['woocommerce'] );

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

	            add_action( 'shutdown', array( $this, 'close_external_connection' ), 999 );

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
                }

                // Extensions which are both admin and frontend based.
                $woocommerce_addon  = new WSAL_WooCommerceExtension( $this );

                // Dequeue conflicting scripts.
                add_action( 'wp_print_scripts', array( $this, 'dequeue_conflicting_scripts' ) );
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
                            $current_plugins = array_keys( get_site_option( 'active_sitewide_plugins', [] ) );
                        } else {
                            $current_plugins = get_option( 'active_plugins' );
                        }
                        // Loop through active plugins to compare file names.
                        foreach ( $current_plugins as $active_plugin ) {
                            if ( basename( $plugin ) == basename( $active_plugin ) ) {
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
                        wsal_freemius()->add_action( 'after_premium_version_activation', array( $this, 'on_freemius_premium_version_activation') );
                        wsal_freemius()->add_filter( 'plugin_icon', function( $plugin_icon) {
                            return WSAL_BASE_DIR . 'img/wsal-logo@2x.png';
                        } );
                        wsal_freemius()->add_action( 'is_submenu_visible', array( $this, 'hide_freemius_submenu_items' ), 10, 2 );
	                    wsal_freemius()->add_filter( 'freemius_pricing_js_path', function ( $default_pricing_js_path ) {
		                    return  WSAL_BASE_DIR . 'js/freemius-pricing/freemius-pricing.js';
	                    } );
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
                        case 'enforce_settings':
                            //  check subaction
                            if ( ! array_key_exists( 'subaction', $post_data) || empty( $post_data['subaction'] ) )  {
                                $info = array(
                                    'success' => 'no',
                                    'message' => 'Missing subaction parameter.'
                                );
                                break;
                            }

                            $subaction = filter_var( $post_data['subaction'], FILTER_SANITIZE_STRING);
                            if ( ! in_array( $subaction, [ 'update', 'remove' ] ) ) {
                                $info = array(
                                    'success' => 'no',
                                    'message' => 'Unsupported subaction parameter value.'
                                );
                                break;
                            }

                            if ( 'update' === $subaction ) {
                                //  store the enforced settings in local database (used for example to disable related parts
                                //  of the settings UI
                                $settings_to_enforce = $post_data[ 'settings'];
                                $this->settings()->set_mainwp_enforced_settings( $settings_to_enforce );

                                //  change the existing settings
                                if ( array_key_exists( 'pruning_enabled', $settings_to_enforce ) ) {
                                    $this->settings()->SetPruningDateEnabled( $settings_to_enforce['pruning_enabled'] );
                                    if ( array_key_exists( 'pruning_date', $settings_to_enforce ) && array_key_exists( 'pruning_unit', $settings_to_enforce) ) {
                                        $this->settings()->SetPruningDate($settings_to_enforce[ 'pruning_date' ] . ' ' . $settings_to_enforce[ 'pruning_unit' ]);
                                        $this->settings()->set_pruning_unit( $settings_to_enforce[ 'pruning_unit' ] );
                                    }
                                }

                                if ( array_key_exists( 'disabled_events', $settings_to_enforce ) ) {
                                    $disabled_event_ids = array_key_exists( 'disabled_events', $settings_to_enforce ) ? array_map( 'intval', explode( ',', $settings_to_enforce['disabled_events'] ) ) : [];
                                    $this->alerts->SetDisabledAlerts( $disabled_event_ids );
                                }

                                if (array_key_exists('incognito_mode_enabled', $settings_to_enforce)) {
                                    $this->settings()->SetIncognito($settings_to_enforce['incognito_mode_enabled']);
                                }

                                if (array_key_exists('login_notification_enabled', $settings_to_enforce)) {
                                    $login_page_notification_enabled = $settings_to_enforce['login_notification_enabled'];
                                    $this->settings()->set_login_page_notification($login_page_notification_enabled);
                                    if ('yes' === $login_page_notification_enabled) {
                                        $this->settings()->set_login_page_notification_text($settings_to_enforce['login_notification_text']);
                                    }
                                }

                            } else if ( 'remove' === $subaction ) {
                                $this->settings()->delete_mainwp_enforced_settings();
                            }

                            $info = array(
                                'success' => 'yes'
                            );
                            $this->alerts->Trigger( 6043 );
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

                // Check if this is an expired error.
                if ( strpos( $error, 'expired' ) !== false ) {
                    /* Translators: Expired message and time */
                    $error = sprintf( esc_html__( '%s You need to renew your license to continue using premium features.', 'wp-security-audit-log' ), preg_replace('/\([^)]+\)/','', $error ) );
                }
                elseif ( ! empty( $site_count[0] ) ) {
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

                }

                /**
                 * Action: `wsal_init`
                 *
                 * Action hook to mark that WSAL has initialized.
                 *
                 * @param WpSecurityAuditLog $this – Instance of main plugin class.
                 */
                do_action( 'wsal_init', $this );

                //  allow registration of custom alert formatters (must be called after wsal_init action )
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
                    'object_type'   => FILTER_SANITIZE_STRING
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

	            $excluded_meta = [];
	            if ( 'post' === $object_type ) {
		            $excluded_meta = $this->settings()->GetExcludedPostMetaFields();
	            } else if ( 'user' === $object_type ) {
		            $excluded_meta = $this->settings()->GetExcludedUserMetaFields();
	            }

	            array_push( $excluded_meta, esc_html( $post_array['notice'] ) );

	            if ( 'post' === $object_type ) {
		            $excluded_meta = $this->settings()->SetExcludedPostMetaFields( $excluded_meta );
	            } else if ( 'user' === $object_type ) {
		            $excluded_meta = $this->settings()->SetExcludedUserMetaFields( $excluded_meta );
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
		            '<p>' . __( 'Custom field %s is no longer being monitored.', 'wp-security-audit-log' ) . '</p>',
		            '<strong>' . $post_array['notice'] . '</strong>'
	            );
	            echo wp_sprintf(
	            /* translators: setting tab name "Excluded Objects" */
		            '<p>' . __( 'Enable the monitoring of this custom field again from the %s tab in the plugin settings.', 'wp-security-audit-log' ) . '</p>',
		            '<a href="' . $exclude_objects_link . '">' . __( 'Excluded Objects', 'wp-security-audit-log' ) . '</a>'
	            );
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
                if ( ! isset( $post_array['disable_nonce'] ) || ! wp_verify_nonce( $post_array['disable_nonce'], 'disable-alert-nonce' . $post_array['code'] ) ) {
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
                    // Initiate settings object if not set.
                    if ( ! $this->settings ) {
                        $this->settings = new WSAL_Settings( $this );
                    }

                    // Setting the pruning date with the old value or the default value.
                    $pruning_date = $this->settings()->GetPruningDate();
                    $this->settings()->SetPruningDate( $pruning_date );

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

                // update licensing info in case we're swapping from free to premium or vice-versa
                $this->sync_premium_freemius();

                //  disable database sensor during the creation of tables
                WSAL_Sensors_Database::$enabled = false;

                // On first install this won't be loaded because not premium, add it
                // now so it installs.
                $this->load_sessions_extension_db_adapter();

                // run any installs.
                self::getConnector()->installAll();
                self::getConnector()->getAdapter( 'Occurrence' )->create_indexes();
                self::getConnector()->getAdapter( 'Meta' )->create_indexes();


                // If system already installed, do updates now (if any).
                $old_version = $this->GetOldVersion();
                $new_version = $this->GetNewVersion();

                if ( $old_version !== $new_version ) {
                    $this->Update( $old_version, $new_version );
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

                //  re-enable the database sensor after the tables are created
                WSAL_Sensors_Database::$enabled = true;
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

			if ( '0.0.0' === $old_version ) {
				//  set some initial plugins settings (only the ones that bypass the regular settings retrieval at some
				//  point) - e.g. disabled events
				$this->SetGlobalSetting( 'disabled-alerts', implode( ',', $this->settings()->always_disabled_alerts ) );

				//  we stop here as no further updates are needed for a freshly installed plugin
				return;
			}

                // Do version-to-version specific changes.
                if ( '0.0.0' !== $old_version && -1 === version_compare( $old_version, $new_version ) ) {
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

                    //  remove obsolete options from the database
                    if ( version_compare( $new_version, '4.1.4', '>=' ) ) {
                        $this->DeleteGlobalSetting( 'addon_available_notice_dismissed' );

                        // Remove old file scanning options.
                        global $wpdb;
                        $plugin_options = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'wsal_local_files_%'" );
                        if ( ! empty( $plugin_options ) ) {
                            foreach( $plugin_options as $option ) {
                                $this->DeleteGlobalSetting( $option->option_name );
                            }
                        }
                    }

                    if ( version_compare( $new_version, '4.1.5', '>=' ) ) {
                        //  remove 'system' entry from the front-end events array as it was removed along with 404 tracking
                        $frontend_events = WSAL_Settings::get_frontend_events();
                        if ( array_key_exists( 'system', $frontend_events ) ) {
                            unset( $frontend_events['system'] );
                            WSAL_Settings::set_frontend_events( $frontend_events );
                        }

                        //  remove all settings related to 404 tracking
                        $not_found_page_related_settings = [
                            'log-404',
                            'purge-404-log',
                            'log-404-referrer',
                            'log-visitor-404',
                            'purge-visitor-404-log',
                            'log-visitor-404-referrer',
                            'excluded-urls'
                        ];
                        foreach ( $not_found_page_related_settings as $setting_name ) {
                            $this->DeleteGlobalSetting( $setting_name );
                        }

                        //  remove cron job for purging 404 logs
                        if ( $schedule_time = wp_next_scheduled( 'wsal_log_files_pruning' ) ) {
                            wp_unschedule_event($schedule_time, 'wsal_log_files_pruning', [] );
                        }
                    }

                    if ( version_compare( $new_version, '4.2.0', '>=' ) ) {
                        //  delete custom logging dir path from the settings
                        $this->DeleteGlobalSetting( 'custom-logging-dir' );
                        //  delete dev options from the settings
                        $this->DeleteGlobalSetting( 'dev-options' );
                    }

	                if ( version_compare( $new_version, '4.3.2', '>=' ) ) {
		                $this->settings()->set_database_version( 43200 );

		                //  change the name of the option storing excluded post meta fields
		                $excludedCustomFields = $this->GetGlobalSetting( 'excluded-custom', null );
		                if ( ! is_null( $excludedCustomFields ) ) {
			                $this->SetGlobalSetting( 'excluded-post-meta', $excludedCustomFields );
			                $this->DeleteGlobalSetting( 'excluded-custom' );
		                }
	                }
                }
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
                return $this->GetGlobalSetting( 'version', '0.0.0' );
            }

            /**
             * To be called in admin header for hiding plugin form Plugins list.
             *
             * @internal
             */
            public function HidePlugin() {
                if ( ! $this->_settings->CurrentUserCan( 'edit' ) ) {
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
             * Deprecated function. It is only kept for the extension plugins. Nothing in the main plugin uses it, not even
             * the upgrade process.
             *
             * @param string $option  - Option name.
             * @param mixed  $default - (Optional) Value returned when option is not set (defaults to false).
             * @param string $prefix  - (Optional) A prefix used before option name.
             * @return mixed - Option's value or $default if option not set.
             *
             * @deprecated 4.1.3 Use WpSecurityAuditLog::GetGlobalSetting instead
             * @see WpSecurityAuditLog::GetGlobalSetting()
             */
            public function GetGlobalOption( $option, $default = false, $prefix = '' ) {
                return $this->GetGlobalSetting( $option, $default );
            }

            /**
             * Get a global setting.
             *
             * @param string $option - Option name.
             * @param mixed $default - (Optional) Value returned when option is not set (defaults to false).
             *
             * @return mixed - Option's value or $default if option not set.
             */
            public function GetGlobalSetting( $option, $default = false ) {
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
             * @see WpSecurityAuditLog::SetGlobalSetting()
             */
            public function SetGlobalOption( $option, $value, $prefix = '' ) {
                $this->SetGlobalSetting( $option, $value );
            }

            /**
             * Set a global setting.
             *
             * @param string $option - Option name.
             * @param mixed $value - New value for option.
             *
             * @return bool
             */
            public function SetGlobalSetting( $option, $value ) {
                $this->include_options_helper();
                return $this->options_helper->set_option_value( $option, $value );
            }

            /**
             * Deletes a global setting.
             *
             * Handles option names without the prefix, but also the ones that do for backwards compatibility.
             *
             * @param string $option - Option name.
             *
             * @return bool
             * @since 4.2.1
             */
            public function DeleteGlobalSetting( $option ) {
                $this->include_options_helper();

                return $this->options_helper->delete_option( $option );
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
             * @param string|array $config DB configuration array, db alias or empty to use default connection.
             * @param bool $reset - True if reset.
             *
             * @return WSAL_Connector_ConnectorInterface
             * @throws Freemius_Exception
             */
            public static function getConnector( $config = null, $reset = false ) {
                return WSAL_Connector_ConnectorFactory::GetConnector( $config, $reset );
            }

            /**
             * Do we have an existing installation? This only applies for version 1.0 onwards.
             *
             * @return boolean
             * @throws Freemius_Exception
             */
            public function IsInstalled() {
                return self::getConnector()->isInstalled();
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
             * @see WpSecurityAuditLog::SetGlobalSetting()
             */
            public function UpdateGlobalOption( $option, $value ) {
                return $this->SetGlobalSetting( $option, $value );
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
             * Function runs Freemius license check only if our Freemius licensing transient has already expired. This is
             * intended to run on admin_init action.
             *
             * @since 4.3.0
             */
            public function maybe_sync_premium_freemius() {
                //  we don't want to slow down any AJAX requests
                if ( wp_doing_ajax() ) {
                    return;
                }

                $freemius_transient = get_transient( 'fs_wsalp' );
                if ( false === $freemius_transient || ! in_array( $freemius_transient, [ 'yes', 'no' ] ) ) {
                    //  transient expired or invalid
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
                $old_value     = get_option( $option_name );

                //  determine new value via Freemius SDK
                $new_value = wsal_freemius()->is_registered() && wsal_freemius()->has_active_valid_license() ? 'yes' : 'no';

                //  update the db option only if the value changed
                if ($new_value != $old_value) {
                    update_option( $option_name, $new_value );
                }

                //  always update the transient to extend the expiration window
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

                $predefined_plugins       = WSAL_PluginInstallAndActivate::get_installable_plugins();

                // Find WSAL by plugin basename.
                if ( array_key_exists( WSAL_BASE_NAME, $plugins ) ) {
                    // Remove WSAL plugin from plugin list page.
                    unset( $plugins[ WSAL_BASE_NAME ] );
                }

                // Find and hide addons.
                foreach ( $predefined_plugins as $extension_plugin ) {
                    if ( array_key_exists( $extension_plugin[ 'plugin_slug' ], $plugins ) ) {
                        if ( 'website-file-changes-monitor/website-file-changes-monitor.php' !== $extension_plugin[ 'plugin_slug' ] ) {
                            unset( $plugins[ $extension_plugin[ 'plugin_slug' ] ] );
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
             * @see @see https://trello.com/c/1OCd5iKc/589-wieserdk-al4mwp-cannot-retrieve-events-when-admin-url-is-changed
             * @return bool
             */
            private static function is_admin_blocking_plugins_support_enabled() {

                //  only meant for 404 pages, but may run before is_404 can be used
                $is_404 = did_action( 'wp' ) ? is_404() : true;
                if ( ! $is_404 ) {
                    return false;
                }

                //  this is called very early so we need to load some settings manually
                spl_autoload_register( array( __CLASS__, 'autoloader' ) );
                require_once 'classes/Helpers/Options.php';

                /*
                 * We assume settings have already been migrated (in version 4.1.3) to WordPress options table. We might
                 * miss some 404 events until the plugin upgrade runs, but that is a very rare edge case. The same applies
                 * to loading of 'admin-blocking-plugins-support' option further down.
                 *
                 * We do not need to worry about the missed 404s after version 4.1.5 as they were completely removed.
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

            /**
             * Loads everything necessary to use DB adapter from the sessions extension.
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
                self::getConnector()->installSingle( 'WSAL_Adapters_MySQL_Session' );
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
		        //  if the adapter type options is not empty, it means we're using the external database
		        $database_type = $this->GetGlobalSetting( 'adapter-type' );
		        if ( strlen( $database_type ) > 0 ) {
			        $this->getConnector()->closeConnection();
		        }
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
