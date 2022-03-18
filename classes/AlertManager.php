<?php
/**
 * Manager: Alert Manager Class
 *
 * CLass file for alert manager.
 *
 * @since 1.0.0
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WSAL_AlertManager class.
 *
 * It is the actual trigger for the alerts.
 *
 * @package wsal
 */
final class WSAL_AlertManager {

	/**
	 * Array of alerts (WSAL_Alert).
	 *
	 * @var WSAL_Alert[]
	 */
	protected $alerts = array();

	/**
	 * Array of Deprecated Events
	 *
	 * @since 3.3
	 *
	 * @var array
	 */
	protected $deprecated_events = array();

	/**
	 * Array of loggers (WSAL_AbstractLogger).
	 *
	 * @var WSAL_AbstractLogger[]
	 */
	protected $loggers = array();

	/**
	 * Instance of WpSecurityAuditLog.
	 *
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	/**
	 * Contains a list of alerts to trigger.
	 *
	 * @var array
	 */
	protected $pipeline = array();

	/**
	 * Contains an array of alerts that have been triggered for this request.
	 *
	 * @var int[]
	 */
	protected $triggered_types = array();

	/**
	 * WP Users
	 *
	 * Store WP Users for caching purposes.
	 *
	 * @var WP_User[]
	 */
	private $wp_users = array();

	/**
	 * Ignored Custom Post Types.
	 *
	 * @var string[]
	 */
	public $ignored_cpts = array();

	/**
	 * Date format.
	 *
	 * @var string Date format.
	 */
	private $date_format;

	/**
	 * Sanitized date format.
	 *
	 * @var string
	 * @since 4.2.1
	 */
	private $sanitized_date_format;

	/**
	 * Create new AlertManager instance.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;
		foreach ( WSAL_Utilities_FileSystemUtils::read_files_in_folder( dirname( __FILE__ ) . '/Loggers', '*.php' ) as $file ) {
			$this->add_logger_from_file( $file );
		}

		add_action( 'shutdown', array( $this, 'commit_pipeline' ), 8 );

		/**
		 * Filter: `wsal_deprecated_event_ids`
		 *
		 * Deprecated event ids filter.
		 *
		 * @since 3.3
		 *
		 * @param array $deprecated_events - Array of deprecated event ids.
		 */
		$this->deprecated_events = apply_filters( 'wsal_deprecated_event_ids', array( 2004, 2005, 2006, 2007, 2009, 2013, 2015, 2018, 2020, 2022, 2026, 2028, 2059, 2060, 2061, 2064, 2066, 2069, 2075, 2087, 2102, 2103, 2113, 2114, 2115, 2116, 2117, 2118, 5020, 5026, 2107, 2003, 2029, 2030, 2031, 2032, 2033, 2034, 2035, 2036, 2037, 2038, 2039, 2040, 2041, 2056, 2057, 2058, 2063, 2067, 2068, 2070, 2072, 2076, 2088, 2104, 2105, 5021, 5027, 2108 ) );

		/**
		 * Filter: `wsal_ignored_custom_post_types`
		 *
		 * Ignored custom post types filter.
		 *
		 * @param array $ignored_cpts - Array of custom post types.
		 *
		 * @since 3.3.1
		 */
		$this->ignored_cpts = apply_filters(
			'wsal_ignored_custom_post_types',
			array_unique(
				array_merge(
					$this->get_disabled_post_types(),
					array(
						'attachment',          // Attachment CPT.
						'revision',            // Revision CPT.
						'nav_menu_item',       // Nav menu item CPT.
						'customize_changeset', // Customize changeset CPT.
						'custom_css',          // Custom CSS CPT.
					)
				)
			)
		);

