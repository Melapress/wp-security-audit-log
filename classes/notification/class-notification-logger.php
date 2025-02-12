<?php
/**
 * Class: Notification Logger
 *
 * Logger class for wsal.
 *
 * @since 5.1.1
 * @package    wsal
 * @subpackage loggers
 */

namespace WSAL\Loggers;

use WSAL\Controllers\Alert;
use WSAL\Helpers\WP_Helper;
use WSAL\Views\Notifications;
use WSAL\Helpers\Email_Helper;
use WSAL\Controllers\Constants;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Twilio\Twilio;
use WSAL\Entities\Occurrences_Entity;
use WSAL\WP_Sensors\WP_System_Sensor;
use WSAL\Controllers\Twilio\Twilio_API;
use WSAL\Helpers\DateTime_Formatter_Helper;
use WSAL\Entities\Custom_Notifications_Entity;
use WSAL\Helpers\Formatters\Formatter_Factory;
use WSAL\Extensions\Helpers\Notification_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Logger class
 */
if ( ! class_exists( '\WSAL\Loggers\Notification_Logger' ) ) {
	/**
	 * This class triggers notifications if set.
	 *
	 * @package    wsal
	 * @subpackage loggers
	 *
	 * @since 5.1.1
	 */
	class Notification_Logger {
// phpcs:disable
// phpcs:enable
		/**
		 * Notifies if conditions match.
		 *
		 * @param integer $type    - Alert code.
		 * @param array   $data    - Metadata.
		 * @param integer $date    - (Optional) created_on.
		 * @param integer $site_id - (Optional) site_id.
		 *
		 * @since 5.2.1
		 */
		public static function log( $type, $data = array(), $date = null, $site_id = null ) {
// phpcs:disable
// phpcs:enable
		}
// phpcs:disable
// phpcs:enable
	}
}
