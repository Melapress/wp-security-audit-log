<?php
/**
 * WP Activity Log.
 *
 * @copyright Copyright (C) 2013-2023, WP White Security - support@wpwhitesecurity.com
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name: WP Activity Log
 * Version:     4.5.3
 * Plugin URI:  https://wpactivitylog.com/
 * Description: Identify WordPress security issues before they become a problem. Keep track of everything happening on your WordPress, including users activity. Similar to Linux Syslog, WP Activity Log generates an activity log with a record of everything that happens on your WordPress websites.
 * Author:      WP White Security
 * Author URI:  https://www.wpwhitesecurity.com/
 * Text Domain: wp-security-audit-log
 * Domain Path: /languages/
 * License:     GPL v3
 * Requires at least: 5.0
 * Requires PHP: 7.2
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

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\Email_Helper;
use WSAL\Helpers\Plugins_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Actions\Pluging_Installer;
use WSAL\Controllers\Plugin_Extensions;
use WSAL\Controllers\Sensors_Load_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require Composer autoloader if it exists.
if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

if ( ! defined( 'WSAL_PREFIX' ) ) {
	define( 'WSAL_VERSION', '4.5.3' );
	define( 'WSAL_PREFIX', 'wsal_' );
	define( 'WSAL_PREFIX_PAGE', 'wsal-' );
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

/**
 * Connections Prefix.
 */
if ( ! defined( 'WSAL_CONN_PREFIX' ) ) {
	define( 'WSAL_CONN_PREFIX', 'connection-' );
}
if ( ! defined( 'WSAL_MIRROR_PREFIX' ) ) {
	define( 'WSAL_MIRROR_PREFIX', 'mirror-' );
}

/* @free:start */
if ( ! function_exists( 'wsal_disable_freemius_on_free' ) ) {
	/**
	 * Disables the freemius
	 *
	 * @return WSAL_Freemius
	 *
	 * @since 4.5.0
	 */
	function wsal_disable_freemius_on_free() {
		require_once dirname( __FILE__ ) . '/nofs/lib/class-wsal-freemius.php';

		return WSAL_Freemius::get_instance();
	}
}
add_filter( 'wsal_freemius_sdk_object', 'wsal_disable_freemius_on_free' );
add_filter( 'wsal_disable_freemius_sdk', '__return_true' );
/* @free:end */

