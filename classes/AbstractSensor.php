<?php
/**
 * Abstract Class: Sensor
 *
 * Abstract sensor class file.
 *
 * @since      1.0.0
 * @package    wsal
 * @subpackage sensors
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class used in all the sensors.
 *
 * @see        Sensors/*.php
 * @package    wsal
 * @subpackage sensors
 */
abstract class WSAL_AbstractSensor {

	/**
	 * Instance of WpSecurityAuditLog.
	 *
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	/**
	 * Amount of seconds to check back for the given alert occurrence.
	 *
	 * @var integer
	 *
	 * @since      4.4.2.1
	 */
	protected static $seconds_to_check_back = 5;

	/**
	 * Holds a cached value if the checked alerts which were recently fired.
	 *
	 * @var array
	 */
	private static $cached_alert_checks = array();

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Whether we are running on multisite or not.
	 *
	 * @return boolean
	 */
	protected function is_multisite() {
		return WpSecurityAuditLog::is_multisite();
	}

	/**
	 * Method: Hook events related to sensor.
	 */
	public function hook_events() {
		// We call the deprecated function for backwards compatibility.
		$this->HookEvents();
	}

	/**
	 * Method: Log the message for sensor.
	 *
	 * @param int    $type    - Type of alert.
	 * @param string $message - Alert message.
	 * @param mixed  $args    - Message arguments.
	 */
	protected function log( $type, $message, $args ) {
		$this->plugin->alerts->trigger_event(
			$type,
			array(
				'Message' => $message,
				'Context' => $args,
				'Trace'   => debug_backtrace(), // phpcs:ignore
			)
		);
	}

	/**
	 * Method: Log error message for sensor.
	 *
	 * @param string $message - Alert message.
	 * @param mixed  $args    - Message arguments.
	 */
	protected function log_error( $message, $args ) {
		$this->log( 0001, $message, $args );
	}

	/**
	 * Method: Log warning message for sensor.
	 *
	 * @param string $message - Alert message.
	 * @param mixed  $args    - Message arguments.
	 */
	protected function log_warn( $message, $args ) {
		$this->log( 0002, $message, $args );
	}

	/**
	 * Method: Log info message for sensor.
	 *
	 * @param string $message - Alert message.
	 * @param mixed  $args    - Message arguments.
	 */
	protected function log_info( $message, $args ) {
		$this->log( 0003, $message, $args );
	}

	/**
	 * Deprecated placeholder function.
	 *
	 * @see        WSAL_AbstractSensor::hook_events()
	 *
	 * @deprecated 4.4.1 Replaced by function hook_events.
	 */
	public function HookEvents() {}

	/**
	 * Check if the alert was triggered recently.
	 *
	 * Checks last 5 events if they occurred less than self::$seconds_to_check_back seconds ago.
	 *
	 * @param integer|array $alert_id - Alert code.
	 * @return boolean
	 */
	protected function was_triggered_recently( $alert_id ) {
		// if we have already checked this don't check again.
		if ( isset( self::$cached_alert_checks ) && array_key_exists( $alert_id, self::$cached_alert_checks ) && self::$cached_alert_checks[ $alert_id ] ) {
			return true;
		}
		$query = new WSAL_Models_OccurrenceQuery();
		$query->add_order_by( 'created_on', true );
		$query->set_limit( 5 );
		$last_occurrences = $query->get_adapter()->execute_query( $query );
		$known_to_trigger = false;
		foreach ( $last_occurrences as $last_occurrence ) {
			if ( $known_to_trigger ) {
				break;
			}
			if ( ! empty( $last_occurrence ) && ( $last_occurrence->created_on + self::$seconds_to_check_back ) > time() ) {
				if ( ! is_array( $alert_id ) && $last_occurrence->alert_id === $alert_id ) {
					$known_to_trigger = true;
				} elseif ( is_array( $alert_id ) && in_array( $last_occurrence[0]->alert_id, $alert_id, true ) ) {
					$known_to_trigger = true;
				}
			}
		}
		// once we know the answer to this don't check again to avoid queries.
		self::$cached_alert_checks[ $alert_id ] = $known_to_trigger;
		return $known_to_trigger;
	}
}
