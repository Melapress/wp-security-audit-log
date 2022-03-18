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
		return $this->plugin->is_multisite();
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
}
