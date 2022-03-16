<?php
/**
 * Class: WSAL Settings.
 *
 * WSAL settings class.
 *
 * @package wsal
 */

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
	protected $plugin;

	const ERROR_CODE_INVALID_IP = 901;

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
	 * Users excluded from monitoring.
	 *
	 * @var array
	 */
	protected $excluded_users = array();

	/**
	 * Roles excluded from monitoring.
	 *
	 * @var array
	 */
	protected $excluded_roles = array();

	/**
	 * Custom post meta fields excluded from monitoring.
	 *
	 * @var array
	 */
	protected $excluded_post_meta = array();

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
	 * @since 4.2.0
	 */
	public $always_disabled_alerts = array( 5010, 5011, 5012, 5013, 5014, 5015, 5016, 5017, 5018, 5022, 5023, 5024 );

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
		// some settings here may be called before the options helper is setup.
		if ( ! isset( $this->plugin->options_helper ) ) {
			$this->plugin->include_options_helper();
		}
		add_action( 'deactivated_plugin', array( $this, 'reset_stealth_mode' ), 10, 1 );
	}


	/**
	 * Enable Basic Mode.
	 */
	public function set_basic_mode() {
		// Disable alerts of geek mode and alerts to be always disabled.
		$this->set_disabled_alerts( array_merge( $this->geek_alerts, $this->always_disabled_alerts ) );
	}

	/**
	 * Enable Geek Mode.
	 */
	public function set_geek_mode() {
		$this->set_disabled_alerts( $this->always_disabled_alerts ); // Disable alerts to be always disabled.
	}


	/**
	 * Check whether dashboard widgets are enabled or not.
	 *
	 * @return boolean
	 */
	public function is_widgets_enabled() {
		return ! $this->plugin->get_global_boolean_setting( 'disable-widgets' );
	}

	/**
	 * Check whether dashboard widgets are enabled or not.
	 *
	 * @param boolean $newvalue - True if enabled.
	 */
	public function set_widgets_enabled( $newvalue ) {
		$this->plugin->set_global_boolean_setting( 'disable-widgets', ! $newvalue );
	}

	/**
	 * Check whether admin bar notifications are enabled or not.
	 *
	 * @since 3.2.4
	 *
	 * @return boolean
	 */
	public function is_admin_bar_notif() {
		return ! $this->plugin->get_global_boolean_setting( 'disable-admin-bar-notif', true );
	}

	/**
	 * Set admin bar notifications.
	 *
	 * @since 3.2.4
	 *
	 * @param boolean $newvalue - True if enabled.
	 */
	public function set_admin_bar_notif( $newvalue ) {
		$this->plugin->set_global_boolean_setting( 'disable-admin-bar-notif', ! $newvalue, true );
	}

	/**
	 * Check admin bar notification updates refresh option.
	 *
	 * @since 3.3.1
	 *
	 * @return string
	 */
	public function get_admin_bar_notif_updates() {
		return $this->plugin->get_global_setting( 'admin-bar-notif-updates', 'page-refresh' );
	}

	/**
	 * Set admin bar notifications.
	 *
	 * @since 3.3.1
	 *
	 * @param string $newvalue - New option value.
	 */
	public function set_admin_bar_notif_updates( $newvalue ) {
		$this->plugin->set_global_setting( 'admin-bar-notif-updates', $newvalue, true );
	}

	/**
	 * Check whether alerts in audit log view refresh automatically or not.
	 *
	 * @return boolean
	 */
	public function is_refresh_alerts_enabled() {
		return ! $this->plugin->get_global_setting( 'disable-refresh' );
	}

	/**
	 * Check whether alerts in audit log view refresh automatically or not.
	 *
	 * @param boolean $newvalue - True if enabled.
	 */
	public function set_refresh_alerts_enabled( $newvalue ) {
		$this->plugin->set_global_setting( 'disable-refresh', ! $newvalue );
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
			$this->pruning = $this->plugin->get_global_setting( 'pruning-date' );
			if ( ! strtotime( $this->pruning ) ) {
				$this->pruning = $this->get_default_pruning_date();
			}
		}
		return $this->pruning;
	}

	/**
	 * Set the new pruning date.
	 *
	 * @param string $newvalue - The new pruning date.
	 */
	public function set_pruning_date( $newvalue ) {
		if ( strtotime( $newvalue ) ) {
			$this->plugin->set_global_setting( 'pruning-date', $newvalue );
			$this->pruning = $newvalue;
		}
	}

	/**
	 * Return current pruning unit.
	 *
	 * @return string
	 */
	public function get_pruning_unit() {
		return $this->plugin->get_global_setting( 'pruning-unit', 'months' );
	}

	/**
	 * Set current pruning unit.
	 *
	 * @param string $newvalue – New value of pruning unit.
	 */
	public function set_pruning_unit( $newvalue ) {
		$this->plugin->set_global_setting( 'pruning-unit', $newvalue );
	}

	/**
	 * Maximum number of alerts to keep.
	 *
	 * @return integer
	 */
	public function get_pruning_limit() {
		$val = (int) $this->plugin->get_global_setting( 'pruning-limit' );
		return $val ? $val : $this->get_max_allowed_alerts();
	}

	/**
	 * Set pruning alerts limit.
	 *
	 * @param integer $newvalue - The new maximum number of alerts.
	 */
	public function set_pruning_limit( $newvalue ) {
		$this->plugin->set_global_setting( 'pruning-limit', max( (int) $newvalue, 1 ) );
	}

	/**
	 * Enables or disables time based retention period.
	 *
	 * @param bool $enabled If true, time based retention period is enabled.
	 */
	public function set_pruning_date_enabled( $enabled ) {

		$old_setting = $this->plugin->get_global_boolean_setting( 'pruning-date-e', false );
		$enable      = \WSAL\Helpers\Options::string_to_bool( $enabled );
		if ( $old_setting !== $enable ) {
			$this->pruning = $this->plugin->get_global_setting( 'pruning-date' ) . ' ' . $this->plugin->get_global_setting( 'pruning-unit', 'months' );
			$alert_data    = array(
				'new_setting'      => ( $enable ) ? 'Delete events older than ' . $this->pruning : 'Keep all data',
				'previous_setting' => ( $old_setting ) ? 'Delete events older than ' . $this->pruning : 'Keep all data',
			);
			$this->plugin->alerts->trigger_event( 6052, $alert_data );
		}

		$this->plugin->set_global_boolean_setting( 'pruning-date-e', $enabled );
	}

	/**
	 * Sets the plugin setting that enabled data pruning limit.
	 *
	 * @param bool $enabled If true, the limit is enabled.
	 */
	public function set_pruning_limit_enabled( $enabled ) {
		$this->plugin->set_global_boolean_setting( 'pruning-limit-e', $enabled );
	}

	/**
	 * Checks if the time based retention period is enabled.
	 *
	 * @return bool
	 */
	public function is_pruning_date_enabled() {
		return $this->plugin->get_global_boolean_setting( 'pruning-date-e' );
	}

	/**
	 * Checks if the data pruning limit is enabled.
	 *
	 * @return bool
	 */
	public function is_pruning_limit_enabled() {
		return $this->plugin->get_global_boolean_setting( 'pruning-limit-e' );
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
		// Only trigger an event if an actual changes is made.
		$old_setting = $this->plugin->get_global_boolean_setting( 'login_page_notification', false );
		$enable      = \WSAL\Helpers\Options::string_to_bool( $enable );
		if ( $old_setting !== $enable ) {
			$event_id   = 6046;
			$alert_data = array(
				'EventType' => ( $enable ) ? 'enabled' : 'disabled',
			);
			$this->plugin->alerts->trigger_event( $event_id, $alert_data );
		}
		$this->plugin->set_global_boolean_setting( 'login_page_notification', $enable );
	}

	/**
	 * Method: Check if Login Page Notification is set.
	 *
	 * @return bool - True if set, false if not.
	 */
	public function is_login_page_notification() {
		return $this->plugin->get_global_boolean_setting( 'login_page_notification', false );
	}

	/**
	 * Method: Set Login Page Notification Text.
	 *
	 * @param string $text - Login Page Notification Text.
	 */
	public function set_login_page_notification_text( $text ) {
		$text        = wp_kses( $text, $this->plugin->allowed_html_tags );
		$old_setting = $this->plugin->get_global_setting( 'login_page_notification_text' );
		if ( ! empty( $old_setting ) && ! empty( $text ) && ! is_null( $old_setting ) && $old_setting !== $text ) {
			$this->plugin->alerts->trigger_event( 6047 );
		}
		$this->plugin->set_global_setting( 'login_page_notification_text', $text );
	}

	/**
	 * Method: Return Login Page Notification Text.
	 *
	 * @return string|bool - Text if set, false if not.
	 */
	public function get_login_page_notification_text() {
		return $this->plugin->get_global_setting( 'login_page_notification_text', false );
	}

	/**
	 * Retrieves a list of alerts disabled by default.
	 *
	 * @return int[] List of alerts disabled by default.
	 */
	public function get_default_disabled_alerts() {
		return array( 0000, 0001, 0002, 0003, 0004, 0005 );
	}

	/**
	 * Return IDs of disabled alerts.
	 *
	 * @return array
	 */
	public function get_disabled_alerts() {
		if ( ! $this->disabled ) {
			$this->disabled = implode( ',', $this->get_default_disabled_alerts() );
			$this->disabled = $this->plugin->get_global_setting( 'disabled-alerts', $this->disabled );
			$this->disabled = ( '' === $this->disabled ) ? array() : explode( ',', $this->disabled );
			$this->disabled = array_map( 'intval', $this->disabled );
		}
		return $this->disabled;
	}

	/**
	 * Method: Set Disabled Alerts.
	 *
	 * @param array $types IDs alerts to disable.
	 */
	public function set_disabled_alerts( $types ) {
		$this->disabled = array_unique( array_map( 'intval', $types ) );
		$this->plugin->set_global_setting( 'disabled-alerts', implode( ',', $this->disabled ) );
	}

	/**
	 * Checks if the plugin is in incognito mode.
	 *
	 * @return bool
	 */
	public function is_incognito() {
		return $this->plugin->get_global_boolean_setting( 'hide-plugin' );
	}

	/**
	 * Enables or disables plugin's incognito mode.
	 *
	 * @param bool $enabled If true, the incognito mode gets enabled.
	 */
	public function set_incognito( $enabled ) {
		$old_value = $this->plugin->get_global_setting( 'hide-plugin' );
		$old_value = ( 'yes' === $old_value );
		if ( $old_value !== $enabled ) {
			$alert_data = array(
				'EventType' => ( $enabled ) ? 'enabled' : 'disabled',
			);
			$this->plugin->alerts->trigger_event( 6051, $alert_data );
		}

		$this->plugin->set_global_boolean_setting( 'hide-plugin', $enabled );
	}

	/**
	 * Checking if the data will be removed.
	 */
	public function is_delete_data() {
		return $this->plugin->get_global_boolean_setting( 'delete-data' );
	}

	/**
	 * Sets the plugin setting that allows data deletion on plugin uninstall.
	 *
	 * @param mixed $enabled If true, data deletion on plugin uninstall gets enabled.
	 */
	public function set_delete_data( $enabled ) {
		$this->plugin->set_global_boolean_setting( 'delete-data', $enabled );
	}

	/**
	 * Set Plugin Viewers.
	 *
	 * @param array $users_or_roles – Users/Roles.
	 */
	public function set_allowed_plugin_viewers( $users_or_roles ) {

		$old_value = $this->plugin->get_global_setting( 'plugin-viewers' );
		$changes   = $this->determine_added_and_removed_items( $old_value, implode( ',', $users_or_roles ) );

		if ( ! empty( $changes['added'] ) ) {
			foreach ( $changes['added'] as $user ) {
				$this->plugin->alerts->trigger_event(
					6050,
					array(
						'user'           => $user,
						'previous_users' => ( empty( $old_value ) ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'      => 'added',
					)
				);
			}
		}

		if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
			foreach ( $changes['removed'] as $user ) {
				if ( ! empty( $user ) ) {
					$this->plugin->alerts->trigger_event(
						6050,
						array(
							'user'           => $user,
							'previous_users' => empty( $old_value ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'      => 'removed',
						)
					);
				}
			}
		}

		$this->viewers = $users_or_roles;
		$this->plugin->set_global_setting( 'plugin-viewers', implode( ',', $this->viewers ), true );
	}

	/**
	 * Get Plugin Viewers.
	 *
	 * @return array List of users allowed to view the plugin.
	 */
	public function get_allowed_plugin_viewers() {
		if ( is_null( $this->viewers ) ) {
			$this->viewers = array_unique( array_filter( explode( ',', $this->plugin->get_global_setting( 'plugin-viewers' ) ) ) );
		}
		return $this->viewers;
	}

	/**
	 * Set restrict plugin setting.
	 *
	 * @param string $setting – Setting.
	 * @since 3.2.3
	 */
	public function set_restrict_plugin_setting( $setting ) {
		$old_value = $this->plugin->get_global_setting( 'restrict-plugin-settings', 'only_admins' );

		if ( ! is_null( $old_value ) && $old_value !== $setting ) {
			$alert_data = array(
				'new_setting'      => ucfirst( str_replace( '_', ' ', $setting ) ),
				'previous_setting' => ucfirst( str_replace( '_', ' ', $old_value ) ),
			);
			$this->plugin->alerts->trigger_event( 6049, $alert_data );
		}

		$this->plugin->set_global_setting( 'restrict-plugin-settings', $setting, true );
	}

	/**
	 * Get restrict plugin setting.
	 *
	 * @since 3.2.3
	 */
	public function get_restrict_plugin_setting() {
		return $this->plugin->get_global_setting( 'restrict-plugin-settings', 'only_admins' );
	}

	/**
	 * Get restriction setting for viewing the log viewer in multisite context.
	 *
	 * @since 4.1.3
	 */
	public function get_restrict_log_viewer() {
		return $this->plugin->get_global_setting( 'restrict-log-viewer', 'only_admins' );
	}

	/**
	 * Set restriction setting for viewing the log viewer in multisite context.
	 *
	 * @param string $setting – Setting.
	 * @since 4.1.3
	 */
	public function set_restrict_log_viewer( $setting ) {
		$this->plugin->set_global_setting( 'restrict-log-viewer', $setting, true );
	}

	/**
	 * Sets the number of items per page for the audit log viewer.
	 *
	 * @param int $newvalue Number of items per page for the audit log viewer.
	 */
	public function set_views_per_page( $newvalue ) {
		$this->per_page = max( intval( $newvalue ), 1 );
		$this->plugin->set_global_setting( 'items-per-page', $this->per_page );
	}

	/**
	 * Gets the number of items per page for the audit log viewer.
	 *
	 * @return int Number of items per page for the audit log viewer.
	 */
	public function get_views_per_page() {
		if ( is_null( $this->per_page ) ) {
			$this->per_page = (int) $this->plugin->get_global_setting( 'items-per-page', 10 );
		}
		return $this->per_page;
	}

	/**
	 * Check if current user can perform an action.
	 *
	 * @param string $action Type of action, either 'view' or 'edit'.
	 * @return boolean If user has access or not.
	 */
	public function current_user_can( $action ) {
		return $this->user_can( wp_get_current_user(), $action );
	}

	/**
	 * Get list of superadmin usernames.
	 *
	 * @return array
	 */
	protected function get_super_admins() {
		return $this->plugin->is_multisite() ? get_super_admins() : array();
	}

	/**
	 * List of admin usernames.
	 *
	 * @return string[]
	 */
	protected function get_admins() {
		if ( $this->plugin->is_multisite() ) {
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
				$this->site_admins = $wpdb->get_col( $sql ); // phpcs:ignore
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
	public function user_can( $user, $action ) {
		if ( is_int( $user ) ) {
			$user = get_userdata( $user );
		}

		// By default, the user has no privileges.
		$result = false;

		$is_multisite = $this->plugin->is_multisite();
		switch ( $action ) {
			case 'view':
				if ( ! $is_multisite ) {
					// Non-multisite piggybacks on the plugin settings access.
					switch ( $this->get_restrict_plugin_setting() ) {
						case 'only_admins':
							// Allow access only if the user is and admin.
							$result = in_array( 'administrator', $user->roles, true );
							break;
						case 'only_me':
							// Allow access only if the user matches the only user allowed access.
							$result = $user->ID === $this->get_only_me_user_id();
							break;
						default:
							// No other options to allow access here.
							$result = false;
					}
				} else {
					// Multisite MUST respect the log viewer restriction settings plus also additional users and roles
					// defined in the extra option.
					switch ( $this->get_restrict_log_viewer() ) {
						case 'only_me':
							// Allow access only if the user matches the only user allowed access.
							$result = ( $user->ID === $this->get_only_me_user_id() );
							break;
						case 'only_superadmins':
							// Allow access only for super admins.
							if ( function_exists( 'is_super_admin' ) && is_super_admin( $user->ID ) ) {
								$result = true;
							}
							break;
						case 'only_admins':
							// Allow access only for super admins and admins.
							$result = in_array( 'administrator', $user->roles, true ) || ( function_exists( 'is_super_admin' ) && is_super_admin( $user->ID ) );
							break;
						default:
							// Fallback for any other cases would go here.
							break;
					}
				}

				if ( ! $result ) {
					// User is still not allowed to view the logs, let's check the additional users and roles
					// settings.
					$extra_viewers = $this->get_allowed_plugin_viewers();
					if ( in_array( $user->user_login, $extra_viewers ) ) { // phpcs:ignore
						$result = true;
					} elseif ( ! empty( array_intersect( $extra_viewers, $user->roles ) ) ) {
						$result = true;
					}
				}
				break;
			case 'edit':
				if ( $is_multisite ) {
					// No one has access to settings on sub site inside a network.
					if ( wp_doing_ajax() ) {
						// AJAX calls are an exception.
						$result = true;
					} elseif ( ! is_network_admin() ) {
						$result = false;
						break;
					}
				}

				$restrict_plugin_setting = $this->get_restrict_plugin_setting();
				if ( 'only_me' === $restrict_plugin_setting ) {
					$result = ( $user->ID === $this->get_only_me_user_id() );
				} elseif ( 'only_admins' === $restrict_plugin_setting ) {
					if ( $is_multisite ) {
						$result = ( function_exists( 'is_super_admin' ) && is_super_admin( $user->ID ) );
					} else {
						$result = in_array( 'administrator', $user->roles, true );
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
		return apply_filters( 'wsal_user_can', $result, $user, $action );
	}

	/**
	 * Retrieves current user's roles.
	 *
	 * @param string[] $base_roles An array of base roles.
	 *
	 * @return string[]
	 */
	public function get_current_user_roles( $base_roles = null ) {
		if ( null === $base_roles ) {
			$base_roles = wp_get_current_user()->roles;
		}
		if ( is_multisite() && function_exists( 'is_super_admin' ) && is_super_admin() ) {
			$base_roles[] = 'superadmin';
		}
		return $base_roles;
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
	 * Checks if IP address is determined based on proxy.
	 *
	 * @return bool True if IP address is determined based on proxy.
	 */
	public function is_main_ip_from_proxy() {
		return $this->plugin->get_global_boolean_setting( 'use-proxy-ip' );
	}

	/**
	 * Sets the setting that decides if IP address should be determined based on proxy.
	 *
	 * @param bool $enabled True if IP address should be determined based on proxy.
	 */
	public function set_main_ip_from_proxy( $enabled ) {
		$old_value = $this->plugin->get_global_boolean_setting( 'use-proxy-ip' );
		$enabled   = \WSAL\Helpers\Options::string_to_bool( $enabled );
		if ( $old_value !== $enabled ) {
			$alert_data = array(
				'EventType' => ( $enabled ) ? 'enabled' : 'disabled',
			);
			$this->plugin->alerts->trigger_event( 6048, $alert_data );
		}
		$this->plugin->set_global_boolean_setting( 'use-proxy-ip', $enabled );
	}

	/**
	 * Checks if internal IP filtering is enabled.
	 *
	 * @return bool
	 */
	public function is_internal_ips_filtered() {
		return $this->plugin->get_global_boolean_setting( 'filter-internal-ip', false );
	}

	/**
	 * Enables or disables the internal IP filtering.
	 *
	 * @param bool $enabled True if internal IP filtering should be enabled.
	 */
	public function set_internal_ips_filtering( $enabled ) {
		$this->plugin->set_global_boolean_setting( 'filter-internal-ip', $enabled );
	}

	/**
	 * Get main client IP.
	 *
	 * @return string|null
	 */
	public function get_main_client_ip() {
		$result = null;

		if ( $this->is_main_ip_from_proxy() ) {
			// TODO: The algorithm below just gets the first IP in the list...we might want to make this more intelligent somehow.
			$result = $this->get_client_ips();
			$result = reset( $result );
			$result = isset( $result[0] ) ? $result[0] : null;
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip     = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			$result = $this->normalize_ip( $ip );

			if ( ! $this->validate_ip( $result ) ) {
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
	public function get_client_ips() {
		$ips = array();

		$proxy_headers = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'X-ORIGINAL-FORWARDED-FOR',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
			// Cloudflare.
			'HTTP_CF-Connecting-IP',
			'HTTP_TRUE_CLIENT_IP',
		);
		foreach ( $proxy_headers as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				$ips[ $key ] = array();

				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) { // phpcs:ignore
					$ip = $this->normalize_ip( $ip );
					if ( $this->validate_ip( $ip ) ) {
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
	 *
	 * phpcs:disable Squiz.PHP.CommentedOutCode.Found
	 */
	protected function normalize_ip( $ip ) {
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
	protected function validate_ip( $ip ) {
		$opts = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;

		if ( $this->is_internal_ips_filtered() ) {
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
	 * Sets the users excluded from monitoring.
	 *
	 * @param array $users Users to be excluded.
	 */
	public function set_excluded_monitoring_users( $users ) {

		$old_value = $this->plugin->get_global_setting( 'excluded-users', array() );
		$changes   = $this->determine_added_and_removed_items( $old_value, implode( ',', $users ) );

		if ( ! empty( $changes['added'] ) ) {
			foreach ( $changes['added'] as $user ) {
				$this->plugin->alerts->trigger_event(
					6053,
					array(
						'user'           => $user,
						'previous_users' => ( empty( $old_value ) ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'      => 'added',
					)
				);
			}
		}
		if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
			foreach ( $changes['removed'] as $user ) {
				$this->plugin->alerts->trigger_event(
					6053,
					array(
						'user'           => $user,
						'previous_users' => empty( $old_value ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'      => 'removed',
					)
				);
			}
		}

		$this->excluded_users = $users;
		$this->plugin->set_global_setting( 'excluded-users', esc_html( implode( ',', $this->excluded_users ) ) );
	}

	/**
	 * Retrieves the users excluded from monitoring.
	 *
	 * @return array Users excluded from monitoring.
	 */
	public function get_excluded_monitoring_users() {
		if ( empty( $this->excluded_users ) ) {
			$this->excluded_users = array_unique( array_filter( explode( ',', $this->plugin->get_global_setting( 'excluded-users' ) ) ) );
		}
		return $this->excluded_users;
	}

	/**
	 * Set Custom Post Types excluded from monitoring.
	 *
	 * @param array $post_types - Array of post types to exclude.
	 * @since 2.6.7
	 */
	public function set_excluded_post_types( $post_types ) {

		$old_value = $this->plugin->get_global_setting( 'custom-post-types', array() );
		$changes   = $this->determine_added_and_removed_items( $old_value, implode( ',', $post_types ) );

		if ( ! empty( $changes['added'] ) ) {
			foreach ( $changes['added'] as $post_type ) {
				$this->plugin->alerts->trigger_event(
					6056,
					array(
						'post_type'      => $post_type,
						'previous_types' => ( empty( $old_value ) ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'      => 'added',
					)
				);
			}
		}

		if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
			foreach ( $changes['removed'] as $post_type ) {
				$this->plugin->alerts->trigger_event(
					6056,
					array(
						'post_type'      => $post_type,
						'previous_types' => empty( $old_value ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'      => 'removed',
					)
				);
			}
		}

		$this->post_types = $post_types;
		$this->plugin->set_global_setting( 'custom-post-types', esc_html( implode( ',', $this->post_types ) ) );
	}

	/**
	 * Get Custom Post Types excluded from monitoring.
	 *
	 * @since 2.6.7
	 */
	public function get_excluded_post_types() {
		if ( empty( $this->post_types ) ) {
			$this->post_types = array_unique( array_filter( explode( ',', $this->plugin->get_global_setting( 'custom-post-types' ) ) ) );
		}
		return $this->post_types;
	}

	/**
	 * Set roles excluded from monitoring.
	 *
	 * @param array $roles - Array of roles.
	 */
	public function set_excluded_monitoring_roles( $roles ) {

		// Trigger alert.
		$old_value = $this->plugin->get_global_setting( 'excluded-roles', array() );
		$changes   = $this->determine_added_and_removed_items( $old_value, implode( ',', $roles ) );

		if ( ! empty( $changes['added'] ) ) {
			foreach ( $changes['added'] as $user ) {
				$this->plugin->alerts->trigger_event(
					6054,
					array(
						'role'           => $user,
						'previous_users' => ( empty( $old_value ) ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'      => 'added',
					)
				);
			}
		}
		if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
			foreach ( $changes['removed'] as $user ) {
				$this->plugin->alerts->trigger_event(
					6054,
					array(
						'role'           => $user,
						'previous_users' => empty( $old_value ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'      => 'removed',
					)
				);
			}
		}

		$this->excluded_roles = $roles;
		$this->plugin->set_global_setting( 'excluded-roles', esc_html( implode( ',', $roles ) ) );
	}

	/**
	 * Get roles excluded from monitoring.
	 */
	public function get_excluded_monitoring_roles() {
		if ( empty( $this->excluded_roles ) ) {
			$this->excluded_roles = array_unique( array_filter( explode( ',', $this->plugin->get_global_setting( 'excluded-roles' ) ) ) );
		}
		return $this->excluded_roles;
	}

	/**
	 * Updates custom post meta fields excluded from monitoring.
	 *
	 * @param array $custom Excluded post meta fields.
	 */
	public function set_excluded_post_meta_fields( $custom ) {
		$old_value = $this->get_excluded_post_meta_fields();
		$changes   = $this->determine_added_and_removed_items( $old_value, implode( ',', $custom ) );

		if ( ! empty( $changes['added'] ) ) {
			foreach ( $changes['added'] as $custom_field ) {
				$this->plugin->alerts->trigger_event(
					6057,
					array(
						'custom_field'    => $custom_field,
						'previous_fields' => ( empty( $old_value ) ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'       => 'added',
					)
				);
			}
		}

		if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
			foreach ( $changes['removed'] as $custom_field ) {
				$this->plugin->alerts->trigger_event(
					6057,
					array(
						'custom_field'    => $custom_field,
						'previous_fields' => empty( $old_value ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'       => 'removed',
					)
				);
			}
		}

		$this->excluded_post_meta = $custom;
		$this->plugin->set_global_setting( 'excluded-post-meta', esc_html( implode( ',', $this->excluded_post_meta ) ) );
	}

	/**
	 * Retrieves a list of post meta fields excluded from monitoring.
	 *
	 * @return array
	 */
	public function get_excluded_post_meta_fields() {
		if ( empty( $this->excluded_post_meta ) ) {
			$this->excluded_post_meta = array_unique( array_filter( explode( ',', $this->plugin->get_global_setting( 'excluded-post-meta' ) ) ) );
			asort( $this->excluded_post_meta );
		}
		return $this->excluded_post_meta;
	}

	/**
	 * Updates custom user meta fields excluded from monitoring.
	 *
	 * @param array $custom Custom user meta fields excluded from monitoring.
	 *
	 * @since 4.3.2
	 */
	public function set_excluded_user_meta_fields( $custom ) {

		$old_value = $this->get_excluded_user_meta_fields();
		$changes   = $this->determine_added_and_removed_items( $old_value, implode( ',', $custom ) );

		if ( ! empty( $changes['added'] ) ) {
			foreach ( $changes['added'] as $custom_field ) {
				$this->plugin->alerts->trigger_event(
					6058,
					array(
						'custom_field'    => $custom_field,
						'previous_fields' => ( empty( $old_value ) ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'       => 'added',
					)
				);
			}
		}

		if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
			foreach ( $changes['removed'] as $custom_field ) {
				$this->plugin->alerts->trigger_event(
					6058,
					array(
						'custom_field'    => $custom_field,
						'previous_fields' => empty( $old_value ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'       => 'removed',
					)
				);
			}
		}

		$this->excluded_user_meta = $custom;
		$this->plugin->set_global_setting( 'excluded-user-meta', esc_html( implode( ',', $this->excluded_user_meta ) ) );
	}

	/**
	 * Retrieves a list of user meta fields excluded from monitoring.
	 *
	 * @return array
	 * @since 4.3.2
	 */
	public function get_excluded_user_meta_fields() {
		if ( empty( $this->excluded_user_meta ) ) {
			$this->excluded_user_meta = array_unique( array_filter( explode( ',', $this->plugin->get_global_setting( 'excluded-user-meta' ) ) ) );
			asort( $this->excluded_user_meta );
		}

		return $this->excluded_user_meta;
	}

	/**
	 * IP excluded from monitoring.
	 *
	 * @param array $ip IP addresses to exclude from monitoring.
	 */
	public function set_excluded_monitoring_ip( $ip ) {
		$old_value = $this->plugin->get_global_setting( 'excluded-ip', array() );
		$changes   = $this->determine_added_and_removed_items( $old_value, implode( ',', $ip ) );

		if ( ! empty( $changes['added'] ) ) {
			foreach ( $changes['added'] as $user ) {
				$this->plugin->alerts->trigger_event(
					6055,
					array(
						'ip'           => $user,
						'previous_ips' => ( empty( $old_value ) ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'    => 'added',
					)
				);
			}
		}
		if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
			foreach ( $changes['removed'] as $user ) {
				$this->plugin->alerts->trigger_event(
					6055,
					array(
						'ip'           => $user,
						'previous_ips' => empty( $old_value ) ? $this->tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
						'EventType'    => 'removed',
					)
				);
			}
		}

		$this->excluded_ip = $ip;
		$this->plugin->set_global_setting( 'excluded-ip', esc_html( implode( ',', $this->excluded_ip ) ) );
	}

	/**
	 * Retrieves a list of IP addresses to exclude from monitoring.
	 *
	 * @return array List of IP addresses to exclude from monitoring.
	 */
	public function get_excluded_monitoring_ip() {
		if ( empty( $this->excluded_ip ) ) {
			$this->excluded_ip = array_unique( array_filter( explode( ',', $this->plugin->get_global_setting( 'excluded-ip' ) ) ) );
		}
		return $this->excluded_ip;
	}

	/**
	 * Determines datetime format to be displayed in any UI in the plugin (logs in administration, emails, reports,
	 * notifications etc.).
	 *
	 * Note: Format returned by this function is not compatible with JavaScript date and time picker widgets. Use
	 * functions GetTimeFormat and GetDateFormat for those.
	 *
	 * @param boolean $line_break             - True if line break otherwise false.
	 * @param boolean $use_nb_space_for_am_pm True if non-breakable space should be placed before the AM/PM chars.
	 *
	 * @return string
	 */
	public function get_datetime_format( $line_break = true, $use_nb_space_for_am_pm = true ) {
		$result = $this->get_date_format();

		$result .= $line_break ? '<\b\r>' : ' ';

		$time_format    = $this->get_time_format();
		$has_am_pm      = false;
		$am_pm_fraction = false;
		$am_pm_pattern  = '/(?i)(\s+A)/';
		if ( preg_match( $am_pm_pattern, $time_format, $am_pm_matches ) ) {
			$has_am_pm      = true;
			$am_pm_fraction = $am_pm_matches[0];
			$time_format    = preg_replace( $am_pm_pattern, '', $time_format );
		}

		// Check if the time format does not have seconds.
		if ( stripos( $time_format, 's' ) === false ) {
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
		return $this->plugin->get_global_setting( 'timezone', 'wp' );
	}

	/**
	 * Updates the timezone handling setting.
	 *
	 * @param string $newvalue New setting value.
	 *
	 * @return void
	 */
	public function set_timezone( $newvalue ) {
		$this->plugin->set_global_setting( 'timezone', $newvalue );
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
		return $this->plugin->get_global_boolean_setting( 'show_milliseconds', true );
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
		$this->plugin->set_global_boolean_setting( 'show_milliseconds', $newvalue );
	}


	/**
	 * Get type of username to display.
	 */
	public function get_type_username() {
		return $this->plugin->get_global_setting( 'type_username', 'display_name' );
	}

	/**
	 * Set type of username to display
	 *
	 * @param string $newvalue - New value variable.
	 * @since 2.6.5
	 */
	public function set_type_username( $newvalue ) {
		$this->plugin->set_global_setting( 'type_username', $newvalue );
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
			'info'       => '1',
		);

		if ( $this->plugin->is_multisite() ) {
			$columns = array_slice( $columns, 0, 6, true ) + array( 'site' => '1' ) + array_slice( $columns, 6, null, true );
		}

		$selected = $this->get_columns_selected();

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

			if ( $this->plugin->is_multisite() ) {
				$columns = array_slice( $columns, 0, 6, true ) + array( 'site' => '0' ) + array_slice( $columns, 6, null, true );
			}

			$selected = (array) json_decode( $selected );
			$columns  = array_merge( $columns, $selected );
		}

		return $columns;
	}

	/**
	 * Gets the list of columns selected for display in the audit log viewer.
	 *
	 * @return array List of columns selected for display in the audit log viewer
	 */
	public function get_columns_selected() {
		return $this->plugin->get_global_setting( 'columns', array() );
	}

	/**
	 * Sets the list of columns selected for display in the audit log viewer.
	 *
	 * @param array $columns List of columns selected for display in the audit log viewer.
	 */
	public function set_columns( $columns ) {
		$this->plugin->set_global_setting( 'columns', json_encode( $columns ) ); // phpcs:ignore
	}

	/**
	 * Checks if the monitoring of background events is enabled.
	 *
	 * @return bool True if the monitoring of background events is enabled.
	 */
	public function is_wp_backend() {
		return $this->plugin->get_global_boolean_setting( 'wp-backend' );
	}

	/**
	 * Enables or disables the monitoring of background events.
	 *
	 * @param bool $enabled True if the monitoring of background events should be enabled.
	 */
	public function set_wp_backend( $enabled ) {
		$this->plugin->set_global_boolean_setting( 'wp-backend', $enabled );
	}

	/**
	 * Set use email setting.
	 *
	 * @param string $use – Setting value.
	 */
	public function set_use_email( $use ) {
		$this->plugin->set_global_setting( 'use-email', $use );
	}

	/**
	 * Get use email setting.
	 *
	 * @return string
	 */
	public function get_use_email() {
		return $this->plugin->get_global_setting( 'use-email', 'default_email' );
	}

	/**
	 * Sets the "From" email address.
	 *
	 * @param string $email_address The "From" email address.
	 */
	public function set_from_email( $email_address ) {
		$this->plugin->set_global_setting( 'from-email', trim( $email_address ) );
	}

	/**
	 * Get the "From" email address.
	 *
	 * @return string The "From" email address.
	 */
	public function get_from_email() {
		return $this->plugin->get_global_setting( 'from-email' );
	}

	/**
	 * Sets the user display name for the event audit log.
	 *
	 * @param string $display_name User display name setting.
	 *
	 * @return void
	 */
	public function set_display_name( $display_name ) {
		$this->plugin->set_global_setting( 'display-name', trim( $display_name ) );
	}

	/**
	 * Gets the user display name for the event audit log.
	 *
	 * @return string User display name setting.
	 */
	public function get_display_name() {
		return $this->plugin->get_global_setting( 'display-name' );
	}

	/**
	 * Sets the log limit for failed login attempts.
	 *
	 * @param  int $value - Failed login limit.
	 * @since  2.6.3
	 */
	public function set_failed_login_limit( $value ) {
		if ( ! empty( $value ) ) {
			$this->plugin->set_global_setting( 'log-failed-login-limit', abs( $value ) );
		} else {
			$this->plugin->set_global_setting( 'log-failed-login-limit', - 1 );
		}
	}

	/**
	 * Get the log limit for failed login attempts.
	 *
	 * @return int
	 * @since  2.6.3
	 */
	public function get_failed_login_limit() {
		return intval( $this->plugin->get_global_setting( 'log-failed-login-limit', 10 ) );
	}

	/**
	 * Sets the log limit for failed login attempts for visitor.
	 *
	 * @param  int $value - Failed login limit.
	 * @since  2.6.3
	 */
	public function set_visitor_failed_login_limit( $value ) {
		if ( ! empty( $value ) ) {
			$this->plugin->set_global_setting( 'log-visitor-failed-login-limit', abs( $value ) );
		} else {
			$this->plugin->set_global_setting( 'log-visitor-failed-login-limit', - 1 );
		}
	}

	/**
	 * Get the log limit for failed login attempts for visitor.
	 *
	 * @return int
	 * @since  2.6.3
	 */
	public function get_visitor_failed_login_limit() {
		return intval( $this->plugin->get_global_setting( 'log-visitor-failed-login-limit', 10 ) );
	}


	/**
	 * Method: Get Token Type.
	 *
	 * @param string $token - Token type.
	 *
	 * @return string
	 * @since 3.2.3
	 */
	public function get_token_type( $token ) {
		// Get users.
		$users = array();
		foreach ( get_users( 'blog_id=0&fields[]=user_login' ) as $obj ) {
			$users[] = $obj->user_login;
		}

		// Check if the token matched users.
		if ( in_array( $token, $users ) ) { // phpcs:ignore
			return 'user';
		}

		// Get user roles.
		$roles = array_keys( get_editable_roles() );

		// Check if the token matched user roles.
		if ( in_array( $token, $roles, true ) ) {
			return 'role';
		}

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

		// Check if the token matched post types.
		if ( in_array( $token, $post_types, true ) ) {
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
			! $this->plugin->get_global_boolean_setting( 'mwp-child-stealth-mode', false ) // MainWP Child Stealth Mode is not already active.
			&& WpSecurityAuditLog::is_mainwp_active() // And if MainWP Child plugin is installed & active.
		) {
			// Check if freemius state is anonymous.
			if ( ! wsal_freemius()->is_premium() && 'anonymous' === $this->plugin->get_global_setting( 'freemius_state', 'anonymous' ) ) {
				// Update Freemius state to skipped.
				$this->plugin->set_global_setting( 'wsal_freemius_state', 'skipped', true );

				if ( ! $this->plugin->is_multisite() ) {
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

			$this->set_incognito( true ); // Incognito mode to hide WSAL on plugins page.
			$this->set_restrict_log_viewer( 'only_me' );
			$this->set_restrict_plugin_setting( 'only_me' );
			// Current user with fallback to default admin (in case this is triggered using WP CLI or something similar).
			$only_me_user_id = is_user_logged_in() ? get_current_user_id() : 1;
			$this->set_only_me_user_id( $only_me_user_id );
			$this->plugin->set_global_boolean_setting( 'mwp-child-stealth-mode', true ); // Save stealth mode option.
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
		$this->plugin->set_global_boolean_setting( 'mwp-child-stealth-mode', false ); // Disable stealth mode option.
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

		if ( $this->plugin->get_global_boolean_setting( 'mwp-child-stealth-mode', false ) ) {
			$this->deactivate_mainwp_child_stealth_mode();
		}
	}

	/**
	 * Check and return if stealth mode is active.
	 *
	 * @return boolean
	 */
	public function is_stealth_mode() {
		return $this->plugin->get_global_boolean_setting( 'mwp-child-stealth-mode', false );
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
			case ! $this->plugin->is_multisite():
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
		return isset( $_REQUEST['wsal-cbid'] ) && 0 !== (int) $_REQUEST['wsal-cbid']; // phpcs:ignore
	}

	/**
	 * Method: Get a specific view.
	 *
	 * @since 3.2.4
	 *
	 * @return int
	 */
	protected function get_specific_view() {
		return isset( $_REQUEST['wsal-cbid'] ) ? (int) sanitize_text_field( wp_unslash( $_REQUEST['wsal-cbid'] ) ) : 0; // phpcs:ignore
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
		$res = $wpdb->get_results( $sql ); // phpcs:ignore
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
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore
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
		return $this->plugin->get_global_setting( 'events-nav-type', 'infinite-scroll' );
	}

	/**
	 * Sets Events Navigation Type.
	 *
	 * Sets type of navigation for events log viewer.
	 *
	 * @since 3.3.1.1
	 *
	 * @param string $nav_type - Navigation type.
	 */
	public function set_events_type_nav( $nav_type ) {
		$this->plugin->set_global_setting( 'events-nav-type', $nav_type );
	}

	/**
	 * Query WSAL Options from DB.
	 *
	 * @return array - WSAL Options array.
	 */
	public function get_plugin_settings() {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM $wpdb->options WHERE option_name LIKE 'wsal_%'" ); // phpcs:ignore
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
			'woocommerce' => false,
		);

		// Get the option.
		return \WSAL\Helpers\Options::get_option_value_ignore_prefix( self::FRONT_END_EVENTS_OPTION_NAME, $default );
	}

	/**
	 * Set WSAL's frontend events option.
	 *
	 * @param array $value - Option values.
	 * @return bool
	 */
	public static function set_frontend_events( $value = array() ) {
		return \WSAL\Helpers\Options::set_option_value_ignore_prefix( self::FRONT_END_EVENTS_OPTION_NAME, $value, true );
	}

	/**
	 * Stores the ID of user who restricted the plugin settings access to "only me".
	 *
	 * @param int $user_id User ID.
	 * @since 4.1.3
	 */
	public function set_only_me_user_id( $user_id ) {
		$this->plugin->set_global_setting( 'only-me-user-id', $user_id, true );
	}

	/**
	 * Stores the ID of user who restricted the plugin settings access to "only me".
	 *
	 * @return int
	 * @since 4.1.3
	 */
	public function get_only_me_user_id() {
		return (int) $this->plugin->get_global_setting( 'only-me-user-id' );
	}

	/**
	 * Save admin blocking plugin support enabled.
	 *
	 * @param bool $enabled True, if admin blocking plugin support should be enabled.
	 */
	public function set_admin_blocking_plugin_support( $enabled ) {
		$this->plugin->set_global_boolean_setting( 'admin-blocking-plugins-support', $enabled );
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
		return $this->plugin->get_global_boolean_setting( 'admin-blocking-plugins-support', false );
	}

	/**
	 * Retrieves the settings enforced by MainWP from local database.
	 *
	 * @return array Settings enforced by MainWP.
	 */
	public function get_mainwp_enforced_settings() {
		return $this->plugin->get_global_setting( 'mainwp_enforced_settings', array() );
	}

	/**
	 * Stores the settings enforced by MainWP in local database.
	 *
	 * @param array $settings Enforced settings.
	 */
	public function set_mainwp_enforced_settings( $settings ) {
		$this->plugin->set_global_setting( 'mainwp_enforced_settings', $settings );
	}

	/**
	 * Deletes the settings enforced by MainWP from local database.
	 */
	public function delete_mainwp_enforced_settings() {
		$this->plugin->delete_global_setting( 'mainwp_enforced_settings' );
	}

	/**
	 * Determines added and removed items between 2 arrays.
	 *
	 * @param array|string $old_value Old list. Support comma separated string.
	 * @param array|string $value     New list. Support comma separated string.
	 *
	 * @return array
	 */
	public function determine_added_and_removed_items( $old_value, $value ) {
		$old_value         = ( ! is_array( $old_value ) ) ? explode( ',', $old_value ) : $old_value;
		$value             = ( ! is_array( $value ) ) ? explode( ',', $value ) : $value;
		$return            = array();
		$return['removed'] = array_filter( array_diff( $old_value, $value ) );
		$return['added']   = array_filter( array_diff( $value, $old_value ) );

		return $return;
	}

	/**
	 * Tidies-up the blank values.
	 *
	 * @param string $value Value.
	 *
	 * @return string Tidies up value.
	 */
	public function tidy_blank_values( $value ) {
		return ( empty( $value ) ) ? __( 'None provided', 'wp-security-audit-log' ) : $value;
	}

	/**
	 * Retrieves current database version.
	 *
	 * @return int Current database version number.
	 * @since 4.3.2
	 */
	public function get_database_version() {
		return (int) $this->plugin->get_global_setting( 'db_version', 0 );
	}

	/**
	 * Updates the current database version.
	 *
	 * @param int $version Database version number.
	 * @since 4.3.2
	 */
	public function set_database_version( $version ) {
		$this->plugin->set_global_setting( 'db_version', $version, true );
	}

}
