<?php
/**
 * Class: MySQL Logger
 *
 * Logger class for wsal.
 *
 * @since      4.6
 * @package    wsal
 * @subpackage loggers
 *
 * @author Stoil Dobrev <sdobreff@gmail.com>
 */

namespace WSAL\Loggers;

use WSAL\Controllers\Connection;
use WSAL\Entities\Occurrences_Entity;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Logger class
 */
if ( ! class_exists( '\WSAL\Loggers\Database_Logger' ) ) {
	/**
	 * This class stores the logs in the database and there is also the function to clean up alerts.
	 *
	 * @package    wsal
	 * @subpackage loggers
	 *
	 * @since 4.6.0
	 */
	class Database_Logger {

		/**
		 * Log an event to the database.
		 *
		 * There is no difference between local and external database handling os of version 4.3.2.
		 *
		 * @param integer $type    - Alert code.
		 * @param array   $data    - Metadata.
		 * @param integer $date    - (Optional) created_on.
		 * @param integer $site_id - (Optional) site_id.
		 */
		public static function log( $type, $data = array(), $date = null, $site_id = null ) {
			// PHP alerts logging was deprecated in version 4.2.0.
			if ( $type < 0010 ) {
				return;
			}

			// We need to remove the timestamp to prevent from saving it as meta.
			unset( $data['Timestamp'] );

			$wsal_db    = Connection::get_connection(); // Get DB connection.
			$connection = true;
			if ( isset( $wsal_db->dbh->errno ) ) {
				$connection = ( 0 === (int) $wsal_db->dbh->errno ); // Database connection error check.
			} elseif ( is_wp_error( $wsal_db->error ) ) {
				$connection = false;
			}

			// Check DB connection.
			if ( $connection ) { // If connected then save the alert in DB.
				$site_id = ! is_null( $site_id ) ? $site_id : ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0 );

				$site_id = \apply_filters( 'wsal_database_site_id_value', $site_id, $type, $data );

				Occurrences_Entity::store_record(
					$data,
					$type,
					self::get_correct_timestamp( $data, $date ),
					! is_null( $site_id ) ? $site_id : ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0 )
				);

			} else { // phpcs:ignore
				// TODO write to a debug log.
			}

			/**
			 * Fires immediately after an alert is logged.
			 *
			 * @since 3.1.2
			 */
			do_action( 'wsal_logged_alert', null, $type, $data, $date, $site_id );
		}


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
		 * @since 4.6.0
		 */
		protected static function get_correct_timestamp( $metadata, $legacy_date ) {

			if ( is_null( $legacy_date ) ) {
				$timestamp = current_time( 'U.u', true );

				$timestamp = \apply_filters( 'wsal_database_timestamp_value', $timestamp, $metadata );

				return array_key_exists( 'Timestamp', $metadata ) ? $metadata['Timestamp'] : $timestamp;
			}

			return floatval( $legacy_date );
		}
	}
}
