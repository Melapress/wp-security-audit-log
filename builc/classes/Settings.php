<?php
/**
 * Class: WSAL Settings.
 *
 * WSAL settings class.
 *
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is the actual controller of the Settings Page.
 *
 * @package Wsal
 */
class WSAL_Settings {

	/**
	 * Option name for front-end events.
	 *
	 * @var string
	 */
	const FRONT_END_EVENTS_OPTION_NAME = 'wsal_frontend-events';

	/**
	 * Instance of the main plugin.
	 *
	 * @var WpSecurityAuditLog
	 */
	protected $_plugin;

	const OPT_DEV_DATA_INSPECTOR = 'd';
	const OPT_DEV_PHP_ERRORS     = 'p';
	const OPT_DEV_REQUEST_LOG    = 'r';
	const OPT_DEV_BACKTRACE_LOG  = 'b';
	const ERROR_CODE_INVALID_IP  = 901;

	/**
	 * List of Site Admins.
	 *
	 * @var array
	 */
	private $site_admins = array();

	/**
	 * Dev Options.
	 *
	 * @var array
	 */
	protected $_devoption = null;

	/**
	 * Pruning Date.
	 *
	 * @var string
	 */
	protected $_pruning = 0;

	/**
	 * IDs of disabled alerts.
	 *
	 * @var array
	 */
	protected $_disabled = null;

	/**
	 * Allowed Plugin Viewers.
	 *
	 * @var array
	 */
	protected $_viewers = null;

	/**
	 * Alerts per page.
	 *
	 * @var int
	 */
	protected $_perpage = null;

	/**
	 * Users excluded from monitoring.
	 *
	 * @var array
	 */
	protected $_excluded_users = array();

	/**
	 * Roles excluded from monitoring.
	 *
	 * @var array
	 */
	protected $_excluded_roles = array();

	/**
	 * Custom fields excluded from monitoring.
	 *
	 * @var array
	 */
	protected $_excluded_custom = array();

	/**
	 * Custom Post Types excluded from monitoring.
	 *
	 * @var array
	 */
	protected $_post_types = array();

	/**
	 * IP excluded from monitoring.
	 *
	 * @var array
	 */
	protected $_excluded_ip = array();

	/**
	 * URLs excluded from monitoring.
	 *
	 * @var array
	 * @since 3.2.2
	 */
	protected $excluded_urls = array();

	/**
	 * Alerts enabled in Geek mode.
	 *
	 * @var array
	 */
	public $geek_alerts = array( 1004, 1005, 1006, 1007, 2023, 2024, 2053, 2054, 2055, 2062, 2100, 2106, 2111, 2112, 2124, 2125, 2131, 2132, 2094, 2095, 2043, 2071, 2082, 2083, 2085, 2089, 4014, 4015, 4016, 5010, 5011, 5012, 5019, 5025, 5013, 5014, 5015, 5016, 5017, 5018, 5022, 5023, 5024, 6001, 6002, 6007, 6008, 6010, 6011, 6012, 6013, 6014, 6015, 6016, 6017, 6018, 6023, 6024, 6025 );

