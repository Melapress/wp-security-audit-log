<?php
/**
 * Manager: Alert Manager Class
 *
 * CLass file for alert manager.
 *
 * @since 1.0.0
 * @package Wsal
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
 * @package Wsal
 */
final class WSAL_AlertManager {

	/**
	 * Array of alerts (WSAL_Alert).
	 *
	 * @var array
	 */
	protected $_alerts = array();

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
	 * @var array
	 */
	protected $_loggers = array();

	/**
	 * Instance of WpSecurityAuditLog.
	 *
	 * @var object
	 */
	protected $plugin;

	/**
	 * Contains a list of alerts to trigger.
	 *
	 * @var array
	 */
	protected $_pipeline = array();

	/**
	 * Contains an array of alerts that have been triggered for this request.
	 *
	 * @var int[]
	 */
	protected $_triggered_types = array();

	/**
	 * Log events schedule hook name
	 *
	 * @var string
	 */
	private static $log_events_schedule_hook = 'wsal_log_events_ext_db';

	/**
	 * WP Users
	 *
	 * Store WP Users for caching purposes.
	 *
	 * @var array
	 */
	private $wp_users = array();

	/**
	 * Ignored Custom Post Types.
	 *
	 * @var array
	 */
	public $ignored_cpts = array();

	/**
	 * Create new AlertManager instance.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;
		foreach ( glob( dirname( __FILE__ ) . '/Loggers/*.php' ) as $file ) {
			$this->AddFromFile( $file );
		}

		add_action( 'shutdown', array( $this, '_CommitPipeline' ) );
		add_action( 'wsal_init', array( $this, 'schedule_log_events' ) );

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
		 * @since 3.3.1
		 *
		 * @param array $ignored_cpts - Array of custom post types.
		 */
		$this->ignored_cpts = apply_filters(
			'wsal_ignored_custom_post_types',
			array_unique(
				array_merge(
					$this->get_disabled_post_types(), array(
						'attachment',          // Attachment CPT.
						'revision',            // Revision CPT.
						'nav_menu_item',       // Nav menu item CPT.
						'customize_changeset', // Customize changeset CPT.
						'custom_css',          // Custom CSS CPT.
					)
				)
			)
		);

		$this->date_format     = $this->plugin->settings()->GetDateFormat();
		$this->datetime_format = $this->plugin->settings()->GetDatetimeFormat( false );
		$timezone              = $this->plugin->settings()->GetTimezone();

		if ( '0' === $timezone ) {
			$timezone = 'utc';
		} elseif ( '1' === $timezone ) {
			$timezone = 'wp';
		}

