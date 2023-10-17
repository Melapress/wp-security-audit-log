<?php
/**
 * Class: WSAL Settings.
 *
 * WSAL settings class.
 *
 * @package wsal
 */

use WSAL\Helpers\WP_Helper;
use WSAL\Controllers\Connection;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is the actual controller of the Settings Page.
 *
 * @package wsal
 */
class WSAL_Settings {
	/**
	 * Instance of the main plugin.
	 *
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	public const ERROR_CODE_INVALID_IP = 901;

	/**
	 * List of Site Admins.
	 *
	 * @var array
	 */
	private $site_admins = array();

	/**
	 * Pruning Date.
	 *
	 * @var string
	 */
	protected $pruning = 0;

	/**
	 * IDs of disabled alerts.
	 *
	 * @var array
	 */
	protected $disabled = null;

	/**
	 * Allowed Plugin Viewers.
	 *
	 * @var array
	 */
	protected $viewers = null;

	/**
	 * Alerts per page.
	 *
	 * @var int
	 */
	protected $per_page = null;

	/**
	 * Custom user meta fields excluded from monitoring.
	 *
	 * @var array
	 */
	protected $excluded_user_meta = array();

	/**
	 * Custom Post Types excluded from monitoring.
	 *
	 * @var array
	 */
	protected $post_types = array();

	/**
	 * IP excluded from monitoring.
	 *
	 * @var array
	 */
	protected $excluded_ip = array();

	/**
	 * Alerts enabled in Geek mode.
	 *
	 * @var int[]
	 */
	public $geek_alerts = array( 1004, 1005, 1006, 1007, 2023, 2024, 2053, 2054, 2055, 2062, 2100, 2111, 2112, 2124, 2125, 2131, 2132, 2094, 2095, 2043, 2071, 2082, 2083, 2085, 2089, 4014, 4015, 4016, 5019, 5025, 6001, 6002, 6008, 6010, 6011, 6012, 6013, 6014, 6015, 6016, 6017, 6018, 6024, 6025 );

	/**
	 * Alerts always disabled by default - in basic mode and also in geek mode.
	 *
	 * @var int[]
	 *
	 * @since 4.2.0
	 */
	public $always_disabled_alerts = array( 5010, 5011, 5012, 5013, 5014, 5015, 5016, 5017, 5018, 5022, 5023, 5024 );

	/**
	 * Alerts disabled by default - duplication of the above for faster access via static call.
	 *
	 * @var int[]
	 *
	 * @since 4.4.2.1
	 */
	private static $default_always_disabled_alerts = array( 5010, 5011, 5012, 5013, 5014, 5015, 5016, 5017, 5018, 5022, 5023, 5024 );

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
		$this->plugin = $plugin;