	/**
	 * Current screen object.
	 *
	 * @var WP_Screen
	 */
	private $current_screen = '';

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->_plugin = $plugin;
		// some settings here may be called before the options helper is setup.
		if ( ! isset( $this->_plugin->options_helper ) ) {
			$this->_plugin->include_options_helper();
		}
		add_action( 'deactivated_plugin', array( $this, 'reset_stealth_mode' ), 10, 1 );
	}

	/**
	 * Gets path to the default working directory relative to the root of WordPress site.
	 *
	 * Uses forward slashes and result has a slash at the end.
	 *
	 * @return string
	 */
	public function get_default_working_dir_relative() {
		$upload_dir  = wp_upload_dir(null, false);
		if (false === $upload_dir || ! array_key_exists('basedir', $upload_dir) ||
		    ( array_key_exists('error', $upload_dir) && ! empty ( $upload_dir['error'] ) )
		) {
			//  fallback in case there is a problem with filesystem
			return '/wp-content/uploads/wp-activity-log/';
		}

		$result = $upload_dir['basedir'] . '/wp-activity-log/';

		//  remove absolute path from the basedir to make it relative
		if ( defined( 'ABSPATH' ) ) {
			$result = preg_replace( '/^' . preg_quote( ABSPATH, '/' ) . '/', '', $result );
			//  add leading slash if missing
			if ( '/' !== substr( $result, 0, 1) ) {
				$result = '/' . $result;
			}
		}
		return $result;
	}

	/**
	 * Retrieves the working directory from the settings relative to the WordPress root folder.
	 *
	 * Used by two public helper functions that provide the working directory path and URL for the rest of the plugin:
	 * - WSAL_Settings::get_working_dir_path
	 * - WSAL_Settings::get_working_dir_url
	 *
	 * Result contains forward slashes suitable for use in URL. It also contains the trailing slash. Replace slashes
	 * with system specific directory separators when used as filesystem path. This is already done in
	 * WSAL_Settings::get_working_dir_path in case you need it.
	 *
	 * @param string $path Optional path relative to the working directory.
	 * @param bool $ignore_site If true, there will be no sub-site specific subfolder in multisite context.
	 * @return string
	 * @since 4.1.3
	 *
	 * @see WSAL_Settings::get_working_dir_path()
	 * @see WSAL_Settings::get_working_dir_url()
	 * @see WSAL_Settings::get_working_dir_path_relative()
	 */
	private function get_working_dir_path_relative( $path = '', $ignore_site = false ) {
		if ( ! \function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		//  get relative path from the plugin settings
		$result = trailingslashit( $this->_plugin->GetGlobalSetting( 'custom-logging-dir', $this->get_default_working_dir_relative() ) );

		//  append site specific subfolder in multisite context
		if ( ! $ignore_site && $this->_plugin->IsMultisite() ) {
			$site_id = get_current_blog_id();
			if ($site_id > 0) {
				$result .= 'sites/' . $site_id . '/';
			}
		}

		//  append optional path passed as a parameter
		if ( $path && is_string( $path ) ) {
			$result .= $path . '/';
		}

		//  result should have the trailing slash at this point, no need to trailingslashit again
		return $result;
	}

	/**
	 * Retrieves the working directory URL including a trailing slash.
	 *
	 * Uses as default:
	 * {uploads folder}/wp-activity-log/
	 *
	 * @param string $path Optional path relative to the working directory.

	 * @return string
	 * @see WSAL_Settings::get_working_dir_path_relative()
	 *
	 * @since  4.1.0
	 */
	public function get_working_dir_url( $path = '' ) {
		$result = $this->get_default_working_dir_relative( $path );
		return trailingslashit( \get_site_url() ) . $result;
	}

	/**
	 * Retrieves the working directory from the settings. Returns a folder path
	 * with a trailing slash. It also creates the folder unless the $skip_creation
	 * parameter is set to true.
	 *
	 * Uses as default:
	 * {uploads folder}/wp-activity-log/
	 *
	 * @param string $path Optional path relative to the working directory.
	 * @param bool $skip_creation If true, the folder will not be created.
	 * @param bool $ignore_site If true, there will be no sub-site specific subfolder in multisite context.
	 *
	 * @return string|WP_Error
	 * @see WSAL_Settings::get_working_dir_path_relative()
	 *
	 * @since  4.1.0
	 */
	public function get_working_dir_path( $path = '', $skip_creation = false, $ignore_site = false ) {
		$result = $this->get_working_dir_path_relative( $path, $ignore_site );
		$result = trailingslashit( ABSPATH ) . $result;
		$result = preg_replace('/\//', DIRECTORY_SEPARATOR, $result);
		if ( ! $skip_creation ) {
			if ( ! wp_mkdir_p( $result ) ) {
				return new WP_Error('mkdir_failed', sprintf(
				/* translators: %s: Directory path. */
					__( 'Unable to create directory %s. Is its parent directory writable by the server?' ),
					esc_html( $result )
				));
			}
		}

		return $result;
	}

	/**
	 * Enable Basic Mode.
	 */
	public function set_basic_mode() {
		// Disable alerts of geek mode.
		$this->SetDisabledAlerts( $this->geek_alerts );
	}

	/**
	 * Enable Geek Mode.
	 */
	public function set_geek_mode() {
		$this->SetDisabledAlerts( array() ); // Disable alerts of geek mode.
	}

	/**
	 * Return array of developer options to be enabled by default.
	 *
	 * @return array
	 */
	public function GetDefaultDevOptions() {
		return array();
	}

	/**
	 * Returns whether a developer option is enabled or not.
	 *
	 * @param string $option - See self::OPT_DEV_* constants.
	 * @return boolean - If option is enabled or not.
	 */
	public function IsDevOptionEnabled( $option ) {
		if ( is_null( $this->_devoption ) ) {
			$this->_devoption = $this->_plugin->GetGlobalSetting(
				'dev-options',
				implode( ',', $this->GetDefaultDevOptions() )
			);
			$this->_devoption = explode( ',', $this->_devoption );
		}
		return in_array( $option, $this->_devoption );
	}

	/**
	 * Check whether any developer option has been enabled or not.
	 *
	 * @return boolean
	 */
	public function IsAnyDevOptionEnabled() {
		return ! ! $this->_plugin->GetGlobalSetting( 'dev-options', null );
	}

	/**
	 * Sets whether a developer option is enabled or not.
	 *
	 * @param string  $option - See self::OPT_DEV_* constants.
	 * @param boolean $enabled - If option should be enabled or not.
	 */
	public function SetDevOptionEnabled( $option, $enabled ) {
		// Make sure options have been loaded.
		$this->IsDevOptionEnabled( '' );
		// Remove option if it exists.
		while ( ( $p = array_search( $option, $this->_devoption ) ) !== false ) {
			unset( $this->_devoption[ $p ] );
		}
		// Add option if callee wants it enabled.
		if ( $enabled ) {
			$this->_devoption[] = $option;
		}
		// Commit option.
		$this->_plugin->SetGlobalSetting(
			'dev-options',
			implode( ',', $this->_devoption )
		);
	}

	/**
	 * Remove all enabled developer options.
	 */
	public function ClearDevOptions() {
		$this->_devoption = array();
		$this->_plugin->SetGlobalSetting( 'dev-options', '' );
	}

	/**
	 * Check whether to enable data inspector or not.
	 *
	 * @return boolean
	 */
	public function IsDataInspectorEnabled() {
		return $this->IsDevOptionEnabled( self::OPT_DEV_DATA_INSPECTOR );
	}

	/**
	 * Check whether to enable PHP error logging or not.
	 *
	 * @return boolean
	 */
	public function IsPhpErrorLoggingEnabled() {
		return $this->IsDevOptionEnabled( self::OPT_DEV_PHP_ERRORS );
	}

	/**
	 * Check whether to log requests to file or not.
	 *
	 * @return boolean
	 */
	public function IsRequestLoggingEnabled() {
		return $this->IsDevOptionEnabled( self::OPT_DEV_REQUEST_LOG );
	}

	/**
	 * Check whether to store debug backtrace for PHP alerts or not.
	 *
	 * @return boolean
	 */
	public function IsBacktraceLoggingEnabled() {
		return $this->IsDevOptionEnabled( self::OPT_DEV_BACKTRACE_LOG );
	}

	/**
	 * Check whether dashboard widgets are enabled or not.
	 *
	 * @return boolean
	 */
	public function IsWidgetsEnabled() {
		return ! $this->_plugin->GetGlobalBooleanSetting( 'disable-widgets' );
	}

	/**
	 * Check whether dashboard widgets are enabled or not.
	 *
	 * @param boolean $newvalue - True if enabled.
	 */
	public function SetWidgetsEnabled( $newvalue ) {
		$this->_plugin->SetGlobalBooleanSetting( 'disable-widgets', ! $newvalue );
	}

	/**
	 * Check whether admin bar notifications are enabled or not.
	 *
	 * @since 3.2.4
	 *
	 * @return boolean
	 */
	public function is_admin_bar_notif() {
		return ! $this->_plugin->GetGlobalSetting( 'disable-admin-bar-notif' );
	}

	/**
	 * Set admin bar notifications.
	 *
	 * @since 3.2.4
	 *
	 * @param boolean $newvalue - True if enabled.
	 */
	public function set_admin_bar_notif( $newvalue ) {
		$this->_plugin->SetGlobalSetting( 'disable-admin-bar-notif', ! $newvalue );
	}

	/**
	 * Check admin bar notification updates refresh option.
	 *
	 * @since 3.3.1
	 *
	 * @return string
	 */
	public function get_admin_bar_notif_updates() {
		return $this->_plugin->GetGlobalSetting( 'admin-bar-notif-updates', 'page-refresh' );
	}

	/**
	 * Set admin bar notifications.
	 *
	 * @since 3.3.1
	 *
	 * @param string $newvalue - New option value.
	 */
	public function set_admin_bar_notif_updates( $newvalue ) {
		$this->_plugin->SetGlobalSetting( 'admin-bar-notif-updates', $newvalue );
	}

	/**
	 * Check whether alerts in audit log view refresh automatically or not.
	 *
	 * @return boolean
	 */
	public function IsRefreshAlertsEnabled() {
		return ! $this->_plugin->GetGlobalSetting( 'disable-refresh' );
	}

	/**
	 * Check whether alerts in audit log view refresh automatically or not.
	 *
	 * @param boolean $newvalue - True if enabled.
	 */
	public function SetRefreshAlertsEnabled( $newvalue ) {
		$this->_plugin->SetGlobalSetting( 'disable-refresh', ! $newvalue );
	}

	/**
	 * Maximum number of alerts to show in dashboard widget.
	 *
	 * @return int
	 */
	public function GetDashboardWidgetMaxAlerts() {
		return 5;
	}

	/**
	 * The maximum number of alerts allowable.
	 *
	 * @return int
	 */
	public function GetMaxAllowedAlerts() {
		return 5000;
	}

	/**
	 * The default pruning date.
	 *
	 * @return string
	 */
	public function GetDefaultPruningDate() {
		return '6 months';
	}

	/**
	 * The current pruning date.
	 *
	 * @return string
	 */
	public function GetPruningDate() {
		if ( ! $this->_pruning ) {
			$this->_pruning = $this->_plugin->GetGlobalSetting( 'pruning-date' );
			if ( ! strtotime( $this->_pruning ) ) {
				$this->_pruning = $this->GetDefaultPruningDate();
			}
		}
		return $this->_pruning;
	}

	/**
	 * Set the new pruning date.
	 *
	 * @param string $newvalue - The new pruning date.
	 */
	public function SetPruningDate( $newvalue ) {
		if ( strtotime( $newvalue ) ) {
			$this->_plugin->SetGlobalSetting( 'pruning-date', $newvalue );
			$this->_pruning = $newvalue;
		}
	}

	/**
	 * Return current pruning unit.
	 *
	 * @return string
	 */
	public function get_pruning_unit() {
		return $this->_plugin->GetGlobalSetting( 'pruning-unit', 'months' );
	}

	/**
	 * Set current pruning unit.
	 *
	 * @param string $newvalue – New value of pruning unit.
	 */
	public function set_pruning_unit( $newvalue ) {
		$this->_plugin->SetGlobalSetting( 'pruning-unit', $newvalue );
	}

	/**
	 * Maximum number of alerts to keep.
	 *
	 * @return integer
	 */
	public function GetPruningLimit() {
		$val = (int) $this->_plugin->GetGlobalSetting( 'pruning-limit' );
		return $val ? $val : $this->GetMaxAllowedAlerts();
	}

	/**
	 * Set pruning alerts limit.
	 *
	 * @param integer $newvalue - The new maximum number of alerts.
	 */
	public function SetPruningLimit( $newvalue ) {
		$newvalue = max( /*min(*/ (int) $newvalue/*, $this->GetMaxAllowedAlerts())*/, 1 );
		$this->_plugin->SetGlobalSetting( 'pruning-limit', $newvalue );
	}

	public function SetPruningDateEnabled( $enabled ) {
		$this->_plugin->SetGlobalBooleanSetting( 'pruning-date-e', $enabled );
	}

	public function SetPruningLimitEnabled( $enabled ) {
		$this->_plugin->SetGlobalBooleanSetting( 'pruning-limit-e', $enabled );
	}

	public function IsPruningDateEnabled() {
		return $this->_plugin->GetGlobalBooleanSetting( 'pruning-date-e' );
	}

	public function IsPruningLimitEnabled() {
		return $this->_plugin->GetGlobalBooleanSetting( 'pruning-limit-e' );
	}

	/**
	 * Sandbox functionality is now in an external plugin.
	 *
	 * @deprecated
	 */
	public function IsSandboxPageEnabled() {
		return esc_html__( 'This function is deprecated', 'wp-security-audit-log' );
	}

	/**
	 * Method: Set Login Page Notification.
	 *
	 * @param bool $enable - Enable/Disable.
	 */
	public function set_login_page_notification( $enable ) {
		$this->_plugin->SetGlobalBooleanSetting( 'login_page_notification', $enable );
	}

	/**
	 * Method: Check if Login Page Notification is set.
	 *
	 * @return bool - True if set, false if not.
	 */
	public function is_login_page_notification() {
		return $this->_plugin->GetGlobalBooleanSetting( 'login_page_notification', false );
	}

	/**
	 * Method: Set Login Page Notification Text.
	 *
	 * @param string $text - Login Page Notification Text.
	 */
	public function set_login_page_notification_text( $text ) {
		$text = wp_kses( $text, $this->_plugin->allowed_html_tags );
		$this->_plugin->SetGlobalSetting( 'login_page_notification_text', $text );
	}

	/**
	 * Method: Return Login Page Notification Text.
	 *
	 * @return string|bool - Text if set, false if not.
	 */
	public function get_login_page_notification_text() {
		return $this->_plugin->GetGlobalSetting( 'login_page_notification_text', false );
	}

	public function GetDefaultDisabledAlerts() {
		return array( 0000, 0001, 0002, 0003, 0004, 0005 );
	}

	/**
	 * Return IDs of disabled alerts.
	 *
	 * @return array
	 */
	public function GetDisabledAlerts() {
		if ( ! $this->_disabled ) {
			$this->_disabled = implode( ',', $this->GetDefaultDisabledAlerts() );
			$this->_disabled = $this->_plugin->GetGlobalSetting( 'disabled-alerts', $this->_disabled );
			$this->_disabled = ( '' == $this->_disabled ) ? array() : explode( ',', $this->_disabled );
			$this->_disabled = array_map( 'intval', $this->_disabled );
		}
		return $this->_disabled;
	}

	/**
	 * Method: Set Disabled Alerts.
	 *
	 * @param array $types IDs alerts to disable.
	 */
	public function SetDisabledAlerts( $types ) {
		$this->_disabled = array_unique( array_map( 'intval', $types ) );
		$this->_plugin->SetGlobalSetting( 'disabled-alerts', implode( ',', $this->_disabled ) );
	}

	/**
	 * @return bool
	 */
	public function IsIncognito() {
		return $this->_plugin->GetGlobalBooleanSetting( 'hide-plugin' );
	}

	/**
	 * @param bool $enabled
	 */
	public function SetIncognito( $enabled ) {
		$this->_plugin->SetGlobalBooleanSetting( 'hide-plugin', $enabled );
	}

	/**
	 * Checking if the data will be removed.
	 */
	public function IsDeleteData() {
		return $this->_plugin->GetGlobalBooleanSetting( 'delete-data' );
	}

	public function SetDeleteData( $enabled ) {
		$this->_plugin->SetGlobalBooleanSetting( 'delete-data', $enabled );
	}

	/**
	 * Set Plugin Viewers.
	 *
	 * @param array $users_or_roles – Users/Roles.
	 */
	public function SetAllowedPluginViewers( $users_or_roles ) {
		$this->_viewers = $users_or_roles;
		$this->_plugin->SetGlobalSetting( 'plugin-viewers', implode( ',', $this->_viewers ) );
	}

	/**
	 * Get Plugin Viewers.
	 */
	public function GetAllowedPluginViewers() {
		if ( is_null( $this->_viewers ) ) {
			$this->_viewers = array_unique( array_filter( explode( ',', $this->_plugin->GetGlobalSetting( 'plugin-viewers' ) ) ) );
		}
		return $this->_viewers;
	}

	/**
	 * Set restrict plugin setting.
	 *
	 * @param string $setting – Setting.
	 * @since 3.2.3
	 */
	public function set_restrict_plugin_setting( $setting ) {
		$this->_plugin->SetGlobalSetting( 'restrict-plugin-settings', $setting );
	}

	/**
	 * Get restrict plugin setting.
	 *
	 * @since 3.2.3
	 */
	public function get_restrict_plugin_setting() {
		return $this->_plugin->GetGlobalSetting( 'restrict-plugin-settings', 'only_admins' );
	}

	/**
	 * Get restriction setting for viewing the log viewer in multisite context.
	 *
	 * @since 4.1.3
	 */
	public function get_restrict_log_viewer() {
		return $this->_plugin->GetGlobalSetting( 'restrict-log-viewer', 'only_admins' );
	}

	/**
	 * Set restriction setting for viewing the log viewer in multisite context.
	 *
	 * @param string $setting – Setting.
	 * @since 4.1.3
	 */
	public function set_restrict_log_viewer( $setting ) {
		$this->_plugin->SetGlobalSetting( 'restrict-log-viewer', $setting );
	}

	public function SetViewPerPage( $newvalue ) {
		$this->_perpage = max( $newvalue, 1 );
		$this->_plugin->SetGlobalSetting( 'items-per-page', $this->_perpage );
	}

	public function GetViewPerPage() {
		if ( is_null( $this->_perpage ) ) {
			$this->_perpage = (int) $this->_plugin->GetGlobalSetting( 'items-per-page', 10 );
		}
		return $this->_perpage;
	}

	/**
	 * Check if current user can perform an action.
	 *
	 * @param string $action Type of action, either 'view' or 'edit'.
	 * @return boolean If user has access or not.
	 */
	public function CurrentUserCan( $action ) {
		return $this->UserCan( wp_get_current_user(), $action );
	}

	/**
	 * Get list of superadmin usernames.
	 *
	 * @return array
	 */
	protected function GetSuperAdmins() {
		return $this->_plugin->IsMultisite() ? get_super_admins() : array();
	}

	/**
	 * List of admin usernames.
	 *
	 * @return string[]
	 */
	protected function GetAdmins() {
		if ( $this->_plugin->IsMultisite() ) {
			if ( empty( $this->site_admins ) ) {
				/**
				 * Get list of admins.
				 *
				 * @see https://gist.github.com/1508426/65785a15b8638d43a9905effb59e4d97319ef8f8
				 */
				global $wpdb;
				$cap = $wpdb->prefix . 'capabilities';
				$sql = "SELECT DISTINCT $wpdb->users.user_login"
					. " FROM $wpdb->users"
					. " INNER JOIN $wpdb->usermeta ON ($wpdb->users.ID = $wpdb->usermeta.user_id )"
					. " WHERE $wpdb->usermeta.meta_key = '$cap'"
					. " AND CAST($wpdb->usermeta.meta_value AS CHAR) LIKE  '%\"administrator\"%'";

				// Get admins.
				$this->site_admins = $wpdb->get_col( $sql );
			}
		} else {
			if ( empty( $this->site_admins ) ) {
				$query = 'role=administrator&fields[]=user_login';
				foreach ( get_users( $query ) as $user ) {
					$this->site_admins[] = $user->user_login;
				}
			}
		}
		return $this->site_admins;
	}

	/**
	 * Check if user can perform an action.
	 *
	 * @param integer|WP_user $user - User object to check.
	 * @param string          $action - Type of action, either 'view' or 'edit'.
	 * @return boolean If user has access or not.
	 */
	public function UserCan( $user, $action ) {
		if ( is_int( $user ) ) {
			$user = get_userdata( $user );
		}

		//  by default the user has no privileges
		$result = false;

		$is_multisite = $this->_plugin->IsMultisite();
		switch ( $action ) {
			case 'view':
				if ( !$is_multisite ) {
					//  non-multisite piggybacks on the plugin settings access
					switch ( $this->get_restrict_plugin_setting() ) {
						case 'only_admins':
							//  allow access only if the user is and admin
							$result = in_array('administrator', $user->roles);
							break;
						case 'only_me':
							//  allow access only if the user matches the only user allowed access
							$result = $user->ID == $this->get_only_me_user_id();
							break;
						default:
							//  no other options to allow access here
							$result = false;
					}
				} else {
					//  multisite MUST respect the log viewer restriction settings plus also additional users and roles
					//  defined in the extra option
					switch ( $this->get_restrict_log_viewer() ) {
						case 'only_me':
							//  allow access only if the user matches the only user allowed access
							$result = ( $user->ID == $this->get_only_me_user_id() );
							break;
						case 'only_superadmins':
							//  allow access only for super admins
							if ( function_exists( 'is_super_admin' ) && is_super_admin($user->ID) ) {
								$result = true;
							}
							break;
						case 'only_admins':
							//  allow access only for super admins and admins
							$result = in_array('administrator', $user->roles) || ( function_exists( 'is_super_admin' ) && is_super_admin($user->ID) );
							break;
					}
				}

				if (!$result) {
					//  user is still not allowed to view the logs, let's check the additional users and roles
					//  settings
					$extra_viewers = $this->GetAllowedPluginViewers();
					if (in_array($user->user_login, $extra_viewers )) {
						$result = true;
					} elseif (!empty(array_intersect($extra_viewers, $user->roles))) {
						$result = true;
					}
				}
				break;
			case 'edit':
				if ( $is_multisite ) {
					//  no one has access to settings on sub site inside a network
					if ( wp_doing_ajax() ) {
						//  AJAX calls are an exception
						$result = true;
					} else if ( ! is_network_admin() ) {
						$result = false;
						break;
					}
				}

				$restrict_plugin_setting = $this->get_restrict_plugin_setting();
				if ( 'only_me' === $restrict_plugin_setting ) {
					$result = ( $user->ID == $this->get_only_me_user_id());
				} elseif ( 'only_admins' === $restrict_plugin_setting ) {
					if ($is_multisite) {
						$result = ( function_exists( 'is_super_admin' ) && is_super_admin($user->ID) );
					} else {
						$result = in_array('administrator', $user->roles);
					}
				}
				break;
			default:
				$result = false;
		}

		/**
		 * Filters the user permissions result.
		 *
		 * @since 4.1.3
		 *
		 * @param bool $result User access flag after applying all internal rules.
		 * @param WP_User $user The user in question.
		 * @param string $action Action to check permissions for.
		 * @return bool
		 */
		return apply_filters('wsal_user_can', $result, $user, $action);
	}

	public function GetCurrentUserRoles( $base_roles = null ) {
		if ( null == $base_roles ) {
			$base_roles = wp_get_current_user()->roles;
		}
		if ( function_exists( 'is_super_admin' ) && is_super_admin() ) {
			$base_roles[] = 'superadmin';
		}
		return $base_roles;
	}

	public function IsLoginSuperAdmin( $username ) {
		$user_id = username_exists( $username );
		return function_exists( 'is_super_admin' ) && is_super_admin( $user_id );
	}

	public function IsMainIPFromProxy() {
		return $this->_plugin->GetGlobalBooleanSetting( 'use-proxy-ip' );
	}

	public function SetMainIPFromProxy( $enabled ) {
		$this->_plugin->SetGlobalBooleanSetting( 'use-proxy-ip', $enabled );
	}

	public function IsInternalIPsFiltered() {
		return $this->_plugin->GetGlobalSetting( 'filter-internal-ip' );
	}

	public function SetInternalIPsFiltering( $enabled ) {
		$this->_plugin->SetGlobalSetting( 'filter-internal-ip', $enabled );
	}

	/**
	 * Get main client IP.
	 *
	 * @return string|null
	 */
	public function GetMainClientIP() {
		$result = null;

		if ( $this->IsMainIPFromProxy() ) {
			// TODO: The algorithm below just gets the first IP in the list...we might want to make this more intelligent somehow.
			$result = $this->GetClientIPs();
			$result = reset( $result );
			$result = isset( $result[0] ) ? $result[0] : null;
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip     = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			$result = $this->NormalizeIP( $ip );

			if ( ! $this->ValidateIP( $result ) ) {
				$result = 'Error ' . self::ERROR_CODE_INVALID_IP . ': Invalid IP Address';
			}
		}

		return $result;
	}

	/**
	 * Get client IP addresses.
	 *
	 * @return array
	 */
	public function GetClientIPs() {
		$ips = array();

		foreach ( array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ) as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				$ips[ $key ] = array();

				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = $this->NormalizeIP( $ip );

					if ( $this->ValidateIP( $ip ) ) {
						$ips[ $key ][] = $ip;
					}
				}
			}
		}

		return $ips;
	}

	/**
	 * Normalize IP address, i.e., remove the port number.
	 *
	 * @param string $ip - IP address.
	 * @return string
	 */
	protected function NormalizeIP( $ip ) {
		$ip = trim( $ip );

		if ( strpos( $ip, ':' ) !== false && substr_count( $ip, '.' ) === 3 && strpos( $ip, '[' ) === false ) {
			// IPv4 with a port (eg: 11.22.33.44:80).
			$ip = explode( ':', $ip );
			$ip = $ip[0];
		} else {
			// IPv6 with a port (eg: [::1]:80).
			$ip = explode( ']', $ip );
			$ip = ltrim( $ip[0], '[' );
		}

		return $ip;
	}

	/**
	 * Validate IP address.
	 *
	 * @param string $ip - IP address.
	 * @return string|bool
	 */
	protected function ValidateIP( $ip ) {
		$opts = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;

		if ( $this->IsInternalIPsFiltered() ) {
			$opts = $opts | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		}

		$filtered_ip = filter_var( $ip, FILTER_VALIDATE_IP, $opts );

		if ( ! $filtered_ip || empty( $filtered_ip ) ) {
			// Regex IPV4.
			if ( preg_match( '/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/', $ip ) ) {
				return $ip;
			} elseif ( preg_match( '/^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$/', $ip ) ) {
				// Regex IPV6.
				return $ip;
			}

			return false;
		} else {
			return $filtered_ip;
		}
	}

	/**
	 * Users excluded from monitoring.
	 */
	public function SetExcludedMonitoringUsers( $users ) {
		$this->_excluded_users = $users;
		$this->_plugin->SetGlobalSetting( 'excluded-users', esc_html( implode( ',', $this->_excluded_users ) ) );
	}

	public function GetExcludedMonitoringUsers() {
		if ( empty( $this->_excluded_users ) ) {
			$this->_excluded_users = array_unique( array_filter( explode( ',', $this->_plugin->GetGlobalSetting( 'excluded-users' ) ) ) );
		}
		return $this->_excluded_users;
	}

	/**
	 * Set Custom Post Types excluded from monitoring.
	 *
	 * @param array $post_types - Array of post types to exclude.
	 * @since 2.6.7
	 */
	public function set_excluded_post_types( $post_types ) {
		$this->_post_types = $post_types;
		$this->_plugin->SetGlobalSetting( 'custom-post-types', esc_html( implode( ',', $this->_post_types ) ) );
	}

	/**
	 * Get Custom Post Types excluded from monitoring.
	 *
	 * @since 2.6.7
	 */
	public function get_excluded_post_types() {
		if ( empty( $this->_post_types ) ) {
			$this->_post_types = array_unique( array_filter( explode( ',', $this->_plugin->GetGlobalSetting( 'custom-post-types' ) ) ) );
		}
		return $this->_post_types;
	}

	/**
	 * Set URLs excluded from monitoring.
	 *
	 * @param array $urls - Array of URLs.
	 * @since 3.2.2
	 */
	public function set_excluded_urls( $urls ) {
		$urls                = array_map( 'untrailingslashit', $urls );
		$urls                = array_unique( $urls );
		$this->excluded_urls = $urls;
		$this->_plugin->SetGlobalSetting( 'excluded-urls', esc_html( implode( ',', $this->excluded_urls ) ) );
	}

	/**
	 * Get URLs excluded from monitoring.
	 *
	 * @since 3.2.2
	 */
	public function get_excluded_urls() {
		if ( empty( $this->excluded_urls ) ) {
			$this->excluded_urls = array_unique( array_filter( explode( ',', $this->_plugin->GetGlobalSetting( 'excluded-urls' ) ) ) );
		}
		return $this->excluded_urls;
	}

	/**
	 * Set roles excluded from monitoring.
	 *
	 * @param array $roles - Array of roles.
	 */
	public function SetExcludedMonitoringRoles( $roles ) {
		$this->_excluded_roles = $roles;
		$this->_plugin->SetGlobalSetting( 'excluded-roles', esc_html( implode( ',', $this->_excluded_roles ) ) );
	}

	/**
	 * Get roles excluded from monitoring.
	 */
	public function GetExcludedMonitoringRoles() {
		if ( empty( $this->_excluded_roles ) ) {
			$this->_excluded_roles = array_unique( array_filter( explode( ',', $this->_plugin->GetGlobalSetting( 'excluded-roles' ) ) ) );
		}
		return $this->_excluded_roles;
	}

	/**
	 * Custom fields excluded from monitoring.
	 */
	public function SetExcludedMonitoringCustom( $custom ) {
		$this->_excluded_custom = $custom;
		$this->_plugin->SetGlobalSetting( 'excluded-custom', esc_html( implode( ',', $this->_excluded_custom ) ) );
	}

	public function GetExcludedMonitoringCustom() {
		if ( empty( $this->_excluded_custom ) ) {
			$this->_excluded_custom = array_unique( array_filter( explode( ',', $this->_plugin->GetGlobalSetting( 'excluded-custom' ) ) ) );
			asort( $this->_excluded_custom );
		}
		return $this->_excluded_custom;
	}

	/**
	 * IP excluded from monitoring.
	 */
	public function SetExcludedMonitoringIP( $ip ) {
		$this->_excluded_ip = $ip;
		$this->_plugin->SetGlobalSetting( 'excluded-ip', esc_html( implode( ',', $this->_excluded_ip ) ) );
	}

	public function GetExcludedMonitoringIP() {
		if ( empty( $this->_excluded_ip ) ) {
			$this->_excluded_ip = array_unique( array_filter( explode( ',', $this->_plugin->GetGlobalSetting( 'excluded-ip' ) ) ) );
		}
		return $this->_excluded_ip;
	}

	/**
	 * Datetime used in the Alerts.
	 *
	 * @param boolean $line_break - True if line break otherwise false.
	 */
	public function GetDatetimeFormat( $line_break = true ) {
		if ( $line_break ) {
			$date_time_format = $this->GetDateFormat() . '<\b\r>' . $this->GetTimeFormat();
		} else {
			$date_time_format = $this->GetDateFormat() . ' ' . $this->GetTimeFormat();
		}

		$wp_time_format = get_option( 'time_format' ); // WP time format.

		// Check if the time format does not have seconds.
		if ( stripos( $wp_time_format, 's' ) === false ) {
			if ( stripos( $wp_time_format, '.v' ) !== false ) {
				$date_time_format = str_replace( '.v', '', $date_time_format );
			}
			$date_time_format .= ':s'; // Add seconds to time format.
			$date_time_format .= '.$$$'; // Add milliseconds to time format.
		} else {
			// Check if the time format does have milliseconds.
			if ( stripos( $wp_time_format, '.v' ) !== false ) {
				$date_time_format = str_replace( '.v', '.$$$', $date_time_format );
			} else {
				$date_time_format .= '.$$$';
			}
		}

		if ( stripos( $wp_time_format, 'A' ) !== false ) {
			$date_time_format .= '&\n\b\s\p;A';
		}
		return $date_time_format;
	}

	/**
	 * Date Format from WordPress General Settings.
	 */
	public function GetDateFormat() {
		$wp_date_format = get_option( 'date_format' );
		$search         = array( 'F', 'M', 'n', 'j', ' ', '/', 'y', 'S', ',', 'l', 'D' );
		$replace        = array( 'm', 'm', 'm', 'd', '-', '-', 'Y', '', '', '', '' );
		$date_format    = str_replace( $search, $replace, $wp_date_format );
		return $date_format;
	}

	/**
	 * Time Format from WordPress General Settings.
	 */
	public function GetTimeFormat() {
		$wp_time_format = get_option( 'time_format' );
		$search         = array( 'a', 'A', 'T', ' ' );
		$replace        = array( '', '', '', '' );
		$time_format    = str_replace( $search, $replace, $wp_time_format );
		return $time_format;
	}

	/**
	 * Alerts Timestamp.
	 *
	 * Server's timezone or WordPress' timezone.
	 */
	public function GetTimezone() {
		return $this->_plugin->GetGlobalSetting( 'timezone', 'wp' );
	}

	public function SetTimezone( $newvalue ) {
		$this->_plugin->SetGlobalSetting( 'timezone', $newvalue );
	}

	/**
	 * Helper method to get the stored setting to determine if milliseconds
	 * appear in the admin list view. This should always be a bool.
	 *
	 * @method get_show_milliseconds
	 * @since  3.5.2
	 * @return bool
	 */
	public function get_show_milliseconds() {
		return $this->_plugin->GetGlobalBooleanSetting( 'show_milliseconds', true );
	}

	/**
	 * Stores the option that dicates if milliseconds show in admin list view
	 * for event times. This is always a bool. When it's not a bool it's set
	 * to `true` to match default.
	 *
	 * @method set_show_milliseconds
	 * @since  3.5.2
	 * @param  mixed $newvalue ideally always bool. If not bool then it's cast to true.
	 */
	public function set_show_milliseconds( $newvalue ) {
		$this->_plugin->SetGlobalBooleanSetting( 'show_milliseconds', $newvalue );
	}


	/**
	 * Get type of username to display.
	 */
	public function get_type_username() {
		return $this->_plugin->GetGlobalSetting( 'type_username', 'display_name' );
	}

	/**
	 * Set type of username to display
	 *
	 * @param string $newvalue - New value variable.
	 * @since 2.6.5
	 */
	public function set_type_username( $newvalue ) {
		$this->_plugin->SetGlobalSetting( 'type_username', $newvalue );
	}

	public function GetAdapterConfig( $name_field, $default_value = false ) {
		return $this->_plugin->GetGlobalSetting( $name_field, $default_value );
	}

	public function SetAdapterConfig( $name_field, $newvalue ) {
		$this->_plugin->SetGlobalSetting( $name_field, trim( $newvalue ) );
	}

	/**
	 * Returns audit log columns.
	 *
	 * @return array
	 */
	public function GetColumns() {
		$columns = array(
			'alert_code' => '1',
			'type'       => '1',
			'info'       => '1',
			'date'       => '1',
			'username'   => '1',
			'source_ip'  => '1',
			'object'     => '1',
			'event_type' => '1',
			'message'    => '1',
			'info'       => '1',
		);

		if ( $this->_plugin->IsMultisite() ) {
			$columns = array_slice( $columns, 0, 6, true ) + array( 'site' => '1' ) + array_slice( $columns, 6, null, true );
		}

		$selected = $this->GetColumnsSelected();

		if ( ! empty( $selected ) ) {
			$columns = array(
				'alert_code' => '0',
				'type'       => '0',
				'info'       => '0',
				'date'       => '0',
				'username'   => '0',
				'source_ip'  => '0',
				'object'     => '0',
				'event_type' => '0',
				'message'    => '0',
			);

			if ( $this->_plugin->IsMultisite() ) {
				$columns = array_slice( $columns, 0, 6, true ) + array( 'site' => '0' ) + array_slice( $columns, 6, null, true );
			}

			$selected = (array) json_decode( $selected );
			$columns  = array_merge( $columns, $selected );
		}

		return $columns;
	}

	public function GetColumnsSelected() {
		return $this->_plugin->GetGlobalSetting( 'columns', array() );
	}

	public function SetColumns( $columns ) {
		$this->_plugin->SetGlobalSetting( 'columns', json_encode( $columns ) );
	}

	public function IsWPBackend() {
		return $this->_plugin->GetGlobalBooleanSetting( 'wp-backend' );
	}

	public function SetWPBackend( $enabled ) {
		$this->_plugin->SetGlobalBooleanSetting( 'wp-backend', $enabled );
	}

	/**
	 * Set use email setting.
	 *
	 * @param string $use – Setting value.
	 */
	public function set_use_email( $use ) {
		$this->_plugin->SetGlobalSetting( 'use-email', $use );
	}

	/**
	 * Get use email setting.
	 *
	 * @return string
	 */
	public function get_use_email() {
		return $this->_plugin->GetGlobalSetting( 'use-email', 'default_email' );
	}

	public function SetFromEmail( $email_address ) {
		$this->_plugin->SetGlobalSetting( 'from-email', trim( $email_address ) );
	}

	public function GetFromEmail() {
		return $this->_plugin->GetGlobalSetting( 'from-email' );
	}

	public function SetDisplayName( $display_name ) {
		$this->_plugin->SetGlobalSetting( 'display-name', trim( $display_name ) );
	}

	public function GetDisplayName() {
		return $this->_plugin->GetGlobalSetting( 'display-name' );
	}

	public function Set404LogLimit( $value ) {
		$this->_plugin->SetGlobalSetting( 'log-404-limit', abs( $value ) );
	}

	public function Get404LogLimit() {
		return $this->_plugin->GetGlobalSetting( 'log-404-limit', 99 );
	}

	/**
	 * Sets the 404 log limit for visitor.
	 *
	 * @param  int $value - 404 log limit.
	 * @since  2.6.3
	 */
	public function SetVisitor404LogLimit( $value ) {
		$this->_plugin->SetGlobalSetting( 'log-visitor-404-limit', abs( $value ) );
	}

	/**
	 * Get the 404 log limit for visitor.
	 *
	 * @since  2.6.3
	 */
	public function GetVisitor404LogLimit() {
		return $this->_plugin->GetGlobalSetting( 'log-visitor-404-limit', 99 );
	}

	/**
	 * Sets the log limit for failed login attempts.
	 *
	 * @param  int $value - Failed login limit.
	 * @since  2.6.3
	 */
	public function set_failed_login_limit( $value ) {
		if ( ! empty( $value ) ) {
			$this->_plugin->SetGlobalSetting( 'log-failed-login-limit', abs( $value ) );
		} else {
			$this->_plugin->SetGlobalSetting( 'log-failed-login-limit', -1 );
		}
	}

	/**
	 * Get the log limit for failed login attempts.
	 *
	 * @since  2.6.3
	 */
	public function get_failed_login_limit() {
		return $this->_plugin->GetGlobalSetting( 'log-failed-login-limit', 10 );
	}

	/**
	 * Sets the log limit for failed login attempts for visitor.
	 *
	 * @param  int $value - Failed login limit.
	 * @since  2.6.3
	 */
	public function set_visitor_failed_login_limit( $value ) {
		if ( ! empty( $value ) ) {
			$this->_plugin->SetGlobalSetting( 'log-visitor-failed-login-limit', abs( $value ) );
		} else {
			$this->_plugin->SetGlobalSetting( 'log-visitor-failed-login-limit', -1 );
		}
	}

	/**
	 * Get the log limit for failed login attempts for visitor.
	 *
	 * @since  2.6.3
	 */
	public function get_visitor_failed_login_limit() {
		return $this->_plugin->GetGlobalSetting( 'log-visitor-failed-login-limit', 10 );
	}

	public function IsArchivingEnabled() {
		return $this->_plugin->GetGlobalSetting( 'archiving-e' );
	}

	/**
	 * Switch to Archive DB if is enabled.
	 */
	public function SwitchToArchiveDB() {
		if ( $this->IsArchivingEnabled() ) {
			$archive_type       = $this->_plugin->GetGlobalSetting( 'archive-type' );
			$archive_user       = $this->_plugin->GetGlobalSetting( 'archive-user' );
			$password           = $this->_plugin->GetGlobalSetting( 'archive-password' );
			$archive_name       = $this->_plugin->GetGlobalSetting( 'archive-name' );
			$archive_hostname   = $this->_plugin->GetGlobalSetting( 'archive-hostname' );
			$archive_baseprefix = $this->_plugin->GetGlobalSetting( 'archive-base-prefix' );
			$archive_ssl        = $this->_plugin->GetGlobalSetting( 'archive-ssl', false );
			$archive_cc         = $this->_plugin->GetGlobalSetting( 'archive-client-certificate', false );
			$archive_ssl_ca     = $this->_plugin->GetGlobalSetting( 'archive-ssl-ca', false );
			$archive_ssl_cert   = $this->_plugin->GetGlobalSetting( 'archive-ssl-cert', false );
			$archive_ssl_key    = $this->_plugin->GetGlobalSetting( 'archive-ssl-key', false );
			$config             = WSAL_Connector_ConnectorFactory::GetConfigArray( $archive_type, $archive_user, $password, $archive_name, $archive_hostname, $archive_baseprefix, $archive_ssl, $archive_cc, $archive_ssl_ca, $archive_ssl_cert, $archive_ssl_key );
			$this->_plugin->getConnector( $config )->getAdapter( 'Occurrence' );
		}
	}

	/**
	 * Generate index.php file for each sub-directory present in the plugin working directory.
	 *
	 * @since 3.1.2
	 */
	public function generate_index_files() {
		//  get plugin working directory.
		$wsal_working_dir = $this->get_working_dir_path();

		// If the directory exists then generate index.php file for every sub-directory.
		if ( ! empty( $wsal_working_dir ) && is_dir( $wsal_working_dir ) ) {
			// Generate index.php for the main directory.
			if ( ! file_exists( $wsal_working_dir . DIRECTORY_SEPARATOR . 'index.php' ) ) {
				// Generate index.php file.
				$this->create_index_file( $wsal_working_dir );
			}

			// Generate .htaccess for the main directory.
			if ( ! file_exists( $wsal_working_dir . DIRECTORY_SEPARATOR . '.htaccess' ) ) {
				// Generate .htaccess file.
				$this->create_htaccess_file( $wsal_working_dir );
			}

			// Fetch all files in the uploads directory.
			$sub_directories = glob( $wsal_working_dir . '*' );
			foreach ( $sub_directories as $sub_dir ) {
				// index.php file.
				if ( is_dir( $sub_dir ) && ! file_exists( $sub_dir . DIRECTORY_SEPARATOR . 'index.php' ) ) {
					// Generate index.php file.
					$this->create_index_file( $sub_dir . '/' );
				}

				// .htaccess file.
				if ( is_dir( $sub_dir ) && ! file_exists( $sub_dir . DIRECTORY_SEPARATOR . '.htaccess' ) ) {
					// Generate .htaccess file.
					$this->create_htaccess_file( $sub_dir . DIRECTORY_SEPARATOR );
				}
			}
		}
	}

	/**
	 * Create an index.php file, if none exists, in order to
	 * avoid directory listing in the specified directory.
	 *
	 * @param string $dir_path - Directory Path.
	 * @return bool
	 * @since 3.1.2
	 */
	final public function create_index_file( $dir_path ) {
		// Check if index.php file exists.
		$dir_path = trailingslashit( $dir_path );
		$result   = 0;
		if ( ! is_file( $dir_path . 'index.php' ) ) {
			$result = @file_put_contents( $dir_path . 'index.php', '<?php // Silence is golden' );
		}
		return ( $result > 0 );
	}

	/**
	 * Create an .htaccess file, if none exists, in order to
	 * block access to directory listing in the specified directory.
	 *
	 * @param string $dir_path - Directory Path.
	 * @return bool
	 * @since 3.1.2
	 */
	final public function create_htaccess_file( $dir_path ) {
		// Check if .htaccess file exists.
		$dir_path = trailingslashit( $dir_path );
		$result   = 0;
		if ( ! is_file( $dir_path . '.htaccess' ) ) {
			$result = @file_put_contents( $dir_path . '.htaccess', 'Deny from all' );
		}
		return ( $result > 0 );
	}

	/**
	 * Method: Get Token Type.
	 *
	 * @param string $token - Token type.
	 * @since 3.2.3
	 */
	public function get_token_type( $token ) {
		// Get users.
		$users = array();
		foreach ( get_users( 'blog_id=0&fields[]=user_login' ) as $obj ) {
			$users[] = $obj->user_login;
		}

		// Get user roles.
		$roles = array_keys( get_editable_roles() );

		// Get custom post types.
		$post_types = get_post_types( array(), 'names', 'and' );
		// if we are running multisite and have networkwide cpt tracker get the
		// list from and merge to the post_types array.
		if ( is_multisite() && class_exists( '\WSAL\Multisite\NetworkWide\CPTsTracker' ) ) {
			$network_cpts = \WSAL\Multisite\NetworkWide\CPTsTracker::get_network_data_list();
			foreach ( $network_cpts as $cpt ) {
				$post_types[ $cpt ] = $cpt;
			}
		}

		// Check if the token matched users.
		if ( in_array( $token, $users ) ) {
			return 'user';
		}

		// Check if the token matched user roles.
		if ( in_array( $token, $roles ) ) {
			return 'role';
		}

		// Check if the token matched post types.
		if ( in_array( $token, $post_types ) ) {
			return 'cpts';
		}

		// Check if the token matches a URL.
		if ( ( false !== strpos( $token, home_url() ) ) && filter_var( $token, FILTER_VALIDATE_URL ) ) {
			return 'urls';
		}

		// Check for IP range.
		if ( strpos( $token, '-' ) !== false ) {
			$ip_range = $this->get_ipv4_by_range( $token );

			if ( $ip_range && filter_var( $ip_range->lower, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $ip_range->upper, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) { // Validate IPv4.
				return 'ip';
			}
		}

		// Check if the token matches an IP address.
		if (
			filter_var( $token, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) // Validate IPv4.
			|| filter_var( $token, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) // Validate IPv6.
		) {
			return 'ip';
		}

		return 'other';
	}

	/**
	 * Set MainWP Child Stealth Mode
	 *
	 * Set the plugin in stealth mode for MainWP child sites.
	 *
	 * Following steps are taken in stealth mode:
	 *   1. Freemius connection is skipped.
	 *   2. Freemius notices are removed.
	 *   3. WSAL's incognito mode is set.
	 *   4. Other site admins are restricted.
	 *   5. The current user is set as the sole editor of WSAL.
	 *   6. Stealth mode option is saved.
	 *
	 * @since 3.2.3.3
	 */
	public function set_mainwp_child_stealth_mode() {
		if (
			! $this->_plugin->GetGlobalBooleanSetting( 'mwp-child-stealth-mode', false ) // MainWP Child Stealth Mode is not already active.
			&& WpSecurityAuditLog::is_mainwp_active() // And if MainWP Child plugin is installed & active.
		) {
			// Check if freemius state is anonymous.
			if ( ! wsal_freemius()->is_premium() && 'anonymous' === $this->_plugin->GetGlobalSetting( 'freemius_state', 'anonymous' ) ) {
				// Update freemius state to skipped.
				$this->_plugin->SetGlobalSetting( 'wsal_freemius_state', 'skipped' );

				if ( ! $this->_plugin->IsMultisite() ) {
					wsal_freemius()->skip_connection(); // Opt out.
				} else {
					wsal_freemius()->skip_connection( null, true ); // Opt out for all websites.
				}

				// Connect account notice.
				FS_Admin_Notices::instance( 'wp-security-audit-log' )->remove_sticky( 'connect_account' );
			}

			if ( ! wsal_freemius()->is_premium() ) {
				// Remove Freemius trial promotion notice.
				FS_Admin_Notices::instance( 'wp-security-audit-log' )->remove_sticky( 'trial_promotion' );
			}

			$this->SetIncognito( true ); // Incognito mode to hide WSAL on plugins page.
			$this->set_restrict_log_viewer('only_me');
			$this->set_restrict_plugin_setting('only_me');
			//  current user with fallback to default admin (in case this is triggered using WP CLI or something similar)
			$only_me_user_id = is_user_logged_in() ? get_current_user_id() : 1;
			$this->set_only_me_user_id($only_me_user_id);
			$this->_plugin->SetGlobalBooleanSetting( 'mwp-child-stealth-mode', true ); // Save stealth mode option.
		}
	}

	/**
	 * Deactivate MainWP Child Stealth Mode.
	 *
	 * @since 3.2.3.3
	 */
	public function deactivate_mainwp_child_stealth_mode() {
		$this->SetIncognito( false ); // Disable incognito mode to hide WSAL on plugins page.
		$this->set_restrict_plugin_setting('only_admins');
		$this->set_restrict_log_viewer('only_admins');
		$this->set_admin_blocking_plugin_support(false);
		$this->_plugin->SetGlobalBooleanSetting( 'mwp-child-stealth-mode', false ); // Disable stealth mode option.
	}

	/**
	 * Reset Stealth Mode on MainWP Child plugin deactivation.
	 *
	 * @param string $plugin — Plugin.
	 */
	public function reset_stealth_mode( $plugin ) {
		if ( 'mainwp-child/mainwp-child.php' !== $plugin ) {
			return;
		}

		if ( $this->_plugin->GetGlobalBooleanSetting( 'mwp-child-stealth-mode', false ) ) {
			$this->deactivate_mainwp_child_stealth_mode();
		}
	}

	/**
	 * Check and return if stealth mode is active.
	 *
	 * @return boolean
	 */
	public function is_stealth_mode() {
		return $this->_plugin->GetGlobalBooleanSetting( 'mwp-child-stealth-mode', false );
	}

	/**
	 * Method: Meta data formatter.
	 *
	 * @param string $name - Name of the data.
	 * @param mixed $value - Value of the data.
	 * @param integer $occ_id - Event occurrence ID.
	 * @param mixed $highlight - Highlight format.
	 *
	 * @return string
	 * @throws Freemius_Exception
	 */
	public function meta_formatter( $name, $value, $occ_id, $highlight ) {
		if ( $highlight && 'daily-report' === $highlight ) {
			$highlight_start_tag = '<span style="color: #149247;">';
			$highlight_end_tag   = '</span>';
		} else {
			$highlight_start_tag = '<strong>';
			$highlight_end_tag   = '</strong>';
		}

		switch ( true ) {
			case '%Message%' == $name:
				return esc_html( $value );
			case '%PromoMessage%' == $name:
				return '<p class="promo-alert">' . $value . '</p>';
			case '%PromoLink%' == $name:
			case '%CommentLink%' == $name:
			case '%CommentMsg%' == $name:
				return $value;

			case '%MetaLink%' == $name:
				if ( ! empty( $value ) ) {
					return "<a href=\"#\" data-disable-custom-nonce='" . wp_create_nonce( 'disable-custom-nonce' . $value ) . "' onclick=\"WsalDisableCustom(this, '" . $value . "');\"> Exclude Custom Field from the Monitoring</a>";
				} else {
					return '';
				}

			case '%RevisionLink%' === $name:
				$check_value = (string) $value;
				if ( 'NULL' !== $check_value ) {
					return '<a target="_blank" href="' . esc_url( $value ) . '">' . __( 'View the content changes', 'wp-security-audit-log' ) . '</a>';
				}
				return false;

			case in_array( $name, array( '%EditorLinkPost%', '%EditorLinkPage%' ) ):
				return '<a target="_blank" href="' . esc_url( $value ) . '">' . __( 'View post in the editor', 'wp-security-audit-log' ) . '</a>';

			case '%EditorLinkOrder%' == $name:
				return '<a target="_blank" href="' . esc_url( $value ) . '">' . __( 'View Order', 'wp-security-audit-log' ) . '</a>';

			case '%CategoryLink%' == $name:
			case '%cat_link%' == $name:
			case '%ProductCatLink%' == $name:
				return '<a target="_blank" href="' . esc_url( $value ) . '">' . __( 'View category', 'wp-security-audit-log' ) . '</a>';

			case '%TagLink%' == $name:
				return '<a target="_blank" href="' . esc_url( $value ) . '">' . __( 'View tag', 'wp-security-audit-log' ) . '</a>';

			case '%EditUserLink%' === $name:
				if ( 'NULL' !== $value ) {
					return '<a href="' . $value . '" target="_blank">' . __( 'User profile page', 'wp-security-audit-log' ) . '</a>';
				}
				return '';

			case in_array( $name, array( '%MetaValue%', '%MetaValueOld%', '%MetaValueNew%' ) ):
				return $highlight_start_tag . (
					strlen( $value ) > 50 ? ( esc_html( substr( $value, 0, 50 ) ) . '&hellip;' ) : esc_html( $value )
				) . $highlight_end_tag;

			case '%ClientIP%' == $name:
			case '%IPAddress%' == $name:
				if ( is_string( $value ) ) {
					return $highlight_start_tag . str_replace( array( '"', '[', ']' ), '', $value ) . $highlight_end_tag;
				} else {
					return '<i>unknown</i>';
				}

			case '%LinkFile%' === $name:
				if ( ! $this->is_current_page( 'dashboard' ) ) {
					if ( 'NULL' != $value ) {
						$site_id = $this->get_view_site_id(); // Site id for multisite.
						return '<a href="javascript:;" onclick="download_404_log( this )" data-log-file="' . esc_attr( $value ) . '" data-site-id="' . esc_attr( $site_id ) . '" data-nonce-404="' . esc_attr( wp_create_nonce( 'wsal-download-404-log-' . $value ) ) . '" title="' . esc_html__( 'Download the log file', 'wp-security-audit-log' ) . '">' . esc_html__( 'Download the log file', 'wp-security-audit-log' ) . '</a>';
					} else {
						return 'Click <a href="' . esc_url( add_query_arg( 'page', 'wsal-togglealerts', admin_url( 'admin.php' ) ) ) . '">here</a> to log such requests to file';
					}
				}
				return '';

			case '%URL%' === $name:
				if ( ! $this->is_current_page( 'dashboard' ) ) {
					return ' or <a href="javascript:;" data-exclude-url="' . esc_url( $value ) . '" data-exclude-url-nonce="' . wp_create_nonce( 'wsal-exclude-url-' . $value ) . '" onclick="wsal_exclude_url( this )">exclude this URL</a> from being reported.';
				}
				return '';

			case '%PostUrlIfPlublished%' === $name:
				// get connection.
				$db_config = WSAL_Connector_ConnectorFactory::GetConfig(); // Get DB connector configuration.
				$connector = $this->_plugin->getConnector( $db_config ); // Get connector for DB.
				$wsal_db   = $connector->getConnection(); // Get DB connection.
				// get values needed.
				$meta_adapter = new WSAL_Adapters_MySQL_Meta( $wsal_db );
				$post_id      = $meta_adapter->LoadByNameAndOccurenceId( 'PostID', $occ_id );
				$occ_post     = ( isset( $post_id['value'] ) ) ? get_post( $post_id['value'] ) : null;
				// start with an empty string.
				$return_value = '';
				if ( null !== $occ_post && 'publish' === $occ_post->post_status ) {
					$post_permalink = get_permalink( $occ_post->ID );
					$return_value   = '<br>URL: <a href="' . esc_url( $post_permalink ) . '" title="' . esc_attr( $occ_post->post_title ) . '" target="_blank">' . esc_html( $post_permalink ) . '</a>';
				}
				return $return_value;

			case '%LogFileLink%' === $name: // Failed login file link.
				return '';

			case '%Attempts%' === $name: // Failed login attempts.
				$check_value = (int) $value;
				if ( 0 === $check_value ) {
					return '';
				} else {
					return $value;
				}

			case '%LogFileText%' === $name: // Failed login file text.
				return '<a href="javascript:;" onclick="download_failed_login_log( this )" data-download-nonce="' . esc_attr( wp_create_nonce( 'wsal-download-failed-logins' ) ) . '" title="' . esc_html__( 'Download the log file.', 'wp-security-audit-log' ) . '">' . esc_html__( 'Download the log file.', 'wp-security-audit-log' ) . '</a>';

			case strncmp( $value, 'http://', 7 ) === 0:
			case strncmp( $value, 'https://', 8 ) === 0:
				$updated_line = apply_filters( 'wsal_link_filter', $value, $name );
				if ( $updated_line !== $value ) {
					return $updated_line;
				} else {
					return '<a href="' . esc_html( $value ) . '" title="' . esc_html( $value ) . '" target="_blank">' . esc_html( $value ) . '</a>';
				}

			case in_array( $name, array( '%PostStatus%', '%ProductStatus%' ), true ):
				if ( ! empty( $value ) && 'publish' === $value ) {
					return $highlight_start_tag . esc_html__( 'published', 'wp-security-audit-log' ) . $highlight_end_tag;
				} else {
					return $highlight_start_tag . esc_html( $value ) . $highlight_end_tag;
				}

			case '%multisite_text%' === $name:
				if ( $this->_plugin->IsMultisite() && $value ) {
					$site_info = get_blog_details( $value, true );
					if ( $site_info ) {
						return ' on site <a href="' . esc_url( $site_info->siteurl ) . '">' . esc_html( $site_info->blogname ) . '</a>';
					}
					return;
				}
				return;

			case '%ReportText%' === $name:
				return;

			case '%ChangeText%' === $name:
				return;

			case '%ScanError%' === $name:
				if ( 'NULL' === $value ) {
					return false;
				}
				/* translators: Mailto link for support. */
				return ' with errors. ' . sprintf( __( 'Contact us on %s for assistance', 'wp-security-audit-log' ), '<a href="mailto:support@wpsecurityauditlog.com" target="_blank">support@wpsecurityauditlog.com</a>' );

			case '%TableNames%' === $name:
				$value = str_replace( ',', ', ', $value );
				return $highlight_start_tag . esc_html( $value ) . $highlight_end_tag;

			case '%FileSettings%' === $name:
				$file_settings_args = array(
					'page' => 'wsal-settings',
					'tab'  => 'file-changes',
				);
				$file_settings      = add_query_arg( $file_settings_args, admin_url( 'admin.php' ) );
				return '<a href="' . esc_url( $file_settings ) . '">' . esc_html__( 'Increase maximum file size limit', 'wp-security-audit-log' ) . '</a>';

			case '%ContactSupport%' === $name:
				return '<a href="https://wpactivitylog.com/contact/" target="_blank">' . esc_html__( 'Contact Support', 'wp-security-audit-log' ) . '</a>';

			case '%LineBreak%' === $name:
				return '<br>';

			case '%PluginFile%' === $name:
				return $highlight_start_tag . dirname( $value ) . $highlight_end_tag;

			default:
				// if we didn't get a match already try get one via a filter.
				$filtered_formatted_value = apply_filters( 'wsal_meta_formatter_custom_formatter', $value, $name );
				return ( $value !== $filtered_formatted_value  ) ? $filtered_formatted_value : $highlight_start_tag . esc_html( $value ) . $highlight_end_tag;
		}
	}

	/**
	 * Method: Get view site id.
	 *
	 * @since 3.2.4
	 *
	 * @return int
	 */
	public function get_view_site_id() {
		switch ( true ) {
			// Non-multisite.
			case ! $this->_plugin->IsMultisite():
				return 0;
			// Multisite + main site view.
			case $this->is_main_blog() && ! $this->is_specific_view():
				return 0;
			// Multisite + switched site view.
			case $this->is_main_blog() && $this->is_specific_view():
				return $this->get_specific_view();
			// Multisite + local site view.
			default:
				return get_current_blog_id();
		}
	}

	/**
	 * Method: Check if the blog is main blog.
	 *
	 * @since 3.2.4
	 *
	 * @return bool
	 */
	protected function is_main_blog() {
		return get_current_blog_id() === 1;
	}

	/**
	 * Method: Check if it is a specific view.
	 *
	 * @since 3.2.4
	 *
	 * @return bool
	 */
	protected function is_specific_view() {
		return isset( $_REQUEST['wsal-cbid'] ) && 0 !== (int) $_REQUEST['wsal-cbid']; // @codingStandardsIgnoreLine
	}

	/**
	 * Method: Get a specific view.
	 *
	 * @since 3.2.4
	 *
	 * @return int
	 */
	protected function get_specific_view() {
		return isset( $_REQUEST['wsal-cbid'] ) ? (int) sanitize_text_field( wp_unslash( $_REQUEST['wsal-cbid'] ) ) : 0; // @codingStandardsIgnoreLine
	}

	/**
	 * Query sites from WPDB.
	 *
	 * @since 3.3.0.1
	 *
	 * @param int|null $limit — Maximum number of sites to return (null = no limit).
	 * @return object — Object with keys: blog_id, blogname, domain
	 */
	public function get_sites( $limit = null ) {
		global $wpdb;

		$sql = 'SELECT blog_id, domain FROM ' . $wpdb->blogs;
		if ( ! is_null( $limit ) ) {
			$sql .= ' LIMIT ' . $limit;
		}
		$res = $wpdb->get_results( $sql );
		foreach ( $res as $row ) {
			$row->blogname = get_blog_option( $row->blog_id, 'blogname' );
		}
		return $res;
	}

	/**
	 * The number of sites on the network.
	 *
	 * @since 3.3.0.1
	 *
	 * @return int
	 */
	public function get_site_count() {
		global $wpdb;
		$sql = 'SELECT COUNT(*) FROM ' . $wpdb->blogs;
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Method: Meta data formatter.
	 *
	 * @since 3.3
	 *
	 * @param string  $name      - Name of the data.
	 * @param mixed   $value     - Value of the data.
	 * @param integer $occ_id    - Event occurrence ID.
	 * @param mixed   $highlight - Highlight format.
	 * @return string
	 */
	public function slack_meta_formatter( $name, $value, $occ_id, $highlight ) {
		switch ( true ) {
			case '%Message%' === $name:
				return esc_html( $value );

			case '%PromoLink%' === $name:
			case '%CommentLink%' === $name:
			case '%CommentMsg%' === $name:
				return $value;

			case '%MetaLink%' === $name:
				return '';

			case '%RevisionLink%' === $name:
				$check_value = (string) $value;
				if ( 'NULL' !== $check_value ) {
					return ' Click <' . esc_url( $value ) . '|here> to see the content changes.';
				} else {
					return false;
				}

			case '%EditorLinkPost%' === $name:
				return ' View the <' . esc_url( $value ) . '|post>';

			case '%EditorLinkOrder%' === $name:
				return ' <' . esc_url( $value ) . '|View Order>';

			case '%CategoryLink%' === $name:
			case '%cat_link%' === $name:
			case '%ProductCatLink%' == $name:
				return ' View the <' . esc_url( $value ) . '|category>';

			case '%TagLink%' === $name:
				return ' View the <' . esc_url( $value ) . '|tag>';

			case '%EditorLinkForum%' === $name:
				return ' View the <' . esc_url( $value ) . '|forum>';

			case '%EditorLinkTopic%' === $name:
				return ' View the <' . esc_url( $value ) . '|topic>';

			case in_array( $name, array( '%MetaValue%', '%MetaValueOld%', '%MetaValueNew%' ), true ):
				return '*' . ( strlen( $value ) > 50 ? ( esc_html( substr( $value, 0, 50 ) ) . '...' ) : esc_html( $value ) ) . '*';

			case '%ClientIP%' === $name:
				if ( is_string( $value ) ) {
					return '*' . str_replace( array( '"', '[', ']' ), '', $value ) . '*';
				} else {
					return '_unknown_';
				}

			case '%LinkFile%' === $name:
				if ( 'NULL' != $value ) {
					return '';
				} else {
					return 'Click <' . esc_url( add_query_arg( 'page', 'wsal-togglealerts', admin_url( 'admin.php' ) ) ) . '|here> to log such requests to file';
				}

			case '%URL%' === $name:
				return '.';

			case '%LogFileLink%' === $name: // Failed login file link.
				return '';

			case '%Attempts%' === $name: // Failed login attempts.
				$check_value = (int) $value;
				if ( 0 === $check_value ) {
					return '';
				} else {
					return $value;
				}

			case '%LogFileText%' === $name: // Failed login file text.
				return '';

			case strncmp( $value, 'http://', 7 ) === 0:
			case strncmp( $value, 'https://', 8 ) === 0:
				return '<' . esc_html( $value ) . '|' . esc_html( $value ) . '>';

			case in_array( $name, array( '%PostStatus%', '%ProductStatus%' ), true ):
				if ( ! empty( $value ) && 'publish' === $value ) {
					return '*' . esc_html__( 'published', 'wp-security-audit-log' ) . '*';
				} else {
					return '*' . esc_html( $value ) . '*';
				}

			case '%multisite_text%' === $name:
				if ( $this->_plugin->IsMultisite() && $value ) {
					$site_info = get_blog_details( $value, true );
					if ( $site_info ) {
						return ' on site <' . esc_url( $site_info->siteurl ) . '|' . esc_html( $site_info->blogname ) . '>';
					}
					return;
				}
				return;

			case '%ReportText%' === $name:
				return;

			case '%ChangeText%' === $name:
				return;

			case '%ScanError%' === $name:
				if ( 'NULL' === $value ) {
					return false;
				}
				/* translators: Mailto link for support. */
				return ' with errors. ' . sprintf( __( 'Contact us on %s for assistance', 'wp-security-audit-log' ), '<mailto:support@wpsecurityauditlog.com|support@wpsecurityauditlog.com>' );

			case '%TableNames%' === $name:
				$value = str_replace( ',', ', ', $value );
				return '*' . esc_html( $value ) . '*';

			case '%FileSettings%' === $name:
				$file_settings_args = array(
					'page' => 'wsal-settings',
					'tab'  => 'file-changes',
				);
				$file_settings      = add_query_arg( $file_settings_args, admin_url( 'admin.php' ) );
				return '<' . esc_url( $file_settings ) . '|' . esc_html__( 'plugin settings', 'wp-security-audit-log' ) . '>';

			case '%ContactSupport%' === $name:
				return '<https://wpactivitylog.com/contact|' . esc_html__( 'contact our support', 'wp-security-audit-log' ) . '>';

			case '%LineBreak%' === $name:
				return;

			default:
				return '*' . esc_html( $value ) . '*';
		}
	}

	/**
	 * Checks Infinite Scroll.
	 *
	 * Returns true if infinite scroll is enabled.
	 *
	 * @since 3.3.1.1
	 *
	 * @return boolean
	 */
	public function is_infinite_scroll() {
		return 'infinite-scroll' === $this->get_events_type_nav() ? true : false;
	}

	/**
	 * Checks Events Navigation Type.
	 *
	 * Returns type of navigation for events log viewer.
	 *
	 * @since 3.3.1.1
	 *
	 * @return string
	 */
	public function get_events_type_nav() {
		return $this->_plugin->GetGlobalSetting( 'events-nav-type', 'infinite-scroll' );
	}

	/**
	 * Sets Events Navigation Type.
	 *
	 * Sets type of navigation for events log viewer.
	 *
	 * @since 3.3.1.1
	 *
	 * @param string $nav_type - Navigation type.
	 * @return string
	 */
	public function set_events_type_nav( $nav_type ) {
		$this->_plugin->SetGlobalSetting( 'events-nav-type', $nav_type );
	}

	/**
	 * Query WSAL Options from DB.
	 *
	 * @return array - WSAL Options array.
	 */
	public function get_wsal_options() {
		// Get options transient.
		$wsal_options = get_transient( 'wsal_options' );

		// If options transient is not set then query and set options.
		if ( false === $wsal_options ) {
			// Get raw options from DB.
			$raw_options = $this->query_wsal_options();

			if ( ! empty( $raw_options ) && is_array( $raw_options ) ) {
				foreach ( $raw_options as $option ) {
					if ( ! empty( $option->option_value ) ) {
						$wsal_options[] = $option;
					}
				}
			}

			// Store the results in a transient.
			set_transient( 'wsal_options', $wsal_options, DAY_IN_SECONDS );
		}

		return $wsal_options;
	}

	/**
	 * Query WSAL Options from DB.
	 *
	 * @return array - Array of options.
	 */
	public function query_wsal_options() {
		// Query WSAL options.
		global $wpdb;

		// Set table name.
		$options_table = $wpdb->prefix . 'wsal_options';

		// Query the options.
		return $wpdb->get_results( "SELECT * FROM $options_table" ); // phpcs:ignore
	}

	/**
	 * Check if IP is in range for IPv4.
	 *
	 * This function takes 2 arguments, an IP address and a "range" in several different formats.
	 *
	 * Network ranges can be specified as:
	 * 1. Wildcard format:     1.2.3.*
	 * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
	 * 3. Start-End IP format: 1.2.3.0-1.2.3.255
	 *
	 * The function will return true if the supplied IP is within the range.
	 * Note little validation is done on the range inputs - it expects you to
	 * use one of the above 3 formats.
	 *
	 * @link https://github.com/cloudflarearchive/Cloudflare-Tools/blob/master/cloudflare/ip_in_range.php#L55
	 *
	 * @param string $ip    - IP address.
	 * @param string $range - Range of IP address.
	 * @return boolean
	 */
	public function check_ipv4_in_range( $ip, $range ) {
		if ( strpos( $range, '/' ) !== false ) {
			// $range is in IP/NETMASK format.
			list($range, $netmask) = explode( '/', $range, 2 );

			if ( strpos( $netmask, '.' ) !== false ) {
				// $netmask is a 255.255.0.0 format.
				$netmask     = str_replace( '*', '0', $netmask );
				$netmask_dec = ip2long( $netmask );
				return ( ( ip2long( $ip ) & $netmask_dec ) === ( ip2long( $range ) & $netmask_dec ) );
			} else {
				// $netmask is a CIDR size block
				// fix the range argument.
				$x       = explode( '.', $range );
				$x_count = count( $x );

				while ( $x_count < 4 ) {
					$x[]     = '0';
					$x_count = count( $x );
				}

				list($a,$b,$c,$d) = $x;
				$range            = sprintf( '%u.%u.%u.%u', empty( $a ) ? '0' : $a, empty( $b ) ? '0' : $b, empty( $c ) ? '0' : $c, empty( $d ) ? '0' : $d );
				$range_dec        = ip2long( $range );
				$ip_dec           = ip2long( $ip );

				// Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
				// $netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));
				// Strategy 2 - Use math to create it.
				$wildcard_dec = pow( 2, ( 32 - $netmask ) ) - 1;
				$netmask_dec  = ~ $wildcard_dec;

				return ( ( $ip_dec & $netmask_dec ) === ( $range_dec & $netmask_dec ) );
			}
		} else {
			// Range might be 255.255.*.* or 1.2.3.0-1.2.3.255.
			if ( strpos( $range, '*' ) !== false ) { // a.b.*.* format
				// Just convert to A-B format by setting * to 0 for A and 255 for B.
				$lower = str_replace( '*', '0', $range );
				$upper = str_replace( '*', '255', $range );
				$range = "$lower-$upper";
			}

			// A-B format.
			if ( strpos( $range, '-' ) !== false ) {
				list($lower, $upper) = explode( '-', $range, 2 );
				$lower_dec           = (float) sprintf( '%u', ip2long( $lower ) );
				$upper_dec           = (float) sprintf( '%u', ip2long( $upper ) );
				$ip_dec              = (float) sprintf( '%u', ip2long( $ip ) );
				return ( ( $ip_dec >= $lower_dec ) && ( $ip_dec <= $upper_dec ) );
			}

			return false;
		}
	}

	/**
	 * Return the range of IP address from 127.0.0.0-24 to 127.0.0.0-127.0.0.24 format.
	 *
	 * @param string $range - Range of IP address.
	 * @return object
	 */
	public function get_ipv4_by_range( $range ) {
		list($lower_ip, $upper_ip) = explode( '-', $range, 2 );

		$lower_arr = explode( '.', $lower_ip );
		$count     = count( $lower_arr );
		unset( $lower_arr[ $count - 1 ] );
		$upper_ip = implode( '.', $lower_arr ) . '.' . $upper_ip;

		return (object) array(
			'lower' => $lower_ip,
			'upper' => $upper_ip,
		);
	}

	/**
	 * Returns site server directories.
	 *
	 * @param string $context - Context of the directories.
	 * @return array
	 */
	public function get_server_directories( $context = '' ) {
		$wp_directories = array();

		// Get WP uploads directory.
		$wp_uploads  = wp_upload_dir();
		$uploads_dir = $wp_uploads['basedir'];

		if ( 'display' === $context ) {
			$wp_directories = array(
				'root'           => __( 'Root directory of WordPress (excluding sub directories)', 'wp-security-audit-log' ),
				'wp-admin'       => __( 'WP Admin directory (/wp-admin/)', 'wp-security-audit-log' ),
				WPINC            => __( 'WP Includes directory (/wp-includes/)', 'wp-security-audit-log' ),
				WP_CONTENT_DIR   => __( '/wp-content/ directory (excluding plugins, themes & uploads directories)', 'wp-security-audit-log' ),
				get_theme_root() => __( 'Themes directory (/wp-content/themes/)', 'wp-security-audit-log' ),
				WP_PLUGIN_DIR    => __( 'Plugins directory (/wp-content/plugins/)', 'wp-security-audit-log' ),
				$uploads_dir     => __( 'Uploads directory (/wp-content/uploads/)', 'wp-security-audit-log' ),
			);

			if ( is_multisite() ) {
				// Upload directories of subsites.
				$wp_directories[ $uploads_dir . '/sites' ] = __( 'Uploads directory of all sub sites on this network (/wp-content/sites/*)', 'wp-security-audit-log' );
			}
		} else {
			// Server directories.
			$wp_directories = array(
				'',               // Root directory.
				'wp-admin',       // WordPress Admin.
				WPINC,            // wp-includes.
				WP_CONTENT_DIR,   // wp-content.
				get_theme_root(), // Themes.
				WP_PLUGIN_DIR,    // Plugins.
				$uploads_dir,     // Uploads.
			);
		}

		// Prepare directories path.
		foreach ( $wp_directories as $index => $server_dir ) {
			if ( 'display' === $context && false !== strpos( $index, ABSPATH ) ) {
				unset( $wp_directories[ $index ] );
				$index = untrailingslashit( $index );
				$index = $this->get_server_directory( $index );
			} else {
				$server_dir = untrailingslashit( $server_dir );
				$server_dir = $this->get_server_directory( $server_dir );
			}

			$wp_directories[ $index ] = $server_dir;
		}

		return $wp_directories;
	}

	/**
	 * Returns a WP directory without ABSPATH.
	 *
	 * @param string $directory - Directory.
	 * @return string
	 */
	public function get_server_directory( $directory ) {
		return preg_replace( '/^' . preg_quote( ABSPATH, '/' ) . '/', '', $directory );
	}

	/**
	 * Check the current page screen id against current screen id of WordPress.
	 *
	 * @param string $page - Page screen id.
	 * @return boolean
	 */
	public function is_current_page( $page ) {
		if ( ! $this->current_screen ) {
			$this->current_screen = get_current_screen();
		}

		if ( isset( $this->current_screen->id ) ) {
			return $page === $this->current_screen->id;
		}

		return false;
	}

	/**
	 * Get WSAL's frontend events option.
	 *
	 * @return array
	 */
	public static function get_frontend_events() {
		// Option defaults.
		$default = array(
			'register'    => false,
			'login'       => false,
			'system'      => false,
			'woocommerce' => WpSecurityAuditLog::is_woocommerce_active(),
		);

		// Get the option.
		$value     = \WSAL\Helpers\Options::get_option_value_ignore_prefix( self::FRONT_END_EVENTS_OPTION_NAME, $default );

		// Check for WooCommerce in case it is not stored.
		$value['woocommerce'] = ! isset( $value['woocommerce'] ) ? WpSecurityAuditLog::is_woocommerce_active() : $value['woocommerce'];
		return $value;
	}

	/**
	 * Set WSAL's frontend events option.
	 *
	 * @param array $value - Option values.
	 * @return bool
	 */
	public function set_frontend_events( $value = array() ) {
		return \WSAL\Helpers\Options::set_option_value_ignore_prefix( self::FRONT_END_EVENTS_OPTION_NAME, $value);
	}

	/**
	 * Stores the ID of user who restricted the plugin settings access to "only me".
	 *
	 * @param int $user_id
	 * @since 4.1.3
	 */
	public function set_only_me_user_id( $user_id ) {
		$this->_plugin->SetGlobalSetting( 'only-me-user-id', $user_id );
	}

	/**
	 * Stores the ID of user who restricted the plugin settings access to "only me".
	 *
	 * @return int
	 * @since 4.1.3
	 */
	public function get_only_me_user_id() {
		return $this->_plugin->GetGlobalSetting( 'only-me-user-id' );
	}

    /**
     * Save admin blocking plugin support enabled.
     *
     * @param bool $enabled
     */
    public function set_admin_blocking_plugin_support( $enabled ) {
        $this->_plugin->SetGlobalBooleanSetting( 'admin-blocking-plugins-support', $enabled );
    }

	/**
	 * Check if admin blocking plugin support is enabled.
	 *
	 * Note: this is purely for retrieving the option value. It is actually used in conjunction with
	 * stealth mode setting and some other exceptions.
	 *
	 * @see WpSecurityAuditLog::is_admin_blocking_plugins_support_enabled()
	 * @return bool
	 */
	public function get_admin_blocking_plugin_support() {
    	return $this->_plugin->GetGlobalBooleanSetting( 'admin-blocking-plugins-support', false );
	}

}