		if ( 'utc' === $timezone ) {
			$this->gmt_offset_sec = date( 'Z' );
		} else {
			$this->gmt_offset_sec = get_option( 'gmt_offset' ) * ( 60 * 60 );
		}
	}

	/**
	 * Method: Schedule log events for External DB
	 * if buffer is enabled.
	 */
	public function schedule_log_events() {
		// Get external buffer option.
		$use_buffer = $this->plugin->GetGlobalBooleanSetting( 'adapter-use-buffer' );

		// If external DB buffer is enabled then set the cron.
		if ( $use_buffer ) {
			// Hook scheduled method.
			add_action( self::$log_events_schedule_hook, array( $this, 'log_temp_alerts' ) );

			// Schedule event if there isn't any already.
			if ( ! wp_next_scheduled( self::$log_events_schedule_hook ) ) {
				wp_schedule_event(
					time(), // Timestamp.
					'tenminutes', // Frequency.
					self::$log_events_schedule_hook // Scheduled event.
				);
			}
		} elseif ( ! $use_buffer && wp_next_scheduled( self::$log_events_schedule_hook ) ) {
			wp_clear_scheduled_hook( self::$log_events_schedule_hook );
		}
	}

	/**
	 * Add new logger from file inside autoloader path.
	 *
	 * @param string $file Path to file.
	 */
	public function AddFromFile( $file ) {
		$file = basename( $file, '.php' );
		$this->AddFromClass( WSAL_CLASS_PREFIX . 'Loggers_' . $file );
	}

	/**
	 * Add new logger given class name.
	 *
	 * @param string $class Class name.
	 */
	public function AddFromClass( $class ) {
		$this->AddInstance( new $class( $this->plugin ) );
	}

	/**
	 * Add newly created logger to list.
	 *
	 * @param WSAL_AbstractLogger $logger The new logger.
	 */
	public function AddInstance( WSAL_AbstractLogger $logger ) {
		$this->_loggers[] = $logger;
	}

	/**
	 * Remove logger by class name.
	 *
	 * @param string $class The class name.
	 */
	public function RemoveByClass( $class ) {
		foreach ( $this->_loggers as $i => $inst ) {
			if ( get_class( $inst ) == $class ) {
				unset( $this->_loggers[ $i ] );
			}
		}
	}

	/**
	 * Trigger an alert.
	 *
	 * @param integer $type    - Alert type.
	 * @param array   $data    - Alert data.
	 * @param mixed   $delayed - False if delayed, function if not.
	 */
	public function Trigger( $type, $data = array(), $delayed = false ) {
		// Get buffer use option.
		$use_buffer = $this->plugin->GetGlobalBooleanSetting( 'adapter-use-buffer' );

		// Log temporary alerts first.
		if ( ! $use_buffer ) {
			$this->log_temp_alerts();
		}

		$username = wp_get_current_user()->user_login;
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

		if ( empty( $username ) && ! empty( $data['Username'] ) ) {
			$username = $data['Username'];
		}

		// Get current user roles.
		if ( isset( $old_user ) && ! false === $old_user ) {
			// looks like this is a switched user so setup original user
			// roles and values for later user.
			$roles = $old_user->roles;
			if ( function_exists( 'is_super_admin' ) && is_super_admin() ) {
				$roles[] = 'superadmin';
			}
			$data['CurrentUserRoles'] = $roles;
		} else {
			// not a switched user so get the current user roles.
			$roles = $this->plugin->settings()->GetCurrentUserRoles();
		}
		if ( empty( $roles ) && ! empty( $data['CurrentUserRoles'] ) ) {
			$roles = $data['CurrentUserRoles'];
		}

		// Check if IP is disabled.
		if ( $this->IsDisabledIP() ) {
			return;
		}

		// Check if PostType index is set in data array.
		if ( isset( $data['PostType'] ) && ! empty( $data['PostType'] ) ) {
			// If the post type is disabled then return.
			if ( $this->is_disabled_post_type( $data['PostType'] ) ) {
				return;
			}
		}

		// If user or user role is enable then go ahead.
		if ( $this->CheckEnableUserRoles( $username, $roles ) ) {
			if ( $delayed ) {
				$this->TriggerIf( $type, $data, null );
			} else {
				$this->_CommitItem( $type, $data, null );
			}
		}
	}

	/**
	 * Check enable user and roles.
	 *
	 * @param string $user - Username.
	 * @param array  $roles - User roles.
	 * @return boolean - True if enable false otherwise.
	 */
	public function CheckEnableUserRoles( $user, $roles ) {
		$is_enable = true;
		if ( '' != $user && $this->IsDisabledUser( $user ) ) {
			$is_enable = false;
		}
		if ( '' != $roles && $this->IsDisabledRole( $roles ) ) {
			$is_enable = false;
		}
		return $is_enable;
	}

	/**
	 * Trigger only if a condition is met at the end of request.
	 *
	 * @param integer  $type - Alert type ID.
	 * @param array    $data - Alert data.
	 * @param callable $cond - A future condition callback (receives an object of type WSAL_AlertManager as parameter).
	 */
	public function TriggerIf( $type, $data, $cond = null ) {
		$username = wp_get_current_user()->user_login;
		$roles    = $this->plugin->settings()->GetCurrentUserRoles();

		if ( $this->CheckEnableUserRoles( $username, $roles ) ) {
			$this->_pipeline[] = array(
				'type' => $type,
				'data' => $data,
				'cond' => $cond,
			);
		}
	}

	/**
	 * Method: Commit an alert now.
	 *
	 * @param int   $type - Alert type.
	 * @param array $data - Data of the alert.
	 * @param array $cond - Condition for the alert.
	 * @param bool  $_retry - Retry.
	 * @internal
	 *
	 * @throws Exception - Error if alert is not registered.
	 */
	protected function _CommitItem( $type, $data, $cond, $_retry = true ) {
		// Double NOT operation here is intentional. Same as ! ( bool ) [ $value ]
		// NOTE: return false on a true condition to compensate.
		if ( ! $cond || ! ! call_user_func( $cond, $this ) ) {
			if ( $this->IsEnabled( $type ) ) {
				if ( isset( $this->_alerts[ $type ] ) ) {
					// Ok, convert alert to a log entry.
					$this->_triggered_types[] = $type;
					$this->Log( $type, $data );
				} elseif ( $_retry ) {
					// This is the last attempt at loading alerts from default file.
					$this->plugin->load_defaults();
					return $this->_CommitItem( $type, $data, $cond, false );
				} else {
					// In general this shouldn't happen, but it could, so we handle it here.
					/* translators: Event ID */
					throw new Exception( sprintf( esc_html__( 'Event with code %d has not be registered.', 'wp-security-audit-log' ), $type ) );
				}
			}
		}
	}

	/**
	 * Method: Runs over triggered alerts in pipeline and passes them to loggers.
	 *
	 * @internal
	 */
	public function _CommitPipeline() {
		foreach ( $this->_pipeline as $item ) {
			$this->_CommitItem( $item['type'], $item['data'], $item['cond'] );
		}
	}

	/**
	 * Method: True if at the end of request an alert of this type will be triggered.
	 *
	 * @param integer $type - Alert type ID.
	 * @return boolean
	 */
	public function WillTrigger( $type ) {
		foreach ( $this->_pipeline as $item ) {
			if ( $item['type'] == $type ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Method: True if an alert has been or will be triggered in this request, false otherwise.
	 *
	 * @param int $type - Alert type ID.
	 * @return boolean
	 */
	public function WillOrHasTriggered( $type ) {
		return in_array( $type, $this->_triggered_types ) || $this->WillTrigger( $type );
	}

	/**
	 * Register an alert type.
	 *
	 * @param array $info - Array of [type, code, category, sub-category, description, message] respectively.
	 * @throws Exception - Error if alert is already registered.
	 */
	public function Register( $info ) {
		if ( func_num_args() === 1 ) {
			// Handle single item.
			list( $type, $code, $catg, $subcatg, $desc, $mesg, $object, $event_type ) = $info;

			if ( isset( $this->_alerts[ $type ] ) ) {
				add_action( 'admin_notices', array( $this, 'duplicate_event_notice' ) );
				/* Translators: Event ID */
				throw new Exception( sprintf( esc_html__( 'Event %s already registered with WP Activity Log.', 'wp-security-audit-log' ), $type ) );
			}

			$this->_alerts[ $type ] = new WSAL_Alert( $type, $code, $catg, $subcatg, $desc, $mesg, $object, $event_type );
		} else {
			// Handle multiple items.
			foreach ( func_get_args() as $arg ) {
				$this->Register( $arg );
			}
		}
	}

	/**
	 * Register a whole group of items.
	 *
	 * @param array $groups - An array with group name as the index and an array of group items as the value.
	 * Item values is an array of [type, code, description, message, object, event type] respectively.
	 */
	public function RegisterGroup( $groups ) {
		foreach ( $groups as $name => $group ) {
			foreach ( $group as $subname => $subgroup ) {
				// Check to see if this ground has any subgroups
				if( $this->GetArrayDepth( $group ) > 1 ) {
					foreach ( $subgroup as $item ) {
						if ( ! isset( $item[4] ) ) {
							$item[4] = ''; // Set default event object.
						}

						if ( ! isset( $item[5] ) ) {
							$item[5] = ''; // Set default event type.
						}

						list( $type, $code, $desc, $mesg, $object, $event_type ) = $item;
						$this->Register( array( $type, $code, $name, $subname, $desc, $mesg, $object, $event_type ) );
					}
				// If no subgroups are found, process them accordingly.
				} else {
					foreach ( $group as $item ) {
						if ( ! isset( $item[4] ) ) {
							$item[4] = ''; // Set default event object.
						}

						if ( ! isset( $item[5] ) ) {
							$item[5] = ''; // Set default event type.
						}

						list( $type, $code, $desc, $mesg, $object, $event_type ) = $item;
						$this->Register( array( $type, $code, $name, $subname, $desc, $mesg, $object, $event_type ) );
					}
				}
			}
		}
	}

	public function GetArrayDepth( $array ) {
		$depth = 0;
		$iteIte = new RecursiveIteratorIterator( new RecursiveArrayIterator( $array ) );

		foreach ($iteIte as $ite) {
		    $d = $iteIte->getDepth();
		    $depth = $d > $depth ? $d : $depth;
		}

		return $depth;
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
	public function IsEnabled( $type ) {
		$disabled_events = $this->GetDisabledAlerts();
		return ! in_array( $type, $disabled_events, true );
	}

	/**
	 * Disables a set of alerts by type.
	 *
	 * @param int[] $types Alert type codes to be disabled.
	 */
	public function SetDisabledAlerts( $types ) {
		$this->plugin->settings()->SetDisabledAlerts( $types );
	}

	/**
	 * Method: Returns an array of disabled alerts' type code.
	 *
	 * @return int[]
	 */
	public function GetDisabledAlerts() {
		return $this->plugin->settings()->GetDisabledAlerts();
	}

	/**
	 * Method: Returns an array of loaded loggers.
	 *
	 * @return WSAL_AbstractLogger[]
	 */
	public function GetLoggers() {
		return $this->_loggers;
	}

	/**
	 * Converts an Alert into a Log entry (by invoking loggers).
	 * You should not call this method directly.
	 *
	 * @param integer $event_id   - Alert type.
	 * @param array   $event_data - Misc alert data.
	 */
	protected function Log( $event_id, $event_data = array() ) {
		if ( ! isset( $event_data['ClientIP'] ) ) {
			$client_ip = $this->plugin->settings()->GetMainClientIP();
			if ( ! empty( $client_ip ) ) {
				$event_data['ClientIP'] = $client_ip;
			}
		}
		if ( ! isset( $event_data['OtherIPs'] ) && $this->plugin->settings()->IsMainIPFromProxy() ) {
			$other_ips = $this->plugin->settings()->GetClientIPs();
			if ( ! empty( $other_ips ) ) {
				$event_data['OtherIPs'] = $other_ips;
			}
		}
		if ( ! isset( $event_data['UserAgent'] ) ) {
			if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
				$event_data['UserAgent'] = $_SERVER['HTTP_USER_AGENT'];
			}
		}
		if ( ! isset( $event_data['Username'] ) && ! isset( $event_data['CurrentUserID'] ) ) {
			if ( function_exists( 'get_current_user_id' ) ) {
				$event_data['CurrentUserID'] = get_current_user_id();
			}
		}
		if ( ! isset( $event_data['CurrentUserRoles'] ) && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$current_user_roles = $this->plugin->settings()->GetCurrentUserRoles();
			if ( ! empty( $current_user_roles ) ) {
				$event_data['CurrentUserRoles'] = $current_user_roles;
			}
		}

		// If the user sessions plugin is loaded try attach the SessionID.
		if ( ! isset( $event_data['SessionID'] ) && class_exists( 'WSAL_UserSessions_Helpers' ) ) {
			// try get the session id generated from logged in cookie.
			$session_id = WSAL_UserSessions_Helpers::get_session_id_from_logged_in_user_cookie();
			// if we have a SessionID then add it to event_data.
			if ( ! empty( $session_id ) ) {
				$event_data['SessionID'] = $session_id;
			}
		}

		// Get event severity.
		$alert_obj  = $this->GetAlert( $event_id );
		$alert_code = $alert_obj ? $alert_obj->code : 0;
		$severity   = $this->plugin->constants->GetConstantBy( 'value', $alert_code );

		/**
		 * Events Severity.
		 *
		 * Add event severity to the meta data of the event.
		 * The lower the number, the higher is the severity.
		 *
		 * @see https://en.wikipedia.org/wiki/Syslog#Severity_level
		 * @since 3.3.1
		 */
		if ( is_object( $severity ) && property_exists( $severity, 'name' ) ) {
			if ( 'E_CRITICAL' === $severity->name ) {
				$event_data['Severity'] = 2;
			} elseif ( 'E_WARNING' === $severity->name ) {
				$event_data['Severity'] = 4;
			} elseif ( 'E_NOTICE' === $severity->name ) {
				$event_data['Severity'] = 5;
			} elseif ( 'WSAL_CRITICAL' === $severity->name ) {
				$event_data['Severity'] = 1;
			} elseif ( 'WSAL_HIGH' === $severity->name ) {
				$event_data['Severity'] = 6;
			} elseif ( 'WSAL_MEDIUM' === $severity->name ) {
				$event_data['Severity'] = 10;
			} elseif ( 'WSAL_LOW' === $severity->name ) {
				$event_data['Severity'] = 15;
			} elseif ( 'WSAL_INFORMATIONAL' === $severity->name ) {
				$event_data['Severity'] = 20;
			}
		}

		/*
		 * In cases where we were not able to figure out a severity already
		 * use a default of 20: info.
		 *
		 * @since 4.3.0
		 */
		if ( ! isset( $event_data['Severity'] ) ) {
			// assuming this is a missclasified item and using info code.
			$event_data['Severity'] = 20;
		}

		// Add event object.
		if ( $alert_obj && ! isset( $event_data['Object'] ) ) {
			$event_data['Object'] = $alert_obj->object;
		}

		// Add event type.
		if ( $alert_obj && ! isset( $event_data['EventType'] ) ) {
			$event_data['EventType'] = $alert_obj->event_type;
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

		foreach ( $this->_loggers as $logger ) {
			$logger->Log( $event_id, $event_data );
		}
	}

	/**
	 * Return alert given alert type.
	 *
	 * @param integer $type    - Alert type.
	 * @param mixed   $default - Returned if alert is not found.
	 * @return WSAL_Alert
	 */
	public function GetAlert( $type, $default = null ) {
		if ( isset( $this->_alerts[ $type ] ) ) {
			return $this->_alerts[ $type ];
		}
		return $default;
	}

	/**
	 * Returns all supported alerts.
	 *
	 * @return WSAL_Alert[]
	 */
	public function GetAlerts() {
		return $this->_alerts;
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
		foreach ( $this->_alerts as $alert ) {
			if ( $category === $alert->catg ) {
				$alerts[ $alert->type ] = $alert;
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
		foreach ( $this->_alerts as $alert ) {
			if ( $sub_category === $alert->subcatg ) {
				$alerts[ $alert->type ] = $alert;
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
	public function GetCategorizedAlerts( $sorted = true ) {
		$result = array();
		foreach ( $this->_alerts as $alert ) {
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
	public function IsDisabledUser( $user ) {
		return ( in_array( $user, $this->GetDisabledUsers() ) ) ? true : false;
	}

	/**
	 * Method: Returns an array of disabled users.
	 *
	 * @return array.
	 */
	public function GetDisabledUsers() {
		return $this->plugin->settings()->GetExcludedMonitoringUsers();
	}

	/**
	 * Returns whether user is enabled or not.
	 *
	 * @param array $roles - User roles.
	 * @return boolean True if disabled, false otherwise.
	 */
	public function IsDisabledRole( $roles ) {
		$is_disabled = false;
		foreach ( $roles as $role ) {
			if ( in_array( $role, $this->GetDisabledRoles() ) ) {
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
	public function GetDisabledRoles() {
		return $this->plugin->settings()->GetExcludedMonitoringRoles();
	}

	/**
	 * Method: Check whether post type is disabled or not.
	 *
	 * @param string $post_type - Post type.
	 * @return bool - True if disabled, False if otherwise.
	 * @since 2.6.7
	 */
	public function is_disabled_post_type( $post_type ) {
		return ( in_array( $post_type, $this->get_disabled_post_types() ) ) ? true : false;
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
	 */
	private function IsDisabledIP() {
		$is_disabled  = false;
		$ip           = $this->plugin->settings()->GetMainClientIP();
		$excluded_ips = $this->plugin->settings()->GetExcludedMonitoringIP();

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
	 * Method: Log temporary stored alerts if DB connection
	 * is back.
	 *
	 * @return boolean
	 */
	public function log_temp_alerts() {
		// Get temporary alerts.
		$temp_alerts = $this->plugin->GetGlobalSetting('temp_alerts', array() );

		if ( empty( $temp_alerts ) ) {
			return;
		}

		// Get DB connector.
		$db_config  = WSAL_Connector_ConnectorFactory::GetConfig(); // Get DB connector configuration.
		$connector  = $this->plugin->getConnector( $db_config ); // Get connector for DB.
		$wsal_db    = $connector->getConnection(); // Get DB connection.
		$connection = true;
		if ( isset( $wsal_db->dbh->errno ) ) {
			$connection = 0 !== (int) $wsal_db->dbh->errno ? false : true; // Database connection error check.
		} elseif ( is_wp_error( $wsal_db->error ) ) {
			$connection = false;
		}

		// Check DB connection.
		if ( $connection ) { // If connected then log temporary alerts in DB.
			// Log each alert.
			foreach ( $temp_alerts as $timestamp => $alert ) {
				$is_migrated = $alert['alert']['is_migrated'];
				$created_on  = $alert['alert']['created_on'];
				$alert_id    = $alert['alert']['alert_id'];
				$site_id     = $alert['alert']['site_id'];

				// Loggers.
				foreach ( $this->_loggers as $logger ) {
					$logger->Log( $alert_id, $alert['alert_data'], $created_on, $site_id, $is_migrated, true );
				}
			}

			// Delete temporary alerts.
			$this->plugin->options_helper->delete_option( 'wsal_temp_alerts' );
			return true;
		}
	}

	/**
	 * Return alerts for MainWP Extension.
	 *
	 * @param integer       $limit      - Number of alerts to retrieve.
	 * @param int|bool      $offset     - Events offset, otherwise false.
	 * @param stdClass|bool $query_args - Events query arguments, otherwise false.
	 * @return stdClass
	 */
	public function get_mainwp_extension_events( $limit = 100, $offset = false, $query_args = false ) {
		$mwp_events = new stdClass();

		// Check if limit is not empty.
		if ( empty( $limit ) ) {
			return $mwp_events;
		}

		// Initiate query occurrence object.
		$events_query = new WSAL_Models_OccurrenceQuery();
		$events_query->addCondition( 'site_id = %s ', 1 ); // Set site id.
		$events_query = $this->filter_query( $events_query, $query_args );

		// Check query arguments.
		if ( false !== $query_args ) {
			if ( isset( $query_args['get_count'] ) && $query_args['get_count'] ) {
				$mwp_events->total_items = $events_query->getAdapter()->Count( $events_query );
			} else {
				$mwp_events->total_items = false;
			}
		}

		// Set order by.
		$events_query->addOrderBy( 'created_on', true );

		// Set the limit.
		$events_query->setLimit( $limit );

		// Set the offset.
		if ( false !== $offset ) {
			$events_query->setOffset( $offset );
		}

		// Execute the query.
		/** @var \WSAL\MainWPExtension\Models\Occurrence[] $events */
		$events = $events_query->getAdapter()->Execute( $events_query );

		if ( ! empty( $events ) && is_array( $events ) ) {
			foreach ( $events as $event ) {
				// Get event meta.
				$meta_data                                    = $event->GetMetaArray();
				$meta_data['UserData']                        = $this->get_event_user_data( $event->GetUsername() );
				$mwp_events->events[ $event->id ]             = new stdClass();
				$mwp_events->events[ $event->id ]->id         = $event->id;
				$mwp_events->events[ $event->id ]->alert_id   = $event->alert_id;
				$mwp_events->events[ $event->id ]->created_on = $event->created_on;
				$mwp_events->events[ $event->id ]->meta_data  = $meta_data;
			}

			$mwp_events->users = $this->wp_users;
		}

		return $mwp_events;
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
	 * @return array|boolean
	 */
	public function get_latest_events( $limit = 1 ) {
		// Occurrence query.
		$occ_query = new WSAL_Models_OccurrenceQuery();

		// Get site id.
		$site_id = (int) $this->plugin->settings()->get_view_site_id();
		if ( $site_id ) {
			$occ_query->addCondition( 'site_id = %d ', $site_id );
		}

		$occ_query->addOrderBy( 'created_on', true ); // Set order for latest events.
		$occ_query->setLimit( $limit ); // Set limit.
		$events = $occ_query->getAdapter()->Execute( $occ_query );

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
		$get_fn = $this->plugin->IsMultisite() ? 'get_site_transient' : 'get_transient';
		$set_fn = $this->plugin->IsMultisite() ? 'set_site_transient' : 'set_transient';

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
		return apply_filters( 'wsal_public_event_ids', array( 1000, 1002, 1003, 1004, 1005, 1007, 2126, 4000, 4012, 6023 ) ); // Public events.
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
			'user'                 => __( 'User', 'wp-security-audit-log' ),
			'system'               => __( 'System', 'wp-security-audit-log' ),
			'plugin'               => __( 'Plugin', 'wp-security-audit-log' ),
			'database'             => __( 'Database', 'wp-security-audit-log' ),
			'post'                 => __( 'Post', 'wp-security-audit-log' ),
			'file'                 => __( 'File', 'wp-security-audit-log' ),
			'tag'                  => __( 'Tag', 'wp-security-audit-log' ),
			'comment'              => __( 'Comment', 'wp-security-audit-log' ),
			'setting'              => __( 'Setting', 'wp-security-audit-log' ),
			'file'                 => __( 'File', 'wp-security-audit-log' ),
			'system-setting'       => __( 'System Setting', 'wp-security-audit-log' ),
			'mainwp-network'       => __( 'MainWP Network', 'wp-security-audit-log' ),
			'mainwp'               => __( 'MainWP', 'wp-security-audit-log' ),
			'yoast-seo'            => __( 'Yoast SEO', 'wp-security-audit-log' ),
			'yoast-seo-metabox'    => __( 'Yoast SEO Meta Box', 'wp-security-audit-log' ),
			'category'             => __( 'Category', 'wp-security-audit-log' ),
			'custom-field'         => __( 'Custom Field', 'wp-security-audit-log' ),
			'widget'               => __( 'Widget', 'wp-security-audit-log' ),
			'menu'                 => __( 'Menu', 'wp-security-audit-log' ),
			'theme'                => __( 'Theme', 'wp-security-audit-log' ),
			'activity-logs'        => __( 'Activity Logs', 'wp-security-audit-log' ),
			'multisite-network'    => __( 'Multisite Network', 'wp-security-audit-log' ),
			'ip-address'           => __( 'IP Address', 'wp-security-audit-log' ),
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
			'login'        => __( 'Login', 'wp-security-audit-log' ),
			'logout'       => __( 'Logout', 'wp-security-audit-log' ),
			'installed'    => __( 'Installed', 'wp-security-audit-log' ),
			'activated'    => __( 'Activated', 'wp-security-audit-log' ),
			'deactivated'  => __( 'Deactivated', 'wp-security-audit-log' ),
			'uninstalled'  => __( 'Uninstalled', 'wp-security-audit-log' ),
			'updated'      => __( 'Updated', 'wp-security-audit-log' ),
			'created'      => __( 'Created', 'wp-security-audit-log' ),
			'modified'     => __( 'Modified', 'wp-security-audit-log' ),
			'deleted'      => __( 'Deleted', 'wp-security-audit-log' ),
			'published'    => __( 'Published', 'wp-security-audit-log' ),
			'approved'     => __( 'Approved', 'wp-security-audit-log' ),
			'unapproved'   => __( 'Unapproved', 'wp-security-audit-log' ),
			'enabled'      => __( 'Enabled', 'wp-security-audit-log' ),
			'disabled'     => __( 'Disabled', 'wp-security-audit-log' ),
			'added'        => __( 'Added', 'wp-security-audit-log' ),
			'failed-login' => __( 'Failed Login', 'wp-security-audit-log' ),
			'blocked'      => __( 'Blocked', 'wp-security-audit-log' ),
			'uploaded'     => __( 'Uploaded', 'wp-security-audit-log' ),
			'restored'     => __( 'Restored', 'wp-security-audit-log' ),
			'opened'       => __( 'Opened', 'wp-security-audit-log' ),
			'viewed'       => __( 'Viewed', 'wp-security-audit-log' ),
			'started'      => __( 'Started', 'wp-security-audit-log' ),
			'stopped'      => __( 'Stopped', 'wp-security-audit-log' ),
			'removed'      => __( 'Removed', 'wp-security-audit-log' ),
			'unblocked'    => __( 'Unblocked', 'wp-security-audit-log' ),
			'renamed'      => __( 'Renamed', 'wp-security-audit-log' ),
			'duplicated'   => __( 'Duplicated', 'wp-security-audit-log' ),
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
		// Try get string from the companion data method.
		return get_event_type_data( $event_type );
	}

	/**
	 * Filter query for MWPAL.
	 *
	 * @param WSAL_Models_OccurrenceQuery $query      - Events query.
	 * @param array                       $query_args - Query args.
	 * @return WSAL_Models_OccurrenceQuery
	 */
	private function filter_query( $query, $query_args ) {
		if ( isset( $query_args['search_term'] ) && $query_args['search_term'] ) {
			$query->addSearchCondition( $query_args['search_term'] );
		}

		if ( ! empty( $query_args['search_filters'] ) ) {
			// Get DB connection array.
			$connection = WpSecurityAuditLog::GetInstance()->getConnector()->getAdapter( 'Occurrence' )->get_connection();
			$connection->set_charset( $connection->dbh, 'utf8mb4', 'utf8mb4_general_ci' );

			// Tables.
			$meta       = new WSAL_Adapters_MySQL_Meta( $connection );
			$table_meta = $meta->GetTable(); // Metadata.
			$occurrence = new WSAL_Adapters_MySQL_Occurrence( $connection );
			$table_occ  = $occurrence->GetTable(); // Occurrences.

			foreach ( $query_args['search_filters'] as $prefix => $value ) {
				if ( 'event' === $prefix ) {
					$query->addORCondition( array( 'alert_id = %s' => $value ) );
				} elseif ( in_array( $prefix, array( 'from', 'to', 'on' ), true ) ) {
					$date_format = WpSecurityAuditLog::GetInstance()->settings->GetDateFormat();
					$date        = DateTime::createFromFormat( $date_format, $value[0] );
					$date->setTime( 0, 0 ); // Reset time to 00:00:00.
					$date_string = $date->format( 'U' );

					if ( 'from' === $prefix ) {
						$query->addCondition( 'created_on >= %s', $date_string );
					} elseif ( 'to' === $prefix ) {
						$query->addCondition( 'created_on <= %s', strtotime( '+1 day -1 minute', $date_string ) );
					} elseif ( 'on' === $prefix ) {
						$query->addCondition( 'created_on >= %s', strtotime( '-1 day +1 day +1 second', $date_string ) );
						$query->addCondition( 'created_on <= %s', strtotime( '+1 day -1 second', $date_string ) );
					}
				} elseif ( in_array( $prefix, array( 'username', 'firstname', 'lastname' ), true ) ) {
					// User ids array.
					$user_ids = array();

					if ( 'username' === $prefix ) {
						foreach ( $value as $username ) {
							$user = get_user_by( 'login', $username );

							if ( ! $user ) {
								$user = get_user_by( 'slug', $username );
							}

							if ( $user ) {
								$user_ids[] = $user->ID;
							}
						}
					} elseif ( 'firstname' === $prefix || 'lastname' === $prefix ) {
						$users    = array();
						$meta_key = 'firstname' === $prefix ? 'first_name' : ( 'lastname' === $prefix ? 'last_name' : false );

						foreach ( $value as $name ) {
							$users_array = get_users(
								array(
									'meta_key'     => $meta_key,
									'meta_value'   => $name,
									'fields'       => array( 'ID', 'user_login' ),
									'meta_compare' => 'LIKE',
								)
							);

							foreach ( $users_array as $user ) {
								$users[] = $user;
							}
						}

						$usernames = array();

						if ( ! empty( $users ) ) {
							foreach ( $users as $user ) {
								$usernames[] = $user->user_login;
								$user_ids[]  = $user->ID;
							}
						}

						$value = $usernames;
					}

					$sql = "$table_occ.id IN ( SELECT occurrence_id FROM $table_meta as meta WHERE ";

					if ( ! empty( $user_ids ) ) {
						$last_userid = end( $user_ids );
						$sql        .= "( meta.name='CurrentUserID' AND ( ";

						foreach ( $user_ids as $user_id ) {
							if ( $last_userid === $user_id ) {
								$sql .= "meta.value='$user_id'";
							} else {
								$sql .= "meta.value='$user_id' OR ";
							}
						}

						$sql .= ' ) )';
						$sql .= ' OR ';
					}

					if ( ! empty( $value ) ) {
						$last_username = end( $value );
						$sql          .= "( meta.name='Username' AND ( ";

						foreach ( $value as $username ) {
							if ( $last_username === $username ) {
								$sql .= "meta.value='%s'";
							} else {
								$sql .= "meta.value='$username' OR ";
							}
						}

						$sql .= ' ) )';
					}

					$sql       .= ' )';
					$user_count = count( $value );

					if ( $user_count ) {
						$query->addORCondition( array( $sql => $value[ $user_count - 1 ] ) );
					} else {
						$query->addORCondition( array( $sql => '' ) );
					}
				} elseif ( 'userrole' === $prefix ) {
					// User role search condition.
					$sql   = "$table_occ.id IN ( SELECT occurrence_id FROM $table_meta as meta WHERE meta.name='CurrentUserRoles' AND replace(replace(replace(meta.value, ']', ''), '[', ''), '\\'', '') REGEXP %s )";
					$value = implode( '|', $value );
					$query->addORCondition( array( $sql => $value ) );
				} elseif ( in_array( $prefix, array( 'posttype', 'poststatus', 'postid', 'postname' ), true ) ) {
					$post_meta = '';

					if ( 'posttype' === $prefix ) {
						$post_meta = 'PostType';
					} elseif ( 'poststatus' === $prefix ) {
						$post_meta = 'PostStatus';
					} elseif ( 'postid' === $prefix ) {
						$post_meta = 'PostID';
					} elseif ( 'postname' === $prefix ) {
						$post_meta = 'PostTitle';
					}

					// Post meta search condition.
					$sql = "$table_occ.id IN ( SELECT occurrence_id FROM $table_meta as meta WHERE meta.name='$post_meta' AND ( ";
					if ( 'postname' === $prefix ) {
						$value = array_map( array( $this, 'add_string_wildcards' ), $value );
					}

					// Get the last value.
					$last_value = end( $value );

					foreach ( $value as $post_meta ) {
						if ( $last_value === $post_meta ) {
							continue;
						} elseif ( 'postname' === $prefix ) {
							$sql .= "( (meta.value LIKE '$post_meta') > 0 ) OR ";
						} else {
							$sql .= "meta.value='$post_meta' OR ";
						}
					}

					// Add placeholder for the last value.
					if ( 'postname' === $prefix ) {
						$sql .= "( (meta.value LIKE '%s') > 0 ) ) )";
					} else {
						$sql .= "meta.value='%s' ) )";
					}

					$query->addORCondition( array( $sql => $last_value ) );
				} elseif ( 'ip' === $prefix ) {
					// IP search condition.
					$sql   = "$table_occ.id IN ( SELECT occurrence_id FROM $table_meta as meta WHERE meta.name='ClientIP' AND ( ";
					$count = count( $value );

					foreach ( $value as $ip ) {
						if ( $value[ $count - 1 ] === $ip ) {
							$sql .= "meta.value='%s'";
						} else {
							$sql .= "meta.value='$ip' OR ";
						}
					}

					$sql .= ' ) )';
					$query->addORCondition( array( $sql => $value[ $count - 1 ] ) );
				}
			}
		}

		return $query;
	}

	/**
	 * Modify post name values to include MySQL wildcards.
	 *
	 * @param string $search_value – Searched post name.
	 * @return string
	 */
	private function add_string_wildcards( $search_value ) {
		return '%' . $search_value . '%';
	}

	/**
	 * Return sub-categorized events of WSAL.
	 *
	 * @return array
	 */
	public function get_sub_categorized_events() {
		$cg_alerts = $this->GetCategorizedAlerts();
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
	 * Generate report matching the filter passed.
	 *
	 * @param array $filters     - Filters.
	 * @param mixed $report_type - Type of report.
	 * @return array
	 */
	public function get_mainwp_extension_report( array $filters, $report_type ) {
		// Check report type.
		if ( ! $report_type ) {
			$report       = new stdClass();
			$report->data = array();

			do {
				$response = $this->generate_report( $filters );

				if ( isset( $response['data'] ) ) {
					$report->data = array_merge( $report->data, $response['data'] );
				}

				// Set the filters next date.
				$filters['nextDate'] = ( isset( $response['lastDate'] ) && $response['lastDate'] ) ? $response['lastDate'] : 0;
			} while ( $filters['nextDate'] );
		} elseif ( 'statistics_unique_ips' === $report_type ) {
			$report       = new stdClass();
			$report->data = $this->generate_statistics_unique_ips( $filters );
		}

		return $report;
	}

	/**
	 * Generate report for MainWP extension.
	 *
	 * @param array $filters - Filters.
	 * @return array
	 */
	private function generate_report( $filters ) {
		// Filters.
		$sites         = null;
		$users         = empty( $filters['users'] ) ? array() : $filters['users'];
		$roles         = empty( $filters['roles'] ) ? null : $filters['roles'];
		$ip_addresses  = empty( $filters['ip-addresses'] ) ? null : $filters['ip-addresses'];
		$alert_groups  = empty( $filters['alert-codes']['groups'] ) ? null : $filters['alert-codes']['groups'];
		$alert_codes   = empty( $filters['alert-codes']['alerts'] ) ? null : $filters['alert-codes']['alerts'];
		$date_start    = empty( $filters['date-range']['start'] ) ? null : $filters['date-range']['start'];
		$date_end      = empty( $filters['date-range']['end'] ) ? null : $filters['date-range']['end'];
		$report_format = empty( $filters['report-format'] ) ? 'html' : 'csv';

		$next_date = empty( $filters['nextDate'] ) ? null : $filters['nextDate'];
		$limit     = empty( $filters['limit'] ) ? 0 : $filters['limit'];

		if ( empty( $alert_groups ) && empty( $alert_codes ) ) {
			return false;
		}

		if ( ! in_array( $report_format, array( 'csv', 'html' ), true ) ) {
			return false;
		}

		// Check alert codes and post types.
		$codes = $this->get_codes_by_groups( $alert_groups, $alert_codes );

		if ( ! $codes ) {
			return false;
		}

		/**
		 * -- @userId: COMMA-SEPARATED-LIST WordPress user id
		 * -- @siteId: COMMA-SEPARATED-LIST WordPress site id
		 * -- @postType: COMMA-SEPARATED-LIST WordPress post types
		 * -- @postStatus: COMMA-SEPARATED-LIST WordPress post statuses
		 * -- @roleName: REGEXP (must be quoted from PHP)
		 * -- @alertCode: COMMA-SEPARATED-LIST of numeric alert codes
		 * -- @startTimestamp: UNIX_TIMESTAMP
		 * -- @endTimestamp: UNIX_TIMESTAMP
		 *
		 * Usage:
		 * --------------------------
		 * set @siteId = null; -- '1,2,3,4....';
		 * set @userId = null;
		 * set @postType = null; -- 'post,page';
		 * set @postStatus = null; -- 'publish,draft';
		 * set @roleName = null; -- '(administrator)|(editor)';
		 * set @alertCode = null; -- '1000,1002';
		 * set @startTimestamp = null;
		 * set @endTimestamp = null;
		 */
		$site_ids = $sites ? "'" . implode( ',', $sites ) . "'" : 'null';
		$user_ids = $this->get_user_ids( $users );

		$role_names      = 'null';
		$start_timestamp = 'null';
		$end_timestamp   = 'null';

		if ( $roles ) {
			$role_names = array();

			foreach ( $roles as $role ) {
				array_push( $role_names, esc_sql( '(' . preg_quote( $role ) . ')' ) );
			}

			$role_names = "'" . implode( '|', $role_names ) . "'";
		}

		$ip_address  = $ip_addresses ? "'" . implode( ',', $ip_addresses ) . "'" : 'null';
		$alert_codes = ! empty( $codes ) ? "'" . implode( ',', $codes ) . "'" : 'null';

		if ( $date_start ) {
			$dt              = new \DateTime();
			$df              = $dt->createFromFormat( $this->date_format . ' H:i:s', $date_start . ' 00:00:00' );
			$start_timestamp = $df->format( 'U' );
		}

		if ( $date_end ) {
			$dt            = new \DateTime();
			$df            = $dt->createFromFormat( $this->date_format . ' H:i:s', $date_end . ' 23:59:59' );
			$end_timestamp = $df->format( 'U' );
		}

		$last_date = null;

		if ( isset( $filters['unique-ip'] ) && $filters['unique-ip'] ) {
			$results = $this->plugin->getConnector()->getAdapter( 'Occurrence' )->GetReportGrouped( $site_ids, $start_timestamp, $end_timestamp, $user_ids, $role_names, $ip_address );
		} else {
			$results = $this->plugin->getConnector()->getAdapter( 'Occurrence' )->GetReporting( $site_ids, $user_ids, $role_names, $alert_codes, $start_timestamp, $end_timestamp, $next_date, $limit, 'null', 'null' );
		}

		if ( ! empty( $results['lastDate'] ) ) {
			$last_date = $results['lastDate'];
			unset( $results['lastDate'] );
		}

		if ( empty( $results ) ) {
			return false;
		}

		$data             = array();
		$data_and_filters = array();

		if ( ! empty( $filters['unique-ip'] ) ) {
			$data = array_values( $results );
		} else {
			// Get alert details.
			foreach ( $results as $entry ) {
				$ip    = esc_html( $entry->ip );
				$ua    = esc_html( $entry->ua );
				$roles = maybe_unserialize( $entry->roles );

				if ( is_array( $roles ) ) {
					$roles = implode( ', ', $roles );
				} else {
					$roles = '';
				}

				if ( 9999 === (int) $entry->alert_id ) {
					continue;
				}

				$t = $this->get_alert_details( $entry->id, $entry->alert_id, $entry->site_id, $entry->created_on, $entry->user_id, $roles, $ip, $ua );

				if ( ! empty( $ip_addresses ) && in_array( $entry->ip, $ip_addresses, true ) ) {
					array_push( $data, $t );
				} else {
					array_push( $data, $t );
				}
			}
		}

		if ( empty( $data ) ) {
			return false;
		}

		$data_and_filters['data']     = $data;
		$data_and_filters['filters']  = $filters;
		$data_and_filters['lastDate'] = $last_date;

		return $data_and_filters;
	}

	/**
	 * Create statistics unique IPs report.
	 *
	 * @param array $filters - Filters.
	 * @return array
	 */
	private function generate_statistics_unique_ips( $filters ) {
		$date_start = ! empty( $filters['date-range']['start'] ) ? $filters['date-range']['start'] : null;
		$date_end   = ! empty( $filters['date-range']['end'] ) ? $filters['date-range']['end'] : null;
		$sites      = null;

		$user_id    = ! empty( $filters['users'] ) ? $filters['users'] : 'null';
		$role_name  = ! empty( $filters['roles'] ) ? $filters['roles'] : 'null';
		$ip_address = ! empty( $filters['ip-addresses'] ) ? $filters['ip-addresses'] : 'null';

		$alert_groups = ! empty( $filters['alert-codes']['groups'] ) ? $filters['alert-codes']['groups'] : null;
		$alert_codes  = ! empty( $filters['alert-codes']['alerts'] ) ? $filters['alert-codes']['alerts'] : null;

		// Alert Groups.
		$_codes = $this->get_codes_by_groups( $alert_groups, $alert_codes );

		if ( ! $_codes ) {
			return false;
		}

		$site_id         = $sites ? "'" . implode( ',', $sites ) . "'" : 'null';
		$start_timestamp = 'null';
		$end_timestamp   = 'null';
		$alert_code      = "'" . implode( ',', $_codes ) . "'";

		if ( $date_start ) {
			$dt              = new \DateTime();
			$df              = $dt->createFromFormat( $this->date_format . ' H:i:s', $date_start . ' 00:00:00' );
			$start_timestamp = $df->format( 'U' );
		}

		if ( $date_end ) {
			$dt            = new \DateTime();
			$df            = $dt->createFromFormat( $this->date_format . ' H:i:s', $date_end . ' 23:59:59' );
			$end_timestamp = $df->format( 'U' );
		}

		$results = $this->plugin->getConnector()->getAdapter( 'Occurrence' )->GetReportGrouped( $site_id, $start_timestamp, $end_timestamp, $user_id, $role_name, $ip_address, $alert_code );
		return array_values( $results );
	}

	/**
	 * Get user ids for reports.
	 *
	 * @param array $usernames - Array of usernames.
	 * @return string
	 */
	private function get_user_ids( $usernames ) {
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
		$result = $wpdb->get_results( $sql, ARRAY_A );

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
	private function get_codes_by_groups( $event_groups, $event_codes, $show_error = true ) {
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
							$aid = $alert->type;

							if ( 1 === strlen( $aid ) ) {
								$aid = $this->pad_key( $aid );
							}

							array_push( $_codes, $aid );
						}
					} else {
						foreach ( $cat_alerts[ $category ] as $alert ) {
							array_push( $_codes, $alert->type );
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
	 * @param int          $entry_id   - Entry ID.
	 * @param int          $alert_id   - Alert ID.
	 * @param int          $site_id    - Site ID.
	 * @param string       $created_on - Alert generation time.
	 * @param int          $user_id    - User id.
	 * @param string|array $roles      - User roles.
	 * @param string       $ip         - IP address of the user.
	 * @param string       $ua         - User agent.
	 * @return array|false details
	 */
	private function get_alert_details( $entry_id, $alert_id, $site_id, $created_on, $user_id = null, $roles = null, $ip = '', $ua = '' ) {
		// Must be a new instance every time, otherwise the alert message is not retrieved properly.
		$occurrence = new WSAL_Models_Occurrence();

		// Get alert details.
		$code  = $this->GetAlert( $alert_id );
		$code  = $code ? $code->code : 0;
		$const = (object) array(
			'name'        => 'E_UNKNOWN',
			'value'       => 0,
			'description' => __( 'Unknown error code.', 'wp-security-audit-log' ),
		);
		$const = $this->plugin->constants->GetConstantBy( 'value', $code, $const );

		// Blog details.
		if ( $this->plugin->IsMultisite() ) {
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

		// Get the alert message - properly.
		$occurrence->id         = $entry_id;
		$occurrence->site_id    = $site_id;
		$occurrence->alert_id   = $alert_id;
		$occurrence->created_on = $created_on;

		if ( $occurrence->is_migrated ) {
			$occurrence->_cachedmessage = $occurrence->GetMetaValue( 'MigratedMesg', false );
		}

		if ( ! $occurrence->is_migrated || ! $occurrence->_cachedmessage ) {
			$occurrence->_cachedmessage = $occurrence->GetAlert()->mesg;
		}

		if ( ! $user_id ) {
			$username = __( 'System', 'wp-security-audit-log' );
			$roles    = '';
		} else {
			$username = $occurrence->GetUsername();
		}

		// Meta details.
		return array(
			'site_id'    => $site_id,
			'blog_name'  => $blog_name,
			'blog_url'   => $blog_url,
			'alert_id'   => $alert_id,
			'date'       => str_replace(
				'$$$',
				substr( number_format( fmod( (int) $created_on + $this->gmt_offset_sec, 1 ), 3 ), 2 ),
				date( $this->datetime_format, (int) $created_on + $this->gmt_offset_sec )
			),
			'code'       => $const->name,
			'message'    => $occurrence->GetAlert()->GetMessage( $occurrence->GetMetaArray(), array( $this->plugin->settings, 'meta_formatter' ), $occurrence->_cachedmessage ),
			'user_name'  => $username,
			'user_data'  => $user_id ? $this->get_event_user_data( $username ) : false,
			'role'       => $roles,
			'user_ip'    => $ip,
			'user_agent' => $ua,
		);
	}
}