		$this->date_format           = $this->plugin->settings()->get_date_format();
		$this->sanitized_date_format = $this->plugin->settings()->get_date_format( true );
	}

	/**
	 * Add new logger from file inside autoloader path.
	 *
	 * @param string $file Path to file.
	 */
	public function add_logger_from_file( $file ) {
		$file = basename( $file, '.php' );
		$this->add_logger_from_class( WSAL_CLASS_PREFIX . 'Loggers_' . $file );
	}

	/**
	 * Add new logger given class name.
	 *
	 * @param string $class Class name.
	 */
	public function add_logger_from_class( $class ) {
		$this->add_logger_instance( new $class( $this->plugin ) );
	}

	/**
	 * Add newly created logger to list.
	 *
	 * @param WSAL_AbstractLogger $logger The new logger.
	 */
	public function add_logger_instance( WSAL_AbstractLogger $logger ) {
		$this->loggers[] = $logger;
	}

	/**
	 * Trigger an alert.
	 *
	 * @param integer $type    - Alert type.
	 * @param array   $data    - Alert data.
	 * @param mixed   $delayed - False if delayed, function if not.
	 */
	public function trigger_event( $type, $data = array(), $delayed = false ) {
		// Figure out the username.
		$username = wp_get_current_user()->user_login;

		// If user switching plugin class exists and filter is set to disable then try to get the old user.
		if ( apply_filters( 'wsal_disable_user_switching_plugin_tracking', false ) && class_exists( 'user_switching' ) ) {
			$old_user = user_switching::get_old_user();
			if ( isset( $old_user->user_login ) ) {
				// Looks like this is a switched user so setup original user values for use when logging.
				$username              = $old_user->user_login;
				$data['Username']      = $old_user->user_login;
				$data['CurrentUserID'] = $old_user->ID;
			}
		}

		if ( empty( $username ) && ! empty( $data['Username'] ) ) {
			$username = $data['Username'];
		}

		// Get current user roles.
		if ( isset( $old_user ) && false !== $old_user ) {
			// looks like this is a switched user so setup original user
			// roles and values for later user.
			$roles = $old_user->roles;
			if ( function_exists( 'is_super_admin' ) && is_super_admin() ) {
				$roles[] = 'superadmin';
			}
			$data['CurrentUserRoles'] = $roles;
		} else {
			// not a switched user so get the current user roles.
			$roles = $this->plugin->settings()->get_current_user_roles();
		}
		if ( empty( $roles ) && ! empty( $data['CurrentUserRoles'] ) ) {
			$roles = $data['CurrentUserRoles'];
		}

		// Check if IP is disabled.
		if ( $this->is_ip_address_disabled() ) {
			return;
		}

		// Check if PostType index is set in data array.
		if ( isset( $data['PostType'] ) && ! empty( $data['PostType'] ) ) {
			// If the post type is disabled then return.
			if ( $this->is_disabled_post_type( $data['PostType'] ) ) {
				return;
			}
		}

		// If user or user role is enabled then go ahead.
		if ( $this->check_enable_user_roles( $username, $roles ) ) {

			$data['Timestamp'] = ( isset( $data['Timestamp'] ) && ! empty( $data['Timestamp'] ) ) ? $data['Timestamp'] : current_time( 'U.u', 'true' );
			if ( $delayed ) {
				$this->trigger_event_if( $type, $data, null );
			} else {
				$this->commit_item( $type, $data, null );
			}
		}
	}

	/**
	 * Check enable user and roles.
	 *
	 * @param string $user - Username.
	 * @param array  $roles - User roles.
	 *
	 * @return boolean - True if enable false otherwise.
	 */
	public function check_enable_user_roles( $user, $roles ) {
		if ( '' != $user && $this->is_disabled_user( $user ) ) { // phpcs:ignore
			return false;
		}

		if ( '' != $roles && $this->is_disabled_role( $roles ) ) { // phpcs:ignore
			return false;
		}
		return true;
	}

	/**
	 * Trigger only if a condition is met at the end of request.
	 *
	 * @param integer  $type - Alert type ID.
	 * @param array    $data - Alert data.
	 * @param callable $cond - A future condition callback (receives an object of type WSAL_AlertManager as parameter).
	 */
	public function trigger_event_if( $type, $data, $cond = null ) {
		$username = null;

		// if user switching plugin class exists and filter is set to disable then try get the old user.
		if ( apply_filters( 'wsal_disable_user_switching_plugin_tracking', false ) && class_exists( 'user_switching' ) ) {
			$old_user = user_switching::get_old_user();
			if ( isset( $old_user->user_login ) ) {
				// looks like this is a switched user so setup original user
				// values for use when logging.
				$username              = $old_user->user_login;
				$data['Username']      = $old_user->user_login;
				$data['CurrentUserID'] = $old_user->ID;
			}
		}

		$roles = array();
		if ( 1000 === $type ) {
			// When event 1000 is triggered, the user is not logged in.
			// We need to extract the username and user roles from the event data.
			$username = array_key_exists( 'Username', $data ) ? $data['Username'] : null;
			$roles    = array_key_exists( 'CurrentUserRoles', $data ) ? $data['CurrentUserRoles'] : array();
		} elseif ( class_exists( 'user_switching' ) && isset( $old_user ) && false !== $old_user ) {
			// looks like this is a switched user so setup original user
			// roles and values for later user.
			$roles = $old_user->roles;
			if ( function_exists( 'is_super_admin' ) && is_super_admin() ) {
				$roles[] = 'superadmin';
			}
			$data['CurrentUserRoles'] = $roles;
		} else {
			$username = wp_get_current_user()->user_login;
			$roles    = $this->plugin->settings()->get_current_user_roles();
		}

		// Check if IP is disabled.
		if ( $this->is_ip_address_disabled() ) {
			return;
		}

		// Check if PostType index is set in data array.
		if ( isset( $data['PostType'] ) && ! empty( $data['PostType'] ) ) {
			// If the post type is disabled then return.
			if ( $this->is_disabled_post_type( $data['PostType'] ) ) {
				return;
			}
		}

		if ( $this->check_enable_user_roles( $username, $roles ) ) {
			if ( ! array_key_exists( 'Timestamp', $data ) ) {
				$data['Timestamp'] = current_time( 'U.u', 'true' );
			}
			$this->pipeline[] = array(
				'type' => $type,
				'data' => $data,
				'cond' => $cond,
			);
		}
	}

	/**
	 * Method: Commit an alert now.
	 *
	 * @param int   $type   - Alert type.
	 * @param array $data   - Data of the alert.
	 * @param array $cond   - Condition for the alert.
	 * @param bool  $_retry - Retry.
	 *
	 * @return mixed
	 * @internal
	 */
	protected function commit_item( $type, $data, $cond, $_retry = true ) {
		// Double NOT operation here is intentional. Same as ! ( bool ) [ $value ]
		// NOTE: return false on a true condition to compensate.
		if ( ! $cond || ! ! call_user_func( $cond, $this ) ) {
			if ( $this->is_enabled( $type ) ) {
				if ( isset( $this->alerts[ $type ] ) ) {
					// Ok, convert alert to a log entry.
					$this->triggered_types[] = $type;
					$this->log( $type, $data );
				} elseif ( $_retry ) {
					// This is the last attempt at loading alerts from default file.
					$this->plugin->load_defaults();
					return $this->commit_item( $type, $data, $cond, false );
				} else {
					// In general this shouldn't happen, but it could, so we handle it here.
					/* translators: Event ID */
					$error_message = sprintf( esc_html__( 'Event with code %d has not be registered.', 'wp-security-audit-log' ), $type );
					$this->plugin->wsal_log( $error_message );
				}
			}
		}
	}

	/**
	 * Method: Runs over triggered alerts in pipeline and passes them to loggers.
	 *
	 * @internal
	 */
	public function commit_pipeline() {
		foreach ( $this->pipeline as $item ) {
			$this->commit_item( $item['type'], $item['data'], $item['cond'] );
		}
	}

	/**
	 * Method: True if at the end of request an alert of this type will be triggered.
	 *
	 * @param integer $type  - Alert type ID.
	 * @param int     $count - A minimum number of event occurrences.
	 *
	 * @return boolean
	 */
	public function will_trigger( $type, $count = 1 ) {
		$number_found = 0;
		foreach ( $this->pipeline as $item ) {
			if ( $item['type'] == $type ) { // phpcs:ignore
				$number_found++;
				if ($count == 1 || $number_found == $count) { // phpcs:ignore
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Method: True if an alert has been or will be triggered in this request, false otherwise.
	 *
	 * @param int $type - Alert type ID.
	 * @param int $count - A minimum number of event occurrences.
	 * @return boolean
	 */
	public function will_or_has_triggered( $type, $count = 1 ) {
		return in_array( $type, $this->triggered_types ) || $this->will_trigger( $type, $count ); // phpcs:ignore
	}

	/**
	 * Register an alert type.
	 *
	 * @param string $category    Category name.
	 * @param string $subcategory Subcategory name.
	 * @param array  $info        Event information from defaults.php.
	 */
	public function register( $category, $subcategory, $info ) {

		// Default for optional fields.
		$metadata   = array();
		$links      = array();
		$object     = '';
		$event_type = '';

		$definition_items_count = count( $info );
		if ( 8 === $definition_items_count ) {
			// Most recent event definition introduced in version 4.2.1.
			list( $code, $severity, $desc, $message, $metadata, $links, $object, $event_type ) = $info;
		} elseif ( 6 === $definition_items_count ) {
			// Legacy event definition for backwards compatibility (used prior to version 4.2.1).
			list( $code, $severity, $desc, $message, $object, $event_type ) = $info;
		} else {
			// Even older legacy event definition for backwards compatibility.
			list( $code, $severity, $desc, $message ) = $info;
		}

		if ( is_string( $links ) ) {
			$links = array( $links );
		}

		if ( isset( $this->alerts[ $code ] ) ) {
			add_action( 'admin_notices', array( $this, 'duplicate_event_notice' ) );
			/* Translators: Event ID */
			$error_message = sprintf( esc_html__( 'Event %s already registered with WP Activity Log.', 'wp-security-audit-log' ), $code );
			$this->plugin->wsal_log( $error_message );

			return;
		}

		/**
		 * WSAL Filter: `wsal_event_metadata_definition`
		 *
		 * Filters event metadata definition before registering specific event with the alert manager. This is the
		 * preferred way to change metadata definition of built-in events.
		 *
		 * @param array $metadata - Event data.
		 * @param integer $code - Event ID.
		 *
		 * @since 4.3.2
		 */
		$metadata = apply_filters( 'wsal_event_metadata_definition', $metadata, $code );

		$this->alerts[ $code ] = new WSAL_Alert( $code, $severity, $category, $subcategory, $desc, $message, $metadata, $links, $object, $event_type );
	}

	/**
	 * Register a whole group of items.
	 *
	 * @param array $groups - An array with group name as the index and an array of group items as the value.
	 * Item values is an array of [type, code, description, message, object, event type] respectively.
	 */
	public function register_group( $groups ) {
		foreach ( $groups as $name => $group ) {
			foreach ( $group as $subname => $subgroup ) {
				foreach ( $subgroup as $item ) {
					$this->register( $name, $subname, $item );
				}
			}
		}
	}

	/**
	 * Duplicate Event Notice
	 *
	 * @since 3.2.4
	 */
	public function duplicate_event_notice() {
		$class   = 'notice notice-error';
		$message = __( 'You have custom events that are using the same ID or IDs which are already registered in the plugin, so they have been disabled.', 'wp-security-audit-log' );
		printf(
			/* Translators: 1.CSS classes, 2. Notice, 3. Contact us link */
			'<div class="%1$s"><p>%2$s %3$s ' . esc_html__( '%4$s to help you solve this issue.', 'wp-security-audit-log' ) . '</p></div>',
			esc_attr( $class ),
			'<span style="color:#dc3232; font-weight:bold;">' . esc_html__( 'ERROR:', 'wp-security-audit-log' ) . '</span>',
			esc_html( $message ),
			'<a href="https://wpactivitylog.com/contact" target="_blank">' . esc_html__( 'Contact us', 'wp-security-audit-log' ) . '</a>'
		);
	}

	/**
	 * Returns whether alert of type $type is enabled or not.
	 *
	 * @param integer $type Alert type.
	 * @return boolean True if enabled, false otherwise.
	 */
	public function is_enabled( $type ) {
		$disabled_events = $this->plugin->settings()->get_disabled_alerts();
		return ! in_array( $type, $disabled_events, true );
	}

	/**
	 * Method: Returns an array of loaded loggers.
	 *
	 * @return WSAL_AbstractLogger[]
	 */
	public function get_loggers() {
		return $this->loggers;
	}

	/**
	 * Converts an Alert into a Log entry (by invoking loggers).
	 * You should not call this method directly.
	 *
	 * @param integer $event_id   - Alert type.
	 * @param array   $event_data - Misc alert data.
	 */
	protected function log( $event_id, $event_data = array() ) {
		if ( ! isset( $event_data['ClientIP'] ) ) {
			$client_ip = $this->plugin->settings()->get_main_client_ip();
			if ( ! empty( $client_ip ) ) {
				$event_data['ClientIP'] = $client_ip;
			}
		}
		if ( ! isset( $event_data['OtherIPs'] ) && $this->plugin->settings()->is_main_ip_from_proxy() ) {
			$other_ips = $this->plugin->settings()->get_client_ips();
			if ( ! empty( $other_ips ) ) {
				$event_data['OtherIPs'] = $other_ips;
			}
		}
		if ( ! isset( $event_data['UserAgent'] ) ) {
			if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
				$event_data['UserAgent'] = $_SERVER['HTTP_USER_AGENT']; // phpcs:ignore
			}
		}
		if ( ! isset( $event_data['Username'] ) && ! isset( $event_data['CurrentUserID'] ) ) {
			if ( function_exists( 'get_current_user_id' ) ) {
				$event_data['CurrentUserID'] = get_current_user_id();
			}
		}
		if ( ! isset( $event_data['CurrentUserRoles'] ) && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$current_user_roles = $this->plugin->settings()->get_current_user_roles();
			if ( ! empty( $current_user_roles ) ) {
				$event_data['CurrentUserRoles'] = $current_user_roles;
			}
		}

		// If the user sessions plugin is loaded try to attach the SessionID.
		if ( ! isset( $event_data['SessionID'] ) && class_exists( 'WSAL_UserSessions_Helpers' ) ) {
			// Try to get the session id generated from logged in cookie.
			$session_id = WSAL_UserSessions_Helpers::get_session_id_from_logged_in_user_cookie();
			// If we have a SessionID then add it to event_data.
			if ( ! empty( $session_id ) ) {
				$event_data['SessionID'] = $session_id;
			}
		}

		// Get event severity.
		$alert_obj  = $this->get_alert( $event_id );
		$alert_code = $alert_obj ? $alert_obj->severity : 0;
		$severity   = $this->plugin->constants->get_constant_by( 'value', $alert_code );

		/**
		 * Events Severity.
		 *
		 * Add event severity to the meta data of the event.
		 * The lower the number, the higher is the severity.
		 *
		 * Based on monolog log levels:
		 *
		 * Formerly based on Syslog severity levels (https://en.wikipedia.org/wiki/Syslog#Severity_level).
		 *
		 * @see https://github.com/Seldaek/monolog/blob/main/doc/01-usage.md#log-levels
		 * @since 3.3.1
		 */
		if ( is_object( $severity ) && property_exists( $severity, 'name' ) ) {
			if ( 'E_CRITICAL' === $severity->name ) {
				// CRITICAL (500): Critical conditions.
				$event_data['Severity'] = 500;
			} elseif ( 'E_WARNING' === $severity->name ) {
				// WARNING (300): Exceptional occurrences that are not errors.
				$event_data['Severity'] = 300;
			} elseif ( 'E_NOTICE' === $severity->name ) {
				// DEBUG (100): Detailed debug information.
				$event_data['Severity'] = 100;
			} elseif ( property_exists( $severity, 'value' ) ) {
				$event_data['Severity'] = $severity->value;
			}
		}

		/*
		 * In cases where we were not able to figure out a severity already
		 * use a default of 200: info.
		 *
		 * @since 4.3.0
		 */
		if ( ! isset( $event_data['Severity'] ) ) {
			// Assuming this is a misclassified item and using info code.
			// INFO (200): Interesting events.
			$event_data['Severity'] = 200;
		}

		// Add event object.
		if ( $alert_obj && ! isset( $event_data['Object'] ) ) {
			$event_data['Object'] = $alert_obj->object;
		}

		// Add event type.
		if ( $alert_obj && ! isset( $event_data['EventType'] ) ) {
			$event_data['EventType'] = $alert_obj->event_type;
		}

		// Append further details if in multisite.
		if ( $this->plugin->is_multisite() ) {
			$event_data['SiteID']  = get_current_blog_id();
			$event_data['SiteURL'] = get_site_url( $event_data['SiteID'] );
		}

		/**
		 * WSAL Filter: `wsal_event_id_before_log`
		 *
		 * Filters event id before logging it to the database.
		 *
		 * @since 3.3.1
		 *
		 * @param integer $event_id   - Event ID.
		 * @param array   $event_data - Event data.
		 */
		$event_id = apply_filters( 'wsal_event_id_before_log', $event_id, $event_data );

		/**
		 * WSAL Filter: `wsal_event_data_before_log`
		 *
		 * Filters event data before logging it to the database.
		 *
		 * @since 3.3.1
		 *
		 * @param array   $event_data - Event data.
		 * @param integer $event_id   - Event ID.
		 */
		$event_data = apply_filters( 'wsal_event_data_before_log', $event_data, $event_id );

		foreach ( $this->loggers as $logger ) {
			$logger->log( $event_id, $event_data );
		}
	}

	/**
	 * Return alert given alert type.
	 *
	 * @param integer $type    - Alert type.
	 * @param mixed   $default - Returned if alert is not found.
	 * @return WSAL_Alert
	 */
	public function get_alert( $type, $default = null ) {
		if ( isset( $this->alerts[ $type ] ) ) {
			return $this->alerts[ $type ];
		}
		return $default;
	}

	/**
	 * Returns all supported alerts.
	 *
	 * @return WSAL_Alert[]
	 */
	public function get_alerts() {
		return $this->alerts;
	}

	/**
	 * Returns all deprecated events.
	 *
	 * @since 3.3
	 *
	 * @return WSAL_Alert[]
	 */
	public function get_deprecated_events() {
		return $this->deprecated_events;
	}

	/**
	 * Method: Returns array of alerts by category.
	 *
	 * @param string $category - Alerts category.
	 * @return WSAL_Alert[]
	 */
	public function get_alerts_by_category( $category ) {
		// Categorized alerts array.
		$alerts = array();
		foreach ( $this->alerts as $alert ) {
			if ( $category === $alert->catg ) {
				$alerts[ $alert->code ] = $alert;
			}
		}
		return $alerts;
	}

	/**
	 * Method: Returns array of alerts by sub-category.
	 *
	 * @param string $sub_category - Alerts sub-category.
	 * @return WSAL_Alert[]
	 */
	public function get_alerts_by_sub_category( $sub_category ) {
		// Sub-categorized alerts array.
		$alerts = array();
		foreach ( $this->alerts as $alert ) {
			if ( $sub_category === $alert->subcatg ) {
				$alerts[ $alert->code ] = $alert;
			}
		}
		return $alerts;
	}

	/**
	 * Returns all supported alerts.
	 *
	 * @param bool $sorted – Sort the alerts array or not.
	 * @return array
	 */
	public function get_categorized_alerts( $sorted = true ) {
		$result = array();
		foreach ( $this->alerts as $alert ) {
			if ( ! isset( $result[ $alert->catg ] ) ) {
				$result[ $alert->catg ] = array();
			}
			if ( ! isset( $result[ $alert->catg ][ $alert->subcatg ] ) ) {
				$result[ $alert->catg ][ $alert->subcatg ] = array();
			}
			$result[ $alert->catg ][ $alert->subcatg ][] = $alert;
		}

		if ( $sorted ) {
			ksort( $result );
		}
		return $result;
	}

	/**
	 * Returns whether user is enabled or not.
	 *
	 * @param string $user - Username.
	 * @return boolean True if disabled, false otherwise.
	 */
	public function is_disabled_user( $user ) {
		return in_array( $user, $this->get_disabled_users() ); // phpcs:ignore
	}

	/**
	 * Method: Returns an array of disabled users.
	 *
	 * @return array.
	 */
	public function get_disabled_users() {
		return $this->plugin->settings()->get_excluded_monitoring_users();
	}

	/**
	 * Returns whether user is enabled or not.
	 *
	 * @param array $roles - User roles.
	 * @return boolean True if disabled, false otherwise.
	 */
	public function is_disabled_role( $roles ) {
		$is_disabled = false;
		foreach ( $roles as $role ) {
			if ( in_array( $role, $this->get_disabled_roles() ) ) { // phpcs:ignore
				$is_disabled = true;
			}
		}
		return $is_disabled;
	}

	/**
	 * Returns an array of disabled users.
	 *
	 * @return array
	 */
	public function get_disabled_roles() {
		return $this->plugin->settings()->get_excluded_monitoring_roles();
	}

	/**
	 * Method: Check whether post type is disabled or not.
	 *
	 * @param string $post_type - Post type.
	 * @return bool - True if disabled, False if otherwise.
	 * @since 2.6.7
	 */
	public function is_disabled_post_type( $post_type ) {
		return in_array( $post_type, $this->get_disabled_post_types(), true );
	}

	/**
	 * Method: Return array of disabled post types.
	 *
	 * @return array
	 * @since 2.6.7
	 */
	public function get_disabled_post_types() {
		return $this->plugin->settings()->get_excluded_post_types();
	}

	/**
	 * Method: Returns if IP is disabled or not.
	 *
	 * @return bool True if current IP address is disabled.
	 */
	private function is_ip_address_disabled() {
		$is_disabled  = false;
		$ip           = $this->plugin->settings()->get_main_client_ip();
		$excluded_ips = $this->plugin->settings()->get_excluded_monitoring_ip();

		if ( ! empty( $excluded_ips ) ) {
			foreach ( $excluded_ips as $excluded_ip ) {
				if ( false !== strpos( $excluded_ip, '-' ) ) {
					$ip_range = $this->plugin->settings()->get_ipv4_by_range( $excluded_ip );
					$ip_range = $ip_range->lower . '-' . $ip_range->upper;

					if ( $this->plugin->settings()->check_ipv4_in_range( $ip, $ip_range ) ) {
						$is_disabled = true;
						break;
					}
				} elseif ( $ip === $excluded_ip ) {
					$is_disabled = true;
					break;
				}
			}
		}

		return $is_disabled;
	}

	/**
	 * Return user data array of the events.
	 *
	 * @param string $username – Username.
	 * @return stdClass
	 */
	public function get_event_user_data( $username ) {
		// User data.
		$user_data = new stdClass();

		// Handle WSAL usernames.
		if ( empty( $username ) ) {
			$user_data->username = 'System';
		} elseif ( 'Plugin' === $username ) {
			$user_data->username = 'Plugin';
		} elseif ( 'Plugins' === $username ) {
			$user_data->username = 'Plugins';
		} elseif ( 'Website Visitor' === $username || 'Unregistered user' === $username ) {
			$user_data->username = 'Unregistered user';
		} else {
			// Check WP user.
			if ( isset( $this->wp_users[ $username ] ) ) {
				// Retrieve from users cache.
				$user = $this->wp_users[ $username ];
			} else {
				// Get user from WP.
				$user = get_user_by( 'login', $username );

				if ( $user && $user instanceof WP_User ) {
					// Store the user data in class member.
					$this->wp_users[ $username ] = (object) array(
						'ID'           => $user->ID,
						'user_login'   => $user->user_login,
						'first_name'   => $user->first_name,
						'last_name'    => $user->last_name,
						'display_name' => $user->display_name,
						'user_email'   => $user->user_email,
					);
				}
			}

			// Set user data.
			if ( $user ) {
				$user_data->user_id      = $user->ID;
				$user_data->username     = $user->user_login;
				$user_data->first_name   = $user->first_name;
				$user_data->last_name    = $user->last_name;
				$user_data->display_name = $user->display_name;
				$user_data->user_email   = $user->user_email;
			} else {
				$user_data->username = 'System';
			}
		}
		return $user_data;
	}

	/**
	 * Get latest events from DB.
	 *
	 * @since 3.2.4
	 *
	 * @param integer $limit – Number of events.
	 * @return WSAL_Models_Occurrence[]|boolean
	 */
	public function get_latest_events( $limit = 1 ) {
		// Occurrence query.
		$occ_query = new WSAL_Models_OccurrenceQuery();
		if ( ! $occ_query->get_adapter()->is_connected() ) {
			// Connection problem while using external database (if local database is used, we would see WordPress's
			// "Error Establishing a Database Connection" screen).
			return false;
		}

		// Get site id.
		$site_id = (int) $this->plugin->settings()->get_view_site_id();
		if ( $site_id ) {
			$occ_query->add_condition( 'site_id = %d ', $site_id );
		}

		$occ_query->add_order_by( 'created_on', true ); // Set order for latest events.
		$occ_query->set_limit( $limit ); // Set limit.
		$events = $occ_query->get_adapter()->execute_query( $occ_query );

		if ( ! empty( $events ) && is_array( $events ) ) {
			return $events;
		}
		return false;
	}

	/**
	 * Get event for WP-Admin bar.
	 *
	 * @since 3.2.4
	 *
	 * @param boolean $from_db - Query from DB if set to true.
	 * @return WSAL_Models_Occurrence|boolean
	 */
	public function get_admin_bar_event( $from_db = false ) {
		// Get event from transient.
		$event_transient = 'wsal_admin_bar_event';

		// Check for multisite.
		$get_fn = $this->plugin->is_multisite() ? 'get_site_transient' : 'get_transient';
		$set_fn = $this->plugin->is_multisite() ? 'set_site_transient' : 'set_transient';

		$admin_bar_event = $get_fn( $event_transient );
		if ( false === $admin_bar_event || false !== $from_db ) {
			$event = $this->get_latest_events( 1 );

			if ( $event ) {
				$set_fn( $event_transient, $event[0], 30 * MINUTE_IN_SECONDS );
				$admin_bar_event = $event[0];
			}
		}
		return $admin_bar_event;
	}

	/**
	 * Return Public Event IDs.
	 *
	 * @since 3.3
	 *
	 * @return array
	 */
	public function get_public_events() {
		/**
		 * Filter: `wsal_public_event_ids`
		 *
		 * Filter array of public event ids.
		 *
		 * @param array $public_events - Array of public event ids.
		 */
		return apply_filters( 'wsal_public_event_ids', array( 1000, 1002, 1003, 1004, 1005, 1007, 2126, 4000, 4012 ) ); // Public events.
	}

	/**
	 * Get event objects.
	 *
	 * @since 4.0.3 - added param to request an individual object.
	 * @param string $object An object the string is requested for (optional).
	 *
	 * @return array|string
	 */
	public function get_event_objects_data( $object = '' ) {
		$objects = array(
			'user'              => esc_html__( 'User', 'wp-security-audit-log' ),
			'system'            => esc_html__( 'System', 'wp-security-audit-log' ),
			'plugin'            => esc_html__( 'Plugin', 'wp-security-audit-log' ),
			'database'          => esc_html__( 'Database', 'wp-security-audit-log' ),
			'post'              => esc_html__( 'Post', 'wp-security-audit-log' ),
			'file'              => esc_html__( 'File', 'wp-security-audit-log' ),
			'tag'               => esc_html__( 'Tag', 'wp-security-audit-log' ),
			'comment'           => esc_html__( 'Comment', 'wp-security-audit-log' ),
			'setting'           => esc_html__( 'Setting', 'wp-security-audit-log' ),
			'file'              => esc_html__( 'File', 'wp-security-audit-log' ),
			'system-setting'    => esc_html__( 'System Setting', 'wp-security-audit-log' ),
			'mainwp-network'    => esc_html__( 'MainWP Network', 'wp-security-audit-log' ),
			'mainwp'            => esc_html__( 'MainWP', 'wp-security-audit-log' ),
			'category'          => esc_html__( 'Category', 'wp-security-audit-log' ),
			'custom-field'      => esc_html__( 'Custom Field', 'wp-security-audit-log' ),
			'widget'            => esc_html__( 'Widget', 'wp-security-audit-log' ),
			'menu'              => esc_html__( 'Menu', 'wp-security-audit-log' ),
			'theme'             => esc_html__( 'Theme', 'wp-security-audit-log' ),
			'activity-log'      => esc_html__( 'Activity log', 'wp-security-audit-log' ),
			'wp-activity-log'   => esc_html__( 'WP Activity Log', 'wp-security-audit-log' ),
			'multisite-network' => esc_html__( 'Multisite Network', 'wp-security-audit-log' ),
			'ip-address'        => esc_html__( 'IP Address', 'wp-security-audit-log' ),
		);

		asort( $objects );
		$objects = apply_filters(
			'wsal_event_objects',
			$objects
		);

		/**
		 * If a specific object was requested then try return that otherwise
		 * the full array gets returned.
		 *
		 * @since 4.0.3
		 */
		if ( ! empty( $object ) ) {
			// NOTE: if we requested object doesn't exist returns 'unknown object'.
			return ( isset( $objects[ $object ] ) ) ? $objects[ $object ] : __( 'unknown object', 'wp-security-audit-log' );
		}

		// if a specific object was not requested return the full array.
		return $objects;
	}

	/**
	 * Returns the text to display for object.
	 *
	 * @deprecated 4.0.3 - please use get_event_objects_data() directly.
	 * @since 4.0.3 - adjusted to return directly from companion data method.
	 *
	 * NOTE: along with this depreciation the filter `wsal_event_object_text`
	 * is being removed, use `wsal_event_objects` filter instead.
	 *
	 * @TODO: this is to be removed shortly after version 4.0.3 - after other
	 * plugins have had a chance to adjust to using the get_event_objects_data()
	 * function directly.
	 *
	 * @param string $object - Object type.
	 * @return string
	 */
	public function get_display_object_text( $object ) {
		return get_event_objects_data( $object );
	}

	/**
	 * Get event type data array or optionally just value of a single type.
	 *
	 * @since 4.0.3 - added param to request an individual type.
	 * @param string $type A type that the string is requested for (optional).
	 *
	 * @return array|string
	 */
	public function get_event_type_data( $type = '' ) {
		$types = array(
			'login'        => esc_html__( 'Login', 'wp-security-audit-log' ),
			'logout'       => esc_html__( 'Logout', 'wp-security-audit-log' ),
			'installed'    => esc_html__( 'Installed', 'wp-security-audit-log' ),
			'activated'    => esc_html__( 'Activated', 'wp-security-audit-log' ),
			'deactivated'  => esc_html__( 'Deactivated', 'wp-security-audit-log' ),
			'uninstalled'  => esc_html__( 'Uninstalled', 'wp-security-audit-log' ),
			'updated'      => esc_html__( 'Updated', 'wp-security-audit-log' ),
			'created'      => esc_html__( 'Created', 'wp-security-audit-log' ),
			'modified'     => esc_html__( 'Modified', 'wp-security-audit-log' ),
			'deleted'      => esc_html__( 'Deleted', 'wp-security-audit-log' ),
			'published'    => esc_html__( 'Published', 'wp-security-audit-log' ),
			'approved'     => esc_html__( 'Approved', 'wp-security-audit-log' ),
			'unapproved'   => esc_html__( 'Unapproved', 'wp-security-audit-log' ),
			'enabled'      => esc_html__( 'Enabled', 'wp-security-audit-log' ),
			'disabled'     => esc_html__( 'Disabled', 'wp-security-audit-log' ),
			'added'        => esc_html__( 'Added', 'wp-security-audit-log' ),
			'failed-login' => esc_html__( 'Failed Login', 'wp-security-audit-log' ),
			'blocked'      => esc_html__( 'Blocked', 'wp-security-audit-log' ),
			'uploaded'     => esc_html__( 'Uploaded', 'wp-security-audit-log' ),
			'restored'     => esc_html__( 'Restored', 'wp-security-audit-log' ),
			'opened'       => esc_html__( 'Opened', 'wp-security-audit-log' ),
			'viewed'       => esc_html__( 'Viewed', 'wp-security-audit-log' ),
			'started'      => esc_html__( 'Started', 'wp-security-audit-log' ),
			'stopped'      => esc_html__( 'Stopped', 'wp-security-audit-log' ),
			'removed'      => esc_html__( 'Removed', 'wp-security-audit-log' ),
			'unblocked'    => esc_html__( 'Unblocked', 'wp-security-audit-log' ),
			'renamed'      => esc_html__( 'Renamed', 'wp-security-audit-log' ),
			'duplicated'   => esc_html__( 'Duplicated', 'wp-security-audit-log' ),
			'submitted'    => esc_html__( 'Submitted', 'wp-security-audit-log' ),
			'revoked'      => esc_html__( 'Revoked', 'wp-security-audit-log' ),
		);
		// sort the types alphabetically.
		asort( $types );
		$types = apply_filters(
			'wsal_event_type_data',
			$types
		);

		/**
		 * If a specific type was requested then try return that otherwise the
		 * full array gets returned.
		 *
		 * @since 4.0.3
		 */
		if ( ! empty( $type ) ) {
			// NOTE: if we requested type doesn't exist returns 'unknown type'.
			return ( isset( $types[ $type ] ) ) ? $types[ $type ] : __( 'unknown type', 'wp-security-audit-log' );
		}

		// if a specific type was not requested return the full array.
		return $types;

	}

	/**
	 * Returns the text to display for event type.
	 *
	 * @deprecated 4.0.3 - please use get_event_type_data() directly.
	 * @since 4.0.3 - adjusted to return directly from companion data method.
	 *
	 * NOTE: along with this depreciation the filter `wsal_event_type_text` is
	 * being removed, use `wsal_event_type_data` filter instead.
	 *
	 * @TODO: this is to be removed shortly after version 4.0.3 - after other
	 * plugins have had a chance to adjust to using the get_event_type_data()
	 * function directly.
	 *
	 * @param string $event_type - Event type.
	 * @return string
	 */
	public function get_display_event_type_text( $event_type ) {
		// Try to get string from the companion data method.
		return get_event_type_data( $event_type );
	}

	/**
	 * Return sub-categorized events of WSAL.
	 *
	 * @return array
	 */
	public function get_sub_categorized_events() {
		$cg_alerts = $this->get_categorized_alerts();
		$events    = array();

		foreach ( $cg_alerts as $group ) {
			foreach ( $group as $subname => $entries ) {
				if ( __( 'Pages', 'wp-security-audit-log' ) === $subname || __( 'Custom Post Types', 'wp-security-audit-log' ) === $subname ) {
					continue;
				}

				$events[ $subname ] = $entries;
			}
		}

		return $events;
	}

	/**
	 * Return event categories array.
	 *
	 * @return array
	 */
	public function get_event_sub_categories() {
		return array_keys( $this->get_sub_categorized_events() );
	}

	/**
	 * Get user ids for reports.
	 *
	 * @param array $usernames - Array of usernames.
	 *
	 * @return string
	 */
	public function get_user_ids( $usernames ) {
		global $wpdb;

		if ( empty( $usernames ) ) {
			return 'null';
		}

		$user_ids = 'null';
		$sql      = 'SELECT ID FROM ' . $wpdb->users . ' WHERE';
		$last     = end( $usernames );

		foreach ( $usernames as $username ) {
			if ( $last === $username ) {
				$sql .= " user_login = '$username'";
			} else {
				$sql .= " user_login = '$username' OR";
			}
		}

		// Get MainWP dashboard user ids.
		$result = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore

		$users = array();
		if ( ! empty( $result ) ) {
			foreach ( $result as $item ) {
				$users[] = $item['ID'];
			}

			$users    = array_unique( $users );
			$user_ids = "'" . implode( ',', $users ) . "'";
		}

		return $user_ids;
	}

	/**
	 * Get codes by groups.
	 *
	 * If we have alert groups, we need to retrieve all alert codes for those groups
	 * and add them to a final alert of alert codes that will be sent to db in the select query
	 * the same goes for individual alert codes.
	 *
	 * @param array $event_groups - Event groups.
	 * @param array $event_codes  - Event codes.
	 * @param bool  $show_error   - (Optional) False if errors do not need to be displayed.
	 */
	public function get_codes_by_groups( $event_groups, $event_codes, $show_error = true ) {
		$_codes           = array();
		$has_event_groups = empty( $event_groups ) ? false : true;
		$has_event_codes  = empty( $event_codes ) ? false : true;

		if ( $has_event_codes ) {
			// Add the specified alerts to the final array.
			$_codes = $event_codes;
		}

		if ( $has_event_groups ) {
			// Get categorized alerts.
			$cat_alerts = $this->get_sub_categorized_events();

			if ( empty( $cat_alerts ) ) {
				return false;
			}

			// Make sure that all specified alert categories are valid.
			foreach ( $event_groups as $category ) {
				// get alerts from the category and add them to the final array
				// #! only if the specified category is valid, otherwise skip it.
				if ( isset( $cat_alerts[ $category ] ) ) {
					// If this is the "System Activity" category...some of those alert needs to be padded.
					if ( __( 'System Activity', 'wp-security-audit-log' ) === $category ) {
						foreach ( $cat_alerts[ $category ] as $alert ) {
							$aid = $alert->code;

							if ( 1 === strlen( $aid ) ) {
								$aid = $this->pad_key( $aid );
							}

							array_push( $_codes, $aid );
						}
					} else {
						foreach ( $cat_alerts[ $category ] as $alert ) {
							array_push( $_codes, $alert->code );
						}
					}
				}
			}
		}

		if ( empty( $_codes ) ) {
			return false;
		}

		return $_codes;
	}

	/**
	 * Key padding.
	 *
	 * @internal
	 * @param string $key - The key to pad.
	 * @return string
	 */
	private function pad_key( $key ) {
		return 1 === strlen( $key ) ? str_pad( $key, 4, '0', STR_PAD_LEFT ) : $key;
	}

	/**
	 * Get alert details.
	 *
	 * @param stdClass $entry   Raw entry from the occurrences table.
	 * @param string   $context Display context.
	 *
	 * @return array|false Alert details.
	 */
	public function get_alert_details( $entry, $context = 'default' ) {
		$entry_id   = $entry->id;
		$alert_id   = $entry->alert_id;
		$site_id    = $entry->site_id;
		$created_on = $entry->created_on;
		$object     = $entry->object;
		$event_type = $entry->event_type;
		$user_id    = $entry->user_id;

		$ip    = esc_html( $entry->ip );
		$ua    = esc_html( $entry->ua );
		$roles = maybe_unserialize( $entry->roles );
		if ( is_string( $roles ) ) {
			$roles = str_replace( array( '"', '[', ']' ), ' ', $roles );
		}

		// Must be a new instance every time, otherwise the alert message is not retrieved properly.
		$occurrence = new WSAL_Models_Occurrence();

		$user_id = ( ! is_numeric( $user_id ) && null !== $user_id ) ? WSAL_Utilities_UsersUtils::swap_login_for_id( $user_id ) : $user_id;

		// Get alert details.
		$code  = $this->get_alert( $alert_id );
		$code  = $code ? $code->severity : 0;
		$const = (object) array(
			'name'        => 'E_UNKNOWN',
			'value'       => 0,
			'description' => __( 'Unknown error code.', 'wp-security-audit-log' ),
		);
		$const = $this->plugin->constants->get_constant_by( 'value', $code, $const );

		$blog_info = self::get_blog_info( $this->plugin, $site_id );

		// Get the alert message - properly.
		$occurrence->id          = $entry_id;
		$occurrence->site_id     = $site_id;
		$occurrence->alert_id    = $alert_id;
		$occurrence->created_on  = $created_on;
		$occurrence->client_ip   = $ip;
		$occurrence->object      = $object;
		$occurrence->event_type  = $event_type;
		$occurrence->user_id     = $user_id;
		$occurrence->user_agent  = $ua;
		$occurrence->post_id     = $entry->post_id;
		$occurrence->post_type   = $entry->post_type;
		$occurrence->post_status = $entry->post_status;
		$occurrence->set_user_roles( $roles );

		$event_metadata = $occurrence->get_meta_array();
		if ( ! $occurrence->_cached_message ) {
			$occurrence->_cached_message = $occurrence->get_alert()->get_message( $event_metadata, null, $entry_id, $context );
		}

		if ( ! $user_id ) {
			$username = __( 'System', 'wp-security-audit-log' );
			$roles    = '';
		} else {
			$username = WSAL_Utilities_UsersUtils::get_username( $event_metadata );
		}

		// Meta details.
		return array(
			'site_id'    => $site_id,
			'blog_name'  => $blog_info['name'],
			'blog_url'   => $blog_info['url'],
			'alert_id'   => $alert_id,
			'date'       => WSAL_Utilities_DateTimeFormatter::instance()->get_formatted_date_time( $created_on ),
			// We need to keep the timestamp to be able to group entries by dates etc. The "date" field is not suitable
			// as it is already translated, thus difficult to parse and process.
			'timestamp'  => $created_on,
			'code'       => $const->name,
			// Fill variables in message.
			'message'    => $occurrence->get_message( $event_metadata, $context ),
			'user_id'    => $user_id,
			'user_name'  => $username,
			'user_data'  => $user_id ? $this->get_event_user_data( $username ) : false,
			'role'       => $roles,
			'user_ip'    => $ip,
			'object'     => $this->get_event_objects_data( $object ),
			'event_type' => $this->get_event_type_data( $event_type ),
			'user_agent' => $ua,
		);
	}

	/**
	 * Retrieves blog info for given site based on current multisite situation. Optimizes for performance using local
	 * cache.
	 *
	 * @param WpSecurityAuditLog $plugin  WSAL plugin instance.
	 * @param int                $site_id Site ID.
	 *
	 * @return array
	 * @since 4.4.0
	 */
	public static function get_blog_info( $plugin, $site_id ) {
		// Blog details.
		if ( $plugin->is_multisite() ) {
			$blog_info = get_blog_details( $site_id, true );
			$blog_name = esc_html__( 'Unknown Site', 'wp-security-audit-log' );
			$blog_url  = '';

			if ( $blog_info ) {
				$blog_name = esc_html( $blog_info->blogname );
				$blog_url  = esc_attr( $blog_info->siteurl );
			}
		} else {
			$blog_name = get_bloginfo( 'name' );
			$blog_url  = '';

			if ( empty( $blog_name ) ) {
				$blog_name = __( 'Unknown Site', 'wp-security-audit-log' );
			} else {
				$blog_name = esc_html( $blog_name );
				$blog_url  = esc_attr( get_bloginfo( 'url' ) );
			}
		}

		return array(
			'name' => $blog_name,
			'url'  => $blog_url,
		);
	}

	/**
	 * Retrieves local cache of WP Users.
	 *
	 * @return WP_User[] WordPress users.
	 *
	 * @since 4.4.0
	 */
	public function get_wp_users(): array {
		return $this->wp_users;
	}

	/**
	 * Deprecated placeholder function.
	 *
	 * @param integer $type    - Alert type.
	 * @param array   $data    - Alert data.
	 * @param mixed   $delayed - False if delayed, function if not.
	 *
	 * @deprecated 4.4.1 Replaced by function trigger_event.
	 *
	 * @see WSAL_AlertManager::trigger_event()
	 *
	 * @phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	 */
	public function Trigger( $type, $data = array(), $delayed = false ) {
		$this->trigger_event( $type, $data, $delayed );
	}

	/**
	 * Deprecated placeholder function.
	 *
	 * @param integer  $type - Alert type ID.
	 * @param array    $data - Alert data.
	 * @param callable $cond - A future condition callback (receives an object of type WSAL_AlertManager as parameter).
	 *
	 * @deprecated 4.4.1 Replaced by function trigger_event_if.
	 *
	 * @see WSAL_AlertManager::trigger_event_if()
	 *
	 * @phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	 */
	public function TriggerIf( $type, $data, $cond = null ) {
		$this->trigger_event_if( $type, $data, $cond );
	}

	/**
	 * Deprecated placeholder function.
	 *
	 * @param integer $type  - Alert type ID.
	 * @param int     $count - A minimum number of event occurrences.
	 *
	 * @return boolean
	 *
	 * @deprecated 4.4.1 Replaced by function will_trigger.
	 * @see        WSAL_AlertManager::will_trigger()
	 *
	 * @phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	 */
	public function WillTrigger( $type, $count = 1 ) {
		return $this->will_trigger( $type, $count );
	}

	/**
	 * Deprecated placeholder function.
	 *
	 * @param int $type - Alert type ID.
	 * @param int $count - A minimum number of event occurrences.
	 *
	 * @return boolean
	 *
	 * @deprecated 4.4.1 Replaced by function will_or_has_triggered.
	 * @see WSAL_AlertManager::will_or_has_triggered()
	 *
	 * @phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	 */
	public function WillOrHasTriggered( $type, $count = 1 ) {
		return $this->will_or_has_triggered( $type, $count );
	}
}