if ( ! function_exists( 'wsal_freemius' ) ) {

	if ( ! class_exists( 'WpSecurityAuditLog' ) ) {

		/**
		 * WSAL Main Class.
		 *
		 * @package wsal
		 */
		class WpSecurityAuditLog {

			/**
			 * Plugin constants.
			 *
			 * The above is misleading - keeping it before making sure all occurrences are covered.
			 *
			 * From here the Autoloader tries to guess the classes - probably that is the meaning of the CLS - it looks like its not used anywhere else
			 *
			 * Possible name meaning - Plugin CLass Prefix
			 *
			 * TODO: Change then name.
			 *
			 * @var string
			 */
			const PLG_CLS_PRFX = 'WSAL_';

			/**
			 * Minimal PHP version.
			 *
			 * @var string
			 */
			const MIN_PHP_VERSION = '7.2.0';

			/**
			 * New option name prefix.
			 *
			 * @var string
			 *
			 * @deprecated latest - This was deprecated and will be removed, use WSAL_PREFIX instead - it is globally available.
			 */
			const OPTIONS_PREFIX = 'wsal_';

			/**
			 * Views supervisor.
			 *
			 * @var WSAL_ViewManager
			 */
			public $views;

			/**
			 * Legacy - added because of php8 deprecation remove
			 *
			 * @var [type]
			 *
			 * @since 4.5.0
			 */
			public $widgets;

			/**
			 * Settings manager.
			 *
			 * @var WSAL_Settings
			 */
			protected $settings;

			/**
			 * Constants manager.
			 *
			 * @var WSAL_ConstantManager
			 */
			public $constants;

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

				add_action( $bootstrap_hook[0], array( $this, 'setup' ), $bootstrap_hook[1] );

				// Register plugin specific activation hook.
				register_activation_hook( __FILE__, array( $this, 'install' ) );

				// Plugin Deactivation Actions.
				register_deactivation_hook( __FILE__, array( $this, 'deactivate_actions' ) );

				// Add custom schedules for WSAL early otherwise they won't work.
				add_filter( 'cron_schedules', array( $this, 'recurring_schedules' ) );


				// Hide all unrelated to the plugin notices on the plugin admin pages.
				add_action( 'admin_print_scripts', array( '\WSAL\Helpers\WP_Helper', 'hide_unrelated_notices' ) );

				add_action(
					'init',
					function() {
						WSAL\Controllers\Alert_Manager::init();
						Sensors_Load_Manager::load_sensors();
					},
					$bootstrap_hook[1]
				);
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
					&& ! WP_Helper::is_login_screen()
					&& ( ! defined( 'WP_CLI' ) || ! WP_CLI )
					&& ( ! defined( 'DOING_CRON' ) || ! DOING_CRON )
					&& ! self::is_rest_api()
					&& ! self::is_admin_blocking_plugins_support_enabled();
			}

			/**
			 * Whether the current request is a frontend request.
			 *
			 * @return bool
			 */
			public static function is_frontend_page() {
				return ! is_admin()
					&& ! WP_Helper::is_login_screen()
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

				$this->set_allowed_html_tags();
				$this->includes();
				\WSAL\Utils\Migration::migrate();
				$this->init_hooks();
				self::load_defaults();
				$this->load_wsal();
				$this->init();
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
						if ( isset( $_REQUEST['checked'] ) ) {
							$selected_plugins = $_REQUEST['checked'];
							if ( ! empty( $selected_plugins ) ) {
								foreach ( $selected_plugins as $selected_plugin ) {
									if ( 'wp-security-audit-log.php' === basename( $selected_plugin ) ) {
										return false;
									}
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
				$frontend_events = Settings_Helper::get_frontend_events();
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

				if ( WP_Helper::is_multisite() ) {
					$cpts_tracker = new \WSAL\Multisite\NetworkWide\CPTsTracker( $this );
					$cpts_tracker->setup();
				}
			}

			/**
			 * Initialize Plugin Hooks.
			 *
			 * @since 3.3
			 */
			public function init_hooks() {
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
					Pluging_Installer::init();
				}

				Plugin_Extensions::init();

				// Dequeue conflicting scripts.
				add_action( 'wp_print_scripts', array( $this, 'dequeue_conflicting_scripts' ) );

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
			 *
			 * @deprecated 4.5 - Use \WSAL\Helpers\WP_Helper::is_multisite()
			 */
			public static function is_plugin_active( $plugin ) {
				_deprecated_function( __FUNCTION__, '4.5', '\WSAL\Helpers\WP_Helper::is_multisite()' );

				return WP_Helper::is_plugin_active( $plugin );
			}

			/**
			 * Check if BBPress plugin is active or not.
			 *
			 * @return boolean
			 */
			public static function is_bbpress_active() {
				return ( WP_Helper::is_plugin_active( 'bbpress/bbpress.php' )
					&& ( WP_Helper::is_plugin_active( 'wsal-bbpress.php' ) )
				);
			}

			/**
			 * Check if WooCommerce plugin is active or not.
			 *
			 * @return boolean
			 */
			public static function is_woocommerce_active() {
				return ( WP_Helper::is_plugin_active( 'woocommerce/woocommerce.php' )
					&& ( WP_Helper::is_plugin_active( 'wp-activity-log-for-woocommerce/wsal-woocommerce.php' ) )
				);
			}

			/**
			 * Check if Yoast SEO plugin is active or not.
			 *
			 * @return boolean
			 */
			public static function is_wpseo_active() {
				return ( ( WP_Helper::is_plugin_active( 'wordpress-seo/wp-seo.php' ) || WP_Helper::is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) )
					&& ( WP_Helper::is_plugin_active( 'activity-log-wp-seo/activity-log-wp-seo.php' ) )
				);
			}

			/**
			 * Check if MainWP plugin is active or not.
			 *
			 * @return boolean
			 */
			public static function is_mainwp_active() {
				return WP_Helper::is_plugin_active( 'mainwp-child/mainwp-child.php' );
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

				if ( $is_admin_blocking_plugins_support_enabled || is_admin() || WP_Helper::is_login_screen() || self::is_rest_api() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {

					self::load_freemius();
					// phpcs:ignore
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
				return 'no' === \WSAL\Helpers\Settings_Helper::get_option_value( WSAL_PREFIX . 'disable-visitor-events' );
			}

			/**
			 * Whether an alert is enabled. For use before loading the settings.
			 *
			 * @param string|int $alert The alert to check.
			 * @return bool Whether the alert is enabled.
			 */
			public static function raw_alert_is_enabled( $alert ) {
				$alerts = \WSAL\Helpers\Settings_Helper::get_option_value( WSAL_PREFIX . 'disabled-alerts' );
				$alerts = explode( ',', $alerts );
				return ! in_array( $alert, $alerts ); // phpcs:ignore
			}

			/**
			 * Method: WSAL plugin redirect.
			 */
			public function wsal_plugin_redirect() {
				// WSAL State.
				$wsal_state = WSAL\Helpers\Settings_Helper::get_option_value( 'freemius_state', 'anonymous' );

				if (
						in_array( $wsal_state, array( 'anonymous', 'skipped' ), true ) && WSAL\Helpers\Settings_Helper::get_option_value( 'redirect_on_activate', false ) // Redirect flag.
				) {
					// If the redirect option is true, then continue.
					\WSAL\Helpers\Settings_Helper::delete_option_value( 'wsal_redirect_on_activate' ); // Delete redirect option.

					// Redirect URL.
					$redirect = '';

					// If current site is multisite and user is super-admin then redirect to network audit log.
					if ( WP_Helper::is_multisite() && $this->settings()->current_user_can( 'edit' ) && is_super_admin() ) {
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
				// if ( ! isset( $this->alerts ) ) {
					// $this->alerts = new WSAL_AlertManager( $this );
				// }

				// if ( ! isset( $this->constants ) ) {
				// 	$this->constants = new WSAL_ConstantManager();
				// }

				// $this->sensors = new WSAL_SensorManager( $this );

				// Sensors_Load_Manager::load_sensors();

				if ( is_admin() ) {
					$this->views   = new WSAL_ViewManager( $this );
					$this->widgets = new WSAL_WidgetManager( $this );
				}

				// Start listening to events.
				// if ( ! empty( $this->sensors ) && $this->sensors instanceof WSAL_SensorManager ) {
				// $this->sensors->hook_events();
				// }

				if ( is_admin() ) {

					// Hide plugin.
					if ( Settings_Helper::get_boolean_option_value( 'hide-plugin' ) ) {
						add_action( 'admin_head', array( $this, 'hide_plugin' ) );
						add_filter( 'all_plugins', array( $this, 'wsal_hide_plugin' ) );
					}
				}

				\WSAL\Controllers\Constants::init();

				/**
				 * Action: `wsal_init`
				 *
				 * Action hook to mark that WSAL has initialized.
				 *
				 * @param WpSecurityAuditLog $this – Instance of main plugin class.
				 */
				do_action( 'wsal_init', $this );

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
					Email_Helper::send_deactivation_email();
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

				// Filter $_POST array for security.
				$post_array = filter_input_array( INPUT_POST );

				$disable_nonce    = ( isset( $_POST['disable_nonce'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['disable_nonce'] ) ) : null;
				$notice           = ( isset( $_POST['notice'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['notice'] ) ) : null;
				$object_type_post = ( isset( $_POST['object_type'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['object_type'] ) ) : null;

				if ( ! isset( $disable_nonce ) || ! wp_verify_nonce( $disable_nonce, 'disable-custom-nonce' . $notice ) ) {
					die();
				}

				$object_type = 'post';
				if ( array_key_exists( 'object_type', $post_array ) && 'user' === $object_type_post ) {
					$object_type = 'user';
				}

				$excluded_meta = array();
				if ( 'post' === $object_type ) {
					$excluded_meta = \WSAL\Helpers\Settings_Helper::get_excluded_post_meta_fields();
				} elseif ( 'user' === $object_type ) {
					$excluded_meta = \WSAL\Helpers\Settings_Helper::get_excluded_user_meta_fields();
				}

				array_push( $excluded_meta, esc_html( $notice ) );

				if ( 'post' === $object_type ) {
					$excluded_meta = \WSAL\Helpers\Settings_Helper::set_excluded_post_meta_fields( $excluded_meta );
				} elseif ( 'user' === $object_type ) {
					$excluded_meta = \WSAL\Helpers\Settings_Helper::set_excluded_user_meta_fields( $excluded_meta );
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
					'<strong>' . $notice . '</strong>' // phpcs:ignore
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

				$disable_nonce = \sanitize_text_field( \wp_unslash( $_POST['disable_nonce'] ) );
				$code          = \sanitize_text_field( \wp_unslash( $_POST['code'] ) );

				if ( ! isset( $disable_nonce ) || ! wp_verify_nonce( $disable_nonce, 'disable-alert-nonce' . $code ) ) {
					die();
				}

				$s_alerts = WSAL\Helpers\Settings_Helper::get_option_value( 'disabled-alerts' );
				if ( isset( $s_alerts ) && '' != $s_alerts ) { // phpcs:ignore
					$s_alerts .= ',' . esc_html( $code );
				} else {
					$s_alerts = esc_html( $code );
				}
				\WSAL\Helpers\Settings_Helper::set_option_value( 'disabled-alerts', $s_alerts );

				echo wp_sprintf(
					'<p>' . __( 'Alert %1$s is no longer being monitored.<br /> %2$s', 'wp-security-audit-log' ) . '</p>',
					esc_html( $code ),
					esc_html__( 'You can enable this alert again from the Enable/Disable Alerts node in the plugin menu.', 'wp-security-audit-log' ) ); // phpcs:ignore
				die;
			}

			/**
			 * Render plugin stuff in page footer.
			 *
			 * @internal
			 */
			public function render_footer() {
				// File is not there / deleted during upgrade ? either way stop the execution.
				if ( ! file_exists( WSAL_BASE_DIR . '/js/common.js' ) ) {
					return;
				}

				// Register common script.
				wp_register_script(
					'wsal-common',
					WSAL_BASE_URL . '/js/common.js',
					array( 'jquery' ),
					WSAL_VERSION,
					true
				);

				// Live events disabled in free version of the plugin.
				$live_events_enabled = false;
				// phpcs:ignore
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

				if ( is_admin() ) {
					// Initiate settings object if not set.
					if ( ! $this->settings ) {
						$this->settings = new WSAL_Settings( $this );
					}

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

				if ( WP_Helper::is_plugin_active( 'mainwp/mainwp.php' ) ) {
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

				// Update licensing info in case we're swapping from free to premium or vice-versa.
				$this->sync_premium_freemius();

				// Disable database sensor during the creation of tables.
				\WSAL\WP_Sensors\WP_Database_Sensor::set_enabled( false );

				// On first install this won't be loaded because not premium, add it
				// now so it installs.
				$this->load_sessions_extension_db_adapter();

				// run any installs.
				self::get_connector()->install_all();
				// self::get_connector()->get_adapter( 'Occurrence' )->create_indexes();
				// self::get_connector()->get_adapter( 'Meta' )->create_indexes();


				// Install cleanup hook (remove older one if it exists).
				wp_clear_scheduled_hook( 'wsal_cleanup' );
				wp_schedule_event( current_time( 'timestamp' ) + 600, 'hourly', 'wsal_cleanup' ); // phpcs:ignore

				// WSAL Audit Log page redirect option in anonymous mode.
				if ( 'anonymous' === WSAL\Helpers\Settings_Helper::get_option_value( 'freemius_state', 'anonymous' ) ) {
					\WSAL\Helpers\Settings_Helper::set_option_value( 'redirect_on_activate', true );
				}

				// Run on each install to check MainWP Child plugin.
				$this->settings()->set_mainwp_child_stealth_mode();

				// Re-enable the database sensor after the tables are created.
				\WSAL\WP_Sensors\WP_Database_Sensor::set_enabled( true );
			}

			/**
			 * Gets all transient keys in the database with a specific prefix.
			 *
			 * Note that this doesn't work for sites that use a persistent object
			 * cache, since in that case, transients are stored in memory.
			 *
			 * @param  string $prefix Prefix to search for.
			 * @return array          Transient keys with prefix, or empty array on error.
			 */
			public static function get_transient_keys_with_prefix( $prefix ) {
				global $wpdb;

				$prefix = $wpdb->esc_like( '_transient_' . $prefix );
				$sql    = "SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE '%s'";
				$keys   = $wpdb->get_results( $wpdb->prepare( $sql, $prefix . '%' ), ARRAY_A );

				if ( is_wp_error( $keys ) ) {
					return array();
				}

				return array_map(
					function( $key ) {
						// Remove '_transient_' from the option name.
						return ltrim( $key['option_name'], '_transient_' );
					},
					$keys
				);
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
			 * Return whether we are running on multisite or not.
			 *
			 * @return boolean
			 *
			 * @deprecated 4.5 - Use \WSAL\Helpers\WP_Helper::is_multisite()
			 */
			public static function is_multisite() {
				_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\WP_Helper::is_multisite()' );

				return \WSAL\Helpers\WP_Helper::is_multisite();
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
				return WSAL\Helpers\Settings_Helper::get_option_value( $option, $default );
			}

			/**
			 * Get a global setting.
			 *
			 * @param string $option - Option name.
			 * @param mixed  $default - (Optional) Value returned when option is not set (defaults to false).
			 *
			 * @return mixed - Option's value or $default if option not set.
			 *
			 * @deprecated 4.4.3 - Use \WSAL\Helpers\Settings_Helper::get_option_value()
			 */
			public function get_global_setting( $option, $default = false ) {
				_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\Settings_Helper::get_option_value()' );

				return \WSAL\Helpers\Settings_Helper::get_option_value( $option, $default );
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
			 *
			 * @deprecated 4.4.3 - Use \WSAL\Helpers\Settings_Helper::set_option_value()
			 */
			public function set_global_setting( $option, $value, $autoload = false ) {
				_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\Settings_Helper::set_option_value()' );

				return \WSAL\Helpers\Settings_Helper::set_option_value( $option, $value, $autoload );
			}

			/**
			 * Deletes a global setting.
			 *
			 * @param string $option - Option name without the prefix.
			 *
			 * @return bool
			 * @since 4.2.1
			 *
			 * @deprecated 4.4.3 - Use \WSAL\Helpers\Settings_Helper::delete_option_value()
			 */
			public function delete_global_setting( $option ) {
				_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\Settings_Helper::delete_option_value()' );

				return \WSAL\Helpers\Settings_Helper::delete_option_value( $option );
			}

			/**
			 * Get a global boolean setting. It takes care of the conversion between string and boolean.
			 *
			 * @param string  $option  - Option name.
			 * @param boolean $default - (Optional) Value returned when option is not set (defaults to false).
			 * @return boolean - Option's value or $default if option not set.
			 * @since 4.1.3
			 *
			 * @deprecated 4.4.3 - Use \WSAL\Helpers\Settings_Helper::get_boolean_option_value()
			 */
			public function get_global_boolean_setting( $option, $default = false ) {
				_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\Settings_Helper::get_boolean_option_value()' );

				return \WSAL\Helpers\Settings_Helper::get_boolean_option_value( $option, $default );
			}

			/**
			 * Sets a global boolean setting. It takes care of the conversion between string and boolean.
			 *
			 * @param string $option - Option name.
			 * @param mixed  $value - New value for option.
			 * @param bool   $autoload Whether or not to autoload this option.
			 *
			 * @since 4.1.3
			 *
			 * @deprecated 4.4.3 - Use \WSAL\Helpers\Settings_Helper::set_boolean_option_value()
			 */
			public function set_global_boolean_setting( $option, $value, $autoload = false ) {
				_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\Settings_Helper::set_boolean_option_value()' );

				\WSAL\Helpers\Settings_Helper::set_boolean_option_value( $option, $value, $autoload );
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
			 *
			 * @deprecated 4.4.3 - Use WSAL_BASE_URL constant
			 */
			public function get_base_url() {
				_deprecated_function( __FUNCTION__, '4.4.3', 'WSAL_BASE_URL' );

				return WSAL_BASE_URL;
			}

			/**
			 * Full path to plugin directory WITH final slash.
			 *
			 * @return string
			 *
			 * @deprecated 4.4.3 - Use WSAL_BASE_DIR constant
			 */
			public function get_base_dir() {
				_deprecated_function( __FUNCTION__, '4.4.3', 'WSAL_BASE_DIR' );

				return WSAL_BASE_DIR;
			}

			/**
			 * Plugin directory name.
			 *
			 * @return string
			 *
			 * @deprecated 4.4.3 - Use WSAL_BASE_NAME constant
			 */
			public function get_base_name() {
				_deprecated_function( __FUNCTION__, '4.4.3', 'WSAL_BASE_NAME' );

				return WSAL_BASE_NAME;
			}

			/**
			 * Load default configuration / data.
			 */
			public static function load_defaults() {
				\WSAL\Controllers\Constants::init();
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
				return \WSAL\Helpers\Settings_Helper::set_option_value( $option, $value );
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
				$schedules['fiveminutes']      = array(
					'interval' => 300,
					'display'  => __( 'Every 5 minutes', 'wp-security-audit-log' ),
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

				$freemius_transient = WP_Helper::get_transient( 'fs_wsalp' );
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
				$old_value   = get_site_option( $option_name );

				// Determine new value via Freemius SDK.
				$new_value = wsal_freemius()->has_active_valid_license() ? 'yes' : 'no';

				// Update the db option only if the value changed.
				if ( $new_value != $old_value ) { // phpcs:ignore
					update_site_option( $option_name, $new_value );
				}

				// Always update the transient to extend the expiration window.
				WP_Helper::set_transient( $option_name, $new_value, DAY_IN_SECONDS );
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
				return 'yes' === get_site_option( 'fs_wsalp' );
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

				$predefined_plugins = Plugins_Helper::get_installable_plugins();

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
				if ( 'pricing' === $submenu_id ) {
					$is_visible = false;
				}
				return $is_visible;
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

				/*
				 * We assume settings have already been migrated (in version 4.1.3) to WordPress options table. We might
				 * miss some 404 events until the plugin upgrade runs, but that is a very rare edge case. The same applies
				 * to loading of 'admin-blocking-plugins-support' option further down.
				 *
				 * We do not need to worry about the missed 404s after version 4.1.5 as they were completely removed.
				 */
				$is_stealth_mode = \WSAL\Helpers\Settings_Helper::get_option_value( 'mwp-child-stealth-mode', 'no' );

				if ( 'yes' !== $is_stealth_mode ) {
					// Only intended if MainWP stealth mode is active.
					return false;
				}

				// Allow if the admin blocking support settings is active.
				return ( 'yes' === \WSAL\Helpers\Settings_Helper::get_option_value( 'admin-blocking-plugins-support', 'no' ) );
			}

			/**
			 * Loads everything necessary to use DB adapter from the sessions' extension.
			 *
			 * @since 4.1.4.1
			 */
			public function load_sessions_extension_db_adapter() {
				if ( file_exists( plugin_dir_path( __FILE__ ) . 'extensions/user-sessions/user-sessions.php' ) ) {
					require_once plugin_dir_path( __FILE__ ) . 'extensions/user-sessions/user-sessions.php';
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
				$database_type = WSAL\Helpers\Settings_Helper::get_option_value( 'adapter-type' );
				if ( ! is_null( $database_type ) ) {
					if ( strlen( $database_type ) > 0 ) {
						$this->get_connector()->close_connection();
					}
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

		// phpcs:disable

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
			// phpcs:ignore
		}

		// Begin load sequence.
		WpSecurityAuditLog::get_instance();

		if ( is_admin() && ! WP_Helper::is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			WpSecurityAuditLog::load_freemius();

			if ( ! apply_filters( 'wsal_disable_freemius_sdk', false ) ) {
				wsal_freemius()->add_action( 'after_uninstall', array( 'WpSecurityAuditLog', 'uninstall' ) );
			}
		}
	}
} elseif ( ! method_exists( 'WSAL_Freemius', 'set_basename' ) ) {
	global $wsal_freemius;
	$wsal_freemius = null;
	unset( $wsal_freemius );
} else {
	wsal_freemius()->set_basename( true, __FILE__ );
}
/* @free:start */
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
		if ( WP_Helper::is_plugin_active( $premium_version_slug ) ) {
			deactivate_plugins( $premium_version_slug, true );
		}
		$premium_version_slug = 'wp-security-audit-log-nofs/wp-security-audit-log.php';
		if ( WP_Helper::is_plugin_active( $premium_version_slug ) ) {
			deactivate_plugins( $premium_version_slug, true );
		}
	}

	register_activation_hook( __FILE__, 'wsal_free_on_plugin_activation' );
}
// phpcs:disable
/* @free:end */
// phpcs:enable