		add_action( 'deactivated_plugin', array( $this, 'reset_stealth_mode' ), 10, 1 );
	}

	/**
	 * Enable Basic Mode.
	 */
	public function set_basic_mode() {
		// Disable alerts of geek mode and alerts to be always disabled.
		Settings_Helper::set_disabled_alerts( array_merge( $this->geek_alerts, $this->always_disabled_alerts ) );
	}

	/**
	 * Enable Geek Mode.
	 */
	public function set_geek_mode() {
		Settings_Helper::set_disabled_alerts( $this->always_disabled_alerts ); // Disable alerts to be always disabled.
	}

	/**
	 * Check whether admin bar notifications are enabled or not.
	 *
	 * @since 3.2.4
	 *
	 * @return bool
	 */
	public function is_admin_bar_notif() {
		return ! \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'disable-admin-bar-notif', true );
	}

	/**
	 * Check admin bar notification updates refresh option.
	 *
	 * @since 3.3.1
	 *
	 * @return string
	 */
	public function get_admin_bar_notif_updates() {
		return WSAL\Helpers\Settings_Helper::get_option_value( 'admin-bar-notif-updates', 'page-refresh' );
	}

	/**
	 * Maximum number of alerts to show in dashboard widget.
	 *
	 * @return int
	 */
	public function get_dashboard_widget_max_alerts() {
		return 5;
	}

	/**
	 * The maximum number of alerts allowable.
	 *
	 * @return int
	 */
	public function get_max_allowed_alerts() {
		return 5000;
	}

	/**
	 * The default pruning date.
	 *
	 * @return string
	 */
	public function get_default_pruning_date() {
		return '6 months';
	}

	/**
	 * The current pruning date.
	 *
	 * @return string
	 */
	public function get_pruning_date() {
		if ( ! $this->pruning ) {
			$this->pruning = WSAL\Helpers\Settings_Helper::get_option_value( 'pruning-date' );
			if ( is_null( $this->pruning ) ) {
				$this->pruning = $this->get_default_pruning_date();
			} elseif ( ! strtotime( $this->pruning ) ) {
				$this->pruning = $this->get_default_pruning_date();
			}
		}

		return $this->pruning;
	}

	/**
	 * Return current pruning unit.
	 *
	 * @return string
	 */
	public function get_pruning_unit() {
		return WSAL\Helpers\Settings_Helper::get_option_value( 'pruning-unit', 'months' );
	}

	/**
	 * Maximum number of alerts to keep.
	 *
	 * @return int
	 */
	public function get_pruning_limit() {
		$val = (int) WSAL\Helpers\Settings_Helper::get_option_value( 'pruning-limit' );

		return $val ? $val : $this->get_max_allowed_alerts();
	}

	/**
	 * Set pruning alerts limit.
	 *
	 * @param int $newvalue - The new maximum number of alerts.
	 */
	public function set_pruning_limit( $newvalue ) {
		\WSAL\Helpers\Settings_Helper::set_option_value( 'pruning-limit', max( (int) $newvalue, 1 ) );
	}

	/**
	 * Sets the plugin setting that enabled data pruning limit.
	 *
	 * @param bool $enabled If true, the limit is enabled.
	 */
	public function set_pruning_limit_enabled( $enabled ) {
		\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'pruning-limit-e', $enabled );
	}

	/**
	 * Method: Set Login Page Notification.
	 *
	 * @param bool $enable - Enable/Disable.
	 */
	public function set_login_page_notification( $enable ) {
		// Only trigger an event if an actual changes is made.
		$old_setting = \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'login_page_notification', false );
		$enable      = Settings_Helper::string_to_bool( $enable );
		if ( $old_setting !== $enable ) {
			$event_id   = 6046;
			$alert_data = array(
				'EventType' => ( $enable ) ? 'enabled' : 'disabled',
			);
			Alert_Manager::trigger_event( $event_id, $alert_data );
		}
		\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'login_page_notification', $enable );
	}

	/**
	 * Method: Check if Login Page Notification is set.
	 *
	 * @return bool - True if set, false if not.
	 */
	public function is_login_page_notification() {
		return \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'login_page_notification', false );
	}

	/**
	 * Method: Set Login Page Notification Text.
	 *
	 * @param string $text - Login Page Notification Text.
	 */
	public function set_login_page_notification_text( $text ) {
		$text        = wp_kses( $text, WpSecurityAuditLog::get_allowed_html_tags() );
		$old_setting = WSAL\Helpers\Settings_Helper::get_option_value( 'login_page_notification_text' );
		if ( ! empty( $old_setting ) && ! empty( $text ) && ! is_null( $old_setting ) && $old_setting !== $text ) {
			Alert_Manager::trigger_event( 6047 );
		}
		\WSAL\Helpers\Settings_Helper::set_option_value( 'login_page_notification_text', $text );
	}

	/**
	 * Method: Return Login Page Notification Text.
	 *
	 * @return string|bool - Text if set, false if not.
	 */
	public function get_login_page_notification_text() {
		return WSAL\Helpers\Settings_Helper::get_option_value( 'login_page_notification_text', false );
	}

	/**
	 * Method: Set Disabled Alerts.
	 *
	 * @param array $types IDs alerts to disable.
	 */
	public function set_disabled_alerts( $types ) {
		$this->disabled = array_unique( array_map( 'intval', $types ) );
		\WSAL\Helpers\Settings_Helper::set_option_value( 'disabled-alerts', implode( ',', $this->disabled ) );
	}

	/**
	 * Enables or disables plugin's incognito mode.
	 *
	 * @param bool $enabled If true, the incognito mode gets enabled.
	 */
	public function set_incognito( $enabled ) {
		$old_value = WSAL\Helpers\Settings_Helper::get_option_value( 'hide-plugin' );
		$old_value = ( 'yes' === $old_value );
		if ( $old_value !== $enabled ) {
			$alert_data = array(
				'EventType' => ( $enabled ) ? 'enabled' : 'disabled',
			);
			Alert_Manager::trigger_event( 6051, $alert_data );
		}

		\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'hide-plugin', $enabled );
	}

	/**
	 * Sets the plugin setting that allows data deletion on plugin uninstall.
	 *
	 * @param mixed $enabled If true, data deletion on plugin uninstall gets enabled.
	 */
	public function set_delete_data( $enabled ) {
		\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'delete-data', $enabled );
	}

	/**
	 * Set Plugin Viewers.
	 *
	 * @param array $users_or_roles – Users/Roles.
	 */
	public function set_allowed_plugin_viewers( $users_or_roles ) {
		$old_value = WSAL\Helpers\Settings_Helper::get_option_value( 'plugin-viewers', '' );
		$changes   = \WSAL\Helpers\Settings_Helper::determine_added_and_removed_items( $old_value, implode( ',', $users_or_roles ) );

		if ( ! empty( $changes['added'] ) ) {
			foreach ( $changes['added'] as $user ) {
				Alert_Manager::trigger_event(
					6050,
					array(
						'user'           => $user,
						'previous_users' => ( empty( $old_value ) ) ? \WSAL\Helpers\Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'      => 'added',
					)
				);
			}
		}

		if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
			foreach ( $changes['removed'] as $user ) {
				if ( ! empty( $user ) ) {
					Alert_Manager::trigger_event(
						6050,
						array(
							'user'           => $user,
							'previous_users' => empty( $old_value ) ? \WSAL\Helpers\Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'      => 'removed',
						)
					);
				}
			}
		}

		$this->viewers = $users_or_roles;
		\WSAL\Helpers\Settings_Helper::set_option_value( 'plugin-viewers', implode( ',', $this->viewers ), true );
	}

	/**
	 * Get Plugin Viewers.
	 *
	 * @return array List of users allowed to view the plugin.
	 */
	public function get_allowed_plugin_viewers() {
		if ( is_null( $this->viewers ) ) {
			$this->viewers = array_unique( array_filter( explode( ',', WSAL\Helpers\Settings_Helper::get_option_value( 'plugin-viewers', '' ) ) ) );
		}

		return $this->viewers;
	}

	/**
	 * Set restrict plugin setting.
	 *
	 * @param string $setting – Setting.
	 *
	 * @since 3.2.3
	 */
	public function set_restrict_plugin_setting( $setting ) {
		$old_value = WSAL\Helpers\Settings_Helper::get_option_value( 'restrict-plugin-settings', 'only_admins' );

		if ( ! is_null( $old_value ) && $old_value !== $setting ) {
			$alert_data = array(
				'new_setting'      => ucfirst( str_replace( '_', ' ', $setting ) ),
				'previous_setting' => ucfirst( str_replace( '_', ' ', $old_value ) ),
			);
			Alert_Manager::trigger_event( 6049, $alert_data );
		}

		\WSAL\Helpers\Settings_Helper::set_option_value( 'restrict-plugin-settings', $setting, true );
	}

	/**
	 * Get restrict plugin setting.
	 *
	 * @since 3.2.3
	 */
	public function get_restrict_plugin_setting() {
		return WSAL\Helpers\Settings_Helper::get_option_value( 'restrict-plugin-settings', 'only_admins' );
	}

	/**
	 * Get restriction setting for viewing the log viewer in multisite context.
	 *
	 * @since 4.1.3
	 */
	public function get_restrict_log_viewer() {
		return WSAL\Helpers\Settings_Helper::get_option_value( 'restrict-log-viewer', 'only_admins' );
	}

	/**
	 * Set restriction setting for viewing the log viewer in multisite context.
	 *
	 * @param string $setting – Setting.
	 *
	 * @since 4.1.3
	 */
	public function set_restrict_log_viewer( $setting ) {
		\WSAL\Helpers\Settings_Helper::set_option_value( 'restrict-log-viewer', $setting, true );
	}

	/**
	 * Sets the number of items per page for the audit log viewer.
	 *
	 * @param int $newvalue Number of items per page for the audit log viewer.
	 */
	public function set_views_per_page( $newvalue ) {
		$this->per_page = max( intval( $newvalue ), 1 );
		\WSAL\Helpers\Settings_Helper::set_option_value( 'items-per-page', $this->per_page );
	}

	/**
	 * Gets the number of items per page for the audit log viewer.
	 *
	 * @return int Number of items per page for the audit log viewer.
	 */
	public function get_views_per_page() {
		if ( is_null( $this->per_page ) ) {
			$this->per_page = (int) WSAL\Helpers\Settings_Helper::get_option_value( 'items-per-page', 10 );
		}

		return $this->per_page;
	}

	/**
	 * Get list of superadmin usernames.
	 *
	 * @return array
	 */
	protected function get_super_admins() {
		return WP_Helper::is_multisite() ? get_super_admins() : array();
	}

	/**
	 * List of admin usernames.
	 *
	 * @return string[]
	 */
	protected function get_admins() {
		if ( WP_Helper::is_multisite() ) {
			if ( empty( $this->site_admins ) ) {
				/*
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
				$this->site_admins = $wpdb->get_col( $sql ); // phpcs:ignore
			}
		} elseif ( empty( $this->site_admins ) ) {
				$query = 'role=administrator&fields[]=user_login';
			foreach ( get_users( $query ) as $user ) {
				$this->site_admins[] = $user->user_login;
			}
		}

		return $this->site_admins;
	}

	/**
	 * Checks if given user is a superadmin.
	 *
	 * @param string $username Username.
	 *
	 * @return bool True if the user is a superadmin.
	 */
	public function is_login_super_admin( $username ) {
		$user_id = username_exists( $username );

		return function_exists( 'is_super_admin' ) && is_super_admin( $user_id );
	}

	/**
	 * Sets the setting that decides if IP address should be determined based on proxy.
	 *
	 * @param bool $enabled True if IP address should be determined based on proxy.
	 */
	public function set_main_ip_from_proxy( $enabled ) {
		$old_value = \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'use-proxy-ip' );
		$enabled   = Settings_Helper::string_to_bool( $enabled );
		if ( $old_value !== $enabled ) {
			$alert_data = array(
				'EventType' => ( $enabled ) ? 'enabled' : 'disabled',
			);
			Alert_Manager::trigger_event( 6048, $alert_data );
		}
		\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'use-proxy-ip', $enabled );
	}

	/**
	 * Checks if internal IP filtering is enabled.
	 *
	 * @return bool
	 */
	public function is_internal_ips_filtered() {
		return \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'filter-internal-ip', false );
	}

	/**
	 * Enables or disables the internal IP filtering.
	 *
	 * @param bool $enabled True if internal IP filtering should be enabled.
	 */
	public function set_internal_ips_filtering( $enabled ) {
		\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'filter-internal-ip', $enabled );
	}

	/**
	 * Set Custom Post Types excluded from monitoring.
	 *
	 * @param array $post_types - Array of post types to exclude.
	 *
	 * @since 2.6.7
	 */
	public function set_excluded_post_types( $post_types ) {
		$old_value = WSAL\Helpers\Settings_Helper::get_option_value( 'custom-post-types', array() );
		$changes   = \WSAL\Helpers\Settings_Helper::determine_added_and_removed_items( $old_value, implode( ',', $post_types ) );

		if ( ! empty( $changes['added'] ) ) {
			foreach ( $changes['added'] as $post_type ) {
				Alert_Manager::trigger_event(
					6056,
					array(
						'post_type'      => $post_type,
						'previous_types' => ( empty( $old_value ) ) ? \WSAL\Helpers\Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'      => 'added',
					)
				);
			}
		}

		if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
			foreach ( $changes['removed'] as $post_type ) {
				Alert_Manager::trigger_event(
					6056,
					array(
						'post_type'      => $post_type,
						'previous_types' => empty( $old_value ) ? \WSAL\Helpers\Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'      => 'removed',
					)
				);
			}
		}

		$this->post_types = $post_types;
		\WSAL\Helpers\Settings_Helper::set_option_value( 'custom-post-types', esc_html( implode( ',', $this->post_types ) ) );
	}

	/**
	 * IP excluded from monitoring.
	 *
	 * @param array $ip IP addresses to exclude from monitoring.
	 */
	public function set_excluded_monitoring_ip( $ip ) {
		$old_value = WSAL\Helpers\Settings_Helper::get_option_value( 'excluded-ip', array() );
		$changes   = \WSAL\Helpers\Settings_Helper::determine_added_and_removed_items( $old_value, implode( ',', $ip ) );

		if ( ! empty( $changes['added'] ) ) {
			foreach ( $changes['added'] as $user ) {
				Alert_Manager::trigger_event(
					6055,
					array(
						'ip'           => $user,
						'previous_ips' => ( empty( $old_value ) ) ? \WSAL\Helpers\Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'    => 'added',
					)
				);
			}
		}
		if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
			foreach ( $changes['removed'] as $user ) {
				Alert_Manager::trigger_event(
					6055,
					array(
						'ip'           => $user,
						'previous_ips' => empty( $old_value ) ? \WSAL\Helpers\Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'    => 'removed',
					)
				);
			}
		}

		$this->excluded_ip = $ip;
		\WSAL\Helpers\Settings_Helper::set_excluded_monitoring_ips( $this->excluded_ip );
	}

	/**
	 * Determines datetime format to be displayed in any UI in the plugin (logs in administration, emails, reports,
	 * notifications etc.).
	 *
	 * Note: Format returned by this function is not compatible with JavaScript date and time picker widgets. Use
	 * functions GetTimeFormat and GetDateFormat for those.
	 *
	 * @param bool $line_break             - True if line break otherwise false.
	 * @param bool $use_nb_space_for_am_pm - True if non-breakable space should be placed before the AM/PM chars.
	 *
	 * @return string
	 */
	public function get_datetime_format( $line_break = true, $use_nb_space_for_am_pm = true ) {
		$result = Settings_Helper::get_date_format();

		$result .= $line_break ? '<\b\r>' : ' ';

		$time_format    = Settings_Helper::get_time_format();
		$has_am_pm      = false;
		$am_pm_fraction = false;
		$am_pm_pattern  = '/(?i)(\s+A)/';
		if ( preg_match( $am_pm_pattern, $time_format, $am_pm_matches ) ) {
			$has_am_pm      = true;
			$am_pm_fraction = $am_pm_matches[0];
			$time_format    = preg_replace( $am_pm_pattern, '', $time_format );
		}

		// Check if the time format does not have seconds.
		if ( false === stripos( $time_format, 's' ) ) {
			$time_format .= ':s'; // Add seconds to time format.
		}

		if ( $this->get_show_milliseconds() ) {
			$time_format .= '.$$$'; // Add milliseconds to time format.
		}

		if ( $has_am_pm ) {
			$time_format .= preg_replace( '/\s/', $use_nb_space_for_am_pm ? '&\n\b\s\p;' : ' ', $am_pm_fraction );
		}

		$result .= $time_format;

		return $result;
	}

	/**
	 * Date format based on WordPress date settings. It can be optionally sanitized to get format compatible with
	 * JavaScript date and time picker widgets.
	 *
	 * Note: This function must not be used to display actual date and time values anywhere. For that use function GetDateTimeFormat.
	 *
	 * @param bool $sanitized If true, the format is sanitized for use with JavaScript date and time picker widgets.
	 *
	 * @return string
	 */
	public function get_date_format( $sanitized = false ) {
		if ( $sanitized ) {
			return 'Y-m-d';
		}

		return get_option( 'date_format' );
	}

	/**
	 * Time format based on WordPress date settings. It can be optionally sanitized to get format compatible with
	 * JavaScript date and time picker widgets.
	 *
	 * Note: This function must not be used to display actual date and time values anywhere. For that use function GetDateTimeFormat.
	 *
	 * @param bool $sanitize If true, the format is sanitized for use with JavaScript date and time picker widgets.
	 *
	 * @return string
	 */
	public function get_time_format( $sanitize = false ) {
		$result = get_option( 'time_format' );
		if ( $sanitize ) {
			$search  = array( 'a', 'A', 'T', ' ' );
			$replace = array( '', '', '', '' );
			$result  = str_replace( $search, $replace, $result );
		}

		return $result;
	}

	/**
	 * Alerts Timestamp.
	 *
	 * Server's timezone or WordPress' timezone.
	 */
	public function get_timezone() {
		return WSAL\Helpers\Settings_Helper::get_option_value( 'timezone', 'wp' );
	}

	/**
	 * Updates the timezone handling setting.
	 *
	 * @param string $newvalue New setting value.
	 */
	public function set_timezone( $newvalue ) {
		\WSAL\Helpers\Settings_Helper::set_option_value( 'timezone', $newvalue );
	}

	/**
	 * Helper method to get the stored setting to determine if milliseconds
	 * appear in the admin list view. This should always be a bool.
	 *
	 * @method get_show_milliseconds
	 *
	 * @since  3.5.2
	 *
	 * @return bool
	 */
	public function get_show_milliseconds() {
		return \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'show_milliseconds', true );
	}

	/**
	 * Stores the option that dicates if milliseconds show in admin list view
	 * for event times. This is always a bool. When it's not a bool it's set
	 * to `true` to match default.
	 *
	 * @method set_show_milliseconds
	 *
	 * @since  3.5.2
	 *
	 * @param mixed $newvalue ideally always bool. If not bool then it's cast to true.
	 */
	public function set_show_milliseconds( $newvalue ) {
		\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'show_milliseconds', $newvalue );
	}

	/**
	 * Get type of username to display.
	 */
	public function get_type_username() {
		return WSAL\Helpers\Settings_Helper::get_option_value( 'type_username', 'display_name' );
	}

	/**
	 * Set type of username to display.
	 *
	 * @param string $newvalue - New value variable.
	 *
	 * @since 2.6.5
	 */
	public function set_type_username( $newvalue ) {
		\WSAL\Helpers\Settings_Helper::set_option_value( 'type_username', $newvalue );
	}

	/**
	 * Returns audit log columns.
	 *
	 * @return array
	 */
	public function get_columns() {
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
		);

		if ( WP_Helper::is_multisite() ) {
			$columns = array_slice( $columns, 0, 6, true ) + array( 'site' => '1' ) + array_slice( $columns, 6, null, true );
		}

		$selected = WSAL\Helpers\Settings_Helper::get_option_value( 'columns', array() );

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

			if ( WP_Helper::is_multisite() ) {
				$columns = array_slice( $columns, 0, 6, true ) + array( 'site' => '0' ) + array_slice( $columns, 6, null, true );
			}

			$selected = (array) json_decode( $selected );
			$columns  = array_merge( $columns, $selected );
		}

		return $columns;
	}

	/**
	 * Sets the list of columns selected for display in the audit log viewer.
	 *
	 * @param array $columns List of columns selected for display in the audit log viewer.
	 */
	public function set_columns( $columns ) {
		\WSAL\Helpers\Settings_Helper::set_option_value( 'columns', json_encode( $columns ) );
	}

	/**
	 * Sets the log limit for failed login attempts.
	 *
	 * @param int $value - Failed login limit.
	 *
	 * @since  2.6.3
	 */
	public function set_failed_login_limit( $value ) {
		if ( ! empty( $value ) ) {
			\WSAL\Helpers\Settings_Helper::set_option_value( 'log-failed-login-limit', abs( $value ) );
		} else {
			\WSAL\Helpers\Settings_Helper::set_option_value( 'log-failed-login-limit', - 1 );
		}
	}

	/**
	 * Get the log limit for failed login attempts.
	 *
	 * @return int
	 *
	 * @since  2.6.3
	 */
	public function get_failed_login_limit() {
		return intval( WSAL\Helpers\Settings_Helper::get_option_value( 'log-failed-login-limit', 10 ) );
	}

	/**
	 * Sets the log limit for failed login attempts for visitor.
	 *
	 * @param int $value - Failed login limit.
	 *
	 * @since  2.6.3
	 */
	public function set_visitor_failed_login_limit( $value ) {
		if ( ! empty( $value ) ) {
			\WSAL\Helpers\Settings_Helper::set_option_value( 'log-visitor-failed-login-limit', abs( $value ) );
		} else {
			\WSAL\Helpers\Settings_Helper::set_option_value( 'log-visitor-failed-login-limit', - 1 );
		}
	}

	/**
	 * Get the log limit for failed login attempts for visitor.
	 *
	 * @return int
	 *
	 * @since  2.6.3
	 */
	public function get_visitor_failed_login_limit() {
		return intval( WSAL\Helpers\Settings_Helper::get_option_value( 'log-visitor-failed-login-limit', 10 ) );
	}


	/**
	 * Method: Get Token Type.
	 *
	 * @param string $token - Token type.
	 * @param string $type - Type of the input to check.
	 *
	 * @return string
	 *
	 * @since 3.2.3
	 */
	public function get_token_type( $token, $type = false ) {
		// Get users.
		$users = array();
		foreach ( get_users( 'blog_id=0&fields[]=user_login' ) as $obj ) {
			$users[] = $obj->user_login;
		}

		// Check if the token matched users.
		if ( in_array( $token, $users, true ) ) {

			// That is shitty code, in order to keep backwards compatibility
			// that code is added.
			// Meaning - if that is AJAX call, and it comes from the roles and not users,
			// we have no business here - the token is valid and within users, but we are looking
			// for roles.
			if ( false !== $type && 'ExRole' === $type ) {

			} else {
				return 'user';
			}
		}

		if ( false !== $type && 'ExUser' === $type ) {
			// We are checking for a user, meaning that at this point we have not find one
			// bounce.

			return 'other';
		}

		// Get user roles.
		$roles = array_keys( get_editable_roles() );

		// Check if the token matched user roles.
		if ( in_array( $token, $roles, true ) ) {
			return 'role';
		}

		if ( false !== $type && 'ExRole' === $type ) {
			// We are checking for a role, meaning that at this point we have not find one
			// bounce.

			return 'other';
		}

		// Get custom post types.
		$post_types = get_post_types( array(), 'names', 'and' );
		// if we are running multisite and have networkwide cpt tracker get the
		// list from and merge to the post_types array.
		if ( WP_Helper::is_multisite() && class_exists( '\WSAL\Multisite\NetworkWide\CPTsTracker' ) ) {
			$network_cpts = \WSAL\Multisite\NetworkWide\CPTsTracker::get_network_data_list();
			foreach ( $network_cpts as $cpt ) {
				$post_types[ $cpt ] = $cpt;
			}
		}

		// Check if the token matched post types.
		if ( in_array( $token, $post_types, true ) ) {
			return 'cpts';
		}

		// Check if the token matches a URL.
		if ( ( false !== strpos( $token, home_url() ) ) && filter_var( $token, FILTER_VALIDATE_URL ) ) {
			return 'urls';
		}

		// Check for IP range.
		if ( false !== strpos( $token, '-' ) ) {
			$ip_range = \WSAL\Helpers\Settings_Helper::get_ipv4_by_range( $token );

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
	 * Set MainWP Child Stealth Mode.
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
			! \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'mwp-child-stealth-mode', false ) // MainWP Child Stealth Mode is not already active.
			&& WpSecurityAuditLog::is_mainwp_active() // And if MainWP Child plugin is installed & active.
		) {
			// Check if freemius state is anonymous.
			if ( ! wsal_freemius()->is_premium() && 'anonymous' === WSAL\Helpers\Settings_Helper::get_option_value( 'freemius_state', 'anonymous' ) ) {
				// Update Freemius state to skipped.
				\WSAL\Helpers\Settings_Helper::set_option_value( 'wsal_freemius_state', 'skipped', true );

				if ( ! WP_Helper::is_multisite() ) {
					wsal_freemius()->skip_connection(); // Opt out.
				} else {
					wsal_freemius()->skip_connection( null, true ); // Opt out for all websites.
				}

				// Connect account notice.
				if ( class_exists( 'FS_Admin_Notices' ) ) {
					FS_Admin_Notices::instance( 'wp-security-audit-log' )->remove_sticky( 'connect_account' );
				}
			}

			if ( ! wsal_freemius()->is_premium() ) {
				// Remove Freemius trial promotion notice.
				if ( class_exists( 'FS_Admin_Notices' ) ) {
					FS_Admin_Notices::instance( 'wp-security-audit-log' )->remove_sticky( 'trial_promotion' );
				}
			}

			$this->set_incognito( true ); // Incognito mode to hide WSAL on plugins page.
			$this->set_restrict_log_viewer( 'only_me' );
			$this->set_restrict_plugin_setting( 'only_me' );
			// Current user with fallback to default admin (in case this is triggered using WP CLI or something similar).
			$only_me_user_id = is_user_logged_in() ? get_current_user_id() : 1;
			$this->set_only_me_user_id( $only_me_user_id );
			\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'mwp-child-stealth-mode', true ); // Save stealth mode option.
		}
	}

	/**
	 * Deactivate MainWP Child Stealth Mode.
	 *
	 * @since 3.2.3.3
	 */
	public function deactivate_mainwp_child_stealth_mode() {
		$this->set_incognito( false ); // Disable incognito mode to hide WSAL on plugins page.
		$this->set_restrict_plugin_setting( 'only_admins' );
		$this->set_restrict_log_viewer( 'only_admins' );
		$this->set_admin_blocking_plugin_support( false );
		\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'mwp-child-stealth-mode', false ); // Disable stealth mode option.
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

		if ( \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'mwp-child-stealth-mode', false ) ) {
			$this->deactivate_mainwp_child_stealth_mode();
		}
	}

	/**
	 * Check and return if stealth mode is active.
	 *
	 * @return bool
	 */
	public function is_stealth_mode() {
		return \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'mwp-child-stealth-mode', false );
	}

	/**
	 * Method: Check if the blog is main blog.
	 *
	 * @since 3.2.4
	 *
	 * @return bool
	 */
	protected function is_main_blog() {
		return 1 === get_current_blog_id();
	}

	/**
	 * Query sites from WPDB.
	 *
	 * @since 3.3.0.1
	 *
	 * @param int|null $limit — Maximum number of sites to return (null = no limit).
	 *
	 * @return object — Object with keys: blog_id, blogname, domain
	 */
	public function get_sites( $limit = null ) {
		global $wpdb;

		$sql = 'SELECT blog_id, domain FROM ' . $wpdb->blogs;
		if ( ! is_null( $limit ) ) {
			$sql .= ' LIMIT ' . $limit;
		}
		$res = $wpdb->get_results($sql); // phpcs:ignore
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

		return (int) $wpdb->get_var($sql); // phpcs:ignore
	}

	/**
	 * Checks Infinite Scroll.
	 *
	 * Returns true if infinite scroll is enabled.
	 *
	 * @since 3.3.1.1
	 *
	 * @return bool
	 */
	public function is_infinite_scroll() {
		return 'infinite-scroll' === $this->get_events_type_nav();
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
		return WSAL\Helpers\Settings_Helper::get_option_value( 'events-nav-type', '' );
	}

	/**
	 * Query WSAL Options from DB.
	 *
	 * @return array - WSAL Options array.
	 */
	public function get_plugin_settings() {
		global $wpdb;

		return $wpdb->get_results("SELECT * FROM $wpdb->options WHERE option_name LIKE 'wsal_%'"); // phpcs:ignore
	}

	/**
	 * Stores the ID of user who restricted the plugin settings access to "only me".
	 *
	 * @param int $user_id User ID.
	 *
	 * @since 4.1.3
	 */
	public function set_only_me_user_id( $user_id ) {
		\WSAL\Helpers\Settings_Helper::set_option_value( 'only-me-user-id', $user_id, true );
	}

	/**
	 * Stores the ID of user who restricted the plugin settings access to "only me".
	 *
	 * @return int
	 *
	 * @since 4.1.3
	 */
	public function get_only_me_user_id() {
		return (int) WSAL\Helpers\Settings_Helper::get_option_value( 'only-me-user-id' );
	}

	/**
	 * Save admin blocking plugin support enabled.
	 *
	 * @param bool $enabled True, if admin blocking plugin support should be enabled.
	 */
	public function set_admin_blocking_plugin_support( $enabled ) {
		\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'admin-blocking-plugins-support', $enabled );
	}

	/**
	 * Check if admin blocking plugin support is enabled.
	 *
	 * Note: this is purely for retrieving the option value. It is actually used in conjunction with
	 * stealth mode setting and some other exceptions.
	 *
	 * @see WpSecurityAuditLog::is_admin_blocking_plugins_support_enabled()
	 *
	 * @return bool
	 */
	public function get_admin_blocking_plugin_support() {
		return \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'admin-blocking-plugins-support', false );
	}

	/**
	 * Retrieves the settings enforced by MainWP from local database.
	 *
	 * @return array Settings enforced by MainWP.
	 */
	public function get_mainwp_enforced_settings() {
		return WSAL\Helpers\Settings_Helper::get_option_value( 'mainwp_enforced_settings', array() );
	}

	/**
	 * Stores the settings enforced by MainWP in local database.
	 *
	 * @param array $settings Enforced settings.
	 */
	public function set_mainwp_enforced_settings( $settings ) {
		\WSAL\Helpers\Settings_Helper::set_option_value( 'mainwp_enforced_settings', $settings );
	}

	/**
	 * Deletes the settings enforced by MainWP from local database.
	 */
	public function delete_mainwp_enforced_settings() {
		\WSAL\Helpers\Settings_Helper::delete_option_value( 'mainwp_enforced_settings' );
	}


	public static function get_frontend_events() {
		return Settings_Helper::get_frontend_events();
	}
}
