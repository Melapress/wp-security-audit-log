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
			array(
				'attachment',          // Attachment CPT.
				'revision',            // Revision CPT.
				'nav_menu_item',       // Nav menu item CPT.
				'customize_changeset', // Customize changeset CPT.
				'custom_css',          // Custom CSS CPT.
				'product',             // WooCommerce Product CPT.
				'shop_order',          // WooCommerce Order CPT.
				'shop_order_refund',   // WooCommerce Order Refund CPT.
				'product_variation',   // WooCommerce Product Variation CPT.
			)
		);
	}

	/**
	 * Method: Schedule log events for External DB
	 * if buffer is enabled.
	 */
	public function schedule_log_events() {
		// Get external buffer option.
		$use_buffer = $this->plugin->GetGlobalOption( 'adapter-use-buffer' );

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
		$use_buffer = $this->plugin->GetGlobalOption( 'adapter-use-buffer' );

		// Log temporary alerts first.
		if ( ! $use_buffer ) {
			$this->log_temp_alerts();
		}

		// Get username.
		$username = wp_get_current_user()->user_login;
		if ( empty( $username ) && ! empty( $data['Username'] ) ) {
			$username = $data['Username'];
		}

		// Get current user roles.
		$roles = $this->plugin->settings->GetCurrentUserRoles();
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
		$roles    = $this->plugin->settings->GetCurrentUserRoles();

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
			list( $type, $code, $catg, $subcatg, $desc, $mesg ) = $info;
			if ( isset( $this->_alerts[ $type ] ) ) {
				add_action( 'admin_notices', array( $this, 'duplicate_event_notice' ) );
				/* Translators: Event ID */
				throw new Exception( sprintf( esc_html__( 'Event %s already registered with WP Security Audit Log.', 'wp-security-audit-log' ), $type ) );
			}
			$this->_alerts[ $type ] = new WSAL_Alert( $type, $code, $catg, $subcatg, $desc, $mesg );
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
	 * Item values is an array of [type, code, description, message] respectively.
	 */
	public function RegisterGroup( $groups ) {
		foreach ( $groups as $name => $group ) {
			foreach ( $group as $subname => $subgroup ) {
				foreach ( $subgroup as $item ) {
					list($type, $code, $desc, $mesg) = $item;
					$this->Register( array( $type, $code, $name, $subname, $desc, $mesg ) );
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
			'<a href="https://www.wpsecurityauditlog.com/contact" target="_blank">' . esc_html__( 'Contact us', 'wp-security-audit-log' ) . '</a>'
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
		if ( 'no' !== $this->plugin->GetGlobalOption( 'disable-visitor-events', 'no' ) ) {
			$disabled_events = array_merge( $disabled_events, $this->get_public_events() );
		}
		return ! in_array( $type, $disabled_events, true );
	}

	/**
	 * Disables a set of alerts by type.
	 *
	 * @param int[] $types Alert type codes to be disabled.
	 */
	public function SetDisabledAlerts( $types ) {
		$this->plugin->settings->SetDisabledAlerts( $types );
	}

	/**
	 * Method: Returns an array of disabled alerts' type code.
	 *
	 * @return int[]
	 */
	public function GetDisabledAlerts() {
		return $this->plugin->settings->GetDisabledAlerts();
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
			$client_ip = $this->plugin->settings->GetMainClientIP();
			if ( ! empty( $client_ip ) ) {
				$event_data['ClientIP'] = $client_ip;
			}
		}
		if ( ! isset( $event_data['OtherIPs'] ) && $this->plugin->settings->IsMainIPFromProxy() ) {
			$other_ips = $this->plugin->settings->GetClientIPs();
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
			$current_user_roles = $this->plugin->settings->GetCurrentUserRoles();
			if ( ! empty( $current_user_roles ) ) {
				$event_data['CurrentUserRoles'] = $current_user_roles;
			}
		}
		// Check if the user management plugin is loaded and adds the SessionID.
		if ( class_exists( 'WSAL_User_Management_Plugin' ) ) {
			if ( function_exists( 'get_current_user_id' ) ) {
				$session_tokens = get_user_meta( get_current_user_id(), 'session_tokens', true );
				if ( ! empty( $session_tokens ) ) {
					end( $session_tokens );
					$event_data['SessionID'] = key( $session_tokens );
				}
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
		if ( 'E_CRITICAL' === $severity->name ) {
			$event_data['Severity'] = 2;
		} elseif ( 'E_WARNING' === $severity->name ) {
			$event_data['Severity'] = 4;
		} elseif ( 'E_NOTICE' === $severity->name ) {
			$event_data['Severity'] = 5;
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
		return $this->plugin->settings->GetExcludedMonitoringUsers();
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
		return $this->plugin->settings->GetExcludedMonitoringRoles();
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
		return $this->plugin->settings->get_excluded_post_types();
	}

	/**
	 * Method: Returns if IP is disabled or not.
	 */
	private function IsDisabledIP() {
		$is_disabled  = false;
		$ip           = $this->plugin->settings->GetMainClientIP();
		$excluded_ips = $this->plugin->settings->GetExcludedMonitoringIP();
		if ( in_array( $ip, $excluded_ips ) ) {
			$is_disabled = true;
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
		$temp_alerts = get_option( 'wsal_temp_alerts', array() );

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
			delete_option( 'wsal_temp_alerts' );
			return true;
		}
	}

	/**
	 * Return alerts for MainWP Extension.
	 *
	 * @param integer $limit – Number of alerts to retrieve.
	 * @return stdClass
	 */
	public function get_mainwp_extension_events( $limit = 100 ) {
		$mwp_events = new stdClass();

		// Check if limit is not empty.
		if ( empty( $limit ) ) {
			return $mwp_events;
		}

		// Initiate query occurrence object.
		$events_query = new WSAL_Models_OccurrenceQuery();
		$events_query->addCondition( 'site_id = %s ', 1 );
		$events_query->addOrderBy( 'created_on', true );
		$events_query->setLimit( $limit );
		$events = $events_query->getAdapter()->Execute( $events_query );

		if ( ! empty( $events ) && is_array( $events ) ) {
			foreach ( $events as $event ) {
				// Get event meta.
				$meta_data             = $event->GetMetaArray();
				$meta_data['UserData'] = $this->get_event_user_data( $event->GetUsername() );

				$mwp_events->events[ $event->id ]             = new stdClass();
				$mwp_events->events[ $event->id ]->id         = $event->id;
				$mwp_events->events[ $event->id ]->alert_id   = $event->alert_id;
				$mwp_events->events[ $event->id ]->created_on = $event->created_on;
				$mwp_events->events[ $event->id ]->meta_data  = $meta_data;
			}
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
		} elseif ( 'Website Visitor' === $username ) {
			$user_data->username = 'Website Visitor';
		} else {
			// Check WP user.
			if ( isset( $this->wp_users[ $username ] ) ) {
				// Retrieve from users cache.
				$user = $this->wp_users[ $username ];
			} else {
				// Get user from WP.
				$user = get_user_by( 'login', $username );

				// Store the object in class member.
				$this->wp_users[ $username ] = $user;
			}

			// Set user data.
			if ( $user && $user instanceof WP_User ) {
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
		$site_id = (int) $this->plugin->settings->get_view_site_id();
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
}
