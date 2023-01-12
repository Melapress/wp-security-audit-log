<?php
/**
 * Abstract logger class.
 *
 * @package    wsal
 * @subpackage loggers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class used in the Logger.
 *
 * @see Loggers/Database.php
 * @package wsal
 */
abstract class WSAL_AbstractLogger {

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
	 *
	 * @since  1.0.0
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Log alert abstract.
	 *
	 * @param integer $type    - Alert code.
	 * @param array   $data    - Metadata.
	 * @param integer $date    (Optional) - Created on.
	 * @param integer $site_id (Optional) - Site id.
	 */
	abstract public function log( $type, $data = array(), $date = null, $site_id = null );

	/**
	 * Determines what is the correct timestamp for the event.
	 *
	 * It uses the timestamp from metadata if available. This is needed because we introduced a possible delay by using
	 * action scheduler in 4.3.0. The $legacy_date attribute is only used for migration of legacy data. This should be
	 * removed in future releases.
	 *
	 * @param array $metadata    Event metadata.
	 * @param int   $legacy_date Legacy date only used when migrating old db event format to the new one.
	 *
	 * @return float GMT timestamp including microseconds.
	 * @since 4.3.0
	 */
	protected function get_correct_timestamp( $metadata, $legacy_date ) {

		if ( is_null( $legacy_date ) ) {
			return array_key_exists( 'Timestamp', $metadata ) ? $metadata['Timestamp'] : current_time( 'U.u', true );
		}

		return floatval( $legacy_date );
	}
}
