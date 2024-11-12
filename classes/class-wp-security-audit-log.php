<?php
/**
 * Audit Log List View
 *
 * Base WSAL class
 *
 * @since 5.0.0
 *
 * @package wsal
 */

use WSAL\Helpers\Notices;
use WSAL\Utils\Migration;
use WSAL\Helpers\WP_Helper;
use WSAL\MainWP\MainWP_Addon;
use WSAL\Helpers\Email_Helper;
use WSAL\Helpers\View_Manager;
use WSAL\Controllers\Constants;
use WSAL\Controllers\Cron_Jobs;
use WSAL\Controllers\Connection;
use WSAL\Helpers\Plugins_Helper;
use WSAL\Helpers\Widget_Manager;
use WSAL\Helpers\Settings_Helper;
use WSAL\Actions\Plugin_Installer;
use WSAL\Helpers\Uninstall_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Controllers\Plugin_Extensions;
use WSAL\WP_Sensors\WP_Database_Sensor;
use WSAL\Helpers\Plugin_Settings_Helper;
use WSAL\Controllers\Sensors_Load_Manager;
use WSAL\Migration\Metadata_Migration_440;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WpSecurityAuditLog' ) ) {

	/**
	 * WSAL Main Class.
	 *
	 * @package wsal
	 */
	class WpSecurityAuditLog {

		/**
		 * Minimal PHP version.
		 *
		 * @var string
		 *
		 * @since 5.0.0
		 */
		const MIN_PHP_VERSION = '7.2.0';

		/**
		 * Premium version constant
		 *
		 * @var string
		 *
		 * @since 5.1.0
		 */
		const PREMIUM_VERSION_WHOLE_PLUGIN_NAME = 'wp-security-audit-log-premium/wp-security-audit-log.php';

		/**
		 * NOFS version constant
		 *
		 * @var string
		 *
		 * @since 5.1.0
		 */
		const NOFS_VERSION_WHOLE_PLUGIN_NAME = 'wp-security-audit-log-nofs/wp-security-audit-log.php';

		/**
		 * Free version constant
		 *
		 * @var string
		 *
		 * @since 5.1.0
		 */
		const FREE_VERSION_WHOLE_PLUGIN_NAME = 'wp-security-audit-log/wp-security-audit-log.php';

		/**
		 * Contains a list of cleanup callbacks.
		 *
		 * @var callable[]
		 *
		 * @since 5.0.0
		 */
		protected static $cleanup_hooks = array();

		/**
		 * Keeps an instance of the class, for temporary purposes - before switching to entirely static methods
		 *
		 * @var WpSecurityAuditLog
		 *
		 * @since 5.1.0
		 */
		private static $instance = null;

		// phpcs:disable
		// phpcs:enable

		/**
		 * Standard singleton pattern.
		 *
		 * WARNING! To ensure the system always works as expected, AVOID using this method.
		 * Instead, make use of the plugin instance provided by 'wsal_init' action.
		 *
		 * @return WpSecurityAuditLog Returns the current plugin instance.
		 *
		 * @since 5.0.0
		 */
		public static function get_instance() {
			if ( ! self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * That is for backward compatibilities - probably it can be removed now
		 *
		 * @return \WpSecurityAuditLog
		 *
		 * @since 5.0.0
		 */
		public static function getInstance() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
			if ( method_exists( 'WSAL\Controllers\Plugin_Extensions', 'deactivate_plugins' ) ) {
				Plugin_Extensions::deactivate_plugins();
			} else {
				set_transient( 'wsal_deactivate_plugins', true );
			}

			static $instance = null;
			if ( ! $instance ) {
				$instance = new self();
			}
			return $instance;
		}

		/**
		 * Initialize plugin.
		 *
		 * @since 5.0.0
		 */
		public function __construct() {
			$bootstrap_hook = array( 'plugins_loaded', 9 );

			Sensors_Load_Manager::load_early_sensors();

			\add_action( $bootstrap_hook[0], array( $this, 'setup' ), $bootstrap_hook[1] );

			// Register plugin specific activation hook.
			\register_activation_hook( WSAL_BASE_NAME, array( __CLASS__, 'install' ) );

			// Plugin Deactivation Actions.
			\register_deactivation_hook( WSAL_BASE_NAME, array( __CLASS__, 'deactivate_actions' ) );

			// phpcs:disable
			/* @free:start */
			// phpcs:enable
			\register_uninstall_hook( WSAL_BASE_NAME, array( Uninstall_Helper::class, 'uninstall' ) );
			// phpcs:disable
			/* @free:end */
			// phpcs:enable

			// phpcs:disable
			// phpcs:enable

			MainWP_Addon::init();

			Cron_Jobs::init();

			// Hide all unrelated to the plugin notices on the plugin admin pages.
			\add_action( 'admin_print_scripts', array( WP_Helper::class, 'hide_unrelated_notices' ) );

			\add_action(
				'init',
				function () {
					Alert_Manager::init();
					Sensors_Load_Manager::load_sensors();
				},
				0
			);

			\add_action( 'admin_init', array( __CLASS__, 'wsal_plugin_redirect' ) );

			\add_action( 'admin_init', array( __CLASS__, 'maybe_sync_premium_freemius' ) );
		}

		/**
		 * Whether the current request is a REST API request.
		 *
		 * @return bool
		 *
		 * @since 5.0.0
		 */
		public static function is_rest_api() {
			if (
				( defined( 'REST_REQUEST' ) && REST_REQUEST )
				|| ! empty( $_GET['rest_route'] ) // phpcs:ignore
				) {
					return true;
			}

			if ( ! get_option( 'permalink_structure' ) ) {
				return false;
			}

			/*
			* This is needed because, if called early, global $wp_rewrite is not defined but required
			* by get_rest_url(). WP will reuse what we set here, or in worst case will replace, but no
			* consequences for us in any case.
			*/
			if ( empty( $GLOBALS['wp_rewrite'] ) ) {
				$GLOBALS['wp_rewrite'] = new \WP_Rewrite(); // phpcs:ignore -- WordPress.WP.GlobalVariablesOverride.Prohibited
			}

			$current_path = trim( (string) parse_url( (string) add_query_arg( array() ), PHP_URL_PATH ), '/' ) . '/'; // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			$rest_path    = trim( (string) parse_url( (string) get_rest_url(), PHP_URL_PATH ), '/' ) . '/'; // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url

			return strpos( $current_path, $rest_path ) === 0;
		}

		/**
		 * Whether the current request is a frontend request.
		 *
		 * @return bool
		 *
		 * @since 5.0.0
		 */
		public static function is_frontend() {
			return ! is_admin()
			&& ! WP_Helper::is_login_screen()
			&& ( ! defined( 'WP_CLI' ) || ! \WP_CLI )
			&& ( ! defined( 'DOING_CRON' ) || ! DOING_CRON )
			&& ! self::is_rest_api()
			&& ! self::is_admin_blocking_plugins_support_enabled();
		}

		/**
		 * Decides if the plugin should run, sets up constants, includes, inits hooks, etc.
		 *
		 * @return bool
		 *
		 * @since 5.0.0
		 */
		public function setup() {
			if ( ! self::should_load() ) {
				return;
			}

			self::includes();

			if ( class_exists( 'WSAL\Migration\Metadata_Migration_440' ) && ! empty( WP_Helper::get_global_option( Metadata_Migration_440::OPTION_NAME_MIGRATION_INFO, array() ) ) ) {

				// ( new Metadata_Migration_440( 'local' ) )->task(['connection'=>'local','batch_size'=>50,'processed_events_count'=>0]);
				new Metadata_Migration_440( 'local' );

				new Metadata_Migration_440( 'external' );
				new Metadata_Migration_440( 'archive' );

				\add_action( 'all_admin_notices', array( Metadata_Migration_440::class, 'maybe_display_progress_admin_notice' ) );
			}

			self::init_hooks();
			self::load_defaults();

			\add_action( 'after_setup_theme', array( __CLASS__, 'load_wsal' ) );

			$this->init();
		}

		/**
		 * Returns whether the plugin should load.
		 *
		 * @return bool Whether the plugin should load.
		 *
		 * @since 5.0.0
		 */
		private static function should_load() {
			// Always load on the admin, except for the scenario when this plugin is being updated.
			if ( \is_admin() ) {
				$acceptable_slugs = array(
					'wp-security-audit-log',
					'wp-activity-log',
				);

				// Check if this plugin is being updated from the plugin list.
				if ( isset( $_REQUEST['action'] ) && isset( $_REQUEST['slug'] ) && 'update-plugin' === trim( \sanitize_text_field( \wp_unslash( $_REQUEST['action'] ) ) )
				&& in_array( trim( \sanitize_text_field( \wp_unslash( $_REQUEST['slug'] ) ) ), $acceptable_slugs ) ) {
					return false;
				}

				// Check if this plugin is being updated using the file upload method.
				if ( isset( $_REQUEST['action'] ) && 'upload-plugin' === trim( \sanitize_text_field( \wp_unslash( $_REQUEST['action'] ) ) )
				&& isset( $_REQUEST['overwrite'] ) && 'update-plugin' === trim( \sanitize_text_field( \wp_unslash( $_REQUEST['overwrite'] ) ) )
				&& isset( $_REQUEST['package'] ) ) {
					/**
					 * Request doesn't contain the file name, but a numeric package ID.
					 *
					 * @see File_Upload_Upgrader::__construct()
					 */
					$post_id    = (int) $_REQUEST['package'];
					$attachment = \get_post( $post_id );
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
				if ( isset( $_REQUEST['action'] ) && 'do-plugin-upgrade' === trim( \sanitize_text_field( \wp_unslash( $_REQUEST['action'] ) ) ) ) {
					if ( isset( $_REQUEST['checked'] ) ) {
						$selected_plugins = $_REQUEST['checked']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
						if ( ! empty( $selected_plugins ) ) {
							foreach ( $selected_plugins as $selected_plugin ) {
								if ( 'wp-security-audit-log.php' === basename( trim( \sanitize_text_field( \wp_unslash( $selected_plugin ) ) ) ) ) {
									return false;
								}
							}
						}
					}
				}

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
		 *
		 * @since 5.0.0
		 */
		public static function should_load_frontend() {
			$frontend_events = Settings_Helper::get_frontend_events();
			$should_load     = ! empty( $frontend_events['register'] ) || ! empty( $frontend_events['login'] ) || ! empty( $frontend_events['woocommerce'] || ! empty( $frontend_events['gravityforms'] ) );

			// Allow extensions to manually allow a sensor to load.
			return apply_filters( 'wsal_load_on_frontend', $should_load, $frontend_events );
		}

		/**
		 * Include Plugin Files.

		 * Initialize Plugin Hooks.
		 *
		 * @since 3.3
		 */
		private static function init_hooks() {

			// Setup screen options. Needs to be here as admin_init hook it too late.
			\add_filter( 'set-screen-option', array( '\WSAL\ListAdminEvents\List_Events', 'set_screen_option' ), 10, 3 );

			\add_action( 'current_screen', array( '\WSAL\Helpers\Upgrade_Notice', 'init' ) );

			// Listen for cleanup event.
			\add_action( 'wsal_cleanup', array( __CLASS__, 'clean_up' ) );

			\add_action( 'shutdown', array( __CLASS__, 'close_external_connection' ), 999 );

			// Render wsal footer.
			\add_action( 'admin_footer', array( __CLASS__, 'render_footer' ) );

			// Handle admin Disable Custom Field.
			\add_action( 'wp_ajax_AjaxDisableCustomField', array( __CLASS__, 'ajax_disable_custom_field' ) );

			// Handle admin Disable Alerts.
			\add_action( 'wp_ajax_AjaxDisableByCode', array( __CLASS__, 'ajax_disable_by_code' ) );

			// Render Login Page Notification.
			\add_filter( 'login_message', array( __CLASS__, 'render_login_page_message' ), 10, 1 );

			\add_filter( 'mainwp_child_extra_execution', array( '\WSAL\MainWP\MainWP_API', 'retrieve_info_call_back' ), 10, 2 );
			// add_filter( 'mainwp_child_extra_execution', array( new WSAL_MainWpApi( $this ), 'handle_callback' ), 10, 2 );

			\add_action( 'wsal_freemius_loaded', array( __CLASS__, 'adjust_freemius_strings' ) );

			self::init_freemius();

			// Extensions which are only admin based.
			if ( \is_admin() ) {
				Plugin_Installer::init();
			}

			Plugin_Extensions::init();

			Notices::init_ajax_hooks();

			// Dequeue conflicting scripts.
			\add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_conflicting_scripts' ) );

			// phpcs:disable
			// phpcs:enable
		}

		// phpcs:disable
		// phpcs:enable

		/**
		 * Load Freemius SDK.
		 *
		 * @since 5.0.0
		 */
		public static function load_freemius() {
			require_once WSAL_BASE_DIR . '/sdk/wsal-freemius.php';
		}

		/**
		 * Check if MainWP plugin is active or not.
		 *
		 * @return boolean
		 *
		 * @since 5.0.0
		 */
		public static function is_mainwp_active() {
			return WP_Helper::is_plugin_active( 'mainwp-child/mainwp-child.php' );
		}

		/**
		 * Initializes Freemius and its hooks, conditionally.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		private static function init_freemius() {

			$is_admin_blocking_plugins_support_enabled = self::is_admin_blocking_plugins_support_enabled();
			if ( self::is_frontend() && self::is_premium_freemius() && file_exists( WSAL_BASE_DIR . '/extensions/class-wsal-extension-manager.php' ) ) {

				if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
					\WSAL_Extension_Manager::include_extension( 'reports' );
					\WSAL_Extension_Manager::include_extension( 'usersessions' );
					\WSAL_Extension_Manager::include_extension( 'external-db' );
				} elseif ( self::should_load() ) {
					\WSAL_Extension_Manager::include_extension( 'notifications' );
					\WSAL_Extension_Manager::include_extension( 'external-db' );
				}

				if ( ! $is_admin_blocking_plugins_support_enabled ) {
					// We only stop here if the support for admin blocking plugins is enabled.
					return;
				}
			}

			if ( $is_admin_blocking_plugins_support_enabled || is_admin() || WP_Helper::is_login_screen() || self::is_rest_api() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && \WP_CLI ) ) {

				self::load_freemius();
				// phpcs:disable
				// phpcs:enable
				if ( ! apply_filters( 'wsal_disable_freemius_sdk', false ) ) {
					// Add filters to customize freemius welcome message.
					wsal_freemius()->add_filter( 'connect_message', array( __CLASS__, 'wsal_freemius_connect_message' ), 10, 6 );
					wsal_freemius()->add_filter( 'connect_message_on_update', array( __CLASS__, 'wsal_freemius_update_connect_message' ), 10, 6 );
					wsal_freemius()->add_filter( 'show_admin_notice', array( __CLASS__, 'freemius_show_admin_notice' ), 10, 2 );
					wsal_freemius()->add_filter( 'show_delegation_option', '__return_false' );
					wsal_freemius()->add_filter( 'enable_per_site_activation', '__return_false' );
					wsal_freemius()->add_filter( 'show_trial', '__return_false' );
					wsal_freemius()->add_filter( 'opt_in_error_message', array( __CLASS__, 'limited_license_activation_error' ), 10, 1 );
					wsal_freemius()->add_action( 'after_account_plan_sync', array( __CLASS__, 'sync_premium_freemius' ), 10, 1 );
					wsal_freemius()->add_action( 'after_premium_version_activation', array( __CLASS__, 'sync_premium_freemius' ) );
					wsal_freemius()->add_filter(
						'plugin_icon',
						function ( $plugin_icon ) {
							return WSAL_BASE_DIR . 'img/wsal-logo@2x.png';
						}
					);
					wsal_freemius()->add_action( 'is_submenu_visible', array( __CLASS__, 'hide_freemius_submenu_items' ), 10, 2 );
					wsal_freemius()->add_filter(
						'freemius_pricing_js_path',
						function ( $default_pricing_js_path ) {
							return WSAL_BASE_DIR . 'js/freemius-pricing/freemius-pricing.js';
						}
					);
					wsal_freemius()->add_filter( 'default_to_anonymous_feedback', '__return_true' );

					wsal_freemius()->add_filter(
						'pricing_url',
						function ( $url ) {
							return 'https://melapress.com/wordpress-activity-log/pricing/?&utm_source=plugin&utm_medium=link&utm_campaign=wsal';
						}
					);
				}
			}
		}

		/**
		 * Method: WSAL plugin redirect.
		 *
		 * @since 5.0.0
		 */
		public static function wsal_plugin_redirect() {
			// Plugin redirect on activation.

			if ( false === Settings_Helper::get_option_value( 'redirect_on_activate', false ) ) {
				return;
			}

			$restrict_to = Settings_Helper::get_option_value( 'restrict-plugin-settings', 'only_admins' );

			if ( ( 'only_admins' === $restrict_to && \current_user_can( 'manage_options' ) ) || ( 'only_me' === $restrict_to && (int) Settings_Helper::get_option_value( 'only-me-user-id' ) === (int) get_current_user_id() ) ) {
				// WSAL State.
				$wsal_state = Settings_Helper::get_option_value( 'freemius_state', 'anonymous' );

				if (
				in_array( $wsal_state, array( 'anonymous', 'skipped' ), true ) && Settings_Helper::get_option_value( 'redirect_on_activate', false ) // Redirect flag.
				) {
					// If the redirect option is true, then continue.
					Settings_Helper::delete_option_value( 'wsal_redirect_on_activate' ); // Delete redirect option.

					// Redirect URL.
					$redirect = '';

					// Otherwise, redirect to main audit log view.
					$redirect = self::get_plugin_admin_url_page();

					\wp_safe_redirect( $redirect );
					exit();
				}
			}
		}

		/**
		 * Method: Include extensions for premium version.
		 *
		 * @since 2.7.0
		 */
		private static function include_extensions__premium_only() {
			// Initiate the extensions' manager.
			\WSAL_Extension_Manager::init();
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
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function wsal_freemius_connect_message( $message, $user_first_name, $plugin_title, $user_login, $site_link, $_freemius_link ) {
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
				'<a href="https://melapress.com/support/kb/non-sensitive-diagnostic-data/" target="_blank" tabindex="1">freemius.com</a>',
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
		 *
		 * @since 3.3
		 */
		public static function freemius_show_admin_notice( $show, $msg ) {
			if ( Settings_Helper::current_user_can( 'edit' ) ) {
				return $show;
			}
			return false;
		}

		/**
		 * Changes some of the strings that Freemius outputs with our own.
		 *
		 * @method adjust_freemius_strings
		 *
		 * @since  4.0.0
		 */
		public static function adjust_freemius_strings() {
			// only update these messages if using premium plugin.
			if ( ( ! wsal_freemius()->is_premium() ) || ( ! method_exists( wsal_freemius(), 'override_il8n' ) ) ) {
				return;
			}

			wsal_freemius()->override_i18n(
				array(
					'few-plugin-tweaks' =>
					// translators: The license key.
					esc_html__(
						'You need to activate the license key to use WP Activity Log Premium. %s',
						'wp-security-audit-log'
					),
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
		 *
		 * @since 5.0.0
		 */
		public static function limited_license_activation_error( $error ) {
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
		 *
		 * @since 5.0.0
		 */
		private function init() {

			if ( is_admin() ) {
				View_Manager::init();

				Widget_Manager::init();
			}

			if ( is_admin() ) {
				// phpcs:disable

				// Hide plugin.
				if ( Settings_Helper::get_boolean_option_value( 'hide-plugin' ) ) {
					\add_action( 'admin_head', array( __CLASS__, 'hide_plugin' ) );
					\add_filter( 'all_plugins', array( __CLASS__, 'wsal_hide_plugin' ) );
				}
			}

			Constants::init();

			/**
			 * Action: `wsal_init`
			 *
			 * Action hook to mark that WSAL has initialized.
			 *
			 * @param WpSecurityAuditLog $this – Instance of main plugin class.
			 */
			\do_action( 'wsal_init', $this );

			// Allow registration of custom alert formatters (must be called after wsal_init action).
			WSAL_AlertFormatterFactory::bootstrap();

			Migration::migrate();

			if ( defined( '\WP_CLI' ) && \WP_CLI ) {
				\WP_CLI::add_command( 'wsal_cli_commands', '\WSAL\Controllers\WP_CLI\WP_CLI_Commands' );
			}
		}

		/**
		 * Plugin Deactivation Actions.
		 *
		 * This function runs on plugin deactivation to send
		 * deactivation email.
		 *
		 * @since 3.3.1
		 */
		public static function deactivate_actions() {
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
			if ( class_exists( '\WSAL\Helpers\Email_Helper', false ) ) {
				// Get email template.
				Email_Helper::send_deactivation_email();
			}
		}

		/**
		 * Disable Custom Field through ajax.
		 *
		 * @internal
		 *
		 * @since 5.0.0
		 */
		public static function ajax_disable_custom_field() {
			// Die if user does not have permission to disable.
			if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
				echo '<p>' . esc_html__( 'Error: You do not have sufficient permissions to disable this custom field.', 'wp-security-audit-log' ) . '</p>';
				die();
			}

			// Filter $_POST array for security.
			$post_array = filter_input_array( INPUT_POST );

			$disable_nonce    = ( isset( $_POST['disable_nonce'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['disable_nonce'] ) ) : null;
			$notice           = ( isset( $_POST['notice'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['notice'] ) ) : null;
			$object_type_post = ( isset( $_POST['object_type'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['object_type'] ) ) : null;

			if ( ! isset( $disable_nonce ) || ! \wp_verify_nonce( $disable_nonce, 'disable-custom-nonce' . $notice ) ) {
				die();
			}

			$object_type = 'post';
			if ( array_key_exists( 'object_type', $post_array ) && 'user' === $object_type_post ) {
				$object_type = 'user';
			}

			$excluded_meta = array();
			if ( 'post' === $object_type ) {
				$excluded_meta = Settings_Helper::get_excluded_post_meta_fields();
			} elseif ( 'user' === $object_type ) {
				$excluded_meta = Settings_Helper::get_excluded_user_meta_fields();
			}

			array_push( $excluded_meta, \esc_html( $notice ) );

			if ( 'post' === $object_type ) {
				$excluded_meta = Settings_Helper::set_excluded_post_meta_fields( $excluded_meta );
			} elseif ( 'user' === $object_type ) {
				$excluded_meta = Settings_Helper::set_excluded_user_meta_fields( $excluded_meta );
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
				'<strong>' . $notice . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);

			echo wp_sprintf(
			/* translators: setting tab name "Excluded Objects" */
				'<p>' . esc_html__( 'Enable the monitoring of this custom field again from the %s tab in the plugin settings.', 'wp-security-audit-log' ) . '</p>',
				'<a href="' . \esc_url( $exclude_objects_link ) . '">' . esc_html__( 'Excluded Objects', 'wp-security-audit-log' ) . '</a>'
			);
			die;
		}

		/**
		 * Disable Alert through ajax.
		 *
		 * @internal
		 *
		 * @since 5.0.0
		 */
		public static function ajax_disable_by_code() {
			// Die if user does not have permission to disable.
			if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
				echo '<p>' . esc_html__( 'Error: You do not have sufficient permissions to disable this alert.', 'wp-security-audit-log' ) . '</p>';
				die();
			}

			$disable_nonce = ( isset( $_POST['disable_nonce'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['disable_nonce'] ) ) : null;
			$code          = ( isset( $_POST['code'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['code'] ) ) : null;

			if ( ! isset( $disable_nonce ) || ! \wp_verify_nonce( $disable_nonce, 'disable-alert-nonce' . $code ) ) {
				die();
			}

			$s_alerts = Settings_Helper::get_option_value( 'disabled-alerts' );
			if ( isset( $s_alerts ) && '' !== $s_alerts ) {
				$s_alerts[] = esc_html( $code );
			} else {
				$s_alerts = esc_html( $code );
			}
			Settings_Helper::set_option_value( 'disabled-alerts', $s_alerts );

			echo wp_sprintf(
				// translators: Alert code which is no longer monitored.
				'<p>' . esc_html__( 'Alert %1$s is no longer being monitored. %2$s', 'wp-security-audit-log' ) . '</p>',
				\esc_html( $code ),
				'<br />' . esc_html__( 'You can enable this alert again from the Enable/Disable Alerts node in the plugin menu.', 'wp-security-audit-log' )
			);
			die;
		}

		/**
		 * Render plugin stuff in page footer.
		 *
		 * @internal
		 *
		 * @since 5.0.0
		 */
		public static function render_footer() {
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
			// phpcs:disable
			// phpcs:enable
			// Set data array for common script.
			$script_data = array(
				'ajaxURL'           => \admin_url( 'admin-ajax.php' ),
				'liveEvents'        => $live_events_enabled,
				'installing'        => esc_html__( 'Installing, please wait', 'wp-security-audit-log' ),
				'already_installed' => esc_html__( 'Already installed', 'wp-security-audit-log' ),
				'installed'         => esc_html__( 'Extension installed', 'wp-security-audit-log' ),
				'activated'         => esc_html__( 'Extension activated', 'wp-security-audit-log' ),
				'failed'            => esc_html__( 'Install failed', 'wp-security-audit-log' ),
				'reloading_page'    => esc_html__( 'Reloading page', 'wp-security-audit-log' ),
			);

			// phpcs:disable
			// phpcs:enable
			\wp_localize_script( 'wsal-common', 'wsalCommonData', $script_data );

			// Enqueue script.
			\wp_enqueue_script( 'wsal-common' );

			// Dont want to add a css file to all admin for the of setting an icon opacity.
			?>			
			<style>
				#adminmenu div.wp-menu-image.svg {
					opacity: 0.6;
				}
			</style>
			<?php
		}

		/**
		 * Load the rest of the system.
		 *
		 * @internal
		 *
		 * @since 5.0.0
		 */
		public static function load_wsal() {

			// if ( is_admin() ) {

				// Load translations.
				load_plugin_textdomain( 'wp-security-audit-log', false, WSAL_BASE_DIR . '/languages/' );
			//}
		}

		/**
		 * Installs all assets required for a usable system.
		 *
		 * @since 5.0.0
		 */
		public static function install() {
			$installation_errors = false;

			// Check for minimum PHP version.
			if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION ) < 0 ) {
				/* Translators: %s: PHP Version */
				$installation_errors  = sprintf( \esc_html__( 'You are using a version of PHP that is older than %s, which is no longer supported.', 'wp-security-audit-log' ), \esc_html( self::MIN_PHP_VERSION ) );
				$installation_errors .= '<br />';
				$installation_errors .= __( 'Contact us on <a href="mailto:plugins@melapress.com">plugins@melapress.com</a> to help you switch the version of PHP you are using.', 'wp-security-audit-log' );
			}

			if ( $installation_errors ) {
				?>
			<html>
				<head><style>body{margin:0;}.warn-icon-tri{top:7px;left:5px;position:absolute;border-left:16px solid #FFF;border-right:16px solid #FFF;border-bottom:28px solid #C33;height:3px;width:4px}.warn-icon-chr{top:10px;left:18px;position:absolute;color:#FFF;font:26px Georgia}.warn-icon-cir{top:4px;left:0;position:absolute;overflow:hidden;border:6px solid #FFF;border-radius:32px;width:34px;height:34px}.warn-wrap{position:relative;font-size:13px;font-family:sans-serif;padding:6px 48px;line-height:1.4;}</style></head>
				<body><div class="warn-wrap"><div class="warn-icon-tri"></div><div class="warn-icon-chr">!</div><div class="warn-icon-cir"></div><span><?php echo $installation_errors; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></div></body>
			</html>
				<?php
				die( 1 );
			}

			// Fully set up the plugin.
			// $this->setup();

			// Update licensing info in case we're swapping from free to premium or vice-versa.
			self::sync_premium_freemius();

			// Disable database sensor during the creation of tables.
			WP_Database_Sensor::set_enabled( false );

			// WSAL Audit Log page redirect option in anonymous mode.
			if ( 'anonymous' === Settings_Helper::get_option_value( 'freemius_state', 'anonymous' ) ) {
				Settings_Helper::set_option_value( 'redirect_on_activate', true );
			}

			// Run on each install to check MainWP Child plugin.
			Plugin_Settings_Helper::set_mainwp_child_stealth_mode();

			// Re-enable the database sensor after the tables are created.
			WP_Database_Sensor::set_enabled( true );
		}

		/**
		 * To be called in admin header for hiding plugin form Plugins list.
		 *
		 * @internal
		 *
		 * @since 5.0.0
		 */
		public static function hide_plugin() {
			if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
				$selectr = '';
				$plugins = array( 'wp-security-audit-log', 'wp-security-audit-log-premium' );
				foreach ( $plugins as $value ) {
					$selectr .= '.wp-list-table.plugins tr[data-slug="' . $value . '"], ';
				}
				?>
			<style type="text/css">
				<?php echo \esc_attr( rtrim( $selectr, ', ' ) ); ?> { display: none; }
			</style>
				<?php
			}
		}

		/**
		 * Run cleanup routines.
		 *
		 * @since 5.0.0
		 */
		public static function clean_up() {
			foreach ( self::$cleanup_hooks as $hook ) {
				call_user_func( $hook );
			}
		}

		/**
		 * Add callback to be called when a cleanup operation is required.
		 *
		 * @param callable $hook - Hook name.
		 *
		 * @since 5.0.0
		 */
		public static function add_cleanup_hook( $hook ) {
			self::$cleanup_hooks[] = $hook;
		}

		/**
		 * Remove a callback from the cleanup callbacks list.
		 *
		 * @param callable $hook - Hook name.
		 *
		 * @since 5.0.0
		 */
		public static function remove_cleanup_hook( $hook ) {
			while ( ( $pos = array_search( $hook, self::$cleanup_hooks ) ) !== false ) {
				unset( self::$cleanup_hooks[ $pos ] );
			}
		}

		/**
		 * Load default configuration / data.
		 *
		 * @since 5.0.0
		 */
		public static function load_defaults() {
			Constants::init();

			require_once WSAL_BASE_DIR . 'defaults.php';
		}

		/**
		 * Method: Render login page message.
		 *
		 * @param string $message - Login message.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function render_login_page_message( $message ) {
			// Check if the option is enabled.
			$login_message_enabled = \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'login_page_notification', false );
			if ( $login_message_enabled ) {
				// Get login message.
				$message = WSAL\Helpers\Settings_Helper::get_option_value( 'login_page_notification_text', false );

				// Default message.
				if ( ! $message ) {
					$message = '<p class="message">' . wp_kses( __( 'For security and auditing purposes, a record of all of your logged-in actions and changes within the WordPress dashboard will be recorded in an activity log with the <a href="https://melapress.com/" target="_blank">WP Activity Log plugin</a>. The audit log also includes the IP address where you accessed this site from.', 'wp-security-audit-log' ), Plugin_Settings_Helper::get_allowed_html_tags() ) . '</p>';
				} else {
					$message = '<p class="message">' . $message . '</p>';
				}
			}

			// Return message.
			return $message;
		}

		/**
		 * Function runs Freemius license check only if our Freemius licensing transient has already expired. This is
		 * intended to run on admin_init action.
		 *
		 * @since 4.3.0
		 */
		public static function maybe_sync_premium_freemius() {
			// We don't want to slow down any AJAX requests.
			if ( \wp_doing_ajax() ) {
				return;
			}

			$freemius_transient = WP_Helper::get_transient( 'fs_wsalp' );
			if ( false === $freemius_transient || ! in_array( $freemius_transient, array( 'yes', 'no' ), true ) ) {
				// Transient expired or invalid.
				self::sync_premium_freemius();
			}
		}

		/**
		 * Runs Freemius license check, updates our db option if necessary and creates/extends a transient we use to
		 * optimize the check. Should run only on couple of Freemius actions related to account sync and plugin activation.
		 *
		 * It might be also called by WpSecurityAuditLog::maybe_sync_premium_freemius() if the transient is not set or valid.
		 *
		 * @see WpSecurityAuditLog::maybe_sync_premium_freemius()
		 *
		 * @since 5.0.0
		 */
		public static function sync_premium_freemius() {
			$option_name = 'fs_wsalp';
			$old_value   = \get_site_option( $option_name );

			// Determine new value via Freemius SDK.
			$new_value = wsal_freemius()->has_active_valid_license() ? 'yes' : 'no';

			// Update the db option only if the value changed.
			if ( $new_value !== $old_value ) {
				\update_site_option( $option_name, $new_value );
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
		 *
		 * @since 5.0.0
		 */
		public static function is_premium_freemius() {
			return 'yes' === \get_site_option( 'fs_wsalp' );
		}

		/**
		 * Hide WSAL plugin from plugin list
		 *
		 * @param  array $plugins All plugins.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function wsal_hide_plugin( $plugins ) {
			global $pagenow;

			// Check current page, bail early if this isn't the plugins page.
			if ( 'plugins.php' !== $pagenow
			&& ! ( \wp_doing_ajax() && isset( $_REQUEST['pagenow'] ) && 'plugins' === $_REQUEST['pagenow'] ) ) {

				return $plugins;
			}

			$predefined_plugins = Plugins_Helper::get_installable_plugins();

			$main_plugins = array(
				'premium' => self::PREMIUM_VERSION_WHOLE_PLUGIN_NAME,
				'nofs'    => self::NOFS_VERSION_WHOLE_PLUGIN_NAME,
				'free'    => self::FREE_VERSION_WHOLE_PLUGIN_NAME,
			);

			foreach ( $main_plugins as $slug ) {
				// Find WSAL by plugin basename.
				if ( array_key_exists( $slug, $plugins ) ) {
					// Remove WSAL plugin from plugin list page.
					unset( $plugins[ $slug ] );
				}
			}

			// Add the software libs to the plugins for hiding.
			$predefined_plugins[] = array( 'plugin_slug' => 'wsal-external-libraries/wsal-external-libraries.php' );

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
		 *
		 * @since 5.0.0
		 */
		public static function hide_freemius_submenu_items( $is_visible, $submenu_id ) {
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
			$is_stealth_mode = Settings_Helper::get_option_value( 'mwp-child-stealth-mode', 'no' );

			if ( 'yes' !== $is_stealth_mode ) {
				// Only intended if MainWP stealth mode is active.
				return false;
			}
		}

		/**
		 * Dequeue JS files which have been added by other plugin to all admin pages and cause conflicts.
		 * See https://github.com/WPWhiteSecurity/wp-security-audit-log-premium/issues/1246 and
		 * https://trello.com/c/pWrQn1Be/742-koenhavelaertsflintgrpcom-reports-ui-does-not-load-with-plugin-installed
		 *
		 * @since 4.1.5
		 */
		public static function dequeue_conflicting_scripts() {
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
		public static function close_external_connection() {
			// If the adapter type options is not empty, it means we're using the external database.
			$database_type = Settings_Helper::get_option_value( 'adapter-type' );
			if ( ! is_null( $database_type ) ) {
				if ( strlen( $database_type ) > 0 ) {
					Connection::close_connection();
				}
			}
		}

		/**
		 * Include Plugin Files.
		 *
		 * @since 5.0.0
		 */
		public static function includes() {

			if ( WP_Helper::is_multisite() ) {
				$cpts_tracker = new \WSAL\Multisite\NetworkWide\CPTsTracker();
				$cpts_tracker->setup();
			}
		}

		/**
		 * Returns the the URL for the plugin admin main view - log view.
		 *
		 * @return string
		 *
		 * @since 5.1.1
		 */
		public static function get_plugin_admin_url_page() {
			return \add_query_arg( 'page', 'wsal-auditlog', \network_admin_url( 'admin.php' ) );
		}

		/**
		 * This method tries to guess the version of the plugin based on different conditions.
		 *
		 * @return string
		 *
		 * @since 5.1.1
		 */
		public static function get_plugin_version(): string {
			if ( class_exists( 'WSAL_Freemius', false ) && ! method_exists( 'WSAL_Freemius', 'set_basename' ) && ! ( new WSAL_Freemius() )->is_free_plan() ) {
				return 'NOFS';
			}
			if ( function_exists( 'wsal_freemius' ) && wsal_freemius()->has_active_valid_license() ) {
				return 'premium';
			}

			return 'free';
		}

		/**
		 * Returns an array of all plugin screens (pages).
		 *
		 * @return array
		 *
		 * @since 5.1.1
		 */
		public static function get_plugin_screens_array(): array {
			return array(
				'toplevel_page_wsal-auditlog',
				'wp-activity-log_page_wsal-usersessions-views',
				'wp-activity-log_page_wsal-np-notifications',
				'wp-activity-log_page_wsal-rep-views-main',
				'toplevel_page_wsal-auditlog-network',
				'wp-activity-log_page_wsal-usersessions-views-network',
				'wp-activity-log_page_wsal-np-notifications-network',
				'wp-activity-log_page_wsal-rep-views-main-network',
				'wp-activity-log_page_wsal-settings',
				'wp-activity-log_page_wsal-settings-network',
				'wp-activity-log_page_wsal-togglealerts',
				'wp-activity-log_page_wsal-togglealerts-network',
				'wp-activity-log_page_wsal-help',
				'wp-activity-log_page_wsal-help-network',
				'wp-activity-log_page_wsal-wsal-views-premium-features',
				'wp-activity-log_page_wsal-wsal-views-premium-features-network',
				'wp-activity-log_page_wsal-reports-new',
				'wp-activity-log_page_wsal-reports-new-network',
				'wp-activity-log_page_wsal-ext-settings',
				'wp-activity-log_page_wsal-ext-settings-network',
				'wp-activity-log_page_wsal-nofs-license',
				'wp-activity-log_page_wsal-nofs-license-network',
			);
		}
	}
}
